<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$requestId = (int)($_GET['id'] ?? 0);
if ($requestId <= 0) {
    header("Location: manage_adoptions.php");
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Dynamic schema column checker
    $stmt_cols = $pdo->query("SHOW COLUMNS FROM rescued_pets");
    $rp_cols = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);
    $petNameCol = in_array('pet_type', $rp_cols, true) ? 'rp.pet_type' : (in_array('name', $rp_cols, true) ? 'rp.name' : "'Unknown Pet'");
    $petBreedCol = in_array('breed', $rp_cols, true) ? 'rp.breed' : (in_array('species', $rp_cols, true) ? 'rp.species' : "'Mixed Breed'");

    try {
        $stmt = $pdo->prepare("
            SELECT ar.id, ar.status, ar.request_date, ar.applicant_name, ar.phone_number, ar.address,
                   ar.household_members, ar.has_other_pets, ar.other_pets_details, ar.experience_with_pets,
                   ar.reason_for_adoption, ar.agree_home_visit,
                   u.first_name, u.last_name, u.email,
                   $petNameCol AS pet_name, $petBreedCol AS pet_breed
            FROM adoption_requests ar
            LEFT JOIN users u ON u.id = ar.resident_id
            LEFT JOIN rescued_pets rp ON rp.id = ar.pet_id
            WHERE ar.id = ?
            LIMIT 1
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $innerException) {
        $stmt = $pdo->prepare("
            SELECT ar.id, ar.status, ar.request_date,
                   NULL AS applicant_name, NULL AS phone_number, NULL AS address, NULL AS household_members,
                   NULL AS has_other_pets, NULL AS other_pets_details, NULL AS experience_with_pets,
                   NULL AS reason_for_adoption, NULL AS agree_home_visit,
                   u.first_name, u.last_name, u.email,
                   $petNameCol AS pet_name, $petBreedCol AS pet_breed
            FROM adoption_requests ar
            LEFT JOIN users u ON u.id = ar.resident_id
            LEFT JOIN rescued_pets rp ON rp.id = ar.pet_id
            WHERE ar.id = ?
            LIMIT 1
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if (!$request) {
    header("Location: manage_adoptions.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Adoption Request</title>
    <style>
        :root { --bg-deep: #0a0a0b; --accent-gold: #c48a3d; --text-warm: #d8d2cb; --glass: rgba(255,255,255,0.03); --glass-border: rgba(255,255,255,0.08); }
        body { background: var(--bg-deep); color: var(--text-warm); font-family: 'Inter', sans-serif; margin: 0; padding: 30px; }
        .card { background: var(--glass); border: 1px solid var(--glass-border); border-radius: 18px; padding: 24px; max-width: 900px; margin: 0 auto; }
        .back { color: var(--accent-gold); text-decoration: none; display: inline-block; margin-bottom: 14px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .full { grid-column: 1 / -1; }
        .field label { display: block; color: var(--accent-gold); font-size: 0.8rem; text-transform: uppercase; margin-bottom: 6px; }
        .value { background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); border-radius: 10px; padding: 12px; min-height: 20px; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="card">
        <a class="back" href="manage_adoptions.php">← Back to Adoption Requests</a>
        <h2>Adoption Request Form (Read Only)</h2>
        <div class="grid">
            <div class="field">
                <label>Pet</label>
                <div class="value"><?= htmlspecialchars((string)($request['pet_name'] ?? 'Unknown Pet')) ?> (<?= htmlspecialchars((string)($request['pet_breed'] ?? '-')) ?>)</div>
            </div>
            <div class="field">
                <label>Status</label>
                <div class="value"><?= htmlspecialchars(ucfirst((string)($request['status'] ?? 'pending'))) ?></div>
            </div>
            <div class="field">
                <label>Resident Account</label>
                <div class="value"><?= htmlspecialchars(trim((string)(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')))) ?></div>
            </div>
            <div class="field">
                <label>Resident Email</label>
                <div class="value"><?= htmlspecialchars((string)($request['email'] ?? '-')) ?></div>
            </div>
            <div class="field">
                <label>Applicant Name</label>
                <div class="value"><?= htmlspecialchars((string)($request['applicant_name'] ?? '-')) ?></div>
            </div>
            <div class="field">
                <label>Phone Number</label>
                <div class="value"><?= htmlspecialchars((string)($request['phone_number'] ?? '-')) ?></div>
            </div>
            <div class="field full">
                <label>Address</label>
                <div class="value"><?= htmlspecialchars((string)($request['address'] ?? '-')) ?></div>
            </div>
            <div class="field">
                <label>Household Members</label>
                <div class="value"><?= htmlspecialchars((string)($request['household_members'] ?? '-')) ?></div>
            </div>
            <div class="field">
                <label>Has Other Pets</label>
                <div class="value"><?= htmlspecialchars((string)($request['has_other_pets'] ?? '-')) ?></div>
            </div>
            <div class="field full">
                <label>Other Pets Details</label>
                <div class="value"><?= nl2br(htmlspecialchars((string)($request['other_pets_details'] ?? '-'))) ?></div>
            </div>
            <div class="field full">
                <label>Pet Care Experience</label>
                <div class="value"><?= nl2br(htmlspecialchars((string)($request['experience_with_pets'] ?? '-'))) ?></div>
            </div>
            <div class="field full">
                <label>Reason for Adoption</label>
                <div class="value"><?= nl2br(htmlspecialchars((string)($request['reason_for_adoption'] ?? '-'))) ?></div>
            </div>
            <div class="field full">
                <label>Home Visit Agreement</label>
                <div class="value"><?= htmlspecialchars((string)($request['agree_home_visit'] ?? '-')) ?></div>
            </div>
        </div>
    </div>
</body>
</html>