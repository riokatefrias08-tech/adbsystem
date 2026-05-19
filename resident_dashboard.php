<?php
session_start();

// SESSION PROTECTION
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}

$residentId = (int)($_SESSION['user_id'] ?? 0);
$display_name = $_SESSION['user_name'] ?? 'User';
$success_msg = "";

$count_reports = 0;
$total_rescues = 0;

// DATABASE CONNECTION
$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

/* ---------------- NORMALIZE IMAGE FUNCTION ---------------- */
if (!function_exists('normalizeImageUrl')) {
    function normalizeImageUrl(string $rawPath): string {
        $path = trim($rawPath);
        if ($path === '') return '';
        $path = str_replace('\\', '/', $path);
        if (preg_match('/^(https?:)?\/\//i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }
        if (str_starts_with($path, '/')) {
            return $path;
        }
        $uploadsPos = stripos($path, 'uploads/');
        if ($uploadsPos !== false) {
            return substr($path, $uploadsPos);
        }
        return 'uploads/' . ltrim(basename($path), '/');
    }
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    /* --- AUTO-MIGRATE COLUMNS TO ENSURE STABILITY --- */
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
        'agree_home_visit'     => "VARCHAR(10) NULL DEFAULT 'no'"
    ];
    foreach ($required_cols as $colName => $colDef) {
        if (!in_array($colName, $existing_cols, true)) {
            $pdo->exec("ALTER TABLE adoption_requests ADD COLUMN `$colName` $colDef");
        }
    }

    /* ---------------- DYNAMIC rescued_pets SCHEMA DETECTOR ---------------- */
    $stmt_rp_cols = $pdo->query("SHOW COLUMNS FROM rescued_pets");
    $rp_cols = $stmt_rp_cols->fetchAll(PDO::FETCH_COLUMN);
    
    // Choose pet_type or fallback gracefully to name
    $petNameCol = in_array('pet_type', $rp_cols, true) ? 'rp.pet_type' : (in_array('name', $rp_cols, true) ? 'rp.name' : "'Unknown Pet'");
    $petBreedCol = in_array('breed', $rp_cols, true) ? 'rp.breed' : (in_array('species', $rp_cols, true) ? 'rp.species' : "'Mixed Breed'");

    /* ---------------- USER INFO ---------------- */
    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_user->execute([$residentId]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    /* ---------------- NOTIFICATIONS ---------------- */
    $all_notifications = [];
    $unread_count = 0;
    try {
        $stmt_notif = $pdo->prepare("
            SELECT n.id, n.message, n.is_read, n.created_at,
                   n.rescued_pet_id, rp.image_path, rp.image
            FROM notifications n
            LEFT JOIN rescued_pets rp ON rp.id = n.rescued_pet_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT 20
        ");
        $stmt_notif->execute([$residentId]);
        $all_notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_notifications as $n) {
            if ($n['is_read'] == 0) $unread_count++;
        }
    } catch (Exception $e) {
        $all_notifications = [];
    }

    /* ---------------- REPORTS ---------------- */
    $my_reports = [];
    try {
        $stmt_reports = $pdo->prepare("
            SELECT id, animal_type, location, image, status, date_submitted, 'lost' AS report_type
            FROM lost_reports
            WHERE user_id = ?
            UNION ALL
            SELECT id, animal_type, location, image, status, date_submitted, 'stray' AS report_type
            FROM stray_reports
            WHERE user_id = ?
            ORDER BY date_submitted DESC
        ");
        $stmt_reports->execute([$residentId, $residentId]);
        $my_reports = $stmt_reports->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $my_reports = [];
    }

    /* ---------------- RESCUED PETS ---------------- */
    $rescued_pets = [];
    try {
        require_once __DIR__ . '/pickup_sync.php';
        $availablePets = availableRescuedPetsCondition('rp');
        $query_rescued = "
            SELECT rp.*
            FROM rescued_pets rp
            WHERE {$availablePets}
            ORDER BY rp.id DESC
        ";
        $rescued_pets = $pdo->query($query_rescued)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $rescued_pets = [];
    }

    /* ---------------- PENDING ADOPTION IDS ---------------- */
    $my_pending_adoption_pet_ids = [];
    try {
        $stmt = $pdo->prepare("
            SELECT pet_id
            FROM adoption_requests
            WHERE resident_id = ?
            AND status = 'pending'
        ");
        $stmt->execute([$residentId]);
        $my_pending_adoption_pet_ids = array_map(
            fn($r) => (int)$r['pet_id'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    } catch (Exception $e) {
        $my_pending_adoption_pet_ids = [];
    }

    /* ---------------- ADOPTION REQUESTS (FIXED SECTION) ---------------- */
    $my_adoption_requests = [];
    try {
        // Built using dynamically detected schema columns
        $stmt_adoption_status = $pdo->prepare("
            SELECT
                ar.id,
                ar.status,
                ar.request_date,
                ar.pet_id,
                ar.applicant_name,
                ar.address,
                ar.phone_number,
                ar.experience_with_pets,
                ar.reason_for_adoption,
                ar.household_members,
                ar.has_other_pets,
                ar.other_pets_details,
                ar.agree_home_visit,
                $petNameCol AS pet_name,
                $petBreedCol AS pet_breed,
                ap.pickup_date,
                ap.pickup_time,
                ap.coupon_code
            FROM adoption_requests ar
            LEFT JOIN rescued_pets rp ON rp.id = ar.pet_id
            LEFT JOIN adoption_pickups ap ON ap.request_id = ar.id
            WHERE ar.resident_id = ?
            ORDER BY ar.id DESC
        ");
        $stmt_adoption_status->execute([$residentId]);
        $my_adoption_requests = $stmt_adoption_status->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Safe logging if needed to diagnose issues
        error_log("Adoption Status Query Failed: " . $e->getMessage());
        $my_adoption_requests = [];
    }

    $count_reports = count($my_reports);
    $total_rescues = count($rescued_pets);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - PetConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-deep: #0a0a0b; 
            --accent-gold: #c48a3d; 
            --accent-gold-glow: rgba(196, 138, 61, 0.3);
            --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --sidebar-width: 280px;
            --danger: #ff6b6b;
            --success: #2ecc71;
        }

        body {
            background-color: var(--bg-deep);
            background-image: radial-gradient(circle at 50% 50%, #1a1a1c 0%, #0a0a0b 100%);
            color: var(--text-warm);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            margin: 0;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* --- NOTIFICATION SIDEBAR --- */
        .notif-sidebar {
            position: fixed;
            top: 0;
            right: -360px; /* Hidden */
            width: 360px;
            height: 100vh;
            background: rgba(15, 15, 17, 0.98);
            backdrop-filter: blur(25px);
            border-left: 1px solid var(--glass-border);
            z-index: 2000;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            box-shadow: -10px 0 30px rgba(0,0,0,0.5);
        }

        .notif-sidebar.active {
            right: 0;
        }

        .notif-header {
            padding: 30px 25px;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notif-header h3 {
            margin: 0;
            color: var(--accent-gold);
            font-size: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .close-notif-sidebar {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.6;
            transition: 0.3s;
        }

        .close-notif-sidebar:hover { opacity: 1; color: var(--danger); }

        .notif-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
        }

        .notif-item {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            transition: 0.3s;
            position: relative;
            cursor: pointer;
        }

        .notif-item:hover { background: rgba(255,255,255,0.02); }

        .notif-item.unread {
            border-left: 3px solid var(--accent-gold);
            background: rgba(196, 138, 61, 0.05);
        }

        .notif-item p {
            margin: 0 0 8px 0;
            font-size: 0.9rem;
            line-height: 1.4;
            color: #fff;
        }

        .notif-item span {
            font-size: 0.75rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Badge on Sidebar Link */
        .notif-badge {
            background: var(--danger);
            color: #fff;
            font-size: 0.7rem;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin-left: auto;
            font-weight: 800;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(15, 15, 17, 0.9);
            border-right: 1px solid var(--glass-border);
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(20px);
            position: fixed;
            height: 100vh;
            box-sizing: border-box;
            z-index: 100;
            top: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 50px;
            padding-left: 10px;
        }

        .brand i { 
            color: var(--accent-gold); 
            font-size: 2rem; 
            filter: drop-shadow(0 0 8px var(--accent-gold-glow));
        }
        
        .brand h2 { 
            margin: 0; 
            font-size: 1.5rem; 
            color: #fff; 
            letter-spacing: 1px; 
            font-weight: 700;
        }

        .nav-links { list-style: none; padding: 0; flex-grow: 1; }
        .nav-links li { margin-bottom: 8px; }

        .nav-links a { 
            text-decoration: none; color: #888; padding: 14px 20px; 
            display: flex; align-items: center; gap: 15px; border-radius: 12px; 
            transition: 0.3s; cursor: pointer;
        }

        .nav-links a:hover { color: #fff; background: var(--glass); }

        .nav-links a.active { 
            background: rgba(196, 138, 61, 0.1);
            color: var(--accent-gold);
            font-weight: 600;
            border-left: 4px solid var(--accent-gold);
        }

        /* --- MAIN CONTENT & CARDS --- */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 50px;
            max-width: 1100px;
        }

        .content-section { display: none; animation: fadeIn 0.4s ease forwards; }
        .content-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .card {
            background: var(--glass);
            padding: 30px;
            border-radius: 28px;
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* --- HOME GRID --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: rgba(255,255,255,0.02);
            padding: 20px;
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            text-align: center;
        }

        .stat-card h3 { font-size: 2rem; margin: 10px 0; color: var(--accent-gold); }
        .stat-card p { margin: 0; font-size: 0.85rem; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; }

        .action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .action-item {
            padding: 25px;
            border-radius: 20px;
            background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            gap: 20px;
            cursor: pointer;
            transition: 0.3s;
        }

        .action-item:hover {
            background: rgba(196, 138, 61, 0.1);
            border-color: var(--accent-gold);
            transform: scale(1.02);
        }

        .action-item i {
            font-size: 2rem;
            color: var(--accent-gold);
        }

        /* Forms & Tables */
        .report-form label {
            display: block; margin-bottom: 8px; font-weight: 500;
            color: var(--accent-gold); font-size: 0.9rem; text-transform: uppercase;
        }

        .report-form input, .report-form select, .report-form textarea {
            width: 100%; padding: 14px 18px; margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.04); border: 1px solid var(--glass-border);
            color: #fff; border-radius: 14px; box-sizing: border-box; transition: 0.3s;
        }

        .report-form select option {
            background: #1b1b1f;
            color: #f5f5f5;
        }

        .btn-gold {
            background: var(--accent-gold); color: #000; border: none; 
            padding: 16px 40px; border-radius: 14px; cursor: pointer;
            font-weight: 800; text-transform: uppercase; transition: 0.3s; width: 100%;
        }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .badge-lost { background: #e74c3c; color: white; }
        .badge-stray { background: var(--accent-gold); color: black; }
        .thumb-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .report-table th { text-align: left; color: var(--accent-gold); padding: 15px; border-bottom: 1px solid var(--glass-border); }
        .report-table td { padding: 15px; border-bottom: 1px solid var(--glass-border); }

        /* Profile Styles */
       .profile-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

/* PROFILE GROUP CARD */

.profile-group {
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--glass-border);
    padding: 20px;
    border-radius: 18px;
}

/* SECTION TITLE */

.profile-group h3 {
    margin-bottom: 15px;
    color: var(--accent-gold);
    font-size: 0.95rem;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* FIELD */

.profile-field {
    margin-bottom: 15px;
}

.profile-field label {
    display: block;
    font-size: 0.75rem;
    margin-bottom: 6px;
    color: var(--accent-gold);
    text-transform: uppercase;
}

/* INPUT STYLE */

.profile-field input {
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--glass-border);
    background: rgba(255,255,255,0.04);
    color: #fff;
    font-size: 0.9rem;
}

/* READONLY LOOK */

.profile-field input[readonly] {
    opacity: 0.9;
    cursor: default;
}
/* PROFILE PICTURE */

.profile-picture-container {
    text-align: center;
    margin-bottom: 30px;
}

/* --- UPDATED PROFILE PICTURE SECTION --- */

.profile-picture-container {
    text-align: center;
    margin-bottom: 40px;
}

/* The clickable wrapper */
.profile-avatar-label {
    cursor: pointer;
    display: inline-block;
    position: relative;
    transition: transform 0.3s ease;
}

.profile-avatar-label:hover {
    transform: scale(1.05);
}

.profile-avatar {
    width: 160px;
    height: 160px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--accent-gold);
    box-shadow: 0 0 20px var(--accent-gold-glow);
    position: relative;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* The "Change Photo" overlay that appears on hover */
.avatar-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.profile-avatar:hover .avatar-overlay {
    opacity: 1;
}

.avatar-overlay span {
    color: #fff;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 10px;
    text-align: center;
}

/* Hide the old button and input */
#profile_upload_input {
    display: none;
}
.profile-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 40px;
    text-align: center;
}
.profile-name-display {
    margin-top: 15px;
}

.profile-name-display h2 {
    margin: 0;
    font-size: 1.8rem;
    color: #fff;
}

.profile-name-display p {
    margin: 5px 0 0;
    color: var(--accent-gold);
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 2px;
}


        /* Rescued Pets Grid — fixed max column width so one pet does not stretch full page */
        .rescued-pets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 260px));
            gap: 20px;
            margin-top: 20px;
            justify-content: start;
        }
        .rescued-pet-card.profile-group {
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-width: 260px;
            width: 100%;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            background: rgba(255,255,255,0.01);
        }
        .rescued-pet-card .rescued-pet-photo {
            height: 130px;
            width: 100%;
            overflow: hidden;
        }
        .rescued-pet-card .rescued-pet-photo img { width: 100%; height: 100%; object-fit: cover; }
        .rescued-pet-body { padding: 14px; flex: 1; }
        .rescued-pet-body h3 { margin: 0 0 10px; font-size: 0.95rem; color: var(--accent-gold); }
        .rescued-pet-card .profile-field { margin-bottom: 8px; }
        .rescued-pet-card .profile-field label { font-size: 10px; opacity: 0.6; margin-bottom: 3px; }
        .rescued-pet-card .profile-field input {
            padding: 6px 8px;
            font-size: 0.85rem;
        }
        .rescued-pet-card .pet-action-row { display: flex; gap: 8px; margin-top: 12px; }
        .rescued-pet-card .btn-pet { padding: 8px 10px; font-size: 0.7rem; border-radius: 8px; }
        .pet-action-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 18px; }
        .btn-pet { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 14px; border-radius: 12px; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; text-decoration: none; transition: 0.25s; }
        .btn-adopt { background: var(--accent-gold); color: #000; }
        .btn-donate { border: 1px solid var(--accent-gold); color: var(--accent-gold); }

        /* Modals */
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.75); display: none; align-items: center; justify-content: center; z-index: 2500; padding: 20px; }
        .modal-backdrop.active { display: flex; }
        .modal-card { width: min(780px, 100%); max-height: 90vh; overflow-y: auto; background: #131316; border: 1px solid var(--glass-border); border-radius: 20px; padding: 24px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .btn-close-modal { border: 1px solid var(--glass-border); background: transparent; color: #fff; width: 32px; height: 32px; cursor: pointer; border-radius: 8px; }

        /* Mini Dashboard modal tweaks */
        #miniDashboardModal .modal-card { width: min(720px, 100%); padding: 20px; border-radius: 14px; background: linear-gradient(180deg, rgba(20,20,22,0.95), #0f0f11); box-shadow: 0 10px 30px rgba(0,0,0,0.6); }
        #miniDashboardModal .modal-header h3 { margin:0; font-size:1.25rem; font-weight:700; letter-spacing:0.2px; color: #fff; }
        #miniDashboardModal h4 { margin:0 0 8px 0; font-size:0.95rem; color: var(--text-warm); font-weight:700; }
        #miniDashboardModal p { color: rgba(255,255,255,0.92); line-height:1.4; font-size:0.95rem; }

        /* Inputs inside mini modal */
        #miniDashboardModal input[type="date"],
        #miniDashboardModal input[type="time"] {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            color: var(--text-warm);
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: box-shadow 0.18s ease, transform 0.12s ease;
        }
        #miniDashboardModal input[type="date"]:focus,
        #miniDashboardModal input[type="time"]:focus {
            outline: none;
            box-shadow: 0 6px 18px var(--accent-gold-glow);
            transform: translateY(-1px);
            border-color: var(--accent-gold);
        }

        /* Buttons */
        .btn { padding: 8px 12px; border-radius: 8px; background: transparent; color: #fff; border: 1px solid var(--glass-border); cursor: pointer; font-weight:600; transition: transform .12s ease, box-shadow .12s ease; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.5); }
        .btn.primary { background: var(--accent-gold); color: #000; border: none; }
        .btn.primary:hover { box-shadow: 0 10px 30px rgba(196,138,61,0.18); }

        /* Notification item hover */
        .notif-item { transition: transform .14s ease, box-shadow .14s ease; border-radius: 12px; }
        .notif-item:hover { transform: translateY(-4px); box-shadow: 0 8px 28px rgba(0,0,0,0.45); }

        /* Dark glass scheduling block inside mini modal */
        #miniDashboardModal .mini-schedule {
            margin-top:12px;
            background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
            border: 1px solid rgba(255,255,255,0.04);
            padding:12px;
            border-radius:10px;
        }
        #miniDashboardModal .mini-schedule label { display:block; font-size:0.85rem; margin-bottom:6px; color: rgba(255,255,255,0.78); font-weight:600; }
        #miniDashboardModal .mini-schedule h4 { margin:0 0 8px 0; font-size:0.95rem; color: var(--text-warm); }
        #miniDashboardModal input::placeholder { color: rgba(255,255,255,0.45); }

        .status-action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-danger { background: rgba(255, 107, 107, 0.1); border: 1px solid var(--danger); color: var(--danger); }
        
        .status-approved { color: var(--success); }
        .status-rejected { color: var(--danger); }
        .status-pending { color: #f1c40f; }

        .adoption-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .adoption-grid .full { grid-column: 1 / -1; }

        .notif-pet-photo {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 14px;
    margin-top: 12px;
    border: 1px solid var(--glass-border);
}

.notif-action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 14px;
}

.notif-btn {
    flex: 1;
    border: none;
    padding: 10px;
    border-radius: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.3s;
}

.notif-btn.yes {
    background: var(--success);
    color: #fff;
}

.notif-btn.no {
    background: rgba(255,255,255,0.06);
    color: #fff;
    border: 1px solid var(--glass-border);
}

.notif-btn:hover {
    transform: scale(1.03);
}
/* Hide default checkbox completely */
.checkbox-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

/* Custom glassmorphic checkbox square */
.checkmark {
    height: 18px;
    width: 18px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 5px;
    flex-shrink: 0; /* Prevents the box from squeezing or distorting */
    position: relative;
    transition: all 0.2s ease-in-out;
    display: inline-block;
}

/* Gold glow effect on hover */
.checkbox-container:hover input ~ .checkmark {
    border-color: rgba(196, 138, 61, 0.5);
}

/* Checked state color shifts */
.checkbox-container input:checked ~ .checkmark {
    background-color: #c48a3d; /* Accent Gold */
    border-color: #c48a3d;
}

/* Generate the check indicator shape hook */
.checkmark:after {
    content: "";
    position: absolute;
    display: none;
    left: 5px;
    top: 1px;
    width: 4px;
    height: 9px;
    border: solid #000000;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Display indicator when active */
.checkbox-container input:checked ~ .checkmark:after {
    display: block;
}
    </style>
</head>
<body>

    <!-- --- NOTIFICATION SIDEBAR --- -->
    <div id="notifSidebar" class="notif-sidebar">
        <div class="notif-header">
            <h3>Notifications</h3>
            <button class="close-notif-sidebar" onclick="toggleNotifSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="notif-list">
            <?php if (empty($all_notifications)): ?>
                <div style="padding: 60px 20px; text-align: center; opacity: 0.4;">
                    <i class="fas fa-bell-slash" style="font-size: 3rem; margin-bottom: 20px; display: block;"></i>
                    <p>No notifications found.</p>
                </div>
            <?php else: ?>
                <?php foreach($all_notifications as $n): ?>

    <?php
        $petImage = $n['image_path'] ?? ($n['image'] ?? '');
        $petImageUrl = normalizeImageUrl((string)$petImage);

        $isMatchNotif =
            !empty($n['rescued_pet_id']) &&
            stripos($n['message'], 'found a match') !== false;

        $alreadyConfirmed =
            stripos($n['message'], '[CONFIRMED]') !== false;

        $alreadyRejected =
            stripos($n['message'], '[NOT MATCHED]') !== false;
    ?>

    <div class="notif-item <?php echo $n['is_read'] == 0 ? 'unread' : ''; ?>"
         onclick="markAsRead(<?php echo $n['id']; ?>, this)">

        <p><?php echo htmlspecialchars($n['message']); ?></p>

        <?php if ($isMatchNotif && $petImageUrl): ?>
            <img
                src="<?php echo htmlspecialchars($petImageUrl); ?>"
                class="notif-pet-photo"
                onerror="this.src='https://via.placeholder.com/300x180?text=No+Image'"
            >
        <?php endif; ?>

        <?php if ($isMatchNotif && !$alreadyConfirmed && !$alreadyRejected): ?>
            <div class="notif-action-buttons">
                <button
                    class="notif-btn yes"
                    onclick="confirmPet(event, <?php echo $n['id']; ?>, this)"
                    data-pet-id="<?php echo htmlspecialchars($n['rescued_pet_id'] ?? ''); ?>"
                    data-pet-name="<?php echo htmlspecialchars($n['pet_name'] ?? ($n['pet_type'] ?? '')); ?>"
                    data-image="<?php echo htmlspecialchars($petImageUrl); ?>"
                    data-message="<?php echo htmlspecialchars($n['message']); ?>"
                >
                    YES, This is My Pet
                </button>

                <button
                    class="notif-btn no"
                    onclick="rejectPet(event, <?php echo $n['id']; ?>)"
                >
                    NO
                </button>
            </div>
        <?php endif; ?>

        <span>
            <i class="far fa-clock"></i>
            <?php echo date('M d, h:i A', strtotime($n['created_at'])); ?>
        </span>
    </div>

<?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <aside class="sidebar">
        <div class="brand">
            <i class="fas fa-paw"></i>
            <h2>PetConnect</h2>
        </div>

        <ul class="nav-links">
            <li><a id="nav-home" class="active" onclick="showSection('home', this)">🏠 Home</a></li>
            
            <!-- NEW NOTIFICATION TOGGLE LINK -->
            <li>
                <a onclick="toggleNotifSidebar()">
                    🔔 Notifications
                    <?php if($unread_count > 0): ?>
                        <span class="notif-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li><a id="nav-report" onclick="showSection('report', this)">🐾 File a Report</a></li>
            <li><a id="nav-history" onclick="showSection('my-reports', this)">📋 My History</a></li>
            <li><a id="nav-rescued" onclick="showSection('rescued', this)">🐶 Rescued Pets</a></li>
            <li><a id="nav-adoption-status" onclick="showSection('adoption-status', this)">💌 Adoption Status</a></li>
            <li><a id="nav-donation" onclick="showSection('donation', this)">🎁 Donations</a></li>
            <li><a id="nav-profile" onclick="showSection('profile', this)">👤 Profile Settings</a></li>
        </ul>
        <a href="logout.php" style="color: var(--danger); text-decoration: none; padding: 15px; border-top: 1px solid var(--glass-border); margin-top: auto;">🚪 Logout</a>
    </aside>

    <main class="main-content">
        <!-- HOME SECTION -->
        <section id="home" class="content-section active">
            <div class="card" style="background: linear-gradient(to right, rgba(196, 138, 61, 0.15), var(--glass)); border-left: 5px solid var(--accent-gold);">
                <h1 style="margin: 0; font-size: 2.5rem;">Hello, <?php echo htmlspecialchars($display_name); ?>!</h1>
                <p style="font-size: 1.1rem; opacity: 0.8;">Your PetConnect Dashboard for New Visayas.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <p>My Submissions</p>
                    <h3><?php echo $count_reports; ?></h3>
                </div>
                <div class="stat-card">
                    <p>Community Rescues</p>
                    <h3><?php echo $total_rescues; ?></h3>
                </div>
                <div class="stat-card">
                    <p>Current Status</p>
                    <h3 style="font-size: 1.2rem; color: #2ecc71;">Active Resident</h3>
                </div>
            </div>

            <h2 style="margin-top: 40px; color: var(--accent-gold);">Quick Actions</h2>
            <div class="action-grid">
                <div class="action-item" onclick="showSection('report', document.getElementById('nav-report'))">
                    <i class="fas fa-bullhorn"></i>
                    <div>
                        <h4 style="margin:0">Report an Animal</h4>
                        <p style="margin:5px 0 0; font-size: 0.8rem; opacity:0.6;">Lost a pet or found a stray? Report it now.</p>
                    </div>
                </div>
                <div class="action-item" onclick="showSection('rescued', document.getElementById('nav-rescued'))">
                    <i class="fas fa-dog"></i>
                    <div>
                        <h4 style="margin:0">View Rescued Pets</h4>
                        <p style="margin:5px 0 0; font-size: 0.8rem; opacity:0.6;">Check animals safe in the shelter.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- REPORT SECTION -->
        <section id="report" class="content-section">
            <div class="card">
                <h2>📢 Report Stray or Lost Pet</h2>
                <form class="report-form" action="submit_report.php" method="POST" enctype="multipart/form-data">
                    <label>What are you reporting?</label>
                    <select name="report_type" required>
                        <option value="stray">I found a Stray Animal</option>
                        <option value="lost">I lost my own Pet</option>
                    </select>

                    <label>Animal Type</label>
                    <select name="animal_type">
                        <option value="Dog">Dog</option>
                        <option value="Cat">Cat</option>
                    </select>

                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Describe the animal..." required></textarea>

                   <label>Last Seen Location</label>
<select name="location" required>
    <option value="" disabled selected>Select a Purok</option>
    <option value="Purok 1">Purok 1</option>
    <option value="Purok 2">Purok 2</option>
    <option value="Purok 3">Purok 3</option>
    <option value="Purok 4">Purok 4</option>
    <option value="Purok 5">Purok 5</option>
    <option value="Purok 6">Purok 6</option>
    <option value="Purok 7">Purok 7</option>
    <option value="Purok 8">Purok 8</option>
    <option value="Purok 9">Purok 9</option>
    <option value="Purok 10">Purok 10</option>
    <option value="Purok 11">Purok 11</option>
    <option value="Purok 12">Purok 12</option>
    <option value="Purok 13">Purok 13</option>
    <option value="Purok 14">Purok 14</option>
    <option value="Purok 15">Purok 15</option>
    <option value="Purok 16">Purok 16</option>
    <option value="Purok 17">Purok 17</option>
</select>

                    <label>Health Status of the Pet</label>
<select name="health_status" required>
    <option value="Healthy">Healthy</option>
    <option value="Injured">Injured</option>
    <option value="Critical">Critical</option>
</select>

                    <label>Upload Photo</label>
                    <input type="file" name="pet_image" accept="image/*" required>

                    <button type="submit" class="btn-gold">Submit Report</button>
                </form>
            </div>
        </section>

        <!-- HISTORY SECTION -->
        <section id="my-reports" class="content-section">
            <div class="card">
                <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i> 
                        Report submitted successfully! It is now being reviewed by the admin.
                    </div>
                <?php endif; ?>

                <h2>📋 My Submitted Reports</h2>
                <div style="overflow-x: auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Type</th>
                                <th>Animal</th>
                                <th>Location</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($my_reports)): ?>
                                <tr><td colspan="5" style="text-align:center; padding: 40px;">No reports found.</td></tr>
                            <?php else: ?>
                                <?php foreach($my_reports as $r): ?>
                                    <?php
                                        $reportImageUrl = normalizeImageUrl((string)($r['image'] ?? ''));
                                        $reportThumbFallback = 'https://via.placeholder.com/60?text=No+Photo';
                                    ?>
                                    <tr>
                                        <td><img src="<?php echo htmlspecialchars($reportImageUrl ?: $reportThumbFallback); ?>" class="thumb-img" alt="Report photo" onerror="this.onerror=null;this.src='<?php echo $reportThumbFallback; ?>'"></td>
                                        <td>
                                            <span class="badge <?php echo ($r['report_type'] == 'lost') ? 'badge-lost' : 'badge-stray'; ?>">
                                                <?php echo strtoupper($r['report_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($r['animal_type']); ?></td>
                                        <td><?php echo htmlspecialchars($r['location']); ?></td>
                                        <td><strong style="color:var(--accent-gold)"><?php echo $r['status']; ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

      <!-- RESCUED PETS SECTION -->
<section id="rescued" class="content-section">
    <div class="card">
        <h2>🐶 Recently Rescued Animals</h2>
        <?php if (isset($_GET['adoption']) && $_GET['adoption'] === 'success'): ?>
            <div class="alert-success" style="margin-top: 18px; padding: 12px; background: rgba(46, 204, 113, 0.15); border: 1px solid #2ecc71; color: #2ecc71; border-radius: 8px;">
                <i class="fas fa-check-circle"></i>
                Adoption request submitted! Please wait for admin approval.
            </div>
        <?php elseif (isset($_GET['adoption']) && $_GET['adoption'] === 'error'): ?>
            <div class="alert-danger" style="margin-top: 18px; padding: 12px; background: rgba(231, 76, 60, 0.15); border: 1px solid #e74c3c; color: #e74c3c; border-radius: 8px;">
                <i class="fas fa-times-circle"></i>
                Failed to submit request. Please check that you filled out all required fields and try again.
                <?php if (!empty($_GET['details'])): ?>
                    <br><small>Error details: <?= htmlspecialchars($_GET['details']) ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="rescued-pets-grid">
            <?php if (empty($rescued_pets)): ?>
                <p>No rescued animals to display.</p>
            <?php else: ?>
                <?php foreach($rescued_pets as $pet): ?>
                    <?php
                        $petId = (int)($pet['id'] ?? 0);
                        $petName = $pet['pet_type'] ?? ($pet['name'] ?? 'Unknown');
                        $petBreed = $pet['breed'] ?? ($pet['species'] ?? 'Unknown');
                        $petImage = $pet['image_path'] ?? ($pet['image'] ?? '');
                        $petImageUrl = normalizeImageUrl((string)$petImage);
                        $petDate = $pet['created_at'] ?? ($pet['date_added'] ?? ($pet['rescue_date'] ?? 'N/A'));
                        $alreadyRequested = in_array($petId, $my_pending_adoption_pet_ids, true);
                    ?>
                    <div class="profile-group rescued-pet-card">
                        <div class="rescued-pet-photo">
                            <img src="<?php echo htmlspecialchars($petImageUrl); ?>" alt="<?php echo htmlspecialchars($petName); ?>" onerror="this.src='https://via.placeholder.com/260x130?text=No+Image'">
                        </div>
                        <div class="rescued-pet-body">
                            <h3>Pet profile</h3>
                            <div class="profile-field" style="margin-bottom: 10px;">
                                <label style="font-size: 11px; opacity: 0.6; display: block; margin-bottom: 4px;">Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($petName); ?>" readonly style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 8px; color: #fff; width: 100%; border-radius: 4px; box-sizing: border-box;">
                            </div>
                            
                            <div class="profile-field" style="margin-bottom: 10px;">
                                <label style="font-size: 11px; opacity: 0.6; display: block; margin-bottom: 4px;">Breed</label>
                                <input type="text" value="<?php echo htmlspecialchars($petBreed); ?>" readonly style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 8px; color: #fff; width: 100%; border-radius: 4px; box-sizing: border-box;">
                            </div>

                            <div class="profile-field" style="margin-bottom: 15px;">
                                <label style="font-size: 11px; opacity: 0.6; display: block; margin-bottom: 4px;">Health Status</label>
                                <?php
                                    $statusColor = "#ccc";
                                    $currentStatus = strtolower($pet['health_status'] ?? '');
                                    if($currentStatus == 'injured'){
                                        $statusColor = "#ff6b6b";
                                    } elseif($currentStatus == 'healthy'){
                                        $statusColor = "#2ecc71";
                                    } elseif($currentStatus == 'critical'){
                                        $statusColor = "#f1c40f";
                                    }
                                ?>
                                <input type="text" value="<?php echo htmlspecialchars($pet['health_status'] ?? 'Unknown'); ?>" readonly style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 8px; color: <?php echo $statusColor; ?>; font-weight:bold; width: 100%; border-radius: 4px; box-sizing: border-box;">
                            </div>
                            
                            <div class="pet-action-row" style="display: flex; gap: 8px;">
                                <?php if ($alreadyRequested): ?>
                                    <span class="btn-pet btn-adopt" style="flex: 1; opacity:0.55; cursor:not-allowed; display:inline-block; text-align:center; padding: 10px; background: rgba(196, 138, 61, 0.2); border-radius: 6px; color: #fff;">
                                        <i class="fas fa-clock"></i> Requested
                                    </span>
                                <?php else: ?>
                                    <button
                                        type="button"
                                        class="btn-pet btn-adopt"
                                        style="flex: 1; padding: 10px; background: #c48a3d; border: none; border-radius: 6px; color: #000; font-weight: bold; cursor: pointer;"
                                        data-pet-id="<?php echo (int)$petId; ?>"
                                        data-pet-name="<?php echo htmlspecialchars($petName, ENT_QUOTES); ?>"
                                        onclick="openAdoptionModal(this)"
                                    >
                                        <i class="fas fa-heart"></i> Adopt
                                    </button>
                                <?php endif; ?>
                                <button
                                    type="button"
                                    class="btn-pet btn-donate"
                                    style="flex: 1; padding: 10px; background: transparent; border: 1px solid var(--accent-gold); border-radius: 6px; color: var(--accent-gold); font-weight: 700; cursor: pointer; font-size: 13px;"
                                    data-pet-id="<?php echo (int)$petId; ?>"
                                    data-pet-name="<?php echo htmlspecialchars($petName, ENT_QUOTES); ?>"
                                    data-pet-breed="<?php echo htmlspecialchars($petBreed, ENT_QUOTES); ?>"
                                    data-pet-image="<?php echo htmlspecialchars($petImageUrl, ENT_QUOTES); ?>"
                                    onclick="openDonateModal(this)"
                                >
                                    <i class="fas fa-hand-holding-heart"></i> Donate
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ADOPTION STATUS SECTION -->
<section id="adoption-status" class="content-section">
    <div class="card">
        <h2>💌 My Adoption Requests</h2>

        <div style="overflow-x: auto;">
            <table class="report-table" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); text-align: left;">
                        <th style="padding: 12px; color: #c48a3d;">Pet</th>
                        <th style="padding: 12px; color: #c48a3d;">Status</th>
                        <th style="padding: 12px; color: #c48a3d;">Pickup</th>
                        <th style="padding: 12px; color: #c48a3d;">Code</th>
                        <th style="padding: 12px; color: #c48a3d;">Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($my_adoption_requests)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 40px; opacity: 0.5;">
                            No requests found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($my_adoption_requests as $request): ?>
                        <?php
                            $status = strtolower($request['status'] ?? 'pending');
                            $pickupDate = $request['pickup_date'] ?? null;
                            $coupon = $request['coupon_code'] ?? null;
                        ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 12px;">
                                <strong><?php echo htmlspecialchars($request['pet_name'] ?? 'Pet'); ?></strong>
                                <div style="font-size: 11px; opacity: 0.5;"><?php echo htmlspecialchars($request['pet_breed'] ?? 'Mixed Breed'); ?></div>
                            </td>

                            <td style="padding: 12px;">
                                <strong class="status-<?php echo htmlspecialchars($status); ?>" style="color: <?php echo ($status === 'approved' ? '#2ecc71' : ($status === 'rejected' ? '#ff6b6b' : '#f1c40f')); ?>;">
                                    <?php echo ucfirst($status); ?>
                                </strong>
                            </td>

                            <td style="padding: 12px;">
                                <?php echo !empty($pickupDate) ? htmlspecialchars($pickupDate) : '—'; ?>
                            </td>

                            <td style="padding: 12px;">
                                <code><?php echo !empty($coupon) ? htmlspecialchars($coupon) : '—'; ?></code>
                            </td>

                            <td style="padding: 12px;">
                                <?php if ($status === 'pending'): ?>
                                    <button
                                        class="btn-pet btn-adopt"
                                        style="padding: 5px 10px; background: #c48a3d; border: none; border-radius: 4px; color: #000; cursor: pointer; font-size: 12px;"
                                        onclick="openEditAdoptionModal(this)"
                                        data-request-id="<?php echo (int)$request['id']; ?>"
                                        data-applicant-name="<?php echo htmlspecialchars($request['applicant_name'] ?? ''); ?>"
                                        data-phone-number="<?php echo htmlspecialchars($request['phone_number'] ?? ''); ?>"
                                        data-address="<?php echo htmlspecialchars($request['address'] ?? ''); ?>"
                                        data-household-members="<?php echo htmlspecialchars($request['household_members'] ?? ''); ?>"
                                        data-has-other-pets="<?php echo htmlspecialchars($request['has_other_pets'] ?? 'no'); ?>"
                                        data-other-pets-details="<?php echo htmlspecialchars($request['other_pets_details'] ?? ''); ?>"
                                        data-experience-with-pets="<?php echo htmlspecialchars($request['experience_with_pets'] ?? ''); ?>"
                                        data-reason-for-adoption="<?php echo htmlspecialchars($request['reason_for_adoption'] ?? ''); ?>"
                                        data-agree-home-visit="<?php echo htmlspecialchars($request['agree_home_visit'] ?? ''); ?>"
                                    >
                                        Edit
                                    </button>
                                <?php elseif ($status === 'approved'): ?>
                                    <span style="color: #2ecc71; font-weight: 700; font-size: 12px;">Scheduled</span>
                                <?php else: ?>
                                    <span style="opacity:0.6; font-size: 12px;">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

        <!-- DONATION SECTION -->
<section id="donation" class="content-section">
    <div class="card">

        <h2 style="margin-bottom: 25px;">🎁 Donate for Rescued Pets</h2>

        <div class="stats-grid">

            <div class="stat-card">
                <i class="fas fa-dog" style="font-size:2rem; color: var(--accent-gold);"></i>
                <h3>Dog Food</h3>
                <p>Dry food, wet food, treats</p>
            </div>

            <div class="stat-card">
                <i class="fas fa-cat" style="font-size:2rem; color: var(--accent-gold);"></i>
                <h3>Cat Food</h3>
                <p>Milk, canned food, treats</p>
            </div>

            <div class="stat-card">
                <i class="fas fa-pills" style="font-size:2rem; color: var(--accent-gold);"></i>
                <h3>Vitamins</h3>
                <p>Supplements & medicine</p>
            </div>

            <div class="stat-card">
                <i class="fas fa-hand-holding-heart" style="font-size:2rem; color: var(--accent-gold);"></i>
                <h3>Pet Supplies</h3>
                <p>Soap, cage, leash, bowls</p>
            </div>

        </div>

        <div class="card" style="margin-top:30px; background: rgba(255,255,255,0.02);">

            <h3 style="color: var(--accent-gold); margin-bottom:20px;">
               🎁 Donations
            </h3>

           <form action="submit_donation.php" method="POST" class="report-form">
    <input type="hidden" name="pet_id" id="donation_pet_id" value="">
    <input type="hidden" name="pet_name" id="donation_pet_name" value="">

<label>Donation Type</label>
<select name="donation_type" id="donation_type" onchange="toggleDonationAmount()" required>
    <option value="">Select</option>
    <option value="dog_food">Dog Food</option>
    <option value="cat_food">Cat Food</option>
    <option value="vitamins">Vitamins</option>
    <option value="supplies">Supplies</option>
    <option value="money">Money</option>
</select>



<div id="amountContainer" style="display:none;">
    <label>Amount (₱)</label>
    <input type="number" name="amount" id="amount" min="1" step="1">
</div>

    <label>Message (Optional)</label>
    <textarea name="message" rows="3" placeholder="Optional message..."></textarea>

    <button type="submit" class="btn-gold">
        Donate Now
    </button>

    <hr style="margin:30px 0; border-color: rgba(255,255,255,0.1);">

<h3 style="color: var(--accent-gold); margin-bottom:20px;">
    📜 My Donation Receipts
</h3>

<?php
try {

    $stmt_donations = $pdo->prepare("
        SELECT *
        FROM donations
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");

    $stmt_donations->execute([$residentId]);

    $my_donations = $stmt_donations->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $my_donations = [];

}
?>

<?php if (empty($my_donations)): ?>

    <p style="color:#888;">No confirmed donations yet.</p>

<?php else: ?>

    <?php
    if (!function_exists('formatDonationTypeLabel')) {
        require_once __DIR__ . '/donation_helpers.php';
    }
    ?>
    <?php foreach ($my_donations as $d): ?>

        <div style="
            background: rgba(255,255,255,0.03);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.05);
        ">

            <?php if (!empty($d['pet_name'])): ?>
                <p style="color: var(--accent-gold); margin-bottom: 8px;">
                    <strong>For pet:</strong> <?= htmlspecialchars($d['pet_name']) ?>
                </p>
            <?php endif; ?>

            <p>
                <strong>Type:</strong>
                <?= htmlspecialchars(formatDonationTypeLabel($d['donation_type'] ?? '')) ?>
            </p>

            <?php if ($d['donation_type'] === 'money'): ?>
                <p>
                    <strong>Amount:</strong>
                    ₱<?= number_format($d['amount'], 2) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($d['message'])): ?>
                <p>
                    <strong>Message:</strong>
                    <?= htmlspecialchars($d['message']) ?>
                </p>
            <?php endif; ?>

            <p>
                <strong>Receipt No:</strong>
                <?= htmlspecialchars($d['receipt_number']) ?>
            </p>

            <p>
                <strong>Date:</strong>
                <?= date('M d, Y h:i A', strtotime($d['created_at'])) ?>
            </p>

        </div>

    <?php endforeach; ?>

<?php endif; ?>

</form>

        </div>

        <?php if (isset($_GET['donation']) && $_GET['donation'] == 'success'): ?>
            <div class="alert-success" style="margin-top:20px;">
                <i class="fas fa-check-circle"></i>
                Donation submitted successfully! Receipt generated.
            </div>
        <?php elseif (isset($_GET['donation']) && $_GET['donation'] == 'error'): ?>
            <div class="alert-danger" style="margin-top:20px; padding:12px; background:rgba(231,76,60,0.15); border:1px solid #e74c3c; color:#e74c3c; border-radius:8px;">
                <i class="fas fa-times-circle"></i>
                Could not process donation. Please check your donation type and amount.
            </div>
        <?php endif; ?>

    </div>
</section>

        <!-- PROFILE SECTION -->
      <section id="profile" class="content-section">
    <div class="card">
        <h2 style="margin-bottom: 25px;">👤 My Profile Settings</h2>

        <div class="profile-header">
            <form action="upload_profile_picture.php" method="POST" enctype="utf-8" id="profile-form">
                <label for="profile_upload_input" class="profile-avatar-label">
                    <div class="profile-avatar">
                        <img 
                            src="uploads/profile/<?php echo htmlspecialchars($user_info['profile_picture'] ?? 'default.png'); ?>" 
                            onerror="this.src='http://localhost/dashboard/favicon.ico'"
                            alt="Profile Picture"
                            id="avatar-preview"
                        >
                        <div class="avatar-overlay">
                            <span>Change Photo</span>
                        </div>
                    </div>
                </label>
                <input type="file" name="profile_picture" id="profile_upload_input" accept="image/*" style="display: none;" onchange="this.form.submit()">
            </form>

            <div class="profile-name-display">
                <h2><?php echo htmlspecialchars(($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? '')); ?></h2>
                <p><?php echo htmlspecialchars($user_info['role'] ?? 'Resident'); ?></p>
            </div>
        </div>

        <div class="profile-grid">

            <div class="profile-group">
                <h3>Personal Information</h3>
                <div class="profile-field">
                    <label>First Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" readonly>
                </div>
                <div class="profile-field">
                    <label>Last Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" readonly>
                </div>
                <div class="profile-field">
                    <label>Purok</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['purok'] ?? ''); ?>" readonly>
                </div>
                <div class="profile-field">
                    <label>Address</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['address'] ?? ''); ?>" readonly>
                </div>
            </div>

            <div class="profile-group">
                <h3>Contact Information</h3>
                <div class="profile-field">
                    <label>Email</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" readonly>
                </div>
                <div class="profile-field">
                    <label>Phone Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>" readonly>
                </div>
                <div class="profile-field">
                    <label>Residency Years</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['residency_years'] ?? ''); ?>" readonly>
                </div>
            </div>

            <div class="profile-group">
                <h3>Account Information</h3>
                <div class="profile-field">
                    <label>Account Role</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['role'] ?? ''); ?>" readonly>
                </div>
                <div class="profile-field">
                    <label>Account Status</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['status'] ?? ''); ?>" readonly>
                </div>
                <div class="profile-field">
                    <label>Date Registered</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_info['created_at'] ?? ''); ?>" readonly>
                </div>
            </div>

        </div>
    </div>
