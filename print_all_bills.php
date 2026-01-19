<?php
// --- START: HIGHLY AGGRESSIVE ERROR REPORTING AND MEMORY INCREASE ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
// **IMPORTANT:** Increase memory limit to 512MB for large PDF generation
ini_set('memory_limit', '512M'); 
// Ensure output buffering is active and clean
ob_start();
// --- END: HIGHLY AGGRESSIVE ERROR REPORTING AND MEMORY INCREASE ---

session_start();

// Security: Checks if the user is logged in.
// if (!isset($_SESSION['username'])) { header("Location: index.php"); exit(); }

// NOTE: Make sure your config.php file is included correctly and sets the $pdo object.
// सुनिश्चित करें कि config.php इसी फ़ोल्डर में है
include __DIR__ . "/config.php";

// --- Define Database Column Names Here ---
// WARNING: Database column names with spaces or special characters require backticks in SQL, 
// but PDO::FETCH_ASSOC will return keys without them.
$db_col_con_no          = '`connection No`';
$db_col_ward_no         = 'ward_no';
$db_col_owner_name      = '`Owner Name`';
$db_col_mobile          = 'Mobile';
$db_col_current_amount  = '`Current amount 2025-26`';
$db_col_arrear_balance  = '`Arrear Balance`';
$db_col_remaining_balance = 'remaining_balance';

// --- Define PDO Access Keys (No backticks) for fetched array ---
// NOTE: These keys MUST match the column names without backticks, as returned by PDO::FETCH_ASSOC.
$key_con_no          = 'connection No';
$key_ward_no         = 'ward_no';
$key_owner_name      = 'Owner Name';
$key_mobile          = 'Mobile';
$key_current_amount  = 'Current amount 2025-26';
$key_arrear_balance  = 'Arrear Balance';
$key_remaining_balance = 'remaining_balance';


// ERROR FIX: Corrected missing PDO:: scope operator
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// NEW: Get the single search query
// FIX: Changed default from 'null' to '' to prevent htmlspecialchars() deprecation warning (line 1190 fix).
$search_query = $_REQUEST['query'] ?? ''; 
$action = $_REQUEST['action'] ?? null;

