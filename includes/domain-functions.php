<?php
/**
 * Domain Checker Functions
 * Contains all the domain checking and analysis functions
 */

/**
 * Performs a comprehensive domain check
 * 
 * @param string $domain The domain to check
 * @return array Detailed domain information
 */
function performDomainCheck($domain) {
    // Initialize result array
    $result = [
        'domain' => $domain,
        'available' => false,
        'status' => 'unknown',
        'timestamp' => date('Y-m-d H:i:s'),
        'dns' => [
            'a' => [],
            'mx' => [],
            'ns' => [],
            'txt' => []
        ],
        'ip_info' => [],
        'ssl' => [
            'valid' => false,
            'issuer' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null
        ],
        'performance' => [
            'load_time' => 0,
            'page_size' => 0,
            'grade' => 'F'
        ],
        'security' => [
            'headers' => [],
            'common_vulnerabilities' => []
        ],
        'technologies' => [],
        'suggestions' => []
    ];

    try {
        // Check if domain is valid
        if (!preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $domain)) {
            throw new Exception('Invalid domain format');
        }

        // Check domain availability
        $result = array_merge($result, checkDomainAvailability($domain));

        // If domain is taken, get additional information
        if (!$result['available']) {
            // Get DNS records
            $result['dns'] = getDnsRecords($domain);
            
            // Get IP information
            $result['ip_info'] = getIpGeolocation(gethostbyname($domain));
            
            // Check SSL certificate
            $result['ssl'] = checkSSL($domain);
            
            // Check performance
            $result['performance'] = checkWebsitePerformance($domain);
            
            // Check security headers
            $result['security']['headers'] = checkSecurityHeaders($domain);
            
            // Detect technologies
            $result['technologies'] = detectTechnologies($domain);
            
            // Check for common vulnerabilities
            $result['security']['common_vulnerabilities'] = checkCommonVulnerabilities($domain);
            
            // Check for common directories
            $result['directories'] = checkCommonDirectories($domain);
            
            // Analyze page content
            $result['page_analysis'] = analyzePageContent($domain);
        } else {
            // If domain is available, get suggestions
            $result['suggestions'] = getDomainSuggestions($domain);
        }
        
        $result['status'] = 'completed';
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        $result['status'] = 'error';
    }
    
    return $result;
}

/**
 * Checks if a domain is available
 * 
 * @param string $domain The domain to check
 * @return array Availability information
 */
function checkDomainAvailability($domain) {
    $result = [
        'available' => false,
        'registered' => false,
        'whois' => []
    ];
    
    // Try to get DNS records
    $dnsRecords = @dns_get_record($domain, DNS_ANY);
    
    if (empty($dnsRecords)) {
        // If no DNS records, domain might be available
        $result['available'] = true;
        $result['registered'] = false;
    } else {
        $result['available'] = false;
        $result['registered'] = true;
        
        // Try to get WHOIS information
        $whoisRaw = @shell_exec('whois ' . escapeshellarg($domain) . ' 2>&1');
        
        if ($whoisRaw) {
            // Parse WHOIS data (simplified)
            $result['whois'] = parseWhoisData($whoisRaw);
        }
    }
    
    return $result;
}

/**
 * Gets DNS records for a domain
 * 
 * @param string $domain The domain to check
 * @return array DNS records
 */
