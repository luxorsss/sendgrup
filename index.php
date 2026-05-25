<?php
include('includes/header.php');
?>

<div class="hero-band-dark text-center">
    <div class="container">
        <h1 class="mb-4">Manage Your WhatsApp Group Messages with Ease</h1>
        <p class="lead mb-5" style="color: var(--colors-on-dark); opacity: 0.9; max-width: 700px; margin: 0 auto;">Send customized messages to multiple WhatsApp groups with scheduling, templating, and tracking features.</p>
        <div class="d-flex justify-content-center gap-3">
            <?php if (is_logged_in()): ?>
                <a href="admin/dashboard.php" class="btn btn-primary btn-lg px-4">Go to Dashboard</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary btn-lg px-4">Get Started Free</a>
                <a href="login.php" class="btn btn-outline-light btn-lg px-4" style="border: 1px solid rgba(255,255,255,0.3); color: white;">Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container">
    <div class="row mt-5">
        <div class="col-md-12 text-center">
            <h2 class="mb-5" style="font-size: 36px;">Keep work moving 24/7</h2>
        </div>
    </div>

    <div class="row mb-5 g-4">
        <div class="col-md-4">
            <div class="card card-feature card-feature-peach h-100">
                <i class="bi bi-people-fill fs-1 text-primary mb-3" style="color: var(--colors-brand-orange) !important;"></i>
                <h4>Group Management</h4>
                <p class="mb-0 text-muted">Easily organize and manage your WhatsApp groups for targeted messaging.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-feature card-feature-lavender h-100">
                <i class="bi bi-file-earmark-text fs-1 mb-3" style="color: var(--colors-brand-purple-800) !important;"></i>
                <h4>Message Templates</h4>
                <p class="mb-0 text-muted">Create and save message templates with text and images for reuse.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-feature card-feature-mint h-100">
                <i class="bi bi-calendar-event fs-1 mb-3" style="color: var(--colors-brand-green) !important;"></i>
                <h4>Scheduling</h4>
                <p class="mb-0 text-muted">Schedule messages to be sent at specific dates and times.</p>
            </div>
        </div>
    </div>

    <div class="row mb-5 g-4">
        <div class="col-md-4">
            <div class="card card-feature card-feature-yellow h-100">
                <i class="bi bi-megaphone fs-1 mb-3" style="color: #B29B00 !important;"></i>
                <h4>Promotions & Footers</h4>
                <p class="mb-0 text-muted">Add customizable promotional content and footers to your messages.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-feature card-feature-sky h-100">
                <i class="bi bi-phone fs-1 mb-3" style="color: var(--colors-link-blue) !important;"></i>
                <h4>Multi-Account Support</h4>
                <p class="mb-0 text-muted">Manage multiple WhatsApp numbers from a single dashboard.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-feature card-feature-rose h-100">
                <i class="bi bi-graph-up fs-1 mb-3" style="color: #D9465B !important;"></i>
                <h4>Tracking & Reporting</h4>
                <p class="mb-0 text-muted">Track message delivery status and view detailed reports.</p>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>