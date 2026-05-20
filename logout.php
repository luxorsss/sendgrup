<?php
// Ubah path relatif
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Logout user
logout_user();

// Redirect to login page
header("Location: login.php");
exit;
?>