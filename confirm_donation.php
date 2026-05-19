<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pending_donation'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No pending donation found',
    ]);
    exit();
}

$data = $_SESSION['pending_donation'];

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=adbsystemm;charset=utf8mb4',
        'root',
        ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    require_once __DIR__ . '/donation_helpers.php';
    ensureDonationPetColumns($pdo);

    $user_id = (int) $_SESSION['user_id'];

    $stmtUser = $pdo->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $stmtUser->execute([$user_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    $donor_name = trim($user['first_name'] . ' ' . $user['last_name']);
    $pet_id = !empty($data['pet_id']) ? (int) $data['pet_id'] : null;
    $pet_name = !empty($data['pet_name']) ? trim($data['pet_name']) : null;

    $stmt = $pdo->prepare('
        INSERT INTO donations (
            user_id,
            pet_id,
            pet_name,
            donor_name,
            donation_type,
            amount,
            message,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ');

    $stmt->execute([
        $user_id,
        $pet_id,
        $pet_name,
        $donor_name,
        $data['donation_type'],
        $data['amount'] ?? 0,
        $data['message'] ?? '',
    ]);

    $donation_id = (int) $pdo->lastInsertId();
    $receipt_number = 'RCPT-' . date('Y') . '-' . str_pad((string) $donation_id, 6, '0', STR_PAD_LEFT);

    $update = $pdo->prepare('UPDATE donations SET receipt_number = ? WHERE id = ?');
    $update->execute([$receipt_number, $donation_id]);

    unset($_SESSION['pending_donation']);

    echo json_encode([
        'success' => true,
        'receipt' => $receipt_number,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
