<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
require 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>TeleOps — Login</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f8; display: flex;
         justify-content: center; align-items: center; min-height: 100vh; }
  .card { background: #fff; padding: 2.5rem; border-radius: 16px;
          border: 1px solid #e0e0e0; width: 380px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
  .logo { text-align: center; margin-bottom: 1.5rem; }
  .logo h1 { font-size: 28px; margin-bottom: 4px; }
  .logo p { font-size: 13px; color: #888; }
  h2 { font-size: 18px; margin-bottom: 4px; color: #111; }
  p.sub { font-size: 13px; color: #888; margin-bottom: 1.5rem; }
  label { font-size: 12px; color: #555; display: block; margin-bottom: 6px; font-weight: 500; }
  input { width: 100%; padding: 10px 12px; border: 1px solid #ddd;
          border-radius: 8px; font-size: 14px; margin-bottom: 1rem; transition: border 0.2s; }
  input:focus { outline: none; border-color: #185FA5; }
  button { width: 100%; padding: 11px; background: #185FA5; color: #fff;
           border: none; border-radius: 8px; font-size: 14px; cursor: pointer; font-weight: 500; }
  button:hover { background: #0C447C; }
  .error { background: #FCEBEB; color: #791F1F; padding: 10px 12px;
           border-radius: 8px; font-size: 13px; margin-bottom: 1rem; }
  .hint { font-size: 12px; color: #aaa; text-align: center; margin-top: 1.5rem; }
  .hint strong { color: #555; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>📡 TeleOps</h1>
    <p>Network Operations Management</p>
  </div>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <label>Email address</label>
    <input type="email" name="email" placeholder="admin@teleops.in" required>
    <label>Password</label>
    <input type="password" name="password" placeholder="Enter password" required>
    <button type="submit">Sign in</button>
  </form>
  <p class="hint">
    <strong>Demo:</strong> admin@teleops.in / password<br>
    <strong>Agent:</strong> agent@teleops.in / password
  </p>
</div>
</body>
</html>