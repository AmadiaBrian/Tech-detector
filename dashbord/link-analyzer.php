<?php
// Simple Link Validator
// Save as validate-link.php

// Set default timezone
date_default_timezone_set('UTC');

// Function to parse WHOIS data for common TLDs
function parseWhoisData($whoisText, $domain) {
    $result = [
        'registrar' => 'N/A',
        'creation_date' => 'N/A',
        'expiry_date' => 'N/A',
        'updated_date' => 'N/A',
        'name_servers' => []
    ];

    // Common patterns for different WHOIS formats
    $patterns = [
        // Common registrar patterns
        'registrar' => [
            '/Registrar:\s*(.+)/i',
            '/Registrar Name:\s*(.+)/i',
            '/Registrar:\s*\n\s*Organization:\s*(.+)/i',
            '/Registrar:\s*\n\s*Name:\s*(.+)/i',
        ],
        // Common creation date patterns
        'creation_date' => [
            '/Creation Date:\s*(.+)/i',
            '/Created On:\s*(.+)/i',
            '/Domain Create Date:\s*(.+)/i',
            '/Registered on:\s*(.+)/i',
            '/Registration Time:\s*(.+)/i',
        ],
        // Common expiry date patterns
        'expiry_date' => [
            '/Expiration Date:\s*(.+)/i',
            '/Expiry Date:\s*(.+)/i',
            '/Registry Expiry Date:\s*(.+)/i',
            '/Expires On:\s*(.+)/i',
            '/Expiration Time:\s*(.+)/i',
        ],
        // Common name server patterns
        'name_servers' => [
            '/Name Server:\s*(\S+)/i',
            '/nserver:\s*(\S+)/i',
            '/Name Servers?:\s*((?:\s*\S+\s*,\s*)*\S+)/i',
        ]
    ];

    // Extract information using patterns
    foreach ($patterns as $key => $regexes) {
        foreach ($regexes as $regex) {
            if (preg_match_all($regex, $whoisText, $matches)) {
                if ($key === 'name_servers') {
                    foreach ($matches[1] as $ns) {
                        // Clean up and add name servers
                        $ns = trim(strtolower($ns));
                        if (!empty($ns) && !in_array($ns, $result['name_servers'])) {
                            $result['name_servers'][] = $ns;
                        }
                    }
                } else {
                    $value = trim(end($matches[1]));
                    if (!empty($value) && $result[$key] === 'N/A') {
                        // Try to format dates
                        if (strpos($key, '_date') !== false) {
                            $time = strtotime($value);
                            $result[$key] = $time ? date('Y-m-d', $time) : $value;
                        } else {
                            $result[$key] = $value;
                        }
                    }
                }
            }
        }
    }

    return $result;
}

// Function to get WHOIS information
function getWhoisInfo($domain) {
    $whois = [
        'domain' => $domain,
        'registrar' => 'N/A',
        'creation_date' => 'N/A',
        'expiry_date' => 'N/A',
        'updated_date' => 'N/A',
        'name_servers' => [],
        'ip' => '',
        'country' => 'N/A',
        'isp' => 'N/A',
        'raw' => ''
    ];

    try {
        // Get IP address first
        $ip = gethostbyname($domain);
        if ($ip !== $domain) {
            $whois['ip'] = $ip;
            
            // Get IP geolocation information
            $ipInfo = @json_decode(file_get_contents("http://ip-api.com/json/{$ip}"), true);
            if ($ipInfo && $ipInfo['status'] === 'success') {
                $whois['country'] = $ipInfo['country'] ?? 'N/A';
                $whois['isp'] = $ipInfo['isp'] ?? 'N/A';
                $whois['org'] = $ipInfo['org'] ?? 'N/A';
                $whois['city'] = $ipInfo['city'] ?? 'N/A';
                $whois['region'] = $ipInfo['regionName'] ?? 'N/A';
            }
        }
        
        // Function to try different WHOIS servers
        $tryWhoisLookup = function($domain) use (&$whois) {
            // Try system whois command first
            if (function_exists('shell_exec') && is_callable('shell_exec')) {
                $whoisText = @shell_exec("whois {$domain}");
                if ($whoisText) {
                    $whois['raw'] = $whoisText;
                    $parsed = parseWhoisData($whoisText, $domain);
                    
                    // Update whois info with parsed data if not already set
                    foreach (['registrar', 'creation_date', 'expiry_date', 'updated_date'] as $key) {
                        if (isset($parsed[$key]) && $parsed[$key] !== 'N/A' && $whois[$key] === 'N/A') {
                            $whois[$key] = $parsed[$key];
                        }
                    }
                    
                    // Merge name servers
                    if (!empty($parsed['name_servers'])) {
                        $whois['name_servers'] = array_unique(array_merge(
                            $whois['name_servers'],
                            $parsed['name_servers']
                        ));
                    }
                    
                    return true;
                }
            }
            return false;
        };

        // Try the WHOIS API first
        $apiSuccess = false;
        $apiUrl = "https://www.whoisxmlapi.com/whoisserver/WhoisService?apiKey=at_8j4Ff1oH5n2VvT1qy0x8n7v5d8n7v5d8&domainName=" . urlencode($domain) . "&outputFormat=JSON";
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = @curl_exec($ch);
        
        if ($response) {
            $data = json_decode($response, true);
            
            if (isset($data['WhoisRecord'])) {
                $record = $data['WhoisRecord'];
                
                // Get registrar information
                if (isset($record['registrarName'])) {
                    $whois['registrar'] = $record['registrarName'];
                } elseif (isset($record['registryData']['registrar'])) {
                    $whois['registrar'] = $record['registryData']['registrar'];
                }
                
                // Get creation date
                if (isset($record['createdDate'])) {
                    $whois['creation_date'] = date('Y-m-d', strtotime($record['createdDate']));
                } elseif (isset($record['registryData']['createdDate'])) {
                    $whois['creation_date'] = date('Y-m-d', strtotime($record['registryData']['createdDate']));
                }
                
                // Get expiration date
                if (isset($record['expiresDate'])) {
                    $whois['expiry_date'] = date('Y-m-d', strtotime($record['expiresDate']));
                } elseif (isset($record['registryData']['expiresDate'])) {
                    $whois['expiry_date'] = date('Y-m-d', strtotime($record['registryData']['expiresDate']));
                }
                
                // Get updated date
                if (isset($record['updatedDate'])) {
                    $whois['updated_date'] = date('Y-m-d', strtotime($record['updatedDate']));
                } elseif (isset($record['registryData']['updatedDate'])) {
                    $whois['updated_date'] = date('Y-m-d', strtotime($record['registryData']['updatedDate']));
                }
                
                // Get name servers
                if (isset($record['nameServers']['hostNames'])) {
                    $whois['name_servers'] = array_unique(array_merge(
                        (array)$record['nameServers']['hostNames'],
                        $whois['name_servers']
                    ));
                }
                
                // Get raw WHOIS data
                if (isset($record['rawText'])) {
                    $whois['raw'] = $record['rawText'];
                }
                
                $apiSuccess = true;
            }
        }
        
        // If API failed or returned incomplete data, try system whois
        if (!$apiSuccess || $whois['registrar'] === 'N/A') {
            $tryWhoisLookup($domain);
        }
        
        // Fallback to DNS lookup for name servers if still empty
        if (empty($whois['name_servers'])) {
            $dns = @dns_get_record($domain, DNS_NS + DNS_A + DNS_MX);
            if ($dns) {
                $whois['name_servers'] = array_unique(array_merge(
                    array_column(array_filter($dns, function($r) { 
                        return isset($r['type']) && $r['type'] === 'NS' && isset($r['target']); 
                    }), 'target'),
                    $whois['name_servers']
                ));
            }
        }
        
    } catch (Exception $e) {
        $whois['error'] = 'Error fetching WHOIS data: ' . $e->getMessage();
    }
    
    // Clean up name servers
    if (!empty($whois['name_servers'])) {
        $whois['name_servers'] = array_values(array_unique(array_filter(array_map('trim', $whois['name_servers']))));
    }
    
    return $whois;
}

