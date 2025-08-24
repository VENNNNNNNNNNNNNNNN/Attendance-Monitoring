<?php
// superadmin_dashboard.php
session_start();
require 'config.php';
require 'db_connect.php';


// Require login
if (!isset($_SESSION['user_id'])) {
  header("Location: login.html"); exit;
}

// Fetch current user
// Fetch current user
$stmt = $pdo->prepare("SELECT id, username, name, role, email, profile_pic, last_login_at, last_login_ip FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);


// Restrict to Super admin only
if (!$me || $me['role'] !== 'Super admin') {
  die("Unauthorized.");
}

/* ---- Helpers ---- */
function sanitize($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

/* ---- CSRF token ---- */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ---- Handle POST actions ---- */
$notice = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF check for ALL actions
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $error = "Invalid request token.";
  }

  // DELETE USER
  if (!$error && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $uid = (int)($_POST['id'] ?? 0);

    // Disallow deleting self
    if ($uid === (int)$me['id']) {
      $error = "You cannot delete your own account.";
    } else {
      // Fetch target to check role
      $u = $pdo->prepare("SELECT id, role FROM users WHERE id=?");
      $u->execute([$uid]);
      $target = $u->fetch(PDO::FETCH_ASSOC);

      if (!$target) {
        $error = "User not found.";
      } elseif ($target['role'] === 'Super admin') {
        $error = "Cannot delete the Super admin account.";
      } else {
        $del = $pdo->prepare("DELETE FROM users WHERE id=?");
        $del->execute([$uid]);
        $notice = "User #$uid deleted.";
      }
    }
  }

  // Update user data (Super admin can update any role's data)
  if (!$error && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $uid     = (int)($_POST['id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $age     = (int)($_POST['age'] ?? 0);
    $email   = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $role    = trim($_POST['role'] ?? '');

    // Guard: prevent creating a 2nd Super admin via role change
    if ($role === 'Super admin') {
      $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='Super admin' AND id <> ?");
      $chk->execute([$uid]);
      if ($chk->fetchColumn() > 0) {
        $error = "Only one Super admin account is allowed.";
      }
    }

    if (!$error) {
      $upd = $pdo->prepare("UPDATE users SET name=?, age=?, email=?, contact=?, role=? WHERE id=?");
      $upd->execute([$name, $age, $email, $contact, $role, $uid]);
      $notice = "User #$uid updated.";
    }
  }

  // Reset password for a user
  if (!$error && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $uid = (int)($_POST['id'] ?? 0);
    $newpass = trim($_POST['new_password'] ?? '');
    if (strlen($newpass) < 6) {
      $error = "Password must be at least 6 characters.";
    } else {
      $hash = password_hash($newpass, PASSWORD_BCRYPT);
      $upd = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
      $upd->execute([$hash, $uid]);
      $notice = "Password updated for user #$uid.";
    }
  }

  // Create a new Admin account (signup form inside dashboard)
  if (!$error && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $age      = (int)($_POST['age'] ?? 0);
    $email    = trim($_POST['email'] ?? '');
    $contact  = trim($_POST['contact'] ?? '');

    if ($username === '' || $password === '' || $email === '') {
      $error = "Username, password, and email are required.";
    } else {
      // Uniqueness checks
      $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? OR email=?");
      $chk->execute([$username, $email]);
      if ($chk->fetchColumn() > 0) {
        $error = "Username or Email already taken.";
      }
    }

    if (!$error) {
      $hash = password_hash($password, PASSWORD_BCRYPT);
      $ins = $pdo->prepare("INSERT INTO users (username, password, role, name, age, email, contact, profile_pic, verified)
                            VALUES (?, ?, 'admin', ?, ?, ?, ?, 'uploads/default.png', 1)");
      $ins->execute([$username, $hash, $name, $age, $email, $contact]);
      $notice = "Admin account '$username' created.";
    }
  }
    // Approve/Reject SIGNUP REQUEST
  if (!$error && isset($_POST['action']) && $_POST['action'] === 'approve_request') {
    $reqId = (int)($_POST['req_id'] ?? 0);

    // kunin request (pending lang)
    $q = $pdo->prepare("SELECT * FROM signup_requests WHERE id=? AND status='pending'");
    $q->execute([$reqId]);
    $req = $q->fetch(PDO::FETCH_ASSOC);

    if ($req) {
      // iwas duplicate vs users
      $dup = $pdo->prepare("SELECT 1 FROM users WHERE username=? OR email=?");
      $dup->execute([$req['username'], $req['email']]);
      if ($dup->fetch()) {
        $error = "Cannot approve. Username/email already exists in users.";
      } else {
        // insert as real user
        $ins = $pdo->prepare("INSERT INTO users
          (username, password, role, name, age, email, contact, profile_pic, verified)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $ins->execute([
          $req['username'], $req['password'], $req['role'],
          $req['name'], $req['age'], $req['email'], $req['contact'], $req['profile_pic']
        ]);

        // mark request approved
        $pdo->prepare("UPDATE signup_requests SET status='approved' WHERE id=?")->execute([$reqId]);

        // optional notify user
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
$roles = ['Super admin','admin','monitoring staff','Faculty staff'];
$filter = $_GET['role'] ?? 'admin'; // default list admins
if (!in_array($filter, $roles, true)) $filter = 'admin';

$list = $pdo->prepare("SELECT id, username, name, role, age, email, contact FROM users WHERE role=? ORDER BY id DESC");
$list->execute([$filter]);
$users = $list->fetchAll(PDO::FETCH_ASSOC);

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
  <title>Super Admin Dashboard</title>
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
<?php
$lastAt = $me['last_login_at'] ?? null;
$lastIp = $me['last_login_ip'] ?? null;
if ($lastAt || $lastIp): ?>
  <div class="alert alert-info d-flex align-items-center" role="alert">
    <div>
      <strong>Last login:</strong> <?= sanitize($lastAt ?: '—') ?>
      &middot; <strong>IP:</strong> <?= sanitize($lastIp ?: '—') ?>
    </div>
  </div>
<?php endif; ?>


  <!-- Header -->
  <div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3">
        <img src="<?= sanitize($me['profile_pic'] ?: 'uploads/default.png') ?>" class="avatar" alt="Profile">
        <div>
          <h5 class="mb-0">Super Admin Panel</h5>
          <small class="text-muted">Logged in as: <?= sanitize($me['username']) ?></small>
        </div>
      </div>
      <a class="btn btn-outline-secondary" href="logout.php">Logout</a>
    </div>
  </div>
  

  <?php if ($notice): ?>
    <div class="alert alert-success"><?= sanitize($notice) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= sanitize($error) ?></div>
  <?php endif; ?>

  <!-- Create Admin -->
  <div class="card mb-4">
    <div class="card-body">
      <h6 class="text-muted mb-2">Create Admin Account</h6>
      <form method="POST" class="row g-3">
        <input type="hidden" name="action" value="create_admin">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="col-md-4"><input class="form-control" name="username" placeholder="Username" required></div>
        <div class="col-md-4"><input class="form-control" type="password" name="password" placeholder="Password" required></div>
        <div class="col-md-4"><input class="form-control" name="name" placeholder="Full Name"></div>
        <div class="col-md-2"><input class="form-control" type="number" name="age" placeholder="Age"></div>
        <div class="col-md-5"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
        <div class="col-md-5"><input class="form-control" name="contact" placeholder="Contact"></div>
        <div class="col-12 text-end">
          <button class="btn btn-gradient">Create Admin</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Manage Users -->
  <div class="card">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="text-muted mb-0">Manage Users</h6>
        <form method="GET" class="d-flex gap-2">
          <select class="form-select" name="role" onchange="this.form.submit()">
            <?php foreach ($roles as $r): ?>
              <option value="<?= sanitize($r) ?>" <?= $filter===$r?'selected':'' ?>><?= sanitize(ucfirst($r)) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button class="btn btn-sm btn-primary">Go</button></noscript>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Age</th><th>Email</th><th>Contact</th><th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td><?= sanitize($u['username']) ?></td>
              <td><?= sanitize($u['name']) ?></td>
              <td><?= sanitize($u['role']) ?></td>
              <td><?= (int)$u['age'] ?></td>
              <td><?= sanitize($u['email']) ?></td>
              <td><?= sanitize($u['contact']) ?></td>
              <td class="text-end">
                <!-- Inline edit form toggle -->
                <button class="btn btn-sm btn-outline-primary" type="button" onclick="toggleEdit('row<?= (int)$u['id'] ?>')">Edit</button>

                <?php if ($u['role'] !== 'Super admin' && (int)$u['id'] !== (int)$me['id']): ?>
                  <!-- Delete user -->
                  <form method="POST" class="d-inline"
                        onsubmit="return confirm('Delete user #<?= (int)$u['id'] ?> (<?= sanitize($u['username']) ?>)? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <tr id="row<?= (int)$u['id'] ?>" style="display:none;">
              <td colspan="8">
                <form method="POST" class="row g-2">
                  <input type="hidden" name="action" value="update_user">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <div class="col-md-3"><input class="form-control" name="name" value="<?= sanitize($u['name']) ?>" placeholder="Name"></div>
                  <div class="col-md-2"><input class="form-control" type="number" name="age" value="<?= (int)$u['age'] ?>" placeholder="Age"></div>
                  <div class="col-md-3"><input class="form-control" type="email" name="email" value="<?= sanitize($u['email']) ?>" placeholder="Email"></div>
                  <div class="col-md-2"><input class="form-control" name="contact" value="<?= sanitize($u['contact']) ?>" placeholder="Contact"></div>
                  <div class="col-md-2">
                    <select class="form-select" name="role">
                      <?php foreach ($roles as $r): ?>
                        <option value="<?= sanitize($r) ?>" <?= $u['role']===$r?'selected':'' ?>><?= sanitize($r) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-success">Save</button>
                  </div>
                </form>

                <form method="POST" class="row g-2 mt-2">
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                  <div class="col-md-4"><input class="form-control" type="password" name="new_password" placeholder="New password (min 6)"></div>
                  <div class="col-auto"><button class="btn btn-sm btn-warning">Reset Password</button></div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
  <div class="card mt-4">
  <div class="card-body">
    <h6 class="text-muted mb-2">Pending Signup Requests</h6>

    <?php if (empty($pendingRequests)): ?>
      <p class="mb-0 text-muted">No pending requests.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Name</th><th>Age</th><th>Requested</th><th class="text-end">Actions</th>
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

<script>
function toggleEdit(id){
  const row = document.getElementById(id);
  row.style.display = (row.style.display === 'none' || !row.style.display) ? 'table-row' : 'none';
}
</script>


</body>
</html>
