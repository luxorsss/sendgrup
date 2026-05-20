<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Get group ID from request
$group_id = isset($_GET['group_id']) ? clean_input($_GET['group_id']) : 0;

// Validate that the group belongs to the current user
$check_query = "SELECT wg.id 
                FROM whatsapp_groups wg 
                JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id 
                WHERE wg.id = $group_id AND wn.user_id = " . $_SESSION['user_id'];
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) != 1) {
    // Return empty array if group doesn't belong to user
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Get promotions for the specified group
$query = "SELECT promotion_id FROM group_promotions 
          WHERE group_id = $group_id 
          ORDER BY display_order ASC";
$result = mysqli_query($conn, $query);

$promotions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $promotions[] = $row['promotion_id'];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($promotions);
?>