<?php
// lib.php - Core functions for the technology detector

/**
 * Normalize URL by ensuring it has a scheme (http/https)
 */
function normalizeUrl(string $url): string {
    $url = trim($url);
    if (empty($url)) {
        return '';
    }
    
    // Add http:// if no scheme is present
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    
    // Parse URL to ensure it's valid
    $parsed = parse_url($url);
    if ($parsed === false) {
        return '';
    }
    
    // Rebuild URL with proper components
    $scheme = $parsed['scheme'] ?? 'http';
    $host = $parsed['host'] ?? '';
    $path = $parsed['path'] ?? '/';
    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
    $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
    
    // Rebuild URL with only the components we want
    return "$scheme://$host$path$query$fragment";
}

/**
 * Basic URL validation.
 */
function isValidUrl(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Check if a hostname resolves to a private IP range.
 * Returns true if host is private/loopback/reserved (block it).
 */
function isPrivateHost(string $host): bool {
    // Prevent direct IP in host that is private
    $ip = @gethostbyname($host);
    if ($ip === $host) {
        // DNS resolution failed; treat as non-private (but later fetch may fail)
        return false;
    }
    return isPrivateIp($ip);
}

/**
 * Check IPv4/IPv6 for private ranges.
 */
function isPrivateIp(string $ip): bool {
    // IPv4 ranges
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $long = ip2long($ip);
        $privateRanges = [
            ['low'=>ip2long('10.0.0.0'), 'high'=>ip2long('10.255.255.255')],
            ['low'=>ip2long('172.16.0.0'), 'high'=>ip2long('172.31.255.255')],
            ['low'=>ip2long('192.168.0.0'), 'high'=>ip2long('192.168.255.255')],
            ['low'=>ip2long('127.0.0.0'), 'high'=>ip2long('127.255.255.255')],
            ['low'=>ip2long('169.254.0.0'), 'high'=>ip2long('169.254.255.255')], // link-local
        ];
        foreach ($privateRanges as $r) {
            if ($long >= $r['low'] && $long <= $r['high']) return true;
        }
        return false;
    }

    // IPv6 simple checks (loopback, unique local addresses)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // ::1 is loopback
        if ($ip === '::1') return true;
        // Unique local addresses fc00::/7
        if (stripos($ip, 'fc') === 0 || stripos($ip, 'fd') === 0) return true;
        return false;
    }

    // Unknown: treat as safe (not private)
    return false;
}

/**
 * Fetch a URL using cURL, return headers (assoc) and body.
 * Options: timeout, maxRedirects
 */
function fetchUrl(string $url, array $options = []): array {
    $timeout = $options['timeout'] ?? 8;
    $maxRedirects = $options['maxRedirects'] ?? 5;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => $maxRedirects,
        CURLOPT_CONNECTTIMEOUT => (int)$timeout,
        CURLOPT_TIMEOUT => (int)$timeout,
        CURLOPT_USERAGENT => 'TechDetectorBot/1.0 (+https://example.local)',
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // don't include the body size limit here; rely on timeout
    ]);

    $raw = curl_exec($ch);
    $err = null;
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['error' => $err, 'headers' => null, 'body' => null];
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerText = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    // Parse headers into associative array (handles multiple header blocks - take last)
    $headers = parseHttpHeaders($headerText);

    curl_close($ch);
    return ['error' => null, 'status' => $status, 'headers' => $headers, 'body' => $body];
}

/**
 * Parse raw header text into associative array.
 */
function parseHttpHeaders(string $headerText): array {
    $blocks = preg_split('/\r\n\r\n|\n\n/', trim($headerText));
    $last = end($blocks);
    $lines = preg_split('/\r\n|\n|\r/', $last);
    $headers = [];
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $k = trim($k);
            $v = trim($v);
            if (isset($headers[$k])) {
                if (is_array($headers[$k])) {
                    $headers[$k][] = $v;
                } else {
                    $headers[$k] = [$headers[$k], $v];
                }
            } else {
                $headers[$k] = $v;
            }
        } else {
            // status line e.g. HTTP/1.1 200 OK
            if (!isset($headers['Status'])) $headers['Status'] = $line;
        }
    }
    return $headers;
}

/**
 * Load rules.json and run detection.
 * Rules format JSON example:
 * {
 *   "WordPress": [{"type":"html","pattern":"wp-content"},{"type":"header","pattern":"X-Powered-By: WordPress"}],
 *   ...
 * }
 *
 * Supports types: html, header, urlpath, cookie
 */