</section>

    <!-- EDIT ADOPTION MODAL -->
   <!-- ADOPTION MODAL -->
<div id="adoptionModal" class="modal-backdrop" onclick="closeAdoptionModal(event)">
    <div class="modal-card">
        
        <div class="modal-header">
            <h3 id="modalPetName">Adopt Pet</h3>

            <button type="button"
                    class="btn-close-modal"
                    onclick="forceCloseAdoptionModal()">
                ✕
            </button>
        </div>

        <form class="report-form"
              action="submit_adoption_request.php"
              method="POST">

            <input type="hidden"
                   name="pet_id"
                   id="modalPetId">

            <div class="adoption-grid">

                <div>
                    <label>Full Name</label>
                    <input type="text"
                           name="applicant_name"
                           required>
                </div>

                <div>
                    <label>Phone Number</label>
                    <input type="text"
                           name="phone_number"
                           required>
                </div>

                <div class="full">
                    <label>Address</label>
                    <textarea name="address"
                              rows="2"
                              required></textarea>
                </div>

                <div>
                    <label>Household Members</label>
                    <input type="number"
                           name="household_members"
                           required>
                </div>

                <div>
                    <label>Do you have other pets?</label>

                    <select name="has_other_pets">
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </div>

               

                <div class="full">
                    <label>Experience with Pets</label>

                    <textarea name="experience_with_pets"
                              rows="2"></textarea>
                </div>

                <div class="full">
                    <label>Reason for Adoption</label>

                    <textarea name="reason_for_adoption"
                              rows="3"
                              required></textarea>
                </div>

             <div class="full" style="display: flex; flex-direction: column; gap: 8px; margin-top: 15px;">
    <label style="display: block; margin-bottom: 5px;">Agreement</label>
    
    <label class="checkbox-container" style="display: inline-flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem; color: rgba(255, 255, 255, 0.85); user-select: none;">
        <input type="checkbox" name="agree_home_visit" value="yes" required>
        <span class="checkmark"></span>
        <span class="checkbox-text" style="line-height: 1.2;">I agree to a home visit and post-adoption follow-up.</span>
    </label>
