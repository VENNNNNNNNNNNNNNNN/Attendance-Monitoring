<?php
// manage_users.php
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
$stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

// Only Admins may access this page
if (!$me || $me['role'] !== 'admin') {
  die("Access denied. Only Admin can view this page.");
}

/* ---- CSRF token ---- */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ---- Handle POST: update user ---- */
$notice = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF check
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $error = "Invalid request token.";
  }

  if (!$error && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $uid      = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $age      = (int)($_POST['age'] ?? 0);
    $email    = trim($_POST['email'] ?? '');
    $contact  = trim($_POST['contact'] ?? '');
    $role     = trim($_POST['role'] ?? '');         // only Faculty staff / monitoring staff
    $newpass  = trim($_POST['new_password'] ?? ''); // optional
    $allowed_roles = ['Faculty staff','monitoring staff'];

    // Fetch target and ensure editable role
    $cur = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $cur->execute([$uid]);
    $target = $cur->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
      $error = "User not found.";
    } elseif (!in_array($target['role'], $allowed_roles, true)) {
      $error = "You can only edit Faculty staff or monitoring staff.";
    } elseif (!in_array($role, $allowed_roles, true)) {
      $error = "Invalid role. Allowed: Faculty staff / monitoring staff.";
    }

    // Uniqueness checks for username/email (excluding this user)
    if (!$error) {
      $chk = $pdo->prepare("SELECT 1 FROM users WHERE (username=? OR email=?) AND id<>?");
      $chk->execute([$username, $email, $uid]);
      if ($chk->fetch()) {
        $error = "Username or Email already taken.";
      }
    }

    // Handle profile picture (optional)
    $profilePic = $target['profile_pic'];
    if (!$error && isset($_FILES['profile_pic']) && !empty($_FILES['profile_pic']['name'])) {
      $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
      $allowed_ext = ['jpg','jpeg','png','gif','webp'];
      if (!in_array($ext, $allowed_ext, true)) {
        $error = "Invalid image type. Allowed: jpg, jpeg, png, gif, webp.";
      } else {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fname = "u{$uid}_".time().".".$ext;
        $dest = $targetDir.$fname;
        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest)) {
          $error = "Failed to upload image.";
        } else {
          $profilePic = $dest;
        }
      }
    }

    if (!$error) {
      // Build update
      $params = [$username, $name, $age, $email, $contact, $role, $profilePic, $uid];
      $sql = "UPDATE users SET username=?, name=?, age=?, email=?, contact=?, role=?, profile_pic=?";

      if ($newpass !== '') {
        if (strlen($newpass) < 6) {
          $error = "Password must be at least 6 characters.";
        } else {
          $hash = password_hash($newpass, PASSWORD_BCRYPT);
          $sql .= ", password=?";
          $params = [$username, $name, $age, $email, $contact, $role, $profilePic, $hash, $uid];
        }
      }
    }

    if (!$error) {
      $sql .= " WHERE id=?";
      $upd = $pdo->prepare($sql);
      $upd->execute($params);
      $notice = "User #$uid updated.";
    }
  }
}

/* ---- Load users (Faculty staff & monitoring staff only) ---- */
$list = $pdo->query("
  SELECT id, username, name, role, age, email, contact, profile_pic
  FROM users
  WHERE role IN ('Faculty staff','monitoring staff')
  ORDER BY id DESC
");
$users = $list->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg,#4e73df,#6f42c1); min-height:100vh; padding: 2rem 1rem; }
    .card { border-radius: 1rem; box-shadow: 0 8px 20px rgba(0,0,0,.15); }
    .btn-gradient { background: linear-gradient(135deg,#4e73df,#6f42c1); border:none; color:#fff; font-weight:600; }
    .btn-gradient:hover { opacity:.9; }
    .avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; }
  </style>
</head>
<body>
<div class="container" style="max-width: 1100px;">

  <!-- Header -->
  <div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between">
      <div>
        <h5 class="mb-0">Manage Users</h5>
        <small class="text-muted">Faculty staff & Monitoring staff</small>
      </div>
      <div class="d-flex gap-2">
        <a href="admin_dashboard.php" class="btn btn-outline-light">⬅ Back to Dashboard</a>
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

  <!-- Users table -->
  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Avatar</th>
              <th>Username</th>
              <th>Name</th>
              <th>Role</th>
              <th>Age</th>
              <th>Email</th>
              <th>Contact</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td><img src="<?= sanitize($u['profile_pic'] ?: 'uploads/default.png') ?>" class="avatar" alt="avatar"></td>
              <td><?= sanitize($u['username']) ?></td>
              <td><?= sanitize($u['name']) ?></td>
              <td><?= sanitize($u['role']) ?></td>
              <td><?= (int)$u['age'] ?></td>
              <td><?= sanitize($u['email']) ?></td>
              <td><?= sanitize($u['contact']) ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" type="button" onclick="toggleEdit('row<?= (int)$u['id'] ?>')">Edit</button>
              </td>
            </tr>
            <tr id="row<?= (int)$u['id'] ?>" style="display:none;">
              <td colspan="9">
                <form method="POST" class="row g-2" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="update_user">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                  <div class="col-md-3">
                    <label class="form-label">Username</label>
                    <input class="form-control" name="username" value="<?= sanitize($u['username']) ?>" required>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" value="<?= sanitize($u['name']) ?>" required>
                  </div>

                  <div class="col-md-2">
                    <label class="form-label">Age</label>
                    <input class="form-control" type="number" name="age" value="<?= (int)$u['age'] ?>">
                  </div>

                  <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" value="<?= sanitize($u['email']) ?>" required>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Contact</label>
                    <input class="form-control" name="contact" value="<?= sanitize($u['contact']) ?>">
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                      <option value="Faculty staff"   <?= $u['role']==='Faculty staff'?'selected':'' ?>>Faculty staff</option>
                      <option value="monitoring staff" <?= $u['role']==='monitoring staff'?'selected':'' ?>>Monitoring staff</option>
                    </select>
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">New Password (optional)</label>
                    <input class="form-control" type="password" name="new_password" placeholder="Leave blank to keep">
                  </div>

                  <div class="col-md-3">
                    <label class="form-label">Profile Picture (optional)</label>
                    <input class="form-control" type="file" name="profile_pic" accept="image/*">
                  </div>

                  <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-sm btn-gradient">Save Changes</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="toggleEdit('row<?= (int)$u['id'] ?>')">Cancel</button>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
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
