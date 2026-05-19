<?php
session_start();
header('Content-Type: application/json');

// Resident protection
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $notif_id = $_POST['notification_id'] ?? null;
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['user_name'] ?? 'A resident';

    if (!$notif_id) throw new Exception("Missing Notification ID");

    // 1. Verify notification belongs to user
    $stmt = $pdo->prepare("SELECT message FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    $notif = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notif) throw new Exception("Notification record not found.");

    /*
    ============================================
    2. UPDATE LOST REPORT STATUS TO 'Pet Found'
    ============================================
    */
    // When the user clicks YES, we finalize the report status.
    $pickup_date = $_POST['pickup_date'] ?? null;
    $pickup_time = $_POST['pickup_time'] ?? null;

    if (empty($pickup_date) || empty($pickup_time)) {
        throw new Exception("Pickup date and time are required.");
    }

    $updateReport = $pdo->prepare("
        UPDATE lost_reports 
        SET status = 'Pet Found' 
        WHERE user_id = ? AND status = 'Found'
        ORDER BY date_submitted DESC LIMIT 1
    ");
    $updateReport->execute([$user_id]);

    // 3. Notify ALL Admins that the pet has been identified
    $admin_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($admins) {
        $admin_msg = "URGENT IDENTIFICATION: $user_name confirmed that the rescued pet matches their lost report! Status updated to 'Pet Found'. (Ref Notif: #$notif_id)";
        
        $insert = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
        foreach($admins as $admin) {
            $insert->execute([$admin['id'], $admin_msg]);
        }
    }

    // 4. Mark resident's notification as confirmed and save the pickup schedule
    $coupon_code = 'CLAIM-' . strtoupper(substr(md5(time() . rand()), 0, 10));
    $update = $pdo->prepare("UPDATE notifications SET is_read = 1, message = CONCAT(message, ' [CONFIRMED]'), pickup_date = :pickup_date, pickup_time = :pickup_time, coupon_code = :coupon_code, is_scheduled = 1 WHERE id = :id");
    $update->execute([
        'pickup_date' => $pickup_date,
        'pickup_time' => $pickup_time,
        'coupon_code' => $coupon_code,
        'id' => $notif_id
    ]);

    require_once __DIR__ . '/pickup_sync.php';
    syncClaimToAdoptionPickups($pdo, (int) $notif_id, 'scheduled');

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>