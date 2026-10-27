<?php
/**
 * ClassPulse — Bulk Upload API Endpoint
 * 
 * Accepts CSV / Excel file uploads, validates rows against the expected schema,
 * and inserts/updates records in the database.
 * 
 * Expected columns:
 *   Student_Name | Student_ID | Class_Name (subject code) | Date | Status (Present/Absent)
 * 
 * This handler uses native PHP CSV parsing. For .xlsx files, it converts via
 * the SheetJS CDN on the client side and sends CSV data.
 * 
 * POST params: file upload (via $_FILES['upload_file']) OR raw CSV text via $_POST['csv_data']
 * Returns JSON: { status, msg, inserted, skipped, errors[] }
 */

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Authentication required.']);
    exit;
}

if (file_exists(__DIR__ . '/../classes/EnvLoader.php')) {
    require_once __DIR__ . '/../classes/EnvLoader.php';
}
if (!class_exists('db_conn')) {
    require_once __DIR__ . '/../classes/db_conn.class.php';
}

$db = $conn->connect();

// ---- Get CSV data ----
$csvLines = [];

// Option A: Uploaded file
if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
    $tmpPath  = $_FILES['upload_file']['tmp_name'];
    $origName = $_FILES['upload_file']['name'];
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, ['csv', 'txt'])) {
        // For xlsx/xls, the client-side JS should have converted it to CSV already.
        // But if someone sends xlsx directly, reject with guidance.
        echo json_encode([
            'status' => 'error',
            'msg'    => "Please upload a .csv file, or use the drag-and-drop zone which auto-converts .xlsx files."
        ]);
        exit;
    }

    $handle = fopen($tmpPath, 'r');
    if (!$handle) {
        echo json_encode(['status' => 'error', 'msg' => 'Could not read uploaded file.']);
        exit;
    }
    while (($row = fgetcsv($handle)) !== false) {
        $csvLines[] = $row;
    }
    fclose($handle);
}
// Option B: Pre-parsed CSV text from client-side (for xlsx)
elseif (isset($_POST['csv_data']) && !empty($_POST['csv_data'])) {
    $rawCsv = $_POST['csv_data'];
    $lines  = explode("\n", $rawCsv);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $csvLines[] = str_getcsv($line);
    }
}
else {
    echo json_encode(['status' => 'error', 'msg' => 'No file or data received.']);
    exit;
}

if (count($csvLines) < 2) {
    echo json_encode(['status' => 'error', 'msg' => 'File is empty or has no data rows.']);
    exit;
}

// ---- Parse header row & map columns ----
$headerRow = array_map(function($h) {
    return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $h)));
}, $csvLines[0]);

// Expected column name variations
$columnMap = [
    'student_name' => ['student_name', 'name', 'student', 'full_name', 'fullname'],
    'student_id'   => ['student_id', 'studentid', 'student_code', 'code', 'id', 'roll_no', 'roll'],
    'class_name'   => ['class_name', 'classname', 'class', 'subject', 'subject_code', 'subjectcode', 'course'],
    'date'         => ['date', 'att_date', 'attendance_date', 'day'],
    'status'       => ['status', 'attendance', 'att_status', 'present', 'type'],
];

$colIndex = [];
foreach ($columnMap as $key => $aliases) {
    foreach ($aliases as $alias) {
        $idx = array_search($alias, $headerRow);
        if ($idx !== false) {
            $colIndex[$key] = $idx;
            break;
        }
    }
}

// Validate required columns
$requiredCols = ['student_name', 'student_id', 'class_name', 'date', 'status'];
$missingCols  = [];
foreach ($requiredCols as $col) {
    if (!isset($colIndex[$col])) {
        $missingCols[] = $col;
    }
}

if (!empty($missingCols)) {
    echo json_encode([
        'status'  => 'error',
        'msg'     => 'Missing required columns: ' . implode(', ', $missingCols) .
                     '. Expected: Student_Name, Student_ID, Class_Name, Date, Status',
        'columns_found' => $headerRow,
    ]);
    exit;
}

