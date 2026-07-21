<?php
/**
 * Analysis Functions for TechDetector
 * Contains SEO, Performance, and Content Analysis functions
 */

// Helper function to get remote file size
function getRemoteFileSize($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    return $size;
}

// Helper function to check if URL is accessible
function isUrlAccessible($url) {
    $headers = @get_headers($url);
    return $headers && strpos($headers[0], '200');
}

// Helper function to calculate readability scores
function calculateReadability($text) {
    $words = str_word_count($text);
    $sentences = preg_match_all('/[.!?]+/', $text);
    $syllables = preg_match_all('/[aeiouy]+/i', $text);
    
    if ($words > 0 && $sentences > 0) {
        // Flesch Reading Ease
        $flesch = 206.835 - (1.015 * ($words / $sentences)) - (84.6 * ($syllables / $words));
        
        // Flesch-Kincaid Grade Level
        $fleschKincaid = (0.39 * ($words / $sentences)) + (11.8 * ($syllables / $words)) - 15.59;
        
        return [
            'flesch' => round($flesch, 1),
            'flesch_kincaid' => round($fleschKincaid, 1),
            'word_count' => $words,
            'sentence_count' => $sentences,
            'syllable_count' => $syllables
        ];
    }
    
    return [
        'flesch' => 0,
        'flesch_kincaid' => 0,
        'word_count' => 0,
        'sentence_count' => 0,
        'syllable_count' => 0
    ];
}

/**
 * Perform SEO Analysis on a webpage
 */
function analyzeSeo($html, $url) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    
    $result = [
        'meta' => [],
        'headings' => [],
        'sitemap' => null,
        'robots' => null,
        'structured_data' => [],
        'viewport' => false,
        'canonical' => null,
        'og_tags' => [],
        'twitter_tags' => [],
        'has_ssl' => strpos($url, 'https://') === 0,
        'mobile_friendly' => false,
        'social_media' => [
            'facebook' => false,
            'twitter' => false,
            'linkedin' => false,
            'instagram' => false,
            'pinterest' => false
        ]
    ];
    
    // Check meta tags
    $metaTags = $doc->getElementsByTagName('meta');
    foreach ($metaTags as $tag) {
        $name = $tag->getAttribute('name') ?: $tag->getAttribute('property');
        if ($name) {
            $result['meta'][$name] = $tag->getAttribute('content');
        }
    }
    
    // Check headings
    for ($i = 1; $i <= 6; $i++) {
        $headings = $doc->getElementsByTagName('h' . $i);
        $result['headings']['h' . $i] = $headings->length;
    }
    
    // Check viewport
    $viewport = $xpath->query('//meta[@name="viewport"]');
    $result['viewport'] = $viewport->length > 0;
    
    // Check canonical URL
    $canonical = $xpath->query('//link[@rel="canonical"]');
    if ($canonical->length > 0) {
        $result['canonical'] = $canonical->item(0)->getAttribute('href');
    }
    
    // Check Open Graph tags
    $ogTags = $xpath->query('//meta[starts-with(@property, "og:")]');
    foreach ($ogTags as $tag) {
        $property = $tag->getAttribute('property');
        $content = $tag->getAttribute('content');
        if ($property && $content) {
            $result['og_tags'][$property] = $content;
        }
    }
    
    // Check Twitter Card tags
    $twitterTags = $xpath->query('//meta[starts-with(@name, "twitter:")]');
    foreach ($twitterTags as $tag) {
        $name = $tag->getAttribute('name');
        $content = $tag->getAttribute('content');
        if ($name && $content) {
            $result['twitter_tags'][$name] = $content;
        }
    }
    
    // Check for sitemap
    $sitemapUrl = rtrim($url, '/') . '/sitemap.xml';
    if (isUrlAccessible($sitemapUrl)) {
        $result['sitemap'] = $sitemapUrl;
    }
    
    // Check robots.txt
    $robotsUrl = rtrim($url, '/') . '/robots.txt';
    $robotsCheck = @file_get_contents($robotsUrl);
    if ($robotsCheck !== false) {
        $result['robots'] = $robotsUrl;
        
        // Check for mobile user agent in robots.txt
        $result['mobile_friendly'] = stripos($robotsCheck, 'mobile') !== false || 
                                    stripos($robotsCheck, 'Mobile') !== false ||
                                    stripos($robotsCheck, 'MOBILE') !== false;
    }
    
    // Check for social media links in the page
    $socialLinks = $xpath->query('//a[contains(@href, "facebook.com") or contains(@href, "twitter.com") or contains(@href, "linkedin.com") or contains(@href, "instagram.com") or contains(@href, "pinterest.com")]');
    foreach ($socialLinks as $link) {
        $href = $link->getAttribute('href');
        if (strpos($href, 'facebook.com') !== false) $result['social_media']['facebook'] = true;
        if (strpos($href, 'twitter.com') !== false) $result['social_media']['twitter'] = true;
        if (strpos($href, 'linkedin.com') !== false) $result['social_media']['linkedin'] = true;
        if (strpos($href, 'instagram.com') !== false) $result['social_media']['instagram'] = true;
        if (strpos($href, 'pinterest.com') !== false) $result['social_media']['pinterest'] = true;
    }
    
    // Check for structured data
    $jsonLd = $xpath->query('//script[@type="application/ld+json"]');
    foreach ($jsonLd as $item) {
        $result['structured_data'][] = json_decode($item->nodeValue, true);
    }
    
    return $result;
}

