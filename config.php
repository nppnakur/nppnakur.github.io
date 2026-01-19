<?php
// Development में errors दिखाएं (Production में इसे 0 पर सेट करें)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DB credentials
$host     = "sql101.infinityfree.com";
$dbname   = "if0_39302314_sunnydhaka";
$username = "if0_39302314";
$password = "Sunnydhaka9003";

try {
    // PDO connection with improved settings
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4", 
        $username, 
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]
    );
    
    // Connection test query (optional, आप इसे हटा सकते हैं)
    $pdo->query("SELECT 1"); 
    
    // ** यह लाइन जोड़ें ताकि PDO ऑब्जेक्ट वापस किया जा सके **
    return $pdo; 
    
} catch (PDOException $e) {
    // User-friendly error message
    $error_message = "Database connection failed. Please try again later.";
    
    // Log the actual error for debugging
    error_log("Database Error [PDO001]: " . $e->getMessage());
    
    // Display error (in development only)
    if (ini_get('display_errors')) {
        $error_message .= "<br><br><strong>Technical Details:</strong> " . htmlspecialchars($e->getMessage());
        $error_message .= "<br><strong>Host:</strong> " . htmlspecialchars($host);
    }
    
    die("<div style='font-family: Arial; padding: 20px; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; max-width: 600px; margin: 50px auto;'>
        <h2 style='color: #721c24;'>⚠️ Database Connection Error (PDO001)</h2>
        <p>$error_message</p>
        <p>Please check:
        <ul>
            <li>Database credentials</li>
            <li>Server availability</li>
            <li>Internet connection</li>
        </ul>
        </p>
        <p>Contact support if this issue persists.</p>
        </div>");
}
?>