</div>
                </div>
              <button type="submit" class="btn-gold">Submit Adoption Request</button>
            </form>
        </div>
    </div>

            <!-- MINI DASHBOARD MODAL (for "Yes, This is My Pet") -->
            <div id="miniDashboardModal" class="modal-backdrop" onclick="if(event.target.id==='miniDashboardModal') closeMiniDashboardModal()">
                <div class="modal-card">
                    <div class="modal-header">
                        <h3 id="miniPetName">Pet Details</h3>
                        <button type="button" class="btn-close-modal" onclick="closeMiniDashboardModal()">✕</button>
                    </div>

                    <div style="padding:16px; display:flex; gap:16px; align-items:flex-start;">
                        <img id="miniPetImage" src="https://via.placeholder.com/300x180?text=No+Image" style="width:220px; height:auto; border-radius:6px; object-fit:cover;" />
                        <div style="flex:1">
                            <p id="miniPetMessage" style="opacity:0.9; margin:0 0 12px 0;"></p>

                            <div class="mini-schedule">
                                <h4>Schedule When You'll Claim</h4>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <div style="flex:1;">
                                        <label>Pickup Date</label>
                                        <input id="mini_pickup_date" type="date" required />
                                    </div>
                                    <div style="width:140px;">
                                        <label>Pickup Time</label>
                                        <input id="mini_pickup_time" type="time" required />
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex; gap:8px; margin-top:12px;">
                                <button id="miniConfirmBtn" class="btn primary" data-notif-id="" onclick="performConfirmFromModal()">Confirm Ownership & Schedule Pickup</button>
                                <button class="btn" onclick="closeMiniDashboardModal()">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </form>

    </div>
