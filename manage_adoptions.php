<?php
session_start();

// 1. SECURE ACCESS CONTROL
// Redirect if not admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. DATABASE CONFIGURATION
$host = "localhost";
$dbname = "adbsystemm"; // NOTE: Double-check phpMyAdmin to ensure it isn't "adbsystem"
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 3. ACTION HANDLERS (APPROVE / REJECT) via POST to prevent CSRF exploits
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
        $req_id = intval($_POST['request_id']);
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            $update_sql = "UPDATE adoption_requests SET status = 'approved' WHERE id = :id AND status = 'pending'";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([':id' => $req_id]);
            
            // Optional: If you want to automatically update pet status in rescued_pets table, 
            // you could run an additional query here.
            
            header("Location: manage_adoptions.php?msg=approved");
            exit();
        } elseif ($action === 'reject') {
            $update_sql = "UPDATE adoption_requests SET status = 'rejected' WHERE id = :id AND status = 'pending'";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([':id' => $req_id]);
            header("Location: manage_adoptions.php?msg=rejected");
            exit();
        }
    }

    // 4. SEARCH & FILTER LOGIC
    $search = $_GET['search'] ?? '';
    $date_filter = $_GET['date'] ?? '';

    $rp_cols = $pdo->query('SHOW COLUMNS FROM rescued_pets')->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('image_path', $rp_cols, true)) {
        $petImageCol = 'rp.image_path';
    } elseif (in_array('image', $rp_cols, true)) {
        $petImageCol = 'rp.image';
    } else {
        $petImageCol = 'NULL';
    }

    // Explicitly fetching ar.status so it is usable in our UI logic
    $sql = "SELECT ar.id, ar.status, ar.request_date, ar.applicant_name,
                   u.first_name, u.last_name, u.email, u.phone,
                   rp.pet_type AS pet_name, rp.breed AS pet_breed, {$petImageCol} AS pet_image
            FROM adoption_requests ar
            LEFT JOIN users u ON u.id = ar.resident_id
            LEFT JOIN rescued_pets rp ON rp.id = ar.pet_id
            WHERE 1=1";

    $params = [];
    if (!empty($search)) {
        // Broadened search slightly to also allow searching by applicant name
        // FIXED: Changed rp.name to rp.pet_type in search constraints
        $sql .= " AND (ar.applicant_name LIKE :search OR rp.pet_type LIKE :search OR rp.breed LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if (!empty($date_filter)) {
        $sql .= " AND DATE(ar.request_date) = :request_date";
        $params[':request_date'] = $date_filter;
    }

    $sql .= " ORDER BY ar.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $adoptionRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

