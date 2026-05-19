<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$status = strtolower(trim((string)($_GET['status'] ?? '')));

if ($id <= 0 || !in_array($status, ['approved', 'rejected'], true)) {
    header("Location: manage_adoptions.php");
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("UPDATE adoption_requests SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    header("Location: manage_adoptions.php");
    exit();
} catch (PDOException $e) {
    header("Location: manage_adoptions.php");
    exit();
}
?>