/**
 * Detect technologies based on rules from rules.json
 * 
 * @param string|null $html The HTML content to scan
 * @param array $headers HTTP headers as key-value pairs
 * @param string $url The URL being scanned
 * @return array Array of detected technologies
 */
function detectTechnologiesFromRules(?string $html, array $headers, string $url): array {
    $rulesFile = __DIR__ . '/../rules.json';
    if (!is_readable($rulesFile)) {
        error_log("Rules file not found or not readable: $rulesFile");
        return [];
    }

    $json = file_get_contents($rulesFile);
    $rules = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error parsing rules.json: " . json_last_error_msg());
        return [];
    }
    
    if (!is_array($rules)) {
        error_log("Rules file does not contain a valid JSON object");
        return [];
    }
    
    // Store detection details
    $detected = [];
    $found = [];
    $headersLower = [];
    
    // Pre-process headers to lowercase keys for case-insensitive comparison
    foreach ($headers as $key => $value) {
        $headersLower[strtolower($key)] = $value;
    }

    // Get all headers as a single string for searching
    $allHeaders = '';
    foreach ($headers as $key => $value) {
        $allHeaders .= "$key: " . (is_array($value) ? implode(', ', $value) : $value) . "\n";
    }

    // Normalize URL path
    $urlPath = parse_url($url, PHP_URL_PATH) ?: '/';
    $urlQuery = parse_url($url, PHP_URL_QUERY) ?: '';
    
    // Get cookies
    $cookies = '';
    if (!empty($headersLower['set-cookie'])) {
        $cookies = is_array($headersLower['set-cookie']) 
            ? implode('; ', $headersLower['set-cookie']) 
            : $headersLower['set-cookie'];
    }

    foreach ($rules as $tech => $patterns) {
        if (!is_array($patterns)) {
            error_log("Invalid patterns for technology: $tech");
            continue;
        }

        foreach ($patterns as $p) {
            if (!is_array($p)) {
                error_log("Invalid pattern for $tech: " . print_r($p, true));
                continue;
            }

            $type = $p['type'] ?? 'html';
            $pattern = $p['pattern'] ?? '';
            $caseInsensitive = $p['i'] ?? true;
            $matchFunction = $caseInsensitive ? 'stripos' : 'strpos';

            if (empty($pattern)) {
                continue;
            }

            $matched = false;

            try {
                switch ($type) {
                    case 'html':
                        if ($html !== null) {
                            $matched = $matchFunction($html, $pattern) !== false;
                        }
                        break;

                    case 'header':
                        // Check if pattern is in format "Header-Name: value"
                        if (strpos($pattern, ':') !== false) {
                            list($hKey, $hVal) = array_map('trim', explode(':', $pattern, 2));
                            $hKey = strtolower($hKey);
                            
                            if (isset($headersLower[$hKey])) {
                                $headerValue = is_array($headersLower[$hKey]) 
                                    ? implode(', ', $headersLower[$hKey])
                                    : $headersLower[$hKey];
                                
                                $matched = $caseInsensitive 
                                    ? stripos($headerValue, $hVal) !== false
                                    : strpos($headerValue, $hVal) !== false;
                            }
                        } else {
                            // Search in all headers
                            $matched = $matchFunction($allHeaders, $pattern) !== false;
                        }
                        break;

                    case 'urlpath':
                        $matched = $matchFunction($urlPath, $pattern) !== false ||
                                 $matchFunction($urlQuery, $pattern) !== false;
                        break;

                    case 'cookie':
                        if (!empty($cookies)) {
                            $matched = $matchFunction($cookies, $pattern) !== false;
                        }
                        break;

                    case 'regex':
                        if ($html !== null) {
                            $flags = $caseInsensitive ? 'i' : '';
                            $matched = @preg_match("/$pattern/$flags", $html) === 1;
                        }
                        break;
                }

                if ($matched) {
                    $found[] = $tech;
                    
                    // Store detection details
                    if (!isset($detected[$tech])) {
                        $detected[$tech] = [
                            'confidence' => 100, // Default confidence
                            'found_in' => [],
                            'patterns' => []
                        ];
                    }
                    
                    // Add where it was found
                    $location = match($type) {
                        'html' => 'HTML content',
                        'header' => 'HTTP Headers',
                        'urlpath' => 'URL Path',
                        'cookie' => 'Cookies',
                        'regex' => 'Content (regex)',
                        default => 'Unknown'
                    };
                    
                    if (!in_array($location, $detected[$tech]['found_in'])) {
                        $detected[$tech]['found_in'][] = $location;
                    }
                    
                    // Store the matching pattern
                    $detected[$tech]['patterns'][] = [
                        'type' => $type,
                        'pattern' => $pattern,
                        'location' => $location,
                        'case_insensitive' => $caseInsensitive
                    ];
                    
                    break; // Move to next technology after first match
                }
            } catch (Exception $e) {
                error_log("Error checking $tech pattern: " . $e->getMessage());
                continue;
            }
        }
    }

    // Return unique, sorted results
    $found = array_values(array_unique($found));
    sort($found);
    
    // If we found nothing, try to detect from common patterns
    if (empty($found) && $html !== null) {
        $commonPatterns = detectFromCommonPatterns($html, $headersLower, $url);
        foreach ($commonPatterns as $commonTech) {
            if (!isset($detected[$commonTech])) {
                $detected[$commonTech] = [
                    'confidence' => 80, // Lower confidence for common patterns
                    'found_in' => ['Common Patterns'],
                    'patterns' => []
                ];
            }
        }
        $found = array_merge($found, $commonPatterns);
    }
    
    // Return both simple and detailed results
    return [
        'technologies' => $found,
        'details' => $detected
    ];
}

