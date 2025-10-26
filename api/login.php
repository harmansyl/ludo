<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../lib/boot.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

$pdo = db();
$stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    if ($user['role'] === 'admin') {
        header("Location: ../public/admin_dashboard.php");
    } else {
    // ✅ redirect to dashboard in public folder
    header("Location: ../public/dashboard.php");
    }
    exit;
} else {
    echo "<script>alert('Invalid login!'); window.location.href='../public/index.php';</script>";
    exit;
}
