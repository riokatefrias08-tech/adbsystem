<?php
header('Content-Type: application/json');

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Using $_POST because frontend is now sending FormData for file support
    $fname           = $_POST['fname'] ?? '';
    $lname           = $_POST['lname'] ?? '';
    $email           = $_POST['email'] ?? '';
    $phone           = $_POST['phone'] ?? '';
    $purok           = $_POST['purok'] ?? '';
    $address         = $_POST['address'] ?? '';
    $residency_years = $_POST['residency_years'] ?? 0;
    $pass            = $_POST['password'] ?? '';
    $role            = 'Resident'; // Defaulting to resident for this signup

    // 1. Basic Validation
    if (empty($email) || empty($pass) || empty($address)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // 2. Check if email already exists
    $stmt = $pdo->prepare("SELECT password, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        if ($existingUser['status'] === 'pending') {
            if (password_verify($pass, $existingUser['password'])) {
                echo json_encode(['status' => 'exists']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Email already registered. Check your password.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Account is already active. Please log in.']);
        }
        exit;
    }

    // 3. Handle File Upload (Verification ID)
    $uploadDir = 'uploads/verification_ids/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = null;
    if (isset($_FILES['proof_id']) && $_FILES['proof_id']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['proof_id']['tmp_name'];
        $originalName = $_FILES['proof_id']['name'];
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Create a unique name to prevent overwriting
        $newFileName = uniqid('verify_', true) . '.' . $fileExtension;
        $dest_path = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $fileName = $newFileName;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Proof of residency file is required.']);
        exit;
    }

    // 4. Insert into Database
    $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
    
    // Ensure your 'users' table has columns: purok, address, residency_years, verification_file
    $sql = "INSERT INTO users (
                first_name, last_name, email, phone, 
                purok, address, residency_years, 
                verification_file, role, password, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $insert = $pdo->prepare($sql);
    
    $result = $insert->execute([
        $fname, 
        $lname, 
        $email, 
        $phone, 
        $purok, 
        $address, 
        $residency_years, 
        $fileName, 
        $role, 
        $hashedPassword
    ]);

    if ($result) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database registration failed.']);
    }

} catch(PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?>