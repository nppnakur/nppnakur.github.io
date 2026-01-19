<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Security: Checks if the user is logged in.
// If you are currently developing and the login system is not active,
// you can temporarily comment out this line (by adding //).
// Keep it active for production (Live).
// if (!isset($_SESSION['username'])) { header("Location: index.php"); exit(); }

// Changed to use __DIR__ for more robust inclusion path.
// Ensure this file provides the PDO database connection in a variable named $pdo.
if (!file_exists(__DIR__ . "/config.php")) {
    die("<h2 style='text-align:center;color:red'>त्रुटि: कॉन्फ़िगरेशन फ़ाइल नहीं मिली!</h2>");
}
include __DIR__ . "/config.php";

// --- Organization Details (Customize these) ---
$org_name = "नगर पालिका परिषद नकुड़";
$org_address = "मुख्य कार्यालय, नियर बस स्टैंड, नगर, उत्तर प्रदेश - 224190";
$org_contact = "फोन: 05244-230000 | ईमेल: info@nagarpalika.org";
$org_logo_path = 'img1.png'; // Path to your logo image - Make sure this file exists in the same directory!


// --- Define Database Column Names Here ---
// THESE NAMES MUST EXACTLY MATCH THE ACTUAL COLUMN NAMES IN YOUR 'bills' TABLE.
// IMPORTANT: Use backticks (`) for column names that contain spaces or special characters.
// Example: If your DB has 'Connection No', use '`Connection No`'.
// If your DB has 'ward_no', use 'ward_no'.

$db_col_con_no         = '`connection No`';         // <-- इसे अपने DB के 'connection No' कॉलम नाम से बदलें
$db_col_ward_no        = 'ward_no';               // <-- इसे अपने DB के 'ward_no' कॉलम नाम से बदलें
$db_col_owner_name     = '`Owner Name`';          // <-- इसे अपने DB के 'Owner Name' कॉलम नाम से बदलें
$db_col_mobile         = 'Mobile';                // <-- इसे अपने DB के 'Mobile' कॉलम नाम से बदलें
$db_col_current_amount = '`Current amount 2025-26`'; // <-- इसे अपने DB के 'Current amount 2025-26' कॉलम नाम से बदलें
$db_col_arrear_balance = '`Arrear Balance`';      // <-- इसे अपने DB के 'Arrear Balance' कॉलम नाम से बदलें
$db_col_remaining_balance = 'remaining_balance';   // <-- इसे अपने DB के 'remaining_balance' कॉलम नाम से बदलें

$bill_data = null;
$payment_data = null; // Changed to $payment_data to avoid conflict with $payment variable in old code.

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $connection_no_param = $_GET['id'] ?? null;

    if ($connection_no_param !== null) {
        $connection_no_param = urldecode($connection_no_param);
    }

    if ($connection_no_param === null || $connection_no_param === '') {
        die("<h2 style='text-align:center;color:red'>त्रुटि: बिल प्रिंट करने के लिए कनेक्शन नंबर प्रदान नहीं किया गया।</h2>");
    }

    // Fetch Bill Details
    $stmt_bill = $pdo->prepare("SELECT
        $db_col_con_no,
        $db_col_ward_no,
        $db_col_owner_name,
        $db_col_mobile,
        $db_col_current_amount,
        $db_col_arrear_balance,
        $db_col_remaining_balance
        FROM bills
        WHERE $db_col_con_no = :con_no");
    $stmt_bill->bindParam(':con_no', $connection_no_param);
    $stmt_bill->execute();
    $bill_data = $stmt_bill->fetch(PDO::FETCH_ASSOC);

    if (!$bill_data) {
        die("<h2 style='text-align:center;color:red'>कनेक्शन नहीं मिला: इस कनेक्शन नंबर के लिए कोई बिल रिकॉर्ड नहीं मिला।</h2>");
    }

    // =========================================================================
    // BUG FIX: Fetch the specific payment record if `payment_id` is provided
    // =========================================================================
    $payment_id_param = $_GET['payment_id'] ?? null;
    $stmt_payment = null;

    if ($payment_id_param !== null && $payment_id_param !== '') {
        // Use a prepared statement to fetch a specific payment by its ID
        $stmt_payment = $pdo->prepare("SELECT * FROM payments WHERE id = :payment_id LIMIT 1");
        $stmt_payment->bindParam(':payment_id', $payment_id_param, PDO::PARAM_INT);
    } else {
        // Fallback: If no payment_id is provided, fetch the latest payment for the connection
        $stmt_payment = $pdo->prepare("SELECT * FROM payments WHERE con_no = :con_no ORDER BY paid_on DESC LIMIT 1");
        $stmt_payment->bindParam(':con_no', $connection_no_param);
    }

    $stmt_payment->execute();
    $payment_data = $stmt_payment->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("डेटाबेस त्रुटि: " . $e->getMessage());
} catch (Exception $e) {
    die("त्रुटि: " . $e->getMessage());
}

