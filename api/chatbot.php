<?php
/**
 * ClassPulse — Chatbot API Endpoint
 * 
 * Handles natural-language attendance queries by parsing user input,
 * querying the database, and returning formatted responses.
 * 
 * Accepts POST JSON: { "message": "user query text" }
 * Returns JSON:      { "reply": "...", "data": [...], "type": "text|card|table" }
 */

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION)) session_start();

// Auth check
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['reply' => 'Please log in first.', 'type' => 'text']);
    exit;
}

// Load environment & DB
if (file_exists(__DIR__ . '/../classes/EnvLoader.php')) {
    require_once __DIR__ . '/../classes/EnvLoader.php';
}
if (!class_exists('db_conn')) {
    require_once __DIR__ . '/../classes/db_conn.class.php';
}

$threshold = (int)(getenv('ATTENDANCE_THRESHOLD') ?: 75);

// Read input
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(['reply' => 'Please type a question about attendance.', 'type' => 'text']);
    exit;
}

$db = $conn->connect();
$msgLower = mb_strtolower($message);

try {
    $response = processQuery($db, $msgLower, $message, $threshold);
} catch (Exception $e) {
    $response = [
        'reply' => "Sorry, I encountered an error processing your request. Please try rephrasing your question.",
        'type'  => 'text'
    ];
}

echo json_encode($response);
exit;


/**
 * Main query processor — pattern-matches the user message and returns results.
 */
function processQuery(PDO $db, string $lower, string $original, int $threshold): array {

    // ============================================================
    // PATTERN 1: Attendance of [student] in [subject/class]
    // "what is the attendance of alice in cs101"
    // "attendance of bob smith in data structures"
    // ============================================================
    if (preg_match('/attendance\s+(?:of|for)\s+(.+?)\s+in\s+(.+)/i', $original, $m)) {
        $studentQuery = trim($m[1]);
        $subjectQuery = trim($m[2]);
        return queryStudentSubjectAttendance($db, $studentQuery, $subjectQuery, $threshold);
    }

    // ============================================================
    // PATTERN 2: Attendance percentage for [student]
    // "show me the attendance percentage for alice johnson"
    // "what is alice's attendance"
    // "attendance of student 2024-CS-001"
    // ============================================================
    if (preg_match('/attendance\s+(?:of|for|percentage\s+(?:of|for)?)\s+(.+)/i', $original, $m)) {
        $studentQuery = trim($m[1]);
        return queryStudentOverallAttendance($db, $studentQuery, $threshold);
    }

    if (preg_match('/(.+?)(?:\'s|s\')\s+attendance/i', $original, $m)) {
        $studentQuery = trim($m[1]);
        return queryStudentOverallAttendance($db, $studentQuery, $threshold);
    }

    // ============================================================
    // PATTERN 3: Low attendance / at-risk students (optionally in a class)
    // "which students have low attendance"
    // "students with low attendance in cs101"
    // "who has low attendance in operating systems"
    // "show at risk students"
    // ============================================================
    if (preg_match('/(low|poor|bad|at.risk|below|critical)\s+attendance/i', $lower) ||
        preg_match('/at.risk\s+students/i', $lower)) {

        // Check if a subject is specified
        $subjectQuery = null;
        if (preg_match('/in\s+(.+)$/i', $original, $m)) {
            $subjectQuery = trim($m[1]);
        }
        return queryLowAttendance($db, $threshold, $subjectQuery);
    }

    // ============================================================
    // PATTERN 4: Overall stats / summary
    // "give me a summary"
    // "how is attendance overall"
    // "dashboard stats"
    // ============================================================
    if (preg_match('/(summary|overall|stats|dashboard|overview|how\s+is\s+attendance)/i', $lower)) {
        return queryOverallSummary($db, $threshold);
    }

    // ============================================================
    // PATTERN 5: Subject/class attendance
    // "attendance for cs101"
    // "how is attendance in data structures"
    // ============================================================
    if (preg_match('/(?:attendance\s+(?:for|in)|how\s+is)\s+(.+)/i', $original, $m)) {
        $query = trim($m[1]);
        // Could be a subject
        return querySubjectAttendance($db, $query, $threshold);
    }

    // ============================================================
    // PATTERN 6: Total students / count queries
    // ============================================================
    if (preg_match('/(how\s+many|total|count)\s+students/i', $lower)) {
        $count = $db->query("SELECT COUNT(*) FROM students WHERE status=1")->fetchColumn();
        return [
            'reply' => "📊 There are currently **{$count}** active students enrolled in the system.",
            'type'  => 'text'
        ];
    }

    // ============================================================
    // PATTERN 7: Help / What can you do
    // ============================================================
    if (preg_match('/(help|what can you|what do you|capabilities|commands)/i', $lower)) {
        return [
            'reply' => "👋 I'm **PulseBot**, your attendance assistant! Here's what I can help with:\n\n" .
                       "• \"What is the attendance of **[Student Name]** in **[Subject]**?\"\n" .
                       "• \"Show me the attendance percentage for **[Student Name]**\"\n" .
                       "• \"Which students have **low attendance** in **[Class]**?\"\n" .
                       "• \"Show me an **overall summary**\"\n" .
                       "• \"How many **students** are enrolled?\"\n" .
                       "• \"How is attendance in **[Subject Code]**?\"",
            'type'  => 'text'
        ];
    }

    // ============================================================
    // PATTERN 8: Greeting
    // ============================================================
    if (preg_match('/^(hi|hello|hey|good\s+(morning|afternoon|evening)|greetings)/i', $lower)) {
        $name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';
        return [
            'reply' => "👋 Hello, **" . htmlspecialchars(ucwords($name)) . "**! I'm PulseBot, your attendance assistant. Ask me anything about student attendance!",
            'type'  => 'text',
            'suggestions' => [
                'Show overall summary',
                'Which students have low attendance?',
                'How many students are enrolled?'
            ]
        ];
    }

    // ============================================================
    // FALLBACK
    // ============================================================
    return [
        'reply' => "🤔 I'm not sure how to answer that. Try asking me:\n\n" .
                   "• \"Attendance of **[Student]** in **[Subject]**\"\n" .
                   "• \"Which students have **low attendance**?\"\n" .
                   "• \"Show **overall summary**\"\n" .
                   "• Type **help** for all commands",
        'type'  => 'text',
        'suggestions' => [
            'Help',
            'Overall summary',
            'Low attendance students'
        ]
    ];
}


