<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all rescued pets for the matching logic
    // FIXED: Selected rp.pet_type AS name to match updated schema modifications safely
    $stmt_rescued = $pdo->query("SELECT id, pet_type AS name, breed, image_path FROM rescued_pets ORDER BY pet_type ASC");
    $rescued_pets = $stmt_rescued->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Lost Reports
    $sql = "SELECT r.*, u.first_name, u.last_name, u.phone 
            FROM lost_reports r 
            JOIN users u ON r.user_id = u.id 
            WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (r.animal_type LIKE :search OR r.description LIKE :search OR r.location LIKE :search)";
        $params['search'] = "%$search%";
    }
    if (!empty($filter_date)) {
        $sql .= " AND DATE(r.date_submitted) = :filter_date";
        $params['filter_date'] = $filter_date;
    }

    $sql .= " ORDER BY r.date_submitted DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) { 
    die("Database Error: " . $e->getMessage()); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Pet Registry | Admin</title>
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
            --warning: #f1c40f;
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
        
        /* --- IMAGE-MATCHED FILTER PANEL BAR --- */
        .header-actions { 
            display: flex; 
            gap: 16px; 
            margin: 30px 0 40px 0; 
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

        /* --- CARDS GRID --- */
        .reports-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 25px; }
        .report-card { background: var(--glass); border: 1px solid var(--glass-border); border-radius: 24px; overflow: hidden; display: flex; flex-direction: column; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .report-card:hover { transform: translateY(-5px); border-color: var(--accent-gold); }
        .card-img { width: 100%; height: 220px; object-fit: cover; background: #121214; }
        .card-body { padding: 20px; flex-grow: 1; }
        
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: rgba(241,196,15,0.1); color: var(--warning); border: 1px solid var(--warning); }
        .status-approved { background: rgba(46,204,113,0.1); color: var(--success); border: 1px solid var(--success); }
        .status-found { background: #c48a3d; color: #000; border: 1px solid #c48a3d; }

        .btn-action { width: 100%; padding: 12px; border-radius: 10px; border: none; cursor: pointer; font-weight: bold; margin-top: 10px; transition: 0.2s; }
        .btn-gold { background: var(--accent-gold); color: #000; }
        .btn-gold:hover { filter: brightness(1.1); }
        
        .match-section { margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--glass-border); }
        select { width: 100%; padding: 12px; background: #0a0a0b; color: #fff; border: 1px solid var(--glass-border); border-radius: 8px; margin-top: 5px; outline: none; }
        select:focus { border-color: var(--accent-gold); }
    </style>
    <link rel="stylesheet" href="admin_sidebar.css">
</head>
<body class="admin-app">

<?php include __DIR__ . '/admin_sidebar.php'; ?>
<main class="main-content">
    <header>
        <h1 style="margin: 0; font-size: 2.5rem; color: #fff;">🔍 Lost Pet Registry</h1>
        <p style="opacity: 0.6; margin-top: 5px;">Manage lost animal cases submitted by residents and cross-match with shelter intakes.</p>
    </header>
    
    <form method="GET" class="header-actions">
        <input type="text" name="search" class="search-bar" placeholder="Search by type, description or location..." value="<?= htmlspecialchars($search) ?>">
        <input type="date" name="filter_date" class="filter-date" value="<?= htmlspecialchars($filter_date) ?>">
        <button type="submit" class="btn-filter">Filter</button>
    </form>
    
    <div class="reports-grid">
        <?php if (empty($reports)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; opacity: 0.4;">
                <h3>No lost pet reports matched your selection.</h3>
            </div>
        <?php else: ?>
            <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <img src="uploads/<?= htmlspecialchars($report['image']) ?>" class="card-img" onerror="this.onerror=null;this.src='https://via.placeholder.com/400x250?text=Pet+Photo'">
                    <div class="card-body">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
                            <h3 style="margin:0; font-size:1.3rem;"><?= htmlspecialchars($report['animal_type']) ?></h3>
                            <span class="status-badge status-<?= strtolower($report['status']) ?>"><?= htmlspecialchars($report['status']) ?></span>
                        </div>
                        <p style="font-size:0.85rem; opacity:0.8; min-height: 40px; margin-bottom:15px;"><?= htmlspecialchars($report['description']) ?></p>
                        <p style="font-size:0.85rem; color: var(--accent-gold); margin: 5px 0;">📍 <?= htmlspecialchars($report['location']) ?></p>
                        
                        <?php
                        $statusColor = "#ccc";
                        $health = strtolower($report['health_status']);
                        if($health == 'injured'){
                            $statusColor = "var(--danger)";
                        } elseif($health == 'healthy'){
                            $statusColor = "var(--success)";
                        } elseif($health == 'critical'){
                            $statusColor = "var(--warning)";
                        }
                        ?>

                        <p style="font-size:0.85rem; margin: 5px 0;">
                            🩺 Health: 
                            <span style="color: <?= $statusColor ?>; font-weight:bold;">
                                <?= htmlspecialchars($report['health_status']) ?>
                            </span>
                        </p>
                        <p style="font-size:0.85rem; opacity: 0.6; margin: 5px 0;">👤 Reported by: <?= htmlspecialchars($report['first_name'].' '.$report['last_name']) ?></p>

                        <?php if (strtolower($report['status']) === 'pending'): ?>
                            <button class="btn-action btn-gold" onclick="processReport(<?= $report['id'] ?>, 'approve')">Approve Report</button>
                        <?php elseif (strtolower($report['status']) === 'approved'): ?>
                            <div class="match-section">
                                <label style="font-size:0.75rem; color:var(--accent-gold); font-weight:bold;">FIND SIMILARITIES / MATCH:</label>
                                <select id="match_pet_<?= $report['id'] ?>">
                                    <option value="">-- Select Shelter Pet --</option>
                                    <?php foreach($rescued_pets as $rp): ?>
                                        <option value="<?= $rp['id'] ?>"><?= htmlspecialchars($rp['name'] . " (" . $rp['breed'] . ")") ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn-action btn-gold" onclick="markAsFound(<?= $report['id'] ?>)">
                                    <i class="fas fa-bell"></i> Notify Resident: Pet Found
                                </button>
                            </div>
                        <?php elseif (strtolower($report['status']) === 'found'): ?>
                            <div style="text-align:center; padding:15px 10px 5px 10px; color: var(--success); font-weight:bold; font-size: 0.95rem;">
                                <i class="fas fa-check-circle"></i> Resident Notified
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
function processReport(id, action) {
    if(!confirm('Approve this report?')) return;
    
    fetch('process_lost_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `report_id=${id}&action=${action}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') location.reload();
        else alert(data.message);
    })
    .catch(err => alert("Communication error with server handler."));
}

function markAsFound(id) {
    const petId = document.getElementById('match_pet_' + id).value;
    if(!petId) return alert('Please select a shelter pet that matches the description.');

    if(!confirm('This will notify the resident that their pet has been found. Proceed?')) return;

    fetch('process_lost_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `report_id=${id}&action=mark_found&rescued_pet_id=${petId}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            alert('Notification sent to resident successfully!');
            location.reload();
        } else alert(data.message);
    })
    .catch(err => alert("Communication error with server handler."));
}
</script>
</body>
</html>