</div>
    <!-- PICKUP MODAL -->
    <div id="pickupModal" class="modal-backdrop" onclick="closePickupModal(event)">
        <div class="modal-card">
            <div class="modal-header"><h3>Schedule Pet Pickup</h3><button type="button" class="btn-close-modal" onclick="forceClosePickupModal()">✕</button></div>
            <form class="report-form" action="submit_pickup_schedule.php" method="POST">
                <input type="hidden" name="request_id" id="pickup_request_id">
                <input type="hidden" name="pet_id" id="pickup_pet_id">
                <div class="adoption-grid">
                    <div><label>Pickup Date</label><input type="date" name="pickup_date" id="pickup_date" required></div>
                    <div><label>Pickup Time</label><input type="time" name="pickup_time" id="pickup_time" required></div>
                </div>
                <button type="submit" class="btn-gold">Schedule Pickup</button>
            </form>
        </div>
    </div>

<?php include __DIR__ . '/donate_modal.php'; ?>

    <script>
function toggleNotifSidebar() {
    document.getElementById('notifSidebar').classList.toggle('active');
}

/**
 * Marks a notification as read in the UI and calls the backend.
 */
function markAsRead(notifId, element) {
    if (!element.classList.contains('unread')) return;

    fetch('mark_notification_read.php?id=' + notifId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                element.classList.remove('unread');

                const badge = document.querySelector('.notif-badge');
                if (badge) {
                    let count = parseInt(badge.innerText);
                    count--;
                    if (count <= 0) badge.remove();
                    else badge.innerText = count;
                }
            }
        })
        .catch(() => {
            element.classList.remove('unread');

            const badge = document.querySelector('.notif-badge');
            if (badge) {
                let count = parseInt(badge.innerText);
                count--;
                if (count <= 0) badge.remove();
                else badge.innerText = count;
            }
        });
}


