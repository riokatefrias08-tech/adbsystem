<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;
$status = $data['status'] ?? '';

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Update the Stray Report Status
    $stmt = $pdo->prepare("UPDATE stray_reports SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    // 2. If APPROVED, check for matches in LOST_REPORTS to notify residents
    if ($status === 'Approved') {
        // Fetch current stray pet details
        $stmt = $pdo->prepare("SELECT animal_type, location FROM stray_reports WHERE id = ?");
        $stmt->execute([$id]);
        $stray = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stray) {
            $type = $stray['animal_type'];
            $loc = $stray['location'];

            // Find matching lost reports (Simple match by animal type)
            // You can make this more complex by adding location matching
            $matchStmt = $pdo->prepare("SELECT user_id FROM lost_reports WHERE animal_type LIKE ? AND status = 'pending'");
            $matchStmt->execute(["%$type%"]);
            $lostReports = $matchStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lostReports as $report) {
                $msg = "GOOD NEWS: A stray " . $type . " was recently rescued/approved by the Admin near " . $loc . ". This might be your lost pet! Please check the dashboard.";
                
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
                $notif->execute([$report['user_id'], $msg]);
            }
        }
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}