<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];

        $handle = fopen($fileTmpPath, 'r');
        if ($handle !== false) {
            // CSV का पहला हेडर स्किप करें
            fgetcsv($handle);

            $inserted = 0;
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                // जरूरी कॉलम्स को एक्सट्रैक्ट करें
                $con_no = $data[0] ?? '';
                $word_no = $data[1] ?? '';
                $owner_name = $data[2] ?? '';
                $mobile = $data[3] ?? '';
                $current = $data[4] ?? 0;
                $arrear = $data[5] ?? 0;
                $remain = $data[6] ?? 0;

                // PDO से DB में डालें
                $stmt = $pdo->prepare("INSERT INTO bills (con_no, word_no, owner_name, mobile, current_amount_2025_26, arrear_balance, remaining_balance)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$con_no, $word_no, $owner_name, $mobile, $current, $arrear, $remain]);

                $inserted++;
            }

            fclose($handle);
            echo "<h3>✅ $inserted रिकॉर्ड सफलतापूर्वक डाले गए!</h3>";
        } else {
            echo "❌ CSV फ़ाइल को पढ़ने में समस्या आई।";
        }
    } else {
        echo "❌ कोई वैध CSV फ़ाइल अपलोड नहीं की गई।";
    }
}
?>

<h2>📥 CSV फ़ाइल अपलोड करें</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="csv_file" accept=".csv" required>
    <button type="submit">अपलोड करें</button>
</form>
