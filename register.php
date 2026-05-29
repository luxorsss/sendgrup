<?php
require_once('config/db_connect.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Check if user is already logged in
if (is_logged_in()) {
    header("Location: admin/dashboard.php");
    exit;
}

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = clean_input($_POST["username"]);
    $email = clean_input($_POST["email"]);
    $password = clean_input($_POST["password"]);
    $confirm_password = clean_input($_POST["confirm_password"]);
    
    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, register user
    if (empty($errors)) {
        if (register_user($username, $email, $password)) {
            set_flash_message("success", "Registration successful! You can now log in.");
            header("Location: login.php");
            exit;
        } else {
            $errors[] = "Username or email already exists";
        }
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
            flex-direction: row-reverse; /* Membalik tata letak: Form Kanan, Gambar Kiri */
            font-family: 'Satoshi', sans-serif;
            overflow-y: auto; /* Memungkinkan scroll jika layar terlalu pendek untuk register form */
        }
        
        /* Right Side: Form (Negative Space & Editorial Typography) */
        .auth-form-side {
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 5% 10%;
            background: var(--surface, #FFFFFF);
            min-height: 100vh;
        }
        
        /* Left Side: Faceless Minimalist Visual */
        .auth-visual-side {
            width: 50%;
            background-color: var(--ink, #0A0A0A);
            /* Gambar ruang kosong minimalis/faceless (variasi sudut) */
            background-image: url('https://images.unsplash.com/photo-1513694203232-719a280e022f?auto=format&fit=crop&w=1200&q=80');
            background-size: cover;
            background-position: center;
            position: fixed; /* Memastikan gambar tidak ikut ter-scroll */
            top: 0; left: 0; bottom: 0;
        }
        
        /* Utilitarian Form Elements */
        .auth-title {
            font-family: 'Clash Display', sans-serif;
            font-size: 3rem;
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
            margin-bottom: 2.5rem;
        }
        .form-group-anim {
            opacity: 0;
            transform: translateY(15px);
            animation: slideUpFade 500ms cubic-bezier(0.23, 1, 0.32, 1) forwards;
        }
        /* Penundaan animasi untuk setiap grup form */
        .form-group-anim:nth-child(1) { animation-delay: 100ms; }
        .form-group-anim:nth-child(2) { animation-delay: 150ms; }
        .form-group-anim:nth-child(3) { animation-delay: 200ms; }
        .form-group-anim:nth-child(4) { animation-delay: 250ms; }
        .form-group-anim:nth-child(5) { animation-delay: 300ms; }
        
        .auth-input {
            width: 100%;
            border: none;
            border-bottom: 2px solid rgba(10, 10, 10, 0.15);
            padding: 0.85rem 0;
            font-size: 1.05rem;
            background: transparent;
            font-family: 'Satoshi', sans-serif;
            transition: border-color 200ms ease;
            margin-bottom: 1.5rem;
            border-radius: 0;
        }
        .auth-input:focus {
            outline: none;
            border-color: var(--ink, #0A0A0A);
        }
        .auth-label {
            font-family: 'Geist Mono', monospace;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ink, #0A0A0A);
            display: block;
            margin-bottom: -0.25rem;
        }
        .auth-btn {
            background-color: var(--ink, #0A0A0A); /* Menggunakan warna hitam pekat untuk register */
            color: var(--surface, #FFFFFF);
            border: none;
            padding: 1.2rem;
            width: 100%;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            cursor: pointer;
            transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1);
            margin-top: 1.5rem;
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
        .auth-error ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
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
            .auth-viewport { display: block; overflow-y: auto; }
            .auth-visual-side { display: none; }
            .auth-form-side { width: 100%; padding: 10% 5%; min-height: 100vh; }
        }
    </style>

    <div class="auth-form-side">
        <div style="max-width: 420px; width: 100%; margin: 0 auto;">
            
            <h1 class="auth-title">Create<br>Account.</h1>
            <div class="auth-subtitle">INITIALIZE NEW IDENTITY PROVISIONING</div>
            
            <?php if (!empty($errors)): ?>
                <div class="auth-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group-anim">
                    <label for="username" class="auth-label">Username</label>
                    <input type="text" class="auth-input" id="username" name="username" placeholder="Buat ID sistem Anda..." value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required autocomplete="off">
                </div>
                
                <div class="form-group-anim">
                    <label for="email" class="auth-label">Email Address</label>
                    <input type="email" class="auth-input" id="email" name="email" placeholder="nama@domain.com" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required autocomplete="off">
                </div>
                
                <div class="row">
                    <div class="col-md-6 form-group-anim" style="animation-delay: 200ms;">
                        <label for="password" class="auth-label">Password</label>
                        <input type="password" class="auth-input" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="col-md-6 form-group-anim" style="animation-delay: 250ms;">
                        <label for="confirm_password" class="auth-label">Verify Password</label>
                        <input type="password" class="auth-input" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
                    </div>
                </div>
                
                <div class="form-group-anim" style="animation-delay: 300ms;">
                    <button type="submit" class="auth-btn">CREATE SECURE IDENTITY &rarr;</button>
                </div>
            </form>
            
            <div class="form-group-anim mt-4 text-center font-mono" style="font-size: 0.85rem; animation-delay: 350ms;">
                <p style="color: var(--ink-muted, #555555);">Already registered? <br><a href="login.php" class="auth-link mt-2 d-inline-block">Return to Terminal (Login)</a></p>
            </div>
        </div>
    </div>
    
    <div class="auth-visual-side"></div>
</div>

<?php include('includes/footer.php'); ?>