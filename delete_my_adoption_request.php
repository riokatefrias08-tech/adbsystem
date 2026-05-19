<?php
session_start();

if (!isset($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}

$residentId = (int)($_SESSION['user_id'] ?? 0);
$requestId = (int)($_POST['request_id'] ?? 0);

if ($residentId <= 0 || $requestId <= 0) {
    header("Location: resident_dashboard.php?adoption=error");
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        DELETE FROM adoption_requests
        WHERE id = ?
          AND resident_id = ?
          AND status IN ('pending', 'rejected')
    ");
    $stmt->execute([$requestId, $residentId]);

    header("Location: resident_dashboard.php?adoption=deleted");
    exit();
} catch (PDOException $e) {
    header("Location: resident_dashboard.php?adoption=error");
    exit();
}
?>