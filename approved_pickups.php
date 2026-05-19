<?php
session_start();

// --- SECURITY CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    require_once __DIR__ . '/pickup_sync.php';
    runClaimPickupSync($pdo);

    // --- Search & Filter Handle ---
    $search = $_GET['search'] ?? '';
    $date_filter = $_GET['date'] ?? '';

    // Core Query Base
    // FIXED: Changed rp.name to rp.pet_type to match updated database schema alias
    $query = "
        SELECT 
            ap.id,
            ap.pickup_date,
            ap.pickup_time,
            ap.coupon_code,
            ap.pickup_status,
            ap.created_at as scheduled_at,
            ar.applicant_name,
            ar.phone_number,
            rp.pet_type as pet_name,
            rp.breed as pet_breed,
            'adoption' AS row_source
        FROM adoption_pickups ap
        JOIN adoption_requests ar ON ap.request_id = ar.id
        JOIN rescued_pets rp ON ar.pet_id = rp.id
        WHERE ap.pickup_status = 'scheduled'
    ";
    
    $params = [];
    if (!empty($search)) {
        // FIXED: Changed rp.name to rp.pet_type in search constraints
        $query .= " AND (ar.applicant_name LIKE :search OR rp.pet_type LIKE :search OR rp.breed LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if (!empty($date_filter)) {
        $query .= " AND ap.pickup_date = :pickup_date";
        $params[':pickup_date'] = $date_filter;
    }

    $query .= " ORDER BY ap.pickup_date ASC, ap.pickup_time ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $pickups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Include scheduled claims from lost report notifications
    $claimsQuery = "
        SELECT
            n.id,
            n.pickup_date,
            n.pickup_time,
            n.coupon_code,
            'scheduled' AS pickup_status,
            n.created_at AS scheduled_at,
            CONCAT(u.first_name, ' ', u.last_name) AS applicant_name,
            u.phone AS phone_number,
            rp.pet_type AS pet_name,
            rp.breed AS pet_breed,
            'claim' AS row_source
        FROM notifications n
        JOIN users u ON u.id = n.user_id
        JOIN rescued_pets rp ON rp.id = n.rescued_pet_id
        WHERE n.is_scheduled = 1
          AND (n.message IS NULL OR n.message NOT LIKE '%[PICKED_UP]%')
    ";

    $claimParams = [];
    if (!empty($search)) {
        $claimsQuery .= " AND (u.first_name LIKE :claim_search OR u.last_name LIKE :claim_search OR rp.pet_type LIKE :claim_search OR rp.breed LIKE :claim_search)";
        $claimParams[':claim_search'] = "%$search%";
    }
    if (!empty($date_filter)) {
        $claimsQuery .= " AND n.pickup_date = :claim_pickup_date";
        $claimParams[':claim_pickup_date'] = $date_filter;
    }

    $claimsQuery .= " ORDER BY n.pickup_date ASC, n.pickup_time ASC";
    $stmtClaims = $pdo->prepare($claimsQuery);
    $stmtClaims->execute($claimParams);
    $claimPickups = $stmtClaims->fetchAll(PDO::FETCH_ASSOC);

    $pickups = array_merge($pickups, $claimPickups);
    usort($pickups, function($a, $b) {
        $aStamp = ($a['pickup_date'] ?? '') . ' ' . ($a['pickup_time'] ?? '');
        $bStamp = ($b['pickup_date'] ?? '') . ' ' . ($b['pickup_time'] ?? '');
        return strcmp($aStamp, $bStamp);
    });

    // Statistics Tracker (Static base value parsing vs today's date)
    $today = date('Y-m-d');
    $pickupsToday = 0;
    $totalPickups = count($pickups);

    foreach($pickups as $p) {
        if ($p['pickup_date'] == $today) {
            $pickupsToday++;
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
    <title>Approved Pickups | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #0a0a0b; 
            --accent-gold: #c48a3d; 
            --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.03); 
            --glass-border: rgba(255, 255, 255, 0.08);
            --sidebar-width: 260px; 
            --danger: #ff6b6b; 
            --success: #2ecc71; 
            --blue: #3498db;
        }

        body { 
            background-color: var(--bg-deep); 
            background-image: radial-gradient(circle at 50% 50%, #1a1a1c 0%, #0a0a0b 100%);
            color: var(--text-warm); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            display: flex; 
            min-height: 100vh;
        }
        
        /* --- SIDEBAR --- */
        .sidebar { 
            width: var(--sidebar-width); 
            background: rgba(15, 15, 17, 0.95); 
            border-right: 1px solid var(--glass-border); 
            padding: 40px 20px; 
            position: fixed; 
            height: 100vh; 
            box-sizing: border-box; 
            z-index: 100;
        }
        .nav-links { list-style: none; padding: 0; }
        .nav-links a { 
            text-decoration: none; color: #888; padding: 14px 20px; 
            display: flex; align-items: center; gap: 15px; border-radius: 12px; 
            transition: 0.3s; 
        }
        .nav-links a:hover, .nav-links a.active { 
            background: rgba(196, 138, 61, 0.1); 
            color: var(--accent-gold); 
            border-left: 4px solid var(--accent-gold); 
        }

        /* --- MAIN CONTENT --- */
        .main-content { 
            flex: 1; 
            margin-left: var(--sidebar-width); 
            padding: 50px; 
            width: calc(100% - var(--sidebar-width)); 
        }

        .page-header { margin-bottom: 30px; }
        .page-title { font-size: 32px; font-weight: bold; color: #fff; margin: 0; }
        
        /* --- IMAGE-MATCHED FILTER PANEL BAR --- */
        .header-actions { 
            display: flex; 
            gap: 16px; 
            margin: 30px 0; 
            align-items: center; 
            width: 100%;
        }
        .search-bar { 
            flex: 2;
            background: rgba(255, 255, 255, 0.02); 
            border: 1px solid rgba(255, 255, 255, 0.07); 
            padding: 14px 20px; 
            border-radius: 12px; 
            color: #ffffff; 
            font-size: 0.95rem;
            outline: none; 
            transition: border-color 0.2s;
        }
        .filter-date { 
            flex: 2;
            background: rgba(255, 255, 255, 0.02); 
            border: 1px solid rgba(255, 255, 255, 0.07); 
            color: rgba(255, 255, 255, 0.8);
            padding: 14px 20px; 
            border-radius: 12px; 
            font-size: 0.95rem;
            outline: none; 
            cursor: pointer;
        }
        .search-bar:focus, .filter-date:focus {
            border-color: rgba(196, 138, 61, 0.4);
        }
        .filter-date::-webkit-calendar-picker-indicator { 
            filter: invert(0.6); 
            cursor: pointer; 
        } 
        .btn-filter { 
            background: var(--accent-gold); 
            color: #000000; 
            border: none; 
            padding: 14px 35px; 
            border-radius: 12px; 
            font-weight: 600; 
            font-size: 0.95rem;
            cursor: pointer; 
            transition: opacity 0.2s;
        }
        .btn-filter:hover { opacity: 0.9; }

        /* --- STATS DISPLAY --- */
        .stats-banner {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-block { display: flex; flex-direction: column; }
        .stat-number { font-size: 36px; font-weight: 800; color: var(--accent-gold); }
        .stat-label { font-size: 12px; text-transform: uppercase; opacity: 0.5; letter-spacing: 1px; }

        /* --- DATA TABLE --- */
        .table-card {
            background: var(--glass);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 20px;
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: var(--accent-gold); border-bottom: 1px solid var(--glass-border); font-size: 0.85rem; }
        td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 0.9rem; }

        .coupon-badge {
            background: rgba(196, 138, 61, 0.15);
            color: var(--accent-gold);
            padding: 5px 10px;
            border-radius: 6px;
            font-family: monospace;
            font-weight: bold;
            border: 1px dashed var(--accent-gold);
        }

        .date-badge { color: var(--blue); font-weight: 600; }
        .time-badge { color: var(--success); font-weight: 600; }

        .btn-pickup {
            padding: 10px 14px;
            background: rgba(52, 152, 219, 0.15);
            border: 1px solid #3498db;
            color: #3498db;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.78rem;
            line-height: 1.3;
            transition: all 0.2s;
            cursor: pointer;
            font-family: inherit;
            display: inline-block;
            text-align: center;
            white-space: normal;
            max-width: 200px;
        }
        .btn-pickup:hover {
            background: #3498db;
            color: white;
        }
        .picked-badge {
            color: #2ecc71;
            font-weight: bold;
        }

        /* --- PICKUP CONFIRM MINI-DASHBOARD --- */
        .pickup-confirm-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.72);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .pickup-confirm-backdrop.is-open {
            opacity: 1;
            visibility: visible;
        }
        .pickup-confirm-panel {
            width: 100%;
            max-width: 440px;
            background: linear-gradient(155deg, rgba(22, 22, 26, 0.98) 0%, rgba(12, 12, 14, 0.99) 100%);
            border: 1px solid rgba(196, 138, 61, 0.35);
            border-radius: 24px;
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.04) inset,
                0 24px 80px rgba(0, 0, 0, 0.65),
                0 0 60px rgba(196, 138, 61, 0.08);
            overflow: hidden;
            transform: translateY(12px) scale(0.98);
            transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .pickup-confirm-backdrop.is-open .pickup-confirm-panel {
            transform: translateY(0) scale(1);
        }
        .pickup-confirm-hero {
            padding: 28px 28px 20px;
            text-align: center;
            background: radial-gradient(ellipse 80% 120% at 50% -20%, rgba(196, 138, 61, 0.22), transparent 55%);
        }
        .pickup-confirm-icon .fas {
            color: var(--accent-gold);
        }
        .pickup-confirm-hero h2 {
            margin: 0 0 8px;
            font-size: 1.35rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.02em;
        }
        .pickup-confirm-hero p {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text-warm);
            opacity: 0.85;
            line-height: 1.45;
        }
        .pickup-confirm-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 0 24px 24px;
        }
        .pickup-confirm-tile {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 12px 14px;
        }
        .pickup-confirm-tile.span-2 {
            grid-column: span 2;
        }
        .pickup-confirm-tile label {
            display: block;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--accent-gold);
            opacity: 0.85;
            margin-bottom: 6px;
            font-weight: 600;
        }
        .pickup-confirm-tile .value {
            font-size: 0.9rem;
            color: #fff;
            font-weight: 600;
            word-break: break-word;
        }
        .pickup-confirm-tile .value.sub {
            font-weight: 500;
            opacity: 0.65;
            font-size: 0.82rem;
        }
        .pickup-confirm-tile .value.mono {
            font-family: ui-monospace, monospace;
            color: var(--accent-gold);
            font-size: 0.85rem;
        }
        .pickup-confirm-actions {
            display: flex;
            gap: 12px;
            padding: 0 24px 28px;
        }
        .pickup-confirm-actions button {
            flex: 1;
            padding: 14px 18px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            font-family: inherit;
            transition: transform 0.15s ease, box-shadow 0.2s ease, opacity 0.2s;
        }
        .pickup-confirm-actions button:active {
            transform: scale(0.98);
        }
        .btn-pickup-cancel {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: var(--text-warm);
        }
        .btn-pickup-cancel:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.25);
        }
        .btn-pickup-confirm {
            border: none;
            background: linear-gradient(135deg, #d4a057 0%, var(--accent-gold) 45%, #a66f2e 100%);
            color: #0a0a0b;
            box-shadow: 0 8px 24px rgba(196, 138, 61, 0.35);
            border-radius: 9999px;
        }
        .btn-pickup-confirm:hover {
            box-shadow: 0 10px 32px rgba(196, 138, 61, 0.45);
        }
    </style>
    <link rel="stylesheet" href="admin_sidebar.css">
</head>
<body class="admin-app">

<?php include __DIR__ . '/admin_sidebar.php'; ?>
<main class="main-content">
    <header class="page-header">
        <h1 class="page-title">📅 Approved Pickups</h1>
        <p style="opacity: 0.6;">Monitoring scheduled pet handovers to residents.</p>
    </header>

    <form method="GET" class="header-actions">
        <input type="text" name="search" class="search-bar" placeholder="Search by name or breed..." value="<?= htmlspecialchars($search) ?>">
        <input type="date" name="date" class="filter-date" value="<?= htmlspecialchars($date_filter) ?>">
        <button type="submit" class="btn-filter">Filter</button>
    </form>

    <div class="stats-banner">
        <div class="stat-block">
            <span class="stat-number"><?= $pickupsToday ?></span>
            <span class="stat-label">Scheduled Today</span>
        </div>
        <div class="stat-block">
            <span class="stat-number"><?= $totalPickups ?></span>
            <span class="stat-label">Total Handover Queue</span>
        </div>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Adopter</th>
                    <th>Pet Details</th>
                    <th>Coupon Code</th>
                    <th>Contact</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pickups)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; opacity: 0.5;">No scheduled pickups found matching filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($pickups as $p): ?>
                        <?php
                            $dtLabel = '—';
                            if (!empty($p['pickup_date']) && !empty($p['pickup_time'])) {
                                $dtLabel = date('M d, Y', strtotime($p['pickup_date'])) . ' · ' . date('h:i A', strtotime($p['pickup_time']));
                            }
                        ?>
                        <tr>
                            <td>
                                <span class="date-badge"><?= date('M d, Y', strtotime($p['pickup_date'])) ?></span><br>
                                <span class="time-badge"><?= date('h:i A', strtotime($p['pickup_time'])) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($p['applicant_name']) ?></strong>
                            </td>
                            <td>
                                <span style="color: var(--accent-gold); font-weight: bold;"><?= htmlspecialchars($p['pet_name']) ?></span><br>
                                <small style="opacity: 0.6;"><?= htmlspecialchars($p['pet_breed']) ?></small>
                            </td>
                            <td>
                                <span class="coupon-badge"><?= htmlspecialchars($p['coupon_code']) ?></span>
                            </td>
                            <td>
                                <i class="fas fa-phone" style="font-size: 0.8rem; margin-right: 5px;"></i> <?= htmlspecialchars($p['phone_number']) ?>
                            </td>
                            <td>
                                <?php if ($p['pickup_status'] === 'scheduled'): ?>
                                    <button type="button"
                                       class="btn-pickup"
                                       data-pickup-id="<?= (int) $p['id'] ?>"
                                       data-source="<?= htmlspecialchars($p['row_source'] ?? 'adoption', ENT_QUOTES, 'UTF-8') ?>"
                                       data-adopter="<?= htmlspecialchars($p['applicant_name'], ENT_QUOTES, 'UTF-8') ?>"
                                       data-pet="<?= htmlspecialchars($p['pet_name'], ENT_QUOTES, 'UTF-8') ?>"
                                       data-breed="<?= htmlspecialchars($p['pet_breed'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-coupon="<?= htmlspecialchars($p['coupon_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-phone="<?= htmlspecialchars($p['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       data-datetime="<?= htmlspecialchars($dtLabel, ENT_QUOTES, 'UTF-8') ?>"
                                       onclick="openMarkPickupModal(this)">
                                        Mark this pet as picked up
                                    </button>
                                <?php else: ?>
                                    <span class="picked-badge">✔ Already Picked Up</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div id="markPickupModal" class="pickup-confirm-backdrop" role="dialog" aria-modal="true" aria-labelledby="markPickupTitle" aria-hidden="true">
    <div class="pickup-confirm-panel" onclick="event.stopPropagation()">
        <div class="pickup-confirm-hero">
            <div class="pickup-confirm-icon" aria-hidden="true"><i class="fas fa-clipboard-check"></i></div>
            <h2 id="markPickupTitle">Mark this pet as picked up</h2>
            <p>Residents collect pets at handover—you only confirm here after they have left with the animal. This saves <strong style="color: var(--accent-gold);">picked_up</strong> on their pickup record in the database.</p>
        </div>
        <div class="pickup-confirm-grid">
            <div class="pickup-confirm-tile span-2">
                <label>Adopter</label>
                <div class="value" id="markPickupAdopter"></div>
            </div>
            <div class="pickup-confirm-tile">
                <label>Pet</label>
                <div class="value" id="markPickupPet"></div>
                <div class="value sub" id="markPickupBreed"></div>
            </div>
            <div class="pickup-confirm-tile">
                <label>Scheduled</label>
                <div class="value" id="markPickupWhen" style="font-size:0.82rem;"></div>
            </div>
            <div class="pickup-confirm-tile">
                <label>Coupon</label>
                <div class="value mono" id="markPickupCoupon"></div>
            </div>
            <div class="pickup-confirm-tile">
                <label>Contact</label>
                <div class="value" id="markPickupPhone" style="font-size:0.85rem;"></div>
            </div>
        </div>
        <div class="pickup-confirm-actions">
            <button type="button" class="btn-pickup-cancel" onclick="closeMarkPickupModal()">Cancel</button>
            <button type="button" class="btn-pickup-confirm" id="markPickupConfirmBtn" onclick="confirmMarkPickup()">Confirm — save to database</button>
        </div>
    </div>
</div>

<script>
(function () {
    var backdrop = document.getElementById('markPickupModal');
    var pendingId = null;
    var pendingSource = 'adoption';
    var pendingTriggerEl = null;

    window.openMarkPickupModal = function (btn) {
        pendingId = btn.getAttribute('data-pickup-id');
        pendingSource = btn.getAttribute('data-source') || 'adoption';
        pendingTriggerEl = btn;
        document.getElementById('markPickupAdopter').textContent = btn.getAttribute('data-adopter') || '—';
        document.getElementById('markPickupPet').textContent = btn.getAttribute('data-pet') || '—';
        document.getElementById('markPickupBreed').textContent = btn.getAttribute('data-breed') || '';
        document.getElementById('markPickupCoupon').textContent = btn.getAttribute('data-coupon') || '—';
        document.getElementById('markPickupPhone').textContent = btn.getAttribute('data-phone') || '—';
        document.getElementById('markPickupWhen').textContent = btn.getAttribute('data-datetime') || '—';
        backdrop.classList.add('is-open');
        backdrop.setAttribute('aria-hidden', 'false');
        document.getElementById('markPickupConfirmBtn').focus();
    };

    window.closeMarkPickupModal = function () {
        backdrop.classList.remove('is-open');
        backdrop.setAttribute('aria-hidden', 'true');
        pendingId = null;
        pendingSource = 'adoption';
        pendingTriggerEl = null;
        var c = document.getElementById('markPickupConfirmBtn');
        if (c) {
            c.disabled = false;
            c.textContent = 'Confirm — save to database';
        }
    };

    window.confirmMarkPickup = function () {
        if (!pendingId || !pendingTriggerEl) {
            return;
        }
        var confirmBtn = document.getElementById('markPickupConfirmBtn');
        var actionTd = pendingTriggerEl.closest('td');
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Saving…';

        var url = 'mark_pickup.php?id=' + encodeURIComponent(pendingId)
            + '&type=' + encodeURIComponent(pendingSource)
            + '&ajax=1';
        fetch(url, { method: 'GET', credentials: 'same-origin' })
            .then(function (res) {
                return res.text().then(function (text) {
                    var data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('Unexpected response from server.');
                    }
                    if (!res.ok || !data.success) {
                        var msg = (data && data.message) ? data.message : 'Could not mark pickup.';
                        throw new Error(msg);
                    }
                    return data;
                });
            })
            .then(function () {
                if (actionTd) {
                    actionTd.innerHTML = '<span class="picked-badge">✔ Already Picked Up</span>';
                }
                closeMarkPickupModal();
            })
            .catch(function (err) {
                alert(err.message || 'Something went wrong.');
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Confirm';
            });
    };

    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) {
            closeMarkPickupModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && backdrop.classList.contains('is-open')) {
            closeMarkPickupModal();
        }
    });
})();
</script>

</body>
</html>