<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}
require 'db_connect.php';

$stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user['role'] !== 'staff') {
    die("Access denied. Only Staff can view this page.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Records</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
  <h2>📂 My Records</h2>
  <p>This is a placeholder page where staff can view their attendance records.</p>
  <a href="javascript:history.back()" class="btn btn-primary">⬅ Back to Dashboard</a>
</body>
</html>
