<?php
session_start();
include 'config.php';
header('Content-Type: text/html; charset=utf-8');

// Set Indian timezone
date_default_timezone_set('Asia/Kolkata');

// --- Define PHP Array Keys (Aliases) for consistency ---
$php_key_con_no        = 'connection_id';
$php_key_owner_name    = 'customer_name';
$php_key_ward_no       = 'ward_num';
$php_key_mobile        = 'mobile_num';
$php_key_current_amount = 'current_amt';
$php_key_arrear_balance = 'arrear_bal';
$php_key_remaining_balance = 'remaining_bal';

// --- Define Actual Database Column Names (WITH BACKTICKS) ---
$sql_col_con_no          = '`connection No`';
$sql_col_owner_name      = '`Owner Name`';
$sql_col_mobile          = '`Mobile`';
$sql_col_ward_no         = '`ward_no`';
$sql_col_current_amount  = '`Current amount 2025-26`';
$sql_col_arrear_balance  = '`Arrear Balance`';
$sql_col_remaining_balance = '`remaining_balance`';

// --- मासिक शुल्क निर्धारित करें ---
$monthly_rate = 50.00;
// ------------------------------------------

// 1. Authentication
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// DELETE PASSWORD - used for both delete payment and reset bill
$delete_password = "admin123";

// 2. Payment Cancellation Process - WITH PASSWORD
if (isset($_GET['delete_payment']) && isset($_GET['delete_password'])) {
    $payment_id = $_GET['delete_payment'];
    $entered_password = $_GET['delete_password'];
    $con_no_for_redirect = '';
    
    // Verify password
    if ($entered_password !== $delete_password) {
        $_SESSION['message'] = "Error: Invalid password. Payment cancellation failed.";
        header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($_GET['con_no'] ?? ''));
        exit();
    }
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment) {
            $con_no_for_redirect = $payment['con_no'];
            
            if ($payment['payment_type'] === 'current') {
                $amount_paid = floatval($payment['amount']);
                $discount_amount = floatval($payment['discount_amount'] ?? 0);
                
                // Current bill में पूरा billable amount जोड़ें (paid + discount)
                $restore_current = $amount_paid + $discount_amount;
                // Remaining balance में भी पूरा billable amount जोड़ें
                $restore_remaining = $amount_paid + $discount_amount;

                $upd = $pdo->prepare("UPDATE bills SET
                    {$sql_col_current_amount} = IFNULL({$sql_col_current_amount}, 0) + ?,
                    {$sql_col_remaining_balance} = IFNULL({$sql_col_remaining_balance}, 0) + ?
                    WHERE {$sql_col_con_no} = ?");
                $upd->execute([$restore_current, $restore_remaining, $payment['con_no']]);
            } 
            else if ($payment['payment_type'] === 'arrear') {
                $amount_paid = floatval($payment['amount']);
                
                // Arrear के लिए दोनों में same amount जोड़ें
                $upd = $pdo->prepare("UPDATE bills SET
                    {$sql_col_arrear_balance} = IFNULL({$sql_col_arrear_balance}, 0) + ?,
                    {$sql_col_remaining_balance} = IFNULL({$sql_col_remaining_balance}, 0) + ?
                    WHERE {$sql_col_con_no} = ?");
                $upd->execute([$amount_paid, $amount_paid, $payment['con_no']]);
            } 
            else if ($payment['payment_type'] === 'combined') {
                $current_paid = floatval($payment['current_paid_amount'] ?? 0);
                $arrear_paid = floatval($payment['arrear_paid_amount'] ?? 0);
                $discount = floatval($payment['discount_amount'] ?? 0);
                
                // IMPORTANT FIX: Combined payment restore logic
                // Current bill restore: current_paid_amount + discount_amount
                $current_to_restore = $current_paid + $discount;
                // Arrear restore: arrear_paid_amount
                $arrear_to_restore = $arrear_paid;
                // Remaining balance restore: current_full + arrear_full
                $remaining_to_restore = $current_to_restore + $arrear_to_restore;

                $upd = $pdo->prepare("UPDATE bills SET
                    {$sql_col_current_amount} = IFNULL({$sql_col_current_amount}, 0) + ?,
                    {$sql_col_arrear_balance} = IFNULL({$sql_col_arrear_balance}, 0) + ?,
                    {$sql_col_remaining_balance} = IFNULL({$sql_col_remaining_balance}, 0) + ?
                    WHERE {$sql_col_con_no} = ?");
                $upd->execute([$current_to_restore, $arrear_to_restore, $remaining_to_restore, $payment['con_no']]);
            }

            $del = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $del->execute([$payment_id]);

            $pdo->commit();
            $_SESSION['message'] = "Payment successfully cancelled and amounts restored.";
            
            // DEBUG: Check the updated amounts
            $stmt_check = $pdo->prepare("SELECT {$sql_col_current_amount}, {$sql_col_arrear_balance}, {$sql_col_remaining_balance} FROM bills WHERE {$sql_col_con_no} = ?");
            $stmt_check->execute([$payment['con_no']]);
            $updated_amounts = $stmt_check->fetch(PDO::FETCH_ASSOC);
            error_log("DEBUG: After delete payment - Current: " . ($updated_amounts[$sql_col_current_amount] ?? 'N/A') . ", Arrear: " . ($updated_amounts[$sql_col_arrear_balance] ?? 'N/A') . ", Remaining: " . ($updated_amounts[$sql_col_remaining_balance] ?? 'N/A'));
        }

        // FIXED REDIRECT - FORCE REFRESH
        if ($con_no_for_redirect) {
            header("Location: ".$_SERVER['PHP_SELF']."?con_no=".$con_no_for_redirect . "&force_refresh=" . time());
            exit();
        } else {
            header("Location: ".$_SERVER['PHP_SELF'] . "?force_refresh=" . time());
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error cancelling payment: " . $e->getMessage();
        error_log("Payment cancellation error: " . $e->getMessage());
        if ($con_no_for_redirect) {
            header("Location: ".$_SERVER['PHP_SELF']."?con_no=".$con_no_for_redirect);
        } else {
            header("Location: ".$_SERVER['PHP_SELF']);
        }
        exit();
    }
}

// 3. Reset Bill Functionality
if (isset($_POST['reset_bill']) && isset($_POST['con_no']) && isset($_POST['reset_password'])) {
    $con_no_reset = $_POST['con_no'];
    $password = $_POST['reset_password'];
    
    $correct_password = $delete_password;
    
    if ($password !== $correct_password) {
        $_SESSION['message'] = "Error: Invalid password. Bill reset failed.";
        header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_reset));
        exit();
    }
    
    try {
        $pdo->beginTransaction();

        // 1. Delete all payments for this connection number
        $del_payments = $pdo->prepare("DELETE FROM payments WHERE con_no = ?");
        $del_payments->execute([$con_no_reset]);

        // 2. Reset the bill amounts in the bills table
        $reset_bill_amount = $monthly_rate * 12; // Full year bill
        $reset_arrear_amount = 1000.00;
        $total_reset_amount = $reset_bill_amount + $reset_arrear_amount;
        
        $upd_bills = $pdo->prepare("UPDATE bills SET
            {$sql_col_current_amount} = ?,
            {$sql_col_arrear_balance} = ?,
            {$sql_col_remaining_balance} = ?
            WHERE {$sql_col_con_no} = ?");
        $upd_bills->execute([$reset_bill_amount, $reset_arrear_amount, $total_reset_amount, $con_no_reset]);

        $pdo->commit();
        $_SESSION['message'] = "Bill for connection '{$con_no_reset}' has been successfully reset.";
        header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_reset) . "&force_refresh=" . time());
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error resetting bill: " . $e->getMessage());
    }
}