/* टाइम‑ज़ोन व समय (सेकंड समेत) */
date_default_timezone_set('Asia/Kolkata');
$current_date = date('d F Y, h:i:s A');

/* वैरिएबल्स */
// Generate Bill ID (Example: NPPNCO001, NPPNCO123)
$numeric_con_no_part = preg_replace('/[^0-9]/', '', $bill_data[str_replace('`', '', $db_col_con_no)] ?? '');
$bill_id_numeric = !empty($numeric_con_no_part) ? (int)$numeric_con_no_part : 0;
$bill_id   = "NPPNCO" . str_pad($bill_id_numeric, 3, '0', STR_PAD_LEFT);


$due_date = date('d/m/Y', strtotime('+15 days'));
// Current URL for reference on bill (optional)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$uri = $_SERVER['REQUEST_URI'];
$current_page_url = htmlspecialchars("{$protocol}://{$host}{$uri}");

// Sanitize and format bill details
$display_con_no = htmlspecialchars($bill_data[str_replace('`', '', $db_col_con_no)] ?? 'N/A');
$display_ward_no = htmlspecialchars($bill_data[str_replace('`', '', $db_col_ward_no)] ?? 'N/A');
$display_owner_name = htmlspecialchars($bill_data[str_replace('`', '', $db_col_owner_name)] ?? 'N/A');
$display_mobile = htmlspecialchars($bill_data[str_replace('`', '', $db_col_mobile)] ?? 'N/A');

$current_bill_amount_val = floatval($bill_data[str_replace('`', '', $db_col_current_amount)] ?? 0);
$arrear_balance_val = floatval($bill_data[str_replace('`', '', $db_col_arrear_balance)] ?? 0);
$remaining_balance_val = floatval($bill_data[str_replace('`', '', $db_col_remaining_balance)] ?? 0);

$display_current_amount = '₹' . number_format($current_bill_amount_val, 2);
$display_arrear_balance = '₹' . number_format($arrear_balance_val, 2);
$display_remaining_balance = '₹' . number_format($remaining_balance_val, 2); // This is the "कितना शेष रह गया"

// Sanitize and format payment details (Re-added from download_paymentbill.php)
$payer_name_display = htmlspecialchars($payment_data['payer_name'] ?? 'N/A');
$payment_date_display = ($payment_data && isset($payment_data['paid_on'])) ? date('d-m-Y H:i:s', strtotime($payment_data['paid_on'])) : 'N/A';
$payment_type_text = ($payment_data && ($payment_data['payment_type'] === 'current')) ? 'वर्तमान बिल भुगतान' : (($payment_data && ($payment_data['payment_type'] === 'arrear')) ? 'पिछला बकाया भुगतान' : 'कोई भुगतान रिकॉर्ड नहीं');

$payment_method_display = htmlspecialchars($payment_data['payment_method'] ?? 'N/A');
$username_display = htmlspecialchars($payment_data['username'] ?? 'N/A'); // This is "किसने किया"
$transaction_id_display = htmlspecialchars($payment_data['transaction_id'] ?? 'N/A');

