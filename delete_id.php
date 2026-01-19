<?php
// delete_id.php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require 'config.php';

$correct_password = "admin@123"; // In production, use database password verification
$id_no = $_GET['id'] ?? null;
$message = '';
$message_type = '';
$deleted = false;

// ID validation
if ($id_no) {
    try {
        $stmt = $pdo->prepare("SELECT id_no, photo FROM employee_id_cards WHERE id_no = :id_no");
        $stmt->execute([':id_no' => $id_no]);
        $card = $stmt->fetch();

        if (!$card) {
            header("Location: id_list.php?error=not_found");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $message = 'डेटाबेस त्रुटि: ' . $e->getMessage();
        $message_type = 'error';
    }
} else {
    header("Location: id_list.php?error=missing_id");
    exit();
}

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $card) {
    $entered_password = $_POST['password'] ?? '';
    
    if ($entered_password === $correct_password) {
        try {
            // Delete photo file if exists
            if (!empty($card['photo']) && file_exists($card['photo'])) {
                unlink($card['photo']);
            }
            
            // Delete record from database
            $stmt = $pdo->prepare("DELETE FROM employee_id_cards WHERE id_no = :id_no");
            $stmt->execute([':id_no' => $id_no]);
            
            $deleted = true;
            $message = 'ID कार्ड सफलतापूर्वक हटाया गया';
            $message_type = 'success';
            
        } catch (PDOException $e) {
            error_log("Delete Error: " . $e->getMessage());
            $message = 'डिलीट करने में त्रुटि: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'गलत पासवर्ड! कृपया सही पासवर्ड दर्ज करें';
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID कार्ड डिलीट - Sunny Dhaka</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .delete-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-top: 5px solid #dc3545;
        }
        .success-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            padding: 15px 30px;
            border-radius: 8px;
            animation: fadeInOut 3s ease-in-out forwards;
        }
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translate(-50%, -20px); }
            20% { opacity: 1; transform: translate(-50%, 0); }
            80% { opacity: 1; transform: translate(-50%, 0); }
            100% { opacity: 0; transform: translate(-50%, -20px); }
        }
    </style>
</head>
<body>
    <?php if ($deleted): ?>
    <div class="alert alert-success success-message">
        <i class="fas fa-check-circle mr-2"></i>
        <?= $message ?>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = "id_list.php";
        }, 3000); // 3 seconds delay before redirect
    </script>
    <?php endif; ?>

    <div class="container">
        <div class="delete-container">
            <?php if (!$deleted): ?>
            <div class="text-center mb-4">
                <i class="fas fa-exclamation-triangle text-danger" style="font-size: 50px;"></i>
                <h3 class="text-danger mt-2">ID कार्ड डिलीट करें</h3>
                <p class="text-muted">ID: <?= htmlspecialchars($card['id_no'] ?? 'N/A') ?></p>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <div class="alert alert-warning">
                <strong>चेतावनी!</strong> यह कार्रवाई पूर्ववत नहीं की जा सकती है
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> पासवर्ड</label>
                    <input type="password" class="form-control" id="password" name="password" required 
                           placeholder="अपना पासवर्ड दर्ज करें">
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="id_list.php" class="btn btn-secondary">
                        <i class="fas fa-times mr-2"></i> रद्द करें
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt mr-2"></i> डिलीट करें
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Confirmation dialog
        document.querySelector('form')?.addEventListener('submit', function(e) {
            if (!confirm('क्या आप वाकई इस ID कार्ड को हटाना चाहते हैं?\nयह कार्रवाई पूर्ववत नहीं की जा सकती है')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>