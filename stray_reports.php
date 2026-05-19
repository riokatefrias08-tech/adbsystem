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

// Capture search and date filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username, $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- BUILD QUERY WITH SEARCH AND FILTER ---
    $sql = "
        SELECT s.*, u.first_name, u.last_name, u.phone
        FROM stray_reports s
        JOIN users u ON s.user_id = u.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (s.animal_type LIKE :search 
                  OR s.description LIKE :search 
                  OR s.location LIKE :search 
                  OR u.first_name LIKE :search 
                  OR u.last_name LIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($filter_date)) {
        $sql .= " AND DATE(s.date_submitted) = :filter_date";
        $params['filter_date'] = $filter_date;
    }

    $sql .= " ORDER BY s.date_submitted DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- CALCULATE STATS ---
    $totalReports = count($reports);
    $pendingReports = 0;
    foreach ($reports as $r) {
        if (strtolower($r['status']) === 'pending') $pendingReports++;
    }
    $processedReports = $totalReports - $pendingReports;

} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stray Sighting Management | Admin</title>
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
            --warning: #f1c40f;
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
            text-decoration: none; color: #888; padding: 14px 20px; display: flex; align-items: center; gap: 15px; 
            border-radius: 12px; transition: 0.3s; margin-bottom: 5px;
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

        .page-header { margin-bottom: 20px; }
        .page-title { font-size: 2.5rem; font-weight: bold; color: #fff; margin: 0; }
        .page-subtitle { opacity: 0.6; font-size: 0.95rem; margin: 5px 0 0; }

        /* --- HORIZONTAL FILTER LAYOUT BAR --- */
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
            flex: 1;
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

        /* --- STATS DISPLAY BANNER --- */
        .stats-banner { 
            background: rgba(255, 255, 255, 0.02); 
            border: 1px solid var(--glass-border); 
            border-radius: 24px; 
            padding: 30px; 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            margin-bottom: 40px; 
            text-align: center;
        }
        .stat-block { display: flex; flex-direction: column; gap: 4px; }
        .stat-number { font-size: 36px; font-weight: 800; }
        .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.5; font-weight: 600; }

        /* --- CARDS PANELS --- */
        .reports-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        .report-card { background: rgba(255, 255, 255, 0.02); border: 1px solid var(--glass-border); border-radius: 24px; overflow: hidden; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; }
        .report-card:hover { transform: translateY(-5px); border-color: var(--accent-gold); }

        .card-img-wrapper { width: 100%; height: 210px; padding: 12px; box-sizing: border-box; }
        .card-img { width: 100%; height: 100%; object-fit: cover; border-radius: 16px; cursor: pointer; background: #121214; }
        .card-content { padding: 0 20px 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .pet-name { font-size: 1.25rem; font-weight: bold; color: #fff; margin: 0 0 5px 0; text-align: center; }
        
        .detail-item { font-size: 0.85rem; margin-top: 10px; line-height: 1.4; }
        .detail-label { display: block; font-size: 0.65rem; text-transform: uppercase; opacity: 0.4; letter-spacing: 0.5px; margin-bottom: 2px; }

        .btn-group { display: flex; flex-direction: column; gap: 8px; margin-top: auto; padding-top: 20px; }
        .btn-action {
            width: 100%; padding: 11px; background: transparent; border: 1px solid var(--accent-gold); 
            color: var(--accent-gold); border-radius: 10px; cursor: pointer; font-weight: bold; font-size: 0.8rem; transition: 0.2s;
        }
        .btn-action:hover { background: var(--accent-gold); color: #000; }
        .btn-reject { border-color: var(--danger); color: var(--danger); }
        .btn-reject:hover { background: var(--danger); color: #fff; }
        .btn-rescued { background: var(--accent-gold); color: #000; border: none; }
        .btn-rescued:hover { filter: brightness(1.1); }

        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.65rem; text-transform: uppercase; font-weight: 800; margin: 10px auto; border: 1px solid transparent; }
        .status-pending { background: rgba(241,196,15,0.1); color: var(--warning); border-color: var(--warning); }
        .status-approved { background: rgba(46,204,113,0.1); color: var(--success); border-color: var(--success); }
        .status-rejected { background: rgba(231,76,60,0.1); color: var(--danger); border-color: var(--danger); }
        .status-rescued { background: #c48a3d; color: #000; font-weight: bold; }

        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); justify-content: center; align-items: center; }
        .modal-content { max-width: 80%; max-height: 80%; border-radius: 15px; border: 3px solid var(--accent-gold); }
    </style>
    <link rel="stylesheet" href="admin_sidebar.css">
</head>

<body class="admin-app">
<div id="imageModal" class="modal"><img class="modal-content" id="imgFullSize"></div>

<?php include __DIR__ . '/admin_sidebar.php'; ?>
<main class="main-content">
    <header class="page-header">
        <h1 class="page-title">📍 Stray Animal Sightings</h1>
        <p class="page-subtitle">Reviewing reports of stray animals submitted by residents 🐾</p>
    </header>

    <form method="GET" class="header-actions">
        <input type="text" name="search" class="search-bar" placeholder="Search by type, description or reporter name..." value="<?= htmlspecialchars($search) ?>">
        <input type="date" name="filter_date" class="filter-date" value="<?= htmlspecialchars($filter_date) ?>">
        <button type="submit" class="btn-filter">Filter</button>
    </form>

    <div class="stats-banner">
        <div class="stat-block"><span class="stat-number" style="color: var(--warning);"><?= $pendingReports ?></span><span class="stat-label">Pending Review</span></div>
        <div class="stat-block"><span class="stat-number" style="color: var(--success);"><?= $processedReports ?></span><span class="stat-label">Processed Reports</span></div>
        <div class="stat-block"><span class="stat-number" style="color: var(--blue);"><?= $totalReports ?></span><span class="stat-label">Total Submitted</span></div>
    </div>

    <div class="reports-grid">
        <?php if (empty($reports)): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 60px; opacity: 0.4;">
                <h3>No stray sighting reports matched your selection.</h3>
            </div>
        <?php else: ?>
            <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <div class="card-img-wrapper">
                        <img src="uploads/<?= htmlspecialchars($report['image']) ?>" class="card-img" onclick="viewImage(this.src)" onerror="this.src='https://via.placeholder.com/400x250?text=Pet+Photo'">
                    </div>
                    <div class="card-content">
                        <p class="pet-name"><?= htmlspecialchars($report['animal_type']) ?></p>

                        <div style="text-align: center;">
                            <span class="status-badge status-<?= strtolower($report['status']) ?>">
                                <?= htmlspecialchars($report['status']) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Description</span>
                            📝 <?= htmlspecialchars($report['description'] ?: 'No description provided.') ?>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Location</span>
                            📍 <?= htmlspecialchars($report['location']) ?>
                        </div>

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

                        <div class="detail-item">
                            <span class="detail-label">Health Status</span>
                            🩹 
                            <span style="color: <?= $statusColor ?>; font-weight:bold;">
                                <?= htmlspecialchars($report['health_status']) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="detail-label">Reported By</span>
                            👤 <?= htmlspecialchars($report['first_name'].' '.$report['last_name']) ?>
                        </div>

                        <div class="btn-group">
                            <?php if (strtolower($report['status']) === 'pending'): ?>
                                <button class="btn-action" onclick="processReport(<?= $report['id'] ?>, 'Approved')">Approve Report</button>
                                <button class="btn-action btn-reject" onclick="processReport(<?= $report['id'] ?>, 'Rejected')">Reject</button>
                            <?php elseif (strtolower($report['status']) === 'approved'): ?>
                                <button class="btn-action btn-rescued" onclick="markAsRescued(<?= $report['id'] ?>)">
                                    <i class="fas fa-check-circle"></i> Mark as Rescued
                                </button>
                            <?php elseif (strtolower($report['status']) === 'rescued'): ?>
                                <span style="font-size: 0.75rem; text-align: center; color: var(--success); padding: 10px; border: 1px solid var(--success); border-radius: 8px; font-weight: 600;">
                                    <i class="fas fa-check"></i> Added to Shelters
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
function viewImage(src) {
    const m = document.getElementById('imageModal');
    document.getElementById('imgFullSize').src = src;
    m.style.display = "flex";
    m.onclick = () => m.style.display = "none";
}

function processReport(reportId, action){
    if(!confirm(`Mark this stray report as ${action}?`)) return;
    fetch('update_report_status.php', {
        method:'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ id: reportId, status: action })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
        else alert(data.message || "Update failed");
    })
    .catch(err => alert("Error communicating with server."));
}

function markAsRescued(id) {
    if(!confirm('This animal has been safely rescued? It will be added to the Rescued Pet registry automatically.')) return;

    fetch('mark_stray_rescued.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'report_id=' + id
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('Pet successfully moved to Rescued Pets registry!');
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(err => alert("Error communicating with server."));
}
</script>
</body>
</html>