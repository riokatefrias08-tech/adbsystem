<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pdo = new PDO(
    "mysql:host=localhost;dbname=adbsystemm;charset=utf8mb4",
    "root",
    ""
);

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$request_id = (int)($_GET['id'] ?? 0);

if ($request_id <= 0) {
    header("Location: manage_adoptions.php");
    exit();
}

/* GET REQUEST */
$stmt = $pdo->prepare("
    SELECT resident_id
    FROM adoption_requests
    WHERE id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header("Location: manage_adoptions.php");
    exit();
}

/* UPDATE STATUS */
$stmt = $pdo->prepare("
    UPDATE adoption_requests
    SET status = 'rejected'
    WHERE id = ?
");
$stmt->execute([$request_id]);

/* NOTIFICATION */
$message = "❌ Your adoption request has been REJECTED.";

$stmt = $pdo->prepare("
    INSERT INTO notifications (user_id, message, is_read, created_at)
    VALUES (?, ?, 0, NOW())
");

$stmt->execute([
    $request['resident_id'],
    $message
]);

/* IMPORTANT FIX: DO NOT FILTER ON REDIRECT */
header("Location: manage_adoptions.php");
exit();
?>