<?php
/**
 * TechTalent Solutions - Database Connection Configuration
 * 
 * Configures PHP PDO MySQL connection with strict error handling
 * and default associative fetch mode.
 */

$host    = 'localhost';
$db      = 'techtalent_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database Connection Error: Could not connect to the database '{$db}'. Please check your MySQL server settings.");
}
