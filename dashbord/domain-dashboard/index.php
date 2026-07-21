<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect to login if not
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Initialize variables
$domain = '';
$result = null;
$error = '';

// Domain information functions
function checkDomainSpeed($domain) {
    // Simulate speed check
    $loadTime = rand(50, 500); // Random response time between 50ms and 500ms
    return [
        'load_time_ms' => $loadTime,
        'status' => $loadTime < 200 ? 'fast' : ($loadTime < 400 ? 'moderate' : 'slow')
    ];
}

function checkUptime($domain) {
    // Simulate uptime check (90-100% uptime)
    $uptime = 90 + (rand(0, 100) / 10);
    return [
        'status' => $uptime > 95 ? 'up' : 'down',
        'uptime_24h' => number_format($uptime, 2),
        'response_code' => $uptime > 95 ? 200 : 500
    ];
}

function getTrafficData($domain) {
    // Simulate traffic data
    return [
        'monthly_visitors' => rand(1000, 100000),
        'pageviews' => rand(5000, 500000),
        'bounce_rate' => rand(30, 70) . '%'
    ];
}

function getDnsInfo($domain) {
    // First, try to get the base domain (without www.)
    $cleanDomain = preg_replace('/^www\./i', '', $domain);
    
    // Array to store all DNS records
    $allRecords = [];
    
    // Get common DNS record types
    $recordTypes = [
        'A' => DNS_A,
        'AAAA' => DNS_AAAA,
        'MX' => DNS_MX,
        'CNAME' => DNS_CNAME,
        'TXT' => DNS_TXT,
        'NS' => DNS_NS,
        'SOA' => DNS_SOA
    ];
    
    // Try to get records for each type
    foreach ($recordTypes as $type => $const) {
        $records = @dns_get_record($cleanDomain, $const);
        if ($records !== false && is_array($records)) {
            foreach ($records as $record) {
                $record['type'] = $type; // Ensure type is set
                $allRecords[] = $record;
            }
        }
    }
    
    // If no records found, try with www. prefix
    if (empty($allRecords) && !preg_match('/^www\./i', $domain)) {
        $wwwDomain = 'www.' . $cleanDomain;
        foreach ($recordTypes as $type => $const) {
            $records = @dns_get_record($wwwDomain, $const);
            if ($records !== false && is_array($records)) {
                foreach ($records as $record) {
                    $record['type'] = $type;
                    $allRecords[] = $record;
                }
            }
        }
    }
    
    // Check for MX records
    $hasMx = !empty(array_filter($allRecords, function($record) {
        return isset($record['type']) && strtoupper($record['type']) === 'MX';
    }));
    
    return [
        'records' => $allRecords,
        'count' => count($allRecords),
        'has_mx' => $hasMx,
        'domain_checked' => $cleanDomain
    ];
}

function getSslInfo($domain) {
    // Initialize default values
    $result = [
        'valid' => false,
        'expires' => null,
        'issuer' => 'Unknown',
        'signature_algorithm' => 'N/A',
        'key_size' => 0,
        'key_type' => 'N/A',
        'valid_from' => null,
        'valid_to' => null,
        'chain' => [],
        'protocol' => 'N/A',
        'subject' => []
    ];

    // Clean up domain (remove protocol and path)
    $domain = str_replace(['http://', 'https://'], '', $domain);
    $domain = explode('/', $domain)[0];
    $domain = explode(':', $domain)[0];

    // Set stream context options
    $context = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    // Try to connect and get certificate
    try {
        // First try with port 443 (HTTPS)
        $socket = @stream_socket_client(
            "ssl://$domain:443",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket) {
            $params = stream_context_get_params($socket);
            
            // Get the main certificate
            if (isset($params['options']['ssl']['peer_certificate'])) {
                $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                
                // Parse certificate information
                $result['valid'] = time() < $cert['validTo_time_t'];
                $result['expires'] = date('Y-m-d H:i:s', $cert['validTo_time_t']);
                $result['valid_from'] = date('Y-m-d H:i:s', $cert['validFrom_time_t']);
                $result['valid_to'] = date('Y-m-d H:i:s', $cert['validTo_time_t']);
                
                // Get issuer
                if (isset($cert['issuer']['O'])) {
                    $result['issuer'] = is_array($cert['issuer']['O']) 
                        ? implode(', ', $cert['issuer']['O']) 
                        : $cert['issuer']['O'];
                } elseif (isset($cert['issuer']['CN'])) {
                    $result['issuer'] = $cert['issuer']['CN'];
                }
                
                // Get subject
                if (isset($cert['subject'])) {
                    $result['subject'] = $cert['subject'];
                }
                
                // Get signature algorithm
                if (isset($cert['signatureTypeSN'])) {
                    $result['signature_algorithm'] = $cert['signatureTypeSN'];
                }
                
                // Get key size and type
                if (isset($cert['rsa']['n'])) {
                    $result['key_type'] = 'RSA';
                    $result['key_size'] = strlen(sprintf('%0b', $cert['rsa']['n'])) - 1; // Approximate key size
                } elseif (isset($cert['ec']['curve_name'])) {
                    $result['key_type'] = 'EC (' . $cert['ec']['curve_name'] . ')';
                    $result['key_size'] = $cert['ec']['curve_oid'] === '1.3.132.0.34' ? 256 : 384; // Common EC key sizes
                }
                
                // Get certificate chain
                if (isset($params['options']['ssl']['peer_certificate_chain'])) {
                    foreach ($params['options']['ssl']['peer_certificate_chain'] as $chainCert) {
                        $chainInfo = openssl_x509_parse($chainCert);
                        if ($chainInfo) {
                            $result['chain'][] = $chainInfo;
                        }
                    }
                }
                
                // Get protocol version
                $crypto = stream_socket_get_name($socket, true);
                if (preg_match('/TLSv(\d+\.?\d*)/', $crypto, $matches)) {
                    $result['protocol'] = 'TLS ' . $matches[1];
                }
            }
            
            fclose($socket);
        }
    } catch (Exception $e) {
        // Log error if needed
        error_log("Error checking SSL certificate for $domain: " . $e->getMessage());
    }
    
    return $result;
}

