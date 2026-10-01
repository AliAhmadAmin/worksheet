<?php
/**
 * Dedicated Login Page: Discover Pakistan WorkSheet Pro
 * Secure Email & Password Login with Automatic Role & Department Landing
 */
require_once __DIR__ . '/config/database.php';

// If already logged in, redirect straight to user's assigned dashboard
if (!empty($_SESSION['user_id'])) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT e.*, d.name as department_name 
        FROM employees e 
        LEFT JOIN departments d ON e.department_id = d.id 
        WHERE e.id = ? AND e.is_active = 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $deptLower = strtolower($user['department_name'] ?? '');
        if ($deptLower === 'hr' || $deptLower === 'human resources') {
            header('Location: hr.php');
            exit;
        }
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In • WorkSheet Pro | Discover Pakistan TV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --login-bg: #0b1329;
            --login-card-bg: rgba(17, 24, 39, 0.85);
            --login-card-border: rgba(255, 255, 255, 0.1);
            --login-text: #f8fafc;
            --login-text-muted: #94a3b8;
            --login-input-bg: rgba(15, 23, 42, 0.7);
            --login-input-border: rgba(255, 255, 255, 0.12);
        }

        body[data-theme="light"] {
            --login-bg: #f1f5f9;
            --login-card-bg: rgba(255, 255, 255, 0.95);
            --login-card-border: rgba(226, 232, 240, 0.8);
            --login-text: #0f172a;
            --login-text-muted: #64748b;
            --login-input-bg: #ffffff;
            --login-input-border: #cbd5e1;
        }

        body.login-page-body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: var(--login-bg);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Glowing Background Accents */
        .login-ambient-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.35;
            pointer-events: none;
            z-index: 0;
            animation: orbFloat 14s ease-in-out infinite alternate;
        }
        .orb-1 {
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, #0284c7, transparent 70%);
            top: -120px;
            left: -120px;
        }
        .orb-2 {
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, #10b981, transparent 70%);
            bottom: -100px;
            right: -100px;
            animation-delay: -5s;
        }
        .orb-3 {
            width: 360px;
            height: 360px;
            background: radial-gradient(circle, #6366f1, transparent 70%);
            top: 40%;
            right: 20%;
            animation-delay: -9s;
        }
        @keyframes orbFloat {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(40px, 30px) scale(1.1); }
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            padding: 24px;
            box-sizing: border-box;
        }

        .login-card {
            background: var(--login-card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--login-card-border);
            border-radius: 24px;
            padding: 36px 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo {
            height: 46px;
            max-width: 180px;
            object-fit: contain;
            margin-bottom: 14px;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.2));
        }
        .login-title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: var(--login-text);
            letter-spacing: -0.02em;
        }
        .login-subtitle {
            margin: 6px 0 0;
            font-size: 13.5px;
            color: var(--login-text-muted);
        }

        .login-form-group {
            margin-bottom: 20px;
        }
        .login-form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--login-text);
        }
        .login-input-box {
            position: relative;
            display: flex;
            align-items: center;
        }
        .login-input-icon {
            position: absolute;
            left: 14px;
            font-size: 15px;
            color: var(--login-text-muted);
            pointer-events: none;
        }
        .login-input {
            width: 100%;
            box-sizing: border-box;
            background: var(--login-input-bg);
            border: 1.5px solid var(--login-input-border);
            border-radius: 12px;
            padding: 12px 14px 12px 42px;
            font-size: 14px;
            font-family: inherit;
            color: var(--login-text);
            outline: none;
            transition: all 0.2s ease;
        }
        .login-input:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.25);
            background: var(--login-input-bg);
        }
        .login-input::placeholder {
            color: var(--login-text-muted);
            opacity: 0.8;
        }

        .btn-eye-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .btn-eye-toggle:hover {
            opacity: 1;
        }

        .login-options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 16px 0 24px;
            font-size: 13px;
        }
        .remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--login-text-muted);
            cursor: pointer;
            font-weight: 500;
            user-select: none;
        }
        .remember-checkbox {
            accent-color: #0284c7;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .btn-login-submit {
            width: 100%;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 13px 20px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-login-submit:hover {
            background: linear-gradient(135deg, #0369a1 0%, #075985 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(2, 132, 199, 0.5);
        }
        .btn-login-submit:active {
            transform: translateY(0);
        }
        .btn-login-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        .login-alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
        }
        .login-alert.error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        .login-alert.success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }

        .login-theme-btn {
            position: absolute;
            top: 24px;
            right: 24px;
            background: var(--login-card-bg);
            border: 1px solid var(--login-card-border);
            color: var(--login-text);
            border-radius: 50%;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
            z-index: 10;
        }
        .login-theme-btn:hover {
            transform: scale(1.08);
        }

        .login-footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--login-text-muted);
        }
    </style>
