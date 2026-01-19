<?php
session_start();
// Security: Checks if the user is logged in.
if (!isset($_SESSION['username'])) { 
    header("Location: index.php"); 
    exit(); 
}

// Includes the 'config.php' file which contains your database connection details.
include "config.php";

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

// --- Define Database Column Names Here ---
$db_col_con_no = '`connection No`';
$db_col_ward_no = 'ward_no';
$db_col_owner_name = '`Owner Name`';
$db_col_mobile = 'Mobile';
$db_col_current_amount = '`Current amount 2025-26`';
$db_col_arrear_balance = '`Arrear Balance`';
$db_col_remaining_balance = 'remaining_balance';

// Handle Excel export
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Set headers for Excel download with proper encoding
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment;filename="connection_details_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Add UTF-8 BOM for proper Hindi character display
    echo "\xEF\xBB\xBF";
    
    // Create Excel content with unique design
    $output = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    $output .= '<head>';
    $output .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    $output .= '<style>';
    $output .= 'body { font-family: "Segoe UI", "Arial Unicode MS", Arial, sans-serif; margin: 20px; }';
    $output .= 'table { border-collapse: collapse; width: 100%; margin: 20px 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }';
    $output .= 'td { border: 1px solid #e0e0e0; padding: 12px 8px; font-size: 12px; vertical-align: middle; }';
    $output .= 'th { background: linear-gradient(135deg, #10b981, #059669); color: white; font-weight: bold; padding: 15px 10px; border: 1px solid #0da271; text-align: center; font-size: 13px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3); }';
    $output .= '.excel-header { background: linear-gradient(135deg, #1e40af, #1e3a8a); color: white; font-size: 24px; font-weight: bold; padding: 25px; text-align: center; border: 2px solid #1e3a8a; margin-bottom: 0; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }';
    $output .= '.excel-subheader { background: #dbeafe; color: #1e40af; padding: 15px; text-align: center; border: 1px solid #bfdbfe; font-weight: bold; font-size: 14px; }';
    $output .= '.excel-footer { background: #f8fafc; color: #64748b; padding: 12px; text-align: center; border-top: 2px solid #e2e8f0; font-size: 11px; }';
    $output .= '.total-row { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; font-weight: bold; }';
    $output .= '.stats-row { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; font-weight: bold; text-align: center; }';
    $output .= '.paid { background: #d1fae5; color: #065f46; font-weight: bold; }';
    $output .= '.pending { background: #fef3c7; color: #92400e; font-weight: bold; }';
    $output .= '.rupee { font-family: "Arial Unicode MS", Arial, sans-serif; color: #065f46; font-weight: bold; }';
    $output .= '.highlight { background: #f0fdf4; }';
    $output .= '.border-top { border-top: 3px solid #10b981; }';
    $output .= '.border-bottom { border-bottom: 3px solid #10b981; }';
    $output .= '</style>';
    $output .= '</head>';
    $output .= '<body>';
    
    $output .= '<table width="100%" cellspacing="0" cellpadding="0" class="border-top">';
    $output .= '<tr><td colspan="8" class="excel-header">🚰 जल कनेक्शन विवरण - विस्तृत रिपोर्ट</td></tr>';
    
    // Date and summary row
    $output .= '<tr><td colspan="8" class="excel-subheader">';
    $output .= '<strong>📅 रिपोर्ट तिथि:</strong> ' . date('d/m/Y') . ' | <strong>⏰ समय:</strong> ' . date('H:i:s') . ' | <strong>👤 उपयोगकर्ता:</strong> ' . ($_SESSION['username'] ?? 'N/A');
    
    // Build the SQL query to fetch data from the database.
    $where = [];
    $search_query = $_GET['query'] ?? '';
    
    // If a search query is provided, build the WHERE clause for multiple columns
    if (!empty($search_query)) {
        $q = $pdo->quote('%' . $search_query . '%');
        $where[] = "($db_col_con_no LIKE $q OR " .
                   "$db_col_owner_name LIKE $q OR " .
                   "$db_col_mobile LIKE $q OR " .
                   "$db_col_ward_no LIKE $q)";
    }
    
    $sql = "SELECT ".
           "$db_col_con_no, ".
           "$db_col_ward_no, ".
           "$db_col_owner_name, ".
           "$db_col_mobile, ".
           "$db_col_current_amount, ".
           "$db_col_arrear_balance, ".
           "$db_col_remaining_balance ".
           "FROM bills".
           ($where?' WHERE '.implode(' AND ',$where):'').
           " ORDER BY ".$db_col_con_no;
    
    try {
        $stmt = $pdo->query($sql);
        $total_connections = $stmt->rowCount();
        $output .= ' | <strong>🔢 कुल कनेक्शन:</strong> ' . $total_connections . '</td></tr>';
        
        $output .= '<tr>';
        $output .= '<th>🔗 कनेक्शन नंबर</th>';
        $output .= '<th>🏘️ वार्ड नंबर</th>';
        $output .= '<th>👤 मालिक का नाम</th>';
        $output .= '<th>📱 मोबाइल नंबर</th>';
        $output .= '<th>💰 वर्तमान राशि<br>2025-26</th>';
        $output .= '<th>📅 बकाया शेष<br>2024-25</th>';
        $output .= '<th>🧾 शेष राशि</th>';
        $output .= '<th>📊 स्थिति</th>';
        $output .= '</tr>';
        
        $total_current_amount = 0;
        $total_arrear_balance = 0;
        $total_remaining_balance = 0;
        $paid_connections = 0;
        $pending_connections = 0;
        
        if($stmt->rowCount() > 0) {
            $i = 1;
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $current_amount = $row[str_replace('`', '', $db_col_current_amount)] ?? 0;
                $arrear_balance = $row[str_replace('`', '', $db_col_arrear_balance)] ?? 0;
                $remaining_balance = $row[str_replace('`', '', $db_col_remaining_balance)] ?? 0;
                
                $total_current_amount += $current_amount;
                $total_arrear_balance += $arrear_balance;
                $total_remaining_balance += $remaining_balance;
                
                // Determine status and count
                if ($remaining_balance == 0) {
                    $status = '✅ भुगतान';
                    $status_class = 'paid';
                    $paid_connections++;
                } else if ($remaining_balance > 0) {
                    $status = '❌ बकाया';
                    $status_class = 'pending';
                    $pending_connections++;
                } else {
                    $status = '🔄 प्रगति';
                    $status_class = '';
                }
                
                $row_class = ($i % 2 == 0) ? 'highlight' : '';
                
                $output .= '<tr class="' . $row_class . '">';
                $output .= '<td style="font-weight:bold;">' . htmlspecialchars($row[str_replace('`', '', $db_col_con_no)] ?? '') . '</td>';
                $output .= '<td style="text-align:center;">' . htmlspecialchars($row[str_replace('`', '', $db_col_ward_no)] ?? '') . '</td>';
                $output .= '<td style="font-weight:bold;">' . htmlspecialchars($row[str_replace('`', '', $db_col_owner_name)] ?? '') . '</td>';
                $output .= '<td style="text-align:center;">' . htmlspecialchars($row[str_replace('`', '', $db_col_mobile)] ?? '') . '</td>';
                // --- APPLIED INDIAN CURRENCY FORMAT FOR EXCEL ---
                $output .= '<td class="rupee" style="text-align:right;">₹' . format_indian_currency($current_amount) . '</td>';
                $output .= '<td class="rupee" style="text-align:right;">₹' . format_indian_currency($arrear_balance) . '</td>';
                $output .= '<td class="rupee" style="text-align:right;">₹' . format_indian_currency($remaining_balance) . '</td>';
                // --- END APPLIED INDIAN CURRENCY FORMAT FOR EXCEL ---
                $output .= '<td style="text-align:center; font-weight:bold;" class="' . $status_class . '">' . $status . '</td>';
                $output .= '</tr>';
            }
            
            // Add totals row
            $output .= '<tr class="total-row border-bottom">';
            $output .= '<td colspan="4" style="text-align:right; font-size:13px;"><strong>कुल योग:</strong></td>';
            // --- APPLIED INDIAN CURRENCY FORMAT FOR EXCEL TOTALS ---
            $output .= '<td style="text-align:right; font-size:13px;"><strong>₹' . format_indian_currency($total_current_amount) . '</strong></td>';
            $output .= '<td style="text-align:right; font-size:13px;"><strong>₹' . format_indian_currency($total_arrear_balance) . '</strong></td>';
            $output .= '<td style="text-align:right; font-size:13px;"><strong>₹' . format_indian_currency($total_remaining_balance) . '</strong></td>';
            // --- END APPLIED INDIAN CURRENCY FORMAT FOR EXCEL TOTALS ---
            $output .= '<td style="text-align:center; font-size:13px;"><strong>📊 सांख्यिकी</strong></td>';
            $output .= '</tr>';
            
            // Add comprehensive statistics row
            $output .= '<tr class="stats-row">';
            $output .= '<td colspan="8" style="text-align:center; padding:20px; font-size:14px;">';
            $output .= '📊 <strong>विस्तृत सांख्यिकी</strong> | ';
            $output .= '🔢 कुल कनेक्शन: <strong>' . $total_connections . '</strong> | ';
            $output .= '✅ भुगतान: <strong>' . $paid_connections . '</strong> | ';
            $output .= '❌ बकाया: <strong>' . $pending_connections . '</strong> | ';
            // --- APPLIED INDIAN CURRENCY FORMAT FOR EXCEL STATS ---
            $output .= '💰 कुल राशि: <strong>₹' . format_indian_currency($total_current_amount + $total_arrear_balance) . '</strong> | ';
            $output .= '📈 औसत बकाया: <strong>₹' . format_indian_currency(($total_remaining_balance)/max($total_connections,1)) . '</strong>';
            // --- END APPLIED INDIAN CURRENCY FORMAT FOR EXCEL STATS ---
            $output .= '</td>';
            $output .= '</tr>';
            
            // Add footer
            $output .= '<tr>';
            $output .= '<td colspan="8" class="excel-footer">';
            $output .= '🖨️ जनरेटेड ऑन: ' . date('d/m/Y H:i:s') . ' | 👨‍💼 द्वारा: ' . ($_SESSION['username'] ?? 'प्रशासन') . ' | 🔐 प्रणाली: जल प्रबंधन';
            $output .= '</td>';
            $output .= '</tr>';
            
        } else {
            $output .= '<tr><td colspan="8" style="text-align:center;padding:30px;color:#666;font-style:italic;">❌ कोई डेटा उपलब्ध नहीं है</td></tr>';
        }
    } catch (PDOException $e) {
        $output .= '<tr><td colspan="8" style="text-align:center;color:red;padding:20px;font-weight:bold;">🚫 डेटाबेस त्रुटि: ' . $e->getMessage() . '</td></tr>';
    }
    
    $output .= '</table>';
    $output .= '</body></html>';
    echo $output;
    exit;
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>जल प्रबंधन</title>
<link rel="icon" type="image/png" href="img1.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* ---------- Global Styles ---------- */
:root {
  --primary: #10b981;
  --secondary: #059669;
  --light-green: #d1fae5;
  --dark-green: #065f46;
  --gradient-primary: linear-gradient(135deg, #10b981, #059669);
  --gradient-secondary: linear-gradient(135deg, #34d399, #10b981);
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
  background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
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
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px 15px;
}

/* ---------- Heading Styles ---------- */
.main-heading {
  text-align: center;
  color: var(--dark-green);
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
  max-width: 650px;
}

.search-box {
  position: relative;
  width: 300px;
  max-width: 100%;
}

.search-box input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #d1fae5;
  border-radius: 10px;
  font-size: 14px;
  background: white;
  box-shadow: var(--shadow);
  transition: all 0.3s ease;
}

.search-box input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}

