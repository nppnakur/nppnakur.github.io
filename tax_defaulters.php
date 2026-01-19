<?php
// PHP त्रुटि रिपोर्टिंग को सक्षम करें (Debugging के लिए)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// सत्र शुरू करें
session_start();

// Authentication: सुनिश्चित करें कि उपयोगकर्ता लॉग इन है
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// डेटाबेस कनेक्शन फ़ाइल शामिल करें
if (!file_exists('config.php')) {
    die("<h2 style='text-align:center;color:red'>त्रुटि: कॉन्फ़िगरेशन फ़ाइल नहीं मिली!</h2>");
}
include 'config.php';

// डेटाबेस कनेक्शन की उपलब्धता जांचें
if (!isset($pdo) || !$pdo instanceof PDO) {
    die("<h3>❌ डेटाबेस त्रुटि: config.php में \$pdo ऑब्जेक्ट परिभाषित नहीं है।</h3>");
}


// --- Define Actual Database Column Names (Based on tax_payment.php) ---
// महत्वपूर्ण: 'Owner\'s Name' में सिंगल कोट को सही से एस्केप किया गया है।
$sql_col_property_id         = '`Property ID`';
$sql_col_owner_name          = '`Owner\'s Name`'; 
$sql_col_mobile              = '`Mobile No`';
$sql_col_ward_name           = '`Ward Name`';
$sql_col_mohalla_name        = '`Mohalla Name`';
$sql_col_house_no            = '`House No`';
$sql_col_property_type       = '`Property Type`'; // नया कॉलम

$sql_col_current_house_tax   = '`Current House Tax`';
$sql_col_arrear_house_tax    = '`Arrear House Tax`';
$sql_col_current_water_tax   = '`Current Water Tax`';
$sql_col_arrear_water_tax    = '`Arrear Water Tax`';

// Calculate total remaining balance dynamically (सभी 4 कंपोनेंट्स का योग)
$sql_remaining_balance = "(IFNULL({$sql_col_current_house_tax}, 0) + IFNULL({$sql_col_arrear_house_tax}, 0) + IFNULL({$sql_col_current_water_tax}, 0) + IFNULL({$sql_col_arrear_water_tax}, 0))";


// इनपुट पैरामीटर्स को सुरक्षित रूप से प्राप्त करें
$min_due = isset($_GET['min_due']) ? max(0, intval($_GET['min_due'])) : 1000;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$export = isset($_GET['export']);

$results = [];
$total_due = 0;
$total_records = 0;

try {
    // मुख्य SQL क्वेरी तैयार करें
    $sql = "SELECT 
                {$sql_col_property_id} AS property_id, 
                {$sql_col_owner_name} AS owner_name, 
                {$sql_col_ward_name} AS ward_name, 
                {$sql_col_mohalla_name} AS mohalla_name,
                {$sql_col_house_no} AS house_no,
                {$sql_col_property_type} AS property_type, 
                {$sql_col_mobile} AS mobile, 
                {$sql_remaining_balance} AS balance,
                {$sql_col_current_house_tax} AS current_house_tax,
                {$sql_col_arrear_house_tax} AS arrear_house_tax,
                {$sql_col_current_water_tax} AS current_water_tax,
                {$sql_col_arrear_water_tax} AS arrear_water_tax
            FROM properties 
            WHERE {$sql_remaining_balance} >= :min_due";
    
    $params = [':min_due' => $min_due];
    
    // Search फ़िल्टर जोड़ें
    if (!empty($search_term)) {
        $search_param = "%{$search_term}%";
        $sql .= " AND (
                        {$sql_col_property_id} LIKE :search OR 
                        {$sql_col_owner_name} LIKE :search OR 
                        {$sql_col_mobile} LIKE :search OR
                        {$sql_col_ward_name} LIKE :search
                    )";
        $params[':search'] = $search_param;
    }
    
    // परिणाम को बकाया राशि के अनुसार अवरोही क्रम में क्रमबद्ध करें (चार्ट के लिए आवश्यक)
    $sql .= " ORDER BY balance DESC";
    
    // स्टेटमेंट तैयार करें और चलाएं
    $stmt = $pdo->prepare($sql);    
    $stmt->execute($params);
    
    // सभी परिणाम प्राप्त करें
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // कुल रिकॉर्ड और कुल बकाया राशि की गणना करें
    $total_records = count($results);
    $total_due = array_sum(array_column($results, 'balance'));

    // ----------------------------------------------------
    // --- CSV (Excel) Export Logic ---
    // ----------------------------------------------------
    if ($export) {
        ob_clean(); // आउटपुट बफ़र को साफ़ करें
        
        // CSV Headers सेट करें 
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Defaulter_List_Property_'.date('Y-m-d').'.csv"');
        $output = fopen('php://output', 'w');
        fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF))); // UTF-8 BOM

        // CSV कॉलम हेडर
        fputcsv($output, [
            'प्रॉपर्टी ID', 
            'स्वामी का नाम', 
            'प्रॉपर्टी प्रकार', 
            'वार्ड नाम', 
            'मोहल्ला/हाउस नं', 
            'मोबाइल नं', 
            'करंट हाउस टैक्स (₹)', 
            'बकाया हाउस टैक्स (₹)', 
            'करंट वाटर टैक्स (₹)', 
            'बकाया वाटर टैक्स (₹)', 
            'कुल बकाया (₹)'
        ]);

        // डेटा पंक्तियाँ
        foreach ($results as $row) {
            $data_row = [
                $row['property_id'],
                $row['owner_name'],
                $row['property_type'], 
                $row['ward_name'],
                $row['mohalla_name'] . ' / ' . $row['house_no'],
                $row['mobile'],
                number_format((float)$row['current_house_tax'], 2, '.', ''),
                number_format((float)$row['arrear_house_tax'], 2, '.', ''),
                number_format((float)$row['current_water_tax'], 2, '.', ''),
                number_format((float)$row['arrear_water_tax'], 2, '.', ''),
                number_format((float)$row['balance'], 2, '.', '') // Total Due
            ];
            fputcsv($output, $data_row);
        }

        fclose($output);
        exit;
    }

} catch (PDOException $e) {
    error_log("Defaulter List PDO Error: " . $e->getMessage());
    die("<h3>❌ डेटाबेस त्रुटि: कृपया सुनिश्चित करें कि 'properties' टेबल और कॉलम नाम सही हैं। Error: " . $e->getMessage() . "</h3>");
}

