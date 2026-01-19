<?php
error_reporting(E_ALL); // सभी PHP त्रुटियों को रिपोर्ट करें
ini_set('display_errors', 1); // त्रुटियों को प्रदर्शित करें

session_start();

// Security: Checks if the user is logged in.
// If you are currently developing and the login system is not active,
// you can temporarily comment out this line (by adding //).
// Keep it active for production (Live).
// if (!isset($_SESSION['username'])) { header("Location: index.php"); exit(); }

// Changed to use __DIR__ for more robust inclusion path.
// Ensure this file provides the PDO database connection in a variable named $pdo.
include __DIR__ . "/config.php";

// --- Define Database Column Names Here ---
// THESE NAMES MUST EXACTLY MATCH THE ACTUAL COLUMN NAMES IN YOUR 'bills' TABLE.
// IMPORTANT: Use backticks (`) for column names that contain spaces or special characters.
// Example: If your DB has 'Connection No', use '`Connection No`'.
// If your DB has 'ward_no', use 'ward_no'.

$db_col_con_no         = '`connection No`';        // <-- इसे अपने DB के 'connection No' कॉलम नाम से बदलें
$db_col_ward_no        = 'ward_no';                // <-- इसे अपने DB के 'ward_no' कॉलम नाम से बदलें
$db_col_owner_name     = '`Owner Name`';           // <-- इसे अपने DB के 'Owner Name' कॉलम नाम से बदलें
$db_col_mobile         = 'Mobile';                 // <-- इसे अपने DB के 'Mobile' कॉलम नाम से बदलें
$db_col_current_amount = '`Current amount 2025-26`'; // <-- इसे अपने DB के 'Current amount 2025-26' कॉलम नाम से बदलें
$db_col_arrear_balance = '`Arrear Balance`';       // <-- इसे अपने DB के 'Arrear Balance' कॉलम नाम से बदलें
$db_col_remaining_balance = 'remaining_balance';   // <-- इसे अपने DB के 'remaining_balance' कॉलम नाम से बदलें

// **** FIX START: Changed $conn to $pdo here ****
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// **** FIX END ****

// connection_detail.php से 'id' पैरामीटर में connection No भेजा जा रहा है
$connection_no_param = $_GET['id'] ?? null; // null का उपयोग करें ताकि empty() 0 को खाली न माने

// FIX: URL-decode the parameter to handle commas or other special characters correctly
if ($connection_no_param !== null) {
    $connection_no_param = urldecode($connection_no_param);
}

// महत्वपूर्ण बदलाव यहाँ:
// 0 को वैध कनेक्शन नंबर के रूप में अनुमति देने के लिए खाली चेक को संशोधित किया गया है।
// आदर्श रूप से, आपके डेटाबेस में 'connection No' 0 नहीं होना चाहिए।
if ($connection_no_param === null || $connection_no_param === '') {
    die("<h2 style='text-align:center;color:red'>त्रुटि: बिल प्रिंट करने के लिए कनेक्शन नंबर प्रदान नहीं किया गया।</h2>");
}

try {
    // SELECT स्टेटमेंट में भी बैक-टिक्स वाले कॉलम नामों का उपयोग करें
    $sql_query = "SELECT
        $db_col_con_no,
        $db_col_ward_no,
        $db_col_owner_name,
        $db_col_mobile,
        $db_col_current_amount,
        $db_col_arrear_balance,
        $db_col_remaining_balance
        FROM bills
        WHERE $db_col_con_no = :con_no";

    // **** FIX START: Changed $conn to $pdo here ****
    $stmt = $pdo->prepare($sql_query);
    // **** FIX END ****
    $stmt->bindParam(':con_no', $connection_no_param);
    $stmt->execute();
    $bill = $stmt->fetch(PDO::FETCH_ASSOC); // डेटा को $bill में प्राप्त करें

    if (!$bill) {
        die("<h2 style='text-align:center;color:red'>कनेक्शन नहीं मिला (Connection Not Found)</h2><p style='text-align:center;'>कृपया URL में दिए गए कनेक्शन नंबर और डेटाबेस में कॉलम के नाम की दोबारा जाँच करें।</p>");
    }

} catch (PDOException $e) {
    die("<h2 style='text-align:center;color:red'>डेटाबेस त्रुटि: " . $e->getMessage() . "</h2>");
}

