<?php
session_start();

if (!isset($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}

$residentId = (int)($_SESSION['user_id'] ?? 0);

// Form data
$petId = (int)($_POST['pet_id'] ?? 0);
$applicantName = trim($_POST['applicant_name'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');
$householdMembers = (int)($_POST['household_members'] ?? 0);
$hasOtherPets = strtolower(trim($_POST['has_other_pets'] ?? 'no'));
$otherPetsDetails = trim($_POST['other_pets_details'] ?? '');
$experienceWithPets = trim($_POST['experience_with_pets'] ?? '');
$reasonForAdoption = trim($_POST['reason_for_adoption'] ?? '');
$agreeHomeVisit = isset($_POST['agree_home_visit']) ? 'yes' : 'no';

// Basic validation
if (
    $residentId <= 0 ||
    $petId <= 0 ||
    $applicantName === '' ||
    $phoneNumber === '' ||
    $address === '' ||
    $householdMembers <= 0 ||
    !in_array($hasOtherPets, ['yes', 'no'], true) ||
    $reasonForAdoption === '' ||
    $agreeHomeVisit !== 'yes'
) {
    header("Location: resident_dashboard.php?adoption=error&reason=validation");
    exit();
}

// DB connection
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

    // --- SELF-HEALING DATABASE MIGRATION ---
    // Safely check and add any missing columns in adoption_requests table
    $stmt_cols = $pdo->query("SHOW COLUMNS FROM adoption_requests");
    $existing_cols = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);

    $required_cols = [
        'applicant_name'       => "VARCHAR(255) NULL",
        'phone_number'         => "VARCHAR(50) NULL",
        'address'              => "TEXT NULL",
        'household_members'    => "INT NULL DEFAULT 0",
        'has_other_pets'       => "VARCHAR(10) NULL DEFAULT 'no'",
        'other_pets_details'   => "TEXT NULL",
        'experience_with_pets' => "TEXT NULL",
        'reason_for_adoption'  => "TEXT NULL",
        'agree_home_visit'     => "VARCHAR(10) NULL DEFAULT 'no'",
        'status'               => "VARCHAR(50) NULL DEFAULT 'pending'",
        'request_date'         => "DATETIME NULL"
    ];

    foreach ($required_cols as $colName => $colDef) {
        if (!in_array($colName, $existing_cols, true)) {
            $pdo->exec("ALTER TABLE adoption_requests ADD COLUMN `$colName` $colDef");
        }
    }

    // Prevent duplicate pending requests
    $check = $pdo->prepare("
        SELECT id 
        FROM adoption_requests 
        WHERE resident_id = ? 
        AND pet_id = ? 
        AND status = 'pending'
        LIMIT 1
    ");
    $check->execute([$residentId, $petId]);

    if (!$check->fetch()) {
        $stmt = $pdo->prepare("
            INSERT INTO adoption_requests (
                pet_id,
                resident_id,
                applicant_name,
                phone_number,
                address,
                household_members,
                has_other_pets,
                other_pets_details,
                experience_with_pets,
                reason_for_adoption,
                agree_home_visit,
                status,
                request_date
            ) VALUES (
                :pet_id,
                :resident_id,
                :applicant_name,
                :phone_number,
                :address,
                :household_members,
                :has_other_pets,
                :other_pets_details,
                :experience_with_pets,
                :reason_for_adoption,
                :agree_home_visit,
                'pending',
                NOW()
            )
        ");

        $stmt->execute([
            ':pet_id' => $petId,
            ':resident_id' => $residentId,
            ':applicant_name' => $applicantName,
            ':phone_number' => $phoneNumber,
            ':address' => $address,
            ':household_members' => $householdMembers,
            ':has_other_pets' => $hasOtherPets,
            ':other_pets_details' => ($otherPetsDetails !== '' ? $otherPetsDetails : null),
            ':experience_with_pets' => $experienceWithPets,
            ':reason_for_adoption' => $reasonForAdoption,
            ':agree_home_visit' => $agreeHomeVisit
        ]);
    }

    header("Location: resident_dashboard.php?adoption=success");
    exit();

} catch (PDOException $e) {
    error_log("Adoption Error: " . $e->getMessage());
    header("Location: resident_dashboard.php?adoption=error&details=" . urlencode($e->getMessage()));
    exit();
}
?>