<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $notif_id = $_POST['notification_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if (!$notif_id) {
        throw new Exception("Missing Notification ID");
    }

    /*
    ============================================
    1. GET NOTIFICATION + RESCUED PET
    ============================================
    */

    $stmt = $pdo->prepare("
        SELECT rescued_pet_id
        FROM notifications
        WHERE id = ?
        AND user_id = ?
    ");

    $stmt->execute([$notif_id, $user_id]);

    $notif = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notif) {
        throw new Exception("Notification not found.");
    }

    $rescued_pet_id = $notif['rescued_pet_id'];

    /*
    ============================================
    2. MARK LOST REPORT AS NOT FOUND
    ============================================
    */

    if ($rescued_pet_id) {

        $updateReport = $pdo->prepare("
            UPDATE lost_reports
            SET status = 'Not Found'
            WHERE user_id = ?
            AND status = 'Found'
        ");

        $updateReport->execute([$user_id]);
    }

    /*
    ============================================
    3. UPDATE NOTIFICATION
    ============================================
    */

    $updateNotif = $pdo->prepare("
        UPDATE notifications
        SET 
            message = CONCAT(message, ' [NOT MATCHED]'),
            is_read = 1
        WHERE id = ?
    ");

    $updateNotif->execute([$notif_id]);

    echo json_encode([
        'success' => true
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>