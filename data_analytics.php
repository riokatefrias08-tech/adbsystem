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

// Capture date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username, $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- BUILD DYNAMIC SQL DATE FILTERS ---
    $lost_filter = " WHERE 1=1";
    $stray_filter = " WHERE 1=1";
    $rescued_filter = " WHERE 1=1";
    $params = [];

    if (!empty($start_date)) {
        $lost_filter .= " AND DATE(date_submitted) >= :start_date";
        $stray_filter .= " AND DATE(date_submitted) >= :start_date";
        $rescued_filter .= " AND DATE(created_at) >= :start_date";
        $params['start_date'] = $start_date;
    }

    if (!empty($end_date)) {
        $lost_filter .= " AND DATE(date_submitted) <= :end_date";
        $stray_filter .= " AND DATE(date_submitted) <= :end_date";
        $rescued_filter .= " AND DATE(created_at) <= :end_date";
        $params['end_date'] = $end_date;
    }

    // --- 1. CORE METRICS OVERVIEW (FILTERED) ---
    
    $stmtLostCount = $pdo->prepare("SELECT COUNT(*) FROM lost_reports" . $lost_filter);
    $stmtLostCount->execute($params);
    $totalLost = $stmtLostCount->fetchColumn();

    $stmtStrayCount = $pdo->prepare("SELECT COUNT(*) FROM stray_reports" . $stray_filter);
    $stmtStrayCount->execute($params);
    $totalStray = $stmtStrayCount->fetchColumn();

    $stmtRescuedCount = $pdo->prepare("SELECT COUNT(*) FROM rescued_pets" . $rescued_filter);
    $stmtRescuedCount->execute($params);
    $totalRescuedPets = $stmtRescuedCount->fetchColumn();

    $stmtSavedCount = $pdo->prepare("SELECT COUNT(*) FROM stray_reports" . $stray_filter . " AND LOWER(status) = 'rescued'");
    $stmtSavedCount->execute($params);
    $straysRescuedCount = $stmtSavedCount->fetchColumn();


    // --- 2. BARANGAY BREAKDOWNS (FILTERED) ---
    
    // Lost Reports by Barangay
    $stmtLostByBrgy = $pdo->prepare("SELECT location, COUNT(*) as count FROM lost_reports" . $lost_filter . " GROUP BY location ORDER BY count DESC");
    $stmtLostByBrgy->execute($params);
    $lostByBrgy = $stmtLostByBrgy->fetchAll(PDO::FETCH_ASSOC);

    // Stray Reports by Barangay
    $stmtStrayByBrgy = $pdo->prepare("SELECT location, COUNT(*) as count FROM stray_reports" . $stray_filter . " GROUP BY location ORDER BY count DESC");
    $stmtStrayByBrgy->execute($params);
    $strayByBrgy = $stmtStrayByBrgy->fetchAll(PDO::FETCH_ASSOC);

    // Rescued Registry Pets by Barangay (Origin location)
    $stmtRescuedByBrgy = $pdo->prepare("SELECT location_seen as location, COUNT(*) as count FROM rescued_pets" . $rescued_filter . " GROUP BY location_seen ORDER BY count DESC");
    $stmtRescuedByBrgy->execute($params);
    $rescuedByBrgy = $stmtRescuedByBrgy->fetchAll(PDO::FETCH_ASSOC);

    // Successfully Saved Strays by Barangay
    $stmtSavedStraysByBrgy = $pdo->prepare("SELECT location, COUNT(*) as count FROM stray_reports" . $stray_filter . " AND LOWER(status) = 'rescued' GROUP BY location ORDER BY count DESC");
    $stmtSavedStraysByBrgy->execute($params);
    $savedStraysByBrgy = $stmtSavedStraysByBrgy->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Analytics | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #0a0a0b; 
            --accent-gold: #c48a3d; 
            --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.02); 
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

        .page-header { margin-bottom: 30px; }
        .page-title { font-size: 2.5rem; font-weight: bold; color: #fff; margin: 0; }
        .page-subtitle { opacity: 0.6; font-size: 0.95rem; margin: 5px 0 0; }

        /* --- DATE FILTER BAR LAYOUT --- */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.01);
            border: 1px solid var(--glass-border);
            padding: 15px 25px;
            border-radius: 16px;
            margin-bottom: 35px;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            opacity: 0.6;
            font-weight: 600;
        }
        .filter-date { 
            background: rgba(255, 255, 255, 0.02); 
            border: 1px solid rgba(255, 255, 255, 0.07); 
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px; 
            border-radius: 10px; 
            font-size: 0.85rem;
            outline: none; 
            cursor: pointer;
        }
        .filter-date:focus {
            border-color: rgba(196, 138, 61, 0.4);
        }
        .filter-date::-webkit-calendar-picker-indicator { 
            filter: invert(0.6); 
            cursor: pointer; 
        } 
        .btn-apply { 
            background: var(--accent-gold); 
            color: #000000; 
            border: none; 
            padding: 10px 25px; 
            border-radius: 10px; 
            font-weight: 600; 
            font-size: 0.85rem;
            cursor: pointer; 
            transition: opacity 0.2s;
            margin-left: auto;
        }
        .btn-apply:hover { opacity: 0.9; }
        .btn-reset {
            background: rgba(255,255,255,0.05);
            color: #fff;
            border: 1px solid var(--glass-border);
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-reset:hover { background: rgba(255,255,255,0.1); }

        /* --- COUNTER CARDS --- */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .analytics-card {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .icon-box {
            width: 55px; height: 55px; border-radius: 14px;
            display: flex; justify-content: center; align-items: center; font-size: 1.5rem;
            background: rgba(255, 255, 255, 0.03);
        }
        .card-data { display: flex; flex-direction: column; }
        .stat-number { font-size: 2rem; font-weight: 800; color: #fff; line-height: 1.1; }
        .stat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.5; font-weight: 600; margin-top: 4px; }

        /* --- BREAKDOWN MATRIX SECTION --- */
        .section-title { font-size: 1.5rem; font-weight: bold; color: #fff; margin: 40px 0 20px; border-left: 4px solid var(--accent-gold); padding-left: 15px; }
        .breakdown-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 25px;
        }
        .breakdown-panel {
            background: rgba(255, 255, 255, 0.01);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 25px;
        }
        .panel-header {
            font-size: 0.9rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .breakdown-list { display: flex; flex-direction: column; gap: 12px; }
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.01);
            border-radius: 8px;
            border-left: 3px solid transparent;
        }
        .brgy-name { color: rgba(255, 255, 255, 0.85); font-weight: 500; }
        .brgy-count { font-weight: bold; padding: 2px 10px; background: rgba(255, 255, 255, 0.05); border-radius: 20px; font-size: 0.8rem; }
        .no-data { opacity: 0.4; font-size: 0.85rem; padding: 20px 0; text-align: center; }
    </style>
    <link rel="stylesheet" href="admin_sidebar.css">
</head>

<body class="admin-app">

<?php include __DIR__ . '/admin_sidebar.php'; ?>
<main class="main-content">
    <header class="page-header">
        <h1 class="page-title">📊 System Data Analytics</h1>
        <p class="page-subtitle">Real-time metrics, date scopes, and geographical analysis summaries 📈</p>
    </header>

    <form method="GET" class="filter-bar">
        <div class="filter-group">
            <span class="filter-label">From:</span>
            <input type="date" name="start_date" class="filter-date" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="filter-group">
            <span class="filter-label">To:</span>
            <input type="date" name="end_date" class="filter-date" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <button type="submit" class="btn-apply">Apply Filter</button>
        <?php if(!empty($start_date) || !empty($end_date)): ?>
            <a href="data_analytics.php" class="btn-reset">Reset Scopes</a>
        <?php endif; ?>
    </form>

    <div class="analytics-grid">
        <div class="analytics-card">
            <div class="icon-box" style="color: var(--danger); background: rgba(255, 107, 107, 0.1);"><i class="fas fa-search"></i></div>
            <div class="card-data"><span class="stat-number"><?= $totalLost ?></span><span class="stat-label">Lost Reports</span></div>
        </div>
        <div class="analytics-card">
            <div class="icon-box" style="color: var(--warning); background: rgba(241, 196, 15, 0.1);"><i class="fas fa-map-marker-alt"></i></div>
            <div class="card-data"><span class="stat-number"><?= $totalStray ?></span><span class="stat-label">Stray Reports</span></div>
        </div>
        <div class="analytics-card">
            <div class="icon-box" style="color: var(--blue); background: rgba(52, 152, 219, 0.1);"><i class="fas fa-dog"></i></div>
            <div class="card-data"><span class="stat-number"><?= $totalRescuedPets ?></span><span class="stat-label">Rescued Registry</span></div>
        </div>
        <div class="analytics-card">
            <div class="icon-box" style="color: var(--success); background: rgba(46, 204, 113, 0.1);"><i class="fas fa-heart"></i></div>
            <div class="card-data"><span class="stat-number"><?= $straysRescuedCount ?></span><span class="stat-label">Strays Saved</span></div>
        </div>
    </div>

    <h2 class="section-title">Geographical Breakdown Matrix</h2>

    <div class="breakdown-container">
        
        <div class="breakdown-panel">
            <div class="panel-header" style="color: var(--danger);"><i class="fas fa-search-location"></i> Lost Cases</div>
            <div class="breakdown-list">
                <?php if(empty($lostByBrgy)): ?> <p class="no-data">No records for this period</p> <?php endif; ?>
                <?php foreach($lostByBrgy as $row): ?>
                    <div class="breakdown-item" style="border-left-color: var(--danger);">
                        <span class="brgy-name">📍 <?= htmlspecialchars($row['location'] ?: 'Unknown Location') ?></span>
                        <span class="brgy-count"><?= $row['count'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="breakdown-panel">
            <div class="panel-header" style="color: var(--warning);"><i class="fas fa-eye"></i> Stray Sightings</div>
            <div class="breakdown-list">
                <?php if(empty($strayByBrgy)): ?> <p class="no-data">No records for this period</p> <?php endif; ?>
                <?php foreach($strayByBrgy as $row): ?>
                    <div class="breakdown-item" style="border-left-color: var(--warning);">
                        <span class="brgy-name">📍 <?= htmlspecialchars($row['location'] ?: 'Unknown Location') ?></span>
                        <span class="brgy-count"><?= $row['count'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="breakdown-panel">
            <div class="panel-header" style="color: var(--blue);"><i class="fas fa-paw"></i> Rescue Origin</div>
            <div class="breakdown-list">
                <?php if(empty($rescuedByBrgy)): ?> <p class="no-data">No records for this period</p> <?php endif; ?>
                <?php foreach($rescuedByBrgy as $row): ?>
                    <div class="breakdown-item" style="border-left-color: var(--blue);">
                        <span class="brgy-name">📍 <?= htmlspecialchars($row['location'] ?: 'Unknown Location') ?></span>
                        <span class="brgy-count"><?= $row['count'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="breakdown-panel">
            <div class="panel-header" style="color: var(--success);"><i class="fas fa-check-double"></i> Strays Rescued</div>
            <div class="breakdown-list">
                <?php if(empty($savedStraysByBrgy)): ?> <p class="no-data">No records for this period</p> <?php endif; ?>
                <?php foreach($savedStraysByBrgy as $row): ?>
                    <div class="breakdown-item" style="border-left-color: var(--success);">
                        <span class="brgy-name">📍 <?= htmlspecialchars($row['location'] ?: 'Unknown Location') ?></span>
                        <span class="brgy-count"><?= $row['count'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</main>

</body>
</html>