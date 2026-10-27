<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ClassPulse — Student Attendance Check-In Portal">
    <title>Check-In Portal | ClassPulse</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <!-- ClassPulse CSS -->
    <link rel="stylesheet" href="css/classpulse.css">

    <style>
        /* Animated gradient background */
        body {
            background: var(--cp-bg);
            overflow: hidden;
        }

        .bg-anim {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse at 15% 40%, rgba(108,99,255,0.18) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 60%, rgba(0,212,170,0.12) 0%, transparent 55%),
                radial-gradient(ellipse at 50% 100%, rgba(255,107,107,0.08) 0%, transparent 50%);
            animation: bgPulse 8s ease-in-out infinite alternate;
        }

        @keyframes bgPulse {
            from { opacity: 0.7; }
            to   { opacity: 1; }
        }

        .terminal-page { position: relative; z-index: 1; }

        /* Success overlay animation */
        .success-overlay {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(15,17,23,0.95);
            z-index: 9999;
            animation: fadeIn 0.3s ease;
            text-align: center;
        }

        .success-icon {
            font-size: 80px;
            animation: bounceIn 0.5s ease;
            margin-bottom: 16px;
        }

        @keyframes bounceIn {
            0%  { transform: scale(0); }
            70% { transform: scale(1.2); }
            100%{ transform: scale(1); }
        }

        .success-name {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--cp-text-primary);
            margin-bottom: 8px;
        }

        .success-msg {
            font-size: 16px;
            font-weight: 600;
        }

        .success-msg.in  { color: var(--cp-secondary); }
        .success-msg.out { color: var(--cp-danger); }

        .recent-list {
            margin-top: 32px;
        }

        .recent-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            background: var(--cp-surface-2);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
            font-size: 13px;
        }

        .recent-item .ri-badge {
            padding: 3px 9px;
            border-radius: var(--radius-full);
            font-size: 11px;
            font-weight: 700;
        }

        .ri-in  { background: var(--cp-success-light); color: var(--cp-success); }
        .ri-out { background: var(--cp-danger-light);  color: var(--cp-danger); }
    </style>
</head>
<body>
<div class="bg-anim"></div>

<div class="terminal-page" style="min-height:100vh;display:flex;flex-direction:column;">

    <!-- Header -->
    <div class="terminal-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:36px;height:36px;background:linear-gradient(135deg,var(--cp-primary),var(--cp-secondary));border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;">🎓</div>
            <div>
                <div style="font-family:'Outfit',sans-serif;font-size:18px;font-weight:700">Class<span style="color:var(--cp-primary)">Pulse</span></div>
                <div style="font-size:11px;color:var(--cp-text-muted)">Student Check-In Portal</div>
            </div>
        </div>
        <a href="./index.php" class="btn btn-outline btn-sm">⚙️ Admin Panel</a>
    </div>

    <!-- Main Body -->
    <div class="terminal-body">
        <div style="width:100%;max-width:960px;display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:start;">

            <!-- LEFT: Check-In Card -->
            <div class="terminal-card">
                <!-- Clock -->
                <div class="clock-display" id="time_display">00:00:00</div>
                <div class="date-display" id="date_display">Loading...</div>
                <input type="hidden" id="date_time" value="">

                <!-- Student Code Input -->
                <div class="terminal-input-wrap">
                    <label class="form-label" for="student_code">Student ID / Code</label>
                    <input
                        type="text"
                        id="student_code"
                        class="form-control"
                        placeholder="Scan or type student code..."
                        autocomplete="off"
                        autofocus
                        style="text-align:center;font-size:16px;font-weight:600;letter-spacing:1px;"
                    >
                </div>

                <!-- Message -->
                <div class="terminal-msg" id="terminal-msg"></div>

                <!-- Action Buttons -->
                <div class="terminal-btns">
                    <button class="terminal-btn time-in att_btn" type="button" data-id="1" id="btn-time-in">
                        ✅ Time In / Present
                    </button>
                    <button class="terminal-btn time-out att_btn" type="button" data-id="2" id="btn-time-out">
                        🚪 Time Out / Absent
                    </button>
                </div>

                <p class="text-muted text-center mt-16" style="font-size:12px">
                    Enter your student code and press <strong>Time In</strong> to mark attendance.
                </p>
            </div>

            <!-- RIGHT: Recent Check-Ins -->
            <div class="cp-card">
                <div class="cp-card-header">
                    <div class="cp-card-title">
                        <span class="title-dot" style="background:var(--cp-secondary)"></span>
                        Today's Check-Ins
                    </div>
                    <span class="badge badge-success" id="today-count">0</span>
                </div>
                <div class="cp-card-body" style="max-height:460px;overflow-y:auto;padding:16px">
                    <div class="recent-list" id="recent-list">
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <p>No check-ins yet today.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- Success Overlay -->
<div class="success-overlay" id="success-overlay" style="display:none">
    <div class="success-icon" id="success-icon">✅</div>
    <div class="success-name" id="success-name">Student Name</div>
    <div class="success-msg" id="success-msg">Marked as Present!</div>
    <div class="text-muted mt-16" style="font-size:13px">Returning to terminal in <span id="countdown">3</span>s...</div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- Scripts -->
