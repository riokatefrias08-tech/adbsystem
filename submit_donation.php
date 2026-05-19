<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$donation_type = trim($_POST['donation_type'] ?? '');
$amount = (float) ($_POST['amount'] ?? 0);
$message = trim($_POST['message'] ?? '');
$pet_id = (int) ($_POST['pet_id'] ?? 0);
$pet_name = trim($_POST['pet_name'] ?? '');

$allowedTypes = ['dog_food', 'cat_food', 'vitamins', 'supplies', 'money'];
if (!in_array($donation_type, $allowedTypes, true)) {
    header('Location: resident_dashboard.php?donation=error');
    exit();
}

if ($donation_type !== 'money') {
    $amount = 0;
} elseif ($amount < 1) {
    header('Location: resident_dashboard.php?donation=error');
    exit();
}

if ($pet_id > 0 && $pet_name === '') {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=adbsystemm;charset=utf8mb4', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare('SELECT pet_type FROM rescued_pets WHERE id = ? LIMIT 1');
        $stmt->execute([$pet_id]);
        $pet_name = (string) ($stmt->fetchColumn() ?: 'Rescued pet');
    } catch (PDOException $e) {
        $pet_name = 'Rescued pet';
    }
}

$_SESSION['pending_donation'] = [
    'donation_type' => $donation_type,
    'amount' => $amount,
    'message' => $message,
    'pet_id' => $pet_id > 0 ? $pet_id : null,
    'pet_name' => $pet_id > 0 ? $pet_name : null,
];

header('Location: donation_receipt.php');
exit();
