<?php
session_start();
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'resident') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$my_reports = [];

if (!function_exists('normalizeImageUrl')) {
    function normalizeImageUrl(string $rawPath): string {
        $path = trim($rawPath);
        if ($path === '') return '';
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
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=adbsystemm;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_reports = $pdo->prepare("
        SELECT id, animal_type, location, image, status, date_submitted, 'lost' AS report_type FROM lost_reports WHERE user_id = ?
        UNION ALL
        SELECT id, animal_type, location, image, status, date_submitted, 'stray' AS report_type FROM stray_reports WHERE user_id = ?
        ORDER BY date_submitted DESC
    ");
    $stmt_reports->execute([$user_id, $user_id]);
    $my_reports = $stmt_reports->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Graceful error handle
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My History - PetConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-deep: #0a0a0b; --accent-gold: #c48a3d; --text-warm: #d8d2cb; --glass: rgba(255, 255, 255, 0.03); --glass-border: rgba(255, 255, 255, 0.08); --danger: #ff6b6b; }
        body { background-color: var(--bg-deep); color: var(--text-warm); font-family: 'Inter', sans-serif; padding: 40px; }
        .card { background: var(--glass); padding: 30px; border-radius: 28px; border: 1px solid var(--glass-border); }
        .alert-success { background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; color: #2ecc71; padding: 15px; border-radius: 12px; margin-bottom: 25px; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .badge-lost { background: #e74c3c; color: white; }
        .badge-stray { background: var(--accent-gold); color: black; }
        .thumb-img { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .report-table th { text-align: left; color: var(--accent-gold); padding: 15px; border-bottom: 1px solid var(--glass-border); }
        .report-table td { padding: 15px; border-bottom: 1px solid var(--glass-border); }
    </style>
</head>
<body>
    <div class="card">
        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> Report submitted successfully! It is now being reviewed by the admin.
            </div>
        <?php endif; ?>

        <h2>📋 My Submitted Reports</h2>
        <div style="overflow-x: auto;">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Type</th>
                        <th>Animal</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_reports)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px;">No reports found.</td></tr>
                    <?php else: ?>
                        <?php foreach($my_reports as $r): ?>
                            <?php
                                $reportImageUrl = normalizeImageUrl((string)($r['image'] ?? ''));
                                $reportThumbFallback = 'https://via.placeholder.com/60?text=No+Photo';
                            ?>
                            <tr>
                                <td><img src="<?php echo htmlspecialchars($reportImageUrl ?: $reportThumbFallback); ?>" class="thumb-img" alt="Report photo" onerror="this.onerror=null;this.src='<?php echo $reportThumbFallback; ?>'"></td>
                                <td>
                                    <span class="badge <?php echo ($r['report_type'] == 'lost') ? 'badge-lost' : 'badge-stray'; ?>">
                                        <?php echo strtoupper($r['report_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($r['animal_type']); ?></td>
                                <td><?php echo htmlspecialchars($r['location']); ?></td>
                                <td><strong style="color:var(--accent-gold)"><?php echo $r['status']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>