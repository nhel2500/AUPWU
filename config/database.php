<?php
/**
 * Database connection configuration
 * 
 * This file contains the database connection parameters and establishes
 * a connection to the MySQL database using PDO
 */

// Database connection parameters for XAMPP
$host = 'localhost';
$db_name = 'aupwu_db';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// DSN (Data Source Name) for PDO connection
$dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";

// PDO options for error handling and prepared statements
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// Try to establish the database connection
try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Log the error and display a user-friendly message
    error_log('Database Connection Error: ' . $e->getMessage());
    die('Database connection failed. Please contact the system administrator.');
}
?>
