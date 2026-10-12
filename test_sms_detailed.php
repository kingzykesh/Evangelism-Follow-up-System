<?php
require_once 'config.php';
require_once 'functions.php';

echo "<h2>🔍 Detailed eBulkSMS Integration Test</h2>";

// Test credentials
$api_username = "watchmanmediaph@gmail.com";
$api_key = "930afe551766ac56ebf4372df64dcd7360a45fec8d0bf880fbbb7a73b7a53873";

echo "<h3>📋 Credentials Check:</h3>";
echo "<p>Username: " . $api_username . "</p>";
echo "<p>API Key: " . substr($api_key, 0, 10) . "..." . "</p>";

// Test phone number (replace with your actual test number)
$test_phone = "+2348100163853"; // Replace with a real number you can check
$test_message = "Test SMS from EvangelismTracker - " . date('Y-m-d H:i:s');

echo "<h3>📱 SMS Test Details:</h3>";
echo "<p>Phone: " . $test_phone . "</p>";
echo "<p>Message: " . $test_message . "</p>";

// Test 1: Check phone number cleaning
echo "<h3>🧹 Phone Number Cleaning:</h3>";
$cleaned_phone = clean_phone_number($test_phone);
echo "<p>Original: " . $test_phone . "</p>";
echo "<p>Cleaned: " . $cleaned_phone . "</p>";
echo "<p>Valid: " . (validate_phone_number($cleaned_phone) ? '✅ Yes' : '❌ No') . "</p>";

// Test 2: Check SMS balance
echo "<h3>💰 SMS Balance Check:</h3>";
$balance = get_sms_balance();
if ($balance !== null) {
    echo "<p style='color: green;'>✅ Balance: " . $balance . " units</p>";
} else {
    echo "<p style='color: red;'>❌ Unable to check balance - check credentials</p>";
}

// Test 3: Send actual SMS
echo "<h3>📤 Sending Test SMS:</h3>";
if ($balance > 0) {
    $result = send_sms($test_phone, $test_message);
    
    if ($result) {
        echo "<p style='color: green; font-weight: bold;'>✅ SMS sent successfully!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ SMS failed to send</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Insufficient balance to test SMS sending</p>";
}

// Test 4: Check SMS logs
echo "<h3>📊 Recent SMS Logs:</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 10");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "<p>No SMS logs found</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'>
                <th>Phone</th>
                <th>Message Preview</th>
                <th>Status</th>
                <th>API Response</th>
                <th>Time</th>
              </tr>";
        foreach ($logs as $log) {
            $status_color = $log['status'] === 'sent' ? 'green' : ($log['status'] === 'failed' ? 'red' : 'orange');
            echo "<tr>";
            echo "<td>{$log['recipient_phone']}</td>";
            echo "<td>" . substr($log['message'], 0, 30) . "...</td>";
            echo "<td style='color: {$status_color}; font-weight: bold;'>{$log['status']}</td>";
            echo "<td style='font-size: 12px;'>{$log['api_response']}</td>";
            echo "<td>{$log['sent_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error reading logs: " . $e->getMessage() . "</p>";
}

// Test 5: Direct API call test
echo "<h3>🔧 Direct API Test:</h3>";
test_direct_api_call();

function test_direct_api_call() {
    $api_username = "watchmanmediaph@gmail.com";
    $api_key = "930afe551766ac56ebf4372df64dcd7360a45fec8d0bf880fbbb7a73b7a53873";
    
    // Test balance endpoint directly
    $balance_data = [
        'SMS' => [
            'auth' => [
                'username' => $api_username,
                'apikey' => $api_key
            ]
        ]
    ];
    
    $json_data = json_encode($balance_data);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.ebulksms.com:8080/balance.json");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_data)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo "<p>HTTP Status: " . $http_code . "</p>";
    echo "<p>Response: " . htmlspecialchars($response) . "</p>";
    if ($curl_error) {
        echo "<p style='color: red;'>cURL Error: " . $curl_error . "</p>";
    }
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        echo "<p>Decoded Response: <pre>" . print_r($data, true) . "</pre></p>";
    }
}
?>