function getDnsRecords($domain) {
    // Initialize debug log
    $debugLog = [];
    $debugLog[] = "Starting DNS lookup for: $domain";
    
    // Check if dns_get_record function exists
    if (!function_exists('dns_get_record')) {
        $debugLog[] = "ERROR: dns_get_record() function is not available on this server";
        return [
            'a' => [],
            'mx' => [],
            'ns' => [],
            'txt' => [],
            'cname' => [],
            'all' => [],
            '_debug' => $debugLog,
            '_error' => 'DNS functions are not available on this server'
        ];
    }
    
    // First, try to get the base domain (without www.)
    $cleanDomain = preg_replace('/^www\./i', '', $domain);
    $debugLog[] = "Using cleaned domain: $cleanDomain";
    
    // Array to store all DNS records
    $allRecords = [];
    
    // Get common DNS record types with error suppression
    $recordTypes = @[
        'A' => defined('DNS_A') ? DNS_A : 1,
        'AAAA' => defined('DNS_AAAA') ? DNS_AAAA : 28,
        'MX' => defined('DNS_MX') ? DNS_MX : 15,
        'CNAME' => defined('DNS_CNAME') ? DNS_CNAME : 5,
        'TXT' => defined('DNS_TXT') ? DNS_TXT : 16,
        'NS' => defined('DNS_NS') ? DNS_NS : 2,
        'SOA' => defined('DNS_SOA') ? DNS_SOA : 6
    ];
    
    $debugLog[] = "Available record types: " . implode(', ', array_keys($recordTypes));
    
    // Try to get records for each type
    foreach ($recordTypes as $type => $const) {
        try {
            $debugLog[] = "Trying to get $type records...";
            $records = @dns_get_record($cleanDomain, $const);
            
            if ($records === false) {
                $error = error_get_last();
                $debugLog[] = "ERROR getting $type records: " . ($error['message'] ?? 'Unknown error');
                continue;
            }
            
            $debugLog[] = "Found " . (is_array($records) ? count($records) : '0') . " $type records for $cleanDomain";
            
            if (is_array($records)) {
                foreach ($records as $record) {
                    $record['type'] = $type; // Ensure type is set
                    $allRecords[] = $record;
                }
            }
        } catch (Exception $e) {
            $debugLog[] = "EXCEPTION getting $type records: " . $e->getMessage();
        }
    }
    
    // If no records found, try with www. prefix
    if (empty($allRecords) && !preg_match('/^www\./i', $domain)) {
        $wwwDomain = 'www.' . $cleanDomain;
        $debugLog[] = "No records found, trying with www. prefix: $wwwDomain";
        
        foreach ($recordTypes as $type => $const) {
            $records = @dns_get_record($wwwDomain, $const);
            $debugLog[] = "Checking $type records for $wwwDomain - " . (is_array($records) ? count($records) . ' found' : 'none');
            
            if ($records !== false && is_array($records)) {
                foreach ($records as $record) {
                    $record['type'] = $type;
                    $allRecords[] = $record;
                }
            }
        }
    }
    
    // Format the records in the expected structure
    $formattedRecords = [
        'a' => [],
        'mx' => [],
        'ns' => [],
        'txt' => [],
        'cname' => [],
        'all' => $allRecords  // Keep all records for reference
    ];
    
    // Process each record and format it
    foreach ($allRecords as $record) {
        $type = strtoupper($record['type'] ?? '');
        
        switch ($type) {
            case 'A':
                $formattedRecords['a'][] = [
                    'ip' => $record['ip'] ?? '',
                    'ttl' => $record['ttl'] ?? 3600,
                    'host' => $record['host'] ?? $cleanDomain
                ];
                break;
                
            case 'MX':
                $formattedRecords['mx'][] = [
                    'target' => rtrim($record['target'] ?? '', '.'),
                    'priority' => $record['pri'] ?? 10,
                    'ttl' => $record['ttl'] ?? 3600
                ];
                break;
                
            case 'NS':
                $formattedRecords['ns'][] = [
                    'target' => rtrim($record['target'] ?? '', '.'),
                    'ttl' => $record['ttl'] ?? 3600
                ];
                break;
                
            case 'TXT':
                if (!empty($record['txt'])) {
                    $formattedRecords['txt'][] = [
                        'text' => $record['txt'],
                        'ttl' => $record['ttl'] ?? 3600
                    ];
                }
                break;
                
            case 'CNAME':
                if (!empty($record['target'])) {
                    $formattedRecords['cname'][] = [
                        'target' => rtrim($record['target'], '.'),
                        'ttl' => $record['ttl'] ?? 3600
                    ];
                }
                break;
        }
    }
    
    // If no records found, try a direct IP lookup as a fallback
    if (empty(array_filter($formattedRecords))) {
        $debugLog[] = "No DNS records found, trying direct IP lookup...";
        $ip = @gethostbyname($cleanDomain);
        
        if ($ip && $ip !== $cleanDomain) {
            $debugLog[] = "Found IP via gethostbyname(): $ip";
            $formattedRecords['a'][] = [
                'ip' => $ip,
                'ttl' => 3600,
                'host' => $cleanDomain
            ];
            
            // Also add to all records for consistency
            $allRecords[] = [
                'type' => 'A',
                'ip' => $ip,
                'host' => $cleanDomain,
                'ttl' => 3600
            ];
        } else {
            $debugLog[] = "gethostbyname() failed or returned the same domain";
        }
    }
    
    // Add debug information
    $formattedRecords['_debug'] = $debugLog;
    $formattedRecords['_debug'][] = "Total records found: " . count($allRecords);
    $formattedRecords['_debug'][] = "Record types: " . implode(', ', array_unique(array_column($allRecords, 'type')));
    
    return $formattedRecords;
    
    return $records;
}

