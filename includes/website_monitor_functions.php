<?php
/**
 * Website Monitor Functions
 */

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/lib.php';

/**
 * Fetch the source code of a URL
 * 
 * @param string $url The URL to fetch
 * @return array Array containing the result and HTML content
 */
function fetchWebsiteSource($url) {
    // Trim any whitespace
    $url = trim($url);
    
    // Check if the URL is empty
    if (empty($url)) {
        return [
            'success' => false,
            'message' => 'URL cannot be empty'
        ];
    }
    
    // Clean and trim the URL
    $url = trim($url);
    
    // Remove any spaces within the URL
    $url = str_replace(' ', '', $url);
    
    // Check for duplicate protocols (e.g., https://ttps://)
    if (preg_match('/(https?:\/\/)(?:https?:\/\/)+/i', $url)) {
        // Remove duplicate protocols, keeping only the first one
        $url = preg_replace('/((https?:\/\/).*?)\1+/i', '$1', $url);
    }
    
    // Check for and fix malformed protocols
    if (preg_match('/^(h?t{1,3}ps?|h?t{2}ps?):\/\//i', $url, $matches)) {
        $protocol = strtolower($matches[0]);
        
        // If the protocol is malformed (like 'ttps://'), fix it
        if (!in_array($protocol, ['http://', 'https://'])) {
            $url = 'https' . substr($url, strpos($url, '://'));
        }
    } 
    // Handle protocol-relative URLs (starting with //)
    elseif (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }
    // Handle missing protocol (e.g., example.com)
    elseif (!preg_match('~^https?://~i', $url)) {
        // Check if it starts with :// (missing http)
        if (strpos($url, '://') === 0) {
            $url = 'https' . $url;
        } else {
            // Default to https
            $url = 'https://' . ltrim($url, '/');
        }
    }
    
    // Normalize the URL (remove duplicate slashes, etc.)
    $url = filter_var($url, FILTER_SANITIZE_URL);
    
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return [
            'success' => false,
            'message' => 'Invalid URL format: ' . htmlspecialchars($url)
        ];
    }

    // Prevent private/reserved IPs
    $host = parse_url($url, PHP_URL_HOST);
    if ($host === false || isPrivateHost($host)) {
        return [
            'success' => false,
            'message' => 'Access to private or reserved IP addresses is not allowed'
        ];
    }

    try {
        // Fetch with timeout and redirects
        $fetch = fetchUrl($url, ['timeout' => 10, 'maxRedirects' => 5]);

        if (isset($fetch['error'])) {
            throw new Exception($fetch['error']);
        }

        $html = $fetch['body'] ?? '';
        if (empty($html)) {
            throw new Exception('No content received from the URL');
        }

        return [
            'success' => true,
            'html' => $html,
            'url' => $url
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Failed to fetch source: ' . $e->getMessage()
        ];
    }
}

/**
 * Get all monitored websites for a user
 * 
 * @param int $userId The ID of the user
 * @return array Array of monitored websites
 */
function getUserMonitoredWebsites($userId) {
    // Include database configuration
    require_once __DIR__ . '/../config/database.php';
    
    return fetchAll(
        "SELECT wm.*, 
                (SELECT COUNT(*) FROM website_monitor_logs WHERE monitor_id = wm.id) as check_count,
                (SELECT created_at FROM website_monitor_logs 
                 WHERE monitor_id = wm.id 
                 ORDER BY created_at DESC LIMIT 1) as last_checked
         FROM website_monitors wm 
         WHERE wm.user_id = ? 
         ORDER BY wm.created_at DESC", 
        [$userId]
    );
}

/**
 * Get monitoring statistics for the dashboard
 * 
 * @param int $userId The ID of the user
 * @return array Array containing monitoring statistics
 */
/**
 * Add a website to monitor
 * 
 * @param int $userId The ID of the user
 * @param string $url The URL to monitor
 * @return array Result of the operation
 */