// 4. Search Functionality
if (isset($_GET['search'])) {
    $search_term = '%'.$_GET['search'].'%';
    $stmt = $pdo->prepare("SELECT
        {$sql_col_con_no} AS {$php_key_con_no},
        {$sql_col_owner_name} AS {$php_key_owner_name}
        FROM bills
        WHERE {$sql_col_con_no} LIKE ? OR {$sql_col_owner_name} LIKE ? LIMIT 50");
    $stmt->execute([$search_term, $search_term]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<!DOCTYPE html>
    <html lang="hi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Search Results</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            h1 { color: #007bff; text-align: center; }
            .search-box { display: flex; margin-bottom: 20px; }
            .search-box input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px 0 0 4px; }
            .search-box button { padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 0 4px 4px 0; cursor: pointer; }
            .search-box button:hover { background: #0056b3; }
            .result-item { padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
            .result-item:hover { background-color: #f0f8ff; }
            .result-info { flex: 1; }
            .result-actions a { margin-left: 10px; color: #007bff; text-decoration: none; }
            .home-btn { display: block; text-align: center; margin-top: 20px; padding: 10px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1><i class="fas fa-search"></i> Search Results</h1>

            <form method="get" class="search-box">
                <input type="text" name="search" value="'.htmlspecialchars($_GET['search']).'" placeholder="Connection number or customer name" required>
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>';

    if (count($results) > 0) {
        foreach ($results as $row) {
            echo '<div class="result-item">
                        <div class="result-info">
                            <strong>'.htmlspecialchars($row[$php_key_con_no]).'</strong> -
                            '.htmlspecialchars($row[$php_key_owner_name]).'
                        </div>
                        <div class="result-actions">
                            <a href="?con_no='.urlencode($row[$php_key_con_no]).'">
                                <i class="fas fa-rupee-sign"></i> Make Payment
                            </a>
                            <a href="payment_history.php?con_no='.urlencode($row[$php_key_con_no]).'">
                                <i class="fas fa-history"></i> History
                            </a>
                        </div>
                    </div>';
        }
    } else {
        echo '<p style="text-align:center; color:red">No results found</p>';
    }

    echo '<a href="https://sunnydhaka.fwh.is/jal.php?search=1" class="home-btn"><i class="fas fa-home"></i> Return to Home</a>
        </div>
    </body>
    </html>';
    exit();
}

// 5. Get Connection Number
if (!isset($_GET['con_no']) && !isset($_POST['con_no']) && !isset($_SESSION['last_con_no'])) {
    echo '<!DOCTYPE html>
    <html lang="hi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Find Connection</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .search-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); text-align: center; width: 90%; max-width: 500px; }
            h1 { color: #007bff; margin-bottom: 20px; }
            .logo { font-size: 50px; color: #007bff; margin-bottom: 20px; }
            .search-form { margin-top: 20px; }
            .search-form input { width: 95%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
            .search-form button { background: #007bff; color: white; border: none; padding: 12px 20px; border-radius: 4px; font-size: 16px; cursor: pointer; width: 100%; }
            .search-form button:hover { background: #0056b3; }
            .features { margin-top: 30px; text-align: left; }
            .feature { display: flex; align-items: center; margin-bottom: 10px; }
            .feature i { margin-right: 10px; color: #28a745; }
        </style>
    </head>
    <body>
        <div class="search-container">
            <div class="logo">
                <i class="fas fa-tint"></i>
            </div>
            <h1>Water Department Payment System</h1>
            <p>Please enter your connection number or customer name</p>

            <form method="get" class="search-form">
                <input type="text" name="search" placeholder="Connection number or customer name" required>
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>

            <div class="features">
                <div class="feature">
                    <i class="fas fa-check"></i>
                    <span>Current and arrears payment</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check"></i>
                    <span>Payment history tracking</span>
                </div>
                <div class="feature">
                    <i class="fas fa-check"></i>
                    <span>Multiple payment methods</span>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit();
}

$con_no_php_var = $_POST['con_no'] ?? $_GET['con_no'] ?? ($_SESSION['last_con_no'] ?? '');
$_SESSION['last_con_no'] = $con_no_php_var;

// 6. Fetch Bill Data
$stmt = $pdo->prepare("SELECT
    {$sql_col_con_no} AS {$php_key_con_no},
    {$sql_col_owner_name} AS {$php_key_owner_name},
    {$sql_col_ward_no} AS {$php_key_ward_no},
    {$sql_col_mobile} AS {$php_key_mobile},
    {$sql_col_current_amount} AS {$php_key_current_amount},
    {$sql_col_arrear_balance} AS {$php_key_arrear_balance},
    {$sql_col_remaining_balance} AS {$php_key_remaining_balance}
    FROM bills
    WHERE {$sql_col_con_no} = ?");
$stmt->execute([$con_no_php_var]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bill) {
    echo '<!DOCTYPE html>
    <html lang="hi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bill Not Found</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); text-align: center; width: 90%; max-width: 500px; }
            h1 { color: #dc3545; margin-bottom: 20px; }
            .error-icon { font-size: 50px; color: #dc3545; margin-bottom: 20px; }
            button { background: #007bff; color: white; border: none; padding: 12px 20px; border-radius: 4px; font-size: 16px; cursor: pointer; margin-top: 20px; }
            button:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1>Bill Not Found</h1>
            <p>No bill record found for the given connection number</p>
            <button onclick="window.location.href=\'?\'">
                <i class="fas fa-search"></i> New Search
            </button>
        </div>
    </body>
    </html>';
    exit();
}

// 7. Fetch Payment History
$payQ = $pdo->prepare("SELECT id, con_no, amount, payment_method, paid_on, username, payment_type, payer_name, discount_amount, discount_percentage, transaction_id, current_paid_amount, arrear_paid_amount FROM payments WHERE con_no = ? ORDER BY paid_on DESC");
$payQ->execute([$con_no_php_var]);
$payments = $payQ->fetchAll(PDO::FETCH_ASSOC);

// 8. Calculate available current bill amount
$current_bill_amount = floatval($bill[$php_key_current_amount] ?? 0);
$arrear_bill_amount = floatval($bill[$php_key_arrear_balance] ?? 0);

// 9. Process Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_type'])) {
    $payment_type = $_POST['payment_type'];
    $pay_method = $_POST['payment_method'] ?? '';
    $payer_name = $_POST['payer_name'] ?? '';
    $discount_amount = 0;
    $discount_percentage = 0;
    $user = $_SESSION['username'];

    $transaction_id = 'txn_' . date('YmdHis') . '_' . substr(uniqid(), 0, 6);

    try {
        $pdo->beginTransaction();

        if ($payment_type === 'current') {
            $amount = floatval($_POST['amount'] ?? 0);
            
            if ($amount <= 0) {
                $_SESSION['message'] = "Error: Amount must be greater than zero.";
                header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_php_var));
                exit();
            }
            
            // Check if selected amount exceeds available current bill
            if ($amount > ($current_bill_amount + 0.001)) {
                $_SESSION['message'] = "Error: The payment amount (₹".number_format($amount, 2).") exceeds the available current bill balance (₹".number_format($current_bill_amount, 2).").";
                header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_php_var));
                exit();
            }

            $discount_percentage_input = floatval($_POST['discount_percentage'] ?? 0);

            if (is_nan($discount_percentage_input) || $discount_percentage_input < 0) {
                $discount_percentage = 0;
                $_POST['discount_percentage'] = 0;
            } elseif ($discount_percentage_input > 100) {
                $discount_percentage = 100;
                $_POST['discount_percentage'] = 100;
            } else {
                $discount_percentage = $discount_percentage_input;
            }

            $calculated_discount_amount = ($amount * $discount_percentage) / 100;
            $discount_amount = min($calculated_discount_amount, $amount);

            $final_amount = $amount - $discount_amount;
            
            // Calculate billable amount (amount before discount)
            $billable_amount = $amount;

            // Get current Indian time
            $current_time = date('Y-m-d H:i:s');
            
            $ins = $pdo->prepare("INSERT INTO payments (con_no, payment_type, amount, payment_method, paid_on, username, payer_name, discount_amount, discount_percentage, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$con_no_php_var, $payment_type, $final_amount, $pay_method, $current_time, $user, $payer_name, $discount_amount, $discount_percentage, $transaction_id]);

            // Current bill से full billable amount कटेगा
            $upd = $pdo->prepare("UPDATE bills SET
                {$sql_col_current_amount} = GREATEST(IFNULL({$sql_col_current_amount}, 0) - ?, 0),
                {$sql_col_remaining_balance} = GREATEST(IFNULL({$sql_col_remaining_balance}, 0) - ?, 0)
                WHERE {$sql_col_con_no} = ?");
            $upd->execute([$billable_amount, $billable_amount, $con_no_php_var]);

        } else if ($payment_type === 'arrear') {
            $amount = floatval($_POST['amount'] ?? 0);
            
            if ($amount > ($arrear_bill_amount + 0.001)) {
                $_SESSION['message'] = "Error: The payment amount (₹".number_format($amount, 2).") exceeds the arrear balance (₹".number_format($arrear_bill_amount, 2)."). Please adjust the payment.";
                header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_php_var));
                exit();
            }

            // Get current Indian time
            $current_time = date('Y-m-d H:i:s');
            
            $ins = $pdo->prepare("INSERT INTO payments (con_no, payment_type, amount, payment_method, paid_on, username, payer_name, discount_amount, discount_percentage, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$con_no_php_var, $payment_type, $amount, $pay_method, $current_time, $user, $payer_name, 0, 0, $transaction_id]);

            // Arrear के लिए भी दोनों में same amount कटेगा
            $upd = $pdo->prepare("UPDATE bills SET
                {$sql_col_arrear_balance} = GREATEST(IFNULL({$sql_col_arrear_balance}, 0) - ?, 0),
                {$sql_col_remaining_balance} = GREATEST(IFNULL({$sql_col_remaining_balance}, 0) - ?, 0)
                WHERE {$sql_col_con_no} = ?");
            $upd->execute([$amount, $amount, $con_no_php_var]);

        } else if ($payment_type === 'combined') {
            $current_amount = floatval($_POST['current_amount'] ?? 0);
            $arrear_paid_amount = floatval($_POST['arrear_paid_amount'] ?? 0);
            
            if ($current_amount <= 0 && $arrear_paid_amount <= 0) {
                $_SESSION['message'] = "Error: At least one amount (current or arrear) must be greater than zero.";
                header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_php_var));
                exit();
            }

            // Check current amount
            if ($current_amount > 0) {
                // Check if selected amount exceeds available current bill
                if ($current_amount > ($current_bill_amount + 0.001)) {
                    $_SESSION['message'] = "Error: The current payment amount (₹".number_format($current_amount, 2).") exceeds the available current bill balance (₹".number_format($current_bill_amount, 2).").";
                    header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_php_var));
                    exit();
                }
            }

            $discount_percentage_input = floatval($_POST['discount_percentage'] ?? 0);

            if (is_nan($discount_percentage_input) || $discount_percentage_input < 0) {
                $discount_percentage = 0;
            } elseif ($discount_percentage_input > 100) {
                $discount_percentage = 100;
            } else {
                $discount_percentage = $discount_percentage_input;
            }

            // Calculate discount and amounts
            $discount_amount = ($current_amount * $discount_percentage) / 100;
            $final_current_amount = $current_amount - $discount_amount;

            if ($arrear_paid_amount > $arrear_bill_amount) {
                $_SESSION['message'] = "Error: The arrear payment amount (₹".number_format($arrear_paid_amount, 2).") exceeds the current arrear balance (₹".number_format($arrear_bill_amount, 2)."). Please adjust the amount.";
                header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_php_var));
                exit();
            }

            $total_paid = $final_current_amount + $arrear_paid_amount;

            // Get current Indian time
            $current_time = date('Y-m-d H:i:s');
            
            // Insert payment record
            $ins = $pdo->prepare("INSERT INTO payments (con_no, payment_type, amount, current_paid_amount, arrear_paid_amount, payment_method, paid_on, username, payer_name, discount_amount, discount_percentage, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$con_no_php_var, 'combined', $total_paid, $final_current_amount, $arrear_paid_amount, $pay_method, $current_time, $user, $payer_name, $discount_amount, $discount_percentage, $transaction_id]);

            // Update bills table
            $current_deduction = $current_amount; // Full current amount (before discount)
            $arrear_deduction = $arrear_paid_amount;
            $remaining_deduction = $current_amount + $arrear_paid_amount;
            
            $upd = $pdo->prepare("UPDATE bills SET
                {$sql_col_current_amount} = GREATEST(IFNULL({$sql_col_current_amount}, 0) - ?, 0),
                {$sql_col_arrear_balance} = GREATEST(IFNULL({$sql_col_arrear_balance}, 0) - ?, 0),
                {$sql_col_remaining_balance} = GREATEST(IFNULL({$sql_col_remaining_balance}, 0) - ?, 0)
                WHERE {$sql_col_con_no} = ?");
            $upd->execute([$current_deduction, $arrear_deduction, $remaining_deduction, $con_no_php_var]);
        }

        $pdo->commit();

        $new_payment_id = $pdo->lastInsertId();
        $_SESSION['message'] = "Payment processed successfully! <a href='download_paymentbill.php?id=".urlencode(htmlspecialchars($con_no_php_var))."&payment_id=".$new_payment_id."' target='_blank'><i class='fas fa-download'></i> Download Bill</a>";

        header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_php_var) . "&force_refresh=" . time());
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = "Error processing payment: " . $e->getMessage();
        header("Location: ".$_SERVER['PHP_SELF']."?con_no=".urlencode($con_no_php_var));
        exit();
    }
}

// Get current Indian time for display
$current_indian_time = date('d-m-Y h:i:s A');

// Calculate what should be displayed for payment options
$current_bill_display = floatval($bill[$php_key_current_amount] ?? 0);
$arrear_bill_display = floatval($bill[$php_key_arrear_balance] ?? 0);
$remaining_bill_display = floatval($bill[$php_key_remaining_balance] ?? 0);

// Calculate combined bill amount for display
$combined_bill_amount = $current_bill_display + $arrear_bill_display;

// Helper function to format amounts without decimal for display only
function formatAmountForDisplay($amount) {
    $amount = floatval($amount);
    // If amount has decimal and it's .00, remove it
    if (round($amount) == $amount) {
        return number_format($amount, 0);
    }
    return number_format($amount, 2);
}

// Helper function to get integer value for input fields
function getIntegerValue($amount) {
    $amount = floatval($amount);
    if (round($amount) == $amount) {
        return intval($amount);
    }
    return $amount;
}

// Format amounts for display only (without .00)
$current_display = formatAmountForDisplay($current_bill_display);
$arrear_display = formatAmountForDisplay($arrear_bill_display);
$remaining_display = formatAmountForDisplay($remaining_bill_display);
$combined_display = formatAmountForDisplay($combined_bill_amount);

// Get integer values for input fields
$current_input_value = getIntegerValue($current_bill_display);
$arrear_input_value = getIntegerValue($arrear_bill_display);

// For form max values (keep decimal for validation)
$current_max_value = number_format($current_bill_display, 2, '.', '');
$arrear_max_value = number_format($arrear_bill_display, 2, '.', '');
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment System - Water Department</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }

        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }

        .header {
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .time-display {
            font-size: 14px;
            color: rgba(255,255,255,0.9);
            background: rgba(0,0,0,0.1);
            padding: 5px 10px;
            border-radius: 4px;
        }

        .action-btns {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .search-btn {
            background: var(--secondary-color);
        }

        .home-btn {
            background: var(--success-color);
        }

        .reset-btn {
            background: var(--danger-color);
        }
        
        .clear-btn {
            background: var(--warning-color);
        }

        .action-btn:hover {
            opacity: 0.9;
        }

        .customer-info {
            padding: 20px;
            background: var(--light-color);
            border-bottom: 1px solid #ddd;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            margin: 0;
        }

        .info-item strong {
            display: block;
            margin-bottom: 3px;
            color: var(--secondary-color);
            font-size: 14px;
        }

        .info-value {
            font-size: 16px;
            font-weight: bold;
        }

        .amount {
            color: var(--success-color);
        }
        
        .remaining-amount-red {
            color: var(--danger-color) !important;
        }

        .payment-info-box {
            background: #e7f3ff;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin: 0 20px 20px;
            border-radius: 4px;
        }

        .payment-info-box h4 {
            margin-top: 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-info-box p {
            margin: 5px 0;
        }

        .payment-section {
            display: flex;
            padding: 20px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .payment-options {
            flex: 1;
            min-width: 300px;
        }

        .payment-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .payment-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(0,123,255,0.1);
        }

        .payment-card.disabled {
            cursor: not-allowed;
            opacity: 0.5;
            position: relative;
        }
        
        .payment-card.disabled::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.7);
            z-index: 1;
            border-radius: 8px;
        }
        
        .payment-card.disabled h3,
        .payment-card.disabled p,
        .payment-card.disabled .amount-display {
            opacity: 0.5;
        }

        .payment-card.selected-current {
            border: 2px solid var(--success-color);
            background: rgba(40, 167, 69, 0.08);
        }
        .payment-card.selected-current h3 {
            color: var(--success-color);
        }
        .payment-card.selected-current .current-amount {
            color: var(--success-color);
        }

        .payment-card.selected-arrear {
            border: 2px solid var(--danger-color);
            background: rgba(220, 53, 69, 0.08);
        }
        .payment-card.selected-arrear h3 {
            color: var(--danger-color);
        }
        .payment-card.selected-arrear .arrear-amount {
            color: var(--danger-color);
        }

        .payment-card.selected-combined {
            border: 2px solid var(--primary-color);
            background: rgba(0, 123, 255, 0.08);
        }
        .payment-card.selected-combined h3 {
            color: var(--primary-color);
        }

        .payment-card h3 {
            margin-top: 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 2;
        }

        .payment-card h3 i {
            font-size: 20px;
        }

        .payment-card p {
            position: relative;
            z-index: 2;
        }

        .amount-display {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: right;
            margin-top: 10px;
            position: relative;
            z-index: 2;
        }

        .current-amount {
            color: var(--primary-color);
        }

        .arrear-amount {
            color: var(--warning-color);
        }

        body.active-current-payment .customer-info .info-item:nth-child(3) .info-value {
            color: var(--success-color);
        }

        body.active-arrear-payment .customer-info .info-item:nth-child(4) .info-value {
            color: var(--danger-color);
        }

        .payment-form-container {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .payment-form {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: white;
        }

        .payment-form.hidden {
            display: none !important;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 94%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input[type="number"]::-webkit-inner-spin-button,
        .form-group input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .form-group input[type="number"] {
            -moz-appearance: textfield;
        }

        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background-color 0.3s ease;
        }

        .submit-btn:disabled {
            background: var(--secondary-color);
            cursor: not-allowed;
        }

        .clear-form-btn {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover:not(:disabled), .clear-form-btn:hover {
            opacity: 0.9;
        }

        #current-form .submit-btn:not(:disabled) {
            background: var(--success-color);
        }
        #current-form .submit-btn:not(:disabled):hover {
            background: #218838;
        }

        #arrear-form .submit-btn:not(:disabled) {
            background: var(--danger-color);
        }
        #arrear-form .submit-btn:not(:disabled):hover {
            background: #c82333;
        }

        #combined-form .submit-btn:not(:disabled) {
            background: var(--primary-color);
        }
        #combined-form .submit-btn:not(:disabled):hover {
            background: #0056b3;
        }

        .payment-form h3 {
            color: var(--primary-color);
            transition: color 0.3s ease;
            margin-top: 0;
        }

        .history-section {
            padding: 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-header h3 {
            margin: 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-actions {
            display: flex;
            gap: 10px;
        }

        .payment-history {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .payment-history th {
            background: var(--primary-color);
            color: white;
            padding: 10px;
            text-align: left;
        }

        .payment-history td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .payment-history tr:nth-child(even) {
            background: var(--light-color);
        }

        .payment-history tr:hover {
            background: #e7f3ff;
        }

        .action-cell {
            text-align: center;
            white-space: nowrap;
        }

        .view-receipt-btn {
            color: var(--primary-color);
            margin-right: 10px;
            text-decoration: none;
            font-size: 16px;
        }

        .delete-btn {
            color: var(--danger-color);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            margin-left: 10px;
        }

        .no-payments {
            text-align: center;
            padding: 20px;
            color: var(--secondary-color);
        }

        .message {
            padding: 15px;
            margin: 0 20px 20px;
            border-radius: 4px;
            background: #d4edda;
            color: #155724;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .close-message {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #155724;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 400px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--danger-color);
        }

        .close-modal {
            color: #aaa;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover {
            color: #000;
        }

        .modal-form-group {
            margin-bottom: 15px;
        }

        .modal-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .modal-form-group input {
            width: 94%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .modal-submit-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }

        .modal-submit-btn:hover {
            background: #c82333;
        }

        .no-payment-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(220, 53, 69, 0.9);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            z-index: 10;
            display: none;
            text-align: center;
            width: 80%;
        }

        .payment-card.disabled:hover .no-payment-text {
            display: block;
        }

        @media (max-width: 768px) {
            .container { margin: 10px; border-radius: 5px; }
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .customer-info, .payment-section { grid-template-columns: 1fr; }
            .payment-options, .payment-form-container { min-width: unset; width: 100%; }
            .payment-history { overflow-x: auto; display: block; white-space: nowrap; }
            .payment-history thead, .payment_history tbody, .payment-history th, .payment-history td, .payment-history tr { display: block; }
            .payment-history thead { display: none; }
            .payment-history tr { margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px; }
            .payment-history td { border: none; border-bottom: 1px dotted #eee; position: relative; padding-left: 50%; text-align: right; }
            .payment-history td:before { content: attr(data-label); position: absolute; left: 6px; width: 45%; padding-right: 10px; white-space: nowrap; text-align: left; font-weight: bold; color: var(--secondary-color); }
            .action-cell { text-align: right; }
        }
    </style>
</head>
<body>
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Reset Bill Confirmation</h3>
                <span class="close-modal">&times;</span>
            </div>
            <form id="resetForm" method="post">
                <input type="hidden" name="con_no" value="<?php echo htmlspecialchars($con_no_php_var); ?>">
                <input type="hidden" name="reset_bill" value="1">
                <div class="modal-form-group">
                    <label for="reset_password">Enter Password to Reset Bill:</label>
                    <input type="password" id="reset_password" name="reset_password" required>
                </div>
                <button type="submit" class="modal-submit-btn">Confirm Reset</button>
            </form>
        </div>
    </div>

    <div id="deletePaymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Delete Payment Confirmation</h3>
                <span class="close-delete-modal">&times;</span>
            </div>
            <form id="deletePaymentForm" method="get">
                <input type="hidden" id="delete_payment_id" name="delete_payment" value="">
                <input type="hidden" name="con_no" value="<?php echo htmlspecialchars($con_no_php_var); ?>">
                <div class="modal-form-group">
                    <label for="delete_password">Enter Password to Delete Payment:</label>
                    <input type="password" id="delete_password" name="delete_password" required>
                </div>
                <button type="submit" class="modal-submit-btn">Confirm Delete</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <h2><i class="fas fa-tint"></i> Water Bill Payment</h2>
            <div class="time-display">
                <i class="fas fa-clock"></i> <?php echo $current_indian_time; ?>
            </div>
            <div class="action-btns">
                <a href="?search=1" class="action-btn search-btn"><i class="fas fa-search"></i> New Search</a>
                <a href="https://sunnydhaka.fwh.is/jal.php?search=1" class="action-btn home-btn"><i class="fas fa-home"></i> Home</a>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo strpos($_SESSION['message'], 'Error') !== false ? 'error-message' : ''; ?>">
                <?php echo $_SESSION['message']; ?>
                <button class="close-message" onclick="this.parentElement.style.display='none';">&times;</button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <div class="customer-info">
            <p class="info-item">
                <strong>Connection No:</strong>
                <span class="info-value"><?php echo htmlspecialchars($bill[$php_key_con_no]); ?></span>
            </p>
            <p class="info-item">
                <strong>Owner Name:</strong>
                <span class="info-value"><?php echo htmlspecialchars($bill[$php_key_owner_name]); ?></span>
            </p>
            <p class="info-item">
                <strong>Ward No:</strong>
                <span class="info-value"><?php echo htmlspecialchars($bill[$php_key_ward_no] ?? 'N/A'); ?></span>
            </p>
            <p class="info-item">
                <strong>Mobile:</strong>
                <span class="info-value"><?php echo htmlspecialchars($bill[$php_key_mobile] ?? 'N/A'); ?></span>
            </p>
            <p class="info-item">
                <strong>Current Bill Amount:</strong>
                <span class="info-value current-amount">₹<?php echo $current_display; ?></span>
            </p>
            <p class="info-item">
                <strong>Arrear Balance:</strong>
                <span class="info-value arrear-amount">₹<?php echo $arrear_display; ?></span>
            </p>
            <p class="info-item">
                <strong>Remaining Balance:</strong>
                <span class="info-value amount remaining-amount-red">₹<?php echo $remaining_display; ?></span>
            </p>
        </div>

        <div class="payment-section">
            <div class="payment-options">
                <!-- Payment cards will always be visible -->
                <div class="payment-card <?php echo ($current_bill_display <= 0) ? 'disabled' : ''; ?>" id="current-payment-card">
                    <h3><i class="fas fa-rupee-sign"></i> Pay Current Bill</h3>
                    <p>Pay for current bill amount.</p>
                    <div class="amount-display current-amount">
                        ₹<?php echo $current_display; ?>
                    </div>
                    <?php if ($current_bill_display <= 0): ?>
                        <div class="no-payment-text">No Current Bill Available</div>
                    <?php endif; ?>
                </div>

                <div class="payment-card <?php echo ($arrear_bill_display <= 0) ? 'disabled' : ''; ?>" id="arrear-payment-card">
                    <h3><i class="fas fa-history"></i> Pay Arrear Balance</h3>
                    <p>Settle outstanding previous balances.</p>
                    <div class="amount-display arrear-amount">
                        ₹<?php echo $arrear_display; ?>
                    </div>
                    <?php if ($arrear_bill_display <= 0): ?>
                        <div class="no-payment-text">No Arrear Balance Available</div>
                    <?php endif; ?>
                </div>

                <div class="payment-card <?php echo (($current_bill_display <= 0) && ($arrear_bill_display <= 0)) ? 'disabled' : ''; ?>" id="combined-payment-card">
                    <h3><i class="fas fa-file-invoice-dollar"></i> Combined Bill</h3>
                    <p>Generate a bill for both current and arrear amounts.</p>
                    <div class="amount-display amount">
                        ₹<?php echo $combined_display; ?>
                    </div>
                    <?php if (($current_bill_display <= 0) && ($arrear_bill_display <= 0)): ?>
                        <div class="no-payment-text">No Amount Available for Combined Bill</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="payment-form-container">
                <!-- Forms will be shown/hidden based on selection -->
                <form action="" method="post" class="payment-form" id="current-form" style="display: none;">
                    <h3><i class="fas fa-money-bill-wave"></i> Current Bill Payment</h3>
                    <input type="hidden" name="con_no" value="<?php echo htmlspecialchars($con_no_php_var); ?>">
                    <input type="hidden" name="payment_type" value="current">

                    <div class="form-group">
                        <label for="current_amount_pay">Amount to Pay (Current Bill)</label>
                        <input type="number" id="current_amount_pay" name="amount" step="0.01" 
                               min="0" max="<?php echo $current_max_value; ?>" 
                               value="<?php echo $current_input_value; ?>" 
                               <?php echo ($current_bill_display <= 0) ? 'readonly' : ''; ?>>
                        <small>Maximum: ₹<?php echo $current_display; ?></small>
                    </div>

                    <div class="form-group">
                        <label for="current_discount_percentage">Discount Percentage (%)</label>
                        <input type="number" id="current_discount_percentage" name="discount_percentage" 
                               step="0.01" min="0" max="100" value="" 
                               <?php echo ($current_bill_display <= 0) ? 'readonly' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label for="current_final_amount">Final Amount to Pay (Auto-calculated)</label>
                        <input type="text" id="current_final_amount" name="final_amount" readonly style="font-weight: bold; color: var(--success-color);">
                    </div>

                    <div class="form-group">
                        <label for="current_payment_method">Payment Method</label>
                        <select id="current_payment_method" name="payment_method" <?php echo ($current_bill_display <= 0) ? 'disabled' : ''; ?>>
                            <option value="">Select Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Online">Online</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="current_payer_name">Payer Name (Optional)</label>
                        <input type="text" id="current_payer_name" name="payer_name" placeholder="Enter payer's name" 
                               <?php echo ($current_bill_display <= 0) ? 'readonly' : ''; ?>>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="submit-btn" id="current-submit-btn" <?php echo ($current_bill_display <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-circle"></i> <?php echo ($current_bill_display <= 0) ? 'No Amount Available' : 'Pay Current Bill'; ?>
                        </button>
                        <button type="button" class="clear-form-btn" onclick="clearCurrentForm()" <?php echo ($current_bill_display <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </form>

                <form action="" method="post" class="payment-form" id="arrear-form" style="display: none;">
                    <h3><i class="fas fa-hand-holding-usd"></i> Arrear Balance Payment</h3>
                    <input type="hidden" name="con_no" value="<?php echo htmlspecialchars($con_no_php_var); ?>">
                    <input type="hidden" name="payment_type" value="arrear">

                    <div class="form-group">
                        <label for="arrear_amount_pay">Amount to Pay (Arrear)</label>
                        <input type="number" id="arrear_amount_pay" name="amount" step="0.01" min="0"
                               value="<?php echo $arrear_input_value; ?>"
                               max="<?php echo $arrear_max_value; ?>" 
                               <?php echo ($arrear_bill_display <= 0) ? 'readonly' : ''; ?>>
                        <small>Maximum: ₹<?php echo $arrear_display; ?></small>
                    </div>

                    <div class="form-group">
                        <label for="arrear_payment_method">Payment Method</label>
                        <select id="arrear_payment_method" name="payment_method" <?php echo ($arrear_bill_display <= 0) ? 'disabled' : ''; ?>>
                            <option value="">Select Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Online">Online</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="arrear_payer_name">Payer Name (Optional)</label>
                        <input type="text" id="arrear_payer_name" name="payer_name" placeholder="Enter payer's name" 
                               <?php echo ($arrear_bill_display <= 0) ? 'readonly' : ''; ?>>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="submit-btn" <?php echo ($arrear_bill_display <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-circle"></i> <?php echo ($arrear_bill_display <= 0) ? 'No Amount Available' : 'Pay Arrear Bill'; ?>
                        </button>
                        <button type="button" class="clear-form-btn" onclick="clearArrearForm()" <?php echo ($arrear_bill_display <= 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </form>

                 <form action="" method="post" class="payment-form" id="combined-form" style="display: none;">
                    <h3><i class="fas fa-file-invoice-dollar"></i> Combined Bill Payment</h3>
                    <input type="hidden" name="con_no" value="<?php echo htmlspecialchars($con_no_php_var); ?>">
                    <input type="hidden" name="payment_type" value="combined">

                    <div class="form-group">
                        <label for="combined_current_amount">Current Bill Amount to Pay</label>
                        <input type="number" id="combined_current_amount" name="current_amount" step="0.01" 
                               min="0" max="<?php echo $current_max_value; ?>"
                               value="<?php echo $current_input_value; ?>"
                               <?php echo ($current_bill_display <= 0) ? 'readonly' : ''; ?>>
                        <small>Maximum: ₹<?php echo $current_display; ?></small>
                    </div>

                    <div class="form-group">
                        <label for="combined_discount_percentage">Discount Percentage for Current Bill (%)</label>
                        <input type="number" id="combined_discount_percentage" name="discount_percentage" 
                               step="0.01" min="0" max="100" value=""
                               <?php echo ($current_bill_display <= 0) ? 'readonly' : ''; ?>>
                        <small style="color: var(--warning-color);" id="discount-info">Discount: ₹0 (0%)</small>
                    </div>

                    <div class="form-group">
                        <label for="combined_arrear_amount">Arrear Amount to Pay</label>
                        <input type="number" id="combined_arrear_amount" name="arrear_paid_amount" step="0.01" 
                               value="<?php echo $arrear_input_value; ?>" 
                               min="0" max="<?php echo $arrear_max_value; ?>"
                               <?php echo ($arrear_bill_display <= 0) ? 'readonly' : ''; ?>>
                        <small>Maximum: ₹<?php echo $arrear_display; ?></small>
                    </div>

                    <div class="form-group">
                        <label for="combined_total_amount">Total Amount to Pay (Auto-calculated)</label>
                        <input type="text" id="combined_total_amount" name="amount" readonly style="font-weight: bold; color: var(--success-color);">
                        <small id="payment-breakdown">Breakdown: Current: ₹0 + Arrear: ₹0 = Total: ₹0</small>
                    </div>

                    <div class="form-group">
                        <label for="combined_payment_method">Payment Method</label>
                        <select id="combined_payment_method" name="payment_method" 
                                <?php echo (($current_bill_display <= 0) && ($arrear_bill_display <= 0)) ? 'disabled' : ''; ?>>
                            <option value="">Select Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Online">Online</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="combined_payer_name">Payer Name (Optional)</label>
                        <input type="text" id="combined_payer_name" name="payer_name" placeholder="Enter payer's name"
                               <?php echo (($current_bill_display <= 0) && ($arrear_bill_display <= 0)) ? 'readonly' : ''; ?>>
                    </div>

                    <div class="form-buttons">
                        <button type="submit" class="submit-btn" id="combined-submit-btn" 
                                <?php echo (($current_bill_display <= 0) && ($arrear_bill_display <= 0)) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-circle"></i> 
                            <?php echo (($current_bill_display <= 0) && ($arrear_bill_display <= 0)) ? 'No Amount Available' : 'Generate & Pay Combined Bill'; ?>
                        </button>
                        <button type="button" class="clear-form-btn" onclick="clearCombinedForm()" 
                                <?php echo (($current_bill_display <= 0) && ($arrear_bill_display <= 0)) ? 'disabled' : ''; ?>>
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="history-section">
            <div class="section-header">
                <h3><i class="fas fa-receipt"></i> Payment History</h3>
                <div class="history-actions">
                    <a href="payment_history.php?con_no=<?php echo urlencode($con_no_php_var); ?>" class="action-btn search-btn">
                        <i class="fas fa-eye"></i> View All History
                    </a>
                    <button id="resetBillBtn" class="action-btn reset-btn">
                        <i class="fas fa-sync"></i> Reset Bill
                    </button>
                </div>
            </div>

            <?php if (count($payments) > 0): ?>
                <table class="payment-history">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Discount</th>
                            <th>Method</th>
                            <th>Paid On (IST)</th>
                            <th>Payer</th>
                            <th>User</th>
                            <th>Txn ID</th>
                            <th class="action-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): 
                            $paid_time = date('d-m-Y h:i:s A', strtotime($payment['paid_on']));
                            $payment_type_display = $payment['payment_type'];
                            if ($payment_type_display === 'combined') {
                                $payment_type_display = 'Combined Payment';
                            } else {
                                $payment_type_display = ucfirst($payment_type_display);
                            }
                            
                            // Format amounts for display only
                            $amount_display = formatAmountForDisplay($payment['amount']);
                            $current_paid = formatAmountForDisplay($payment['current_paid_amount'] ?? 0);
                            $arrear_paid = formatAmountForDisplay($payment['arrear_paid_amount'] ?? 0);
                            $discount = formatAmountForDisplay($payment['discount_amount'] ?? 0);
                            $discount_percent = number_format($payment['discount_percentage'] ?? 0, 2);
                        ?>
                            <tr>
                                <td data-label="ID"><?php echo htmlspecialchars($payment['id']); ?></td>
                                <td data-label="Type">
                                    <?php echo htmlspecialchars($payment_type_display); ?>
                                    <?php if ($payment['payment_type'] === 'combined'): ?>
                                        <br><small style="color: #666;">
                                            Current: ₹<?php echo $current_paid; ?> + 
                                            Arrear: ₹<?php echo $arrear_paid; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Amount">
                                    ₹<?php echo $amount_display; ?>
                                    <?php if ($payment['payment_type'] === 'combined'): ?>
                                        <br><small style="color: #666;">
                                            (Current: ₹<?php echo $current_paid; ?> + Arrear: ₹<?php echo $arrear_paid; ?>)
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Discount">
                                    ₹<?php echo $discount; ?> 
                                    (<?php echo $discount_percent; ?>%)
                                </td>
                                <td data-label="Method"><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td data-label="Paid On"><?php echo htmlspecialchars($paid_time); ?></td>
                                <td data-label="Payer"><?php echo htmlspecialchars($payment['payer_name'] ?? 'N/A'); ?></td>
                                <td data-label="User"><?php echo htmlspecialchars($payment['username']); ?></td>
                                <td data-label="Txn ID"><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                <td class="action-cell" data-label="Actions">
                                    <a href='download_paymentbill.php?id=<?php echo urlencode(htmlspecialchars($payment['con_no'] ?? '')); ?>&payment_id=<?php echo urlencode(htmlspecialchars($payment['id'] ?? '')); ?>' target='_blank' class='view-receipt-btn' title='View Receipt'>
                                        <i class='fas fa-eye'></i>
                                    </a>
                                    <button type="button" class="delete-btn" title="Delete Payment" onclick="openDeleteModal('<?php echo htmlspecialchars($payment['id']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-payments">No payments found for this connection.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const currentBillAmount = <?php echo json_encode($current_bill_display); ?>;
            const arrearBillAmount = <?php echo json_encode($arrear_bill_display); ?>;
            
            const currentCard = document.getElementById('current-payment-card');
            const arrearCard = document.getElementById('arrear-payment-card');
            const combinedCard = document.getElementById('combined-payment-card');
            const currentForm = document.getElementById('current-form');
            const arrearForm = document.getElementById('arrear-form');
            const combinedForm = document.getElementById('combined-form');
            const body = document.body;

            const currentAmountInput = document.getElementById('current_amount_pay');
            const currentDiscountPercentageInput = document.getElementById('current_discount_percentage');
            const currentFinalAmountInput = document.getElementById('current_final_amount');
            const currentSubmitBtn = document.getElementById('current-submit-btn');

            const combinedCurrentAmountInput = document.getElementById('combined_current_amount');
            const combinedDiscountPercentageInput = document.getElementById('combined_discount_percentage');
            const combinedArrearAmountInput = document.getElementById('combined_arrear_amount');
            const combinedTotalAmountInput = document.getElementById('combined_total_amount');
            const discountInfo = document.getElementById('discount-info');
            const paymentBreakdown = document.getElementById('payment-breakdown');
            const combinedSubmitBtn = document.getElementById('combined-submit-btn');

            // Modal elements
            const resetModal = document.getElementById('passwordModal');
            const deleteModal = document.getElementById('deletePaymentModal');
            const resetBtn = document.getElementById('resetBillBtn');
            const closeModal = document.querySelector('.close-modal');
            const closeDeleteModal = document.querySelector('.close-delete-modal');
            const resetForm = document.getElementById('resetForm');
            const deletePaymentForm = document.getElementById('deletePaymentForm');
            const deletePaymentIdInput = document.getElementById('delete_payment_id');

            // Function to format amount without .00
            function formatAmount(amount) {
                if (Math.round(amount) === amount) {
                    return '₹' + Math.round(amount);
                }
                return '₹' + amount.toFixed(2);
            }

            // Function to show the correct form based on available options
            function showInitialForm() {
                // Hide all forms first
                currentForm.style.display = 'none';
                arrearForm.style.display = 'none';
                combinedForm.style.display = 'none';
                
                // Remove all selected classes
                currentCard.classList.remove('selected-current');
                arrearCard.classList.remove('selected-arrear');
                combinedCard.classList.remove('selected-combined');

                // Show the first available form
                if (currentBillAmount > 0) {
                    showForm(currentForm, currentCard);
                    updateCurrentPaymentAmount();
                } else if (arrearBillAmount > 0) {
                    showForm(arrearForm, arrearCard);
                } else if (currentBillAmount > 0 || arrearBillAmount > 0) {
                    showForm(combinedForm, combinedCard);
                    updateCombinedPaymentAmount();
                } else {
                    // If nothing is available, show current form but it will be disabled
                    showForm(currentForm, currentCard);
                    updateCurrentPaymentAmount();
                }
            }

            function showForm(formToShow, cardToSelect) {
                // Hide all forms
                currentForm.style.display = 'none';
                arrearForm.style.display = 'none';
                combinedForm.style.display = 'none';

                // Remove all selected classes
                currentCard.classList.remove('selected-current');
                arrearCard.classList.remove('selected-arrear');
                combinedCard.classList.remove('selected-combined');

                body.classList.remove('active-current-payment', 'active-arrear-payment');

                // Show selected form and add selected class
                formToShow.style.display = 'block';
                if (cardToSelect === currentCard) {
                    currentCard.classList.add('selected-current');
                    body.classList.add('active-current-payment');
                    updateCurrentPaymentAmount();
                } else if (cardToSelect === arrearCard) {
                    arrearCard.classList.add('selected-arrear');
                    body.classList.add('active-arrear-payment');
                } else if (cardToSelect === combinedCard) {
                    combinedCard.classList.add('selected-combined');
                    body.classList.add('active-current-payment', 'active-arrear-payment');
                    updateCombinedPaymentAmount();
                }
            }

            function updateCurrentPaymentAmount() {
                let amount = parseFloat(currentAmountInput.value);
                
                if (isNaN(amount) || amount <= 0) {
                    currentFinalAmountInput.value = '₹0';
                    currentSubmitBtn.disabled = true;
                    currentSubmitBtn.innerHTML = '<i class="fas fa-times-circle"></i> No Amount Available';
                    return;
                } else if (amount > currentBillAmount) {
                    currentFinalAmountInput.value = '₹0';
                    currentSubmitBtn.disabled = true;
                    currentSubmitBtn.innerHTML = '<i class="fas fa-times-circle"></i> Amount Exceeds Limit';
                    return;
                } else {
                    currentSubmitBtn.disabled = false;
                    currentSubmitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Pay Current Bill';
                }
                
                let discountPercentage = parseFloat(currentDiscountPercentageInput.value);

                if (isNaN(discountPercentage) || discountPercentage < 0) {
                    discountPercentage = 0;
                    if (currentBillAmount > 0) {
                        currentDiscountPercentageInput.value = '';
                    }
                } else if (discountPercentage > 100) {
                    discountPercentage = 100;
                    currentDiscountPercentageInput.value = '100';
                }

                const calculatedDiscountAmount = (amount * discountPercentage) / 100;
                const finalAmount = Math.max(0, amount - calculatedDiscountAmount);

                currentFinalAmountInput.value = formatAmount(finalAmount);
            }

            function updateCombinedPaymentAmount() {
                let currentAmount = parseFloat(combinedCurrentAmountInput.value);
                if (isNaN(currentAmount) || currentAmount < 0) {
                    currentAmount = 0;
                    combinedCurrentAmountInput.value = '0';
                }
                
                let discountPercentage = parseFloat(combinedDiscountPercentageInput.value);

                if (isNaN(discountPercentage) || discountPercentage < 0) {
                    discountPercentage = 0;
                    if (currentBillAmount > 0) {
                        combinedDiscountPercentageInput.value = '';
                    }
                } else if (discountPercentage > 100) {
                    discountPercentage = 100;
                    combinedDiscountPercentageInput.value = '100';
                }

                const discountAmount = (currentAmount * discountPercentage) / 100;
                const finalCurrentAmount = Math.max(0, currentAmount - discountAmount);

                let arrearPaidAmount = parseFloat(combinedArrearAmountInput.value);
                if (isNaN(arrearPaidAmount) || arrearPaidAmount < 0) {
                    arrearPaidAmount = 0;
                    combinedArrearAmountInput.value = '0';
                } else if (arrearPaidAmount > arrearBillAmount) {
                    arrearPaidAmount = arrearBillAmount;
                    combinedArrearAmountInput.value = arrearBillAmount;
                }

                const finalTotalAmount = finalCurrentAmount + arrearPaidAmount;

                combinedTotalAmountInput.value = formatAmount(finalTotalAmount);
                
                // Update discount info - show without decimal places if possible
                discountInfo.textContent = `Discount: ${formatAmount(discountAmount)} (${discountPercentage}%)`;
                
                // Update payment breakdown - show without decimal places if possible
                paymentBreakdown.textContent = `Breakdown: Current: ${formatAmount(finalCurrentAmount)} + Arrear: ${formatAmount(arrearPaidAmount)} = Total: ${formatAmount(finalTotalAmount)}`;
                
                // Enable/disable submit button
                if (currentAmount <= 0 && arrearPaidAmount <= 0) {
                    combinedSubmitBtn.disabled = true;
                    combinedSubmitBtn.innerHTML = '<i class="fas fa-times-circle"></i> No Amount Available';
                } else {
                    combinedSubmitBtn.disabled = false;
                    combinedSubmitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Generate & Pay Combined Bill';
                }
            }

            // Clear form functions
            window.clearCurrentForm = function() {
                if (currentBillAmount > 0) {
                    currentAmountInput.value = <?php echo $current_input_value; ?>;
                    currentDiscountPercentageInput.value = '';
                    updateCurrentPaymentAmount();
                    document.getElementById('current_payment_method').selectedIndex = 0;
                    document.getElementById('current_payer_name').value = '';
                }
            };

            window.clearArrearForm = function() {
                if (arrearBillAmount > 0) {
                    document.getElementById('arrear_amount_pay').value = <?php echo $arrear_input_value; ?>;
                    document.getElementById('arrear_payment_method').selectedIndex = 0;
                    document.getElementById('arrear_payer_name').value = '';
                }
            };

            window.clearCombinedForm = function() {
                if (currentBillAmount > 0 || arrearBillAmount > 0) {
                    combinedCurrentAmountInput.value = <?php echo $current_input_value; ?>;
                    combinedDiscountPercentageInput.value = '';
                    combinedArrearAmountInput.value = <?php echo $arrear_input_value; ?>;
                    document.getElementById('combined_payment_method').selectedIndex = 0;
                    document.getElementById('combined_payer_name').value = '';
                    updateCombinedPaymentAmount();
                }
            };

            // Open delete payment modal
            window.openDeleteModal = function(paymentId) {
                deletePaymentIdInput.value = paymentId;
                deleteModal.style.display = 'block';
            };

            // Modal functions
            resetBtn.addEventListener('click', function() {
                resetModal.style.display = 'block';
            });

            closeModal.addEventListener('click', function() {
                resetModal.style.display = 'none';
            });

            closeDeleteModal.addEventListener('click', function() {
                deleteModal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target === resetModal || event.target === deleteModal) {
                    resetModal.style.display = 'none';
                    deleteModal.style.display = 'none';
                }
            });

            resetForm.addEventListener('submit', function(e) {
                const passwordInput = document.getElementById('reset_password');
                if (!passwordInput.value) {
                    e.preventDefault();
                    alert('Please enter the password to reset the bill.');
                }
            });

            deletePaymentForm.addEventListener('submit', function(e) {
                const passwordInput = document.getElementById('delete_password');
                if (!passwordInput.value) {
                    e.preventDefault();
                    alert('Please enter the password to delete the payment.');
                }
            });

            // Event listeners for payment cards
            currentCard.addEventListener('click', function() {
                if (currentBillAmount > 0) {
                    showForm(currentForm, currentCard);
                }
            });

            arrearCard.addEventListener('click', function() {
                if (arrearBillAmount > 0) {
                    showForm(arrearForm, arrearCard);
                }
            });

            combinedCard.addEventListener('click', function() {
                if (currentBillAmount > 0 || arrearBillAmount > 0) {
                    showForm(combinedForm, combinedCard);
                }
            });

            // Event listeners for form updates
            currentAmountInput.addEventListener('input', updateCurrentPaymentAmount);
            currentDiscountPercentageInput.addEventListener('input', updateCurrentPaymentAmount);

            combinedCurrentAmountInput.addEventListener('input', updateCombinedPaymentAmount);
            combinedDiscountPercentageInput.addEventListener('input', updateCombinedPaymentAmount);
            combinedArrearAmountInput.addEventListener('input', updateCombinedPaymentAmount);

            // Close message button
            const closeMessageBtn = document.querySelector('.close-message');
            if(closeMessageBtn) {
                closeMessageBtn.addEventListener('click', function() {
                    this.parentElement.style.display = 'none';
                });
            }

            // Show initial form on page load
            showInitialForm();
            
            // Check if we need to force refresh (after payment cancel)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('force_refresh')) {
                // Remove refresh parameter to avoid infinite loop
                const cleanUrl = window.location.pathname + '?con_no=<?php echo urlencode($con_no_php_var); ?>';
                window.history.replaceState({}, document.title, cleanUrl);
                // Force a small delay then reload the page
                setTimeout(() => {
                    window.location.reload(true);
                }, 100);
            }
        });
    </script>
</body>
</html>