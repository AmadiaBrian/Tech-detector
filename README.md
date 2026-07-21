# TechDetector - Web Technology Scanner

A powerful web technology detection tool that analyzes websites to discover the technologies, frameworks, CMS, and services that power them. Get comprehensive insights into any website's technology stack in seconds.

![TechDetector](https://img.shields.io/badge/version-1.0.0-orange)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)

## 🚀 Features

### Core Functionality
- **Website Technology Detection**: Scan any website to detect:
  - Content Management Systems (WordPress, Drupal, Joomla, etc.)
  - Web Frameworks (React, Vue.js, Angular, etc.)
  - E-commerce Platforms (Shopify, WooCommerce, Magento, etc.)
  - Analytics Tools (Google Analytics, Hotjar, etc.)
  - JavaScript Libraries (jQuery, Bootstrap, etc.)
  - Server Technologies (Apache, Nginx, etc.)
  - SSL/TLS Information
  - DNS Records
  - WHOIS Data

### User Features
- **User Authentication**: Secure login and registration system
- **Dashboard**: Comprehensive dashboard with scan history and statistics
- **Domain Analysis**: Detailed domain information and analytics
- **Link Analyzer**: Validate and analyze URLs for accessibility
- **Profile Management**: Update user profile and preferences
- **Settings**: Configure application settings

### UI/UX Features
- **Responsive Design**: Mobile-friendly interface with Google Search Console-style sidebar
- **Dark Theme**: Modern dark theme with orange accent colors
- **Smooth Animations**: Professional transitions and hover effects
- **Mobile Sidebar**: Collapsible sidebar with overlay on mobile devices
- **Error Pages**: Custom 404 and 403 error pages

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.2 or higher
- Apache or Nginx web server
- mod_rewrite enabled (for URL rewriting)
- cURL extension enabled
- JSON extension enabled
- PDO extension enabled

## 🛠️ Installation

### 1. Clone the Repository

```bash
git clone https://github.com/AmadiaBrian/Tech-detector.git
cd Tech-detector
```

### 2. Configure Database

Create a new MySQL database:

```sql
CREATE DATABASE techdetector CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import the database schema:

```bash
mysql -u username -p techdetector < migrations/database.sql
```

### 3. Configure Application

Edit `config/database.php` with your database credentials:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'techdetector');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 4. Set File Permissions

Ensure the following directories are writable:

```bash
chmod 755 uploads/
chmod 755 logs/
chmod 644 .htaccess
```

### 5. Configure Web Server

#### Apache
Ensure `mod_rewrite` is enabled and `.htaccess` is allowed.

#### Nginx
Add the following to your Nginx configuration:

```nginx
location / {
    try_files $uri $uri/ $uri.php?$query_string;
}
```

### 6. Access the Application

Open your browser and navigate to:
```
http://localhost/SCAN/
```

## 📁 Project Structure

```
SCAN/
├── api/                    # API endpoints
│   ├── lib.php            # Library functions
│   ├── scan.php           # Scanning API
│   └── logout.php         # Logout handler
├── assets/                # Static assets
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   ├── images/            # Images and icons
│   └── logo.png           # Application logo
├── config/                # Configuration files
│   └── database.php       # Database configuration
├── dashbord/              # Dashboard pages
│   ├── index.php          # Main dashboard
│   ├── domain-check.php   # Domain analysis
│   ├── link-analyzer.php  # Link validation
│   ├── profile.php        # User profile
│   ├── settings.php       # Settings page
│   └── view_source.php    # Source code viewer
├── includes/              # Include files
│   ├── auth.php           # Authentication helpers
│   └── functions.php     # Utility functions
├── login/                 # Login system
│   ├── index.php          # Login page
│   └── process.php        # Login handler
├── register/              # Registration system
│   ├── index.php          # Registration page
│   └── process.php        # Registration handler
├── migrations/            # Database migrations
│   └── database.sql       # Database schema
├── PHPMailer/             # Email library
├── uploads/               # User uploads directory
├── logs/                  # Application logs
├── .htaccess              # URL rewriting rules
├── 403.php                # 403 Forbidden page
├── 404.php                # 404 Not Found page
├── index.php              # Homepage
├── scan.php               # Main scanning script
└── README.md              # This file
```

## 🔧 Configuration

### Database Configuration

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'techdetector');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Email Configuration (Optional)

Configure PHPMailer in the registration system for email verification.

### Security Settings

- Ensure `display_errors` is set to `0` in production
- Use strong database passwords
- Enable HTTPS in production
- Configure proper file permissions

## 🎯 Usage

### Scanning a Website

1. Navigate to the homepage
2. Enter a URL in the search field (e.g., `https://example.com`)
3. Click "Scan" or press Enter
4. View the comprehensive technology report

### Using the Dashboard

1. Log in to your account
2. Access the dashboard from the sidebar
3. View your scan history and statistics
4. Use the various tools available

### Domain Analysis

1. Navigate to Dashboard → Domain Check
2. Enter a domain name
3. View detailed domain information including:
   - WHOIS data
   - DNS records
   - SSL information
   - Server details

### Link Analyzer

1. Navigate to Dashboard → Link Analyzer
2. Enter a URL
3. Validate accessibility and get detailed information

## 🔐 Security Features

- Password hashing using bcrypt
- SQL injection prevention using PDO prepared statements
- XSS protection with output escaping
- CSRF protection on forms
- Session management
- Private IP blocking
- Input validation and sanitization

## 🌐 API Endpoints

### Scan API
- **POST** `/api/scan.php` - Scan a website for technologies

### Authentication
- **POST** `/login/process.php` - User login
- **POST** `/register/process.php` - User registration
- **GET** `/api/logout.php` - User logout

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🐛 Known Issues

- Some websites may block automated scanning
- Rate limiting may be required for frequent scans
- Private IP addresses are blocked for security reasons

## 🔄 Roadmap

- [ ] Add more technology detection patterns
- [ ] Implement API rate limiting
- [ ] Add email verification for registration
- [ ] Create admin panel
- [ ] Add export functionality (PDF, CSV)
- [ ] Implement scan scheduling
- [ ] Add multi-language support
- [ ] Create mobile app

## 📞 Support

For support, please open an issue on GitHub or contact the development team.

## 🙏 Acknowledgments

- Font Awesome for icons
- Tailwind CSS for styling
- PHPMailer for email functionality
- The open-source community

## 📊 Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript
- **Styling**: Tailwind CSS, Custom CSS
- **Icons**: Font Awesome 6.0
- **Email**: PHPMailer

## 🔍 Detection Rules

The application uses a comprehensive set of detection rules defined in `rules.json` to identify various technologies. These rules include:

- CMS detection patterns
- Framework detection patterns
- E-commerce platform detection
- Analytics tool detection
- JavaScript library detection
- Server technology detection

## 📱 Mobile Responsiveness

The application is fully responsive and works seamlessly on:
- Desktop computers (992px+)
- Tablets (768px - 991px)
- Mobile devices (≤767px)

## 🎨 Customization

### Changing the Theme

Edit the CSS variables in `assets/css/ntsa-style.css`:

```css
:root {
    --primary: #ff6b00;
    --secondary: #FFD700;
    --background: #000000;
    --text: #ffffff;
}
```

### Adding New Detection Rules

Add new patterns to `rules.json` following the existing format.

## 🚀 Deployment

### Production Deployment Checklist

- [ ] Set `display_errors` to `0`
- [ ] Enable HTTPS
- [ ] Configure strong database passwords
- [ ] Set proper file permissions
- [ ] Configure error logging
- [ ] Enable caching
- [ ] Set up backups
- [ ] Configure firewall rules
- [ ] Monitor server resources

## 📄 License

MIT License - feel free to use this project for personal or commercial purposes.

---

**Built with ❤️ by the TechDetector Team**
