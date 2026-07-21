<?php
/**
 * SMTP Configuration for Gmail
 * 
 * This file contains the SMTP settings for sending emails using Gmail's SMTP server.
 * You'll need to enable 'Less secure app access' or use an App Password if 2FA is enabled.
 */

return [
    // Gmail SMTP Configuration
    'host' => 'smtp.gmail.com',
    'username' => 'otienobrian029@gmail.com',  // Your Gmail address
    'password' => 'your_app_specific_password', // Generate App Password from Google Account
    'port' => 587,  // Gmail SMTP port for TLS
    'encryption' => 'tls',  // Encryption to use - TLS is required for Gmail
    'auth' => true,  // Enable SMTP authentication
    
    // Email Settings
    'from_email' => 'otienobrian029@gmail.com',
    'from_name' => 'TechDetector',
    'debug' => 2,  // Enable debug output
    
    // Additional Options
    'debug' => 2,  // Set to 2 for debugging, 0 for production
    'debug_output' => 'html',  // Output debug info to the browser
    'smtp_options' => [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ],
    
    // Email Templates
    'verification_subject' => 'Verify Your Email Address',
    'verification_body' => '<p>Welcome to our platform! Your verification code is: <strong>{code}</strong></p>',
];
