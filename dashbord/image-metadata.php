<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$uploadSuccess = false;
$error = '';
$exifData = [];

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    
    // Check for upload errors
    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmpName = $file['tmp_name'];
        $fileName = basename($file['name']);
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check if EXIF extension is available
        if (!function_exists('exif_read_data')) {
            $error = 'EXIF extension is not enabled in your PHP configuration. Please enable the exif extension in your php.ini file.';
        } else {
            // Enable error logging for EXIF
            $exifErrors = [];
            set_error_handler(function($errno, $errstr) use (&$exifErrors) {
                $exifErrors[] = $errstr;
                return true;
            }, E_ALL);
            
            // Try to read EXIF data with detailed error reporting
            $exif = exif_read_data($tmpName, 'ANY_TAG', true, true);
            
            // Restore error handler
            restore_error_handler();
            
            // Debug output
            error_log('EXIF Debug - File: ' . $file['name']);
            error_log('EXIF Debug - MIME type: ' . mime_content_type($tmpName));
            error_log('EXIF Debug - File size: ' . filesize($tmpName) . ' bytes');
            
            if ($exif !== false) {
                $uploadSuccess = true;
                $exifData = $exif;
                error_log('EXIF Debug - EXIF data found: ' . print_r($exif, true));
            } else {
                $error = 'No EXIF data found. ';
                if (!empty($exifErrors)) {
                    $error .= 'Errors: ' . implode('; ', array_unique($exifErrors));
                }
                error_log('EXIF Debug - No EXIF data. Errors: ' . print_r($exifErrors, true));
                
                // Try to get basic image info even if EXIF is not available
                $imageInfo = @getimagesize($tmpName);
                if ($imageInfo !== false) {
                    $exifData = [
                        'FileInfo' => [
                            'MimeType' => $imageInfo['mime'],
                            'Width' => $imageInfo[0],
                            'Height' => $imageInfo[1],
                            'Bits' => $imageInfo['bits'] ?? 'N/A',
                            'Channels' => $imageInfo['channels'] ?? 'N/A',
                        ]
                    ];
                    
                    // Try to get more info using GD
                    if (function_exists('imagecreatefromstring')) {
                        $img = @imagecreatefromstring(file_get_contents($tmpName));
                        if ($img !== false) {
                            $exifData['FileInfo']['ColorType'] = function_exists('imageistruecolor') && imageistruecolor($img) ? 'True Color' : 'Indexed';
                            $exifData['FileInfo']['Transparency'] = function_exists('imagecolortransparent') && imagecolortransparent($img) >= 0 ? 'Yes' : 'No';
                            imagedestroy($img);
                        }
                    }
                    
                    $uploadSuccess = true;
                    $error = 'No EXIF data found, but basic image information was retrieved.';
                } else {
                    $error = 'The uploaded file is not a valid image or is corrupted.';
                }
            }
        }
    } else {
        $error = 'Error uploading file. Error code: ' . $file['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Metadata - TechDetector</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ntsa-style.css">
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
        .card-header {
            background-color: #1a1a1a;
            border-bottom: 1px solid #3c4043;
            color: #ffffff;
        }
        .form-control {
            background-color: #1a1a1a;
            border-color: #3c4043;
            color: #ffffff;
        }
        .form-control:focus {
            border-color: #ff6b00;
            outline: none;
        }
        .form-label {
            color: #d1d5db;
        }
        .btn-primary {
            background-color: #ff6b00;
            border-color: #ff6b00;
        }
        .btn-primary:hover {
            background-color: #e65a00;
            border-color: #e65a00;
        }
        .alert-danger {
            background-color: rgba(255, 107, 0, 0.1);
            border-color: #ff6b00;
            color: #ff6b00;
        }
        .alert-warning {
            background-color: rgba(255, 107, 0, 0.1);
            border-color: #ff6b00;
            color: #ff6b00;
        }
        .table {
            color: #ffffff;
        }
        .table th {
            background-color: #1a1a1a;
            border-color: #3c4043;
        }
        .table td {
            border-color: #3c4043;
        }
        .text-blue-600 {
            color: #ff6b00 !important;
        }
    </style>
</head>
<body class="bg-black font-sans antialiased">
    <!-- Google Search Console-style Sidebar -->
    <aside class="gsc-sidebar" id="gscSidebar">
        <div class="gsc-sidebar-header">
            <a href="../index.php" class="gsc-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </div>
        
        <nav class="gsc-sidebar-nav">
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Dashboard</div>
                <a href="overview" class="gsc-nav-item">
                    <i class="fas fa-home"></i>
                    <span>Overview</span>
                </a>
                <a href="domain-check" class="gsc-nav-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Domain Check</span>
                </a>
                <a href="link-analyzer" class="gsc-nav-item">
                    <i class="fas fa-link"></i>
                    <span>Link Analyzer</span>
                </a>
            </div>
            
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Account</div>
                <a href="profile" class="gsc-nav-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="settings" class="gsc-nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="../api/logout" class="gsc-nav-item">
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
            <a href="overview" class="gsc-topbar-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </header>

        <!-- Page Content -->
        <div class="gsc-content-wrapper">
    <div class="container">
        <h1 class="mb-4 text-white">Image Metadata Extractor</h1>
        
        <div class="card">
            <div class="card-header">
                <h5>Upload Image</h5>
            </div>
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="image" class="form-label">Select an image (JPG, PNG, WEBP, HEIC, TIFF):</label>
                        <input class="form-control" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,image/tiff" required>
                        <div class="form-text">
                            <p>For best results, use original images directly from your camera or device.</p>
                            <p class="mt-2">
                                <strong>Test with this sample image:</strong> 
                                <a href="https://raw.githubusercontent.com/ianare/exif-samples/master/jpg/gps/DSCN0010.jpg" target="_blank" class="text-blue-600 hover:underline">
                                    Download test image with EXIF data
                                </a>
                            </p>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Extract Metadata</button>
                </form>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($uploadSuccess && !empty($exifData)): ?>
        <div class="exif-section">
            <h2>Image Metadata</h2>
            <div class="card">
                <div class="card-body">
                    <?php
                    // Function to display EXIF data recursively
                    function displayExifData($data, $level = 0) {
                        $html = '<ul class="list-unstyled' . ($level > 0 ? ' ms-3' : '') . '">';
                        foreach ($data as $key => $value) {
                            if (is_array($value)) {
                                $html .= '<li><strong>' . htmlspecialchars($key) . ':</strong>';
                                $html .= displayExifData($value, $level + 1);
                                $html .= '</li>';
                            } else {
                                // Skip binary data
                                if (is_string($value) && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', $value)) {
                                    $value = '[Binary Data]';
                                }
                                $html .= '<li><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars(print_r($value, true)) . '</li>';
                            }
                        }
                        $html .= '</ul>';
                        return $html;
                    }
                    
                    // Display important EXIF data in a table
                    $importantData = [
                        'Date/Time' => $exifData['IFD0']['DateTime'] ?? ($exifData['EXIF']['DateTimeOriginal'] ?? 'Not available'),
                        'Camera Make' => $exifData['IFD0']['Make'] ?? 'Not available',
                        'Camera Model' => $exifData['IFD0']['Model'] ?? 'Not available',
                        'Software' => $exifData['IFD0']['Software'] ?? 'Not available',
                        'Orientation' => $exifData['IFD0']['Orientation'] ?? 'Not available',
                        'X Resolution' => isset($exifData['IFD0']['XResolution']) ? 
                            $exifData['IFD0']['XResolution'] . ' dpi' : 'Not available',
                        'Y Resolution' => isset($exifData['IFD0']['YResolution']) ? 
                            $exifData['IFD0']['YResolution'] . ' dpi' : 'Not available',
                        'Exposure Time' => $exifData['EXIF']['ExposureTime'] ?? 'Not available',
                        'F-Number' => $exifData['EXIF']['FNumber'] ?? 'Not available',
                        'ISO' => $exifData['EXIF']['ISOSpeedRatings'] ?? 'Not available',
                        'Focal Length' => $exifData['EXIF']['FocalLength'] ?? 'Not available',
                        'Flash' => $exifData['EXIF']['Flash'] ?? 'Not available',
                    ];
                    ?>
                    
                    <h5>Key Information</h5>
                    <table class="table table-bordered exif-table">
                        <tbody>
                            <?php foreach ($importantData as $label => $value): ?>
                                <tr>
                                    <th style="width: 200px;"><?php echo htmlspecialchars($label); ?></th>
                                    <td><?php echo htmlspecialchars($value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (!empty($exifData['FileInfo']) && count($exifData) === 1): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No EXIF data found in this image. This usually happens when the image has been edited or saved by software that strips metadata.
                        </div>
                    <?php endif; ?>
                    
                    <h5 class="mt-4"><?php echo (empty($exifData['FileInfo']) || count($exifData) > 1) ? 'Complete EXIF Data' : 'Basic Image Information'; ?></h5>
                    <div class="exif-raw">
                        <?php 
                        // Sort the data for better readability
                        if (!empty($exifData['EXIF'])) {
                            ksort($exifData['EXIF']);
                        }
                        if (!empty($exifData['IFD0'])) {
                            ksort($exifData['IFD0']);
                        }
                        echo displayExifData($exifData); 
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
        </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
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
</body>
</html>