// ============================================================
// QUERY FUNCTIONS
// ============================================================

/**
 * Query a specific student's attendance in a specific subject.
 */
function queryStudentSubjectAttendance(PDO $db, string $studentQ, string $subjectQ, int $threshold): array {
    $students = findStudents($db, $studentQ);
    if (empty($students)) {
        return ['reply' => "❌ I couldn't find a student matching \"**{$studentQ}**\". Please check the name or student code.", 'type' => 'text'];
    }

    $student = $students[0];
    $sid = $student['student_id'];

    // Find subject
    $subjectStmt = $db->prepare("
        SELECT * FROM subjects
        WHERE (LOWER(subject_code) LIKE ? OR LOWER(subject_name) LIKE ?) AND status=1
        LIMIT 1
    ");
    $subjectStmt->execute(['%' . mb_strtolower($subjectQ) . '%', '%' . mb_strtolower($subjectQ) . '%']);
    $subject = $subjectStmt->fetch();

    if (!$subject) {
        return ['reply' => "❌ I couldn't find a subject matching \"**{$subjectQ}**\".", 'type' => 'text'];
    }

    // Get attendance data from the view
    $attStmt = $db->prepare("
        SELECT * FROM v_attendance_summary
        WHERE student_id = ? AND subject_id = ?
    ");
    $attStmt->execute([$sid, $subject['subject_id']]);
    $att = $attStmt->fetch();

    $name = htmlspecialchars($student['fname'] . ' ' . $student['lname']);
    $subCode = htmlspecialchars($subject['subject_code']);
    $subName = htmlspecialchars($subject['subject_name']);

    if (!$att) {
        return [
            'reply' => "📋 **{$name}** has no attendance records for **{$subCode} — {$subName}** yet.",
            'type'  => 'text'
        ];
    }

    $pct = (float)$att['attendance_pct'];
    $status = $pct >= $threshold ? '✅ Good Standing' : '⚠️ Below Threshold';
    $emoji  = $pct >= 90 ? '🌟' : ($pct >= $threshold ? '👍' : ($pct >= 60 ? '⚠️' : '🚨'));

    return [
        'reply' => "{$emoji} Here's the attendance for **{$name}** in **{$subCode}**:",
        'type'  => 'card',
        'data'  => [
            ['label' => 'Student',         'value' => $name],
            ['label' => 'Subject',         'value' => "{$subCode} — {$subName}"],
            ['label' => 'Classes Attended', 'value' => "{$att['attended']} / {$att['total_classes']}"],
            ['label' => 'Attendance %',    'value' => "{$pct}%"],
            ['label' => 'Status',          'value' => $status],
        ]
    ];
}

/**
 * Query a student's overall attendance across all subjects.
 */
function queryStudentOverallAttendance(PDO $db, string $studentQ, int $threshold): array {
    $students = findStudents($db, $studentQ);
    if (empty($students)) {
        return ['reply' => "❌ I couldn't find a student matching \"**{$studentQ}**\". Please check the name or student code.", 'type' => 'text'];
    }

    $student = $students[0];
    $sid  = $student['student_id'];
    $name = htmlspecialchars($student['fname'] . ' ' . $student['lname']);

    $stmt = $db->prepare("
        SELECT subject_code, subject_name, attended, total_classes, attendance_pct
        FROM v_attendance_summary
        WHERE student_id = ?
        ORDER BY attendance_pct ASC
    ");
    $stmt->execute([$sid]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        return ['reply' => "📋 **{$name}** ({$student['student_code']}) has no attendance records yet.", 'type' => 'text'];
    }

    // Compute overall average
    $totalAtt = array_sum(array_column($rows, 'attended'));
    $totalCls = array_sum(array_column($rows, 'total_classes'));
    $avgPct   = $totalCls > 0 ? round(($totalAtt / $totalCls) * 100, 1) : 0;

    $emoji  = $avgPct >= 90 ? '🌟' : ($avgPct >= $threshold ? '👍' : ($avgPct >= 60 ? '⚠️' : '🚨'));
    $status = $avgPct >= $threshold ? '✅ Good Standing' : '⚠️ Below Threshold';

    $reply = "{$emoji} **{$name}** — Overall: **{$avgPct}%** ({$status})\n\n";
    foreach ($rows as $r) {
        $subEmoji = (float)$r['attendance_pct'] >= $threshold ? '✅' : '⚠️';
        $reply .= "{$subEmoji} {$r['subject_code']}: **{$r['attendance_pct']}%** ({$r['attended']}/{$r['total_classes']})\n";
    }

    return [
        'reply' => $reply,
        'type'  => 'text',
        'data'  => ['overall_pct' => $avgPct, 'subjects' => count($rows)]
    ];
}

/**
 * Query students with low attendance, optionally in a specific subject.
 */
function queryLowAttendance(PDO $db, int $threshold, ?string $subjectQ = null): array {
    $subjectFilter = '';
    $params = [];

    if ($subjectQ) {
        $subjectFilter = "AND (LOWER(subject_code) LIKE ? OR LOWER(subject_name) LIKE ?)";
        $params = ['%' . mb_strtolower($subjectQ) . '%', '%' . mb_strtolower($subjectQ) . '%'];
    }

    $stmt = $db->prepare("
        SELECT student_name, student_code, subject_code, attendance_pct
        FROM v_attendance_summary
        WHERE attendance_pct < {$threshold} {$subjectFilter}
        ORDER BY attendance_pct ASC
        LIMIT 15
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        $msg = $subjectQ
            ? "Great news! No students are below **{$threshold}%** attendance in \"**{$subjectQ}**\". 🎉"
            : "Great news! All students are meeting the **{$threshold}%** attendance threshold. 🎉";
        return ['reply' => $msg, 'type' => 'text'];
    }

    $header = $subjectQ
        ? "⚠️ **" . count($rows) . " record(s)** below {$threshold}% in \"**{$subjectQ}**\":"
        : "⚠️ **" . count($rows) . " record(s)** below {$threshold}% attendance:";

    $tableData = [];
    foreach ($rows as $r) {
        $tableData[] = [
            'name'    => htmlspecialchars($r['student_name']),
            'code'    => htmlspecialchars($r['student_code']),
            'subject' => htmlspecialchars($r['subject_code']),
            'pct'     => $r['attendance_pct'] . '%'
        ];
    }

    return [
        'reply'   => $header,
        'type'    => 'table',
        'columns' => ['Name', 'Code', 'Subject', 'Attendance'],
        'data'    => $tableData
    ];
}

/**
 * Subject-level attendance query.
 */
function querySubjectAttendance(PDO $db, string $query, int $threshold): array {
    $stmt = $db->prepare("
        SELECT subject_code, subject_name,
               COUNT(DISTINCT student_id) as total_students,
               ROUND(AVG(attendance_pct),1) as avg_pct,
               SUM(CASE WHEN attendance_pct < ? THEN 1 ELSE 0 END) as at_risk
        FROM v_attendance_summary
        WHERE LOWER(subject_code) LIKE ? OR LOWER(subject_name) LIKE ?
        GROUP BY subject_id
        LIMIT 5
    ");
    $like = '%' . mb_strtolower($query) . '%';
    $stmt->execute([$threshold, $like, $like]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        return ['reply' => "❌ No subject found matching \"**{$query}**\".", 'type' => 'text'];
    }

    $r = $rows[0];
    $emoji = (float)$r['avg_pct'] >= $threshold ? '📗' : '📕';
    return [
        'reply' => "{$emoji} Attendance for **{$r['subject_code']} — {$r['subject_name']}**:",
        'type'  => 'card',
        'data'  => [
            ['label' => 'Subject',       'value' => "{$r['subject_code']} — {$r['subject_name']}"],
            ['label' => 'Total Students', 'value' => $r['total_students']],
            ['label' => 'Avg Attendance', 'value' => "{$r['avg_pct']}%"],
            ['label' => 'At Risk (<' . $threshold . '%)', 'value' => $r['at_risk']],
        ]
    ];
}

/**
 * Overall system summary.
 */
function queryOverallSummary(PDO $db, int $threshold): array {
    $totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE status=1")->fetchColumn();
    $todayPresent  = (int)$db->query("SELECT COUNT(DISTINCT student_id) FROM attendance_list WHERE DATE(date_created)=CURDATE() AND type=1")->fetchColumn();
    $todayAbsent   = max(0, $totalStudents - $todayPresent);

    $avgStmt = $db->query("SELECT ROUND(AVG(attendance_pct),1) FROM v_attendance_summary");
    $avgPct  = $avgStmt ? (float)$avgStmt->fetchColumn() : 0;

    $atRisk  = (int)$db->query("SELECT COUNT(DISTINCT student_id) FROM v_attendance_summary WHERE attendance_pct < {$threshold}")->fetchColumn();
    $subjects = (int)$db->query("SELECT COUNT(*) FROM subjects WHERE status=1")->fetchColumn();

    return [
        'reply' => "📊 **ClassPulse System Summary**",
        'type'  => 'card',
        'data'  => [
            ['label' => 'Total Active Students', 'value' => $totalStudents],
            ['label' => 'Present Today',         'value' => $todayPresent],
            ['label' => 'Absent Today',          'value' => $todayAbsent],
            ['label' => 'Overall Avg Attendance', 'value' => "{$avgPct}%"],
            ['label' => "At Risk (<{$threshold}%)", 'value' => $atRisk],
            ['label' => 'Active Subjects',       'value' => $subjects],
        ],
        'suggestions' => [
            'Low attendance students',
            'How many students?'
        ]
    ];
}


// ============================================================
// HELPER: Find students by name or code (fuzzy match)
// ============================================================
function findStudents(PDO $db, string $query): array {
    $query = trim($query, ' "\'');
    // Try exact student_code match first
    $stmt = $db->prepare("SELECT * FROM students WHERE student_code = ? AND status=1 LIMIT 3");
    $stmt->execute([$query]);
    $results = $stmt->fetchAll();
    if (!empty($results)) return $results;

    // Then fuzzy name match
    $like = '%' . mb_strtolower($query) . '%';
    $stmt = $db->prepare("
        SELECT * FROM students
        WHERE status=1
          AND (LOWER(CONCAT(fname,' ',mname,' ',lname)) LIKE ?
               OR LOWER(CONCAT(fname,' ',lname)) LIKE ?
               OR LOWER(fname) LIKE ?
               OR LOWER(lname) LIKE ?)
        ORDER BY fname
        LIMIT 3
    ");
    $stmt->execute([$like, $like, $like, $like]);
    return $stmt->fetchAll();
}
