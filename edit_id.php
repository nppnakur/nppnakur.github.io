<?php
// edit_id.php
session_start(); // सेशन शुरू करें, यह फाइल की सबसे पहली लाइन होनी चाहिए

// लॉग इन चेक: अगर यूजर लॉग इन नहीं है, तो index.php पर रीडायरेक्ट करें
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require 'config.php'; // डेटाबेस कनेक्शन के लिए config.php को शामिल करें

$id_no = $_GET['id'] ?? null; // URL से 'id' प्राप्त करें
$card = null; // कार्ड डेटा को इनिशियलाइज़ करें
$message = ''; // संदेशों के लिए वेरिएबल
$message_type = ''; // संदेश के प्रकार (success, error, info)

// यदि सत्र में कोई संदेश है (जैसे कि id_list.php से रीडायरेक्ट होने के बाद) तो उसे प्रदर्शित करें
// यह edit_id.php पर सीधा नहीं होगा, बल्कि तब जब edit_id.php पर ही कोई संदेश सेट हो।
if (isset($_SESSION['status_message'])) {
    $message = $_SESSION['status_message'];
    $message_type = $_SESSION['status_type'] ?? 'info';
    unset($_SESSION['status_message']); // संदेश प्रदर्शित होने के बाद उसे सत्र से हटा दें
    unset($_SESSION['status_type']);
}

// यदि ID मिली है, तो मौजूदा कार्ड डेटा लाएँ
if ($id_no) {
    try {
        $stmt = $pdo->prepare("SELECT id_no, name, father_name, designation, mobile_no, blood_group, address, uan_no, esic_no, photo FROM employee_id_cards WHERE id_no = :id_no");
        $stmt->execute([':id_no' => $id_no]);
        $card = $stmt->fetch();

        if (!$card) {
            // यदि ID कार्ड नहीं मिला, तो त्रुटि संदेश सेट करें और पेज पर ही दिखाएं
            $message = 'माफ करना! संपादित करने के लिए आईडी कार्ड नहीं मिला।';
            $message_type = 'error';
            // **महत्वपूर्ण: यहाँ कोई रीडायरेक्ट नहीं है ताकि लूप से बचा जा सके**
        }

    } catch (PDOException $e) {
        // डेटाबेस से जानकारी प्राप्त करने में त्रुटि
        error_log("Database Error (fetching card in edit_id.php): " . $e->getMessage()); // लॉग में त्रुटि लिखें
        $message = 'डेटाबेस से जानकारी प्राप्त करने में विफल: ' . $e->getMessage();
        $message_type = 'error';
    }
} else {
    // यदि URL में कोई ID प्रदान नहीं की गई है, तो त्रुटि संदेश सेट करें और पेज पर ही दिखाएं
    $message = 'संपादित करने के लिए कोई आईडी प्रदान नहीं की गई।';
    $message_type = 'error';
    // **महत्वपूर्ण: यहाँ कोई रीडायरेक्ट नहीं है ताकि लूप से बचा जा सके**
}

