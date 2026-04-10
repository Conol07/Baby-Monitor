<?php
require_once '../includes/config.php';
requireLogin();
if ($_SESSION['user_role'] !== 'babysitter') {
    header('Location: ../admin/dashboard.php'); exit;
}

$db   = getDB();
$user = currentUser();

// Get assigned baby
$baby = $db->prepare("
    SELECT bp.* FROM baby_profiles bp
    JOIN babysitter_assignments ba ON ba.baby_id = bp.id
    WHERE ba.babysitter_id = ? AND ba.active = 1
    LIMIT 1
");
$baby->execute([$user['id']]);
$baby = $baby->fetch();

// Recent logs
$logs = $db->prepare("SELECT * FROM activity_logs WHERE baby_id = ? ORDER BY created_at DESC LIMIT 15");
$logs->execute([$baby['id'] ?? 0]);
$logs = $logs->fetchAll();

// Unresolved
$unresolved = array_filter($logs, fn($l) => !$l['resolved']);

$prefs = $db->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
$prefs->execute([$user['id']]);
$prefs = $prefs->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BabyWatch — Babysitter Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@300;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #f0f9ff; --surface: #ffffff; --blue: #0ea5e9; --blue2: #0284c7;
  --text: #0c1a2e; --muted: #64748b; --border: #e0f2fe;
  --red: #ef4444; --green: #22c55e; --nav-w: 240px;
}
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
.sidebar {
  width: var(--nav-w); background: #0c1a2e; position: fixed; top:0; left:0; bottom:0;
  display: flex; flex-direction: column; z-index: 100; transition: transform 0.3s;
}
.sidebar-logo { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.07); }
.sidebar-logo h1 { font-family: 'Fraunces', serif; font-size: 1.4rem; color: white; font-weight: 600; display: flex; align-items: center; gap: 10px; }
.sidebar-logo .role-badge { font-family: 'DM Sans'; font-size: 0.68rem; font-weight: 500; background: var(--blue); color: white; padding: 2px 8px; border-radius: 20px; letter-spacing: 0.05em; text-transform: uppercase; display: inline-block; margin-top: 4px; }
.sidebar-user { padding: 16px 24px; border-bottom: 1px solid rgba(255,255,255,0.07); display: flex; align-items: center; gap: 10px; }
.avatar-circle { width: 36px; height: 36px; background: linear-gradient(135deg, var(--blue), var(--blue2)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 600; color: white; }
.sidebar-user .info p { font-size: 0.85rem; color: white; } .sidebar-user .info span { font-size: 0.73rem; color: rgba(255,255,255,0.4); }
nav { flex: 1; padding: 16px 0; }
.nav-section { font-size: 0.68rem; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 0.1em; padding: 12px 24px 6px; }
.nav-item { display: flex; align-items: center; gap: 12px; padding: 11px 24px; color: rgba(255,255,255,0.55); text-decoration: none; font-size: 0.88rem; cursor: pointer; transition: all 0.15s; border-left: 3px solid transparent; }
.nav-item:hover { color: white; background: rgba(255,255,255,0.05); }
.nav-item.active { color: white; background: rgba(14,165,233,0.15); border-left-color: var(--blue); font-weight: 500; }
.nav-item i { width: 18px; text-align: center; }
.sidebar-footer { padding: 16px 24px; border-top: 1px solid rgba(255,255,255,0.07); }
.btn-logout { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.4); text-decoration: none; font-size: 0.85rem; transition: color 0.15s; }
.btn-logout:hover { color: var(--red); }
.main { margin-left: var(--nav-w); flex: 1; min-height: 100vh; display: flex; flex-direction: column; }
.topbar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 32px; height: 64px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 90; }
.topbar h2 { font-family: 'Fraunces', serif; font-size: 1.2rem; font-weight: 600; }
.status-pill { display: flex; align-items: center; gap: 7px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; color: #16a34a; }
.status-dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; animation: blink 1.5s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
.content { padding: 28px 32px; flex: 1; }
.hamburger { display: none; background: none; border: none; cursor: pointer; color: var(--text); font-size: 1.2rem; }
/* Baby status card */
.status-hero { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); border-radius: 20px; padding: 28px 32px; color: white; display: flex; align-items: center; gap: 24px; margin-bottom: 24px; position: relative; overflow: hidden; }
.status-hero::after { content: '👶'; position: absolute; right: 24px; bottom: -10px; font-size: 100px; opacity: 0.1; line-height: 1; }
.status-hero-icon { width: 68px; height: 68px; background: rgba(255,255,255,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
.status-hero h3 { font-family: 'Fraunces', serif; font-size: 1.5rem; }
.status-hero p { opacity: 0.85; font-size: 0.88rem; margin-top: 4px; }
/* Alert cards */
.alert-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.alert-card { background: var(--surface); border-radius: 16px; padding: 20px; border: 1px solid var(--border); }
.alert-card.urgent { border-color: #fecaca; background: #fff1f2; }
.alert-card .icon { font-size: 2rem; margin-bottom: 10px; }
.alert-card h4 { font-size: 1.6rem; font-weight: 600; }
.alert-card p { font-size: 0.8rem; color: var(--muted); margin-top: 4px; }
/* Card */
.card { background: var(--surface); border-radius: 20px; border: 1px solid var(--border); overflow: hidden; margin-bottom: 20px; }
.card-header { padding: 18px 24px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-header h3 { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; }
.card-body { padding: 20px 24px; }
/* Log table */
.log-table { width: 100%; border-collapse: collapse; }
.log-table th { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); padding: 10px 0; text-align: left; border-bottom: 1px solid var(--border); font-weight: 500; }
.log-table td { padding: 12px 0; font-size: 0.87rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.log-table tr:last-child td { border-bottom: none; }
.type-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 500; }
.badge-resolved { font-size: 0.72rem; padding: 3px 10px; background: #f0fdf4; color: #16a34a; border-radius: 10px; }
.badge-pending  { font-size: 0.72rem; padding: 3px 10px; background: #fff7ed; color: #c2410c; border-radius: 10px; animation: urgentPulse 2s ease infinite; }
@keyframes urgentPulse { 0%,100%{opacity:1} 50%{opacity:0.6} }
/* Section */
.section { display: none; }
.section.active { display: block; }
/* Toast */
#toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; max-width: 340px; }
.toast { background: var(--surface); border-radius: 14px; padding: 16px 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); display: flex; align-items: flex-start; gap: 12px; border-left: 4px solid var(--blue); animation: slideIn 0.3s ease; cursor: pointer; }
@keyframes slideIn { from { opacity:0; transform: translateX(20px); } to { opacity:1; transform: translateX(0); } }
.toast-icon { font-size: 1.4rem; }
.toast-body h4 { font-size: 0.9rem; font-weight: 600; }
.toast-body p { font-size: 0.8rem; color: var(--muted); margin-top: 2px; }
.toast-close { margin-left: auto; background: none; border: none; cursor: pointer; color: var(--muted); font-size: 1rem; }
/* Prefs */
.pref-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--border); }
.pref-row:last-child { border-bottom: none; }
.pref-row label { font-size: 0.9rem; }
.pref-row span { font-size: 0.78rem; color: var(--muted); display: block; margin-top: 2px; }
.toggle { position: relative; width: 44px; height: 24px; display: inline-block; }
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; inset: 0; background: var(--border); border-radius: 24px; cursor: pointer; transition: background 0.2s; }
.toggle-slider::before { content: ''; position: absolute; width: 18px; height: 18px; background: white; border-radius: 50%; top: 3px; left: 3px; transition: transform 0.2s; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
.toggle input:checked + .toggle-slider { background: var(--blue); }
.toggle input:checked + .toggle-slider::before { transform: translateX(20px); }
.btn-primary { padding: 10px 22px; background: var(--blue); color: white; border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.88rem; font-weight: 500; cursor: pointer; transition: opacity 0.15s; }
.btn-primary:hover { opacity: 0.85; }
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main { margin-left: 0; }
  .hamburger { display: block; }
  .content { padding: 20px 16px; }
  .topbar { padding: 0 16px; }
}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <h1>👶 BabyWatch</h1>
    <span class="role-badge">Babysitter View</span>
  </div>
  <div class="sidebar-user">
    <div class="avatar-circle"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
    <div class="info">
      <p><?= htmlspecialchars($user['name']) ?></p>
      <span>Babysitter</span>
    </div>
  </div>
  <nav>
    <div class="nav-section">Monitoring</div>
    <a class="nav-item active" onclick="showSection('status')"><i class="fas fa-heartbeat"></i> Baby Status</a>
    <a class="nav-item" onclick="showSection('alerts')"><i class="fas fa-bell"></i> Alerts <span id="alert-badge" style="background:#ef4444;color:white;border-radius:50%;width:18px;height:18px;font-size:0.65rem;font-weight:600;display:inline-flex;align-items:center;justify-content:center;margin-left:4px"><?= count($unresolved) ?></span></a>
    <a class="nav-item" onclick="showSection('logs')"><i class="fas fa-clock"></i> Activity Log</a>
    <div class="nav-section">Account</div>
    <a class="nav-item" onclick="showSection('settings')"><i class="fas fa-cog"></i> Preferences</a>
  </nav>
  <div class="sidebar-footer">
    <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="hamburger" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="fas fa-bars"></i></button>
      <h2 id="page-title">Baby Status</h2>
    </div>
    <div style="display:flex;align-items:center;gap:14px">
      <div class="status-pill"><span class="status-dot"></span> Monitoring Active</div>
    </div>
  </div>

  <div class="content">

    <!-- STATUS -->
    <div class="section active" id="sec-status">
      <?php if ($baby): ?>
      <div class="status-hero">
        <div class="status-hero-icon">🌸</div>
        <div>
          <h3><?= htmlspecialchars($baby['name']) ?></h3>
          <p>In your care right now</p>
          <div style="display:flex;gap:12px;margin-top:10px;flex-wrap:wrap">
            <?php
              $ageMonths = (int)((time() - strtotime($baby['birth_date'])) / (60*60*24*30.44));
            ?>
            <span style="background:rgba(255,255,255,0.15);padding:4px 12px;border-radius:16px;font-size:0.8rem">Age: <?= $ageMonths ?> months</span>
            <span style="background:rgba(255,255,255,0.15);padding:4px 12px;border-radius:16px;font-size:0.8rem">Feed every <?= $baby['feeding_interval_hours'] ?>h</span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="alert-grid">
        <div class="alert-card <?= count($unresolved) > 0 ? 'urgent' : '' ?>">
          <div class="icon">🚨</div>
          <h4><?= count($unresolved) ?></h4>
          <p>Unresolved Alerts</p>
        </div>
        <div class="alert-card">
          <div class="icon">📋</div>
          <h4><?= count($logs) ?></h4>
          <p>Total Events Today</p>
        </div>
        <div class="alert-card">
          <div class="icon">⏱️</div>
          <h4 id="last-event-time"><?= !empty($logs) ? timeAgo($logs[0]['created_at']) : '—' ?></h4>
          <p>Last Detected Event</p>
        </div>
      </div>

      <!-- Latest Alert -->
      <?php if (!empty($unresolved)): $latest = reset($unresolved); ?>
      <div class="card" style="border-color:#fecaca">
        <div class="card-header" style="background:#fff1f2">
          <h3 style="color:#dc2626">⚠️ Active Alert</h3>
          <span style="font-size:0.8rem;color:var(--muted)"><?= timeAgo($latest['created_at']) ?></span>
        </div>
        <div class="card-body" style="display:flex;align-items:center;gap:16px">
          <div style="font-size:3rem"><?= ['hungry'=>'🍼','sleepy'=>'😴','discomfort'=>'😣','happy'=>'😊','burp'=>'💨'][$latest['sound_type']] ?? '❓' ?></div>
          <div style="flex:1">
            <div style="font-size:1.1rem;font-weight:600"><?= soundTypeLabel($latest['sound_type']) ?></div>
            <div style="font-size:0.85rem;color:var(--muted);margin-top:4px">Detected <?= timeAgo($latest['created_at']) ?> · Confidence: <?= $latest['confidence_score'] ?>%</div>
          </div>
          <button onclick="resolveEvent(<?= $latest['id'] ?>, this)" class="btn-primary">Mark Resolved</button>
        </div>
      </div>
      <?php else: ?>
      <div class="card" style="border-color:#bbf7d0">
        <div class="card-body" style="text-align:center;padding:40px">
          <div style="font-size:3rem;margin-bottom:12px">✅</div>
          <div style="font-size:1rem;font-weight:500;color:#16a34a">All Clear!</div>
          <div style="font-size:0.85rem;color:var(--muted);margin-top:6px">No active alerts right now.</div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ALERTS -->
    <div class="section" id="sec-alerts">
      <div class="card">
        <div class="card-header">
          <h3>🔔 All Alerts</h3>
          <span style="font-size:0.8rem;color:var(--muted)"><?= count($unresolved) ?> pending</span>
        </div>
        <div class="card-body" style="padding:0">
          <?php if (empty($logs)): ?>
            <div style="padding:40px;text-align:center;color:var(--muted)">No alerts yet.</div>
          <?php else: ?>
          <table class="log-table" style="width:100%">
            <thead><tr>
              <th style="padding-left:24px">Type</th>
              <th>Confidence</th>
              <th>Detected</th>
              <th style="padding-right:24px">Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td style="padding-left:24px">
                <span class="type-badge" style="background:<?= soundTypeColor($log['sound_type']) ?>20;color:<?= soundTypeColor($log['sound_type']) ?>">
                  <?= soundTypeLabel($log['sound_type']) ?>
                </span>
              </td>
              <td style="font-size:0.82rem"><?= $log['confidence_score'] ?>%</td>
              <td style="font-size:0.8rem;color:var(--muted)"><?= timeAgo($log['created_at']) ?></td>
              <td style="padding-right:24px">
                <?php if ($log['resolved']): ?>
                  <span class="badge-resolved">✓ Resolved</span>
                <?php else: ?>
                  <button onclick="resolveEvent(<?= $log['id'] ?>, this)" style="padding:4px 12px;background:var(--blue);color:white;border:none;border-radius:8px;font-size:0.75rem;cursor:pointer;font-family:'DM Sans',sans-serif">Resolve</button>
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

    <!-- LOGS -->
    <div class="section" id="sec-logs">
      <div class="card">
        <div class="card-header"><h3>📋 Activity Log</h3></div>
        <div class="card-body" style="padding:0">
          <?php if (empty($logs)): ?>
            <div style="padding:40px;text-align:center;color:var(--muted)">No activity yet.</div>
          <?php else: ?>
          <table class="log-table" style="width:100%">
            <thead><tr>
              <th style="padding-left:24px">Type</th><th>Time</th><th style="padding-right:24px">Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td style="padding-left:24px">
                <span class="type-badge" style="background:<?= soundTypeColor($log['sound_type']) ?>20;color:<?= soundTypeColor($log['sound_type']) ?>">
                  <?= soundTypeLabel($log['sound_type']) ?>
                </span>
              </td>
              <td>
                <div style="font-size:0.85rem"><?= date('M j, g:ia', strtotime($log['created_at'])) ?></div>
                <div style="font-size:0.75rem;color:var(--muted)"><?= timeAgo($log['created_at']) ?></div>
              </td>
              <td style="padding-right:24px">
                <?= $log['resolved'] ? '<span class="badge-resolved">✓ Done</span>' : '<span class="badge-pending">● Pending</span>' ?>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- SETTINGS -->
    <div class="section" id="sec-settings">
      <div class="card">
        <div class="card-header"><h3>🔔 Alert Preferences</h3></div>
        <div class="card-body">
          <form method="POST" action="../api/update_prefs.php">
            <div class="pref-row">
              <div><label>Sound Alerts</label><span>Play sound on alert</span></div>
              <label class="toggle"><input type="checkbox" name="sound_enabled" <?= ($prefs['sound_enabled']??1)?'checked':'' ?>><span class="toggle-slider"></span></label>
            </div>
            <div class="pref-row">
              <div><label>Pop-up Notifications</label><span>Show in-app alerts</span></div>
              <label class="toggle"><input type="checkbox" name="popup_enabled" <?= ($prefs['popup_enabled']??1)?'checked':'' ?>><span class="toggle-slider"></span></label>
            </div>
            <div class="pref-row">
              <div><label>🍼 Hunger Alerts</label></div>
              <label class="toggle"><input type="checkbox" name="hungry_alert" <?= ($prefs['hungry_alert']??1)?'checked':'' ?>><span class="toggle-slider"></span></label>
            </div>
            <div class="pref-row">
              <div><label>😴 Sleepy Alerts</label></div>
              <label class="toggle"><input type="checkbox" name="sleepy_alert" <?= ($prefs['sleepy_alert']??1)?'checked':'' ?>><span class="toggle-slider"></span></label>
            </div>
            <div class="pref-row">
              <div><label>😣 Discomfort Alerts</label></div>
              <label class="toggle"><input type="checkbox" name="discomfort_alert" <?= ($prefs['discomfort_alert']??1)?'checked':'' ?>><span class="toggle-slider"></span></label>
            </div>
            <div style="margin-top:20px"><button type="submit" class="btn-primary">Save</button></div>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>

<div id="toast-container"></div>

<script>
const soundColors = {hungry:'#f97316',sleepy:'#8b5cf6',discomfort:'#ef4444',happy:'#22c55e',burp:'#06b6d4'};
const soundEmoji  = {hungry:'🍼',sleepy:'😴',discomfort:'😣',happy:'😊',burp:'💨'};

function showSection(name) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.getElementById('sec-'+name).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  event?.target?.closest('.nav-item')?.classList.add('active');
  document.getElementById('page-title').textContent = {status:'Baby Status',alerts:'Alerts',logs:'Activity Log',settings:'Preferences'}[name]||name;
}

