<?php
require_once dirname(__FILE__) . '/../config/db_connect.php';
require_once dirname(__FILE__) . '/functions.php';

// Register a new user
function register_user($username, $email, $password) {
    global $conn;
    
    // Check if username or email already exists
    $check_query = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
    $result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($result) > 0) {
        return false;
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $query = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed_password')";
    
    if (mysqli_query($conn, $query)) {
        return true;
    } else {
        return false;
    }
}

// Login user
function login_user($username, $password) {
    global $conn;
    
    $query = "SELECT id, username, password FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
    }
    
    return false;
}

// Logout user
function logout_user() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
}
?>