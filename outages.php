<?php
require 'auth.php';
require 'db.php';

$success = '';
$error = '';

// Add new outage (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_outage'])) {
    if ($_SESSION['user_role'] !== 'admin') {
        $error = "Only admins can log outages.";
    } else {
        $tower_id = trim($_POST['tower_id']);
        $zone = trim($_POST['zone']);
        $location = trim($_POST['location']);
        $sims_affected = (int)$_POST['sims_affected'];
        $notes = trim($_POST['notes']);
        
        if (!preg_match('/^T-\d{3}$/', $tower_id)) {
            $error = "Tower ID must be in format T-### (e.g., T-004)";
        } elseif ($sims_affected < 0) {
            $error = "Affected SIMs cannot be negative.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO tower_outages 
                (tower_id, zone, location, sims_affected, notes, status) 
                VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$tower_id, $zone, $location, $sims_affected, $notes]);
            $success = "Outage logged for Tower $tower_id";
        }
    }
}

// Resolve outage
if (isset($_GET['resolve']) && $_SESSION['user_role'] === 'admin') {
    $id = (int)$_GET['resolve'];
    $stmt = $pdo->prepare("UPDATE tower_outages SET status='resolved', resolved_at=NOW() WHERE id=?");
    $stmt->execute([$id]);
    header('Location: outages.php');
    exit;
}

// Fetch outages
$status_filter = $_GET['status'] ?? 'all';
$query = "SELECT * FROM tower_outages WHERE 1=1";
$params = [];

if ($status_filter === 'active') {
    $query .= " AND status = 'active'";
} elseif ($status_filter === 'resolved') {
    $query .= " AND status = 'resolved'";
}

$query .= " ORDER BY status ASC, started_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$outages = $stmt->fetchAll();

// Stats
$active_outages = $pdo->query("SELECT COUNT(*) FROM tower_outages WHERE status='active'")->fetchColumn();
$total_affected = $pdo->query("SELECT COALESCE(SUM(sims_affected), 0) FROM tower_outages WHERE status='active'")->fetchColumn();
$avg_resolution = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(HOUR, started_at, resolved_at)) 
    FROM tower_outages WHERE status='resolved' AND resolved_at IS NOT NULL