/**
 * Format SEO analysis results for display
 */
function formatSeoResults($analysis) {
    if (!is_array($analysis)) {
        return [
            'score' => 0,
            'checks' => []
        ];
    }
    
    // Initialize score and checks
    $score = 100;
    $checks = [];
    
    // Check for title
    $hasTitle = !empty($analysis['meta']['title']);
    $titleLength = $hasTitle ? strlen($analysis['meta']['title']) : 0;
    $titleStatus = 'pass';
    $titleMessage = 'Page has a title';
    
    if (!$hasTitle) {
        $score -= 15;
        $titleStatus = 'fail';
        $titleMessage = 'Missing title tag';
    } elseif ($titleLength < 30 || $titleLength > 60) {
        $score -= 5;
        $titleStatus = 'warning';
        $titleMessage = 'Title should be between 30-60 characters';
    }
    
    $checks[] = [
        'title' => 'Page Title',
        'status' => $titleStatus,
        'value' => $hasTitle ? htmlspecialchars($analysis['meta']['title']) : 'Missing',
        'recommendation' => $titleMessage
    ];
    
    // Check meta description
    $hasMetaDesc = !empty($analysis['meta']['description']);
    $descLength = $hasMetaDesc ? strlen($analysis['meta']['description']) : 0;
    $descStatus = 'pass';
    $descMessage = 'Good meta description length';
    
    if (!$hasMetaDesc) {
        $score -= 10;
        $descStatus = 'fail';
        $descMessage = 'Missing meta description';
    } elseif ($descLength < 50 || $descLength > 160) {
        $score -= 3;
        $descStatus = 'warning';
        $descMessage = 'Meta description should be 50-160 characters';
    }
    
    $checks[] = [
        'title' => 'Meta Description',
        'status' => $descStatus,
        'value' => $hasMetaDesc ? 
            (strlen($analysis['meta']['description']) > 60 ? 
                htmlspecialchars(substr($analysis['meta']['description'], 0, 60)) . '...' : 
                htmlspecialchars($analysis['meta']['description'])) : 
            'Missing',
        'recommendation' => $descMessage
    ];
    
    // Check viewport
    $hasViewport = $analysis['viewport'] ?? false;
    if (!$hasViewport) {
        $score -= 10;
        $checks[] = [
            'title' => 'Viewport Meta Tag',
            'status' => 'fail',
            'value' => 'Missing',
            'recommendation' => 'Add viewport meta tag for mobile responsiveness'
        ];
    } else {
        $checks[] = [
            'title' => 'Viewport Meta Tag',
            'status' => 'pass',
            'value' => 'Present',
            'recommendation' => 'Viewport meta tag is properly configured'
        ];
    }
    
    // Check headings structure
    $hasH1 = ($analysis['headings']['h1'] ?? 0) > 0;
    $h1Count = $analysis['headings']['h1'] ?? 0;
    $h1Status = $hasH1 ? 'pass' : 'fail';
    $h1Message = $hasH1 ? 
        ($h1Count > 1 ? 'Multiple H1 tags found' : 'Good, one H1 tag found') : 
        'No H1 tag found';
        
    if (!$hasH1) {
        $score -= 10;
    } elseif ($h1Count > 1) {
        $score -= 5;
        $h1Status = 'warning';
    }
    
    $checks[] = [
        'title' => 'Heading Structure (H1)',
        'status' => $h1Status,
        'value' => $hasH1 ? "$h1Count H1 tag(s) found" : 'No H1 tag',
        'recommendation' => $h1Message
    ];
    
    // Check for SSL
    $hasSSL = $analysis['has_ssl'] ?? false;
    if (!$hasSSL) {
        $score -= 15;
        $checks[] = [
            'title' => 'SSL Certificate',
            'status' => 'fail',
            'value' => 'Not using HTTPS',
            'recommendation' => 'Install an SSL certificate to enable HTTPS'
        ];
    } else {
        $checks[] = [
            'title' => 'SSL Certificate',
            'status' => 'pass',
            'value' => 'Using HTTPS',
            'recommendation' => 'Great! Your site is secured with SSL'
        ];
    }
    
    // Check for Open Graph tags
    $hasOgTags = !empty($analysis['og_tags']);
    if (!$hasOgTags) {
        $score -= 5;
        $checks[] = [
            'title' => 'Open Graph Tags',
            'status' => 'warning',
            'value' => 'Missing',
            'recommendation' => 'Add Open Graph meta tags for better social sharing'
        ];
    } else {
        $checks[] = [
            'title' => 'Open Graph Tags',
            'status' => 'pass',
            'value' => 'Present',
            'recommendation' => 'Open Graph tags are properly configured'
        ];
    }
    
    // Check for sitemap
    $hasSitemap = !empty($analysis['sitemap']);
    if (!$hasSitemap) {
        $score -= 5;
        $checks[] = [
            'title' => 'XML Sitemap',
            'status' => 'warning',
            'value' => 'Not found',
            'recommendation' => 'Create and submit an XML sitemap to search engines'
        ];
    } else {
        $checks[] = [
            'title' => 'XML Sitemap',
            'status' => 'pass',
            'value' => 'Found',
            'recommendation' => 'Sitemap is properly configured at ' . $analysis['sitemap']
        ];
    }
    
    // Check for robots.txt
    $hasRobots = !empty($analysis['robots']);
    if (!$hasRobots) {
        $score -= 3;
        $checks[] = [
            'title' => 'Robots.txt',
            'status' => 'warning',
            'value' => 'Not found',
            'recommendation' => 'Create a robots.txt file to control search engine crawling'
        ];
    } else {
        $checks[] = [
            'title' => 'Robots.txt',
            'status' => 'pass',
            'value' => 'Found',
            'recommendation' => 'robots.txt is properly configured'
        ];
    }
    
    // Ensure score is within bounds
    $score = max(0, min(100, $score));
    
    return [
        'score' => (int)$score,
        'checks' => $checks,
        'details' => $analysis // Include raw analysis data for reference
    ];
}