<script src="js/jquery-3.6.0.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/classpulse.js"></script>
<script>
// -------- Clock --------
function updateClock() {
    const now  = new Date();
    const hrs  = now.getHours();
    const mins = String(now.getMinutes()).padStart(2,'0');
    const secs = String(now.getSeconds()).padStart(2,'0');
    const ampm = hrs >= 12 ? 'PM' : 'AM';
    const h12  = String(hrs > 12 ? hrs - 12 : hrs || 12).padStart(2,'0');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

    document.getElementById('time_display').textContent = `${h12}:${mins}:${secs} ${ampm}`;
    document.getElementById('date_display').textContent =
        `${days[now.getDay()]}, ${months[now.getMonth()]} ${String(now.getDate()).padStart(2,'0')}, ${now.getFullYear()}`;

    // Hidden full datetime for server
    const fullDt = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')} ${String(hrs).padStart(2,'0')}:${mins}:${secs}`;
    document.getElementById('date_time').value = fullDt;
}
setInterval(updateClock, 1000);
updateClock();

// -------- Recent Check-ins Feed --------
const recentItems = [];
function addRecent(name, code, type) {
    const isIn    = type === 1;
    const item    = { name, code, type, time: document.getElementById('time_display').textContent };
    recentItems.unshift(item);
    if (recentItems.length > 20) recentItems.pop();

    const container = document.getElementById('recent-list');
    const div = document.createElement('div');
    div.className = 'recent-item';
    div.style.animation = 'slideInRight 0.3s ease';
    div.innerHTML = `
        <span>${isIn ? '✅' : '🚪'}</span>
        <div style="flex:1">
            <div style="font-weight:600;color:var(--cp-text-primary)">${name}</div>
            <div style="font-size:11px;color:var(--cp-text-muted)">${code} · ${item.time}</div>
        </div>
        <span class="ri-badge ${isIn ? 'ri-in' : 'ri-out'}">${isIn ? 'IN' : 'OUT'}</span>
    `;
    // Remove empty state if present
    const empty = container.querySelector('.empty-state');
    if (empty) empty.remove();

    container.insertBefore(div, container.firstChild);

    // Update count
    const cnt = document.getElementById('today-count');
    cnt.textContent = parseInt(cnt.textContent || 0) + 1;
}

// -------- Show Success Overlay --------
function showSuccess(name, type) {
    const overlay = document.getElementById('success-overlay');
    const isIn    = type === 1;
    document.getElementById('success-icon').textContent = isIn ? '✅' : '🚪';
    document.getElementById('success-name').textContent = name;
    const msgEl = document.getElementById('success-msg');
    msgEl.textContent = isIn ? '✅ Marked as Present!' : '🚪 Marked as Time Out!';
    msgEl.className   = 'success-msg ' + (isIn ? 'in' : 'out');
    overlay.style.display = 'flex';

    let count = 3;
    document.getElementById('countdown').textContent = count;
    const timer = setInterval(() => {
        count--;
        document.getElementById('countdown').textContent = count;
        if (count <= 0) {
            clearInterval(timer);
            overlay.style.display = 'none';
            document.getElementById('student_code').value = '';
            document.getElementById('student_code').focus();
        }
    }, 1000);
}

// -------- Attendance Buttons --------
document.querySelectorAll('.att_btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const code    = document.getElementById('student_code').value.trim();
        const msgEl   = document.getElementById('terminal-msg');
        const attType = this.dataset.id;
        const dateTime= document.getElementById('date_time').value;

        msgEl.className = 'terminal-msg';
        msgEl.textContent = '';

        if (!code) {
            document.getElementById('student_code').focus();
            msgEl.className = 'terminal-msg error';
            msgEl.textContent = '⚠️ Please enter your student code first.';
            return;
        }

        document.querySelectorAll('.att_btn').forEach(b => b.disabled = true);

        const formData = new FormData();
        formData.append('student_code', code);
        formData.append('att_type', attType);
        formData.append('date_created', dateTime);

        fetch('Actions.php?a=save_attendance', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'success') {
                    const studentName = resp.student_name || code;
                    addRecent(studentName, code, parseInt(attType));
                    showSuccess(studentName, parseInt(attType));
                } else {
                    msgEl.className = 'terminal-msg error';
                    msgEl.textContent = '❌ ' + (resp.msg || 'Error occurred');
                    document.getElementById('student_code').focus();
                }
            })
            .catch(() => {
                msgEl.className = 'terminal-msg error';
                msgEl.textContent = '❌ Network error. Please try again.';
            })
            .finally(() => {
                document.querySelectorAll('.att_btn').forEach(b => b.disabled = false);
            });
    });
});

// Enter key submits Time In
document.getElementById('student_code').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') document.getElementById('btn-time-in').click();
});
</script>
</body>
</html>