// Function to fetch HTML content and headers
function fetchHtmlContent($url) {
    $ch = curl_init();
    $headers = [];
    
    // This function is called by curl for each header received
    $headerCallback = function($curl, $headerLine) use (&$headers) {
        $parts = explode(':', $headerLine, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if (!isset($headers[$name])) {
                $headers[$name] = [];
            }
            $headers[$name][] = $value;
        }
        return strlen($headerLine);
    };
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'LinkValidator/1.0',
        CURLOPT_HEADERFUNCTION => $headerCallback,
        CURLOPT_HEADER => false
    ]);
    
    $html = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $error 
        ? ['error' => $error] 
        : [
            'content' => $html,
            'headers' => $headers,
            'http_code' => $httpCode
          ];
}

// Function to extract links from HTML content
function extractLinksFromHtml($html, $baseUrl) {
    $links = [];
    $baseHost = parse_url($baseUrl, PHP_URL_HOST);
    $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME) . '://';
    
    if (empty($html)) {
        return $links;
    }
    
    // Create a DOMDocument instance
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR);
    
    // Get all anchor tags
    $anchorTags = $dom->getElementsByTagName('a');
    
    foreach ($anchorTags as $anchor) {
        $href = $anchor->getAttribute('href');
        
        // Skip empty links and javascript: links
        if (empty($href) || strpos($href, 'javascript:') === 0) {
            continue;
        }
        
        // Handle relative URLs
        if (strpos($href, 'http') !== 0) {
            if (strpos($href, '/') === 0) {
                // Root-relative URL
                $href = $baseScheme . $baseHost . $href;
            } else {
                // Path-relative URL
                $path = parse_url($baseUrl, PHP_URL_PATH);
                $path = dirname($path);
                if ($path === '.') $path = '';
                $href = $baseScheme . $baseHost . $path . '/' . $href;
            }
        }
        
        // Normalize URL
        $href = rtrim($href, '/');
        
        // Add to links array if not already present
        $linkInfo = [
            'url' => $href,
            'text' => trim($anchor->textContent),
            'external' => (strpos($href, $baseHost) === false)
        ];
        
        // Only add if not already in array
        if (!in_array($linkInfo['url'], array_column($links, 'url'))) {
            $links[] = $linkInfo;
        }
    }
    
    // Sort links: internal first, then external
    usort($links, function($a, $b) {
        if ($a['external'] === $b['external']) {
            return strcmp($a['url'], $b['url']);
        }
        return $a['external'] ? 1 : -1;
    });
    
    return $links;
}

