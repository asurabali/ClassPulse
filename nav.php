<?php
/**
 * ClassPulse — Sidebar Navigation Component
 */
if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

$p = isset($_GET['page']) ? $_GET['page'] : 'home';

// Get unread alerts count (students below threshold)
$alert_count = 0;
if (class_exists('db_conn')) {
    try {
        $threshold = getenv('ATTENDANCE_THRESHOLD') ?: 75;
        $stmt = $conn->connect()->query("
            SELECT COUNT(DISTINCT student_id) as cnt
            FROM v_attendance_summary
            WHERE attendance_pct < {$threshold}
        ");
        $alert_count = $stmt ? (int)($stmt->fetch()['cnt'] ?? 0) : 0;
    } catch (Exception $e) { /* silently fail */ }
}

$nav_items = [
    ['page' => 'home',            'icon' => '🏠', 'label' => 'Dashboard'],
    ['page' => 'students',        'icon' => '👨‍🎓', 'label' => 'Students'],
    ['page' => 'attendance_list', 'icon' => '📋', 'label' => 'Attendance Records'],
    ['page' => 'analytics',       'icon' => '📊', 'label' => 'Analytics & Reports'],
    ['page' => 'bulk_upload',     'icon' => '📤', 'label' => 'Bulk Upload'],
    ['page' => 'users',           'icon' => '👥', 'label' => 'Users'],
    ['page' => 'maintenance',     'icon' => '⚙️',  'label' => 'Maintenance'],
];
?>

<!-- Sidebar overlay for mobile -->
<div id="sidebar-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;backdrop-filter:blur(4px);" class="d-lg-none"></div>

<!-- Sidebar -->
<aside id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="logo-icon">🎓</div>
        <div>
            <div class="logo-text">Class<span>Pulse</span></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Main Menu</div>
        <?php foreach ($nav_items as $item): ?>
        <a href="<?= ($item['page'] === 'attendance') ? 'attendance.php' : "./?page={$item['page']}" ?>"
           class="nav-item <?= ($p === $item['page']) ? 'active' : '' ?>"
           <?= $item['page'] === 'attendance' ? 'target="_blank"' : '' ?>>
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <span><?= $item['label'] ?></span>
            <?php if ($item['page'] === 'home' && $alert_count > 0): ?>
                <span class="nav-badge"><?= $alert_count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>

        <div class="sidebar-section-label mt-16">Quick Access</div>
        <a href="attendance.php" target="_blank" class="nav-item">
            <span class="nav-icon">✅</span>
            <span>Student Check-In</span>
        </a>
        <a href="./?page=bulk_upload" class="nav-item <?= ($p === 'bulk_upload') ? 'active' : '' ?>">
            <span class="nav-icon">📂</span>
            <span>Import CSV/Excel</span>
        </a>
        <a href="./?page=analytics&export=csv" class="nav-item" id="quick-export-csv">
            <span class="nav-icon">📥</span>
            <span>Export Report</span>
        </a>
    </nav>

    <!-- User Profile Footer -->
    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars(ucwords($_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin')) ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
            <a href="./?page=manage_account" class="btn btn-outline btn-sm" style="flex:1">⚙ Account</a>
            <a href="Actions.php?a=logout" class="btn btn-ghost btn-sm" style="flex:1">🚪 Logout</a>
        </div>
    </div>
</aside>