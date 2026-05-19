<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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

} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

if (isset($_FILES['profile_picture'])) {

    $file = $_FILES['profile_picture'];

    if ($file['error'] === 0) {

        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        $filename = $file['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed)) {
            die("Invalid file type.");
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            die("File too large.");
        }

        $new_name = "user_" . $user_id . "_" . time() . "." . $file_ext;

        $upload_path = "uploads/profile/" . $new_name;

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {

            $stmt = $pdo->prepare(
                "UPDATE users SET profile_picture = ? WHERE id = ?"
            );

            $stmt->execute([$new_name, $user_id]);

            header("Location: resident_dashboard.php");
            exit();

        } else {
            echo "Upload failed.";
        }

    }

}
?>