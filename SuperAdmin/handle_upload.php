<?php
session_start();
include '../includes/dbcon.php';

if (isset($_POST['upload_profile'])) {
    $userId = $_SESSION['userId'];
    $target_dir = "../static/images/profile_pics/";
    
    // Create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
    $new_filename = "profile_" . $userId . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
        // Update database - using relative path for easier loading
        $db_path = "static/images/profile_pics/" . $new_filename;
        $sql = "UPDATE employees SET profile_pic = '$db_path' WHERE employee_id = '$userId'";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['profile_pic'] = $db_path; // Update session
            header("Location: " . $_SERVER['HTTP_REFERER'] . "?status=success");
        } else {
            header("Location: " . $_SERVER['HTTP_REFERER'] . "?status=error");
        }
    } else {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?status=upload_failed");
    }
}
?>
