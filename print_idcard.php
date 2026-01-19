<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
// यह सुनिश्चित करता है कि आपको कोई भी PHP त्रुटि तुरंत दिखाई दे।
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Require necessary files
// सुनिश्चित करें कि 'config.php' में आपका PDO डेटाबेस कनेक्शन सही ढंग से 'return $pdo;' करता है।
require_once 'config.php';
// सुनिश्चित करें कि 'qrlib.php' आपके सर्वर पर मौजूद है और सही पाथ पर है।
require_once 'qrlib.php';

// Check if user is logged in
// यह सुनिश्चित करने के लिए कि केवल लॉग इन उपयोगकर्ता ही इस पेज तक पहुँच सकें।
if (!isset($_SESSION['username']) || !isset($_SESSION['otp_verified'])) {
    header("Location: index.php");
    exit();
}

// Automatic logout code
// उपयोगकर्ता की निष्क्रियता के बाद स्वचालित रूप से लॉगआउट करने के लिए।
$timeout_duration = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Get PDO object from config.php
$pdo = require 'config.php';

if (!($pdo instanceof PDO)) {
    die("Database connection object not obtained. Please check config.php.");
}

$cards = [];
$error_message = '';

try {
    // Fetch ALL ID cards from the employee_id_cards table
    // 'id_no AS id' का उपयोग किया गया है क्योंकि आपकी टेबल में 'id' नामक कोई कॉलम नहीं है,
    // लेकिन आपको HTML में `$card['id']` की आवश्यकता हो सकती है।
    // 'qr_code_path' को SELECT स्टेटमेंट से हटा दिया गया है क्योंकि यह डेटाबेस में मौजूद नहीं है।
    // इसके बजाय, QR कोड PHP द्वारा डायनामिक रूप से जेनरेट किया जाएगा।
    $stmt = $pdo->prepare("SELECT id_no AS id, id_no, name, father_name, designation, mobile_no, blood_group, address, uan_no, esic_no, photo FROM employee_id_cards ORDER BY id_no ASC");
    $stmt->execute();
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cards)) {
        $error_message = 'कोई ID कार्ड उपलब्ध नहीं हैं। कृपया सुनिश्चित करें कि डेटाबेस में रिकॉर्ड मौजूद हैं।';
    }

} catch(PDOException $e) {
    $error_message = 'डेटाबेस त्रुटि: ' . $e->getMessage() . '. कृपया अपने डेटाबेस कनेक्शन और टेबल कॉलम की जाँच करें।';
    error_log("Print All IDs database error: " . $e->getMessage());
} catch(Exception $e){
    $error_message = 'आईडी कार्ड जनरेशन में एक सामान्य त्रुटि हुई: ' . $e->getMessage();
    error_log("Print All IDs general error: " . $e->getMessage());
}

