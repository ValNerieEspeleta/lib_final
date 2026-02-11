<?php
include '../includes/dbcon.php';

header('Content-Type: application/json');

if (isset($_GET['uid'])) {
    $uid = mysqli_real_escape_string($conn, $_GET['uid']);
    $query = mysqli_query($conn, "SELECT * FROM lib_rfid_auth WHERE uid='$uid' AND inuse=0");
    if (mysqli_num_rows($query) > 0) {
        echo json_encode(['status' => 'valid']);
    } else {
        echo json_encode(['status' => 'invalid']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No UID provided']);
}
