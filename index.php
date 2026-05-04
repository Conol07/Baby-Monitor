<?php
require_once 'includes/config.php';

$db = getDB();

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? '';
    header('Location: ' . ($role === 'admin' ? 'admin/dashboard.php' : 'babysitter/dashboard.php'));
    exit;
}

$error = '';
$msg = sanitize($_GET['msg'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['session_token'] = session_id();

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $device = $_SERVER['HTTP_USER_AGENT'] ?? 'Browser / Unknown';

            $logStmt = $db->prepare("
                INSERT INTO session_logs 
                (user_id, session_token, logged_in_at, status, ip_address, device)
                VALUES (?, ?, NOW(), 'active', ?, ?)
            ");

            $logStmt->execute([
                $user['id'],
                $_SESSION['session_token'],
                $ip_address,
                $device
            ]);

            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
               ->execute([$user['id']]);

            $db->prepare("INSERT IGNORE INTO notification_preferences (user_id) VALUES (?)")
               ->execute([$user['id']]);

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
  --cream: #fef6ec;
  --peach: #f97316;
  --soft: #fff8f3;
  --text: #2d1a0e;
  --muted: #9a7b6a;
  --card: #ffffff;
  --border: #f0e0d6;
  --shadow: rgba(249,115,22,0.12);
}

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--cream);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.login-wrap {
  display: grid;
  grid-template-columns: 1fr 1fr;
  max-width: 860px;
  width: 95%;
  background: var(--card);
  border-radius: 28px;
  overflow: hidden;
  box-shadow: 0 20px 80px var(--shadow), 0 2px 8px rgba(0,0,0,0.04);
}

.panel-left {
  background: linear-gradient(145deg, #ff8c5a 0%, #f97316 40%, #e85d04 100%);
  padding: 56px 44px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.logo-icon {
  font-size: 48px;
  display: block;
  margin-bottom: 12px;
}

.logo h1 {
  font-family: 'Fraunces', serif;
  font-size: 2.2rem;
  color: white;
}

.logo p {
  color: rgba(255,255,255,0.85);
  font-size: 0.85rem;
  margin-top: 6px;
}

.features-list {
  list-style: none;
}

.features-list li {
  color: rgba(255,255,255,0.9);
  font-size: 0.9rem;
  padding: 8px 0;
}

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
  margin-bottom: 6px;
}

.subtitle {
  color: var(--muted);
  font-size: 0.88rem;
  margin-bottom: 36px;
}

.form-group { margin-bottom: 20px; }

.form-group label {
  display: block;
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--text);
  margin-bottom: 7px;
  text-transform: uppercase;
}

.form-group input {
  width: 100%;
  padding: 13px 16px;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  font-size: 0.95rem;
  background: var(--soft);
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
  cursor: pointer;
}

.btn-register {
  width: 100%;
  padding: 13px;
  color: var(--peach);
  border: 1.5px solid var(--peach);
  border-radius: 12px;
  margin-top: 10px;
  display: block;
  text-align: center;
  text-decoration: none;
}

.divider {
  text-align: center;
  margin: 18px 0 8px;
  color: var(--muted);
  font-size: 0.78rem;
}

.quick-login {
  margin-top: 20px;
  text-align: center;
}

.quick-login p {
  font-size: 0.8rem;
  color: var(--muted);
  margin-bottom: 10px;
}

.quick-btns {
  display: flex;
  gap: 10px;
}

.q-btn {
  flex: 1;
  padding: 9px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  background: var(--soft);
  cursor: pointer;
}

@media (max-width: 640px) {
  .login-wrap { grid-template-columns: 1fr; }
  .panel-left { display: none; }
  .panel-right { padding: 40px 28px; }
}
</style>
</head>

<body>

<div class="login-wrap">

  <div class="panel-left">
    <div class="logo">
      <span class="logo-icon">👶</span>
      <h1>BabyWatch</h1>
      <p>Smart Baby Monitoring System</p>
    </div>

    <ul class="features-list">
      <li>• Real-time sound classification</li>
      <li>• Instant alerts & notifications</li>
      <li>• Activity logs & history</li>
      <li>• Analytics & insights</li>
      <li>• Multi-role access control</li>
    </ul>
  </div>

  <div class="panel-right">
    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to your monitoring dashboard</p>

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
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>

      <button type="submit" class="btn-login">Sign In →</button>
    </form>

    <div class="divider">or</div>

    <a href="register_parent.php" class="btn-register">Create a Parent Account</a>

    <div class="quick-login">
      <p>Quick login as:</p>
      <div class="quick-btns">
        <button class="q-btn" onclick="quickLogin('parent@demo.com')">👪 Parent</button>
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