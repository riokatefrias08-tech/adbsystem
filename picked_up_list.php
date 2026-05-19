<?php
session_start();

// ================= SECURITY CHECK =================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ================= DATABASE =================
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

    require_once __DIR__ . '/pickup_sync.php';
    runClaimPickupSync($pdo);

    // ================= BUILD QUERY WITH SEARCH AND FILTER =================
    $sql = "
        SELECT 
            ap.pickup_date,
            ap.pickup_time,
            ap.coupon_code,
            ap.created_at,
            COALESCE(ar.applicant_name, CONCAT(u.first_name, ' ', u.last_name)) AS applicant_name,
            COALESCE(ar.phone_number, u.phone) AS phone_number,
            rp.pet_type AS pet_name,
            rp.breed AS pet_breed
        FROM adoption_pickups ap
        JOIN rescued_pets rp ON rp.id = ap.pet_id
        LEFT JOIN adoption_requests ar ON ar.id = ap.request_id AND ap.request_id > 0
        LEFT JOIN users u ON u.id = ap.resident_id
        WHERE ap.pickup_status = 'picked_up'
    ";
    
    $params = [];

    if (!empty($search)) {
        // FIXED: Changed rp.name to rp.pet_type in search constraints
        $sql .= " AND (ar.applicant_name LIKE :search 
                  OR rp.pet_type LIKE :search 
                  OR rp.breed LIKE :search 
                  OR ap.coupon_code LIKE :search 
                  OR ar.phone_number LIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($filter_date)) {
        $sql .= " AND ap.pickup_date = :filter_date";
        $params['filter_date'] = $filter_date;
    }

    $sql .= " ORDER BY ap.pickup_date DESC, ap.pickup_time DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ================= STATS =================
    $totalPickedUp = count($data);

} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Already Picked Up Pets | Admin</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-deep);
            background-image: radial-gradient(circle at 50% 50%, #1a1a1c 0%, #0a0a0b 100%);
            color: var(--text-warm); 
            font-family: 'Inter', sans-serif;
            display: flex; 
            min-height: 100vh;
        }

        /* ================= SIDEBAR ================= */
        .sidebar { 
            width: var(--sidebar-width); 
            background: rgba(15, 15, 17, 0.95); 
            border-right: 1px solid var(--glass-border);
            padding: 40px 20px; 
            position: fixed; 
            height: 100vh; 
            z-index: 100;
            backdrop-filter: blur(20px);
        }
        .sidebar h2 {
            color: var(--accent-gold);
            margin-bottom: 30px;
        }
        .nav-links { list-style: none; }
        .nav-links li { margin-bottom: 8px; }
        .nav-links a { 
            text-decoration: none; color: #888; padding: 14px 20px; display: flex; align-items: center; gap: 15px; 
            border-radius: 12px; transition: 0.3s;
        }
        .nav-links a:hover, .nav-links a.active { 
            background: rgba(196, 138, 61, 0.1); 
            color: var(--accent-gold); 
            border-left: 4px solid var(--accent-gold); 
        }

        /* ================= MAIN CONTENT ================= */
        .main-content { 
            flex: 1; 
            margin-left: var(--sidebar-width); 
            padding: 50px; 
            width: calc(100% - var(--sidebar-width)); 
        }

        .page-header { margin-bottom: 20px; }
        .page-title { font-size: 2.5rem; font-weight: bold; color: #fff; margin: 0; }
        .page-subtitle { opacity: 0.6; font-size: 0.95rem; margin: 5px 0 0; }

        /* ================= HORIZONTAL FILTER LAYOUT BAR ================= */
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

        /* ================= STATS DISPLAY BANNER ================= */
        .stats-banner { 
            background: rgba(255, 255, 255, 0.02); 
            border: 1px solid var(--glass-border); 
            border-radius: 24px; 
            padding: 25px; 
            display: grid; 
            grid-template-columns: repeat(1, 1fr); 
            margin-bottom: 40px; 
            text-align: center;
        }
        .stat-block { display: flex; flex-direction: column; gap: 4px; }
        .stat-number { font-size: 36px; font-weight: 800; color: var(--success); }
        .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.5; font-weight: 600; }

        /* ================= TABLE ================= */
        .table-card { 
            background: rgba(255, 255, 255, 0.02); 
            border: 1px solid var(--glass-border); 
            border-radius: 24px; 
            padding: 25px; 
            overflow-x: auto; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        th { 
            text-align: left; 
            padding: 18px 15px; 
            color: var(--accent-gold); 
            border-bottom: 1px solid var(--glass-border); 
            font-size: 0.85rem; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td { 
            padding: 20px 15px; 
            border-bottom: 1px solid rgba(255,255,255,0.03); 
            font-size: 0.9rem; 
        }
        .adopter-name {
            font-weight: 600;
            color: #ffffff;
        }
        .pet-name { 
            color: var(--accent-gold); 
            font-weight: bold; 
            font-size: 1rem;
        }
        .pet-breed { 
            opacity: 0.5; 
            font-size: 0.8rem; 
            margin-top: 2px;
        }
        .date-badge { 
            color: var(--blue); 
            font-weight: 600; 
        }
        .time-badge { 
            color: var(--success); 
            font-weight: 600; 
            font-size: 0.8rem;
            margin-top: 2px;
        }
        .pickup-badge { 
            background: rgba(46, 204, 113, 0.1); 
            color: var(--success); 
            border: 1px solid rgba(46, 204, 113, 0.2); 
            padding: 6px 14px; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: bold; 
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .coupon-badge { 
            background: rgba(196, 138, 61, 0.1); 
            color: var(--accent-gold); 
            padding: 6px 12px; 
            border-radius: 8px; 
            font-family: monospace; 
            font-weight: bold; 
            font-size: 0.85rem;
            border: 1px dashed rgba(196, 138, 61, 0.3); 
            display: inline-block;
        }
        .contact-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            opacity: 0.8;
        }
        .contact-info i {
            color: var(--accent-gold);
            font-size: 0.8rem;
        }
        .empty-state { 
            text-align: center; 
            padding: 60px; 
            opacity: 0.4; 
            font-size: 1rem;
        }
    </style>
    <link rel="stylesheet" href="admin_sidebar.css">
</head>

<body class="admin-app">

<?php include __DIR__ . '/admin_sidebar.php'; ?>
<main class="main-content">

    <header class="page-header">
        <h1 class="page-title">📦 Already Picked Up Pets</h1>
        <p class="page-subtitle">List of pets successfully picked up by adopters.</p>
    </header>

    <form method="GET" class="header-actions">
        <input type="text" name="search" class="search-bar" placeholder="Search by adopter, pet name, breed, or coupon..." value="<?= htmlspecialchars($search) ?>">
        <input type="date" name="filter_date" class="filter-date" value="<?= htmlspecialchars($filter_date) ?>">
        <button type="submit" class="btn-filter">Filter</button>
    </form>

    <div class="stats-banner">
        <div class="stat-block">
            <span class="stat-number"><?= $totalPickedUp ?></span>
            <span class="stat-label">Total Picked Up Pets</span>
        </div>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Adopter</th>
                    <th>Pet Details</th>
                    <th>Date & Time</th>
                    <th>Coupon</th>
                    <th>Contact</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($data)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">
                            No picked up pet records found matching the selection.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($data as $row): ?>
                        <tr>
                            <td>
                                <span class="adopter-name"><?= htmlspecialchars($row['applicant_name']) ?></span>
                            </td>

                            <td>
                                <div class="pet-name"><?= htmlspecialchars($row['pet_name']) ?></div>
                                <div class="pet-breed"><?= htmlspecialchars($row['pet_breed']) ?></div>
                            </td>

                            <td>
                                <div class="date-badge"><?= date('M d, Y', strtotime($row['pickup_date'])) ?></div>
                                <div class="time-badge"><?= date('h:i A', strtotime($row['pickup_time'])) ?></div>
                            </td>

                            <td>
                                <span class="coupon-badge"><?= htmlspecialchars($row['coupon_code']) ?></span>
                            </td>

                            <td>
                                <div class="contact-info">
                                    <i class="fas fa-phone"></i>
                                    <?= htmlspecialchars($row['phone_number']) ?>
                                </div>
                            </td>

                            <td>
                                <span class="pickup-badge">
                                    <i class="fas fa-check-circle"></i> Already Picked Up
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

</body>
</html>