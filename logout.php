<?php
require_once 'includes/config.php';

$db = getDB();

if (isset($_SESSION['user_id'], $_SESSION['session_token'])) {
    $stmt = $db->prepare("
        UPDATE session_logs
        SET logged_out_at = NOW(), status = 'logged_out'
        WHERE user_id = ? 
        AND session_token = ? 
        AND status = 'active'
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['session_token']
    ]);
}

session_unset();
session_destroy();

header("Location: index.php?msg=logged_out");
exit;