/* =========================
   PET CONFIRM FUNCTION
========================= */
function confirmPet(event, notifId) {
    event.stopPropagation();

    const formData = new FormData();
    formData.append('notification_id', notifId);

    fetch('confirm_pet_found.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Admin has been notified that the owner was found.');
            location.reload();
        } else {
            alert(data.message || 'Something went wrong.');
        }
    })
    .catch(() => {
        alert('Request failed.');
    });
}

function confirmPet(event, notifId, button) {
    event.stopPropagation();

    const btn = button || event.currentTarget;
    const petId = btn ? btn.getAttribute('data-pet-id') : '';
    const petName = btn ? btn.getAttribute('data-pet-name') : '';
    const image = btn ? btn.getAttribute('data-image') : '';
    const message = btn ? btn.getAttribute('data-message') : '';

    const imgEl = document.getElementById('miniPetImage');
    const nameEl = document.getElementById('miniPetName');
    const msgEl = document.getElementById('miniPetMessage');
    const confirmBtn = document.getElementById('miniConfirmBtn');

    if (imgEl) imgEl.src = image || 'https://via.placeholder.com/300x180?text=No+Image';
    if (nameEl) nameEl.innerText = petName || 'Unknown Pet';
    if (msgEl) msgEl.innerText = message || '';
    if (confirmBtn) confirmBtn.setAttribute('data-notif-id', notifId);

    document.getElementById('miniDashboardModal').classList.add('active');
}

