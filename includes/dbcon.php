<?php
// Determine if running on Localhost or Live Server
$whitelist = array('127.0.0.1', '::1', 'localhost');

if (in_array($_SERVER['HTTP_HOST'], $whitelist)) {
    // --- LOCALHOST CREDENTIALS (XAMPP) ---
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "srceduph_src_db";
    $port = 3306;
} else {
    // --- LIVE SERVER CREDENTIALS (Tigernethost) ---
    $servername = "localhost";
    $username = "srceduph_src_db";
    $password = "na&S_y2#QI&#";
    $database = "srceduph_src_db";
    $port = 3306;
}

// Create connection
$conn = new mysqli($servername, $username, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fix for "Expression #1 of SELECT list is not in GROUP BY clause" error
// This disables strict grouping mode for the current session
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
?>