/**
 * Extract internal links from HTML content
 */
function findInternalLinks($html, $baseUrl) {
    $internalLinks = [];
    
    // Create DOMDocument and suppress warnings for malformed HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR);
    
    // Get all links
    $links = $dom->getElementsByTagName('a');
    $baseHost = parse_url($baseUrl, PHP_URL_HOST);
    $basePath = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
    
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (empty($href) || $href === '#' || strpos($href, 'javascript:') === 0) {
            continue;
        }
        
        // Parse the URL
        $url = parse_url($href);
        
        // Skip if not a relative path or same domain
        if (isset($url['host']) && $url['host'] !== $baseHost) {
            continue;
        }
        
        // Handle relative URLs
        $path = $url['path'] ?? '/';
        if (strpos($path, '/') !== 0) {
            $path = rtrim(dirname($basePath), '/') . '/' . $path;
        }
        
        // Clean up the path
        $path = '/' . ltrim($path, '/');
        
        // Add to internal links if not already present
        if (!in_array($path, $internalLinks)) {
            $internalLinks[] = $path;
        }
    }
    
    return $internalLinks;
}

/**
 * Check if directory listing is enabled for a URL
 * Get HTTP status text from status code
 */
function getHttpStatusText(int $status): string {
    $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    return $statusTexts[$status] ?? 'Unknown Status';
}

/**
 * Check if directory listing is enabled for a URL
 */
function checkDirectoryListing($url) {
    $url = rtrim($url, '/') . '/';
    $result = [
        'enabled' => false,
        'files' => [],
        'directories' => []
    ];
    
    $response = fetchUrl($url, ['timeout' => 5]);
    
    if ($response['error']) {
        return $result;
    }
    
    // Simple check for directory listing
    if (preg_match('/<title>Index of .*<\/title>/i', $response['body'])) {
        $result['enabled'] = true;
        
        // Parse directory listing (simple version)
        if (preg_match_all('/<a href="([^"]+)"/i', $response['body'], $matches)) {
            foreach ($matches[1] as $item) {
                $item = trim($item);
                // Skip parent directory and self-references
                if (in_array($item, ['../', './', '/', ''])) {
                    continue;
                }
                
                // Remove query strings and hashes
                $item = strtok($item, '?#');
                
                // Categorize as file or directory
                if (substr($item, -1) === '/') {
                    $result['directories'][] = rtrim($item, '/');
                } else {
                    $result['files'][] = $item;
                }
            }
        }
    }
    
    return $result;
}

function detectFromCommonPatterns(string $html, array $headers, string $url): array {
    $detected = [];
    
    // Check for common meta tags
    if (stripos($html, '<meta name="generator"') !== false) {
        if (preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']+)/i', $html, $matches)) {
            $detected[] = trim($matches[1]);
        }
    }
    
    // Check for common framework indicators
    if (stripos($html, 'react') !== false) {
        $detected[] = 'React';
    }
    if (stripos($html, 'vue') !== false) {
        $detected[] = 'Vue.js';
    }
    if (stripos($html, 'angular') !== false) {
        $detected[] = 'Angular';
    }
    
    // Check for common server headers
    foreach ($headers as $key => $value) {
        $value = is_array($value) ? implode(' ', $value) : $value;
        
        if (stripos($key, 'x-powered-by') !== false) {
            $detected[] = trim($value);
        } elseif (stripos($key, 'server') !== false) {
            $detected[] = 'Server: ' . trim($value);
        }
    }
    
    return array_unique($detected);
}