function addWebsiteToMonitor($userId, $url) {
    // Include database configuration
    require_once __DIR__ . '/../config/database.php';
    
    try {
        // Debug: Log the input values
        error_log("Adding website to monitor - User ID: $userId, URL: $url");
        
        // Validate user ID
        if (empty($userId) || !is_numeric($userId)) {
            error_log("Invalid user ID: " . print_r($userId, true));
            return [
                'success' => false,
                'message' => 'Invalid user.'
            ];
        }
        
        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            error_log("Invalid URL: $url");
            return [
                'success' => false,
                'message' => 'Please enter a valid URL (e.g., https://example.com)'
            ];
        }
        
        // Check if the website is already being monitored by this user
        $existing = fetch(
            "SELECT id FROM website_monitors WHERE user_id = ? AND url = ?", 
            [$userId, $url]
        );
        
        if ($existing) {
            error_log("Website already being monitored: $url");
            return [
                'success' => false,
                'message' => 'This website is already being monitored.'
            ];
        }
        
        // Add the website to monitoring
        $result = insert('website_monitors', [
            'user_id' => $userId,
            'url' => $url,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            error_log("Successfully added website to monitoring. ID: $result");
            return [
                'success' => true,
                'message' => 'Website added to monitoring successfully.',
                'id' => $result
            ];
        } else {
            $error = 'Failed to insert into database';
            error_log("Database error: " . $error);
            throw new Exception('Failed to add website to monitoring. ' . $error);
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Remove a website from monitoring
 * 
 * @param int $userId The ID of the user
 * @param int $monitorId The ID of the monitor to remove
 * @return array Result of the operation
 */
function removeWebsiteFromMonitor($userId, $monitorId) {
    // Include database configuration
    require_once __DIR__ . '/../config/database.php';
    
    try {
        // Verify the monitor belongs to the user
        $monitor = fetch(
            "SELECT id FROM website_monitors WHERE id = ? AND user_id = ?", 
            [$monitorId, $userId]
        );
        
        if (!$monitor) {
            return [
                'success' => false,
                'message' => 'Monitor not found or you do not have permission to remove it.'
            ];
        }
        
        // Delete related logs first (due to foreign key constraint)
        query("DELETE FROM website_monitor_logs WHERE monitor_id = ?", [$monitorId]);
        
        // Delete the monitor
        $deleted = query("DELETE FROM website_monitors WHERE id = ?", [$monitorId]);
        
        if ($deleted->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Website removed from monitoring.'
            ];
        } else {
            throw new Exception('Failed to remove website from monitoring.');
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Get monitoring statistics for the dashboard
 * 
 * @param int $userId The ID of the user
 * @return array Array containing monitoring statistics
 */
function getMonitoringStats($userId) {
    $stats = [
        'total_monitors' => 0,
        'up_monitors' => 0,
        'down_monitors' => 0,
        'expiring_ssl' => []
    ];
    
    // Get basic stats
    $result = fetch(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN last_status_code = 200 THEN 1 ELSE 0 END) as up,
            SUM(CASE WHEN last_status_code != 200 OR last_status_code IS NULL THEN 1 ELSE 0 END) as down
         FROM website_monitors 
         WHERE user_id = ? AND is_active = 1", 
        [$userId]
    );
    
    if ($result) {
        $stats['total_monitors'] = (int)$result['total'];
        $stats['up_monitors'] = (int)$result['up'];
        $stats['down_monitors'] = (int)$result['down'];
    }
    
    // Get expiring SSL certificates (within 30 days)
    $thirtyDaysFromNow = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stats['expiring_ssl'] = fetchAll(
        "SELECT wm.url, wml.ssl_valid_until 
         FROM website_monitor_logs wml
         JOIN website_monitors wm ON wml.monitor_id = wm.id
         WHERE wm.user_id = ? 
           AND wml.ssl_valid_until IS NOT NULL
           AND wml.ssl_valid_until < ?
         ORDER BY wml.ssl_valid_until ASC",
        [$userId, $thirtyDaysFromNow]
    );
    
    return $stats;
}

/**
 * Scan a website for technologies and other information
 * 
 * @param string $url The URL to scan
 * @return array Array containing scan results
 */
function scanWebsite($url) {
    // Include required files
    require_once __DIR__ . '/../api/lib.php';
    require_once __DIR__ . '/../api/analysis_functions.php';
    
    // Initialize result array
    $result = [
        'success' => false,
        'message' => '',
        'technologies' => [],
        'seo' => [],
        'performance' => [],
        'security' => []
    ];
    
    try {
        // Validate URL
        $url = normalizeUrl($url);
        if (empty($url) || !isValidUrl($url)) {
            throw new Exception('Invalid URL provided');
        }
        
        // Block private/reserved IPs
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || isPrivateHost($host)) {
            throw new Exception('Cannot scan private or local IP addresses');
        }
        
        // Fetch the URL
        $fetch = fetchUrl($url, ['timeout' => 8, 'maxRedirects' => 5]);
        
        if (isset($fetch['error'])) {
            throw new Exception($fetch['error']);
        }
        
        $headers = $fetch['headers'] ?? [];
        $html = $fetch['body'] ?? '';
        
        // Detect technologies
        $techData = [
            'technologies' => [],
            'details' => []
        ];
        
        // Detect server technology from headers
        if (isset($headers['Server'])) {
            $server = is_array($headers['Server']) ? end($headers['Server']) : $headers['Server'];
            $techData['technologies'][] = $server;
            $techData['details'][$server] = [
                'type' => 'server',
                'confidence' => 100,
                'version' => '',
                'description' => 'Web server software',
                'categories' => ['Web Servers']
            ];
        }
        
        // Detect PHP
        if (strpos($html, '.php') !== false || preg_match('/<\?php/i', $html) || preg_match('/<\?=/', $html)) {
            $techData['technologies'][] = 'PHP';
            $techData['details']['PHP'] = [
                'type' => 'programming-language',
                'confidence' => 90,
                'version' => '',
                'description' => 'Server-side scripting language',
                'categories' => ['Programming Languages']
            ];
        }
        
        // Detect common CMS and frameworks
        $techPatterns = [
            'WordPress' => ['wp-content', 'wp-includes', 'wp-json'],
            'Laravel' => ['mix-manifest.json', 'laravel', 'csrf-token'],
            'React' => ['react.', 'ReactDOM'],
            'Vue.js' => ['vue.', 'v-bind', 'v-model'],
            'Angular' => ['ng-', 'data-ng'],
            'jQuery' => ['jquery', 'jQuery'],
            'Bootstrap' => ['bootstrap', 'data-bs-'],
            'Tailwind CSS' => ['tailwind', 'tw-'],
            'Next.js' => ['__next', 'NextScript'],
            'Nuxt.js' => ['_nuxt', 'nuxt-link'],
            'Drupal' => ['sites/all', 'drupal.js'],
            'Joomla' => ['/media/system/', 'joomla'],
            'Magento' => ['/skin/frontend/', 'Mage.'],
            'Shopify' => ['cdn.shopify.com', 'shopify'],
            'WooCommerce' => ['woocommerce', 'wc-'],
            'Google Analytics' => ['googletagmanager.com/gtag/js', 'ga(', 'gtag('],
            'Google Tag Manager' => ['googletagmanager.com/gtm.js']
        ];
        
        foreach ($techPatterns as $tech => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($html, $pattern) !== false) {
                    if (!in_array($tech, $techData['technologies'])) {
                        $techData['technologies'][] = $tech;
                        $techData['details'][$tech] = [
                            'type' => strpos($tech, '.js') !== false ? 'javascript' : (stripos($tech, 'css') !== false ? 'stylesheet' : 'framework'),
                            'confidence' => 85,
                            'version' => '',
                            'description' => $tech . ' detected',
                            'categories' => ['Frameworks']
                        ];
                    }
                    break;
                }
            }
        }
        
        // Detect hosting provider
        $hostingProviders = [
            'AWS' => ['amazonaws.com', 'cloudfront.net', 's3.amazonaws.com'],
            'Cloudflare' => ['cloudflare.com', 'cloudflaressl.com'],
            'Google Cloud' => ['googleapis.com', 'googlehosted.com', 'gstatic.com'],
            'Microsoft Azure' => ['azurewebsites.net', 'azure.com', 'windows.net'],
            'WP Engine' => ['wpengine.com', 'wpenginepowered.com'],
            'SiteGround' => ['siteground.biz', 'siteground.com'],
            'Bluehost' => ['bluehost.com', 'bluehostcdn.com'],
            'HostGator' => ['hostgator.com', 'hostgator.com.br'],
            'GoDaddy' => ['godaddy.com', 'secureserver.net'],
            'Hostinger' => ['hostinger.com', 'hstgr.io']
        ];
        
        $host = parse_url($url, PHP_URL_HOST);
        foreach ($hostingProviders as $provider => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($host, $pattern) !== false || 
                    (isset($headers['Server']) && stripos($headers['Server'], $pattern) !== false)) {
                    if (!in_array($provider, $techData['technologies'])) {
                        $techData['technologies'][] = $provider;
                        $techData['details'][$provider] = [
                            'type' => 'hosting',
                            'confidence' => 90,
                            'version' => '',
                            'description' => 'Hosting provider',
                            'categories' => ['Hosting']
                        ];
                    }
                    break 2;
                }
            }
        }
        
        // If no hosting detected, try to get from IP
        if (!in_array('Hosting', array_column($techData['details'], 'type'))) {
            $ip = gethostbyname($host);
            $ipInfo = @file_get_contents("http://ip-api.com/json/{$ip}?fields=isp,org,as");
            if ($ipInfo) {
                $ipData = json_decode($ipInfo, true);
                if (isset($ipData['isp'])) {
                    $techData['technologies'][] = $ipData['isp'];
                    $techData['details'][$ipData['isp']] = [
                        'type' => 'hosting',
                        'confidence' => 80,
                        'version' => '',
                        'description' => 'Hosting provider detected via IP',
                        'categories' => ['Hosting']
                    ];
                }
            }
        }
        
        // Run basic SEO analysis
        $seoAnalysis = [];
        
        // Check for title
        if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
            $title = trim(html_entity_decode($matches[1]));
            $seoAnalysis['title'] = [
                'status' => 'pass',
                'message' => 'Page has a title',
                'details' => $title,
                'importance' => 'high'
            ];
            
            // Check title length
            $titleLength = mb_strlen($title);
            if ($titleLength < 30) {
                $seoAnalysis['title_length'] = [
                    'status' => 'warning',
                    'message' => 'Title is too short (less than 30 characters)',
                    'details' => "Title length: {$titleLength} characters",
                    'importance' => 'medium'
                ];
            } elseif ($titleLength > 60) {
                $seoAnalysis['title_length'] = [
                    'status' => 'warning',
                    'message' => 'Title is too long (more than 60 characters)',
                    'details' => "Title length: {$titleLength} characters",
                    'importance' => 'medium'
                ];
            } else {
                $seoAnalysis['title_length'] = [
                    'status' => 'pass',
                    'message' => 'Title length is good',
                    'details' => "Title length: {$titleLength} characters",
                    'importance' => 'medium'
                ];
            }
        } else {
            $seoAnalysis['title'] = [
                'status' => 'fail',
                'message' => 'Page is missing a title tag',
                'details' => 'Add a descriptive title tag between 30-60 characters',
                'importance' => 'high'
            ];
        }
        
        // Check for meta description
        if (preg_match('/<meta\s+name=[\'\"]description[\'\"]\s+content=[\'"](.*?)[\'"]/i', $html, $matches)) {
            $description = trim(html_entity_decode($matches[1]));
            $descLength = mb_strlen($description);
            $seoAnalysis['meta_description'] = [
                'status' => 'pass',
                'message' => 'Page has a meta description',
                'details' => $description,
                'importance' => 'high'
            ];
            
            // Check description length
            if ($descLength < 70) {
                $seoAnalysis['description_length'] = [
                    'status' => 'warning',
                    'message' => 'Meta description is too short (less than 70 characters)',
                    'details' => "Description length: {$descLength} characters",
                    'importance' => 'medium'
                ];
            } elseif ($descLength > 160) {
                $seoAnalysis['description_length'] = [
                    'status' => 'warning',
                    'message' => 'Meta description is too long (more than 160 characters)',
                    'details' => "Description length: {$descLength} characters",
                    'importance' => 'medium'
                ];
            } else {
                $seoAnalysis['description_length'] = [
                    'status' => 'pass',
                    'message' => 'Meta description length is good',
                    'details' => "Description length: {$descLength} characters",
                    'importance' => 'medium'
                ];
            }
        } else {
            $seoAnalysis['meta_description'] = [
                'status' => 'warning',
                'message' => 'Page is missing a meta description',
                'details' => 'Add a descriptive meta description between 70-160 characters',
                'importance' => 'high'
            ];
        }
        
        // Check for viewport meta tag
        if (preg_match('/<meta[^>]+name=[\'\"]viewport[\'\"][^>]*>/i', $html)) {
            $seoAnalysis['viewport'] = [
                'status' => 'pass',
                'message' => 'Viewport meta tag is present',
                'details' => 'The viewport meta tag is properly set for mobile devices',
                'importance' => 'high'
            ];
        } else {
            $seoAnalysis['viewport'] = [
                'status' => 'fail',
                'message' => 'Viewport meta tag is missing',
                'details' => 'Add a viewport meta tag to ensure proper mobile rendering',
                'importance' => 'high'
            ];
        }
        
        // Check for h1 tag
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            $h1 = trim(strip_tags($matches[1]));
            $seoAnalysis['h1'] = [
                'status' => 'pass',
                'message' => 'Page has an H1 heading',
                'details' => $h1,
                'importance' => 'high'
            ];
            
            // Check for multiple h1 tags
            $h1Count = preg_match_all('/<h1[^>]*>/i', $html);
            if ($h1Count > 1) {
                $seoAnalysis['h1_count'] = [
                    'status' => 'warning',
                    'message' => 'Multiple H1 headings found',
                    'details' => "Found {$h1Count} H1 tags. It's recommended to have only one H1 per page.",
                    'importance' => 'medium'
                ];
            }
        } else {
            $seoAnalysis['h1'] = [
                'status' => 'warning',
                'message' => 'Page is missing an H1 heading',
                'details' => 'Add an H1 heading to improve SEO',
                'importance' => 'high'
            ];
        }
        
        // Check for images without alt text
        $imagesWithoutAlt = [];
        if (preg_match_all('/<img\s+[^>]*alt=[\'\"]([^\'"]*)[\'\"][^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
            $totalImages = count($matches);
            $imagesWithAlt = 0;
            
            foreach ($matches as $match) {
                if (!empty(trim($match[1]))) {
                    $imagesWithAlt++;
                } else {
                    // Try to get image src for reporting
                    if (preg_match('/src=[\'\"]([^\'"]*)[\'\"]/i', $match[0], $srcMatch)) {
                        $imagesWithoutAlt[] = $srcMatch[1];
                    }
                }
            }
            
            if ($imagesWithAlt < $totalImages) {
                $missingAltCount = $totalImages - $imagesWithAlt;
                $seoAnalysis['image_alt'] = [
                    'status' => 'warning',
                    'message' => "{$missingAltCount} images are missing alt text",
                    'details' => 'Add descriptive alt text to all images for better accessibility and SEO',
                    'importance' => 'medium',
                    'images' => array_slice($imagesWithoutAlt, 0, 5) // Show up to 5 examples
                ];
            } else {
                $seoAnalysis['image_alt'] = [
                    'status' => 'pass',
                    'message' => 'All images have alt text',
                    'details' => "Found {$totalImages} images with alt text",
                    'importance' => 'medium'
                ];
            }
        } else {
            // No images found or no alt attributes at all
            $seoAnalysis['image_alt'] = [
                'status' => 'info',
                'message' => 'No images with alt attributes found',
                'details' => 'If you add images, include descriptive alt text',
                'importance' => 'medium'
            ];
        }
        
        // Check for internal links
        $internalLinks = [];
        $externalLinks = [];
        $baseDomain = parse_url($url, PHP_URL_HOST);
        
        if (preg_match_all('/<a\s+[^>]*href=[\'\"]([^\'\"]*)[\'\"][^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $href = $match[1];
                
                // Skip empty or anchor links
                if (empty($href) || $href === '#' || strpos($href, 'javascript:') === 0) {
                    continue;
                }
                
                // Parse the URL
                $urlParts = parse_url($href);
                
                // Handle relative URLs
                if (!isset($urlParts['host'])) {
                    $internalLinks[] = $href;
                    continue;
                }
                
                // Check if it's an internal or external link
                if (str_ends_with($urlParts['host'], $baseDomain) || $urlParts['host'] === $baseDomain) {
                    $internalLinks[] = $href;
                } else {
                    $externalLinks[] = $href;
                }
            }
            
            $totalLinks = count($internalLinks) + count($externalLinks);
            $seoAnalysis['links'] = [
                'status' => 'info',
                'message' => "Found {$totalLinks} links ({count($internalLinks)} internal, {count($externalLinks)} external)",
                'details' => 'Links help search engines discover and understand your site structure',
                'importance' => 'low',
                'internal_links' => array_slice($internalLinks, 0, 5),
                'external_links' => array_slice($externalLinks, 0, 5)
            ];
        } else {
            $seoAnalysis['links'] = [
                'status' => 'warning',
                'message' => 'No links found on the page',
                'details' => 'Consider adding internal links to help with site navigation and SEO',
                'importance' => 'medium'
            ];
        }
        
        // Basic performance analysis
        $performanceAnalysis = [];
        
        // Check for render-blocking resources
        $renderBlocking = [];
        if (preg_match_all('/<link\s+[^>]*rel=[\'\"]stylesheet[\'\"][^>]*>/i', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                if (strpos($tag, 'media=') === false || strpos($tag, 'media="all"') !== false) {
                    $renderBlocking[] = 'CSS: ' . (preg_match('/href=[\'\"]([^\'"]*)[\'\"]/i', $tag, $href) ? $href[1] : 'Unknown CSS');
                }
            }
        }
        
        if (preg_match_all('/<script\s+[^>]*src=[\'\"]([^\'"]*)[\'\"][^>]*>\s*<\/script>/i', $html, $matches)) {
            foreach ($matches[0] as $i => $tag) {
                if (strpos($tag, 'defer') === false && strpos($tag, 'async') === false) {
                    $renderBlocking[] = 'JS: ' . $matches[1][$i];
                }
            }
        }
        
        if (!empty($renderBlocking)) {
            $performanceAnalysis['render_blocking'] = [
                'status' => 'warning',
                'message' => 'Render-blocking resources detected',
                'details' => 'The following resources may block page rendering:',
                'importance' => 'high',
                'blocking_resources' => array_slice($renderBlocking, 0, 5)
            ];
        } else {
            $performanceAnalysis['render_blocking'] = [
                'status' => 'pass',
                'message' => 'No render-blocking resources detected',
                'details' => 'All CSS and JS are loaded asynchronously or deferred',
                'importance' => 'high'
            ];
        }
        
        // Check for image optimization
        $largeImages = [];
        if (preg_match_all('/<img[^>]+src=[\'\"]([^\'\"]+)[\'\"][^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Check for width and height attributes
                $hasDimensions = (strpos($match[0], 'width=') !== false && strpos($match[0], 'height=') !== false);
                $src = $match[1];
                
                // Skip data URIs and SVGs
                if (strpos($src, 'data:') === 0 || strtolower(substr($src, -4)) === '.svg') {
                    continue;
                }
                
                // Check if it's a large image (in a very basic way)
                if (preg_match('/\.(jpg|jpeg|png|webp|gif)(?:[?#]|$)/i', $src)) {
                    $largeImages[] = [
                        'src' => $src,
                        'has_dimensions' => $hasDimensions,
                        'size' => null // We can't get the actual size without downloading
                    ];
                }
            }
            
            if (!empty($largeImages)) {
                $imagesWithoutDims = array_filter($largeImages, function($img) {
                    return !$img['has_dimensions'];
                });
                
                if (!empty($imagesWithoutDims)) {
                    $performanceAnalysis['image_dimensions'] = [
                        'status' => 'warning',
                        'message' => 'Some images are missing width and height attributes',
                        'details' => 'Specifying image dimensions prevents layout shifts during page load',
                        'importance' => 'medium',
                        'images' => array_slice(array_column($imagesWithoutDims, 'src'), 0, 3)
                    ];
                }
                
                // Check for potential optimization opportunities
                $unoptimizedFormats = [];
                foreach ($largeImages as $img) {
                    if (preg_match('/\.(jpg|jpeg|png|gif)(?:[?#]|$)/i', $img['src'])) {
                        $unoptimizedFormats[] = $img['src'];
                    }
                }
                
                if (!empty($unoptimizedFormats)) {
                    $performanceAnalysis['image_format'] = [
                        'status' => 'info',
                        'message' => 'Consider using modern image formats',
                        'details' => 'WebP or AVIF formats typically provide better compression than JPG/PNG',
                        'importance' => 'medium',
                        'images' => array_slice($unoptimizedFormats, 0, 3)
                    ];
                }
            }
        }
        
        // Basic security analysis
        $securityAnalysis = [];
        
        // Check for HTTPS
        if (strpos($url, 'https://') === 0) {
            $securityAnalysis['https'] = [
                'status' => 'pass',
                'message' => 'Site uses HTTPS',
                'details' => 'The connection to this website is encrypted',
                'importance' => 'high'
            ];
            
            // Check for HSTS
            $hstsHeader = '';
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'strict-transport-security') {
                    $hstsHeader = is_array($value) ? $value[0] : $value;
                    break;
                }
            }
            
            if (!empty($hstsHeader)) {
                $securityAnalysis['hsts'] = [
                    'status' => 'pass',
                    'message' => 'HSTS is enabled',
                    'details' => 'HTTP Strict Transport Security is properly configured',
                    'importance' => 'high'
                ];
                
                // Check for HSTS preload directive
                if (strpos(strtolower($hstsHeader), 'preload') !== false) {
                    $securityAnalysis['hsts_preload'] = [
                        'status' => 'pass',
                        'message' => 'HSTS preload is enabled',
                        'details' => 'Your site can be preloaded by browsers for maximum security',
                        'importance' => 'medium'
                    ];
                }
            } else {
                $securityAnalysis['hsts'] = [
                    'status' => 'warning',
                    'message' => 'HSTS is not enabled',
                    'details' => 'Consider enabling HTTP Strict Transport Security',
                    'importance' => 'high'
                ];
            }
        } else {
            $securityAnalysis['https'] = [
                'status' => 'fail',
                'message' => 'Site does not use HTTPS',
                'details' => 'Switch to HTTPS to encrypt all traffic',
                'importance' => 'critical'
            ];
        }
        
        // Check for security headers
        $securityHeaders = [
            'X-Content-Type-Options' => [
                'recommended' => 'nosniff',
                'description' => 'Prevents MIME type sniffing',
                'importance' => 'high'
            ],
            'X-Frame-Options' => [
                'recommended' => 'DENY or SAMEORIGIN',
                'description' => 'Prevents clickjacking attacks',
                'importance' => 'high'
            ],
            'X-XSS-Protection' => [
                'recommended' => '1; mode=block',
                'description' => 'Enables XSS filtering',
                'importance' => 'medium'
            ],
            'Content-Security-Policy' => [
                'recommended' => 'Present',
                'description' => 'Mitigates XSS and data injection attacks',
                'importance' => 'high'
            ],
            'Referrer-Policy' => [
                'recommended' => 'strict-origin-when-cross-origin',
                'description' => 'Controls referrer information',
                'importance' => 'medium'
            ],
            'Permissions-Policy' => [
                'recommended' => 'Present',
                'description' => 'Controls browser features',
                'importance' => 'medium'
            ]
        ];
        
        foreach ($securityHeaders as $header => $info) {
            $headerFound = false;
            foreach ($headers as $hName => $hValue) {
                if (strtolower($hName) === strtolower($header)) {
                    $headerFound = true;
                    $headerValue = is_array($hValue) ? $hValue[0] : $hValue;
                    
                    $securityAnalysis[strtolower($header)] = [
                        'status' => 'pass',
                        'message' => "{$header} is present",
                        'details' => "{$info['description']}. Current value: {$headerValue}",
                        'importance' => $info['importance']
                    ];
                    break;
                }
            }
            
            if (!$headerFound) {
                $securityAnalysis[strtolower($header)] = [
                    'status' => $info['importance'] === 'high' ? 'warning' : 'info',
                    'message' => "{$header} is missing",
                    'details' => "Recommendation: Add {$header}: {$info['recommended']}",
                    'importance' => $info['importance']
                ];
            }
        }
        
        // Check for server information disclosure
        $serverInfoDisclosure = [];
        $sensitiveServerHeaders = ['Server', 'X-Powered-By', 'X-AspNet-Version', 'X-AspNetMvc-Version'];
        
        foreach ($sensitiveServerHeaders as $header) {
            foreach ($headers as $hName => $hValue) {
                if (strtolower($hName) === strtolower($header)) {
                    $headerValue = is_array($hValue) ? $hValue[0] : $hValue;
                    $serverInfoDisclosure[] = [
                        'header' => $header,
                        'value' => $headerValue
                    ];
                    break;
                }
            }
        }
        
        if (!empty($serverInfoDisclosure)) {
            $securityAnalysis['server_info'] = [
                'status' => 'warning',
                'message' => 'Server information disclosure',
                'details' => 'The following server information is exposed:',
                'importance' => 'medium',
                'disclosed_info' => array_map(function($item) {
                    return "{$item['header']}: {$item['value']}";
                }, $serverInfoDisclosure)
            ];
        }
        
        // Check for common vulnerabilities
        $vulnerabilities = [];
        
        // Check for common admin paths
        $adminPaths = ['/admin', '/wp-admin', '/administrator', '/backend'];
        foreach ($adminPaths as $path) {
            if (strpos($url, $path) !== false) {
                $vulnerabilities[] = [
                    'type' => 'Exposed Admin Panel',
                    'severity' => 'high',
                    'details' => "Admin panel is accessible at: {$path}",
                    'recommendation' => 'Restrict access to admin panels using authentication and IP whitelisting'
                ];
            }
        }
        
        // Check for exposed directory listings
        if (preg_match('/<title>Index of \//i', $html) || 
            preg_match('/<h1>Directory Listing/i', $html) ||
            preg_match('/<h1>Index of \//i', $html)) {
            $vulnerabilities[] = [
                'type' => 'Directory Listing Enabled',
                'severity' => 'medium',
                'details' => 'Directory listing is enabled, which can expose sensitive files',
                'recommendation' => 'Disable directory listing in your web server configuration'
            ];
        }
        
        // Check for common vulnerable files
        $vulnerableFiles = [
            'phpinfo.php' => 'PHP info file that exposes server configuration',
            'test.php' => 'Test file that may expose sensitive information',
            'config.php.bak' => 'Backup configuration file that may contain credentials',
            '.env' => 'Environment file that may contain sensitive information',
            '.git/HEAD' => 'Git directory that may expose source code',
            'wp-config.php' => 'WordPress configuration file that may contain database credentials'
        ];
        
        $baseUrl = rtrim($url, '/');
        foreach ($vulnerableFiles as $file => $description) {
            $testUrl = "{$baseUrl}/{$file}";
            $testResult = @file_get_contents($testUrl, false, stream_context_create([
                'http' => ['timeout' => 5]
            ]));
            
            if ($testResult !== false) {
                $vulnerabilities[] = [
                    'type' => 'Exposed Sensitive File',
                    'severity' => 'high',
                    'details' => "Sensitive file is accessible: {$file} - {$description}",
                    'recommendation' => "Remove or restrict access to: {$file}"
                ];
            }
        }
        
        if (!empty($vulnerabilities)) {
            $securityAnalysis['vulnerabilities'] = [
                'status' => 'fail',
                'message' => 'Potential security vulnerabilities found',
                'details' => 'The following potential security issues were identified:',
                'importance' => 'critical',
                'vulnerabilities' => $vulnerabilities
            ];
        }
        
        // Prepare the result
        $result = [
            'success' => true,
            'message' => 'Scan completed successfully',
            'technologies' => $techData,
            'seo' => $seoAnalysis,
            'performance' => $performanceAnalysis,
            'security' => $securityAnalysis,
            'url' => $url,
            'scan_time' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        $result['message'] = 'Scan failed: ' . $e->getMessage();
    }
    
    return $result;
}