function closeMiniDashboardModal() {
    const m = document.getElementById('miniDashboardModal');
    if (m) m.classList.remove('active');
}

function performConfirmFromModal() {
    const btn = document.getElementById('miniConfirmBtn');
    if (!btn) return;
    const notifId = btn.getAttribute('data-notif-id');
    if (!notifId) return;

    const pickupDateEl = document.getElementById('mini_pickup_date');
    const pickupTimeEl = document.getElementById('mini_pickup_time');
    const pickupDate = pickupDateEl ? pickupDateEl.value : '';
    const pickupTime = pickupTimeEl ? pickupTimeEl.value : '';

    if (!pickupDate || !pickupTime) {
        alert('Please choose both pickup date and time before confirming your claim.');
        return;
    }

    const formData = new FormData();
    formData.append('notification_id', notifId);
    formData.append('pickup_date', pickupDate);
    formData.append('pickup_time', pickupTime);

    fetch('confirm_pet_found.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Admin has been notified that the owner was found.');
            location.reload();
        } else {
            alert(data.message || 'Something went wrong.');
        }
    })
    .catch(() => {
        alert('Request failed.');
    });
}


/* =========================
   REJECT PET FUNCTION
========================= */
function rejectPet(event, notifId) {
    event.preventDefault();
    event.stopPropagation();

    fetch('reject_pet_match.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'notification_id=' + notifId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Marked as Not Match / Not Found.');
        } else {
            alert(data.message || 'Something went wrong.');
        }
    })
    .catch(() => {
        alert('Request failed.');
    });
}