/* टाइम‑ज़ोन व समय (सेकंड समेत) */
date_default_timezone_set('Asia/Kolkata');
$current_date = date('d F Y, h:i:s A');

/* वैरिएबल्स */
// बिल ID जनरेट करने के लिए कनेक्शन नंबर के संख्यात्मक भाग का उपयोग करें
// preg_replace('/[^0-9]/', '', ...) स्ट्रिंग से केवल संख्याएँ निकालता है
$numeric_con_no_part = preg_replace('/[^0-9]/', '', $bill[str_replace('`', '', $db_col_con_no)] ?? '');
$bill_id_numeric = !empty($numeric_con_no_part) ? (int)$numeric_con_no_part : 0;
$bill_id  = "NPPNCO" . str_pad($bill_id_numeric, 3, '0', STR_PAD_LEFT);


$due_date = date('d/m/Y', strtotime('+15 days'));
// URL में $connection_no_param का उपयोग किया जा रहा है, जो सही है
$url      = "https://sunnydhaka.fwh.is/print_bill.php?id=" . urlencode($connection_no_param);

// सही सरणी कुंजियों का उपयोग करके बिल डेटा तक पहुँचें
// $BILL सरणी में कुंजियाँ बिल्कुल DB कॉलम नाम हैं (बैक-टिक्स के बिना)
$display_con_no = $bill[str_replace('`', '', $db_col_con_no)] ?? '-';
$display_ward_no = $bill[str_replace('`', '', $db_col_ward_no)] ?? '-';
$display_owner_name = $bill[str_replace('`', '', $db_col_owner_name)] ?? '-';
$display_mobile = $bill[str_replace('`', '', $db_col_mobile)] ?? '-';
$display_current_amount = $bill[str_replace('`', '', $db_col_current_amount)] ?? '0';
$display_arrear_balance = $bill[str_replace('`', '', $db_col_arrear_balance)] ?? '0';
$display_remaining_balance = $bill[str_replace('`', '', $db_col_remaining_balance)] ?? '0';


// सुनिश्चित करें कि संख्यात्मक मान वास्तव में गणना के लिए संख्याएँ हैं
$display_current_amount = (float) $display_current_amount;
$display_arrear_balance = (float) $display_arrear_balance;
$display_remaining_balance = (float) $display_remaining_balance;


