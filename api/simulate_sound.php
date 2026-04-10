<?php
// api/simulate_sound.php
require_once '../includes/config.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$type  = $input['type'] ?? 'unknown';
$conf  = round(floatval($input['confidence'] ?? 80), 2);
$dur   = intval($input['duration'] ?? 30);
$babyId= intval($input['baby_id'] ?? 0);

$allowed = ['hungry','sleepy','discomfort','happy','burp','unknown'];
if (!in_array($type, $allowed)) $type = 'unknown';
if (!$babyId) jsonResponse(['success' => false, 'error' => 'No baby ID'], 400);

$db = getDB();
$stmt = $db->prepare("INSERT INTO activity_logs (baby_id, detected_by, sound_type, confidence_score, duration_seconds, alert_sent) VALUES (?,?,?,?,?,1)");
$stmt->execute([$babyId, $_SESSION['user_id'], $type, $conf, $dur]);
$id = $db->lastInsertId();

$log = $db->prepare("SELECT * FROM activity_logs WHERE id = ?")->execute([$id]);
$stmt2 = $db->prepare("SELECT * FROM activity_logs WHERE id = ?");
$stmt2->execute([$id]);
$log = $stmt2->fetch();

jsonResponse(['success' => true, 'log' => $log]);
