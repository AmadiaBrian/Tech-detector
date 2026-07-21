<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get domain from query parameter
$domain = filter_input(INPUT_GET, 'domain', FILTER_SANITIZE_STRING);

if (empty($domain)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Domain parameter is required']);
    exit;
}

// Function to make HTTP requests
function makeRequest($url, $headers = []) {
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ], $headers)
    ];
    
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status_code' => $httpCode,
        'data' => $response,
        'error' => $error
    ];
}

// Function to check domain speed
function checkDomainSpeed($domain) {
    $startTime = microtime(true);
    $result = makeRequest("http://" . $domain);
    $endTime = microtime(true);
    
    $loadTime = round(($endTime - $startTime) * 1000); // in milliseconds
    $pageSize = isset($result['data']) ? strlen($result['data']) : 0;
    
    return [
        'load_time_ms' => $loadTime,
        'page_size_bytes' => $pageSize,
        'page_size_kb' => round($pageSize / 1024, 2),
        'status' => $result['status_code'] ?? 0,
        'success' => $result['success'] ?? false
    ];
}

// Function to check domain uptime (simplified)
function checkUptime($domain) {
    // In a real application, this would query your monitoring database
    // For this example, we'll simulate some data
    return [
        'status' => 'up', // up, down, unknown
        'last_checked' => date('Y-m-d H:i:s'),
        'uptime_24h' => '99.9%',
        'downtime_24h' => '0.1%',
        'response_time_avg' => rand(100, 500) . 'ms',
        'last_incident' => null
    ];
}

// Function to get domain traffic data (simplified)
function getTrafficData($domain) {
    // In a real application, this would query Google Analytics or similar
    return [
        'visits_24h' => rand(1000, 10000),
        'pageviews_24h' => rand(2000, 50000),
        'avg_session_duration' => rand(60, 300) . 's',
        'bounce_rate' => rand(30, 70) . '%',
        'top_pages' => [
            ['url' => '/', 'visits' => rand(500, 5000)],
            ['url' => '/about', 'visits' => rand(100, 1000)],
            ['url' => '/contact', 'visits' => rand(50, 500)]
        ],
        'traffic_sources' => [
            'direct' => rand(20, 60) . '%',
            'search' => rand(20, 50) . '%',
            'social' => rand(5, 20) . '%',
            'referral' => rand(5, 30) . '%'
        ]
    ];
}

// Function to get DNS information
function getDnsInfo($domain) {
    // First, try to get the base domain (without www.)
    $cleanDomain = preg_replace('/^www\./i', '', $domain);
    
    // Get all DNS records
    $dnsRecords = @dns_get_record($cleanDomain, DNS_ALL);
    
    if ($dnsRecords === false) {
        // If no records found, try with www. prefix
        if (strpos($cleanDomain, 'www.') !== 0) {
            $dnsRecords = @dns_get_record('www.' . $cleanDomain, DNS_ALL);
            if ($dnsRecords === false) {
                return ['error' => 'Failed to retrieve DNS records'];
            }
        } else {
            return ['error' => 'Failed to retrieve DNS records'];
        }
    }
    
    // Process and format the records
    $formattedRecords = [];
    $nsRecords = [];
    
    foreach ($dnsRecords as $record) {
        $formattedRecord = [
            'type' => strtoupper($record['type']),
            'name' => $record['host'],
            'ttl' => $record['ttl'] ?? 0,
            'class' => $record['class'] ?? 'IN'
        ];
        
        // Format based on record type
        switch (strtoupper($record['type'])) {
            case 'A':
                $formattedRecord['value'] = $record['ip'];
                break;
                
            case 'AAAA':
                $formattedRecord['value'] = $record['ipv6'];
                break;
                
            case 'MX':
                $formattedRecord['priority'] = $record['pri'];
                $formattedRecord['value'] = $record['target'];
                break;
                
            case 'CNAME':
                $formattedRecord['value'] = $record['target'];
                break;
                
            case 'TXT':
                $formattedRecord['value'] = is_array($record['txt']) ? implode('', $record['txt']) : $record['txt'];
                break;
                
            case 'NS':
                $formattedRecord['value'] = $record['target'] ?? $record['host'];
                $nsRecords[] = [
                    'server' => $formattedRecord['value'],
                    'type' => 'NS',
                    'ttl' => $formattedRecord['ttl']
                ];
                break;
                
            case 'SOA':
                $formattedRecord['value'] = sprintf(
                    'MNAME: %s, RNAME: %s, SERIAL: %d, REFRESH: %d, RETRY: %d, EXPIRE: %d, MINIMUM-TTL: %d',
                    $record['mname'] ?? '',
                    $record['rname'] ?? '',
                    $record['serial'] ?? 0,
                    $record['refresh'] ?? 0,
                    $record['retry'] ?? 0,
                    $record['expire'] ?? 0,
                    $record['minimum-ttl'] ?? 0
                );
                break;
                
            default:
                $formattedRecord['value'] = json_encode($record);
                break;
        }
        
        $formattedRecords[] = $formattedRecord;
    }
    
    // If no NS records found, try to get them directly
    if (empty($nsRecords)) {
        $nsRecordsRaw = @dns_get_record($cleanDomain, DNS_NS);
        if ($nsRecordsRaw !== false) {
            foreach ($nsRecordsRaw as $ns) {
                $nsRecords[] = [
                    'server' => $ns['target'],
                    'type' => 'NS',
                    'ttl' => $ns['ttl'] ?? 3600
                ];
            }
        }
    }
    
    return [
        'domain_checked' => $cleanDomain,
        'records' => $formattedRecords,
        'nameservers' => $nsRecords
    ];
}

