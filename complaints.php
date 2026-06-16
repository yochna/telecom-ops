<?php
require 'auth.php';
require 'db.php';

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_complaint'])) {
    $sim_id = (int)$_POST['sim_id'];
    $customer = trim($_POST['customer_name']);
    $zone = trim($_POST['zone']);
    $type = $_POST['type'];
    $desc = trim($_POST['description']);

    // Auto-priority scoring based on type
    $priority_map = [
        'network' => 'high',
        'sim_swap' => 'critical',
        'billing' => 'medium',
        'data' => 'low',
        'other' => 'low'
    ];
    $priority = $priority_map[$type] ?? 'medium';

    $stmt = $pdo->prepare("INSERT INTO complaints 
      (sim_id, customer_name, zone, type, description, priority, status) 
      VALUES (?, ?, ?, ?, ?, ?, 'open')");
    $stmt->execute([$sim_id, $customer, $zone, $type, $desc, $priority]);
    $success = "Complaint logged with priority: " . strtoupper($priority);
}

if (isset($_GET['resolve'])) {
    $stmt = $pdo->prepare("UPDATE complaints SET status='resolved', resolved_at=NOW() WHERE id=?");
    $stmt->execute([(int)$_GET['resolve']]);
    header('Location: complaints.php');
    exit;
}

$complaints = $pdo->query("SELECT * FROM complaints ORDER BY 
  FIELD(priority,'critical','high','medium','low'), created_at DESC")->fetchAll();
$sims = $pdo->query("SELECT id, sim_number, customer_name FROM sims WHERE status='active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Complaints — TeleOps</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #f4f6f8; }
  .sidebar { width: 200px; background: #fff; border-right: 1px solid #e8e8e8;
             padding: 1rem 0; position: fixed; height: 100vh; }
  .logo { padding: 0 1rem 1rem; border-bottom: 1px solid #f0f0f0; margin-bottom: 0.5rem; }
  .logo h1 { font-size: 15px; }
  .nav a { display: flex; align-items: center; gap: 8px; padding: 10px 1rem;
            font-size: 13px; color: #666; text-decoration: none; }
  .nav a:hover, .nav a.active { background: #EBF4FF; color: #185FA5; }
  .main { margin-left: 200px; padding: 1.5rem; }
  h2 { font-size: 18px; margin-bottom: 1.5rem; color: #111; font-weight: 500; }
  .two-col { display: grid; grid-template-columns: 340px 1fr; gap: 1rem; }
  .card { background: #fff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 1.25rem; }
  .card h3 { font-size: 14px; font-weight: 500; margin-bottom: 1rem; color: #111; }
  label { font-size: 13px; color: #555; display: block; margin-bottom: 4px; margin-top: 10px; }
  input, select, textarea { width: 100%; padding: 8px 10px; border: 1px solid #ddd;
    border-radius: 7px; font-size: 13px; font-family: inherit; }
  textarea { resize: vertical; min-height: 70px; }
  button { margin-top: 12px; width: 100%; padding: 9px; background: #185FA5;
           color: #fff; border: none; border-radius: 7px; font-size: 13px; cursor: pointer; }
  .success { background: #EAF3DE; color: #27500A; padding: 8px 12px;
             border-radius: 7px; font-size: 13px; margin-bottom: 1rem; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { font-size: 11px; color: #aaa; text-align: left; padding: 0 8px 8px 0;
       border-bottom: 1px solid #f0f0f0; }
  td { padding: 9px 8px 9px 0; border-bottom: 1px solid #f8f8f8; color: #444; }
  .pill { font-size: 11px; padding: 2px 8px; border-radius: 999px; }
  .p-critical { background:#FCEBEB;color:#791F1F; }
  .p-high { background:#FAEEDA;color:#633806; }
  .p-medium { background:#E6F1FB;color:#0C447C; }
  .p-low { background:#F1EFE8;color:#444441; }
  .p-resolved { background:#EAF3DE;color:#27500A; }
  a.resolve { font-size: 12px; color: #185FA5; text-decoration: none; }
</style>
</head>
<body>
<div class="sidebar">
  <div class="logo"><h1>📡 TeleOps</h1></div>
  <nav class="nav">
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="sims.php">📱 SIM Inventory</a>
    <a href="complaints.php" class="active">🎫 Complaints</a>
    <a href="outages.php">🗼 Tower Outages</a>
    <a href="logout.php">🚪 Logout</a>
  </nav>
</div>
<div class="main">
  <h2>Complaint management</h2>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <div class="two-col">
    <div class="card">
      <h3>Log new complaint</h3>
      <form method="POST">
        <label>SIM / Customer</label>
        <select name="sim_id" required>
          <option value="">Select SIM</option>
          <?php foreach ($sims as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['sim_number'] . ' — ' . $s['customer_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Customer name</label>
        <input type="text" name="customer_name" required placeholder="Full name">
        <label>Zone</label>
        <input type="text" name="zone" required placeholder="e.g. 4B">
        <label>Complaint type</label>
        <select name="type" required>
          <option value="network">Network issue</option>
          <option value="billing">Billing issue</option>
          <option value="sim_swap">SIM swap</option>
          <option value="data">Data issue</option>
          <option value="other">Other</option>
        </select>
        <label>Description</label>
        <textarea name="description" placeholder="Describe the issue..."></textarea>
        <button type="submit" name="add_complaint">Log complaint (auto-priority)</button>
      </form>
    </div>
    <div class="card">
      <h3>All complaints (sorted by priority)</h3>
      <table>
        <tr><th>Customer</th><th>Zone</th><th>Type</th><th>Priority</th><th>Status</th><th>Action</th></tr>
        <?php foreach ($complaints as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['customer_name']) ?></td>
          <td><?= htmlspecialchars($c['zone']) ?></td>
          <td><?= ucfirst($c['type']) ?></td>
          <td><span class="pill p-<?= $c['priority'] ?>"><?= ucfirst($c['priority']) ?></span></td>
          <td><span class="pill <?= $c['status']==='resolved' ? 'p-resolved' : 'p-high' ?>"><?= ucfirst($c['status']) ?></span></td>
          <td><?php if ($c['status'] !== 'resolved'): ?>
            <a class="resolve" href="?resolve=<?= $c['id'] ?>">✓ Resolve</a>
          <?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
</body>
</html>