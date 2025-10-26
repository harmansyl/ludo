<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../lib/boot.php';

// If already logged in → redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Quantum Ludo - Login</title>
  <style>
  body {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    background: url("img/bg.jpg") no-repeat center center fixed;
    background-size: cover;
    
    /* Centering */
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;   /* full height */
  }

  .login-box {
    width: 300px;
    background: rgba(0,0,0,0.7);
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.5);
  }

  .login-box h1 {
    color: #FFD700;
    margin-bottom: 20px;
  }

  .login-box input {
    width: 90%;
    padding: 10px;
    margin: 10px 0;
    border: none;
    border-radius: 5px;
  }

  .btn {
    width: 95%;
    padding: 10px;
    background: #FFD700;
    border: none;
    border-radius: 20px;
    font-weight: bold;
    cursor: pointer;
  }

  .btn:hover {
    background: #FFEA00;
  }

  .register-link {
    margin-top: 10px;
    color: #fff;
  }

  .register-link a {
    background: #FFD700;
    padding: 5px 10px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: bold;
    color: black;
  }
</style>

</head>
<body>
  <div class="login-box">
    <h1>QUANTUM LUDO</h1>
    <form action="../api/login.php" method="post">
      <input type="text" name="username" placeholder="Username" required><br>
      <input type="password" name="password" placeholder="Password" required><br>
      <button type="submit" class="btn">LOGIN</button>
    </form>
    <div class="register-link">
      New user? <a href="register.php">REGISTER NOW</a>
    </div>
  </div>
</body>
</html>
