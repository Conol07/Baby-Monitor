<?php
// api/update_prefs.php
require_once '../includes/config.php';
requireLogin();

$db = getDB();
$fields = ['sound_enabled','popup_enabled','hungry_alert','sleepy_alert','discomfort_alert','happy_alert','email_alerts'];
$data   = [];
foreach ($fields as $f) $data[$f] = isset($_POST[$f]) ? 1 : 0;
$data['alert_volume'] = min(100, max(0, intval($_POST['alert_volume'] ?? 80)));

$stmt = $db->prepare("
    INSERT INTO notification_preferences (user_id, sound_enabled, popup_enabled, hungry_alert, sleepy_alert, discomfort_alert, happy_alert, email_alerts, alert_volume)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      sound_enabled = VALUES(sound_enabled),
      popup_enabled = VALUES(popup_enabled),
      hungry_alert  = VALUES(hungry_alert),
      sleepy_alert  = VALUES(sleepy_alert),
      discomfort_alert = VALUES(discomfort_alert),
      happy_alert   = VALUES(happy_alert),
      email_alerts  = VALUES(email_alerts),
      alert_volume  = VALUES(alert_volume)
");
$stmt->execute([
    $_SESSION['user_id'],
    $data['sound_enabled'], $data['popup_enabled'],
    $data['hungry_alert'], $data['sleepy_alert'],
    $data['discomfort_alert'], $data['happy_alert'],
    $data['email_alerts'], $data['alert_volume']
]);

$role = $_SESSION['user_role'];
header('Location: ' . APP_URL . '/' . ($role === 'admin' ? 'admin' : 'babysitter') . '/dashboard.php?saved=1');
exit;
