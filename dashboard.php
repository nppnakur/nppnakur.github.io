<?php
session_start(); // सत्र शुरू करें या फिर से शुरू करें
ini_set('display_errors', 1); // त्रुटि प्रदर्शन सक्षम करें
error_reporting(E_ALL); // सभी त्रुटियों की रिपोर्ट करें

// OTP वेरिफिकेशन चेक
if (!isset($_SESSION['username']) || !isset($_SESSION['otp_verified'])) { // अगर यूजरनेम या OTP वेरिफाइड नहीं है
    header("Location: index.php"); // index.php पर रीडायरेक्ट करें
    exit(); // स्क्रिप्ट बंद करें
}

// ** ऑटोमैटिक लॉगआउट के लिए नया कोड जोड़ा गया **

$timeout_duration = 1800; // 30 मिनट को सेकंड में (30 * 60 = 1800)

// जांचें कि 'last_activity' सत्र वेरिएबल मौजूद है और क्या निष्क्रियता की अवधि समाप्त हो गई है
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) { // अगर आखिरी गतिविधि का समय और वर्तमान समय का अंतर टाइमआउट से ज़्यादा है
    // यदि पिछली गतिविधि टाइमआउट अवधि से अधिक थी, तो सत्र को नष्ट करें
    session_unset();     // सभी सत्र वेरिएबल्स को अनसेट करें
    session_destroy();   // सत्र को नष्ट करें
    header("Location: index.php"); // आपको लॉगिन पेज पर रीडायरेक्ट करें (या जहाँ भी आप लॉगआउट के बाद भेजना चाहते हैं)
    exit(); // स्क्रिप्ट बंद करें
}

// प्रत्येक पेज लोड पर पिछली गतिविधि के समय को वर्तमान समय में अपडेट करें
$_SESSION['last_activity'] = time(); // वर्तमान समय को 'last_activity' में स्टोर करें

// ** ऑटोमैटिक लॉगआउट कोड का अंत **


$base_url = "https://sunnydhaka.fwh.is/"; // बेस URL सेट करें
?>

<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <title>WATER MANAGEMENT SYSTEM</title>
  <link rel="icon" href="https://sunnydhaka.fwh.is/img1.png" type="image/png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', sans-serif;
    }
    
    body {
      background-color: #f0f8ff;
      overflow-x: hidden;
      position: relative;
      min-height: 100vh;
    }
    
    /* Background banner style */
    .background-banner {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('nakur.png');
      background-size: cover;
      background-position: center;
      opacity: 0.25;
      z-index: -1;
      filter: brightness(0.8);
    }
    
    /* Tab style */
    .water-tab {
      background: linear-gradient(90deg, #0066cc, #00a8ff);
      color: white;
      padding: 18px 10px;
      font-size: clamp(18px, 3.5vw, 26px);
      font-weight: bold;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: relative;
      overflow: hidden;
    }
    
    /* Card container */
    .card-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 20px;
      max-width: 900px;
      margin: 25px auto;
      padding: 0 20px;
    }
    
    /* Card style */
    .card {
      background: rgba(255,255,255,0.95);
      border-radius: 12px;
      padding: 20px 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      text-align: center;
      transition: all 0.3s ease;
      cursor: pointer;
      height: 190px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      align-items: center;
      border: 1px solid rgba(0,0,0,0.1);
      position: relative;
      overflow: hidden;
    }
    
    /* Card content */
    .card-content {
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      flex-grow: 1;
    }
    
    /* Icon container at bottom - larger size */
    .card-icon-container {
      margin-top: auto;
      padding-top: 12px;
      width: 100%;
      border-top: 1px dashed rgba(0,0,0,0.15);
    }
    
    .card-icon {
      font-size: 48px; /* Increased icon size */
      transition: all 0.3s;
      display: inline-block;
    }
    
    .card h2 {
      font-size: 18px;
      margin-bottom: 6px;
      color: #333;
    }
    
    .card p {
      font-size: 13px;
      color: #666;
    }
    
    /* Hover animations */
    .card:hover {
      transform: translateY(-6px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    }
    
    /* Different animations for each card */
    .bill-card:hover .card-icon {
      animation: waterDrop 1.2s ease infinite;
      color: #0066cc;
    }
    
    .payment-card:hover .card-icon {
      animation: pulse 0.9s ease infinite;
      color: #00aa00;
    }
    
    .arrears-card:hover .card-icon {
      animation: shake 0.6s ease infinite;
      color: #cc3300;
    }
    
    .total-card:hover .card-icon {
      animation: bounce 0.7s ease infinite;
      color: #ff9900;
    }
    
    .electric-card:hover .card-icon {
      animation: flash 0.8s ease infinite;
      color: #ffcc00;
    }
    
    .shop-card:hover .card-icon {
      animation: spin 1.2s linear infinite;
      color: #6600cc;
    }
    
    .dhaka-ai-card:hover .card-icon {
      animation: aiGlow 1.5s ease infinite;
      color: #ff3366;
    }
    
    .id-card:hover .card-icon {
      animation: swing 1.1s ease infinite;
      color: #555;
    }
    
    /* Animation keyframes */
    @keyframes waterDrop {
      0%, 100% { transform: translateY(0) scale(1); }
      50% { transform: translateY(-6px) scale(1.1); }
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.2); }
    }
    
    @keyframes shake {
      0%, 100% { transform: rotate(0deg); }
      25% { transform: rotate(-8deg); }
      75% { transform: rotate(8deg); }
    }
    
    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    
    @keyframes flash {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.6; }
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    @keyframes aiGlow {
      0%, 100% { 
        transform: scale(1); 
        text-shadow: 0 0 5px rgba(255, 51, 102, 0.5);
      }
      50% { 
        transform: scale(1.15); 
        text-shadow: 0 0 15px rgba(255, 51, 102, 0.8), 0 0 25px rgba(255, 51, 102, 0.4);
      }
    }
    
    @keyframes swing {
      0%, 100% { transform: rotate(0deg); }
      25% { transform: rotate(8deg); }
      75% { transform: rotate(-8deg); }
    }
    
    /* Logout button */
    .logout-container {
      display: flex;
      justify-content: center;
      margin: 25px 0;
      padding: 0 20px;
    }
    
    .logout-btn {
      display: inline-block;
      padding: 12px 28px;
      background: linear-gradient(to right, #ff5e62, #ff2400);
      color: white;
      text-decoration: none;
      border-radius: 50px;
      font-weight: bold;
      font-size: 15px;
      box-shadow: 0 4px 10px rgba(255, 94, 98, 0.3);
      transition: all 0.3s;
    }
    
    .logout-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 14px rgba(255, 94, 98, 0.4);
    }
    
    /* Mobile responsiveness */
    @media (max-width: 600px) {
      .card-container {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
      }
      
      .card {
        height: 170px;
        padding: 18px 12px;
      }
      
      .card-icon {
        font-size: 42px; /* Slightly smaller on mobile */
      }
    }
  </style>