// यदि फॉर्म सबमिट किया गया है (POST रिक्वेस्ट) और कार्ड डेटा उपलब्ध है (यानी शुरुआती चेक पास हो गए हैं)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $card) {
    // फॉर्म फ़ील्ड से डेटा प्राप्त करें और रिक्त स्थान हटाएँ
    $name = trim($_POST['name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $mobile_no = trim($_POST['mobile_no'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $uan_no = trim($_POST['uan_no'] ?? '');
    $esic_no = trim($_POST['esic_no'] ?? '');
    $photo_path = $card['photo']; // डिफ़ॉल्ट रूप से मौजूदा फोटो पथ

    // आवश्यक फ़ील्ड की वैधता जांचें
    if (empty($name) || empty($father_name) || empty($designation)) {
        $message = 'कृपया सभी आवश्यक फ़ील्ड भरें (नाम, पिता का नाम, पद)।';
        $message_type = 'error';
    } else {
        // फोटो अपलोड हैंडलिंग
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/'; // अपलोड डायरेक्टरी

            // सुनिश्चित करें कि अपलोड डायरेक्टरी मौजूद है
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) { // डायरेक्टरी बनाने का प्रयास करें
                    $message = 'फोटो अपलोड फ़ोल्डर बनाने में विफल। कृपया अनुमतियाँ जांचें।';
                    $message_type = 'error';
                    error_log("Failed to create upload directory: " . $upload_dir);
                }
            }

            // यदि डायरेक्टरी बनाने में कोई त्रुटि नहीं हुई है, तो फोटो अपलोड करें
            if ($message_type !== 'error') {
                $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $new_file_name = uniqid('photo_') . '.' . $file_extension; // अद्वितीय फ़ाइल नाम जेनरेट करें
                $upload_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_file)) {
                    $photo_path = $upload_file;
                    // यदि एक नई फोटो सफलतापूर्वक अपलोड की गई है और पुरानी फोटो अलग है, तो पुरानी को हटा दें
                    if (!empty($card['photo']) && $card['photo'] !== $photo_path && file_exists($card['photo']) && strpos($card['photo'], $upload_dir) === 0) {
                         unlink($card['photo']); // पुरानी फोटो फ़ाइल हटाएँ
                    }
                } else {
                    $message = 'फोटो अपलोड करने में विफल। फ़ाइल अनुमतियाँ या आकार जांचें।';
                    $message_type = 'error';
                    error_log("Failed to move uploaded file in edit_id.php: " . $_FILES['photo']['tmp_name'] . " to " . $upload_file . " Error code: " . $_FILES['photo']['error']);
                }
            }
        } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // अपलोड में अन्य विशिष्ट त्रुटियों को हैंडल करें
            switch ($_FILES['photo']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE: $message = 'अपलोड की गई फ़ाइल बहुत बड़ी है।'; break;
                case UPLOAD_ERR_PARTIAL: $message = 'फ़ाइल आंशिक रूप से अपलोड की गई थी।'; break;
                case UPLOAD_ERR_CANT_WRITE: $message = 'डिस्क पर फ़ाइल लिखने में विफल।'; break;
                case UPLOAD_ERR_NO_TMP_DIR: $message = 'एक अस्थायी फ़ोल्डर गुम है।'; break;
                case UPLOAD_ERR_EXTENSION: $message = 'एक PHP एक्सटेंशन ने फ़ाइल अपलोड करना बंद कर दिया।'; break;
                default: $message = 'एक अज्ञात फोटो अपलोड त्रुटि हुई।'; break;
            }
            $message_type = 'error';
            error_log("Photo upload error in edit_id.php (Code: " . $_FILES['photo']['error'] . "): " . $message);
        }

        // यदि कोई पिछली त्रुटि नहीं है (जैसे फोटो अपलोड विफल नहीं हुआ), तो डेटाबेस अपडेट के साथ आगे बढ़ें
        if ($message_type !== 'error') {
            try {
                $stmt = $pdo->prepare("UPDATE employee_id_cards SET name = :name, father_name = :father_name, designation = :designation, mobile_no = :mobile_no, blood_group = :blood_group, address = :address, uan_no = :uan_no, esic_no = :esic_no, photo = :photo WHERE id_no = :id_no");
                $stmt->execute([
                    ':name' => $name,
                    ':father_name' => $father_name,
                    ':designation' => $designation,
                    ':mobile_no' => $mobile_no,
                    ':blood_group' => $blood_group,
                    ':address' => $address,
                    ':uan_no' => $uan_no,
                    ':esic_no' => $esic_no,
                    ':photo' => $photo_path,
                    ':id_no' => $id_no
                ]);

                // यदि अपडेट सफल होता है, तो id_list.php पर सफलता संदेश के साथ रीडायरेक्ट करें
                header("Location: id_list.php?status=success");
                exit(); // सुनिश्चित करें कि रीडायरेक्ट के बाद कोई और कोड निष्पादित न हो

            } catch (PDOException $e) {
                // डेटाबेस अपडेट में त्रुटि
                error_log("Database Error (updating card in edit_id.php): " . $e->getMessage());
                $message = 'जानकारी अपडेट करने में विफल: ' . $e->getMessage(); // त्रुटि संदेश को मानवीय रूप में सेट करें
                $message_type = 'error';
                // यहाँ रीडायरेक्ट न करें। पेज को त्रुटि संदेश के साथ रेंडर होने दें।
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID कार्ड एडिट करें - Sunny Dhaka</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS वही रहेगा जो आपने पहले दिया था */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            min-height: 100vh;
        }
        .container {
            margin-top: 40px;
            margin-bottom: 40px;
            max-width: 800px;
        }
        .card-form {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background: white;
        }
        .card-header {
            background: linear-gradient(135deg, #4a6fdc, #3a56b0);
            color: white;
            padding: 20px;
            border-bottom: none;
            position: relative;
        }
        .card-header h2 {
            margin: 0;
            font-weight: 600;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        .card-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, rgba(255,255,255,0.3), transparent);
        }
        .card-body {
            padding: 30px;
        }
        .form-group label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }
        .form-control:focus {
            border-color: #4a6fdc;
            box-shadow: 0 0 0 0.2rem rgba(74, 111, 220, 0.25);
        }
        .current-photo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .current-photo {
            max-width: 180px;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border: 3px solid white;
            transition: all 0.3s;
        }
        .current-photo:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .photo-upload {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .photo-upload:hover {
            border-color: #4a6fdc;
            background-color: #f0f5ff;
        }
        .photo-upload i {
            font-size: 40px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .btn {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4a6fdc, #3a56b0);
            box-shadow: 0 4px 6px rgba(74, 111, 220, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(74, 111, 220, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            box-shadow: 0 4px 6px rgba(108, 117, 125, 0.3);
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(108, 117, 125, 0.4);
        }
        .alert-message {
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid;
            font-weight: 500;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child {
            border-bottom: none;
        }
        .form-section-title {
            color: #4a6fdc;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .form-section-title i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card card-form">
            <div class="card-header">
                <h2><i class="fas fa-id-card mr-2"></i>ID कार्ड एडिट करें (ID: <?php echo htmlspecialchars($id_no ?? 'N/A'); ?>)</h2>
            </div>
            
            <div class="card-body">
                <?php if ($message): // संदेश (सफलता या त्रुटि) यहाँ प्रदर्शित होगा ?>
                    <div class="alert-message alert-<?php echo htmlspecialchars($message_type); ?>">
                        <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($card): // यदि कार्ड डेटा उपलब्ध है तो फॉर्म प्रदर्शित करें ?>
                <form action="edit_id.php?id=<?php echo htmlspecialchars($card['id_no']); ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i>व्यक्तिगत जानकारी
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">नाम:</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($card['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="father_name">पिता का नाम:</label>
                                    <input type="text" class="form-control" id="father_name" name="father_name" value="<?php echo htmlspecialchars($card['father_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="designation">पद:</label>
                                    <input type="text" class="form-control" id="designation" name="designation" value="<?php echo htmlspecialchars($card['designation']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mobile_no">मोबाइल नंबर:</label>
                                    <input type="text" class="form-control" id="mobile_no" name="mobile_no" value="<?php echo htmlspecialchars($card['mobile_no']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-info-circle"></i>अतिरिक्त जानकारी
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="blood_group">ब्लड ग्रुप:</label>
                                    <input type="text" class="form-control" id="blood_group" name="blood_group" value="<?php echo htmlspecialchars($card['blood_group']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="uan_no">UAN नंबर:</label>
                                    <input type="text" class="form-control" id="uan_no" name="uan_no" value="<?php echo htmlspecialchars($card['uan_no']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="esic_no">ESIC नंबर:</label>
                            <input type="text" class="form-control" id="esic_no" name="esic_no" value="<?php echo htmlspecialchars($card['esic_no']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="address">पता:</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($card['address']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-camera"></i>फोटो
                        </div>
                        <?php if (!empty($card['photo'])): ?>
                            <div class="current-photo-container">
                                <img src="<?php echo htmlspecialchars($card['photo']); ?>" alt="वर्तमान फोटो" class="current-photo">
                                <p class="mt-2 text-muted">वर्तमान फोटो</p>
                            </div>
                        <?php endif; ?>
                        <label for="photo">नई फोटो अपलोड करें:</label>
                        <div class="photo-upload" onclick="document.getElementById('photo').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>फोटो अपलोड करने के लिए क्लिक करें या फाइल को यहां ड्रॉप करें</p>
                            <input type="file" class="d-none" id="photo" name="photo" accept="image/*">
                        </div>
                        <small class="form-text text-muted">अनुमत फॉर्मेट: JPG, PNG. अधिकतम आकार: 2MB</small>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="id_list.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i>वापस सूची पर
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i>अपडेट करें
                        </button>
                    </div>
                </form>
                <?php else: // यदि कार्ड डेटा उपलब्ध नहीं है (जैसे अमान्य ID) ?>
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h4>ID कार्ड डेटा उपलब्ध नहीं है या अमान्य ID</h4>
                        <div class="mt-3">
                            <a href="id_list.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>वापस सूची पर
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // फोटो इनपुट में फ़ाइल का नाम दिखाएँ
        document.getElementById('photo').addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                const uploadArea = this.closest('.photo-upload');
                const infoText = uploadArea.querySelector('p');
                infoText.textContent = 'चयनित फाइल: ' + fileName;
                uploadArea.style.borderColor = '#4a6fdc';
                uploadArea.style.backgroundColor = '#e6f0ff';
            } else { // अगर कोई फाइल नहीं चुनी गई है तो टेक्स्ट रीसेट करें
                 const uploadArea = this.closest('.photo-upload');
                 const infoText = uploadArea.querySelector('p');
                 infoText.textContent = 'फोटो अपलोड करने के लिए क्लिक करें या फाइल को यहां ड्रॉप करें';
                 uploadArea.style.borderColor = '#ced4da';
                 uploadArea.style.backgroundColor = '#f8f9fa';
            }
        });

        // ड्रैग एंड ड्रॉप कार्यक्षमता
        const uploadArea = document.querySelector('.photo-upload');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            uploadArea.style.borderColor = '#4a6fdc';
            uploadArea.style.backgroundColor = '#e6f0ff';
        }

        function unhighlight() {
            uploadArea.style.borderColor = '#ced4da';
            uploadArea.style.backgroundColor = '#f8f9fa';
        }

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('photo').files = files; // फ़ाइलों को इनपुट में असाइन करें
            
            if (files.length > 0) {
                const fileName = files[0].name;
                const infoText = uploadArea.querySelector('p');
                infoText.textContent = 'चयनित फाइल: ' + fileName;
            }
        }
    </script>
</body>
</html>