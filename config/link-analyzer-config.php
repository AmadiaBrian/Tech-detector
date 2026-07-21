<?php

return [
    // General settings
    'timezone' => 'UTC',
    'max_execution_time' => 300, // 5 minutes
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    
    // API Settings
    'apis' => [
        'google_safe_browsing' => [
            'enabled' => false,
            'api_key' => 'YOUR_GOOGLE_SAFE_BROWSING_API_KEY',
            'client_id' => 'link-analyzer',
            'endpoint' => 'https://safebrowsing.googleapis.com/v4/threatMatches:find'
        ],
        'wappalyzer' => [
            'enabled' => false,
            'api_key' => 'YOUR_WAPPALYZER_API_KEY',
            'endpoint' => 'https://api.wappalyzer.com/v2/lookup'
        ],
        'screenshot' => [
            'enabled' => false,
            'api_key' => 'YOUR_SCREENSHOT_API_KEY',
            'endpoint' => 'https://screenshotapi.net/api/v1/screenshot'
        ]
    ],
    
    // Security settings
    'security' => [
        'allowed_domains' => [], // Empty array allows all domains
        'blocked_ips' => [],
        'rate_limiting' => [
            'enabled' => true,
            'requests_per_minute' => 60
        ]
    ],
    
    // Analysis settings
    'analysis' => [
        'max_redirects' => 10,
        'timeout' => 30, // seconds
        'verify_ssl' => false // Set to true in production
    ]
];
