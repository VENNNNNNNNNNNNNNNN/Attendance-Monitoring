<?php
session_start();
require 'config.php';
require 'db_connect.php';

if (!isset($_SESSION['pending_user'])) {
    die("No signup data found. Please register again.");
}

$pending = $_SESSION['pending_user'];

// ✅ If OTP form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $enteredOtp = $_POST['otp'];
    $pending = $_SESSION['pending_user'];

    if (time() > $pending['otp_expires']) {
        $error = "⏳ OTP expired. Please request a new one.";
    } elseif ($enteredOtp == $pending['otp']) {
// Instead of creating the user directly, create a signup request
require 'db_connect.php';

// Prevent duplicates against existing users (safety)
$dup = $pdo->prepare("SELECT 1 FROM users WHERE username=? OR email=?");
$dup->execute([$pending['username'], $pending['email']]);
if ($dup->fetch()) {
  unset($_SESSION['pending_user']);
  die("❌ Username or Email already exists.");
}

// Insert request
$ins = $pdo->prepare("INSERT INTO signup_requests
  (username, password, role, name, age, email, contact, profile_pic, status)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
$ins->execute([
  $pending['username'],
  $pending['password'],
  $pending['role'],
  $pending['name'],
  $pending['age'],
  $pending['email'],
  $pending['contact'],
  $pending['profile_pic']
]);

// Notify Admin/Super Admin by email with simple heads-up (no public approve link)
$subject = "New Signup Request (".$pending['role'].")";
$message = "
  <p>A new signup request is pending approval.</p>
  <ul>
    <li>Username: ".htmlspecialchars($pending['username'])."</li>
    <li>Email: ".htmlspecialchars($pending['email'])."</li>
    <li>Role: ".htmlspecialchars($pending['role'])."</li>
  </ul>
  <p>Please review it in the dashboard.</p>
";
@sendMail('attendancemonitoringbot@gmail.com', $subject, $message);

unset($_SESSION['pending_user']);
echo "✅ Your signup request has been submitted. Please wait for Admin/Super Admin approval.";
exit;

    } else {
        $error = "❌ Invalid OTP. Please try again.";
    }
}


// ✅ If Resend OTP clicked
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $newOtp = rand(100000, 999999);
    $_SESSION['pending_user']['otp'] = $newOtp;

    $subject = "Your New OTP Code";
    $message = "<p>Your new OTP code is: <strong>$newOtp</strong></p>";

    if (sendMail($pending['email'], $subject, $message)) {
        $info = "📩 New OTP has been sent to your email.";
    } else {
        $error = "❌ Failed to resend OTP.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify OTP</title>
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
  <h2>🔐 Verify Your Email</h2>
  <p>Enter the OTP sent to <strong><?= htmlspecialchars($pending['email']) ?></strong>.</p>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>
  <?php if (!empty($info)): ?>
    <div class="alert alert-success"><?= $info ?></div>
  <?php endif; ?>

  <form method="POST" class="mb-3">
    <input type="text" name="otp" class="form-control mb-3" placeholder="Enter OTP" required>
    <button type="submit" class="btn btn-primary w-100">Verify</button>
  </form>

  <form method="POST">
    <input type="hidden" name="resend" value="1">
    <button type="submit" class="btn btn-secondary w-100">🔄 Resend OTP</button>
  </form>
</body>
</html>
