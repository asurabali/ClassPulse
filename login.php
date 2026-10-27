<?php
session_start();
// Redirect to dashboard if already logged in
if (isset($_SESSION['admin_id'])) {
    header("location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ClassPulse — Classroom Analytics System. Secure admin login.">
    <title>Login | ClassPulse — Classroom Analytics System</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <!-- ClassPulse CSS -->
    <link rel="stylesheet" href="css/classpulse.css">

    <style>
        /* Animated background particles */
        .bg-particles {
            position: fixed;
            inset: 0;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }
        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(108,99,255,0.4);
            border-radius: 50%;
            animation: floatUp var(--dur) ease-in infinite;
            animation-delay: var(--delay);
        }
        @keyframes floatUp {
            0%   { transform: translateY(110vh) scale(0); opacity: 0; }
            10%  { opacity: 1; }
            90%  { opacity: 0.5; }
            100% { transform: translateY(-10vh) scale(1.5); opacity: 0; }
        }

        .login-wrapper { position: relative; z-index: 1; }
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }
        .divider span { flex: 1; height: 1px; background: var(--cp-border); }
        .divider p { color: var(--cp-text-muted); font-size: 12px; }

        .demo-creds {
            background: rgba(108,99,255,0.08);
            border: 1px solid rgba(108,99,255,0.2);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            font-size: 12px;
            color: var(--cp-text-secondary);
            margin-bottom: 20px;
        }
        .demo-creds strong { color: var(--cp-primary); }
    </style>
</head>
<body>
    <!-- Animated background -->
    <div class="bg-particles" id="particles"></div>

    <div class="login-page login-wrapper">
        <div class="login-card">
            <!-- Logo -->
            <div class="login-logo">
                <div class="brand-icon">🎓</div>
                <h1>Class<span>Pulse</span></h1>
                <p>Classroom Analytics System</p>
            </div>

            <!-- Demo Credentials -->
            <div class="demo-creds">
                <strong>Demo:</strong>&nbsp;
                Username: <strong>admin</strong> &nbsp;|&nbsp;
                Password: <strong>admin123</strong>
            </div>

            <!-- Error Message -->
            <div class="login-error" id="login-error">Invalid username or password.</div>

            <!-- Login Form -->
            <form id="login-form" autocomplete="on">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-wrap">
                        <span class="input-icon">👤</span>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-control"
                            placeholder="Enter username"
                            autofocus
                            required
                            autocomplete="username"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter password"
                            required
                            autocomplete="current-password"
                        >
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100" id="login-btn">
                    Sign In to Dashboard
                </button>
            </form>

            <div class="divider"><span></span><p>or</p><span></span></div>

            <a href="attendance.php" class="btn btn-outline w-100">
                🏫 Go to Student Check-In Portal
            </a>

            <p class="text-center mt-16 text-muted" style="font-size:12px">
                ClassPulse v2.0 — Classroom Analytics System
            </p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/classpulse.js"></script>
    <script>
    // Generate floating particles
    (function() {
        const container = document.getElementById('particles');
        for (let i = 0; i < 25; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            p.style.cssText = `
                left: ${Math.random() * 100}%;
                --dur: ${6 + Math.random() * 10}s;
                --delay: ${-Math.random() * 15}s;
                width: ${2 + Math.random() * 4}px;
                height: ${2 + Math.random() * 4}px;
                background: rgba(${Math.random() > 0.5 ? '108,99,255' : '0,212,170'},${0.2 + Math.random() * 0.4});
            `;
            container.appendChild(p);
        }
    })();

    // Login form handler
    document.getElementById('login-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn   = document.getElementById('login-btn');
        const errEl = document.getElementById('login-error');
        errEl.style.display = 'none';

        CP.ajax({
            url: 'Actions.php?a=login',
            data: new FormData(this),
            btn,
            onSuccess: function() {
                btn.innerHTML = '✅ Redirecting...';
                setTimeout(() => { window.location.href = './index.php'; }, 800);
            },
            onError: function(resp) {
                errEl.textContent = resp.msg || 'Login failed. Check your credentials.';
                errEl.style.display = 'block';
            }
        });
    });
    </script>
</body>
</html>