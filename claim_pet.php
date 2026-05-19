<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

$user_id = $_SESSION['user_id'];
$notification_id = isset($_REQUEST['notification_id']) ? (int)$_REQUEST['notification_id'] : 0;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the notification data
    $stmt = $pdo->prepare("
        SELECT n.*, rp.pet_type, rp.breed, rp.image_path
        FROM notifications n
        JOIN rescued_pets rp ON n.rescued_pet_id = rp.id
        WHERE n.id = :notif_id AND n.user_id = :user_id
    ");
    $stmt->execute(['notif_id' => $notification_id, 'user_id' => $user_id]);
    $claim_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$claim_data) {
        die("Error: No valid claim records found or authorization missing.");
    }

    // Auto-generate verification code if empty
    $claim_code = !empty($claim_data['coupon_code']) ? $claim_data['coupon_code'] : "CLAIM-" . strtoupper(substr(md5($claim_data['id'] . $user_id), 0, 8));

    // --- HANDLE SCHEDULING FORM SUBMISSION ---
    $success_message = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_schedule'])) {
        $pickup_date = $_POST['pickup_date'];
        $pickup_time = $_POST['pickup_time'];

        if (!empty($pickup_date) && !empty($pickup_time)) {
            // Update the record with the chosen date, time, and code
            $update_stmt = $pdo->prepare("
                UPDATE notifications 
                SET pickup_date = :p_date, pickup_time = :p_time, coupon_code = :c_code, is_scheduled = 1 
                WHERE id = :notif_id
            ");
            $update_stmt->execute([
                'p_date'   => $pickup_date,
                'p_time'   => $pickup_time,
                'c_code'   => $claim_code,
                'notif_id' => $notification_id
            ]);

            require_once __DIR__ . '/pickup_sync.php';
            syncClaimToAdoptionPickups($pdo, $notification_id, 'scheduled');
            
            // FIXED: Fully synchronize local structure variables so layout flips to template view instantly
            $claim_data['pickup_date'] = $pickup_date;
            $claim_data['pickup_time'] = $pickup_time;
            $claim_data['coupon_code'] = $claim_code;
            $claim_data['is_scheduled'] = 1;
            $success_message = true;
        }
    }

} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Your Pet | PetConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #0a0a0b; 
            --accent-gold: #c48a3d; 
            --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.03); 
            --glass-border: rgba(255, 255, 255, 0.08);
            --success: #2ecc71;
            --blue: #3498db;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background-color: var(--bg-deep); 
            background-image: radial-gradient(circle at 50% 50%, #1a1a1c 0%, #0a0a0b 100%);
            color: var(--text-warm); font-family: 'Inter', sans-serif; 
            min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 40px 20px;
        }
        .claim-container {
            max-width: 650px; width: 100%; background: rgba(15, 15, 17, 0.95);
            border: 1px solid var(--glass-border); border-radius: 28px; padding: 40px;
            backdrop-filter: blur(20px); box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .icon-header { font-size: 3.5rem; text-align: center; margin-bottom: 15px; display: block; }
        .icon-success { color: var(--success); }
        .icon-calendar { color: var(--accent-gold); }
        .title { font-size: 2rem; font-weight: 800; color: #fff; text-align: center; margin-bottom: 10px; }
        .subtitle { font-size: 0.95rem; opacity: 0.7; text-align: center; margin-bottom: 35px; line-height: 1.5; }
        .voucher-card { background: var(--glass); border: 1px dashed var(--accent-gold); border-radius: 20px; padding: 25px; margin-bottom: 30px; }
        .pet-info-flex { display: flex; gap: 20px; align-items: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--glass-border); }
        .pet-thumb { width: 90px; height: 90px; border-radius: 16px; object-fit: cover; border: 1px solid var(--glass-border); }
        .pet-meta h3 { color: var(--accent-gold); font-size: 1.25rem; margin-bottom: 4px; }
        .pet-meta p { font-size: 0.85rem; opacity: 0.6; }
        
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--accent-gold); font-weight: 600; text-transform: uppercase; }
        .form-control { width: 100%; padding: 14px; background: rgba(255,255,255,0.02); border: 1px solid var(--glass-border); border-radius: 12px; color: #fff; font-size: 1rem; outline: none; }
        .form-control:focus { border-color: var(--accent-gold); }
        .form-control::-webkit-calendar-picker-indicator { filter: invert(0.6); cursor: pointer; }

        .coupon-box { text-align: center; background: rgba(196, 138, 61, 0.08); border: 1px solid rgba(196, 138, 61, 0.2); padding: 15px; border-radius: 12px; }
        .coupon-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--accent-gold); margin-bottom: 4px; font-weight: 600; }
        .coupon-code { font-family: monospace; font-size: 1.6rem; font-weight: bold; color: #fff; }
        
        .scheduled-time-info { display: flex; justify-content: space-around; margin-top: 15px; font-size: 1rem; border-top: 1px solid var(--glass-border); padding-top: 15px; }
        .scheduled-time-info span { font-weight: bold; color: #fff; }

        .btn-container { display: flex; gap: 15px; }
        .btn { flex: 1; padding: 14px 20px; border-radius: 12px; border: none; font-weight: 600; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; transition: 0.2s; }
        .btn-gold { background: var(--accent-gold); color: #000; }
        .btn-gold:hover { opacity: 0.9; }
        .btn-outline { background: transparent; border: 1px solid var(--glass-border); color: var(--text-warm); }
        .btn-outline:hover { background: rgba(255,255,255,0.02); color: #fff; }
    </style>
</head>
<body>

<div class="claim-container">
    
    <?php if (empty($claim_data['is_scheduled'])): ?>
        <i class="fas fa-calendar-alt icon-header icon-calendar"></i>
        <h1 class="title">Schedule Your Pickup</h1>
        <p class="subtitle">Please pinpoint your planned arrival window down below so our desk operators can prepare documentation checks.</p>

        <form method="POST">
            <input type="hidden" name="notification_id" value="<?= $notification_id ?>">
            
            <div class="voucher-card">
                <div class="pet-info-flex">
                    <img src="<?= htmlspecialchars($claim_data['image_path'] ? $claim_data['image_path'] : 'https://via.placeholder.com/300x180?text=No+Image'); ?>" class="pet-thumb">
                    <div class="pet-meta">
                        <h3><?= htmlspecialchars($claim_data['pet_type']); ?> Match Found</h3>
                        <p>Breed: <?= htmlspecialchars($claim_data['breed']); ?></p>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-calendar-day"></i> Choose Target Date</label>
                    <input type="date" name="pickup_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Choose Target Time Slot</label>
                    <input type="time" name="pickup_time" class="form-control" required>
                </div>
            </div>

            <button type="submit" name="submit_schedule" class="btn btn-gold" style="width: 100%;">
                <i class="fas fa-lock"></i> Lock Schedule & Generate Pass
            </button>
        </form>

    <?php else: ?>
        <i class="fas fa-check-circle icon-header icon-success"></i>
        <h1 class="title">Handover Slotted!</h1>
        <p class="subtitle">Your match verification tracking pipeline is ready. This pickup allocation has been mirrored directly to administrative screens.</p>

        <div class="voucher-card">
            <div class="pet-info-flex">
                <img src="<?= htmlspecialchars($claim_data['image_path'] ? $claim_data['image_path'] : 'https://via.placeholder.com/300x180?text=No+Image'); ?>" class="pet-thumb">
                <div class="pet-meta">
                    <h3>Identified Intake: <?= htmlspecialchars($claim_data['pet_type']); ?></h3>
                    <p>Breed / Trait: <?= htmlspecialchars($claim_data['breed']); ?></p>
                </div>
            </div>
            
            <div class="coupon-box">
                <div class="coupon-label">Shelter Verification Pickup Code</div>
                <div class="coupon-code"><?= htmlspecialchars($claim_data['coupon_code']); ?></div>
            </div>

            <div class="scheduled-time-info">
                <div><i class="fas fa-calendar" style="color:var(--blue)"></i> Date: <span><?= date('M d, Y', strtotime($claim_data['pickup_date'])) ?></span></div>
                <div><i class="fas fa-clock" style="color:var(--success)"></i> Time: <span><?= date('h:i A', strtotime($claim_data['pickup_time'])) ?></span></div>
            </div>
        </div>

        <div class="btn-container">
            <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print Voucher</button>
            <a href="resident_dashboard.php" class="btn btn-gold"><i class="fas fa-home"></i> Dashboard Home</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>