<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["admin_login"])) {
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    if ($username === "Sunnydhaka" && $password === "Sunny1990") {
        // OTP जनरेट करें (6 अंक)
        $otp = rand(100000, 999999);
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_expiry'] = time() + 300; // 5 मिनट के लिए वैध
        $_SESSION['temp_username'] = $username; // अस्थायी सेशन
        
        // डेवलपमेंट के लिए OTP दिखाएं (प्रोडक्शन में हटा दें)
        $_SESSION['debug_otp'] = $otp;
        
        header("Location: otp_verify.php");
        exit();
    } else {
        $_SESSION["error"] = '<div class="error-msg">
            <img src="https://media.giphy.com/media/3og0IPxMM0erATueVW/giphy.gif" width="70" alt="Sad"><br>
            😞 गलत यूज़रनेम या पासवर्ड!
        </div>';
        header("Location: index.php");
        exit();
    }
}

header("Location: index.php");
exit();
?>