// Function to detect technologies used by a website
function detectTechnologies($url, $htmlContent, $headers = []) {
    $technologies = [];
    $rules = [];
    
    // Try multiple possible locations for the rules file
    $possibleRulesFiles = [
        __DIR__ . '/../rules.json',  // One level up from dashboard
        __DIR__ . '/rules.json',     // In dashboard directory
        dirname(__DIR__) . '/rules.json' // Absolute path to root
    ];
    
    // Try each possible rules file location
    foreach ($possibleRulesFiles as $rulesFile) {
        if (file_exists($rulesFile) && is_readable($rulesFile)) {
            $jsonContent = @file_get_contents($rulesFile);
            if ($jsonContent !== false) {
                $rules = @json_decode($jsonContent, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($rules)) {
                    break; // Stop at the first valid rules file found
                }
            }
        }
    }
    
    // If no rules file found or invalid, use minimal default rules
    if (empty($rules)) {
        $rules = [
            'WordPress' => [
                ['type' => 'html', 'pattern' => 'wp-content', 'i' => true],
                ['type' => 'header', 'pattern' => 'X-Powered-By:.*WordPress', 'i' => true],
                ['type' => 'html', 'pattern' => 'wp-json', 'i' => true]
            ],
            'Drupal' => [
                ['type' => 'html', 'pattern' => 'Drupal.settings', 'i' => true],
                ['type' => 'header', 'pattern' => 'X-Generator: Drupal', 'i' => true]
            ]
        ];
    }
    
    // Convert headers to a string for pattern matching
    $headerString = '';
    $cookies = [];
    
    foreach ($headers as $name => $value) {
        $headerValue = is_array($value) ? end($value) : $value;
        $headerString .= "$name: $headerValue\n";
        
        // Extract cookies for separate checking
        if (strtolower($name) === 'set-cookie') {
            $cookieParts = explode(';', $headerValue);
            $cookies[] = trim($cookieParts[0]); // Get just the cookie name=value
        }
    }
    
    // Convert cookies to a string for pattern matching
    $cookieString = implode("\n", $cookies);
    
    // Check each technology's rules
    foreach ($rules as $tech => $patterns) {
        foreach ($patterns as $rule) {
            if (!isset($rule['type'], $rule['pattern'])) {
                continue; // Skip invalid rules
            }
            
            $pattern = $rule['pattern'];
            $caseInsensitive = isset($rule['i']) ? (bool)$rule['i'] : true;
            $modifier = $caseInsensitive ? 'i' : '';
            
            try {
                // If pattern is already a regex (starts and ends with /), use it as is
                if (strpos($pattern, '/') === 0 && substr($pattern, -1) === '/') {
                    $regex = $pattern . $modifier;
                } else {
                    // Otherwise, escape special regex chars and add word boundaries
                    $escaped = preg_quote($pattern, '/');
                    $regex = "/\\b$escaped\\b/$modifier";
                }
                
                $found = false;
                
                switch (strtolower($rule['type'])) {
                    case 'html':
                        $found = (bool)@preg_match($regex, $htmlContent);
                        break;
                        
                    case 'header':
                        $found = (bool)@preg_match($regex, $headerString);
                        break;
                        
                    case 'cookie':
                        $found = (bool)@preg_match($regex, $cookieString);
                        break;
                        
                    case 'urlpath':
                        $path = parse_url($url, PHP_URL_PATH) ?: '/';
                        $found = (bool)@preg_match($regex, $path);
                        break;
                        
                    case 'url':
                        $found = (bool)@preg_match($regex, $url);
                        break;
                }
                
                if ($found) {
                    $technologies[$tech] = [
                        'name' => $tech,
                        'confidence' => 90, // Default confidence when a rule matches
                        'version' => null,  // Could be extended to detect versions
                        'categories' => []  // Could be extended to categorize technologies
                    ];
                    break; // Move to next technology if any rule matches
                }
                
            } catch (Exception $e) {
                // Log error but continue with other rules
                error_log("Error in technology detection for $tech: " . $e->getMessage());
                continue;
            }
        }
    }
    
    // Convert to simple array of technology names for backward compatibility
    return array_keys($technologies);
}

