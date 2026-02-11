<?php
// Set custom session save path
$sess_path = dirname(__DIR__) . '/sessions';
if (!is_dir($sess_path)) {
    mkdir($sess_path, 0777, true);
}
session_save_path($sess_path);
session_start();

if (!isset($_SESSION['userId'])) {
    header("Location: ../index.php#login");
    exit();
}
// $expiry = 1800 ;//session expiry required after 30 mins
// if (isset($_SESSION['LAST']) && (time() - $_SESSION['LAST'] > $expiry)) {

//     session_unset();
//     session_destroy();
//     echo "<script type = \"text/javascript\">
//           window.location = (\"../index.php\");
//           </script>";

// }
// $_SESSION['LAST'] = time();