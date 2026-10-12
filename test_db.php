<?php
// Simple database test
$host = 'sdb-e.hosting.stackcp.net';
$dbname = 'envangelism-313833d278';
$username = 'envangelism-313833d278';
$password = 'q1oa30y393';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully!<br>";
    
    // Test if users table exists
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() > 0) {
        echo "✅ Users table exists!<br>";
        
        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];
        echo "✅ Users in database: " . $count . "<br>";
    } else {
        echo "❌ Users table doesn't exist!<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}
?>