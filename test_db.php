<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Database config
$host     = "sql101.infinityfree.com";
$dbname   = "if0_39302314_sunnydhaka";
$username = "if0_39302314";
$password = "Sunnydhaka9003";

$dsn = "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8";

try {
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10
    ]);
    echo "✅ PDO कनेक्शन सफल!";
} catch (PDOException $e) {
    echo "<h2>❌ DB कनेक्शन फेल:</h2><pre>" . $e->getMessage() . "</pre>";
    exit;
}
?>
