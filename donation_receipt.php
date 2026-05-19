<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pending_donation'])) {
    header('Location: resident_dashboard.php');
    exit();
}

require_once __DIR__ . '/donation_helpers.php';

$data = $_SESSION['pending_donation'];
$typeLabel = formatDonationTypeLabel($data['donation_type'] ?? '');
$isMoney = ($data['donation_type'] ?? '') === 'money';
$petName = $data['pet_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Receipt | PetConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0a0b;
            color: #d8d2cb;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .receipt {
            background: linear-gradient(155deg, #1a1a1c, #111113);
            padding: 32px;
            border-radius: 20px;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(196, 138, 61, 0.35);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
        }
        h2 { color: #c48a3d; margin: 0 0 8px; }
        .subtitle { opacity: 0.7; font-size: 0.9rem; margin-bottom: 24px; }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.95rem;
        }
        .row strong { color: #fff; }
        .pet-banner {
            background: rgba(196, 138, 61, 0.12);
            border: 1px solid rgba(196, 138, 61, 0.3);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .btn {
            margin-top: 24px;
            padding: 14px;
            background: linear-gradient(135deg, #d4a057, #c48a3d);
            color: #0a0a0b;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            font-weight: 800;
            font-size: 0.95rem;
        }
        .btn:hover { opacity: 0.92; }
        .btn-link {
            display: block;
            text-align: center;
            margin-top: 12px;
            color: #888;
            text-decoration: none;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<div class="receipt">
    <h2>🎁 Donation Preview</h2>
    <p class="subtitle">Review before saving to your donation history.</p>

    <?php if ($petName !== ''): ?>
        <div class="pet-banner">
            <strong style="color: #c48a3d;">Supporting:</strong>
            <?= htmlspecialchars($petName) ?>
        </div>
    <?php endif; ?>

    <div class="row"><span>Type</span><strong><?= htmlspecialchars($typeLabel) ?></strong></div>
    <?php if ($isMoney): ?>
        <div class="row"><span>Amount</span><strong>₱<?= number_format((float) ($data['amount'] ?? 0), 2) ?></strong></div>
    <?php endif; ?>
    <?php if (!empty($data['message'])): ?>
        <div class="row" style="flex-direction: column; align-items: flex-start;">
            <span>Message</span>
            <strong style="margin-top: 6px; font-weight: 500;"><?= nl2br(htmlspecialchars($data['message'])) ?></strong>
        </div>
    <?php endif; ?>

    <button type="button" class="btn" onclick="confirmDonation()">Confirm Donation</button>
    <a href="resident_dashboard.php" class="btn-link">Cancel and go back</a>
</div>

<script>
function confirmDonation() {
    const btn = document.querySelector('.btn');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    fetch('confirm_donation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'resident_dashboard.php?donation=success';
        } else {
            alert(data.message || 'Error occurred');
            btn.disabled = false;
            btn.textContent = 'Confirm Donation';
        }
    })
    .catch(() => {
        alert('Request failed. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Confirm Donation';
    });
}
</script>

</body>
</html>
