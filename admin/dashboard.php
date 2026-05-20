<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="whatsapp_accounts.php">
                            <i class="bi bi-person-circle"></i> WhatsApp Accounts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="whatsapp_groups.php">
                            <i class="bi bi-people-fill"></i> WhatsApp Groups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="message_templates.php">
                            <i class="bi bi-file-earmark-text"></i> Message Templates
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="promotions.php">
                            <i class="bi bi-megaphone"></i> Promotions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="footers.php">
                            <i class="bi bi-list-ul"></i> Footers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="copy_content.php">
                            <i class="bi bi-clipboard"></i> Copy Content
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="scheduled_messages.php">
                            <i class="bi bi-calendar-event"></i> Scheduled Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="message_history.php">
                            <i class="bi bi-clock-history"></i> Message History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="instant_message.php">
                            <i class="bi bi-send"></i> Instant Message
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="group_settings.php">
                            <i class="bi bi-gear"></i> Group Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="api_settings.php">
                            <i class="bi bi-key"></i> API Settings
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                        <i class="bi bi-list"></i> Menu
                    </button>
                    <h1 class="h2 mb-0">Dashboard</h1>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-person-circle fs-1 text-primary"></i>
                            <h5 class="card-title mt-3">WhatsApp Accounts</h5>
                            <?php
                            $query = "SELECT COUNT(*) as total FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'];
                            $result = mysqli_query($conn, $query);
                            $row = mysqli_fetch_assoc($result);
                            ?>
                            <p class="card-text fs-4"><?php echo $row['total']; ?></p>
                            <a href="whatsapp_accounts.php" class="btn btn-sm btn-primary">Manage Accounts</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-people-fill fs-1 text-success"></i>
                            <h5 class="card-title mt-3">WhatsApp Groups</h5>
                            <?php
                            $query = "SELECT COUNT(*) as total FROM whatsapp_groups WHERE whatsapp_number_id IN (SELECT id FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . ")";
                            $result = mysqli_query($conn, $query);
                            $row = mysqli_fetch_assoc($result);
                            ?>
                            <p class="card-text fs-4"><?php echo $row['total']; ?></p>
                            <a href="whatsapp_groups.php" class="btn btn-sm btn-success">Manage Groups</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-file-earmark-text fs-1 text-info"></i>
                            <h5 class="card-title mt-3">Message Templates</h5>
                            <?php
                            $query = "SELECT COUNT(*) as total FROM message_templates WHERE whatsapp_number_id IN (SELECT id FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . ")";
                            $result = mysqli_query($conn, $query);
                            $row = mysqli_fetch_assoc($result);
                            ?>
                            <p class="card-text fs-4"><?php echo $row['total']; ?></p>
                            <a href="message_templates.php" class="btn btn-sm btn-info">Manage Templates</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-clock-history fs-1 text-warning"></i>
                            <h5 class="card-title mt-3">Sent Messages</h5>
                            <?php
                            $query = "SELECT COUNT(*) as total FROM message_history WHERE whatsapp_number_id IN (SELECT id FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . ")";
                            $result = mysqli_query($conn, $query);
                            $row = mysqli_fetch_assoc($result);
                            ?>
                            <p class="card-text fs-4"><?php echo $row['total']; ?></p>
                            <a href="message_history.php" class="btn btn-sm btn-warning">View History</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Messages</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Group</th>
                                            <th>Template</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $query = "SELECT mh.sent_at, wg.group_name, mt.template_name, mh.status 
                                                FROM message_history mh
                                                LEFT JOIN whatsapp_groups wg ON mh.group_id = wg.id
                                                LEFT JOIN message_templates mt ON mh.template_id = mt.id
                                                WHERE mh.whatsapp_number_id IN (SELECT id FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . ")
                                                ORDER BY mh.sent_at DESC LIMIT 5";
                                        $result = mysqli_query($conn, $query);
                                        
                                        if (mysqli_num_rows($result) > 0) {
                                            while($row = mysqli_fetch_assoc($result)) {
                                                $status_class = '';
                                                switch($row['status']) {
                                                    case 'sent': $status_class = 'text-info'; break;
                                                    case 'delivered': $status_class = 'text-primary'; break;
                                                    case 'read': $status_class = 'text-success'; break;
                                                    case 'failed': $status_class = 'text-danger'; break;
                                                }
                                                
                                                echo "<tr>
                                                        <td>" . date('d M Y H:i', strtotime($row['sent_at'])) . "</td>
                                                        <td>" . $row['group_name'] . "</td>
                                                        <td>" . ($row['template_name'] ? $row['template_name'] : 'Instant Message') . "</td>
                                                        <td class='{$status_class}'>" . ucfirst($row['status']) . "</td>
                                                    </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4' class='text-center'>No messages sent yet</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Upcoming Scheduled Messages</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Group</th>
                                            <th>Template</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
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
                                            while($row = mysqli_fetch_assoc($result)) {
                                                $status_class = '';
                                                switch($row['status']) {
                                                    case 'pending': $status_class = 'text-warning'; break;
                                                    case 'sent': $status_class = 'text-success'; break;
                                                    case 'failed': $status_class = 'text-danger'; break;
                                                }
                                                
                                                echo "<tr>
                                                        <td>" . date('d M Y', strtotime($row['schedule_date'])) . " " . date('H:i', strtotime($row['schedule_time'])) . "</td>
                                                        <td>" . htmlspecialchars($row['group_name']) . "</td>
                                                        <td>" . htmlspecialchars($row['template_name']) . "</td>
                                                        <td class='{$status_class}'>" . ucfirst($row['status']) . "</td>
                                                    </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4' class='text-center'>No upcoming scheduled messages</td></tr>";
                                        }
                                        mysqli_stmt_close($stmt);
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include('../includes/footer.php'); ?>