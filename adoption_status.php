<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'] ?? 0;
$my_adoption_requests = [];

try {
    $pdo = new PDO("mysql:host=localhost;dbname=adbsystemm;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT ar.*, rp.pet_type AS pet_name, ap.pickup_date, ap.pickup_time, ap.coupon_code FROM adoption_requests ar LEFT JOIN rescued_pets rp ON rp.id = ar.pet_id LEFT JOIN adoption_pickups ap ON ap.request_id = ar.id WHERE ar.resident_id = ? ORDER BY ar.id DESC");
    $stmt->execute([$user_id]);
    $my_adoption_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $my_adoption_request_count = count($my_adoption_requests);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Adoption Status - PetConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-deep: #0a0a0b; --accent-gold: #c48a3d; --text-warm: #d8d2cb; --glass: rgba(255, 255, 255, 0.03); --glass-border: rgba(255, 255, 255, 0.08); --success: #2ecc71; --danger: #ff6b6b;}
        body { background-color: var(--bg-deep); color: var(--text-warm); font-family: 'Inter', sans-serif; padding: 40px; }
        .card { background: var(--glass); padding: 30px; border-radius: 28px; border: 1px solid var(--glass-border); }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { padding: 15px; border-bottom: 1px solid var(--glass-border); text-align: left; }
        .status-approved { color: var(--success); }
        .status-rejected { color: var(--danger); }
        .status-pending { color: #f1c40f; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.75); display: none; align-items: center; justify-content: center; z-index: 2500; }
        .modal-backdrop.active { display: flex; }
        .modal-card { width: 500px; background: #131316; border: 1px solid var(--glass-border); border-radius: 20px; padding: 24px; }
        .btn-gold { background: var(--accent-gold); color: #000; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h2>💌 My Adoption Requests</h2>
        <table class="report-table">
            <thead>
                <tr><th>Pet</th><th>Status</th><th>Pickup Date</th><th>Coupon Code</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($my_adoption_requests as $request): ?>
                    <?php $status = strtolower($request['status'] ?? 'pending'); ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($request['pet_name'] ?? 'Pet'); ?></strong></td>
                        <td><strong class="status-<?php echo $status; ?>"><?php echo ucfirst($status); ?></strong></td>
                        <td><?php echo $request['pickup_date'] ?: '—'; ?></td>
                        <td><code><?php echo $request['coupon_code'] ?: '—'; ?></code></td>
                        <td>
                            <?php if ($status === 'approved'): ?>
                                <span style="color: #2ecc71; font-weight: 700;">Scheduled</span>
                            <?php else: echo '—'; endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (isset($my_adoption_request_count) && $my_adoption_request_count === 0): ?>
            <p style="color:#d8d2cb; opacity:0.8; margin-top: 16px; font-size: 0.95rem; text-align:center;">
                No adoption requests found for your current account (resident_id = <?php echo htmlspecialchars($user_id); ?>).
            </p>
        <?php endif; ?>
    </div>

    <div id="pickupModal" class="modal-backdrop" onclick="if(event.target.id === 'pickupModal') this.classList.remove('active')">
        <div class="modal-card">
            <h3>Schedule Pet Pickup</h3>
            <form action="submit_pickup_schedule.php" method="POST">
                <input type="hidden" name="request_id" id="pickup_request_id">
                <input type="hidden" name="pet_id" id="pickup_pet_id">
                <label style="color:var(--accent-gold)">Pickup Date</label>
                <input type="date" name="pickup_date" style="width:100%; padding:10px; margin: 10px 0;" required>
                <label style="color:var(--accent-gold)">Pickup Time</label>
                <input type="time" name="pickup_time" style="width:100%; padding:10px; margin: 10px 0;" required>
                <button type="submit" class="btn-gold" style="width:100%; margin-top:10px;">Confirm Schedule</button>
            </form>
        </div>
    </div>

    <script>
        function openPickupModal(btn) {
            document.getElementById('pickup_request_id').value = btn.getAttribute('data-request-id');
            document.getElementById('pickup_pet_id').value = btn.getAttribute('data-pet-id');
            document.getElementById('pickupModal').classList.add('active');
        }
    </script>
</body>
</html>