<?php
// api/poll_events.php
require_once '../includes/config.php';
requireLogin();

$since  = intval($_GET['since'] ?? 0);
$babyId = intval($_GET['baby_id'] ?? 0);

if (!$babyId) jsonResponse(['events' => []]);

$db = getDB();
$stmt = $db->prepare("SELECT * FROM activity_logs WHERE baby_id = ? AND id > ? ORDER BY id ASC LIMIT 10");
$stmt->execute([$babyId, $since]);
$events = $stmt->fetchAll();

jsonResponse(['events' => $events, 'count' => count($events)]);