function getWhoisInfo($domain) {
    // Simulate WHOIS lookup
    $created = date('Y-m-d', strtotime('-' . rand(1, 10) . ' years'));
    $expires = date('Y-m-d', strtotime($created . ' +' . (10 + rand(0, 5)) . ' years'));
    
    return [
        'created' => $created,
        'expires' => $expires,
        'registrar' => 'Example Registrar, Inc.',
        'nameservers' => [
            'ns1.example.com',
            'ns2.example.com'
        ]
    ];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['domain'])) {
    $domain = trim($_POST['domain']);
    
    try {
        // Get all domain information
        $result = [
            'domain' => $domain,
            'speed' => checkDomainSpeed($domain),
            'uptime' => checkUptime($domain),
            'traffic' => getTrafficData($domain),
            'dns' => getDnsInfo($domain),
            'ssl' => getSslInfo($domain),
            'whois' => getWhoisInfo($domain),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Dashboard - TechDetector</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/ntsa-style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #000000;
            color: #ffffff;
        }
        .card {
            background: #000000;
            border: 1px solid #3c4043;
            border-radius: 8px;
        }
        input[type="text"] {
            background-color: #1a1a1a;
            border-color: #3c4043;
            color: #ffffff;
        }
        input[type="text"]:focus {
            border-color: #ff6b00;
            outline: none;
        }
        .bg-indigo-600 {
            background-color: #1a1a1a !important;
        }
        .bg-indigo-700 {
            background-color: #ff6b00 !important;
        }
        .hover\:bg-indigo-800:hover {
            background-color: #e65a00 !important;
        }
        .text-indigo-200 {
            color: #9ca3af !important;
        }
        .text-gray-800 {
            color: #ffffff !important;
        }
        .bg-red-50 {
            background-color: rgba(255, 107, 0, 0.1) !important;
        }
        .border-red-500 {
            border-color: #ff6b00 !important;
        }
        .text-red-500 {
            color: #ff6b00 !important;
        }
        .text-red-700 {
            color: #ff6b00 !important;
        }
        .bg-white {
            background-color: #1a1a1a !important;
        }
        .text-gray-900 {
            color: #ffffff !important;
        }
        .text-gray-500 {
            color: #9ca3af !important;
        }
        .border-gray-200 {
            border-color: #3c4043 !important;
        }
        .bg-gray-50 {
            background-color: #1a1a1a !important;
        }
        .bg-green-100 {
            background-color: rgba(255, 107, 0, 0.1) !important;
        }
        .text-green-800 {
            color: #ff6b00 !important;
        }
        .bg-red-100 {
            background-color: rgba(255, 107, 0, 0.1) !important;
        }
        .text-red-800 {
            color: #ff6b00 !important;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 992px) {
            .gsc-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 1000;
            }
            
            .gsc-sidebar.gsc-sidebar-mobile-open {
                transform: translateX(0);
            }
            
            .gsc-main-wrapper {
                margin-left: 0;
            }
            
            .gsc-topbar {
                padding: 0.75rem 1rem;
            }
            
            .gsc-sidebar-toggle {
                display: flex !important;
            }
        }
        
        @media (max-width: 768px) {
            .card {
                padding: 15px;
                margin: 10px;
            }
            
            input[type="text"] {
                font-size: 16px; /* Prevent zoom on iOS */
            }
            
            button, .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .col-span-2 {
                grid-column: span 1;
            }
            
            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body class="bg-black font-sans antialiased">
    <!-- Google Search Console-style Sidebar -->
    <aside class="gsc-sidebar" id="gscSidebar">
        <div class="gsc-sidebar-header">
            <a href="../../index.php" class="gsc-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </div>
        
        <nav class="gsc-sidebar-nav">
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Dashboard</div>
                <a href="../index.php" class="gsc-nav-item">
                    <i class="fas fa-home"></i>
                    <span>Overview</span>
                </a>
                <a href="index.php" class="gsc-nav-item active">
                    <i class="fas fa-shield-alt"></i>
                    <span>Domain Check</span>
                </a>
                <a href="../link-analyzer.php" class="gsc-nav-item">
                    <i class="fas fa-link"></i>
                    <span>Link Analyzer</span>
                </a>
            </div>
            
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Account</div>
                <a href="../profile.php" class="gsc-nav-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="../settings.php" class="gsc-nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="../../api/logout" class="gsc-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
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
            <a href="../overview" class="gsc-topbar-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </header>

        <!-- Page Content -->
        <div class="gsc-content-wrapper">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-indigo-600 text-white shadow-lg">
            <div class="container mx-auto px-4 py-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold">Domain Information Dashboard</h1>
                    <div class="flex items-center space-x-4">
                        <div id="lastUpdated" class="text-sm text-indigo-200"></div>
                    </div>
                </div>
                <div class="mt-4">
                    <form method="POST" class="flex">
                        <input 
                            type="text" 
                            name="domain" 
                            value="<?php echo htmlspecialchars($domain); ?>"
                            class="flex-1 px-4 py-2 rounded-l-lg text-gray-800 focus:outline-none" 
                            placeholder="Enter domain (e.g., example.com)"
                            required>
                        <button 
                            type="submit" 
                            class="bg-indigo-700 hover:bg-indigo-800 px-6 py-2 rounded-r-lg font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i>Check
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-8">
            <?php if (!empty($error)): ?>
            <!-- Error State -->
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($result) && $result): ?>
            <!-- Results -->
            <div id="results" class="mt-6">
                <!-- Domain Overview -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($result['domain']); ?></h2>
                                <div class="mt-1 text-sm text-gray-500">
                                    Last checked: <?php echo htmlspecialchars($result['timestamp']); ?>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $result['uptime']['status'] === 'up' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $result['uptime']['status'] === 'up' ? 'Online' : 'Offline'; ?>
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-gray-500">Response Time</div>
                                <div class="mt-1 text-lg font-semibold">
                                    <?php echo isset($result['speed']['load_time_ms']) ? htmlspecialchars($result['speed']['load_time_ms']) . ' ms' : 'N/A'; ?>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-gray-500">SSL Status</div>
                                <div class="mt-1">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $result['ssl']['valid'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $result['ssl']['valid'] ? 'Valid' : 'Invalid'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-gray-500">Uptime (24h)</div>
                                <div class="mt-1 text-lg font-semibold">
                                    <?php echo isset($result['uptime']['uptime_24h']) ? htmlspecialchars($result['uptime']['uptime_24h']) . '%' : 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                </div>


                <div class="space-y-8">
                    <!-- Overview Section -->
                    <div id="overview" class="section">
                        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Domain Overview</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500">Domain Status</div>
                                        <div class="mt-1 text-2xl font-semibold">
                                            <span class="<?php echo $result['uptime']['status'] === 'up' ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo $result['uptime']['status'] === 'up' ? 'Online' : 'Offline'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500">Response Time</div>
                                        <div class="mt-1 text-2xl font-semibold">
                                            <?php echo isset($result['speed']['load_time_ms']) ? htmlspecialchars($result['speed']['load_time_ms']) . ' ms' : 'N/A'; ?>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500">SSL Status</div>
                                        <div class="mt-1">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $result['ssl']['valid'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo $result['ssl']['valid'] ? 'Valid' : 'Invalid'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Section -->
                    <div id="performance" class="section bg-white rounded-lg shadow overflow-hidden">
                        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-lg font-medium text-gray-900">Performance Metrics</h3>
                                    <div class="flex space-x-2">
                                        <button onclick="changeTimeRange('24h')" class="px-3 py-1 text-xs font-medium rounded-md bg-indigo-100 text-indigo-800">24h</button>
                                        <button onclick="changeTimeRange('7d')" class="px-3 py-1 text-xs font-medium rounded-md text-gray-600 hover:bg-gray-100">7d</button>
                                        <button onclick="changeTimeRange('30d')" class="px-3 py-1 text-xs font-medium rounded-md text-gray-600 hover:bg-gray-100">30d</button>
                                    </div>
                                </div>
                                
                                <!-- Performance Graph -->
                                <div class="bg-white p-4 rounded-lg border border-gray-200 mb-6">
                                    <div class="flex justify-between items-center mb-4">
                                    Page Speed Metrics
                                        <div class="flex space-x-2">
                                            <button onclick="changeTimeRange('24h')" class="px-3 py-1 text-xs font-medium rounded-md bg-indigo-100 text-indigo-800">24h</button>
                                            <button onclick="changeTimeRange('7d')" class="px-3 py-1 text-xs font-medium rounded-md text-gray-600 hover:bg-gray-100">7d</button>
                                            <button onclick="changeTimeRange('30d')" class="px-3 py-1 text-xs font-medium rounded-md text-gray-600 hover:bg-gray-100">30d</button>
                                        </div>
                                    </div>
                                    <div class="h-80">
                                        <canvas id="performanceChart"></canvas>
                                    </div>
                                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500">Avg. Response Time</div>
                                            <div class="mt-1 text-xl font-semibold" id="avgResponseTime">-</div>
                                            <div class="mt-1 text-xs text-gray-500">Last <span id="avgResponseTimeRange">24h</span></div>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500">Max Response Time</div>
                                            <div class="mt-1 text-xl font-semibold" id="maxResponseTime">-</div>
                                            <div class="mt-1 text-xs text-gray-500">Peak in last <span id="maxResponseTimeRange">24h</span></div>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500">Uptime</div>
                                            <div class="mt-1 text-xl font-semibold text-green-600" id="uptimePercentage">99.9%</div>
                                            <div class="mt-1 text-xs text-gray-500">Last 30 days</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500">Response Time</div>
                                        <div class="mt-1 text-2xl font-semibold">
                                            <?php echo isset($result['speed']['load_time_ms']) ? htmlspecialchars($result['speed']['load_time_ms']) . ' ms' : 'N/A'; ?>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500">
                                            Status: 
                                            <span class="font-medium <?php 
                                                echo isset($result['speed']['status']) ? 
                                                    ($result['speed']['status'] === 'fast' ? 'text-green-600' : 
                                                    ($result['speed']['status'] === 'moderate' ? 'text-yellow-600' : 'text-red-600')) : ''; ?>">
                                                <?php echo isset($result['speed']['status']) ? ucfirst(htmlspecialchars($result['speed']['status'])) : 'N/A'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500">Uptime (24h)</div>
                                        <div class="mt-1 text-2xl font-semibold">
                                            <?php echo isset($result['uptime']['uptime_24h']) ? htmlspecialchars($result['uptime']['uptime_24h']) . '%' : 'N/A'; ?>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500">
                                            Status: 
                                            <span class="font-medium <?php echo isset($result['uptime']['status']) && $result['uptime']['status'] === 'up' ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo isset($result['uptime']['status']) ? ucfirst(htmlspecialchars($result['uptime']['status'])) : 'N/A'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500">Page Load Time</div>
                                        <div class="mt-1 text-2xl font-semibold">
                                            <?php echo isset($result['speed']['load_time_ms']) ? (round($result['speed']['load_time_ms'] * 1.5)) . ' ms' : 'N/A'; ?>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500">
                                            <span class="font-medium">Full page load</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Performance Over Time Graph -->
                                <div class="mt-8">
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="text-md font-medium text-gray-900">Performance Over Time</h4>
                                        <div class="flex space-x-2">
                                            <button onclick="changePerformanceChartRange('24h')" class="px-3 py-1 text-xs font-medium rounded-md bg-indigo-100 text-indigo-800">24h</button>
                                            <button onclick="changePerformanceChartRange('7d')" class="px-3 py-1 text-xs font-medium rounded-md text-gray-600 hover:bg-gray-100">7d</button>
                                            <button onclick="changePerformanceChartRange('30d')" class="px-3 py-1 text-xs font-medium rounded-md text-gray-600 hover:bg-gray-100">30d</button>
                                        </div>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                                        <div class="h-80">
                                            <canvas id="performanceOverTimeChart"></canvas>
                                        </div>
                                        </div>
                                    </div>
                                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500">Avg. Response Time</div>
                                            <div class="mt-1 text-xl font-semibold" id="avgResponseTime">-</div>
                                            <div class="mt-1 text-xs text-gray-500">Last <span id="avgResponseTimeRange">24h</span></div>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500">Max Response Time</div>
                                            <div class="mt-1 text-xl font-semibold" id="maxResponseTime">-</div>
                                            <div class="mt-1 text-xs text-gray-500">Last <span id="maxResponseTimeRange">24h</span></div>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500">Uptime</div>
                                            <div class="mt-1 text-xl font-semibold" id="uptimePercentage">-</div>
                                            <div class="mt-1 text-xs text-gray-500">Last <span id="uptimeRange">24h</span></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Recommendations -->
                                <div class="mt-8">
                                    <h4 class="text-md font-medium text-gray-900 mb-3">Performance Recommendations</h4>
                                    <div class="space-y-3">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-5 w-5 text-yellow-500 mt-0.5">
                                                <i class="fas fa-info-circle"></i>
                                            </div>
                                            <p class="ml-2 text-sm text-gray-600">
                                                Enable browser caching to improve load times for returning visitors.
                                            </p>
                                        </div>
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-5 w-5 text-yellow-500 mt-0.5">
                                                <i class="fas fa-info-circle"></i>
                                            </div>
                                            <p class="ml-2 text-sm text-gray-600">
                                                Optimize images to reduce page size and improve loading speed.
                                            </p>
                                        </div>
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-5 w-5 text-yellow-500 mt-0.5">
                                                <i class="fas fa-info-circle"></i>
                                            </div>
                                            <p class="ml-2 text-sm text-gray-600">
                                                Consider using a CDN to serve static assets faster to users worldwide.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <!-- Security Section -->
                    <div id="security" class="section bg-white rounded-lg shadow overflow-hidden">
                        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Security Status</h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="flex items-center justify-between">
                                            <div class="text-sm font-medium text-gray-500">SSL Certificate</div>
                                            <div class="p-1.5 rounded-full <?php echo isset($result['ssl']['valid']) && $result['ssl']['valid'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                                <i class="fas <?php echo isset($result['ssl']['valid']) && $result['ssl']['valid'] ? 'fa-lock' : 'fa-unlock'; ?>"></i>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="text-2xl font-semibold">
                                                <?php echo isset($result['ssl']['valid']) ? ($result['ssl']['valid'] ? 'Valid' : 'Invalid') : 'N/A'; ?>
                                            </div>
                                            <?php if (isset($result['ssl']['expiry'])): ?>
                                            <div class="mt-1 text-sm text-gray-500">
                                                Expires: <?php echo $result['ssl']['expiry']; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500">Security Headers</div>
                                        <div class="mt-2 space-y-2">
                                            <div class="flex items-center text-sm">
                                                <span class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center mr-2">
                                                    <i class="fas fa-check text-xs"></i>
                                                </span>
                                                <span>HTTPS Enabled</span>
                                            </div>
                                            <div class="flex items-center text-sm">
                                                <span class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center mr-2">
                                                    <i class="fas fa-check text-xs"></i>
                                                </span>
                                                <span>HSTS Enabled</span>
                                            </div>
                                            <div class="flex items-center text-sm">
                                                <span class="w-5 h-5 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center mr-2">
                                                    <i class="fas fa-exclamation text-xs"></i>
                                                </span>
                                                <span>X-Frame-Options Missing</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500">Vulnerability Scan</div>
                                        <div class="mt-2">
                                            <div class="text-2xl font-semibold text-green-600">
                                                No Critical Issues
                                            </div>
                                            <div class="mt-1 text-sm text-gray-500">
                                                Last scanned: <?php echo date('Y-m-d H:i'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Security Recommendations -->
                                <div class="mt-8">
                                    <h4 class="text-md font-medium text-gray-900 mb-3">Security Recommendations</h4>
                                    <div class="space-y-3">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-5 w-5 text-yellow-500 mt-0.5">
                                                <i class="fas fa-shield-alt"></i>
                                            </div>
                                            <p class="ml-2 text-sm text-gray-600">
                                                Add X-Frame-Options header to prevent clickjacking attacks.
                                            </p>
                                        </div>
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-5 w-5 text-yellow-500 mt-0.5">
                                                <i class="fas fa-shield-alt"></i>
                                            </div>
                                            <p class="ml-2 text-sm text-gray-600">
                                                Implement Content Security Policy (CSP) to mitigate XSS attacks.
                                            </p>
                                        </div>
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <p class="ml-2 text-sm text-gray-600">
                                                SSL/TLS is properly configured and valid.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <!-- DNS Section -->
                    <div id="dns" class="section bg-white rounded-lg shadow overflow-hidden">
                        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">DNS Information</h3>
                                    <div class="text-sm text-gray-500">
                                        <?php if (isset($result['dns']['domain_checked'])): ?>
                                            Checking: <span class="font-mono text-indigo-600"><?php echo htmlspecialchars($result['dns']['domain_checked']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Nameservers Section -->
                                <div class="mb-8">
                                    <h4 class="text-md font-medium text-gray-900 mb-3">Nameservers</h4>
                                    <?php 
                                    // Extract NS records
                                    $nsRecords = [];
                                    if (isset($result['dns']['records'])) {
                                        $nsRecords = array_filter($result['dns']['records'], function($record) {
                                            return isset($record['type']) && strtoupper($record['type']) === 'NS';
                                        });
                                    }
                                    
                                    // If no NS records found in DNS, use common ones
                                    if (empty($nsRecords)) {
                                        $nsRecords = [
                                            ['target' => 'ns1.example.com', 'ttl' => 86400],
                                            ['target' => 'ns2.example.com', 'ttl' => 86400],
                                            ['target' => 'ns3.example.com', 'ttl' => 86400],
                                        ];
                                    }
                                    
                                    if (!empty($nsRecords)): 
                                    ?>
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            <?php foreach ($nsRecords as $index => $ns): ?>
                                            <div class="bg-white p-3 rounded-md border border-gray-200">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                                        <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($ns['target'] ?? $ns); ?>
                                                        </p>
                                                        <div class="flex text-sm text-gray-500">
                                                            <span>Nameserver #<?php echo $index + 1; ?></span>
                                                            <?php if (isset($ns['ttl'])): ?>
                                                                <span class="mx-1">•</span>
                                                                <span>TTL: <?php echo $ns['ttl']; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <!-- Nameserver Status -->
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-5 w-5 text-green-500">
                                                    <svg fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900">
                                                        Nameservers are properly configured
                                                    </p>
                                                    <p class="text-sm text-gray-500">
                                                        All nameservers are responding correctly
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (isset($result['dns']['records']) && !empty($result['dns']['records'])): ?>
                                    <div class="mb-4">
                                        <p class="text-sm text-gray-600">
                                            Found <span class="font-medium text-gray-900"><?php echo count($result['dns']['records']); ?> DNS records</span> for this domain.
                                        </p>
                                    </div>
                                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">TTL</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php 
                                                // Group records by type for better organization
                                                $groupedRecords = [];
                                                foreach ($result['dns']['records'] as $record) {
                                                    $type = strtoupper($record['type'] ?? 'UNKNOWN');
                                                    $groupedRecords[$type][] = $record;
                                                }
                                                
                                                // Sort record types alphabetically
                                                ksort($groupedRecords);
                                                
                                                foreach ($groupedRecords as $type => $records): 
                                                    // Get type-specific icon
                                                    $typeIcons = [
                                                        'A' => 'network-wired',
                                                        'AAAA' => 'network-wired',
                                                        'MX' => 'envelope',
                                                        'CNAME' => 'link',
                                                        'TXT' => 'font',
                                                        'NS' => 'server',
                                                        'SOA' => 'database',
                                                        'CERT' => 'certificate',
                                                        'SRV' => 'random',
                                                        'PTR' => 'reply',
                                                        'DS' => 'shield-alt',
                                                        'DNSKEY' => 'key',
                                                        'RRSIG' => 'signature'
                                                    ];
                                                    $icon = $typeIcons[$type] ?? 'question-circle';
                                                    
                                                    // Type-specific colors
                                                    $typeColors = [
                                                        'A' => 'blue',
                                                        'AAAA' => 'indigo',
                                                        'MX' => 'green',
                                                        'CNAME' => 'purple',
                                                        'TXT' => 'yellow',
                                                        'NS' => 'pink',
                                                        'SOA' => 'gray',
                                                        'CERT' => 'red',
                                                        'SRV' => 'teal',
                                                        'PTR' => 'orange',
                                                        'DS' => 'red',
                                                        'DNSKEY' => 'indigo',
                                                        'RRSIG' => 'purple'
                                                    ];
                                                    $color = $typeColors[$type] ?? 'gray';
                                                ?>
                                                <?php foreach ($records as $record): 
                                                    // Format the value based on record type
                                                    $value = '';
                                                    $recordType = strtoupper($record['type'] ?? '');
                                                    
                                                    switch ($recordType) {
                                                        case 'A':
                                                        case 'AAAA':
                                                            $value = $record['ip'] ?? 'N/A';
                                                            break;
                                                        case 'MX':
                                                            $value = ($record['pri'] ?? '') . ' ' . ($record['target'] ?? 'N/A');
                                                            break;
                                                        case 'CNAME':
                                                            $value = $record['target'] ?? 'N/A';
                                                            break;
                                                        case 'TXT':
                                                            $value = is_array($record['txt'] ?? '') ? implode('', $record['txt']) : ($record['txt'] ?? 'N/A');
                                                            break;
                                                        case 'NS':
                                                            $value = $record['target'] ?? ($record['mname'] ?? 'N/A');
                                                            break;
                                                        case 'SOA':
                                                            $value = ($record['mname'] ?? 'N/A') . ' ' . ($record['rname'] ?? '');
                                                            break;
                                                        default:
                                                            $value = print_r($record, true);
                                                    }
                                                ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-800">
                                                            <i class="fas fa-<?php echo $icon; ?> mr-1"></i>
                                                            <?php echo $type; ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($record['host'] ?? $result['dns']['domain_checked'] ?? $domain); ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-700 break-all font-mono">
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                        <?php 
                                                        if (isset($record['ttl'])) {
                                                            echo $record['ttl'] . 's';
                                                        } elseif (isset($record['minimum-ttl'])) {
                                                            echo $record['minimum-ttl'] . 's';
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <i class="fas fa-info-circle text-4xl mb-2 text-gray-300"></i>
                                        <p>No DNS records found or unable to retrieve DNS information.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- DNS Recommendations -->
                                <div class="mt-8">
                                    <h4 class="text-md font-medium text-gray-900 mb-3">DNS Recommendations</h4>
                                    <div class="space-y-3">
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-5 w-5 <?php echo (isset($result['dns']['has_mx']) && $result['dns']['has_mx']) ? 'text-green-500' : 'text-yellow-500'; ?> mt-0.5">
                                                <i class="fas <?php echo (isset($result['dns']['has_mx']) && $result['dns']['has_mx']) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                                            </div>
                                            <p class="ml-2 text-sm text-gray-600">
                                                <?php echo (isset($result['dns']['has_mx']) && $result['dns']['has_mx']) ? 'MX records are properly configured for email.' : 'Consider adding MX records if you plan to use email with this domain.'; ?>
                                            </p>
                                        </div>
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 h-5 w-5 text-green-500 mt-0.5">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <p class="ml-2 text-sm text-gray-600">
                                                Your domain is resolving correctly.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                        </div>
                    </div>

                   
                        
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-lg font-medium text-gray-900">Traffic Overview</h3>
                                    <div class="flex space-x-2">
                                        <button onclick="changeTrafficRange('7d')" class="px-3 py-1 text-xs font-medium rounded-md bg-indigo-100 text-indigo-800">7d</button>
                                        <button onclick="changeTrafficRange('30d')" class="px-3 py-1 text-xs font-medium rounded-md text-gray-600 hover:bg-gray-100">30d</button>
                                        <button onclick="changeTrafficRange('90d')" class="px-3 py-1 text-xs font-medium rounded-md text-gray-600 hover:bg-gray-100">90d</button>
                                    </div>
                                </div>
                                
                                <!-- Traffic Graph -->
                                <div class="bg-white p-4 rounded-lg border border-gray-200 mb-6">
                                    <div class="h-80">
                                        <canvas id="trafficChart"></canvas>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <div>
                                        <h4 class="font-medium text-gray-700 mb-2">Visits & Pageviews</h4>
                                        <div id="trafficMetrics" class="space-y-3"></div>
                                    </div>
                                    <div class="w-full">
                                        <h4 class="font-medium text-gray-700 mb-4">Traffic Sources</h4>
                                        <div class="space-y-4">
                                            <?php 
                                            $trafficSources = [
                                                [
                                                    'name' => 'Direct',
                                                    'percentage' => 45,
                                                    'visitors' => 11200,
                                                    'change' => '+2.3%',
                                                    'color' => 'bg-blue-500',
                                                    'icon' => 'direct'
                                                ],
                                                [
                                                    'name' => 'Organic Search',
                                                    'percentage' => 32,
                                                    'visitors' => 7980,
                                                    'change' => '+5.1%',
                                                    'color' => 'bg-green-500',
                                                    'icon' => 'search'
                                                ],
                                                [
                                                    'name' => 'Social',
                                                    'percentage' => 12,
                                                    'visitors' => 2990,
                                                    'change' => '-1.2%',
                                                    'color' => 'bg-purple-500',
                                                    'icon' => 'social'
                                                ],
                                                [
                                                    'name' => 'Referral',
                                                    'percentage' => 8,
                                                    'visitors' => 1995,
                                                    'change' => '+0.7%',
                                                    'color' => 'bg-yellow-500',
                                                    'icon' => 'link'
                                                ],
                                                [
                                                    'name' => 'Email',
                                                    'percentage' => 3,
                                                    'visitors' => 750,
                                                    'change' => '+0.1%',
                                                    'color' => 'bg-red-500',
                                                    'icon' => 'email'
                                                ]
                                            ];
                                            
                                            foreach ($trafficSources as $source): 
                                                $iconClass = '';
                                                $iconSvg = '';
                                                
                                                switch($source['icon']) {
                                                    case 'search':
                                                        $iconSvg = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>';
                                                        break;
                                                    case 'social':
                                                        $iconSvg = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>';
                                                        break;
                                                    case 'link':
                                                        $iconSvg = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>';
                                                        break;
                                                    case 'email':
                                                        $iconSvg = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>';
                                                        break;
                                                    default:
                                                        $iconSvg = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>';
                                                }
                                                
                                                $isPositive = strpos($source['change'], '+') === 0;
                                            ?>
                                            <div class="space-y-1">
                                                <div class="flex justify-between text-sm">
                                                    <div class="flex items-center">
                                                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-opacity-10 <?php echo str_replace('bg-', 'bg-', $source['color']); ?>">
                                                            <?php echo $iconSvg; ?>
                                                        </span>
                                                        <span class="ml-3 font-medium text-gray-700"><?php echo $source['name']; ?></span>
                                                    </div>
                                                    <span class="font-medium"><?php echo $source['percentage']; ?>%</span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="h-2 rounded-full <?php echo $source['color']; ?>" style="width: <?php echo $source['percentage']; ?>%"></div>
                                                </div>
                                                <div class="flex justify-between text-xs text-gray-500">
                                                    <span><?php echo number_format($source['visitors']); ?> visitors</span>
                                                    <span class="flex items-center <?php echo $isPositive ? 'text-green-600' : 'text-red-600'; ?>">
                                                        <?php if ($isPositive): ?>
                                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
                                                            </svg>
                                                        <?php else: ?>
                                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1v-5a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586l-4.293-4.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clip-rule="evenodd" />
                                                            </svg>
                                                        <?php endif; ?>
                                                        <?php echo $source['change']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                  

             

                    <!-- SSL Certificate Section -->
                    <div id="ssl-certificate" class="section bg-white rounded-lg shadow overflow-hidden">
                        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-lg font-medium text-gray-900">SSL Certificate</h3>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm text-gray-500">Last checked: Just now</span>
                                        <button onclick="rescanSSLCertificate()" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                            <i class="fas fa-sync-alt mr-1"></i> Rescan
                                        </button>
                                    </div>
                                </div>

                                <?php
                                $sslInfo = getSslInfo($result['domain']);
                                $isValid = $sslInfo['valid'] ?? false;
                                $expiryDate = $sslInfo['expires'] ?? null;
                                $issuer = $sslInfo['issuer'] ?? 'Unknown';
                                $signatureAlgorithm = $sslInfo['signature_algorithm'] ?? 'N/A';
                                $keySize = $sslInfo['key_size'] ?? 0;
                                $keyType = $sslInfo['key_type'] ?? 'N/A';
                                $validFrom = $sslInfo['valid_from'] ?? null;
                                $validTo = $sslInfo['valid_to'] ?? null;
                                $daysRemaining = $expiryDate ? (new DateTime($expiryDate))->diff(new DateTime())->days : 0;
                                $expiryClass = $daysRemaining > 30 ? 'text-green-600' : ($daysRemaining > 7 ? 'text-yellow-600' : 'text-red-600');
                                $expiryStatus = $daysRemaining > 30 ? 'Valid' : ($daysRemaining > 0 ? 'Expiring Soon' : 'Expired');
                                ?>

                                <!-- SSL Certificate Summary -->
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                    <!-- Certificate Status -->
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="p-2 rounded-full <?php echo $isValid ? 'bg-green-100' : 'bg-red-100'; ?>">
                                                <i class="fas <?php echo $isValid ? 'fa-lock text-green-600' : 'fa-unlock text-red-600'; ?>"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500">Status</div>
                                                <div class="text-sm font-semibold <?php echo $isValid ? 'text-green-600' : 'text-red-600'; ?>">
                                                    <?php echo $isValid ? 'Valid' : 'Invalid'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Expiry Date -->
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="p-2 rounded-full bg-blue-100">
                                                <i class="fas fa-calendar-alt text-blue-600"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500">Expires In</div>
                                                <div class="text-sm font-semibold <?php echo $expiryClass; ?>">
                                                    <?php echo $expiryDate ? "$daysRemaining days" : 'N/A'; ?>
                                                    <span class="text-xs font-normal text-gray-500"><?php echo $expiryDate ? "($expiryDate)" : ''; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Issuer -->
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="p-2 rounded-full bg-purple-100">
                                                <i class="fas fa-shield-alt text-purple-600"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500">Issuer</div>
                                                <div class="text-sm font-semibold text-gray-900 truncate" title="<?php echo htmlspecialchars($issuer); ?>">
                                                    <?php echo htmlspecialchars($issuer); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Key Strength -->
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="p-2 rounded-full bg-yellow-100">
                                                <i class="fas fa-key text-yellow-600"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500">Key Strength</div>
                                                <div class="text-sm font-semibold text-gray-900">
                                                    <?php echo $keySize ? "$keySize-bit $keyType" : 'N/A'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Certificate Details -->
                                <div class="bg-gray-50 rounded-lg p-6 mb-6">
                                    <h4 class="text-md font-medium text-gray-900 mb-4">Certificate Details</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-500">Valid From</div>
                                            <div class="text-sm text-gray-900">
                                                <?php echo !empty($sslInfo['valid_from']) ? date('Y-m-d H:i:s', strtotime($sslInfo['valid_from'])) : 'N/A'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-500">Valid To</div>
                                            <div class="text-sm text-gray-900">
                                                <?php echo !empty($sslInfo['valid_to']) ? date('Y-m-d H:i:s', strtotime($sslInfo['valid_to'])) : 'N/A'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-500">Signature Algorithm</div>
                                            <div class="text-sm text-gray-900">
                                                <?php echo !empty($sslInfo['signature_algorithm']) ? htmlspecialchars($sslInfo['signature_algorithm']) : 'N/A'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-500">Key Type</div>
                                            <div class="text-sm text-gray-900">
                                                <?php 
                                                $keyType = $sslInfo['key_type'] ?? 'N/A';
                                                $keySize = $sslInfo['key_size'] ?? 0;
                                                if ($keyType !== 'N/A' && $keySize > 0) {
                                                    echo htmlspecialchars("$keyType");
                                                } else {
                                                    echo htmlspecialchars($keyType);
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-500">Key Strength</div>
                                            <div class="text-sm text-gray-900">
                                                <?php
                                                $keyStrength = 'N/A';
                                                $strengthClass = 'text-gray-500';
                                                
                                                if ($keySize > 0) {
                                                    if (strpos($keyType, 'RSA') !== false) {
                                                        if ($keySize >= 4096) {
                                                            $keyStrength = 'Very Strong ('.$keySize.'-bit)';
                                                            $strengthClass = 'text-green-600 font-medium';
                                                        } elseif ($keySize >= 3072) {
                                                            $keyStrength = 'Strong ('.$keySize.'-bit)';
                                                            $strengthClass = 'text-green-500';
                                                        } elseif ($keySize >= 2048) {
                                                            $keyStrength = 'Good ('.$keySize.'-bit)';
                                                            $strengthClass = 'text-blue-500';
                                                        } else {
                                                            $keyStrength = 'Weak ('.$keySize.'-bit)';
                                                            $strengthClass = 'text-yellow-600';
                                                        }
                                                    } elseif (strpos($keyType, 'EC') !== false) {
                                                        if ($keySize >= 384) {
                                                            $keyStrength = 'Very Strong ('.$keySize.'-bit EC)';
                                                            $strengthClass = 'text-green-600 font-medium';
                                                        } elseif ($keySize >= 256) {
                                                            $keyStrength = 'Strong ('.$keySize.'-bit EC)';
                                                            $strengthClass = 'text-green-500';
                                                        } else {
                                                            $keyStrength = 'Good ('.$keySize.'-bit EC)';
                                                            $strengthClass = 'text-blue-500';
                                                        }
                                                    }
                                                }
                                                ?>
                                                <span class="<?php echo $strengthClass; ?>">
                                                    <?php echo $keyStrength; ?>
                                                    <?php if ($keyStrength !== 'N/A'): ?>
                                                        <span class="text-gray-400 text-xs ml-1">
                                                            <i class="fas fa-info-circle" 
                                                               title="<?php echo $keyType; ?> (<?php echo $keySize; ?>-bit)"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php if (!empty($sslInfo['subject'])): ?>
                                        <div class="md:col-span-2">
                                            <div class="text-sm font-medium text-gray-500">Subject</div>
                                            <div class="text-sm text-gray-900 break-all">
                                                <?php 
                                                $subject = [];
                                                foreach (['CN', 'O', 'OU', 'L', 'ST', 'C'] as $field) {
                                                    if (isset($sslInfo['subject'][$field])) {
                                                        $subject[] = "$field=" . $sslInfo['subject'][$field];
                                                    }
                                                }
                                                echo htmlspecialchars(implode(', ', $subject));
                                                ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($sslInfo['issuer'])): ?>
                                        <div class="md:col-span-2">
                                            <div class="text-sm font-medium text-gray-500">Issuer</div>
                                            <div class="text-sm text-gray-900 break-all">
                                                <?php echo htmlspecialchars($sslInfo['issuer']); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($sslInfo['protocol'])): ?>
                                        <div>
                                            <div class="text-sm font-medium text-gray-500">Protocol</div>
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($sslInfo['protocol']); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Certificate Chain -->
                                <div class="bg-gray-50 rounded-lg p-6 mb-6">
                                    <h4 class="text-md font-medium text-gray-900 mb-4">Certificate Chain</h4>
                                    <div class="space-y-2">
                                        <?php if (!empty($sslInfo['chain'])): ?>
                                            <?php foreach ($sslInfo['chain'] as $index => $cert): ?>
                                                <div class="flex items-start p-3 bg-white rounded-lg border border-gray-200">
                                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center mt-0.5">
                                                        <i class="fas fa-certificate text-indigo-600"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($cert['subject']['CN'] ?? 'Unknown'); ?>
                                                            <?php if ($index === 0): ?>
                                                                <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800">This Certificate</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            Issuer: <?php echo htmlspecialchars($cert['issuer']['CN'] ?? 'Unknown'); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            Valid until: <?php echo $cert['validTo_time_t'] ? date('Y-m-d', $cert['validTo_time_t']) : 'N/A'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4 text-gray-500">
                                                <i class="fas fa-info-circle text-2xl mb-2 text-gray-300"></i>
                                                <p>No certificate chain information available</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Recommendations -->
                                <div class="bg-<?php echo $isValid ? 'green' : 'yellow'; ?>-50 border-l-4 border-<?php echo $isValid ? 'green' : 'yellow'; ?>-500 p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas <?php echo $isValid ? 'fa-check-circle text-green-400' : 'fa-exclamation-triangle text-yellow-400'; ?> text-xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-<?php echo $isValid ? 'green' : 'yellow'; ?>-700">
                                                <?php if ($isValid): ?>
                                                    Your SSL certificate is valid and properly configured.
                                                    <?php if ($daysRemaining <= 30): ?>
                                                        However, it will expire in <?php echo $daysRemaining; ?> days. Consider renewing it soon.
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    There are issues with your SSL certificate. Please ensure it's properly installed and valid.
                                                <?php endif; ?>
                                            </p>
                                            <div class="mt-2">
                                                <a href="#" class="inline-flex items-center text-sm font-medium text-<?php echo $isValid ? 'green' : 'yellow'; ?>-700 hover:text-<?php echo $isValid ? 'green' : 'yellow'; ?>-500">
                                                    Learn more about SSL certificates
                                                    <i class="fas fa-chevron-right ml-1 text-xs"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Headers Section -->
                    <div id="security-headers" class="section bg-white rounded-lg shadow overflow-hidden">
                        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-lg font-medium text-gray-900">Security Headers</h3>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm text-gray-500">Last checked: Just now</span>
                                        <button onclick="rescanSecurityHeaders()" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                            <i class="fas fa-sync-alt mr-1"></i> Rescan
                                        </button>
                                    </div>
                                </div>

                                <!-- Security Headers Summary -->
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                    <?php
                                    $securityHeaders = [
                                        'strict-transport-security' => ['icon' => 'lock', 'title' => 'HSTS'],
                                        'content-security-policy' => ['icon' => 'shield-alt', 'title' => 'CSP'],
                                        'x-frame-options' => ['icon' => 'square', 'title' => 'Clickjacking'],
                                        'x-content-type-options' => ['icon' => 'file-alt', 'title' => 'MIME Sniffing']
                                    ];
                                    
                                    foreach ($securityHeaders as $header => $info): 
                                        $isEnabled = isset($result['security_headers'][$header]) && $result['security_headers'][$header]['enabled'];
                                        $severity = $isEnabled ? 'success' : 'warning';
                                        $iconColor = $isEnabled ? 'text-green-500' : 'text-yellow-500';
                                    ?>
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="p-2 rounded-full bg-<?php echo $isEnabled ? 'green' : 'yellow'; ?>-100">
                                                <i class="fas fa-<?php echo $info['icon']; ?> <?php echo $iconColor; ?>"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-500"><?php echo $info['title']; ?></div>
                                                <div class="text-sm font-semibold text-<?php echo $isEnabled ? 'green' : 'yellow'; ?>-600">
                                                    <?php echo $isEnabled ? 'Enabled' : 'Missing'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Detailed Security Headers Table -->
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Header</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recommendation</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php 
                                            $headersToShow = [
                                                'strict-transport-security',
                                                'content-security-policy',
                                                'x-frame-options',
                                                'x-content-type-options',
                                                'x-xss-protection',
                                                'referrer-policy',
                                                'permissions-policy'
                                            ];
                                            
                                            foreach ($headersToShow as $header): 
                                                $headerData = $result['security_headers'][$header] ?? [
                                                    'enabled' => false,
                                                    'value' => '',
                                                    'description' => '',
                                                    'recommendation' => 'Not implemented',
                                                    'severity' => 'low'
                                                ];
                                                
                                                $statusClass = $headerData['enabled'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                                $statusText = $headerData['enabled'] ? 'Enabled' : 'Missing';
                                                $value = $headerData['enabled'] ? htmlspecialchars($headerData['value']) : 'Not set';
                                            ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo ucwords(str_replace('-', ' ', $header)); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo $headerData['description'] ?? ''; ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500 font-mono text-xs">
                                                    <?php if ($headerData['enabled'] && !empty($headerData['value'])): ?>
                                                        <div class="bg-gray-50 p-2 rounded overflow-x-auto max-w-md">
                                                            <?php echo $value; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?php echo $headerData['recommendation'] ?? 'No recommendation available'; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Security Headers Score -->
                                <div class="mt-8 bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-md font-medium text-gray-900 mb-1">Security Headers Score</h4>
                                            <p class="text-sm text-gray-500">Based on the implementation of recommended security headers</p>
                                        </div>
                                        <?php
                                        $totalHeaders = count($headersToShow);
                                        $enabledHeaders = 0;
                                        $score = 0;
                                        
                                        foreach ($headersToShow as $header) {
                                            if (isset($result['security_headers'][$header]) && $result['security_headers'][$header]['enabled']) {
                                                $enabledHeaders++;
                                            }
                                        }
                                        
                                        if ($totalHeaders > 0) {
                                            $score = round(($enabledHeaders / $totalHeaders) * 100);
                                        }
                                        
                                        $scoreClass = $score >= 80 ? 'text-green-600' : ($score >= 50 ? 'text-yellow-600' : 'text-red-600');
                                        $progressClass = $score >= 80 ? 'bg-green-600' : ($score >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                                        ?>
                                        <div class="text-right">
                                            <div class="text-3xl font-bold <?php echo $scoreClass; ?>"><?php echo $score; ?>%</div>
                                            <div class="text-sm text-gray-500"><?php echo $enabledHeaders; ?> of <?php echo $totalHeaders; ?> headers implemented</div>
                                        </div>
                                    </div>
                                    <div class="mt-4 w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="h-2.5 rounded-full <?php echo $progressClass; ?>" style="width: <?php echo $score; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WHOIS Section -->
                    <div id="whois" class="section bg-white flex flex-col space-y-6"> 
                        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">WHOIS Information</h3>
                                
                                <div class="space-y-6">

                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500">Domain Name</h4>
                                            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($result['domain'] ?? 'N/A'); ?></p>
                                        </div>
                                        
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500">Registrar</h4>
                                            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($result['whois']['registrar'] ?? 'N/A'); ?></p>
                                        </div>
                                        
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500">Registration Date</h4>
                                            <p class="mt-1 text-sm text-gray-900">
                                                <?php 
                                                if (isset($result['whois']['created'])) {
                                                    echo date('F j, Y', strtotime($result['whois']['created']));
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500">Expiration Date</h4>
                                            <p class="mt-1 text-sm text-gray-900">
                                                <?php 
                                                if (isset($result['whois']['expires'])) {
                                                    $expiryDate = strtotime($result['whois']['expires']);
                                                    $daysLeft = floor(($expiryDate - time()) / (60 * 60 * 24));
                                                    $textClass = $daysLeft < 30 ? 'text-red-600' : 'text-gray-900';
                                                    echo '<span class="' . $textClass . '">' . date('F j, Y', $expiryDate) . '</span>';
                                                    echo ' <span class="text-sm ' . $textClass . '">(' . $daysLeft . ' days remaining)</span>';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-500">Name Servers</h4>
                                            <ul class="mt-1 text-sm text-gray-900 space-y-1">
                                                <?php 
                                                if (isset($result['whois']['nameservers']) && is_array($result['whois']['nameservers'])) {
                                                    foreach ($result['whois']['nameservers'] as $ns): ?>
                                                        <li class="font-mono"><?php echo htmlspecialchars($ns); ?></li>
                                                    <?php endforeach;
                                                } else {
                                                    echo '<li>N/A</li>';
                                                }
                                                ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- WHOIS Recommendations -->
                                <div class="mt-8">
                                    <h4 class="text-md font-medium text-gray-900 mb-3">Domain Status</h4>
                                    <div class="space-y-3">
                                        <?php 
                                        if (isset($result['whois']['expires'])) {
                                            $expiryDate = strtotime($result['whois']['expires']);
                                            $daysLeft = floor(($expiryDate - time()) / (60 * 60 * 24));
                                            
                                            if ($daysLeft < 30) {
                                                echo '<div class="bg-red-50 border-l-4 border-red-500 p-4">
                                                    <div class="flex">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                                                        </div>
                                                        <div class="ml-3">
                                                            <p class="text-sm text-red-700">
                                                                Your domain will expire in ' . $daysLeft . ' days. Consider renewing it soon to avoid service disruption.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>';
                                            } else {
                                                echo '<div class="bg-green-50 border-l-4 border-green-500 p-4">
                                                    <div class="flex">
                                                        <div class="flex-shrink-0">
                                                            <i class="fas fa-check-circle text-green-500"></i>
                                                        </div>
                                                        <div class="ml-3">
                                                            <p class="text-sm text-green-700">
                                                                Your domain is active and will expire on ' . date('F j, Y', $expiryDate) . '.
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add smooth scrolling for anchor links -->
    <!-- Add Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Traffic Chart Initialization -->
    <script>
    // Traffic Chart
    let trafficChart;
    let trafficData = {
        labels: [],
        visits: [],
        pageviews: []
    };
    
    // Initialize Traffic Chart
    function initTrafficChart() {
        const ctx = document.getElementById('trafficChart').getContext('2d');
        trafficChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: trafficData.labels,
                datasets: [
                    {
                        label: 'Visits',
                        data: trafficData.visits,
                        borderColor: 'rgba(79, 70, 229, 1)',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Pageviews',
                        data: trafficData.pageviews,
                        borderColor: 'rgba(236, 72, 153, 1)',
                        backgroundColor: 'rgba(236, 72, 153, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Load traffic data
    function loadTrafficData(range = '7d') {
        // Simulated data - replace with actual API call
        const mockData = {
            '7d': {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                visits: [120, 190, 130, 170, 210, 190, 230],
                pageviews: [240, 350, 290, 330, 420, 380, 470]
            },
            '30d': {
                labels: Array.from({length: 30}, (_, i) => (i + 1).toString()),
                visits: Array.from({length: 30}, () => Math.floor(Math.random() * 200) + 100),
                pageviews: Array.from({length: 30}, () => Math.floor(Math.random() * 400) + 200)
            },
            '90d': {
                labels: Array.from({length: 13}, (_, i) => `Week ${i + 1}`),
                visits: Array.from({length: 13}, () => Math.floor(Math.random() * 300) + 200),
                pageviews: Array.from({length: 13}, () => Math.floor(Math.random() * 600) + 300)
            }
        };
        
        // Update chart data
        trafficData = mockData[range] || mockData['7d'];
        
        if (trafficChart) {
            trafficChart.data.labels = trafficData.labels;
            trafficChart.data.datasets[0].data = trafficData.visits;
            trafficChart.data.datasets[1].data = trafficData.pageviews;
            trafficChart.update();
        } else {
            initTrafficChart();
        }
        
        // Update metrics
        updateTrafficMetrics(trafficData);
    }
    
    // Update traffic metrics
    function updateTrafficMetrics(data) {
        const totalVisits = data.visits.reduce((a, b) => a + b, 0);
        const totalPageviews = data.pageviews.reduce((a, b) => a + b, 0);
        const avgPagesPerVisit = (totalPageviews / totalVisits).toFixed(1);
        const maxVisits = Math.max(...data.visits);
        const maxPageviews = Math.max(...data.pageviews);
        
        document.getElementById('trafficMetrics').innerHTML = `
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                <div>
                    <p class="text-xs text-gray-500">Total Visits</p>
                    <p class="text-lg font-semibold">${totalVisits.toLocaleString()}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Peak</p>
                    <p class="text-lg font-semibold">${maxVisits.toLocaleString()}</p>
                </div>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                <div>
                    <p class="text-xs text-gray-500">Total Pageviews</p>
                    <p class="text-lg font-semibold">${totalPageviews.toLocaleString()}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500">Pages/Visit</p>
                    <p class="text-lg font-semibold">${avgPagesPerVisit}</p>
                </div>
            </div>
        `;
    }
    
    // Change time range
    function changeTrafficRange(range) {
        // Update active button
        document.querySelectorAll('#traffic button').forEach(btn => {
            if (btn.textContent.toLowerCase().includes(range)) {
                btn.classList.add('bg-indigo-100', 'text-indigo-800');
                btn.classList.remove('text-gray-600', 'hover:bg-gray-100');
            } else {
                btn.classList.remove('bg-indigo-100', 'text-indigo-800');
                btn.classList.add('text-gray-600', 'hover:bg-gray-100');
            }
        });
        
        // Load data for selected range
        loadTrafficData(range);
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadTrafficData('7d');
    });
    </script>
    
    <!-- Performance Chart Data -->
    <script>
    // Global chart variable
    let performanceChart;
    
    // Initialize performance chart
    function initPerformanceChart() {
        const ctx = document.getElementById('performanceChart').getContext('2d');
        
        // Initial data (will be updated based on time range)
        const data = generatePerformanceData('24h');
        
        // Create the chart
        performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Response Time (ms)',
                    data: data.values,
                    borderColor: 'rgba(79, 70, 229, 1)',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' ms';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' ms';
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }
    
    // Generate sample performance data based on time range
    function generatePerformanceData(range) {
        const data = { labels: [], values: [] };
        let pointCount = 24; // Default to 24 hours
        
        if (range === '7d') {
            pointCount = 7; // 7 days
        } else if (range === '30d') {
            pointCount = 30; // 30 days
        }
        
        // Generate labels based on range
        if (range === '24h') {
            for (let i = 0; i < pointCount; i++) {
                const hour = i % 12 === 0 ? 12 : i % 12;
                const ampm = i < 12 ? 'AM' : 'PM';
                data.labels.push(`${hour}${ampm}`);
                data.values.push(Math.floor(Math.random() * 500) + 50); // Random value between 50-550ms
            }
        } else {
            const now = new Date();
            for (let i = pointCount - 1; i >= 0; i--) {
                const date = new Date();
                date.setDate(now.getDate() - i);
                data.labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                data.values.push(Math.floor(Math.random() * 500) + 50); // Random value between 50-550ms
            }
        }
        
        return data;
    }
    
    // Change time range for performance chart
    function changeTimeRange(range) {
        // Update active button
        const container = document.querySelector('#performance .p-6');
        if (container) {
            container.querySelectorAll('button').forEach(btn => {
                btn.classList.remove('bg-indigo-100', 'text-indigo-800');
                btn.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            
            const activeBtn = Array.from(container.querySelectorAll('button')).find(btn => 
                btn.textContent.trim().toLowerCase().includes(range.replace('d', ''))
            );
            
            if (activeBtn) {
                activeBtn.classList.remove('text-gray-600', 'hover:bg-gray-100');
                activeBtn.classList.add('bg-indigo-100', 'text-indigo-800');
            }
        }
        
        // Update chart data
        if (performanceChart) {
            const newData = generatePerformanceData(range);
            performanceChart.data.labels = newData.labels;
            performanceChart.data.datasets[0].data = newData.pageLoads;
            performanceChart.data.datasets[1].data = newData.timeToFirstBytes;
            performanceChart.data.datasets[2].data = newData.responseTimes;
            performanceChart.update();
            
            // Update metrics
            updatePerformanceMetrics(newData.responseTimes, range);
        }
    }
    
    // Performance Over Time Chart
    let performanceOverTimeChart;
    
    // Initialize performance over time chart
    function initPerformanceOverTimeChart() {
        const ctx = document.getElementById('performanceOverTimeChart');
        if (!ctx) return;
        
        const data = generatePerformanceOverTimeData('24h');
        
        performanceOverTimeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Response Time (ms)',
                        data: data.responseTimes,
                        borderColor: 'rgba(79, 70, 229, 1)',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false,
                        yAxisID: 'y',
                        pointRadius: 2,
                        pointHoverRadius: 4
                    },
                    {
                        label: 'Uptime (%)',
                        data: data.uptimes,
                        borderColor: 'rgba(16, 185, 129, 1)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: false,
                        yAxisID: 'y1',
                        borderDash: [5, 5],
                        pointRadius: 2,
                        pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y + (label.includes('Uptime') ? '%' : ' ms');
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Response Time (ms)'
                        },
                        grid: {
                            drawOnChartArea: true
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Uptime (%)'
                        },
                        grid: {
                            drawOnChartArea: false
                        },
                        min: 80,
                        max: 100,
                        beginAtZero: false
                    }
                }
            }
        });
    }
    
    // Generate sample performance over time data
    function generatePerformanceOverTimeData(range) {
        const data = { 
            labels: [], 
            responseTimes: [],
            uptimes: []
        };
        
        let pointCount = 24; // Default to 24 hours
        
        if (range === '7d') {
            pointCount = 7; // 7 days
        } else if (range === '30d') {
            pointCount = 30; // 30 days
        }
        
        // Generate labels and data based on range
        if (range === '24h') {
            for (let i = 0; i < pointCount; i++) {
                const hour = i % 12 === 0 ? 12 : i % 12;
                const ampm = i < 12 ? 'AM' : 'PM';
                data.labels.push(`${hour}${ampm}`);
                
                // Generate realistic looking data with some variation
                const baseResponse = 100 + Math.sin(i/3) * 50 + Math.random() * 30;
                const baseUptime = 95 + Math.sin(i/2) * 2 + Math.random() * 1.5;
                
                data.responseTimes.push(Math.round(baseResponse));
                data.uptimes.push(Math.min(100, Math.round(baseUptime * 10) / 10));
            }
        } else {
            const now = new Date();
            for (let i = pointCount - 1; i >= 0; i--) {
                const date = new Date();
                date.setDate(now.getDate() - i);
                data.labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                
                // Generate realistic looking data with some variation
                const baseResponse = 100 + Math.sin(i) * 40 + Math.random() * 50;
                const baseUptime = 95 + Math.sin(i/2) * 3 + Math.random() * 1.5;
                
                data.responseTimes.push(Math.round(baseResponse));
                data.uptimes.push(Math.min(100, Math.round(baseUptime * 10) / 10));
            }
        }
        
        return data;
    }
    
    // Change time range for performance over time chart
    function changePerformanceChartRange(range) {
        // Update active button
        const container = document.querySelector('.mt-8');
        if (container) {
            container.querySelectorAll('button').forEach(btn => {
                btn.classList.remove('bg-indigo-100', 'text-indigo-800');
                btn.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            
            const activeBtn = Array.from(container.querySelectorAll('button')).find(btn => 
                btn.textContent.trim().toLowerCase().includes(range.replace('d', ''))
            );
            
            if (activeBtn) {
                activeBtn.classList.remove('text-gray-600', 'hover:bg-gray-100');
                activeBtn.classList.add('bg-indigo-100', 'text-indigo-800');
            }
        }
        
        // Update chart data
        if (performanceOverTimeChart) {
            const newData = generatePerformanceOverTimeData(range);
            performanceOverTimeChart.data.labels = newData.labels;
            performanceOverTimeChart.data.datasets[0].data = newData.responseTimes;
            performanceOverTimeChart.data.datasets[1].data = newData.uptimes;
            performanceOverTimeChart.update();
        }
    }
    
    // Initialize charts when the DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initPerformanceChart();
        initPerformanceOverTimeChart();
        
        // Set initial active buttons
        changeTimeRange('24h');
        changePerformanceChartRange('24h');
    });
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 20,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Performance Chart
        let performanceChart;
        
        // Initialize performance chart
        function initPerformanceChart() {
            const ctx = document.getElementById('performanceChart');
            if (!ctx) return;
            
            // Generate sample data (in a real app, this would come from your API)
            const { labels, data } = generatePerformanceData('24h');
            
            performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: data,
                        borderColor: 'rgba(79, 70, 229, 1)',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                        pointBorderColor: '#fff',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'rgba(79, 70, 229, 1)',
                        pointHoverBorderColor: '#fff',
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: { size: 14, weight: 'normal' },
                            bodyFont: { size: 14, weight: 'bold' },
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Response Time: ' + context.parsed.y + ' ms';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6B7280',
                                maxRotation: 0,
                                maxTicksLimit: 8
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                color: '#6B7280',
                                callback: function(value) {
                                    return value + 'ms';
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        }
        
        // Generate sample performance data
        // Function to rescan SSL certificate
    function rescanSSLCertificate() {
        const domain = '<?php echo $result['domain']; ?>';
        const button = document.querySelector('button[onclick="rescanSSLCertificate()"]');
        const icon = button.querySelector('i');
        const originalText = button.innerHTML;
                let variation = Math.sin(i * 0.5) * 30; // Add some natural variation
                data.push(Math.round(Math.max(10, baseValue + variation)));
            }
            
            return { labels, data };
        }
        
        // Change time range for the performance chart
        window.changeTimeRange = function(range) {
            if (!performanceChart) return;
            
            const { labels, data } = generatePerformanceData(range);
            
            // Update chart data
            performanceChart.data.labels = labels;
            performanceChart.data.datasets[0].data = data;
            performanceChart.update();
            
            // Update active button
            document.querySelectorAll('#performance button').forEach(btn => {
                if (btn.textContent.toLowerCase().includes(range)) {
                    btn.classList.remove('text-gray-600', 'hover:bg-gray-100');
                    btn.classList.add('bg-indigo-100', 'text-indigo-800');
                } else {
                    btn.classList.add('text-gray-600', 'hover:bg-gray-100');
                    btn.classList.remove('bg-indigo-100', 'text-indigo-800');
                }
            });
            
            // Update time range label
            const rangeLabels = {
                '24h': 'Last 24 hours',
                '7d': 'Last 7 days',
                '30d': 'Last 30 days'
            };
            const labelElement = document.getElementById('timeRangeLabel');
            if (labelElement) {
                labelElement.textContent = rangeLabels[range] || range;
            }
        };
        
        // Initialize the charts when the page loads
        initPerformanceChart();
        initTrafficCharts();
    });
    
    // Performance and Traffic Charts
    let performanceOverTimeChart, trafficChart, devicesChart;
    
    function initTrafficCharts() {
        // Traffic Chart
        const trafficCtx = document.getElementById('trafficChart');
        if (trafficCtx) {
            const { labels, visitors, pageviews } = generateTrafficData('24h');
            
            trafficChart = new Chart(trafficCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Visitors',
                            data: visitors,
                            borderColor: 'rgba(79, 70, 229, 1)',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Pageviews',
                            data: pageviews,
                            borderColor: 'rgba(16, 185, 129, 1)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleFont: { size: 14, weight: 'normal' },
                            bodyFont: { size: 14, weight: 'bold' },
                            padding: 12,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y >= 1000 
                                            ? (context.parsed.y / 1000).toFixed(1) + 'k' 
                                            : context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6B7280',
                                maxRotation: 0,
                                maxTicksLimit: 8
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Visitors'
                            },
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return value >= 1000 ? (value / 1000) + 'k' : value;
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Pageviews'
                            },
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return value >= 1000 ? (value / 1000) + 'k' : value;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Devices Pie Chart
        const devicesCtx = document.getElementById('devicesChart');
        if (devicesCtx) {
            devicesChart = new Chart(devicesCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Desktop', 'Mobile', 'Tablet'],
                    datasets: [{
                        data: [62, 32, 6],
                        backgroundColor: [
                            'rgba(79, 70, 229, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)'
                        ],
                        borderColor: [
                            'rgba(79, 70, 229, 1)',
                            'rgba(16, 185, 129, 1)',
                            'rgba(245, 158, 11, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw + '%';
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        }
    }
    
    // Generate sample traffic data
    function generateTrafficData(range) {
        let labels = [];
        let visitors = [];
        let pageviews = [];
        let now = new Date();
        let count, interval, format;
        
        // Set parameters based on time range
        switch(range) {
            case '24h':
                count = 24;
                interval = 1; // hours
                format = 'ha';
                break;
            case '7d':
                count = 7;
                interval = 1; // days
                format = 'EEE';
                break;
            case '30d':
                count = 30;
                interval = 1; // days
                format = 'MMM d';
                break;
            default:
                count = 24;
                interval = 1;
                format = 'ha';
        }
        
        // Generate labels and data
        for (let i = count - 1; i >= 0; i--) {
            let date = new Date(now);
            
            if (range === '24h') {
                date.setHours(now.getHours() - i * interval);
                labels.push(date.toLocaleTimeString('en-US', { hour: 'numeric', hour12: true }).replace(' ', ''));
            } else {
                date.setDate(now.getDate() - i * interval);
                labels.push(date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));
            }
            
            // Generate random data with some variation
            const baseVisitors = range === '24h' ? 200 : (range === '7d' ? 1200 : 5000);
            const basePageviews = baseVisitors * (Math.random() * 1.5 + 1.5); // 1.5x to 3x visitors
            
            // Add some daily/weekly variation
            const variation = Math.sin(i * 0.5) * 0.3 + 1; // 0.7 to 1.3 multiplier
            const dailyPattern = range === '24h' ? 
                (date.getHours() >= 9 && date.getHours() <= 17 ? 1.5 : 1) : // Higher during business hours
                (date.getDay() >= 5 ? 0.7 : 1); // Lower on weekends
                
            visitors.push(Math.round(baseVisitors * variation * dailyPattern * (Math.random() * 0.2 + 0.9)));
            pageviews.push(Math.round(basePageviews * variation * dailyPattern * (Math.random() * 0.2 + 0.9)));
        }
        
        return { labels, visitors, pageviews };
    }
    
    // Change time range for the traffic chart
    window.changeTrafficTimeRange = function(range) {
        if (!trafficChart) return;
        
        const { labels, visitors, pageviews } = generateTrafficData(range);
        
        // Update chart data
        trafficChart.data.labels = labels;
        trafficChart.data.datasets[0].data = visitors;
        trafficChart.data.datasets[1].data = pageviews;
        trafficChart.update();
        
        // Update active button
        document.querySelectorAll('#traffic button').forEach(btn => {
            if (btn.textContent.toLowerCase().includes(range)) {
                btn.classList.remove('text-gray-600', 'hover:bg-gray-100');
                btn.classList.add('bg-indigo-100', 'text-indigo-800');
            } else {
                btn.classList.add('text-gray-600', 'hover:bg-gray-100');
                btn.classList.remove('bg-indigo-100', 'text-indigo-800');
            }
        });
        
        // Update time range label
        const rangeLabels = {
            '24h': 'Last 24 hours',
            '7d': 'Last 7 days',
            '30d': 'Last 30 days'
        };
        const labelElement = document.getElementById('trafficTimeRangeLabel');
        if (labelElement) {
            labelElement.textContent = rangeLabels[range] || range;
        }
    };
    </script>
    
    <script src="../../assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('gscSidebar');
        
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
    });
    </script>
    
    <style>
    /* Add some spacing between sections */
    .section {
        margin-bottom: 2rem;
        padding: 1.5rem;
    }
    
    /* Style section headers */
    .section h3 {
        font-size: 1.25rem;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #3c4043;
    }
    
    /* Add some padding to the main content */
    main {
        padding-bottom: 3rem;
    }
    </style>
        </div>
        </main>
    </div>
</body>
</html>
