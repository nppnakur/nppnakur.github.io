<?php
session_start();

// Development में errors दिखाएं ताकि 500 एरर को समझा जा सके
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Security: Checks if the user is logged in.
if (!isset($_SESSION['username'])) { 
    // Testing के बाद इसे अन-कमेंट करें
    // header("Location: ../index.php"); 
    // exit(); 
}

// ✅ पाथ फिक्स: आपके सर्वर एरर के अनुसार, property_list.php और config.php दोनों htdocs/ में हैं।
// इसलिए पाथ को केवल "config.php" होना चाहिए।
$pdo = require_once "config.php"; 

// अब $pdo ऑब्जेक्ट config.php से सीधे उपलब्ध है।

$database_error = null; // ग्लोबल एरर मैसेज को इनिशियलाइज़ करें

// --- Custom Function for Indian Currency Format ---
/**
 * Formats a number into Indian currency style (Lakhs, Crores) with two decimal places.
 * E.g., 1234567.89 becomes 12,34,567.89
 *
 * @param float|int $number The number to format.
 * @return string The formatted currency string.
 */
function format_indian_currency($number) {
    if (is_null($number) || $number === '') {
        $number = 0;
    }
    $number = (float) $number;
    $number = round($number, 2); // Round to 2 decimal places

    // Separate whole number and decimal part
    $parts = explode('.', (string) $number);
    $whole = $parts[0];
    $decimal = isset($parts[1]) ? '.' . str_pad(substr($parts[1], 0, 2), 2, '0', STR_PAD_RIGHT) : '.00';

    // Handle negative sign
    $is_negative = false;
    if (strpos($whole, '-') === 0) {
        $is_negative = true;
        $whole = substr($whole, 1);
    }
    
    $len = strlen($whole);
    $formatted_whole = '';

    if ($len <= 3) {
        $formatted_whole = $whole;
    } else {
        // Logic: Extract last three digits (thousands)
        $last_three = substr($whole, -3); // e.g., '566'
        $remaining = substr($whole, 0, -3); // e.g., '11648'

        // Logic: Format remaining part by pairs (Lakhs, Crores, etc.)
        $formatted_remaining = '';
        
        // Reverse the remaining part to apply 2-digit grouping from the right
        $temp_remaining = strrev($remaining); 
        
        // Loop and extract pairs (e.g., '84', '61', '1')
        for ($i = 0; $i < strlen($temp_remaining); $i += 2) {
            if (!empty($formatted_remaining)) {
                $formatted_remaining .= ',';
            }
            $formatted_remaining .= substr($temp_remaining, $i, 2);
        }
        
        // Reverse the result back to normal order (e.g., '1,16,48')
        $formatted_remaining = strrev($formatted_remaining); 
        
        // Combine the formatted parts
        $formatted_whole = $formatted_remaining . ',' . $last_three;
    }

    // Add negative sign back if necessary
    if ($is_negative) {
        $formatted_whole = '-' . $formatted_whole;
    }

    return $formatted_whole . $decimal;
}
// --- END Custom Function for Indian Currency Format ---


// --- Define Database Column Names ---
$db_table = 'properties';
// Note: Backticks (` `) आरक्षित शब्दों या स्पेस वाले नामों के लिए PHP में आवश्यक हैं।
$db_col_sr_no = 'Sr_No';
$db_col_ward_name = '`Ward Name`';
// Mohalla Name वेरिएबल जोड़ा गया
$db_col_mohalla_name = '`Mohalla Name`'; 
$db_col_property_id = '`Property ID`';
$db_col_house_no = '`House No`';
$db_col_property_type = '`Property Type`';
$db_col_owner_name = '`Owner\'s Name`';

// FIX: Changed column name to '`Mobile No`' (with backticks due to space) 
$db_col_mobile = '`Mobile No`'; 
$db_col_current_arv = '`Current ARV`';
$db_col_current_house_tax = '`Current House Tax`';
$db_col_current_water_tax = '`Current Water Tax`';
$db_col_arrear_house_tax = '`Arrear House Tax`';
$db_col_arrear_water_tax = '`Arrear Water Tax`';

