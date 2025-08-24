<?php
session_start();
require 'config.php';
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Find user by username or email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        /* -------------------------------
           SUPER ADMIN: bypass OTP + notify
           ------------------------------- */
        if ($user['role'] === 'Super admin') {
            $_SESSION['user_id'] = $user['id'];

            // capture IP + time
            $ip  = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';
            $now = date('Y-m-d H:i:s');

            // save to DB
            $upd = $pdo->prepare("UPDATE users SET last_login_at=?, last_login_ip=? WHERE id=?");
            $upd->execute([$now, $ip, $user['id']]);

                $ins = $pdo->prepare("INSERT INTO login_audit (user_id, role, ip) VALUES (?, ?, ?)");
                $ins->execute([$user['id'], $user['role'], $ip]);

            // notify mailbox
            $to = 'attendancemonitoringbot@gmail.com';
            $subject = 'Super Admin Login Notification';
            $message = "
              <p><strong>Super Admin</strong> has logged in.</p>
              <ul>
                <li>Username: {$user['username']}</li>
                <li>Email: {$user['email']}</li>
                <li>Time: {$now}</li>
                <li>IP: {$ip}</li>
              </ul>";

            // fire-and-forget; proceed even if email fails
            @sendMail($to, $subject, $message);

            header("Location: superadmin_dashboard.php");
            exit;
        }

        /* -------------------------------
           OTHER ROLES: normal OTP flow
           ------------------------------- */
        $otp = rand(100000, 999999);
        $expires = time() + (5 * 60);

        $_SESSION['pending_login'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'otp' => $otp,
            'otp_expires' => $expires
        ];

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