.search-btn, .export-btn {
  padding: 12px 16px;
  border: none;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: var(--shadow);
  display: flex;
  align-items: center;
  gap: 6px;
}

.search-btn {
  background: var(--gradient-primary);
  color: white;
}

.export-btn {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}

.search-btn:hover, .export-btn:hover {
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
  max-width: 1200px;
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

.stat-card.connections {
  background: var(--gradient-primary);
  color: white;
}

.stat-card.current {
  background: var(--gradient-secondary);
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
  max-width: 1400px;
  margin: 0 auto;
  background: white;
  border-radius: 12px;
  box-shadow: var(--shadow-hover);
  overflow: hidden;
  border: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  height: calc(100vh - 380px);
  min-height: 400px;
}

/* ---------- Fixed Table Header Container ---------- */
.table-header-container {
  background: white;
  border-bottom: 2px solid #e5e7eb;
  overflow: hidden;
  flex-shrink: 0;
  position: sticky;
  top: 0;
  z-index: 100;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  margin: 0;
  table-layout: fixed;
}

/* Fixed header styling */
.data-table thead {
  position: sticky;
  top: 0;
  z-index: 101;
  background: white;
}

.data-table th {
  background: var(--gradient-primary);
  color: white;
  padding: 15px 8px;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  border-right: 1px solid rgba(255,255,255,0.2);
  position: sticky;
  top: 0;
  z-index: 102;
}

.data-table th:last-child {
  border-right: none;
}

/* ---------- Scrollable Table Body Container ---------- */
.table-body-container {
  flex: 1;
  overflow-y: auto;
  overflow-x: auto;
}

/* Ensure header stays fixed while body scrolls */
.table-body-container table {
  border-collapse: collapse;
  width: 100%;
}

.table-body-container thead {
  display: none; /* Hide thead in body container as we already have fixed header */
}

.data-table tbody tr {
  transition: all 0.2s ease;
}

.data-table tbody tr:nth-child(even) {
  background: #f8fafc;
}

.data-table tbody tr:hover {
  background: var(--light-green);
}

.data-table td {
  padding: 12px 8px;
  border-bottom: 1px solid #e5e7eb;
  font-size: 14px;
  text-align: center;
  vertical-align: middle;
  transition: all 0.2s ease;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Column specific styles - updated for better fit */
.con-no-col { width: 12%; font-weight: 600; font-size: 13px; }
.ward-no-col { width: 8%; font-size: 13px; }
.owner { 
  width: 22%; 
  text-align: left; 
  padding-left: 10px !important; 
  font-size: 13px; 
  font-weight: bold !important;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.mobile-col { width: 10%; font-family: monospace; font-size: 13px; }
.current-amount-col { width: 12%; font-weight: 600; color: #059669; font-size: 13px; text-align: right; padding-right: 15px !important; }
.arrear-col { width: 12%; font-weight: 600; color: #d97706; font-size: 13px; text-align: right; padding-right: 15px !important; }
.remaining-col { width: 12%; font-weight: 600; color: #dc2626; font-size: 13px; text-align: right; padding-right: 15px !important; }
.act { width: 12%; }

/* Status badges - केवल आइकन */
.status-paid {
  background: #d1fae5;
  color: #065f46;
  padding: 6px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  display: inline-block;
  min-width: 80px;
}

/* Action buttons */
.action-buttons {
  display: flex;
  justify-content: center;
  gap: 6px;
}

.action-btn {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 13px;
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
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}

.pay-btn {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}

.action-btn:hover {
  transform: scale(1.1);
  box-shadow: var(--shadow-hover);
}

/* ---------- Message Styles ---------- */
.message-box {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
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

/* ---------- Empty Table Message ---------- */
.empty-table-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding: 40px;
  color: #6b7280;
  text-align: center;
}

.empty-table-message i {
  font-size: 60px;
  color: #d1d5db;
  margin-bottom: 20px;
  opacity: 0.5;
}

.empty-table-message h3 {
  font-size: 20px;
  color: #374151;
  margin-bottom: 10px;
  font-weight: 600;
}

.empty-table-message p {
  font-size: 14px;
  max-width: 500px;
  line-height: 1.6;
  color: #6b7280;
}

/* ---------- Modal Styles ---------- */
.modal-content {
  border-radius: 12px;
  border: none;
  box-shadow: var(--shadow-hover);
  overflow: hidden;
}

.modal-header {
  background: var(--gradient-primary);
  color: white;
  border-bottom: none;
  padding: 16px 20px;
}

.modal-title {
  font-weight: 600;
  font-size: 16px;
}

.btn-close {
  filter: invert(1);
  opacity: 0.8;
}

.password-container {
  position: relative;
}

.toggle-password {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  cursor: pointer;
  color: #6b7280;
  z-index: 5;
}

/* ---------- Responsive Styles ---------- */
@media (max-width: 1200px) {
  .stats-container {
    gap: 12px;
  }
  
  .stat-card {
    min-width: 160px;
    max-width: 220px;
  }
  
  .main-table-container {
    height: calc(100vh - 370px);
  }
}

@media (max-width: 992px) {
  .main-heading {
    font-size: 28px;
  }
  
  .data-table th {
    padding: 12px 6px;
    font-size: 12px;
  }
  
  .data-table td {
    padding: 10px 6px;
    font-size: 12px;
  }
  
  .action-btn {
    width: 28px;
    height: 28px;
    font-size: 11px;
  }
  
  .org-name {
    font-size: 18px;
  }
  
  .main-table-container {
    height: calc(100vh - 350px);
  }
  
  .empty-table-message i {
    font-size: 50px;
  }
  
  .empty-table-message h3 {
    font-size: 18px;
  }
  
  .status-paid {
    font-size: 11px;
    padding: 5px 8px;
    min-width: 70px;
  }
  
  /* Adjust column widths for tablet */
  .owner { 
    width: 20%; 
    font-size: 12px; 
  }
  .con-no-col { width: 14%; font-size: 12px; }
  .ward-no-col { width: 9%; font-size: 12px; }
  .mobile-col { width: 12%; font-size: 12px; }
  .current-amount-col, .arrear-col, .remaining-col { 
    width: 11%; 
    font-size: 12px; 
  }
  .act { width: 13%; }
}

@media (max-width: 768px) {
  .topbar {
    padding: 10px 15px;
    height: auto;
    flex-direction: column;
    gap: 10px;
  }
  
  .topbar-left, .topbar-right {
    width: 100%;
    justify-content: center;
  }
  
  .logo {
    height: 35px;
  }
  
  .topbar a {
    font-size: 14px;
    padding: 6px 12px;
  }
  
  .main-heading {
    font-size: 24px;
    margin: 8px 0 15px;
  }
  
  .search-container {
    flex-direction: column;
    align-items: center;
    gap: 10px;
  }
  
  .search-box {
    width: 100%;
  }
  
  .stats-container {
    gap: 10px;
  }
  
  .stat-card {
    min-width: 45%;
    padding: 12px;
  }
  
  .stat-card .number {
    font-size: 18px;
  }
  
  .stat-card h3 {
    font-size: 12px;
  }
  
  .search-box input, .search-btn, .export-btn {
    padding: 10px 14px;
    font-size: 13px;
  }
  
  .main-table-container {
    border-radius: 10px;
    height: calc(100vh - 330px);
  }
  
  .data-table {
    min-width: 800px; /* For horizontal scrolling on mobile */
  }
  
  .data-table th, .data-table td {
    padding: 8px 4px;
    font-size: 10px;
  }
  
  .org-name {
    font-size: 16px;
  }
  
  .action-btn {
    width: 24px;
    height: 24px;
    font-size: 10px;
  }
  
  .empty-table-message {
    padding: 20px;
  }
  
  .empty-table-message i {
    font-size: 40px;
  }
  
  .empty-table-message h3 {
    font-size: 16px;
  }
  
  .status-paid {
    font-size: 9px;
    padding: 4px 6px;
    min-width: 60px;
  }
  
  /* Adjust column widths for mobile */
  .owner { 
    width: 18%; 
    font-size: 10px; 
    padding-left: 6px !important;
  }
  .con-no-col { width: 16%; font-size: 10px; }
  .ward-no-col { width: 10%; font-size: 10px; }
  .mobile-col { width: 13%; font-size: 10px; }
  .current-amount-col, .arrear-col, .remaining-col { 
    width: 9%; 
    font-size: 10px; 
  }
  .act { width: 15%; }
}

@media (max-width: 576px) {
  .main-heading {
    font-size: 22px;
  }
  
  .stat-card {
    min-width: 100%;
    padding: 10px;
  }
  
  .topbar {
    padding: 8px 12px;
  }
  
  .data-table th,
  .data-table td {
    padding: 6px 3px;
    font-size: 9px;
  }
  
  .action-buttons {
    gap: 3px;
  }
  
  .action-btn {
    width: 22px;
    height: 22px;
    font-size: 9px;
  }
  
  .org-name {
    font-size: 14px;
  }
  
  .empty-table-message i {
    font-size: 35px;
  }
  
  .empty-table-message h3 {
    font-size: 15px;
  }
  
  .status-paid {
    font-size: 8px;
    padding: 3px 5px;
    min-width: 50px;
  }
  
  .main-table-container {
    height: calc(100vh - 310px);
  }
}

/* Custom scrollbar */
.table-body-container::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.table-body-container::-webkit-scrollbar-track {
  background: #f1f5f9;
}

.table-body-container::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #10b981, #059669);
  border-radius: 4px;
}

.table-body-container::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #059669, #047857);
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

/* Table cell alignment for better readability */
.table-body-container td {
  vertical-align: middle;
}

/* Make sure table header stays on top during scroll */
.table-header-container th {
  position: sticky;
  top: 0;
  z-index: 10;
}

/* Ensure proper z-index for fixed elements */
.topbar {
  z-index: 1000;
}

.table-header-container {
  z-index: 999;
}

.data-table th {
  z-index: 1000;
}
</style>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Delete record with modal confirmation
function showDeleteModal(conNo, ownerName) {
    document.getElementById('connectionDetails').innerHTML = 
        `<strong>कनेक्शन नंबर:</strong> ${conNo}<br>
         <strong>ग्राहक का नाम:</strong> ${ownerName}`;
    
    var deleteUrl = 'delete_connection.php?id=' + conNo;
    document.getElementById('deleteConfirmBtn').setAttribute('data-href', deleteUrl);
    
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
    const deleteUrl = deleteBtn.getAttribute('data-href');
    
    if (password !== 'admin123') {
        alert('❌ गलत पासवर्ड');
        return;
    }
    
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> डिलीट हो रहा है...';
    deleteBtn.disabled = true;
    
    window.location.href = deleteUrl;
}

// Opens a new window to print bill
function printBill(conNo) {
    window.open('print_bill.php?id='+encodeURIComponent(conNo), '_blank');
}

// Redirect to payment page
function redirectToPayment(conNo) {
    window.open('https://sunnydhaka.fwh.is/payment.php?search=' + encodeURIComponent(conNo), '_blank');
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
        const windowHeight = window.innerHeight;
        const topbar = document.querySelector('.topbar');
        const heading = document.querySelector('.main-heading');
        const search = document.querySelector('.search-container');
        const stats = document.querySelector('.stats-container');
        
        // Ensure all elements exist before getting offsetHeight
        if (!topbar || !heading || !search || !stats || !tableContainer) return;
        
        const topbarHeight = topbar.offsetHeight;
        const headingHeight = heading.offsetHeight;
        const searchHeight = search.offsetHeight;
        const statsHeight = stats.offsetHeight;
        const margins = 40; // Extra padding/margins
        
        const calculatedHeight = windowHeight - topbarHeight - headingHeight - searchHeight - statsHeight - margins;
        const minHeight = 350;
        
        if (tableContainer) {
            tableContainer.style.height = Math.max(calculatedHeight, minHeight) + 'px';
        }
    }
    
    // Initial adjustment
    setTimeout(adjustTableHeight, 100);
    
    // Adjust on resize
    window.addEventListener('resize', adjustTableHeight);
    
    // Initialize tooltips if using Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
};

// Handle search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.querySelector('.search-box input');
    const searchButton = document.querySelector('.search-btn');
    
    // Update form input when typing in search box
    searchInput.addEventListener('input', function() {
        document.getElementById('searchQuery').value = this.value;
    });
    
    // Submit form when search button is clicked
    searchButton.addEventListener('click', function() {
        document.getElementById('searchQuery').value = searchInput.value;
        searchForm.submit();
    });
    
    // Submit form when Enter is pressed in search box
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('searchQuery').value = this.value;
            searchForm.submit();
        }
    });
    
    // Initialize table height
    adjustTableHeight();
});

