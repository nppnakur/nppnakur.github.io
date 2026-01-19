<?php
session_start();
include 'config.php'; 
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Kolkata');

// Function to create tax_payments table if it doesn't exist
function createTaxPaymentsTable($pdo) {
    try {
        // First check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'tax_payments'")->rowCount() > 0;
        
        if (!$tableExists) {
            $sql = "CREATE TABLE `tax_payments` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `property_id` varchar(100) NOT NULL,
                `amount` decimal(12,2) NOT NULL,
                `payment_method` varchar(50) NOT NULL,
                `paid_on` datetime NOT NULL,
                `username` varchar(100) NOT NULL,
                `payment_type` varchar(50) NOT NULL,
                `tax_type` varchar(50) DEFAULT NULL,
                `payer_name` varchar(255) NOT NULL,
                `discount_amount` decimal(12,2) DEFAULT 0.00,
                `discount_percentage` decimal(5,2) DEFAULT 0.00,
                `transaction_id` varchar(100) NOT NULL,
                `current_house_amount` decimal(12,2) DEFAULT 0.00,
                `arrear_house_amount` decimal(12,2) DEFAULT 0.00,
                `current_water_amount` decimal(12,2) DEFAULT 0.00,
                `arrear_water_amount` decimal(12,2) DEFAULT 0.00,
                PRIMARY KEY (`id`),
                KEY `property_id` (`property_id`),
                KEY `paid_on` (`paid_on`),
                KEY `transaction_id` (`transaction_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            $pdo->exec($sql);
            error_log("tax_payments table created successfully.");
        }
        
        // Check and add new columns if they don't exist
        $columns = ['current_house_amount', 'arrear_house_amount', 'current_water_amount', 'arrear_water_amount'];
        
        foreach ($columns as $column) {
            $check = $pdo->query("SHOW COLUMNS FROM tax_payments LIKE '$column'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE tax_payments ADD COLUMN `$column` decimal(12,2) DEFAULT 0.00");
                error_log("Column $column added to tax_payments table.");
            }
        }
        
    } catch (PDOException $e) {
        error_log("Error with tax_payments table: " . $e->getMessage());
    }
}

// Call function to ensure table exists
createTaxPaymentsTable($pdo);

// --- Define PHP Array Keys ---
$php_key_con_no        = 'property_id';
$php_key_owner_name    = 'owner_name';
$php_key_ward_no       = 'ward_name';
$php_key_mobile        = 'mobile';
$php_key_current_amount = 'current_house_tax';
$php_key_arrear_balance = 'arrear_house_tax';
$php_key_current_water_tax = 'current_water_tax';
$php_key_arrear_water_tax = 'arrear_water_tax';

// --- Define Actual Database Column Names ---
$sql_col_property_id   = '`Property ID`';
$sql_col_owner_name    = '`Owner\'s Name`';
$sql_col_mobile        = '`Mobile No`';
$sql_col_ward_name     = '`Ward Name`';
$sql_col_mohalla_name  = '`Mohalla Name`';
$sql_col_house_no      = '`House No`';
$sql_col_property_type = '`Property Type`';

$sql_col_current_house_tax   = '`Current House Tax`';
$sql_col_arrear_house_tax    = '`Arrear House Tax`';
$sql_col_current_water_tax   = '`Current Water Tax`';
$sql_col_arrear_water_tax    = '`Arrear Water Tax`';

// Calculate remaining balance dynamically
$sql_remaining_balance = "(IFNULL(`Current House Tax`, 0) + IFNULL(`Arrear House Tax`, 0) + IFNULL(`Current Water Tax`, 0) + IFNULL(`Arrear Water Tax`, 0))";

// 1. Authentication
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Generate transaction ID function
function generateTransactionID($property_id) {
    $timestamp = time();
    $random = rand(1000, 9999);
    $hash = substr(md5($property_id . $timestamp . $random), 0, 8);
    return 'TXN-' . strtoupper($hash) . '-' . date('YmdHis', $timestamp);
}

// 2. View Receipt Functionality
if (isset($_GET['view_receipt'])) {
    $payment_id = $_GET['view_receipt'];
    
    $stmt = $pdo->prepare("SELECT 
        tp.*,
        p.`Property ID` as property_id_full,
        p.`Owner's Name` as owner_name,
        p.`Ward Name` as ward_name,
        p.`Mohalla Name` as mohalla_name,
        p.`House No` as house_no,
        p.`Property Type` as property_type,
        DATE_FORMAT(tp.paid_on, '%d-%m-%Y %h:%i %p') as formatted_date
        FROM tax_payments tp
        LEFT JOIN properties p ON tp.property_id = p.`Property ID`
        WHERE tp.id = ?");
    $stmt->execute([$payment_id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($receipt) {
        echo '<!DOCTYPE html>
        <html lang="hi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Receipt</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
            <style>
                body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; margin: 0; }
                .receipt-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; }
                .receipt-header { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 25px; text-align: center; }
                .receipt-header h1 { margin: 0 0 10px 0; font-size: 24px; display: flex; align-items: center; justify-content: center; gap: 10px; }
                .receipt-id { background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px; font-family: monospace; margin-top: 10px; display: inline-block; }
                .receipt-body { padding: 25px; }
                .receipt-section { margin-bottom: 25px; }
                .receipt-title { color: #007bff; font-size: 18px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #eee; display: flex; align-items: center; gap: 8px; }
                .receipt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
                .receipt-item { margin-bottom: 12px; }
                .receipt-label { font-weight: 600; color: #666; font-size: 14px; margin-bottom: 5px; display: flex; align-items: center; gap: 6px; }
                .receipt-value { font-size: 15px; color: #333; padding: 8px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #007bff; }
                .amount-display { background: #e6f2ff; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0; border: 2px solid #007bff; }
                .amount-main { font-size: 32px; font-weight: bold; color: #007bff; margin: 10px 0; }
                .amount-details { font-size: 14px; color: #666; }
                .receipt-footer { background: #f8f9fa; padding: 20px; text-align: center; border-top: 2px solid #eee; }
                .action-buttons { display: flex; gap: 15px; justify-content: center; margin-top: 20px; }
                .btn { padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; transition: all 0.3s; }
                .btn-print { background: #28a745; color: white; }
                .btn-print:hover { background: #218838; transform: translateY(-2px); box-shadow: 0 5px 12px rgba(40,167,69,0.3); }
                .btn-close { background: #6c757d; color: white; }
                .btn-close:hover { background: #5a6268; transform: translateY(-2px); box-shadow: 0 5px 12px rgba(108,117,125,0.3); }
                @media print {
                    body { background: white; }
                    .receipt-container { box-shadow: none; margin: 0; }
                    .action-buttons { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="receipt-container">
                <div class="receipt-header">
                    <h1><i class="fas fa-receipt"></i> Payment Receipt</h1>
                    <div class="receipt-id">
                        <i class="fas fa-hashtag"></i> '.$receipt['transaction_id'].'
                    </div>
                </div>
                
                <div class="receipt-body">
                    <div class="receipt-section">
                        <h2 class="receipt-title"><i class="fas fa-info-circle"></i> Property Information</h2>
                        <div class="receipt-grid">
                            <div class="receipt-item">
                                <div class="receipt-label"><i class="fas fa-hashtag"></i> Property ID</div>
                                <div class="receipt-value">'.$receipt['property_id_full'].'</div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label"><i class="fas fa-user"></i> Owner Name</div>
                                <div class="receipt-value">'.$receipt['owner_name'].'</div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label"><i class="fas fa-map-marker-alt"></i> Location</div>
                                <div class="receipt-value">'.$receipt['ward_name'].' - '.$receipt['mohalla_name'].'</div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label"><i class="fas fa-home"></i> House No</div>
                                <div class="receipt-value">'.$receipt['house_no'].'</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="receipt-section">
                        <h2 class="receipt-title"><i class="fas fa-credit-card"></i> Payment Details</h2>
                        <div class="amount-display">
                            <div style="font-size: 14px; color: #666;">Paid Amount</div>
                            <div class="amount-main">₹'.number_format($receipt['amount'], 2).'</div>';
        
        if ($receipt['discount_amount'] > 0) {
            echo '<div class="amount-details">
                    <i class="fas fa-tag"></i> Discount: ₹'.number_format($receipt['discount_amount'], 2).' ('.$receipt['discount_percentage'].'%)
                  </div>';
        }
        
        echo '</div>
                        <div class="receipt-grid">
                            <div class="receipt-item">
                                <div class="receipt-label"><i class="fas fa-calendar-alt"></i> Payment Date & Time</div>
                                <div class="receipt-value">'.$receipt['formatted_date'].'</div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label"><i class="fas fa-wallet"></i> Payment Method</div>
                                <div class="receipt-value">'.$receipt['payment_method'].'</div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label"><i class="fas fa-user-circle"></i> Payer Name</div>
                                <div class="receipt-value">'.$receipt['payer_name'].'</div>
                            </div>
                            <div class="receipt-item">
                                <div class="receipt-label"><i class="fas fa-user-tie"></i> Received By</div>
                                <div class="receipt-value">'.$receipt['username'].'</div>
                            </div>
                        </div>';
        
        if ($receipt['payment_type'] === 'combined' && isset($receipt['current_house_amount'])) {
            echo '<div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #6f42c1;">
                    <div style="font-weight: bold; color: #6f42c1; margin-bottom: 8px;"><i class="fas fa-layer-group"></i> Combined Payment Breakdown</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 13px;">
                        <div><strong>House Tax (Current):</strong> ₹'.number_format($receipt['current_house_amount'], 2).'</div>
                        <div><strong>House Tax (Arrear):</strong> ₹'.number_format($receipt['arrear_house_amount'], 2).'</div>
                        <div><strong>Water Tax (Current):</strong> ₹'.number_format($receipt['current_water_amount'], 2).'</div>
                        <div><strong>Water Tax (Arrear):</strong> ₹'.number_format($receipt['arrear_water_amount'], 2).'</div>
                    </div>
                  </div>';
        }
        
        echo '</div>
                </div>
                
                <div class="receipt-footer">
                    <div style="color: #666; font-size: 14px; margin-bottom: 10px;">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i> Payment Verified & Processed
                    </div>
                    <div style="color: #999; font-size: 12px;">
                        Generated on: '.date('d-m-Y h:i A').'
                    </div>
                    <div class="action-buttons">
                        <button onclick="window.print()" class="btn btn-print">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                        <button onclick="window.close()" class="btn btn-close">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        exit();
    }
}

// 3. Delete Single Payment - FIXED COMBINED PAYMENT RESTORE
if (isset($_GET['delete_payment'])) {
    $payment_id = $_GET['delete_payment'];
    $property_id = $_GET['property_id'] ?? '';
    
    // Check if password is provided
    if (!isset($_GET['password'])) {
        // Show password prompt
        echo '<!DOCTYPE html>
        <html lang="hi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Confirm Delete Payment</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
            <style>
                body { font-family: Arial, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 20px; }
                .password-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; width: 100%; max-width: 400px; }
                h2 { color: #dc3545; margin-bottom: 20px; font-size: 22px; display: flex; align-items: center; justify-content: center; gap: 10px; }
                .warning-icon { font-size: 40px; color: #dc3545; margin-bottom: 20px; }
                .warning-message { color: #666; margin-bottom: 25px; font-size: 15px; line-height: 1.6; }
                .form-group { margin-bottom: 20px; text-align: left; }
                label { display: block; margin-bottom: 8px; font-weight: 600; color: #495057; }
                input[type="password"] { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 16px; box-sizing: border-box; }
                input[type="password"]:focus { border-color: #007bff; outline: none; }
                .action-buttons { display: flex; gap: 12px; margin-top: 20px; }
                .btn { padding: 12px 25px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; flex: 1; justify-content: center; }
                .btn-delete { background: #dc3545; color: white; }
                .btn-delete:hover { background: #c82333; }
                .btn-cancel { background: #6c757d; color: white; }
                .btn-cancel:hover { background: #5a6268; }
                @media (max-width: 768px) { .password-container { padding: 20px; } .action-buttons { flex-direction: column; } .btn { width: 100%; } }
            </style>
        </head>
        <body>
            <div class="password-container">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2>Delete Payment</h2>
                <div class="warning-message">
                    <p><strong>Warning:</strong> This action will permanently delete this payment record and restore the tax balance.</p>
                    <p>To confirm deletion, please enter the admin password:</p>
                </div>
                
                <form method="get">
                    <input type="hidden" name="delete_payment" value="'.htmlspecialchars($payment_id).'">
                    <input type="hidden" name="property_id" value="'.htmlspecialchars($property_id).'">
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Admin Password:</label>
                        <input type="password" id="password" name="password" required placeholder="Enter admin password" autocomplete="off">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-delete">
                            <i class="fas fa-trash"></i> Delete Payment
                        </button>
                        <a href="?property_id='.htmlspecialchars($property_id).'" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </body>
        </html>';
        exit();
    } else {
        // Verify password
        $password = $_GET['password'];
        $correct_password = "admin123";
        
        if ($password !== $correct_password) {
            $_SESSION['error'] = "Error: Invalid password. Payment deletion failed.";
            header("Location: ".$_SERVER['PHP_SELF']."?property_id=".htmlspecialchars($property_id));
            exit();
        }
        
        // Proceed with deletion if password is correct
        try {
            $pdo->beginTransaction();
            
            // Get the payment details
            $stmt = $pdo->prepare("SELECT * FROM tax_payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception("Payment not found.");
            }
            
            // Check if payment has new columns
            $has_new_columns = isset($payment['current_house_amount']);
            
            // Calculate total amount (amount + discount)
            $total_amount = $payment['amount'] + ($payment['discount_amount'] ?? 0);
            
            // DEBUG: Log payment details
            error_log("DEBUG: Deleting payment ID: " . $payment_id);
            error_log("DEBUG: Payment type: " . $payment['payment_type']);
            error_log("DEBUG: Total amount to restore: " . $total_amount);
            
            // Restore tax amount based on payment type
            if ($payment['payment_type'] === 'house_tax') {
                if ($payment['tax_type'] === 'current') {
                    $upd = $pdo->prepare("UPDATE properties SET
                        {$sql_col_current_house_tax} = IFNULL({$sql_col_current_house_tax}, 0) + ?
                        WHERE {$sql_col_property_id} = ?");
                    $upd->execute([$total_amount, $payment['property_id']]);
                    error_log("DEBUG: Restored ₹{$total_amount} to Current House Tax");
                } else if ($payment['tax_type'] === 'arrear') {
                    $upd = $pdo->prepare("UPDATE properties SET
                        {$sql_col_arrear_house_tax} = IFNULL({$sql_col_arrear_house_tax}, 0) + ?
                        WHERE {$sql_col_property_id} = ?");
                    $upd->execute([$total_amount, $payment['property_id']]);
                    error_log("DEBUG: Restored ₹{$total_amount} to Arrear House Tax");
                }
            } else if ($payment['payment_type'] === 'water_tax') {
                if ($payment['tax_type'] === 'current') {
                    $upd = $pdo->prepare("UPDATE properties SET
                        {$sql_col_current_water_tax} = IFNULL({$sql_col_current_water_tax}, 0) + ?
                        WHERE {$sql_col_property_id} = ?");
                    $upd->execute([$total_amount, $payment['property_id']]);
                    error_log("DEBUG: Restored ₹{$total_amount} to Current Water Tax");
                } else if ($payment['tax_type'] === 'arrear') {
                    $upd = $pdo->prepare("UPDATE properties SET
                        {$sql_col_arrear_water_tax} = IFNULL({$sql_col_arrear_water_tax}, 0) + ?
                        WHERE {$sql_col_property_id} = ?");
                    $upd->execute([$total_amount, $payment['property_id']]);
                    error_log("DEBUG: Restored ₹{$total_amount} to Arrear Water Tax");
                }
            } else if ($payment['payment_type'] === 'combined') {
                if ($has_new_columns && isset($payment['current_house_amount'])) {
                    // Get the actual paid amounts from the payment record
                    $current_house_amount = $payment['current_house_amount'] ?? 0;
                    $arrear_house_amount = $payment['arrear_house_amount'] ?? 0;
                    $current_water_amount = $payment['current_water_amount'] ?? 0;
                    $arrear_water_amount = $payment['arrear_water_amount'] ?? 0;
                    
                    $discount_amount = $payment['discount_amount'] ?? 0;
                    
                    // Calculate total paid amount (before discount)
                    $total_paid_before_discount = $current_house_amount + $arrear_house_amount + $current_water_amount + $arrear_water_amount;
                    
                    if ($total_paid_before_discount > 0) {
                        // Distribute discount proportionally to all tax types
                        $discount_ch = ($discount_amount * ($current_house_amount / $total_paid_before_discount));
                        $discount_ah = ($discount_amount * ($arrear_house_amount / $total_paid_before_discount));
                        $discount_cw = ($discount_amount * ($current_water_amount / $total_paid_before_discount));
                        $discount_aw = $discount_amount - $discount_ch - $discount_ah - $discount_cw;
                    } else {
                        $discount_ch = $discount_ah = $discount_cw = $discount_aw = 0;
                    }
                    
                    // DEBUG: Log combined payment restoration
                    error_log("DEBUG: Combined Payment Restoration:");
                    error_log("DEBUG: Original Payment: CH: {$current_house_amount}, AH: {$arrear_house_amount}, CW: {$current_water_amount}, AW: {$arrear_water_amount}");
                    error_log("DEBUG: Discount: {$discount_amount}");
                    error_log("DEBUG: Discount Distribution: CH: {$discount_ch}, AH: {$discount_ah}, CW: {$discount_cw}, AW: {$discount_aw}");
                    error_log("DEBUG: Total to restore - CH: " . ($current_house_amount + $discount_ch));
                    error_log("DEBUG: Total to restore - AH: " . ($arrear_house_amount + $discount_ah));
                    error_log("DEBUG: Total to restore - CW: " . ($current_water_amount + $discount_cw));
                    error_log("DEBUG: Total to restore - AW: " . ($arrear_water_amount + $discount_aw));
                    
                    // Add amounts back with distributed discount
                    $upd = $pdo->prepare("UPDATE properties SET
                        {$sql_col_current_house_tax} = IFNULL({$sql_col_current_house_tax}, 0) + ?,
                        {$sql_col_arrear_house_tax} = IFNULL({$sql_col_arrear_house_tax}, 0) + ?,
                        {$sql_col_current_water_tax} = IFNULL({$sql_col_current_water_tax}, 0) + ?,
                        {$sql_col_arrear_water_tax} = IFNULL({$sql_col_arrear_water_tax}, 0) + ?
                        WHERE {$sql_col_property_id} = ?");
                    
                    $upd->execute([
                        $current_house_amount + $discount_ch,
                        $arrear_house_amount + $discount_ah,
                        $current_water_amount + $discount_cw,
                        $arrear_water_amount + $discount_aw,
                        $payment['property_id']
                    ]);
                    
                    error_log("DEBUG: Successfully restored combined payment");
                    
                } else {
                    // For old structure without breakdown columns
                    // Distribute total amount equally among all 4 tax types
                    $upd = $pdo->prepare("UPDATE properties SET
                        {$sql_col_current_house_tax} = IFNULL({$sql_col_current_house_tax}, 0) + ?,
                        {$sql_col_arrear_house_tax} = IFNULL({$sql_col_arrear_house_tax}, 0) + ?,
                        {$sql_col_current_water_tax} = IFNULL({$sql_col_current_water_tax}, 0) + ?,
                        {$sql_col_arrear_water_tax} = IFNULL({$sql_col_arrear_water_tax}, 0) + ?
                        WHERE {$sql_col_property_id} = ?");
                    
                    $quarter = $total_amount / 4;
                    $upd->execute([
                        $quarter,
                        $quarter,
                        $quarter,
                        $quarter,
                        $payment['property_id']
                    ]);
                    error_log("DEBUG: Restored old combined payment: ₹{$quarter} to each tax type");
                }
            }
            
            // Delete the payment
            $del = $pdo->prepare("DELETE FROM tax_payments WHERE id = ?");
            $del->execute([$payment_id]);
            
            $pdo->commit();
            $_SESSION['message'] = "Payment has been successfully deleted and tax balance restored.";
            header("Location: ".$_SERVER['PHP_SELF']."?property_id=".$payment['property_id']);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error: ".$e->getMessage();
            header("Location: ".$_SERVER['PHP_SELF']."?property_id=".htmlspecialchars($property_id));
            exit();
        }
    }
}

// 4. Enhanced Search Functionality
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search_term = '%'.trim($_GET['search']).'%';
    $stmt = $pdo->prepare("SELECT
        {$sql_col_property_id} AS property_id,
        {$sql_col_owner_name} AS owner_name,
        {$sql_col_ward_name} AS ward_name,
        {$sql_col_mohalla_name} AS mohalla_name,
        {$sql_col_house_no} AS house_no,
        {$sql_col_mobile} AS mobile,
        {$sql_col_property_type} AS property_type,
        {$sql_remaining_balance} AS total_due
        FROM properties
        WHERE {$sql_col_property_id} LIKE ? 
        OR {$sql_col_owner_name} LIKE ? 
        OR {$sql_col_mobile} LIKE ?
        OR {$sql_col_house_no} LIKE ?
        OR {$sql_col_ward_name} LIKE ?
        OR {$sql_col_mohalla_name} LIKE ?
        OR {$sql_col_property_type} LIKE ?
        OR CONCAT({$sql_col_ward_name}, ' ', {$sql_col_mohalla_name}) LIKE ?
        OR CONCAT({$sql_col_owner_name}, ' ', {$sql_col_mobile}) LIKE ?
        OR CONCAT({$sql_col_property_id}, ' ', {$sql_col_owner_name}) LIKE ?
        ORDER BY {$sql_col_owner_name}
        LIMIT 100");
    
    $stmt->execute([
        $search_term, $search_term, $search_term, $search_term, 
        $search_term, $search_term, $search_term, $search_term,
        $search_term, $search_term
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- HTML for Search Results ---
    
    echo '<!DOCTYPE html>
    <html lang="hi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Search Results - Property Tax System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; margin: 0; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 25px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
            h1 { color: #007bff; text-align: center; margin-bottom: 25px; font-size: 26px; }
            .search-box { display: flex; margin-bottom: 25px; gap: 12px; }
            .search-box input { flex: 1; padding: 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; }
            .search-box button { padding: 15px 25px; background: #007bff; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; display: flex; align-items: center; gap: 10px; font-weight: bold; }
            .search-box button:hover { background: #0056b3; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
            .results-count { margin-bottom: 20px; color: #666; font-size: 15px; padding: 10px 0; border-bottom: 2px solid #eee; }
            .results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 25px; margin-bottom: 25px; }
            .result-card { border: none; border-radius: 12px; padding: 25px; background: white; transition: all 0.3s ease; display: flex; flex-direction: column; position: relative; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .result-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.15); transform: translateY(-5px); }
            .property-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
            .property-id { font-weight: bold; color: #007bff; font-size: 20px; display: flex; align-items: center; gap: 10px; }
            .total-due-badge { background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 8px 15px; border-radius: 25px; font-size: 15px; font-weight: bold; box-shadow: 0 3px 8px rgba(220,53,69,0.3); }
            .property-details { flex: 1; margin-bottom: 25px; }
            .detail-row { display: flex; margin-bottom: 12px; align-items: flex-start; }
            .detail-label { font-weight: 600; color: #555; min-width: 140px; font-size: 15px; display: flex; align-items: center; gap: 8px; }
            .detail-value { color: #333; flex: 1; font-size: 15px; word-break: break-word; }
            .property-actions { display: flex; gap: 15px; margin-top: auto; }
            .action-btn { flex: 1; padding: 15px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 10px; font-size: 15px; font-weight: 500; transition: all 0.3s; }
            .payment-btn { background: linear-gradient(135deg, #28a745, #218838); color: white; }
            .history-btn { background: linear-gradient(135deg, #17a2b8, #138496); color: white; }
            .action-btn:hover { opacity: 0.95; transform: translateY(-3px); box-shadow: 0 5px 12px rgba(0,0,0,0.2); }
            .bottom-actions { display: flex; justify-content: center; gap: 25px; margin-top: 35px; padding-top: 30px; border-top: 2px solid #eee; }
            .bottom-btn { padding: 16px 35px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 12px; font-size: 17px; font-weight: 500; transition: all 0.3s; }
            .bottom-btn:hover { background: #5a6268; text-decoration: none; transform: translateY(-3px); box-shadow: 0 5px 12px rgba(0,0,0,0.2); }
            .bottom-btn.primary { background: linear-gradient(135deg, #007bff, #0056b3); }
            .bottom-btn.primary:hover { background: linear-gradient(135deg, #0056b3, #004085); }
            .home-btn { background: linear-gradient(135deg, #28a745, #218838); }
            .home-btn:hover { background: linear-gradient(135deg, #218838, #1e7e34); }
            .no-results { text-align: center; padding: 70px 25px; color: #666; font-size: 20px; background: #f8f9fa; border-radius: 12px; margin: 35px 0; border: 2px dashed #dee2e6; }
            .no-results i { font-size: 70px; color: #dc3545; margin-bottom: 25px; display: block; opacity: 0.7; }
            .search-hint { text-align: center; color: #6c757d; font-size: 15px; margin-top: 15px; margin-bottom: 25px; font-style: italic; }
            @media (max-width: 992px) { .results-grid { grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); } }
            @media (max-width: 768px) {
                .container { padding: 20px; } .search-box { flex-direction: column; } .search-box input { width: 100%; }
                .results-grid { grid-template-columns: 1fr; } .property-actions { flex-direction: column; }
                .bottom-actions { flex-direction: column; align-items: center; } .bottom-btn { width: 100%; text-align: center; justify-content: center; }
                .detail-row { flex-direction: column; } .detail-label { min-width: 100%; margin-bottom: 5px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1><i class="fas fa-search"></i> Property Search Results</h1>

            <form method="get" class="search-box">
                <input type="text" name="search" value="'.htmlspecialchars($_GET['search']).'" 
                       placeholder="Search by Property ID, Owner Name, Mobile, Ward, Mohalla, House No, Property Type..." required>
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>';

    if (count($results) > 0) {
        $search_original = htmlspecialchars($_GET['search']);
        
        echo '<div class="search-hint">
                <i class="fas fa-lightbulb"></i> You can search by: Property ID, Owner Name, Mobile Number, Ward, Mohalla, House Number, or Property Type
              </div>';
        
        echo '<div class="results-count">
                <i class="fas fa-info-circle"></i> Found <strong>'.count($results).'</strong> properties matching "<strong>'.$search_original.'</strong>"
              </div>';
        
        echo '<div class="results-grid">';
        
        foreach ($results as $row) {
            $total_due = isset($row['total_due']) ? number_format($row['total_due'], 2) : '0.00';
            $owner_name = htmlspecialchars($row['owner_name']);
            $property_id = htmlspecialchars($row['property_id']);
            $mobile = htmlspecialchars($row['mobile'] ?? 'N/A');
            $ward_name = htmlspecialchars($row['ward_name'] ?? '');
            $mohalla_name = htmlspecialchars($row['mohalla_name'] ?? '');
            $house_no = htmlspecialchars($row['house_no'] ?? 'N/A');
            $property_type = htmlspecialchars($row['property_type'] ?? 'N/A');
            
            echo '<div class="result-card">
                    <div class="property-header">
                        <div class="property-id">
                            <i class="fas fa-hashtag"></i> '.$property_id.'
                        </div>
                        <div class="total-due-badge">₹'.$total_due.' Due</div>
                    </div>
                    
                    <div class="property-details">
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-user"></i> Owner:</span>
                            <span class="detail-value">'.$owner_name.'</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-home"></i> House No:</span>
                            <span class="detail-value">'.$house_no.'</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Location:</span>
                            <span class="detail-value">'.$ward_name.' - '.$mohalla_name.'</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-phone"></i> Mobile:</span>
                            <span class="detail-value">'.$mobile.'</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-tag"></i> Type:</span>
                            <span class="detail-value">'.$property_type.'</span>
                        </div>
                    </div>
                    
                    <div class="property-actions">
                        <a href="?property_id='.urlencode($row['property_id']).'" class="action-btn payment-btn">
                            <i class="fas fa-rupee-sign"></i> Make Payment
                        </a>
                        <a href="?property_id='.urlencode($row['property_id']).'" class="action-btn history-btn">
                            <i class="fas fa-history"></i> View History
                        </a>
                    </div>
                </div>';
        }
        
        echo '</div>';
        
    } else {
        echo '<div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No Properties Found</h3>
                <p>No properties found matching: "<strong>'.htmlspecialchars($_GET['search']).'</strong>"</p>
                <p style="margin-top: 20px; font-size: 15px;">Try searching with different keywords like:</p>
                <ul style="text-align: left; display: inline-block; margin-top: 15px; font-size: 14px;">
                    <li>Property ID (e.g., PROP001)</li>
                    <li>Owner Name</li>
                    <li>Mobile Number</li>
                    <li>Ward or Mohalla Name</li>
                    <li>House Number</li>
                </ul>
              </div>';
    }

    echo '<div class="bottom-actions">
            <a href="?search=1" class="bottom-btn primary">
                <i class="fas fa-redo"></i> New Search
            </a>
            <a href="https://sunnydhaka.fwh.is/house_tax_dashboard.php" class="bottom-btn home-btn">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
        </div>
    </body>
    </html>';
    exit();
}

// 5. Get Property ID (If no ID and no search, show search form)
if (!isset($_GET['property_id']) && !isset($_POST['property_id']) && !isset($_SESSION['last_property_id'])) {
    echo '<!DOCTYPE html>
    <html lang="hi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Find Property - Tax Payment System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex; 
                justify-content: center; 
                align-items: center; 
                height: 100vh; 
                margin: 0; 
                padding: 20px;
            }
            .search-container { 
                background: white; 
                padding: 40px; 
                border-radius: 15px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
                text-align: center; 
                width: 100%; 
                max-width: 500px; 
            }
            h1 { color: #007bff; margin-bottom: 20px; font-size: 28px; }
            .logo { font-size: 60px; color: #007bff; margin-bottom: 20px; }
            .description { color: #666; margin-bottom: 30px; font-size: 16px; line-height: 1.6; }
            .search-form { margin-top: 20px; }
            .search-form input { 
                width: 100%; padding: 15px; margin-bottom: 15px; border: 2px solid #ddd; 
                border-radius: 8px; font-size: 16px; box-sizing: border-box; transition: border-color 0.3s;
            }
            .search-form input:focus { border-color: #007bff; outline: none; }
            .search-form button { 
                background: #007bff; color: white; border: none; padding: 15px 30px; 
                border-radius: 8px; font-size: 16px; cursor: pointer; width: 100%; 
                font-weight: bold; transition: background 0.3s; display: flex; 
                align-items: center; justify-content: center; gap: 10px;
            }
            .search-form button:hover { background: #0056b3; }
            .home-btn {
                margin-top: 20px; padding: 12px 25px; background: #28a745; 
                color: white; text-decoration: none; border-radius: 6px; 
                display: inline-flex; align-items: center; gap: 8px; transition: background 0.3s;
            }
            .home-btn:hover { background: #218838; text-decoration: none; }
            .features { 
                margin-top: 30px; text-align: left; background: #f8f9fa;
                padding: 20px; border-radius: 8px; border-left: 4px solid #007bff;
            }
            .feature { display: flex; align-items: center; margin-bottom: 12px; color: #555; }
            .feature i { margin-right: 12px; color: #28a745; font-size: 18px; }
            @media (max-width: 768px) {
                .search-container { padding: 25px; } h1 { font-size: 24px; } .logo { font-size: 50px; }
            }
        </style>
    </head>
    <body>
        <div class="search-container">
            <div class="logo">
                <i class="fas fa-landmark"></i>
            </div>
            <h1>Property Tax Payment System</h1>
            <p class="description">Search for property to make tax payment</p>

            <form method="get" class="search-form">
                <input type="text" name="search" placeholder="Search properties..." required>
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>

            <a href="https://sunnydhaka.fwh.is/house_tax_dashboard.php" class="home-btn">
                <i class="fas fa-home"></i> Back to Home
            </a>

            <div class="features">
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>House Tax & Water Tax Payment</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Current & Arrears Payment</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Payment History Tracking</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Multiple Payment Methods</span>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit();
}

// 6. Fetch Property Data
$property_id_php_var = $_POST['property_id'] ?? $_GET['property_id'] ?? ($_SESSION['last_property_id'] ?? '');
$_SESSION['last_property_id'] = $property_id_php_var;

$stmt = $pdo->prepare("SELECT
    {$sql_col_property_id} AS property_id,
    {$sql_col_owner_name} AS owner_name,
    {$sql_col_ward_name} AS ward_name,
    {$sql_col_mohalla_name} AS mohalla_name,
    {$sql_col_house_no} AS house_no,
    {$sql_col_mobile} AS mobile,
    {$sql_col_property_type} AS property_type,
    {$sql_col_current_house_tax} AS current_house_tax,
    {$sql_col_arrear_house_tax} AS arrear_house_tax,
    {$sql_col_current_water_tax} AS current_water_tax,
    {$sql_col_arrear_water_tax} AS arrear_water_tax,
    {$sql_remaining_balance} AS total_due
    FROM properties
    WHERE {$sql_col_property_id} = ?");
$stmt->execute([$property_id_php_var]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    echo '<!DOCTYPE html>
    <html lang="hi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Property Not Found</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; padding: 20px; }
            .error-container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; width: 100%; max-width: 500px; }
            h1 { color: #dc3545; margin-bottom: 20px; }
            .error-icon { font-size: 60px; color: #dc3545; margin-bottom: 20px; }
            .error-message { color: #666; margin-bottom: 25px; font-size: 16px; line-height: 1.6; }
            .action-buttons { display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; }
            .action-btn { padding: 12px 25px; background: #007bff; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: background 0.3s; }
            .action-btn:hover { background: #0056b3; text-decoration: none; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
            .home-btn { background: #28a745; }
            .home-btn:hover { background: #218838; }
            @media (max-width: 768px) { .error-container { padding: 25px; } .action-buttons { flex-direction: column; } .action-btn { width: 100%; justify-content: center; } }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1>Property Not Found</h1>
            <div class="error-message">
                <p>No property record found</p>
            </div>
            <div class="action-buttons">
                <button onclick="window.location.href=\'?search=1\'" class="action-btn">
                    <i class="fas fa-search"></i> Search Again
                </button>
                <a href="https://sunnydhaka.fwh.is/house_tax_dashboard.php" class="action-btn home-btn">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    </body>
    </html>';
    exit();
}

// Calculate tax amounts with rounding
$current_house_tax = round((float)($property['current_house_tax'] ?? 0), 2);
$arrear_house_tax = round((float)($property['arrear_house_tax'] ?? 0), 2);
$current_water_tax = round((float)($property['current_water_tax'] ?? 0), 2);
$arrear_water_tax = round((float)($property['arrear_water_tax'] ?? 0), 2);
$total_due_amount = round((float)($property['total_due'] ?? 0), 2);

// 7. Enhanced Payment History Query
$payQ = $pdo->prepare("SELECT 
    id, 
    property_id, 
    amount, 
    payment_method, 
    paid_on, 
    username, 
    payment_type, 
    tax_type, 
    payer_name, 
    discount_amount, 
    discount_percentage, 
    transaction_id,
    current_house_amount,
    arrear_house_amount,
    current_water_amount,
    arrear_water_amount,
    DATE_FORMAT(paid_on, '%d-%m-%Y %h:%i %p') as formatted_date
    FROM tax_payments 
    WHERE property_id = ? 
    ORDER BY paid_on DESC");
$payQ->execute([$property_id_php_var]);
$payments = $payQ->fetchAll(PDO::FETCH_ASSOC);

// Calculate total paid amount
$total_paid = 0;
foreach ($payments as $payment) {
    $total_paid += round((float)$payment['amount'], 2);
}

// Check if database has new columns
$has_new_columns = false;
try {
    $test_query = $pdo->prepare("SHOW COLUMNS FROM tax_payments LIKE 'current_house_amount'");
    $test_query->execute();
    $has_new_columns = $test_query->rowCount() > 0;
} catch (Exception $e) {
    $has_new_columns = false;
}

// Generate unique transaction ID for new payments
$new_transaction_id = generateTransactionID($property_id_php_var);

// 8. Payment Submission Handler
if (isset($_POST['submit_payment'])) {
    $property_id_pay = $_POST['property_id'];
    $amount = round((float)$_POST['amount'], 2);
    $payment_type = $_POST['payment_type'];
    $tax_type = $_POST['tax_type'] ?? null;
    $payer_name = trim($_POST['payer_name']);
    $payment_method = $_POST['payment_method'];
    $discount_percentage = round((float)$_POST['discount_percentage'], 2);
    $discount_amount = round((float)$_POST['discount_amount'], 2);
    $transaction_id = $_POST['transaction_id'];
    $is_combined = (int)$_POST['is_combined'];
    
    // Combined amounts
    $current_house_amount = $is_combined ? round((float)($_POST['current_house_amount'] ?? 0), 2) : 0;
    $arrear_house_amount = $is_combined ? round((float)($_POST['arrear_house_amount'] ?? 0), 2) : 0;
    $current_water_amount = $is_combined ? round((float)($_POST['current_water_amount'] ?? 0), 2) : 0;
    $arrear_water_amount = $is_combined ? round((float)($_POST['arrear_water_amount'] ?? 0), 2) : 0;

    // Basic validation
    if (empty($payer_name) || empty($payment_method) || $amount <= 0) {
        $_SESSION['error'] = "Please fill all required fields correctly.";
        header("Location: ".$_SERVER['PHP_SELF']."?property_id=".urlencode($property_id_pay));
        exit();
    }

    // Calculate base amount (amount + discount)
    $base_amount = $amount + $discount_amount;
    
    // Fetch CURRENT tax balances directly from database using same query as earlier
    $stmt = $pdo->prepare("SELECT 
        {$sql_col_current_house_tax} as current_house_tax,
        {$sql_col_arrear_house_tax} as arrear_house_tax,
        {$sql_col_current_water_tax} as current_water_tax,
        {$sql_col_arrear_water_tax} as arrear_water_tax
        FROM properties WHERE {$sql_col_property_id} = ?");
    $stmt->execute([$property_id_pay]);
    $current_tax_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_tax_row) {
        $_SESSION['error'] = "Property ID not found during payment process.";
        header("Location: ".$_SERVER['PHP_SELF']."?property_id=".urlencode($property_id_pay));
        exit();
    }
    
    // Get current balances
    $db_ch_tax = round((float)$current_tax_row['current_house_tax'], 2);
    $db_ah_tax = round((float)$current_tax_row['arrear_house_tax'], 2);
    $db_cw_tax = round((float)$current_tax_row['current_water_tax'], 2);
    $db_aw_tax = round((float)$current_tax_row['arrear_water_tax'], 2);
    
    try {
        $pdo->beginTransaction();

        $update_query = "";
        $update_params = [];
        $payment_insert_combined_columns = "";
        $payment_insert_combined_placeholders = "";
        $payment_insert_params = [];
        
        if ($is_combined) {
            // For combined payment
            $current_house_amount = min(max($current_house_amount, 0), $db_ch_tax);
            $arrear_house_amount = min(max($arrear_house_amount, 0), $db_ah_tax);
            $current_water_amount = min(max($current_water_amount, 0), $db_cw_tax);
            $arrear_water_amount = min(max($arrear_water_amount, 0), $db_aw_tax);
            
            $adj_total_base = $current_house_amount + $arrear_house_amount + $current_water_amount + $arrear_water_amount;
            
            if ($adj_total_base <= 0) {
                $_SESSION['error'] = "Please select at least one tax amount to pay.";
                header("Location: ".$_SERVER['PHP_SELF']."?property_id=".urlencode($property_id_pay));
                exit();
            }

            $base_amount = $adj_total_base;
            if ($discount_percentage > 0) {
                $discount_amount = $base_amount * ($discount_percentage / 100);
                $amount = $base_amount - $discount_amount;
            } else {
                $amount = $base_amount;
                $discount_amount = 0;
            }

            $update_query = "UPDATE properties SET
                {$sql_col_current_house_tax} = GREATEST(IFNULL({$sql_col_current_house_tax}, 0) - ?, 0),
                {$sql_col_arrear_house_tax} = GREATEST(IFNULL({$sql_col_arrear_house_tax}, 0) - ?, 0),
                {$sql_col_current_water_tax} = GREATEST(IFNULL({$sql_col_current_water_tax}, 0) - ?, 0),
                {$sql_col_arrear_water_tax} = GREATEST(IFNULL({$sql_col_arrear_water_tax}, 0) - ?, 0)
                WHERE {$sql_col_property_id} = ?";
                
            $update_params = [
                $current_house_amount, 
                $arrear_house_amount, 
                $current_water_amount, 
                $arrear_water_amount, 
                $property_id_pay
            ];

            if ($has_new_columns) {
                $payment_insert_combined_columns = ", current_house_amount, arrear_house_amount, current_water_amount, arrear_water_amount";
                $payment_insert_combined_placeholders = ", ?, ?, ?, ?";
                $payment_insert_params[] = $current_house_amount;
                $payment_insert_params[] = $arrear_house_amount;
                $payment_insert_params[] = $current_water_amount;
                $payment_insert_params[] = $arrear_water_amount;
            }
            
        } else {
            // Single payment logic
            $tax_column = "";
            $tax_balance = 0;

            if ($payment_type === 'house_tax') {
                if ($tax_type === 'current') {
                    $tax_column = $sql_col_current_house_tax;
                    $tax_balance = $db_ch_tax;
                } else if ($tax_type === 'arrear') {
                    $tax_column = $sql_col_arrear_house_tax;
                    $tax_balance = $db_ah_tax;
                }
            } elseif ($payment_type === 'water_tax') {
                if ($tax_type === 'current') {
                    $tax_column = $sql_col_current_water_tax;
                    $tax_balance = $db_cw_tax;
                } else if ($tax_type === 'arrear') {
                    $tax_column = $sql_col_arrear_water_tax;
                    $tax_balance = $db_aw_tax;
                }
            } else {
                $_SESSION['error'] = "Invalid payment type selected.";
                header("Location: ".$_SERVER['PHP_SELF']."?property_id=".urlencode($property_id_pay));
                exit();
            }
            
            if (empty($tax_column)) {
                $_SESSION['error'] = "Invalid tax type selected.";
                header("Location: ".$_SERVER['PHP_SELF']."?property_id=".urlencode($property_id_pay));
                exit();
            }
            
            $base_amount = $amount + $discount_amount;
            
            if ($base_amount <= 0 && $tax_balance > 0) {
                $base_amount = $tax_balance;
                if ($discount_percentage > 0) {
                    $discount_amount = $base_amount * ($discount_percentage / 100);
                    $amount = $base_amount - $discount_amount;
                } else {
                    $amount = $base_amount;
                    $discount_amount = 0;
                }
            }
            
            if ($base_amount > $tax_balance) {
                $base_amount = $tax_balance;
                if ($discount_percentage > 0) {
                    $discount_amount = $base_amount * ($discount_percentage / 100);
                    $amount = $base_amount - $discount_amount;
                } else {
                    $amount = $base_amount;
                    $discount_amount = 0;
                }
            }
            
            if ($base_amount <= 0 || $amount <= 0 || $tax_balance <= 0) {
                if ($tax_balance > 0) {
                    $base_amount = $tax_balance;
                    if ($discount_percentage > 0) {
                        $discount_amount = $base_amount * ($discount_percentage / 100);
                        $amount = $base_amount - $discount_amount;
                    } else {
                        $amount = $base_amount;
                        $discount_amount = 0;
                    }
                } else {
                    $_SESSION['error'] = "This tax amount is already zero. Please select another tax type.";
                    header("Location: ".$_SERVER['PHP_SELF']."?property_id=".urlencode($property_id_pay));
                    exit();
                }
            }

            $update_query = "UPDATE properties SET
                {$tax_column} = GREATEST(IFNULL({$tax_column}, 0) - ?, 0)
                WHERE {$sql_col_property_id} = ?";
                
            $update_params = [
                $base_amount, 
                $property_id_pay
            ];
        }

        // Execute property update
        if (!empty($update_query)) {
            $upd = $pdo->prepare($update_query);
            if (!$upd->execute($update_params)) {
                throw new Exception("Failed to update property tax balance.");
            }
            
            if ($upd->rowCount() == 0) {
                throw new Exception("Property not found or no changes made.");
            }
        }

        // Insert Payment Record
        $current_datetime = date('Y-m-d H:i:s');
        
        $insert_query = "INSERT INTO tax_payments (
            property_id, 
            amount, 
            payment_method, 
            paid_on, 
            username, 
            payment_type, 
            tax_type, 
            payer_name, 
            discount_amount, 
            discount_percentage,
            transaction_id
            {$payment_insert_combined_columns}
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            {$payment_insert_combined_placeholders}
        )";

        $insert_params = array_merge([
            $property_id_pay,
            $amount,
            $payment_method,
            $current_datetime,
            $_SESSION['username'],
            $payment_type,
            $tax_type,
            $payer_name,
            $discount_amount,
            $discount_percentage,
            $transaction_id,
        ], $payment_insert_params);

        $ins = $pdo->prepare($insert_query);
        if (!$ins->execute($insert_params)) {
            throw new Exception("Failed to insert payment record.");
        }

        $pdo->commit();
        $_SESSION['message'] = "Payment successfully recorded! Transaction ID: " . $transaction_id;
        header("Location: ".$_SERVER['PHP_SELF']."?property_id=".urlencode($property_id_pay));
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Payment Error: " . $e->getMessage();
        header("Location: ".$_SERVER['PHP_SELF']."?property_id=".urlencode($property_id_pay));
        exit();
    }
}

// 9. Display Property and Payment Form
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Tax Payment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        .main-container {
            max-width: 1300px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .header-section {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header-left h1 {
            margin: 0;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .property-id-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            margin-top: 10px;
            display: inline-block;
            backdrop-filter: blur(10px);
        }
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-search {
            background: #28a745;
            color: white;
        }
        .btn-search:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.2);
        }
        .btn-home {
            background: #17a2b8;
            color: white;
        }
        .btn-home:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.2);
        }
        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
        }
        @media (max-width: 992px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }
        }
        .property-section, .payment-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #eaeaea;
        }
        .section-title {
            color: #007bff;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .property-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-value {
            font-size: 15px;
            color: #333;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .summary-cards {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }
        .summary-card {
            flex: 1;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .summary-card.due {
            background: linear-gradient(135deg, #ffe0e0 0%, #ffcccc 100%);
            border: 2px solid #ffcccc;
        }
        .summary-card.paid {
            background: linear-gradient(135deg, #e0fff0 0%, #ccffdd 100%);
            border: 2px solid #ccffdd;
        }
        .summary-card h3 {
            margin: 0 0 12px 0;
            font-size: 16px;
            color: #555;
        }
        .summary-card .amount {
            font-size: 28px;
            font-weight: bold;
        }
        .summary-card.due .amount {
            color: #dc3545;
        }
        .summary-card.paid .amount {
            color: #28a745;
        }
        .tax-section {
            margin-bottom: 25px;
        }
        .tax-section-title {
            color: #495057;
            font-size: 17px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
            padding-left: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tax-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        @media (max-width: 768px) {
            .tax-cards-grid {
                grid-template-columns: 1fr;
            }
        }
        .tax-card {
            padding: 20px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #e9ecef;
            position: relative;
        }
        .tax-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .tax-card.selected {
            border-color: #007bff;
            background: #e6f2ff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.2);
        }
        .tax-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f8f9fa;
            border: 2px dashed #6c757d;
        }
        .tax-card.disabled:hover {
            transform: none;
            box-shadow: none;
        }
        .tax-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .tax-title {
            font-weight: 600;
            font-size: 16px;
        }
        .tax-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        .current-badge {
            background: #28a745;
            color: white;
        }
        .arrear-badge {
            background: #dc3545;
            color: white;
        }
        .tax-amount {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .tax-amount.current {
            color: #28a745;
        }
        .tax-amount.arrear {
            color: #dc3545;
        }
        .tax-action {
            text-align: right;
        }
        .pay-now-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        .pay-now-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40,167,69,0.3);
        }
        .paid-status {
            color: #28a745;
            font-size: 13px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .combined-payment-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 3px dashed #dee2e6;
        }
        .combined-btn {
            background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }
        .combined-btn:hover {
            background: linear-gradient(135deg, #5a32a3 0%, #d81b60 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(111,66,193,0.3);
        }
        .combined-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #6c757d;
        }
        .combined-btn:disabled:hover {
            transform: none;
            box-shadow: none;
            background: #6c757d;
        }
        .payment-form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ced4da;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        .form-input:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(0,123,255,0.2);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        .submit-btn {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            margin-top: 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .submit-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(40,167,69,0.3);
        }
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #6c757d;
        }
        .combined-fields {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            display: none;
            margin-top: 15px;
        }
        .combined-summary {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #cce5ff;
            margin-bottom: 20px;
        }
        .combined-summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 14px;
        }
        .combined-summary-grid div {
            padding: 5px 0;
        }
        .combined-field-group {
            margin-bottom: 15px;
        }
        .combined-field-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        .amount-input-group {
            display: flex;
            gap: 8px;
        }
        .amount-input-group input {
            flex: 1;
        }
        .error-message {
            color: #dc3545;
            font-size: 13px;
            margin-top: 5px;
            display: none;
        }
        .selected-tax-display {
            background: #f0f8ff;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #007bff;
            margin-bottom: 20px;
            display: none;
        }
        .alert-message {
            padding: 20px;
            margin: 20px 30px;
            border: 2px solid transparent;
            border-radius: 10px;
            font-size: 15px;
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-color: #c3e6cb;
        }
        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-color: #f5c6cb;
        }
        .history-section {
            margin-top: 30px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 10px;
        }
        .history-table th {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            font-size: 14px;
        }
        .history-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        .history-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .history-table tr:hover {
            background: #e9ecef;
        }
        .no-history {
            text-align: center;
            padding: 50px 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 3px dashed #dee2e6;
            margin-top: 20px;
        }
        .time-display {
            font-family: monospace;
            color: #6c757d;
            font-size: 13px;
            white-space: nowrap;
        }
        .action-icons {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .action-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .action-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .view-icon {
            background: #17a2b8;
            color: white;
        }
        .view-icon:hover {
            background: #138496;
        }
        .delete-icon {
            background: #dc3545;
            color: white;
        }
        .delete-icon:hover {
            background: #c82333;
        }
        .payment-type-text {
            font-weight: bold;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }
        .payment-details-small {
            font-size: 12px;
            color: #666;
        }
        .discount-display {
            font-size: 12px;
            color: #28a745;
            margin-top: 3px;
        }
        .zero-tax-message {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #ffeaa7;
            text-align: center;
            margin-bottom: 20px;
            font-size: 16px;
        }
        .clickable {
            cursor: pointer !important;
        }
        .tax-card.clickable:hover {
            background: #f8f9fa;
        }
        .payment-type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 5px;
        }
        .badge-house-current {
            background: #28a745;
            color: white;
        }
        .badge-house-arrear {
            background: #dc3545;
            color: white;
        }
        .badge-water-current {
            background: #17a2b8;
            color: white;
        }
        .badge-water-arrear {
            background: #ffc107;
            color: #212529;
        }
        .badge-combined {
            background: #6f42c1;
            color: white;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-section">
            <div class="header-left">
                <h1><i class="fas fa-money-check-alt"></i> Property Tax Payment</h1>
                <div class="property-id-badge">
                    <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($property['property_id']); ?>
                </div>
            </div>
            <div class="header-actions">
                <a href="?search=1" class="btn btn-search">
                    <i class="fas fa-search"></i> Search
                </a>
                <a href="https://sunnydhaka.fwh.is/house_tax_dashboard.php" class="btn btn-home">
                    <i class="fas fa-home"></i> Home
                </a>
            </div>
        </div>

        <?php 
        if (isset($_SESSION['message'])): 
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
        ?>
            <div class="alert-message alert-success" id="successMessage">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <script>
                setTimeout(function() {
                    document.getElementById('successMessage').style.display = 'none';
                }, 5000);
            </script>
        <?php endif; ?>
        
        <?php 
        if (isset($_SESSION['error'])): 
            $error = $_SESSION['error'];
            unset($_SESSION['error']);
        ?>
            <div class="alert-message alert-error" id="errorMessage">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <script>
                setTimeout(function() {
                    document.getElementById('errorMessage').style.display = 'none';
                }, 5000);
            </script>
        <?php endif; ?>

        <div class="content-wrapper">
            <div class="property-section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i> Property Details
                </h2>
                
                <div class="property-info-grid">
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-user"></i> Owner Name
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($property['owner_name']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-map-marker-alt"></i> Location
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($property['ward_name']); ?> - <?php echo htmlspecialchars($property['mohalla_name']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-home"></i> House No
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($property['house_no']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-phone"></i> Mobile
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($property['mobile']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">
                            <i class="fas fa-tag"></i> Property Type
                        </span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($property['property_type']); ?>
                        </span>
                    </div>
                </div>

                <div class="summary-cards">
                    <div class="summary-card due">
                        <h3>Total Due Amount</h3>
                        <div class="amount">₹<?php echo number_format($total_due_amount, 2); ?></div>
                    </div>
                    <div class="summary-card paid">
                        <h3>Total Paid Amount</h3>
                        <div class="amount">₹<?php echo number_format($total_paid, 2); ?></div>
                    </div>
                </div>

                <?php if ($total_due_amount <= 0): ?>
                <div class="zero-tax-message">
                    <i class="fas fa-check-circle"></i> All taxes are already paid for this property. No payment required.
                </div>
                <?php endif; ?>

                <div class="tax-section">
                    <h3 class="tax-section-title">
                        <i class="fas fa-house-chimney-crack"></i> House Tax
                    </h3>
                    <div class="tax-cards-grid">
                        <div class="tax-card <?php echo $current_house_tax <= 0 ? 'disabled' : 'clickable'; ?>" 
                             onclick="<?php echo $current_house_tax > 0 ? "selectTaxType('house', 'current', $current_house_tax)" : "return false"; ?>">
                            <div class="tax-card-header">
                                <div class="tax-title">Current House Tax</div>
                                <div class="tax-badge current-badge">Current</div>
                            </div>
                            <div class="tax-amount current">₹<?php echo number_format($current_house_tax, 2); ?></div>
                            <div class="tax-action">
                                <?php if ($current_house_tax > 0): ?>
                                <button type="button" class="pay-now-btn" onclick="event.stopPropagation(); payTax('house', 'current', <?php echo $current_house_tax; ?>)">
                                    <i class="fas fa-rupee-sign"></i> Pay Now
                                </button>
                                <?php else: ?>
                                <div class="paid-status">
                                    <i class="fas fa-check-circle"></i> Paid
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tax-card <?php echo $arrear_house_tax <= 0 ? 'disabled' : 'clickable'; ?>" 
                             onclick="<?php echo $arrear_house_tax > 0 ? "selectTaxType('house', 'arrear', $arrear_house_tax)" : "return false"; ?>">
                            <div class="tax-card-header">
                                <div class="tax-title">Arrear House Tax</div>
                                <div class="tax-badge arrear-badge">Arrear</div>
                            </div>
                            <div class="tax-amount arrear">₹<?php echo number_format($arrear_house_tax, 2); ?></div>
                            <div class="tax-action">
                                <?php if ($arrear_house_tax > 0): ?>
                                <button type="button" class="pay-now-btn" onclick="event.stopPropagation(); payTax('house', 'arrear', <?php echo $arrear_house_tax; ?>)">
                                    <i class="fas fa-rupee-sign"></i> Pay Now
                                </button>
                                <?php else: ?>
                                <div class="paid-status">
                                    <i class="fas fa-check-circle"></i> Paid
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tax-section">
                    <h3 class="tax-section-title">
                        <i class="fas fa-tint"></i> Water Tax
                    </h3>
                    <div class="tax-cards-grid">
                        <div class="tax-card <?php echo $current_water_tax <= 0 ? 'disabled' : 'clickable'; ?>" 
                             onclick="<?php echo $current_water_tax > 0 ? "selectTaxType('water', 'current', $current_water_tax)" : "return false"; ?>">
                            <div class="tax-card-header">
                                <div class="tax-title">Current Water Tax</div>
                                <div class="tax-badge current-badge">Current</div>
                            </div>
                            <div class="tax-amount current">₹<?php echo number_format($current_water_tax, 2); ?></div>
                            <div class="tax-action">
                                <?php if ($current_water_tax > 0): ?>
                                <button type="button" class="pay-now-btn" onclick="event.stopPropagation(); payTax('water', 'current', <?php echo $current_water_tax; ?>)">
                                    <i class="fas fa-rupee-sign"></i> Pay Now
                                </button>
                                <?php else: ?>
                                <div class="paid-status">
                                    <i class="fas fa-check-circle"></i> Paid
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tax-card <?php echo $arrear_water_tax <= 0 ? 'disabled' : 'clickable'; ?>" 
                             onclick="<?php echo $arrear_water_tax > 0 ? "selectTaxType('water', 'arrear', $arrear_water_tax)" : "return false"; ?>">
                            <div class="tax-card-header">
                                <div class="tax-title">Arrear Water Tax</div>
                                <div class="tax-badge arrear-badge">Arrear</div>
                            </div>
                            <div class="tax-amount arrear">₹<?php echo number_format($arrear_water_tax, 2); ?></div>
                            <div class="tax-action">
                                <?php if ($arrear_water_tax > 0): ?>
                                <button type="button" class="pay-now-btn" onclick="event.stopPropagation(); payTax('water', 'arrear', <?php echo $arrear_water_tax; ?>)">
                                    <i class="fas fa-rupee-sign"></i> Pay Now
                                </button>
                                <?php else: ?>
                                <div class="paid-status">
                                    <i class="fas fa-check-circle"></i> Paid
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($total_due_amount > 0): ?>
                <div class="combined-payment-section">
                    <h3 class="tax-section-title">
                        <i class="fas fa-layer-group"></i> Combined Payment
                    </h3>
                    <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                        Pay all remaining taxes at once with a single transaction
                    </p>
                    <button type="button" class="combined-btn" onclick="selectCombinedPayment()" id="combinedPaymentBtn">
                        <i class="fas fa-layer-group"></i> Pay All Remaining Taxes ₹<?php echo number_format($total_due_amount, 2); ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="payment-section">
                <h2 class="section-title">
                    <i class="fas fa-credit-card"></i> Payment Details
                </h2>

                <form id="paymentForm" method="post" onsubmit="return validatePaymentForm()">
                    <input type="hidden" name="property_id" value="<?php echo htmlspecialchars($property['property_id']); ?>">
                    <input type="hidden" name="transaction_id" value="<?php echo $new_transaction_id; ?>">
                    <input type="hidden" name="is_combined" id="is_combined" value="0">
                    <input type="hidden" name="payment_type" id="payment_type" value="">
                    <input type="hidden" name="tax_type" id="tax_type" value="">
                    <input type="hidden" name="submit_payment" value="1">

                    <div id="selectedTaxDisplay" class="selected-tax-display">
                        <div style="font-weight: bold; font-size: 16px; color: #007bff;">
                            <i class="fas fa-file-invoice-dollar"></i> Selected Tax Type
                        </div>
                        <div id="selectedTaxText" style="font-size: 14px; margin-top: 5px;"></div>
                    </div>

                    <div id="combinedFields" class="combined-fields">
                        <h4 style="margin-top: 0; margin-bottom: 15px; color: #6f42c1; font-size: 16px;">
                            <i class="fas fa-hand-holding-usd"></i> Select Amounts to Pay
                        </h4>
                        <div class="combined-summary">
                            <div class="combined-summary-grid">
                                <div><strong>Current House Tax:</strong> ₹<?php echo number_format($current_house_tax, 2); ?></div>
                                <div><strong>Arrear House Tax:</strong> ₹<?php echo number_format($arrear_house_tax, 2); ?></div>
                                <div><strong>Current Water Tax:</strong> ₹<?php echo number_format($current_water_tax, 2); ?></div>
                                <div><strong>Arrear Water Tax:</strong> ₹<?php echo number_format($arrear_water_tax, 2); ?></div>
                            </div>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ccc; font-weight: bold;">
                                <i class="fas fa-calculator"></i> Total Available: ₹<?php echo number_format($total_due_amount, 2); ?>
                            </div>
                        </div>

                        <div class="combined-field-group">
                            <div class="combined-field-label">
                                <span><i class="fas fa-house-chimney-crack" style="color: #28a745;"></i> Current House Tax</span>
                                <span style="font-size: 12px; color: #6c757d;">Available: ₹<?php echo number_format($current_house_tax, 2); ?></span>
                            </div>
                            <div class="amount-input-group">
                                <input type="number" id="current_house_amount" name="current_house_amount" class="form-input" step="0.01" min="0" max="<?php echo $current_house_tax; ?>" value="<?php echo $current_house_tax; ?>" onchange="calculateCombinedTotal()" placeholder="Enter amount" <?php echo $current_house_tax <= 0 ? 'readonly disabled' : ''; ?>>
                            </div>
                            <div class="error-message" id="error_current_house_amount"></div>
                        </div>

                        <div class="combined-field-group">
                            <div class="combined-field-label">
                                <span><i class="fas fa-house-chimney-crack" style="color: #dc3545;"></i> Arrear House Tax</span>
                                <span style="font-size: 12px; color: #6c757d;">Available: ₹<?php echo number_format($arrear_house_tax, 2); ?></span>
                            </div>
                            <div class="amount-input-group">
                                <input type="number" id="arrear_house_amount" name="arrear_house_amount" class="form-input" step="0.01" min="0" max="<?php echo $arrear_house_tax; ?>" value="<?php echo $arrear_house_tax; ?>" onchange="calculateCombinedTotal()" placeholder="Enter amount" <?php echo $arrear_house_tax <= 0 ? 'readonly disabled' : ''; ?>>
                            </div>
                            <div class="error-message" id="error_arrear_house_amount"></div>
                        </div>

                        <div class="combined-field-group">
                            <div class="combined-field-label">
                                <span><i class="fas fa-tint" style="color: #17a2b8;"></i> Current Water Tax</span>
                                <span style="font-size: 12px; color: #6c757d;">Available: ₹<?php echo number_format($current_water_tax, 2); ?></span>
                            </div>
                            <div class="amount-input-group">
                                <input type="number" id="current_water_amount" name="current_water_amount" class="form-input" step="0.01" min="0" max="<?php echo $current_water_tax; ?>" value="<?php echo $current_water_tax; ?>" onchange="calculateCombinedTotal()" placeholder="Enter amount" <?php echo $current_water_tax <= 0 ? 'readonly disabled' : ''; ?>>
                            </div>
                            <div class="error-message" id="error_current_water_amount"></div>
                        </div>

                        <div class="combined-field-group">
                            <div class="combined-field-label">
                                <span><i class="fas fa-tint" style="color: #ffc107;"></i> Arrear Water Tax</span>
                                <span style="font-size: 12px; color: #6c757d;">Available: ₹<?php echo number_format($arrear_water_tax, 2); ?></span>
                            </div>
                            <div class="amount-input-group">
                                <input type="number" id="arrear_water_amount" name="arrear_water_amount" class="form-input" step="0.01" min="0" max="<?php echo $arrear_water_tax; ?>" value="<?php echo $arrear_water_tax; ?>" onchange="calculateCombinedTotal()" placeholder="Enter amount" <?php echo $arrear_water_tax <= 0 ? 'readonly disabled' : ''; ?>>
                            </div>
                            <div class="error-message" id="error_arrear_water_amount"></div>
                        </div>

                        <div class="payment-form-group">
                            <label class="form-label">
                                <i class="fas fa-calculator"></i> Combined Total (Base Amount)
                            </label>
                            <input type="number" id="combined_total" class="form-input" value="<?php echo $total_due_amount; ?>" readonly>
                            <div class="error-message" id="error_combined_total"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="payment-form-group">
                            <label class="form-label" for="amount_base">
                                <i class="fas fa-money-bill-wave"></i> Base Amount (before discount)
                            </label>
                            <input type="number" id="amount_base" name="amount_base" class="form-input" step="0.01" min="0" value="0.00" readonly>
                            <div class="error-message" id="error_amount_base"></div>
                        </div>
                        <div class="payment-form-group">
                            <label class="form-label" for="discount_percentage">
                                <i class="fas fa-percent"></i> Discount Percentage (0-100%)
                            </label>
                            <input type="number" id="discount_percentage" name="discount_percentage" class="form-input" step="1" min="0" max="100" value="0" oninput="calculateDiscount()">
                            <div class="error-message" id="error_discount_percentage"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="payment-form-group">
                            <label class="form-label" for="discount_amount">
                                <i class="fas fa-tag"></i> Discount Amount (₹)
                            </label>
                            <input type="number" id="discount_amount" name="discount_amount" class="form-input" step="0.01" min="0" value="0.00" readonly>
                        </div>
                        <div class="payment-form-group">
                            <label class="form-label" for="amount">
                                <i class="fas fa-check"></i> Final Amount to Pay (₹)
                            </label>
                            <input type="number" id="amount" name="amount" class="form-input" step="0.01" min="0" value="0.00" readonly required>
                            <div class="error-message" id="error_final_amount"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="payment-form-group">
                            <label class="form-label" for="payer_name">
                                <i class="fas fa-user-circle"></i> Payer Name (Required)
                            </label>
                            <input type="text" id="payer_name" name="payer_name" class="form-input" required placeholder="Enter payer name" value="">
                            <div class="error-message" id="error_payer_name"></div>
                        </div>
                        <div class="payment-form-group">
                            <label class="form-label" for="payment_method">
                                <i class="fas fa-wallet"></i> Payment Method (Required)
                            </label>
                            <select id="payment_method" name="payment_method" class="form-input" required>
                                <option value="" disabled selected>Select Method</option>
                                <option value="Cash">Cash</option>
                                <option value="Online">Online / UPI</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Card">Card</option>
                            </select>
                            <div class="error-message" id="error_payment_method"></div>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="submit-btn" disabled>
                        <i class="fas fa-lock"></i> Select a Tax to Begin
                    </button>
                </form>

                <div class="history-section">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i> Payment History
                        <span style="font-size: 14px; color: #6c757d; margin-left: 10px;">(<?php echo count($payments); ?> records)</span>
                    </h2>
                    <?php if (count($payments) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Type & Details</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment):
                                    // Use formatted_date from query
                                    $formatted_time = $payment['formatted_date'];
                                    
                                    $payment_type_text = '';
                                    $payment_type_icon = 'fas fa-file-invoice';
                                    $payment_type_color = '#007bff';
                                    $payment_badge_class = '';
                                    
                                    if ($payment['payment_type'] === 'house_tax') {
                                        if ($payment['tax_type'] === 'current') {
                                            $payment_type_text = 'House Tax (Current)';
                                            $payment_type_icon = 'fas fa-house-chimney-crack';
                                            $payment_type_color = '#28a745';
                                            $payment_badge_class = 'badge-house-current';
                                        } elseif ($payment['tax_type'] === 'arrear') {
                                            $payment_type_text = 'House Tax (Arrear)';
                                            $payment_type_icon = 'fas fa-house-chimney-crack';
                                            $payment_type_color = '#dc3545';
                                            $payment_badge_class = 'badge-house-arrear';
                                        }
                                    } elseif ($payment['payment_type'] === 'water_tax') {
                                        if ($payment['tax_type'] === 'current') {
                                            $payment_type_text = 'Water Tax (Current)';
                                            $payment_type_icon = 'fas fa-tint';
                                            $payment_type_color = '#17a2b8';
                                            $payment_badge_class = 'badge-water-current';
                                        } elseif ($payment['tax_type'] === 'arrear') {
                                            $payment_type_text = 'Water Tax (Arrear)';
                                            $payment_type_icon = 'fas fa-tint';
                                            $payment_type_color = '#ffc107';
                                            $payment_badge_class = 'badge-water-arrear';
                                        }
                                    } elseif ($payment['payment_type'] === 'combined') {
                                        $payment_type_text = 'Combined Payment';
                                        $payment_type_icon = 'fas fa-layer-group';
                                        $payment_type_color = '#6f42c1';
                                        $payment_badge_class = 'badge-combined';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="time-display">
                                            <i class="fas fa-calendar-alt" style="color: #6c757d; margin-right: 5px;"></i>
                                            <?php echo $formatted_time; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="payment-type-text" style="color: <?php echo $payment_type_color; ?>;">
                                            <i class="<?php echo $payment_type_icon; ?>"></i> <?php echo $payment_type_text; ?>
                                            <span class="payment-type-badge <?php echo $payment_badge_class; ?>">
                                                <?php 
                                                if ($payment['payment_type'] === 'combined') {
                                                    echo 'Combined';
                                                } else {
                                                    echo ucfirst($payment['tax_type']);
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <?php if ($payment['payment_type'] === 'combined' && isset($payment['current_house_amount'])): ?>
                                            <div class="payment-details-small">
                                                <div><strong>House Tax:</strong> 
                                                    C: ₹<?php echo number_format($payment['current_house_amount'], 2); ?>
                                                    <?php if ($payment['arrear_house_amount'] > 0): ?>
                                                        | A: ₹<?php echo number_format($payment['arrear_house_amount'], 2); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div><strong>Water Tax:</strong> 
                                                    C: ₹<?php echo number_format($payment['current_water_amount'], 2); ?>
                                                    <?php if ($payment['arrear_water_amount'] > 0): ?>
                                                        | A: ₹<?php echo number_format($payment['arrear_water_amount'], 2); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php elseif ($payment['payment_type'] !== 'combined'): ?>
                                            <div class="payment-details-small">
                                                <strong>Tax Type:</strong> 
                                                <?php 
                                                if ($payment['payment_type'] === 'house_tax') {
                                                    echo 'House Tax';
                                                } else {
                                                    echo 'Water Tax';
                                                }
                                                ?>
                                                (<?php echo ucfirst($payment['tax_type']); ?>)
                                            </div>
                                        <?php endif; ?>
                                        <div style="font-size: 12px; color: #007bff; margin-top: 3px;">
                                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($payment['payer_name']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: #999; margin-top: 2px; font-family: monospace;">
                                            <i class="fas fa-receipt"></i> <?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td style="font-weight: bold; color: #007bff;">
                                        ₹<?php echo number_format($payment['amount'], 2); ?>
                                        <?php if ($payment['discount_amount'] > 0): ?>
                                            <div class="discount-display">
                                                <i class="fas fa-tag"></i> Saved: ₹<?php echo number_format($payment['discount_amount'], 2); ?>
                                                <?php if ($payment['discount_percentage'] > 0): ?>
                                                    (<?php echo $payment['discount_percentage']; ?>%)
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px;">
                                            <?php 
                                            $method_icon = 'fas fa-money-bill-wave';
                                            if ($payment['payment_method'] === 'Online') $method_icon = 'fas fa-globe';
                                            elseif ($payment['payment_method'] === 'Cheque') $method_icon = 'fas fa-file-invoice-dollar';
                                            elseif ($payment['payment_method'] === 'Card') $method_icon = 'fas fa-credit-card';
                                            ?>
                                            <i class="<?php echo $method_icon; ?>"></i> <?php echo htmlspecialchars($payment['payment_method']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-icons">
                                            <a href="?view_receipt=<?php echo $payment['id']; ?>" target="_blank" class="action-icon view-icon" title="View Receipt">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?delete_payment=<?php echo $payment['id']; ?>&property_id=<?php echo urlencode($property['property_id']); ?>" class="action-icon delete-icon" title="Delete Payment">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="no-history">
                            <i class="fas fa-history" style="font-size: 60px; color: #6c757d; margin-bottom: 20px; opacity: 0.5;"></i>
                            <h3 style="color: #6c757d; margin-bottom: 10px;">No Payment History</h3>
                            <p style="color: #6c757d; font-size: 14px;">No payment records found for this property.</p>
                            <p style="color: #6c757d; font-size: 13px; margin-top: 10px;">Make your first payment to see records here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedTaxType = '';
        let selectedSubType = '';
        let selectedAmount = 0;
        let isCombinedPayment = false;
        let paymentSubmitted = false;

        function selectTaxType(type, subtype, amount) {
            if (paymentSubmitted) {
                alert("Payment has already been submitted. Please refresh the page to make another payment.");
                return;
            }
            
            if (parseFloat(amount) <= 0) {
                alert("This tax amount is already zero. Please select another tax type.");
                return;
            }
            
            isCombinedPayment = false;
            // Remove selection from all cards
            document.querySelectorAll('.tax-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            const clickedCards = document.querySelectorAll('.tax-card');
            clickedCards.forEach(card => {
                if (card.onclick && card.onclick.toString().includes(type) && card.onclick.toString().includes(subtype)) {
                    card.classList.add('selected');
                }
            });

            // Update form values
            selectedTaxType = type;
            selectedSubType = subtype;
            selectedAmount = parseFloat(amount);

            // Update form display
            document.getElementById('payment_type').value = type + '_tax';
            document.getElementById('tax_type').value = subtype;
            document.getElementById('is_combined').value = '0';
            
            let displayText = '';
            if (type === 'house') {
                displayText = 'House Tax';
            } else {
                displayText = 'Water Tax';
            }
            
            if (subtype === 'current') {
                displayText += ' - Current';
            } else {
                displayText += ' - Arrear';
            }
            
            document.getElementById('selectedTaxText').textContent = displayText + ' (₹' + selectedAmount.toFixed(2) + ')';
            document.getElementById('selectedTaxDisplay').style.display = 'block';

            // Set exact amount
            document.getElementById('amount_base').value = selectedAmount.toFixed(2);

            // Show single payment field, hide combined fields
            document.getElementById('combinedFields').style.display = 'none';

            // Reset discount
            document.getElementById('discount_percentage').value = 0;

            // Calculate discount
            calculateDiscount();

            // Clear errors
            clearErrors();

            // Enable submit button if amount > 0
            updateSubmitButton();
            
            // Scroll to payment form
            $('html, body').animate({
                scrollTop: $("#paymentForm").offset().top - 100
            }, 500);
        }

        function selectCombinedPayment() {
            if (paymentSubmitted) {
                alert("Payment has already been submitted. Please refresh the page to make another payment.");
                return;
            }
            
            const totalDue = <?php echo $total_due_amount; ?>;
            if (totalDue <= 0) {
                alert("All taxes are already paid. No payment required.");
                return;
            }
            
            isCombinedPayment = true;
            // Remove selection from all cards
            document.querySelectorAll('.tax-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Update form values
            document.getElementById('payment_type').value = 'combined';
            document.getElementById('tax_type').value = '';
            document.getElementById('is_combined').value = '1';
            document.getElementById('selectedTaxText').textContent = 'Combined Payment - All Remaining Taxes (₹' + totalDue.toFixed(2) + ')';
            document.getElementById('selectedTaxDisplay').style.display = 'block';

            // Show combined fields, hide single payment field
            document.getElementById('combinedFields').style.display = 'block';

            // Calculate combined total
            calculateCombinedTotal();

            // Reset discount
            document.getElementById('discount_percentage').value = 0;

            // Calculate discount (based on combined total)
            calculateDiscount();

            // Clear errors
            clearErrors();

            // Update submit button
            updateSubmitButton();
            
            // Scroll to payment form
            $('html, body').animate({
                scrollTop: $("#paymentForm").offset().top - 100
            }, 500);
        }

        function calculateDiscount() {
            const amountBase = isCombinedPayment ? parseFloat(document.getElementById('combined_total').value) || 0 : selectedAmount;
            const discountPercent = parseFloat(document.getElementById('discount_percentage').value) || 0;
            
            if (discountPercent < 0 || discountPercent > 100) {
                 showError('discount_percentage', 'Percentage must be between 0 and 100');
            } else {
                 hideError('discount_percentage');
            }

            document.getElementById('amount_base').value = amountBase.toFixed(2);

            // Calculate final amounts
            const discountAmount = (amountBase * discountPercent) / 100;
            const finalAmount = amountBase - discountAmount;

            document.getElementById('discount_amount').value = discountAmount.toFixed(2);
            document.getElementById('amount').value = finalAmount.toFixed(2);

            if (finalAmount <= 0 && amountBase > 0) {
                 showError('final_amount', 'Final amount must be greater than 0');
            } else {
                 hideError('final_amount');
            }

            // Update submit button
            updateSubmitButton();
        }

        function calculateCombinedTotal() {
            const ch = parseFloat(document.getElementById('current_house_amount').value) || 0;
            const ah = parseFloat(document.getElementById('arrear_house_amount').value) || 0;
            const cw = parseFloat(document.getElementById('current_water_amount').value) || 0;
            const aw = parseFloat(document.getElementById('arrear_water_amount').value) || 0;

            const total = ch + ah + cw + aw;
            document.getElementById('combined_total').value = total.toFixed(2);

            calculateDiscount(); 

            if (total <= 0) {
                showError('combined_total', 'Please select amounts to pay');
            } else {
                hideError('combined_total');
            }
            
            // Update submit button
            updateSubmitButton();
        }

        function clearErrors() {
             document.querySelectorAll('.error-message').forEach(errorEl => {
                errorEl.style.display = 'none';
            });
             document.querySelectorAll('.form-input').forEach(inputEl => {
                inputEl.style.borderColor = '#ced4da';
            });
        }
        
        function updateSubmitButton() {
            let isValid = true;
            const finalAmount = parseFloat(document.getElementById('amount').value) || 0;
            const payerName = document.getElementById('payer_name').value.trim();
            const paymentMethod = document.getElementById('payment_method').value;

            if (!isCombinedPayment && selectedAmount <= 0) {
                if (finalAmount <= 0) isValid = false;
            }

            if (payerName === '') { 
                isValid = false; 
                showError('payer_name', 'Payer Name is required'); 
            } else { 
                hideError('payer_name'); 
            }
            
            if (paymentMethod === '') { 
                isValid = false; 
                showError('payment_method', 'Payment Method is required'); 
            } else { 
                hideError('payment_method'); 
            }

            if (finalAmount <= 0) {
                 isValid = false;
                 const baseAmount = parseFloat(document.getElementById('amount_base').value) || 0;
                 if (baseAmount > 0) {
                     showError('final_amount', 'Final amount must be greater than 0');
                 } else {
                     hideError('final_amount');
                 }
            } else {
                hideError('final_amount');
            }

            document.querySelectorAll('.error-message').forEach(errorEl => {
                if (errorEl.style.display === 'block') {
                    isValid = false;
                }
            });

            if (isCombinedPayment) {
                const total = parseFloat(document.getElementById('combined_total').value) || 0;
                if (total <= 0) {
                    isValid = false;
                    showError('combined_total', 'Please select amounts to pay');
                } else {
                    hideError('combined_total');
                }
            }
            
            document.getElementById('submitBtn').disabled = !isValid;
            
            if (isValid) {
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-paper-plane"></i> Process Payment of ₹' + document.getElementById('amount').value;
            } else {
                if (selectedAmount > 0 || isCombinedPayment) {
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-lock"></i> Fill All Fields Correctly';
                } else {
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-lock"></i> Select a Tax to Begin';
                }
            }
        }

        function showError(fieldId, message) {
            const errorElement = document.getElementById('error_' + fieldId);
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
                const inputElement = document.getElementById(fieldId);
                if (inputElement) inputElement.style.borderColor = '#dc3545';
            }
        }

        function hideError(fieldId) {
            const errorElement = document.getElementById('error_' + fieldId);
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.style.display = 'none';
                const inputElement = document.getElementById(fieldId);
                if (inputElement) inputElement.style.borderColor = '#ced4da';
            }
        }

        function payTax(type, subtype, amount) {
            if (paymentSubmitted) {
                alert("Payment has already been submitted. Please refresh the page to make another payment.");
                return;
            }
            
            if (parseFloat(amount) <= 0) {
                alert("This tax amount is already zero. Please select another tax type.");
                return;
            }
            
            // Select the tax type
            selectTaxType(type, subtype, amount);
            
            // Empty payer name
            document.getElementById('payer_name').value = "";
            
            // Set payment method to Cash by default
            document.getElementById('payment_method').value = "Cash";
            
            // Update submit button
            updateSubmitButton();
        }

        function validatePaymentForm() {
            updateSubmitButton();
            
            if (document.getElementById('submitBtn').disabled) {
                alert("Please fill all required fields and ensure the payment amount is correct.");
                $('html, body').animate({
                    scrollTop: $("#paymentForm").offset().top - 100
                }, 500);
                return false;
            }
            
            if (parseFloat(document.getElementById('amount').value) <= 0) {
                alert("Payment amount must be greater than 0.");
                return false;
            }
            
            // Confirm payment
            if (!confirm("Are you sure you want to process this payment of ₹" + document.getElementById('amount').value + "?")) {
                return false;
            }
            
            paymentSubmitted = true;
            
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('payer_name').addEventListener('input', updateSubmitButton);
            document.getElementById('payment_method').addEventListener('change', updateSubmitButton);
            document.getElementById('discount_percentage').addEventListener('input', updateSubmitButton);
            
            document.getElementById('current_house_amount').addEventListener('input', updateSubmitButton);
            document.getElementById('arrear_house_amount').addEventListener('input', updateSubmitButton);
            document.getElementById('current_water_amount').addEventListener('input', updateSubmitButton);
            document.getElementById('arrear_water_amount').addEventListener('input', updateSubmitButton);
            
            updateSubmitButton();
        });
    </script>
</body>
</html>