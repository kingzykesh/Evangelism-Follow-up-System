<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Debug page loaded successfully!<br>";

// Test database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=evangelism_system", "username", "password");
    echo "Database connected!<br>";
    
    // Check if super admin exists
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'super_admin'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Super admins found: " . count($users) . "<br>";
    foreach($users as $user) {
        echo "Matric: " . $user['matric_number'] . " - Name: " . $user['name'] . "<br>";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// Test password verification
$test_password = "123456";
$test_hash = password_hash($test_password, PASSWORD_DEFAULT);
echo "Password test: " . (password_verify($test_password, $test_hash) ? "Working" : "Failed");
?>