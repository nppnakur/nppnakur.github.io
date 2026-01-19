<?php
// config.php में पहले से session_start() है, इसलिए यहाँ से हटा रहे हैं
// session_start();

// Security: Checks if the user is logged in.
// if (!isset($_SESSION['username'])) { header("Location: index.php"); exit(); }

// Includes the 'config.php' file which contains your database connection details.
include "config.php";

// --- Define Database Column Names Here (MUST MATCH EXACTLY) ---
$db_col_id             = 'id'; // PRIMARY KEY
$db_col_con_no         = '`connection No`';
$db_col_ward_no        = 'ward_no';
$db_col_owner_name     = '`Owner Name`';
$db_col_mobile         = 'Mobile';
$db_col_current_amount = '`Current amount 2025-26`';
$db_col_arrear_balance = '`Arrear Balance`';
$db_col_remaining_balance = 'remaining_balance';

// PDO error mode setting
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// connection_detail.php से 'id' पैरामीटर में connection_no भेजा जा रहा है
$connection_no_to_edit = $_GET['id'] ?? '';

if (!isset($_GET['id']) || trim($_GET['id']) === '') {
    die("त्रुटि: संपादन के लिए कनेक्शन नंबर प्रदान नहीं किया गया है।");
}

$message = ""; // To store success/error messages

// Handle POST request (form submission)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect updated data from the form
    $new_con_no = $_POST['connection_no'] ?? '';
    $new_ward_no = $_POST['ward_no'] ?? '';
    $new_owner_name = $_POST['owner_name'] ?? '';
    $new_mobile = $_POST['mobile'] ?? '';
    // Ensure numeric values are properly handled, default to 0 if empty or not numeric
    $new_current_amount = is_numeric($_POST['current_amount'] ?? '') ? floatval($_POST['current_amount']) : 0;
    $new_arrear_balance = is_numeric($_POST['arrear_balance'] ?? '') ? floatval($_POST['arrear_balance']) : 0;
    
    // Calculate remaining balance automatically
    $new_remaining_balance = $new_current_amount + $new_arrear_balance;

    // Validate inputs (basic validation)
    if (empty(trim($new_con_no)) || empty(trim($new_owner_name))) {
        $message = '<div class="alert error">कनेक्शन नंबर और मालिक का नाम आवश्यक है।</div>';
    } else {
        try {
            // Prepare UPDATE statement
            $stmt = $pdo->prepare("UPDATE bills SET
                $db_col_con_no = :con_no,
                $db_col_ward_no = :ward_no,
                $db_col_owner_name = :owner_name,
                $db_col_mobile = :mobile,
                $db_col_current_amount = :current_amount,
                $db_col_arrear_balance = :arrear_balance,
                $db_col_remaining_balance = :remaining_balance
                WHERE $db_col_con_no = :old_con_no");

            $stmt->bindParam(':con_no', $new_con_no);
            $stmt->bindParam(':ward_no', $new_ward_no);
            $stmt->bindParam(':owner_name', $new_owner_name);
            $stmt->bindParam(':mobile', $new_mobile);
            $stmt->bindParam(':current_amount', $new_current_amount);
            $stmt->bindParam(':arrear_balance', $new_arrear_balance);
            $stmt->bindParam(':remaining_balance', $new_remaining_balance);
            $stmt->bindParam(':old_con_no', $connection_no_to_edit);

            $stmt->execute();

            if ($stmt->rowCount()) {
                $message = '<div class="alert success">रिकॉर्ड सफलतापूर्वक अपडेट किया गया!</div>';
                // Update $connection_no_to_edit in case the connection number itself was changed
                $connection_no_to_edit = $new_con_no;
            } else {
                $message = '<div class="alert info">कोई बदलाव नहीं किया गया।</div>';
            }

        } catch (PDOException $e) {
            $message = '<div class="alert error">अपडेट करते समय त्रुटि: ' . $e->getMessage() . '</div>';
            error_log("Edit connection PDO Error: " . $e->getMessage());
        }
    }
}

// Fetch current data for display in the form
try {
    $stmt = $pdo->prepare("SELECT
        $db_col_con_no,
        $db_col_ward_no,
        $db_col_owner_name,
        $db_col_mobile,
        $db_col_current_amount,
        $db_col_arrear_balance,
        $db_col_remaining_balance
        FROM bills
        WHERE $db_col_con_no = :con_no");

    $stmt->bindParam(':con_no', $connection_no_to_edit);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        $message = '<div class="alert error">यह कनेक्शन नंबर मौजूद नहीं है।</div>';
        $data = [];
    }
} catch (PDOException $e) {
    $message = '<div class="alert error">डेटा प्राप्त करते समय त्रुटि: ' . $e->getMessage() . '</div>';
    error_log("Fetch edit data PDO Error: " . $e->getMessage());
    $data = [];
}

// Extract data for form fields
$current_con_no = $data[str_replace('`', '', $db_col_con_no)] ?? '';
$current_ward_no = $data[$db_col_ward_no] ?? '';
$current_owner_name = $data[str_replace('`', '', $db_col_owner_name)] ?? '';
$current_mobile = $data[$db_col_mobile] ?? '';
$current_current_amount = $data[str_replace('`', '', $db_col_current_amount)] ?? '';
$current_arrear_balance = $data[str_replace('`', '', $db_col_arrear_balance)] ?? '';
$current_remaining_balance = $data[$db_col_remaining_balance] ?? '';

