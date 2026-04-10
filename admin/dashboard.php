<?php
require_once '../includes/config.php';
requireAdmin();

$db   = getDB();
$user = currentUser();

// Fetch baby profile
$baby = $db->prepare("SELECT bp.*, u.name AS parent_name FROM baby_profiles bp JOIN users u ON bp.parent_id = u.id WHERE bp.parent_id = ? ORDER BY bp.id LIMIT 1");
$baby->execute([$user['id']]);
$baby = $baby->fetch();

// Stats
$stats = $db->prepare("
    SELECT
        COUNT(*) AS total_events,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_events,
        SUM(CASE WHEN resolved = 0 THEN 1 ELSE 0 END) AS unresolved,
        SUM(CASE WHEN sound_type = 'hungry' THEN 1 ELSE 0 END) AS hungry_count,
        SUM(CASE WHEN sound_type = 'sleepy' THEN 1 ELSE 0 END) AS sleepy_count,
        SUM(CASE WHEN sound_type = 'discomfort' THEN 1 ELSE 0 END) AS discomfort_count
    FROM activity_logs WHERE baby_id = ?
");
$stats->execute([$baby['id'] ?? 0]);
$stats = $stats->fetch();

// Recent logs
$logs = $db->prepare("SELECT * FROM activity_logs WHERE baby_id = ? ORDER BY created_at DESC LIMIT 20");
$logs->execute([$baby['id'] ?? 0]);
$logs = $logs->fetchAll();

// Notification prefs
$prefs = $db->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
$prefs->execute([$user['id']]);
$prefs = $prefs->fetch();

// Last feeding
$lastFeed = $db->prepare("SELECT * FROM feeding_logs WHERE baby_id = ? ORDER BY fed_at DESC LIMIT 1");
$lastFeed->execute([$baby['id'] ?? 0]);
$lastFeed = $lastFeed->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BabyWatch — Parent Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@300;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:      #fef6ec;
  --surface: #ffffff;
  --peach:   #f97316;
  --peach2:  #e85d04;
  --purple:  #8b5cf6;
  --green:   #22c55e;
  --red:     #ef4444;
  --cyan:    #06b6d4;
  --text:    #1e0f05;
  --muted:   #9a7b6a;
  --border:  #f0e0d6;
  --sidebar: #1e0f05;
  --nav-w:   260px;
}

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--bg);
  color: var(--text);
  display: flex;
  min-height: 100vh;
}

/* ── Sidebar ── */
.sidebar {
  width: var(--nav-w);
  background: var(--sidebar);
  position: fixed; top: 0; left: 0; bottom: 0;
  display: flex; flex-direction: column;
  z-index: 100;
  transition: transform 0.3s ease;
}
.sidebar-logo {
  padding: 28px 24px 20px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}
.sidebar-logo h1 {
  font-family: 'Fraunces', serif;
  font-size: 1.5rem;
  color: white;
  font-weight: 600;
  display: flex; align-items: center; gap: 10px;
}
.sidebar-logo span.role-badge {
  font-family: 'DM Sans', sans-serif;
  font-size: 0.68rem;
  font-weight: 500;
  background: var(--peach);
  color: white;
  padding: 2px 8px;
  border-radius: 20px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  display: inline-block;
  margin-top: 4px;
}
.sidebar-user {
  padding: 16px 24px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
  display: flex; align-items: center; gap: 10px;
}
.avatar-circle {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--peach), var(--peach2));
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; font-weight: 600; color: white;
  flex-shrink: 0;
}
.sidebar-user .info p { font-size: 0.85rem; color: white; font-weight: 500; }
.sidebar-user .info span { font-size: 0.73rem; color: rgba(255,255,255,0.4); }

nav { flex: 1; padding: 16px 0; overflow-y: auto; }
.nav-section {
  font-size: 0.68rem;
  color: rgba(255,255,255,0.3);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  padding: 12px 24px 6px;
}
.nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 24px;
  color: rgba(255,255,255,0.55);
  text-decoration: none;
  font-size: 0.88rem;
  font-weight: 400;
  cursor: pointer;
  transition: all 0.15s;
  border-left: 3px solid transparent;
}
.nav-item:hover { color: white; background: rgba(255,255,255,0.05); }
.nav-item.active {
  color: white;
  background: rgba(249,115,22,0.15);
  border-left-color: var(--peach);
  font-weight: 500;
}
.nav-item i { width: 18px; text-align: center; }

.sidebar-footer {
  padding: 16px 24px;
  border-top: 1px solid rgba(255,255,255,0.07);
}
.btn-logout {
  display: flex; align-items: center; gap: 10px;
  color: rgba(255,255,255,0.4);
  text-decoration: none;
  font-size: 0.85rem;
  transition: color 0.15s;
}
.btn-logout:hover { color: var(--red); }

/* ── Main ── */
.main {
  margin-left: var(--nav-w);
  flex: 1;
  min-height: 100vh;
  display: flex; flex-direction: column;
}
.topbar {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 32px;
  height: 64px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 90;
}
.topbar h2 {
  font-family: 'Fraunces', serif;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text);
}
.topbar-right { display: flex; align-items: center; gap: 14px; }
.hamburger {
  display: none;
  background: none; border: none; cursor: pointer;
  color: var(--text); font-size: 1.2rem;
}

