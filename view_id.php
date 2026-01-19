<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Require necessary files
require_once 'config.php'; // Contains database connection ($pdo)
require_once 'qrlib.php';  // QR code library

$card = null;
$qrCodeImagePath = '';

// Check if an ID is provided in the URL
if (isset($_GET['id'])) {
    $id_no = $_GET['id'];

    // Input validation: Ensure ID contains only alphanumeric characters
    if (!preg_match('/^[A-Z0-9]+$/', $id_no)) {
        header("Location: id_list.php?error=invalid_id");
        exit();
    }

    try {
        // Prepare and execute the SQL query to fetch employee details
        $stmt = $pdo->prepare("
            SELECT id_no, name, father_name, designation, photo, mobile_no, blood_group, address, uan_no, esic_no
            FROM employee_id_cards WHERE id_no = :id_no
        ");
        $stmt->bindParam(':id_no', $id_no, PDO::PARAM_STR);
        $stmt->execute();
        $card = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no card found for the given ID, redirect with an error
        if (!$card) {
            header("Location: id_list.php?error=not_found");
            exit();
        }

        // --- QR Code Generation ---
        $qr_data = json_encode([
            'id_no'       => $card['id_no'] ?? '',
            'name'        => $card['name'] ?? '',
            'father_name' => $card['father_name'] ?? '',
            'designation' => $card['designation'] ?? '',
            'photo'       => $card['photo'] ?? '',
            'mobile_no'   => $card['mobile_no'] ?? '',
            'blood_group' => $card['blood_group'] ?? '',
            'address'     => $card['address'] ?? '',
            'uan_no'      => $card['uan_no'] ?? '',
            'esic_no'     => $card['esic_no'] ?? '',
        ]);

        $encoded = base64_encode($qr_data);
        $qr_url = "https://sunnydhaka.fwh.is/qr_info.php?data=" . urlencode($encoded);

        // Define QR code storage directory and file path
        $qrCodeDir = 'qrcodes';
        if (!is_dir($qrCodeDir)) {
            mkdir($qrCodeDir, 0777, true);
        }
        $qrFile = $qrCodeDir . '/card_' . $card['id_no'] . '.png';

        // Generate the QR code image
        QRcode::png($qr_url, $qrFile, QR_ECLEVEL_L, 4, 2);
        $qrCodeImagePath = $qrFile;

    } catch(PDOException $e) {
        error_log("Database error for ID " . ($id_no ?? 'N/A') . ": " . $e->getMessage());
        die("A database error occurred. Please try again later. Error: " . $e->getMessage());
    } catch(Exception $e){
        error_log("ID Card generation error for ID " . ($id_no ?? 'N/A') . ": " . $e->getMessage());
        die("Error processing ID card. Please try again later or contact support. Error: " . $e->getMessage());
    }
} else {
    header("Location: id_list.php?error=missing_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card</title>
    <style>
        /* General Body Styling */
        body {
            margin: 0;
            padding: 20px;
            background: #f7f9fc;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ID Card Container */
        .id-card {
            width: 5.9cm;
            height: 8.9cm;
            background: #fff;
            position: relative;
            box-shadow: 0 0 6px rgba(0,0,0,0.3);
            box-sizing: border-box;
            display: flex;
            flex-direction: row;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        /* Orange Accent Strip on Left */
      .accent {
    width: 35px; /* Or whatever width you need, e.g., 2cm, 100px */
    height: 100%;
    background: #ff9933;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px; /* Always specify units for padding */
    box-sizing: border-box; /* Makes padding included in the width */
}

        .accent span {
            writing-mode: vertical-lr;
            transform: rotate(0deg);
            font-size: 18px;
            font-weight: bold;
            color: #000;
            text-align: center;
            white-space: nowrap;
            line-height: 1.3;
            letter-spacing: 0.5px;
            padding: 10px 0;
        }

        /* Main Content Area */
        .content {
            flex: 1;
            padding: 4px; /* Padding for overall content */
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
        }

        /* Government Logo */
        .logo {
            width: 60px;
            height: 60px;
            margin-top: 15px;
            margin-bottom: 3px;
            object-fit: contain;
        }

        /* Header Text */
        .header-text {
            text-align: center;
            font-size: 12.5px;
            font-weight: bold;
            margin-bottom: 5px;
            line-height: 1.1;
            color: #333;
            text-transform: uppercase;
        }

        /* Employee Photo Placeholder - Adjusted to match image */
        .photo-placeholder {
            width: 66px; /* Adjust width as needed */
            height: 93px; /* Adjust height as needed */
            border: 1.5px solid #ff9933; /* Orange border */
            border-radius: 20%; /* Oval shape */
            background: #eee; /* Light gray background */
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 7px;
            overflow: hidden; /* Ensure photo fits inside */
        }

        .photo-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Employee Name */
        .name {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #1a5276;
            margin-bottom: 2px;
            line-height: 1.1;
            text-transform: uppercase;
        }

        /* Employee Designation */
        .design {
            text-align: center;
            font-size: 11px;
            color: #333;
            margin-bottom: 5px; /* Spacing before bottom section */
            line-height: 1.1;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* NEW: Bottom Section Container (for QR, Sig, Officer) */
        .bottom-section {
            display: flex;
            width: 100%;
            justify-content: space-between; /* Spread items across */
            align-items: stretch; /* Changed from flex-end to stretch */
            padding: 0 5px 5px 5px; /* Adjusted padding to be tighter at bottom */
            box-sizing: border-box;
            flex-grow: 1; /* Allow this section to take up remaining vertical space */
        }

        .bottom-left {
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Default for bottom-left */
            justify-content: flex-end; /* Push content to the bottom */
            flex-shrink: 0; /* Prevent shrinking */
        }

        /* QR Code Container */
        .qr-container {
            width: auto; /* Let content define width */
            text-align: left; /* Ensure QR code aligns left within its column */
            margin-bottom: 17px; /* Space between QR and authorized sig */
        }

        .qr {
            width: 60px; /* Smaller QR code as per image */
            height: 60px;
            background: #fff;
            padding: 1px;
            border: 1px solid #ddd;
            border-radius: 7px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: inline-block;
        }

        .qr img {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Authorized Signature Text */
        .authorized-sig {
            font-size: 9px; /* Adjusted font size slightly from 8px for better readability */
            line-height: 2.2; /* Adjusted line height */
            color: #333;
            white-space: nowrap; /* Prevent text from breaking */
            border: none; /* Ensure no border/line */
            margin-top: 3px; /* Small space above */
            margin-bottom: 0px; /* Small space below */
            text-align: left; /* Keep this left-aligned within bottom-left column */
        }

        /* Signature Area (Middle) */
        .signature-area {
            flex-grow: 1; /* Takes up all available space in the middle */
            display: flex;
            flex-direction: column; /* For signature text below image */
            justify-content: flex-end; /* Push signature content to the bottom of its area */
            align-items: flex-start; /* Changed to flex-start to align content left within this area */
            padding-bottom: 5px; /* Spacing from bottom edge */
        }

        /* Signature Image Styling */
        .signature-area .signature-image {
            width: 100px; /* Adjust width as needed */
            height: auto; /* Maintain aspect ratio */
            margin-bottom: -55px; /* Space between signature and text */
            margin-left: -3px; /* <-- इसे एडजस्ट करें */
        }

        /* Executive Officer Text Styling */
        .executive-officer {
            font-size: 9px;
            line-height: 4.1;
            color: #333;
            font-weight: bold;
            margin-bottom: 10;
            text-align: left; /* या इसे center करें यदि आप इसे केंद्र में लाना चाहते हैं */
            width: 100%;
            margin-left: -8px; /* <-- इस वैल्यू को एडजस्ट करें (नकारात्मक मान बाईं ओर खिसकाएगा, सकारात्मक दाईं ओर) */
            white-space: nowrap; /* <-- यह बदलाव है: टेक्स्ट को एक ही लाइन में रखेगा */
        }

        /* Button Container */
        .button-container {
            text-align: center;
            margin-top:20px;
        }

        .btn {
            padding: 8px 16px;
            font-size: 14px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            margin: 0 5px;
            cursor: pointer;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: #6c757d;
        }

        /* Print Specific Styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: none;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }
            .id-card {
                width: 5.9cm;
                height: 8.9cm;
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 0;
            }
            .accent {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .button-container {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="id-card">
        <div class="accent">
            <span>URBAN DEVELOPMENT GOV, U.P</span>
        </div>

        <div class="content">
            <img src="government_logo.png" alt="Gov Logo" class="logo">

            <div class="header-text">
                NAGAR PALIKA PARISHAD<br>
                NAKUR, SAHARANPUR
            </div>

            <div class="photo-placeholder">
                <?php if (!empty($card['photo'])): ?>
                    <img src="<?= htmlspecialchars($card['photo']) ?>" alt="Photo">
                <?php endif; ?>
            </div>

            <div class="name"><?= htmlspecialchars($card['name']) ?></div>
            <div class="design"><?= htmlspecialchars($card['designation']) ?></div>

            <div class="bottom-section">
                <div class="bottom-left">
                    <div class="qr-container">
                        <?php if ($qrCodeImagePath && file_exists($qrCodeImagePath)): ?>
                            <div class="qr"><img src="<?= htmlspecialchars($qrCodeImagePath) ?>" alt="QR Code"></div>
                        <?php endif; ?>
                    </div>
                    <div class="authorized-sig">AUTHORIZED SIG -</div>
                </div>

                <div class="signature-area">
                    <img src="Picture1.png" alt="Signature" class="signature-image">
                    <div class="executive-officer">EXECUTIVE OFFICER</div>
                </div>

                </div>
        </div>
    </div>

    <div class="button-container">
        <a href="id_list.php" class="btn btn-secondary">Back to List</a>
        <button onclick="window.print()" class="btn">Print ID Card</button>
    </div>

</body>
</html>