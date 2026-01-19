<?php
session_start();

// PHPMailer classes को जोड़ें
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// =========================================================
// 🛑 1. PHPMailer Autoload चेक (Fatal Error 500 से बचने के लिए)
// =========================================================

$autoload_path = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoload_path)) {
    // ❌ अगर फ़ाइलें नहीं मिलीं, तो स्पष्ट FATAL ERROR दिखाओ
    die("<div style='background: #fee; border: 2px solid red; padding: 20px; font-family: sans-serif; text-align: center;'>
        <h1>❌ FATAL ERROR: PHPMailer Vendor Folder Missing</h1>
        <p style='font-size: 18px;'>आपकी PHP स्क्रिप्ट क्रैश हो गई क्योंकि यह PHPMailer का <b><code>vendor/autoload.php</code></b> नहीं ढूंढ पाई।</p>
        <p style='font-size: 20px; color: red; font-weight: bold;'>
            कृपया PHPMailer लाइब्रेरी की ज़िप फ़ाइल से पूरा <b><code>vendor</code> फ़ोल्डर</b> <code>admin_login.php</code> के समान फ़ोल्डर में अपलोड करें।
        </p>
    </div>");
}
require $autoload_path;


// अगर यूजर सीधे इस पेज पर आया है तो लॉगिन पेज पर भेजें
if (!isset($_SESSION['temp_username'])) {
    header("Location: index.php");
    exit;
}

// ===============================================
// 🛠️ 2. डेटाबेस कनेक्शन और EMAIL प्राप्त करें
// ===============================================
$db_host = 'sql101.infinityfree.com'; // InfinityFree Host
$db_name = 'if0_39302314_sunnydhaka';
$db_user = 'if0_39302314';
$db_pass = 'Sunnydhaka9003';

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ➡️ यूजर का EMAIL डेटाबेस से लें
    $stmt = $db->prepare("SELECT email FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['temp_username']]);
    $user = $stmt->fetch();

    if (!$user || empty($user['email'])) {
        $_SESSION['error'] = "यूजर ईमेल एड्रेस डेटाबेस में नहीं मिला।";
        header("Location: index.php");
        exit;
    }
    $recipient_email = $user['email']; // प्राप्तकर्ता का ईमेल

} catch (PDOException $e) {
    // डेटाबेस कनेक्शन विफल होने पर त्रुटि
    $_SESSION['error'] = "Database connection failed: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// ===============================================
// 🚀 3. OTP जनरेट और सेशन में स्टोर करें
// ===============================================
$otp = rand(100000, 999999);
$_SESSION['otp'] = $otp;
$_SESSION['otp_expiry'] = time() + 300; // 5 मिनट का समय
$_SESSION['debug_otp'] = $otp; // DEBUG के लिए OTP दिखाएं

// ===============================================
// 🔥 4. PHPMailer (Gmail SMTP) का कोड शुरू होता है 🔥
// ===============================================

$mail = new PHPMailer(true);

try {
    // A. सर्वर सेटिंग्स (Gmail SMTP)
    
    // **SMTP त्रुटियों की जाँच के लिए 2 पर सेट है**
    $mail->SMTPDebug  = 2; 
    
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; 
    $mail->SMTPAuth   = true; 
    
    // ✅ आपके क्रेडेंशियल्स
    $mail->Username   = 'sunnydhaka91@gmail.com';     // ⬅️ आपका पूरा Gmail ID
    $mail->Password   = 'refjgtfimkfyabdy';            // ⬅️ आपका 16-अंकों का App Password
    
    // पोर्ट 465 (SSL)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465; 

    // B. ईमेल विवरण
    $mail->setFrom('sunnydhaka91@gmail.com', 'NAGAR PALIKA NAKUR');
    $mail->addAddress($recipient_email); // डेटाबेस से प्राप्तकर्ता का ईमेल
    
    $mail->isHTML(true); 
    $mail->Subject = 'Your OTP for Login - NAGAR PALIKA NAKUR'; 
    $mail->Body    = "
        <p>प्रिय उपयोगकर्ता,</p>
        <p>आपके लॉगिन के लिए वन-टाइम पासवर्ड (OTP) नीचे दिया गया है:</p>
        <h3 style='color: #007bff; font-size: 24px;'>$otp</h3>
        <p>यह OTP **5 मिनट** के लिए मान्य है।</p>
        <p>धन्यवाद,<br>NAGAR PALIKA NAKUR</p>
    "; 
    $mail->AltBody = "आपका OTP है: $otp. यह 5 मिनट के लिए मान्य है।";
    
    // C. भेजें
    $mail->send();
    
    // ✅ सफलता संदेश (डीबग आउटपुट देखने के लिए रीडायरेक्ट रोका गया है)
    echo '<h1>✅ ईमेल भेजने का प्रयास सफल हुआ।</h1>';
    echo '<p>डीबग आउटपुट ऊपर देखें। यदि आपको ईमेल मिला है, तो **$mail->SMTPDebug** को 0 पर सेट करें और रीडायरेक्ट चालू करें।</p>';
    // header("Location: otp_verification.php"); // इसे चालू करें जब डीबग पूरा हो जाए
    
} catch (Exception $e) {
    // ईमेल भेजने में त्रुटि होने पर
    echo '<h1>❌ OTP ईमेल भेजा नहीं जा सका।</h1>';
    echo '<p>SMTP त्रुटि विवरण: ' . htmlspecialchars($mail->ErrorInfo) . '</p>';
    // $_SESSION['error'] = "OTP ईमेल भेजा नहीं जा सका। कृपया बाद में प्रयास करें। (Error: {$mail->ErrorInfo})";
    // header("Location: index.php");
    // exit;
}
?>