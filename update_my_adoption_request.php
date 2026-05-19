<?php
session_start();

if (!isset($_SESSION['role']) || strtolower((string)$_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}

$residentId = (int)($_SESSION['user_id'] ?? 0);
$requestId = (int)($_POST['request_id'] ?? 0);
$applicantName = trim((string)($_POST['applicant_name'] ?? ''));
$phoneNumber = trim((string)($_POST['phone_number'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$householdMembers = (int)($_POST['household_members'] ?? 0);
$hasOtherPets = strtolower(trim((string)($_POST['has_other_pets'] ?? 'no')));
$otherPetsDetails = trim((string)($_POST['other_pets_details'] ?? ''));
$experienceWithPets = trim((string)($_POST['experience_with_pets'] ?? ''));
$reasonForAdoption = trim((string)($_POST['reason_for_adoption'] ?? ''));
$agreeHomeVisit = strtolower(trim((string)($_POST['agree_home_visit'] ?? '')));

if (
    $residentId <= 0 ||
    $requestId <= 0 ||
    $applicantName === '' ||
    $phoneNumber === '' ||
    $address === '' ||
    $householdMembers <= 0 ||
    !in_array($hasOtherPets, ['yes', 'no'], true) ||
    $experienceWithPets === '' ||
    $reasonForAdoption === '' ||
    !in_array($agreeHomeVisit, ['yes', 'no'], true)
) {
    header("Location: resident_dashboard.php?adoption=error");
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
        UPDATE adoption_requests
        SET
            applicant_name = :applicant_name,
            phone_number = :phone_number,
            address = :address,
            household_members = :household_members,
            has_other_pets = :has_other_pets,
            other_pets_details = :other_pets_details,
            experience_with_pets = :experience_with_pets,
            reason_for_adoption = :reason_for_adoption,
            agree_home_visit = :agree_home_visit
        WHERE id = :request_id
          AND resident_id = :resident_id
          AND status = 'pending'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':applicant_name' => $applicantName,
        ':phone_number' => $phoneNumber,
        ':address' => $address,
        ':household_members' => $householdMembers,
        ':has_other_pets' => $hasOtherPets,
        ':other_pets_details' => $otherPetsDetails !== '' ? $otherPetsDetails : null,
        ':experience_with_pets' => $experienceWithPets,
        ':reason_for_adoption' => $reasonForAdoption,
        ':agree_home_visit' => $agreeHomeVisit,
        ':request_id' => $requestId,
        ':resident_id' => $residentId
    ]);

    header("Location: resident_dashboard.php?adoption=updated");
    exit();
} catch (PDOException $e) {
    header("Location: resident_dashboard.php?adoption=error");
    exit();
}
?>