// यह सुनिश्चित करता है कि ब्राउज़र सामग्री को सही ढंग से एन्कोड करे।
header('Content-Type: text/html; charset=UTF-8');
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>सभी ID कार्ड प्रिंट करें</title>
    <style>
        /* General Body Styling for screen view */
        body {
            margin: 0;
            padding: 20px;
            background: #f7f9fc;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Container for multiple ID cards in screen view */
        .id-card-wrapper {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px; /* Space between cards on screen */
            padding: 20px;
        }

        /* Base ID Card Container Styling (common for screen and print) */
        .id-card {
            width: 5.9cm; /* ID कार्ड की आवश्यक चौड़ाई */
            height: 8.9cm; /* ID कार्ड की आवश्यक ऊँचाई */
            background: #fff;
            position: relative;
            box-shadow: 0 0 6px rgba(0,0,0,0.3);
            box-sizing: border-box; /* Padding और border को width/height में शामिल करें */
            display: flex;
            flex-direction: row;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 20px; /* Space for screen view, overridden in print */
        }

        /* Orange Accent Strip on Left side of the ID card */
        .accent {
            width: 35px; /* स्ट्रिप की चौड़ाई */
            height: 100%;
            background: #ff9933; /* नारंगी रंग */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            box-sizing: border-box;
        }

        .accent span {
            writing-mode: vertical-lr; /* टेक्स्ट को लंबवत बनाता है */
            transform: rotate(0deg); /* 'vertical-lr' के साथ, यह स्वतः ही घूमता है */
            font-size: 18px;
            font-weight: bold;
            color: #000;
            text-align: center;
            white-space: nowrap; /* टेक्स्ट को एक ही लाइन में रखता है */
            line-height: 1.3;
            letter-spacing: 0.5px;
            padding: 10px 0;
        }

        /* Main Content Area (right side of the orange strip) */
        .content {
            flex: 1; /* बची हुई जगह लेता है */
            padding: 4px; /* समग्र सामग्री के लिए पैडिंग */
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
        }

        /* Government Logo Styling */
        .logo {
            width: 50px;
            height: 50px;
            margin-top: 15px;
            margin-bottom: 3px;
            object-fit: contain; /* इमेज को बिना क्रॉप किए फिट करता है */
        }

        /* Header Text (Nagar Palika Parishad) */
        .header-text {
            text-align: center;
            font-size: 12.5px;
            font-weight: bold;
            margin-bottom: 5px;
            line-height: 1.1;
            color: #333;
            text-transform: uppercase;
        }

        /* Employee Photo Placeholder/Container */
        .photo-placeholder {
            width: 66px;
            height: 93px;
            border: 1.5px solid #ff9933; /* नारंगी बॉर्डर */
            border-radius: 20%; /* अंडाकार आकार */
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 7px;
            overflow: hidden; /* सुनिश्चित करता है कि फोटो अंदर फिट हो */
        }

        .photo-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* इमेज को कंटेनर में फिट करने के लिए क्रॉप करता है */
        }

        /* Employee Name Styling */
        .name {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #1a5276; /* नीला रंग */
            margin-bottom: 2px;
            line-height: 1.1;
            text-transform: uppercase;
        }

        /* Employee Designation Styling */
        .design {
            text-align: center;
            font-size: 11px;
            color: #333;
            margin-bottom: 5px;
            line-height: 1.1;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* Bottom Section Container (for QR, Authorized Sig, Executive Officer) */
        .bottom-section {
            display: flex;
            width: 100%;
            justify-content: space-between;
            align-items: stretch;
            padding: 0 5px 5px 5px;
            box-sizing: border-box;
            flex-grow: 1; /* बची हुई ऊर्ध्वाधर जगह लेता है */
        }

        .bottom-left {
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* बाईं ओर संरेखित करता है */
            justify-content: flex-end; /* सामग्री को नीचे धकेलता है */
            flex-shrink: 0; /* सिकुड़ने से रोकता है */
        }

        /* QR Code Container Styling */
        .qr-container {
            width: auto;
            text-align: left;
            margin-bottom: 17px; /* QR और अधिकृत हस्ताक्षर के बीच की जगह */
        }

        .qr {
            width: 60px; /* QR कोड का आकार */
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

        /* Authorized Signature Text Styling */
        .authorized-sig {
            font-size: 9px;
            line-height: 2.2;
            color: #333;
            white-space: nowrap;
            border: none;
            margin-top: 3px;
            margin-bottom: 0px;
            text-align: left;
        }

        /* Signature Area (Right side of bottom section) */
        .signature-area {
            flex-grow: 1; /* बीच में उपलब्ध सभी जगह लेता है */
            display: flex;
            flex-direction: column;
            justify-content: flex-end; /* हस्ताक्षर सामग्री को अपने क्षेत्र के नीचे धकेलता है */
            align-items: flex-start; /* सामग्री को इस क्षेत्र के भीतर बाईं ओर संरेखित करता है */
            padding-bottom: 5px; /* निचले किनारे से पैडिंग */
        }

        /* Signature Image Styling */
        .signature-area .signature-image {
            width: 100px;
            height: auto;
            margin-bottom: -55px; /* हस्ताक्षर और टेक्स्ट के बीच की जगह */
            margin-left: -3px; /* सटीक संरेखण के लिए इसे समायोजित करें */
        }

        /* Executive Officer Text Styling */
        .executive-officer {
            font-size: 9px;
            line-height: 4.1;
            color: #333;
            font-weight: bold;
            margin-bottom: 10;
            text-align: left;
            width: 100%;
            margin-left: -8px; /* सटीक संरेखण के लिए इसे समायोजित करें */
            white-space: nowrap; /* टेक्स्ट को एक ही लाइन में रखता है */
        }

        /* Button Container Styling (for screen view) */
        .button-container {
            text-align: center;
            margin-top: 20px;
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

        /* Print Specific Styles - A4 page with 4 ID cards */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: none;
                display: block; /* प्रिंट के लिए फ्लेक्सबॉक्स को ओवरराइड करता है */
                height: auto; /* प्रिंट के लिए ऊंचाई को ओवरराइड करता है */
            }
            .id-card-wrapper {
                /* A4 पेज पर 2x2 ग्रिड लेआउट के लिए CSS ग्रिड का उपयोग करें */
                display: grid;
                grid-template-columns: repeat(2, 5.9cm); /* दो कॉलम, प्रत्येक कार्ड की चौड़ाई */
                grid-template-rows: repeat(2, 8.9cm); /* दो पंक्तियाँ, प्रत्येक कार्ड की ऊंचाई */
                gap: 5mm; /* कार्ड के बीच की जगह */
                justify-content: center; /* ग्रिड को पेज पर क्षैतिज रूप से केंद्रित करें */
                align-content: center; /* ग्रिड को पेज पर लंबवत रूप से केंद्रित करें */
                width: 100%;
                height: 100%;
                padding: 10mm; /* A4 पेज के लिए समग्र पैडिंग */
                box-sizing: border-box;
            }
            .id-card {
                width: 5.9cm; /* प्रिंट के लिए निश्चित चौड़ाई */
                height: 8.9cm; /* प्रिंट के लिए निश्चित ऊंचाई */
                box-shadow: none;
                border: 1px solid #ccc; /* प्रिंट के लिए एक सूक्ष्म बॉर्डर जोड़ें */
                margin: 0; /* व्यक्तिगत कार्ड मार्जिन हटाएँ, ग्रिड गैप द्वारा नियंत्रित */
                page-break-after: avoid; /* ग्रिड के भीतर कार्ड के बीच पेज ब्रेक को रोकें */
                border-radius: 0; /* क्लीनर प्रिंट के लिए बॉर्डर-रेडियस हटाएँ */
                overflow: visible; /* सुनिश्चित करें कि प्रिंट इंजन अलग तरह से प्रस्तुत होने पर सामग्री कटी नहीं है */
            }
            .accent {
                /* प्रिंट करते समय सटीक रंग सुनिश्चित करें */
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
            .button-container {
                display: none; /* प्रिंट करते समय बटन छिपाएँ */
            }
        }
    </style>
</head>
<body>

    <div class="button-container no-print">
        <a href="id_list.php" class="btn btn-secondary">← सूची पर वापस जाएं</a>
        <button onclick="window.print()" class="btn">सभी ID कार्ड प्रिंट करें</button>
    </div>

    <?php if (!empty($cards)): ?>
        <div class="id-card-wrapper">
            <?php foreach ($cards as $card):
                $qrCodeImagePath = ''; // प्रत्येक कार्ड के लिए रीसेट करें
                try {
                    // --- प्रत्येक कार्ड के लिए QR कोड जनरेशन ---
                    // QR कोड में एन्कोड करने के लिए डेटा। आप अपनी आवश्यकतानुसार इसमें जोड़ या हटा सकते हैं।
                    $qr_data = json_encode([
                        'id_no'       => $card['id_no'] ?? '',
                        'name'        => $card['name'] ?? '',
                        'father_name' => $card['father_name'] ?? '',
                        'designation' => $card['designation'] ?? '',
                        'mobile_no'   => $card['mobile_no'] ?? '',
                        'blood_group' => $card['blood_group'] ?? '',
                        'address'     => $card['address'] ?? '',
                        'uan_no'      => $card['uan_no'] ?? '',
                        'esic_no'     => $card['esic_no'] ?? '',
                    ]);

                    $encoded = base64_encode($qr_data);
                    // यह वह URL है जिसे QR कोड स्कैन करने पर खोलेगा।
                    // सुनिश्चित करें कि 'qr_info.php' मौजूद है और डेटा को डीकोड करके प्रदर्शित करता है।
                    $qr_url = "https://sunnydhaka.fwh.is/qr_info.php?data=" . urlencode($encoded);

                    // QR कोड इमेज स्टोरेज डायरेक्टरी और फ़ाइल पाथ परिभाषित करें
                    $qrCodeDir = 'qrcodes'; // यह डायरेक्टरी वेब सर्वर द्वारा लिखे जाने योग्य होनी चाहिए
                    if (!is_dir($qrCodeDir)) {
                        mkdir($qrCodeDir, 0777, true); // यदि डायरेक्टरी मौजूद नहीं है तो बनाएँ
                    }
                    $qrFile = $qrCodeDir . '/card_' . $card['id_no'] . '.png';

                    // QR कोड इमेज जेनरेट करें
                    // QR_ECLEVEL_L: सबसे कम त्रुटि सुधार स्तर, सबसे छोटा QR कोड।
                    // 4: मॉड्यूल का आकार (पिक्सेल में)
                    // 2: फ्रेम का मार्जिन (मॉड्यूल में)
                    QRcode::png($qr_url, $qrFile, QR_ECLEVEL_L, 4, 2);
                    $qrCodeImagePath = $qrFile;

                } catch (Exception $e) {
                    error_log("QR Code generation error for ID " . ($card['id_no'] ?? 'N/A') . ": " . $e->getMessage());
                    // यदि QR कोड जनरेशन विफल हो जाता है, तो एक प्लेसहोल्डर इमेज दिखाएँ।
                    $qrCodeImagePath = 'placeholder_qr.png';
                }
            ?>
                <div class="id-card">
                    <div class="accent">
                        <span>URBAN DEVELOPMENT GOV, U.P</span>
                    </div>

                    <div class="content">
                        <img src="government_logo.png" alt="Gov Logo" class="logo"> <div class="header-text">
                            NAGAR PALIKA PARISHAD<br>
                            NAKUR, SAHARANPUR
                        </div>

                        <div class="photo-placeholder">
                            <?php if (!empty($card['photo']) && file_exists($card['photo'])): ?>
                                <img src="<?= htmlspecialchars($card['photo']) ?>" alt="Employee Photo">
                            <?php else: ?>
                                <img src="placeholder_photo_square.png" alt="No Photo">
                            <?php endif; ?>
                        </div>

                        <div class="name"><?= htmlspecialchars($card['name']) ?></div>
                        <div class="design"><?= htmlspecialchars($card['designation']) ?></div>

                        <div class="bottom-section">
                            <div class="bottom-left">
                                <div class="qr-container">
                                    <?php if ($qrCodeImagePath && file_exists($qrCodeImagePath)): ?>
                                        <div class="qr"><img src="<?= htmlspecialchars($qrCodeImagePath) ?>" alt="QR Code"></div>
                                    <?php else: ?>
                                        <div class="qr"><img src="placeholder_qr.png" alt="QR Code Missing"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="authorized-sig">AUTHORIZED SIG -</div>
                            </div>

                            <div class="signature-area">
                                <img src="Picture1.png" alt="Signature" class="signature-image"> <div class="executive-officer">EXECUTIVE OFFICER</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

</body>
</html>