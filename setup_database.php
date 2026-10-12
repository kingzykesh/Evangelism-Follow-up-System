<?php
/**
 * Database Setup Script
 * Run this once to create the database and tables
 */

// Database configuration
$host = 'sdb-e.hosting.stackcp.net';
$dbname = 'envangelism-313833d278';
$username = 'envangelism-313833d278';
$password = 'q1oa30y393';



try {
    // Create database connection
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");

    echo "✅ Database created successfully!\n";

    // Read and execute the schema SQL
    $schema_sql = file_get_contents('database_schema.sql');
    $pdo->exec($schema_sql);

    echo "✅ Tables created successfully!\n";
    echo "✅ Sample data inserted!\n";
    echo "✅ Database views created!\n\n";

    echo "🎉 Setup completed! You can now:\n";
    echo "1. Access the system at: http://your-domain/\n";
    echo "2. Login with: Matric: SUPER001, Password: password\n";
    echo "3. Start managing your evangelism activities!\n";

} catch(PDOException $e) {
    die("❌ Database setup failed: " . $e->getMessage());
}
?>