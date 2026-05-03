<?php
// setup.php — Run this ONCE to initialize demo users with correct password hashes
// Visit: http://localhost/baby-monitor/setup.php
// DELETE this file after running it!

require_once 'includes/config.php';

$db = getDB();

// Generate correct hashes on the server where PHP is running
$parentHash = password_hash('demo1234', PASSWORD_BCRYPT);
$sitterHash = password_hash('demo1234', PASSWORD_BCRYPT);

// Remove old demo users if exist
$db->exec("DELETE FROM users WHERE email IN ('parent@demo.com', 'sitter@demo.com')");

// Insert with correct hashes
$stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->execute(['Sarah Johnson', 'parent@demo.com', $parentHash, 'admin']);
$parentId = $db->lastInsertId();

$stmt->execute(['Maria Cruz', 'sitter@demo.com', $sitterHash, 'babysitter']);
$sitterId = $db->lastInsertId();

// Ensure baby profile exists
$baby = $db->query("SELECT id FROM baby_profiles LIMIT 1")->fetch();
if (!$baby) {
    $db->prepare("INSERT INTO baby_profiles (parent_id, name, birth_date, gender, weight_kg, feeding_interval_hours) VALUES (?, 'Baby Lily', '2024-09-15', 'female', 7.50, 3)")
       ->execute([$parentId]);
    $babyId = $db->lastInsertId();
} else {
    $babyId = $baby['id'];
    // Update parent_id to match new user
    $db->prepare("UPDATE baby_profiles SET parent_id = ? WHERE id = ?")->execute([$parentId, $babyId]);
}

// Ensure babysitter assignment
$db->prepare("DELETE FROM babysitter_assignments WHERE babysitter_id = ?")->execute([$sitterId]);
$db->prepare("INSERT INTO babysitter_assignments (babysitter_id, baby_id, assigned_by) VALUES (?, ?, ?)")
   ->execute([$sitterId, $babyId, $parentId]);

// Notification prefs
$db->prepare("INSERT IGNORE INTO notification_preferences (user_id) VALUES (?)")->execute([$parentId]);
$db->prepare("INSERT IGNORE INTO notification_preferences (user_id) VALUES (?)")->execute([$sitterId]);

// Demo activity logs
$db->prepare("DELETE FROM activity_logs WHERE baby_id = ?")->execute([$babyId]);
$events = [
    ['hungry', 92.5, 45, 1, 1],
    ['sleepy', 88.0, 30, 1, 1],
    ['discomfort', 75.3, 60, 1, 0],
    ['hungry', 95.1, 50, 1, 1],
    ['burp', 70.0, 15, 0, 1],
    ['sleepy', 82.5, 35, 1, 0],
];
$ins = $db->prepare("INSERT INTO activity_logs (baby_id, sound_type, confidence_score, duration_seconds, alert_sent, resolved) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($events as $e) {
    $ins->execute(array_merge([$babyId], $e));
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>BabyWatch Setup</title>
<style>
  body { font-family: sans-serif; max-width: 500px; margin: 80px auto; text-align: center; }
  .box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; padding: 32px; }
  h2 { color: #16a34a; margin-bottom: 12px; }
  p { color: #555; font-size: 0.95rem; margin: 8px 0; }
  a { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #f97316; color: white; border-radius: 10px; text-decoration: none; font-weight: 500; }
  .cred { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin: 16px 0; font-size: 0.88rem; text-align: left; line-height: 1.8; }
  .warn { color: #dc2626; font-size: 0.82rem; margin-top: 16px; }
</style>
</head>
<body>
<div class="box">
  <h2>✅ Setup Complete!</h2>
  <p>Demo users created with correct password hashes.</p>
  <div class="cred">
    <strong>Parent (Admin):</strong><br>
    Email: <code>parent@demo.com</code><br>
    Password: <code>demo1234</code><br><br>
    <strong>Babysitter:</strong><br>
    Email: <code>sitter@demo.com</code><br>
    Password: <code>demo1234</code>
  </div>
  <a href="index.php">Go to Login →</a>
  <p class="warn">⚠️ Please delete <strong>setup.php</strong> after use.</p>
</div>
</body>
</html>
