<?php
// index.php - main entry point
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
require_once __DIR__ . '/api/lib.php';
require_once __DIR__ . '/config/database.php';

$errors = [];
$url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = $_POST['url'] ?? '';
    if (empty($url)) {
        $errors[] = 'Please enter a URL';
    } elseif (!isValidUrl($url)) {
        $errors[] = 'Please enter a valid URL (e.g., https://example.com)';
    } else {
        // Block private/reserved IPs
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || isPrivateHost($host)) {
            $errors[] = 'Cannot scan private or reserved IP addresses';
        } else {
            // If we get here, redirect to scan.php
            header('Location: scan?url=' . urlencode($url));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechDetector</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets\logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <meta name="author" content="TechDetector">
    <meta name="keywords" content="TechDetector, Web Technology Detector, Website Technology Scanner">
    <meta name="description" content="Discover what technologies power any website. Get detailed insights into CMS, frameworks, e-commerce platforms, and more.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/ntsa-style.css">
    <style>
        @media (min-width: 992px) {
            .nav-links .btn-primary {
                background-color: var(--primary) !important;
                border: none !important;
                color: white !important;
            }
            .nav-links .btn-primary:hover {
                background-color: var(--primary-dark) !important;
            }
            
            .nav-links .nav-link {
                color: var(--gray) !important;
            }
            .nav-links .nav-link:hover,
            .nav-links .nav-link.active {
                color: var(--primary) !important;
                background-color: var(--surface-hover) !important;
            }
        }
    </style>
    
<body>

    <!-- Google Search Console-style Sidebar -->
    <aside class="gsc-sidebar" id="gscSidebar">
        <div class="gsc-sidebar-header">
            <a href="" class="gsc-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </div>
        
        <nav class="gsc-sidebar-nav">
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Overview</div>
                <a href="" class="gsc-nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="#features" class="gsc-nav-item">
                    <i class="fas fa-star"></i>
                    <span>Features</span>
                </a>
                <a href="#how-it-works" class="gsc-nav-item">
                    <i class="fas fa-cog"></i>
                    <span>How It Works</span>
                </a>
            </div>
            
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Account</div>
                <?php if (isLoggedIn()): ?>
                    <a href="dashbord" class="gsc-nav-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="api/logout" class="gsc-nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                <?php else: ?>
                    <a href="login" class="gsc-nav-item">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Sign in</span>
                    </a>
                <?php endif; ?>
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
            <a href="" class="gsc-topbar-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </header>

        <!-- Page Content -->
        <div class="gsc-content-wrapper">
    
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Web Technology Detector</h1>
            <p>Analyze any website to discover the technologies, frameworks, and services that power it. Get comprehensive insights in seconds.</p>
            
            <div class="search-container">
                <form id="scan-form" method="post" class="mb-0">
                    <div class="form-group">
                        <div style="display: flex; gap: 10px;">
                            <input 
                                type="text" 
                                id="url" 
                                name="url" 
                                class="form-control" 
                                placeholder="Enter website URL (e.g., https://example.com)" 
                                value="<?= htmlspecialchars($url) ?>" 
                                required
                                autofocus
                                style="flex: 1;"
                            >
                            <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
                                <i class="fas fa-search"></i> Analyze
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-top: 15px;">
                            <?php foreach ($errors as $error): ?>
                                <p style="margin: 0;"><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>


    <!-- Features Section -->
    <section id="features" class="section">
        <div class="container">
            <h2 class="section-title">Features</h2>
            <p style="max-width: 600px; color: #9aa0a6; margin-bottom: 24px;">
                Comprehensive website analysis with detailed technology detection and performance insights.
            </p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 style="color: #ffffff;">Technology Detection</h3>
                    <p style="color: #9aa0a6;">Identify CMS, frameworks, libraries, and server technologies used by any website.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 style="color: #ffffff;">Performance Analysis</h3>
                    <p style="color: #9aa0a6;">Analyze page load times, resource sizes, and optimization opportunities.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 style="color: #ffffff;">Security Checks</h3>
                    <p style="color: #9aa0a6;">Detect SSL certificates, security headers, and potential vulnerabilities.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <h3 style="color: #ffffff;">SEO Analysis</h3>
                    <p style="color: #9aa0a6;">Evaluate meta tags, headings, structured data, and SEO best practices.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 style="color: #ffffff;">Mobile Friendly</h3>
                    <p style="color: #9aa0a6;">Check responsive design, viewport configuration, and mobile optimization.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-code"></i>
                    </div>
                    <h3 style="color: #ffffff;">Code Analysis</h3>
                    <p style="color: #9aa0a6;">Examine HTML structure, CSS frameworks, and JavaScript libraries.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3 style="color: #ffffff;">Domain Intelligence</h3>
                    <p style="color: #9aa0a6;">Analyze DNS records, SSL certificates, and hosting infrastructure.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3 style="color: #ffffff;">Automation Ready</h3>
                    <p style="color: #9aa0a6;">Bulk scanning capabilities and scheduled monitoring for enterprise needs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="section">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <p style="max-width: 600px; color: #9aa0a6; margin-bottom: 24px;">
                Get detailed website insights in three simple steps.
            </p>
            
            <div class="features-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                <div class="feature-card" style="text-align: left; display: flex; align-items: flex-start; gap: 16px; padding: 16px;">
                    <div class="feature-icon" style="background: #ff6b00; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; font-weight: 500;">1</div>
                    <div>
                        <h3 style="margin-top: 0; margin-bottom: 8px; color: #ffffff; font-size: 16px; font-weight: 500;">Enter URL</h3>
                        <p style="color: #9aa0a6; margin: 0; font-size: 13px;">Paste the website URL you want to analyze.</p>
                    </div>
                </div>
                
                <div class="feature-card" style="text-align: left; display: flex; align-items: flex-start; gap: 16px; padding: 16px;">
                    <div class="feature-icon" style="background: #ff6b00; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; font-weight: 500;">2</div>
                    <div>
                        <h3 style="margin-top: 0; margin-bottom: 8px; color: #ffffff; font-size: 16px; font-weight: 500;">Click Analyze</h3>
                        <p style="color: #9aa0a6; margin: 0; font-size: 13px;">Our system scans the website for technologies and performance metrics.</p>
                    </div>
                </div>
                
                <div class="feature-card" style="text-align: left; display: flex; align-items: flex-start; gap: 16px; padding: 16px;">
                    <div class="feature-icon" style="background: #ff6b00; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; font-weight: 500;">3</div>
                    <div>
                        <h3 style="margin-top: 0; margin-bottom: 8px; color: #ffffff; font-size: 16px; font-weight: 500;">View Results</h3>
                        <p style="color: #9aa0a6; margin: 0; font-size: 13px;">Get comprehensive insights about the website's technology stack.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section">
        <div class="container">
            <h2 class="section-title">About TechDetector</h2>
            
            <div style="max-width: 800px; margin-bottom: 40px;">
                <p style="color: #9aa0a6; font-size: 14px; line-height: 1.6; margin-bottom: 16px;">
                    TechDetector is a professional web technology analysis platform designed for developers, security researchers, and digital professionals. Our advanced scanning engine provides comprehensive insights into the technology stack, performance metrics, and security posture of any website.
                </p>
                <p style="color: #9aa0a6; font-size: 14px; line-height: 1.6;">
                    Built with precision and accuracy in mind, TechDetector leverages sophisticated pattern matching and heuristic analysis to identify thousands of web technologies, frameworks, and services. Whether you're conducting competitive intelligence, performing security assessments, or optimizing your own web properties, our platform delivers actionable data to support your decisions.
                </p>
            </div>

            <div class="features-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 32px;">
                <div class="feature-card" style="padding: 24px;">
                    <div style="color: #ff6b00; font-size: 28px; margin-bottom: 16px;">
                        <i class="fas fa-crosshairs"></i>
                    </div>
                    <h3 style="margin: 0 0 12px 0; color: #ffffff; font-size: 16px; font-weight: 500;">Precision Detection</h3>
                    <p style="margin: 0; color: #9aa0a6; font-size: 13px; line-height: 1.5;">Advanced algorithms identify technologies with high accuracy, reducing false positives and providing reliable data.</p>
                </div>
                
                <div class="feature-card" style="padding: 24px;">
                    <div style="color: #ff6b00; font-size: 28px; margin-bottom: 16px;">
                        <i class="fas fa-database"></i>
                    </div>
                    <h3 style="margin: 0 0 12px 0; color: #ffffff; font-size: 16px; font-weight: 500;">Comprehensive Database</h3>
                    <p style="margin: 0; color: #9aa0a6; font-size: 13px; line-height: 1.5;">Continuously updated database covering thousands of technologies, frameworks, CMS platforms, and web services.</p>
                </div>
                
                <div class="feature-card" style="padding: 24px;">
                    <div style="color: #ff6b00; font-size: 28px; margin-bottom: 16px;">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h3 style="margin: 0 0 12px 0; color: #ffffff; font-size: 16px; font-weight: 500;">Actionable Insights</h3>
                    <p style="margin: 0; color: #9aa0a6; font-size: 13px; line-height: 1.5;">Detailed reports with performance metrics, security analysis, and optimization recommendations.</p>
                </div>
                
                <div class="feature-card" style="padding: 24px;">
                    <div style="color: #ff6b00; font-size: 28px; margin-bottom: 16px;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 style="margin: 0 0 12px 0; color: #ffffff; font-size: 16px; font-weight: 500;">Enterprise Security</h3>
                    <p style="margin: 0; color: #9aa0a6; font-size: 13px; line-height: 1.5;">Built with security best practices, ensuring your data remains protected and your scans remain confidential.</p>
                </div>
            </div>

            <div style="margin-top: 48px; padding: 32px; background: #1e1e1e; border-radius: 8px; border: 1px solid #3c4043;">
                <h3 style="margin-top: 0; color: #ffffff; font-size: 18px; font-weight: 500; margin-bottom: 16px;">Use Cases</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px;">
                    <div>
                        <h4 style="margin: 0 0 8px 0; color: #ffffff; font-size: 14px; font-weight: 500;">Competitive Analysis</h4>
                        <p style="margin: 0; color: #9aa0a6; font-size: 13px; line-height: 1.5;">Understand your competitors' technology stacks and identify opportunities for differentiation.</p>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 8px 0; color: #ffffff; font-size: 14px; font-weight: 500;">Security Audits</h4>
                        <p style="margin: 0; color: #9aa0a6; font-size: 13px; line-height: 1.5;">Identify outdated software versions, security misconfigurations, and potential vulnerabilities.</p>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 8px 0; color: #ffffff; font-size: 14px; font-weight: 500;">Technology Research</h4>
                        <p style="margin: 0; color: #9aa0a6; font-size: 13px; line-height: 1.5;">Explore technology adoption trends and discover new tools and frameworks in your industry.</p>
                    </div>
                    <div>
                        <h4 style="margin: 0 0 8px 0; color: #ffffff; font-size: 14px; font-weight: 500;">Due Diligence</h4>
                        <p style="margin: 0; color: #9aa0a6; font-size: 13px; line-height: 1.5;">Assess the technical infrastructure of potential acquisitions or business partners.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <h3>About TechDetector</h3>
                    <p style="color: rgba(255,255,255,0.8); margin-bottom: 20px;">
                        TechDetector helps you discover the technologies used on any website. 
                        Our powerful scanning tool provides detailed insights into web technologies, 
                        frameworks, and platforms.
                    </p>
                    <div class="social-links" style="display: flex; gap: 15px; margin-top: 20px;">
                        <a href="#" aria-label="Facebook" style="color: #ff6b00;"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter" style="color: #ff6b00;"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="LinkedIn" style="color: #ff6b00;"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" aria-label="GitHub" style="color: #ff6b00;"><i class="fab fa-github"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#">Blog</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h3>Resources</h3>
                    <ul>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">API Documentation</a></li>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Tutorials</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h3>Contact Us</h3>
                    <ul>
                        <li><i class="fas fa-envelope"></i> otienobrian029@gmail.com</li>
                        <li><i class="fas fa-phone"></i> +254 745 959757</li>
                        <li><i class="fas fa-map-marker-alt"></i> Nairobi, Kenya</li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; <?= date('Y') ?> TechDetector. All rights reserved. | 
                <a href="privecy" style="color: rgba(255,255,255,0.8);">Privacy Policy</a> | 
                <a href="terms" style="color: rgba(255,255,255,0.8);">Terms of Service</a></p>
            </div>
        </div>
    </footer>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle functionality
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('gscSidebar');
        
        console.log('Sidebar toggle:', sidebarToggle);
        console.log('Sidebar:', sidebar);
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Check if we're on mobile or desktop
                if (window.innerWidth <= 992) {
                    // Mobile: toggle mobile-open class
                    sidebar.classList.toggle('gsc-sidebar-mobile-open');
                    
                    if (sidebar.classList.contains('gsc-sidebar-mobile-open')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                } else {
                    // Desktop: toggle collapsed class
                    sidebar.classList.toggle('gsc-sidebar-collapsed');
                }
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('gscSidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (sidebar && sidebarToggle) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && sidebar.classList.contains('gsc-sidebar-mobile-open')) {
                    sidebar.classList.remove('gsc-sidebar-mobile-open');
                    document.body.style.overflow = '';
                }
            }
        });

        const form = document.getElementById('scan-form');
        const button = form ? form.querySelector('button[type="submit"]') : null;
        
        // Reset button state immediately on page load
        if (button) {
            button.disabled = false;
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = 'fas fa-search';
            }
            button.innerHTML = '<i class="fas fa-search"></i> Analyze';
        }
        
        if (form && button) {
            form.addEventListener('submit', function() {
                // Disable button during submission
                button.disabled = true;
                const icon = button.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-spinner fa-spin';
                }
                button.innerHTML = button.innerHTML.replace('Analyze', 'Analyzing...');
            });
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });
        }
    });
    </script>
    <script src="assets/js/darkmode.js"></script>
</body>
</html>
