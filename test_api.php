<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Get parameters
$api_key = isset($_POST['api_key']) ? clean_input($_POST['api_key']) : '';
$api_url = isset($_POST['api_url']) ? clean_input($_POST['api_url']) : '';

// Test the connection
$result = test_api_connection($api_url, $api_key);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($result);
?>