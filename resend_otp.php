<?php
session_start();

if (!isset($_SESSION['temp_username'])) {
    header("Location: index.php");
    exit;
}

// पिछले OTP के 60 सेकंड बाद ही नया OTP भेजें
if (isset($_SESSION['last_otp_time']) && (time() - $_SESSION['last_otp_time'] < 60)) {
    $_SESSION['otp_error'] = "कृपया 60 सेकंड के बाद नया OTP मांगें";
    header("Location: otp_verify.php");
    exit;
}

$_SESSION['last_otp_time'] = time();
header("Location: send_otp.php"); 
exit;
?>