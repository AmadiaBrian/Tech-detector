<?php
// scan.php - handles the scanning, uses lib.php and analysis_functions.php
require_once __DIR__ . '/api/lib.php';
require_once __DIR__ . '/api/analysis_functions.php';

// Get URL from query parameter

// Get URL from query parameter
$url = $_GET['url'] ?? '';
$url = normalizeUrl($url);

// Validate URL
if (empty($url) || !isValidUrl($url)) {
    header('Location: ?error=invalid_url');
    exit;
}

// Block private/reserved IPs
$host = parse_url($url, PHP_URL_HOST);
if ($host === false || isPrivateHost($host)) {
    header('Location: ?error=private_ip');
    exit;
}

// Initialize variables
$technologies = [];
$rawHeaders = [];
$htmlSnippet = '';
$status = 0;

// Include analysis functions
require_once __DIR__ . '/api/analysis_functions.php';

// Initialize analysis results
$seoAnalysis = [];
$performanceAnalysis = [];
$contentAnalysis = [];

// Fetch the URL and detect technologies
try {
    $fetch = fetchUrl($url, ['timeout' => 8, 'maxRedirects' => 5]);
    
    if (isset($fetch['error'])) {
        throw new Exception($fetch['error']);
    }
    
    $rawHeaders = $fetch['headers'] ?? [];
    $html = $fetch['body'] ?? '';
    $htmlContent = $html ?: '';
    $status = $fetch['status'] ?? 0;
    
    // Run analysis functions
    if (!empty($html)) {
        // Initialize analysis arrays with default values
        $seoAnalysis = [
            'score' => 0,
            'checks' => []
        ];
        $performanceAnalysis = [
            'score' => 0,
            'checks' => []
        ];
        $contentAnalysis = [
            'score' => 0,
            'checks' => [],
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
        
        // Run SEO analysis
        try {
            $seoData = analyzeSeo($html, $url);
            $seoAnalysis = array_merge($seoAnalysis, formatSeoResults($seoData));
        } catch (Exception $e) {
            error_log('SEO Analysis Error: ' . $e->getMessage());
            $seoAnalysis = array_merge($seoAnalysis, [
                'score' => 0,
                'checks' => []
            ]);
        }
        
        // Run content analysis
        try {
            $contentData = analyzeContent($html, $url);
            $contentAnalysis = array_merge($contentAnalysis, $contentData);
            $contentAnalysis = array_merge($contentAnalysis, formatContentResults($contentData));
        } catch (Exception $e) {
            error_log('Content Analysis Error: ' . $e->getMessage());
            $contentAnalysis = array_merge($contentAnalysis, [
                'score' => 0,
                'checks' => []
            ]);
        }
        
        // Run performance analysis
        try {
            $perfData = analyzePerformance($url, $html);
            $performanceAnalysis = array_merge($performanceAnalysis, $perfData);
            $performanceAnalysis = array_merge($performanceAnalysis, formatPerformanceResults($perfData));
        } catch (Exception $e) {
            error_log('Performance Analysis Error: ' . $e->getMessage());
            $performanceAnalysis = array_merge($performanceAnalysis, [
                'score' => 0,
                'checks' => []
            ]);
        } catch (Throwable $t) {
            error_log('Performance Analysis Error: ' . $t->getMessage());
            $performanceAnalysis = array_merge($performanceAnalysis, [
                'score' => 0,
                'checks' => []
            ]);
        }
    }
    
    // Detect technologies
    $technologies = detectTechnologiesFromRules($html, $rawHeaders, $url);
} catch (Exception $e) {
    header('Location: ?error=' . urlencode($e->getMessage()));
    exit;
}

// Prepare page title
$pageTitle = 'Scan Results - ' . htmlspecialchars(parse_url($url, PHP_URL_HOST) ?: $url) . ' - TechDetector';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Prism.js for syntax highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup-templating.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/normalize-whitespace/prism-normalize-whitespace.min.js"></script>
    <style>
        /* Base styles - Google Search Console inspired with black theme */
        :root {
            --primary: #ff6b00;
            --primary-dark: #e65a00;
            --secondary: #5f6368;
            --success: #34a853;
            --danger: #ea4335;
            --warning: #fbbc04;
            --info: #4285f4;
            --light: #ffffff;
            --dark: #202124;
            --gray-100: #ffffff;
            --gray-200: #f1f3f4;
            --gray-300: #dadce0;
            --gray-400: #9aa0a6;
            --gray-500: #5f6368;
            --gray-600: #3c4043;
            --gray-700: #202124;
            --gray-800: #171717;
            --gray-900: #000000;
            --surface: #1e1e1e;
            --surface-hover: #2d2d2d;
            --border: #3c4043;
            --shadow: 0 1px 2px 0 rgba(0,0,0,0.3), 0 1px 3px 1px rgba(0,0,0,0.15);
            --shadow-hover: 0 1px 3px 0 rgba(0,0,0,0.4), 0 4px 8px 3px rgba(0,0,0,0.2);
        }

        /* Dark mode overrides - maintain black theme */
        .dark {
            --primary: #ff6b00;
            --primary-dark: #e65a00;
            --secondary: #9aa0a6;
            --light: #1e1e1e;
            --dark: #ffffff;
            --gray-100: #1e1e1e;
            --gray-200: #2d2d2d;
            --gray-300: #3c4043;
            --gray-400: #5f6368;
            --gray-500: #9aa0a6;
            --gray-600: #dadce0;
            --gray-700: #f1f3f4;
            --gray-800: #ffffff;
            --gray-900: #ffffff;
        }

        /* Navbar styles - Google Console style with black theme */
        .navbar {
            background-color: #171717;
            box-shadow: var(--shadow);
            border-bottom: 1px solid var(--border);
        }

        .dark .navbar {
            background-color: #171717;
            box-shadow: var(--shadow);
            border-bottom: 1px solid var(--border);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .logo {
            font-size: 1.25rem;
            font-weight: 400;
            color: #ffffff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dark .logo {
            color: #ffffff;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-link {
            color: #9aa0a6;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }

        .dark .nav-link {
            color: #9aa0a6;
        }

        .nav-link:hover {
            color: #ffffff;
            background-color: var(--surface-hover);
        }

        .dark .nav-link:hover {
            color: #ffffff;
            background-color: var(--surface-hover);
        }

        /* Container */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Card styles - Google Console style with black theme */
        .card {
            background-color: #000000;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #3c4043;
        }

        .dark .card {
            background-color: #000000;
            box-shadow: var(--shadow);
            border: 1px solid #3c4043;
        }

        /* Text colors - Black theme */
        body {
            background-color: #000000;
            color: #ffffff;
            transition: background-color 0.2s, color 0.2s;
        }

        .dark body {
            background-color: #000000;
            color: #ffffff;
        }

        h1, h2, h3, h4, h5, h6 {
            color: #ffffff;
        }

        .dark h1, 
        .dark h2, 
        .dark h3, 
        .dark h4, 
        .dark h5, 
        .dark h6 {
            color: #ffffff;
        }

        /* Form elements - Black theme */
        input, textarea, select {
            background-color: var(--surface);
            border: 1px solid var(--border);
            color: #ffffff;
        }

        .dark input,
        .dark textarea,
        .dark select {
            background-color: var(--surface);
            border-color: var(--border);
            color: #ffffff;
        }

        /* Buttons - Google Console style with orange */
        .btn {
            background-color: var(--primary);
            color: white;
            border-radius: 4px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: background-color 0.2s;
            border: none;
        }

        .btn:hover {
            background-color: var(--primary-dark);
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .dark th,
        .dark td {
            border-bottom-color: #374151;
        }

        th {
            background-color: var(--gray-50);
            color: var(--gray-700);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .dark th {
            background-color: #1f2937;
            color: #9ca3af;
        }

        /* Code blocks - Black theme */
        pre, code {
            font-family: 'Fira Code', 'Consolas', 'Monaco', 'Andale Mono', monospace;
            border-radius: 4px;
            background-color: var(--surface);
            color: #ffffff;
        }

        .dark pre,
        .dark code {
            background-color: var(--surface);
            color: #ffffff;
        }

        /* Custom Prism.js theme to match VS Code dark theme */
        :root {
            --prism-background: #1e1e1e;
            --prism-foreground: #d4d4d4;
            --prism-comment: #6a9955;
            --prism-string: #ce9178;
            --prism-literal: #569cd6;
            --prism-keyword: #c586c0;
            --prism-tag: #569cd6;
            --prism-attr-name: #9cdcfe;
            --prism-attr-value: #ce9178;
            --prism-punctuation: #d4d4d4;
            --prism-operator: #d4d4d4;
            --prism-entity: #9cdcfe;
        }

        .dark pre[class*="language-"],
        pre[class*="language-"].dark-mode {
            background: var(--prism-background);
            color: var(--prism-foreground);
            text-shadow: none;
            border-radius: 0.5rem;
            margin: 0;
            padding: 1.5rem;
            overflow: auto;
            max-height: 600px;
            font-size: 0.9em;
            line-height: 1.5;
        }

        .dark pre[class*="language-"] code,
        pre[class*="language-"].dark-mode code {
            color: var(--prism-foreground);
            text-shadow: none;
            font-family: 'Fira Code', 'Consolas', 'Monaco', 'Andale Mono', 'Ubuntu Mono', monospace;
        }

        .dark .token.comment,
        .dark .token.prolog,
        .dark .token.doctype,
        .dark .token.cdata {
            color: var(--prism-comment);
        }

        .dark .token.punctuation {
            color: var(--prism-punctuation);
        }

        .dark .token.property,
        .dark .token.tag,
        .dark .token.boolean,
        .dark .token.number,
        .dark .token.constant,
        .dark .token.symbol,
        .dark .token.deleted {
            color: var(--prism-literal);
        }

        .dark .token.selector,
        .dark .token.attr-name,
        .dark .token.string,
        .dark .token.char,
        .dark .token.builtin,
        .dark .token.inserted {
            color: var(--prism-string);
        }

        .dark .token.operator,
        .dark .token.entity,
        .dark .token.url,
        .dark .language-css .token.string,
        .dark .style .token.string {
            color: var(--prism-operator);
            background: none;
        }

        .dark .token.atrule,
        .dark .token.attr-value,
        .dark .token.keyword {
            color: var(--prism-keyword);
        }

        .dark .token.function,
        .dark .token.class-name {
            color: var(--prism-entity);
        }

        .dark .token.regex,
        .dark .token.important,
        .dark .token.variable {
            color: var(--prism-string);
        }

        .dark .line-numbers .line-numbers-rows {
            border-right: 1px solid #2d2d2d;
        }

        .dark .line-numbers-rows > span:before {
            color: #858585;
        }

        /* Make sure the copy button stays on top */
        .relative .absolute {
            z-index: 10;
        }
    </style>
    <style>
        .tech-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            background-color: #e0f2fe;
            color: #0369a1;
            margin: 0.25rem;
            transition: all 0.2s ease;
            cursor: help;
            position: relative;
        }
        .tech-badge:hover {
            background-color: #bae6fd;
            transform: translateY(-1px);
        }
        .tech-badge i {
            margin-right: 0.375rem;
        }
        .tech-tooltip {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .tech-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Courier New', Courier, monospace;
        }
        .dark .tech-badge {
            background-color: #2d2d2d;
            color: #ff6b00;
            border: 1px solid var(--border);
        }
        .dark .tech-badge:hover {
            background-color: var(--surface-hover);
        }
        .dark .tech-tooltip {
            background-color: #2d2d2d;
            border: 1px solid var(--border);
        }
    </style>
</head>
<body class="bg-black dark:bg-black min-h-screen">
    <nav class="bg-[#171717] dark:bg-[#171717] shadow border-b border-[#3c4043]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="" class="flex items-center space-x-2">
                            <i class="fas fa-search text-[#ff6b00] text-xl"></i>
                            <span class="text-xl font-medium text-white hidden sm:inline-block">
                                <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
                            </span>
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="" class="text-[#9aa0a6] hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-home mr-1"></i> New Scan
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="bg-[#000000] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Scan Results</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Scanned: <a href="<?= htmlspecialchars($url) ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline"><?= htmlspecialchars($url) ?></a>
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Status: <span class="font-mono px-2 py-1 rounded <?= $status >= 400 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' ?>">
                            <?= $status ?> <?= getHttpStatusText($status) ?>
                        </span>
                    </p>
                </div>
            </div>

            <?php if (!empty($technologies['technologies'])): ?>
                <div class="bg-[#000000] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                    <div class="px-4 py-5 sm:px-6 border-b border-[#3c4043]">
                        <h3 class="text-lg leading-6 font-medium text-white">
                            Detected Technologies
                        </h3>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex flex-wrap gap-2 mb-6">
                            <?php foreach ($technologies['technologies'] as $tech): ?>
                                <?php 
                                $details = $technologies['details'][$tech] ?? [];
                                $confidence = $details['confidence'] ?? 100;
                                $locations = $details['found_in'] ?? [];
                                $patterns = $details['patterns'] ?? [];
                                ?>
                                <span class="tech-badge group relative" title="Found in: <?= htmlspecialchars(implode(', ', $locations)) ?>">
                                    <?= htmlspecialchars($tech) ?>
                                    <?php if ($confidence < 100): ?>
                                        <span class="ml-1 text-xs opacity-75"><?= $confidence ?>%</span>
                                    <?php endif; ?>
                                    <span class="tech-tooltip hidden group-hover:block absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded whitespace-nowrap z-10">
                                        Found in: <?= htmlspecialchars(implode(', ', $locations)) ?>
                                    </span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (!empty($technologies['details'])): ?>
                            <div class="mt-6 space-y-6">
                                <h4 class="text-md font-medium text-white">Detection Details</h4>
                                <div class="space-y-4">
                                    <?php foreach ($technologies['details'] as $tech => $details): ?>
                                        <div class="bg-[#000000] rounded-lg p-4 border border-[#3c4043]">
                                            <div class="flex items-center justify-between">
                                                <h5 class="font-medium text-white"><?= htmlspecialchars($tech) ?></h5>
                                                <?php if (isset($details['confidence'])): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#ff6b00] text-white">
                                                        Confidence: <?= $details['confidence'] ?>%
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($details['found_in'])): ?>
                                                <div class="mt-2">
                                                    <span class="text-sm font-medium text-[#9aa0a6]">Found in:</span>
                                                    <div class="flex flex-wrap gap-2 mt-1">
                                                        <?php foreach ($details['found_in'] as $location): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#34a853] text-white">
                                                                <?= htmlspecialchars($location) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($details['patterns'])): ?>
                                                <div class="mt-3">
                                                    <span class="text-sm font-medium text-[#9aa0a6]">Matching Patterns:</span>
                                                    <div class="mt-1 space-y-2">
                                                        <?php foreach ($details['patterns'] as $index => $pattern): ?>
                                                            <div class="bg-[#000000] p-3 rounded-md text-sm border border-[#3c4043]">
                                                                <div class="font-mono text-xs break-all">
                                                                    <span class="text-[#5f6368]"><?= $index + 1 ?>. </span>
                                                                    <?= htmlspecialchars($pattern['pattern']) ?>
                                                                </div>
                                                                <div class="mt-1 text-xs text-[#5f6368]">
                                                                    Type: <?= htmlspecialchars($pattern['type']) ?> 
                                                                    • Case <?= $pattern['case_insensitive'] ? 'insensitive' : 'sensitive' ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SEO Analysis -->
            <div class="bg-[#1e1e1e] dark:bg-[#1e1e1e] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                <div class="px-4 py-5 sm:px-6 border-b border-[#3c4043]">
                    <h3 class="text-lg leading-6 font-medium text-white">
                        <i class="fas fa-search mr-2 text-[#ff6b00]"></i> SEO Analysis
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <?php if (empty($seoAnalysis) || !isset($seoAnalysis['score'])): ?>
                        <div class="text-center py-4 text-[#9aa0a6]">
                            <p>SEO analysis could not be performed. The page may not be accessible or an error occurred.</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-md font-medium text-white">SEO Score</h4>
                                <?php 
                                    $seoScore = $seoAnalysis['score'] ?? 0;
                                    $seoColor = $seoScore >= 80 ? '#34a853' : ($seoScore >= 60 ? '#4285f4' : ($seoScore >= 40 ? '#fbbc04' : '#ea4335'));
                                ?>
                                <span class="px-3 py-1 rounded-full text-sm font-medium text-white" style="background-color: <?= $seoColor ?>;">
                                    <?= $seoScore ?>/100
                                </span>
                            </div>
                            <div class="w-full bg-[#000000] rounded-full h-2.5 border border-[#3c4043]">
                                <div class="h-2.5 rounded-full" style="width: <?= $seoScore ?>%; background-color: <?= $seoColor ?>;"></div>
                            </div>
                        </div>

                        <?php if (!empty($seoAnalysis['checks']) && is_array($seoAnalysis['checks'])): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach ($seoAnalysis['checks'] as $check): 
                                    if (!is_array($check) || !isset($check['status'])) continue;
                                    $status = $check['status'] ?? 'fail';
                                    $bgColor = $status === 'pass' ? '#1e3a1e' : ($status === 'warning' ? '#3a2e0e' : '#3a1e1e');
                                    $borderColor = $status === 'pass' ? '#34a853' : ($status === 'warning' ? '#fbbc04' : '#ea4335');
                                    $textColor = $status === 'pass' ? '#34a853' : ($status === 'warning' ? '#fbbc04' : '#ea4335');
                                ?>
                                    <div class="p-4 border rounded-lg" style="background-color: <?= $bgColor ?>; border-color: <?= $borderColor ?>;">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <?php if ($status === 'pass'): ?>
                                                    <svg class="h-5 w-5" style="color: #34a853;" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php elseif ($status === 'warning'): ?>
                                                    <svg class="h-5 w-5" style="color: #fbbc04;" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="h-5 w-5" style="color: #ea4335;" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-3">
                                                <h4 class="text-sm font-medium" style="color: <?= $textColor ?>;">
                                                    <?= !empty($check['title']) ? htmlspecialchars($check['title']) : 'Check' ?>
                                                </h4>
                                                <?php if (!empty($check['value'])): ?>
                                                    <p class="mt-1 text-sm text-[#9aa0a6]">
                                                        <?= htmlspecialchars($check['value']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (!empty($check['recommendation'])): ?>
                                                    <p class="mt-1 text-xs text-[#5f6368]">
                                                        <span class="font-medium">Recommendation:</span> <?= htmlspecialchars($check['recommendation']) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-[#9aa0a6]">
                                <p>No SEO checks were performed or an error occurred during analysis.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Performance Analysis -->
            <div class="bg-[#1e1e1e] dark:bg-[#1e1e1e] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                <div class="px-4 py-5 sm:px-6 border-b border-[#3c4043]">
                    <h3 class="text-lg leading-6 font-medium text-white">
                        <i class="fas fa-tachometer-alt mr-2 text-[#ff6b00]"></i> Performance Analysis
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <?php 
                    // Ensure performanceAnalysis has the expected structure
                    if (!is_array($performanceAnalysis)) {
                        $performanceAnalysis = ['score' => 0, 'checks' => []];
                    }
                    
                    if (!isset($performanceAnalysis['score'])) {
                        $performanceAnalysis['score'] = 0;
                    }
                    
                    if (!isset($performanceAnalysis['checks']) || !is_array($performanceAnalysis['checks'])) {
                        $performanceAnalysis['checks'] = [];
                    }
                    
                    $perfScore = (int)$performanceAnalysis['score'];
                    $perfColor = $perfScore >= 80 ? '#34a853' : ($perfScore >= 60 ? '#4285f4' : ($perfScore >= 40 ? '#fbbc04' : '#ea4335'));
                    ?>
                    
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-md font-medium text-white">Performance Score</h4>
                            <span class="px-3 py-1 rounded-full text-sm font-medium text-white" style="background-color: <?= $perfColor ?>;">
                                <?= $perfScore ?>/100
                            </span>
                        </div>
                        <div class="w-full bg-[#2d2d2d] rounded-full h-2.5">
                            <div class="h-2.5 rounded-full" style="width: <?= $perfScore ?>%; background-color: <?= $perfColor ?>;"></div>
                        </div>
                    </div>

                        <?php if (empty($performanceAnalysis['checks'])): ?>
                            <div class="text-center py-4 text-[#9aa0a6]">
                                <p>No performance checks were performed. The page may not be accessible or an error occurred during analysis.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php 
                                $hasValidChecks = false;
                                foreach ($performanceAnalysis['checks'] as $check): 
                                    if (!is_array($check) || !isset($check['status'])) continue;
                                    $hasValidChecks = true;
                                    $status = $check['status'] ?? 'fail';
                                    $bgColor = $status === 'pass' ? '#1e3a1e' : ($status === 'warning' ? '#3a2e0e' : '#3a1e1e');
                                    $borderColor = $status === 'pass' ? '#34a853' : ($status === 'warning' ? '#fbbc04' : '#ea4335');
                                    $textColor = $status === 'pass' ? '#34a853' : ($status === 'warning' ? '#fbbc04' : '#ea4335');
                                ?>
                                    <div class="p-4 border rounded-lg" style="background-color: <?= $bgColor ?>; border-color: <?= $borderColor ?>;">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <?php if ($status === 'pass'): ?>
                                                    <svg class="h-5 w-5" style="color: #34a853;" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php elseif ($status === 'warning'): ?>
                                                    <svg class="h-5 w-5" style="color: #fbbc04;" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="h-5 w-5" style="color: #ea4335;" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                    </svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ml-3">
                                                <h4 class="text-sm font-medium" style="color: <?= $textColor ?>;">
                                                    <?= !empty($check['title']) ? htmlspecialchars($check['title']) : 'Performance Check' ?>
                                                </h4>
                                                <?php if (!empty($check['value'])): ?>
                                                    <p class="mt-1 text-sm text-[#9aa0a6]">
                                                        <?= htmlspecialchars($check['value']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (!empty($check['recommendation'])): ?>
                                                    <p class="mt-1 text-xs text-[#5f6368]">
                                                        <span class="font-medium">Recommendation:</span> <?= htmlspecialchars($check['recommendation']) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                endforeach; 
                                
                                if (!$hasValidChecks): 
                                ?>
                                    <div class="col-span-2 text-center py-4 text-[#9aa0a6]">
                                        <p>No valid performance checks were found in the analysis results.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                </div>
            </div>

            <!-- Content Analysis -->
            <div class="bg-[#1e1e1e] dark:bg-[#1e1e1e] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                <div class="px-4 py-5 sm:px-6 border-b border-[#3c4043]">
                    <h3 class="text-lg leading-6 font-medium text-white">
                        <i class="fas fa-file-alt mr-2 text-[#ff6b00]"></i> Content Analysis
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <?php if (empty($contentAnalysis) || !isset($contentAnalysis['score'])): ?>
                        <div class="text-center py-4 text-[#9aa0a6]">
                            <p>Content analysis could not be performed. The page may not be accessible or an error occurred.</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-md font-medium text-white">Content Quality Score</h4>
                                <?php 
                                    $contentScore = $contentAnalysis['score'] ?? 0;
                                    $contentColor = $contentScore >= 80 ? '#34a853' : ($contentScore >= 60 ? '#4285f4' : ($contentScore >= 40 ? '#fbbc04' : '#ea4335'));
                                ?>
                                <span class="px-3 py-1 rounded-full text-sm font-medium text-white" style="background-color: <?= $contentColor ?>;">
                                    <?= $contentScore ?>/100
                                </span>
                            </div>
                            <div class="w-full bg-[#000000] rounded-full h-2.5 border border-[#3c4043]">
                                <div class="h-2.5 rounded-full" style="width: <?= $contentScore ?>%; background-color: <?= $contentColor ?>;"></div>
                            </div>
                        </div>

                        <!-- Content Metrics -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            <!-- Word Count -->
                            <div class="bg-[#000000] p-4 rounded-lg border border-[#3c4043]">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full" style="background-color: #1e3a8a; color: #60a5fa;">
                                        <i class="fas fa-font text-lg"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-sm font-medium text-[#9aa0a6]">Word Count</h4>
                                        <p class="text-2xl font-semibold text-white">
                                            <?= number_format($contentAnalysis['word_count'] ?? 0) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Readability -->
                            <div class="bg-[#000000] p-4 rounded-lg border border-[#3c4043]">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full" style="background-color: #1e3a1e; color: #34a853;">
                                        <i class="fas fa-book-reader text-lg"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-sm font-medium text-[#9aa0a6]">Readability</h4>
                                        <p class="text-2xl font-semibold text-white">
                                            <?= htmlspecialchars($contentAnalysis['readability']['grade_level'] ?? 'N/A') ?>
                                        </p>
                                        <p class="text-xs text-[#5f6368]">
                                            Flesch: <?= $contentAnalysis['readability']['flesch'] ?? 'N/A' ?> | 
                                            Grade: <?= $contentAnalysis['readability']['flesch_kincaid'] ?? 'N/A' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Content Ratio -->
                            <div class="bg-[#000000] p-4 rounded-lg border border-[#3c4043]">
                                <div class="flex items-center">
                                    <div class="p-3 rounded-full" style="background-color: #3a1e3a; color: #a855f7;">
                                        <i class="fas fa-percentage text-lg"></i>
                                    </div>
                                    <div class="ml-4">
                                        <h4 class="text-sm font-medium text-[#9aa0a6]">Content/Code Ratio</h4>
                                        <p class="text-2xl font-semibold text-white">
                                            <?= $contentAnalysis['content_ratio'] ?? 0 ?>%
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Keyword Analysis -->
                        <?php if (!empty($contentAnalysis['keywords']) && is_array($contentAnalysis['keywords'])): ?>
                            <div class="mb-8">
                                <h4 class="text-md font-medium text-white mb-3">Top Keywords</h4>
                                <div class="flex flex-wrap gap-2">
                                    <?php 
                                    $keywords = array_slice($contentAnalysis['keywords'], 0, 10, true);
                                    foreach ($keywords as $keyword => $count): 
                                        if (strlen($keyword) < 4) continue; // Skip short words
                                        $density = isset($contentAnalysis['keyword_density'][$keyword]) ? 
                                            $contentAnalysis['keyword_density'][$keyword] : 
                                            round(($count / $contentAnalysis['word_count']) * 100, 2) . '%';
                                    ?>
                                        <div class="bg-[#2d2d2d] px-3 py-1.5 rounded-full border border-[#3c4043] flex items-center">
                                            <span class="text-sm font-medium text-white"><?= htmlspecialchars($keyword) ?></span>
                                            <span class="ml-2 text-xs bg-[#1e1e1e] text-[#9aa0a6] px-2 py-0.5 rounded-full">
                                                <?= $count ?>x (<?= $density ?>)
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Content Checks -->
                        <?php if (!empty($contentAnalysis['checks']) && is_array($contentAnalysis['checks'])): ?>
                            <div class="space-y-4">
                                <h4 class="text-md font-medium text-white">Content Checks</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php 
                                    $checksByStatus = [
                                        'pass' => [],
                                        'warning' => [],
                                        'fail' => []
                                    ];
                                    
                                    foreach ($contentAnalysis['checks'] as $check) {
                                        if (!is_array($check) || !isset($check['status'])) continue;
                                        $status = $check['status'] ?? 'fail';
                                        $checksByStatus[$status][] = $check;
                                    }
                                    
                                    // Display checks in order: fail, warning, pass
                                    $orderedChecks = array_merge(
                                        $checksByStatus['fail'],
                                        $checksByStatus['warning'],
                                        $checksByStatus['pass']
                                    );
                                    
                                    foreach ($orderedChecks as $check): 
                                        $status = $check['status'] ?? 'fail';
                                        $bgColor = $status === 'pass' ? '#1e3a1e' : ($status === 'warning' ? '#3a2e0e' : '#3a1e1e');
                                        $borderColor = $status === 'pass' ? '#34a853' : ($status === 'warning' ? '#fbbc04' : '#ea4335');
                                        $textColor = $status === 'pass' ? '#34a853' : ($status === 'warning' ? '#fbbc04' : '#ea4335');
                                    ?>
                                        <div class="p-4 border rounded-lg" style="background-color: <?= $bgColor ?>; border-color: <?= $borderColor ?>;">
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0 mt-0.5">
                                                    <?php if ($status === 'pass'): ?>
                                                        <svg class="h-5 w-5" style="color: #34a853;" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                        </svg>
                                                    <?php elseif ($status === 'warning'): ?>
                                                        <svg class="h-5 w-5" style="color: #fbbc04;" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg class="h-5 w-5" style="color: #ea4335;" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                        </svg>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ml-3">
                                                    <h4 class="text-sm font-medium" style="color: <?= $textColor ?>;">
                                                        <?= !empty($check['title']) ? htmlspecialchars($check['title']) : 'Check' ?>
                                                    </h4>
                                                    <?php if (!empty($check['value'])): ?>
                                                        <p class="mt-1 text-sm text-[#9aa0a6]">
                                                            <?= htmlspecialchars($check['value']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($check['recommendation'])): ?>
                                                        <p class="mt-1 text-xs text-[#5f6368]">
                                                            <span class="font-medium">Recommendation:</span> <?= htmlspecialchars($check['recommendation']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-[#9aa0a6]">
                                <p>No content checks were performed or an error occurred during analysis.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Additional Content Information -->
                        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Links Summary -->
                            <div class="bg-[#000000] p-4 rounded-lg border border-[#3c4043]">
                                <h4 class="text-md font-medium text-white mb-3">Links Summary (<?= ($contentAnalysis['links']['internal'] ?? 0) + ($contentAnalysis['links']['external'] ?? 0) ?>)</h4>
                                
                                <!-- Links Summary Stats -->
                                <div class="grid grid-cols-3 gap-2 mb-4 text-center">
                                    <div class="bg-[#1e3a8a] p-2 rounded">
                                        <div class="text-sm font-medium text-[#60a5fa]">Internal</div>
                                        <div class="text-lg font-bold text-white"><?= $contentAnalysis['links']['internal'] ?? 0 ?></div>
                                    </div>
                                    <div class="bg-[#1e3a1e] p-2 rounded">
                                        <div class="text-sm font-medium text-[#34a853]">External</div>
                                        <div class="text-lg font-bold text-white"><?= $contentAnalysis['links']['external'] ?? 0 ?></div>
                                    </div>
                                    <div class="bg-[#3a2e0e] p-2 rounded">
                                        <div class="text-sm font-medium text-[#fbbc04]">NoFollow</div>
                                        <div class="text-lg font-bold text-white"><?= $contentAnalysis['links']['nofollow'] ?? 0 ?></div>
                                    </div>
                                </div>

                                <!-- Detailed Links -->
                                <div class="space-y-3">
                                    <!-- Internal Links -->
                                    <div class="mb-4">
                                        <h5 class="text-sm font-medium text-[#60a5fa] mb-2">
                                            Internal Links (<?= count($contentAnalysis['links']['internal_links'] ?? []) ?>)
                                            <?php if (empty($contentAnalysis['links']['internal_links'])): ?>
                                                <span class="text-xs font-normal text-[#5f6368]">- No internal links found</span>
                                            <?php endif; ?>
                                        </h5>
                                        <?php if (!empty($contentAnalysis['links']['internal_links'])): ?>
                                            <div class="space-y-1 max-h-96 overflow-y-auto border border-[#3c4043] rounded p-2 bg-[#1e1e1e]">
                                                <?php foreach ($contentAnalysis['links']['internal_links'] as $link): ?>
                                                    <div class="flex items-start text-sm">
                                                        <span class="text-[#5f6368] mr-2">•</span>
                                                        <a href="<?= htmlspecialchars($link['url']) ?>" 
                                                           target="_blank" 
                                                           class="text-[#60a5fa] hover:underline break-all"
                                                           title="<?= htmlspecialchars($link['text']) ?>">
                                                            <?= htmlspecialchars($link['text'] ?: '(no text)') ?>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- External Links -->
                                    <div class="mb-4">
                                        <h5 class="text-sm font-medium text-[#34a853] mb-2">
                                            External Links (<?= count($contentAnalysis['links']['external_links'] ?? []) ?>)
                                            <?php if (empty($contentAnalysis['links']['external_links'])): ?>
                                                <span class="text-xs font-normal text-[#5f6368]">- No external links found</span>
                                            <?php endif; ?>
                                        </h5>
                                        <?php if (!empty($contentAnalysis['links']['external_links'])): ?>
                                        <div class="mb-4">
                                            <h5 class="text-sm font-medium text-[#34a853] mb-2">External Links (<?= count($contentAnalysis['links']['external_links']) ?>)</h5>
                                            <div class="space-y-1 max-h-96 overflow-y-auto border border-[#3c4043] rounded p-2 bg-[#1e1e1e]">
                                                <?php foreach ($contentAnalysis['links']['external_links'] as $link): ?>
                                                    <div class="flex items-start text-sm">
                                                        <span class="text-[#5f6368] mr-2">•</span>
                                                        <a href="<?= htmlspecialchars($link['url']) ?>" 
                                                           target="_blank" 
                                                           rel="noopener noreferrer"
                                                           class="text-[#34a853] hover:underline break-all"
                                                           title="<?= htmlspecialchars($link['text']) ?>">
                                                            <?= htmlspecialchars(parse_url($link['url'], PHP_URL_HOST) . ($link['text'] ? ': ' . $link['text'] : '')) ?>
                                                        </a>
                                                        <?php if ($link['nofollow']): ?>
                                                            <span class="ml-2 text-xs bg-[#3a2e0e] text-[#fbbc04] px-1.5 py-0.5 rounded">nofollow</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Broken Links -->
                                    <?php if (!empty($contentAnalysis['links']['broken']) && is_array($contentAnalysis['links']['broken'])): ?>
                                    <!-- Broken Links -->
                                    <div class="mb-4">
                                        <h5 class="text-sm font-medium text-[#ea4335] mb-2">
                                            Broken Links (<?= count($contentAnalysis['links']['broken'] ?? []) ?>)
                                            <?php if (empty($contentAnalysis['links']['broken'])): ?>
                                                <span class="text-xs font-normal text-[#5f6368]">- No broken links found</span>
                                            <?php endif; ?>
                                        </h5>
                                        <?php if (!empty($contentAnalysis['links']['broken'])): ?>
                                            <div class="space-y-2 max-h-96 overflow-y-auto border border-[#ea4335] rounded p-2 bg-[#3a1e1e]">
                                                <?php foreach ($contentAnalysis['links']['broken'] as $link): ?>
                                                    <div class="p-2 hover:bg-[#4a2e2e] rounded">
                                                        <div class="flex items-start">
                                                            <span class="text-[#ea4335] mr-2">•</span>
                                                            <div class="flex-1">
                                                                <div class="text-sm text-[#ea4335] break-all">
                                                                    <?= htmlspecialchars($link['url']) ?>
                                                                </div>
                                                                <?php if (!empty($link['text'])): ?>
                                                                    <div class="text-xs text-[#5f6368] mt-0.5">
                                                                        <?= htmlspecialchars($link['text']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if (!empty($link['status'])): ?>
                                                                    <div class="mt-1 text-xs font-medium text-[#ea4335]">
                                                                        <?= htmlspecialchars($link['status']) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                    </div>
                            </div>

                            <!-- Images Summary -->
                            <div class="bg-[#000000] p-4 rounded-lg border border-[#3c4043]">
                                <h4 class="text-md font-medium text-white mb-3">
                                    Images (<?= $contentAnalysis['images']['total'] ?? 0 ?>)
                                </h4>
                                
                                <!-- Images Summary Stats -->
                                <div class="grid grid-cols-3 gap-2 mb-4 text-center">
                                    <div class="bg-[#1e3a8a] p-2 rounded">
                                        <div class="text-sm font-medium text-[#60a5fa]">With Alt</div>
                                        <div class="text-lg font-bold text-white">
                                            <?= $contentAnalysis['images']['with_alt'] ?? 0 ?>
                                        </div>
                                    </div>
                                    <div class="bg-[#1e3a1e] p-2 rounded">
                                        <div class="text-sm font-medium text-[#34a853]">With Dimensions</div>
                                        <div class="text-lg font-bold text-white">
                                            <?= $contentAnalysis['images']['with_dimensions'] ?? 0 ?>
                                        </div>
                                    </div>
                                    <div class="bg-[#3a1e3a] p-2 rounded">
                                        <div class="text-sm font-medium text-[#a855f7]">Missing Alt</div>
                                        <div class="text-lg font-bold text-white">
                                            <?= ($contentAnalysis['images']['total'] ?? 0) - ($contentAnalysis['images']['with_alt'] ?? 0) ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Detailed Images -->
                                <div class="space-y-3">
                                    <!-- Images with Alt Text -->
                                    <div class="mb-4">
                                        <h5 class="text-sm font-medium text-[#60a5fa] mb-2">
                                            Images with Alt Text (<?= count($contentAnalysis['images']['images_with_alt'] ?? []) ?>)
                                            <?php if (empty($contentAnalysis['images']['images_with_alt'])): ?>
                                                <span class="text-xs font-normal text-[#5f6368]">- No images with alt text found</span>
                                            <?php endif; ?>
                                        </h5>
                                        <?php if (!empty($contentAnalysis['images']['images_with_alt'])): ?>
                                            <div class="space-y-2 max-h-96 overflow-y-auto border border-[#3c4043] rounded p-2 bg-[#1e1e1e]">
                                                <?php foreach ($contentAnalysis['images']['images_with_alt'] as $img): ?>
                                                    <div class="flex items-start p-2 hover:bg-[#2d2d2d] rounded">
                                                        <div class="flex-shrink-0 w-16 h-16 bg-[#1e1e1e] rounded overflow-hidden">
                                                            <?php if (strpos($img['src'], 'http') === 0): ?>
                                                                <img src="<?= htmlspecialchars($img['src']) ?>" 
                                                                     alt="Preview" 
                                                                     class="w-full h-full object-cover"
                                                                     onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>'">
                                                            <?php else: ?>
                                                                <div class="w-full h-full flex items-center justify-center text-[#5f6368]">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                    </svg>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="ml-3 flex-1 min-w-0">
                                                            <div class="text-sm font-medium text-white break-all">
                                                                <?= !empty($img['alt']) ? htmlspecialchars($img['alt']) : '(no alt text)' ?>
                                                            </div>
                                                            <div class="text-xs text-[#5f6368] truncate" title="<?= htmlspecialchars($img['src']) ?>">
                                                                <?= htmlspecialchars(basename(parse_url($img['src'], PHP_URL_PATH) ?: $img['src'])) ?>
                                                            </div>
                                                            <?php if (!empty($img['width']) && !empty($img['height'])): ?>
                                                                <div class="text-xs text-[#5f6368]">
                                                                    <?= $img['width'] ?> × <?= $img['height'] ?>px
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Images without Alt Text -->
                                    <div class="mb-4">
                                        <h5 class="text-sm font-medium text-[#ea4335] mb-2">
                                            Images Missing Alt Text (<?= count($contentAnalysis['images']['images_without_alt'] ?? []) ?>)
                                            <?php if (empty($contentAnalysis['images']['images_without_alt'])): ?>
                                                <span class="text-xs font-normal text-[#5f6368]">- No images missing alt text</span>
                                            <?php endif; ?>
                                        </h5>
                                        <?php if (!empty($contentAnalysis['images']['images_without_alt'])): ?>
                                            <div class="space-y-2 max-h-96 overflow-y-auto border border-[#ea4335] rounded p-2 bg-[#3a1e1e]">
                                                <?php foreach ($contentAnalysis['images']['images_without_alt'] as $img): ?>
                                                    <div class="flex items-center p-2 hover:bg-[#4a2e2e] rounded">
                                                        <div class="flex-shrink-0 w-12 h-12 bg-[#1e1e1e] rounded overflow-hidden">
                                                            <?php if (strpos($img['src'], 'http') === 0): ?>
                                                                <img src="<?= htmlspecialchars($img['src']) ?>" 
                                                                     alt="" 
                                                                     class="w-full h-full object-cover"
                                                                     onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>'">
                                                            <?php else: ?>
                                                                <div class="w-full h-full flex items-center justify-center text-[#5f6368]">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                    </svg>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="ml-3 flex-1 min-w-0">
                                                            <div class="text-sm text-[#ea4335] break-all">
                                                                Missing alt text
                                                            </div>
                                                            <div class="text-xs text-[#5f6368] truncate" title="<?= htmlspecialchars($img['src']) ?>">
                                                                <?= htmlspecialchars(basename(parse_url($img['src'], PHP_URL_PATH) ?: $img['src'])) ?>
                                                            </div>
                                                            <?php if (!empty($img['width']) && !empty($img['height'])): ?>
                                                                <div class="text-xs text-[#5f6368]">
                                                                    <?= $img['width'] ?> × <?= $img['height'] ?>px
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Images without Dimensions -->
                                    <div class="mb-4">
                                        <h5 class="text-sm font-medium text-[#fbbc04] mb-2">
                                            Images Without Dimensions (<?= count($contentAnalysis['images']['images_without_dimensions'] ?? []) ?>)
                                            <?php if (empty($contentAnalysis['images']['images_without_dimensions'])): ?>
                                                <span class="text-xs font-normal text-[#5f6368]">- All images have dimensions specified</span>
                                            <?php endif; ?>
                                        </h5>
                                        <?php if (!empty($contentAnalysis['images']['images_without_dimensions'])): ?>
                                            <div class="text-xs text-[#9aa0a6] mb-2">
                                                These images don't have explicit width and height attributes, which can cause layout shifts during page load.
                                            </div>
                                            <div class="space-y-2 max-h-96 overflow-y-auto border border-[#fbbc04] rounded p-2 bg-[#3a2e0e]">
                                                <?php foreach ($contentAnalysis['images']['images_without_dimensions'] as $img): ?>
                                                    <div class="flex items-center p-1.5 text-sm">
                                                        <span class="text-[#fbbc04] mr-2">•</span>
                                                        <span class="text-[#fbbc04] break-all">
                                                            <?= htmlspecialchars(basename(parse_url($img['src'], PHP_URL_PATH) ?: $img['src'])) ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form Detection -->
            <?php
            // Extract form information
            $forms = [];
            if (preg_match_all('/<form[^>]*>(.*?)<\/form>/is', $htmlContent, $formMatches)) {
                foreach ($formMatches[0] as $index => $formHtml) {
                    $form = [
                        'html' => $formHtml,
                        'action' => '',
                        'method' => 'get',
                        'inputs' => [],
                        'has_file_upload' => false
                    ];
                    
                    // Get form action (target URL)
                    if (preg_match('/action=["\']?([^"\' >]*)/i', $formHtml, $actionMatch)) {
                        $form['action'] = html_entity_decode($actionMatch[1]);
                        // Make relative URLs absolute
                        if (!preg_match('/^https?:\/\//i', $form['action'])) {
                            $baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
                            $form['action'] = rtrim($baseUrl, '/') . '/' . ltrim($form['action'], '/');
                        }
                    }
                    
                    // Get form method
                    if (preg_match('/method=["\'](GET|POST|PUT|DELETE|PATCH)["\']/i', $formHtml, $methodMatch)) {
                        $form['method'] = strtoupper($methodMatch[1]);
                    }
                    
                    // Get form inputs
                    if (preg_match_all('/<(input|textarea|select|button)[^>]*>/i', $formHtml, $inputMatches)) {
                        foreach ($inputMatches[0] as $inputHtml) {
                            $input = [
                                'type' => 'text',
                                'name' => '',
                                'value' => '',
                                'required' => false
                            ];
                            
                            // Get input type
                            if (preg_match('/type=["\']?([^"\' >]*)/i', $inputHtml, $typeMatch)) {
                                $input['type'] = strtolower($typeMatch[1]);
                            } elseif (strpos(strtolower($inputHtml), '<textarea') === 0) {
                                $input['type'] = 'textarea';
                            } elseif (strpos(strtolower($inputHtml), '<select') === 0) {
                                $input['type'] = 'select';
                            } elseif (strpos(strtolower($inputHtml), '<button') === 0) {
                                $input['type'] = 'button';
                            }
                            
                            // Check for file upload
                            if ($input['type'] === 'file') {
                                $form['has_file_upload'] = true;
                            }
                            
                            // Get input name
                            if (preg_match('/name=["\']?([^"\' >]*)/i', $inputHtml, $nameMatch)) {
                                $input['name'] = $nameMatch[1];
                            }
                            
                            // Get input value
                            if (preg_match('/value=["\']?([^"\' >]*)/i', $inputHtml, $valueMatch)) {
                                $input['value'] = html_entity_decode($valueMatch[1]);
                            }
                            
                            // Check if required
                            if (preg_match('/\brequired\b/i', $inputHtml)) {
                                $input['required'] = true;
                            }
                            
                            // Skip buttons without names
                            if (empty($input['name']) && in_array($input['type'], ['submit', 'button', 'reset'])) {
                                continue;
                            }
                            
                            $form['inputs'][] = $input;
                        }
                    }
                    
                    $forms[] = $form;
                }
            }
            ?>
            
            <?php if (!empty($forms)): ?>
                <div class="bg-[#000000] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                    <div class="px-4 py-5 sm:px-6 border-b border-[#3c4043]">
                        <h3 class="text-lg leading-6 font-medium text-white">
                            <i class="fas fa-window-restore mr-2 text-[#ff6b00]"></i> Form Submissions
                        </h3>
                        <p class="mt-1 text-sm text-[#9aa0a6]">
                            Found <?= count($forms) ?> form<?= count($forms) > 1 ? 's' : '' ?> on this page
                        </p>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <div class="space-y-6">
                            <?php foreach ($forms as $formIndex => $form): ?>
                                <div class="border border-[#3c4043] rounded-lg overflow-hidden">
                                    <div class="bg-[#2d2d2d] px-4 py-3 border-b border-[#3c4043]">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-sm font-medium text-white">
                                                Form #<?= $formIndex + 1 ?>
                                                <?php if (!empty($form['action'])): ?>
                                                    <span class="text-xs font-normal text-[#5f6368] ml-2">
                                                        (<?= htmlspecialchars($form['method']) ?> <?= htmlspecialchars($form['action']) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </h4>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#1e3a8a] text-[#60a5fa]">
                                                <?= $form['method'] ?: 'GET' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="p-4">
                                        <?php if (!empty($form['inputs'])): ?>
                                            <h5 class="text-sm font-medium text-[#9aa0a6] mb-2">Input Fields:</h5>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-[#3c4043]">
                                                    <thead class="bg-[#2d2d2d]">
                                                        <tr>
                                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-[#9aa0a6] uppercase tracking-wider">
                                                                Name
                                                            </th>
                                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-[#9aa0a6] uppercase tracking-wider">
                                                                Type
                                                            </th>
                                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-[#9aa0a6] uppercase tracking-wider">
                                                                Value
                                                            </th>
                                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-[#9aa0a6] uppercase tracking-wider">
                                                                Required
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-[#1e1e1e] divide-y divide-[#3c4043]">
                                                        <?php foreach ($form['inputs'] as $input): ?>
                                                            <tr>
                                                                <td class="px-4 py-2 whitespace-nowrap text-sm font-mono text-white">
                                                                    <?= htmlspecialchars($input['name']) ?: '<span class="text-[#5f6368]">(no name)</span>' ?>
                                                                </td>
                                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-[#9aa0a6]">
                                                                    <?= htmlspecialchars($input['type']) ?>
                                                                </td>
                                                                <td class="px-4 py-2 text-sm text-[#9aa0a6] break-all">
                                                                    <?= !empty($input['value']) ? htmlspecialchars($input['value']) : '<span class="text-[#5f6368]">(empty)</span>' ?>
                                                                </td>
                                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-[#9aa0a6]">
                                                                    <?= $input['required'] ? '✅' : '❌' ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-sm text-[#9aa0a6]">No form inputs detected.</p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($form['action'])): ?>
                                            <div class="mt-4 pt-4 border-t border-[#3c4043]">
                                                <h5 class="text-sm font-medium text-[#9aa0a6] mb-2">Form Target:</h5>
                                                <div class="flex items-center">
                                                    <code class="bg-[#2d2d2d] px-2 py-1 rounded text-sm break-all text-white">
                                                        <?= htmlspecialchars($form['method'] ?: 'GET') ?> <?= htmlspecialchars($form['action']) ?>
                                                    </code>
                                                    <button onclick="testFormEndpoint('<?= htmlspecialchars($form['action']) ?>', '<?= $form['method'] ?: 'GET' ?>')" 
                                                            class="ml-2 px-3 py-1 bg-[#ff6b00] text-white text-xs rounded hover:bg-[#e65a00] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#ff6b00]">
                                                        Test Endpoint
                                                    </button>
                                                </div>
                                                
                                                <?php if ($form['has_file_upload']): ?>
                                                    <div class="mt-2 flex items-center text-[#fbbc04] text-sm">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        <span>This form contains file upload fields</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- API Detection Summary -->
            <div class="bg-[#1e1e1e] dark:bg-[#1e1e1e] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                <div class="px-4 py-5 sm:px-6 border-b border-[#3c4043]">
                    <h3 class="text-lg leading-6 font-medium text-white">
                        <i class="fas fa-plug mr-2 text-[#ff6b00]"></i> API Detection
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <?php
                    // Enhanced API endpoint detection
                    $apiEndpoints = [];
                    $apiIndicators = [
                        '/api/', '/graphql', '/rest/', '/v1/', '/v2/',
                        'fetch("', 'fetch(`', 'axios.get("', 'axios.post("', '$.ajax(', 'XMLHttpRequest',
                        '"endpoint":', '"url":', '"api":', 'Content-Type: application/json'
                    ];
                    
                    // Find API endpoints in JavaScript code
                    if (preg_match_all('/(https?:\/\/[^\s"\'`;>]+\/(?:api|rest|graphql|v[0-9])(?:\/[^\s"\'`;>]*)?)/i', $htmlContent, $matches)) {
                        $apiEndpoints = array_merge($apiEndpoints, $matches[1]);
                    }
                    
                    // Find API endpoints in fetch/axios calls
                    if (preg_match_all('/(?:fetch|axios\.(?:get|post|put|delete))\([\'"`]([^\'"`]+)[\'"`]/i', $htmlContent, $matches)) {
                        foreach ($matches[1] as $endpoint) {
                            if (strpos($endpoint, 'http') === 0 || strpos($endpoint, '/') === 0) {
                                $apiEndpoints[] = $endpoint;
                            }
                        }
                    }
                    
                    // Remove duplicates and filter out common non-API URLs
                    $apiEndpoints = array_unique($apiEndpoints);
                    $apiEndpoints = array_filter($apiEndpoints, function($url) {
                        // Filter out common non-API URLs
                        $excludePatterns = ['\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot|map)$', 'google-analytics', 'googletagmanager', 'facebook', 'twitter', 'youtube'];
                        foreach ($excludePatterns as $pattern) {
                            if (preg_match('/' . $pattern . '/i', $url)) {
                                return false;
                            }
                        }
                        return true;
                    });
                    
                    $hasAPIs = !empty($apiEndpoints);
                    $apiCount = count($apiEndpoints);
                    ?>
                    
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <?php if ($hasAPIs): ?>
                                <div class="h-12 w-12 rounded-full bg-[#1e3a1e] flex items-center justify-center">
                                    <i class="fas fa-plug text-[#34a853] text-2xl"></i>
                                </div>
                            <?php else: ?>
                                <div class="h-12 w-12 rounded-full bg-[#3a1e1e] flex items-center justify-center">
                                    <i class="fas fa-times text-[#ea4335] text-2xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-medium text-white">
                                <?= $hasAPIs ? 'APIs Detected' : 'No APIs Detected' ?>
                            </h4>
                            <p class="text-sm text-[#9aa0a6]">
                                <?php if ($hasAPIs): ?>
                                    This page appears to contain API endpoints or API-related code.
                                    <?php if ($apiCount > 0): ?>
                                        Found <?= $apiCount ?> potential API indicators.
                                    <?php endif; ?>
                                <?php else: ?>
                                    No API endpoints or API-related code was detected on this page.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($hasAPIs): ?>
                    <div class="mt-6">
                        <h4 class="text-md font-medium text-white mb-3">
                            <i class="fas fa-link mr-2"></i> Detected API Endpoints
                        </h4>
                        <div class="bg-[#2d2d2d] rounded-lg border border-[#3c4043] overflow-hidden">
                            <div class="divide-y divide-[#3c4043]">
                                <?php foreach ($apiEndpoints as $endpoint): ?>
                                    <div class="p-3 hover:bg-[#3c4043] flex justify-between items-center group">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-mono text-[#60a5fa] break-all">
                                                <?= htmlspecialchars($endpoint) ?>
                                            </div>
                                        </div>
                                        <div class="flex space-x-1">
                                            <button onclick="testEndpoint('<?= htmlspecialchars($endpoint, ENT_QUOTES) ?>')" 
                                                    class="p-1.5 rounded-md text-[#5f6368] hover:text-[#60a5fa] hover:bg-[#1e3a8a]"
                                                    title="Test endpoint">
                                                <i class="fas fa-bolt"></i>
                                            </button>
                                            <button onclick="copyToClipboard('<?= htmlspecialchars($endpoint, ENT_QUOTES) ?>', this)" 
                                                    class="p-1.5 rounded-md text-[#5f6368] hover:text-white hover:bg-[#3c4043]"
                                                    title="Copy to clipboard">
                                                <i class="far fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 border-t border-[#3c4043] pt-4">
                        <h5 class="text-sm font-medium text-[#9aa0a6] mb-2">API Indicators Found:</h5>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($apiIndicators as $indicator): ?>
                                <?php if (stripos($htmlContent, $indicator) !== false): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-[#1e3a8a] text-[#60a5fa]">
                                        <i class="fas fa-search mr-1"></i> <?= htmlspecialchars($indicator) ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Security Analysis -->
            <div class="bg-[#1e1e1e] dark:bg-[#1e1e1e] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                <div class="px-4 py-5 sm:px-6 border-b border-[#3c4043]">
                    <h3 class="text-lg leading-6 font-medium text-white">
                        <i class="fas fa-shield-alt mr-2 text-[#ff6b00]"></i> Security Analysis
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <?php
                    // Check security headers
                    $securityHeaders = [
                        'Strict-Transport-Security' => 'Forces secure (HTTP over SSL/TLS) connections to the server',
                        'X-Content-Type-Options' => 'Prevents MIME-sniffing',
                        'X-Frame-Options' => 'Protects against clickjacking',
                        'X-XSS-Protection' => 'Enables XSS filtering',
                        'Content-Security-Policy' => 'Prevents XSS and other code injection attacks',
                        'Referrer-Policy' => 'Controls how much referrer information is included with requests',
                        'Permissions-Policy' => 'Controls browser features that can be used',
                        'Cross-Origin-Opener-Policy' => 'Prevents cross-origin attacks'
                    ];
                    
                    $sslInfo = [];
                    $sslScore = 0;
                    $maxSslScore = 5;
                    
                    // Check SSL/TLS
                    $host = parse_url($url, PHP_URL_HOST);
                    $sslContext = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
                    $sslStream = @stream_socket_client("ssl://$host:443", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $sslContext);
                    
                    if ($sslStream) {
                        $sslCert = stream_context_get_params($sslStream);
                        if (isset($sslCert['options']['ssl']['peer_certificate'])) {
                            $cert = openssl_x509_parse($sslCert['options']['ssl']['peer_certificate']);
                            $sslInfo['Issuer'] = $cert['issuer']['O'] ?? 'Unknown';
                            $sslInfo['Valid From'] = date('Y-m-d', $cert['validFrom_time_t']);
                            $sslInfo['Valid Until'] = date('Y-m-d', $cert['validTo_time_t']);
                            $sslInfo['Signature Algorithm'] = $cert['signatureTypeLN'] ?? 'Unknown';
                            $sslScore += 2; // Basic SSL present
                            
                            // Check certificate expiration (more than 30 days = good)
                            $daysToExpire = floor(($cert['validTo_time_t'] - time()) / (60 * 60 * 24));
                            if ($daysToExpire > 30) {
                                $sslScore++;
                            }
                            
                            // Check if using modern TLS (TLS 1.2 or higher)
                            if (isset($cert['extensions']['extendedKeyUsage']) && 
                                strpos($cert['extensions']['extendedKeyUsage'], 'TLS Web Server Authentication') !== false) {
                                $sslScore++;
                            }
                        }
                        fclose($sslStream);
                    }
                    
                    // Check for common vulnerabilities
                    $vulnerabilities = [];
                    
                    // Check for SQL Injection patterns in URL
                    if (preg_match('/(union.*select|select.*from|insert\s+into|update\s+\w+\s+set|delete\s+from)/i', $url)) {
                        $vulnerabilities[] = [
                            'type' => 'SQL Injection',
                            'severity' => 'High',
                            'description' => 'Potential SQL injection attempt detected in URL parameters',
                            'recommendation' => 'Use prepared statements and parameterized queries'
                        ];
                    }
                    
                    // Check for XSS patterns in URL
                    if (preg_match('/(<script|javascript:|on\w+\s*=)/i', $url)) {
                        $vulnerabilities[] = [
                            'type' => 'Cross-Site Scripting (XSS)',
                            'severity' => 'High',
                            'description' => 'Potential XSS attempt detected in URL',
                            'recommendation' => 'Implement proper output encoding and input validation'
                        ];
                    }
                    
                    // Check for directory traversal
                    if (strpos($url, '../') !== false || strpos($url, '..\\') !== false) {
                        $vulnerabilities[] = [
                            'type' => 'Directory Traversal',
                            'severity' => 'Medium',
                            'description' => 'Potential directory traversal attempt detected',
                            'recommendation' => 'Validate and sanitize all user-supplied input'
                        ];
                    }
                    
                    // Check for exposed admin panels
                    $adminPaths = ['/admin', '/wp-admin', '/administrator', '/backend'];
                    foreach ($adminPaths as $path) {
                        if (strpos($url, $path) !== false) {
                            $vulnerabilities[] = [
                                'type' => 'Exposed Admin Panel',
                                'severity' => 'Medium',
                                'description' => 'Admin panel is accessible without authentication',
                                'recommendation' => 'Implement proper access controls and authentication'
                            ];
                            break;
                        }
                    }
                    
                    // Check for sensitive files
                    $sensitiveFiles = [
                        '.env' => 'Environment configuration file',
                        '.git/config' => 'Git configuration',
                        'wp-config.php' => 'WordPress configuration',
                        'config.php' => 'Application configuration'
                    ];
                    
                    foreach ($sensitiveFiles as $file => $desc) {
                        $testUrl = rtrim($url, '/') . '/' . $file;
                        $headers = @get_headers($testUrl);
                        if ($headers && strpos($headers[0], '200') !== false) {
                            $vulnerabilities[] = [
                                'type' => 'Sensitive File Exposure',
                                'severity' => 'High',
                                'description' => "$desc is publicly accessible",
                                'recommendation' => 'Restrict access to sensitive files'
                            ];
                        }
                    }
                    
                    // Calculate security score (0-100)
                    $securityScore = 0;
                    $maxScore = 100;
                    
                    // SSL Score (0-20)
                    $securityScore += ($sslScore / $maxSslScore) * 20;
                    
                    // Security Headers (0-40)
                    $headerScore = 0;
                    foreach ($securityHeaders as $header => $desc) {
                        if (isset($rawHeaders[$header])) {
                            $headerScore += 5; // 5 points per header
                        }
                    }
                    $securityScore += min($headerScore, 40);
                    
                    // Vulnerabilities (0-40, subtract points)
                    $vulnPenalty = count($vulnerabilities) * 5;
                    $securityScore = max(0, $securityScore - $vulnPenalty);
                    
                    // Determine security level
                    $securityLevel = 'Low';
                    if ($securityScore >= 80) $securityLevel = 'Excellent';
                    elseif ($securityScore >= 60) $securityLevel = 'Good';
                    elseif ($securityScore >= 40) $securityLevel = 'Moderate';
                    elseif ($securityScore >= 20) $securityLevel = 'Poor';
                    
                    // Security level colors
                    $securityColors = [
                        'Excellent' => '#34a853',
                        'Good' => '#4285f4',
                        'Moderate' => '#fbbc04',
                        'Poor' => '#ff6b00',
                        'Low' => '#ea4335'
                    ];
                    ?>
                    
                    <!-- Security Overview -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-md font-medium text-white">Security Score</h4>
                            <span class="px-3 py-1 rounded-full text-sm font-medium text-white" style="background-color: <?= $securityColors[$securityLevel] ?>;">
                                <?= $securityLevel ?> (<?= round($securityScore) ?>/100)
                            </span>
                        </div>
                        <div class="w-full bg-[#2d2d2d] rounded-full h-2.5">
                            <div class="h-2.5 rounded-full" 
                                style="width: <?= $securityScore ?>%; background-color: <?= $securityColors[$securityLevel] ?>;">
                            </div>
                        </div>
                    </div>
                    
                    <!-- SSL/TLS Information -->
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-white mb-2">SSL/TLS Information</h4>
                        <?php if (!empty($sslInfo)): ?>
                            <div class="bg-[#000000] p-4 rounded-lg border border-[#3c4043]">
                                <div class="grid grid-cols-2 gap-4">
                                    <?php foreach ($sslInfo as $key => $value): ?>
                                        <div>
                                            <span class="text-sm font-medium text-[#9aa0a6]"><?= $key ?>:</span>
                                            <span class="text-sm text-white"><?= $value ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-3 text-sm">
                                    <span class="font-medium text-[#9aa0a6]">SSL Score:</span>
                                    <span class="text-[#5f6368]">
                                        <?= $sslScore ?>/<?= $maxSslScore ?> 
                                        (<?= round(($sslScore / $maxSslScore) * 100) ?>%)
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-[#9aa0a6]">No SSL/TLS information available.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Security Headers -->
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-white mb-2">Security Headers</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-[#3c4043]">
                                <thead class="bg-[#2d2d2d]">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-[#9aa0a6] uppercase tracking-wider">
                                            Header
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-[#9aa0a6] uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-[#9aa0a6] uppercase tracking-wider">
                                            Description
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-[#1e1e1e] divide-y divide-[#3c4043]">
                                    <?php foreach ($securityHeaders as $header => $description): ?>
                                        <?php $hasHeader = isset($rawHeaders[$header]); ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-mono text-white">
                                                <?= $header ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <?php if ($hasHeader): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-[#1e3a1e] text-[#34a853]">
                                                        Present
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-[#3a1e1e] text-[#ea4335]">
                                                        Missing
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-[#9aa0a6]">
                                                <?= $description ?>
                                                <?php if ($hasHeader): ?>
                                                    <div class="text-xs text-[#34a853] mt-1">
                                                        <?= htmlspecialchars($rawHeaders[$header]) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Detected Vulnerabilities -->
                    <?php if (!empty($vulnerabilities)): ?>
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-white mb-2">
                                Potential Vulnerabilities
                                <span class="ml-2 px-2 py-0.5 bg-[#ea4335] text-white text-xs font-medium rounded-full">
                                    <?= count($vulnerabilities) ?> found
                                </span>
                            </h4>
                            <div class="space-y-4">
                                <?php foreach ($vulnerabilities as $vuln): ?>
                                    <div class="border-l-4 border-[#ea4335] bg-[#3a1e1e] p-4 rounded-r">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-[#ea4335]" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <div class="flex items-center">
                                                    <h5 class="text-sm font-medium text-[#ea4335]">
                                                        <?= $vuln['type'] ?>
                                                        <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full 
                                                            <?= $vuln['severity'] === 'High' ? 'bg-[#ea4335] text-white' : '' ?>
                                                            <?= $vuln['severity'] === 'Medium' ? 'bg-[#3a2e0e] text-[#fbbc04]' : '' ?>
                                                            <?= $vuln['severity'] === 'Low' ? 'bg-[#2d2d2d] text-[#9aa0a6]' : '' ?>">
                                                            <?= $vuln['severity'] ?>
                                                        </span>
                                                    </h5>
                                                </div>
                                                <div class="mt-1 text-sm text-[#ea4335]">
                                                    <?= $vuln['description'] ?>
                                                </div>
                                                <div class="mt-2 text-sm">
                                                    <p class="text-sm font-medium text-white">Recommendation:</p>
                                                    <p class="text-sm text-[#9aa0a6]"><?= $vuln['recommendation'] ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="rounded-md bg-[#1e3a1e] p-4 mb-6 border border-[#34a853]">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-[#34a853]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-[#34a853]">
                                        No critical vulnerabilities detected
                                    </h3>
                                    <div class="mt-2 text-sm text-[#34a853]">
                                        <p>No obvious security vulnerabilities were found during the scan. However, this doesn't guarantee the absence of all security issues.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Security Recommendations -->
                    <div class="bg-[#1e3a8a] p-4 rounded-lg border border-[#3c4043]">
                        <h4 class="text-md font-medium text-[#60a5fa] mb-2">
                            <i class="fas fa-lightbulb mr-1"></i> Security Recommendations
                        </h4>
                        <ul class="list-disc pl-5 space-y-1 text-sm text-[#60a5fa]">
                            <?php if ($sslScore < $maxSslScore): ?>
                                <li>Ensure SSL/TLS is properly configured with a valid certificate</li>
                                <li>Consider using HSTS to enforce HTTPS connections</li>
                            <?php endif; ?>
                            
                            <?php 
                            $missingHeaders = array_diff_key($securityHeaders, array_intersect_key($rawHeaders, $securityHeaders));
                            if (!empty($missingHeaders)): 
                            ?>
                                <li>Add missing security headers: 
                                    <?= implode(', ', array_map(function($h) { 
                                        return '<code class="text-xs">' . htmlspecialchars($h) . '</code>'; 
                                    }, array_keys($missingHeaders))) ?>
                                </li>
                            <?php endif; ?>
                            
                            <?php if (empty($vulnerabilities)): ?>
                                <li>Regularly update all software components to patch known vulnerabilities</li>
                                <li>Implement a Web Application Firewall (WAF) for additional protection</li>
                                <li>Conduct regular security audits and penetration testing</li>
                            <?php endif; ?>
                            
                            <li>Implement rate limiting to prevent brute force attacks</li>
                            <li>Ensure proper input validation and output encoding</li>
                            <li>Keep all software and dependencies up to date</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- HTTP Headers -->
            <div class="bg-[#1e1e1e] dark:bg-[#1e1e1e] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                <div class="px-4 py-5 sm:px-6 border-b border-[#3c4043]">
                    <h3 class="text-lg leading-6 font-medium text-white">
                        HTTP Headers
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[#3c4043]">
                        <tbody class="bg-[#1e1e1e] divide-y divide-[#3c4043]">
                            <?php foreach ($rawHeaders as $name => $value): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white">
                                        <?= htmlspecialchars($name) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-normal break-all text-sm text-[#9aa0a6]">
                                        <?= is_array($value) ? htmlspecialchars(implode(', ', $value)) : htmlspecialchars($value) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($htmlContent) || !empty($responseBody)): ?>
                <!-- Raw Response (initially hidden) -->
                <div id="raw-response" class="hidden bg-[#1e1e1e] rounded-lg p-4 mb-8 border border-[#3c4043]">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="text-lg font-medium text-white">Complete Server Response</h3>
                        <button onclick="copyToClipboard('raw-response-content')" class="text-[#5f6368] hover:text-white" title="Copy to clipboard">
                            <i class="far fa-copy"></i>
                        </button>
                    </div>
                    <div class="bg-black p-4 rounded overflow-auto max-h-96">
                        <pre id="raw-response-content" class="text-green-400 text-sm font-mono">
<?php
// Display response headers
if (!empty($headers)) {
    echo "<span class='text-blue-300'>HTTP/1.1 200 OK</span>\n";
    foreach ($headers as $name => $values) {
        $value = is_array($values) ? implode(', ', $values) : $values;
        echo "<span class='text-purple-400'>" . htmlspecialchars($name) . ":</span> <span class='text-yellow-300'>" . htmlspecialchars($value) . "</span>\n";
    }
    echo "\n";
}

// Display full response body without any limitation
$responseToShow = $responseBody ?? $htmlContent;
echo htmlspecialchars($responseToShow, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
?></pre>
                    </div>
                </div>
                
                <!-- File Information -->
                <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg mb-4 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Source File</h4>
                            <div class="flex items-center">
                                <?php
                                $path = parse_url($url, PHP_URL_PATH);
                                $filename = $path ? basename($path) : 'index';
                                $pathInfo = pathinfo($filename);
                                $name = $pathInfo['filename'] ?? 'index';
                                $extension = $pathInfo['extension'] ?? null;
                                
                                // If no extension in URL, try to determine from Content-Type header
                                if (empty($extension) && !empty($headers['Content-Type'][0])) {
                                    $contentType = $headers['Content-Type'][0];
                                    $mimeToExt = [
                                        'text/html' => 'html',
                                        'text/plain' => 'txt',
                                        'application/json' => 'json',
                                        'application/xml' => 'xml',
                                        'application/pdf' => 'pdf',
                                        'image/jpeg' => 'jpg',
                                        'image/png' => 'png',
                                        'image/gif' => 'gif',
                                        'application/javascript' => 'js',
                                        'text/css' => 'css',
                                        'text/x-php' => 'php',
                                        'application/x-httpd-php' => 'php',
                                        'application/x-httpd-php-source' => 'php',
                                    ];
                                    
                                    // Check if content type matches any known MIME type
                                    foreach ($mimeToExt as $mime => $ext) {
                                        if (stripos($contentType, $mime) !== false) {
                                            $extension = $ext;
                                            break;
                                        }
                                    }
                                    
                                    // If still no extension and it's a directory-like URL, assume HTML
                                    if (empty($extension) && (empty($path) || substr($path, -1) === '/')) {
                                        $extension = 'html';
                                        $name = $name === 'index' ? $name : $name . '/index';
                                    }
                                }
                                ?>
                                <span class="font-mono text-sm text-gray-900 dark:text-white">
                                    <?= htmlspecialchars($name) ?>
                                </span>
                                <?php if (!empty($extension)): ?>
                                    <span class="text-blue-600 dark:text-blue-400 font-medium">.<?= htmlspecialchars($extension) ?></span>
                                <?php endif; ?>
                                <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                    (<?= number_format(strlen($htmlContent)) ?> bytes)
                                </span>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <?= htmlspecialchars(parse_url($url, PHP_URL_HOST)) ?>
                            </div>
                            <div class="text-xs text-gray-400">
                                <?= htmlspecialchars(parse_url($url, PHP_URL_PATH) ?: '/') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-[#000000] shadow overflow-hidden sm:rounded-lg mb-8 border border-[#3c4043]">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                            Full HTML Content
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Complete HTML response
                        </p>
                    </div>
                    <div class="px-4 py-5 sm:p-6">
                        <div class="relative">
                            <button onclick="copyToClipboard('html-content')" class="absolute top-2 right-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 text-xs px-2 py-1 rounded">
                                <i class="fas fa-copy mr-1"></i> Copy
                            </button>
                            <div class="relative">
                                <pre id="html-content" class="language-markup line-numbers" style="margin: 0; border-radius: 0.5rem;"><code class="language-html"><?= htmlspecialchars($htmlContent) ?></code></pre>
                            </div>
                            <script>
                                // Initialize Prism.js after content is loaded
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Process HTML content with Prism
                                    if (typeof Prism !== 'undefined') {
                                        // Normalize whitespace for better formatting
                                        Prism.plugins.NormalizeWhitespace.setDefaults({
                                            'remove-trailing': true,
                                            'remove-indent': true,
                                            'left-trim': true,
                                            'right-trim': true,
                                            'break-lines': 80,
                                            'indent': 2,
                                            'remove-initial-line-feed': true,
                                            'tabs-to-spaces': 4,
                                            'spaces-to-tabs': 4
                                        });
                                        
                                        // Highlight all pre elements
                                        Prism.highlightAll();
                                        
                                        // Add line numbers
                                        if (Prism.plugins.lineNumbers) {
                                            Prism.highlightAll();
                                            Prism.plugins.lineNumbers.resize();
                                        }
                                    }
                                });
                            </script>
                        </div>
                        <script>
                        function copyToClipboard(elementId) {
                            const element = document.getElementById(elementId);
                            const text = element.innerText;
                            navigator.clipboard.writeText(text).then(() => {
                                const button = element.previousElementSibling;
                                const originalText = button.innerHTML;
                                button.innerHTML = '<i class="fas fa-check mr-1"></i> Copied!';
                                setTimeout(() => {
                                    button.innerHTML = originalText;
                                }, 2000);
                            });
                        }
                        </script>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                &copy; <?= date('Y') ?> TechDetector. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
        // Theme Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('theme-toggle');
            const themeIcon = document.getElementById('theme-icon');
            const html = document.documentElement;
            const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');

            // Function to set the theme
            function setTheme(theme) {
                if (theme === 'dark') {
                    html.classList.add('dark');
                    if (themeIcon) themeIcon.textContent = '☀️';
                    localStorage.setItem('theme', 'dark');
                } else {
                    html.classList.remove('dark');
                    if (themeIcon) themeIcon.textContent = '🌙';
                    localStorage.setItem('theme', 'light');
                }
            }

            // Check for saved user preference or use system preference
            const savedTheme = localStorage.getItem('theme');
            let currentTheme;

            if (savedTheme) {
                currentTheme = savedTheme;
            } else {
                currentTheme = prefersDarkScheme.matches ? 'dark' : 'light';
            }

            // Apply the theme
            setTheme(currentTheme);

            // Listen for system theme changes
            prefersDarkScheme.addListener((e) => {
                if (!localStorage.getItem('theme')) {
                    setTheme(e.matches ? 'dark' : 'light');
                }
            });

            // Toggle theme on button click
            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    const currentTheme = localStorage.getItem('theme') || (prefersDarkScheme.matches ? 'dark' : 'light');
                    setTheme(currentTheme === 'dark' ? 'light' : 'dark');
                });
            }
        });

        // Copy text to clipboard
        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(() => {
                // Show success feedback
                const icon = button.querySelector('i');
                const originalIcon = icon.className;
                icon.className = 'fas fa-check text-green-500';
                button.disabled = true;
                
                // Reset button after 2 seconds
                setTimeout(() => {
                    icon.className = originalIcon;
                    button.disabled = false;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
            });
        }
        
        // Test API endpoint
        async function testEndpoint(url) {
            // Show loading state
            const modal = document.getElementById('endpointTestModal');
            const modalContent = document.getElementById('endpointTestContent');
            const modalTitle = document.getElementById('endpointTestTitle');
            
            modalTitle.textContent = `Testing: ${url}`;
            modalContent.innerHTML = `
                <div class="flex justify-center items-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
                    <span class="ml-3 text-gray-700 dark:text-gray-300">Testing endpoint...</span>
                </div>
            `;
            
            // Show modal
            modal.classList.remove('hidden');
            
            try {
                const response = await fetch(`test_endpoint.php?url=${encodeURIComponent(url)}`);
                const result = await response.json();
                
                // Format the response
                const statusClass = response.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
                const statusText = response.ok ? 'Success' : `Error: ${response.status} ${response.statusText || ''}`;
                
                // Format response data with syntax highlighting
                let responseContent = '';
                try {
                    const formattedJson = JSON.stringify(result, null, 2);
                    responseContent = `<pre class="bg-gray-100 dark:bg-gray-700 p-4 rounded overflow-auto max-h-96"><code class="language-json">${formattedJson}</code></pre>`;
                } catch (e) {
                    responseContent = `<div class="bg-gray-100 dark:bg-gray-700 p-4 rounded overflow-auto max-h-96">${result}</div>`;
                }
                
                // Update modal with results
                modalContent.innerHTML = `
                    <div class="space-y-4">
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Test Results</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">${url}</p>
                        </div>
                        
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <span class="font-medium text-gray-700 dark:text-gray-300 w-24">Status:</span>
                                <span class="${statusClass} font-mono text-sm">${statusText}</span>
                            </div>
                            <div class="flex items-start">
                                <span class="font-medium text-gray-700 dark:text-gray-300 w-24 mt-1">Response:</span>
                                <div class="flex-1">
                                    ${responseContent}
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button onclick="copyToClipboard(JSON.stringify(${JSON.stringify(result)}, null, 2), this)" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="far fa-copy mr-2"></i> Copy Response
                            </button>
                        </div>
                    </div>
                `;
                
                // Highlight syntax if Prism.js is available
                if (window.Prism) {
                    Prism.highlightAllUnder(modalContent);
                }
                
            } catch (error) {
                modalContent.innerHTML = `
                    <div class="rounded-md bg-red-50 dark:bg-red-900/20 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Error Testing Endpoint</h3>
                                <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                    <p>Failed to test the endpoint. Please check the console for more details.</p>
                                    <p class="mt-2 font-mono text-xs">${error.message}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                console.error('Error testing endpoint:', error);
            }
        }
        
        // Close modal function
        function closeEndpointTestModal() {
            document.getElementById('endpointTestModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside content
        document.getElementById('endpointTestModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEndpointTestModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEndpointTestModal();
            }
        });
        
        // Copy to clipboard
        function copyToClipboardText(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Show success feedback
                const button = event.target.closest('button');
                const originalHTML = button.innerHTML;
                button.title = 'Copied!';
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.title = 'Copy to Clipboard';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
        
        // Toggle raw response visibility
        function toggleRawResponse() {
            const rawResponse = document.getElementById('raw-response');
            rawResponse.classList.toggle('hidden');
            
            // Scroll to the raw response when showing
            if (!rawResponse.classList.contains('hidden')) {
                rawResponse.scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        // Copy to clipboard function
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const range = document.createRange();
            range.selectNode(element);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            document.execCommand('copy');
            window.getSelection().removeAllRanges();
            
            // Show copied feedback
            const button = event.target.closest('button');
            const originalTitle = button.title;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.title = 'Copied!';
            setTimeout(() => {
                button.innerHTML = '<i class="far fa-copy"></i>';
                button.title = originalTitle;
            }, 2000);
        }
        
        // Enable dark mode toggle if needed
        // Theme Toggle Functionality
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const html = document.documentElement;
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');

        // Function to set the theme
        function setTheme(theme) {
            if (theme === 'dark') {
                html.classList.add('dark');
                html.setAttribute('data-theme', 'dark');
                if (themeIcon) themeIcon.textContent = '☀️';
                localStorage.setItem('theme', 'dark');
            } else {
                html.classList.remove('dark');
                html.removeAttribute('data-theme');
                if (themeIcon) themeIcon.textContent = '🌙';
                localStorage.setItem('theme', 'light');
            }
        }

        // Check for saved user preference or use system preference
        const savedTheme = localStorage.getItem('theme');
        let currentTheme;

        if (savedTheme) {
            currentTheme = savedTheme;
        } else {
            currentTheme = prefersDarkScheme.matches ? 'dark' : 'light';
        }

        // Apply the theme
        setTheme(currentTheme);

        // Listen for system theme changes
        prefersDarkScheme.addListener((e) => {
            if (!localStorage.getItem('theme')) {
                setTheme(e.matches ? 'dark' : 'light');
            }
        });

        // Toggle theme on button click
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const currentTheme = localStorage.getItem('theme') || (prefersDarkScheme.matches ? 'dark' : 'light');
                setTheme(currentTheme === 'dark' ? 'light' : 'dark');
            });
        }

        // Technologies Used Section
        document.addEventListener('DOMContentLoaded', function() {
            const mainContent = document.querySelector('main');
            if (mainContent) {
                const techSection = document.createElement('div');
                techSection.className = 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8';
                techSection.innerHTML = `
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                <i class="fas fa-code mr-2"></i> Technologies Used
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                This application is built using the following technologies
                            </p>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <!-- PHP -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <div class="flex items-center mb-3">
                                        <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg mr-3">
                                            <i class="fab fa-php text-blue-600 dark:text-blue-300 text-2xl"></i>
                                        </div>
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-white">PHP</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        Server-side scripting language for backend processing, form handling, and dynamic content generation.
                                    </p>
                                </div>

                                <!-- JavaScript -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <div class="flex items-center mb-3">
                                        <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg mr-3">
                                            <i class="fab fa-js text-yellow-600 dark:text-yellow-300 text-2xl"></i>
                                        </div>
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-white">JavaScript</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        Client-side scripting for dynamic UI updates, form validation, and asynchronous requests.
                                    </p>
                                </div>

                                <!-- HTML5 -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <div class="flex items-center mb-3">
                                        <div class="p-2 bg-orange-100 dark:bg-orange-900 rounded-lg mr-3">
                                            <i class="fab fa-html5 text-orange-600 dark:text-orange-300 text-2xl"></i>
                                        </div>
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-white">HTML5</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        Markup language for structuring and presenting content on the web.
                                    </p>
                                </div>

                                <!-- Tailwind CSS -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <div class="flex items-center mb-3">
                                        <div class="p-2 bg-cyan-100 dark:bg-cyan-900 rounded-lg mr-3">
                                            <i class="fas fa-wind text-cyan-600 dark:text-cyan-300 text-2xl"></i>
                                        </div>
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-white">Tailwind CSS</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        Utility-first CSS framework for building responsive and modern user interfaces.
                                    </p>
                                </div>

                                <!-- Font Awesome -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <div class="flex items-center mb-3">
                                        <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg mr-3">
                                            <i class="fab fa-font-awesome text-blue-600 dark:text-blue-300 text-2xl"></i>
                                        </div>
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-white">Font Awesome</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        Icon library for adding scalable vector icons to the user interface.
                                    </p>
                                </div>

                                <!-- JSON -->
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <div class="flex items-center mb-3">
                                        <div class="p-2 bg-gray-200 dark:bg-gray-600 rounded-lg mr-3">
                                            <span class="text-gray-700 dark:text-gray-200 font-mono text-xl">{ }</span>
                                        </div>
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-white">JSON</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        Lightweight data-interchange format used for configuration and rule sets.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                mainContent.appendChild(techSection);
            }
        });
    </script>
    
    <!-- Endpoint Test Modal -->
    <div id="endpointTestModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 id="endpointTestTitle" class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-2">
                                Testing Endpoint
                            </h3>
                            <div id="endpointTestContent" class="mt-2">
                                <!-- Content will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeEndpointTestModal()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Prism.js for syntax highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
</body>
</html>
