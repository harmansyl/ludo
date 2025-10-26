<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../lib/boot.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = urlencode($_POST['name']);
    $email = urlencode($_POST['email']);
    $message = urlencode($_POST['message']);

    // ✅ Redirect to WhatsApp with filled message
    $whatsapp_url = "https://wa.me/918288970850?text=📩%20New%20Contact%20Query%0A👤%20Name:%20{$name}%0A📧%20Email:%20{$email}%0A💬%20Message:%20{$message}";
    header("Location: $whatsapp_url");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Us - Quantum Ludo</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 0;
      background: url('../img/bg.jpg') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }
    .container {
      background: #111; /* solid dark background */
      padding: 30px;
      border-radius: 15px;
      width: 400px;
      box-shadow: 0 0 15px rgba(255,215,0,0.6);
      text-align: center;
    }
    h1 {
      margin-bottom: 20px;
      color: #FFD700;
      font-size: 26px;
      text-shadow: 0 0 8px #FFD700;
    }
    input, textarea {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border: none;
      border-radius: 8px;
      background: #222;
      color: #fff;
      font-size: 16px;
      outline: none;
    }
    input:focus, textarea:focus {
      border: 1px solid #FFD700;
      box-shadow: 0 0 8px #FFD700;
    }
    textarea { resize: none; }
    button {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 8px;
      background: #FFD700;
      color: #000;
      font-weight: bold;
      font-size: 16px;
      cursor: pointer;
      transition: 0.3s;
    }
    button:hover {
      background: #e6c200;
      box-shadow: 0 0 12px #FFD700;
    }
    .back-link {
      display: inline-block;
      margin-top: 15px;
      color: #FFD700;
      text-decoration: none;
      font-weight: bold;
    }
    .back-link:hover {
      text-shadow: 0 0 10px #FFD700;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>📩 Contact Us</h1>
    <form method="POST">
      <input type="text" name="name" placeholder="Your Name" required>
      <input type="email" name="email" placeholder="Your Email" required>
      <textarea name="message" rows="5" placeholder="Your Message" required></textarea>
      <button type="submit">Send via WhatsApp</button>
    </form>
    <a href="../dashboard.php" class="back-link">← Back to Dashboard</a>
  </div>
</body>
</html>