/**
    }
    
    try {
        $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query";
        $response = @file_get_contents($url);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                return [
                    'ip' => $data['query'],
                    'country' => $data['country'] ?? 'Unknown',
                    'country_code' => $data['countryCode'] ?? '',
                    'region' => $data['regionName'] ?? '',
                    'city' => $data['city'] ?? 'Unknown',
                    'zip' => $data['zip'] ?? '',
                    'latitude' => $data['lat'] ?? 0,
                    'longitude' => $data['lon'] ?? 0,
                    'timezone' => $data['timezone'] ?? '',
                    'isp' => $data['isp'] ?? 'Unknown',
                    'organization' => $data['org'] ?? '',
                    'as' => $data['as'] ?? ''
                ];
            }
        }
    } catch (Exception $e) {
        // Silently handle errors
    }
    
    // Return minimal data if API fails
    return [
        'ip' => $ip,
        'country' => 'Unknown',
        'city' => 'Unknown',
        'isp' => 'Unknown'
    ];
}

/**
 * Checks SSL certificate information for a domain
 * 
 * @param string $domain The domain to check
 * @return array SSL certificate information
 */
function checkSSL($domain) {
    $result = [
        'valid' => false,
        'issuer' => null,
        'valid_from' => null,
        'valid_to' => null,
        'days_remaining' => null,
        'algorithm' => null,
        'key_size' => null
    ];
    
    try {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $client = @stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        
        if ($client) {
            $params = stream_context_get_params($client);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            
            if ($cert) {
                $result['valid'] = true;
                $result['issuer'] = $cert['issuer']['O'] ?? 'Unknown';
                $result['valid_from'] = date('Y-m-d H:i:s', $cert['validFrom_time_t']);
                $result['valid_to'] = date('Y-m-d H:i:s', $cert['validTo_time_t']);
                $result['days_remaining'] = floor(($cert['validTo_time_t'] - time()) / 86400);
                $result['algorithm'] = $cert['signatureTypeLN'] ?? 'Unknown';
                $result['key_size'] = $cert['bits'] ?? null;
                
                // Check for common issues
                if (time() > $cert['validTo_time_t']) {
                    $result['status'] = 'expired';
                } elseif (time() < $cert['validFrom_time_t']) {
                    $result['status'] = 'not_yet_valid';
                } else {
                    $result['status'] = 'valid';
                }
            }
            
            fclose($client);
        }
    } catch (Exception $e) {
        // Silently handle errors
    }
    
    return $result;
}

/**
 * Checks website performance metrics
 * 
 * @param string $domain The domain to check
 * @return array Performance metrics
 */
function checkWebsitePerformance($domain) {
    $result = [
        'load_time' => 0,
        'page_size' => 0,
        'requests' => 0,
        'grade' => 'F',
        'ttfb' => 0,
        'dom_load_time' => 0,
        'page_load_time' => 0
    ];
    
    try {
        $start = microtime(true);
        $content = @file_get_contents("https://{$domain}", false, stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]));
        
        if ($content !== false) {
            $end = microtime(true);
            $loadTime = ($end - $start) * 1000; // Convert to milliseconds
            $pageSize = strlen($content) / 1024; // Convert to KB
            
            // Count resources (simplified)
            preg_match_all('/(src|href)=["\']([^"\']+\.(js|css|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot))["\']/i', $content, $matches);
            $requestCount = count($matches[0]) + 1; // +1 for the main page
            
            // Simple grading
            $grade = 'F';
            if ($loadTime < 500) $grade = 'A';
            elseif ($loadTime < 1000) $grade = 'B';
            elseif ($loadTime < 2000) $grade = 'C';
            elseif ($loadTime < 3000) $grade = 'D';
            
            $result = [
                'load_time' => round($loadTime, 2),
                'page_size' => round($pageSize, 2),
                'requests' => $requestCount,
                'grade' => $grade,
                'ttfb' => round($loadTime * 0.3, 2), // Simulate TTFB as 30% of load time
                'dom_load_time' => round($loadTime * 0.6, 2), // Simulate DOM load as 60% of load time
                'page_load_time' => round($loadTime, 2)
            ];
        }
    } catch (Exception $e) {
        // Silently handle errors
    }
    
    return $result;
}