function normalizeImageUrl(string $rawPath): string
{
    $path = trim($rawPath);
    if ($path === '') {
        return '';
    }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adoption Requests | PetConnect Admin</title>
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
        }

        body { 
            background: var(--bg-deep); 
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
            overflow-y: auto;
        }
        .sidebar h2 { color: var(--accent-gold); margin-bottom: 30px; font-weight: 800; margin-top: 0;}
        .nav-links { list-style: none; padding: 0; margin: 0; }
        .nav-links a { 
            text-decoration: none; color: #888; padding: 12px 15px; 
            display: flex; border-radius: 10px; margin-bottom: 5px; transition: 0.3s; 
        }
        .nav-links a:hover, .nav-links a.active { 
            background: rgba(196, 138, 61, 0.1); color: var(--accent-gold); 
            border-left: 4px solid var(--accent-gold);
        }

        /* --- MAIN CONTENT --- */
        .main-content { flex: 1; margin-left: var(--sidebar-width); padding: 50px; box-sizing: border-box; }

        /* --- STATUS NOTIFICATION BAR --- */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .alert-success { background: rgba(46, 204, 113, 0.15); color: var(--success); border: 1px solid var(--success); }
        .alert-danger { background: rgba(255, 107, 107, 0.15); color: var(--danger); border: 1px solid var(--danger); }

        /* --- FILTERS BAR --- */
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

        /* --- GRID & CARDS --- */
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .request-card {
            background: var(--glass); border: 1px solid var(--glass-border);
            border-radius: 24px; overflow: hidden; display: flex; flex-direction: column;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .request-card:hover { transform: translateY(-5px); border-color: var(--accent-gold); }

        .card-img { height: 200px; width: 100%; object-fit: cover; background: #1a1a1c; }
        .card-body { padding: 20px; flex-grow: 1; }
        
        .status-pill {
            display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; margin-bottom: 10px;
        }
        .pending { background: rgba(241, 196, 15, 0.1); color: var(--warning); border: 1px solid var(--warning); }
        .approved { background: rgba(46, 204, 113, 0.1); color: var(--success); border: 1px solid var(--success); }
        .rejected { background: rgba(255, 107, 107, 0.1); color: var(--danger); border: 1px solid var(--danger); }

        .pet-title { margin: 0; font-size: 1.4rem; color: #fff; }
        .applicant-meta { margin-top: 15px; font-size: 0.85rem; border-top: 1px solid var(--glass-border); padding-top: 15px; }
        .meta-row { display: flex; justify-content: space-between; margin-bottom: 5px; }

        .footer-btns { display: flex; gap: 10px; padding: 15px 20px; background: rgba(0,0,0,0.2); align-items: center; }
        .btn-card { flex: 1; padding: 10px; border-radius: 10px; text-decoration: none; font-size: 0.8rem; font-weight: bold; text-align: center; transition: 0.2s; border: none; cursor: pointer;}
        .btn-v { border: 1px solid var(--glass-border); color: white; background: transparent; }
        .btn-v:hover { background: rgba(255,255,255,0.05); }
        .btn-a { background: var(--accent-gold); color: black; }
        .btn-a:hover { opacity: 0.9; }
        .btn-r { background: var(--danger); color: white; }
        .btn-r:hover { opacity: 0.9; }
        
        /* Form inline wrapper for layout consistency */
        .action-form { flex: 1; display: flex; margin: 0; }
    </style>
    <link rel="stylesheet" href="admin_sidebar.css">
</head>
<body class="admin-app">

<?php include __DIR__ . '/admin_sidebar.php'; ?>
<main class="main-content">
    <header>
        <h1 style="margin:0; font-size: 2.5rem; color: var(--accent-gold);">Adoption Pipeline</h1>
        <p style="opacity:0.6;">Review applications and match pets with their forever homes.</p>
    </header>

    <?php if(($msg = $_GET['msg'] ?? '') === 'approved'): ?>
        <div class="alert alert-success">Adoption application has been successfully approved!</div>
    <?php elseif($msg === 'rejected'): ?>
        <div class="alert alert-danger">Adoption application has been rejected.</div>
    <?php endif; ?>

    <form method="GET" class="header-actions">
        <input type="text" name="search" class="search-bar" placeholder="Search by pet name, breed, or applicant..." value="<?= htmlspecialchars($search) ?>">
        <input type="date" name="date" class="filter-date" value="<?= htmlspecialchars($date_filter) ?>">
        <button type="submit" class="btn-filter">Filter</button>
    </form>

    <div class="requests-grid">
        <?php if (empty($adoptionRequests)): ?>
            <div style="grid-column: 1/-1; text-align:center; padding: 100px; opacity: 0.3;">
                <h2>No applications found</h2>
            </div>
        <?php else: ?>
            <?php foreach ($adoptionRequests as $req): 
                $statusClass = strtolower($req['status'] ?? 'pending');
                
                $petImageUrl = normalizeImageUrl((string) ($req['pet_image'] ?? ''));
                $petImg = $petImageUrl !== '' ? $petImageUrl : 'https://via.placeholder.com/400x300?text=No+Photo';
            ?>
                <div class="request-card">
                    <img src="<?= htmlspecialchars($petImg) ?>" class="card-img" alt="Pet Image" onerror="this.onerror=null;this.src='https://via.placeholder.com/400x300?text=No+Photo'">
                    <div class="card-body">
                        <span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($statusClass) ?></span>
                        <h3 class="pet-title"><?= htmlspecialchars($req['pet_name'] ?? 'Unknown Pet') ?></h3>
                        <p style="color: var(--accent-gold); font-size: 0.8rem; margin: 5px 0;"><?= htmlspecialchars($req['pet_breed'] ?? 'Mixed Breed') ?></p>

                        <div class="applicant-meta">
                            <div class="meta-row">
                                <span style="opacity:0.5;">Applicant:</span>
                                <span><?= htmlspecialchars($req['applicant_name'] ?? (($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? 'Guest'))) ?></span>
                            </div>
                            <div class="meta-row">
                                <span style="opacity:0.5;">Applied on:</span>
                                <span><?= !empty($req['request_date']) ? date('M d, Y', strtotime($req['request_date'])) : 'N/A' ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="footer-btns">
                        <a href="view_adoption_request.php?id=<?= intval($req['id']) ?>" class="btn-card btn-v">View Full Info</a>
                        
                        <?php if ($statusClass === 'pending'): ?>
                            <form method="POST" class="action-form">
                                <input type="hidden" name="request_id" value="<?= intval($req['id']) ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-card btn-a">Approve</button>
                            </form>
                            
                            <form method="POST" class="action-form">
                                <input type="hidden" name="request_id" value="<?= intval($req['id']) ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn-card btn-r">Reject</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

</body>
</html>