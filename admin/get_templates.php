<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Get account ID from request
$account_id = isset($_GET['account_id']) ? clean_input($_GET['account_id']) : 0;

// Validate that the account belongs to the current user
$check_query = "SELECT id FROM whatsapp_numbers WHERE id = $account_id AND user_id = " . $_SESSION['user_id'];
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) != 1) {
    // Return empty array if account doesn't belong to user
    echo json_encode([]);
    exit;
}

// Get templates for the specified account
$query = "SELECT id, template_name FROM message_templates WHERE whatsapp_number_id = $account_id ORDER BY template_name ASC";
$result = mysqli_query($conn, $query);

$templates = [];
while ($row = mysqli_fetch_assoc($result)) {
    $templates[] = [
        'id' => $row['id'],
        'template_name' => $row['template_name']
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($templates);
?>