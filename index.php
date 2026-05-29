<?php
include('includes/header.php');
?>

<style>
    /* Reset & Override Header Bawaan khusus untuk Landing Page */
    body {
        background-color: var(--bg-color, #F4F4F0);
        color: var(--ink, #0A0A0A);
    }
    
    .landing-hero {
        padding: 8rem 0 5rem 0;
        border-bottom: 2px solid var(--ink);
        position: relative;
        overflow: hidden;
    }
    
    /* Background Pattern: Grid Blueprint */
    .landing-hero::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: 
            linear-gradient(rgba(10,10,10,0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(10,10,10,0.05) 1px, transparent 1px);
        background-size: 40px 40px;
        z-index: -1;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: 'Geist Mono', monospace;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        padding: 6px 12px;
        border: 1px solid var(--ink);
        background: var(--surface, #FFFFFF);
        border-radius: 50px;
        margin-bottom: 2rem;
        text-transform: uppercase;
        animation: fadeInDown 600ms cubic-bezier(0.23, 1, 0.32, 1);
    }

    .hero-title {
        font-family: 'Clash Display', sans-serif;
        font-size: clamp(3rem, 6vw, 5.5rem);
        font-weight: 600;
        line-height: 1.05;
        letter-spacing: -0.02em;
        color: var(--ink);
        margin-bottom: 1.5rem;
        max-width: 900px;
        animation: fadeInDown 600ms cubic-bezier(0.23, 1, 0.32, 1) 100ms both;
        opacity: 0;
    }

    .hero-subtitle {
        font-family: 'Satoshi', sans-serif;
        font-size: 1.25rem;
        line-height: 1.6;
        color: var(--ink-muted, #555555);
        max-width: 600px;
        margin-bottom: 3rem;
        animation: fadeInDown 600ms cubic-bezier(0.23, 1, 0.32, 1) 200ms both;
        opacity: 0;
    }

    .hero-actions {
        display: flex;
        gap: 1rem;
        animation: fadeInDown 600ms cubic-bezier(0.23, 1, 0.32, 1) 300ms both;
        opacity: 0;
    }

    /* Utilitarian Buttons */
    .btn-hero-primary {
        background-color: var(--accent, #0038FF);
        color: #FFFFFF;
        font-family: 'Satoshi', sans-serif;
        font-weight: 600;
        font-size: 1.05rem;
        padding: 1rem 2rem;
        border: none;
        border-radius: 4px;
        text-decoration: none;
        transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1), background-color 160ms ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-hero-primary:hover { background-color: #002BCC; color: #FFFFFF; }
    .btn-hero-primary:active { transform: scale(0.96); }

    .btn-hero-secondary {
        background-color: transparent;
        color: var(--ink);
        font-family: 'Geist Mono', monospace;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 2rem;
        border: 1px solid var(--ink);
        border-radius: 4px;
        text-decoration: none;
        transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1), background-color 160ms ease;
    }
    .btn-hero-secondary:hover { background-color: rgba(10,10,10,0.05); color: var(--ink); }
    .btn-hero-secondary:active { transform: scale(0.96); }

    /* Bento Grid Features */
    .features-section {
        padding: 5rem 0;
    }
    
    .section-label {
        font-family: 'Geist Mono', monospace;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--ink-muted);
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .section-label::after {
        content: '';
        height: 1px;
        flex-grow: 1;
        background-color: var(--border-color, rgba(10,10,10,0.15));
    }

    .bento-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 1rem;
    }

    .feature-cell {
        background: var(--surface, #FFFFFF);
        border: 1px solid var(--border-color, rgba(10,10,10,0.15));
        border-radius: 4px;
        padding: 2rem;
        transition: transform 300ms ease, border-color 300ms ease;
        position: relative;
        overflow: hidden;
    }
    .feature-cell:hover {
        border-color: var(--ink);
        transform: translateY(-4px);
    }
    .feature-cell::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 4px; height: 100%;
        background: var(--ink);
        transform: scaleY(0);
        transform-origin: bottom;
        transition: transform 300ms cubic-bezier(0.23, 1, 0.32, 1);
    }
    .feature-cell:hover::before {
        transform: scaleY(1);
    }

    /* Col-span modifiers for Bento */
    .cell-large { grid-column: span 8; }
    .cell-medium { grid-column: span 4; }
    
    @media (max-width: 991px) {
        .cell-large, .cell-medium { grid-column: span 6; }
    }
    @media (max-width: 767px) {
        .cell-large, .cell-medium { grid-column: span 12; }
    }

    .feature-icon {
        font-size: 2rem;
        margin-bottom: 1.5rem;
        color: var(--accent, #0038FF);
    }
    .feature-title {
        font-family: 'Clash Display', sans-serif;
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
        color: var(--ink);
    }
    .feature-desc {
        font-family: 'Satoshi', sans-serif;
        font-size: 1rem;
        color: var(--ink-muted);
        line-height: 1.6;
        margin: 0;
    }

    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<section class="landing-hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-10">
                <div class="hero-badge">
                    <i class="bi bi-circle-fill text-success" style="font-size: 0.5rem;"></i> System V2.0 Online
                </div>
                <h1 class="hero-title">Manage Your WhatsApp Group Messages with Ease.</h1>
                <p class="hero-subtitle">
                    Send customized messages to multiple WhatsApp groups with scheduling, templating, and tracking features. Built for precision and scale.
                </p>
                <div class="hero-actions">
                    <?php if (is_logged_in()): ?>
                        <a href="admin/dashboard.php" class="btn-hero-primary">
                            Access Dashboard <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="btn-hero-primary">
                            Get Started Free <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                        <a href="login.php" class="btn-hero-secondary">
                            Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="features-section">
    <div class="container">
        <div class="section-label">Core Infrastructure // 24/7 Reliability</div>
        
        <div class="bento-grid">
            <div class="feature-cell cell-large">
                <i class="bi bi-calendar-event feature-icon"></i>
                <h4 class="feature-title">Precision Scheduling</h4>
                <p class="feature-desc">Schedule messages to be sent at specific dates and times. Set it up once, and the system engine handles the delivery while you sleep. Keep your work moving 24/7 without manual intervention.</p>
            </div>

            <div class="feature-cell cell-medium">
                <i class="bi bi-people feature-icon" style="color: var(--ink);"></i>
                <h4 class="feature-title">Group Management</h4>
                <p class="feature-desc">Easily organize and manage your WhatsApp groups for highly targeted mass messaging.</p>
            </div>

            <div class="feature-cell cell-medium">
                <i class="bi bi-file-earmark-text feature-icon" style="color: var(--ink);"></i>
                <h4 class="feature-title">Message Templates</h4>
                <p class="feature-desc">Create and save structural message templates with text and image attachments for rapid reuse.</p>
            </div>

            <div class="feature-cell cell-medium">
                <i class="bi bi-diagram-3 feature-icon" style="color: var(--ink);"></i>
                <h4 class="feature-title">Multi-Account</h4>
                <p class="feature-desc">Manage and route messages through multiple WhatsApp numbers from a single unified dashboard.</p>
            </div>

            <div class="feature-cell cell-medium">
                <i class="bi bi-puzzle feature-icon" style="color: var(--ink);"></i>
                <h4 class="feature-title">Modular Add-ons</h4>
                <p class="feature-desc">Dynamically inject customizable promotional content and footers to your primary messages.</p>
            </div>
        </div>
        
        <div class="mt-4">
            <div class="feature-cell" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                <div>
                    <h4 class="feature-title mb-1">Tracking & Reporting</h4>
                    <p class="feature-desc">Track message delivery status (Sent/Delivered/Failed) and view detailed server logs.</p>
                </div>
                <i class="bi bi-activity" style="font-size: 2.5rem; color: var(--ink-muted);"></i>
            </div>
        </div>
    </div>
</section>

<?php include('includes/footer.php'); ?>