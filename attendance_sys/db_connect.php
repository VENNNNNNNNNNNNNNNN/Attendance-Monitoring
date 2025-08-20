<?php
// db_connect.php
$host = "localhost";   // usually localhost sa XAMPP
$db   = "secure_login"; // palitan mo sa pangalan ng database mo
$user = "root";        // default sa XAMPP
$pass = "";            // default walang password sa XAMPP

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}
