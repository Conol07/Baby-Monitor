<?php
// ============================================================
// config.php — Database & App Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'baby_monitor');

define('APP_NAME', 'BabyWatch');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/Baby-Monitor');
define('SESSION_LIFETIME', 3600 * 8);

date_default_timezone_set('Asia/Manila');

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ============================================================
// Database Connection
// ============================================================
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    return $pdo;
}

// ============================================================
// Auth Helpers
// ============================================================
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php?msg=login_required');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();

    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: ' . APP_URL . '/babysitter/dashboard.php');
        exit;
    }
}

function currentUser(): array {
    if (!isLoggedIn()) return [];

    $db = getDB();

    $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    return $stmt->fetch() ?: [];
}

function logout(): void {
    $_SESSION = [];
    session_destroy();

    header('Location: ' . APP_URL . '/index.php?msg=logged_out');
    exit;
}

// ============================================================
// Utility Helpers
// ============================================================
function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->days > 0) return $diff->days . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';

    return 'Just now';
}

function soundTypeLabel(string $type): string {
    return match($type) {
        'hungry'     => '🍼 Hungry',
        'sleepy'     => '😴 Sleepy',
        'discomfort' => '😣 Discomfort',
        'happy'      => '😊 Happy',
        'burp'       => '💨 Burp',
        default      => '❓ Unknown',
    };
}

function soundTypeColor(string $type): string {
    return match($type) {
        'hungry'     => '#f97316',
        'sleepy'     => '#8b5cf6',
        'discomfort' => '#ef4444',
        'happy'      => '#22c55e',
        'burp'       => '#06b6d4',
        default      => '#6b7280',
    };
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
};