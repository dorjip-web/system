<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "attendance_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log error but do not echo to page (prevents header issues)
    error_log('DB connection failed: ' . $e->getMessage());
    $conn = null;
}
?>