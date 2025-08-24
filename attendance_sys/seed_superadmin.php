<?php
// seed_superadmin.php
require 'db_connect.php';

$username = "Super Admin";
$passwordPlain = "superadmin";
$email = "superadmin@example.com";

try {
    // Check if Super admin already exists
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='Super admin'");
    $check->execute();
    if ($check->fetchColumn() > 0) {
        echo "❌ Super admin account already exists.\n";
        exit;
    }

    $hash = password_hash($passwordPlain, PASSWORD_BCRYPT);

    $ins = $pdo->prepare("INSERT INTO users (username, password, role, name, age, email, contact, profile_pic, verified)
                          VALUES (?, ?, 'Super admin', 'Super Admin', 0, ?, NULL, 'uploads/default.png', 1)");
    $ins->execute([$username, $hash, $email]);

    echo "✅ Super admin account created.\n";
    echo "   Username: {$username}\n";
    echo "   Password: {$passwordPlain}\n";
    echo "   Email: {$email}\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