.status-pill {
  display: flex; align-items: center; gap: 7px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
  color: #16a34a;
}
.status-dot {
  width: 8px; height: 8px;
  background: #22c55e;
  border-radius: 50%;
  animation: blink 1.5s ease-in-out infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

.content { padding: 28px 32px; flex: 1; }

/* ── Baby Header ── */
.baby-header {
  background: linear-gradient(135deg, var(--peach) 0%, var(--peach2) 100%);
  border-radius: 20px;
  padding: 28px 32px;
  color: white;
  display: flex;
  align-items: center;
  gap: 24px;
  margin-bottom: 28px;
  position: relative;
  overflow: hidden;
}
.baby-header::after {
  content: '👶';
  position: absolute; right: 24px; bottom: -10px;
  font-size: 100px;
  opacity: 0.12;
  line-height: 1;
}
.baby-avatar {
  width: 72px; height: 72px;
  background: rgba(255,255,255,0.2);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 2rem;
  flex-shrink: 0;
}
.baby-info h3 { font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 600; }
.baby-info p { opacity: 0.85; font-size: 0.88rem; margin-top: 4px; }
.baby-meta { display: flex; gap: 16px; margin-top: 12px; flex-wrap: wrap; }
.baby-meta-item {
  background: rgba(255,255,255,0.15);
  padding: 5px 14px;
  border-radius: 20px;
  font-size: 0.8rem;
}

/* ── Stats Grid ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 28px;
}
.stat-card {
  background: var(--surface);
  border-radius: 16px;
  padding: 20px;
  border: 1px solid var(--border);
  transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(249,115,22,0.1); }
.stat-icon {
  width: 42px; height: 42px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  margin-bottom: 14px;
}
.stat-card h4 { font-size: 1.8rem; font-weight: 600; }
.stat-card p  { font-size: 0.8rem; color: var(--muted); margin-top: 3px; }

/* ── Grid 2col ── */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
@media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

/* ── Cards ── */
.card {
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--border);
  overflow: hidden;
}
.card-header {
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.card-header h3 { font-family: 'Fraunces', serif; font-size: 1.05rem; font-weight: 600; }
.card-body { padding: 20px 24px; }

/* ── Monitor Panel ── */
.monitor-panel { margin-bottom: 28px; }
.monitor-live {
  display: flex; align-items: center; gap: 18px;
  padding: 20px 24px;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
}
.waveform {
  flex: 1;
  height: 60px;
  display: flex; align-items: center; gap: 3px;
}
.wave-bar {
  flex: 1;
  background: var(--peach);
  border-radius: 2px;
  transition: height 0.15s ease;
  min-height: 4px;
}
.current-status {
  text-align: right;
}
.current-status .type-label {
  font-size: 1rem;
  font-weight: 600;
}
.current-status .conf {
  font-size: 0.78rem;
  color: var(--muted);
  margin-top: 2px;
}

.sim-buttons { padding: 16px 24px; display: flex; gap: 10px; flex-wrap: wrap; }
.sim-btn {
  padding: 10px 18px;
  border: none;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 500;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  transition: transform 0.12s, opacity 0.12s;
}
.sim-btn:hover { opacity: 0.85; transform: translateY(-1px); }
.sim-btn:active { transform: scale(0.97); }
.sim-btn.hungry    { background: #fff7ed; color: #c2410c; border: 1.5px solid #fed7aa; }
.sim-btn.sleepy    { background: #f5f3ff; color: #6d28d9; border: 1.5px solid #ddd6fe; }
.sim-btn.discomfort{ background: #fff1f2; color: #be123c; border: 1.5px solid #fecdd3; }
.sim-btn.happy     { background: #f0fdf4; color: #15803d; border: 1.5px solid #bbf7d0; }
.sim-btn.random    { background: #f0f9ff; color: #0369a1; border: 1.5px solid #bae6fd; }

/* ── Logs Table ── */
.log-table { width: 100%; border-collapse: collapse; }
.log-table th {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted);
  padding: 10px 0;
  text-align: left;
  border-bottom: 1px solid var(--border);
  font-weight: 500;
}
.log-table td {
  padding: 12px 0;
  font-size: 0.87rem;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.log-table tr:last-child td { border-bottom: none; }
.type-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 500;
}
.conf-bar { width: 60px; height: 5px; background: var(--border); border-radius: 3px; overflow: hidden; margin-top: 4px; }
.conf-fill { height: 100%; border-radius: 3px; background: var(--peach); }
.badge-resolved { font-size: 0.72rem; padding: 3px 10px; background: #f0fdf4; color: #16a34a; border-radius: 10px; }
.badge-pending  { font-size: 0.72rem; padding: 3px 10px; background: #fff7ed; color: #c2410c; border-radius: 10px; }

/* ── Chart ── */
.chart-wrap { position: relative; height: 180px; }

/* ── Analytics ── */
.breakdown-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.breakdown-item { text-align: center; padding: 14px; background: var(--bg); border-radius: 12px; }
.breakdown-item .emoji { font-size: 1.6rem; }
.breakdown-item .count { font-size: 1.4rem; font-weight: 600; margin: 4px 0 2px; }
.breakdown-item .label { font-size: 0.75rem; color: var(--muted); }

/* ── Notification toast ── */
#toast-container {
  position: fixed;
  top: 20px; right: 20px;
  z-index: 9999;
  display: flex; flex-direction: column; gap: 10px;
  max-width: 340px;
}
.toast {
  background: var(--surface);
  border-radius: 14px;
  padding: 16px 18px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
  display: flex; align-items: flex-start; gap: 12px;
  border-left: 4px solid var(--peach);
  animation: slideIn 0.3s ease;
  cursor: pointer;
}
@keyframes slideIn { from { opacity:0; transform: translateX(20px); } to { opacity:1; transform: translateX(0); } }
.toast-icon { font-size: 1.4rem; line-height: 1; }
.toast-body h4 { font-size: 0.9rem; font-weight: 600; }
.toast-body p  { font-size: 0.8rem; color: var(--muted); margin-top: 2px; }
.toast-close { margin-left: auto; background: none; border: none; cursor: pointer; color: var(--muted); font-size: 1rem; }

/* ── Profile Section ── */
.profile-row { display: flex; gap: 14px; align-items: flex-start; flex-wrap: wrap; }
.profile-field { flex: 1; min-width: 140px; }
.profile-field label { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }
.profile-field p { font-size: 0.9rem; font-weight: 500; margin-top: 3px; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main { margin-left: 0; }
  .hamburger { display: block; }
  .content { padding: 20px 16px; }
  .topbar { padding: 0 16px; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .breakdown-grid { grid-template-columns: 1fr 1fr; }
}

/* ── Hidden sections ── */
.section { display: none; }
.section.active { display: block; }

/* ── Tabs ── */
.tabs { display: flex; gap: 6px; padding: 20px 24px 0; border-bottom: 1px solid var(--border); }
.tab-btn {
  padding: 8px 16px;
  border: none; background: none;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem;
  color: var(--muted);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: all 0.15s;
}
.tab-btn.active { color: var(--peach); border-bottom-color: var(--peach); font-weight: 500; }

/* ── Prefs Form ── */
.pref-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 0;
  border-bottom: 1px solid var(--border);
}
.pref-row:last-child { border-bottom: none; }
.pref-row label { font-size: 0.9rem; }
.pref-row span { font-size: 0.78rem; color: var(--muted); display: block; margin-top: 2px; }
.toggle {
  position: relative;
  width: 44px; height: 24px;
  display: inline-block;
}
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
  position: absolute; inset: 0;
  background: var(--border);
  border-radius: 24px;
  cursor: pointer;
  transition: background 0.2s;
}
.toggle-slider::before {
  content: '';
  position: absolute;
  width: 18px; height: 18px;
  background: white;
  border-radius: 50%;
  top: 3px; left: 3px;
  transition: transform 0.2s;
  box-shadow: 0 1px 4px rgba(0,0,0,0.15);
}
.toggle input:checked + .toggle-slider { background: var(--peach); }
.toggle input:checked + .toggle-slider::before { transform: translateX(20px); }

.volume-slider { width: 120px; accent-color: var(--peach); }

.btn-primary {
  padding: 10px 22px;
  background: var(--peach);
  color: white; border: none;
  border-radius: 10px;
  font-family: 'DM Sans', sans-serif;
  font-size: 0.88rem;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.15s;
}
.btn-primary:hover { opacity: 0.85; }

/* ── Alert Indicator ── */
#alert-count {
  background: var(--red);
  color: white;
  border-radius: 50%;
  width: 18px; height: 18px;
  font-size: 0.65rem;
  font-weight: 600;
  display: inline-flex; align-items: center; justify-content: center;
  margin-left: 4px;
}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <h1>👶 BabyWatch</h1>
    <span class="role-badge">Parent Dashboard</span>
  </div>
  <div class="sidebar-user">
    <div class="avatar-circle"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
    <div class="info">
      <p><?= htmlspecialchars($user['name']) ?></p>
      <span>Administrator</span>
    </div>
  </div>
  <nav>
    <div class="nav-section">Main</div>
    <a class="nav-item active" onclick="showSection('dashboard')"><i class="fas fa-home"></i> Dashboard</a>
    <a class="nav-item" onclick="showSection('monitor')"><i class="fas fa-microphone"></i> Live Monitor</a>
    <a class="nav-item" onclick="showSection('logs')"><i class="fas fa-list-ul"></i> Activity Logs <span id="alert-count" style="display:none">0</span></a>
    <div class="nav-section">Management</div>
    <a class="nav-item" onclick="showSection('analytics')"><i class="fas fa-chart-bar"></i> Analytics</a>
    <a class="nav-item" onclick="showSection('profile')"><i class="fas fa-baby"></i> Baby Profile</a>
    <a class="nav-item" onclick="showSection('settings')"><i class="fas fa-bell"></i> Notifications</a>
  </nav>
  <div class="sidebar-footer">
    <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</aside>

<!-- Main -->
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
      <h2 id="page-title">Dashboard</h2>
    </div>
    <div class="topbar-right">
      <div class="status-pill"><span class="status-dot"></span> Monitoring Active</div>
      <div style="font-size:0.8rem;color:var(--muted)"><?= date('D, M j') ?></div>
    </div>
  </div>

  <div class="content">

    <!-- ═══ DASHBOARD SECTION ═══ -->
    <div class="section active" id="sec-dashboard">
      <!-- Baby Header -->
      <?php if ($baby): ?>
      <div class="baby-header">
        <div class="baby-avatar">🌸</div>
        <div class="baby-info">
          <h3><?= htmlspecialchars($baby['name']) ?></h3>
          <p>Born <?= date('F j, Y', strtotime($baby['birth_date'])) ?></p>
          <div class="baby-meta">
            <?php
              $ageMonths = (int)((time() - strtotime($baby['birth_date'])) / (60*60*24*30.44));
              $ageYears  = floor($ageMonths / 12);
              $ageRem    = $ageMonths % 12;
            ?>
            <span class="baby-meta-item">Age: <?= $ageYears > 0 ? "$ageYears yr $ageRem mo" : "$ageMonths months" ?></span>
            <span class="baby-meta-item">Weight: <?= $baby['weight_kg'] ?>kg</span>
            <span class="baby-meta-item">Feed every <?= $baby['feeding_interval_hours'] ?>h</span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon" style="background:#fff7ed"><span style="color:#c2410c">🍼</span></div>
          <h4><?= $stats['hungry_count'] ?? 0 ?></h4>
          <p>Hunger Alerts</p>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#f5f3ff"><span style="color:#7c3aed">😴</span></div>
          <h4><?= $stats['sleepy_count'] ?? 0 ?></h4>
          <p>Sleepy Alerts</p>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#fff1f2"><span style="color:#be123c">😣</span></div>
          <h4><?= $stats['discomfort_count'] ?? 0 ?></h4>
          <p>Discomfort Alerts</p>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#f0fdf4"><span style="color:#15803d">📋</span></div>
          <h4><?= $stats['today_events'] ?? 0 ?></h4>
          <p>Events Today</p>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="card">
        <div class="card-header">
          <h3>Recent Activity</h3>
          <a onclick="showSection('logs')" style="font-size:0.82rem;color:var(--peach);cursor:pointer">View All →</a>
        </div>
        <div class="card-body" style="padding:0">
          <?php if (empty($logs)): ?>
            <div style="padding:32px;text-align:center;color:var(--muted);font-size:0.9rem">No activity yet. Use the simulator to test!</div>
          <?php else: ?>
          <table class="log-table" style="width:100%;padding:0 24px">
            <thead><tr style="padding:0 24px">
              <th style="padding-left:24px">Type</th>
              <th>Confidence</th>
              <th>Time</th>
              <th style="padding-right:24px">Status</th>
            </tr></thead>
            <tbody>
            <?php foreach (array_slice($logs, 0, 6) as $log): ?>
            <tr>
              <td style="padding-left:24px">
                <span class="type-badge" style="background:<?= soundTypeColor($log['sound_type']) ?>20;color:<?= soundTypeColor($log['sound_type']) ?>">
                  <?= soundTypeLabel($log['sound_type']) ?>
                </span>
              </td>
              <td>
                <div style="font-size:0.82rem"><?= $log['confidence_score'] ?>%</div>
                <div class="conf-bar"><div class="conf-fill" style="width:<?= $log['confidence_score'] ?>%"></div></div>
              </td>
              <td style="font-size:0.82rem;color:var(--muted)"><?= timeAgo($log['created_at']) ?></td>
              <td style="padding-right:24px">
                <?php if ($log['resolved']): ?>
                  <span class="badge-resolved">Resolved</span>
                <?php else: ?>
                  <span class="badge-pending">Pending</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ═══ LIVE MONITOR SECTION ═══ -->
    <div class="section" id="sec-monitor">
      <div class="card monitor-panel">
        <div class="card-header">
          <h3>🎙️ Sound Monitor — Simulator</h3>
          <span style="font-size:0.8rem;color:var(--muted)" id="last-detected">Listening...</span>
        </div>
        <div class="monitor-live">
          <div class="waveform" id="waveform"></div>
          <div class="current-status">
            <div class="type-label" id="current-type" style="color:var(--muted)">Idle</div>
            <div class="conf">Confidence: <span id="current-conf">—</span></div>
          </div>
        </div>
        <div class="sim-buttons">
          <button class="sim-btn hungry"     onclick="simulateSound('hungry')">🍼 Hungry</button>
          <button class="sim-btn sleepy"     onclick="simulateSound('sleepy')">😴 Sleepy</button>
          <button class="sim-btn discomfort" onclick="simulateSound('discomfort')">😣 Discomfort</button>
          <button class="sim-btn happy"      onclick="simulateSound('happy')">😊 Happy</button>
          <button class="sim-btn random"     onclick="simulateSound('random')">🎲 Random</button>
          <button class="sim-btn" style="background:#f9fafb;color:#374151;border:1.5px solid #e5e7eb" onclick="startAutoSim()">⚡ Auto-Simulate</button>
          <button class="sim-btn" style="background:#f9fafb;color:#374151;border:1.5px solid #e5e7eb" onclick="stopAutoSim()">⏹ Stop Auto</button>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>📊 Live Log Feed</h3></div>
        <div class="card-body" id="live-feed" style="max-height:400px;overflow-y:auto">
          <p style="color:var(--muted);font-size:0.88rem;text-align:center;padding:24px">Simulate a sound event to see the live feed here.</p>
        </div>
      </div>
    </div>

    <!-- ═══ LOGS SECTION ═══ -->
    <div class="section" id="sec-logs">
      <div class="card">
        <div class="card-header">
          <h3>Activity Logs</h3>
          <div style="display:flex;gap:8px;align-items:center">
            <select id="filter-type" onchange="filterLogs()" style="padding:6px 10px;border:1px solid var(--border);border-radius:8px;font-size:0.82rem;font-family:'DM Sans',sans-serif">
              <option value="">All Types</option>
              <option value="hungry">Hungry</option>
              <option value="sleepy">Sleepy</option>
              <option value="discomfort">Discomfort</option>
              <option value="happy">Happy</option>
            </select>
          </div>
        </div>
        <div class="card-body" style="padding:0">
          <div id="logs-table-wrap">
          <?php if (empty($logs)): ?>
            <div style="padding:32px;text-align:center;color:var(--muted)">No logs yet.</div>
          <?php else: ?>
          <table class="log-table" style="width:100%">
            <thead><tr>
              <th style="padding-left:24px">#</th>
              <th>Type</th>
              <th>Confidence</th>
              <th>Duration</th>
              <th>Detected</th>
              <th style="padding-right:24px">Status</th>
            </tr></thead>
            <tbody id="logs-tbody">
            <?php foreach ($logs as $i => $log): ?>
            <tr data-type="<?= $log['sound_type'] ?>">
              <td style="padding-left:24px;color:var(--muted);font-size:0.8rem"><?= $log['id'] ?></td>
              <td>
                <span class="type-badge" style="background:<?= soundTypeColor($log['sound_type']) ?>20;color:<?= soundTypeColor($log['sound_type']) ?>">
                  <?= soundTypeLabel($log['sound_type']) ?>
                </span>
              </td>
              <td>
                <div style="font-size:0.82rem"><?= $log['confidence_score'] ?>%</div>
                <div class="conf-bar"><div class="conf-fill" style="width:<?= $log['confidence_score'] ?>%"></div></div>
              </td>
              <td style="font-size:0.82rem"><?= $log['duration_seconds'] ?>s</td>
              <td style="font-size:0.8rem;color:var(--muted)">
                <?= date('M j, g:ia', strtotime($log['created_at'])) ?>
              </td>
              <td style="padding-right:24px">
                <?php if ($log['resolved']): ?>
                  <span class="badge-resolved">✓ Resolved</span>
                <?php else: ?>
                  <button onclick="resolveEvent(<?= $log['id'] ?>, this)" style="padding:3px 10px;background:var(--peach);color:white;border:none;border-radius:8px;font-size:0.75rem;cursor:pointer;font-family:'DM Sans',sans-serif">Resolve</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ ANALYTICS SECTION ═══ -->
    <div class="section" id="sec-analytics">
      <div class="grid-2">
        <div class="card">
          <div class="card-header"><h3>Sound Distribution</h3></div>
          <div class="card-body">
            <div class="breakdown-grid">
              <div class="breakdown-item">
                <div class="emoji">🍼</div>
                <div class="count" style="color:#f97316"><?= $stats['hungry_count'] ?? 0 ?></div>
                <div class="label">Hungry</div>
              </div>
              <div class="breakdown-item">
                <div class="emoji">😴</div>
                <div class="count" style="color:#8b5cf6"><?= $stats['sleepy_count'] ?? 0 ?></div>
                <div class="label">Sleepy</div>
              </div>
              <div class="breakdown-item">
                <div class="emoji">😣</div>
                <div class="count" style="color:#ef4444"><?= $stats['discomfort_count'] ?? 0 ?></div>
                <div class="label">Discomfort</div>
              </div>
            </div>
            <canvas id="pieChart" style="margin-top:20px;max-height:200px"></canvas>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><h3>Activity Timeline (Last 7 Days)</h3></div>
          <div class="card-body">
            <canvas id="lineChart" style="max-height:220px"></canvas>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3>Summary Stats</h3></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px">
            <div style="text-align:center;padding:16px;background:var(--bg);border-radius:12px">
              <div style="font-size:2rem;font-weight:600;color:var(--peach)"><?= $stats['total_events'] ?? 0 ?></div>
              <div style="font-size:0.8rem;color:var(--muted);margin-top:4px">Total Events</div>
            </div>
            <div style="text-align:center;padding:16px;background:var(--bg);border-radius:12px">
              <div style="font-size:2rem;font-weight:600;color:#ef4444"><?= $stats['unresolved'] ?? 0 ?></div>
              <div style="font-size:0.8rem;color:var(--muted);margin-top:4px">Unresolved</div>
            </div>
            <div style="text-align:center;padding:16px;background:var(--bg);border-radius:12px">
              <div style="font-size:2rem;font-weight:600;color:#22c55e"><?= $stats['today_events'] ?? 0 ?></div>
              <div style="font-size:0.8rem;color:var(--muted);margin-top:4px">Today</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ BABY PROFILE ═══ -->
    <div class="section" id="sec-profile">
      <?php if ($baby): ?>
      <div class="card" style="margin-bottom:20px">
        <div class="card-header">
          <h3>👶 Baby Profile — <?= htmlspecialchars($baby['name']) ?></h3>
          <button class="btn-primary" onclick="toggleEdit()">✏️ Edit</button>
        </div>
        <div class="card-body" id="profile-view">
          <div class="profile-row" style="margin-bottom:20px">
            <div class="profile-field"><label>Full Name</label><p><?= htmlspecialchars($baby['name']) ?></p></div>
            <div class="profile-field"><label>Date of Birth</label><p><?= date('F j, Y', strtotime($baby['birth_date'])) ?></p></div>
            <div class="profile-field"><label>Gender</label><p><?= ucfirst($baby['gender']) ?></p></div>
            <div class="profile-field"><label>Weight</label><p><?= $baby['weight_kg'] ?> kg</p></div>
            <div class="profile-field"><label>Feeding Interval</label><p>Every <?= $baby['feeding_interval_hours'] ?> hours</p></div>
          </div>
          <?php if ($baby['notes']): ?>
          <div style="background:var(--bg);padding:14px;border-radius:12px;font-size:0.88rem">
            <strong>Notes:</strong> <?= htmlspecialchars($baby['notes']) ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="card-body" id="profile-edit" style="display:none">
          <form method="POST" action="../api/update_baby.php">
            <input type="hidden" name="baby_id" value="<?= $baby['id'] ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
              <div>
                <label style="font-size:0.78rem;color:var(--muted);display:block;margin-bottom:5px">Baby Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($baby['name']) ?>" style="width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none">
              </div>
              <div>
                <label style="font-size:0.78rem;color:var(--muted);display:block;margin-bottom:5px">Birth Date</label>
                <input type="date" name="birth_date" value="<?= $baby['birth_date'] ?>" style="width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none">
              </div>
              <div>
                <label style="font-size:0.78rem;color:var(--muted);display:block;margin-bottom:5px">Weight (kg)</label>
                <input type="number" name="weight_kg" step="0.01" value="<?= $baby['weight_kg'] ?>" style="width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none">
              </div>
              <div>
                <label style="font-size:0.78rem;color:var(--muted);display:block;margin-bottom:5px">Feeding Interval (hours)</label>
                <input type="number" name="feeding_interval_hours" value="<?= $baby['feeding_interval_hours'] ?>" min="1" max="12" style="width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none">
              </div>
            </div>
            <div style="margin-bottom:16px">
              <label style="font-size:0.78rem;color:var(--muted);display:block;margin-bottom:5px">Notes</label>
              <textarea name="notes" rows="3" style="width:100%;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none;resize:vertical"><?= htmlspecialchars($baby['notes'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn-primary">Save Changes</button>
            <button type="button" onclick="toggleEdit()" style="margin-left:10px;padding:10px 22px;background:none;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif">Cancel</button>
          </form>
        </div>
      </div>

      <!-- Feeding Log -->
      <div class="card">
        <div class="card-header"><h3>🍼 Log Feeding</h3></div>
        <div class="card-body">
          <?php if ($lastFeed): ?>
          <div style="background:var(--bg);padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:0.85rem">
            Last fed: <strong><?= date('M j, g:ia', strtotime($lastFeed['fed_at'])) ?></strong> — <?= ucfirst($lastFeed['feeding_type']) ?> <?= $lastFeed['amount_ml'] ? "({$lastFeed['amount_ml']}ml)" : '' ?>
          </div>
          <?php endif; ?>
          <form method="POST" action="../api/log_feeding.php" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="baby_id" value="<?= $baby['id'] ?>">
            <div>
              <label style="font-size:0.78rem;color:var(--muted);display:block;margin-bottom:5px">Type</label>
              <select name="feeding_type" style="padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif">
                <option value="formula">Formula</option>
                <option value="breast">Breastfeed</option>
                <option value="solids">Solids</option>
                <option value="water">Water</option>
              </select>
            </div>
            <div>
              <label style="font-size:0.78rem;color:var(--muted);display:block;margin-bottom:5px">Amount (ml)</label>
              <input type="number" name="amount_ml" placeholder="e.g. 120" style="width:120px;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif">
            </div>
            <button type="submit" class="btn-primary">Log Feeding</button>
          </form>
        </div>
      </div>
      <?php else: ?>
      <div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--muted)">No baby profile found.</div></div>
      <?php endif; ?>
    </div>

    <!-- ═══ SETTINGS ═══ -->
    <div class="section" id="sec-settings">
      <div class="card">
        <div class="card-header"><h3>🔔 Notification Preferences</h3></div>
        <div class="card-body">
          <form method="POST" action="../api/update_prefs.php">
            <div class="pref-row">
              <div>
                <label>Sound Alerts</label>
                <span>Play audio when event detected</span>
              </div>
              <label class="toggle">
                <input type="checkbox" name="sound_enabled" <?= ($prefs['sound_enabled'] ?? 1) ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="pref-row">
              <div>
                <label>Pop-up Notifications</label>
                <span>Show in-app toast alerts</span>
              </div>
              <label class="toggle">
                <input type="checkbox" name="popup_enabled" <?= ($prefs['popup_enabled'] ?? 1) ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
              </label>
            </div>
            <div class="pref-row">
              <div><label>Alert Volume</label><span>Adjust notification volume</span></div>
              <input type="range" class="volume-slider" name="alert_volume" min="0" max="100" value="<?= $prefs['alert_volume'] ?? 80 ?>" oninput="document.getElementById('vol-val').textContent=this.value+'%'">
              <span id="vol-val" style="font-size:0.8rem;color:var(--muted);min-width:32px"><?= ($prefs['alert_volume'] ?? 80) ?>%</span>
            </div>
            <div style="margin:16px 0 8px;font-size:0.82rem;font-weight:600;color:var(--text)">Alert Types</div>
            <div class="pref-row">
              <div><label>🍼 Hunger Alerts</label></div>
              <label class="toggle"><input type="checkbox" name="hungry_alert" <?= ($prefs['hungry_alert'] ?? 1) ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
            </div>
            <div class="pref-row">
              <div><label>😴 Sleepy Alerts</label></div>
              <label class="toggle"><input type="checkbox" name="sleepy_alert" <?= ($prefs['sleepy_alert'] ?? 1) ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
            </div>
            <div class="pref-row">
              <div><label>😣 Discomfort Alerts</label></div>
              <label class="toggle"><input type="checkbox" name="discomfort_alert" <?= ($prefs['discomfort_alert'] ?? 1) ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
            </div>
            <div class="pref-row">
              <div><label>😊 Happy Detection</label></div>
              <label class="toggle"><input type="checkbox" name="happy_alert" <?= ($prefs['happy_alert'] ?? 0) ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
            </div>
            <div style="margin-top:20px">
              <button type="submit" class="btn-primary">Save Preferences</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<!-- Toast Container -->
<div id="toast-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
// ═══════════════════════════════════════
// Navigation
// ═══════════════════════════════════════
const sectionTitles = {
  dashboard: 'Dashboard', monitor: 'Live Monitor',
  logs: 'Activity Logs', analytics: 'Analytics & Insights',
  profile: 'Baby Profile', settings: 'Notifications'
};
function showSection(name) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.getElementById('sec-' + name).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  event?.target?.closest('.nav-item')?.classList.add('active');
  document.getElementById('page-title').textContent = sectionTitles[name] || name;
  if (name === 'analytics') initCharts();
  document.getElementById('sidebar').classList.remove('open');
}
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

// ═══════════════════════════════════════
// Waveform Animation
// ═══════════════════════════════════════
const waveform = document.getElementById('waveform');
const BARS = 32;
for (let i = 0; i < BARS; i++) {
  const bar = document.createElement('div');
  bar.className = 'wave-bar';
  bar.style.height = '4px';
  waveform.appendChild(bar);
}
let waveInterval = null;
let isActive = false;

function animateWave(color = '#f97316') {
  const bars = waveform.querySelectorAll('.wave-bar');
  if (waveInterval) clearInterval(waveInterval);
  isActive = true;
  waveInterval = setInterval(() => {
    if (!isActive) { bars.forEach(b => b.style.height = '4px'); return; }
    bars.forEach(bar => {
      const h = Math.random() * 50 + 6;
      bar.style.height = h + 'px';
      bar.style.background = color;
      bar.style.opacity = 0.5 + Math.random() * 0.5;
    });
  }, 100);
}

function stopWave() {
  isActive = false;
  const bars = waveform.querySelectorAll('.wave-bar');
  bars.forEach(b => { b.style.height = '4px'; b.style.background = '#e5e7eb'; });
}

// Idle shimmer
setInterval(() => {
  if (!isActive) {
    const bars = waveform.querySelectorAll('.wave-bar');
    bars.forEach(bar => { bar.style.height = (Math.random() * 8 + 2) + 'px'; bar.style.background = '#e5e7eb'; });
  }
}, 200);

// ═══════════════════════════════════════
// Sound Simulation
// ═══════════════════════════════════════
const soundTypes = ['hungry','sleepy','discomfort','happy','burp'];
const soundColors = { hungry:'#f97316',sleepy:'#8b5cf6',discomfort:'#ef4444',happy:'#22c55e',burp:'#06b6d4',unknown:'#6b7280' };
const soundEmoji  = { hungry:'🍼',sleepy:'😴',discomfort:'😣',happy:'😊',burp:'💨',random:'🎲' };
let pendingCount = 0;
let autoSimTimer = null;

function simulateSound(type) {
  if (type === 'random') type = soundTypes[Math.floor(Math.random() * soundTypes.length)];
  const conf = (70 + Math.random() * 29).toFixed(1);
  const dur  = Math.floor(15 + Math.random() * 60);

  // Update UI
  document.getElementById('current-type').textContent = soundEmoji[type] + ' ' + type.charAt(0).toUpperCase() + type.slice(1);
  document.getElementById('current-type').style.color = soundColors[type];
  document.getElementById('current-conf').textContent = conf + '%';
  document.getElementById('last-detected').textContent = 'Last: ' + new Date().toLocaleTimeString();

  animateWave(soundColors[type]);
  setTimeout(stopWave, 3000);

  // Send to server
  fetch('../api/simulate_sound.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ type, confidence: conf, duration: dur, baby_id: <?= $baby['id'] ?? 0 ?> })
  }).then(r => r.json()).then(data => {
    if (data.success) {
      addLiveFeedEntry(data.log);
      if (type !== 'happy') {
        showToast(type, conf, data.log.id);
        playAlert(soundColors[type]);
        pendingCount++;
        updateAlertCount();
      }
    }
  }).catch(console.error);
}

function addLiveFeedEntry(log) {
  const feed = document.getElementById('live-feed');
  const p = feed.querySelector('p');
  if (p) p.remove();

  const row = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);animation:fadeUp 0.3s ease';
  row.innerHTML = `
    <span style="font-size:1.3rem">${soundEmoji[log.sound_type]}</span>
    <div style="flex:1">
      <div style="font-weight:500;font-size:0.9rem">${log.sound_type.charAt(0).toUpperCase()+log.sound_type.slice(1)} detected</div>
      <div style="font-size:0.78rem;color:var(--muted)">Confidence: ${log.confidence_score}% · ${log.duration_seconds}s · Just now</div>
    </div>
    <span style="background:${soundColors[log.sound_type]}20;color:${soundColors[log.sound_type]};padding:3px 10px;border-radius:10px;font-size:0.75rem">${log.confidence_score}%</span>
  `;
  feed.insertBefore(row, feed.firstChild);
}

// ═══════════════════════════════════════
// Auto Simulation
// ═══════════════════════════════════════
function startAutoSim() {
  if (autoSimTimer) return;
  autoSimTimer = setInterval(() => simulateSound('random'), 5000);
  showToast('info', null, null, '⚡ Auto-Simulate started! Events every 5s.');
}
function stopAutoSim() {
  clearInterval(autoSimTimer);
  autoSimTimer = null;
  showToast('info', null, null, '⏹ Auto-Simulate stopped.');
}

// ═══════════════════════════════════════
// Toast Notifications
// ═══════════════════════════════════════
function showToast(type, conf, logId, customMsg = null) {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.style.borderLeftColor = soundColors[type] || 'var(--peach)';

  const labels = { hungry:'Baby is Hungry!',sleepy:'Baby is Sleepy!',discomfort:'Baby in Discomfort!',happy:'Baby is Happy!',burp:'Baby needs to burp!',info:'System' };

  toast.innerHTML = `
    <div class="toast-icon">${soundEmoji[type] || 'ℹ️'}</div>
    <div class="toast-body">
      <h4>${customMsg || labels[type] || type}</h4>
      ${conf ? `<p>Confidence: ${conf}% · Just now</p>` : ''}
    </div>
    <button class="toast-close" onclick="this.closest('.toast').remove()">✕</button>
  `;
  container.appendChild(toast);
  setTimeout(() => toast.style.opacity !== '0' && toast.remove(), 6000);
}

function updateAlertCount() {
  const badge = document.getElementById('alert-count');
  if (pendingCount > 0) { badge.style.display = 'inline-flex'; badge.textContent = pendingCount; }
  else { badge.style.display = 'none'; }
}

// ═══════════════════════════════════════
// Alert Sound (Web Audio API)
// ═══════════════════════════════════════
function playAlert(color) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain); gain.connect(ctx.destination);
    osc.frequency.setValueAtTime(880, ctx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(440, ctx.currentTime + 0.3);
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
    osc.start(); osc.stop(ctx.currentTime + 0.4);
  } catch (e) {}
}

