<?php
/**
 * ClassPulse — Dashboard / Home Page
 * Real-time stats, alert banners, and trend charts.
 */
if (!class_exists('db_conn')) require_once __DIR__ . '/classes/db_conn.class.php';

$threshold = (int)(getenv('ATTENDANCE_THRESHOLD') ?: 75);
$db = $conn->connect();

// === Stats ===
$total_students  = (int)($db->query("SELECT COUNT(*) FROM students WHERE status=1")->fetchColumn() ?? 0);
$total_today     = (int)($db->query("SELECT COUNT(DISTINCT student_id) FROM attendance_list WHERE DATE(date_created)=CURDATE() AND type=1")->fetchColumn() ?? 0);
$total_absent    = max(0, $total_students - $total_today);

// Overall average attendance %
$avg_stmt = $db->query("
    SELECT ROUND(AVG(attendance_pct),1) as avg_pct
    FROM v_attendance_summary
");
$avg_pct = $avg_stmt ? (float)($avg_stmt->fetchColumn() ?? 0) : 0;

// Students below threshold
$at_risk_stmt = $db->query("
    SELECT student_id, student_name, student_code,
           MIN(attendance_pct) as min_pct
    FROM v_attendance_summary
    WHERE attendance_pct < {$threshold}
    GROUP BY student_id
    ORDER BY min_pct ASC
    LIMIT 10
");
$at_risk = $at_risk_stmt ? $at_risk_stmt->fetchAll() : [];

// Weekly attendance trend (last 7 days)
$trend_stmt = $db->query("
    SELECT
        DATE(date_created) as att_date,
        COUNT(DISTINCT CASE WHEN type=1 THEN student_id END) as present,
        COUNT(DISTINCT CASE WHEN type=2 THEN student_id END) as absent
    FROM attendance_list
    WHERE date_created >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(date_created)
    ORDER BY att_date ASC
");
$trend_rows = $trend_stmt ? $trend_stmt->fetchAll() : [];

$trend_labels  = [];
$trend_present = [];
$trend_absent  = [];
foreach ($trend_rows as $r) {
    $trend_labels[]  = date('M j', strtotime($r['att_date']));
    $trend_present[] = (int)$r['present'];
    $trend_absent[]  = (int)$r['absent'];
}

// Subject-wise attendance (for bar chart)
$subj_stmt = $db->query("
    SELECT subject_code, ROUND(AVG(attendance_pct),1) as avg_pct
    FROM v_attendance_summary
    GROUP BY subject_id
    ORDER BY avg_pct ASC
    LIMIT 8
");
$subj_rows = $subj_stmt ? $subj_stmt->fetchAll() : [];

$subj_labels = array_column($subj_rows, 'subject_code');
$subj_vals   = array_column($subj_rows, 'avg_pct');

// Course distribution (doughnut)
$course_stmt = $db->query("
    SELECT c.course_code, COUNT(s.student_id) as cnt
    FROM students s
    INNER JOIN course_list c ON s.course_id=c.course_id
    WHERE s.status=1
    GROUP BY c.course_id
");
$course_rows = $course_stmt ? $course_stmt->fetchAll() : [];

// Top 5 best-attendance students
$top_stmt = $db->query("
    SELECT student_name, student_code, course_code, year_level,
           ROUND(AVG(attendance_pct),1) as avg_pct
    FROM v_attendance_summary
    GROUP BY student_id
    ORDER BY avg_pct DESC
    LIMIT 5
");
$top_students = $top_stmt ? $top_stmt->fetchAll() : [];

// Recent attendance (last 10 records)
$recent_stmt = $db->query("
    SELECT s.fname, s.lname, s.student_code,
           a.type, a.date_updated
    FROM attendance_list a
    INNER JOIN students s ON a.student_id=s.student_id
    ORDER BY a.attendance_id DESC
    LIMIT 10
");
$recent = $recent_stmt ? $recent_stmt->fetchAll() : [];
?>

<!-- ============================================================
     ALERT BANNER — Students Below Threshold
     ============================================================ -->
<?php if (!empty($at_risk)): ?>
<div class="alert-panel">
    <div class="alert-panel-header">
        <div class="alert-icon">⚠️</div>
        <span><?= count($at_risk) ?> Student<?= count($at_risk) > 1 ? 's' : '' ?> Below <?= $threshold ?>% Attendance Threshold</span>
        <button class="btn btn-outline btn-sm ms-auto" onclick="this.closest('.alert-panel').remove()">✕ Dismiss</button>
    </div>
    <div class="alert-student-list">
        <?php foreach ($at_risk as $s): ?>
        <div class="alert-student-chip">
            <span>👤</span>
            <span><?= htmlspecialchars($s['student_name']) ?></span>
            <span class="alert-pct"><?= $s['min_pct'] ?>%</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================
     STATS CARDS
     ============================================================ -->
<div class="stats-grid">
    <div class="stat-card primary">
        <div class="stat-icon">👨‍🎓</div>
        <div class="stat-value" data-target="<?= $total_students ?>"><?= $total_students ?></div>
        <div class="stat-label">Total Active Students</div>
        <span class="stat-change up">↑ Enrolled</span>
    </div>
    <div class="stat-card success">
        <div class="stat-icon">✅</div>
        <div class="stat-value" data-target="<?= $total_today ?>"><?= $total_today ?></div>
        <div class="stat-label">Present Today</div>
        <span class="stat-change up">⬆ Today's Check-Ins</span>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon">❌</div>
        <div class="stat-value" data-target="<?= $total_absent ?>"><?= $total_absent ?></div>
        <div class="stat-label">Absent Today</div>
        <span class="stat-change down">⬇ Not Checked In</span>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?= $avg_pct ?>%</div>
        <div class="stat-label">Overall Attendance Rate</div>
        <span class="stat-change <?= $avg_pct >= $threshold ? 'up' : 'down' ?>"><?= $avg_pct >= $threshold ? '↑ Good' : '↓ Needs Attention' ?></span>
    </div>
    <div class="stat-card info">
        <div class="stat-icon">⚠️</div>
        <div class="stat-value" data-target="<?= count($at_risk) ?>"><?= count($at_risk) ?></div>
        <div class="stat-label">At-Risk Students (&lt;<?= $threshold ?>%)</div>
        <span class="stat-change <?= count($at_risk) === 0 ? 'up' : 'down' ?>"><?= count($at_risk) === 0 ? '✓ None!' : '⚠ Needs Attention' ?></span>
    </div>
</div>

<!-- ============================================================
     CHARTS ROW
     ============================================================ -->
<div class="charts-grid">

    <!-- Weekly Trend Line Chart -->
    <div class="cp-card">
        <div class="cp-card-header">
            <div class="cp-card-title">
                <span class="title-dot" style="background:var(--cp-primary)"></span>
                Weekly Attendance Trend
            </div>
            <span class="badge badge-primary">Last 7 Days</span>
        </div>
        <div class="cp-card-body">
            <div class="chart-wrapper">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Subject-wise Bar Chart -->
    <div class="cp-card">
        <div class="cp-card-header">
            <div class="cp-card-title">
                <span class="title-dot" style="background:var(--cp-secondary)"></span>
                Subject-wise Avg Attendance
            </div>
            <span class="badge badge-success">By Subject</span>
        </div>
        <div class="cp-card-body">
            <div class="chart-wrapper">
                <canvas id="subjectChart"></canvas>
            </div>
        </div>
    </div>

</div><!-- /charts-grid -->

<!-- Second chart row -->
<div class="charts-grid" style="grid-template-columns: 1fr 1.8fr;">

    <!-- Course Doughnut -->
    <div class="cp-card">
        <div class="cp-card-header">
            <div class="cp-card-title">
                <span class="title-dot" style="background:var(--cp-accent)"></span>
                Students by Course
            </div>
        </div>
        <div class="cp-card-body">
            <div class="chart-wrapper">
                <canvas id="courseChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Students Table -->
    <div class="cp-card">
        <div class="cp-card-header">
            <div class="cp-card-title">
                <span class="title-dot" style="background:var(--cp-warning)"></span>
                🏆 Top Attendance Students
            </div>
            <a href="./?page=students" class="btn btn-outline btn-sm">View All →</a>
        </div>
        <div class="cp-card-body" style="padding:0">
            <div class="cp-table-wrap">
                <table class="cp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Course / Year</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_students)): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No data yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($top_students as $i => $s): ?>
                        <tr>
                            <td><?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : $i+1)) ?></td>
                            <td>
                                <div style="font-weight:600;color:var(--cp-text-primary)"><?= htmlspecialchars($s['student_name']) ?></div>
                                <div style="font-size:11px;color:var(--cp-text-muted)"><?= htmlspecialchars($s['student_code']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($s['course_code']) ?> · <?= htmlspecialchars($s['year_level']) ?></td>
                            <td>
                                <div class="att-progress-wrap">
                                    <div class="att-progress-bar">
                                        <div class="att-progress-fill"
                                             data-width="<?= $s['avg_pct'] ?>"
                                             style="width:0;background:<?= CP_getAttColor($s['avg_pct']) ?>">
                                        </div>
                                    </div>
                                    <span class="att-pct-label" style="color:<?= CP_getAttColor($s['avg_pct']) ?>">
                                        <?= $s['avg_pct'] ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Recent Activity Feed -->
<div class="cp-card mt-24">
    <div class="cp-card-header">
        <div class="cp-card-title">
            <span class="title-dot" style="background:var(--cp-info)"></span>
            🕐 Recent Attendance Activity
        </div>
        <a href="./?page=attendance_list" class="btn btn-outline btn-sm">View All Records →</a>
    </div>
    <div class="cp-card-body" style="padding:0">
        <div class="cp-table-wrap">
            <table class="cp-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Type</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent)): ?>
                    <tr><td colspan="3" class="text-center py-4 text-muted">No attendance records yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($recent as $r): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:var(--cp-text-primary)">
                                <?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?>
                            </div>
                            <div style="font-size:11px;color:var(--cp-text-muted)"><?= htmlspecialchars($r['student_code']) ?></div>
                        </td>
                        <td>
                            <?php if ((int)$r['type'] === 1): ?>
                            <span class="badge badge-success">✅ Time In / Present</span>
                            <?php else: ?>
                            <span class="badge badge-danger">🚪 Time Out / Absent</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($r['date_updated'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Helper used in this file
function CP_getAttColor($pct): string {
    if ($pct >= 90) return '#22c55e';
    if ($pct >= 75) return '#6C63FF';
    if ($pct >= 60) return '#FFB347';
    return '#ef4444';
}
?>

<!-- Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Weekly Trend Line Chart
    CP.charts.createTrendChart('trendChart',
        <?= json_encode($trend_labels) ?>,
        [
            {
                label: 'Present',
                data: <?= json_encode($trend_present) ?>,
                borderColor: '#6C63FF',
                backgroundColor: 'rgba(108,99,255,0.12)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#6C63FF',
                pointRadius: 4
            },
            {
                label: 'Absent',
                data: <?= json_encode($trend_absent) ?>,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239,68,68,0.08)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ef4444',
                pointRadius: 4
            }
        ]
    );

    // 2. Subject-wise Bar Chart
    CP.charts.createBarChart('subjectChart',
        <?= json_encode($subj_labels) ?>,
        [{
            label: 'Avg Attendance %',
            data: <?= json_encode($subj_vals) ?>,
            backgroundColor: <?= json_encode($subj_vals) ?>.map(v =>
                v >= 90 ? 'rgba(34,197,94,0.7)' :
                v >= 75 ? 'rgba(108,99,255,0.7)' :
                v >= 60 ? 'rgba(255,179,71,0.7)' :
                'rgba(239,68,68,0.7)'
            ),
            borderRadius: 6,
            borderSkipped: false
        }]
    );

    // 3. Course Doughnut Chart
    const courseData   = <?= json_encode(array_column($course_rows, 'cnt')) ?>;
    const courseLabels = <?= json_encode(array_column($course_rows, 'course_code')) ?>;
    CP.charts.createDoughnutChart('courseChart',
        courseLabels, courseData,
        ['#6C63FF','#00D4AA','#FF6B6B','#FFB347','#45B7D1']
    );

    // 4. Animate progress bars
    setTimeout(() => {
        document.querySelectorAll('.att-progress-fill[data-width]').forEach(el => {
            el.style.width = el.dataset.width + '%';
        });
    }, 300);
});
</script>