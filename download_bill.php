<?php
session_start();
include 'config.php'; // Your database connection and configuration file
header('Content-Type: text/html; charset=utf-8');

// --- Define Database Column Names Here (MUST MATCH EXACTLY) ---
// These names MUST EXACTLY MATCH the actual column names in your 'bills' table.
// They contain spaces, so they MUST be enclosed in backticks (`).
$db_col_con_no         = '`connection No`';
$db_col_ward_no        = '`Ward No`';
$db_col_owner_name     = '`Owner Name`';
$db_col_mobile         = '`Mobile`';
$db_col_current_amount = '`Current amount 2025-26`'; // In bills table
$db_col_arrear_balance = '`Arrear Balance`';       // In bills table
$db_col_remaining_balance = '`Remaining Balance`';  // In bills table

// Column names for the 'payments' table (assuming these don't have spaces, so no backticks needed)
$db_pay_id             = 'id';
$db_pay_con_no         = 'con_no'; // This column in 'payments' should store the `connection No` from 'bills'
$db_pay_type           = 'payment_type';
$db_pay_amount         = 'amount';
$db_pay_method         = 'payment_method';
$db_pay_paid_on        = 'paid_on';
$db_pay_username       = 'username';
$db_pay_depositor_name = 'depositor_name'; // Assuming you added this column as discussed


// 1. Authentication
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// 2. Get Payment ID
$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($payment_id <= 0) {
    echo '<!DOCTYPE html><html lang="hi"><head><meta charset="UTF-8"><title>Error</title><style>body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; } .error { color: red; }</style></head><body><h2 class="error">❌ अमान्य बिल ID।</h2><p>कृपया एक वैध बिल ID प्रदान करें।</p><p><a href="javascript:history.back()">⬅️ वापस जाएं</a> | <a href="jal.php">🏠 मुख्य पृष्ठ</a></p></body></html>';
    exit();
}

try {
    // 3. Fetch Payment Details
    $stmt_payment = $conn->prepare("SELECT
        {$db_pay_id},
        {$db_pay_con_no},
        {$db_pay_type},
        {$db_pay_amount},
        {$db_pay_method},
        {$db_pay_paid_on},
        {$db_pay_username},
        {$db_pay_depositor_name}
        FROM payments
        WHERE {$db_pay_id} = ?");
    $stmt_payment->execute([$payment_id]);
    $payment = $stmt_payment->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo '<!DOCTYPE html><html lang="hi"><head><meta charset="UTF-8"><title>Error</title><style>body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; } .error { color: red; }</style></head><body><h2 class="error">❌ भुगतान रिकॉर्ड नहीं मिला।</h2><p>इस ID के लिए कोई भुगतान रिकॉर्ड मौजूद नहीं है।</p><p><a href="javascript:history.back()">⬅️ वापस जाएं</a> | <a href="jal.php">🏠 मुख्य पृष्ठ</a></p></body></html>';
        exit();
    }

    // Use the connection number from the payment record to fetch bill details
    $con_no_from_payment = $payment[str_replace('`', '', $db_pay_con_no)];

    // 4. Fetch Bill Details
    $stmt_bill = $conn->prepare("SELECT
        {$db_col_con_no},
        {$db_col_owner_name},
        {$db_col_ward_no},
        {$db_col_mobile},
        {$db_col_current_amount},
        {$db_col_arrear_balance},
        {$db_col_remaining_balance}
        FROM bills
        WHERE {$db_col_con_no} = ?");
    $stmt_bill->execute([$con_no_from_payment]);
    $bill = $stmt_bill->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        echo '<!DOCTYPE html><html lang="hi"><head><meta charset="UTF-8"><title>Error</title><style>body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; } .error { color: red; }</style></head><body><h2 class="error">❌ बिल विवरण नहीं मिला।</h2><p>इस कनेक्शन नंबर के लिए बिल विवरण मौजूद नहीं है।</p><p><a href="javascript:history.back()">⬅️ वापस जाएं</a> | <a href="jal.php">🏠 मुख्य पृष्ठ</a></p></body></html>';
        exit();
    }

} catch (PDOException $e) {
    echo '<!DOCTYPE html><html lang="hi"><head><meta charset="UTF-8"><title>Error</title><style>body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; } .error { color: red; }</style></head><body><h2 class="error">❌ डेटाबेस त्रुटि:</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p><a href="javascript:history.back()">⬅️ वापस जाएं</a> | <a href="jal.php">🏠 मुख्य पृष्ठ</a></p></body></html>';
    exit();
}

// Function to format INR
function format_inr($amount) {
    $locale = 'en_IN';
    $fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    return $fmt->formatCurrency($amount, "INR");
}

// Access fetched data using the defined column variables for consistency
$con_no_display = htmlspecialchars($bill[str_replace('`', '', $db_col_con_no)]);
$owner_name_display = htmlspecialchars($bill[str_replace('`', '', $db_col_owner_name)]);
$ward_no_display = htmlspecialchars($bill[str_replace('`', '', $db_col_ward_no)]);
$mobile_display = htmlspecialchars($bill[str_replace('`', '', $db_col_mobile)]);

$current_amount_display = format_inr($bill[str_replace('`', '', $db_col_current_amount)]);
$arrear_balance_display = format_inr($bill[str_replace('`', '', $db_col_arrear_balance)]);
$remaining_balance_display = format_inr($bill[str_replace('`', '', $db_col_remaining_balance)]);

