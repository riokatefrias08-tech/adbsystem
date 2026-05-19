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

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch donations
    $stmt = $pdo->query("
        SELECT d.*, u.first_name, u.last_name
        FROM donations d
        LEFT JOIN users u ON d.user_id = u.id
        ORDER BY d.created_at DESC
    ");

    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

function donationTypeBadgeClass(string $type): string
{
    $map = [
        'money' => 'money',
        'vitamins' => 'vitamins',
        'dog_food' => 'dog_food',
        'cat_food' => 'cat_food',
        'supplies' => 'supplies',
    ];
    return $map[$type] ?? 'default';
}

function donationTypeLabel(string $type): string
{
    $labels = [
        'money' => 'Money',
        'vitamins' => 'Vitamins',
        'dog_food' => 'Dog Food',
        'cat_food' => 'Cat Food',
        'supplies' => 'Supplies',
    ];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Donations | Admin</title>

    <style>
        :root {
            --bg-deep: #0a0a0b;
            --accent-gold: #c48a3d;
            --text-warm: #d8d2cb;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --sidebar-width: 280px;
            --danger: #ff6b6b;
            --success: #2ecc71;
            --blue: #3498db;
        }

        body {
            margin: 0;
            font-family: Arial;
            background: var(--bg-deep);
            color: var(--text-warm);
            display: flex;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(15, 15, 17, 0.9);
            border-right: 1px solid var(--glass-border);
            padding: 40px 20px;
            height: 100vh;
            position: fixed;
        }

        .nav-links {
            list-style: none;
            padding: 0;
        }

        .nav-links a {
            text-decoration: none;
            color: #888;
            padding: 14px 20px;
            display: block;
            border-radius: 12px;
            transition: 0.3s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(196, 138, 61, 0.1);
            color: var(--accent-gold);
            border-left: 4px solid var(--accent-gold);
        }

        /* MAIN CONTENT */
        .container {
            margin-left: var(--sidebar-width);
            padding: 40px;
            width: calc(100% - var(--sidebar-width));
        }

        h2 {
            color: var(--accent-gold);
            margin-bottom: 20px;
        }

        /* TABLE CARD */
        .table-card {
            background: var(--glass);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 10px;
            overflow-x: auto;
            backdrop-filter: blur(10px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th {
            text-align: left;
            padding: 15px;
            color: var(--accent-gold);
            border-bottom: 1px solid var(--glass-border);
            font-size: 0.8rem;
            background: rgba(10,10,11,0.95);
            position: sticky;
            top: 0;
        }

        td {
            padding: 18px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }

        tr:hover {
            background: rgba(255,255,255,0.03);
            transition: 0.2s;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            text-transform: capitalize;
        }

        .money { background: rgba(46,204,113,0.2); color: #2ecc71; border: 1px solid rgba(46,204,113,0.35); }
        .vitamins { background: rgba(241,196,15,0.2); color: #f1c40f; border: 1px solid rgba(241,196,15,0.35); }
        .dog_food { background: rgba(52,152,219,0.2); color: #3498db; border: 1px solid rgba(52,152,219,0.35); }
        .cat_food { background: rgba(230,126,34,0.2); color: #e67e22; border: 1px solid rgba(230,126,34,0.35); }
        .supplies { background: rgba(155,89,182,0.2); color: #9b59b6; border: 1px solid rgba(155,89,182,0.35); }
        .default { background: rgba(255,255,255,0.08); color: var(--text-warm); border: 1px solid var(--glass-border); }

        .amount {
            color: var(--success);
            font-weight: bold;
        }
    </style>
    <link rel="stylesheet" href="admin_sidebar.css">
</head>

<body class="admin-app">

<?php include __DIR__ . '/admin_sidebar.php'; ?>
<!-- MAIN CONTENT -->
<div class="container">

    <h2> Donation Records </h2>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Donor</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Message</th>
                    <th>Date</th>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($donations)): ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding: 30px;">
                        No donations yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($donations as $d): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?>
                        </td>

                        <td>
                            <?php $dtype = $d['donation_type'] ?? ''; ?>
                            <span class="badge <?= htmlspecialchars(donationTypeBadgeClass($dtype)) ?>">
                                <?= htmlspecialchars(donationTypeLabel($dtype)) ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($d['donation_type'] === 'money'): ?>
                                <span class="amount">
                                    ₱<?= number_format($d['amount'], 2) ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($d['message']) ?>
                        </td>

                        <td>
                            <?= date('M d, Y', strtotime($d['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>

        </table>
    </div>

</div>

</body>
</html>