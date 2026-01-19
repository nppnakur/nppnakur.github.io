<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Process form data
if($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data
    $emp_id = htmlspecialchars($_POST['emp_id']);
    $emp_name = htmlspecialchars($_POST['emp_name']);
    $father_name = htmlspecialchars($_POST['father_name']);
    $dob = htmlspecialchars($_POST['dob']);
    $mobile = htmlspecialchars($_POST['mobile']);
    $blood_group = htmlspecialchars($_POST['blood_group']);
    $address = htmlspecialchars($_POST['address']);
    $uan = htmlspecialchars($_POST['uan']);
    $esic = htmlspecialchars($_POST['esic']);
    
    // Handle file upload
    $target_dir = "uploads/";
    if(!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $target_file = $target_dir . uniqid() . basename($_FILES["emp_photo"]["name"]);
    move_uploaded_file($_FILES["emp_photo"]["tmp_name"], $target_file);
    
    // Here you would typically save to database
    // For now we'll pass data via session
    $_SESSION['emp_data'] = [
        'emp_id' => $emp_id,
        'emp_name' => $emp_name,
        'father_name' => $father_name,
        'dob' => $dob,
        'mobile' => $mobile,
        'blood_group' => $blood_group,
        'address' => $address,
        'uan' => $uan,
        'esic' => $esic,
        'photo' => $target_file
    ];
    
    header("Location: generate_id_card.php");
    exit();
} else {
    header("Location: id_form.php");
    exit();
}
?>