// Function to get property data from the database
function getPropertyData($pdo, $search_query = '') {
    global $database_error, $db_table;
    // Mohalla Name के लिए कॉलम वेरिएबल भी जोड़ें
    global $db_col_property_id, $db_col_owner_name, $db_col_ward_name, $db_col_mobile, $db_col_house_no, $db_col_mohalla_name;
    
    // अगर $pdo ऑब्जेक्ट सेट नहीं है (मतलब config.php विफल हुआ)
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $database_error = "Database Connection Missing: \$pdo ऑब्जेक्ट उपलब्ध नहीं है। कृपया config.php फ़ाइल जाँचें।";
        return [];
    }
    
    try {
        // SELECT * सभी कॉलमों को खींचता है
        $sql = "SELECT 
                    *,
                    (`Current House Tax` + `Arrear House Tax` + `Current Water Tax` + `Arrear Water Tax`) AS `Total Remaining Balance`
                FROM {$db_table}";
        $params = [];
        
        if (!empty($search_query)) {
            // $db_col_mobile अब '`Mobile No`' है, और यह खोज क्वेरी में सही ढंग से उपयोग होगा
            $sql .= " WHERE {$db_col_property_id} LIKE ? OR {$db_col_owner_name} LIKE ? OR {$db_col_ward_name} LIKE ? OR {$db_col_mobile} LIKE ? OR {$db_col_house_no} LIKE ? OR {$db_col_mohalla_name} LIKE ?";
            $search_term = "%$search_query%";
            $params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]; 
        }
        
        $sql .= " ORDER BY Sr_No ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        // यदि क्वेरी विफल होती है तो वास्तविक एरर दिखाएँ
        $database_error = "Database Query Error: '{$db_table}' टेबल या कॉलम नाम गलत हो सकता है। विस्तृत एरर: " . $e->getMessage();
        return [];
    }
}

// Function to calculate totals
function calculateTotals($data) {
    $totals = [
        'current_arv' => 0,
        'current_house_tax' => 0,
        'arrear_house_tax' => 0,
        'current_water_tax' => 0,
        'arrear_water_tax' => 0,
        'remaining_balance' => 0
    ];
    
    foreach($data as $row) {
        // Column names must match the names returned by the SQL query
        $totals['current_arv'] += $row['Current ARV'] ?? 0;
        $totals['current_house_tax'] += $row['Current House Tax'] ?? 0;
        $totals['arrear_house_tax'] += $row['Arrear House Tax'] ?? 0;
        $totals['current_water_tax'] += $row['Current Water Tax'] ?? 0;
        $totals['arrear_water_tax'] += $row['Arrear Water Tax'] ?? 0;
        
        // Use the calculated field name if available, otherwise calculate it
        if (isset($row['Total Remaining Balance'])) {
             $totals['remaining_balance'] += $row['Total Remaining Balance'];
        } else {
             $totals['remaining_balance'] += (
                ($row['Current House Tax'] ?? 0) + 
                ($row['Arrear House Tax'] ?? 0) + 
                ($row['Current Water Tax'] ?? 0) + 
                ($row['Arrear Water Tax'] ?? 0)
            );
        }
    }
    
    return $totals;
}

// Get data based on search
$search_query = $_GET['query'] ?? '';
// $pdo variable config.php के माध्यम से require_once द्वारा सेट किया गया है
$property_data = getPropertyData($pdo ?? null, $search_query); 
$totals = calculateTotals($property_data);


