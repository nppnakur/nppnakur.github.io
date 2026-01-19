<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// If already logged in, redirect to dashboard
if (isset($_SESSION["username"]) && $_SESSION["otp_verified"] && $_SESSION["username"] === "Sunnydhaka") {
    header("Location: dashboard.php");
    exit;
}

// Login processing
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["admin_login"])) {
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    if ($username === "Sunnydhaka" && $password === "Sunny1990") {
        // Generate OTP (6 digits)
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = time() + 300; // Valid for 5 minutes
        $_SESSION['temp_username'] = $username;
        
        // For testing - remove in production
        $_SESSION['debug_otp'] = $otp;
        
        // Try to send email (works on InfinityFree with their mail server)
        $to = "sunnydhaka91@gmail.com";
        $subject = "NAGAR PALIKA NAKUR - OTP Verification";
        $message = "Your OTP is: $otp\n\nThis OTP is valid for 5 minutes.";
        $headers = "From: noreply@yourdomain.com\r\n";
        
        if(@mail($to, $subject, $message, $headers)) {
            header("Location: otp_verify.php");
            exit;
        } else {
            // Even if mail fails, proceed to OTP page (for testing)
            header("Location: otp_verify.php");
            exit;
        }
    } else {
        $_SESSION["error"] = "❌ Invalid username or password!";
        header("Location: index.php");
        exit;
    }
}

$error = $_SESSION["error"] ?? '';
unset($_SESSION["error"]);
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <title>NAGAR PALIKA NAKUR</title>
  <link rel="icon" href="https://sunnydhaka.fwh.is/img1.png" type="image/png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="HandheldFriendly" content="true">
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      background-image: url('https://ptaxsnn.com/Login/images/banner.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      color: #fff;
    }
    h2 {
      position: absolute;
      top: 8px;
      width: 100%;
      text-align: center;
      font-size: 40px;
      color: #fff;
      animation: glowing 1.5s infinite alternate;
      z-index: 1;
    }
    @keyframes glowing {
      0% { text-shadow: 0 0 5px #ffcc00, 0 0 10px #ffcc00; }
      50% { text-shadow: 0 0 15px #ffcc00, 0 0 25px #ffcc00, 0 0 35px #ffcc00; }
      100% { text-shadow: 0 0 20px #ffcc00, 0 0 30px #ffcc00, 0 0 40px #ffcc00; }
    }
    .container {
      background-color: rgba(0, 0, 0, 0.19);
      border-radius: 35px;
      padding: 10px;
      display: flex;
      flex-direction: row;
      width: 90%;
      max-width: 1000px;
      box-shadow: 0 8px 10px rgba(0, 0, 0, 0.2);
      position: relative;
    }
    .logo-section {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 30px;
    }
    .rotating-logo {
      width: 300px;
      animation: flipUpright 5s ease infinite;
      transform-style: preserve-3d;
    }
    @keyframes flipUpright {
      0% { transform: rotateY(0deg) scaleX(1); }
      100% { transform: rotateY(180deg) scaleX(-1); }
    }
    .form-section {
      flex: 1;
      padding: 20px;
    }
    .form-section h3 {
      font-size: 28px;
      text-align: center;
      color: #ffcc00;
      margin-bottom: 20px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      font-size: 16px;
      margin-bottom: 10px;
      display: block;
    }
    .form-group input {
      width: 94%;
      padding: 12px;
      font-size: 15px;
      background-color: #0000003d;
      color: white;
      border: none;
      border-radius: 23px;
    }
    .form-group button {
      width: 100%;
      padding: 12px;
      font-size: 15px;
      font-weight: bold;
      background-color: #fbf2f2cf;
      color: #1a1818;
      border: none;
      border-radius: 23px;
      cursor: pointer;
      animation: waveButton 2s infinite alternate ease-in-out;
    }
    .form-group button:hover {
      background-color: #e6b800;
    }
    @keyframes waveButton {
      0% { transform: translateY(0); }
      30% { transform: translateY(-3px); }
      50% { transform: translateY(0); }
      75% { transform: translateY(3px); }
      100% { transform: translateY(0); }
    }
    .powered-by {
      position: absolute;
      bottom: 0px;
      right: 20px;
      font-size: 16px;
      color: #f2f2f1;
      white-space: nowrap;
      overflow: hidden;
      width: 0;
      animation: typing 3s steps(28, end) forwards, floatUpDown 2s ease-in-out infinite;
    }
    @keyframes typing {
      from { width: 0 }
      to { width: 260px }
    }
    @keyframes floatUpDown {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-5px); }
    }
    .error-message {
      color: #ff6b6b;
      text-align: center;
      margin-bottom: 15px;
      font-weight: bold;
    }
    @media screen and (max-width: 768px) {
      .container { flex-direction: column; align-items: center; }
      .rotating-logo { width: 180px; }
      h2 { font-size: 30px; top: 10px; }
      .form-section h3 { font-size: 22px; }
      .form-group input { font-size: 16px; }
      .form-group button { font-size: 16px; }
      .powered-by { font-size: 14px; }
    }
    @media screen and (max-width: 480px) {
      h2 { font-size: 24px; top: 6px; }
      .form-section h3 { font-size: 20px; }
      .form-group button { font-size: 14px; }
    }
  </style>
</head>
<body>

<h2>WELCOME TO NAGAR PALIKA NAKUR</h2>

<div class="container">
  <div class="logo-section">
    <img src="img1.png" alt="Logo" class="rotating-logo">
  </div>

  <div class="form-section">
    <h3>ADMIN LOGIN</h3>
    <?php if (!empty($error)): ?>
      <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="form-group">
        <label for="username"><b>USER NAME</b></label>
        <input type="text" name="username" id="username" placeholder="ENTER YOUR USER ID" required>
      </div>
      <div class="form-group">
        <label for="password"><b>PASSWORD</b></label>
        <input type="password" name="password" id="password" placeholder="ENTER YOUR PASSWORD" required>
      </div>
      <div class="form-group">
        <button type="submit" name="admin_login">LOG IN →</button>
      </div>
    </form>
  </div>

  <div class="powered-by">POWERED-BY ➤ SUNNY DHAKA</div>
</div>

</body>
</html>