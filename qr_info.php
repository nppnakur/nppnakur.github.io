<?php
// qr_info.php - Updated with enhanced design and English, uppercase text
// General PHP error reporting setup. In a production environment, it's recommended
// to log errors instead of displaying them to the user for security and cleaner output.
error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);

session_start();

$card_data = null;
$error_message = '';

if (isset($_GET['data'])) {
    $encoded_data = $_GET['data'];

    // Decode Base64 data from the GET parameter
    $decoded_json = base64_decode($encoded_data);

    // Convert JSON string to PHP array
    $card_data = json_decode($decoded_json, true);

    // Check JSON decoding success and data validity
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($card_data) || empty($card_data)) {
        $error_message = 'INVALID DATA RECEIVED. PLEASE SCAN A CORRECT QR CODE.';
        $card_data = null;
    } else {
        // **फोटो URL को सही करें - यह सबसे महत्वपूर्ण बदलाव है**
        if (!empty($card_data['photo'])) {
            $photo_path = $card_data['photo'];
            
            // अगर फोटो रिलेटिव पथ है तो पूर्ण URL में बदलें
            if (!preg_match('/^https?:\/\//', $photo_path)) {
                $base_url = "https://sunnydhaka.fwh.is/"; // अपना बेस URL डालें
                $card_data['photo'] = $base_url . ltrim($photo_path, '/');
            }
        }
    }
} else {
    $error_message = 'NO DATA TO DISPLAY.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMPLOYEE INFORMATION (QR SCAN)</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #e0e5ec;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        .info-container {
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 650px;
            width: 100%;
            position: relative;
            overflow: hidden;
            border: 1px solid #f0f0f0;
            text-align: center;
        }
        .info-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('img1.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 70%;
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
        }
        .info-container > *:not(.info-container::before) {
            position: relative;
            z-index: 1;
        }

        .info-container h2 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 30px;
            font-weight: 700;
            font-size: 1.8rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        }
        .detail-row {
            margin-bottom: 15px;
            display: flex;
            align-items: baseline;
            font-size: 1.2rem;
            line-height: 1.4;
            max-width: 580px;
            margin-left: auto;
            margin-right: auto;
            justify-content: center;
            gap: 15px;
        }
        .detail-label {
            font-weight: 600;
            color: #444;
            text-align: right;
            text-shadow: 0.5px 0.5px 1px rgba(0,0,0,0.2);
            flex-shrink: 0;
            width: 160px;
        }
        .dash-separator {
            flex-shrink: 0;
            margin: 0 8px;
            color: #555;
            text-shadow: 0.5px 0.5px 1px rgba(0,0,0,0.1);
        }
        .detail-value {
            color: #333;
            text-align: left;
            text-shadow: 0.5px 0.5px 1px rgba(0,0,0,0.2);
            flex-basis: auto;
            flex-grow: 1;
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
        }
        .single-line-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .info-photo {
            max-width: 180px;
            height: 210px; /* Fixed height for consistency */
            border-radius: 12px;
            border: 4px solid #007bff;
            object-fit: cover; /* यह महत्वपूर्ण है - फोटो को container में fit करेगा */
            background: #eee;
            margin: 0 auto 30px auto;
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
            display: block;
        }
        .info-photo:hover {
            transform: scale(1.03);
        }
        .photo-placeholder {
            max-width: 180px;
            height: 210px;
            border-radius: 12px;
            border: 4px solid #ccc;
            background: #f8f9fa;
            margin: 0 auto 30px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-weight: 500;
        }

        .btn-print {
            background: linear-gradient(45deg, #28a745, #218838);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border: none;
        }
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            background: linear-gradient(45deg, #218838, #28a745);
        }

        /* Print Specific Styles */
        @media print {
            body {
                background-color: #fff;
            }
            .info-container {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .info-container::before {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                opacity: 0.1 !important;
            }
            .btn-print {
                display: none;
            }
            h2, .detail-label, .dash-separator, .detail-value {
                color: #000 !important;
            }
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .info-container {
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            }
            .info-container h2 {
                font-size: 1.5rem;
                margin-bottom: 20px;
            }
            .info-photo, .photo-placeholder {
                max-width: 120px;
                height: 140px;
                margin-bottom: 20px;
                border: 3px solid #007bff;
            }
            .detail-row {
                flex-direction: column;
                align-items: center;
                gap: 5px;
                max-width: 100%;
                margin-left: 0;
                margin-right: 0;
                padding: 0 10px;
                font-size: 1rem;
            }
            .detail-label {
                width: 100%;
                text-align: center;
                margin-bottom: 0;
                font-size: 1.1rem;
                white-space: normal;
            }
            .dash-separator {
                display: none;
            }
            .detail-value {
                width: 100%;
                text-align: center;
                word-wrap: break-word;
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
                font-size: 1.1rem;
            }
            .single-line-truncate {
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
            }
        }
    </style>
</head>
<body>
    <div class="info-container">
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center mb-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <div class="text-center">
                <a href="index.php" class="btn btn-primary">GO BACK TO LOGIN PAGE</a>
            </div>
        <?php elseif ($card_data): ?>
            <h2>NPP NAKUR EMPLOYEE DETAILS</h2>
            
            <?php
            // फोटो display के लिए बेहतर तरीका
            $photo_path = $card_data['photo'] ?? '';
            if (!empty($photo_path)):
            ?>
                <img src="<?php echo htmlspecialchars($photo_path); ?>" 
                     alt="Employee Photo" 
                     class="info-photo"
                     onerror="this.style.display='none'; document.getElementById('photo-placeholder').style.display='flex';">
            <?php endif; ?>
            
            <div id="photo-placeholder" class="photo-placeholder" 
                 style="<?php echo !empty($photo_path) ? 'display: none;' : ''; ?>">
                PHOTO NOT AVAILABLE
            </div>

            <div class="detail-row">
                <span class="detail-label">ID NO.:</span>
                <span class="dash-separator">&nbsp;------------------&nbsp;</span>
                <span class="detail-value single-line-truncate"><?php echo strtoupper(htmlspecialchars($card_data['id_no'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">NAME:</span>
                <span class="dash-separator">&nbsp;------------------&nbsp;</span>
                <span class="detail-value"><?php echo strtoupper(htmlspecialchars($card_data['name'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">FATHER'S NAME:</span>
                <span class="dash-separator">&nbsp;------------------&nbsp;</span>
                <span class="detail-value"><?php echo strtoupper(htmlspecialchars($card_data['father_name'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">DESIGNATION:</span>
                <span class="dash-separator">&nbsp;------------------&nbsp;</span>
                <span class="detail-value"><?php echo strtoupper(htmlspecialchars($card_data['designation'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">MOBILE NO.:</span>
                <span class="dash-separator">&nbsp;------------------&nbsp;</span>
                <span class="detail-value single-line-truncate"><?php echo strtoupper(htmlspecialchars($card_data['mobile_no'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">BLOOD GROUP:</span>
                <span class="dash-separator">&nbsp;------------------&nbsp;</span>
                <span class="detail-value single-line-truncate"><?php echo strtoupper(htmlspecialchars($card_data['blood_group'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">ADDRESS:</span>
                <span class="dash-separator">&nbsp;------------------&nbsp;</span>
                <span class="detail-value"><?php echo strtoupper(htmlspecialchars($card_data['address'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">UAN NO.:</span>
                <span class="dash-separator">&nbsp;------------------&nbsp;</span>
                <span class="detail-value single-line-truncate"><?php echo strtoupper(htmlspecialchars($card_data['uan_no'] ?? 'N/A')); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">ESIC NO.:</span>
                <span class="dash-separator">&nbsp;------------------&nbsp;</span>
                <span class="detail-value single-line-truncate"><?php echo strtoupper(htmlspecialchars($card_data['esic_no'] ?? 'N/A')); ?></span>
            </div>

            <div class="text-center mt-5">
                <button onclick="window.print()" class="btn btn-print">PRINT</button>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>