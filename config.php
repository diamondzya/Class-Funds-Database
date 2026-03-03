<?php
// config.php

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$db_host = 'sql202.infinityfree.com';
$db_user = 'if0_41294009';
$db_pass = 'Awtsgege123123';
$db_name = 'if0_41294009_funds';
$db_port = 3306;

// Start session
session_start();

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>