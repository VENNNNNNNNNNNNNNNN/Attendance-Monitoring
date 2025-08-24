<?php
// admin_dashboard.php
session_start();
require 'config.php';
require 'db_connect.php';

// Require login
if (!isset($_SESSION['user_id'])) {
  header("Location: login.html"); exit;
}

/* ---- Helpers ---- */
function sanitize($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* ---- Fetch current user ---- */
$stmt = $pdo->prepare("SELECT id, username, name, role, email, profile_pic FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

// Only Admins may access this page
if (!$me || $me['role'] !== 'admin') {
  die("Unauthorized.");
}

/* ---- CSRF token ---- */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ---- Handle POST actions (Approve / Reject) ---- */
$notice = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF check
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $error = "Invalid request token.";
  }

  if (!$error && isset($_POST['action']) && $_POST['action'] === 'approve_request') {
    $reqId = (int)($_POST['req_id'] ?? 0);

    // fetch pending request
    $q = $pdo->prepare("SELECT * FROM signup_requests WHERE id=? AND status='pending'");
    $q->execute([$reqId]);
    $req = $q->fetch(PDO::FETCH_ASSOC);

    if ($req) {
      // prevent duplicates
      $dup = $pdo->prepare("SELECT 1 FROM users WHERE username=? OR email=?");
      $dup->execute([$req['username'], $req['email']]);
      if ($dup->fetch()) {
        $error = "Cannot approve. Username/email already exists in users.";
      } else {
        // create actual user
        $ins = $pdo->prepare("INSERT INTO users
          (username, password, role, name, age, email, contact, profile_pic, verified)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $ins->execute([
          $req['username'], $req['password'], $req['role'],
          $req['name'], $req['age'], $req['email'], $req['contact'], $req['profile_pic']
        ]);

        // mark request approved
        $pdo->prepare("UPDATE signup_requests SET status='approved' WHERE id=?")->execute([$reqId]);

        // optional: notify user
        @sendMail($req['email'], 'Signup Approved', '<p>Your account has been approved. You can now log in.</p>');

        $notice = "Request #$reqId approved. Account created.";
      }
    } else {
      $error = "Request not found or already processed.";
    }
  }

  if (!$error && isset($_POST['action']) && $_POST['action'] === 'reject_request') {
    $reqId = (int)($_POST['req_id'] ?? 0);
    $pdo->prepare("UPDATE signup_requests SET status='rejected' WHERE id=? AND status='pending'")->execute([$reqId]);
    $notice = "Request #$reqId rejected.";
  }
}

/* ---- Data for listing ---- */
// Pending signup requests
try {
  $reqStmt = $pdo->query("SELECT * FROM signup_requests WHERE status='pending' ORDER BY created_at DESC");
  $pendingRequests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $pendingRequests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg,#4e73df,#6f42c1); min-height:100vh; padding: 2rem 1rem; }
    .card { border-radius: 1rem; box-shadow: 0 8px 20px rgba(0,0,0,.15); }
    .btn-gradient { background: linear-gradient(135deg,#4e73df,#6f42c1); border:none; color:#fff; font-weight:600; }
    .btn-gradient:hover { opacity:.9; }
    .avatar { width:48px; height:48px; border-radius:50%; object-fit:cover; }
  </style>
</head>
<body>
<div class="container" style="max-width: 1100px;">

  <!-- Header -->
  <div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3">
        <img src="<?= sanitize($me['profile_pic'] ?: 'uploads/default.png') ?>" class="avatar" alt="Profile">
        <div>
          <h5 class="mb-0">Admin Panel</h5>
          <small class="text-muted">Logged in as: <?= sanitize($me['username']) ?></small>
        </div>
      </div>
      <div class="d-flex gap-2">
        <a href="manage_users.php" class="btn btn-outline-primary">Manage Users</a>
        <a href="attendance_report.php" class="btn btn-outline-secondary">View Reports</a>
        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
      </div>
    </div>
  </div>

  <?php if ($notice): ?>
    <div class="alert alert-success"><?= sanitize($notice) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= sanitize($error) ?></div>
  <?php endif; ?>

  <!-- Pending Signup Requests -->
  <div class="card">
    <div class="card-body">
      <h6 class="text-muted mb-2">Pending Signup Requests</h6>

      <?php if (empty($pendingRequests)): ?>
        <p class="mb-0 text-muted">No pending requests.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>#</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Name</th>
                <th>Age</th>
                <th>Requested</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingRequests as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= sanitize($r['username']) ?></td>
                <td><?= sanitize($r['email']) ?></td>
                <td><?= sanitize($r['role']) ?></td>
                <td><?= sanitize($r['name']) ?></td>
                <td><?= (int)$r['age'] ?></td>
                <td><?= sanitize($r['created_at']) ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline" onsubmit="return confirm('Approve this request?');">
                    <input type="hidden" name="action" value="approve_request">
                    <input type="hidden" name="req_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button class="btn btn-sm btn-success">Approve</button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('Reject this request?');">
                    <input type="hidden" name="action" value="reject_request">
                    <input type="hidden" name="req_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button class="btn btn-sm btn-outline-danger">Reject</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>
