<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <title>NAGAR PALIKA NAKUR</title>
  <link rel="icon" href="img1.png" type="image/png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      /* बैकग्राउंड इमेज */
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
      background-color: rgba(0, 0, 0, 0.6); /* थोडा गहरा किया ताकि फॉर्म साफ़ दिखे */
      border-radius: 35px;
      padding: 20px;
      display: flex;
      flex-direction: row;
      width: 90%;
      max-width: 1000px;
      box-shadow: 0 8px 10px rgba(0, 0, 0, 0.5);
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
      0% { transform: rotateY(0deg); }
      100% { transform: rotateY(360deg); }
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
      width: 90%;
      padding: 12px;
      font-size: 15px;
      background-color: rgba(255, 255, 255, 0.1);
      color: white;
      border: 1px solid #ffcc00;
      border-radius: 23px;
    }

    .form-group button {
      width: 100%;
      padding: 12px;
      font-size: 15px;
      font-weight: bold;
      background-color: #ffcc00;
      color: #1a1818;
      border: none;
      border-radius: 23px;
      cursor: pointer;
      transition: 0.3s;
    }

    .form-group button:hover {
      background-color: #fff;
      transform: scale(1.05);
    }

    .powered-by {
      position: absolute;
      bottom: 10px;
      right: 20px;
      font-size: 16px;
      color: #f2f2f1;
    }

    @media screen and (max-width: 768px) {
      .container { flex-direction: column; text-align: center; }
      .rotating-logo { width: 150px; }
      h2 { font-size: 24px; }
      .form-section h3 { font-size: 22px; }
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
    
    <form id="loginForm">
      <div class="form-group">
        <label for="username"><b>USER NAME</b></label>
        <input type="text" id="username" placeholder="ENTER YOUR USER ID" required>
      </div>
      <div class="form-group">
        <label for="password"><b>PASSWORD</b></label>
        <input type="password" id="password" placeholder="ENTER YOUR PASSWORD" required>
      </div>
      <div class="form-group">
        <button type="button" onclick="checkLogin()">LOG IN →</button>
      </div>
    </form>
    <p id="msg" style="text-align: center; color: red;"></p>
  </div>

  <div class="powered-by">POWERED-BY ➤ SUNNY DHAKA</div>
</div>

<script>
  function checkLogin() {
    const user = document.getElementById('username').value;
    const pass = document.getElementById('password').value;
    const msg = document.getElementById('msg');

    // डेमो लॉगिन चेक (चूँकि PHP यहाँ काम नहीं करेगा)
    if(user === "Sunnydhaka" && pass === "Sunny1990") {
      alert("लॉगिन सफल! (GitHub पर सिर्फ HTML काम करता है, डेटाबेस नहीं)");
      // यहाँ आप किसी और पेज पर भेज सकते हैं
      // window.location.href = "dashboard.html"; 
    } else {
      msg.innerHTML = "❌ गलत यूजरनेम या पासवर्ड!";
    }
  }
</script>

</body>
</html>
