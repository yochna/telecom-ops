<?php
require 'auth.php';
require 'db.php';

// KPIs
$active_sims = $pdo->query("SELECT COUNT(*) FROM sims WHERE status='active'")->fetchColumn();
$total_sims = $pdo->query("SELECT COUNT(*) FROM sims")->fetchColumn();
$open_complaints = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('open', 'in_progress')")->fetchColumn();
$critical_complaints = $pdo->query("SELECT COUNT(*) FROM complaints WHERE priority='critical' AND status != 'resolved'")->fetchColumn();
$active_outages = $pdo->query("SELECT COUNT(*) FROM tower_outages WHERE status='active'")->fetchColumn();
$total_affected = $pdo->query("SELECT COALESCE(SUM(sims_affected), 0) FROM tower_outages WHERE status='active'")->fetchColumn();

// Avg resolution time
$avg_res = $pdo->query("
  SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 
  FROM complaints WHERE resolved_at IS NOT NULL
")->fetchColumn();
$avg_res = $avg_res ? round($avg_res, 1) : 'N/A';

// Recent complaints
$recent_complaints = $pdo->query("
  SELECT c.*, s.sim_number 
  FROM complaints c 
  LEFT JOIN sims s ON c.sim_id = s.id
  ORDER BY c.created_at DESC LIMIT 5
")->fetchAll();

// Active outages
$outages = $pdo->query("
  SELECT * FROM tower_outages 
  WHERE status='active' 
  ORDER BY started_at DESC LIMIT 5
")->fetchAll();

// Zone health stats (for SIM health chart)
$zone_stats = $pdo->query("
  SELECT zone,
    COUNT(*) AS total,
    SUM(status='active') AS active_count,
    SUM(status='suspended') AS suspended_count,
    SUM(status='inactive') AS inactive_count
  FROM sims GROUP BY zone ORDER BY zone
")->fetchAll();

// Churn risk zones (high complaints + outages in same zone)
$churn_risk = $pdo->query("
  SELECT 
    z.zone,
    z.total_sims,
    z.active_sims,
    COALESCE(o.outage_count, 0) as outage_count,
    COALESCE(o.sims_affected, 0) as sims_affected,
    COALESCE(c.complaint_count, 0) as complaint_count,
    COALESCE(c.critical_count, 0) as critical_count,
    ROUND((z.total_sims - z.active_sims) / z.total_sims * 100, 1) as churn_rate
  FROM (
    SELECT zone, COUNT(*) as total_sims, SUM(status='active') as active_sims 
    FROM sims GROUP BY zone
  ) z
  LEFT JOIN (
    SELECT zone, COUNT(*) as outage_count, SUM(sims_affected) as sims_affected 
    FROM tower_outages WHERE status='active' GROUP BY zone
  ) o ON z.zone = o.zone
  LEFT JOIN (
    SELECT zone, COUNT(*) as complaint_count, SUM(priority='critical') as critical_count 
    FROM complaints WHERE status != 'resolved' GROUP BY zone
  ) c ON z.zone = c.zone
  ORDER BY churn_rate DESC, complaint_count DESC
  LIMIT 5
")->fetchAll();

// Complaints by type for mini chart
$type_stats = $pdo->query("
  SELECT type, COUNT(*) as count 
  FROM complaints 
  WHERE status != 'resolved' 
  GROUP BY type
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>TeleOps — Dashboard</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; display: flex; min-height: 100vh; }
  .sidebar { width: 220px; background: #fff; border-right: 1px solid #e8e8e8;
             padding: 1rem 0; position: fixed; height: 100vh; z-index: 100; }
  .logo { padding: 0 1.25rem 1rem; border-bottom: 1px solid #f0f0f0; margin-bottom: 0.5rem; }
  .logo h1 { font-size: 16px; color: #111; font-weight: 600; }
  .logo span { font-size: 11px; color: #888; }
  .nav a { display: flex; align-items: center; gap: 10px; padding: 10px 1.25rem;
            font-size: 13px; color: #666; text-decoration: none; transition: all 0.2s; }
  .nav a:hover, .nav a.active { background: #EBF4FF; color: #185FA5; font-weight: 500; }
  .main { margin-left: 220px; flex: 1; padding: 1.5rem 2rem; }
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
  .topbar h2 { font-size: 20px; color: #111; font-weight: 600; }
  .user-info { font-size: 13px; color: #888; }
  
  .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 1.5rem; }
  .kpi { background: #fff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 1.25rem; position: relative; }
  .kpi-label { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
  .kpi-val { font-size: 28px; font-weight: 600; color: #111; }
  .kpi-sub { font-size: 12px; margin-top: 6px; font-weight: 500; }
  .green { color: #0F6E56; } .amber { color: #854F0B; } .red { color: #A32D2D; }
  .kpi-trend { position: absolute; top: 1.25rem; right: 1.25rem; font-size: 11px; padding: 2px 8px; border-radius: 4px; }
  .trend-up { background: #FCEBEB; color: #791F1F; }
  .trend-down { background: #EAF3DE; color: #27500A; }
  
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
  .card { background: #fff; border: 1px solid #e8e8e8; border-radius: 10px; padding: 1.25rem; }
  .card h3 { font-size: 14px; font-weight: 600; color: #111; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; }
  
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { font-size: 11px; color: #888; font-weight: 600; text-align: left;
       padding: 10px 12px 10px 0; border-bottom: 2px solid #f0f0f0; text-transform: uppercase; letter-spacing: 0.5px; }
  td { padding: 10px 12px 10px 0; border-bottom: 1px solid #f8f8f8; color: #444; vertical-align: middle; }
  tr:hover td { background: #fafafa; }
  tr:last-child td { border-bottom: none; }
  
  .pill { font-size: 11px; padding: 3px 10px; border-radius: 999px; font-weight: 500; }
  .p-critical { background: #FCEBEB; color: #791F1F; }
  .p-high { background: #FAEEDA; color: #633806; }
  .p-medium { background: #E6F1FB; color: #0C447C; }
  .p-low { background: #F1EFE8; color: #444441; }
  .p-open { background: #FAEEDA; color: #633806; }
  .p-in_progress { background: #E6F1FB; color: #0C447C; }
  .p-resolved { background: #EAF3DE; color: #27500A; }
  .p-active { background: #E1F5EE; color: #085041; }
  
  .bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 12px; }
  .bar-label { width: 60px; color: #666; font-weight: 500; }
  .bar-track { flex: 1; background: #f4f4f4; border-radius: 4px; height: 10px; overflow: hidden; }
  .bar-fill { height: 10px; border-radius: 4px; transition: width 0.3s ease; }
  
  .risk-card { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f8f8f8; }
  .risk-card:last-child { border-bottom: none; }
  .risk-zone { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
               font-weight: 700; font-size: 14px; color: #fff; }
  .risk-high { background: #E24B4A; }
  .risk-medium { background: #EF9F27; }
  .risk-low { background: #1D9E75; }
  .risk-info { flex: 1; }
  .risk-title { font-size: 13px; font-weight: 600; color: #111; }
  .risk-meta { font-size: 11px; color: #888; margin-top: 2px; }
  .risk-rate { font-size: 18px; font-weight: 700; }
  
  .mini-chart { display: flex; align-items: flex-end; gap: 8px; height: 60px; margin-top: 8px; }
  .chart-bar { flex: 1; border-radius: 4px 4px 0 0; min-height: 4px; position: relative; }
  .chart-label { position: absolute; bottom: -18px; left: 50%; transform: translateX(-50%); font-size: 10px; color: #888; white-space: nowrap; }
  
  a.btn { display: inline-block; padding: 6px 14px; background: #185FA5; color: #fff;
           border-radius: 7px; font-size: 12px; text-decoration: none; }
  a.btn:hover { background: #0C447C; }
  
  .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
  .dot-red { background: #E24B4A; }
  .dot-green { background: #1D9E75; }
  .dot-amber { background: #EF9F27; }
  
  @media (max-width: 1200px) {
    .two-col { grid-template-columns: 1fr; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
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
    <a href="dashboard.php" class="active">🏠 Dashboard</a>
    <a href="sims.php">📱 SIM Inventory</a>
    <a href="complaints.php">🎫 Complaints</a>
    <a href="outages.php">🗼 Tower Outages</a>
    <a href="logout.php">🚪 Logout</a>
  </nav>
</div>

<div class="main">
  <div class="topbar">
    <h2>Network Operations Dashboard</h2>
    <span class="user-info">👤 <?= htmlspecialchars($_SESSION['user_name']) ?> 
      (<?= htmlspecialchars($_SESSION['user_role']) ?>)</span>
  </div>

  <div class="kpi-grid">
    <div class="kpi">
      <div class="kpi-label">Active SIMs</div>
      <div class="kpi-val"><?= number_format($active_sims) ?></div>
      <div class="kpi-sub green">
        <span class="status-dot dot-green"></span>Live on network
      </div>
      <span class="kpi-trend trend-down">of <?= number_format($total_sims) ?> total</span>
    </div>
    <div class="kpi">
      <div class="kpi-label">Open Complaints</div>
      <div class="kpi-val"><?= $open_complaints ?></div>
      <div class="kpi-sub amber">
        <span class="status-dot dot-amber"></span><?= $critical_complaints ?> critical pending
      </div>
      <span class="kpi-trend trend-up">Needs attention</span>
    </div>
    <div class="kpi">
      <div class="kpi-label">Tower Outages</div>
      <div class="kpi-val"><?= $active_outages ?></div>
      <div class="kpi-sub red">
        <span class="status-dot dot-red"></span><?= number_format($total_affected) ?> SIMs affected
      </div>
      <span class="kpi-trend trend-up">Active incidents</span>
    </div>
    <div class="kpi">
      <div class="kpi-label">Avg Resolution</div>
      <div class="kpi-val"><?= $avg_res ?>h</div>
      <div class="kpi-sub">SLA target: 6h</div>
      <span class="kpi-trend <?= $avg_res > 6 ? 'trend-up' : 'trend-down' ?>">
        <?= $avg_res > 6 ? 'Over SLA' : 'Within SLA' ?>
      </span>
    </div>
  </div>

  <div class="two-col">
    <div class="card">
      <h3>
        ⚠️ Recent Complaints
        <a href="complaints.php" class="btn">View All</a>
      </h3>
      <table>
        <tr><th>Customer</th><th>Zone</th><th>Type</th><th>Priority</th></tr>
        <?php foreach ($recent_complaints as $c): ?>
        <tr>
          <td>
            <?= htmlspecialchars($c['customer_name']) ?>
            <?php if ($c['sim_number']): ?>
              <div style="font-size: 11px; color: #888; font-family: monospace;"><?= $c['sim_number'] ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($c['zone']) ?></td>
          <td><?= ucfirst($c['type']) ?></td>
          <td>
            <span class="pill p-<?= $c['priority'] ?>">
              <?= ucfirst($c['priority']) ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div class="card">
      <h3>📊 SIM Health by Zone</h3>
      <?php foreach ($zone_stats as $z):
        $pct = $z['total'] > 0 ? round(($z['active_count'] / $z['total']) * 100) : 0;
        $suspended_pct = $z['total'] > 0 ? round(($z['suspended_count'] / $z['total']) * 100) : 0;
        $color = $pct >= 80 ? '#1D9E75' : ($pct >= 60 ? '#EF9F27' : '#E24B4A');
      ?>
      <div class="bar-row">
        <span class="bar-label">Zone <?= htmlspecialchars($z['zone']) ?></span>
        <div class="bar-track">
          <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
        </div>
        <span style="font-size:11px;color:#888;width:35px;text-align:right"><?= $pct ?>%</span>
      </div>
      <?php if ($suspended_pct > 0): ?>
      <div style="margin-left: 70px; font-size: 11px; color: #A32D2D; margin-bottom: 8px;">
        ⚠️ <?= $suspended_pct ?>% suspended
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="two-col">
    <div class="card">
      <h3>
        🗼 Active Tower Outages
        <a href="outages.php" class="btn">Manage</a>
      </h3>
      <table>
        <tr><th>Tower</th><th>Zone</th><th>Location</th><th>Affected</th><th>Duration</th></tr>
        <?php foreach ($outages as $o): 
          $hours = round((time() - strtotime($o['started_at'])) / 3600, 1);
        ?>
        <tr>
          <td style="font-family: monospace; font-weight: 600; color: #185FA5;"><?= htmlspecialchars($o['tower_id']) ?></td>
          <td><?= htmlspecialchars($o['zone']) ?></td>
          <td><?= htmlspecialchars($o['location']) ?></td>
          <td style="font-weight: 600; color: #A32D2D;"><?= number_format($o['sims_affected']) ?></td>
          <td><?= $hours ?>h</td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($outages)): ?>
        <tr><td colspan="5" style="text-align: center; color: #1D9E75; padding: 1.5rem;">
          <span class="status-dot dot-green"></span>All towers operational
        </td></tr>
        <?php endif; ?>
      </table>
    </div>

    <div class="card">
      <h3>🚨 Churn Risk Zones (Top 5)</h3>
      <?php foreach ($churn_risk as $risk): 
        $risk_level = $risk['churn_rate'] > 30 ? 'high' : ($risk['churn_rate'] > 15 ? 'medium' : 'low');
      ?>
      <div class="risk-card">
        <div class="risk-zone risk-<?= $risk_level ?>"><?= htmlspecialchars($risk['zone']) ?></div>
        <div class="risk-info">
          <div class="risk-title">Zone <?= htmlspecialchars($risk['zone']) ?></div>
          <div class="risk-meta">
            <?= $risk['outage_count'] ?> active outage<?= $risk['outage_count'] != 1 ? 's' : '' ?> • 
            <?= $risk['complaint_count'] ?> open complaint<?= $risk['complaint_count'] != 1 ? 's' : '' ?>
          </div>
        </div>
        <div class="risk-rate <?= $risk_level === 'high' ? 'red' : ($risk_level === 'medium' ? 'amber' : 'green') ?>">
          <?= $risk['churn_rate'] ?>%
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card" style="margin-top: 12px;">
    <h3>📈 Complaint Distribution by Type</h3>
    <div class="mini-chart">
      <?php 
      $colors = ['#185FA5', '#EF9F27', '#E24B4A', '#1D9E75', '#854F0B'];
      $max_count = max(array_column($type_stats, 'count'));
      foreach ($type_stats as $i => $t): 
        $height = $max_count > 0 ? ($t['count'] / $max_count * 100) : 0;
      ?>
      <div class="chart-bar" style="height: <?= max($height, 10) ?>%; background: <?= $colors[$i % count($colors)] ?>">
        <span class="chart-label"><?= ucfirst($t['type']) ?> (<?= $t['count'] ?>)</span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($type_stats)): ?>
        <div style="color: #888; font-size: 13px; padding: 1rem;">No active complaints</div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>