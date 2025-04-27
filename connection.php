<?php
// Database configuration
$host = 'localhost';     // Database host
$dbname = 'bbms';       // Database name
$username = 'root';     // Database username
$password = '';         // Database password for XAMPP default

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Create a new PDO instance
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
} catch(PDOException $e) {
    // Log the error and show a user-friendly message
    error_log("Connection failed: " . $e->getMessage());
    die("Sorry, there was a problem connecting to the database. Please try again later.");
}
?>