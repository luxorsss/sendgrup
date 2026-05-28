<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <?php include('../includes/sidebar.php'); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content dashboard-terminal">
            
            <style>
                .dashboard-terminal .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                    gap: 1.5rem;
                    margin-bottom: 3.5rem;
                }
                .dashboard-terminal .stat-box {
                    padding: 1.75rem 1.5rem;
                    border: 1px solid var(--border-color);
                    border-radius: 4px;
                    background: transparent;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    text-decoration: none;
                    transition: background-color 200ms var(--ease-out), transform 200ms var(--ease-out);
                    
                    /* Efek Animasi Muncul Berjenjang */
                    opacity: 0;
                    transform: translateY(12px);
                    animation: fadeInStat 450ms var(--ease-out) forwards;
                }
                .dashboard-terminal .stat-box:hover {
                    background-color: rgba(0, 56, 255, 0.02);
                    border-color: var(--ink);
                }
                .dashboard-terminal .stat-box:active {
                    transform: scale(0.98);
                }
                .dashboard-terminal .stat-label {
                    font-family: 'Geist Mono', monospace;
                    font-size: 0.8rem;
                    font-weight: 500;
                    color: var(--ink-muted);
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .dashboard-terminal .stat-number {
                    font-family: 'Geist Mono', monospace;
                    font-size: 3.5rem; 
                    font-weight: 400;
                    color: var(--ink);
                    line-height: 1;
                    margin-top: 1rem;
                    letter-spacing: -0.03em;
                }
                
                /* Penentuan delay stagger untuk kotak statistik */
                .dashboard-terminal .stat-box:nth-child(1) { animation-delay: 0ms; }
                .dashboard-terminal .stat-box:nth-child(2) { animation-delay: 40ms; }
                .dashboard-terminal .stat-box:nth-child(3) { animation-delay: 80ms; }
                .dashboard-terminal .stat-box:nth-child(4) { animation-delay: 120ms; }

                @keyframes fadeInStat {
                    to { opacity: 1; transform: translateY(0); }
                }

                /* Layout Row-List 50/50 */
                .compact-row-item {
                    display: grid;
                    grid-template-columns: 1fr 1.5fr 1.5fr auto;
                    align-items: center;
                    gap: 1rem;
                    padding: 1rem 0.5rem;
                    border-bottom: 1px solid var(--border-color);
                    transition: background-color 200ms var(--ease-out);
                    
                    opacity: 0;
                    transform: translateY(10px);
                    animation: fadeInRow 400ms var(--ease-out) forwards;
                }
                @media (hover: hover) and (pointer: fine) {
                    .compact-row-item:hover {
                        background-color: rgba(0, 0, 0, 0.02);
                    }
                }
            </style>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-end pt-4 pb-3 mb-4" style="border-bottom: 2px solid var(--ink);">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" style="border-radius: 4px;">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h2 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600; letter-spacing: -0.01em;">Overview</h1>
                        <span class="font-mono text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">SYSTEM DASHBOARD // <?php echo strtoupper(htmlspecialchars($_SESSION['username'])); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid">
                
                <a href="whatsapp_accounts.php" class="stat-box text-decoration-none">
                    <div class="stat-label">
                        <i class="bi bi-person-circle" style="color: var(--accent);"></i> WA Accounts
                    </div>
                    <?php
                    $query_acc = "SELECT COUNT(*) as total FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'];
                    $result_acc = mysqli_query($conn, $query_acc);
                    $row_acc = mysqli_fetch_assoc($result_acc);
                    ?>
                    <div class="stat-number"><?php echo $row_acc['total']; ?></div>
                </a>
                
                <a href="whatsapp_groups.php" class="stat-box text-decoration-none">
                    <div class="stat-label">
                        <i class="bi bi-people-fill" style="color: #2E7D32;"></i> WA Groups
                    </div>
                    <?php
                    $query_grp = "SELECT COUNT(*) as total FROM whatsapp_groups WHERE whatsapp_number_id IN (SELECT id FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . ")";
                    $result_grp = mysqli_query($conn, $query_grp);
                    $row_grp = mysqli_fetch_assoc($result_grp);
                    ?>
                    <div class="stat-number"><?php echo $row_grp['total']; ?></div>
                </a>
                
                <a href="message_templates.php" class="stat-box text-decoration-none">
                    <div class="stat-label">
                        <i class="bi bi-file-earmark-text" style="color: #0A85D1;"></i> Templates
                    </div>
                    <?php
                    $query_tpl = "SELECT COUNT(*) as total FROM message_templates WHERE whatsapp_number_id IN (SELECT id FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . ")";
                    $result_tpl = mysqli_query($conn, $query_tpl);
                    $row_tpl = mysqli_fetch_assoc($result_tpl);
                    ?>
                    <div class="stat-number"><?php echo $row_tpl['total']; ?></div>
                </a>
                
                <a href="message_history.php" class="stat-box text-decoration-none">
                    <div class="stat-label">
                        <i class="bi bi-clock-history" style="color: #E65100;"></i> Sent Messages
                    </div>
                    <?php
                    $query_hist = "SELECT COUNT(*) as total FROM message_history WHERE whatsapp_number_id IN (SELECT id FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . ")";
                    $result_hist = mysqli_query($conn, $query_hist);
                    $row_hist = mysqli_fetch_assoc($result_hist);
                    ?>
                    <div class="stat-number"><?php echo $row_hist['total']; ?></div>
                </a>
                
            </div>
            
            <div class="row mt-4">
                
                <div class="col-lg-6 mb-4">
                    <div style="font-family: 'Geist Mono', monospace; font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 1.25rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                        <i class="bi bi-journal-check me-2"></i> Recent Messages
                    </div>
                    
                    <div class="row-list">
                        <?php
                        $query = "SELECT mh.sent_at, wg.group_name, mt.template_name, mh.status 
                                  FROM message_history mh
                                  LEFT JOIN whatsapp_groups wg ON mh.group_id = wg.id
                                  LEFT JOIN message_templates mt ON mh.template_id = mt.id
                                  WHERE mh.whatsapp_number_id IN (SELECT id FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . ")
                                  ORDER BY mh.sent_at DESC LIMIT 5";
                        $result = mysqli_query($conn, $query);
                        
                        if (mysqli_num_rows($result) > 0) {
                            $delay = 0;
                            while($row = mysqli_fetch_assoc($result)) {
                                // Utilitarian Status Badge Logic
                                $badge_style = '';
                                switch($row['status']) {
                                    case 'sent': case 'delivered': case 'read': 
                                        $badge_style = 'background: #E8F5E9; color: #2E7D32;'; 
                                        break;
                                    case 'failed': 
                                        $badge_style = 'background: #FFEBEE; color: #C62828;'; 
                                        break;
                                    default: 
                                        $badge_style = 'background: #F4F4F0; color: #555555;';
                                }
                                
                                echo "<div class='compact-row-item' style='animation-delay: {$delay}ms'>
                                        <div class='font-mono text-muted' style='font-size: 0.75rem; line-height: 1.2;'>
                                            <div class='fw-bold' style='color: var(--ink);'>" . date('H:i', strtotime($row['sent_at'])) . "</div>
                                            <div>" . date('d M y', strtotime($row['sent_at'])) . "</div>
                                        </div>
                                        <div style='font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" . htmlspecialchars($row['group_name']) . "'>
                                            " . htmlspecialchars($row['group_name']) . "
                                        </div>
                                        <div class='text-muted' style='font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>
                                            " . ($row['template_name'] ? htmlspecialchars($row['template_name']) : 'Instant Msg') . "
                                        </div>
                                        <div class='text-end'>
                                            <span class='badge' style='{$badge_style} border-radius: 2px; font-family: \"Geist Mono\", monospace; font-size: 0.65rem; padding: 4px 6px; letter-spacing: 0.05em;'>
                                                " . strtoupper($row['status']) . "
                                            </span>
                                        </div>
                                      </div>";
                                $delay += 40;
                            }
                        } else {
                            echo "<div class='alert mt-3' style='border: 1px dashed var(--border-color); background: transparent; text-align: center; color: var(--ink-muted); font-family: \"Geist Mono\", monospace; font-size: 0.85rem;'>No messages sent yet.</div>";
                        }
                        ?>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div style="font-family: 'Geist Mono', monospace; font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 1.25rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                        <i class="bi bi-calendar-event me-2"></i> Upcoming Scheduled
                    </div>
                    
                    <div class="row-list">
                        <?php
                        $query = "SELECT sm.id, sm.schedule_date, sm.schedule_time, wg.group_name, mt.template_name, sm.status 
                                  FROM scheduled_messages sm
                                  JOIN message_templates mt ON sm.template_id = mt.id
                                  JOIN scheduled_message_groups smg ON sm.id = smg.scheduled_message_id
                                  JOIN whatsapp_groups wg ON smg.group_id = wg.id
                                  WHERE wg.whatsapp_number_id IN (SELECT id FROM whatsapp_numbers WHERE user_id = ?)
                                  AND sm.status = 'pending'
                                  ORDER BY sm.schedule_date ASC, sm.schedule_time ASC LIMIT 5";
                        
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        
                        if (mysqli_num_rows($result) > 0) {
                            $delay = 0;
                            while($row = mysqli_fetch_assoc($result)) {
                                
                                echo "<div class='compact-row-item' style='animation-delay: {$delay}ms'>
                                        <div class='font-mono text-muted' style='font-size: 0.75rem; line-height: 1.2;'>
                                            <div class='fw-bold' style='color: var(--accent);'>" . date('H:i', strtotime($row['schedule_time'])) . "</div>
                                            <div>" . date('d M y', strtotime($row['schedule_date'])) . "</div>
                                        </div>
                                        <div style='font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;' title='" . htmlspecialchars($row['group_name']) . "'>
                                            " . htmlspecialchars($row['group_name']) . "
                                        </div>
                                        <div class='text-muted' style='font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>
                                            " . htmlspecialchars($row['template_name']) . "
                                        </div>
                                        <div class='text-end'>
                                            <span class='badge' style='background: #FFF9C4; color: #F57F17; border-radius: 2px; font-family: \"Geist Mono\", monospace; font-size: 0.65rem; padding: 4px 6px; letter-spacing: 0.05em;'>
                                                PENDING
                                            </span>
                                        </div>
                                      </div>";
                                $delay += 40;
                            }
                        } else {
                            echo "<div class='alert mt-3' style='border: 1px dashed var(--border-color); background: transparent; text-align: center; color: var(--ink-muted); font-family: \"Geist Mono\", monospace; font-size: 0.85rem;'>No upcoming scheduled messages.</div>";
                        }
                        mysqli_stmt_close($stmt);
                        ?>
                    </div>
                </div>
                
            </div>
        </main>
    </div>
</div>

<?php include('../includes/footer.php'); ?>