<?php
// Dapatkan nama file yang sedang dibuka
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* CSS Khusus Sidebar Utilitarian */
    .sidebar {
        background-color: var(--surface);
        border-right: 1px solid var(--border-color);
        min-height: calc(100vh - 60px);
        padding-top: 1rem;
    }
    .sidebar .nav-item {
        margin-bottom: 2px;
    }
    .sidebar .nav-link {
        color: var(--ink-muted) !important;
        padding: 0.6rem 1.25rem;
        font-family: 'Satoshi', sans-serif;
        font-weight: 500;
        font-size: 0.95rem;
        border-radius: 0; /* Sengaja kotak agar tegas */
        border-left: 3px solid transparent;
        transition: all 150ms var(--ease-out);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .sidebar .nav-link:hover {
        background-color: rgba(0, 0, 0, 0.03);
        color: var(--ink) !important;
    }
    .sidebar .nav-link:active {
        transform: scale(0.98); /* Efek mekanis saat diklik */
    }
    .sidebar .nav-link.active {
        background-color: rgba(0, 56, 255, 0.05);
        color: var(--accent) !important;
        border-left: 3px solid var(--accent); /* Garis biru tajam penanda halaman aktif */
        font-weight: 600;
    }
    .sidebar .nav-link i {
        font-size: 1.1rem;
    }
</style>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky">
        <ul class="nav flex-column">
            <?php
            $menus = [
                'dashboard.php' => ['icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
                'whatsapp_accounts.php' => ['icon' => 'bi-person-circle', 'label' => 'WhatsApp Accounts'],
                'whatsapp_groups.php' => ['icon' => 'bi-people-fill', 'label' => 'WhatsApp Groups'],
                'message_templates.php' => ['icon' => 'bi-file-earmark-text', 'label' => 'Message Templates'],
                'automations.php' => ['icon' => 'bi-robot', 'label' => 'Automations'],
                'promotions.php' => ['icon' => 'bi-megaphone', 'label' => 'Promotions'],
                'footers.php' => ['icon' => 'bi-list-ul', 'label' => 'Footers'],
                'copy_content.php' => ['icon' => 'bi-clipboard', 'label' => 'Copy Content'],
                'scheduled_messages.php' => ['icon' => 'bi-calendar-event', 'label' => 'Scheduled Messages'],
                'message_history.php' => ['icon' => 'bi-clock-history', 'label' => 'Message History'],
                'instant_message.php' => ['icon' => 'bi-send', 'label' => 'Instant Message'],
                'group_settings.php' => ['icon' => 'bi-gear', 'label' => 'Group Settings'],
                'api_settings.php' => ['icon' => 'bi-key', 'label' => 'API Settings']
            ];

            foreach ($menus as $file => $data) {
                $isActive = ($current_page == $file) ? 'active' : '';
                echo '<li class="nav-item">';
                echo '<a class="nav-link ' . $isActive . '" href="' . $file . '">';
                echo '<i class="bi ' . $data['icon'] . '"></i> ' . $data['label'];
                echo '</a></li>';
            }
            ?>
        </ul>
    </div>
</nav>