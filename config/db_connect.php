<?php
// Set timezone untuk PHP
date_default_timezone_set('Asia/Jakarta');

// Database configuration
$host = 'localhost';
$username = 'root'; // Ganti dengan username database Anda
$password = ''; // Ganti dengan password database Anda
$database = 'wegqxcgv_wagrup'; // Ganti dengan nama database Anda

// Create database connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set character set
mysqli_set_charset($conn, "utf8mb4");

// Set timezone untuk sesi MySQL ini
mysqli_query($conn, "SET time_zone = '+07:00'");
?>