// --- Data Fetching Logic ---
try {
    $sql_query = "SELECT
        $db_col_con_no,
        $db_col_ward_no,
        $db_col_owner_name,
        $db_col_mobile,
        $db_col_current_amount,
        $db_col_arrear_balance,
        $db_col_remaining_balance
        FROM bills";

    $where_conditions = [];
    $bind_params = [];

    // Check for single search criteria (Ward No. OR Connection No.)
    if (!empty($search_query)) {
        // Trim the search query and wrap it in % for LIKE search
        $search_term = "%" . trim($search_query) . "%";
        
        // Search in both Ward No and Connection No.
        $where_conditions[] = "($db_col_ward_no LIKE :query_ward OR $db_col_con_no LIKE :query_con)";
        $bind_params[':query_ward'] = $search_term;
        $bind_params[':query_con'] = $search_term;
    }
    
    if (!empty($where_conditions)) {
        $sql_query .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $stmt = $pdo->prepare($sql_query);
    
    // NOTE: PDO::PARAM_STR is used, but for variables this still works. 
    // Passing $bind_params directly to execute is often cleaner and avoids reference issues.
    $stmt->execute($bind_params); 
    
    $all_bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // FIX: Removed the die() call here for search not found, as it prevents download logic execution.
    // The message is now handled in the main HTML output block below.

} catch (PDOException $e) {
    // SECURITY: In a production environment, avoid exposing $e->getMessage() directly.
    die("<h2 style='text-align:center;color:red;margin-top:100px;'>डेटाबेस त्रुटि: " . $e->getMessage() . "</h2>");
}

date_default_timezone_set('Asia/Kolkata');
$current_date = date('d F Y, h:i:s A');
$due_date = date('d/m/Y', strtotime('+15 days'));

// --- Helper function to get the display value safely and consistently ---
function get_bill_data(array $bill, string $key_name, string $default = '-') {
    return $bill[$key_name] ?? $default;
}

// --- Direct HTML Download ---
if ($action === 'download_html' && !empty($all_bills)) {
    // Set headers for HTML file download
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="Water_Bills_' . date('Ymd_His') . '.html"');
    
    // Start HTML output
    echo '<!DOCTYPE html>
    <html lang="hi">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>जल बिल</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 10px;
            width: 100%;
        }
        .print-container {
            width: 210mm;
            min-height: 297mm;
            margin: 10px auto;
            background: #ffffff;
            padding: 5mm 10mm;
            page-break-after: always;
            position: relative;
        }
        .bills-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 8mm;
            height: 287mm;
            position: relative;
        }
        .bill-section {
            position: relative;
            border: 1.5px dashed #007bff;
            padding: 6mm;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            min-height: 135mm;
            display: flex;
            flex-direction: column;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 150px;
            height: 150px;
            opacity: 0.04;
            background: url(\'img1.png\') center/contain no-repeat;
            transform: translate(-50%, -50%);
            z-index: 0;
        }
        .top-left-date {
            position: absolute;
            top: 6px;
            left: 10px;
            font-size: 9px;
            color: #333333;
            z-index: 2;
            background: rgba(255,255,255,0.9);
            padding: 2px 5px;
            border-radius: 3px;
        }
        .header {
            text-align: center;
            position: relative;
            z-index: 1;
            margin-bottom: 8px;
            flex-shrink: 0;
        }
        .header h1 {
            margin: 0;
            color: #4dabf7;
            font-size: 16px;
            font-weight: bold;
            line-height: 1.2;
        }
        .header h2 {
            margin: 3px 0;
            color: #333333;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
        }
        .bill-id {
            margin-top: 4px;
            font-size: 10px;
            background: linear-gradient(135deg, #4dabf7, #339af0);
            color: #ffffff;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            position: relative;
            z-index: 1;
            background: white;
            font-size: 10px;
            flex-grow: 1;
        }
        table th, table td {
            padding: 5px 7px;
            border: 1px solid #aaaaaa;
            text-align: left;
            line-height: 1.3;
        }
        table th {
            background: linear-gradient(135deg, #4dabf7, #339af0);
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            width: 50%;
            font-size: 11px;
        }
        table td {
            background: #f8f9fa;
            font-weight: 500;
            font-size: 10px;
        }
        .total {
            background: #ffe3e3 !important;
            font-weight: bold;
        }
        .total th {
            background: linear-gradient(135deg, #ff6b6b, #fa5252) !important;
            font-size: 12px;
        }
        .total td {
            background: #ffc9c9 !important;
            font-size: 12px;
            font-weight: bold;
            color: #c92a2a;
        }
        .footer {
            margin-top: 12px;
            text-align: center;
            font-size: 10px;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        .footer p {
            margin: 5px 0;
            font-weight: 600;
            color: #dc3545;
            line-height: 1.2;
        }
        .signature {
            margin-top: 18px;
            text-align: right;
            padding-right: 15px;
        }
        .signature p {
            font-size: 10px;
            font-weight: bold;
            color: #333;
            line-height: 1.2;
        }
        .url-bottom-left {
            font-size: 8px;
            text-align: left;
            margin-top: 15px;
            color: #666666;
        }
        /* Cutting lines ONLY between bills */
        .cutting-line-horizontal {
            position: absolute;
            left: 0;
            right: 0;
            height: 2px;
            background: repeating-linear-gradient(90deg, #ff0000, #ff0000 8px, transparent 8px, transparent 16px);
            z-index: 10;
        }
        .cutting-line-vertical {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 2px;
            background: repeating-linear-gradient(180deg, #ff0000, #ff0000 8px, transparent 8px, transparent 16px);
            z-index: 10;
        }
        .cutting-line-middle-horizontal {
            top: 50%;
            transform: translateY(-50%);
        }
        .cutting-line-middle-vertical {
            left: 50%;
            transform: translateX(-50%);
        }
        /* Scissor icons at the ends of cutting lines */
        .scissor-icon {
            position: absolute;
            font-size: 12px;
            color: #ff0000;
            z-index: 11;
            background: white;
            padding: 2px;
            border-radius: 50%;
        }
        .scissor-left {
            left: 0px;
        }
        .scissor-right {
            right: 0px;
        }
        .scissor-top {
            top: 0px;
        }
        .scissor-bottom {
            bottom: 0px;
        }

        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .print-container {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 5mm 10mm !important;
                width: 210mm !important;
                min-height: 297mm !important;
                page-break-after: always !important;
            }
            .bill-section {
                border: 1.5px dashed #007bff !important;
                box-shadow: none !important;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }
    </style>
    </head>
    <body>';
    
    // Group bills into groups of 4 for 4 per page
    $bill_groups = array_chunk($all_bills, 4);
    
    foreach ($bill_groups as $group_index => $group) {
        echo '<div class="print-container">';
        echo '<div class="bills-grid">';
        
        // Add cutting lines ONLY between bills (middle horizontal and vertical)
        echo '<div class="cutting-line-horizontal cutting-line-middle-horizontal"></div>';
        echo '<div class="cutting-line-vertical cutting-line-middle-vertical"></div>';
        
        // Add scissor icons at the ends of cutting lines
        echo '<div class="scissor-icon scissor-left" style="top: 50%; transform: translateY(-50%);">✂️</div>';
        echo '<div class="scissor-icon scissor-right" style="top: 50%; transform: translateY(-50%);">✂️</div>';
        echo '<div class="scissor-icon scissor-top" style="left: 50%; transform: translateX(-50%);">✂️</div>';
        echo '<div class="scissor-icon scissor-bottom" style="left: 50%; transform: translateX(-50%);">✂️</div>';
        
        foreach ($group as $bill) {
            // FIX: Use defined keys without backticks for fetched array access
            $display_con_no = get_bill_data($bill, $key_con_no);
            $display_ward_no = get_bill_data($bill, $key_ward_no);
            $display_owner_name = get_bill_data($bill, $key_owner_name);
            $display_mobile = get_bill_data($bill, $key_mobile);
            
            // Cast to float for numeric operations
            $display_current_amount = (float)get_bill_data($bill, $key_current_amount, '0');
            $display_arrear_balance = (float)get_bill_data($bill, $key_arrear_balance, '0');
            $display_remaining_balance = (float)get_bill_data($bill, $key_remaining_balance, '0');
            
            $numeric_con_no_part = preg_replace('/[^0-9]/', '', $display_con_no);
            $bill_id_numeric = !empty($numeric_con_no_part) ? (int)$numeric_con_no_part : 0;
            $bill_id = "NPPNCO" . str_pad($bill_id_numeric, 3, '0', STR_PAD_LEFT);
            $url = "https://sunnydhaka.fwh.is/print_bill.php?id=" . urlencode($display_con_no);
            ?>
            
            <div class="bill-section">
                <div class="top-left-date">🕒 समय: <?= $current_date ?></div>
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
            <?php
        }
        
        // Fill remaining spaces if less than 4 bills
        $remaining_bills = 4 - count($group);
        for ($i = 0; $i < $remaining_bills; $i++) {
            echo '<div class="bill-section" style="visibility: hidden;"></div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    echo '</body>
    </html>';
    
    exit;
}

// --- Word Document Download ---
if ($action === 'download_word' && !empty($all_bills)) {
    // Set headers for Word file download
    header('Content-Type: application/vnd.ms-word');
    header('Content-Disposition: attachment; filename="Water_Bills_' . date('Ymd_His') . '.doc"');
    
    // Start Word document output
    echo '<!DOCTYPE html>
    <html lang="hi">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>जल बिल</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        .print-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 5mm 10mm;
            page-break-after: always;
            position: relative;
        }
        .bills-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 8mm;
            height: 287mm;
            position: relative;
        }
        .bill-section {
            position: relative;
            border: 1.5px dashed #007bff;
            padding: 6mm;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            min-height: 135mm;
            display: flex;
            flex-direction: column;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 150px;
            height: 150px;
            opacity: 0.04;
            /* Word compatibility is poor, removing background-url for better results */
            /* background: url(\'img1.png\') center/contain no-repeat; */
            transform: translate(-50%, -50%);
            z-index: 0;
        }
        .top-left-date {
            position: absolute;
            top: 6px;
            left: 10px;
            font-size: 9px;
            color: #333333;
            z-index: 2;
            background: rgba(255,255,255,0.9);
            padding: 2px 5px;
            border-radius: 3px;
        }
        .header {
            text-align: center;
            position: relative;
            z-index: 1;
            margin-bottom: 8px;
            flex-shrink: 0;
        }
        .header h1 {
            margin: 0;
            color: #4dabf7;
            font-size: 16px;
            font-weight: bold;
            line-height: 1.2;
        }
        .header h2 {
            margin: 3px 0;
            color: #333333;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
        }
        .bill-id {
            margin-top: 4px;
            font-size: 10px;
            /* Word compatibility is poor, simplifying background-linear-gradient */
            background: #4dabf7; 
            color: #ffffff;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            position: relative;
            z-index: 1;
            background: white;
            font-size: 10px;
            flex-grow: 1;
        }
        table th, table td {
            padding: 5px 7px;
            border: 1px solid #aaaaaa;
            text-align: left;
            line-height: 1.3;
        }
        table th {
            /* Word compatibility is poor, simplifying background-linear-gradient */
            background: #4dabf7;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            width: 50%;
            font-size: 11px;
        }
        table td {
            background: #f8f9fa;
            font-weight: 500;
            font-size: 10px;
        }
        .total {
            background: #ffe3e3 !important;
            font-weight: bold;
        }
        .total th {
            /* Word compatibility is poor, simplifying background-linear-gradient */
            background: #ff6b6b !important;
            font-size: 12px;
        }
        .total td {
            background: #ffc9c9 !important;
            font-size: 12px;
            font-weight: bold;
            color: #c92a2a;
        }
        .footer {
            margin-top: 12px;
            text-align: center;
            font-size: 10px;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }
        .footer p {
            margin: 5px 0;
            font-weight: 600;
            color: #dc3545;
            line-height: 1.2;
        }
        .signature {
            margin-top: 18px;
            text-align: right;
            padding-right: 15px;
        }
        .signature p {
            font-size: 10px;
            font-weight: bold;
            color: #333;
            line-height: 1.2;
        }
        .url-bottom-left {
            font-size: 8px;
            text-align: left;
            margin-top: 15px;
            color: #666666;
        }
        
        @page {
            size: A4 portrait;
            margin: 0;
        }
    </style>
    </head>
    <body>';
    
    // Group bills into groups of 4 for 4 per page
    $bill_groups = array_chunk($all_bills, 4);
    
    foreach ($bill_groups as $group_index => $group) {
        echo '<div class="print-container">';
        // Word document compatibility is poor with CSS grid and cutting lines, simplifying the structure
        echo '';
        echo '<div class="bills-grid" style="display: block; height: auto;">'; 
        
        foreach ($group as $bill) {
            // FIX: Use defined keys without backticks for fetched array access
            $display_con_no = get_bill_data($bill, $key_con_no);
            $display_ward_no = get_bill_data($bill, $key_ward_no);
            $display_owner_name = get_bill_data($bill, $key_owner_name);
            $display_mobile = get_bill_data($bill, $key_mobile);
            
            // Cast to float for numeric operations
            $display_current_amount = (float)get_bill_data($bill, $key_current_amount, '0');
            $display_arrear_balance = (float)get_bill_data($bill, $key_arrear_balance, '0');
            $display_remaining_balance = (float)get_bill_data($bill, $key_remaining_balance, '0');
            
            $numeric_con_no_part = preg_replace('/[^0-9]/', '', $display_con_no);
            $bill_id_numeric = !empty($numeric_con_no_part) ? (int)$numeric_con_no_part : 0;
            $bill_id = "NPPNCO" . str_pad($bill_id_numeric, 3, '0', STR_PAD_LEFT);
            $url = "https://sunnydhaka.fwh.is/print_bill.php?id=" . urlencode($display_con_no);
            ?>
            
            <div class="bill-section" style="margin-bottom: 8mm; float: left; width: 48%; /* Simplified layout for Word */"> 
                <div class="top-left-date">🕒 समय: <?= $current_date ?></div>
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
            <?php
        }
        
        // Clear float for the next row/page in word
        echo '<div style="clear: both;"></div>';

        // Word layout can be tricky. This ensures the container ends cleanly.
        echo '</div>'; 
        echo '</div>';
    }
    
    echo '</body>
    </html>';
    
    exit;
}

// --- HTML Output ---
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>जल बिल डाउनलोड</title>
<link rel="icon" type="image/png" href="favicon.png">

<style>
    /* CSS for web view - 4 BILLS PER PAGE (2x2 grid) */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Noto+Sans+Devanagari:wght@400;600&display=swap');
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    body {
        font-family: 'Poppins', 'Noto Sans Devanagari', sans-serif;
        background: #f0f0f0;
        margin: 0;
        padding: 0;
        width: 100%;
    }
    
    .main-header-round {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        text-align: center;
        padding: 20px 0;
        margin: 0 0 20px 0;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        border-bottom-left-radius: 50px;
        border-bottom-right-radius: 50px;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .header-title {
        margin: 0;
        font-size: 32px;
        letter-spacing: 2px;
        text-transform: uppercase;
        padding: 0 50px;
    }
    .header-icon {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        font-size: 24px;
        color: white;
        text-decoration: none;
        padding: 10px;
        transition: transform 0.2s;
    }
    .header-icon:hover {
        transform: translateY(-50%) scale(1.1);
    }
    .home-icon {
        left: 20px;
    }
    .logout-icon {
        right: 20px;
    }

    .top-controls-container {
        text-align: center;
        margin-top: 20px;
        padding: 15px;
        background: #e9ecef;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        position: relative;
        max-width: 900px;
        margin: 20px auto;
    }
    .search-form {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .top-controls-container label {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }
    .top-controls-container input[type="text"] {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 5px;
        font-size: 16px;
        width: 250px;
        text-align: center;
    }
    .top-controls-container button[type="submit"] {
        padding: 8px 15px;
        background: #28a745;
        color: #fff;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
        font-weight: 600;
    }
    .html-btn {
        background: #dc3545;
        color: #fff;
        padding: 12px 25px;
        font-size: 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
    }
    .word-btn {
        background: #28a745;
        color: #fff;
        padding: 12px 25px;
        font-size: 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
    }
    .html-btn:hover {
        background: #c82333;
        transform: translateY(-2px);
    }
    .word-btn:hover {
        background: #218838;
        transform: translateY(-2px);
    }

    /* Bill Styles - 4 BILLS PER PAGE (2x2 grid) */
    .print-container {
        width: 210mm;
        min-height: 297mm;
        margin: 10px auto;
        background: #ffffff;
        padding: 5mm 10mm;
        page-break-after: always;
        position: relative;
    }
    .bills-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        gap: 8mm;
        height: 287mm;
        position: relative;
    }
    .bill-section {
        position: relative;
        border: 1.5px dashed #007bff;
        padding: 6mm;
        border-radius: 8px;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        min-height: 135mm;
        display: flex;
        flex-direction: column;
    }
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 150px;
        height: 150px;
        opacity: 0.04;
        background: url('img1.png') center/contain no-repeat;
        transform: translate(-50%, -50%);
        z-index: 0;
    }
    .top-left-date {
        position: absolute;
        top: 6px;
        left: 10px;
        font-size: 9px;
        color: #333333;
        z-index: 2;
        background: rgba(255,255,255,0.9);
        padding: 2px 5px;
        border-radius: 3px;
    }
    .header {
        text-align: center;
        position: relative;
        z-index: 1;
        margin-bottom: 8px;
        flex-shrink: 0;
    }
    .header h1 {
        margin: 0;
        color: #4dabf7;
        font-size: 16px;
        font-weight: bold;
        line-height: 1.2;
    }
    .header h2 {
        margin: 3px 0;
        color: #333333;
        font-size: 14px;
        font-weight: 600;
        line-height: 1.2;
    }
    .bill-id {
        margin-top: 4px;
        font-size: 10px;
        background: linear-gradient(135deg, #4dabf7, #339af0);
        color: #ffffff;
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-weight: bold;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        position: relative;
        z-index: 1;
        background: white;
        font-size: 10px;
        flex-grow: 1;
    }
    table th, table td {
        padding: 5px 7px;
        border: 1px solid #aaaaaa;
        text-align: left;
        line-height: 1.3;
    }
    table th {
        background: linear-gradient(135deg, #4dabf7, #339af0);
        color: #ffffff;
        font-weight: bold;
        text-align: left;
        width: 50%;
        font-size: 11px;
    }
    table td {
        background: #f8f9fa;
        font-weight: 500;
        font-size: 10px;
    }
    .total {
        background: #ffe3e3 !important;
        font-weight: bold;
    }
    .total th {
        background: linear-gradient(135deg, #ff6b6b, #fa5252) !important;
        font-size: 12px;
    }
    .total td {
        background: #ffc9c9 !important;
        font-size: 12px;
        font-weight: bold;
        color: #c92a2a;
    }
    .footer {
        margin-top: 12px;
        text-align: center;
        font-size: 10px;
        position: relative;
        z-index: 1;
        flex-shrink: 0;
    }
    .footer p {
        margin: 5px 0;
        font-weight: 600;
        color: #dc3545;
        line-height: 1.2;
    }
    .signature {
        margin-top: 18px;
        text-align: right;
        padding-right: 15px;
    }
    .signature p {
        font-size: 10px;
        font-weight: bold;
        color: #333;
        line-height: 1.2;
    }
    .url-bottom-left {
        font-size: 8px;
        text-align: left;
        margin-top: 15px;
        color: #666666;
    }
    /* Cutting lines ONLY between bills */
    .cutting-line-horizontal {
        position: absolute;
        left: 0;
        right: 0;
        height: 2px;
        background: repeating-linear-gradient(90deg, #ff0000, #ff0000 8px, transparent 8px, transparent 16px);
        z-index: 10;
    }
    .cutting-line-vertical {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 2px;
        background: repeating-linear-gradient(180deg, #ff0000, #ff0000 8px, transparent 8px, transparent 16px);
        z-index: 10;
    }
    .cutting-line-middle-horizontal {
        top: 50%;
        transform: translateY(-50%);
    }
    .cutting-line-middle-vertical {
        left: 50%;
        transform: translateX(-50%);
    }
    /* Scissor icons at the ends of cutting lines */
    .scissor-icon {
        position: absolute;
        font-size: 12px;
        color: #ff0000;
        z-index: 11;
        background: white;
        padding: 2px;
        border-radius: 50%;
    }
    .scissor-left {
        left: 0px;
    }
    .scissor-right {
        right: 0px;
    }
    .scissor-top {
        top: 0px;
    }
    .scissor-bottom {
        bottom: 0px;
    }

    @media print {
        body {
            background: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .print-container {
            box-shadow: none !important;
            margin: 0 !important;
            padding: 5mm 10mm !important;
            width: 210mm !important;
            min-height: 297mm !important;
            page-break-after: always !important;
        }
        .bill-section {
            border: 1.5px dashed #007bff !important;
            box-shadow: none !important;
        }
        .no-print {
            display: none !important;
        }
        * {
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }

    @page {
        size: A4 portrait;
        margin: 0;
    }

    .no-bills-message {
        text-align: center;
        color: #dc3545;
        font-size: 20px;
        margin: 50px 0;
        padding: 20px;
        background: #ffe6e6;
        border-radius: 10px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }

    .bills-count {
        text-align: center;
        color: #28a745;
        font-size: 18px;
        font-weight: bold;
        margin: 10px 0;
        padding: 10px;
        background: #e8f5e8;
        border-radius: 5px;
    }

    /* HTML Loading */
    #htmlLoading, #wordLoading {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.95);
        z-index: 10000;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }
    
    .loading-logo {
        width: 120px;
        height: 120px;
        margin-bottom: 20px;
        background: url('img1.png') center/contain no-repeat;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
</head>
<body>

<div class="main-header-round no-print">
    <a href="https://sunnydhaka.fwh.is/jal.php" class="header-icon home-icon" title="Home">
        🏠
    </a>
    <h1 class="header-title">जल बिल</h1>
    <a href="logout.php" class="header-icon logout-icon" title="Logout">
        🚪
    </a>
</div>

<div class="top-controls-container no-print">
    <form class="search-form" action="" method="get">
        <label for="search_query">वार्ड/कनेक्शन नंबर खोजें:</label>
        <input type="text" id="search_query" name="query" value="<?= htmlspecialchars($search_query) ?>" placeholder="वार्ड या कनेक्शन नंबर दर्ज करें">
        <button type="submit">🔍 खोजें</button>
    </form>
    
    <?php if (!empty($all_bills)): ?>
    <a class="html-btn" href="?query=<?= urlencode($search_query) ?>&action=download_html" onclick="showHtmlLoading()">
        📄 HTML बिल डाउनलोड (<?= count($all_bills) ?> बिल)
    </a>
    <a class="word-btn" href="?query=<?= urlencode($search_query) ?>&action=download_word" onclick="showWordLoading()">
        📝 Word बिल डाउनलोड (<?= count($all_bills) ?> बिल)
    </a>
    <?php endif; ?>
</div>

<div id="htmlLoading" style="display: none;">
    <div class="loading-logo"></div>
    <div style="font-size: 24px; margin-bottom: 20px; color: #dc3545;">HTML बिल डाउनलोड हो रहा है...</div>
    <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #dc3545; border-radius: 50%; animation: spin 1s linear infinite;"></div>
    <div style="margin-top: 20px; font-size: 14px; color: #666;">
        कृपया प्रतीक्षा करें...
    </div>
</div>

<div id="wordLoading" style="display: none;">
    <div class="loading-logo"></div>
    <div style="font-size: 24px; margin-bottom: 20px; color: #28a745;">Word बिल डाउनलोड हो रहा है...</div>
    <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #28a745; border-radius: 50%; animation: spin 1s linear infinite;"></div>
    <div style="margin-top: 20px; font-size: 14px; color: #666;">
        कृपया प्रतीक्षा करें...
    </div>
</div>

<?php
if (!empty($all_bills)) {
    echo "<div class='bills-count no-print'>कुल बिल मिले: " . count($all_bills) . " | पेज: " . ceil(count($all_bills) / 4) . "</div>";
}

// FIX: Security fix: htmlspecialchars() is safe now as $search_query is guaranteed to be a string (or an empty string).
$message = "खोज शब्द <strong>" . htmlspecialchars($search_query) . "</strong> के लिए कोई बिल नहीं मिला।";

if (empty($all_bills) && !empty($search_query)) {
    echo "<div class='no-bills-message'>$message</div>";
} elseif (!empty($all_bills)) {
    // Group bills into groups of 4 for 4 per page
    $bill_groups = array_chunk($all_bills, 4);
    
    foreach ($bill_groups as $group_index => $group) {
        echo '<div class="print-container">';
        echo '<div class="bills-grid">';
        
        // Add cutting lines ONLY between bills (middle horizontal and vertical)
        echo '<div class="cutting-line-horizontal cutting-line-middle-horizontal"></div>';
        echo '<div class="cutting-line-vertical cutting-line-middle-vertical"></div>';
        
        // Add scissor icons at the ends of cutting lines
        echo '<div class="scissor-icon scissor-left" style="top: 50%; transform: translateY(-50%);">✂️</div>';
        echo '<div class="scissor-icon scissor-right" style="top: 50%; transform: translateY(-50%);">✂️</div>';
        echo '<div class="scissor-icon scissor-top" style="left: 50%; transform: translateX(-50%);">✂️</div>';
        echo '<div class="scissor-icon scissor-bottom" style="left: 50%; transform: translateX(-50%);">✂️</div>';
        
        foreach ($group as $bill) {
            // FIX: Use defined keys without backticks for fetched array access
            $display_con_no = get_bill_data($bill, $key_con_no);
            $display_ward_no = get_bill_data($bill, $key_ward_no);
            $display_owner_name = get_bill_data($bill, $key_owner_name);
            $display_mobile = get_bill_data($bill, $key_mobile);
            
            // Cast to float for numeric operations
            $display_current_amount = (float)get_bill_data($bill, $key_current_amount, '0');
            $display_arrear_balance = (float)get_bill_data($bill, $key_arrear_balance, '0');
            $display_remaining_balance = (float)get_bill_data($bill, $key_remaining_balance, '0');
            
            $numeric_con_no_part = preg_replace('/[^0-9]/', '', $display_con_no);
            $bill_id_numeric = !empty($numeric_con_no_part) ? (int)$numeric_con_no_part : 0;
            $bill_id = "NPPNCO" . str_pad($bill_id_numeric, 3, '0', STR_PAD_LEFT);
            $url = "https://sunnydhaka.fwh.is/print_bill.php?id=" . urlencode($display_con_no);
            ?>
            
            <div class="bill-section">
                <div class="top-left-date">🕒 समय: <?= $current_date ?></div>
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
            <?php
        }
        
        // Fill remaining spaces if less than 4 bills
        $remaining_bills = 4 - count($group);
        for ($i = 0; $i < $remaining_bills; $i++) {
            echo '<div class="bill-section" style="visibility: hidden;"></div>';
        }
        
        echo '</div>';
        echo '</div>';
    }
} elseif (empty($all_bills) && empty($search_query)) {
    echo "<div class='no-bills-message'>कोई बिल उपलब्ध नहीं है। कृपया खोज करें या डेटाबेस जांचें।</div>";
}
?>

<script>
// HTML Download Loading
function showHtmlLoading() {
    const htmlLoading = document.getElementById('htmlLoading');
    htmlLoading.style.display = 'flex';
    
    // Hide loading after 10 seconds (fallback)
    setTimeout(() => {
        htmlLoading.style.display = 'none';
    }, 10000);
}

// Word Download Loading
function showWordLoading() {
    const wordLoading = document.getElementById('wordLoading');
    wordLoading.style.display = 'flex';
    
    // Hide loading after 10 seconds (fallback)
    setTimeout(() => {
        wordLoading.style.display = 'none';
    }, 10000);
}

// Preload for better performance
window.addEventListener('DOMContentLoaded', function() {
    // Preload watermark image
    const img = new Image();
    img.src = 'img1.png';
    
    console.log('Page loaded. Total bills: <?= count($all_bills) ?>');
});
</script>

</body>
</html>