<?php
require 'auth.php';
require 'db.php';

$success = '';
$error = '';

// Handle SIM activation/deactivation/porting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sim'])) {
    $sim_id = (int)$_POST['sim_id'];
    $new_status = $_POST['new_status'];
    $valid_statuses = ['active', 'inactive', 'ported', 'suspended'];
    
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE sims SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $sim_id]);
        $success = "SIM status updated to " . strtoupper($new_status);
    } else {
        $error = "Invalid status selected.";
    }
}

// Handle new SIM registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sim'])) {
    $sim_number = trim($_POST['sim_number']);
    $customer_name = trim($_POST['customer_name']);
    $phone = trim($_POST['phone']);
    $zone = trim($_POST['zone']);
    
    // Validate SIM number format
    if (!preg_match('/^SIM\d{6}$/', $sim_number)) {
        $error = "SIM number must be in format SIM###### (e.g., SIM001234)";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO sims (sim_number, customer_name, phone, zone, status, activated_at) 
                                  VALUES (?, ?, ?, ?, 'active', CURDATE())");
            $stmt->execute([$sim_number, $customer_name, $phone, $zone]);
            $success = "New SIM registered successfully.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "SIM number already exists.";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all SIMs with filtering
$status_filter = $_GET['status'] ?? 'all';
$zone_filter = $_GET['zone'] ?? '';

$query = "SELECT * FROM sims WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($zone_filter)) {
    $query .= " AND zone = ?";
    $params[] = $zone_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sims = $stmt->fetchAll();

// Get unique zones for filter
$zones = $pdo->query("SELECT DISTINCT zone FROM sims ORDER BY zone")->fetchAll(PDO::FETCH_COLUMN);

// Stats
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(status='active') as active,
    SUM(status='inactive') as inactive,
    SUM(status='ported') as ported,
    SUM(status='suspended') as suspended
    FROM sims")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SIM Inventory — TeleOps</title>
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
  
  .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 1.5rem; }
  .stat-card { background: #fff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 1rem 1.25rem; }
  .stat-label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
  .stat-val { font-size: 24px; font-weight: 600; color: #111; }
  .stat-active { color: #0F6E56; }
  .stat-inactive { color: #888; }
  .stat-ported { color: #854F0B; }
  .stat-suspended { color: #A32D2D; }
  
  .content-grid { display: grid; grid-template-columns: 320px 1fr; gap: 1.25rem; }
  .card { background: #fff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 1.25rem; }
  .card h3 { font-size: 14px; font-weight: 600; margin-bottom: 1rem; color: #111; }
  
  .filters { display: flex; gap: 10px; margin-bottom: 1rem; }
  .filters select { padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
  .filters a { padding: 6px 14px; background: #f4f4f4; color: #666; border-radius: 6px; 
              font-size: 13px; text-decoration: none; }
  .filters a:hover { background: #e8e8e8; }
  
  label { font-size: 12px; color: #555; display: block; margin-bottom: 4px; margin-top: 12px; font-weight: 500; }
  input, select { width: 100%; padding: 8px 10px; border: 1px solid #ddd;
    border-radius: 7px; font-size: 13px; font-family: inherit; }
  input:focus, select:focus { outline: none; border-color: #185FA5; }
  
  .btn { display: inline-block; padding: 8px 16px; background: #185FA5; color: #fff;
         border-radius: 7px; font-size: 13px; text-decoration: none; border: none; cursor: pointer; }
  .btn:hover { background: #0C447C; }
  .btn-success { background: #0F6E56; }
  .btn-success:hover { background: #0a5744; }
  .btn-danger { background: #A32D2D; }
  .btn-danger:hover { background: #7a2222; }
  .btn-warning { background: #854F0B; }
  .btn-warning:hover { background: #633806; }
  
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
  .p-active { background: #E1F5EE; color: #085041; }
  .p-inactive { background: #F1EFE8; color: #444441; }
  .p-ported { background: #FAEEDA; color: #633806; }
  .p-suspended { background: #FCEBEB; color: #791F1F; }
  
  .action-form { display: inline; }
  .action-form select { width: 110px; display: inline; padding: 4px 6px; font-size: 12px; }
  .action-form button { padding: 4px 10px; font-size: 12px; margin-top: 0; width: auto; }
  
  .sim-number { font-family: 'Courier New', monospace; font-weight: 600; color: #185FA5; }
  .zone-badge { background: #f0f0f0; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
  
  @media (max-width: 1024px) {
    .content-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
    <a href="sims.php" class="active">📱 SIM Inventory</a>
    <a href="complaints.php">🎫 Complaints</a>
    <a href="outages.php">🗼 Tower Outages</a>
    <a href="logout.php">🚪 Logout</a>
  </nav>
</div>

<div class="main">
  <div class="topbar">
    <h2>SIM Inventory Management</h2>
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
      <div class="stat-label">Total SIMs</div>
      <div class="stat-val"><?= number_format($stats['total']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Active</div>
      <div class="stat-val stat-active"><?= number_format($stats['active']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Inactive / Ported</div>
      <div class="stat-val stat-inactive"><?= number_format($stats['inactive'] + $stats['ported']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Suspended</div>
      <div class="stat-val stat-suspended"><?= number_format($stats['suspended']) ?></div>
    </div>
  </div>

  <div class="content-grid">
    <div class="card">
      <h3>➕ Register New SIM</h3>
      <form method="POST">
        <label>SIM Number</label>
        <input type="text" name="sim_number" placeholder="SIM001234" required 
               pattern="SIM\d{6}" title="Format: SIM######">
        
        <label>Customer Name</label>
        <input type="text" name="customer_name" placeholder="Full name" required>
        
        <label>Phone Number</label>
        <input type="tel" name="phone" placeholder="9876543210" required 
               pattern="\d{10}" title="10 digit phone number">
        
        <label>Zone</label>
        <input type="text" name="zone" placeholder="e.g. 4B" required 
               pattern="[A-Z0-9]{2}" title="2 character zone code">
        
        <button type="submit" name="add_sim" class="btn btn-success" style="margin-top: 16px; width: 100%;">
          Register SIM
        </button>
      </form>
    </div>

    <div class="card">
      <h3>📋 All SIM Cards</h3>
      <div class="filters">
        <select onchange="location.href='?status='+this.value">
          <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
          <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          <option value="ported" <?= $status_filter === 'ported' ? 'selected' : '' ?>>Ported</option>
          <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
        <select onchange="location.href='?zone='+this.value">
          <option value="">All Zones</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= $z ?>" <?= $zone_filter === $z ? 'selected' : '' ?>>Zone <?= $z ?></option>
          <?php endforeach; ?>
        </select>
        <a href="sims.php">Reset</a>
      </div>

      <table>
        <tr>
          <th>SIM Number</th>
          <th>Customer</th>
          <th>Phone</th>
          <th>Zone</th>
          <th>Status</th>
          <th>Activated</th>
          <th>Action</th>
        </tr>
        <?php foreach ($sims as $sim): ?>
        <tr>
          <td class="sim-number"><?= htmlspecialchars($sim['sim_number']) ?></td>
          <td><?= htmlspecialchars($sim['customer_name']) ?></td>
          <td><?= htmlspecialchars($sim['phone']) ?></td>
          <td><span class="zone-badge"><?= htmlspecialchars($sim['zone']) ?></span></td>
          <td>
            <span class="pill p-<?= $sim['status'] ?>">
              <?= ucfirst($sim['status']) ?>
            </span>
          </td>
          <td><?= date('d M Y', strtotime($sim['activated_at'])) ?></td>
          <td>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <form method="POST" class="action-form">
              <input type="hidden" name="sim_id" value="<?= $sim['id'] ?>">
              <select name="new_status" onchange="this.form.submit()">
                <option value="">Change...</option>
                <option value="active">Activate</option>
                <option value="inactive">Deactivate</option>
                <option value="ported">Port Out</option>
                <option value="suspended">Suspend</option>
              </select>
              <input type="hidden" name="update_sim" value="1">
            </form>
            <?php else: ?>
              <span style="color: #aaa; font-size: 12px;">View only</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($sims)): ?>
        <tr><td colspan="7" style="text-align: center; color: #aaa; padding: 2rem;">No SIMs found</td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
</body>
</html>