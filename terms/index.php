<?php
$pageTitle = 'Terms of Service - TechDetector';
$activePage = 'terms-of-service';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Read our Terms of Service to understand the rules and guidelines for using TechDetector's website analysis services.">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                    },
                },
            },
        }
    </script>
    <style>
        .prose {
            max-width: 65ch;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .prose h1 {
            @apply text-4xl font-bold text-gray-900 dark:text-white mb-6 text-center;
        }
        .prose h2 {
            @apply text-2xl font-semibold text-gray-800 dark:text-gray-100 mt-8 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700;
        }
        .prose p {
            @apply text-gray-600 dark:text-gray-300 leading-relaxed mb-4;
        }
        .prose ul {
            @apply list-disc pl-6 mb-6;
        }
        .prose li {
            @apply text-gray-600 dark:text-gray-300 mb-2;
        }
        .prose a {
            @apply text-blue-600 dark:text-blue-400 hover:underline;
        }
        .last-updated {
            @apply text-gray-500 dark:text-gray-400 text-center mb-8 text-sm;
        }
        .container {
            @apply max-w-7xl mx-auto px-4 sm:px-6 lg:px-8;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors duration-200 flex flex-col">
<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active link highlighting
$currentPage = $activePage ?? basename($_SERVER['PHP_SELF'], '.php');
?>
<header class="bg-white dark:bg-gray-800 shadow-sm">
    <div class="container mx-auto px-4">
        <nav class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <a href="../" class="flex items-center space-x-2">
                    <i class="fas fa-search text-blue-600 dark:text-blue-400 text-xl"></i>
                    <span class="text-xl font-bold text-gray-900 dark:text-white"><span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span></span>
                </a>
            </div>
            
            <div class="hidden md:flex items-center space-x-8">
                <a href="../" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 <?= $currentPage === 'index' ? 'font-medium text-blue-600 dark:text-blue-400' : '' ?>">
                    Home
                </a>
                <a href="#features" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">
                    Features
                </a>
                <a href="#how-it-works" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">
                    How It Works
                </a>
                <a href="#about" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200">
                    About
                </a>
                <a href="../privecy" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 <?= $currentPage === 'privacy-policy' ? 'font-medium text-blue-600 dark:text-blue-400' : '' ?>">
                    Privacy
                </a>
                <a href="" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors duration-200 <?= $currentPage === 'terms-of-service' ? 'font-medium text-blue-600 dark:text-blue-400' : '' ?>">
                    Terms
                </a>
                <a href="domain-dashboard/" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200 <?= $currentPage === 'domain-dashboard' ? 'bg-blue-700' : '' ?>">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    DASHBOARD
                </a>
                <button id="theme-toggle" class="p-2 rounded-full text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none" aria-label="Toggle dark mode">
                    <svg id="theme-toggle-dark-icon" class="w-5 h-5 hidden" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                    </svg>
                    <svg id="theme-toggle-light-icon" class="w-5 h-5 hidden" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center">
                <button id="mobile-menu-button" class="text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                    </svg>
                </button>
            </div>
        </nav>
    </div>
    
    <!-- Mobile menu -->
    <div id="mobile-menu" class="md:hidden hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-white dark:bg-gray-800 shadow-lg">
            <a href="../" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                Home
            </a>
            <a href="#features" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                Features
            </a>
            <a href="#how-it-works" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                How It Works
            </a>
            <a href="#about" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                About
            </a>
            <a href="../privecy" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                Privacy Policy
            </a>
            <a href="" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                Terms of Service
            </a>
            <div class="px-3 py-2">
                <button id="mobile-theme-toggle" class="flex items-center text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                    <span class="mr-2">Toggle Dark Mode</span>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path id="mobile-theme-icon" d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</header>

    <main class="py-16 px-4">
        <div class="container mx-auto">
            <div class="terms-content">
                <h1 class="section-title">Terms of Service</h1>
                <p class="last-updated">Last updated: <?= date('F j, Y') ?></p>
                
                <div class="terms-text">
                    <section>
                        <h2>1. Acceptance of Terms</h2>
                        <p>
                            By accessing or using TechDetector ("Service"), you agree to be bound by these Terms of Service. 
                            If you disagree with any part of the terms, you may not access the Service.
                        </p>
                    </section>

                    <section>
                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">2. Description of Service</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">
                                TechDetector provides website analysis services including but not limited to technology detection, performance analysis, and security assessment. The Service is provided "as is" and we make no warranties regarding its accuracy or reliability.
                            </p>
                        </section>

                        <section class="mb-8">
                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">3. User Responsibilities</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">You agree not to:</p>
                            <ul class="list-disc pl-6 text-gray-600 dark:text-gray-300 space-y-2 mb-4">
                                <li>Use the Service for any illegal purpose or in violation of any laws</li>
                                <li>Attempt to gain unauthorized access to our systems or networks</li>
                                <li>Use the Service to scan websites without permission</li>
                                <li>Interfere with or disrupt the Service or servers</li>
                                <li>Submit malicious code or content</li>
                            </ul>
                        </section>

                        <section class="mb-8">
                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">4. Intellectual Property</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">
                                The Service and its original content, features, and functionality are owned by TechDetector and are protected by international copyright, trademark, and other intellectual property laws.
                            </p>
                        </section>

                        <section class="mb-8">
                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">5. Limitation of Liability</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">
                                In no event shall TechDetector, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages resulting from your access to or use of the Service.
                            </p>
                        </section>

                        <section class="mb-8">
                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">6. Termination</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">
                                We may terminate or suspend your access to the Service immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach these Terms.
                            </p>
                        </section>

                        <section class="mb-8">
                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">7. Changes to Terms</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">
                                We reserve the right to modify or replace these Terms at any time. We will provide notice of any changes by posting the new Terms on this page and updating the "Last updated" date.
                            </p>
                        </section>

                        <section>
                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-white mb-4">8. Contact Us</h2>
                            <p class="text-gray-600 dark:text-gray-300">
                                If you have any questions about these Terms, please contact us at:
                            </p>
                            <p class="text-gray-600 dark:text-gray-300 mt-2">
                                Email: otienobrian029@gmail.com<br>
                                Address: Nairobi Kenya
                            </p>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <footer class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 border-t border-opacity-10 dark:border-opacity-20 border-gray-300 dark:border-gray-700 shadow-inner">
    <div class="container mx-auto px-4 py-12 md:py-16">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Logo and description -->
            <div class="col-span-1">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="bg-blue-600 dark:bg-blue-700 p-2 rounded-lg shadow-md">
                        <i class="fas fa-search text-white text-xl"></i>
                    </div>
                    <span class="text-2xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent dark:from-blue-400 dark:to-indigo-400">TechDetector</span>
                </div>
                <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed mb-6">
                    Discover the technologies behind any website with our powerful scanning tool. Get detailed insights into web technologies, performance, and security.
                </p>
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 transition-all hover:scale-110">
                        <i class="fab fa-twitter text-xl"></i>
                    </a>
                    <a href="#" class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-all hover:scale-110">
                        <i class="fab fa-github text-xl"></i>
                    </a>
                    <a href="#" class="text-gray-500 hover:text-blue-700 dark:text-gray-400 dark:hover:text-blue-400 transition-all hover:scale-110">
                        <i class="fab fa-linkedin text-xl"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="relative group">
                <div class="absolute -left-3 top-0 h-full w-1 bg-blue-600 dark:bg-blue-500 rounded-full transform scale-y-0 group-hover:scale-y-100 transition-transform origin-top duration-300"></div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5 relative pl-2">Quick Links</h3>
                <ul class="space-y-2">
                    <li><a href="../" class="text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition-colors duration-200">Home</a></li>
                    <li><a href="#features" class="text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition-colors duration-200">Features</a></li>
                    <li><a href="#how-it-works" class="text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition-colors duration-200">How It Works</a></li>
                    <li><a href="#about" class="text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition-colors duration-200">About Us</a></li>
                </ul>
            </div>

            <!-- Legal -->
            <div class="relative group">
                <div class="absolute -left-3 top-0 h-full w-1 bg-blue-600 dark:bg-blue-500 rounded-full transform scale-y-0 group-hover:scale-y-100 transition-transform origin-top duration-300"></div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5 relative pl-2">Legal</h3>
                <ul class="space-y-2">
                    <li><a href="../privecy" class="text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition-colors duration-200">Privacy Policy</a></li>
                    <li><a href="" class="text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition-colors duration-200">Terms of Service</a></li>
                    <li><a href="#" class="text-gray-600 hover:text-blue-600 dark:text-gray-300 dark:hover:text-blue-400 transition-colors duration-200">Cookie Policy</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="relative group">
                <div class="absolute -left-3 top-0 h-full w-1 bg-blue-600 dark:bg-blue-500 rounded-full transform scale-y-0 group-hover:scale-y-100 transition-transform origin-top duration-300"></div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-5 relative pl-2">Contact Us</h3>
                <ul class="space-y-2">
                    <li class="flex items-start group">
                        <div class="bg-blue-100 dark:bg-blue-900/30 p-2 rounded-lg mr-3 group-hover:bg-blue-200 dark:group-hover:bg-blue-800/50 transition-colors">
                            <i class="fas fa-envelope text-blue-600 dark:text-blue-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Email us at</p>
                            <a href="mailto:otienobrian029@gmail.com" class="text-gray-700 dark:text-gray-200 font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors">otienobrian029@gmail.com</a>
                        </div>
                    </li>
                    <li class="flex items-start mt-4 group">
                        <div class="bg-blue-100 dark:bg-blue-900/30 p-2 rounded-lg mr-3 group-hover:bg-blue-200 dark:group-hover:bg-blue-800/50 transition-colors">
                            <i class="fas fa-map-marker-alt text-blue-600 dark:text-blue-400 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Based in</p>
                            <span class="text-gray-700 dark:text-gray-200 font-medium">Nairobi, Kenya</span>
                        </div>
                    </li>
                            </div>
        </div>

        <div class="border-t border-opacity-20 border-gray-400 dark:border-gray-600 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center">
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-4 md:mb-0">
                &copy; <?= date('Y') ?> <span class="font-medium text-gray-700 dark:text-gray-200">TechDetector</span>. All rights reserved.
            </p>
            <div class="flex flex-wrap justify-center gap-4 md:gap-6">
                <a href="privecy" class="text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 text-sm font-medium transition-all hover:translate-x-0.5">
                    Privacy Policy
                </a>
                <span class="text-gray-300 dark:text-gray-600">•</span>
                <a href="terms" class="text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 text-sm font-medium transition-all hover:translate-x-0.5">
                    Terms of Service
                </a>
                <span class="text-gray-300 dark:text-gray-600">•</span>
                <a href="#" class="text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 text-sm font-medium transition-all hover:translate-x-0.5">
                    Sitemap
                </a>
            </div>
        </div>
    </div>
</footer>
    <script>
        // Theme toggle functionality
        const themeToggleBtn = document.getElementById('theme-toggle');
        const mobileThemeToggleBtn = document.getElementById('mobile-theme-toggle');
        const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
        const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
        const mobileThemeIcon = document.getElementById('mobile-theme-icon');
        
        // Check for saved user preference, if any, on load
        if (localStorage.getItem('color-theme') === 'dark' || (!localStorage.getItem('color-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            themeToggleLightIcon.classList.remove('hidden');
            mobileThemeIcon.setAttribute('d', 'M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z');
        } else {
            document.documentElement.classList.remove('dark');
            themeToggleDarkIcon.classList.remove('hidden');
            mobileThemeIcon.setAttribute('d', 'M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z');
        }

        // Toggle theme function
        function toggleTheme() {
            // Toggle icons
            themeToggleDarkIcon.classList.toggle('hidden');
            themeToggleLightIcon.classList.toggle('hidden');
            
            // Toggle dark class
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
                mobileThemeIcon.setAttribute('d', 'M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
                mobileThemeIcon.setAttribute('d', 'M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z');
            }
        }

        // Add event listeners
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', toggleTheme);
        }
        if (mobileThemeToggleBtn) {
            mobileThemeToggleBtn.addEventListener('click', toggleTheme);
        }

        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                mobileMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