/**
 * Checks security headers for a domain
 * 
 * @param string $domain The domain to check
 * @return array Security headers information
 */
function checkSecurityHeaders($domain) {
    $headersToCheck = [
        'Strict-Transport-Security' => [
            'description' => 'HTTP Strict Transport Security (HSTS) ensures all communications are sent over HTTPS',
            'recommendation' => 'Set a long max-age (e.g., 63072000 for 2 years) and includeSubDomains',
            'severity' => 'high'
        ],
        'X-Frame-Options' => [
            'description' => 'Prevents clickjacking attacks by controlling whether the site can be embedded in an iframe',
            'recommendation' => 'Set to "DENY" or "SAMEORIGIN"',
            'severity' => 'high'
        ],
        'X-Content-Type-Options' => [
            'description' => 'Prevents MIME type sniffing which can lead to security vulnerabilities',
            'recommendation' => 'Set to "nosniff"',
            'severity' => 'medium'
        ],
        'X-XSS-Protection' => [
            'description' => 'Enables the XSS filter in older browsers',
            'recommendation' => 'Set to "1; mode=block"',
            'severity' => 'medium'
        ],
        'Content-Security-Policy' => [
            'description' => 'Defines which dynamic resources are allowed to load',
            'recommendation' => 'Implement a strict CSP policy',
            'severity' => 'high'
        ],
        'Referrer-Policy' => [
            'description' => 'Controls how much referrer information is included with requests',
            'recommendation' => 'Set to "strict-origin-when-cross-origin" or "no-referrer-when-downgrade"',
            'severity' => 'low'
        ],
        'Permissions-Policy' => [
            'description' => 'Controls which web platform features are available in the browser',
            'recommendation' => 'Set a restrictive policy for better security',
            'severity' => 'medium'
        ]
    ];
    
    $results = [];
    
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'follow_location' => 1,
                'max_redirects' => 3,
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.5',
                    'Connection: close'
                ]
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        // Try HTTPS first
        $url = "https://{$domain}";
        $headers = @get_headers($url, 1, $context);
        
        // Fallback to HTTP if HTTPS fails
        if ($headers === false) {
            $url = "http://{$domain}";
            $headers = @get_headers($url, 1, $context);
        }
        
        if ($headers !== false) {
            $headers = array_change_key_case($headers, CASE_LOWER);
            
            foreach ($headersToCheck as $header => $info) {
                $headerKey = strtolower($header);
                $headerInfo = [
                    'enabled' => false,
                    'value' => null,
                    'description' => $info['description'],
                    'recommendation' => $info['recommendation'],
                    'severity' => $info['severity']
                ];
                
                if (isset($headers[$headerKey])) {
                    $headerInfo['enabled'] = true;
                    $headerInfo['value'] = is_array($headers[$headerKey]) ? end($headers[$headerKey]) : $headers[$headerKey];
                    
                    // Additional validation for specific headers
                    if ($headerKey === 'strict-transport-security' && stripos($headerInfo['value'], 'max-age=') === false) {
                        $headerInfo['enabled'] = false;
                        $headerInfo['error'] = 'Missing max-age directive';
                    }
                }
                
                $results[$header] = $headerInfo;
            }
        }
    } catch (Exception $e) {
        // Silently handle errors
    }
    
    return $results;
}

/**
 * Detects technologies used by a website
 * 
 * @param string $domain The domain to check
 * @return array Detected technologies
 */
