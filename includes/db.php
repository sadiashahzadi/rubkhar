<?php
// Database configuration
$host = 'localhost';
$dbname = 'rubkhar_db';
$username = 'root'; // Replace with your database username
$password = '';     // Replace with your database password

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // In a production environment, you might want to log this error instead of displaying it
    die("Database connection failed: " . $e->getMessage());
}
?>
