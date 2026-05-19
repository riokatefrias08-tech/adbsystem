<?php
/**
 * Shared admin sidebar — include on every admin page.
 * Optional: set $admin_current_page = 'filename.php' before including.
 */
$admin_current_page = $admin_current_page ?? basename($_SERVER['PHP_SELF'] ?? '');

$admin_nav_items = [
    ['admin_dashboard.php', '👥 Resident List'],
    ['manage_adoptions.php', '💌 Adoption Requests'],
    ['approved_pickups.php', '📅 Approved Pickups'],
    ['picked_up_list.php', '📦 Picked Up Pets'],
    ['add_dog.php', '🐶 Add Rescue'],
    ['lost_reports.php', '🔍 Lost Reports'],
    ['stray_reports.php', '📍 Stray Reports'],
    ['donations.php', '🎁 Donations'],
    ['data_analytics.php', '📊 Data Analytics'],
];
?>
<nav class="sidebar" aria-label="Admin navigation">
    <h2 class="sidebar-brand">PetConnect Admin</h2>
    <ul class="nav-links">
        <?php foreach ($admin_nav_items as $item): ?>
            <?php
                [$href, $label] = $item;
                $active = ($href === $admin_current_page) ? ' active' : '';
            ?>
            <li>
                <a href="<?= htmlspecialchars($href) ?>" class="<?= trim($active) ?>"><?= htmlspecialchars($label) ?></a>
            </li>
        <?php endforeach; ?>
        <li class="nav-logout">
            <a href="logout.php">🚪 Logout</a>
        </li>
    </ul>
</nav>