// Function to detect API endpoints and information
function detectApiInfo($url, $htmlContent = '') {
    $apiInfo = [
        'is_api' => false,
        'type' => null,
        'endpoints' => [],
        'documentation' => null,
        'authentication' => [],
        'version' => null
    ];

    // Common API patterns
    $apiPatterns = [
        'rest' => ['/api/v\d+', 'rest', 'json', 'xml', 'endpoint'],
        'graphql' => ['graphql', 'graphiql', 'graphql-playground'],
        'soap' => ['wsdl', 'soap', 'asmx', 'svc'],
        'rpc' => ['rpc', 'xmlrpc', 'jsonrpc'],
        'oembed' => ['oembed', 'embed'],
        'openapi' => ['swagger', 'openapi', 'api-docs']
    ];

    // Check URL for API patterns
    $urlPath = parse_url($url, PHP_URL_PATH) ?: '';
    $urlQuery = parse_url($url, PHP_URL_QUERY) ?: '';
    $urlFragments = array_filter(explode('/', strtolower($urlPath)));
    
    // Check for common API path segments
    foreach ($apiPatterns as $type => $patterns) {
        foreach ($patterns as $pattern) {
            if (stripos($urlPath, $pattern) !== false || 
                stripos($urlQuery, $pattern) !== false ||
                in_array($pattern, $urlFragments)) {
                $apiInfo['is_api'] = true;
                $apiInfo['type'] = $type;
                
                // Extract version if present (e.g., /api/v1/...)
                if (preg_match('/[\/\-](v\d+)[\/\-]?/i', $url, $versionMatches)) {
                    $apiInfo['version'] = $versionMatches[1];
                }
                
                // Look for common documentation endpoints
                $docPatterns = ['docs', 'documentation', 'api-docs', 'swagger', 'openapi'];
                foreach ($docPatterns as $docPattern) {
                    if (stripos($urlPath, $docPattern) !== false) {
                        $apiInfo['documentation'] = $url;
                        break;
                    }
                }
                
                break 2; // Found a match, no need to check other patterns
            }
        }
    }

    // Check response headers for API indicators
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    }
    
    // Check for common API response headers
    $apiHeaders = [
        'content-type' => ['application/json', 'application/xml', 'application/soap+xml'],
        'x-api-version' => 'version',
        'x-powered-by' => ['api', 'rest', 'graphql'],
        'server' => ['api', 'gateway']
    ];
    
    foreach ($headers as $header => $value) {
        if (isset($apiHeaders[$header])) {
            $apiInfo['is_api'] = true;
            if (is_array($apiHeaders[$header])) {
                foreach ($apiHeaders[$header] as $indicator) {
                    if (stripos($value, $indicator) !== false) {
                        $apiInfo['type'] = $apiInfo['type'] ?? 'rest';
                        break;
                    }
                }
            } elseif ($header === 'x-api-version') {
                $apiInfo['version'] = $value;
            }
        }
    }
    
    // Check for common authentication methods in headers
    $authHeaders = [
        'authorization' => 'Bearer/Token',
        'x-api-key' => 'API Key',
        'x-auth-token' => 'Auth Token',
        'x-access-token' => 'Access Token',
        'api-key' => 'API Key'
    ];
    
    foreach ($authHeaders as $authHeader => $authType) {
        if (isset($headers[$authHeader])) {
            $apiInfo['authentication'][] = $authType;
        }
    }
    
    // If we have HTML content, look for API documentation links
    if (!empty($htmlContent) && $apiInfo['is_api']) {
        // Look for common documentation link patterns
        if (preg_match('/<a[^>]+href=[\'"]([^\'"]*?(?:docs|documentation|api-docs|swagger|openapi)[^\'"]*?)[\'"][^>]*>/i', $htmlContent, $docMatches)) {
            $docUrl = $docMatches[1];
            // Convert relative URLs to absolute
            if (strpos($docUrl, 'http') !== 0) {
                $baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
                $docUrl = rtrim($baseUrl, '/') . '/' . ltrim($docUrl, '/');
            }
            $apiInfo['documentation'] = $docUrl;
        }
        
        // Look for common API endpoint patterns in HTML
        if (preg_match_all('/["\']((?:\/[a-zA-Z0-9_\-\/{}]+\/?(?:\?[^\'\"]+)?))["\']/i', $htmlContent, $endpointMatches)) {
            $baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
            foreach ($endpointMatches[1] as $endpoint) {
                // Skip common non-API paths
                if (preg_match('/(\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)|\/static\/|\/assets\/)/i', $endpoint)) {
                    continue;
                }
                
                // Convert relative URLs to absolute
                if (strpos($endpoint, 'http') !== 0) {
                    $endpoint = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
                }
                
                // Add to endpoints if it looks like an API endpoint
                if (preg_match('/\/(api|v\d+|rest|graphql|rpc|soap|ws)\//i', $endpoint)) {
                    $apiInfo['endpoints'][] = $endpoint;
                }
            }
            $apiInfo['endpoints'] = array_unique($apiInfo['endpoints']);
        }
    }
    
    // If we have endpoints but no documentation, try common documentation paths
    if ($apiInfo['is_api'] && empty($apiInfo['documentation'])) {
        $commonDocPaths = ['/docs', '/documentation', '/api-docs', '/swagger', '/openapi'];
        $baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
        
        foreach ($commonDocPaths as $docPath) {
            $apiInfo['endpoints'][] = rtrim($baseUrl, '/') . $docPath;
        }
    }
    
    return $apiInfo;
}