/**
 * Perform Performance Analysis
 */
function analyzePerformance($url, $html) {
    $startTime = microtime(true);
    
    $result = [
        'page_size' => strlen($html),
        'resources' => [
            'images' => [],
            'scripts' => [],
            'stylesheets' => [],
            'fonts' => []
        ],
        'load_time' => 0,
        'suggestions' => [],
        'compression' => [
            'gzip' => false,
            'brotli' => false
        ],
        'caching' => [
            'enabled' => false,
            'max_age' => 0
        ],
        'render_blocking' => [
            'scripts' => [],
            'stylesheets' => []
        ]
    ];
    
    // Check compression
    $headers = get_headers($url, 1);
    if (isset($headers['Content-Encoding'])) {
        $result['compression']['gzip'] = strpos($headers['Content-Encoding'], 'gzip') !== false;
        $result['compression']['brotli'] = strpos($headers['Content-Encoding'], 'br') !== false;
    }
    
    // Check caching headers
    if (isset($headers['Cache-Control'])) {
        $cacheControl = is_array($headers['Cache-Control']) ? $headers['Cache-Control'][0] : $headers['Cache-Control'];
        $result['caching']['enabled'] = stripos($cacheControl, 'no-cache') === false && 
                                      stripos($cacheControl, 'no-store') === false;
        
        // Extract max-age if present
        if (preg_match('/max-age=(\d+)/i', $cacheControl, $matches)) {
            $result['caching']['max_age'] = intval($matches[1]);
        }
    }
    
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    
    // Analyze images
    $images = $doc->getElementsByTagName('img');
    $totalImageSize = 0;
    $largeImages = [];
    $imagesWithoutAlt = [];
    
    foreach ($images as $img) {
        $src = $img->getAttribute('src');
        if (!$src) continue;
        
        $alt = $img->getAttribute('alt');
        if (empty($alt)) {
            $imagesWithoutAlt[] = $src;
        }
        
        // Get image size if it's a local file
        if (strpos($src, 'http') !== 0) {
            $src = rtrim($url, '/') . '/' . ltrim($src, '/');
        }
        
        $headers = @get_headers($src, 1);
        if ($headers && strpos($headers[0], '200')) {
            $size = isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : 0;
            $totalImageSize += $size;
            
            if ($size > 500000) { // 500KB
                $largeImages[] = [
                    'src' => $src,
                    'size' => round($size / 1024, 2) . ' KB'
                ];
            }
            
            $result['resources']['images'][] = [
                'src' => $src,
                'size' => $size,
                'has_alt' => !empty($alt)
            ];
        }
    }
    
    // Analyze scripts
    $scripts = $doc->getElementsByTagName('script');
    foreach ($scripts as $script) {
        $src = $script->getAttribute('src');
        if ($src) {
            $isRenderBlocking = !$script->hasAttribute('async') && !$script->hasAttribute('defer');
            $scriptInfo = ['src' => $src];
            
            if ($isRenderBlocking) {
                $result['render_blocking']['scripts'][] = $src;
                $scriptInfo['render_blocking'] = true;
            } else {
                $scriptInfo['render_blocking'] = false;
            }
            
            // Check if script is minified
            $isMinified = false;
            if (strpos($src, '.min.') !== false) {
                $isMinified = true;
            } else if (strpos($src, '?') !== false) {
                $src = substr($src, 0, strpos($src, '?'));
            }
            
            $scriptInfo['minified'] = $isMinified;
            $result['resources']['scripts'][] = $scriptInfo;
        }
    }
    
    // Analyze stylesheets and fonts
    $links = $doc->getElementsByTagName('link');
    foreach ($links as $link) {
        $rel = $link->getAttribute('rel');
        $href = $link->getAttribute('href');
        
        if ($rel === 'stylesheet') {
            $isRenderBlocking = $link->getAttribute('media') !== 'print';
            $stylesheetInfo = ['href' => $href];
            
            if ($isRenderBlocking) {
                $result['render_blocking']['stylesheets'][] = $href;
                $stylesheetInfo['render_blocking'] = true;
            } else {
                $stylesheetInfo['render_blocking'] = false;
            }
            
            // Check if stylesheet is minified
            $isMinified = strpos($href, '.min.') !== false;
            $stylesheetInfo['minified'] = $isMinified;
            
            $result['resources']['stylesheets'][] = $stylesheetInfo;
        } 
        // Check for web fonts
        elseif (in_array($rel, ['preload', 'stylesheet']) && 
                (strpos($href, '.woff') !== false || 
                 strpos($href, '.woff2') !== false ||
                 strpos($href, '.ttf') !== false ||
                 strpos($href, '.eot') !== false)) {
            $result['resources']['fonts'][] = [
                'href' => $href,
                'type' => $rel,
                'format' => pathinfo(parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION)
            ];
        }
    }
    
    // Calculate load time
    $result['load_time'] = round((microtime(true) - $startTime) * 1000); // in milliseconds
    
    // Add performance suggestions
    $totalImageSizeMB = round($totalImageSize / 1024 / 1024, 2);
    if ($totalImageSize > 2000000) { // 2MB
        $result['suggestions'][] = [
            'type' => 'warning',
            'message' => "Total image size is large ({$totalImageSizeMB} MB). Consider optimizing images.",
            'severity' => 'medium'
        ];
    }
    
    if (count($imagesWithoutAlt) > 0) {
        $result['suggestions'][] = [
            'type' => 'info',
            'message' => count($imagesWithoutAlt) . ' images are missing alt attributes.',
            'severity' => 'low'
        ];
    }
    
    $scriptCount = count($result['resources']['scripts']);
    if ($scriptCount > 10) {
        $result['suggestions'][] = [
            'type' => 'warning',
            'message' => "Consider reducing the number of JavaScript files ({$scriptCount} detected).",
            'severity' => 'medium'
        ];
    }
    
    // Check for render-blocking resources
    $blockingScripts = count($result['render_blocking']['scripts']);
    $blockingStyles = count($result['render_blocking']['stylesheets']);
    
    if ($blockingScripts > 0) {
        $result['suggestions'][] = [
            'type' => 'warning',
            'message' => "{$blockingScripts} render-blocking JavaScript files detected. Consider using 'async' or 'defer'.",
            'severity' => 'high'
        ];
    }
    
    if ($blockingStyles > 0) {
        $result['suggestions'][] = [
            'type' => 'warning',
            'message' => "{$blockingStyles} render-blocking stylesheets detected. Consider inlining critical CSS.",
            'severity' => 'high'
        ];
    }
    
    // Check for compression
    if (!$result['compression']['gzip'] && !$result['compression']['brotli']) {
        $result['suggestions'][] = [
            'type' => 'warning',
            'message' => 'Enable GZIP or Brotli compression to reduce file sizes.',
            'severity' => 'high'
        ];
    }
    
    // Check for caching
    if (!$result['caching']['enabled'] || $result['caching']['max_age'] < 86400) {
        $result['suggestions'][] = [
            'type' => 'info',
            'message' => 'Browser caching could be improved. Set appropriate Cache-Control headers.',
            'severity' => 'medium'
        ];
    }
    
    return $result;
}

