<?php
$host = 'localhost';
$db   = 'ecommerce_core';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Force MySQL session to use Manila timezone (matches PHP)
    $pdo->exec("SET time_zone = '+08:00'");
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}