<?php
session_start();

// 2. Check Permissions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$dbname = "adbsystemm";
$username = "root";
$password = "";

/**
 * Generates initials for user avatars
 */
function getInitials($first, $last) {
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- NEW INLINE DATABASE DELETE HANDLER ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        header('Content-Type: application/json');
        $emailToDelete = isset($_POST['email']) ? trim($_POST['email']) : '';

        if (!empty($emailToDelete)) {
            $stmtDel = $pdo->prepare("DELETE FROM users WHERE email = ?");
            $stmtDel->execute([$emailToDelete]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Missing target user email address field value.']);
        }
        exit();
    }

    // --- FILTER LOGIC ---
    $whereClauses = ["role = 'resident'", "status = 'approved'"];
    $params = [];

    if (!empty($_GET['filter_date'])) {
        $whereClauses[] = "DATE(created_at) = :selected_date";
        $params[':selected_date'] = $_GET['filter_date'];
    }

    $whereSql = implode(" AND ", $whereClauses);

    // Fetch Residents
    $query = "SELECT first_name, last_name, email, phone, created_at FROM users WHERE $whereSql ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'resident' AND status = 'approved'")->fetchColumn();
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    
    // Placeholder values
    $strayCount = 12; 
    $rescueCount = 8;

    // Fetch Pending List
    $stmtPending = $pdo->query("SELECT first_name, last_name, email, phone, created_at FROM users WHERE status = 'pending' ORDER BY created_at ASC");
    $pendingUsers = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | NewVisayasPetConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-deep: #0a0a0b; --accent-gold: #c48a3d; --text-warm: #d8d2cb; 
            --glass: rgba(255, 255, 255, 0.03); --glass-border: rgba(255, 255, 255, 0.08);
            --sidebar-width: 280px; --danger: #ff6b6b; --success: #2ecc71; --blue: #3498db;
        }
        body { background-color: var(--bg-deep); color: var(--text-warm); font-family: 'Inter', sans-serif; margin: 0; display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); background: rgba(15, 15, 17, 0.9); border-right: 1px solid var(--glass-border); padding: 40px 20px; display: flex; flex-direction: column; backdrop-filter: blur(20px); position: fixed; height: 100vh; box-sizing: border-box; z-index: 100; }
        .nav-links { list-style: none; padding: 0; flex-grow: 1; }
        .nav-links a { text-decoration: none; color: #888; padding: 14px 20px; display: flex; align-items: center; gap: 15px; border-radius: 12px; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(196, 138, 61, 0.1); color: var(--accent-gold); border-left: 4px solid var(--accent-gold); }
        .main-content { flex: 1; margin-left: var(--sidebar-width); padding: 50px; width: calc(100% - var(--sidebar-width)); background: rgba(15, 15, 17, 0.9); border-left: 1px solid var(--glass-border); }
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; background: var(--glass); padding: 30px; border-radius: 20px; border: 1px solid var(--glass-border); }
        .stat-item h2 { margin: 0; font-size: 2rem; }
        .stat-item p { margin: 5px 0 0; font-size: 0.75rem; opacity: 0.6; text-transform: uppercase; }
        .c-blue { color: var(--blue); } .c-green { color: var(--success); } .c-red { color: var(--danger); } .c-gold { color: var(--accent-gold); }
        .table-card { background: var(--glass); border-radius: 20px; border: 1px solid var(--glass-border); padding: 20px; margin-bottom: 40px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { text-align: left; padding: 15px; color: var(--accent-gold); border-bottom: 1px solid var(--glass-border); font-size: 0.8rem; }
        td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-icon { width: 35px; height: 35px; border-radius: 50%; background: rgba(196, 138, 61, 0.1); color: var(--accent-gold); display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; background: rgba(46, 204, 113, 0.1); color: var(--success); }
        .status-pending { background: rgba(255, 107, 107, 0.1); color: var(--danger); }
        .search-bar { padding: 12px; border-radius: 10px; border: 1px solid var(--glass-border); background: var(--glass); color: white; width: 300px; outline: none; }
        .btn-filter { padding: 10px 20px; border-radius: 8px; background: var(--accent-gold); border: none; color: black; font-weight: bold; cursor: pointer; }
        .action-buttons { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-action { padding: 8px 12px; border-radius: 8px; border: 1px solid; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-approve { background: rgba(46, 204, 113, 0.15); color: var(--success); border-color: var(--success); }
        .btn-approve:hover { background: var(--success); color: white; }
        .btn-reject { background: rgba(255, 107, 107, 0.15); color: var(--danger); border-color: var(--danger); }
        .btn-reject:hover { background: var(--danger); color: white; }
        .btn-delete { background: rgba(255, 107, 107, 0.15); color: var(--danger); border-color: var(--danger); }
        .btn-delete:hover { background: var(--danger); color: white; }
    </style>
    <link rel="stylesheet" href="admin_sidebar.css">
</head>
<body class="admin-app">

    <?php include __DIR__ . '/admin_sidebar.php'; ?>
<main class="main-content">
        <div class="stats-container">
            <div class="stat-item">
                <h2 class="c-blue" id="stat-total"><?= $totalUsers ?></h2>
                <p>Total Residents</p>
            </div>
            <div class="stat-item">
                <h2 class="c-green"><?= $rescueCount ?></h2>
                <p>Rescue Dogs</p>
            </div>
            <div class="stat-item">
                <h2 class="c-red" id="stat-pending"><?= $pendingCount ?></h2>
                <p>Pending Approvals</p>
            </div>
            <div class="stat-item">
                <h2 class="c-gold"><?= $strayCount ?></h2>
                <p>Stray Reports</p>
            </div>
        </div>

        <section id="pending">
            <h2 style="color: var(--danger);">⏳ Pending Approvals</h2>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Resident Name</th>
                            <th>Email</th>
                            <th>Applied On</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingUsers)): ?>
                            <tr class="empty-row"><td colspan="5" style="text-align: center; opacity: 0.5;">No pending applications.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pendingUsers as $p): ?>
                            <!-- Added data-email context attribute to target row element cleanly -->
                            <tr data-email="<?= htmlspecialchars($p['email']) ?>">
                                <td>
                                    <div class="user-info">
                                        <div class="user-icon" style="color: var(--danger);"><?= getInitials($p['first_name'], $p['last_name']) ?></div>
                                        <strong><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($p['email']) ?></td>
                                <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
                                <td><span class="status-pill status-pending">Pending</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="approve_user.php?email=<?= urlencode($p['email']) ?>" class="btn-action btn-approve" title="Approve">✅ Approve</a>
                                        <!-- UPDATED: Replaced anchor tags with unified JavaScript method execution call -->
                                        <button onclick="deleteResidentUser('<?= htmlspecialchars($p['email']) ?>', 'pending')" class="btn-action btn-reject" title="Reject">❌ Reject</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="residents">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>👥 Approved Residents</h2>
                <input type="text" id="residentSearch" class="search-bar" placeholder="Search residents...">
            </div>

            <div style="margin-bottom: 20px;">
                <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
                    <input type="date" name="filter_date" value="<?= $_GET['filter_date'] ?? '' ?>" 
                        style="padding: 8px; border-radius: 8px; background: var(--glass); border: 1px solid var(--glass-border); color: white;">
                    <button type="submit" class="btn-filter">Apply Filter</button>
                </form>
            </div>

            <div class="table-card">
                <table id="residentTable">
                    <thead>
                        <tr>
                            <th>Resident Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Join Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($residents)): ?>
                            <tr class="empty-row"><td colspan="5" style="text-align: center; opacity: 0.5;">No approved residents found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($residents as $res): ?>
                            <!-- Added data-email context attribute to target row element cleanly -->
                            <tr data-email="<?= htmlspecialchars($res['email']) ?>">
                                <td>
                                    <div class="user-info">
                                        <div class="user-icon"><?= getInitials($res['first_name'], $res['last_name']) ?></div>
                                        <strong><?= htmlspecialchars($res['first_name'].' '.$res['last_name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($res['phone']) ?></td>
                                <td><?= htmlspecialchars($res['email']) ?></td>
                                <td><?= date('M d, Y', strtotime($res['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- UPDATED: Replaced anchor tags with unified JavaScript method execution call -->
                                        <button onclick="deleteResidentUser('<?= htmlspecialchars($res['email']) ?>', 'approved')" class="btn-action btn-delete" title="Delete">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
    // Live text search mechanism filtering interface records
    document.getElementById('residentSearch').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#residentTable tbody tr');
        rows.forEach(row => {
            if (!row.classList.contains('empty-row')) {
                row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
            }
        });
    });

    // Unified dynamic database asynchronous delete workflow handler
    function deleteResidentUser(email, context) {
        const confirmMsg = context === 'pending' ? 'Reject this application and delete user completely?' : 'Delete this resident permanently from the system database?';
        if (!confirm(confirmMsg)) return;

        const formData = new URLSearchParams();
        formData.append('action', 'delete_user');
        formData.append('email', email);

        fetch('admin_dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                // Find matching HTML table element row using data context attribute selector tags
                const row = document.querySelector(`tr[data-email="${email}"]`);
                if (row) {
                    const parentTableBody = row.parentNode;
                    row.remove();
                    
                    // Display dynamic layout placeholders fallback messaging if final records removed
                    if (parentTableBody.querySelectorAll('tr:not(.empty-row)').length === 0) {
                        const colCount = context === 'pending' ? 5 : 5;
                        const msg = context === 'pending' ? 'No pending applications.' : 'No approved residents found.';
                        parentTableBody.innerHTML = `<tr class="empty-row"><td colspan="${colCount}" style="text-align: center; opacity: 0.5;">${msg}</td></tr>`;
                    }
                }

                // Adjust the quick statistics layout badge numbers dynamically across dashboard metrics
                if (context === 'approved') {
                    const totalBadge = document.getElementById('stat-total');
                    if (totalBadge) totalBadge.innerText = Math.max(0, parseInt(totalBadge.innerText) - 1);
                } else if (context === 'pending') {
                    const pendingBadge = document.getElementById('stat-pending');
                    if (pendingBadge) pendingBadge.innerText = Math.max(0, parseInt(pendingBadge.innerText) - 1);
                }
            } else {
                alert(data.message || 'An error occurred while deleting the user.');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Communication failure with backend script endpoint execution.');
        });
    }
    </script>
</body>
</html>