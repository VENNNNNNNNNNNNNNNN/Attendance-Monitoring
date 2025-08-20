<?php
session_start();
require 'config.php';
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Hanapin user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Generate OTP
       $otp = rand(100000, 999999);
$expires = time() + (5 * 60);

$_SESSION['pending_login'] = [
    'id' => $user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'otp' => $otp,
    'otp_expires' => $expires
];


        // Send OTP to email
        $subject = "Your Login OTP Code";
        $message = "<p>Your OTP code is: <strong>$otp</strong></p>";

        if (sendMail($user['email'], $subject, $message)) {
            header("Location: login_verify.php");
            exit;
        } else {
            echo "❌ Failed to send OTP. Please try again.";
        }

    } else {
        echo "❌ Invalid username or password.";
    }
}
?>
