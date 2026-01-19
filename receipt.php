<?php
// 📄 pdf.php — Generate Payment Receipt
session_start();
include 'config.php';
require_once('fpdf/fpdf.php'); // ✅ Make sure FPDF is available

if (!isset($_GET['con_no']) || !isset($_GET['timestamp'])) {
    die('इनपुट अधूरा है');
}

$con_no = $_GET['con_no'];
$timestamp = $_GET['timestamp'];

// 🔍 पेमेंट जानकारी निकालें
$stmt = $conn->prepare("SELECT * FROM payments WHERE con_no = ? AND paid_on = ?");
$stmt->execute([$con_no, $timestamp]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    die('पेमेंट रिकॉर्ड नहीं मिला');
}

// 🔍 बिल से ग्राहक नाम
$stmt2 = $conn->prepare("SELECT owner_name FROM bills WHERE con_no = ?");
$stmt2->execute([$con_no]);
$owner = $stmt2->fetchColumn();

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'नगर पालिका परिषद नकुड़', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'जल बिल भुगतान रसीद', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(50, 8, 'कनेक्शन संख्या:', 0, 0);
$pdf->Cell(0, 8, $con_no, 0, 1);

$pdf->Cell(50, 8, 'ग्राहक नाम:', 0, 0);
$pdf->Cell(0, 8, $owner, 0, 1);

$pdf->Cell(50, 8, 'भुगतान दिनांक:', 0, 0);
$pdf->Cell(0, 8, date('d-m-Y H:i:s', strtotime($payment['paid_on'])), 0, 1);

$pdf->Cell(50, 8, 'राशि (₹):', 0, 0);
$pdf->Cell(0, 8, number_format($payment['amount'], 2), 0, 1);

$pdf->Cell(50, 8, 'प्रकार:', 0, 0);
$pdf->Cell(0, 8, $payment['payment_type'] == 'current' ? 'वर्तमान' : 'बकाया', 0, 1);

$pdf->Cell(50, 8, 'विधि:', 0, 0);
$pdf->Cell(0, 8, $payment['payment_method'], 0, 1);

$pdf->Cell(50, 8, 'जमा करने वाला:', 0, 0);
$pdf->Cell(0, 8, $payment['username'], 0, 1);

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 8, 'यह एक ऑटो-जनरेटेड रसीद है।', 0, 1, 'C');

$pdf->Output('I', 'receipt_'.$con_no.'.pdf');