// Function to detect potential fraud in URLs
function detectFraud($url, &$result) {
    $fraudIndicators = [
        'suspicious_keywords' => [
            'login', 'signin', 'account', 'verify', 'secure', 'bank', 'paypal', 'ebay', 'amazon',
            'password', 'update', 'billing', 'confirmation', 'service', 'alert', 'urgent', 'suspended',
            'limited', 'verify', 'security', 'unusual', 'activity', 'unauthorized', 'locked', 'suspicious'
        ],
        'suspicious_tlds' => ['.tk', '.ml', '.ga', '.cf', '.gq', '.xyz', '.top', '.club', '.online', '.site'],
        'ip_address' => false,
        'shortened' => false,
        'punycode' => false,
        'suspicious' => [],
        'score' => 0,
        'is_suspicious' => false
    ];

    // Check for IP address in URL
    $host = parse_url($url, PHP_URL_HOST);
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $fraudIndicators['ip_address'] = true;
        $fraudIndicators['score'] += 30;
        $fraudIndicators['suspicious'][] = 'Uses IP address instead of domain name';
    }

    // Check for shortened URLs
    $shorteners = ['bit.ly', 'goo.gl', 'tinyurl.com', 't.co', 'ow.ly', 'is.gd', 'buff.ly', 'adf.ly'];
    foreach ($shorteners as $shortener) {
        if (stripos($host, $shortener) !== false) {
            $fraudIndicators['shortened'] = true;
            $fraudIndicators['score'] += 20;
            $fraudIndicators['suspicious'][] = 'Uses URL shortening service';
            break;
        }
    }

    // Check for punycode (IDN homograph attack)
    if (preg_match('/xn--/', $host)) {
        $fraudIndicators['punycode'] = true;
        $fraudIndicators['score'] += 40;
        $fraudIndicators['suspicious'][] = 'Uses punycode (possible homograph attack)';
    }

    // Check for suspicious TLDs
    $tld = strtolower(substr($host, strrpos($host, '.')));
    if (in_array($tld, $fraudIndicators['suspicious_tlds'])) {
        $fraudIndicators['score'] += 15;
        $fraudIndicators['suspicious'][] = 'Suspicious TLD: ' . $tld;
    }

    // Check for suspicious keywords in URL
    $urlLower = strtolower($url);
    foreach ($fraudIndicators['suspicious_keywords'] as $keyword) {
        if (strpos($urlLower, $keyword) !== false) {
            $fraudIndicators['score'] += 10;
            $fraudIndicators['suspicious'][] = 'Contains suspicious keyword: ' . $keyword;
        }
    }

    // Check for @ symbol in URL (possible credential phishing)
    if (strpos($url, '@') !== false) {
        $fraudIndicators['score'] += 50;
        $fraudIndicators['suspicious'][] = 'Contains @ symbol (possible credential phishing)';
    }

    // Check for multiple subdomains (possible domain spoofing)
    $subdomains = explode('.', $host);
    if (count($subdomains) > 3) {
        $fraudIndicators['score'] += 20;
        $fraudIndicators['suspicious'][] = 'Multiple subdomains (possible domain spoofing)';
    }

    // Check if domain is very new (less than 30 days old)
    if (!empty($result['whois']['creation_date']) && $result['whois']['creation_date'] !== 'N/A') {
        $domainAge = (time() - strtotime($result['whois']['creation_date'])) / (60 * 60 * 24);
        if ($domainAge < 30) {
            $fraudIndicators['score'] += 20;
            $fraudIndicators['suspicious'][] = 'Domain is very new (' . round($domainAge) . ' days old)';
        }
    }

    // Determine if suspicious
    $fraudIndicators['is_suspicious'] = $fraudIndicators['score'] >= 30;
    
    return $fraudIndicators;
}

// Function to validate and analyze a single URL
function validateLink($url) {
    // Initialize result array
    $result = [
        'url' => $url,
        'is_valid' => false,
        'status' => 'invalid',
        'reason' => '',
        'http_code' => 0,
        'final_url' => '',
        'redirects' => 0,
        'response_time' => 0,
        'content_type' => '',
        'error' => '',
        'api_info' => null,
        'fraud_indicators' => null,
        'whois' => []
    ];
    
    // Get WHOIS information first
    $host = parse_url($url, PHP_URL_HOST);
    if ($host) {
        $result['whois'] = getWhoisInfo($host);
    }

    // Basic URL validation
    if (empty($url)) {
        $result['reason'] = 'URL is empty';
        return $result;
    }

    // Sanitize URL
    $url = filter_var($url, FILTER_SANITIZE_URL);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $result['reason'] = 'Invalid URL format';
        return $result;
    }

    // Check for private IPs (basic SSRF protection)
    $host = parse_url($url, PHP_URL_HOST);
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        // IP is not in private or reserved range
    } else {
        $result['reason'] = 'URL points to a private or reserved IP address';
        return $result;
    }

    // Initialize cURL
    $ch = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'LinkValidator/1.0',
        CURLOPT_CONNECTTIMEOUT => 5
    ];
    curl_setopt_array($ch, $options);

    // Execute request
    $start = microtime(true);
    $response = curl_exec($ch);
    $end = microtime(true);

    // Process response
    if ($response === false) {
        $result['error'] = curl_error($ch);
        $result['reason'] = 'Failed to fetch URL: ' . $result['error'];
        curl_close($ch);
        return $result;
    }

    // Get request info
    $info = curl_getinfo($ch);
    curl_close($ch);

    // Set result details
    $result['http_code'] = $info['http_code'];
    $result['final_url'] = $info['url'];
    $result['redirects'] = $info['redirect_count'];
    $result['response_time'] = round(($end - $start) * 1000); // in ms
    $result['content_type'] = $info['content_type'] ?? '';

    // Determine validity
    if ($info['http_code'] >= 200 && $info['http_code'] < 400) {
        $result['is_valid'] = true;
        $result['status'] = 'valid';
        $result['reason'] = 'URL is accessible';
        
        // Add specific success messages
        if ($info['redirect_count'] > 0) {
            $result['reason'] .= ' (redirects to ' . $result['final_url'] . ')';
        }
        
        // Get domain from URL
        $domain = parse_url($url, PHP_URL_HOST);
        if ($domain) {
            // Remove www. if present
            $domain = preg_replace('/^www\./', '', $domain);
            // Get WHOIS information
            $result['whois'] = getWhoisInfo($domain);
        }
        
        // Fetch HTML content for valid URLs
        $htmlResult = fetchHtmlContent($url);
        if (isset($htmlResult['content'])) {
            $result['html_content'] = htmlspecialchars($htmlResult['content']);
            
            // Detect API information
            $result['api_info'] = detectApiInfo($url, $htmlResult['content']);
            
            // Get headers for technology detection
            $headers = [];
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
            } elseif (isset($htmlResult['headers']) && is_array($htmlResult['headers'])) {
                $headers = $htmlResult['headers'];
            }
            
            // Detect technologies
            $result['technologies'] = detectTechnologies($url, $htmlResult['content'], $headers);
            
            // Extract links from HTML
            $result['links'] = extractLinksFromHtml($htmlResult['content'], $url);
        } else {
            $result['html_content'] = 'Could not fetch HTML content: ' . ($htmlResult['error'] ?? 'Unknown error');
            
            // Still try to detect API from URL alone
            $result['api_info'] = detectApiInfo($url);
            
            // Try to detect technologies from URL and headers if available
            $headers = [];
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
            }
            $result['technologies'] = detectTechnologies($url, '', $headers);
            $result['links'] = []; // No links to extract without HTML content
        }
    } else {
        $result['status'] = 'error';
        
        // Common HTTP status code reasons
        switch ($info['http_code']) {
            case 0:
                $result['reason'] = 'Connection failed or timed out';
                break;
            case 400:
                $result['reason'] = 'Bad Request - The server cannot process the request';
                break;
            case 401:
                $result['reason'] = 'Unauthorized - Authentication required';
                break;
            case 403:
                $result['reason'] = 'Forbidden - Access denied';
                break;
            case 404:
                $result['reason'] = 'Not Found - The requested URL was not found';
                break;
            case 500:
                $result['reason'] = 'Internal Server Error';
                break;
            case 502:
                $result['reason'] = 'Bad Gateway - Server received an invalid response';
                break;
            case 503:
                $result['reason'] = 'Service Unavailable - The server is currently unavailable';
                break;
            case 504:
                $result['reason'] = 'Gateway Timeout - The server did not respond in time';
                break;
            default:
                if ($info['http_code'] >= 400) {
                    $result['reason'] = 'HTTP Error ' . $info['http_code'];
                } else {
                    $result['reason'] = 'Unknown error';
                }
        }
    }

    return $result;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $url = trim($_POST['url']);
    $result = validateLink($url);
} else {
    $url = '';
    $result = null;
}

