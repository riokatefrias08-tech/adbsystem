<?php
session_start();

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
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

    $request_id = $_POST['request_id'] ?? 0;
    $resident_id = $_SESSION['user_id'];
    $pet_id = $_POST['pet_id'] ?? 0;
    $pickup_date = $_POST['pickup_date'] ?? '';
    $pickup_time = $_POST['pickup_time'] ?? '';

    $coupon_code = 'PET-' . strtoupper(substr(md5(time() . rand()), 0, 10));

    // Check if pickup already exists for this request
    $check = $pdo->prepare("SELECT id, coupon_code FROM adoption_pickups WHERE request_id = ?");
    $check->execute([$request_id]);
    $existingPickup = $check->fetch(PDO::FETCH_ASSOC);

    if ($existingPickup) {
        // UPDATE existing pickup
        $stmt = $pdo->prepare("
            UPDATE adoption_pickups
            SET pickup_date = ?, pickup_time = ?, updated_at = CURRENT_TIMESTAMP
            WHERE request_id = ?
        ");
        $stmt->execute([
            $pickup_date,
            $pickup_time,
            $request_id
        ]);
    } else {
        // INSERT new pickup
        $stmt = $pdo->prepare("
            INSERT INTO adoption_pickups
            (request_id, resident_id, pet_id, pickup_date, pickup_time, coupon_code)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $request_id,
            $resident_id,
            $pet_id,
            $pickup_date,
            $pickup_time,
            $coupon_code
        ]);
    }

    header("Location: resident_dashboard.php?pickup=success");
    exit();

} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>