function format_inr($amount) {
    // यदि NumberFormatter उपलब्ध है तो बेहतर फॉर्मेटिंग
    if (class_exists('NumberFormatter')) {
        $fmt = new NumberFormatter('en_IN', NumberFormatter::CURRENCY);
        return $fmt->formatCurrency($amount, "INR");
    }
    // अन्यथा डिफ़ॉल्ट फॉर्मेटिंग
    return "₹" . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>बड़े बकायदार - प्रॉपर्टी टैक्स</title>
    <link rel="icon" type="image/png" href="img1.png"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* CSS Styles */
        body {
            font-family: 'Segoe UI', 'Nirmala UI', sans-serif;
            background: #f4f7f6; 
            padding: 20px;
            margin: 0;
            color: #333;
        }
        .nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: linear-gradient(135deg, #e0f7fa, #b2ebf2); 
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .nav-header a {
            text-decoration: none;
            font-size: 18px;
            color: #000;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        .nav-header a:hover {
            background-color: rgba(0, 188, 212, 0.2);
        }
        .nav-header a.logout {
            color: #d32f2f;
        }
        .nav-header a.logout:hover {
            background-color: rgba(211, 47, 47, 0.1);
        }
        .nav-logo {
            height: 40px;
            margin-right: 15px;
        }
        .nav-main-link {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
        }
        h2 {
            color: #007bff; 
            text-align: center;
            margin: 0 0 25px 0;
            font-size: 32px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            padding-bottom: 10px;
            border-bottom: 2px solid #b2ebf2;
        }
        .filter-container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-group label {
            font-weight: 600;
            color: #555;
        }
        input[type="number"], 
        input[type="text"] {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            min-width: 200px;
            transition: border 0.3s;
        }
        input[type="number"]:focus, 
        input[type="text"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
        }
        button, .btn {
            padding: 10px 20px;
            background: #007bff; 
            color: #fff;
            border: none;
            cursor: pointer;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        button:hover, .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-print {
            background: #28a745; 
        }
        .btn-print:hover {
            background: #1e7e34;
        }
        .total-summary {
            background: #e6f7ff; 
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 18px;
            color: #004d99;
            margin: 20px 0;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            border-left: 5px solid #007bff;
        }
        .total-summary span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .chart-container {
            width: 100%;
            max-width: 700px; /* चार्ट का आकार नियंत्रित */
            height: 400px; /* ऊँचाई नियंत्रित */
            margin: 30px auto;
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin: 20px 0 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-radius: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        thead th {
            background: linear-gradient(135deg, #007bff, #0056b3); 
            color: #fff;
            font-weight: 600;
            padding: 15px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        tbody tr:nth-child(even) {
            background-color: #f8faff;
        }
        tbody tr:hover {
            background-color: #e6f4ff;
        }
        td {
            padding: 12px 15px;
            text-align: center;
        }
        td:first-child {
            font-weight: 600;
            color: #0066cc;
        }
        td:nth-child(3) {  
            max-width: 150px; 
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: left; 
        }
        .no-results {
            text-align: center;
            padding: 30px;
            color: #666;
            font-size: 18px;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            display: none;
            flex-direction: column;
        }

        .logo-spinner {
            text-align: center;
        }

        .loading-logo {
            width: 80px;
            height: 80px;
            animation: pulsate 1.5s ease-out infinite;  
        }

        @keyframes pulsate {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .logo-spinner p {
            margin-top: 15px;
            font-size: 18px;
            font-weight: bold;
            color: #007bff; 
        }

        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group {
                flex-direction: column;
                align-items: stretch;
                gap: 5px;
            }
            input[type="number"], 
            input[type="text"] {
                width: 100%;
                min-width: auto;
            }
            .nav-header {
                flex-direction: column;
                gap: 10px;
            }
            .total-summary {
                flex-direction: column;
                gap: 10px;
            }
            thead th, td {
                padding: 10px 8px;
                font-size: 14px;
            }
            td:nth-child(3) {
                max-width: 100px;  
                font-size: 12px; 
            }
            .chart-container {
                height: 350px; /* छोटे स्क्रीन के लिए ऊँचाई कम की गई */
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>

<div class="loading-overlay">
    <div class="logo-spinner">
        <img src="img1.png" alt="Loading Logo" class="loading-logo">
        <p>कृपया प्रतीक्षा करें...</p>
    </div>
</div>

<div class="nav-header">
    <a href="house_tax_dashboard.php" class="nav-main-link">
        <img src="img1.png" alt="Logo" class="nav-logo">
        <i class="fas fa-home"></i> <b>मुख्य पृष्ठ</b>
    </a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> लॉगआउट</a>
</div>

<h2><i class="fas fa-house-damage"></i> बड़े बकायदारों की सूची (प्रॉपर्टी टैक्स)</h2>

<div class="filter-container">
    <form method="get" id="filterForm" class="filter-form">
        <div class="filter-group">
            <label for="min_due"><i class="fas fa-rupee-sign"></i> न्यूनतम बकाया:</label>
            <input type="number" id="min_due" name="min_due" value="<?= htmlspecialchars($min_due) ?>" min="0" step="100">
        </div>
        
        <div class="filter-group">
            <label for="search"><i class="fas fa-search"></i> खोजें:</label>
            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_term) ?>" 
                    placeholder="ID, नाम, मोबाइल, वार्ड...">
        </div>
        
        <button type="submit"><i class="fas fa-filter"></i> फिल्टर लागू करें</button>
        <a href="?min_due=1000" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> रीसेट</a>
        <button type="button" onclick="exportToExcel()" class="btn"><i class="fas fa-file-excel"></i> एक्सेल (CSV)</button>
        <button type="button" onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> प्रिंट</button>
    </form>
</div>

<div class="total-summary">
    <span><i class="fas fa-users"></i> कुल प्रॉपर्टीज़: <?= number_format($total_records) ?></span>
    <span><i class="fas fa-rupee-sign"></i> कुल बकाया राशि: <?= format_inr($total_due) ?></span>
</div>

<?php if (!$export): ?>
    <?php if ($total_records > 0): ?>
    
    <div class="chart-container">
        <h3 style="text-align: center; color: #007bff; margin-top: 0;">शीर्ष 10 बकायेदार ग्राहक (राशि के अनुसार)</h3>
        <canvas id="barChart"></canvas>
    </div>

    <div class="table-responsive">
        <table id="defaulterTable">
            <thead>
                <tr>
                    <th>प्रॉपर्टी ID</th>
                    <th>स्वामी का नाम</th>
                    <th>प्रॉपर्टी प्रकार</th> 
                    <th>वार्ड / मोहल्ला</th> 
                    <th>हाउस नं</th>
                    <th>मोबाइल नंबर</th>
                    <th>कुल बकाया</th>
                    <th>एक्शन</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['property_id']) ?></td>
                    <td><?= htmlspecialchars($row['owner_name']) ?></td>
                    <td><?= htmlspecialchars($row['property_type']) ?></td> 
                    <td><?= htmlspecialchars($row['ward_name']) . ' / ' . htmlspecialchars($row['mohalla_name']) ?></td> 
                    <td><?= htmlspecialchars($row['house_no']) ?></td>
                    <td><?= htmlspecialchars($row['mobile']) ?></td>
                    <td data-balance="<?= htmlspecialchars($row['balance']) ?>" style="color:#d32f2f;font-weight:bold;">
                        <?= format_inr($row['balance']) ?>
                    </td>
                    <td>
                         <a href="tax_payment.php?property_id=<?= urlencode($row['property_id']) ?>" 
                            class="btn" style="background: #28a745; padding: 6px 10px; font-size: 13px;">
                            <i class="fas fa-search"></i> देखें
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="no-results">
        <i class="far fa-frown" style="font-size:24px;"></i><br><br>
        कोई बकायेदार प्रॉपर्टी नहीं मिली। कृपया फिल्टर मान बदलकर पुनः प्रयास करें।
    </div>
    <?php endif; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// लोडिंग ओवरले दिखाएं
function showLoading() {
    document.querySelector('.loading-overlay').style.display = 'flex';
}

// लोडिंग ओवरले छिपाएं
function hideLoading() {
    document.querySelector('.loading-overlay').style.display = 'none';
}

// "पीछे जाएं" बटन पर काम करने के लिए नया कोड
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        hideLoading();
    }
});

// पेज लोड होने पर लोडर को छिपा दें
window.addEventListener('load', function() {
    hideLoading();
});

// एक्सेल एक्सपोर्ट फंक्शन
function exportToExcel() {
    showLoading();
    const form = document.getElementById('filterForm');
    const exportInput = document.createElement('input');
    exportInput.type = 'hidden';
    exportInput.name = 'export';
    exportInput.value = '1';
    form.appendChild(exportInput);
    
    // फ़ॉर्म सबमिट करने से पहले लोडिंग को कुछ समय तक दिखाने के लिए
    setTimeout(function() {
        hideLoading(); 
    }, 5000); 

    form.submit();
}

// चार्ट इनिशियलाइज़ेशन
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('barChart');
    
    if (!ctx) return;
    
    const table = document.getElementById('defaulterTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    const chartData = {
        labels: [],
        datasets: [{
            label: 'बकाया राशि (₹)',
            backgroundColor: '#d32f2f', 
            borderColor: '#800000',
            borderWidth: 1,
            data: [],
            borderRadius: 6
        }]
    };
    
    // केवल पहले 10 रिकॉर्ड का उपयोग करें (slice(0, 10))
    if (rows.length > 0) {
        Array.from(rows).slice(0, 10).forEach(row => {  
            // 2nd cell is Owner Name
            const name = row.cells[1]?.textContent.trim() || 'N/A'; 
            // 6th cell is Balance (index 6)
            const balanceCell = row.cells[6]; 
            const balance = parseFloat(balanceCell?.getAttribute('data-balance')) || 0;
            
            // नाम को X-अक्ष पर फिट करने के लिए छोटा करें (Vertical Chart के लिए)
            // 15 वर्णों की सीमा के बाद '...' जोड़ें
            const shortName = name.length > 15 ? name.substring(0, 15) + '...' : name; 
            
            chartData.labels.push(shortName);
            chartData.datasets[0].data.push(balance);
        });
        
        // पोर्ट्रेट (वर्टिकल) चार्ट के लिए रिवर्स करने की आवश्यकता नहीं है क्योंकि डेटा पहले से ही अवरोही क्रम में है
        
    } else {
        chartData.labels.push('कोई बकायेदार नहीं');
        chartData.datasets[0].data.push(0);
        chartData.datasets[0].backgroundColor = '#cccccc';
    }
    
    new Chart(ctx, {
        type: 'bar',
        // indexAxis: 'y' हटा दिया गया है, इसलिए यह डिफ़ॉल्ट रूप से Vertical (Portrait) है
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }, 
                title: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ₹' + context.raw.toLocaleString('en-IN');
                        },
                        title: function(context) {
                             // Tooltip में पूरा नाम दिखाएँ
                            // context[0].label अभी भी छोटा नाम हो सकता है, इसलिए यहाँ full name का उपयोग करना मुश्किल है। 
                            // हम label के रूप में शॉर्ट नाम ही उपयोग करेंगे
                            return context[0].label;
                        }
                    },
                    bodyFont: {
                        size: 14,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                // X-axis (Labels/Names)
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: {  
                        font: { size: 12, weight: 'bold' },
                        color: '#666',
                        maxRotation: 45, // नाम को थोड़ा घुमाएँ
                        minRotation: 45
                    },
                    title: { 
                        display: true,
                        text: 'स्वामी का नाम',
                         font: {
                            size: 14,
                            weight: 'bold'
                        },
                        color: '#333'
                    }
                },
                // Y-axis (Values/Balance)
                y: {
                    beginAtZero: true,
                    grid: {  
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    },
                    ticks: {  
                        font: { size: 12, weight: 'bold' },
                        color: '#666',
                        callback: function(value) {
                            // Y-Axis (राशि) के लिए
                            return '₹' + value.toLocaleString('en-IN');
                        }
                    },
                    title: { 
                        display: true,
                        text: 'कुल बकाया राशि (₹)',
                        font: {
                            size: 14,
                            weight: 'bold'
                        },
                        color: '#333'
                    }
                }
            },
            animation: {
                duration: 1500,
                easing: 'easeOutQuart'
            }
        },
        data: chartData 
    });
});

document.getElementById('filterForm').addEventListener('submit', function() {
    showLoading();
});
</script>
</body>
</html>