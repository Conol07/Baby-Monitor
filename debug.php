<?php
// debug.php — Diagnose login issues
// Visit: http://localhost/baby-monitor/debug.php
// DELETE after fixing!

require_once 'includes/config.php';

$db = getDB();

echo "<style>body{font-family:monospace;padding:20px;background:#f9f9f9} .ok{color:green} .err{color:red} .warn{color:orange} table{border-collapse:collapse;width:100%} td,th{border:1px solid #ccc;padding:8px 12px;text-align:left} h2{margin-top:24px}</style>";
echo "<h1>🔍 BabyWatch Debug Tool</h1>";

// 1. DB connection
echo "<h2>1. Database Connection</h2>";
try {
    $db->query("SELECT 1");
    echo "<p class='ok'>✅ Connected to MySQL successfully</p>";
} catch (Exception $e) {
    echo "<p class='err'>❌ DB Error: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Tables
echo "<h2>2. Tables</h2>";
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$required = ['users','baby_profiles','activity_logs','notification_preferences','babysitter_assignments'];
foreach ($required as $t) {
    if (in_array($t, $tables))
        echo "<p class='ok'>✅ Table <b>$t</b> exists</p>";
    else
        echo "<p class='err'>❌ Table <b>$t</b> MISSING — re-import database.sql</p>";
}

// 3. Users
echo "<h2>3. Users in Database</h2>";
$users = $db->query("SELECT id, name, email, role, LEFT(password,20) AS hash_preview, LENGTH(password) AS hash_len FROM users")->fetchAll();
if (empty($users)) {
    echo "<p class='err'>❌ No users found! Run setup.php first.</p>";
} else {
    echo "<table><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Hash Preview</th><th>Hash Len</th><th>Valid Hash?</th></tr>";
    foreach ($users as $u) {
        $validHash = (strlen($u['hash_preview']) > 0 && str_starts_with($u['hash_preview'], '$2'));
        echo "<tr>
            <td>{$u['id']}</td>
            <td>{$u['name']}</td>
            <td>{$u['email']}</td>
            <td>{$u['role']}</td>
            <td><code>{$u['hash_preview']}...</code></td>
            <td>{$u['hash_len']}</td>
            <td>" . ($validHash ? "<span class='ok'>✅ bcrypt</span>" : "<span class='err'>❌ INVALID</span>") . "</td>
        </tr>";
    }
    echo "</table>";
}

// 4. Password test
echo "<h2>4. Password Verification Test</h2>";
$testUser = $db->prepare("SELECT * FROM users WHERE email = 'parent@demo.com'")->execute([]) ? null : null;
$stmt = $db->prepare("SELECT * FROM users WHERE email = 'parent@demo.com'");
$stmt->execute();
$testUser = $stmt->fetch();

if (!$testUser) {
    echo "<p class='err'>❌ User parent@demo.com not found. Run setup.php!</p>";
} else {
    $pass = 'demo1234';
    $result = password_verify($pass, $testUser['password']);
    if ($result) {
        echo "<p class='ok'>✅ password_verify('demo1234', hash) = TRUE — Login should work!</p>";
    } else {
        echo "<p class='err'>❌ password_verify('demo1234', hash) = FALSE — Hash is wrong. Re-run setup.php</p>";
        echo "<p class='warn'>Stored hash: <code>" . htmlspecialchars($testUser['password']) . "</code></p>";

        // Auto-fix option
        echo "<h2>5. Auto-Fix</h2>";
        echo "<p>Click the button below to reset the passwords directly:</p>";
        if (isset($_POST['autofix'])) {
            $newHash = password_hash('demo1234', PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password = ? WHERE email IN ('parent@demo.com','sitter@demo.com')")->execute([$newHash]);
            echo "<p class='ok'>✅ Passwords reset! <a href='index.php'>Go to Login →</a></p>";
        } else {
            echo "<form method='POST'><button name='autofix' value='1' style='padding:12px 24px;background:#f97316;color:white;border:none;border-radius:8px;cursor:pointer;font-size:1rem'>🔧 Auto-Fix Passwords Now</button></form>";
        }
    }
}

// 5. Baby profile
echo "<h2>5. Baby Profiles</h2>";
$babies = $db->query("SELECT * FROM baby_profiles")->fetchAll();
if (empty($babies)) {
    echo "<p class='err'>❌ No baby profiles. Run setup.php!</p>";
} else {
    foreach ($babies as $b) echo "<p class='ok'>✅ Baby: <b>{$b['name']}</b> (ID {$b['id']}, parent_id {$b['parent_id']})</p>";
}

echo "<hr><p style='color:#999;font-size:0.85rem'>⚠️ Delete debug.php after fixing!</p>";
