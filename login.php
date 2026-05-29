<?php
require_once('config/db_connect.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Check if user is already logged in
if (is_logged_in()) {
    header("Location: admin/dashboard.php");
    exit;
}

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = clean_input($_POST["username"]);
    $password = clean_input($_POST["password"]);
    
    // Attempt to login
    if (login_user($username, $password)) {
        header("Location: admin/dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}

include('includes/header.php');
?>

<!-- AUTHENTICATION VIEWPORT (Menutupi tata letak header bawaan) -->
<div class="auth-viewport">
    <style>
        .auth-viewport {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: var(--bg-color, #F4F4F0);
            z-index: 9999;
            display: flex;
            font-family: 'Satoshi', sans-serif;
            overflow: hidden;
        }
        
        /* Left Side: Form (Negative Space & Editorial Typography) */
        .auth-form-side {
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 10%;
            background: var(--surface, #FFFFFF);
            position: relative;
        }
        
        /* Right Side: Faceless Minimalist Visual */
        .auth-visual-side {
            width: 50%;
            background-color: var(--ink, #0A0A0A);
            /* Gambar ruang kosong minimalis/faceless */
            background-image: url('https://images.unsplash.com/photo-1494438639946-1ebd1d20bf85?auto=format&fit=crop&w=1200&q=80');
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .auth-visual-side::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(10, 10, 10, 0.1); /* Subtle dark overlay */
        }
        
        /* Utilitarian Form Elements */
        .auth-title {
            font-family: 'Clash Display', sans-serif;
            font-size: 3.5rem;
            font-weight: 600;
            line-height: 1.1;
            letter-spacing: -0.02em;
            color: var(--ink, #0A0A0A);
            margin-bottom: 0.5rem;
        }
        .auth-subtitle {
            font-family: 'Geist Mono', monospace;
            font-size: 0.85rem;
            color: var(--ink-muted, #555555);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 3rem;
        }
        .form-group-anim {
            opacity: 0;
            transform: translateY(15px);
            animation: slideUpFade 500ms cubic-bezier(0.23, 1, 0.32, 1) forwards;
        }
        .form-group-anim:nth-child(1) { animation-delay: 100ms; }
        .form-group-anim:nth-child(2) { animation-delay: 150ms; }
        .form-group-anim:nth-child(3) { animation-delay: 200ms; }
        
        .auth-input {
            width: 100%;
            border: none;
            border-bottom: 2px solid rgba(10, 10, 10, 0.15);
            padding: 1rem 0;
            font-size: 1.1rem;
            background: transparent;
            font-family: 'Satoshi', sans-serif;
            transition: border-color 200ms ease;
            margin-bottom: 2rem;
            border-radius: 0;
        }
        .auth-input:focus {
            outline: none;
            border-color: var(--ink, #0A0A0A);
        }
        .auth-label {
            font-family: 'Geist Mono', monospace;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ink, #0A0A0A);
            display: block;
            margin-bottom: -0.5rem;
        }
        .auth-btn {
            background-color: var(--accent, #0038FF);
            color: #FFFFFF;
            border: none;
            padding: 1.2rem;
            width: 100%;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            cursor: pointer;
            transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1), background-color 160ms ease;
            margin-top: 1rem;
        }
        .auth-btn:hover {
            background-color: #002BCC; /* Darker accent */
        }
        .auth-btn:active {
            transform: scale(0.97);
        }
        .auth-error {
            background: #FFF1F0;
            border: 1px solid #FFCCC7;
            color: #CF1322;
            padding: 1rem;
            font-family: 'Geist Mono', monospace;
            font-size: 0.85rem;
            margin-bottom: 2rem;
            opacity: 0;
            animation: fadeIn 300ms ease forwards;
        }
        .auth-link {
            color: var(--ink-muted, #555555);
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px solid transparent;
            transition: border-color 200ms ease, color 200ms ease;
        }
        .auth-link:hover {
            color: var(--ink, #0A0A0A);
            border-color: var(--ink, #0A0A0A);
        }

        @keyframes slideUpFade {
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            to { opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 991px) {
            .auth-visual-side { display: none; }
            .auth-form-side { width: 100%; padding: 10% 5%; }
        }
    </style>

    <div class="auth-form-side">
        <div style="max-width: 420px; width: 100%; margin: 0 auto;">
            
            <h1 class="auth-title">Welcome<br>Back.</h1>
            <div class="auth-subtitle">AUTHENTICATION PROTOCOL // SYSTEM ACCESS</div>
            
            <?php if (isset($error)): ?>
                <div class="auth-error">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php display_flash_message(); ?>
            
            <form method="post" action="">
                <div class="form-group-anim">
                    <label for="username" class="auth-label">Username</label>
                    <input type="text" class="auth-input" id="username" name="username" placeholder="Masukkan ID Anda..." value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required autocomplete="off">
                </div>
                
                <div class="form-group-anim">
                    <label for="password" class="auth-label">Password</label>
                    <input type="password" class="auth-input" id="password" name="password" placeholder="••••••••" required>
                </div>
                
                <div class="form-group-anim">
                    <button type="submit" class="auth-btn">AUTHORIZE SESSION &rarr;</button>
                </div>
            </form>
            
            <div class="form-group-anim mt-5 text-center font-mono" style="font-size: 0.85rem;">
                <p style="color: var(--ink-muted, #555555);">Unregistered personnel? <br><a href="register.php" class="auth-link mt-2 d-inline-block">Request Access (Register)</a></p>
            </div>
        </div>
    </div>
    
    <div class="auth-visual-side"></div>
</div>

<?php include('includes/footer.php'); ?>