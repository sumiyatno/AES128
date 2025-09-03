<?php

// Test File Manager API
session_start();
$_SESSION['user_id'] = 2; // Set user ID for testing
$_SESSION['user_level'] = 2;

echo "=== TESTING FILE MANAGER API ===\n\n";

// Function to test API endpoint
function testAPI($endpoint, $params = [], $method = 'GET') {
    $url = 'http://localhost/AES128/api/file_manager_api.php?' . $endpoint;
    
    $context = [
        'http' => [
            'method' => $method,
            'header' => [
                'Content-Type: application/x-www-form-urlencoded',
                'Cookie: ' . session_name() . '=' . session_id()
            ]
        ]
    ];
    
    if ($method === 'POST' && !empty($params)) {
        $context['http']['content'] = http_build_query($params);
    }
    
    $result = file_get_contents($url, false, stream_context_create($context));
    
    if ($result === false) {
        return ['error' => 'Failed to fetch URL'];
    }
    
    $json = json_decode($result, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => 'Invalid JSON response',
            'raw_response' => $result,
            'json_error' => json_last_error_msg()
        ];
    }
    
    return $json;
}

// Test 1: Get my files
echo "1. Testing get_my_files...\n";
$result = testAPI('action=get_my_files&page=1&limit=5');
echo "Response: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Test 2: Get labels
echo "2. Testing get_labels...\n";
$result = testAPI('action=get_labels');
echo "Response: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Test 3: Get access levels
echo "3. Testing get_access_levels...\n";
$result = testAPI('action=get_access_levels');
echo "Response: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Test 4: Get stats
echo "4. Testing get_stats...\n";
$result = testAPI('action=get_stats');
echo "Response: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== API TESTING COMPLETE ===\n";
?>