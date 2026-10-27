<?php
/**
 * ClassPulse — Bulk Upload Page
 * Drag-and-drop file upload with client-side XLSX parsing and server-side CSV processing.
 */
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['admin_id'])) { header("location: login.php"); exit; }
?>

<!-- Schema Help Card -->
<div class="schema-card mb-24">
    <div class="schema-header">
        📐 Expected File Format
    </div>
    <p style="font-size:13px;color:var(--cp-text-secondary);margin-bottom:12px;">
        Upload a <strong>.csv</strong>, <strong>.xlsx</strong>, or <strong>.xls</strong> file with the following columns (header row required):
    </p>
    <div class="schema-columns">
        <span class="col-chip required">Student_Name *</span>
        <span class="col-chip required">Student_ID *</span>
        <span class="col-chip required">Class_Name *</span>
        <span class="col-chip required">Date *</span>
        <span class="col-chip required">Status *</span>
    </div>
    <p style="font-size:12px;color:var(--cp-text-muted);margin:0;">
        <strong>Status</strong> accepts: Present / Absent / P / A / 1 / 0 &nbsp;|&nbsp;
        <strong>Date</strong> accepts: YYYY-MM-DD, MM/DD/YYYY, or any parseable date format.<br>
        Students or subjects not found in the database will be <strong>auto-created</strong>.
    </p>
    <div style="margin-top:14px;">
        <button class="btn btn-outline btn-sm" id="download-template-btn">📥 Download Template CSV</button>
    </div>
</div>

<!-- Upload Zone -->
<div class="cp-card mb-24">
    <div class="cp-card-header">
        <div class="cp-card-title">
            <span class="title-dot" style="background:var(--cp-primary)"></span>
            📤 Upload Attendance Data
        </div>
    </div>
    <div class="cp-card-body">
        <div class="upload-zone" id="upload-zone">
            <span class="upload-icon">📂</span>
            <h3>Drag & Drop Your File Here</h3>
            <p>or click to browse from your computer</p>
            <div class="upload-formats">
                <span class="format-chip xlsx">.xlsx</span>
                <span class="format-chip xls">.xls</span>
                <span class="format-chip csv">.csv</span>
            </div>
            <input type="file" id="file-input" accept=".csv,.xlsx,.xls" style="display:none">
        </div>

        <!-- Progress bar -->
        <div class="upload-progress-wrap" id="upload-progress">
            <div class="upload-progress-bar-bg">
                <div class="upload-progress-fill" id="progress-fill"></div>
            </div>
            <div class="upload-progress-label" id="progress-label">Reading file...</div>
        </div>

        <!-- Result Banner -->
        <div class="upload-result" id="upload-result">
            <div class="result-title" id="result-title"></div>
            <div id="result-body"></div>
        </div>
    </div>
</div>

