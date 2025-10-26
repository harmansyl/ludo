<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../lib/boot.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$username = trim($_POST['username'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$password = trim($_POST['password'] ?? '');

// Validation
if ($username === '' || $phone === '' || $password === '') {
    echo "<script>alert('All fields are required!'); window.history.back();</script>";
    exit;
}

$pdo = db();

// Check if username exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    echo "<script>alert('Username already exists!'); window.history.back();</script>";
    exit;
}

// Hash password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Insert into DB
$stmt = $pdo->prepare("INSERT INTO users (username, phone, password_hash) VALUES (?, ?, ?)");
$stmt->execute([$username, $phone, $passwordHash]);

// Auto login
$_SESSION['user_id']  = $pdo->lastInsertId();
$_SESSION['username'] = $username;

// Redirect to dashboard
header("Location: ../public/dashboard.php");
exit;