// ----------------------------------------------------------------------
// ✅ FIX: Modified Excel export logic for better formatting and centering.
// ----------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="property_details_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Start HTML/XML structure for better Excel formatting and centering
    $output = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    $output .= '<head>';
    $output .= '<meta charset="UTF-8">';
    // CSS for table formatting and centering
    $output .= '<style>';
    $output .= 'table { width: 100%; border-collapse: collapse; }';
    // Center all text in TH/TD by default
    $output .= 'th, td { border: 1px solid #000; padding: 5px; text-align: center; vertical-align: middle; }';
    // Header style
    $output .= 'th { background: #1e40af; color: white; font-weight: bold; }';
    // Owner\'s Name (7th column) should be left-aligned
    $output .= 'td:nth-child(7) { text-align: left; }'; 
    // Currency/Number columns (9th to 14th) should be right-aligned
    $output .= 'td:nth-child(n+9):nth-child(-n+14) { text-align: right; }';
    // Total Row Style
    $output .= '.total-row td { background: #f59e0b; color: white; font-weight: bold; border-color: #d97706; }';
    // Total label column (Colspan 8) should be right-aligned
    $output .= '.total-row td:first-child { text-align: right !important; }';
    $output .= '</style>';
    $output .= '</head>';
    $output .= '<body>';

    // Create Excel content 
    // Colspan 14 (14 data columns)
    $output .= '<table border="1">';
    $output .= '<tr><td colspan="14" style="text-align:center; background:#1e40af; color:white; font-weight:bold; padding:15px;">संपत्ति कर विवरण - विस्तृत रिपोर्ट</td></tr>';
    $output .= '<tr>';
    $output .= '<th style="background:#1e40af; color:white;">#</th>';
    $output .= '<th style="background:#1e40af; color:white;">संपत्ति ID</th>';
    $output .= '<th style="background:#1e40af; color:white;">वार्ड नाम</th>';
    $output .= '<th style="background:#1e40af; color:white;">मोहल्ला नाम</th>'; 
    $output .= '<th style="background:#1e40af; color:white;">हाउस नं.</th>';
    $output .= '<th style="background:#1e40af; color:white;">प्रकार</th>';
    $output .= '<th style="background:#1e40af; color:white;">मालिक का नाम</th>';
    $output .= '<th style="background:#1e40af; color:white;">मोबाइल</th>';
    $output .= '<th style="background:#1e40af; color:white;">वर्तमान ARV</th>';
    $output .= '<th style="background:#1e40af; color:white;">वर्तमान हाउस कर</th>';
    $output .= '<th style="background:#1e40af; color:white;">बकाया हाउस कर</th>';
    $output .= '<th style="background:#1e40af; color:white;">वर्तमान जल कर</th>';
    $output .= '<th style="background:#1e40af; color:white;">बकाया जल कर</th>';
    $output .= '<th style="background:#1e40af; color:white;">कुल शेष</th>';
    $output .= '</tr>';
    
    $i = 1;
    foreach($property_data as $row) {
        // Use the calculated field
        $remaining_balance = $row['Total Remaining Balance'] ?? 0;
        
        $output .= '<tr>';
        // Data cells: Rely on CSS for alignment
        $output .= '<td>' . $i++ . '</td>';
        $output .= '<td>' . htmlspecialchars($row['Property ID'] ?? '') . '</td>';
        $output .= '<td>' . htmlspecialchars($row['Ward Name'] ?? '') . '</td>';
        $output .= '<td>' . htmlspecialchars($row['Mohalla Name'] ?? '') . '</td>'; 
        $output .= '<td>' . htmlspecialchars($row['House No'] ?? '') . '</td>';
        $output .= '<td>' . htmlspecialchars($row['Property Type'] ?? '') . '</td>';
        $output .= '<td>' . htmlspecialchars($row['Owner\'s Name'] ?? '') . '</td>';
        $output .= '<td>' . htmlspecialchars($row['Mobile No'] ?? '') . '</td>'; 
        // Currency formatting for numbers (Rely on CSS for right alignment)
        $output .= '<td>₹' . format_indian_currency($row['Current ARV'] ?? 0) . '</td>';
        $output .= '<td>₹' . format_indian_currency($row['Current House Tax'] ?? 0) . '</td>';
        $output .= '<td>₹' . format_indian_currency($row['Arrear House Tax'] ?? 0) . '</td>';
        $output .= '<td>₹' . format_indian_currency($row['Current Water Tax'] ?? 0) . '</td>';
        $output .= '<td>₹' . format_indian_currency($row['Arrear Water Tax'] ?? 0) . '</td>';
        $output .= '<td>₹' . format_indian_currency($remaining_balance) . '</td>';
        $output .= '</tr>';
    }
    
    // Totals row - Use total-row class and set alignment for robustness
    $output .= '<tr class="total-row">';
    // Colspan 8 (non-currency columns + label)
    $output .= '<td colspan="8" style="text-align:right;">कुल योग:</td>'; 
    $output .= '<td style="text-align:right;">₹' . format_indian_currency($totals['current_arv']) . '</td>';
    $output .= '<td style="text-align:right;">₹' . format_indian_currency($totals['current_house_tax']) . '</td>';
    $output .= '<td style="text-align:right;">₹' . format_indian_currency($totals['arrear_house_tax']) . '</td>';
    $output .= '<td style="text-align:right;">₹' . format_indian_currency($totals['current_water_tax']) . '</td>';
    $output .= '<td style="text-align:right;">₹' . format_indian_currency($totals['arrear_water_tax']) . '</td>';
    $output .= '<td style="text-align:right;">₹' . format_indian_currency($totals['remaining_balance']) . '</td>';
    // ✅ REMOVED: Extra empty <td> tag that caused structural issue
    $output .= '</tr>';
    
    $output .= '</table>';
    $output .= '</body>';
    $output .= '</html>';
    echo $output;
    exit;
}
// ----------------------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>संपत्ति प्रबंधन</title>
<link rel="icon" type="image/png" href="../img1.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* (All your existing CSS code here) */

