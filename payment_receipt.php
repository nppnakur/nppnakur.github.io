<?php
session_start();
include 'config.php';

// यूजर ऑथेंटिकेशन
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$transaction_id = $_GET['txn_id'] ?? '';
if (empty($transaction_id)) {
    die("अमान्य ट्रांजैक्शन ID");
}

// भुगतान विवरण प्राप्त करें
$stmt = $conn->prepare("SELECT p.*, b.owner_name 
                       FROM payments p
                       JOIN bills b ON p.con_no = b.con_no
                       WHERE p.transaction_id = ?");
$stmt->execute([$transaction_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die("भुगतान विवरण नहीं मिला");
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>भुगतान रसीद - नगर पालिका परिषद</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #007bff;
            text-align: center;
        }
        .receipt-details {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .receipt-details p {
            margin: 10px 0;
        }
        .success-message {
            text-align: center;
            color: #28a745;
            font-size: 18px;
            margin: 20px 0;
        }
        .print-btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background-color: #007bff;
            color: white;
            text-align: center;
            border-radius: 5px;
            text-decoration: none;
        }
        .print-btn:hover {
            background-color: #0056b3;
        }
        @media print {
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>भुगतान रसीद</h2>
        
        <div class="success-message">
            <h3>आपका भुगतान सफलतापूर्वक प्राप्त हुआ!</h3>
        </div>
        
        <div class="receipt-details">
            <p><strong>ट्रांजैक्शन ID:</strong> <?= htmlspecialchars($payment['transaction_id']) ?></p>
            <p><strong>कनेक्शन नंबर:</strong> <?= htmlspecialchars($payment['con_no']) ?></p>
            <p><strong>ग्राहक नाम:</strong> <?= htmlspecialchars($payment['owner_name']) ?></p>
            <p><strong>भुगतान प्रकार:</strong> <?= $payment['payment_type'] === 'current' ? 'वर्तमान भुगतान' : 'बकाया भुगतान' ?></p>
            <p><strong>भुगतान विधि:</strong> 
                <?php 
                switch($payment['payment_method']) {
                    case 'credit_card': echo 'क्रेडिट कार्ड'; break;
                    case 'debit_card': echo 'डेबिट कार्ड'; break;
                    case 'net_banking': echo 'नेट बैंकिंग'; break;
                    case 'upi': echo 'UPI'; break;
                    default: echo $payment['payment_method'];
                }
                ?>
            </p>
            <p><strong>राशि:</strong> ₹<?= number_format($payment['amount'], 2) ?></p>
            <p><strong>भुगतान तिथि:</strong> <?= date('d/m/Y H:i:s', strtotime($payment['payment_date'])) ?></p>
        </div>
        
        <a href="javascript:window.print()" class="print-btn">रसीद प्रिंट करें</a>
        <a href="dashboard.php" class="print-btn">डैशबोर्ड पर वापस जाएं</a>
    </div>
</body>
</html>