$payment_amount_val = floatval($payment_data['amount'] ?? 0);
$discount_amount_val = floatval($payment_data['discount_amount'] ?? 0);
$discount_percentage_val = floatval($payment_data['discount_percentage'] ?? 0);

$payment_amount_display = '₹' . number_format($payment_amount_val, 2);
$discount_amount_display = '₹' . number_format($discount_amount_val, 2);
$discount_percentage_display = number_format($discount_percentage_val, 2) . '%';

$total_paid_with_discount_display = '₹' . number_format($payment_amount_val + $discount_amount_val, 2);


// QR Code Data (You can customize this)
$qr_data = "कनेक्शन नंबर: " . $display_con_no . "\nबिल आईडी: " . $bill_id . "\nवेब लिंक: " . $current_page_url;
// Using a free online QR code API for demonstration. For production, consider generating on your server.
$qr_code_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($qr_data);

// Placeholder for discount/additional charge (These values are not fetched from DB in current code)
// If you fetch them from DB, use $payment_data['discount_amount'] and $payment_data['additional_charge']
$display_discount_amount_for_bill_summary = $discount_amount_val; // Using the fetched payment discount
$display_additional_charge = 0.00; // Example: fetched from DB if available for bill summary
$helpline_number = "+91*************"; // Customize your helpline number

