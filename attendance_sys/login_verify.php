<?php
session_start();
require 'config.php';

if (!isset($_SESSION['pending_login'])) {
    die("No login attempt found. Please login again.");
}

$pending = $_SESSION['pending_login'];

// ✅ If OTP form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $enteredOtp = $_POST['otp'];
    $pending = $_SESSION['pending_login'];

    if (time() > $pending['otp_expires']) {
        $error = "⏳ OTP expired. Please request a new one.";
    } elseif ($enteredOtp == $pending['otp']) {
        $_SESSION['user_id'] = $pending['id'];
        unset($_SESSION['pending_login']);

        // ✅ Kunin role ng user
        require 'db_connect.php';
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id=?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';
    $ins = $pdo->prepare("INSERT INTO login_audit (user_id, role, ip) VALUES (?, ?, ?)");
    $ins->execute([$_SESSION['user_id'], $user['role'], $ip]);
    
        if ($user['role'] === 'admin') {
    header("Location: admin_dashboard.php");
} elseif ($user['role'] === 'Faculty staff') {
    header("Location: faculty_dashboard.php");
} elseif ($user['role'] === 'monitoring staff') {
    header("Location: monitoring_dashboard.php");
    } elseif ($user['role'] === 'Super admin') {
    header("Location: superadmin_dashboard.php");
} else {
    die("Error: Unrecognized user role '".htmlspecialchars($user['role'], ENT_QUOTES)."'");
}

        exit;
    } else {
        $error = "❌ Invalid OTP. Please try again.";
    }
}

// ✅ If Resend OTP clicked
if (isset($_POST['resend'])) {
    $newOtp = rand(100000, 999999);
    $expires = time() + (5 * 60); // extend 5 mins

    $_SESSION['pending_login']['otp'] = $newOtp;
    $_SESSION['pending_login']['otp_expires'] = $expires;

    $subject = "Your New OTP Code";
    $message = "<p>Your new OTP code is: <strong>$newOtp</strong></p><p>This OTP is valid for 5 minutes.</p>";

    if (sendMail($pending['email'], $subject, $message)) {
        $info = "📩 New OTP has been sent to your email (valid for 5 minutes).";
    } else {
        $error = "❌ Failed to resend OTP.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Login OTP</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {background: linear-gradient(135deg,#4e73df,#6f42c1);min-height:100vh;display:flex;justify-content:center;align-items:center;}
.auth-card {background:white;border-radius:1rem;box-shadow:0 8px 20px rgba(0,0,0,0.15);padding:2rem;max-width:400px;width:100%;text-align:center;}
.auth-card h2 {color:#4e73df;font-weight:bold;margin-bottom:1.5rem;}
.btn-gradient {background:linear-gradient(135deg,#4e73df,#6f42c1);border:none;color:white;font-weight:bold;}
.btn-gradient:hover {opacity:0.9;}
.logo {width:80px;height:80px;margin-bottom:1rem;}
</style>
</head>
<body class="container py-5">
  <h2>🔐 Login Verification</h2>
  <p>Enter the OTP sent to <strong><?= htmlspecialchars($pending['email']) ?></strong>.</p>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>
  <?php if (!empty($info)): ?>
    <div class="alert alert-success"><?= $info ?></div>
  <?php endif; ?>

  <form method="POST" class="mb-3">
    <input type="text" name="otp" class="form-control mb-3" placeholder="Enter OTP" required>
    <button type="submit" class="btn btn-success w-100">Verify</button>
  </form>

  <form method="POST">
    <input type="hidden" name="resend" value="1">
    <button type="submit" class="btn btn-secondary w-100">🔄 Resend OTP</button>
  </form>
</body>
</html>