// Function to adjust table height
function adjustTableHeight() {
    const tableContainer = document.querySelector('.main-table-container');
    if (!tableContainer) return;
    
    const windowHeight = window.innerHeight;
    const topbarHeight = document.querySelector('.topbar')?.offsetHeight || 70;
    const headingHeight = document.querySelector('.main-heading')?.offsetHeight || 60;
    const searchHeight = document.querySelector('.search-container')?.offsetHeight || 50;
    const statsHeight = document.querySelector('.stats-container')?.offsetHeight || 100;
    
    const calculatedHeight = windowHeight - topbarHeight - headingHeight - searchHeight - statsHeight - 60;
    const minHeight = 350;
    
    tableContainer.style.height = Math.max(calculatedHeight, minHeight) + 'px';
}
</script>
</head>
<body>
<div class="topbar">
    <div class="topbar-left">
        <img src="img1.png" alt="जल प्रबंधन लोगो" class="logo">
        <span class="org-name">जल प्रबंधन</span>
    </div>
    <div class="topbar-right">
        <a href="jal.php"><i class="fas fa-home"></i> होम</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> लॉगआउट</a>
    </div>
</div>

<div class="main-container">
    <h1 class="main-heading">🚰 जल कनेक्शन विवरण डैशबोर्ड</h1>

    <?php
    // Success and error message display
    if (isset($_GET['success'])) {
    echo '<div class="message-box">';
    echo '<i class="fas fa-check-circle"></i>' . htmlspecialchars($_GET['success']);
    echo '</div>';
    }

    if (isset($_GET['error'])) {
    echo '<div class="message-box error">';
    echo '<i class="fas fa-exclamation-circle"></i>' . htmlspecialchars($_GET['error']);
    echo '</div>';
    }
    ?>

    <div class="search-container">
        <div class="search-box">
            <input type="text" placeholder="🔍 कनेक्शन नंबर, नाम, मोबाइल, या वार्ड खोजें..." value="<?= htmlspecialchars($_GET['query']??'') ?>">
        </div>
        <button class="search-btn" type="button">
            <i class="fas fa-search"></i> खोजें
        </button>
        <a href="?<?= $_SERVER['QUERY_STRING'] ?>&export=excel" class="export-btn">
            <i class="fas fa-file-excel"></i> एक्सेल एक्सपोर्ट
        </a>
    </div>

    <form id="searchForm" method="get" style="display: none;">
        <input type="hidden" name="query" id="searchQuery" value="<?= htmlspecialchars($_GET['query']??'') ?>">
    </form>

    <?php
    // Create an empty array for search filters
    $where = [];

    // Get the single search query
    $search_query = $_GET['query'] ?? '';

    // If a search query is provided, build the WHERE clause for multiple columns
    if (!empty($search_query)) {
        $q = $pdo->quote('%' . $search_query . '%');
        $where[] = "($db_col_con_no LIKE $q OR " .
                   "$db_col_owner_name LIKE $q OR " .
                   "$db_col_mobile LIKE $q OR " .
                   "$db_col_ward_no LIKE $q)";
    }

    // Build the SQL query to fetch data from the database.
    $sql = "SELECT ".
           "$db_col_con_no, ".
           "$db_col_ward_no, ".
           "$db_col_owner_name, ".
           "$db_col_mobile, ".
           "$db_col_current_amount, ".
           "$db_col_arrear_balance, ".
           "$db_col_remaining_balance ".
           "FROM bills".
           ($where?' WHERE '.implode(' AND ',$where):'').
           " ORDER BY ".$db_col_con_no;
    try {
        $stmt=$pdo->query($sql);
    } catch (PDOException $e) {
        die("<h2 style='text-align:center;color:red;padding:20px;'>डेटाबेस क्वेरी त्रुटि: " . $e->getMessage() . "</h2>");
    }

    // Calculate totals
    $total_connections = 0;
    $total_current_amount = 0;
    $total_arrear_balance = 0;
    $total_remaining_balance = 0;

    // Resetting cursor/fetching all for re-use:
    $all_rows = [];
    if($stmt->rowCount() > 0) {
        $all_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Calculate totals from the fetched array
        foreach($all_rows as $row) {
            $total_connections++;
            $total_current_amount += $row[str_replace('`', '', $db_col_current_amount)] ?? 0;
            $total_arrear_balance += $row[str_replace('`', '', $db_col_arrear_balance)] ?? 0;
            $total_remaining_balance += $row[str_replace('`', '', $db_col_remaining_balance)] ?? 0;
        }
    }
    ?>

    <!-- Stats Cards ALWAYS SHOW -->
    <div class="stats-container">
        <div class="stat-card connections">
            <div class="icon">👥</div>
            <h3>कुल कनेक्शन</h3>
            <div class="number"><?= $total_connections ?></div>
            <p>सभी कनेक्शनों की कुल संख्या</p>
        </div>
        <div class="stat-card current">
            <div class="icon">💰</div>
            <h3>वर्तमान राशि</h3>
            <div class="number">₹<?= format_indian_currency($total_current_amount) ?></div>
            <p>2025-26 वर्ष की राशि</p>
        </div>
        <div class="stat-card arrear">
            <div class="icon">📅</div>
            <h3>बकाया शेष</h3>
            <div class="number">₹<?= format_indian_currency($total_arrear_balance) ?></div>
            <p>2024-25 वर्ष का बकाया</p>
        </div>
        <div class="stat-card remaining">
            <div class="icon">🧾</div>
            <h3>शेष राशि</h3>
            <div class="number">₹<?= format_indian_currency($total_remaining_balance) ?></div>
            <p>सभी कनेक्शनों का शेष</p>
        </div>
    </div>

    <!-- Main Table Container ALWAYS SHOW -->
    <div class="main-table-container">
        <!-- Fixed Header Container -->
        <div class="table-header-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="con-no-col">🔗 कनेक्शन</th>
                        <th class="ward-no-col">🏘️ वार्ड</th>
                        <th class="owner">👤 नाम</th>
                        <th class="mobile-col">📱 मोबाइल</th>
                        <th class="current-amount-col">💰 25‑26</th>
                        <th class="arrear-col">📅 24‑25</th>
                        <th class="remaining-col">🧾 शेष</th>
                        <th class="act">⚙️ क्रिया</th>
                    </tr>
                </thead>
            </table>
        </div>

        <!-- Scrollable Body Container -->
        <div class="table-body-container">
            <?php if (empty($search_query)): ?>
            <!-- Show empty message when no search query -->
            <div class="empty-table-message">
                <i class="fas fa-search"></i>
                <h3>खोज शुरू करें</h3>
                <p>कनेक्शन विवरण देखने के लिए ऊपर दिए गए सर्च बॉक्स में कनेक्शन नंबर, ग्राहक का नाम, मोबाइल नंबर या वार्ड नंबर डालकर सर्च करें।</p>
            </div>
            <?php else: ?>
            <!-- Show table data only when search is performed -->
            <table class="data-table">
                <tbody>
                <?php
                // Check if any rows were found
                if(empty($all_rows)) {
                    echo '<tr><td colspan="8" class="no-data"><i class="fas fa-inbox"></i>"' . htmlspecialchars($search_query) . '" के लिए कोई डेटा नहीं मिला</td></tr>';
                } else {
                    $i=1;
                    foreach($all_rows as $row):
                    $current_con_no = $row[str_replace('`', '', $db_col_con_no)] ?? '';
                    $owner_name = $row[str_replace('`', '', $db_col_owner_name)] ?? '-';
                    $remaining_balance = $row[str_replace('`', '', $db_col_remaining_balance)] ?? 0;
                    ?>
                    <tr>
                        <td class="con-no-col"><?= htmlspecialchars($current_con_no);?></td>
                        <td class="ward-no-col"><?= htmlspecialchars($row[str_replace('`', '', $db_col_ward_no)]??'-');?></td>
                        <td class="owner" title="<?= htmlspecialchars($owner_name);?>"><?= htmlspecialchars($owner_name);?></td>
                        <td class="mobile-col"><?= htmlspecialchars($row[str_replace('`', '', $db_col_mobile)]??'-');?></td>
                        <td class="current-amount-col">₹<?= format_indian_currency($row[str_replace('`', '', $db_col_current_amount)]??0);?></td>
                        <td class="arrear-col">₹<?= format_indian_currency($row[str_replace('`', '', $db_col_arrear_balance)]??0);?></td>
                        <td class="remaining-col">₹<?= format_indian_currency($remaining_balance);?></td>
                        <td class="act">
                            <div class="action-buttons">
                                <a href="edit_connection.php?id=<?= urlencode($current_con_no); ?>" class="action-btn edit-btn" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" onclick="showDeleteModal('<?= urlencode($current_con_no); ?>', '<?= htmlspecialchars($owner_name); ?>'); return false;" class="action-btn delete-btn" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a href="#" onclick="printBill('<?= urlencode($current_con_no); ?>')" class="action-btn print-btn" title="Print Bill">
                                    <i class="fas fa-print"></i>
                                </a>
                                <?php if ($remaining_balance > 0): ?>
                                <a href="#" onclick="redirectToPayment('<?= urlencode($current_con_no); ?>')" class="action-btn pay-btn" title="Pay Now">
                                    <i class="fas fa-money-bill-wave"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="mt-1">
                                <?php if ($remaining_balance == 0): ?>
                                <span class="status-paid">✅ भुगतान</span>
                                <?php else: ?>
                                <!-- बकाया के लिए केवल खाली जगह छोड़ी है -->
                                <span style="display: inline-block; width: 80px;">&nbsp;</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php
                    endforeach;
                }
                ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>कनेक्शन डिलीट करें
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger text-center" role="alert" style="border-radius: 10px;">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>चेतावनी!</strong> यह कार्रवाई पूर्ववत नहीं की जा सकती है
                </div>
                
                <div class="border p-3 mb-3 rounded" style="background: #f8f9fa;">
                    <h6 class="text-center mb-3">निम्नलिखित कनेक्शन को डिलीट करना चाहते हैं?</h6>
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