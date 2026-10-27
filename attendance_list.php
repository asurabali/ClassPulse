<?php
/**
 * ClassPulse — Attendance Records List
 * Filterable attendance log with export capability.
 */
if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['username'])) {
    header("location: login.php");
    exit;
}

if (!class_exists('db_conn')) require_once __DIR__ . '/classes/db_conn.class.php';

$db = $conn->connect();

// Fetch filter options
$course_lists = $db->query("SELECT * FROM course_list WHERE status=1")->fetchAll();
$year_levels  = $db->query("SELECT * FROM year_level ORDER BY yl_id")->fetchAll();
$subjects_all = $db->query("SELECT subject_id, subject_code, subject_name FROM subjects WHERE status=1 ORDER BY subject_code")->fetchAll();

// Sanitize filters
$filter_course  = isset($_GET['course_id']) && is_numeric($_GET['course_id']) && $_GET['course_id'] > 0
    ? (int)$_GET['course_id'] : 0;
$filter_yl      = isset($_GET['yl_id']) && is_numeric($_GET['yl_id']) && $_GET['yl_id'] > 0
    ? (int)$_GET['yl_id'] : 0;
$filter_subject = isset($_GET['subject_id']) && is_numeric($_GET['subject_id']) && $_GET['subject_id'] > 0
    ? (int)$_GET['subject_id'] : 0;
$filter_date    = isset($_GET['att_date']) && !empty($_GET['att_date'])
    ? htmlspecialchars($_GET['att_date']) : '';
$filter_type    = isset($_GET['att_type']) && in_array($_GET['att_type'], ['1','2'])
    ? (int)$_GET['att_type'] : 0;

// Build query
$where_parts = ["1=1"];
if ($filter_course)  $where_parts[] = "s.course_id = {$filter_course}";
if ($filter_yl)      $where_parts[] = "s.yl_id = {$filter_yl}";
if ($filter_subject) $where_parts[] = "a.subject_id = {$filter_subject}";
if ($filter_type)    $where_parts[] = "a.type = {$filter_type}";
if ($filter_date)    $where_parts[] = "DATE(a.date_created) = '" . date('Y-m-d', strtotime($filter_date)) . "'";

$where_sql = implode(' AND ', $where_parts);

$sql = "
    SELECT
        a.attendance_id,
        a.type,
        a.date_updated,
        a.date_created,
        s.student_code,
        s.gender,
        CONCAT(s.fname, ' ', NULLIF(s.mname,''), ' ', s.lname) AS student_name,
        c.course_code,
        yl.name AS year_level,
        sub.subject_code,
        sub.subject_name
    FROM attendance_list a
    INNER JOIN students s    ON a.student_id  = s.student_id
    INNER JOIN course_list c ON s.course_id   = c.course_id
    INNER JOIN year_level yl ON s.yl_id       = yl.yl_id
    LEFT  JOIN subjects sub  ON a.subject_id  = sub.subject_id
    WHERE {$where_sql}
    ORDER BY a.attendance_id DESC
    LIMIT 500
";

$stmt = $db->query($sql);
$datas = $stmt ? $stmt->fetchAll() : [];

// Summary counts
$total_records = count($datas);
$present_count = count(array_filter($datas, fn($r) => (int)$r['type'] === 1));
$absent_count  = $total_records - $present_count;
?>

<!-- Export Bar -->
<div class="export-bar">
    <div>
        <div class="export-bar-title">📋 Attendance Records</div>
        <div class="text-muted fs-12">
            Showing <?= $total_records ?> records —
            <span style="color:var(--cp-success)">✅ <?= $present_count ?> Present</span> &nbsp;|&nbsp;
            <span style="color:var(--cp-danger)">❌ <?= $absent_count ?> Absent/Out</span>
        </div>
    </div>
    <div class="btn-group">
        <button class="btn btn-success btn-sm" onclick="CP.export.toCSV('att-records-table','ClassPulse_Attendance_<?= date('Y-m-d') ?>')">
            📥 Export CSV
        </button>
        <button class="btn btn-danger btn-sm" onclick="window.print()">
            🖨️ Print
        </button>
    </div>
</div>

<!-- Filter Card -->
<div class="cp-card mb-24">
    <div class="cp-card-header">
        <div class="cp-card-title">🔍 Filter Attendance Records</div>
    </div>
    <div class="cp-card-body">
        <form method="GET" action="./" id="filter-form">
            <input type="hidden" name="page" value="attendance_list">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Course</label>
                    <select name="course_id" class="form-select">
                        <option value="">All Courses</option>
                        <?php foreach ($course_lists as $c): ?>
                        <option value="<?= $c['course_id'] ?>" <?= $filter_course == $c['course_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['course_code']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Year Level</label>
                    <select name="yl_id" class="form-select">
                        <option value="">All Year Levels</option>
                        <?php foreach ($year_levels as $yl): ?>
                        <option value="<?= $yl['yl_id'] ?>" <?= $filter_yl == $yl['yl_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($yl['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects_all as $sub): ?>
                        <option value="<?= $sub['subject_id'] ?>" <?= $filter_subject == $sub['subject_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['subject_code']) ?> — <?= htmlspecialchars($sub['subject_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Attendance Type</label>
                    <select name="att_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="1" <?= $filter_type == 1 ? 'selected' : '' ?>>✅ Time In / Present</option>
                        <option value="2" <?= $filter_type == 2 ? 'selected' : '' ?>>🚪 Time Out / Absent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" name="att_date" class="form-control"
                           value="<?= htmlspecialchars($filter_date) ?>">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;gap:8px">
                    <button type="submit" class="btn btn-primary" style="flex:1">Apply Filters</button>
                    <a href="./?page=attendance_list" class="btn btn-outline">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Records Table -->
<div class="cp-card">
    <div class="cp-card-header">
        <div class="cp-card-title">
            <span class="title-dot" style="background:var(--cp-info)"></span>
            Attendance Log
        </div>
        <span class="badge badge-info"><?= $total_records ?> records</span>
    </div>
    <div class="cp-card-body" style="padding:0">
        <div class="cp-table-wrap">
            <table class="cp-table" id="att-records-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Course / Year</th>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($datas)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon">📋</div>
                                <p>No attendance records found for the selected filters.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($datas as $i => $data): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <div style="font-weight:600;color:var(--cp-text-primary)">
                                <?= htmlspecialchars(trim($data['student_name'])) ?>
                            </div>
                            <div style="font-size:11px;color:var(--cp-text-muted)">
                                <?= htmlspecialchars($data['student_code']) ?>
                                · <?= $data['gender'] === 'female' ? '♀' : '♂' ?>
                            </div>
                        </td>
                        <td>
                            <?= htmlspecialchars($data['course_code']) ?>
                            <span style="color:var(--cp-text-muted)">·</span>
                            <?= htmlspecialchars($data['year_level']) ?>
                        </td>
                        <td>
                            <?php if ($data['subject_code']): ?>
                            <span style="font-weight:600"><?= htmlspecialchars($data['subject_code']) ?></span>
                            <div style="font-size:11px;color:var(--cp-text-muted)"><?= htmlspecialchars($data['subject_name']) ?></div>
                            <?php else: ?>
                            <span class="text-muted">General</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$data['type'] === 1): ?>
                            <span class="badge badge-success">✅ Time In</span>
                            <?php else: ?>
                            <span class="badge badge-danger">🚪 Time Out</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($data['date_updated'] ?? date('M d, Y H:i', strtotime($data['date_created']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>