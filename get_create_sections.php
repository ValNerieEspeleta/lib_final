<?php
include 'includes/dbcon.php';
$res = mysqli_query($conn, "SHOW CREATE TABLE sections");
$row = mysqli_fetch_row($res);
file_put_contents('create_sections.txt', $row[1]);
?>
