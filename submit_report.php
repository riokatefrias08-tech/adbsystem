<?php
session_start();

// --- SECURITY CHECK ---
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!isset($_SESSION['user_id'])) {
            die("Error: User session not found.");
        }

        $user_id = (int) $_SESSION['user_id'];

        $report_type = $_POST['report_type'] ?? '';
        $animal_type = $_POST['animal_type'] ?? '';
        $breed = trim($_POST['breed'] ?? '');
        $description = $_POST['description'] ?? '';
        $location    = $_POST['location'] ?? '';
        $health_status = $_POST['health_status'] ?? 'Unknown';
        $file_name   = '';

        // Handle Image Upload
        if (isset($_FILES['pet_image']) && $_FILES['pet_image']['error'] === 0) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

            $raw_file_name = $_FILES["pet_image"]["name"];
            $file_extension = strtolower(pathinfo($raw_file_name, PATHINFO_EXTENSION));
            $file_name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
            move_uploaded_file($_FILES["pet_image"]["tmp_name"], $target_dir . $file_name);
        }

        // --- DATABASE INSERTION ---
        $table = ($report_type === 'stray') ? 'stray_reports' : 'lost_reports';
        
       $sql = "INSERT INTO $table 
(user_id, animal_type, description, location, health_status, image, status, date_submitted) 
VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $animal_type, $description, $location, $health_status, $file_name]);


       
        // --- NOTIFICATION & MATCHING LOGIC ---
        // Kung ang Resident nag post og LOST PET, i-check nato kung naay kaparehas sa STRAY/RESCUED reports
        if ($report_type === 'lost') {
            $checkMatch = $pdo->prepare("SELECT id FROM stray_reports WHERE animal_type LIKE ? AND (location LIKE ? OR description LIKE ?) LIMIT 5");
            $checkMatch->execute(["%$animal_type%", "%$location%", "%$description%"]);
            $matches = $checkMatch->fetchAll();

            if ($matches) {
                $msg = "Naay " . count($matches) . " ka rescued pets nga naay similarities sa imong nawala nga $animal_type sa $location. Palihog susiha ang imong dashboard.";
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif->execute([$user_id, $msg]);
            }
        }

        header("Location: resident_dashboard.php?status=success");
        exit();
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>