?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>जल बिल प्रिंट</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap');
    body{font-family:'Poppins','Noto Sans Devanagari',sans-serif;background:#f0f0f0;margin:0;padding:0;}
    .print-container{width:210mm;margin:auto;background:#fff;padding:10mm 15mm;box-shadow:0 0 8px rgba(0,0,0,.2);page-break-after:always;position:relative;}
    .bill-section{position:relative;border:1.5px dashed #007bff;padding:15mm;border-radius:10px;margin-bottom:15px;}
    .watermark{position:absolute;top:50%;left:50%;width:360px;height:360px;opacity:.08;background:url('img1.png') center/contain no-repeat;transform:translate(-50%,-50%);z-index:0;}
    .copy-label{position:absolute;top:12px;right:20px;background:#dc3545;color:#fff;font-size:12px;padding:4px 10px;border-radius:5px;z-index:2;}
    .top-left-date{position:absolute;top:12px;left:20px;font-size:12px;color:#333;z-index:2;}
    .header{text-align:center;position:relative;z-index:1;margin-bottom:20px;}
    .header h1{margin:0;color:#007bff;font-size:24px;}
    .header h2{margin:5px 0;color:#333;font-size:18px;}
    .bill-id{margin-top:8px;font-size:14px;background:#007bff;color:#fff;display:inline-block;padding:5px 15px;border-radius:20px;}
    table{width:100%;border-collapse:collapse;margin-top:20px;position:relative;z-index:1;}
    table th,table td{padding:10px;border:1px solid #aaa;text-align:left;font-size:14px;}
    table th{background:#007bff;color:#fff;}
    .total{background:#e6f2ff;font-weight:bold;}
    .footer{margin-top:30px;text-align:center;font-size:13px;position:relative;z-index:1;}
    .signature{margin-top:40px;text-align:right;padding-right:30px;}
    .url-bottom-left{font-size:11px;text-align:left;margin-top:40px;color:#444;}
    .divider-line{text-align:center;font-size:14px;color:#999;margin:10px 0 25px;border-top:2px dashed #ccc;padding-top:10px;}
    .print-btn-container{text-align:center;margin:20px;}
    .print-btn{background:#007bff;color:#fff;padding:12px 25px;font-size:16px;border:none;border-radius:8px;cursor:pointer;}
    .back-btn{display:inline-block;margin-top:10px;background:#28a745;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:16px;}
    .back-btn:hover{background:#218838;}
    @media print{.print-btn-container{display:none;}body{background:#fff;}.print-container{box-shadow:none;margin:0;padding:0;}}
    @page{size:A4;margin:0;}
</style>
</head>
<body>

<div class="print-container">

    <div class="bill-section">
        <div class="top-left-date">🕒 समय: <?= $current_date ?></div>
        <div class="copy-label">OFFICE COPY</div>
        <div class="watermark"></div>

        <div class="header">
            <h1>नगर पालिका परिषद नगर</h1>
            <h2>💧 जल एवं स्वच्छता बिल</h2>
            <div class="bill-id">बिल आईडी: <?= htmlspecialchars($bill_id) ?></div>
        </div>

        <table>
            <tr><th>कनेक्शन नंबर</th><td><?= htmlspecialchars($display_con_no) ?></td></tr>
            <tr><th>वार्ड नंबर</th><td><?= htmlspecialchars($display_ward_no) ?></td></tr>
            <tr><th>ग्राहक का नाम</th><td><?= htmlspecialchars($display_owner_name) ?></td></tr>
            <tr><th>मोबाइल नंबर</th><td><?= htmlspecialchars($display_mobile) ?></td></tr>
            <tr><th>वर्तमान राशि</th><td>₹<?= number_format($display_current_amount,2) ?></td></tr>
            <tr><th>बकाया राशि</th><td>₹<?= number_format($display_arrear_balance,2) ?></td></tr>
            <tr class="total"><th>कुल देय राशि</th><td>₹<?= number_format($display_remaining_balance,2) ?></td></tr>
        </table>

        <div class="footer">
            <p>💡 भुगतान की अंतिम तिथि: <?= $due_date ?></p>
            <div class="signature"><p>अधिकृत हस्ताक्षर: ___________________</p></div>
        </div>

        <div class="url-bottom-left">🔗 <?= htmlspecialchars($url) ?></div>
    </div>

    <div class="divider-line">--- ✂️ --- यह रेखा काटने हेतु है --- ✂️ ---</div>

    <div class="bill-section">
        <div class="top-left-date">🕒 समय: <?= $current_date ?></div>
        <div class="copy-label">CUSTOMER COPY</div>
        <div class="watermark"></div>

        <div class="header">
            <h1>नगर पालिका परिषद नगर</h1>
            <h2>💧 जल एवं स्वच्छता बिल</h2>
            <div class="bill-id">बिल आईडी: <?= htmlspecialchars($bill_id) ?></div>
        </div>

        <table>
            <tr><th>कनेक्शन नंबर</th><td><?= htmlspecialchars($display_con_no) ?></td></tr>
            <tr><th>वार्ड नंबर</th><td><?= htmlspecialchars($display_ward_no) ?></td></tr>
            <tr><th>ग्राहक का नाम</th><td><?= htmlspecialchars($display_owner_name) ?></td></tr>
            <tr><th>मोबाइल नंबर</th><td><?= htmlspecialchars($display_mobile) ?></td></tr>
            <tr><th>वर्तमान राशि</th><td>₹<?= number_format($display_current_amount,2) ?></td></tr>
            <tr><th>बकाया राशि</th><td>₹<?= number_format($display_arrear_balance,2) ?></td></tr>
            <tr class="total"><th>कुल देय राशि</th><td>₹<?= number_format($display_remaining_balance,2) ?></td></tr>
        </table>

        <div class="footer">
            <p>💡 भुगतान की अंतिम तिथि: <?= $due_date ?></p>
            <div class="signature"><p>अधिकृत हस्ताक्षर: ___________________</p></div>
        </div>

        <div class="url-bottom-left">🔗 <?= htmlspecialchars($url) ?></div>
    </div>
</div>

<div class="print-btn-container">
    <button class="print-btn" onclick="window.print()">🖨️ प्रिंट करें</button><br><br>
    <a href="connection_detail.php" class="back-btn">🔙 सूची पर वापस जाएँ</a>
</div>

</body>
</html>