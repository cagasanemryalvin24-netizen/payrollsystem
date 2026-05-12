<?php
// database.php – PDO connection (required for transaction management)
$host = "localhost";
$user = "root";
$pass = "";
$db   = "payroll_db";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new RuntimeException("mysqli: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("<p style='color:red;font-family:monospace'>DB Error: " . htmlspecialchars($e->getMessage()) . "</p>");
}
?>
