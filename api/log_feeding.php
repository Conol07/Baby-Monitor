<?php
// api/log_feeding.php
require_once '../includes/config.php';
requireLogin();

$babyId = intval($_POST['baby_id'] ?? 0);
$type   = sanitize($_POST['feeding_type'] ?? 'formula');
$amount = !empty($_POST['amount_ml']) ? intval($_POST['amount_ml']) : null;
$notes  = sanitize($_POST['notes'] ?? '');

$allowed = ['breast','formula','solids','water'];
if (!in_array($type, $allowed)) $type = 'formula';

if (!$babyId) { header('Location: ' . APP_URL . '/admin/dashboard.php'); exit; }

$db = getDB();
$stmt = $db->prepare("INSERT INTO feeding_logs (baby_id, logged_by, feeding_type, amount_ml, notes) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$babyId, $_SESSION['user_id'], $type, $amount, $notes]);

$db->prepare("UPDATE baby_profiles SET last_fed = NOW() WHERE id = ?")->execute([$babyId]);

header('Location: ' . APP_URL . '/admin/dashboard.php?fed=1'); exit;