// ---- Get active school year ----
$syStmt = $db->query("SELECT sy_id FROM school_year WHERE status=1 LIMIT 1");
$syRow  = $syStmt->fetch();
$syId   = $syRow ? (int)$syRow['sy_id'] : 1;

// ---- Preload lookups for efficiency ----
// Students cache: student_code => student_id
$studentCache = [];
$sStmt = $db->query("SELECT student_id, student_code FROM students");
while ($r = $sStmt->fetch()) {
    $studentCache[$r['student_code']] = (int)$r['student_id'];
}

// Subjects cache: subject_code (lower) => subject_id
$subjectCache = [];
$subStmt = $db->query("SELECT subject_id, LOWER(subject_code) as code, LOWER(subject_name) as sname FROM subjects WHERE status=1");
while ($r = $subStmt->fetch()) {
    $subjectCache[$r['code']] = (int)$r['subject_id'];
    $subjectCache[$r['sname']] = (int)$r['subject_id'];
}

// ---- Process data rows ----
$inserted = 0;
$skipped  = 0;
$errors   = [];
$totalRows = count($csvLines) - 1; // Exclude header

$insertStmt = $db->prepare("
    INSERT INTO attendance_list (student_id, subject_id, type, date_updated, date_created)
    VALUES (?, ?, ?, ?, ?)
");

// Duplicate check statement
$dupeStmt = $db->prepare("
    SELECT COUNT(*) FROM attendance_list
    WHERE student_id = ? AND subject_id = ? AND type = ? AND DATE(date_created) = ?
");

for ($i = 1; $i < count($csvLines); $i++) {
    $row = $csvLines[$i];
    $rowNum = $i + 1;

    // Skip completely empty rows
    $rowStr = implode('', $row);
    if (trim($rowStr) === '') continue;

    // Extract values
    $studentName = trim($row[$colIndex['student_name']] ?? '');
    $studentCode = trim($row[$colIndex['student_id']] ?? '');
    $className   = trim($row[$colIndex['class_name']] ?? '');
    $dateStr     = trim($row[$colIndex['date']] ?? '');
    $statusStr   = strtolower(trim($row[$colIndex['status']] ?? ''));

    // Validate required fields
    $rowErrors = [];
    if (empty($studentName)) $rowErrors[] = 'Student_Name is empty';
    if (empty($studentCode)) $rowErrors[] = 'Student_ID is empty';
    if (empty($className))   $rowErrors[] = 'Class_Name is empty';
    if (empty($dateStr))     $rowErrors[] = 'Date is empty';
    if (empty($statusStr))   $rowErrors[] = 'Status is empty';

    if (!empty($rowErrors)) {
        $errors[] = "Row {$rowNum}: " . implode('; ', $rowErrors);
        $skipped++;
        continue;
    }

    // Parse date
    $parsedDate = strtotime($dateStr);
    if ($parsedDate === false) {
        $errors[] = "Row {$rowNum}: Invalid date \"{$dateStr}\"";
        $skipped++;
        continue;
    }
    $sqlDate  = date('Y-m-d H:i:s', $parsedDate);
    $dateOnly = date('Y-m-d', $parsedDate);
    $dateDisplay = date('M d, Y h:i A', $parsedDate);

    // Map status to type (1=Present/TimeIn, 2=Absent/TimeOut)
    $type = null;
    if (in_array($statusStr, ['present', 'p', '1', 'yes', 'attended', 'time in', 'timein', 'in'])) {
        $type = 1;
    } elseif (in_array($statusStr, ['absent', 'a', '0', '2', 'no', 'missed', 'time out', 'timeout', 'out'])) {
        $type = 2;
    } else {
        $errors[] = "Row {$rowNum}: Unknown status \"{$statusStr}\" — expected Present/Absent";
        $skipped++;
        continue;
    }

    // Resolve student_id
    $studentId = $studentCache[$studentCode] ?? null;
    if ($studentId === null) {
        // Auto-create student if not found
        $nameParts = explode(' ', $studentName, 3);
        $fname = $nameParts[0] ?? '';
        $mname = $nameParts[2] ?? '';  // If 3 parts: first middle last
        $lname = $nameParts[1] ?? '';
        if (count($nameParts) === 3) {
            $lname = $nameParts[2];
            $mname = $nameParts[1];
        }

        // Get first available course and year level
        $defaultCourse = $db->query("SELECT course_id FROM course_list WHERE status=1 LIMIT 1")->fetchColumn() ?: 1;
        $defaultYl     = $db->query("SELECT yl_id FROM year_level LIMIT 1")->fetchColumn() ?: 1;

        try {
            $createStmt = $db->prepare("
                INSERT INTO students (student_code, fname, mname, lname, gender, email, contact, course_id, yl_id, sy_id, status)
                VALUES (?, ?, ?, ?, 'unknown', '', '', ?, ?, ?, 1)
            ");
            $createStmt->execute([$studentCode, $fname, $mname, $lname, $defaultCourse, $defaultYl, $syId]);
            $studentId = (int)$db->lastInsertId();
            $studentCache[$studentCode] = $studentId;
        } catch (PDOException $e) {
            $errors[] = "Row {$rowNum}: Could not create student \"{$studentCode}\" — " . $e->getMessage();
            $skipped++;
            continue;
        }
    }

    // Resolve subject_id
    $subjectId = $subjectCache[mb_strtolower($className)] ?? null;
    if ($subjectId === null) {
        // Auto-create subject
        $defaultCourse = $db->query("SELECT course_id FROM course_list WHERE status=1 LIMIT 1")->fetchColumn() ?: 1;
        $defaultYl     = $db->query("SELECT yl_id FROM year_level LIMIT 1")->fetchColumn() ?: 1;
        try {
            $createSubStmt = $db->prepare("
                INSERT INTO subjects (subject_code, subject_name, course_id, yl_id, sy_id, total_classes, status)
                VALUES (?, ?, ?, ?, ?, 30, 1)
            ");
            $createSubStmt->execute([$className, $className, $defaultCourse, $defaultYl, $syId]);
            $subjectId = (int)$db->lastInsertId();
            $subjectCache[mb_strtolower($className)] = $subjectId;
        } catch (PDOException $e) {
            $errors[] = "Row {$rowNum}: Could not create subject \"{$className}\" — " . $e->getMessage();
            $skipped++;
            continue;
        }
    }

    // Check for duplicate
    $dupeStmt->execute([$studentId, $subjectId, $type, $dateOnly]);
    if ((int)$dupeStmt->fetchColumn() > 0) {
        $skipped++;
        continue; // Silently skip duplicates
    }

    // Insert attendance record
    try {
        $insertStmt->execute([$studentId, $subjectId, $type, $dateDisplay, $sqlDate]);
        $inserted++;
    } catch (PDOException $e) {
        $errors[] = "Row {$rowNum}: Insert failed — " . $e->getMessage();
        $skipped++;
    }
}

// ---- Return response ----
$status = ($inserted > 0) ? 'success' : ($skipped === $totalRows ? 'error' : 'partial');
$msg    = "✅ Processed {$totalRows} rows: {$inserted} inserted, {$skipped} skipped.";
if ($inserted === 0 && $skipped > 0) {
    $msg = "⚠️ No records were imported. {$skipped} rows had errors.";
}

echo json_encode([
    'status'   => $status,
    'msg'      => $msg,
    'inserted' => $inserted,
    'skipped'  => $skipped,
    'total'    => $totalRows,
    'errors'   => array_slice($errors, 0, 20), // Cap error list
]);
