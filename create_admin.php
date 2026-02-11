<?php
include 'includes/dbcon.php';

$email = 'admin@src.edu.ph';
$password = 'password123';
$firstname = 'Default';
$lastname = 'Super Admin';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if already exists in employees table
$check = mysqli_query($conn, "SELECT employee_id FROM employees WHERE email = '$email'");
if (mysqli_num_rows($check) == 0) {
    // role_id 6 is typically for SuperAdmin/Consultant
    $sql = "INSERT INTO employees (firstname, lastname, email, password, role_id) 
            VALUES ('$firstname', '$lastname', '$email', '$hashed_password', 6)";
    if (mysqli_query($conn, $sql)) {
        echo "Successfully created SuperAdmin account in employees table!\n";
        echo "Login Email: admin@src.edu.ph\n";
        echo "Password: password123\n";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
} else {
    echo "Account with email 'admin@src.edu.ph' already exists.\n";
    echo "Updating password to 'password123'...\n";
    mysqli_query($conn, "UPDATE employees SET password = '$hashed_password', firstname = '$firstname', lastname = '$lastname', role_id = 6 WHERE email = '$email'");
    echo "Done!";
}
?>
