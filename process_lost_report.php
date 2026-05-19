<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $report_id = $_POST['report_id'] ?? null;
    $action = $_POST['action'] ?? '';

    if (!$report_id) {
        throw new Exception("Missing Report ID");
    }

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE lost_reports SET status = 'Approved' WHERE id = ?");
        $stmt->execute([$report_id]);
        echo json_encode(['status' => 'success']);
    } 
    elseif ($action === 'mark_found') {
        $rescued_pet_id = $_POST['rescued_pet_id'] ?? null;
        
        // 1. Update Report Status to Found
        $stmt = $pdo->prepare("UPDATE lost_reports SET status = 'Found' WHERE id = ?");
        $stmt->execute([$report_id]);

        // 2. Fetch Report Details
        $stmt_info = $pdo->prepare("SELECT user_id, animal_type FROM lost_reports WHERE id = ?");
        $stmt_info->execute([$report_id]);
        $report = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if ($report) {
            $user_id = $report['user_id'];
            $animal = $report['animal_type'];
            // Personalize message but keep it clean for UI
            $msg = "GREAT NEWS! We found a match for your lost $animal. Please review the photo below to confirm if this is your pet.";
            
            // 3. Insert Notification for the Resident (Including rescued_pet_id)
            // Ensure your notifications table has a 'rescued_pet_id' column
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, message, rescued_pet_id, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            $notif->execute([$user_id, $msg, $rescued_pet_id]);
        }

        echo json_encode(['status' => 'success']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>