?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>जल बिल प्रिंट</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Noto+Sans+Devanagari:wght@400;700&display=swap');
    body{font-family:'Poppins','Noto Sans Devanagari',sans-serif;background:#f0f0f0;margin:0;padding:0;}
    /* .print-container{width:210mm;margin:auto;background:#fff;padding:10mm 15mm;box-shadow:0 0 8px rgba(0,0,0,.2);page-break-after:always;position:relative;} */
    /* Updated .print-container for flexible width */
    .print-container {
        margin: auto;
        background: #fff;
        padding: 10mm 15mm;
        box-shadow: 0 0 8px rgba(0,0,0,.2);
        page-break-after: always;
        position: relative;
    }
    .bill-section{position:relative;border:1.5px dashed #007bff;padding:15mm;border-radius:10px;margin-bottom:8mm;}
    .watermark{position:absolute;top:50%;left:50%;width:500px;height:500px;opacity:.08;background:url('img1.png') center/contain no-repeat;transform:translate(-50%,-50%);z-index:0;}
    .copy-label{position:absolute;top:12px;right:20px;background:#dc3545;color:#fff;font-size:12px;padding:4px 10px;border-radius:5px;z-index:2;}
    .top-left-date{position:absolute;top:12px;left:20px;font-size:12px;color:#333;z-index:2;}
    .header{text-align:center;position:relative;z-index:1;margin-bottom:20px;}
    .header h1{margin:0;color:#007bff;font-size:24px;}
    .header h2{margin:5px 0;color:#333;font-size:18px;}
    .header h3{margin:5px 0 0;font-size:16px;}
    .bill-id{margin-top:8px;font-size:14px;background:#007bff;color:#fff;display:inline-block;padding:5px 15px;border-radius:20px;}
    table{width:100%;border-collapse:collapse;margin-top:20px;position:relative;z-index:1;}
    table th,table td{padding:10px;border:1px solid #aaa;text-align:left;font-size:14px;}
    table th{background:#eee;color:#000;}
    .total{background:#f0f0f0;font-weight:bold;}
    .footer{margin-top:30px;text-align:center;font-size:13px;position:relative;z-index:1;}
    .signature{margin-top:40px;text-align:right;padding-right:30px;}
    .url-bottom-left{font-size:9px;text-align:left;margin-top:40px;color:#444;}
    .divider-line{text-align:center;font-size:12px;color:#999;margin:5mm 0;border-top:2px dashed #ccc;padding-top:3px;}
    .print-btn-container{text-align:center;margin:20px;}
    .print-btn{background:#007bff;color:#fff;padding:12px 25px;font-size:16px;border:none;border-radius:8px;cursor:pointer;}
    .back-btn{display:inline-block;margin-top:10px;background:#28a745;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:16px;}
    .back-btn:hover{background:#218838;}
    .qr-code-section {
        text-align: center;
        margin-top: 20px;
    }
    .qr-code-section img {
        width: 80px;
        height: 80px;
        border: 1px solid #ccc;
        padding: 5px;
        background-color: #fff;
    }
    .note-section {
        font-size: 8pt;
        color: #777;
        margin-top: 15px;
        font-style: italic;
        text-align: center;
        line-height: 1.4;
        padding: 5px;
        border-top: 1px dashed #eee;
    }
    .details-card {
        border: 1px solid #e0e6ed;
        border-radius: 10px;
        padding: 15px;
        background-color: #fcfdfe;
        box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        margin-top: 20px;
    }
    .details-card h3 {
        color: #000;
        font-size: 11.5pt;
        margin: 0 0 12px 0;
        padding-bottom: 8px;
        border-bottom: 2px solid #ccc;
        text-transform: uppercase;
        font-weight: 600;
    }
    .footer p span{
        color:#007bff;
    }

    /* Styles for the combined details and summary */
    .combined-details-summary {
        display: flex;
        border: 1px solid #e0e6ed;
        border-radius: 10px;
        padding: 0; /* Remove padding from parent */
        background-color: #fcfdfe;
        box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        margin-top: 20px;
    }
    .combined-details-summary .section-half {
        flex: 1; /* Each section takes half the width */
        padding: 15px; /* Apply padding to internal sections */
    }
    .combined-details-summary .section-half:first-child {
        border-right: 1px solid #e0e6ed; /* Vertical divider */
    }
    .combined-details-summary .section-half h3 {
        margin-top: 0; /* Reset margin */
    }
    /* End of Styles for combined details and summary */


    /* Styles for side-by-side (2 columns) within the 'भुगतान जानकारी' table */
    .payment-info-table {
        width: 100%;
        border-collapse: collapse;
    }
    .payment-info-table th,
    .payment-info-table td {
        padding: 10px;
        border: 1px solid #aaa;
        font-size: 14px;
        vertical-align: top; /* Align content to top */
    }
    .payment-info-table th {
        background: #eee;
        color: #000;
        width: 25%; /* Adjust width for labels */
    }
    .payment-info-table td {
        width: 25%; /* Adjust width for values */
    }

    /* ***** PRINT SPECIFIC STYLES TO FIT ON ONE PAGE ***** */
    @media print{
        @page {
            size: A4 landscape; /* A4 के साथ लैंडस्केप ओरिएंटेशन */
            margin: 2mm; /* Reduced page margins */
        }
        body {
            background: #fff;
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important; /* Ensures colors print */
            font-size: 6.5pt; /* Smaller font for print */
            padding: 0;
        }

        .print-container {
            display: flex; /* Make it a flex container */
            flex-direction: row; /* Arrange children in a row */
            justify-content: center; /* Center the two copies horizontally */
            align-items: flex-start; /* Align items to the top */
            width: 100%; /* Take full available width of the page */
            height: 100%; /* Take full available height of the page */
            box-shadow: none;
            margin: 0;
            padding: 0; /* Remove padding from container, add to sections */
            page-break-after: always; /* Each print-container (a pair of bills) gets its own page */
            overflow: hidden; /* Hide anything that overflows */
        }

        .bill-section {
            flex: 1; /* Each section takes equal space */
            max-width: 140mm; /* Roughly half of A4 landscape width, allowing for divider and margins */
            border: 0.5px solid #000;
            padding: 5mm;
            margin-bottom: 0; /* Remove bottom margin as they are side-by-side */
            height: auto; /* Allow height to adjust to content */
            box-sizing: border-box; /* Include padding and border in width/height */
        }

        .watermark {
            width: 40%; /* Smaller watermark for print */
            height: 40%;
            opacity: 0.05; /* Slightly less opaque for print */
        }

        .header {
            margin-bottom: 8px; /* Reduced margin */
        }
        .header h1 {
            font-size: 14px; /* Smaller font */
            color: #000 !important;
        }
        .header h2 {
            font-size: 11px; /* Smaller font */
        }
        .header h3 {
            font-size: 10px; /* Smaller font */
            margin-top: 4px;
        }
        .bill-id {
            font-size: 9px;
            padding: 2px 8px;
            background: transparent !important;
            color: #000 !important;
            border: 1px solid #000 !important;
        }
        .copy-label {
            font-size: 8px;
            padding: 1px 4px;
            top: 4px;
            right: 8px;
        }
        .top-left-date {
            font-size: 7px;
            top: 4px;
            left: 8px;
        }

        table {
            margin-top: 8px;
        }
        table th, table td {
            padding: 3px; /* Reduced table padding */
            font-size: 8.5pt; /* Adjusted table font size */
        }
        table th {
            background: #f5f5f5 !important;
            color: #000 !important;
        }
        .total {
            padding: 4px;
            background: #f8f8f8 !important;
            color: #000 !important;
        }

        /* Print specific for combined details and summary */
        .combined-details-summary {
            padding: 0; /* Remove padding from parent */
            margin-top: 8px; /* Reduced margin above combined card */
            background-color: transparent !important;
            box-shadow: none !important;
            border-color: #ddd !important;
        }
        .combined-details-summary .section-half {
            padding: 6px; /* Apply reduced padding to internal sections */
        }
        .combined-details-summary .section-half:first-child {
            border-right-color: #ddd !important; /* Vertical divider color */
        }
        .details-card h3 {
            font-size: 9.5pt;
            margin-bottom: 5px;
            padding-bottom: 2px;
            border-bottom-color: #ccc !important;
            color: #000 !important;
        }

        .footer {
            margin-top: 10px;
            font-size: 7pt; /* Smaller font for footer */
        }
        .footer p {
            margin-top: 4px;
        }
        .footer p span {
            color: #000 !important;
        }
        .signature {
            margin-top: 15px;
            padding-right: 15px;
            font-size: 8pt;
        }
        .note-section {
            font-size: 6pt;
            margin-top: 5px;
            padding: 3px;
        }

        /* This will now be a vertical divider */
        .divider-line {
            width: 1mm; /* Thin vertical line */
            height: 168mm; /* Fixed height for the line */
            background: none; /* Remove background, use border for dashed effect */
            border: none;
            border-left: 2px dashed #ccc; /* Create a vertical dashed line */
            margin: 0 5mm; /* Space between the two bill sections */
            padding: 0; /* No padding */
            display: flex; /* Make it a flex container to center its text */
            align-items: center; /* Vertically center the text */
            justify-content: center; /* Horizontally center the text */
            text-align: center;
            font-size: 8px; /* Smaller font for the divider text */
            color: #999;
            /* To make text vertical: */
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap; /* Prevent text from wrapping */
        }

        .print-btn-container {
            display: none;
        }

        /* Ensure all text is dark for readability */
        body, p, table, div, span, h1, h2, h3, h4, h5, h6 {
            color: #000 !important;
        }

        /* Print specific for payment-info-table */
        .payment-info-table th,
        .payment-info-table td {
            padding: 2.5px; /* Smaller padding for print */
            font-size: 7.5pt; /* Smaller font for print */
        }
    }
</style>
</head>
<body>

<div class="print-container">

    <div class="bill-section">
        <div class="top-left-date">🕒 समय: <?= $current_date ?></div>
        <div class="copy-label">OFFICE COPY</div>
        <div class="watermark"></div>

        <div class="header">
            <h1><?= htmlspecialchars($org_name) ?></h1>
            <h2>💧 जल ही जीवन है 💧</h2>
            <h3 style="color:#28a745; margin-top:10px;">✅ भुगतान रसीद ✅</h3>
            <div class="bill-id">बिल आईडी: <?= htmlspecialchars($bill_id) ?></div>
        </div>

        <div class="combined-details-summary">
            <div class="section-half">
                <h3>ग्राहक विवरण</h3>
                <table>
                    <tr><th>कनेक्शन नंबर</th><td><?= htmlspecialchars($display_con_no) ?></td></tr>
                    <tr><th>वार्ड नंबर</th><td><?= htmlspecialchars($display_ward_no) ?></td></tr>
                    <tr><th>ग्राहक का नाम</th><td><?= htmlspecialchars($display_owner_name) ?></td></tr>
                    <tr><th>मोबाइल नंबर</th><td><?= htmlspecialchars($display_mobile) ?></td></tr>
                </table>
            </div>

            <div class="section-half">
                <h3>बिल सारांश</h3>
                <table>
                    <tr><th>वर्तमान बिल राशि</th><td><?= $display_current_amount ?></td></tr>
                    <tr><th>पिछला बकाया</th><td><?= $display_arrear_balance ?></td></tr>
                    <?php if ($display_discount_amount_for_bill_summary > 0) : ?>
                    <tr><th>छूट</th><td>₹<?= number_format($display_discount_amount_for_bill_summary,2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($display_additional_charge > 0) : ?>
                    <tr><th>अतिरिक्त शुल्क</th><td>₹<?= number_format($display_additional_charge,2) ?></td></tr>
                    <?php endif; ?>
                    <tr class="total"><th>कुल देय राशि</th><td><?= $display_remaining_balance ?></td></tr>
                </table>
            </div>
        </div>

        <div class="details-card">
            <h3>भुगतान जानकारी</h3>
            <table class="payment-info-table">
                <tr>
                    <th>भुगतानकर्ता का नाम</th><td><?= htmlspecialchars($payer_name_display) ?></td>
                    <th>लेन-देन ID</th><td><?= htmlspecialchars($transaction_id_display) ?></td>
                </tr>
                <tr>
                    <th>भुगतान की तिथि/समय</th><td><?= htmlspecialchars($payment_date_display) ?></td>
                    <th>भुगतान की राशि</th><td><?= $payment_amount_display ?></td>
                </tr>
                <tr>
                    <th>भुगतान का प्रकार</th><td><?= htmlspecialchars($payment_type_text) ?></td>
                    <th>छूट की राशि</th><td><?= $discount_amount_display ?> (<?= $discount_percentage_display ?>)</td>
                </tr>
                <tr>
                    <th>भुगतान का तरीका</th><td><?= htmlspecialchars($payment_method_display) ?></td>
                    <th class="total">कुल भुगतान</th><td class="total"><?= $total_paid_with_discount_display ?></td>
                </tr>
                <tr>
                    <th>भुगतान किया किसने (Username)</th><td><?= htmlspecialchars($username_display) ?></td>
                    <td colspan="2"></td>
                </tr>
            </table>
        </div>


        <div class="footer">
            <p style="margin-top:10px; font-weight:bold;">किसी भी प्रश्न या शिकायत के लिए, संपर्क करें: <span style="color:#007bff;"><?= htmlspecialchars($helpline_number) ?></span></p>
            <div class="signature"><p>अधिकृत हस्ताक्षर: ___________________</p></div>
            <p class="note-section">
                **नोट:** कृपया भुगतान की अंतिम तिथि तक बिल का भुगतान करें। विलंब शुल्क लागू हो सकते हैं।<br>
                यह एक कंप्यूटर जनित रसीद है और इस पर हस्ताक्षर की आवश्यकता नहीं है।
            </p>
        </div>

        <div class="url-bottom-left">🔗 <?= htmlspecialchars($current_page_url) ?></div>
    </div>

    <div class="divider-line">--- ✂️ --- यह रेखा काटने हेतु है --- ✂️ ---</div>

    <div class="bill-section">
        <div class="top-left-date">🕒 समय: <?= $current_date ?></div>
        <div class="copy-label">CUSTOMER COPY</div>
        <div class="watermark"></div>

        <div class="header">
            <h1><?= htmlspecialchars($org_name) ?></h1>
            <h2>💧 जल ही जीवन है 💧</h2>
            <h3 style="color:#28a745; margin-top:10px;">✅ भुगतान रसीद ✅</h3>
            <div class="bill-id">बिल आईडी: <?= htmlspecialchars($bill_id) ?></div>
        </div>

        <div class="combined-details-summary">
            <div class="section-half">
                <h3>ग्राहक विवरण</h3>
                <table>
                    <tr><th>कनेक्शन नंबर</th><td><?= htmlspecialchars($display_con_no) ?></td></tr>
                    <tr><th>वार्ड नंबर</th><td><?= htmlspecialchars($display_ward_no) ?></td></tr>
                    <tr><th>ग्राहक का नाम</th><td><?= htmlspecialchars($display_owner_name) ?></td></tr>
                    <tr><th>मोबाइल नंबर</th><td><?= htmlspecialchars($display_mobile) ?></td></tr>
                </table>
            </div>

            <div class="section-half">
                <h3>बिल सारांश</h3>
                <table>
                    <tr><th>वर्तमान बिल राशि</th><td><?= $display_current_amount ?></td></tr>
                    <tr><th>पिछला बकाया</th><td><?= $display_arrear_balance ?></td></tr>
                    <?php if ($display_discount_amount_for_bill_summary > 0) : ?>
                    <tr><th>छूट</th><td>₹<?= number_format($display_discount_amount_for_bill_summary,2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($display_additional_charge > 0) : ?>
                    <tr><th>अतिरिक्त शुल्क</th><td>₹<?= number_format($display_additional_charge,2) ?></td></tr>
                    <?php endif; ?>
                    <tr class="total"><th>कुल देय राशि</th><td><?= $display_remaining_balance ?></td></tr>
                </table>
            </div>
        </div>

        <div class="details-card">
            <h3>भुगतान जानकारी</h3>
            <table class="payment-info-table">
                <tr>
                    <th>भुगतानकर्ता का नाम</th><td><?= htmlspecialchars($payer_name_display) ?></td>
                    <th>लेन-देन ID</th><td><?= htmlspecialchars($transaction_id_display) ?></td>
                </tr>
                <tr>
                    <th>भुगतान की तिथि/समय</th><td><?= htmlspecialchars($payment_date_display) ?></td>
                    <th>भुगतान की राशि</th><td><?= $payment_amount_display ?></td>
                </tr>
                <tr>
                    <th>भुगतान का प्रकार</th><td><?= htmlspecialchars($payment_type_text) ?></td>
                    <th>छूट की राशि</th><td><?= $discount_amount_display ?> (<?= $discount_percentage_display ?>)</td>
                </tr>
                <tr>
                    <th>भुगतान का तरीका</th><td><?= htmlspecialchars($payment_method_display) ?></td>
                    <th class="total">कुल भुगतान</th><td class="total"><?= $total_paid_with_discount_display ?></td>
                </tr>
                <tr>
                    <th>भुगतान किया किसने (Username)</th><td><?= htmlspecialchars($username_display) ?></td>
                    <td colspan="2"></td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p style="margin-top:10px; font-weight:bold;">किसी भी प्रश्न या शिकायत के लिए, संपर्क करें: <span style="color:#007bff;"><?= htmlspecialchars($helpline_number) ?></span></p>
            <div class="signature"><p>अधिकृत हस्ताक्षर: ___________________</p></div>
            <p class="note-section">
                **नोट:** कृपया भुगतान की अंतिम तिथि तक बिल का भुगतान करें। विलंब शुल्क लागू हो सकते हैं।<br>
                यह एक कंप्यूटर जनित रसीद है और इस पर हस्ताक्षर की आवश्यकता नहीं है।
            </p>
        </div>

        <div class="url-bottom-left">🔗 <?= htmlspecialchars($current_page_url) ?></div>
    </div>
</div>

<div class="print-btn-container">
    <button class="print-btn" onclick="window.print()">🖨️ प्रिंट करें</button><br><br>
    <a href="https://sunnydhaka.fwh.is/payment.php?con_no=<?= urlencode(htmlspecialchars($display_con_no)) ?>" class="back-btn">🔙 सूची पर वापस जाएँ</a>
</div>

</body>
</html>