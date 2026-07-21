<?php
// 404 Error Page
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | TechDetector</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/ntsa-style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #000000;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            color: #ff6b00;
            line-height: 1;
            margin-bottom: 1rem;
        }
        
        .error-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #ffffff;
        }
        
        .error-message {
            color: #9ca3af;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #ff6b00;
            margin-bottom: 1rem;
        }
        
        .btn-home {
            display: inline-block;
            background-color: #ff6b00;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s, transform 0.2s;
            margin: 0.5rem;
        }
        
        .btn-home:hover {
            background-color: #e65a00;
            transform: translateY(-2px);
        }
        
        .btn-dashboard {
            display: inline-block;
            background-color: #1a1a1a;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s, transform 0.2s;
            border: 2px solid #3c4043;
            margin: 0.5rem;
        }
        
        .btn-dashboard:hover {
            background-color: #2d2d2d;
            border-color: #ff6b00;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .error-code {
                font-size: 6rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .error-message {
                font-size: 1rem;
            }
            
            .btn-home,
            .btn-dashboard {
                display: block;
                width: 100%;
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-message">
            Sorry, the page you are looking for doesn't exist or has been moved. 
            Please check the URL or navigate to another section of the site.
        </p>
        <div class="button-container">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashbord" class="btn-dashboard">
                    <i class="fas fa-home mr-2"></i>Go to Dashboard
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn-home">
                <i class="fas fa-search mr-2"></i>Go to Homepage
            </a>
        </div>
    </div>
</body>
</html>
