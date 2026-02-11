<?php
include '../includes/dbcon.php';

$response = ['place_of_publication' => ''];

if (isset($_GET['publisher'])) {
    $publisher = mysqli_real_escape_string($conn, $_GET['publisher']);
    $result = mysqli_query($conn, "SELECT place_of_publication FROM lib_publishers WHERE name = '$publisher' LIMIT 1");
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $response['place_of_publication'] = $row['place_of_publication'] ?? '';
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