</head>
<body>

  <div class="background-banner"></div>

  <div class="water-tab">
    WELCOME TO NPP NAKUR SERVICE
  </div>
  
  <div class="card-container">
    <div class="card bill-card" onclick="window.location.href='<?php echo $base_url; ?>jal.php';">
      <div class="card-content">
        <h2>WATER</h2>
        <p>MANAGEMENT</p>
      </div>
      <div class="card-icon-container">
        <div class="card-icon">🚰</div>
      </div>
    </div>
    
    <div class="card payment-card" onclick="window.location.href='<?php echo $base_url; ?>house_tax_dashboard.php';">
      <div class="card-content">
        <h2>HOUSE TAX</h2>
        <p>MANAGEMENT</p>
      </div>
      <div class="card-icon-container">
        <div class="card-icon">🏠</div>
      </div>
    </div>
    
    <div class="card arrears-card" onclick="window.location.href='<?php echo $base_url; ?>establishment.php';">
      <div class="card-content">
        <h2>ESTABLISHMENT</h2>
        <p>DEPARTMENT</p>
      </div>
      <div class="card-icon-container">
        <div class="card-icon">⚠️</div>
      </div>
    </div>
    
    <div class="card total-card" onclick="window.location.href='<?php echo $base_url; ?>other_tax.php';">
      <div class="card-content">
        <h2>OTHER</h2>
        <p>TAXES</p>
      </div>
      <div class="card-icon-container">
        <div class="card-icon">💰</div>
      </div>
    </div>
    
    <div class="card electric-card" onclick="window.location.href='<?php echo $base_url; ?>electricity.php';">
      <div class="card-content">
        <h2>ELECTRICITY</h2>
        <p>DEPARTMENT</p>
      </div>
      <div class="card-icon-container">
        <div class="card-icon">💡</div>
      </div>
    </div>
    
    <div class="card shop-card" onclick="window.location.href='<?php echo $base_url; ?>shop_tax.php';">
      <div class="card-content">
        <h2>SHOP</h2>
        <p>TAX</p>
      </div>
      <div class="card-icon-container">
        <div class="card-icon">🏬</div>
      </div>
    </div>
    
    <div class="card dhaka-ai-card" onclick="window.location.href='<?php echo $base_url; ?>dhaka_ai.php';">
      <div class="card-content">
        <h2>DHAKA-AI</h2>
        <p>ARTIFICIAL INTELLIGENCE</p>
      </div>
      <div class="card-icon-container">
        <div class="card-icon">🤖</div>
      </div>
    </div>
    
    <div class="card id-card" onclick="window.location.href='<?php echo $base_url; ?>idcard_desbord.php';">
     <div class="card-content">
        <h2>ID CARD</h2>
        <p>MANAGEMENT</p>
      </div>
      <div class="card-icon-container">
        <div class="card-icon">🪪</div>
      </div>
    </div>
  </div>
  
  <div class="logout-container">
    <a href="logout.php" class="logout-btn">
      <span style="margin-right: 8px;">🚪</span> लॉगआउट
    </a>
  </div>

</body>
</html>