/* =========================
   NAVIGATION
========================= */
function showSection(sectionId, element) {
    document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-links a').forEach(l => l.classList.remove('active'));

    const target = document.getElementById(sectionId);
    if (target) target.classList.add('active');

    const navMap = {
        'home': 'nav-home',
        'report': 'nav-report',
        'my-reports': 'nav-history',
        'rescued': 'nav-rescued',
        'adoption-status': 'nav-adoption-status',
        'profile': 'nav-profile',
        'donation': 'nav-donation'
    };

    const navLink = document.getElementById(navMap[sectionId] || (element ? element.id : ''));
    if (navLink) navLink.classList.add('active');

    localStorage.setItem('activeTab', sectionId);
}


/* =========================
   ADOPTION MODAL
========================= */
function openAdoptionModal(button) {
    const petId = button.getAttribute('data-pet-id');
    const petName = button.getAttribute('data-pet-name');

    document.getElementById('modalPetId').value = petId;
    document.getElementById('modalPetName').innerText = "Adopt " + petName;

    document.getElementById('adoptionModal').classList.add('active');
}

function forceCloseAdoptionModal() {
    document.getElementById('adoptionModal').classList.remove('active');
}

function closeAdoptionModal(e) {
    if (e.target.id === 'adoptionModal') {
        forceCloseAdoptionModal();
    }
}


