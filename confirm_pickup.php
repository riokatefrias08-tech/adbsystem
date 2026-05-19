<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pdo = new PDO("mysql:host=localhost;dbname=adbsystemm;charset=utf8mb4", "root", "");

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    UPDATE adoption_requests
    SET pickup_status = 'claimed'
    WHERE id = ?
");

$stmt->execute([$id]);

header("Location: manage_adoptions.php?pickup=done");
exit();
?>