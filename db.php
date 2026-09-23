<?php
$host = 'localhost';
$port = '3306';
$db   = 'eictdb';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db, (int) $port);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>