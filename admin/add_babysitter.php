<?php
require_once '../includes/config.php';

// Must be logged in as admin/parent
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php?msg=login_required');
    exit;
}

$errors  = [];
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name']     = trim($_POST['name']     ?? '');
    $formData['email']    = trim($_POST['email']    ?? '');
    $formData['phone']    = trim($_POST['phone']    ?? '');
    $formData['notes']    = trim($_POST['notes']    ?? '');
    $password             = $_POST['password']      ?? '';
    $confirm              = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($formData['name']))  $errors['name']  = 'Full name is required.';
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $db = getDB();

            // Check duplicate email
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$formData['email']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'A user with this email already exists.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $db->prepare('
                    INSERT INTO users (name, email, password, role, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $formData['name'],
                    $formData['email'],
                    $hashed,
                    'babysitter',
                ]);

                $newId = $db->lastInsertId();

                // Init notification preferences for the new babysitter
                $db->prepare('INSERT IGNORE INTO notification_preferences (user_id) VALUES (?)')->execute([$newId]);

                $success    = 'Babysitter account created successfully!';
                $formData   = []; // clear form
            }
        } catch (PDOException $e) {
            $errors['db'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch existing babysitters for the list
$babysitters = [];
try {
    $db          = getDB();
    $babysitters = $db->query("SELECT id, name, email, created_at FROM users WHERE role = 'babysitter' ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) { /* silently ignore on fetch error */ }

$userName = $_SESSION['user_name'] ?? 'Admin';
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', $userName))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BabyWatch — Manage Babysitters</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --sidebar-bg:  #1c1007;
  --sidebar-w:   256px;
  --orange:      #f97316;
  --orange-dark: #ea580c;
  --cream:       #fef6ec;
  --text:        #1a1a1a;
  --muted:       #888;
  --border:      #f0e0d6;
  --card:        #ffffff;
  --soft:        #fff8f3;
  --success-bg:  #f0fdf4;
  --success-txt: #16a34a;
  --error-bg:    #fff1f1;
  --error-txt:   #dc2626;
}

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--cream);
  min-height: 100vh;
  display: flex;
}

/* ── Sidebar ── */
.sidebar {
  width: var(--sidebar-w);
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
  padding: 0 0 24px;
}
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 24px 20px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sidebar-brand .brand-name {
  font-family: 'Fraunces', serif;
  font-size: 18px;
  color: #fff;
  font-weight: 600;
}
.sidebar-badge {
  background: var(--orange);
  color: #fff;
  font-size: 9px;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: 4px;
  letter-spacing: 0.08em;
  margin-top: 2px;
}
.sidebar-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 16px 20px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  margin-bottom: 8px;
}
.avatar {
  width: 36px; height: 36px;
  background: var(--orange);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 500; color: #fff;
  flex-shrink: 0;
}
.sidebar-user-name  { font-size: 13px; color: #fff; font-weight: 500; }
.sidebar-user-role  { font-size: 11px; color: rgba(255,255,255,0.45); }

.sidebar-section {
  font-size: 10px;
  letter-spacing: 0.1em;
  color: rgba(255,255,255,0.3);
  padding: 10px 20px 4px;
  text-transform: uppercase;
}
.sidebar-nav a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 20px;
  color: rgba(255,255,255,0.6);
  text-decoration: none;
  font-size: 13.5px;
  border-radius: 8px;
  margin: 1px 10px;
  transition: background 0.15s, color 0.15s;
}
.sidebar-nav a:hover { background: rgba(255,255,255,0.07); color: #fff; }
.sidebar-nav a.active { background: rgba(249,115,22,0.18); color: var(--orange); }
.sidebar-nav a .icon { font-size: 15px; width: 18px; text-align: center; }

.sidebar-bottom {
  margin-top: auto;
  padding: 0 10px;
}
.sidebar-bottom a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 10px;
  color: rgba(255,255,255,0.45);
  text-decoration: none;
  font-size: 13px;
  border-radius: 8px;
  transition: color 0.15s;
}
.sidebar-bottom a:hover { color: #fff; }

/* ── Main ── */
.main {
  margin-left: var(--sidebar-w);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.topbar {
  background: var(--card);
  border-bottom: 1px solid var(--border);
  padding: 16px 32px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.topbar h1 {
  font-family: 'Fraunces', serif;
  font-size: 20px;
  color: var(--text);
  font-weight: 600;
}
.topbar-meta { font-size: 12px; color: var(--muted); }

.content { padding: 28px 32px; }

/* ── Alert boxes ── */
.alert {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13.5px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.alert-success { background: var(--success-bg); color: var(--success-txt); border: 1px solid #bbf7d0; }
.alert-error   { background: var(--error-bg);   color: var(--error-txt);   border: 1px solid #fecaca; }

/* ── Cards ── */
.card {
  background: var(--card);
  border-radius: 16px;
  border: 1px solid var(--border);
  padding: 28px;
  margin-bottom: 24px;
}
.card-title {
  font-family: 'Fraunces', serif;
  font-size: 16px;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ── Form ── */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label {
  font-size: 11px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted);
}
.form-group input,
.form-group textarea {
  padding: 11px 14px;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-size: 14px;
  font-family: 'DM Sans', sans-serif;
  color: var(--text);
  background: var(--soft);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  width: 100%;
}
.form-group input:focus,
.form-group textarea:focus {
  border-color: var(--orange);
  box-shadow: 0 0 0 3px rgba(249,115,22,0.12);
}
.form-group textarea { resize: vertical; min-height: 72px; }
.err-msg { font-size: 11.5px; color: var(--error-txt); }
.has-error input { border-color: var(--error-txt) !important; }

.pass-wrap { position: relative; }
.pass-wrap input { padding-right: 40px; }
.eye-btn {
  position: absolute; right: 12px; top: 50%;
  transform: translateY(-50%);
  background: none; border: none;
  cursor: pointer; font-size: 14px; color: var(--muted); padding: 0;
}

.form-actions {
  display: flex;
  gap: 12px;
  margin-top: 22px;
  justify-content: flex-end;
}
.btn {
  padding: 11px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-family: 'DM Sans', sans-serif;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  border: none;
}
.btn-primary {
  background: linear-gradient(135deg, var(--orange), var(--orange-dark));
  color: #fff;
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(249,115,22,0.3); }
.btn-secondary {
  background: transparent;
  border: 1.5px solid var(--border);
  color: var(--muted);
}
.btn-secondary:hover { border-color: var(--orange); color: var(--orange); }

/* ── Babysitter table ── */
.bs-table { width: 100%; border-collapse: collapse; }
.bs-table th {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
  font-weight: 500;
  padding: 8px 12px;
  text-align: left;
  border-bottom: 1px solid var(--border);
}
.bs-table td {
  padding: 14px 12px;
  font-size: 13.5px;
  color: var(--text);
  border-bottom: 1px solid #faf4ef;
  vertical-align: middle;
}
.bs-table tr:last-child td { border-bottom: none; }
.bs-table tr:hover td { background: var(--soft); }

.bs-avatar {
  width: 32px; height: 32px;
  background: var(--orange);
  border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 500; color: #fff;
  margin-right: 10px;
  vertical-align: middle;
}
.bs-name { vertical-align: middle; font-weight: 500; }

.badge-sitter {
  background: #fff3e0;
  color: #c2410c;
  font-size: 11px;
  padding: 3px 10px;
  border-radius: 20px;
  font-weight: 500;
}

.btn-delete {
  background: none;
  border: 1px solid #fecaca;
  color: var(--error-txt);
  padding: 5px 12px;
  border-radius: 6px;
  font-size: 12px;
  cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  transition: background 0.15s;
}
.btn-delete:hover { background: var(--error-bg); }

.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--muted);
  font-size: 14px;
}
.empty-state .icon { font-size: 36px; margin-bottom: 10px; }

@media (max-width: 768px) {
  .sidebar { display: none; }
  .main { margin-left: 0; }
  .form-grid { grid-template-columns: 1fr; }
  .content { padding: 20px 16px; }
}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <span style="font-size:26px">👶</span>
    <div>
      <div class="brand-name">BabyWatch</div>
      <div class="sidebar-badge">PARENT DASHBOARD</div>
    </div>
  </div>

  <div class="sidebar-user">
    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
    <div>
      <div class="sidebar-user-name"><?= htmlspecialchars($userName) ?></div>
      <div class="sidebar-user-role">Administrator</div>
    </div>
  </div>

  <div class="sidebar-section">Main</div>
  <nav class="sidebar-nav">
    <a href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a href="live_monitor.php"><span class="icon">🎙️</span> Live Monitor</a>
    <a href="activity_logs.php"><span class="icon">☰</span> Activity Logs</a>
  </nav>

  <div class="sidebar-section">Management</div>
  <nav class="sidebar-nav">
    <a href="analytics.php"><span class="icon">📊</span> Analytics</a>
    <a href="baby_profile.php"><span class="icon">👶</span> Baby Profile</a>
    <a href="add_babysitter.php" class="active"><span class="icon">🤱</span> Babysitters</a>
    <a href="notifications.php"><span class="icon">🔔</span> Notifications</a>
  </nav>

  <div class="sidebar-bottom">
    <a href="../logout.php">↩ Sign Out</a>
  </div>
</aside>

<!-- Main -->
<div class="main">
  <div class="topbar">
    <h1>Manage Babysitters</h1>
    <span class="topbar-meta"><?= date('D, M j') ?></span>
  </div>

  <div class="content">

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (isset($errors['db'])): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($errors['db']) ?></div>
    <?php endif; ?>

    <!-- Add Babysitter Form -->
    <div class="card">
      <div class="card-title">🤱 Add New Babysitter</div>

      <form method="POST" action="">
        <div class="form-grid">

          <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name"
                   placeholder="e.g. Maria Cruz"
                   value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
            <?php if (isset($errors['name'])): ?>
              <span class="err-msg"><?= $errors['name'] ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email"
                   placeholder="sitter@mail.com"
                   value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
            <?php if (isset($errors['email'])): ?>
              <span class="err-msg"><?= $errors['email'] ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone"
                   placeholder="+63 912 345 6789"
                   value="<?= htmlspecialchars($formData['phone'] ?? '') ?>">
            <?php if (isset($errors['phone'])): ?>
              <span class="err-msg"><?= $errors['phone'] ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group <?= isset($errors['password']) ? 'has-error' : '' ?>">
            <label for="password">Password</label>
            <div class="pass-wrap">
              <input type="password" id="password" name="password" placeholder="Min. 8 characters" required>
              <button type="button" class="eye-btn" onclick="togglePw('password',this)">👁</button>
            </div>
            <?php if (isset($errors['password'])): ?>
              <span class="err-msg"><?= $errors['password'] ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group <?= isset($errors['confirm_password']) ? 'has-error' : '' ?>">
            <label for="confirm_password">Confirm Password</label>
            <div class="pass-wrap">
              <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
              <button type="button" class="eye-btn" onclick="togglePw('confirm_password',this)">👁</button>
            </div>
            <?php if (isset($errors['confirm_password'])): ?>
              <span class="err-msg"><?= $errors['confirm_password'] ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group full">
            <label for="notes">Notes <span style="color:var(--muted);font-style:italic;text-transform:none;">(optional)</span></label>
            <textarea id="notes" name="notes" placeholder="e.g. Available weekends, speaks English & Filipino..."><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
          </div>

        </div>

        <div class="form-actions">
          <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Add Babysitter →</button>
        </div>
      </form>
    </div>

    <!-- Existing Babysitters List -->
    <div class="card">
      <div class="card-title">
        👥 Current Babysitters
        <span style="margin-left:auto;font-size:12px;color:var(--muted);font-family:'DM Sans',sans-serif;font-weight:400;">
          <?= count($babysitters) ?> account<?= count($babysitters) !== 1 ? 's' : '' ?>
        </span>
      </div>

      <?php if (empty($babysitters)): ?>
        <div class="empty-state">
          <div class="icon">🤱</div>
          No babysitter accounts yet. Add one above!
        </div>
      <?php else: ?>
        <table class="bs-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Added</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($babysitters as $bs): ?>
              <?php
                $bsInitials = strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(explode(' ', $bs['name']), 0, 2))));
              ?>
              <tr>
                <td>
                  <span class="bs-avatar"><?= htmlspecialchars($bsInitials) ?></span>
                  <span class="bs-name"><?= htmlspecialchars($bs['name']) ?></span>
                </td>
                <td style="color:var(--muted)"><?= htmlspecialchars($bs['email']) ?></td>
                <td><span class="badge-sitter">Babysitter</span></td>
                <td style="color:var(--muted)"><?= date('M j, Y', strtotime($bs['created_at'])) ?></td>
                <td>
                  <form method="POST" action="delete_babysitter.php" onsubmit="return confirm('Remove this babysitter account?')">
                    <input type="hidden" name="id" value="<?= (int)$bs['id'] ?>">
                    <button type="submit" class="btn-delete">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script>
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  inp.type  = inp.type === 'password' ? 'text' : 'password';
  btn.style.opacity = inp.type === 'text' ? '1' : '0.5';
}
</script>
</body>
</html>
