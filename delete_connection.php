<?php
session_start();

// सुरक्षा: जांचें कि उपयोगकर्ता लॉग इन है या नहीं
// डेवलपमेंट के दौरान आप इसे कमेंट कर सकते हैं
// if (!isset($_SESSION['username'])) {
//     header("Location: index.php");
//     exit();
// }

// config.php फ़ाइल की उपस्थिति जांचें
if (!file_exists('config.php')) {
    die("त्रुटि: कॉन्फ़िगरेशन फ़ाइल (config.php) नहीं मिली");
}

include "config.php"; // आपकी डेटाबेस कनेक्शन फ़ाइल

// डेटाबेस कॉलम के नाम यहाँ परिभाषित करें
$db_col_con_no = '`connection No`'; // यह आपके डेटाबेस के कॉलम नाम से मेल खाना चाहिए

// जांचें कि 'id' पैरामीटर URL में मौजूद है या नहीं
if (isset($_GET['id']) && trim($_GET['id']) !== '') {
    $connection_no_to_delete = trim($_GET['id']); // 'id' यहाँ कनेक्शन नंबर है

    try {
        // PDO कनेक्शन की जांच करें
        if (!$pdo) {
            throw new Exception("डेटाबेस कनेक्शन उपलब्ध नहीं है");
        }

        // SQL क्वेरी तैयार करें
        // यहाँ $conn को $pdo से बदला गया है
        $sql = "DELETE FROM bills WHERE " . $db_col_con_no . " = :con_no_to_delete";
        $stmt = $pdo->prepare($sql);
        
        // पैरामीटर बाइंड करें ताकि SQL इंजेक्शन से बचा जा सके
        $stmt->bindParam(':con_no_to_delete', $connection_no_to_delete, PDO::PARAM_STR);
        
        // क्वेरी चलाएं
        $stmt->execute();
        
        // जांचें कि कोई पंक्ति प्रभावित हुई है या नहीं
        if ($stmt->rowCount() > 0) {
            // सफलता: connection_detail.php पर वापस रीडायरेक्ट करें
            header("Location: connection_detail.php?success=" . urlencode("कनेक्शन सफलतापूर्वक हटाया गया।"));
            exit();
        } else {
            // कनेक्शन नंबर नहीं मिला
            header("Location: connection_detail.php?error=" . urlencode("यह कनेक्शन नंबर नहीं मिला: " . $connection_no_to_delete));
            exit();
        }

    } catch (PDOException $e) {
        // डेटाबेस त्रुटि को संभालें
        error_log("Delete Connection PDO Error: " . $e->getMessage());
        header("Location: connection_detail.php?error=" . urlencode("डेटाबेस त्रुटि: कृपया व्यवस्थापक से संपर्क करें"));
        exit();
    } catch (Exception $e) {
        // सामान्य त्रुटि को संभालें
        error_log("Delete Connection Error: " . $e->getMessage());
        header("Location: connection_detail.php?error=" . urlencode("त्रुटि: " . $e->getMessage()));
        exit();
    }
} else {
    // 'id' पैरामीटर प्रदान नहीं किया गया या खाली है
    header("Location: connection_detail.php?error=" . urlencode("हटाने के लिए कनेक्शन नंबर प्रदान नहीं किया गया।"));
    exit();
}
?>