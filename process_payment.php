<?php
session_start();
include 'config.php';

// यूजर ऑथेंटिकेशन
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// भुगतान डेटा प्राप्त करें
$payment_type = $_POST['payment_type'] ?? '';
$amount = $_POST['amount'] ?? 0;
$con_no = $_POST['con_no'] ?? '';
$payment_method = $_POST['payment_method'] ?? '';

// वैधता जांच
if (empty($payment_type) || empty($con_no) || empty($payment_method) || $amount <= 0) {
    die("अमान्य भुगतान डेटा");
}

// ट्रांजैक्शन ID जनरेट करें
$transaction_id = 'TXN' . uniqid();

// भुगतान डेटाबेस में सहेजें
try {
    $stmt = $conn->prepare("INSERT INTO payments 
                          (con_no, payment_type, amount, payment_method, transaction_id, payment_date) 
                          VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$con_no, $payment_type, $amount, $payment_method, $transaction_id]);
    
    // बिल अपडेट करें (यदि भुगतान सफल हुआ)
    if ($payment_type === 'current') {
        $update_stmt = $conn->prepare("UPDATE bills SET current_amount_2025_26 = 0 WHERE con_no = ?");
    } else {
        $update_stmt = $conn->prepare("UPDATE bills SET arrear_balance = 0 WHERE con_no = ?");
    }
    $update_stmt->execute([$con_no]);
    
    // भुगतान रसीद पेज पर रीडायरेक्ट करें
    header("Location: payment_receipt.php?txn_id=$transaction_id");
    exit();
    
} catch (PDOException $e) {
    die("भुगतान प्रोसेसिंग में त्रुटि: " . $e->getMessage());
}
?>