/* =========================
   PICKUP MODAL
========================= */
function openPickupModal(button) {
    document.getElementById('pickup_request_id').value = button.getAttribute('data-request-id') || '';
    document.getElementById('pickup_pet_id').value = button.getAttribute('data-pet-id') || '';
    document.getElementById('pickup_date').value = button.getAttribute('data-pickup-date') || '';
    document.getElementById('pickup_time').value = button.getAttribute('data-pickup-time') || '';

    document.getElementById('pickupModal').classList.add('active');
}

function forceClosePickupModal() {
    document.getElementById('pickupModal').classList.remove('active');
}

function closePickupModal(e) {
    if (e.target.id === 'pickupModal') {
        forceClosePickupModal();
    }
}


/* =========================
   DONATION FIX (IMPORTANT)
========================= */
function openDonateModal(button) {
    const petId = button.getAttribute('data-pet-id') || '';
    const petName = button.getAttribute('data-pet-name') || 'Rescued pet';
    const petBreed = button.getAttribute('data-pet-breed') || '';
    const petImage = button.getAttribute('data-pet-image') || '';

    document.getElementById('donate_modal_pet_id').value = petId;
    document.getElementById('donate_modal_pet_name').value = petName;
    document.getElementById('donateModalTitle').innerText = 'Donate for ' + petName;
    document.getElementById('donateModalPetName').innerText = petName;
    document.getElementById('donateModalPetBreed').innerText = petBreed;
    document.getElementById('donateModalImage').src = petImage || 'https://via.placeholder.com/88?text=Pet';

    const typeSel = document.getElementById('donate_modal_type');
    const amtBox = document.getElementById('donateModalAmountBox');
    const amtInput = document.getElementById('donate_modal_amount');
    if (typeSel) typeSel.value = '';
    if (amtBox) amtBox.style.display = 'none';
    if (amtInput) { amtInput.value = ''; amtInput.required = false; }

    document.getElementById('donateModal').classList.add('active');
}

function forceCloseDonateModal() {
    document.getElementById('donateModal').classList.remove('active');
}

function closeDonateModal(e) {
    if (e.target.id === 'donateModal') {
        forceCloseDonateModal();
    }
}

function toggleDonateModalAmount() {
    const type = document.getElementById('donate_modal_type').value;
    const container = document.getElementById('donateModalAmountBox');
    const amountInput = document.getElementById('donate_modal_amount');
    if (!container || !amountInput) return;
    if (type === 'money') {
        container.style.display = 'block';
        amountInput.required = true;
    } else {
        container.style.display = 'none';
        amountInput.required = false;
        amountInput.value = '';
    }
}

function toggleDonationAmount() {
    const type = document.getElementById('donation_type').value;
    const container = document.getElementById('amountContainer');
    const amountInput = document.getElementById('amount');

    if (!container || !amountInput) return;

    if (type === 'money') {
        container.style.display = 'block';
        amountInput.disabled = false;
        amountInput.required = true;
    } else {
        container.style.display = 'none';
        amountInput.disabled = true;
        amountInput.required = false;
        amountInput.value = '';
    }
}


/* =========================
   PAGE LOAD
========================= */
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.get('status') === 'success') {
        showSection('my-reports');
    } else if (urlParams.get('donation') === 'success') {
        showSection('donation', document.getElementById('nav-donation'));
    } else if (urlParams.get('adoption')) {
        showSection('adoption-status');
    } else {
        showSection(localStorage.getItem('activeTab') || 'home');
    }
};
</script>
</body>
</html>