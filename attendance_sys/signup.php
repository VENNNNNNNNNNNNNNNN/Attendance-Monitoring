<?php
session_start();
require 'config.php';

// kapag form na-submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    $name = $_POST['name'];
    $age = $_POST['age'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];

    // Profile picture upload
    $profilePic = "uploads/default.png";
    if (!empty($_FILES['profile_pic']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $profilePic = $targetDir . basename($_FILES['profile_pic']['name']);
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $profilePic);
    }

    // Check kung may existing username/email
    require 'db_connect.php'; // dito naka $pdo connection
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=?");
    $stmt->execute([$username, $email]);
    if ($stmt->rowCount() > 0) {
        die("❌ Username or Email already taken.");
    }

    // Generate OTP
$otp = rand(100000, 999999);
$expires = time() + (5 * 60); // valid for 5 minutes

// Save sa session (temp storage before verification)
$_SESSION['pending_user'] = [
    'username' => $username,
    'password' => $password,
    'role' => $role,
    'name' => $name,
    'age' => $age,
    'email' => $email,
    'contact' => $contact,
    'profile_pic' => $profilePic,
    'otp' => $otp,
    'otp_expires' => $expires
];

    // Send OTP to email
    $subject = "Your OTP Code";
    $message = "<p>Your OTP code is: <strong>$otp</strong></p>";

    if (sendMail($email, $subject, $message)) {
        header("Location: verify.php");
        exit;
    } else {
        echo "❌ Failed to send OTP. Please try again.";
    }
}
?>

