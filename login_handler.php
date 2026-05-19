<?php
session_start();
header('Content-Type: application/json');

// Get the JSON data from the fetch request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$email = $data['email'];
$password = $data['password'];
$selectedRole = $data['role']; // This will be "Admin" or "Resident" from your <select>

// Database connection
$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password_db = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    
    // 1. Find the user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        
        // 2. Check if account is pending (Only for Residents usually)
        if ($user['status'] === 'pending' && $user['role'] !== 'admin') {
            echo json_encode(['status' => 'pending']);
            exit;
        }

        // 3. SET THE SESSION (Crucial for admin_dashboard.php)
        $_SESSION['user_id'] = $user['id'];
        
        // We convert to lowercase to make the dashboard check easier
        $role = strtolower($user['role']); 
        $_SESSION['role'] = $role; 

        // 4. Send the correct redirect path back to JS
        if ($role === 'admin') {
            echo json_encode([
                'status' => 'success', 
                'redirect' => 'admin_dashboard.php'
            ]);
        } else {
            echo json_encode([
                'status' => 'success', 
                'redirect' => 'resident_dashboard.php'
            ]);
        }
        exit;

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}