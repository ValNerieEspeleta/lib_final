<?php
include '../includes/session.php';
include '../includes/dbcon.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['userId'];

if ($action === 'update_name') {
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);

    $sql = "UPDATE employees SET firstname = '$firstname', lastname = '$lastname' WHERE employee_id = '$userId'";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['firstname'] = $firstname;
        $_SESSION['lastname'] = $lastname;
        $_SESSION['fullname'] = $firstname . ' ' . $lastname;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
} 
elseif ($action === 'update_password') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($new !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit();
    }

    $res = mysqli_query($conn, "SELECT password FROM employees WHERE employee_id = '$userId'");
    $row = mysqli_fetch_assoc($res);

    if (password_verify($current, $row['password']) || $current === $row['password']) {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        if (mysqli_query($conn, "UPDATE employees SET password = '$hashed' WHERE employee_id = '$userId'")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
    }
} 
elseif ($action === 'update_pic') {
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $targetDir = "../static/images/profile_pics/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $fileName = 'user_' . $userId . '_' . time() . '.' . $ext;
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFile)) {
            if (mysqli_query($conn, "UPDATE employees SET profile_pic = '$fileName' WHERE employee_id = '$userId'")) {
                $_SESSION['profile_pic'] = $fileName;
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    }
} 
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>