<!-- Preview Section -->
<div class="preview-section" id="preview-section">
    <div class="cp-card">
        <div class="cp-card-header">
            <div class="cp-card-title">
                <span class="title-dot" style="background:var(--cp-secondary)"></span>
                📋 Data Preview
            </div>
            <div class="preview-meta" id="preview-meta"></div>
        </div>
        <div class="cp-card-body" style="padding:0">
            <div class="cp-table-wrap" style="max-height:400px;overflow-y:auto;">
                <table class="cp-table" id="preview-table">
                    <thead id="preview-thead"></thead>
                    <tbody id="preview-tbody"></tbody>
                </table>
            </div>
        </div>
        <div style="padding:16px 24px;border-top:1px solid var(--cp-border);display:flex;justify-content:space-between;align-items:center;">
            <div>
                <span id="preview-count" class="text-muted" style="font-size:13px;"></span>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline btn-sm" id="cancel-upload-btn">✕ Cancel</button>
                <button class="btn btn-primary" id="confirm-upload-btn">
                    🚀 Import Data to Database
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SheetJS library for client-side XLSX parsing -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
(function() {
    const uploadZone  = document.getElementById('upload-zone');
    const fileInput   = document.getElementById('file-input');
    const progressDiv = document.getElementById('upload-progress');
    const progressFill= document.getElementById('progress-fill');
    const progressLbl = document.getElementById('progress-label');
    const resultDiv   = document.getElementById('upload-result');
    const resultTitle = document.getElementById('result-title');
    const resultBody  = document.getElementById('result-body');
    const previewSec  = document.getElementById('preview-section');
    const previewThead= document.getElementById('preview-thead');
    const previewTbody= document.getElementById('preview-tbody');
    const previewMeta = document.getElementById('preview-meta');
    const previewCount= document.getElementById('preview-count');

    let parsedCsvText = ''; // Holds the CSV string ready for upload
    let parsedRows    = []; // Array of arrays
    let fileName      = '';

    // ---- Drag & Drop ----
    uploadZone.addEventListener('click', () => fileInput.click());

    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('drag-over');
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('drag-over');
    });

    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) handleFile(files[0]);
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) handleFile(e.target.files[0]);
    });

    // ---- File Handler ----
    function handleFile(file) {
        fileName = file.name;
        const ext = fileName.split('.').pop().toLowerCase();
        const validExts = ['csv', 'xlsx', 'xls'];

        if (!validExts.includes(ext)) {
            showResult('error', 'Invalid File Type', 'Please upload a .csv, .xlsx, or .xls file.');
            return;
        }

        // Limit: 10MB
        if (file.size > 10 * 1024 * 1024) {
            showResult('error', 'File Too Large', 'Maximum file size is 10MB.');
            return;
        }

        resetUI();
        showProgress('Reading file...', 15);

        if (ext === 'csv') {
            readCSV(file);
        } else {
            readExcel(file);
        }
    }

    // ---- Read CSV directly ----
    function readCSV(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            showProgress('Parsing CSV...', 50);
            const text = e.target.result;
            parsedCsvText = text;
            parsedRows = parseCSVText(text);
            showProgress('Preview ready!', 100);
            setTimeout(() => renderPreview(), 300);
        };
        reader.readAsText(file);
    }

    // ---- Read Excel (XLSX/XLS) using SheetJS ----
    function readExcel(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                showProgress('Parsing spreadsheet...', 40);
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array', cellDates: true });

                showProgress('Converting to CSV...', 70);
                const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                parsedCsvText = XLSX.utils.sheet_to_csv(firstSheet);
                parsedRows = parseCSVText(parsedCsvText);

                showProgress('Preview ready!', 100);
                setTimeout(() => renderPreview(), 300);
            } catch (err) {
                showResult('error', 'Parse Error', 'Could not read the spreadsheet: ' + err.message);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    // ---- Parse CSV text into array of arrays ----
    function parseCSVText(text) {
        const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
        return lines.map(line => {
            // Simple CSV parse (handles quoted fields)
            const result = [];
            let current = '';
            let inQuotes = false;
            for (let i = 0; i < line.length; i++) {
                const ch = line[i];
                if (ch === '"') { inQuotes = !inQuotes; }
                else if (ch === ',' && !inQuotes) { result.push(current.trim()); current = ''; }
                else { current += ch; }
            }
            result.push(current.trim());
            return result;
        });
    }

    // ---- Render Preview Table ----
    function renderPreview() {
        progressDiv.style.display = 'none';

        if (parsedRows.length < 2) {
            showResult('error', 'Empty File', 'The file has no data rows after the header.');
            return;
        }

        const headers = parsedRows[0];
        const dataRows = parsedRows.slice(1);

        // Validate columns
        const requiredCols = ['student_name', 'student_id', 'class_name', 'date', 'status'];
        const normalizedHeaders = headers.map(h => h.toLowerCase().replace(/[^a-z0-9_]/g, '_'));
        const colAliases = {
            student_name: ['student_name', 'name', 'student', 'full_name', 'fullname'],
            student_id: ['student_id', 'studentid', 'student_code', 'code', 'id', 'roll_no', 'roll'],
            class_name: ['class_name', 'classname', 'class', 'subject', 'subject_code', 'subjectcode', 'course'],
            date: ['date', 'att_date', 'attendance_date', 'day'],
            status: ['status', 'attendance', 'att_status', 'present', 'type']
        };

        let foundCols = {};
        for (const [key, aliases] of Object.entries(colAliases)) {
            for (const alias of aliases) {
                const idx = normalizedHeaders.indexOf(alias);
                if (idx !== -1) { foundCols[key] = idx; break; }
            }
        }

        const missing = requiredCols.filter(c => foundCols[c] === undefined);
        if (missing.length > 0) {
            showResult('error', 'Missing Columns', 
                `Could not find required columns: <strong>${missing.join(', ')}</strong>.<br>` +
                `Found columns: ${headers.join(', ')}`);
            return;
        }

        // Build header
        previewThead.innerHTML = '<tr>' + headers.map(h => `<th>${escHtml(h)}</th>`).join('') + '<th>Valid?</th></tr>';

        // Build rows with validation
        let validCount = 0;
        let invalidCount = 0;

        previewTbody.innerHTML = '';
        const maxPreview = Math.min(dataRows.length, 100);

        for (let i = 0; i < maxPreview; i++) {
            const row = dataRows[i];
            const isValid = validateRow(row, foundCols);
            if (isValid) validCount++; else invalidCount++;

            const cls = isValid ? 'row-valid' : 'row-invalid';
            const icon = isValid ? '✅' : '❌';
            const cells = row.map(cell => `<td>${escHtml(cell)}</td>`).join('');
            previewTbody.innerHTML += `<tr class="${cls}">${cells}<td>${icon}</td></tr>`;
        }

        // Meta stats
        previewMeta.innerHTML = `
            <div class="preview-stat"><span class="dot" style="background:var(--cp-success)"></span> Valid: ${validCount}</div>
            <div class="preview-stat"><span class="dot" style="background:var(--cp-danger)"></span> Invalid: ${invalidCount}</div>
        `;
        previewCount.textContent = `Showing ${maxPreview} of ${dataRows.length} data rows from "${fileName}"`;

        previewSec.style.display = 'block';
        previewSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function validateRow(row, colMap) {
        const name   = (row[colMap.student_name] || '').trim();
        const id     = (row[colMap.student_id] || '').trim();
        const cls    = (row[colMap.class_name] || '').trim();
        const date   = (row[colMap.date] || '').trim();
        const status = (row[colMap.status] || '').trim().toLowerCase();

        if (!name || !id || !cls || !date || !status) return false;
        if (!['present','absent','p','a','1','0','yes','no','in','out','time in','time out','attended','missed'].includes(status)) return false;
        return true;
    }

    // ---- Confirm Upload ----
    document.getElementById('confirm-upload-btn').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Importing...';

        const formData = new FormData();
        formData.append('csv_data', parsedCsvText);

        fetch('api/upload_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(resp => {
            if (resp.status === 'success') {
                showResult('success', '🎉 Import Successful!',
                    `<strong>${resp.inserted}</strong> records imported, <strong>${resp.skipped}</strong> skipped.` +
                    (resp.errors && resp.errors.length > 0
                        ? `<br><br><strong>Warnings:</strong><ul>${resp.errors.map(e => `<li>${escHtml(e)}</li>`).join('')}</ul>`
                        : '')
                );
                previewSec.style.display = 'none';
                if (typeof CP !== 'undefined' && CP.toast) {
                    CP.toast.show(`${resp.inserted} records imported successfully!`, 'success');
                }
            } else {
                showResult('error', '⚠️ Import Issue',
                    `${escHtml(resp.msg)}` +
                    (resp.errors && resp.errors.length > 0
                        ? `<ul>${resp.errors.map(e => `<li>${escHtml(e)}</li>`).join('')}</ul>`
                        : '')
                );
            }
        })
        .catch(err => {
            showResult('error', '❌ Network Error', 'Could not reach the server. Please try again.');
            console.error(err);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '🚀 Import Data to Database';
        });
    });

    // ---- Cancel ----
    document.getElementById('cancel-upload-btn').addEventListener('click', function() {
        previewSec.style.display = 'none';
        parsedCsvText = '';
        parsedRows = [];
        resultDiv.style.display = 'none';
    });

    // ---- Download Template ----
    document.getElementById('download-template-btn').addEventListener('click', function() {
        const template = 'Student_Name,Student_ID,Class_Name,Date,Status\n' +
                         'Alice Johnson,2024-CS-001,CS101,2024-10-01,Present\n' +
                         'Bob Smith,2024-CS-002,CS101,2024-10-01,Absent\n' +
                         'Carol Williams,2024-CS-003,CS201,2024-10-01,Present\n';
        const blob = new Blob([template], { type: 'text/csv' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url;
        a.download = 'ClassPulse_Upload_Template.csv';
        a.click();
        URL.revokeObjectURL(url);
        if (typeof CP !== 'undefined' && CP.toast) {
            CP.toast.show('Template downloaded!', 'info');
        }
    });

    // ---- Helpers ----
    function showProgress(label, pct) {
        progressDiv.style.display = 'block';
        progressFill.style.width = pct + '%';
        progressLbl.textContent = label;
    }

    function showResult(type, title, body) {
        resultDiv.className = 'upload-result ' + type;
        resultDiv.style.display = 'block';
        resultTitle.innerHTML = title;
        resultBody.innerHTML = body;
        resultDiv.scrollIntoView({ behavior: 'smooth' });
    }

    function resetUI() {
        resultDiv.style.display = 'none';
        previewSec.style.display = 'none';
        progressDiv.style.display = 'none';
        progressFill.style.width = '0%';
        previewThead.innerHTML = '';
        previewTbody.innerHTML = '';
    }

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
})();
</script>
