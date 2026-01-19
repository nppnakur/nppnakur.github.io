<?php
// search.php
$csv_file = 'staff.csv';
$found = false;
$data = [];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    if (file_exists($csv_file)) {
        $rows = array_map('str_getcsv', file($csv_file));
        $header = array_shift($rows);
        foreach ($rows as $row) {
            $record = array_combine($header, $row);
            if ($record['id_number'] === $id) {
                $data = $record;
                $found = true;
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>QR ID खोज</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; padding: 40px; text-align: center; }
        .card {
            display: inline-block; background: white; padding: 20px; border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2); max-width: 400px;
        }
        img.photo {
            width: 150px; height: 200px; object-fit: cover; border: 2px solid #1a5f1a;
        }
        h2 { color: #1a5f1a; }
        .info { margin-top: 20px; text-align: left; }
        .info div { margin: 5px 0; font-size: 16px; }
    </style>
</head>
<body>
    <h2>QR ID खोज परिणाम</h2>
    <?php if ($found): ?>
        <div class="card">
            <img src="uploads/photos/<?php echo htmlspecialchars($data['photo']); ?>" class="photo" alt="फोटो">
            <div class="info">
                <div><strong>नाम:</strong> <?php echo htmlspecialchars($data['name']); ?></div>
                <div><strong>पद:</strong> <?php echo htmlspecialchars($data['designation']); ?></div>
                <div><strong>ID:</strong> <?php echo htmlspecialchars($data['id_number']); ?></div>
            </div>
        </div>
    <?php else: ?>
        <p style="color:red; font-weight:bold;">कोई रिकॉर्ड नहीं मिला!</p>
    <?php endif; ?>
</body>
</html>
