<?php
/**
 * ClassPulse — Main Application Entry Point
 */
if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

// Env loader
if (file_exists(__DIR__ . '/classes/EnvLoader.php')) {
    require_once __DIR__ . '/classes/EnvLoader.php';
}

// DB connection
if (!class_exists('db_conn')) {
    require_once __DIR__ . '/classes/db_conn.class.php';
}

// Active school year
$school_year_label = '';
$sy_stmt = $conn->connect()->prepare("SELECT school_year FROM school_year WHERE status = 1 LIMIT 1");
$sy_stmt->execute();
$sy_row = $sy_stmt->fetch();
if ($sy_row) {
    $_SESSION['sy_id'] = $sy_row['sy_id'] ?? null;
    $school_year_label = $sy_row['school_year'];
}

$page = isset($_GET['page']) && !empty($_GET['page']) ? $_GET['page'] : 'home';

// Whitelist valid pages for security
$allowed_pages = [
    'home', 'students', 'attendance_list', 'analytics',
    'users', 'maintenance', 'manage_account', 'view_student',
    'bulk_upload'
];

if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}

// Page title map
$page_titles = [
    'home'            => ['Dashboard',            'Overview & Analytics'],
    'students'        => ['Students',             'Manage enrolled students'],
    'attendance_list' => ['Attendance Records',   'View & filter attendance data'],
    'analytics'       => ['Analytics & Reports',  'Trends and exportable reports'],
    'users'           => ['Users',                'Manage admin users'],
    'maintenance'     => ['Maintenance',          'System settings'],
    'manage_account'  => ['My Account',           'Update your profile'],
    'bulk_upload'     => ['Bulk Upload',           'Import attendance data from CSV/Excel'],
];

$current_title    = $page_titles[$page][0]    ?? ucfirst($page);
$current_subtitle = $page_titles[$page][1]    ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ClassPulse — Real-time classroom attendance analytics and reporting system.">
    <title><?= htmlspecialchars($current_title) ?> | ClassPulse</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">

    <!-- ClassPulse Design System -->
    <link rel="stylesheet" href="css/classpulse.css">

    <!-- Upload & Chatbot Styles -->
    <link rel="stylesheet" href="css/upload-chat.css">

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- jQuery -->
    <script src="js/jquery-3.6.0.min.js"></script>
</head>
<body>

<div id="app-wrapper">

    <!-- ===== SIDEBAR ===== -->
    <?php include 'nav.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <div id="main-content">

        <!-- Top Bar -->
        <header id="topbar">
            <div class="topbar-left">
                <!-- Mobile hamburger -->
                <button class="btn-icon d-lg-none" id="sidebar-toggle" aria-label="Toggle navigation" title="Menu">
                    ☰
                </button>
                <div>
                    <div class="topbar-title"><?= htmlspecialchars($current_title) ?></div>
                    <?php if ($current_subtitle): ?>
                    <div class="topbar-subtitle"><?= htmlspecialchars($current_subtitle) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="topbar-right">
                <?php if ($school_year_label): ?>
                <span class="school-year-badge">📅 SY <?= htmlspecialchars($school_year_label) ?></span>
                <?php endif; ?>
                <a href="attendance.php" target="_blank" class="btn btn-primary btn-sm">
                    ✅ Check-In Portal
                </a>
                <div class="btn-icon" title="Notifications" id="notif-btn">🔔</div>
            </div>
        </header>

        <!-- Page Content -->
        <div id="page-container">
            <?php
            $pa = $page . '.php';
            if (file_exists($pa)) {
                include $pa;
            } else {
                include 'home.php';
            }
            ?>
        </div>

    </div><!-- /main-content -->

</div><!-- /app-wrapper -->

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- Sidebar Overlay (mobile) -->
<div id="sidebar-overlay"></div>

<!-- ======================================================
     MODALS (Legacy Bootstrap modals — kept for compatibility)
     ====================================================== -->

<!-- Universal Modal -->
<div class="modal fade" id="uni_modal" role="dialog" data-bs-backdrop="static" data-bs-keyboard="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-sm" id="submit"
                    onclick="$('#uni_modal form').submit()">
                    💾 Save
                </button>
                <button type="button" class="btn btn-outline btn-sm" data-bs-dismiss="modal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div class="modal fade" id="confirm_modal" role="dialog">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">⚠️ Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="delete_content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" id="confirm">
                    Yes, Continue
                </button>
                <button type="button" class="btn btn-outline btn-sm" data-bs-dismiss="modal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Chatbot Widget -->
<?php include 'chatbot_widget.php'; ?>

<!-- Scripts -->
<script src="js/popper.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/classpulse.js"></script>
<script>
// Mobile sidebar overlay
document.getElementById('sidebar-overlay').addEventListener('click', function() {
    CP.sidebar.close();
    this.style.display = 'none';
});

document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    const overlay = document.getElementById('sidebar-overlay');
    const sidebar = document.getElementById('sidebar');
    const isOpen  = sidebar.classList.contains('open');
    sidebar.classList.toggle('open');
    overlay.style.display = isOpen ? 'none' : 'block';
});
</script>
</body>
</html>