// HTML Output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Analyzer - TechDetector</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ntsa-style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #000000;
            color: #ffffff;
        }
        .container {
            background: #000000;
            border: 1px solid #3c4043;
            border-radius: 8px;
            padding: 25px;
            margin: 15px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        
        .header {
            background: #1a1a1a;
            color: white;
            padding: 15px 25px;
            border-bottom: 1px solid #3c4043;
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .header p {
            margin: 5px 0 0;
            opacity: 0.9;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 992px) {
            .gsc-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 1000;
            }
            
            .gsc-sidebar.gsc-sidebar-mobile-open {
                transform: translateX(0);
            }
            
            .gsc-main-wrapper {
                margin-left: 0;
            }
            
            .gsc-topbar {
                padding: 0.75rem 1rem;
            }
            
            .gsc-sidebar-toggle {
                display: flex !important;
                border: none;
                border-radius: 8px;
                width: 44px;
                height: 44px;
                margin-right: 12px;
                color: #9aa0a6;
            }
            
            .gsc-sidebar-toggle:hover {
                background: #2d2d2d;
                color: #ffffff;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
                margin: 10px;
            }
            
            .header {
                padding: 1.5rem 1rem;
                text-align: center;
                margin-bottom: 1rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .header p {
                font-size: 0.9rem;
                color: #9ca3af;
                padding: 0 1rem;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            input[type="text"],
            input[type="url"],
            textarea {
                font-size: 16px; /* Prevent zoom on iOS */
            }
            
            button {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .form-container {
                margin: 0 1rem 1rem;
                padding: 1.5rem;
            }
        }
        .form-container {
            background: #000000;
            padding: 25px;
            border: 1px solid #3c4043;
            border-radius: 8px;
            margin: 0 15px 15px;
        }
        input[type="url"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #3c4043;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: #1a1a1a;
            color: #ffffff;
        }
        
        input[type="url"]:focus {
            border-color: #ff6b00;
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.2);
            outline: none;
        }
        button, .btn {
            background: #ff6b00;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s, transform 0.2s;
        }
        
        button:hover, .btn:hover {
            background: #e65a00;
            transform: translateY(-1px);
        }
        
        button:active, .btn:active {
            transform: translateY(0);
        }
        .result {
            margin: 20px 0;
            padding: 20px;
            border-radius: 8px;
            background: #000000;
            border: 1px solid #3c4043;
        }
        .valid {
            background: rgba(255, 107, 0, 0.1);
            color: #ff6b00;
            border-left: 4px solid #ff6b00;
        }
        .invalid {
            background: rgba(255, 107, 0, 0.1);
            color: #ff6b00;
            border-left: 4px solid #ff6b00;
        }
        .details {
            margin-top: 15px;
            padding: 15px;
            background: #1a1a1a;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            word-break: break-word;
            border: 1px solid #3c4043;
        }
        .table {
            color: #ffffff;
        }
        .table a {
            color: #ff6b00;
        }
    </style>
</head>
<body class="bg-black font-sans antialiased">
    <!-- Google Search Console-style Sidebar -->
    <aside class="gsc-sidebar" id="gscSidebar">
        <div class="gsc-sidebar-header">
            <a href="../index.php" class="gsc-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </div>
        
        <nav class="gsc-sidebar-nav">
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Dashboard</div>
                <a href="overview" class="gsc-nav-item">
                    <i class="fas fa-home"></i>
                    <span>Overview</span>
                </a>
                <a href="domain-check" class="gsc-nav-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Domain Check</span>
                </a>
                <a href="link-analyzer" class="gsc-nav-item active">
                    <i class="fas fa-link"></i>
                    <span>Link Analyzer</span>
                </a>
            </div>
            
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Account</div>
                <a href="profile" class="gsc-nav-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="settings" class="gsc-nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="../api/logout" class="gsc-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="gsc-main-wrapper">
        <!-- Desktop/Mobile Top Bar -->
        <header class="gsc-topbar">
            <button class="gsc-sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <a href="overview" class="gsc-topbar-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </header>

        <!-- Page Content -->
        <div class="gsc-content-wrapper">
    <header class="header">
        <h1><i class="fas fa-link me-2" style="color: #ff6b00;"></i>Link Analyzer</h1>
        <p>Enter a URL to validate its accessibility and get detailed information</p>
    </header>
    
    <div class="form-container">
        <form method="post" class="flex flex-col gap-2">
            <div class="form-group flex-grow-1">
                <input type="url" id="url" name="url" 
                       class="form-control"
                       placeholder="https://example.com" 
                       value="<?php echo htmlspecialchars($url); ?>" 
                       required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search me-1"></i> Analyze
            </button>
        </form>
    </div>

    <?php if ($result !== null): ?>
    <div class="container-fluid">
        <h2>Validation Result</h2>
        <div class="result <?php echo $result['is_valid'] ? 'valid' : 'invalid'; ?>">
            <strong>URL:</strong> <?php echo htmlspecialchars($result['url']); ?><br>
            <strong>Status:</strong> 
            <?php if ($result['is_valid']): ?>
                <span style="color: green;">✓ Valid</span>
            <?php else: ?>
                <span style="color: red;">✗ Invalid</span>
            <?php endif; ?>
            <br>
            <strong>Reason:</strong> <?php echo htmlspecialchars($result['reason']); ?>
            
            <div class="details">
                <strong>Details:</strong><br>
                HTTP Status: <?php echo $result['http_code']; ?><br>
                <?php if (!empty($result['final_url']) && $result['final_url'] !== $result['url']): ?>
                Final URL: <?php echo htmlspecialchars($result['final_url']); ?><br>
                <?php endif; ?>
                <?php if ($result['redirects'] > 0): ?>
                Redirects: <?php echo $result['redirects']; ?><br>
                <?php endif; ?>
                Response Time: <?php echo $result['response_time']; ?> ms<br>
                <?php if (!empty($result['content_type'])): ?>
                Content Type: <?php echo htmlspecialchars($result['content_type']); ?><br>
                <?php endif; ?>
                <?php if (!empty($result['error'])): ?>
                Error: <?php echo htmlspecialchars($result['error']); ?><br>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($result['technologies'])): ?>
            <div class="mt-3 p-3 bg-light rounded">
                <h5><i class="bi bi-tools me-2"></i>Technologies Detected</h5>
                <div class="technology-grid">
                    <?php 
                    $techIcons = [
                        'WordPress' => 'wordpress',
                        'Drupal' => 'drupal',
                        'Joomla' => 'joomla',
                        'Laravel' => 'laravel',
                        'React' => 'react',
                        'Vue.js' => 'vuejs',
                        'Angular' => 'angular',
                        'jQuery' => 'jquery',
                        'Bootstrap' => 'bootstrap',
                        'Tailwind CSS' => 'bootstrap',
                        'Node.js' => 'node-js',
                        'Express.js' => 'node-js',
                        'MongoDB' => 'database',
                        'MySQL' => 'database',
                        'PostgreSQL' => 'database',
                        'Nginx' => 'server',
                        'Apache' => 'server',
                        'PHP' => 'php',
                        'Python' => 'python',
                        'Ruby on Rails' => 'ruby',
                        'Django' => 'python',
                        'Flask' => 'python',
                        'Symfony' => 'symfony',
                        'ASP.NET' => 'microsoft',
                        'Google Analytics' => 'google',
                        'Cloudflare' => 'cloud'
                    ];
                    
                    foreach ($result['technologies'] as $tech): 
                        $icon = $techIcons[$tech] ?? 'code';
                        $color = 'bg-primary';
                        
                        // Set different colors based on technology type
                        if (strpos($tech, 'JS') !== false || $tech === 'jQuery' || $tech === 'React' || $tech === 'Vue.js' || $tech === 'Angular') {
                            $color = 'bg-info text-dark';
                        } elseif (strpos($tech, 'PHP') !== false || strpos($tech, 'Laravel') !== false || strpos($tech, 'WordPress') !== false || strpos($tech, 'Drupal') !== false) {
                            $color = 'bg-primary';
                        } elseif (strpos($tech, 'Python') !== false || strpos($tech, 'Django') !== false || strpos($tech, 'Flask') !== false) {
                            $color = 'bg-success';
                        } elseif (strpos($tech, 'Node') !== false || strpos($tech, 'Express') !== false) {
                            $color = 'bg-success';
                        } elseif (strpos($tech, 'Google') !== false || strpos($tech, 'Analytics') !== false) {
                            $color = 'bg-warning text-dark';
                        } elseif (strpos($tech, 'Cloudflare') !== false || strpos($tech, 'CDN') !== false) {
                            $color = 'bg-danger';
                        }
                    ?>
                        <div class="tech-item">
                            <i class="bi bi-<?php echo $icon; ?> me-2"></i>
                            <span><?php echo htmlspecialchars($tech); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($result['whois'])): ?>
            <div class="mt-3 p-3 bg-light rounded">
                <h5>Domain Information</h5>
                <div class="row">
                    <?php if (!empty($result['whois']['registrar']) && $result['whois']['registrar'] !== 'N/A'): ?>
                    <div class="col-md-6">
                        <strong>Registrar:</strong> <?php echo htmlspecialchars($result['whois']['registrar']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($result['api_info']) && $result['api_info']['is_api']): ?>
                    <div class="mt-3 p-3 bg-info bg-opacity-10 rounded">
                        <h5><i class="bi bi-plug me-2"></i>API Detected</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Type:</strong> 
                                <?php echo strtoupper($result['api_info']['type'] ?? 'Unknown'); ?>
                                <?php if (!empty($result['api_info']['version'])): ?>
                                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($result['api_info']['version']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($result['api_info']['documentation'])): ?>
                            <div class="col-md-6">
                                <strong>Documentation:</strong> 
                                <a href="<?php echo htmlspecialchars($result['api_info']['documentation']); ?>" target="_blank" rel="noopener noreferrer">
                                    View API Docs <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($result['api_info']['authentication'])): ?>
                            <div class="col-12 mt-2">
                                <strong>Authentication:</strong> 
                                <?php echo implode(', ', array_map('htmlspecialchars', $result['api_info']['authentication'])); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($result['api_info']['endpoints'])): ?>
                            <div class="col-12 mt-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>Endpoints (<?php echo count($result['api_info']['endpoints']); ?>):</strong>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-sm" 
                                            onclick="copyToClipboard('apiEndpoints')">
                                        Copy to Clipboard
                                    </button>
                                </div>
                                <div id="apiEndpoints" class="mt-1 p-2 bg-white rounded border" style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach (array_slice($result['api_info']['endpoints'], 0, 10) as $i => $endpoint): ?>
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge bg-secondary me-2"><?php echo $i + 1; ?></span>
                                            <code class="text-break"><?php echo htmlspecialchars($endpoint); ?></code>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($result['api_info']['endpoints']) > 10): ?>
                                        <div class="text-muted">+ <?php echo count($result['api_info']['endpoints']) - 10; ?> more endpoints found</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($result['whois']['creation_date']) && $result['whois']['creation_date'] !== 'N/A'): ?>
                    <div class="col-md-6">
                        <strong>Created:</strong> <?php echo htmlspecialchars($result['whois']['creation_date']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($result['whois']['expiry_date']) && $result['whois']['expiry_date'] !== 'N/A'): ?>
                    <div class="col-md-6">
                        <strong>Expires:</strong> <?php echo htmlspecialchars($result['whois']['expiry_date']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($result['whois']['name_servers'])): ?>
                    <div class="col-12 mt-2">
                        <strong>Name Servers:</strong><br>
                        <?php foreach ((array)$result['whois']['name_servers'] as $ns): ?>
                            <span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($ns); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($result['whois']['raw'])): ?>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-info" 
                            onclick="toggleSource(this, 'whoisRaw')">
                        Show Full WHOIS Data
                    </button>
                    <pre id="whoisRaw" style="display:none; max-height: 300px; overflow: auto; background: #000; color: #fff; padding: 10px; border: 1px solid #444; border-radius: 4px; font-size: 12px; margin-top: 10px;">