:root {
  --primary: #1e40af;
  --secondary: #1e3a8a;
  --light-color: #dbeafe;
  --dark-color: #1e3a8a;
  --gradient-primary: linear-gradient(135deg, #1e40af, #1e3a8a);
  --gradient-secondary: linear-gradient(135deg, #3b82f6, #1e40af);
  --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  --shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%);
  min-height: 100vh;
  color: #1f2937;
  overflow-x: hidden;
}

/* ---------- Top Bar Styles ---------- */
.topbar {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: var(--gradient-primary);
  box-shadow: var(--shadow);
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 24px;
  height: 70px;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 15px;
}

.logo {
  height: 45px;
  width: auto;
  border-radius: 6px;
}

.org-name {
  color: white;
  font-size: 20px;
  font-weight: bold;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
}

.topbar-right {
    /* संपत्ति जोड़ें बटन हटाया गया */
    display: flex;
    gap: 15px;
}

.topbar a {
  color: white;
  font-size: 16px;
  text-decoration: none;
  transition: all 0.3s ease;
  padding: 8px 16px;
  border-radius: 8px;
  background: rgba(255,255,255,0.15);
  display: flex;
  align-items: center;
  gap: 8px;
}

.topbar a:hover {
  background: rgba(255,255,255,0.25);
  transform: translateY(-1px);
}

/* ---------- Main Container ---------- */
.main-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  max-width: 1500px;
  margin: 0 auto;
  padding: 20px 15px;
}

/* ---------- Heading Styles ---------- */
.main-heading {
  text-align: center;
  color: var(--dark-color);
  font-size: 32px;
  margin: 10px 0 20px;
  font-weight: 700;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
  width: 100%;
}

/* ---------- Search Box Styles ---------- */
.search-container {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
  width: 100%;
  max-width: 800px;
}

.search-box {
  position: relative;
  width: 400px;
  max-width: 100%;
}

.search-box input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #bfdbfe;
  border-radius: 10px;
  font-size: 14px;
  background: white;
  box-shadow: var(--shadow);
  transition: all 0.3s ease;
}

.search-box input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.2);
}

.search-btn, .export-btn, .add-btn {
  padding: 12px 20px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: var(--shadow);
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
}

.search-btn {
  background: var(--gradient-primary);
  color: white;
}

.export-btn {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}

.add-btn {
  background: linear-gradient(135deg, #059669, #047857);
  color: white;
}

.search-btn:hover, .export-btn:hover, .add-btn:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-hover);
}

/* ---------- Stats Cards ---------- */
.stats-container {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin: 20px auto;
  flex-wrap: wrap;
  width: 100%;
  max-width: 1400px;
}

.stat-card {
  flex: 1;
  min-width: 180px;
  max-width: 250px;
  padding: 15px;
  border-radius: 12px;
  text-align: center;
  box-shadow: var(--shadow);
  transition: all 0.3s ease;
  background: white;
  border: 1px solid #e5e7eb;
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--gradient-primary);
}

.stat-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-hover);
}