function detectTechnologies($domain) {
    $technologies = [];
    
    try {
        $content = @file_get_contents("https://{$domain}", false, stream_context_create([
            'http' => [
                'timeout' => 5,
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]));
        
        if ($content !== false) {
            // Check for common CMS and frameworks
            $cmsSignatures = [
                'WordPress' => ['wp-content', 'wp-includes'],
                'Joomla' => ['/media/jui/', '/media/system/'],
                'Drupal' => ['/sites/all/', '/misc/drupal.js'],
                'Magento' => ['/skin/frontend/', '/js/mage/'],
                'Shopify' => ['cdn.shopify.com', 'shopify_analytics'],
                'Laravel' => ['/vendor/laravel/', 'mix-manifest.json'],
                'React' => ['/static/js/main.', '/static/css/main.'],
                'Vue.js' => ['/js/app.', '/css/app.', '__VUE__'],
                'Angular' => ['/runtime.', '/main.', 'polyfills.']
            ];
            
            foreach ($cmsSignatures as $cms => $signatures) {
                $found = true;
                foreach ($signatures as $signature) {
                    if (strpos($content, $signature) === false) {
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    $technologies[] = [
                        'name' => $cms,
                        'type' => 'CMS/Framework',
                        'confidence' => 90,
                        'version' => null,
                        'icon' => strtolower(str_replace([' ', '.'], ['', ''], $cms))
                    ];
                    break;
                }
            }
            
            // Check for server software from headers
            $headers = $http_response_header ?? [];
            $serverHeader = '';
            
            foreach ($headers as $header) {
                if (stripos($header, 'Server:') === 0) {
                    $serverHeader = $header;
                    break;
                }
            }
            
            if ($serverHeader) {
                $server = trim(substr($serverHeader, 7));
                $technologies[] = [
                    'name' => $server,
                    'type' => 'Web Server',
                    'confidence' => 100,
                    'version' => null,
                    'icon' => 'server'
                ];
            }
            
            // Check for programming languages
            if (strpos($content, '.php') !== false) {
                $technologies[] = [
                    'name' => 'PHP',
                    'type' => 'Programming Language',
                    'confidence' => 80,
                    'version' => null,
                    'icon' => 'php'
                ];
            }
            
            // Check for frontend frameworks
            if (strpos($content, 'react') !== false || strpos($content, 'React') !== false) {
                $technologies[] = [
                    'name' => 'React',
                    'type' => 'JavaScript Framework',
                    'confidence' => 85,
                    'version' => null,
                    'icon' => 'react'
                ];
            }
            
            if (strpos($content, 'Vue.') !== false || strpos($content, 'vue.') !== false) {
                $technologies[] = [
                    'name' => 'Vue.js',
                    'type' => 'JavaScript Framework',
                    'confidence' => 85,
                    'version' => null,
                    'icon' => 'vuejs'
                ];
            }
            
            if (strpos($content, 'angular') !== false || strpos($content, 'ng-') !== false) {
                $technologies[] = [
                    'name' => 'Angular',
                    'type' => 'JavaScript Framework',
                    'confidence' => 85,
                    'version' => null,
                    'icon' => 'angular'
                ];
            }
        }
    } catch (Exception $e) {
        // Silently handle errors
    }
    
    return $technologies;
}

/**
 * Checks for common vulnerabilities
 * 
 * @param string $domain The domain to check
 * @return array Vulnerability information
 */
function checkCommonVulnerabilities($domain) {
    $vulnerabilities = [];
    
    // This is a simplified example - in a real application, you would perform actual security checks
    
    // Check for exposed .git directory
    $gitCheck = @file_get_contents("http://{$domain}/.git/HEAD", false, stream_context_create([
        'http' => ['timeout' => 5],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]));
    
    if ($gitCheck !== false && strpos($gitCheck, 'ref:') !== false) {
        $vulnerabilities[] = [
            'name' => 'Exposed .git Directory',
            'severity' => 'high',
            'description' => 'The .git directory is accessible, which may expose sensitive information including source code and commit history.',
            'recommendation' => 'Restrict access to the .git directory in your web server configuration.'
        ];
    }
    
    // Check for exposed .env file
    $envCheck = @file_get_contents("http://{$domain}/.env", false, stream_context_create([
        'http' => ['timeout' => 5],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]));
    
    if ($envCheck !== false && (strpos($envCheck, 'DB_') !== false || strpos($envCheck, 'APP_') !== false)) {
        $vulnerabilities[] = [
            'name' => 'Exposed .env File',
            'severity' => 'critical',
            'description' => 'The .env file is accessible, which may contain sensitive information like database credentials and API keys.',
            'recommendation' => 'Move the .env file outside the web root or configure your web server to block access to it.'
        ];
    }
    
    // Check for common admin paths
    $adminPaths = [
        '/admin',
        '/wp-admin',
        '/administrator',
        '/backend',
        '/manager',
        '/admin.php',
        '/admin/login',
        '/admin/login.php'
    ];
    
    foreach ($adminPaths as $path) {
        $url = "http://{$domain}{$path}";
        $headers = @get_headers($url);
        
        if ($headers && strpos($headers[0], '200') !== false) {
            $vulnerabilities[] = [
                'name' => 'Exposed Admin Interface',
                'severity' => 'medium',
                'description' => "An admin interface was found at: {$url}",
                'recommendation' => 'Restrict access to admin interfaces using IP whitelisting or authentication.'
            ];
            break;
        }
    }
    
    return $vulnerabilities;
}

/**
 * Checks for common directories
 * 
 * @param string $domain The domain to check
 * @return array Directory information
 */
function checkCommonDirectories($domain) {
    $directories = [
        'admin' => 'Admin Panel',
        'wp-admin' => 'WordPress Admin',
        'administrator' => 'Joomla Admin',
        'backup' => 'Backup Directory',
        'cgi-bin' => 'CGI Bin',
        'config' => 'Configuration Files',
        'database' => 'Database Backups',
        'doc' => 'Documentation',
        'docs' => 'Documentation',
        'includes' => 'Includes',
        'install' => 'Installation Files',
        'logs' => 'Log Files',
        'phpmyadmin' => 'phpMyAdmin',
        'server-status' => 'Server Status',
        'tmp' => 'Temporary Files',
        'uploads' => 'Uploads Directory',
        'vendor' => 'Vendor Files',
        'wp-content' => 'WordPress Content'
    ];
    
    $found = [];
    
    foreach ($directories as $dir => $description) {
        $url = "http://{$domain}/{$dir}";
        $headers = @get_headers($url);
        
        if ($headers && strpos($headers[0], '200') !== false) {
            $found[] = [
                'path' => $dir,
                'url' => $url,
                'description' => $description,
                'status' => 'found'
            ];
        } else {
            $found[] = [
                'path' => $dir,
                'url' => $url,
                'description' => $description,
                'status' => 'not_found'
            ];
        }
    }
    
    return $found;
}

/**
 * Analyzes page content and extracts forms
 * 
 * @param string $domain The domain to analyze
 * @return array Page analysis results
 */
function analyzePageContent($domain) {
    $result = [
        'title' => '',
        'description' => '',
        'keywords' => '',
        'forms' => [],
        'links' => [],
        'images' => [],
        'word_count' => 0,
        'has_contact_form' => false,
        'has_search_form' => false,
        'has_login_form' => false
    ];
    
    try {
        $url = "http://{$domain}";
        $html = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 10],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]));
        
        if ($html !== false) {
            // Create a DOMDocument object
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            
            // Get page title
            $title = $dom->getElementsByTagName('title');
            if ($title->length > 0) {
                $result['title'] = trim($title->item(0)->nodeValue);
            }
            
            // Get meta description
            $metas = $dom->getElementsByTagName('meta');
            foreach ($metas as $meta) {
                if ($meta->getAttribute('name') === 'description') {
                    $result['description'] = $meta->getAttribute('content');
                }
                if ($meta->getAttribute('name') === 'keywords') {
                    $result['keywords'] = $meta->getAttribute('content');
                }
            }
            
            // Count words in the visible text
            $text = strip_tags($html);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            $result['word_count'] = str_word_count($text);
            
            // Find all forms
            $forms = $dom->getElementsByTagName('form');
            foreach ($forms as $form) {
                $formData = [
                    'action' => $form->getAttribute('action') ?: $url,
                    'method' => strtoupper($form->getAttribute('method') ?: 'GET'),
                    'inputs' => [],
                    'has_password' => false,
                    'has_file_upload' => false,
                    'has_hidden_fields' => false
                ];
                
                // Check if this is a login form
                $formHtml = $dom->saveHTML($form);
                $isLoginForm = (stripos($formHtml, 'login') !== false || 
                              stripos($formHtml, 'signin') !== false ||
                              stripos($formHtml, 'log in') !== false);
                
                if ($isLoginForm) {
                    $result['has_login_form'] = true;
                }
                
                // Find all input fields
                $inputs = $form->getElementsByTagName('input');
                foreach ($inputs as $input) {
                    $type = strtolower($input->getAttribute('type') ?: 'text');
                    $name = $input->getAttribute('name') ?: 'input_' . uniqid();
                    $value = $input->getAttribute('value') ?: '';
                    $required = $input->hasAttribute('required');
                    
                    $formData['inputs'][] = [
                        'type' => $type,
                        'name' => $name,
                        'value' => $value,
                        'required' => $required
                    ];
                    
                    // Check for password fields
                    if ($type === 'password') {
                        $formData['has_password'] = true;
                    }
                    
                    // Check for file uploads
                    if ($type === 'file') {
                        $formData['has_file_upload'] = true;
                    }
                    
                    // Check for hidden fields
                    if ($type === 'hidden') {
                        $formData['has_hidden_fields'] = true;
                    }
                }
                
                // Find all textareas
                $textareas = $form->getElementsByTagName('textarea');
                foreach ($textareas as $textarea) {
                    $name = $textarea->getAttribute('name') ?: 'textarea_' . uniqid();
                    $value = $textarea->nodeValue;
                    $required = $textarea->hasAttribute('required');
                    
                    $formData['inputs'][] = [
                        'type' => 'textarea',
                        'name' => $name,
                        'value' => $value,
                        'required' => $required
                    ];
                }
                
                // Find all select elements
                $selects = $form->getElementsByTagName('select');
                foreach ($selects as $select) {
                    $name = $select->getAttribute('name') ?: 'select_' . uniqid();
                    $options = [];
                    
                    foreach ($select->getElementsByTagName('option') as $option) {
                        $options[] = [
                            'value' => $option->getAttribute('value'),
                            'text' => $option->nodeValue,
                            'selected' => $option->hasAttribute('selected')
                        ];
                    }
                    
                    $formData['inputs'][] = [
                        'type' => 'select',
                        'name' => $name,
                        'options' => $options,
                        'multiple' => $select->hasAttribute('multiple')
                    ];
                }
                
                // Check if this is a contact form
                $isContactForm = (stripos($formHtml, 'contact') !== false || 
                                 stripos($formHtml, 'message') !== false ||
                                 stripos($formHtml, 'email') !== false);
                
                if ($isContactForm) {
                    $result['has_contact_form'] = true;
                }
                
                // Check if this is a search form
                $isSearchForm = (stripos($formHtml, 'search') !== false || 
                                $form->getAttribute('role') === 'search' ||
                                $form->getAttribute('class') === 'search');
                
                if ($isSearchForm) {
                    $result['has_search_form'] = true;
                }
                
                $result['forms'][] = $formData;
            }
            
            // Find all links
            $links = $dom->getElementsByTagName('a');
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->nodeValue);
                
                if ($href) {
                    $result['links'][] = [
                        'url' => $href,
                        'text' => $text ?: '[No text]',
                        'is_external' => (strpos($href, 'http') === 0 && strpos($href, $domain) === false),
                        'nofollow' => ($link->getAttribute('rel') === 'nofollow')
                    ];
                }
            }
            
            // Find all images
            $images = $dom->getElementsByTagName('img');
            foreach ($images as $img) {
                $src = $img->getAttribute('src');
                $alt = $img->getAttribute('alt');
                
                if ($src) {
                    $result['images'][] = [
                        'src' => $src,
                        'alt' => $alt ?: '',
                        'width' => $img->getAttribute('width') ?: null,
                        'height' => $img->getAttribute('height') ?: null,
                        'has_alt' => !empty($alt)
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Silently handle errors
    }
    
    return $result;
}

/**
 * Generates domain name suggestions
 * 
 * @param string $domain The original domain
 * @return array List of suggested domains
 */
function getDomainSuggestions($domain) {
    $suggestions = [];
    $tlds = ['.com', '.net', '.org', '.io', '.co', '.biz', '.info'];
    $parts = explode('.', $domain);
    $name = $parts[0];
    
    // Suggest different TLDs
    foreach ($tlds as $tld) {
        if (!in_array($tld, $parts)) {
            $suggestedDomain = $name . $tld;
            $suggestions[] = [
                'domain' => $suggestedDomain,
                'available' => checkDomainAvailability($suggestedDomain)['available']
            ];
        }
    }
    
    // Suggest common variations
    $variations = [
        'get' . ucfirst($name),
        'my' . ucfirst($name),
        $name . 'app',
        $name . 'hq',
        $name . 'online',
        'the' . $name,
        $name . 'inc',
        $name . 'co'
    ];
    
    foreach ($variations as $variation) {
        $suggestedDomain = $variation . '.com';
        $suggestions[] = [
            'domain' => $suggestedDomain,
            'available' => checkDomainAvailability($suggestedDomain)['available']
        ];
    }
    
    return array_slice($suggestions, 0, 10); // Return max 10 suggestions
}

/**
 * Parses WHOIS data into a structured format
 * 
 * @param string $whoisRaw Raw WHOIS data
 * @return array Parsed WHOIS information
 */
function parseWhoisData($whoisRaw) {
    $whois = [
        'created_date' => null,
        'updated_date' => null,
        'expires_date' => null,
        'registrar' => null,
        'name_servers' => [],
        'status' => []
    ];
    
    // Parse creation date
    if (preg_match('/Creation Date: (.+?)\n/', $whoisRaw, $matches)) {
        $whois['created_date'] = date('Y-m-d', strtotime($matches[1]));
    } elseif (preg_match('/Created On: (.+?)\n/', $whoisRaw, $matches)) {
        $whois['created_date'] = date('Y-m-d', strtotime($matches[1]));
    } elseif (preg_match('/created:\s+(.+?)\n/i', $whoisRaw, $matches)) {
        $whois['created_date'] = date('Y-m-d', strtotime($matches[1]));
    }
    
    // Parse updated date
    if (preg_match('/Updated Date: (.+?)\n/', $whoisRaw, $matches)) {
        $whois['updated_date'] = date('Y-m-d', strtotime($matches[1]));
    } elseif (preg_match('/Last Updated On: (.+?)\n/', $whoisRaw, $matches)) {
        $whois['updated_date'] = date('Y-m-d', strtotime($matches[1]));
    } elseif (preg_match('/changed:\s+(.+?)\n/i', $whoisRaw, $matches)) {
        $whois['updated_date'] = date('Y-m-d', strtotime($matches[1]));
    }
    
    // Parse expiration date
    if (preg_match('/Expir(?:y|ation) Date: (.+?)\n/', $whoisRaw, $matches)) {
        $whois['expires_date'] = date('Y-m-d', strtotime($matches[1]));
    } elseif (preg_match('/Registry Expiry Date: (.+?)\n/', $whoisRaw, $matches)) {
        $whois['expires_date'] = date('Y-m-d', strtotime($matches[1]));
    } elseif (preg_match('/expire:\s+(.+?)\n/i', $whoisRaw, $matches)) {
        $whois['expires_date'] = date('Y-m-d', strtotime($matches[1]));
    }
    
    // Parse registrar
    if (preg_match('/Registrar: (.+?)\n/', $whoisRaw, $matches)) {
        $whois['registrar'] = trim($matches[1]);
    } elseif (preg_match('/Registrar:\s+(.+?)\n/', $whoisRaw, $matches)) {
        $whois['registrar'] = trim($matches[1]);
    }
    
    // Parse name servers
    if (preg_match_all('/Name Server: (.+?)\n/', $whoisRaw, $matches)) {
        $whois['name_servers'] = array_map('trim', $matches[1]);
    } elseif (preg_match_all('/nserver:\s+(.+?)\n/i', $whoisRaw, $matches)) {
        $whois['name_servers'] = array_map('trim', $matches[1]);
    }
    
    // Parse domain status
    if (preg_match_all('/Domain Status: (.+?)\n/', $whoisRaw, $matches)) {
        $whois['status'] = array_map('trim', $matches[1]);
    } elseif (preg_match_all('/status:\s+(.+?)\n/i', $whoisRaw, $matches)) {
        $whois['status'] = array_map('trim', $matches[1]);
    }
    
    return $whois;
}