function showToast(type, conf) {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.style.borderLeftColor = soundColors[type]||'#0ea5e9';
  toast.innerHTML = `
    <div class="toast-icon">${soundEmoji[type]||'🔔'}</div>
    <div class="toast-body">
      <h4>Baby ${type.charAt(0).toUpperCase()+type.slice(1)} Alert!</h4>
      <p>Confidence: ${conf}% · Just now</p>
    </div>
    <button class="toast-close" onclick="this.closest('.toast').remove()">✕</button>
  `;
  container.appendChild(toast);
  try {
    const ctx = new AudioContext();
    const o = ctx.createOscillator(), g = ctx.createGain();
    o.connect(g); g.connect(ctx.destination);
    o.frequency.value = 660; g.gain.setValueAtTime(0.3, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
    o.start(); o.stop(ctx.currentTime + 0.5);
  } catch(e){}
  setTimeout(() => toast.remove(), 7000);
}

function resolveEvent(id, btn) {
  fetch('../api/resolve_event.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({log_id: id})
  }).then(r => r.json()).then(data => {
    if (data.success) {
      const cell = btn.closest('td');
      if (cell) cell.innerHTML = '<span class="badge-resolved">✓ Resolved</span>';
      // Update badge
      const badge = document.getElementById('alert-badge');
      const cur = parseInt(badge.textContent) - 1;
      badge.textContent = Math.max(0, cur);
      if (cur <= 1) location.reload();
    }
  });
}

// Poll for new events
let lastId = <?= !empty($logs) ? $logs[0]['id'] : 0 ?>;
setInterval(() => {
  fetch(`../api/poll_events.php?since=${lastId}&baby_id=<?= $baby['id'] ?? 0 ?>`)
    .then(r => r.json())
    .then(data => {
      if (data.events?.length > 0) {
        data.events.forEach(e => {
          showToast(e.sound_type, e.confidence_score);
          lastId = Math.max(lastId, e.id);
          // Update badge
          const badge = document.getElementById('alert-badge');
          badge.textContent = parseInt(badge.textContent||0) + 1;
          // Update stat
          document.getElementById('last-event-time').textContent = 'Just now';
        });
      }
    }).catch(()=>{});
}, 8000);
</script>
</body>
</html>
