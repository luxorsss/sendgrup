<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/functions.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Sender - OneSender Integration</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        /* --- Import Font Keren dari Google --- */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

/* --- Variabel Warna & Properti Kustom --- */
:root {
    --primary-color: #128C7E;
    --secondary-color: #25D366;
    --dark-color: #075E54;
    --light-color: #DCF8C6;
    --background-color: #f0f2f5; /* Warna latar belakang seperti WhatsApp Web */
    --primary-gradient: linear-gradient(135deg, #25D366, #128C7E);
    --border-radius-lg: 16px; /* Sudut yang lebih tumpul */
    --border-radius-sm: 8px;
    --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.1);
    --transition-speed: 0.3s;
}

/* --- Gaya Dasar Body --- */
body {
    background-color: var(--background-color);
    font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-image: radial-gradient(circle at 10% 20%, rgba(220, 248, 198, 0.3) 0%, rgba(255, 255, 255, 0) 50%);
}

/* --- Navbar dengan Efek Gradien --- */
.navbar {
    background: var(--primary-color);
    box-shadow: var(--shadow-md);
    padding: 0.75rem 0;
}

.navbar-brand {
    font-weight: 700; /* Lebih tebal */
    color: white !important;
    transition: transform var(--transition-speed) ease;
}

.navbar-brand:hover {
    transform: scale(1.05);
}

.nav-link {
    color: rgba(255, 255, 255, 0.85) !important;
    font-weight: 500;
    transition: color var(--transition-speed) ease;
}

.nav-link:hover {
    color: white !important;
}

/* --- Efek Kaca Buram (Glassmorphism) untuk Card --- */
.card {
    border-radius: var(--border-radius-lg);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: var(--shadow-md);
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px); /* Untuk Safari */
    transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
}

.card-header {
    background: var(--primary-gradient);
    color: white;
    border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0 !important;
    font-weight: 600;
    border-bottom: none;
    padding: 1rem 1.5rem;
}

/* --- Sidebar Modern dengan Transisi --- */
.sidebar {
    background-color: #ffffff;
    box-shadow: var(--shadow-sm);
    min-height: calc(100vh - 72px); /* Disesuaikan dengan padding navbar */
}

.sidebar .nav-link {
    color: #555 !important;
    padding: 0.8rem 1.5rem;
    border-radius: var(--border-radius-sm);
    margin: 0.2rem 0.5rem;
    font-weight: 500;
    transition: all var(--transition-speed) ease;
}

.sidebar .nav-link:hover {
    background-color: var(--light-color);
    color: var(--dark-color) !important;
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    background: var(--primary-gradient);
    color: white !important;
    box-shadow: 0 4px 12px rgba(18, 140, 126, 0.3);
}

.sidebar .nav-link i {
    margin-right: 12px;
    width: 20px; /* Agar ikon lebih rapi */
    text-align: center;
}

/* --- Konten Utama & Form --- */
.main-content {
    padding: 30px;
}

.form-label strong {
    color: var(--dark-color);
}

.form-control {
    border-radius: var(--border-radius-sm);
    padding: 12px;
    border: 1px solid #ddd;
    transition: border-color var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(37, 211, 102, 0.2);
}

/* --- Tombol Aksi Utama yang Keren --- */
.btn-brand-send {
    background: var(--primary-gradient);
    color: white;
    border: none;
    font-weight: 600;
    padding: 12px 30px;
    border-radius: 50px; /* Bentuk pil */
    transition: all var(--transition-speed) ease;
    box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
    letter-spacing: 0.5px;
}

.btn-brand-send:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 7px 20px rgba(37, 211, 102, 0.6);
    color: white;
}

.footer {
    background-color: transparent; /* Biar menyatu dengan body */
    padding: 15px 0;
    text-align: center;
    border-top: 1px solid #dee2e6;
}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo is_logged_in() ? 'dashboard.php' : 'index.php'; ?>">
                <i class="bi bi-whatsapp me-2"></i>WhatsApp Sender
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <span class="nav-link">Welcome, <?php echo $_SESSION['username']; ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../logout.php' : 'logout.php'; ?>"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php"><i class="bi bi-person-plus"></i> Register</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php display_flash_message(); ?>