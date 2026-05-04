<?php
function startSessionLog($db, $userId) {
    $token = session_id();
    $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $existing = $db->prepare("SELECT id FROM session_logs WHERE session_token = ? AND status = 'active'");
    $existing->execute([$token]);
    if ($existing->fetch()) return;

    $stmt = $db->prepare("INSERT INTO session_logs (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $token, $ip, $ua]);
}

function touchSessionLog($db) {
    $token = session_id();
    $db->prepare("UPDATE session_logs SET last_active = NOW() WHERE session_token = ? AND status = 'active'")->execute([$token]);
}

function endSessionLog($db) {
    $token = session_id();
    $db->prepare("UPDATE session_logs SET logged_out_at = NOW(), status = 'ended' WHERE session_token = ? AND status = 'active'")->execute([$token]);
}

function expireOldSessions($db, $minutesIdle = 120) {
    $db->prepare("UPDATE session_logs SET status = 'expired', logged_out_at = NOW() WHERE status = 'active' AND last_active < NOW() - INTERVAL ? MINUTE")->execute([$minutesIdle]);
}

function parseDevice($ua) {
    $isMobile = preg_match('/Mobile|Android|iPhone|iPad/i', $ua);
    if (preg_match('/Chrome/i', $ua))  $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/Firefox/i', $ua)) $browser = 'Firefox';
    else $browser = 'Browser';

    if (preg_match('/Windows/i', $ua))    $os = 'Win';
    elseif (preg_match('/Mac/i', $ua))    $os = 'Mac';
    elseif (preg_match('/iPhone/i', $ua)) $os = 'iOS';
    elseif (preg_match('/Android/i', $ua)) $os = 'Android';
    elseif (preg_match('/Linux/i', $ua))  $os = 'Linux';
    else $os = 'Unknown';

    return ['type' => $isMobile ? 'M' : 'D', 'label' => "$browser / $os"];
}