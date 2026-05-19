<?php
ob_start(); // <-- FIX: Forces PHP to buffer output and prevents header conflicts
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
    SELECT *
    FROM adoption_requests
    WHERE id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header("Location: manage_adoptions.php");
    exit();
}

/* APPROVE SELECTED REQUEST */
$stmt = $pdo->prepare("
    UPDATE adoption_requests
    SET status = 'approved'
    WHERE id = ?
");
$stmt->execute([$request_id]);

/* OPTIONAL: Reject other requests for same pet */
$stmt = $pdo->prepare("
    UPDATE adoption_requests
    SET status = 'rejected'
    WHERE pet_id = ?
    AND id != ?
");
$stmt->execute([
    $request['pet_id'],
    $request_id
]);

/* CREATE NOTIFICATION */
$message = "🎉 Your adoption request has been APPROVED! Please go to your dashboard to set up your pickup schedule appointment details.";

$stmt = $pdo->prepare("
    INSERT INTO notifications (user_id, message, is_read, created_at)
    VALUES (?, ?, 0, NOW())
");

$stmt->execute([
    $request['resident_id'],
    $message
]);

/* =====================================================================
   CORE WORKFLOW FIX: POPULATE ADOPTION_PICKUPS SYSTEM AUTOMATICALLY
   ===================================================================== */
// Verify whether a handover sequence block is already active to protect against duplicates
$checkPickup = $pdo->prepare("SELECT id FROM adoption_pickups WHERE request_id = ?");
$checkPickup->execute([$request_id]);

if (!$checkPickup->fetch()) {
    // Generate a secure, trackable default coupon confirmation string
    $coupon_code = 'PET-' . strtoupper(substr(md5(time() . rand()), 0, 10));

    // Establish a baseline queue row entry using safe fallback values
    // This allows it to instantly populate on the Admin's Approved Pickups list
    $insertPickup = $pdo->prepare("
        INSERT INTO adoption_pickups (
            request_id, 
            resident_id, 
            pet_id, 
            pickup_date, 
            pickup_time, 
            coupon_code, 
            pickup_status, 
            created_at
        ) VALUES (?, ?, ?, CURDATE(), '10:00:00', ?, 'scheduled', CURRENT_TIMESTAMP)
    ");
    
    $insertPickup->execute([
        $request_id,
        $request['resident_id'],
        $request['pet_id'],
        $coupon_code
    ]);
}
/* ===================================================================== */

/* IMPORTANT FIX: DO NOT FILTER ON REDIRECT */
header("Location: manage_adoptions.php");
ob_end_flush(); // <-- FIX: Safely sends out the final header redirect command cleanly
exit();
?>