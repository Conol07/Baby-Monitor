<?php
session_start();

// Database configuration
$host     = 'localhost';
$db       = 'baby_monitor';
$user     = 'root';
$pass     = '';
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$errors   = [];
$success  = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize & collect input
    $formData['first_name']    = trim($_POST['first_name']    ?? '');
    $formData['last_name']     = trim($_POST['last_name']     ?? '');
    $formData['email']         = trim($_POST['email']         ?? '');
    $formData['phone']         = trim($_POST['phone']         ?? '');
    $formData['relationship']  = trim($_POST['relationship']  ?? '');
    $password                  = $_POST['password']           ?? '';
    $confirm_password          = $_POST['confirm_password']   ?? '';
    $terms                     = isset($_POST['terms']);

    // Validation
    if (empty($formData['first_name'])) {
        $errors['first_name'] = 'First name is required.';
    }
    if (empty($formData['last_name'])) {
        $errors['last_name'] = 'Last name is required.';
    }
    if (empty($formData['email'])) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (empty($formData['phone'])) {
        $errors['phone'] = 'Phone number is required.';
    }
    if (empty($formData['relationship'])) {
        $errors['relationship'] = 'Please select your relationship to the baby.';
    }
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    if (!$terms) {
        $errors['terms'] = 'You must agree to the Terms of Service and Privacy Policy.';
    }

    // If no validation errors, save to DB
    if (empty($errors)) {
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);

            // Check for duplicate email
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$formData['email']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'An account with this email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);

                // Combine first + last into 'name' column to match actual users table schema
                $fullName = trim($formData['first_name'] . ' ' . $formData['last_name']);

                $stmt = $pdo->prepare('
                    INSERT INTO users (name, email, password, role, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $fullName,
                    $formData['email'],
                    $hashed,
                    'admin',  // parent = admin role (matches login redirect to admin/dashboard.php)
                ]);

                // Store session & redirect
                $_SESSION['user_id']    = $pdo->lastInsertId();
                $_SESSION['user_name']  = $fullName;
                $_SESSION['user_role']  = 'admin';

                $success = 'Account created successfully! Redirecting...';
                header('refresh:2; url=admin/dashboard.php');
            }
        } catch (PDOException $e) {
            $errors['db'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Helper: field has error?
function fieldError($field, $errors) {
    return isset($errors[$field]) ? 'border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,0.18);' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BabyWatch — Create Parent Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Lato', sans-serif;
            background: #fef3e8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .card {
            display: flex;
            width: 100%;
            max-width: 820px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 40px rgba(0,0,0,0.10);
            background: #fff;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 240px;
            flex-shrink: 0;
            background: linear-gradient(160deg, #f97316 0%, #ea580c 60%, #c2410c 100%);
            padding: 36px 28px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        .sidebar::before {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            bottom: -60px; right: -60px;
        }
        .sidebar::after {
            content: '';
            position: absolute;
            width: 130px; height: 130px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            bottom: 80px; right: -30px;
        }
        .sidebar-logo {
            font-family: 'Nunito', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            margin-top: 10px;
        }
        .sidebar-tagline {
            font-size: 12px;
            color: rgba(255,255,255,0.75);
            margin-top: 3px;
            margin-bottom: 40px;
        }
        .sidebar-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: auto;
            position: relative;
            z-index: 1;
        }
        .sidebar-features li {
            font-size: 12.5px;
            color: rgba(255,255,255,0.88);
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            flex-shrink: 0;
        }

        /* ── Main form ── */
        .main {
            flex: 1;
            padding: 36px 40px;
            overflow-y: auto;
        }
        .main h1 {
            font-family: 'Nunito', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        .subtitle {
            font-size: 14px;
            color: #888;
            margin-bottom: 22px;
        }

        /* Progress bar */
        .progress-bar {
            display: flex;
            gap: 6px;
            margin-bottom: 22px;
        }
        .progress-bar span {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: #f3f3f3;
        }
        .progress-bar span.active { background: #f97316; }

        /* Alert */
        .alert {
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            margin-bottom: 18px;
        }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fff7f0; color: #c2410c; border: 1px solid #fcd9b7; }

        /* Form grid */
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 14px;
        }
        .field.full { grid-column: 1 / -1; }

        label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #888;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"],
        select {
            background: #f5f6fa;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            color: #1a1a1a;
            font-family: 'Lato', sans-serif;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
            width: 100%;
        }
        input:focus, select:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249,115,22,0.15);
        }
        select { appearance: none; cursor: pointer; }

        .pass-wrap { position: relative; }
        .pass-wrap input { padding-right: 40px; }
        .eye-btn {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 15px;
            color: #aaa;
            padding: 0;
        }

        .err-msg {
            font-size: 11.5px;
            color: #ea580c;
            margin-top: 2px;
        }

        /* Terms */
        .terms {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12.5px;
            color: #666;
            margin-bottom: 18px;
        }
        .terms input[type="checkbox"] {
            width: 15px; height: 15px;
            flex-shrink: 0;
            margin-top: 2px;
            accent-color: #f97316;
        }
        .terms a { color: #f97316; font-weight: 700; text-decoration: none; }

        /* Submit */
        .btn-submit {
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            background: #f97316;
            border: none;
            color: #fff;
            font-family: 'Nunito', sans-serif;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
        }
        .btn-submit:hover  { background: #ea580c; }
        .btn-submit:active { transform: scale(0.98); }

        .login-link {
            text-align: center;
            font-size: 13px;
            color: #888;
            margin-top: 14px;
        }
        .login-link a { color: #f97316; font-weight: 700; text-decoration: none; }

        @media (max-width: 620px) {
            .sidebar { display: none; }
            .main { padding: 28px 22px; }
            .row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="card">

    <!-- Sidebar -->
    <div class="sidebar">
        <div style="font-size:36px;line-height:1">👶</div>
        <div class="sidebar-logo">BabyWatch</div>
        <div class="sidebar-tagline">Smart Baby Monitoring System</div>
        <ul class="sidebar-features">
            <li><span class="dot"></span>Real-time sound classification</li>
            <li><span class="dot"></span>Instant alerts &amp; notifications</li>
            <li><span class="dot"></span>Activity logs &amp; history</li>
            <li><span class="dot"></span>Analytics &amp; insights</li>
            <li><span class="dot"></span>Multi-role access control</li>
        </ul>
    </div>

    <!-- Main -->
    <div class="main">
        <h1>Create your account</h1>
        <p class="subtitle">Register as a Parent to start monitoring</p>

        <div class="progress-bar">
            <span class="active"></span>
            <span class="active"></span>
            <span></span>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (isset($errors['db'])): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($errors['db']) ?></div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>

            <div class="row">
                <div class="field">
                    <label for="first_name">First Name</label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        placeholder="Maria"
                        value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>"
                        style="<?= fieldError('first_name', $errors) ?>"
                        required>
                    <?php if (isset($errors['first_name'])): ?>
                        <span class="err-msg"><?= $errors['first_name'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="last_name">Last Name</label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        placeholder="Santos"
                        value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>"
                        style="<?= fieldError('last_name', $errors) ?>"
                        required>
                    <?php if (isset($errors['last_name'])): ?>
                        <span class="err-msg"><?= $errors['last_name'] ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="field">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="parent@mail.com"
                    value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                    style="<?= fieldError('email', $errors) ?>"
                    required>
                <?php if (isset($errors['email'])): ?>
                    <span class="err-msg"><?= $errors['email'] ?></span>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="field">
                    <label for="phone">Phone Number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="+63 912 345 6789"
                        value="<?= htmlspecialchars($formData['phone'] ?? '') ?>"
                        style="<?= fieldError('phone', $errors) ?>"
                        required>
                    <?php if (isset($errors['phone'])): ?>
                        <span class="err-msg"><?= $errors['phone'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="relationship">Relationship to Baby</label>
                    <select
                        id="relationship"
                        name="relationship"
                        style="<?= fieldError('relationship', $errors) ?>"
                        required>
                        <option value="">Select...</option>
                        <option value="Mother"   <?= ($formData['relationship'] ?? '') === 'Mother'   ? 'selected' : '' ?>>Mother</option>
                        <option value="Father"   <?= ($formData['relationship'] ?? '') === 'Father'   ? 'selected' : '' ?>>Father</option>
                        <option value="Guardian" <?= ($formData['relationship'] ?? '') === 'Guardian' ? 'selected' : '' ?>>Guardian</option>
                    </select>
                    <?php if (isset($errors['relationship'])): ?>
                        <span class="err-msg"><?= $errors['relationship'] ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <div class="field">
                    <label for="password">Password</label>
                    <div class="pass-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            style="<?= fieldError('password', $errors) ?>"
                            required>
                        <button type="button" class="eye-btn" onclick="togglePw('password', this)">👁</button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <span class="err-msg"><?= $errors['password'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="pass-wrap">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="••••••••"
                            style="<?= fieldError('confirm_password', $errors) ?>"
                            required>
                        <button type="button" class="eye-btn" onclick="togglePw('confirm_password', this)">👁</button>
                    </div>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="err-msg"><?= $errors['confirm_password'] ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="terms">
                <input
                    type="checkbox"
                    id="terms"
                    name="terms"
                    <?= isset($_POST['terms']) ? 'checked' : '' ?>>
                <label for="terms">
                    I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a> of BabyWatch.
                </label>
            </div>
            <?php if (isset($errors['terms'])): ?>
                <span class="err-msg" style="display:block;margin:-10px 0 14px;"><?= $errors['terms'] ?></span>
            <?php endif; ?>

            <button type="submit" class="btn-submit">Create Parent Account →</button>
        </form>

        <p class="login-link">Already have an account? <a href="login.php">Sign in</a></p>

    </div>

</div>

<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type  = (inp.type === 'password') ? 'text' : 'password';
    btn.style.opacity = (inp.type === 'text') ? '1' : '0.5';
}
</script>

</body>
</html>