/**
 * Format performance analysis results for display
 */
function formatPerformanceResults($analysis) {
    if (!is_array($analysis)) {
        return [
            'score' => 0,
            'checks' => []
        ];
    }
    
    // Initialize score and checks
    $score = 100;
    $checks = [];
    
    // Deduct points based on suggestions
    if (!empty($analysis['suggestions']) && is_array($analysis['suggestions'])) {
        foreach ($analysis['suggestions'] as $suggestion) {
            $severity = $suggestion['severity'] ?? 'low';
            switch ($severity) {
                case 'high':
                    $score -= 10;
                    break;
                case 'medium':
                    $score -= 5;
                    break;
                case 'low':
                    $score -= 2;
                    break;
            }
            
            // Add suggestion as a check
            $status = 'warning';
            if ($suggestion['type'] === 'info') {
                $status = 'info';
            } elseif ($severity === 'high') {
                $status = 'fail';
            }
            
            $checks[] = [
                'title' => $suggestion['message'] ?? 'Performance suggestion',
                'status' => $status,
                'value' => ucfirst($severity) . ' priority',
                'recommendation' => 'Address this issue to improve performance'
            ];
        }
    }
    
    // Add resource checks
    if (!empty($analysis['resources'])) {
        $resourceTypes = ['images', 'scripts', 'stylesheets', 'fonts'];
        foreach ($resourceTypes as $type) {
            $count = count($analysis['resources'][$type] ?? []);
            $checks[] = [
                'title' => ucfirst($type) . ' (' . $count . ')' ,
                'status' => 'info',
                'value' => $count . ' ' . $type . ' found',
                'recommendation' => $count > 0 ? 'Review ' . $type . ' for optimization opportunities' : ''
            ];
        }
    }
    
    // Add compression check
    $gzip = $analysis['compression']['gzip'] ?? false;
    $brotli = $analysis['compression']['brotli'] ?? false;
    $compressionStatus = ($gzip || $brotli) ? 'pass' : 'fail';
    if ($compressionStatus === 'fail') {
        $score -= 10; // Deduct more points for missing compression
    }
    
    $checks[] = [
        'title' => 'Compression',
        'status' => $compressionStatus,
        'value' => $gzip ? 'GZIP' : ($brotli ? 'Brotli' : 'Not enabled'),
        'recommendation' => $compressionStatus === 'pass' ? 
            'Great! Compression is enabled' : 
            'Enable GZIP or Brotli compression to reduce file sizes'
    ];
    
    // Add caching check
    $cachingEnabled = $analysis['caching']['enabled'] ?? false;
    $maxAge = $analysis['caching']['max_age'] ?? 0;
    $cachingStatus = ($cachingEnabled && $maxAge >= 86400) ? 'pass' : 'warning';
    if ($cachingStatus === 'warning') {
        $score -= 5; // Deduct points for suboptimal caching
    }
    
    $checks[] = [
        'title' => 'Browser Caching',
        'status' => $cachingStatus,
        'value' => $cachingEnabled ? 
            ($maxAge > 0 ? 'Enabled (max-age: ' . $maxAge . 's)' : 'Enabled') : 
            'Not properly configured',
        'recommendation' => $cachingStatus === 'pass' ?
            'Good caching configuration' :
            'Configure proper Cache-Control headers with appropriate max-age (at least 1 day)'
    ];
    
    // Add render-blocking resources check
    $blockingScripts = count($analysis['render_blocking']['scripts'] ?? []);
    $blockingStyles = count($analysis['render_blocking']['stylesheets'] ?? []);
    $blockingCount = $blockingScripts + $blockingStyles;
    $blockingStatus = $blockingCount > 0 ? 'warning' : 'pass';
    if ($blockingStatus === 'warning') {
        $score -= 7; // Deduct points for render-blocking resources
    }
    
    $checks[] = [
        'title' => 'Render-Blocking Resources',
        'status' => $blockingStatus,
        'value' => $blockingCount . ' render-blocking resources',
        'recommendation' => $blockingStatus === 'pass' ?
            'No render-blocking resources found' :
            'Eliminate render-blocking resources to improve page load time'
    ];
    
    // Ensure score is within bounds
    $score = max(0, min(100, $score));
    
    return [
        'score' => (int)$score,
        'checks' => $checks
    ];
}