?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>कनेक्शन संपादित करें</title>
    <link rel="icon" href="img1.png" type="image/png">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fa;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: linear-gradient(160deg, #007bff, #0047b3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 36px;
            height: 31.9px;
            width: 100%;
            box-sizing: border-box;
        }
        .topbar a {
            color: #fff;
            font-size: 20px;
            text-decoration: none;
            transition: 0.25s;
        }
        .topbar a:hover {
            opacity: 0.85;
            text-decoration: underline;
        }
        h2 {
            text-align: center;
            color: #007bff;
            font-size: 32px;
            margin: 32px 0 24px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.18);
        }
        .container {
            background-color: #ffffff;
            padding: 30px 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
            margin-bottom: 30px;
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: -20px;
        }
        .form-group {
            flex: 1 1 calc(50% - 10px);
            margin-bottom: 20px;
            min-width: 200px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"],
        input[type="number"] {
            width: calc(100% - 24px);
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="number"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-submit {
            background-color: #28a745;
            color: white;
            padding: 14px 25px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s;
            width: 100%;
            box-sizing: border-box;
            margin-top: 20px;
        }
        .btn-submit:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
        }
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .auto-calculated {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #28a745;
        }

        /* Mobile Portrait */
        @media (max-width: 767px) {
            .topbar {
                padding: 10px 15px;
                height: auto;
            }
            .topbar a {
                font-size: 16px;
            }
            h2 {
                font-size: 24px;
                margin: 20px 0 15px;
            }
            .container {
                padding: 20px 25px;
                margin: 15px;
            }
            .form-group {
                flex: 1 1 100%;
                min-width: auto;
            }
            input[type="text"],
            input[type="number"] {
                font-size: 14px;
                padding: 10px;
            }
        }

        /* Mobile Landscape and Tablets */
        @media (min-width: 480px) and (max-width: 1024px) and (orientation: landscape) {
            .container {
                max-width: 700px;
                padding: 25px 35px;
            }
            .form-row {
                gap: 15px;
            }
            .form-group {
                flex: 1 1 calc(50% - 7.5px);
                min-width: 180px;
            }
        }
        
        @media (min-width: 768px) and (orientation: landscape) {
            .container {
                max-width: 800px;
            }
            .form-group {
                 flex: 1 1 calc(33.333% - 13.333px);
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <a href="connection_detail.php">⬅️ वापस जाएं</a>
        <a href="logout.php">🚪 Logout</a>
    </div>

    <h2>✏️ कनेक्शन विवरण संपादित करें</h2>

    <div class="container">
        <?= $message; ?>
        <form method="POST" id="editForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="connection_no">कनेक्शन नंबर:</label>
                    <input type="text" id="connection_no" name="connection_no" value="<?= htmlspecialchars($current_con_no); ?>" required>
                </div>
                <div class="form-group">
                    <label for="ward_no">वर्ड नंबर:</label>
                    <input type="text" id="ward_no" name="ward_no" value="<?= htmlspecialchars($current_ward_no); ?>">
                </div>
                <div class="form-group">
                    <label for="owner_name">मालिक का नाम:</label>
                    <input type="text" id="owner_name" name="owner_name" value="<?= htmlspecialchars($current_owner_name); ?>" required>
                </div>
                <div class="form-group">
                    <label for="mobile">मोबाइल:</label>
                    <input type="text" id="mobile" name="mobile" value="<?= htmlspecialchars($current_mobile); ?>">
                </div>
                <div class="form-group">
                    <label for="current_amount">वर्तमान राशि:</label>
                    <input type="number" id="current_amount" name="current_amount" value="<?= htmlspecialchars($current_current_amount); ?>" step="0.01" oninput="calculateRemainingBalance()">
                </div>
                <div class="form-group">
                    <label for="arrear_balance">बकाया शेष:</label>
                    <input type="number" id="arrear_balance" name="arrear_balance" value="<?= htmlspecialchars($current_arrear_balance); ?>" step="0.01" oninput="calculateRemainingBalance()">
                </div>
                <div class="form-group">
                    <label for="remaining_balance">शेष राशि:</label>
                    <input type="number" id="remaining_balance" name="remaining_balance" value="<?= htmlspecialchars($current_remaining_balance); ?>" step="0.01" class="auto-calculated" readonly>
                </div>
            </div>
            <button type="submit" class="btn-submit">अपडेट करें</button>
        </form>
    </div>

    <script>
        function calculateRemainingBalance() {
            // Get values from current amount and arrear balance fields
            const currentAmount = parseFloat(document.getElementById('current_amount').value) || 0;
            const arrearBalance = parseFloat(document.getElementById('arrear_balance').value) || 0;
            
            // Calculate remaining balance
            const remainingBalance = currentAmount + arrearBalance;
            
            // Set the calculated value to remaining balance field
            document.getElementById('remaining_balance').value = remainingBalance.toFixed(2);
        }

        // Calculate initial remaining balance when page loads
        document.addEventListener('DOMContentLoaded', function() {
            calculateRemainingBalance();
        });
    </script>
</body>
</html>