<?php
include('includes/header.php');
?>

<div class="row align-items-center">
    <div class="col-md-6">
        <h1 class="mb-4">Manage Your WhatsApp Group Messages with Ease</h1>
        <p class="lead mb-4">Send customized messages to multiple WhatsApp groups with scheduling, templating, and tracking features.</p>
        <div class="d-grid gap-2 d-md-flex">
            <?php if (is_logged_in()): ?>
                <a href="admin/dashboard.php" class="btn btn-primary btn-lg px-4 me-md-2">Go to Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-lg px-4 me-md-2">Login</a>
                <a href="register.php" class="btn btn-outline-secondary btn-lg px-4">Register</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6 mt-5 mt-md-0 text-center">
        <img src="assets/img/whatsapp-illustration.svg" alt="WhatsApp Messaging" class="img-fluid" onerror="this.src='https://via.placeholder.com/600x400?text=WhatsApp+Messaging'">
    </div>
</div>

<div class="row mt-5">
    <div class="col-md-12 text-center">
        <h2 class="mb-4">Key Features</h2>
    </div>
</div>

<div class="row mb-5">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-people-fill fs-1 text-primary mb-3"></i>
                <h4 class="card-title">Group Management</h4>
                <p class="card-text">Easily organize and manage your WhatsApp groups for targeted messaging.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-file-earmark-text fs-1 text-primary mb-3"></i>
                <h4 class="card-title">Message Templates</h4>
                <p class="card-text">Create and save message templates with text and images for reuse.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-calendar-event fs-1 text-primary mb-3"></i>
                <h4 class="card-title">Scheduling</h4>
                <p class="card-text">Schedule messages to be sent at specific dates and times.</p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-5">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-megaphone fs-1 text-primary mb-3"></i>
                <h4 class="card-title">Promotions & Footers</h4>
                <p class="card-text">Add customizable promotional content and footers to your messages.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-phone fs-1 text-primary mb-3"></i>
                <h4 class="card-title">Multi-Account Support</h4>
                <p class="card-text">Manage multiple WhatsApp numbers from a single dashboard.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-graph-up fs-1 text-primary mb-3"></i>
                <h4 class="card-title">Tracking & Reporting</h4>
                <p class="card-text">Track message delivery status and view detailed reports.</p>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>