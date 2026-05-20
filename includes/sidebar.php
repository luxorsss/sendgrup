<?php
// Dapatkan nama file yang sedang dibuka (misal: 'dashboard.php', 'automations.php')
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'whatsapp_accounts.php') ? 'active' : ''; ?>" href="whatsapp_accounts.php">
                    <i class="bi bi-person-circle"></i> WhatsApp Accounts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'whatsapp_groups.php') ? 'active' : ''; ?>" href="whatsapp_groups.php">
                    <i class="bi bi-people-fill"></i> WhatsApp Groups
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'message_templates.php') ? 'active' : ''; ?>" href="message_templates.php">
                    <i class="bi bi-file-earmark-text"></i> Message Templates
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'automations.php') ? 'active' : ''; ?>" href="automations.php">
                    <i class="bi bi-robot"></i> Automations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'promotions.php') ? 'active' : ''; ?>" href="promotions.php">
                    <i class="bi bi-megaphone"></i> Promotions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'footers.php') ? 'active' : ''; ?>" href="footers.php">
                    <i class="bi bi-list-ul"></i> Footers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'copy_content.php') ? 'active' : ''; ?>" href="copy_content.php">
                    <i class="bi bi-clipboard"></i> Copy Content
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'scheduled_messages.php') ? 'active' : ''; ?>" href="scheduled_messages.php">
                    <i class="bi bi-calendar-event"></i> Scheduled Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'message_history.php') ? 'active' : ''; ?>" href="message_history.php">
                    <i class="bi bi-clock-history"></i> Message History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'instant_message.php') ? 'active' : ''; ?>" href="instant_message.php">
                    <i class="bi bi-send"></i> Instant Message
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'group_settings.php') ? 'active' : ''; ?>" href="group_settings.php">
                    <i class="bi bi-gear"></i> Group Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'api_settings.php') ? 'active' : ''; ?>" href="api_settings.php">
                    <i class="bi bi-key"></i> API Settings
                </a>
            </li>
        </ul>
    </div>
</nav>