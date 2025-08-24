<?php
// faculty_dashboard.php
session_start();
require 'db_connect.php';

// Require login
if (!isset($_SESSION['user_id'])) {
  header("Location: login.html");
  exit;
}

// Fetch current user
$stmt = $pdo->prepare("SELECT id, username, name, role, email, profile_pic FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Restrict to Faculty staff only (exact string)
if (!$user || $user['role'] !== 'Faculty staff') {
  // Optional smart redirects if role mismatched
  if ($user && $user['role'] === 'admin') {
    header("Location: admin_dashboard.php"); exit;
  } elseif ($user && $user['role'] === 'monitoring staff') {
    header("Location: monitoring_dashboard.php"); exit;
  }
  die("Unauthorized access.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Faculty Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #4e73df, #6f42c1);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 2rem 1rem;
    }
    .card { border-radius: 1rem; box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
    .avatar {
      width: 64px; height: 64px; object-fit: cover; border-radius: 50%;
      border: 2px solid #e9ecef;
    }
    .btn-gradient {
      background: linear-gradient(135deg, #4e73df, #6f42c1);
      border: none; color: white; font-weight: 600;
    }
    .btn-gradient:hover { opacity: .9; }
  </style>
</head>
<body>
  <div class="container" style="max-width: 980px;">
    <!-- Header -->
    <div class="card mb-4">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
          <img src="<?= htmlspecialchars($user['profile_pic'] ?: 'uploads/default.png') ?>" alt="Profile" class="avatar">
          <div>
            <h5 class="mb-0">Welcome, <?= htmlspecialchars($user['name'] ?: $user['username']) ?> 👋</h5>
            <small class="text-muted">Role: Faculty Staff</small>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-secondary" href="logout.php">Logout</a>
        </div>
      </div>
    </div>

    <!-- Shortcuts / Widgets -->
    <div class="row g-4">
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="text-muted mb-2">Quick Actions</h6>
            <div class="d-grid gap-2">
              <a href="#" class="btn btn-gradient">View My Schedule</a>
              <a href="#" class="btn btn-gradient">Submit Attendance</a>
              <a href="#" class="btn btn-gradient">Download Reports</a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="text-muted mb-2">Account</h6>
            <ul class="list-group">
              <li class="list-group-item d-flex justify-content-between">
                <span>Email</span><strong><?= htmlspecialchars($user['email']) ?></strong>
              </li>
              <li class="list-group-item d-flex justify-content-between">
                <span>Username</span><strong><?= htmlspecialchars($user['username']) ?></strong>
              </li>
              <li class="list-group-item d-flex justify-content-between">
                <span>Role</span><span class="badge text-bg-primary">Faculty staff</span>
              </li>
            </ul>
            <div class="mt-3 text-end">
              <a href="#" class="btn btn-sm btn-outline-primary">Edit Profile</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Announcements -->
    <div class="card mt-4">
      <div class="card-body">
        <h6 class="text-muted mb-2">Announcements</h6>
        <p class="mb-0">No announcements yet. Check back later.</p>
      </div>
    </div>
  </div>
</body>
</html>
