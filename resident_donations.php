<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'] ?? 0;
$my_donations = [];

try {
    $pdo = new PDO("mysql:host=localhost;dbname=adbsystemm;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM donations WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $my_donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donations - PetConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-deep: #0a0a0b; --accent-gold: #c48a3d; --text-warm: #d8d2cb; --glass: rgba(255, 255, 255, 0.03); --glass-border: rgba(255, 255, 255, 0.08); }
        body { background-color: var(--bg-deep); color: var(--text-warm); font-family: 'Inter', sans-serif; padding: 40px; }
        .card { background: var(--glass); padding: 30px; border-radius: 28px; border: 1px solid var(--glass-border); margin-bottom: 20px;}
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stat-card { background: rgba(255,255,255,0.02); padding: 20px; border-radius: 20px; border: 1px solid var(--glass-border); text-align: center; }
        .btn-gold { background: var(--accent-gold); color: #000; border: none; padding: 14px; border-radius: 12px; font-weight: bold; width: 100%; cursor: pointer; text-transform: uppercase;}
        input, select, textarea { width:100%; padding: 12px; margin-bottom: 15px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: #fff; border-radius: 10px; box-sizing: border-box;}
    </style>
</head>
<body>
    <div class="card">
        <h2>🎁 Donate for Rescued Pets</h2>
        <div class="stats-grid">
            <div class="stat-card"><i class="fas fa-dog" style="font-size:2rem; color: var(--accent-gold);"></i><h3>Dog Food</h3></div>
            <div class="stat-card"><i class="fas fa-cat" style="font-size:2rem; color: var(--accent-gold);"></i><h3>Cat Food</h3></div>
            <div class="stat-card"><i class="fas fa-pills" style="font-size:2rem; color: var(--accent-gold);"></i><h3>Vitamins</h3></div>
            <div class="stat-card"><i class="fas fa-hand-holding-heart" style="font-size:2rem; color: var(--accent-gold);"></i><h3>Supplies</h3></div>
        </div>
    </div>

    <div class="card">
        <form action="submit_donation.php" method="POST">
            <label>Donation Type</label>
            <select name="donation_type" id="donation_type" onchange="if(this.value==='money'){document.getElementById('amtBox').style.display='block'}else{document.getElementById('amtBox').style.display='none'}" required>
                <option value="">Select</option>
                <option value="dog_food">Dog Food</option>
                <option value="cat_food">Cat Food</option>
                <option value="money">Money</option>
            </select>

            <div id="amtBox" style="display:none;"><label>Amount (₱)</label><input type="number" name="amount" min="1"></div>
            <label>Message</label><textarea name="message" rows="2"></textarea>
            <button type="submit" class="btn-gold">Donate Now</button>
        </form>
    </div>

    <div class="card">
        <h3>📜 My Donation Receipts</h3>
        <?php foreach ($my_donations as $d): ?>
            <div style="background: rgba(255,255,255,0.02); padding: 15px; border-radius: 12px; margin-bottom: 10px;">
                <p><strong>Type:</strong> <?= htmlspecialchars($d['donation_type']) ?> | <strong>Receipt No:</strong> <?= htmlspecialchars($d['receipt_number']) ?></p>
                <?php if($d['donation_type'] === 'money'): ?><p><strong>Amount:</strong> ₱<?= number_format($d['amount'], 2) ?></p><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>