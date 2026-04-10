<?php
// api/update_baby.php
require_once '../includes/config.php';
requireAdmin();

$babyId = intval($_POST['baby_id'] ?? 0);
$name   = sanitize($_POST['name'] ?? '');
$birth  = $_POST['birth_date'] ?? '';
$weight = floatval($_POST['weight_kg'] ?? 0);
$interval = intval($_POST['feeding_interval_hours'] ?? 3);
$notes  = sanitize($_POST['notes'] ?? '');

if (!$babyId || !$name || !$birth) {
    header('Location: ' . APP_URL . '/admin/dashboard.php?error=missing'); exit;
}

$db = getDB();
$stmt = $db->prepare("UPDATE baby_profiles SET name=?, birth_date=?, weight_kg=?, feeding_interval_hours=?, notes=? WHERE id=? AND parent_id=?");
$stmt->execute([$name, $birth, $weight, $interval, $notes, $babyId, $_SESSION['user_id']]);

header('Location: ' . APP_URL . '/admin/dashboard.php?saved=1'); exit;