// ═══════════════════════════════════════
// Resolve Event
// ═══════════════════════════════════════
function resolveEvent(id, btn) {
  fetch('../api/resolve_event.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ log_id: id })
  }).then(r => r.json()).then(data => {
    if (data.success) {
      btn.closest('td').innerHTML = '<span class="badge-resolved">✓ Resolved</span>';
      if (pendingCount > 0) { pendingCount--; updateAlertCount(); }
    }
  });
}

// ═══════════════════════════════════════
// Filter Logs
// ═══════════════════════════════════════
function filterLogs() {
  const type = document.getElementById('filter-type').value;
  document.querySelectorAll('#logs-tbody tr').forEach(row => {
    row.style.display = (!type || row.dataset.type === type) ? '' : 'none';
  });
}

// ═══════════════════════════════════════
// Profile edit toggle
// ═══════════════════════════════════════
function toggleEdit() {
  const view = document.getElementById('profile-view');
  const edit = document.getElementById('profile-edit');
  if (view.style.display === 'none') { view.style.display=''; edit.style.display='none'; }
  else { view.style.display='none'; edit.style.display=''; }
}

// ═══════════════════════════════════════
// Charts
// ═══════════════════════════════════════
let chartsInit = false;
function initCharts() {
  if (chartsInit) return;
  chartsInit = true;

  // Pie chart
  const pieCtx = document.getElementById('pieChart').getContext('2d');
  new Chart(pieCtx, {
    type: 'doughnut',
    data: {
      labels: ['Hungry','Sleepy','Discomfort'],
      datasets: [{
        data: [<?= $stats['hungry_count']??0 ?>, <?= $stats['sleepy_count']??0 ?>, <?= $stats['discomfort_count']??0 ?>],
        backgroundColor: ['#f97316','#8b5cf6','#ef4444'],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { position: 'bottom', labels: { font: { family: "'DM Sans'" }, padding: 16, boxWidth: 12 } } },
      cutout: '65%'
    }
  });

  // Line chart
  const days = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date(); d.setDate(d.getDate() - i);
    days.push(d.toLocaleDateString('en',{weekday:'short'}));
  }
  const lineCtx = document.getElementById('lineChart').getContext('2d');
  new Chart(lineCtx, {
    type: 'line',
    data: {
      labels: days,
      datasets: [{
        label: 'Events',
        data: days.map(() => Math.floor(Math.random() * 8 + 1)),
        borderColor: '#f97316',
        backgroundColor: 'rgba(249,115,22,0.1)',
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#f97316',
        pointRadius: 4
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { family: "'DM Sans'", size: 11 } } },
        y: { grid: { color: '#f0e0d6' }, ticks: { font: { family: "'DM Sans'", size: 11 }, stepSize: 1 }, min: 0 }
      }
    }
  });
}

// ═══════════════════════════════════════
// Poll for new events (every 10s)
// ═══════════════════════════════════════
let lastLogId = <?= !empty($logs) ? $logs[0]['id'] : 0 ?>;
setInterval(() => {
  fetch(`../api/poll_events.php?since=${lastLogId}&baby_id=<?= $baby['id'] ?? 0 ?>`)
    .then(r => r.json())
    .then(data => {
      if (data.events && data.events.length > 0) {
        data.events.forEach(e => {
          addLiveFeedEntry(e);
          if (e.sound_type !== 'happy') { showToast(e.sound_type, e.confidence_score, e.id); pendingCount++; updateAlertCount(); }
          lastLogId = Math.max(lastLogId, e.id);
        });
      }
    }).catch(() => {});
}, 10000);
</script>
</body>
</html>
