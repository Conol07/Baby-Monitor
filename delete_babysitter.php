<?php
require_once '../includes/config.php';

// Must be logged in as admin/parent
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php?msg=login_required');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            $db = getDB();

            // Safety check: only allow deleting babysitter role
            $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();

            if ($user && $user['role'] === 'babysitter') {
                // Remove notification prefs first (FK constraint)
                $db->prepare("DELETE FROM notification_preferences WHERE user_id = ?")->execute([$id]);
                // Remove the user
                $db->prepare("DELETE FROM users WHERE id = ? AND role = 'babysitter'")->execute([$id]);
            }
        } catch (PDOException $e) {
            // Optionally log error
        }
    }
}

header('Location: add_babysitter.php');
exit;
