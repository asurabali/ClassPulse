<?php
/**
 * ClassPulse — Analytics & Reports Page
 * Subject-wise attendance, monthly trends, CSV/PDF export.
 */
if (!class_exists('db_conn')) require_once __DIR__ . '/classes/db_conn.class.php';

$threshold = (int)(getenv('ATTENDANCE_THRESHOLD') ?: 75);
$db = $conn->connect();

// Filters
$filter_course = isset($_GET['course_id']) && is_numeric($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_yl     = isset($_GET['yl_id'])     && is_numeric($_GET['yl_id'])     ? (int)$_GET['yl_id']     : 0;
$filter_period = $_GET['period'] ?? 'monthly';

// Courses & Year Levels for filter dropdowns
$courses     = $db->query("SELECT * FROM course_list WHERE status=1")->fetchAll();
$year_levels = $db->query("SELECT * FROM year_level ORDER BY yl_id")->fetchAll();

// ---- Subject-wise Attendance Summary ----
$where = "WHERE 1=1";
if ($filter_course) $where .= " AND course_id={$filter_course}";
if ($filter_yl)     $where .= " AND yl_id={$filter_yl}";

$subj_summary_stmt = $db->query("
    SELECT subject_code, subject_name, course_code, year_level,
           COUNT(student_id) as total_students,
           ROUND(AVG(attendance_pct),1) as avg_pct,
           SUM(CASE WHEN attendance_pct >= {$threshold} THEN 1 ELSE 0 END) as passing,
           SUM(CASE WHEN attendance_pct < {$threshold} THEN 1 ELSE 0 END) as at_risk
    FROM v_attendance_summary
    {$where}
    GROUP BY subject_id
    ORDER BY avg_pct ASC
");
$subj_summary = $subj_summary_stmt ? $subj_summary_stmt->fetchAll() : [];

// ---- Monthly Attendance Trend (last 6 months) ----
$monthly_stmt = $db->query("
    SELECT
        DATE_FORMAT(date_created, '%b %Y') as month_label,
        DATE_FORMAT(date_created, '%Y-%m') as month_sort,
        COUNT(DISTINCT CASE WHEN type=1 THEN CONCAT(student_id,'-',DATE(date_created)) END) as present_sessions,
        COUNT(DISTINCT CASE WHEN type=2 THEN CONCAT(student_id,'-',DATE(date_created)) END) as absent_sessions
    FROM attendance_list
    WHERE date_created >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_sort
    ORDER BY month_sort ASC
");
$monthly_rows = $monthly_stmt ? $monthly_stmt->fetchAll() : [];

// ---- Per-student Detailed Report ----
$student_report_stmt = $db->query("
    SELECT student_id, student_code, student_name, course_code, year_level,
           COUNT(subject_id) as total_subjects,
           ROUND(AVG(attendance_pct),1) as overall_pct,
           SUM(attended) as total_attended,
           SUM(total_classes) as total_possible
    FROM v_attendance_summary
    {$where}
    GROUP BY student_id
    ORDER BY overall_pct ASC
    LIMIT 50
");
$student_report = $student_report_stmt ? $student_report_stmt->fetchAll() : [];
?>

<!-- Export Bar -->
<div class="export-bar">
    <div>
        <div class="export-bar-title">📊 Analytics & Attendance Reports</div>
        <div class="text-muted fs-12">Subject-wise breakdowns, trends, and downloadable reports</div>
    </div>
    <div class="btn-group">
        <button class="btn btn-success btn-sm" onclick="CP.export.toCSV('student-report-table','ClassPulse_Attendance_Report')">
            📥 Export CSV
        </button>
        <button class="btn btn-danger btn-sm" onclick="CP.export.toPDF('analytics-printable','ClassPulse Analytics Report')">
            🖨️ Export PDF
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="cp-card mb-24">
    <div class="cp-card-header">
        <div class="cp-card-title">🔍 Filter Reports</div>
    </div>
    <div class="cp-card-body">
        <form method="GET" action="./" id="analytics-filter">
            <input type="hidden" name="page" value="analytics">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Course</label>
                    <select name="course_id" class="form-select">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $c): ?>
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
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     Monthly Trend Chart
     ============================================================ -->
<div class="charts-grid mb-24">
    <div class="cp-card">
        <div class="cp-card-header">
            <div class="cp-card-title">
                <span class="title-dot" style="background:var(--cp-primary)"></span>
                Monthly Attendance Trend
            </div>
            <span class="badge badge-primary">Last 6 Months</span>
        </div>
        <div class="cp-card-body">
            <div class="chart-wrapper">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="cp-card">
        <div class="cp-card-header">
            <div class="cp-card-title">
                <span class="title-dot" style="background:var(--cp-secondary)"></span>
                Subject Attendance Comparison
            </div>
        </div>
        <div class="cp-card-body">
            <div class="chart-wrapper">
                <canvas id="subjectCompChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Subject-wise Attendance Summary
     ============================================================ -->
<div class="cp-card mb-24">
    <div class="cp-card-header">
        <div class="cp-card-title">
            <span class="title-dot" style="background:var(--cp-warning)"></span>
            📚 Subject-wise Attendance Summary
        </div>
    </div>
    <div class="cp-card-body" style="padding:0">
        <div class="cp-table-wrap">
            <table class="cp-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Course / Year</th>
                        <th>Students</th>
                        <th>Avg Attendance</th>
                        <th>Passing (≥<?= $threshold ?>%)</th>
                        <th>At Risk</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subj_summary)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No subject data available.</td></tr>
                    <?php else: ?>
                    <?php foreach ($subj_summary as $s): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:var(--cp-text-primary)"><?= htmlspecialchars($s['subject_code']) ?></div>
                            <div style="font-size:11px;color:var(--cp-text-muted)"><?= htmlspecialchars($s['subject_name']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($s['course_code']) ?> · <?= htmlspecialchars($s['year_level']) ?></td>
                        <td><?= $s['total_students'] ?></td>
                        <td>
                            <div class="att-progress-wrap">
                                <div class="att-progress-bar">
                                    <div class="att-progress-fill"
                                         data-width="<?= $s['avg_pct'] ?>"
                                         style="width:0;background:<?= CP_getAttColor_analytics($s['avg_pct']) ?>">
                                    </div>
                                </div>
                                <span class="att-pct-label" style="color:<?= CP_getAttColor_analytics($s['avg_pct']) ?>">
                                    <?= $s['avg_pct'] ?>%
                                </span>
                            </div>
                        </td>
                        <td><span class="badge badge-success">✅ <?= $s['passing'] ?></span></td>
                        <td>
                            <?php if ($s['at_risk'] > 0): ?>
                            <span class="badge badge-danger">⚠️ <?= $s['at_risk'] ?></span>
                            <?php else: ?>
                            <span class="badge badge-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['avg_pct'] >= 90): ?>
                            <span class="badge badge-success">Excellent</span>
                            <?php elseif ($s['avg_pct'] >= $threshold): ?>
                            <span class="badge badge-primary">Good</span>
                            <?php elseif ($s['avg_pct'] >= 60): ?>
                            <span class="badge badge-warning">At Risk</span>
                            <?php else: ?>
                            <span class="badge badge-danger">Critical</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================
     Per-Student Report (Printable/Exportable)
     ============================================================ -->
<div class="cp-card" id="analytics-printable">
    <div class="cp-card-header">
        <div class="cp-card-title">
            <span class="title-dot" style="background:var(--cp-accent)"></span>
            📋 Student-wise Attendance Report
        </div>
        <div class="btn-group">
            <button class="btn btn-success btn-sm" onclick="CP.export.toCSV('student-report-table','ClassPulse_Student_Report')">
                📥 CSV
            </button>
            <button class="btn btn-danger btn-sm" onclick="CP.export.toPDF('analytics-printable','Student Attendance Report')">
                🖨️ PDF
            </button>
        </div>
    </div>
    <div class="cp-card-body" style="padding:0">
        <div class="cp-table-wrap">
            <table class="cp-table" id="student-report-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Code</th>
                        <th>Name</th>
                        <th>Course / Year</th>
                        <th>Subjects</th>
                        <th>Attended / Total</th>
                        <th>Avg Attendance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($student_report)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No student data found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($student_report as $i => $sr): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td style="font-weight:600"><?= htmlspecialchars($sr['student_code']) ?></td>
                        <td style="color:var(--cp-text-primary)"><?= htmlspecialchars($sr['student_name']) ?></td>
                        <td><?= htmlspecialchars($sr['course_code']) ?> · <?= htmlspecialchars($sr['year_level']) ?></td>
                        <td><?= $sr['total_subjects'] ?></td>
                        <td><?= $sr['total_attended'] ?> / <?= $sr['total_possible'] ?></td>
                        <td>
                            <span style="font-weight:700;color:<?= CP_getAttColor_analytics($sr['overall_pct']) ?>">
                                <?= $sr['overall_pct'] ?>%
                            </span>
                        </td>
                        <td>
                            <?php if ($sr['overall_pct'] >= $threshold): ?>
                            <span class="badge badge-success">✅ OK</span>
                            <?php else: ?>
                            <span class="badge badge-danger">⚠️ At Risk</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
function CP_getAttColor_analytics($pct): string {
    if ($pct >= 90) return '#22c55e';
    if ($pct >= 75) return '#6C63FF';
    if ($pct >= 60) return '#FFB347';
    return '#ef4444';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Trend Chart
    const monthlyLabels = <?= json_encode(array_column($monthly_rows, 'month_label')) ?>;
    const presentData   = <?= json_encode(array_column($monthly_rows, 'present_sessions')) ?>;
    const absentData    = <?= json_encode(array_column($monthly_rows, 'absent_sessions')) ?>;

    CP.charts.createBarChart('monthlyTrendChart', monthlyLabels, [
        {
            label: 'Present Sessions',
            data: presentData,
            backgroundColor: 'rgba(108,99,255,0.75)',
            borderRadius: 6, borderSkipped: false
        },
        {
            label: 'Absent Sessions',
            data: absentData,
            backgroundColor: 'rgba(239,68,68,0.65)',
            borderRadius: 6, borderSkipped: false
        }
    ]);

    // Subject Comparison Chart
    const subjLabels = <?= json_encode(array_column($subj_summary, 'subject_code')) ?>;
    const subjAvg    = <?= json_encode(array_column($subj_summary, 'avg_pct')) ?>;
    const threshold  = <?= $threshold ?>;

    CP.charts.createBarChart('subjectCompChart', subjLabels, [
        {
            label: 'Avg Attendance %',
            data: subjAvg,
            backgroundColor: subjAvg.map(v =>
                v >= 90 ? 'rgba(34,197,94,0.75)' :
                v >= threshold ? 'rgba(108,99,255,0.75)' :
                v >= 60 ? 'rgba(255,179,71,0.75)' :
                'rgba(239,68,68,0.75)'
            ),
            borderRadius: 6, borderSkipped: false
        }
    ]);

    // Animate progress bars
    setTimeout(() => {
        document.querySelectorAll('.att-progress-fill[data-width]').forEach(el => {
            el.style.width = el.dataset.width + '%';
        });
    }, 300);
});
</script>
