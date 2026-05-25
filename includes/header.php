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
        /* --- Import Inter from Google Fonts --- */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');

        /* --- Design Tokens based on DESIGN.md --- */
        :root {
            /* Brand & Primary */
            --colors-primary: #8a2be2; /* Notion Purple */
            --colors-primary-pressed: #7822c9;
            --colors-brand-navy: #0F172A;
            --colors-link-blue: #0A85D1;
            
            /* Surface */
            --colors-canvas: #FFFFFF;
            --colors-surface: #F7F7F5;
            --colors-surface-soft: #F9F9F8;
            --colors-hairline: #E2E2E2;
            --colors-hairline-strong: #D1D1D1;
            
            /* Card Tints */
            --colors-card-tint-peach: #FFF0E5;
            --colors-card-tint-rose: #FFE5E5;
            --colors-card-tint-mint: #E5F2E5;
            --colors-card-tint-lavender: #F0E5FF;
            --colors-card-tint-sky: #E5F0FF;
            --colors-card-tint-yellow: #FFFBE5;
            --colors-card-tint-yellow-bold: #FFE500;
            --colors-card-tint-cream: #F5F2F0;
            
            /* Text */
            --colors-ink-deep: #000000;
            --colors-ink: #37352F;
            --colors-charcoal: #5A5A5A;
            --colors-steel: #7A7A7A;
            --colors-muted: #999999;
            --colors-on-dark: #FFFFFF;
            
            /* Spacing */
            --spacing-xs: 4px;
            --spacing-sm: 8px;
            --spacing-md: 12px;
            --spacing-lg: 16px;
            --spacing-xl: 20px;
            --spacing-xxl: 24px;
            
            /* Shapes */
            --rounded-xs: 4px;
            --rounded-sm: 6px;
            --rounded-md: 8px; /* Buttons, inputs */
            --rounded-lg: 12px; /* Cards */
            --rounded-full: 9999px; /* Badges, pill tabs */
        }

        /* --- Base Typography & Body --- */
        body {
            background-color: var(--colors-canvas);
            color: var(--colors-ink);
            font-family: 'Inter', -apple-system, system-ui, 'Segoe UI', Helvetica, sans-serif;
            font-size: 16px;
            line-height: 1.55;
            background-image: none; /* Remove previous background gradient */
        }

        h1, h2, h3, h4, h5, h6, .navbar-brand {
            color: var(--colors-ink-deep);
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        /* --- Top Navigation --- */
        .navbar {
            background-color: var(--colors-canvas) !important;
            border-bottom: 1px solid var(--colors-hairline);
            box-shadow: none;
            padding: var(--spacing-sm) 0;
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--colors-ink-deep) !important;
            transition: none;
        }

        .navbar-brand:hover {
            transform: none;
        }

        .nav-link {
            color: var(--colors-steel) !important;
            font-weight: 500;
            font-size: 14px;
        }

        .nav-link:hover {
            color: var(--colors-ink) !important;
        }

        /* --- Cards --- */
        .card {
            background-color: var(--colors-canvas);
            border: 1px solid var(--colors-hairline);
            border-radius: var(--rounded-lg);
            box-shadow: none; /* Flat by default */
            transition: none;
            backdrop-filter: none;
        }

        .card:hover {
            transform: none;
            box-shadow: none;
        }

        .card-header {
            background-color: transparent;
            color: var(--colors-ink-deep);
            border-bottom: 1px solid var(--colors-hairline);
            border-radius: var(--rounded-lg) var(--rounded-lg) 0 0 !important;
            font-weight: 600;
            padding: var(--spacing-lg) var(--spacing-xl);
            font-size: 18px;
        }

        /* --- Sidebar Modern --- */
        .sidebar {
            background-color: var(--colors-surface);
            box-shadow: none;
            border-right: 1px solid var(--colors-hairline);
            min-height: calc(100vh - 65px);
        }

        .sidebar .nav-link {
            color: var(--colors-ink) !important;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--rounded-md);
            margin: 0.2rem 0.5rem;
            font-weight: 500;
            font-size: 14px;
            transition: none;
        }

        .sidebar .nav-link:hover {
            background-color: var(--colors-surface-soft);
            color: var(--colors-ink-deep) !important;
            transform: none;
        }

        .sidebar .nav-link.active {
            background-color: var(--colors-hairline);
            color: var(--colors-ink-deep) !important;
            box-shadow: none;
        }

        .sidebar .nav-link i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            color: var(--colors-charcoal);
        }

        /* --- Konten Utama & Form --- */
        .main-content {
            padding: 32px;
        }

        .form-label {
            font-weight: 500;
            color: var(--colors-ink);
            font-size: 14px;
        }
        
        .form-label strong {
            color: var(--colors-ink-deep);
        }

        .form-control, .form-select {
            border-radius: var(--rounded-md);
            padding: 10px 14px;
            border: 1px solid var(--colors-hairline-strong);
            background-color: var(--colors-canvas);
            color: var(--colors-ink);
            font-size: 14px;
            transition: border-color 0.2s ease;
            height: 44px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--colors-primary);
            box-shadow: 0 0 0 2px rgba(138, 43, 226, 0.2);
            outline: none;
        }

        /* --- Tombol Aksi --- */
        .btn {
            border-radius: var(--rounded-md);
            font-weight: 500;
            font-size: 14px;
            padding: 10px 18px;
            letter-spacing: 0;
            transition: background-color 0.2s ease;
            box-shadow: none;
        }
        
        .btn-primary, .btn-brand-send {
            background-color: var(--colors-primary);
            border: none;
            color: var(--colors-on-dark);
        }

        .btn-primary:hover, .btn-brand-send:hover {
            background-color: var(--colors-primary-pressed);
            transform: none;
            box-shadow: none;
            color: var(--colors-on-dark);
        }

        .btn-secondary {
            background-color: transparent;
            border: 1px solid var(--colors-hairline-strong);
            color: var(--colors-ink);
        }

        .btn-secondary:hover {
            background-color: var(--colors-surface);
            color: var(--colors-ink);
            border-color: var(--colors-hairline-strong);
        }

        .btn-outline-primary {
            color: var(--colors-ink);
            border: 1px solid var(--colors-hairline-strong);
        }
        .btn-outline-primary:hover {
            background-color: var(--colors-surface);
            color: var(--colors-ink);
        }

        .footer {
            background-color: var(--colors-canvas);
            padding: 24px 0;
            text-align: center;
            border-top: 1px solid var(--colors-hairline);
            color: var(--colors-steel);
            font-size: 14px;
        }
        
        /* Utility */
        .text-muted {
            color: var(--colors-steel) !important;
        }
        
        .badge {
            border-radius: var(--rounded-full);
            font-weight: 600;
            font-size: 13px;
            padding: 4px 10px;
        }

        /* --- Custom Notion-like Classes --- */
        .hero-band-dark {
            background-color: var(--colors-brand-navy);
            color: var(--colors-on-dark);
            padding: 80px 0;
            margin-top: -24px; /* Pull up into container margin */
            border-radius: var(--rounded-lg);
            margin-bottom: 48px;
        }

        .hero-band-dark h1, .hero-band-dark h2 {
            color: var(--colors-on-dark);
            font-size: 56px;
            letter-spacing: -1px;
            line-height: 1.1;
        }

        .card-feature-peach { background-color: var(--colors-card-tint-peach); }
        .card-feature-rose { background-color: var(--colors-card-tint-rose); }
        .card-feature-mint { background-color: var(--colors-card-tint-mint); }
        .card-feature-sky { background-color: var(--colors-card-tint-sky); }
        .card-feature-lavender { background-color: var(--colors-card-tint-lavender); }
        .card-feature-yellow { background-color: var(--colors-card-tint-yellow); }
        .card-feature-yellow-bold { background-color: var(--colors-card-tint-yellow-bold); color: var(--colors-ink-deep); }
        
        .card-feature {
            padding: var(--spacing-xxl);
            border: none;
        }
        .card-feature h4 {
            font-size: 22px;
            margin-bottom: var(--spacing-md);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
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