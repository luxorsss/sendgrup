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

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-5">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i>Login</h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($username) ? $username : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                
                <div class="mt-3 text-center">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>