$payment_amount_display = format_inr($payment[$db_pay_amount]);
$payment_type_display = ($payment[$db_pay_type] == 'current') ? 'चालू बिल' : 'पिछला बकाया';
$payment_method_display = htmlspecialchars($payment[$db_pay_method]);
$paid_on_display = date('d/m/Y H:i A', strtotime($payment[$db_pay_paid_on]));
$username_display = htmlspecialchars($payment[$db_pay_username]);
$depositor_name_display = htmlspecialchars($payment[$db_pay_depositor_name] ?? 'N/A'); // Use null coalescing for older records
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Receipt - Connection No: <?= $con_no_display ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            color: #333;
        }
        .bill-container {
            width: 100%;
            max-width: 700px;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 2.2em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .header p {
            font-size: 1.1em;
            color: #555;
            margin: 5px 0 0;
        }
        .section-title {
            color: #007bff;
            font-size: 1.4em;
            margin-top: 25px;
            margin-bottom: 15px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item {
            background-color: #f9f9f9;
            padding: 12px;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        .info-item strong {
            display: block;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 3px;
        }
        .info-item span {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
        }
        .amount-highlight {
            font-size: 1.6em;
            font-weight: bold;
            color: #28a745; /* Green for paid amount */
            background-color: #e6ffe6;
            padding: 10px 15px;
            border-radius: 5px;
            text-align: center;
            margin-top: 20px;
            border: 1px dashed #28a745;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 0.9em;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .footer p {
            margin: 5px 0;
        }
        
        /* Print Specific Styles */
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .bill-container {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 10mm;
            }
            .print-button {
                display: none !important; /* Hide print button in print view */
            }
        }
        
        /* Buttons for actions */
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        .action-buttons button, .action-buttons a {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .print-button {
            background-color: #007bff;
            color: white;
        }
        .print-button:hover {
            background-color: #0056b3;
        }
        .back-button {
            background-color: #6c757d;
            color: white;
        }
        .back-button:hover {
            background-color: #5a6268;
        }
        .home-button {
            background-color: #28a745;
            color: white;
        }
        .home-button:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <div class="bill-container">
        <div class="header">
            <h1><i class="fas fa-tint"></i> जल विभाग</h1>
            <p>भुगतान रसीद</p>
            <p>दिनांक: <?= date('d/m/Y') ?></p>
        </div>

        <h2 class="section-title">ग्राहक विवरण</h2>
        <div class="info-grid">
            <div class="info-item">
                <strong>कनेक्शन नंबर</strong>
                <span><?= $con_no_display ?></span>
            </div>
            <div class="info-item">
                <strong>वार्ड नंबर</strong>
                <span><?= $ward_no_display ?></span>
            </div>
            <div class="info-item">
                <strong>मालिक का नाम</strong>
                <span><?= $owner_name_display ?></span>
            </div>
            <div class="info-item">
                <strong>मोबाइल नंबर</strong>
                <span><?= $mobile_display ?></span>
            </div>
        </div>

        <h2 class="section-title">भुगतान विवरण</h2>
        <div class="info-grid">
            <div class="info-item">
                <strong>भुगतान ID</strong>
                <span><?= htmlspecialchars($payment[$db_pay_id]) ?></span>
            </div>
            <div class="info-item">
                <strong>भुगतान प्रकार</strong>
                <span><?= $payment_type_display ?></span>
            </div>
            <div class="info-item">
                <strong>भुगतान विधि</strong>
                <span><?= $payment_method_display ?></span>
            </div>
            <div class="info-item">
                <strong>जमाकर्ता का नाम</strong>
                <span><?= $depositor_name_display ?></span>
            </div>
            <div class="info-item">
                <strong>भुगतान की तिथि</strong>
                <span><?= $paid_on_display ?></span>
            </div>
            <div class="info-item">
                <strong>प्रोसेस करने वाला यूजर</strong>
                <span><?= $username_display ?></span>
            </div>
        </div>

        <div class="amount-highlight">
            भुगतान की गई राशि: <?= $payment_amount_display ?>
        </div>

        <h2 class="section-title">वर्तमान बिल स्थिति</h2>
        <div class="info-grid">
            <div class="info-item">
                <strong>चालू बकाया</strong>
                <span><?= $current_amount_display ?></span>
            </div>
            <div class="info-item">
                <strong>पिछला बकाया</strong>
                <span><?= $arrear_balance_display ?></span>
            </div>
            <div class="info-item">
                <strong>कुल शेष राशि</strong>
                <span><?= $remaining_balance_display ?></span>
            </div>
        </div>

        <div class="footer">
            <p>यह एक कंप्यूटर जनित रसीद है और इस पर हस्ताक्षर की आवश्यकता नहीं है।</p>
            <p>&copy; <?= date('Y') ?> जल विभाग। सभी अधिकार सुरक्षित।</p>
        </div>
        
        <div class="action-buttons print-button">
            <button onclick="window.print()" class="print-button">
                <i class="fas fa-print"></i> प्रिंट / PDF सहेजें
            </button>
            <a href="javascript:history.back()" class="back-button">
                <i class="fas fa-arrow-left"></i> वापस जाएं
            </a>
            <a href="jal.php" class="home-button">
                <i class="fas fa-home"></i> मुख्य पृष्ठ
            </a>
        </div>
    </div>
</body>
</html>