.stat-card.total-tax {
  background: linear-gradient(135deg, #059669, #047857);
  color: white;
}

.stat-card.house-tax {
  background: var(--gradient-primary);
  color: white;
}

.stat-card.water-tax {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
}

.stat-card.arrear {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}

.stat-card.remaining {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}

.stat-card h3 {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 600;
  opacity: 0.9;
}

.stat-card .number {
  font-size: 22px;
  font-weight: 700;
  margin: 8px 0;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
}

.stat-card .icon {
  font-size: 24px;
  margin-bottom: 8px;
  opacity: 0.9;
}

/* ---------- Main Table Container ---------- */
.main-table-container {
  width: 100%;
  max-width: 1500px;
  margin: 0 auto;
  background: white;
  border-radius: 12px;
  box-shadow: var(--shadow-hover);
  overflow: hidden;
  border: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  height: 60vh;
  min-height: 400px;
}

/* ---------- Table Header Container ---------- */
.table-header-container {
  background: white;
  border-bottom: 2px solid #e5e7eb;
  overflow: hidden;
  flex-shrink: 0;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  margin: 0;
  table-layout: fixed;
}

.data-table thead {
  position: sticky;
  top: 0;
  z-index: 100;
}

.data-table th {
  background: var(--gradient-primary);
  color: white;
  padding: 12px 6px;
  font-size: 12px;
  font-weight: 600;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  border-right: 1px solid rgba(255,255,255,0.2);
  position: sticky;
  top: 0;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
}

.data-table th:last-child {
  border-right: none;
}

/* ---------- Table Body Container ---------- */
.table-body-container {
  flex: 1;
  overflow-y: auto;
  overflow-x: auto;
}

.data-table tbody tr {
  transition: all 0.2s ease;
}

.data-table tbody tr:nth-child(even) {
  background: #f8fafc;
}

.data-table tbody tr:hover {
  background: var(--light-color);
}

.data-table td {
  padding: 10px 6px;
  border-bottom: 1px solid #e5e7eb;
  font-size: 12px;
  text-align: center;
  vertical-align: middle;
  transition: all 0.2s ease;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* * ✅ Column specific styles: Widths optimized for visibility on a standard screen 
* Total Columns: 15
* Total Width: ~100% (allows for margin/padding on table)
*/
.data-table {
    /* टेबल को 100% चौड़ा होने दें, लेकिन कम से कम 1200px (छोटे लैपटॉप के लिए) पर स्क्रॉल करें */
    min-width: 1200px; 
}
.id-col { width: 3%; font-weight: 600; color: var(--primary); font-size: 10px; }
.property-id-col { width: 8%; font-weight: 600; font-size: 10px; }
.ward-name-col { width: 7%; font-size: 10px; }
.mohalla-name-col { width: 7%; font-size: 10px; } /* Mohalla Name width added */
.house-no-col { width: 6%; font-size: 10px; }
.prop-type-col { width: 7%; font-size: 10px; }
.owner { width: 13%; text-align: left; padding-left: 8px !important; font-size: 10px; }
.mobile-col { width: 7%; font-family: monospace; font-size: 10px; }
.arv-col { width: 7%; font-weight: 600; color: #059669; font-size: 10px; }
.tax-col { width: 7%; font-weight: 600; font-size: 10px; } /* Current House Tax */
.arrear-col { width: 7%; font-weight: 600; color: #d97706; font-size: 10px; } /* Arrear House Tax */
.water-tax-col { width: 7%; font-weight: 600; font-size: 10px; } /* Current Water Tax */
.water-arrear-col { width: 7%; font-weight: 600; color: #d97706; font-size: 10px; } /* Arrear Water Tax */
.remaining-col { width: 7%; font-weight: 700; color: #dc2626; font-size: 10px; }
.act { width: 5%; }

/* Action buttons */
.action-buttons {
  display: flex;
  justify-content: center;
  gap: 4px;
}

.action-btn {
  width: 26px;
  height: 26px;
  border-radius: 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 11px;
  box-shadow: var(--shadow);
}

.edit-btn {
  background: var(--gradient-primary);
  color: white;
}

.delete-btn {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}

.print-btn {
  background: linear-gradient(135deg, #059669, #047857);
  color: white;
}

.action-btn:hover {
  transform: scale(1.1);
  box-shadow: var(--shadow-hover);
}

/* Total row */
.total-row {
  background: var(--gradient-primary) !important;
  color: white;
  font-weight: bold;
  position: sticky;
  bottom: 0;
  z-index: 99;
}

.total-row td {
  border-bottom: none;
  font-size: 12px;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
}

/* ---------- Message Styles ---------- */
.message-box {
  background: #dbeafe;
  color: #1e40af;
  border: 1px solid #bfdbfe;
  padding: 12px 16px;
  margin: 15px auto;
  border-radius: 10px;
  text-align: center;
  max-width: 600px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  box-shadow: var(--shadow);
  font-weight: 500;
  font-size: 14px;
}

.message-box.error {
  background: #fecaca;
  color: #991b1b;
  border-color: #fca5a5;
}

.message-box.success {
  background: #d1fae5;
  color: #065f46;
  border-color: #a7f3d0;
}

/* Custom scrollbar */
.table-body-container::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

.table-body-container::-webkit-scrollbar-track {
  background: #f1f5f9;
}

.table-body-container::-webkit-scrollbar-thumb {
  background: var(--gradient-primary);
  border-radius: 3px;
}

.table-body-container::-webkit-scrollbar-thumb:hover {
  background: var(--secondary);
}

/* Loading animation */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.stat-card, .message-box, .main-table-container {
  animation: fadeIn 0.4s ease-out;
}

/* No data message */
.no-data {
  text-align: center;
  padding: 30px;
  color: #6c757d;
  font-style: italic;
  font-size: 14px;
}

.no-data i {
  font-size: 28px;
  margin-bottom: 10px;
  display: block;
  opacity: 0.5;
}

/* Responsive styles */
@media (max-width: 1200px) {
  .stats-container {
    max-width: 100%;
  }
  
  .stat-card {
    min-width: 160px;
  }
}

@media (max-width: 768px) {
  .topbar {
    padding: 10px 15px;
    height: 60px;
  }
  
  .logo {
    height: 35px;
  }
  
  .org-name {
    font-size: 16px;
  }
  
  .topbar a {
    font-size: 14px;
    padding: 6px 12px;
  }
  
  .main-heading {
    font-size: 24px;
  }
  
  .search-container {
    flex-direction: column;
    align-items: center;
  }
  
  .search-box {
    width: 100%;
  }
  
  .stats-container {
    gap: 10px;
  }
  
  .stat-card {
    min-width: 140px;
    padding: 10px;
  }
  
  .stat-card .number {
    font-size: 18px;
  }
  
  .main-table-container {
    height: 50vh;
  }
  
  /* छोटी स्क्रीन पर भी न्यूनतम चौड़ाई 1200px बनाए रखें ताकि स्क्रॉलिंग काम करे */
  .data-table {
    min-width: 1200px;
  }
}

/* Print styles */
@media print {
  .topbar, .search-container, .stats-container, .action-buttons {
    display: none !important;
  }
  
  .main-table-container {
    box-shadow: none;
    border: 1px solid #000;
  }
  
  body {
    background: white;
  }
}

</style>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Delete record with modal confirmation
function showDeleteModal(propertyId, ownerName) {
    document.getElementById('connectionDetails').innerHTML = 
        `<strong>संपत्ति ID:</strong> ${propertyId}<br>
         <strong>मालिक का नाम:</strong> ${ownerName}`;
    
    document.getElementById('deleteConfirmBtn').setAttribute('data-property-id', propertyId);
    document.getElementById('password').value = '';
    document.getElementById('deleteConfirmBtn').classList.add('disabled');
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

// Enable delete button only when password is entered
function validateDelete() {
    const password = document.getElementById('password').value;
    const deleteBtn = document.getElementById('deleteConfirmBtn');
    
    if (password.length > 0) {
        deleteBtn.classList.remove('disabled');
    } else {
        deleteBtn.classList.add('disabled');
    }
}

// Toggle password visibility
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.querySelector('.toggle-password i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Confirm delete function
function confirmDelete() {
    const password = document.getElementById('password').value;
    const deleteBtn = document.getElementById('deleteConfirmBtn');
    const propertyId = deleteBtn.getAttribute('data-property-id');
    
    // NOTE: This is client-side validation. Server-side must be done in delete_property.php
    if (password !== 'admin123') { // Example password check, ensure this matches your logic
        alert('❌ गलत पासवर्ड');
        return;
    }
    
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> डिलीट हो रहा है...';
    deleteBtn.disabled = true;
    
    // Send delete request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'delete_property.php';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'property_id';
    input.value = propertyId;
    
    const inputPassword = document.createElement('input');
    inputPassword.type = 'hidden';
    inputPassword.name = 'password';
    inputPassword.value = password; // Pass password for server-side check
    
    form.appendChild(input);
    form.appendChild(inputPassword);
    document.body.appendChild(form);
    form.submit();
}

// Print bill
function printBill(propertyId) {
    const url = `print_bill.php?id=${encodeURIComponent(propertyId)}`;
    window.open(url, '_blank', 'width=800,height=600');
}

// Auto-hide messages after a few seconds
window.onload = function() {
    const messageBox = document.querySelector('.message-box');
    if (messageBox) {
        setTimeout(() => {
            messageBox.style.opacity = '0';
            messageBox.style.transition = 'opacity 0.5s ease-out';
            setTimeout(() => {
                messageBox.remove();
            }, 500);
        }, 5000);
    }
    
    // Adjust table height based on window size
    function adjustTableHeight() {
        const tableContainer = document.querySelector('.main-table-container');
        if (tableContainer) {
            const windowHeight = window.innerHeight;
            const topbarHeight = document.querySelector('.topbar').offsetHeight;
            const headingHeight = document.querySelector('.main-heading').offsetHeight;
            const searchHeight = document.querySelector('.search-container').offsetHeight;
            const statsHeight = document.querySelector('.stats-container').offsetHeight;
            const margins = 60;
            
            const calculatedHeight = windowHeight - topbarHeight - headingHeight - searchHeight - statsHeight - margins;
            const minHeight = 350;
            
            tableContainer.style.height = Math.max(calculatedHeight, minHeight) + 'px';
        }
    }
    
    // Initial adjustment
    setTimeout(adjustTableHeight, 100);
    window.addEventListener('resize', adjustTableHeight);
};

// Handle search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.search-box input');
    const searchButton = document.querySelector('.search-btn');
    
    // Submit form when search button is clicked
    searchButton.addEventListener('click', function() {
        const searchValue = searchInput.value;
        // Use window.location.href directly to prevent form duplication
        window.location.href = `?query=${encodeURIComponent(searchValue)}`; 
    });
    
    // Submit form when Enter is pressed in search box
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // Prevent default form submit (if wrapped in a form)
            const searchValue = this.value;
            window.location.href = `?query=${encodeURIComponent(searchValue)}`;
        }
    });
    
    // Show current search query in input
    const urlParams = new URLSearchParams(window.location.search);
    const searchQuery = urlParams.get('query');
    if (searchQuery) {
        searchInput.value = searchQuery;
    }
});
</script>
</head>
<body>
<div class="topbar">
    <div class="topbar-left">
        <img src="../img1.png" alt="संपत्ति प्रबंधन लोगो" class="logo">
        <span class="org-name">संपत्ति प्रबंधन</span>
    </div>
    <div class="topbar-right">
        <a href="../house_tax_dashboard.php"><i class="fas fa-home"></i> होम</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> लॉगआउट</a>
    </div>
</div>

<div class="main-container">
    <h1 class="main-heading">🏠 संपत्ति कर विवरण डैशबोर्ड</h1>

    <?php
    // Success and error message display
    if (isset($_GET['success'])) {
        echo '<div class="message-box success">';
        echo '<i class="fas fa-check-circle"></i>' . htmlspecialchars($_GET['success']);
        echo '</div>';
    }

    if (isset($_GET['error'])) {
        echo '<div class="message-box error">';
        echo '<i class="fas fa-exclamation-circle"></i>' . htmlspecialchars($_GET['error']);
        echo '</div>';
    }
    
    // Show database connection or query error (from new logic)
    if ($database_error) {
        echo '<div class="message-box error">';
        echo '<i class="fas fa-exclamation-circle"></i> डेटाबेस त्रुटि: ' . htmlspecialchars($database_error);
        echo '</div>';
    }
    ?>

    <div class="search-container">
        <div class="search-box">
             <input type="text" placeholder="🔍 संपत्ति ID, नाम, मोबाइल, वार्ड या मोहल्ला खोजें..." 
                   value="<?= htmlspecialchars($search_query) ?>">
        </div>
        <button class="search-btn" type="button">
            <i class="fas fa-search"></i> खोजें
        </button>
        <a href="?query=<?= urlencode($search_query) ?>&export=excel" class="export-btn">
            <i class="fas fa-file-excel"></i> एक्सेल एक्सपोर्ट
        </a>
        </div>

    <div class="stats-container">
        <div class="stat-card total-tax">
            <div class="icon">🏠</div>
            <h3>कुल संपत्तियां</h3>
            <div class="number"><?= count($property_data) ?></div>
            <p>पंजीकृत संपत्तियों की संख्या</p>
        </div>
        <div class="stat-card house-tax">
            <div class="icon">💰</div>
            <h3>कुल हाउस कर (वर्तमान)</h3>
            <div class="number">₹<?= format_indian_currency($totals['current_house_tax']) ?></div>
            <p>हाउस कर 2025-26 वर्ष</p>
        </div>
        <div class="stat-card water-tax">
            <div class="icon">💧</div>
            <h3>कुल जल कर (वर्तमान)</h3>
            <div class="number">₹<?= format_indian_currency($totals['current_water_tax']) ?></div>
            <p>जल कर 2025-26 वर्ष</p>
        </div>
        <div class="stat-card arrear">
            <div class="icon">📅</div>
            <h3>कुल बकाया (पिछला)</h3>
            <div class="number">₹<?= format_indian_currency($totals['arrear_house_tax'] + $totals['arrear_water_tax']) ?></div>
            <p>हाउस और जल कर का बकाया</p>
        </div>
        <div class="stat-card remaining">
            <div class="icon">🧾</div>
            <h3>कुल शेष राशि</h3>
            <div class="number">₹<?= format_indian_currency($totals['remaining_balance']) ?></div>
            <p>सभी करों का कुल बकाया</p>
        </div>
    </div>

    <div class="main-table-container">
        <div class="table-header-container">
            <table class="data-table"> 
                <thead>
                    <tr>
                        <th class="id-col">#</th>
                        <th class="property-id-col">🔑 संपत्ति ID</th>
                        <th class="ward-name-col">🏘️ वार्ड नाम</th>
                        <th class="mohalla-name-col">🏘️ मोहल्ला नाम</th> <th class="house-no-col">🚪 हाउस नं.</th>
                        <th class="prop-type-col">🏠 प्रकार</th>
                        <th class="owner">👤 मालिक का नाम</th>
                        <th class="mobile-col">📱 मोबाइल</th>
                        <th class="arv-col">📈 वर्तमान ARV</th>
                        <th class="tax-col">💰 वर्तमान हाउस कर</th>
                        <th class="arrear-col">📅 बकाया हाउस कर</th>
                        <th class="water-tax-col">💧 वर्तमान जल कर</th> 
                        <th class="water-arrear-col">🌊 बकाया जल कर</th> 
                        <th class="remaining-col">💸 कुल शेष</th>
                        <th class="act">⚙️</th>
                    </tr>
                </thead>
            </table>
        </div>

        <div class="table-body-container">
            <table class="data-table">
                <tbody>
                <?php
                if(empty($property_data)) {
                    // अगर $database_error सेट है तो कोई रिकॉर्ड नहीं दिखाना बेहतर है
                    // Colspan 15 (14 data columns + 1 action column)
                    $colspan = 15;
                    if (!$database_error) {
                        echo '<tr><td colspan="' . $colspan . '" class="no-data"><i class="fas fa-inbox"></i>कोई डेटा नहीं मिला</td></tr>';
                    } else {
                         echo '<tr><td colspan="' . $colspan . '" class="no-data"><i class="fas fa-database"></i>डेटाबेस से डेटा लोड नहीं हुआ। ऊपर की त्रुटि देखें।</td></tr>';
                    }
                } else {
                    $i = 1;
                    foreach($property_data as $row):
                        // Use the calculated field from the SQL query
                        $remaining_balance = $row['Total Remaining Balance'] ?? 0;
                ?>
                    <tr>
                        <td class="id-col"><?= $i ?></td>
                        <td class="property-id-col"><?= htmlspecialchars($row['Property ID'] ?? '') ?></td>
                        <td class="ward-name-col"><?= htmlspecialchars($row['Ward Name'] ?? '') ?></td>
                        <td class="mohalla-name-col"><?= htmlspecialchars($row['Mohalla Name'] ?? '') ?></td> <td class="house-no-col"><?= htmlspecialchars($row['House No'] ?? '') ?></td>
                        <td class="prop-type-col"><?= htmlspecialchars($row['Property Type'] ?? '') ?></td>
                        <td class="owner"><?= htmlspecialchars($row['Owner\'s Name'] ?? '') ?></td>
                        <td class="mobile-col"><?= htmlspecialchars($row['Mobile No'] ?? '') ?></td> 
                        <td class="arv-col">₹<?= format_indian_currency($row['Current ARV'] ?? 0) ?></td>
                        <td class="tax-col">₹<?= format_indian_currency($row['Current House Tax'] ?? 0) ?></td>
                        <td class="arrear-col">₹<?= format_indian_currency($row['Arrear House Tax'] ?? 0) ?></td>
                        <td class="water-tax-col">₹<?= format_indian_currency($row['Current Water Tax'] ?? 0) ?></td>
                        <td class="water-arrear-col">₹<?= format_indian_currency($row['Arrear Water Tax'] ?? 0) ?></td>
                        <td class="remaining-col">₹<?= format_indian_currency($remaining_balance) ?></td>
                        <td class="act">
                            <div class="action-buttons">
                                <a href="edit_property.php?id=<?= urlencode($row['Property ID'] ?? '') ?>" class="action-btn edit-btn" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" onclick="showDeleteModal('<?= urlencode($row['Property ID'] ?? '') ?>', '<?= htmlspecialchars($row['Owner\'s Name'] ?? '') ?>'); return false;" class="action-btn delete-btn" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a href="#" onclick="printBill('<?= urlencode($row['Property ID'] ?? '') ?>')" class="action-btn print-btn" title="Print Bill">
                                    <i class="fas fa-print"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php 
                    $i++;
                    endforeach; 
                ?>
                    <tr class="total-row">
                        <td colspan="8" style="text-align:right;">कुल योग:</td>
                        <td>₹<?= format_indian_currency($totals['current_arv']) ?></td>
                        <td>₹<?= format_indian_currency($totals['current_house_tax']) ?></td>
                        <td>₹<?= format_indian_currency($totals['arrear_house_tax']) ?></td>
                        <td>₹<?= format_indian_currency($totals['current_water_tax']) ?></td>
                        <td>₹<?= format_indian_currency($totals['arrear_water_tax']) ?></td>
                        <td>₹<?= format_indian_currency($totals['remaining_balance']) ?></td>
                        <td></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>संपत्ति डिलीट करें
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger text-center" role="alert" style="border-radius: 10px;">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>चेतावनी!</strong> यह कार्रवाई पूर्ववत नहीं की जा सकती है
                </div>
                
                <div class="border p-3 mb-3 rounded" style="background: #f8f9fa;">
                    <h6 class="text-center mb-3">निम्नलिखित संपत्ति को डिलीट करना चाहते हैं?</h6>
                    <div class="text-center" id="connectionDetails"></div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">पासवर्ड</label>
                    <div class="password-container">
                        <input type="password" class="form-control" id="password" 
                               placeholder="अपना पासवर्ड दर्ज करें" oninput="validateDelete()" style="padding-right: 40px;">
                        <span class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द करें</button>
                <button id="deleteConfirmBtn" class="btn btn-danger disabled" onclick="confirmDelete()">डिलीट करें</button>
            </div>
        </div>
    </div>
</div>

</body>
</html>