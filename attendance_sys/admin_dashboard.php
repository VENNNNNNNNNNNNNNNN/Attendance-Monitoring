<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}
require 'db_connect.php';
$stmt = $pdo->prepare("SELECT username, name FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
  <h1>👑 Admin Dashboard</h1>
  <p>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</p>
  <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
  <a href="attendance_report.php" class="btn btn-secondary">View Reports</a>
  <a href="logout.php" class="btn btn-danger">Logout</a>
</body>
</html>