/**
 * Perform Content Analysis
 */
function analyzeContent($html, $url) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    
    $result = [
        'word_count' => 0,
        'readability' => [
            'flesch' => 0,
            'flesch_kincaid' => 0,
            'grade_level' => '',
            'notes' => []
        ],
        'keywords' => [],
        'keyword_density' => [],
        'content_ratio' => 0,
        'language' => 'en',
        'duplicate_content' => [
            'titles' => [],
            'descriptions' => []
        ],
        'links' => [
            'internal' => 0,
            'external' => 0,
            'broken' => [],
            'nofollow' => 0
        ],
        'images' => [
            'with_alt' => 0,
            'without_alt' => 0,
            'total' => 0,
            'with_dimensions' => 0,
            'without_dimensions' => 0
        ]
    ];
    
    // Extract all text content for analysis
    $textContent = '';
    $body = $doc->getElementsByTagName('body')->item(0);
    if ($body) {
        $textContent = strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html));
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim($textContent);
    }
    
    // Calculate content-to-code ratio
    $contentLength = strlen($textContent);
    $totalLength = strlen($html);
    $result['content_ratio'] = $totalLength > 0 ? round(($contentLength / $totalLength) * 100, 2) : 0;
    
    // Calculate readability scores
    $readability = calculateReadability($textContent);
    $result['word_count'] = $readability['word_count'];
    $result['readability']['flesch'] = $readability['flesch'];
    $result['readability']['flesch_kincaid'] = $readability['flesch_kincaid'];
    
    // Interpret Flesch-Kincaid score
    if ($readability['flesch_kincaid'] >= 12) {
        $result['readability']['grade_level'] = 'College';
        $result['readability']['notes'][] = 'Content is at college reading level. Consider simplifying for broader audience.';
    } elseif ($readability['flesch_kincaid'] >= 10) {
        $result['readability']['grade_level'] = 'High School';
    } else {
        $result['readability']['grade_level'] = 'Basic';
    }
    
    // Basic keyword analysis (top 10 words)
    $words = str_word_count(strtolower($textContent), 1);
    $wordFreq = array_count_values($words);
    arsort($wordFreq);
    $result['keywords'] = array_slice($wordFreq, 0, 10, true);
    
    // Calculate keyword density (top 10)
    $totalWords = count($words);
    if ($totalWords > 0) {
        foreach ($result['keywords'] as $word => $count) {
            if (strlen($word) > 3) { // Only consider words longer than 3 characters
                $density = ($count / $totalWords) * 100;
                $result['keyword_density'][$word] = round($density, 2) . '%';
            }
        }
    }
    
    // Analyze links
    $links = $doc->getElementsByTagName('a');
    $baseDomain = parse_url($url, PHP_URL_HOST);
    $linkUrls = [];
    
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (empty($href) || $href === '#' || $href === 'javascript:void(0)') continue;
        
        $parsedUrl = parse_url($href);
        $isNofollow = $link->getAttribute('rel') === 'nofollow' || 
                      stripos($link->getAttribute('rel'), 'nofollow') !== false;
        
        if ($isNofollow) {
            $result['links']['nofollow']++;
        }
        
        if (isset($parsedUrl['host'])) {
            if (strpos($parsedUrl['host'], $baseDomain) !== false) {
                $result['links']['internal']++;
                $linkType = 'internal';
            } else {
                $result['links']['external']++;
                $linkType = 'external';
                
                // Check if external link is broken (only check first 5 to avoid timeouts)
                if (count($result['links']['broken']) < 5) {
                    $headers = @get_headers($href, 1);
                    $status = $headers ? $headers[0] : 'Unknown';
                    
                    if (strpos($status, '200') === false) {
                        $result['links']['broken'][] = [
                            'url' => $href,
                            'status' => $status,
                            'type' => $linkType,
                            'text' => trim($link->textContent)
                        ];
                    }
                }
            }
        } else {
            // Relative URL
            $result['links']['internal']++;
            $linkType = 'internal';
            $href = rtrim($url, '/') . '/' . ltrim($href, '/');
        }
        
        // Track link URLs for duplicate content check
        if (!empty($href)) {
            $linkUrls[] = [
                'url' => $href,
                'type' => $linkType,
                'text' => trim($link->textContent),
                'nofollow' => $isNofollow
            ];
        }
    }
    
    // Analyze images
    $images = $doc->getElementsByTagName('img');
    $result['images']['total'] = $images->length;
    $imageAltTexts = [];
    
    foreach ($images as $img) {
        $hasAlt = $img->hasAttribute('alt') && !empty(trim($img->getAttribute('alt')));
        $hasDimensions = ($img->hasAttribute('width') && $img->hasAttribute('height'));
        
        if ($hasAlt) {
            $result['images']['with_alt']++;
            $altText = trim($img->getAttribute('alt'));
            
            // Track duplicate alt text
            if (!isset($imageAltTexts[$altText])) {
                $imageAltTexts[$altText] = 0;
            }
            $imageAltTexts[$altText]++;
        } else {
            $result['images']['without_alt']++;
        }
        
        if ($hasDimensions) {
            $result['images']['with_dimensions']++;
        } else {
            $result['images']['without_dimensions']++;
        }
    }
    
    // Check for duplicate alt text
    foreach ($imageAltTexts as $alt => $count) {
        if ($count > 1 && strlen($alt) > 0) {
            $result['duplicate_content']['images'][] = [
                'alt' => $alt,
                'count' => $count
            ];
        }
    }
    
    // Check for duplicate page titles and descriptions
    $titles = $doc->getElementsByTagName('title');
    $descriptions = [];
    
    $metaTags = $doc->getElementsByTagName('meta');
    foreach ($metaTags as $tag) {
        if (strtolower($tag->getAttribute('name')) === 'description') {
            $descriptions[] = $tag->getAttribute('content');
        }
    }
    
    if ($titles->length > 1) {
        $result['duplicate_content']['titles'] = $titles->length;
    }
    
    if (count($descriptions) > 1) {
        $result['duplicate_content']['descriptions'] = count($descriptions);
    }
    
    return $result;
}

