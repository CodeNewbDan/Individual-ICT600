<?php
// Database configuration for XAMPP
$host = 'localhost';
$db_name = 'my_db'; // Change this to your actual database name in phpMyAdmin
$username = 'root';               // Default XAMPP MySQL username
$password = '';                   // Default XAMPP MySQL password is empty

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);

    // Set error mode to exception so database errors are easy to catch and debug
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Turn off emulated prepared statements to use real prepared statements
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Uncomment the line below if you want to test the connection directly in your browser:
    // echo "Connected successfully!";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