")->fetchColumn();
$avg_resolution = $avg_resolution ? round($avg_resolution, 1) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tower Outages — TeleOps</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; }
  .sidebar { width: 220px; background: #fff; border-right: 1px solid #e8e8e8;
             padding: 1rem 0; position: fixed; height: 100vh; z-index: 100; }
  .logo { padding: 0 1.25rem 1rem; border-bottom: 1px solid #f0f0f0; margin-bottom: 0.5rem; }
  .logo h1 { font-size: 16px; color: #111; font-weight: 600; }
  .logo span { font-size: 11px; color: #888; }
  .nav a { display: flex; align-items: center; gap: 10px; padding: 10px 1.25rem;
            font-size: 13px; color: #666; text-decoration: none; transition: all 0.2s; }
  .nav a:hover, .nav a.active { background: #EBF4FF; color: #185FA5; font-weight: 500; }
  .main { margin-left: 220px; padding: 1.5rem 2rem; }
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
  .topbar h2 { font-size: 20px; color: #111; font-weight: 600; }
  .user-info { font-size: 13px; color: #888; }
  
  .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 1.5rem; }
  .stat-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 1rem 1.25rem; }
  .stat-label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
  .stat-val { font-size: 28px; font-weight: 600; color: #111; }
  .stat-red { color: #A32D2D; }
  .stat-amber { color: #854F0B; }
  
  .content-grid { display: grid; grid-template-columns: 340px 1fr; gap: 1.25rem; }
  .card { background: #fff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 1.25rem; }
  .card h3 { font-size: 14px; font-weight: 600; margin-bottom: 1rem; color: #111; }
  
  .filters { display: flex; gap: 10px; margin-bottom: 1rem; }
  .filters a { padding: 6px 14px; background: #f4f4f4; color: #666; border-radius: 6px; 
              font-size: 13px; text-decoration: none; }
  .filters a.active { background: #185FA5; color: #fff; }
  .filters a:hover:not(.active) { background: #e8e8e8; }
  
  label { font-size: 12px; color: #555; display: block; margin-bottom: 4px; margin-top: 12px; font-weight: 500; }
  input, textarea { width: 100%; padding: 8px 10px; border: 1px solid #ddd;
    border-radius: 7px; font-size: 13px; font-family: inherit; }
  input:focus, textarea:focus { outline: none; border-color: #185FA5; }
  textarea { resize: vertical; min-height: 80px; }
  
  .btn { display: inline-block; padding: 8px 16px; background: #185FA5; color: #fff;
         border-radius: 7px; font-size: 13px; text-decoration: none; border: none; cursor: pointer; }
  .btn:hover { background: #0C447C; }
  .btn-success { background: #0F6E56; }
  .btn-success:hover { background: #0a5744; }
  .btn-danger { background: #A32D2D; }
  .btn-danger:hover { background: #7a2222; }
  
  .alert { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 1rem; }
  .alert-success { background: #EAF3DE; color: #27500A; border: 1px solid #d4e7c0; }
  .alert-error { background: #FCEBEB; color: #791F1F; border: 1px solid #f5c2c2; }
  
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { font-size: 11px; color: #888; font-weight: 600; text-align: left;
       padding: 10px 12px 10px 0; border-bottom: 2px solid #f0f0f0; text-transform: uppercase; letter-spacing: 0.5px; }
  td { padding: 12px 12px 12px 0; border-bottom: 1px solid #f8f8f8; color: #444; vertical-align: middle; }
  tr:hover td { background: #fafafa; }
  tr:last-child td { border-bottom: none; }
  
  .pill { font-size: 11px; padding: 3px 10px; border-radius: 999px; font-weight: 500; }
  .p-active { background: #FCEBEB; color: #791F1F; }
  .p-resolved { background: #EAF3DE; color: #27500A; }
  
  .tower-id { font-family: 'Courier New', monospace; font-weight: 600; color: #185FA5; }
  .zone-badge { background: #f0f0f0; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
  .affected-count { font-weight: 600; color: #A32D2D; }
  
  .timeline { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #666; }
  .timeline-dot { width: 8px; height: 8px; border-radius: 50%; }
  .dot-active { background: #E24B4A; }
  .dot-resolved { background: #1D9E75; }
  
  .notes-preview { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  
  @media (max-width: 1024px) {
    .content-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="sidebar">
  <div class="logo">
    <h1>📡 TeleOps</h1>
    <span>Network Operations</span>
  </div>
  <nav class="nav">
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="sims.php">📱 SIM Inventory</a>
    <a href="complaints.php">🎫 Complaints</a>
    <a href="outages.php" class="active">🗼 Tower Outages</a>
    <a href="logout.php">🚪 Logout</a>
  </nav>
</div>

<div class="main">
  <div class="topbar">
    <h2>Tower Outage Management</h2>
    <span class="user-info">👤 <?= htmlspecialchars($_SESSION['user_name']) ?> 
      (<?= htmlspecialchars($_SESSION['user_role']) ?>)</span>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error">✗ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Active Outages</div>
      <div class="stat-val stat-red"><?= number_format($active_outages) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">SIMs Affected (Live)</div>
      <div class="stat-val stat-red"><?= number_format($total_affected) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Avg Resolution Time</div>
      <div class="stat-val stat-amber"><?= $avg_resolution ?>h</div>
    </div>
  </div>

  <div class="content-grid">
    <?php if ($_SESSION['user_role'] === 'admin'): ?>
    <div class="card">
      <h3>⚠️ Log New Outage</h3>
      <form method="POST">
        <label>Tower ID</label>
        <input type="text" name="tower_id" placeholder="T-004" required 
               pattern="T-\d{3}" title="Format: T-###">
        
        <label>Zone</label>
        <input type="text" name="zone" placeholder="e.g. 4B" required 
               pattern="[A-Z0-9]{2}" title="2 character zone code">
        
        <label>Location</label>
        <input type="text" name="location" placeholder="e.g. Raipur North" required>
        
        <label>SIMs Affected</label>
        <input type="number" name="sims_affected" placeholder="0" required min="0">
        
        <label>Notes</label>
        <textarea name="notes" placeholder="Describe the issue..."></textarea>
        
        <button type="submit" name="add_outage" class="btn btn-danger" style="margin-top: 16px; width: 100%;">
          Log Outage
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="card">
      <h3>ℹ️ Agent View</h3>
      <p style="font-size: 13px; color: #666; line-height: 1.6;">
        You are viewing outages in read-only mode. Contact an admin to log new outages or resolve existing ones.
      </p>
      <div style="margin-top: 1rem; padding: 1rem; background: #f8f8f8; border-radius: 8px;">
        <div style="font-size: 12px; color: #888; margin-bottom: 4px;">Your Role</div>
        <div style="font-size: 14px; font-weight: 600; color: #185FA5;">Support Agent</div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <h3>🗼 Outage Log</h3>
      <div class="filters">
        <a href="outages.php" class="<?= $status_filter === 'all' ? 'active' : '' ?>">All</a>
        <a href="?status=active" class="<?= $status_filter === 'active' ? 'active' : '' ?>">Active</a>
        <a href="?status=resolved" class="<?= $status_filter === 'resolved' ? 'active' : '' ?>">Resolved</a>
      </div>

      <table>
        <tr>
          <th>Tower</th>
          <th>Zone</th>
          <th>Location</th>
          <th>Affected</th>
          <th>Started</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
        <?php foreach ($outages as $o): ?>
        <tr>
          <td class="tower-id"><?= htmlspecialchars($o['tower_id']) ?></td>
          <td><span class="zone-badge"><?= htmlspecialchars($o['zone']) ?></span></td>
          <td><?= htmlspecialchars($o['location']) ?></td>
          <td class="affected-count"><?= number_format($o['sims_affected']) ?></td>
          <td>
            <div class="timeline">
              <span class="timeline-dot <?= $o['status'] === 'active' ? 'dot-active' : 'dot-resolved' ?>"></span>
              <?= date('d M, H:i', strtotime($o['started_at'])) ?>
            </div>
            <?php if ($o['resolved_at']): ?>
              <div style="font-size: 11px; color: #888; margin-top: 2px;">
                Resolved: <?= date('d M, H:i', strtotime($o['resolved_at'])) ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <span class="pill p-<?= $o['status'] ?>">
              <?= ucfirst($o['status']) ?>
            </span>
          </td>
          <td>
            <?php if ($o['status'] === 'active' && $_SESSION['user_role'] === 'admin'): ?>
              <a href="?resolve=<?= $o['id'] ?>" class="btn btn-success" style="padding: 4px 10px; font-size: 12px;"
                 onclick="return confirm('Mark Tower <?= $o['tower_id'] ?> as resolved?')">
                ✓ Resolve
              </a>
            <?php elseif ($o['status'] === 'resolved'): ?>
              <span style="font-size: 12px; color: #1D9E75;">✓ Fixed</span>
            <?php else: ?>
              <span style="font-size: 12px; color: #aaa;">Pending</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($outages)): ?>
        <tr><td colspan="7" style="text-align: center; color: #aaa; padding: 2rem;">No outages found</td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
</body>
</html>