<?php
include 'includes/dbcon.php';

// Delete existing library roles
$delete_sql = "DELETE FROM roles WHERE role_id IN (6, 7, 8, 9)";
if (mysqli_query($conn, $delete_sql)) {
    echo "Deleted existing roles.<br>";
} else {
    echo "Error deleting roles: " . mysqli_error($conn) . "<br>";
}

// Insert new library roles
$insert_sql = "INSERT INTO roles (role_id, role_name) VALUES 
               (6, 'Library Consultant'),
               (7, 'Library Assistant'),
               (8, 'Library Staff'),
               (9, 'Library Technician')";

if (mysqli_query($conn, $insert_sql)) {
    echo "✓ Successfully added all 4 library staff roles:<br>";
    echo "- Role 6: Library Consultant<br>";
    echo "- Role 7: Library Assistant<br>";
    echo "- Role 8: Library Staff<br>";
    echo "- Role 9: Library Technician<br><br>";
    
    // Verify the roles were inserted
    $verify_sql = "SELECT * FROM roles WHERE role_id IN (6, 7, 8, 9) ORDER BY role_id";
    $result = mysqli_query($conn, $verify_sql);
    
    echo "<strong>Verification:</strong><br>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Role ID</th><th>Role Name</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr><td>" . $row['role_id'] . "</td><td>" . $row['role_name'] . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error inserting roles: " . mysqli_error($conn);
}

mysqli_close($conn);
?>
