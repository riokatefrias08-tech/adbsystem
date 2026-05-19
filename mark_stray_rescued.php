<?php
// Start session immediately with no leading spaces before the opening tag
session_start();
header('Content-Type: application/json');

// Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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

    $report_id = $_POST['report_id'] ?? null;

    if (!$report_id) {
        throw new Exception("Missing Report ID");
    }

    // 1. Fetch the Stray Report details
    $stmt = $pdo->prepare("SELECT * FROM stray_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        throw new Exception("Report not found.");
    }

    // 2. INSERT into rescued_pets table
    $sqlInsert = "INSERT INTO rescued_pets 
    (name, breed, location_seen, health_status, image_path, created_at) 
    VALUES (?, ?, ?, ?, ?, NOW())";

    $insert = $pdo->prepare($sqlInsert);

    // Fixed variable assignments to prevent "Undefined Variable" database crash
    $petName = $report['animal_type'];
    $breed = "Unknown"; // Initialized variable since $breed was used but never defined
    $locationSeen = $report['location'];
    $healthStatus = $report['health_status'];
    $imagePath = "uploads/" . $report['image']; // Fixed path prefix to match your frontend image loader

    $insert->execute([
        $petName,
        $breed,
        $locationSeen,
        $healthStatus,
        $imagePath
    ]);
    
    $newPetId = $pdo->lastInsertId();

    // 3. Update Stray Report status to 'Rescued'
    $update = $pdo->prepare("UPDATE stray_reports SET status = 'Rescued' WHERE id = ?");
    $update->execute([$report_id]);

    // --- NOTIFICATION LOGIC ---

    // A. Notify the reporter if they are a registered resident
    if (!empty($report['user_id'])) {
        $msgReporter = "📢 Great news! The {$report['animal_type']} you reported at {$report['location']} has been successfully rescued.";
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
        $stmtNotif->execute([$report['user_id'], $msgReporter]);
    }

    // B. Notify ALL residents that a new pet is available for adoption
    $msgGeneral = "🐾 New Rescued Pet Alert! A {$report['animal_type']} is now available for adoption.";
    $stmtResidents = $pdo->query("SELECT id FROM users WHERE role = 'resident'");
    $residents = $stmtResidents->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtNotifAll = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at, is_read) VALUES (?, ?, NOW(), 0)");
    foreach ($residents as $res) {
        // Don't notify twice if this person is also the reporter
        if (!empty($report['user_id']) && $res['id'] == $report['user_id']) {
            continue;
        }
        $stmtNotifAll->execute([$res['id'], $msgGeneral]);
    }

    echo json_encode(['success' => true, 'message' => 'Pet successfully marked as rescued and notifications sent.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>