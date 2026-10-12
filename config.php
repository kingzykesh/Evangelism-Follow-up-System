<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'sdb-e.hosting.stackcp.net';
$dbname = 'envangelism-313833d278';
$username = 'envangelism-313833d278';
$password = 'q1oa30y393';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Log error but don't show to user
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Create basic tables if they don't exist
$tables_sql = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        matric_number VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(100) NOT NULL,
        level VARCHAR(20),
        department VARCHAR(100),
        course VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        role ENUM('super_admin', 'coordinator', 'worker', 'prayer_team') DEFAULT 'worker',
        password VARCHAR(255) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS converts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        worker_id INT,
        name VARCHAR(100) NOT NULL,
        phone_number VARCHAR(20),
        room_number VARCHAR(20),
        hostel VARCHAR(100),
        gender ENUM('male', 'female'),
        status ENUM('pending', 'converted') DEFAULT 'pending',
        date_submitted TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
];

foreach ($tables_sql as $sql) {
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
    }
}

// Create emergency super admin if not exists
try {
    $check = $pdo->prepare("SELECT id FROM users WHERE matric_number = 'SUPER001'");
    $check->execute();
    
    if ($check->rowCount() == 0) {
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (matric_number, name, role, password) VALUES (?, ?, ?, ?)");
        $stmt->execute(['SUPER001', 'Super Admin', 'super_admin', $password_hash]);
    }
} catch (PDOException $e) {
    error_log("Admin creation error: " . $e->getMessage());
}

// Helper function
//function sanitize_input($data) {
  //  if (empty($data)) return $data;
    //return htmlspecialchars(strip_tags(trim($data)));
//}
?>