/**
 * Format content analysis results for display
 */
function formatContentResults($analysis) {
    if (!is_array($analysis)) {
        return [
            'score' => 0,
            'checks' => []
        ];
    }
    
    // Initialize score and checks
    $score = 100;
    $checks = [];
    
    // Check word count
    $wordCount = $analysis['word_count'] ?? 0;
    $wordCountStatus = 'pass';
    $wordCountMessage = 'Good content length';
    
    if ($wordCount < 300) {
        $score -= 10;
        $wordCountStatus = 'warning';
        $wordCountMessage = 'Content is too short. Aim for at least 300 words.';
    } elseif ($wordCount > 1500) {
        $score -= 5;
        $wordCountStatus = 'warning';
        $wordCountMessage = 'Content is very long. Consider breaking it into multiple pages.';
    }
    
    $checks[] = [
        'title' => 'Word Count',
        'status' => $wordCountStatus,
        'value' => number_format($wordCount) . ' words',
        'recommendation' => $wordCountMessage
    ];
    
    // Check readability
    $flesch = $analysis['readability']['flesch'] ?? 0;
    $fleschKincaid = $analysis['readability']['flesch_kincaid'] ?? 0;
    $readabilityStatus = 'pass';
    $readabilityMessage = 'Good readability score';
    
    if ($flesch < 60) {
        $score -= 5;
        $readabilityStatus = 'warning';
        $readabilityMessage = 'Content may be difficult to read. Consider simplifying your language.';
    }
    
    $checks[] = [
        'title' => 'Readability',
        'status' => $readabilityStatus,
        'value' => 'Flesch: ' . $flesch . ' | Grade: ' . $fleschKincaid,
        'recommendation' => $readabilityMessage
    ];
    
    // Check for images
    $imageCount = $analysis['images']['total'] ?? 0;
    $imagesWithoutAlt = $analysis['images']['without_alt'] ?? 0;
    $imagesWithAlt = $analysis['images']['with_alt'] ?? 0;
    
    if ($imageCount === 0) {
        $score -= 5;
        $checks[] = [
            'title' => 'Images',
            'status' => 'warning',
            'value' => 'No images found',
            'recommendation' => 'Consider adding relevant images to improve engagement'
        ];
    } else {
        $imageStatus = $imagesWithoutAlt > 0 ? 'warning' : 'pass';
        $imageMessage = $imagesWithoutAlt > 0 ? 
            "$imagesWithoutAlt images missing alt text" : 
            'All images have alt text';
            
        if ($imagesWithoutAlt > 0) {
            $score -= 3;
        }
        
        $checks[] = [
            'title' => 'Image Alt Text',
            'status' => $imageStatus,
            'value' => "$imagesWithAlt with alt text, $imagesWithoutAlt without",
            'recommendation' => $imageMessage
        ];
    }
    
    // Check for headings
    $hasH1 = ($analysis['headings']['h1'] ?? 0) > 0;
    $h1Count = $analysis['headings']['h1'] ?? 0;
    $h2Count = $analysis['headings']['h2'] ?? 0;
    
    if (!$hasH1) {
        $score -= 8;
        $checks[] = [
            'title' => 'Heading Structure',
            'status' => 'fail',
            'value' => 'No H1 heading found',
            'recommendation' => 'Add an H1 heading to improve content structure'
        ];
    } elseif ($h1Count > 1) {
        $score -= 3;
        $checks[] = [
            'title' => 'Heading Structure',
            'status' => 'warning',
            'value' => 'Multiple H1 headings found',
            'recommendation' => 'Use only one H1 heading per page'
        ];
    } else {
        $checks[] = [
            'title' => 'Heading Structure',
            'status' => 'pass',
            'value' => 'Good heading structure',
            'recommendation' => 'H1 and H2 headings are properly used'
        ];
    }
    
    // Check for paragraphs
    $paragraphCount = $analysis['paragraphs'] ?? 0;
    $avgWordsPerParagraph = $paragraphCount > 0 ? round($wordCount / $paragraphCount) : 0;
    
    if ($avgWordsPerParagraph > 100) {
        $score -= 3;
        $checks[] = [
            'title' => 'Paragraph Length',
            'status' => 'warning',
            'value' => 'Average paragraph length: ' . $avgWordsPerParagraph . ' words',
            'recommendation' => 'Consider breaking long paragraphs into smaller ones for better readability'
        ];
    } else {
        $checks[] = [
            'title' => 'Paragraph Length',
            'status' => 'pass',
            'value' => 'Good paragraph length',
            'recommendation' => 'Average paragraph length is readable'
        ];
    }
    
    // Check for content/HTML ratio
    $contentRatio = $analysis['content_ratio'] ?? 0;
    if ($contentRatio < 20) {
        $score -= 7;
        $checks[] = [
            'title' => 'Content/HTML Ratio',
            'status' => 'warning',
            'value' => $contentRatio . '%',
            'recommendation' => 'Low text to HTML ratio. Consider reducing template code and increasing content.'
        ];
    } else {
        $checks[] = [
            'title' => 'Content/HTML Ratio',
            'status' => 'pass',
            'value' => $contentRatio . '%',
            'recommendation' => 'Good text to HTML ratio'
        ];
    }
    
    // Ensure score is within bounds
    $score = max(0, min(100, $score));
    
    // Get readability level
    $gradeLevel = $analysis['readability']['flesch_kincaid'] ?? 0;
    $readabilityLevel = '';
    if ($gradeLevel <= 6) {
        $readabilityLevel = 'Easy to read';
    } elseif ($gradeLevel <= 9) {
        $readabilityLevel = 'Fairly easy to read';
    } elseif ($gradeLevel <= 12) {
        $readabilityLevel = 'Standard reading level';
    } elseif ($gradeLevel <= 16) {
        $readabilityLevel = 'Difficult to read';
    } else {
        $readabilityLevel = 'Very difficult to read';
    }
    
    return [
        'score' => (int)$score,
        'checks' => $checks,
        'word_count' => $wordCount,
        'readability' => [
            'flesch' => $flesch,
            'flesch_kincaid' => $fleschKincaid,
            'grade_level' => $readabilityLevel
        ],
        'content_ratio' => $contentRatio,
        'details' => $analysis // Include raw analysis data for reference
    ];
}