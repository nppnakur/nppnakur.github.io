// ✅ pdf.php – placeholder for PDF download (using mPDF or TCPDF in real setup)
<?php
echo "PDF रसीद जनरेशन coming soon. आप mPDF / TCPDF जोड़ सकते हैं.";
?>


// ✅ jal.php – searchable + exportable water bill table
<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}
include "config.php";
?>
