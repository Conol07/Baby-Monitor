<?php
// api/resolve_event.php
require_once '../includes/config.php';
requireLogin();

$input = json_decode(file_get_contents('php://input'), true);
$logId = intval($input['log_id'] ?? 0);
if (!$logId) jsonResponse(['success' => false, 'error' => 'Invalid ID'], 400);

$db = getDB();
$stmt = $db->prepare("UPDATE activity_logs SET resolved = 1, resolved_at = NOW(), resolved_by = ? WHERE id = ?");
$stmt->execute([$_SESSION['user_id'], $logId]);

jsonResponse(['success' => true]);
