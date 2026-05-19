<?php
session_start();

// 1. SECURITY CHECK: Ensure only an admin can run this script
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. VALIDATION: Check if the email is provided in the URL
if (!isset($_GET['email']) || empty($_GET['email'])) {
    header("Location: admin_dashboard.php?error=no_email");
    exit();
}

$userEmail = $_GET['email'];

// 3. DATABASE CONNECTION
$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. UPDATE STATUS: Change 'pending' to 'approved'
    // We use a prepared statement to prevent SQL injection
    $sql = "UPDATE users SET status = 'approved' WHERE email = :email AND status = 'pending'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $userEmail);
    
    if ($stmt->execute()) {
        // Check if a row was actually updated (in case the email didn't exist or was already approved)
        if ($stmt->rowCount() > 0) {
            header("Location: admin_dashboard.php?msg=approved");
        } else {
            header("Location: admin_dashboard.php?msg=no_change");
        }
    } else {
        header("Location: admin_dashboard.php?error=failed");
    }

} catch(PDOException $e) {
    // In a production environment, log this error instead of dying
    die("Error updating record: " . $e->getMessage());
}
exit();