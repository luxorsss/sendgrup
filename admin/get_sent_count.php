<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

check_login();

header('Content-Type: application/json');

if (isset($_GET['status'])) {
    $status = clean_input($_GET['status']);
    $filter_account = isset($_GET['account']) ? clean_input($_GET['account']) : '';
    $filter_date = isset($_GET['date']) ? clean_input($_GET['date']) : '';
    
    $query = "SELECT COUNT(sm.id) as count
              FROM scheduled_messages sm 
              JOIN message_templates mt ON sm.template_id = mt.id 
              JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
              WHERE wn.user_id = " . $_SESSION['user_id'] . " 
              AND sm.status = '$status'";
    
    if (!empty($filter_account)) {
        $query .= " AND wn.id = '$filter_account'";
    }
    
    if (!empty($filter_date)) {
        $query .= " AND sm.schedule_date = '$filter_date'";
    }
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    echo json_encode($row);
}
?>