<?php echo htmlspecialchars($result['whois']['raw']); ?>
                    </pre>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($result['links'])): ?>
            <div class="mt-3 p-3 bg-light rounded">
                <h5><i class="bi bi-link-45deg me-2"></i>Links Found (<?php echo count($result['links']); ?>)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>URL</th>
                                <th>Link Text</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['links'] as $link): ?>
                                <tr>
                                    <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis;">
                                        <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo htmlspecialchars($link['url']); ?>
                                        </a>
                                    </td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo !empty($link['text']) ? htmlspecialchars(mb_substr($link['text'], 0, 50) . (mb_strlen($link['text']) > 50 ? '...' : '')) : '<span class="text-muted">(no text)</span>'; ?>
                                    </td>
                                    <td>
                                        <?php if ($link['external']): ?>
                                            <span class="badge bg-warning text-dark">External</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Internal</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($result['html_content'])): ?>
            <div class="mt-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" 
                        onclick="toggleSource(this, 'sourceContainer')">
                    Show HTML Source
                </button>
                <div id="sourceContainer" style="display:none; margin-top:10px;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Complete HTML Source (<?php echo number_format(strlen($result['html_content'])); ?> chars):</small>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-sm me-1" 
                                    onclick="toggleWordWrap('sourceCode')">
                                Toggle Word Wrap
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-sm" 
                                    onclick="copyToClipboard('sourceCode')">
                                Copy to Clipboard
                            </button>
                        </div>
                    </div>
                    <pre id="sourceCode" style="max-height: 500px; overflow: auto; background: #000; color: #fff; padding: 10px; border: 1px solid #444; border-radius: 4px; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;">
<?php echo $result['html_content']; ?>
                    </pre>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    function toggleSource(button, containerId) {
        const container = document.getElementById(containerId);
        if (container.style.display === 'none') {
            container.style.display = 'block';
            button.textContent = 'Hide HTML Source';
        } else {
            container.style.display = 'none';
            button.textContent = 'Show HTML Source';
        }
    }
    
    function copyToClipboard(elementId) {
        const el = document.getElementById(elementId);
        const range = document.createRange();
        range.selectNode(el);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        document.execCommand('copy');
        window.getSelection().removeAllRanges();
        
        // Show feedback
        const btn = document.querySelector('button[onclick*="' + elementId + '"]');
        const originalText = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    }
    
    function toggleWordWrap(elementId) {
        const el = document.getElementById(elementId);
        if (el.style.whiteSpace === 'pre-wrap') {
            el.style.whiteSpace = 'pre';
            el.style.wordWrap = 'normal';
        } else {
            el.style.whiteSpace = 'pre-wrap';
            el.style.wordWrap = 'break-word';
        }
    }
    </script>
    
    <script src="../assets/js/main.js"></script>
        </div>
        </main>
    </div>
</body>
</html>