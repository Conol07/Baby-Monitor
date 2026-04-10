<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? '';
    header('Location: ' . ($role === 'admin' ? 'admin/dashboard.php' : 'babysitter/dashboard.php'));
    exit;
}

$error = '';
$msg   = sanitize($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email']= $user['email'];

            // Update last login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            // Init notification prefs if missing
            $db->prepare("INSERT IGNORE INTO notification_preferences (user_id) VALUES (?)")->execute([$user['id']]);

            header('Location: ' . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'babysitter/dashboard.php'));
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BabyWatch — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,300;0,600;1,300&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --cream:   #fef6ec;
    --blush:   #f9c5c5;
    --peach:   #f97316;
    --soft:    #fff8f3;
    --text:    #2d1a0e;
    --muted:   #9a7b6a;
    --card:    #ffffff;
    --border:  #f0e0d6;
    --shadow:  rgba(249,115,22,0.12);
  }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--cream);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
  }

  /* Background blobs */
  body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    z-index: 0;
    filter: blur(80px);
    opacity: 0.5;
  }
  body::before {
    width: 500px; height: 500px;
    background: radial-gradient(circle, #f9c5c5 0%, transparent 70%);
    top: -150px; right: -150px;
  }
  body::after {
    width: 400px; height: 400px;
    background: radial-gradient(circle, #fde68a 0%, transparent 70%);
    bottom: -100px; left: -100px;
  }

  .login-wrap {
    position: relative; z-index: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 860px;
    width: 95%;
    background: var(--card);
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 20px 80px var(--shadow), 0 2px 8px rgba(0,0,0,0.04);
    animation: fadeUp 0.6s ease both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* Left panel */
  .panel-left {
    background: linear-gradient(145deg, #ff8c5a 0%, #f97316 40%, #e85d04 100%);
    padding: 56px 44px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
  }
  .panel-left::before {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    background: rgba(255,255,255,0.08);
    border-radius: 50%;
    bottom: -80px; right: -80px;
  }
  .panel-left::after {
    content: '';
    position: absolute;
    width: 180px; height: 180px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
    top: 30px; left: -60px;
  }

  .logo {
    position: relative; z-index: 1;
  }
  .logo-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 12px;
    animation: pulse 2s ease-in-out infinite;
  }
  @keyframes pulse {
    0%, 100% { transform: scale(1); }
    50%       { transform: scale(1.08); }
  }
  .logo h1 {
    font-family: 'Fraunces', serif;
    font-size: 2.2rem;
    color: white;
    font-weight: 600;
    line-height: 1;
  }
  .logo p {
    color: rgba(255,255,255,0.8);
    font-size: 0.85rem;
    margin-top: 6px;
    font-weight: 300;
    letter-spacing: 0.05em;
  }

  .panel-left-body {
    position: relative; z-index: 1;
  }
  .features-list {
    list-style: none;
  }
  .features-list li {
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
    padding: 8px 0;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .features-list li span.dot {
    width: 8px; height: 8px;
    background: rgba(255,255,255,0.6);
    border-radius: 50%;
    flex-shrink: 0;
  }

  /* Right panel */
  .panel-right {
    padding: 56px 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .panel-right h2 {
    font-family: 'Fraunces', serif;
    font-size: 1.8rem;
    color: var(--text);
    font-weight: 600;
    margin-bottom: 6px;
  }
  .panel-right .subtitle {
    color: var(--muted);
    font-size: 0.88rem;
    margin-bottom: 36px;
  }

  .demo-box {
    background: var(--soft);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 28px;
    font-size: 0.82rem;
    color: var(--muted);
    line-height: 1.7;
  }
  .demo-box strong { color: var(--text); }

  .form-group { margin-bottom: 20px; }
  .form-group label {
    display: block;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--text);
    margin-bottom: 7px;
    letter-spacing: 0.03em;
    text-transform: uppercase;
  }
  .form-group input {
    width: 100%;
    padding: 13px 16px;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    font-size: 0.95rem;
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    background: var(--soft);
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
  }
  .form-group input:focus {
    border-color: var(--peach);
    box-shadow: 0 0 0 3px rgba(249,115,22,0.1);
  }

  .error-msg {
    background: #fff1f1;
    border: 1px solid #fecaca;
    color: #dc2626;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 18px;
  }
  .success-msg {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #16a34a;
    padding: 10px 14px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 18px;
  }

  .btn-login {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #f97316, #e85d04);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
    letter-spacing: 0.02em;
  }
  .btn-login:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 24px rgba(249,115,22,0.35);
  }
  .btn-login:active { transform: translateY(0); }

  .quick-login {
    margin-top: 20px;
    text-align: center;
  }
  .quick-login p { font-size: 0.8rem; color: var(--muted); margin-bottom: 10px; }
  .quick-btns { display: flex; gap: 10px; }
  .q-btn {
    flex: 1;
    padding: 9px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    background: var(--soft);
    font-size: 0.8rem;
    color: var(--text);
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    transition: border-color 0.2s, background 0.2s;
  }
  .q-btn:hover { border-color: var(--peach); background: #fff8f3; }

  @media (max-width: 640px) {
    .login-wrap { grid-template-columns: 1fr; }
    .panel-left { display: none; }
    .panel-right { padding: 40px 28px; }
  }
</style>
</head>
<body>
<div class="login-wrap">
  <!-- Left Panel -->
  <div class="panel-left">
    <div class="logo">
      <span class="logo-icon">👶</span>
      <h1>BabyWatch</h1>
      <p>Smart Baby Monitoring System</p>
    </div>
    <div class="panel-left-body">
      <ul class="features-list">
        <li><span class="dot"></span> Real-time sound classification</li>
        <li><span class="dot"></span> Instant alerts & notifications</li>
        <li><span class="dot"></span> Activity logs & history</li>
        <li><span class="dot"></span> Analytics & insights</li>
        <li><span class="dot"></span> Multi-role access control</li>
      </ul>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="panel-right">
    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to your monitoring dashboard</p>

   <!-- <div class="demo-box">
      <strong>Demo Credentials:</strong><br>
      Parent: <strong>parent@demo.com</strong> / <strong>demo1234</strong><br>
      Babysitter: <strong>sitter@demo.com</strong> / <strong>demo1234</strong>
    </div>
      -->
    <?php if ($error): ?>
      <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($msg === 'logged_out'): ?>
      <div class="success-msg">✅ You have been logged out.</div>
    <?php elseif ($msg === 'login_required'): ?>
      <div class="error-msg">🔒 Please sign in to continue.</div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required autocomplete="email">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-login">Sign In →</button>
    </form>

    <div class="quick-login">
      <p>Quick login as:</p>
      <div class="quick-btns">
        <button class="q-btn" onclick="quickLogin('parent@demo.com')">👪 Parent (Admin)</button>
        <button class="q-btn" onclick="quickLogin('sitter@demo.com')">🤱 Babysitter</button>
      </div>
    </div>
  </div>
</div>

<script>
function quickLogin(email) {
  document.querySelector('input[name="email"]').value = email;
  document.querySelector('input[name="password"]').value = 'demo1234';
}
</script>
</body>
</html>