</head>
<body class="login-page-body" data-theme="dark">
    <!-- Theme Toggle Floating Button -->
    <button type="button" class="login-theme-btn" id="theme-toggle-btn" title="Toggle Dark/Light Mode">🌙</button>

    <!-- Glowing Background Ambient Orbs -->
    <div class="login-ambient-orb orb-1"></div>
    <div class="login-ambient-orb orb-2"></div>
    <div class="login-ambient-orb orb-3"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <!-- Brand Header -->
            <div class="login-brand">
                <img src="assets/img/logo.svg" alt="Discover Pakistan UHD TV" class="login-logo">
                <h1 class="login-title">Sign In to WorkSheet</h1>
                <p class="login-subtitle">Enter your work credentials to access your portal</p>
            </div>

            <!-- Error / Success Alert Box -->
            <div id="login-alert-box" class="login-alert">
                <span id="login-alert-icon">⚠️</span>
                <span id="login-alert-msg">Error message</span>
            </div>

            <form id="dedicated-login-form" onsubmit="handleDedicatedLogin(event)">
                <!-- Work Email Input -->
                <div class="login-form-group">
                    <label class="login-form-label" for="login-email">Email Address</label>
                    <div class="login-input-box">
                        <span class="login-input-icon">✉️</span>
                        <input type="email" id="login-email" class="login-input" placeholder="e.g. name@discoverpakistan.tv" required autocomplete="username" autofocus>
                    </div>
                </div>

                <!-- Password Input -->
                <div class="login-form-group">
                    <label class="login-form-label" for="login-password">Password</label>
                    <div class="login-input-box">
                        <span class="login-input-icon">🔒</span>
                        <input type="password" id="login-password" class="login-input" placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="btn-eye-toggle" onclick="togglePasswordVisibility()" title="Show/Hide Password">
                            <span id="eye-icon">👁️</span>
                        </button>
                    </div>
                </div>

                <!-- Remember Email Checkbox -->
                <div class="login-options-row">
                    <label class="remember-label">
                        <input type="checkbox" id="login-remember-me" class="remember-checkbox">
                        <span>Remember my email</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="btn-submit" class="btn-login-submit">
                    <span id="btn-submit-icon">🚀</span>
                    <span id="btn-submit-text">Sign In to Dashboard</span>
                </button>
            </form>

            <div class="login-footer-text">
                🌟 Discover Pakistan UHD TV • Teamwork & Excellence
            </div>
        </div>
    </div>

    <script>
        // Theme Management
        const savedTheme = localStorage.getItem('worksheet_theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
        const themeBtn = document.getElementById('theme-toggle-btn');
        if (themeBtn) {
            themeBtn.textContent = savedTheme === 'dark' ? '🌙' : '☀️';
            themeBtn.addEventListener('click', () => {
                const current = document.body.getAttribute('data-theme') || 'dark';
                const next = current === 'dark' ? 'light' : 'dark';
                document.body.setAttribute('data-theme', next);
                localStorage.setItem('worksheet_theme', next);
                themeBtn.textContent = next === 'dark' ? '🌙' : '☀️';
            });
        }

        // Pre-fill Remembered Email
        document.addEventListener('DOMContentLoaded', () => {
            const savedEmail = localStorage.getItem('worksheet_remembered_email');
            const emailInput = document.getElementById('login-email');
            const rememberCheckbox = document.getElementById('login-remember-me');
            if (savedEmail && emailInput) {
                emailInput.value = savedEmail;
                if (rememberCheckbox) rememberCheckbox.checked = true;
                const passInput = document.getElementById('login-password');
                if (passInput) passInput.focus();
            }
        });

        // Show/Hide Password Toggle
        function togglePasswordVisibility() {
            const passInput = document.getElementById('login-password');
            const eyeIcon = document.getElementById('eye-icon');
            if (!passInput) return;
            if (passInput.type === 'password') {
                passInput.type = 'text';
                if (eyeIcon) eyeIcon.textContent = '🙈';
            } else {
                passInput.type = 'password';
                if (eyeIcon) eyeIcon.textContent = '👁️';
            }
        }

        // Show Alert Helper
        function showAlert(message, type = 'error') {
            const alertBox = document.getElementById('login-alert-box');
            const alertIcon = document.getElementById('login-alert-icon');
            const alertMsg = document.getElementById('login-alert-msg');
            if (!alertBox) return;

            alertBox.className = `login-alert ${type}`;
            if (alertIcon) alertIcon.textContent = type === 'error' ? '❌' : '✅';
            if (alertMsg) alertMsg.textContent = message;
            alertBox.style.display = 'flex';
        }

        function hideAlert() {
            const alertBox = document.getElementById('login-alert-box');
            if (alertBox) alertBox.style.display = 'none';
        }

        // Form Submit Handler
        async function handleDedicatedLogin(e) {
            e.preventDefault();
            hideAlert();

            const email = document.getElementById('login-email')?.value.trim();
            const password = document.getElementById('login-password')?.value.trim();
            const rememberMe = document.getElementById('login-remember-me')?.checked;
            const btnSubmit = document.getElementById('btn-submit');
            const btnText = document.getElementById('btn-submit-text');
            const btnIcon = document.getElementById('btn-submit-icon');

            if (!email) {
                showAlert("Please enter your work email address.", "error");
                return;
            }
            if (!password) {
                showAlert("Please enter your account password.", "error");
                return;
            }

            // Button loading state
            if (btnSubmit) btnSubmit.disabled = true;
            if (btnText) btnText.textContent = "Authenticating...";
            if (btnIcon) btnIcon.textContent = "⏳";

            try {
                const res = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'login',
                        email: email,
                        password: password
                    })
                });
                const data = await res.json();

                if (data.success && data.user) {
                    // Remember Email Preference
                    if (rememberMe) {
                        localStorage.setItem('worksheet_remembered_email', email);
                    } else {
                        localStorage.removeItem('worksheet_remembered_email');
                    }

                    showAlert(`Welcome back, ${data.user.name}! Redirecting...`, "success");
                    if (btnText) btnText.textContent = "Redirecting...";
                    if (btnIcon) btnIcon.textContent = "✅";

                    // Redirect based on employee department / role
                    const destination = data.redirect_url || 'index.php';
                    setTimeout(() => {
                        window.location.href = destination;
                    }, 400);

                } else {
                    showAlert(data.message || "Invalid email or password.", "error");
                    if (btnSubmit) btnSubmit.disabled = false;
                    if (btnText) btnText.textContent = "Sign In to Dashboard";
                    if (btnIcon) btnIcon.textContent = "🚀";
                }
            } catch (err) {
                showAlert("Failed to connect to authentication service. Please check server.", "error");
                if (btnSubmit) btnSubmit.disabled = false;
                if (btnText) btnText.textContent = "Sign In to Dashboard";
                if (btnIcon) btnIcon.textContent = "🚀";
            }
        }
    </script>
</body>
</html>