// Function to get SSL certificate information
function getSslInfo($domain) {
    $original = parse_url($domain, PHP_URL_HOST) ?: $domain;
    $context = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    
    $client = @stream_socket_client("ssl://$original:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
    
    if (!$client) {
        return ['error' => 'SSL connection failed'];
    }
    
    $params = stream_context_get_params($client);
    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
    fclose($client);
    
    if (!$cert) {
        return ['error' => 'Failed to parse SSL certificate'];
    }
    
    $validFrom = date('Y-m-d H:i:s', $cert['validFrom_time_t']);
    $validTo = date('Y-m-d H:i:s', $cert['validTo_time_t']);
    $daysRemaining = floor(($cert['validTo_time_t'] - time()) / 86400);
    
    return [
        'valid' => $daysRemaining > 0,
        'issuer' => $cert['issuer']['O'] ?? 'Unknown',
        'subject' => $cert['subject']['CN'] ?? $original,
        'valid_from' => $validFrom,
        'valid_to' => $validTo,
        'days_remaining' => $daysRemaining,
        'signature_algorithm' => $cert['signatureTypeSN'] ?? 'Unknown',
        'key_size' => $cert['bits'] ?? 0
    ];
}

// Function to get WHOIS information
function getWhoisInfo($domain) {
    $whois = [];
    
    // In a production environment, you would use a WHOIS API service
    // This is a simplified version that works with some TLDs
    $whoisServer = 'whois.iana.org';
    $port = 43;
    
    $fp = @fsockopen($whoisServer, $port, $errno, $errstr, 10);
    
    if (!$fp) {
        return ['error' => 'WHOIS lookup failed'];
    }
    
    fwrite($fp, $domain . "\r\n");
    $response = '';
    while (!feof($fp)) {
        $response .= fgets($fp, 128);
    }
    fclose($fp);
    
    // Parse the response (simplified)
    $lines = explode("\n", $response);
    
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $key = strtolower(trim($key));
            $value = trim($value);
            
            if (!empty($value)) {
                switch ($key) {
                    case 'creation date':
                        $whois['created_date'] = $value;
                        break;
                    case 'updated date':
                        $whois['updated_date'] = $value;
                        break;
                    case 'expiry date':
                    case 'registry expiry date':
                        $whois['expiry_date'] = $value;
                        break;
                    case 'registrar':
                        $whois['registrar'] = $value;
                        break;
                    case 'name server':
                        $whois['name_servers'][] = strtolower($value);
                        break;
                }
            }
        }
    }
    
    return $whois;
}

try {
    // Get all domain information
    $domainInfo = [
        'domain' => $domain,
        'speed' => checkDomainSpeed($domain),
        'uptime' => checkUptime($domain),
        'traffic' => getTrafficData($domain),
        'dns' => getDnsInfo($domain),
        'ssl' => getSslInfo($domain),
        'whois' => getWhoisInfo($domain),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $domainInfo
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching domain information',
        'error' => $e->getMessage()
    ]);
}
