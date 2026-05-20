<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Process form submission for scheduling a message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['schedule_message'])) {
    $template_id = clean_input($_POST["template_id"]);
    $group_ids = isset($_POST["group_ids"]) ? $_POST["group_ids"] : [];
    $schedule_date = clean_input($_POST["schedule_date"]);
    $schedule_time = clean_input($_POST["schedule_time"]);
    
    // Validation
    $errors = [];
    
    if (empty($template_id)) {
        $errors[] = "Template is required";
    }
    
    if (empty($group_ids)) {
        $errors[] = "At least one group is required";
    }
    
    if (empty($schedule_date)) {
        $errors[] = "Date is required";
    }
    
    if (empty($schedule_time)) {
        $errors[] = "Time is required";
    } else {
        // Ensure time is in HH:MM format
        if (!preg_match("/^([0-1][0-9]|2[0-3]):([0-5][0-9])$/", $schedule_time)) {
            $errors[] = "Time must be in HH:MM format";
            $schedule_time = "00:00"; // Default to midnight
        }
    }
    
    // Check if the scheduled time is in the future
    $scheduled_datetime = strtotime($schedule_date . ' ' . $schedule_time);
    $current_datetime = time();
    
    if ($scheduled_datetime <= $current_datetime) {
        $errors[] = "Scheduled time must be in the future";
    }
    
    // If no errors, schedule the message
    if (empty($errors)) {
        // Check if the template belongs to the current user
        $check_query = "SELECT mt.id
                        FROM message_templates mt 
                        JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
                        WHERE mt.id = $template_id 
                        AND wn.user_id = " . $_SESSION['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert into scheduled_messages
                $query = "INSERT INTO scheduled_messages (template_id, schedule_date, schedule_time, status) 
                          VALUES ('$template_id', '$schedule_date', '$schedule_time', 'pending')";
                
                if (mysqli_query($conn, $query)) {
                    $scheduled_id = mysqli_insert_id($conn);
                    
                    // Add all selected groups
                    foreach ($group_ids as $group_id) {
                        $group_id = clean_input($group_id);
                        
                        // Verify the group belongs to the user
                        $group_check = "SELECT wg.id 
                                        FROM whatsapp_groups wg 
                                        JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id 
                                        WHERE wg.id = $group_id AND wn.user_id = " . $_SESSION['user_id'];
                        $group_result = mysqli_query($conn, $group_check);
                        
                        if (mysqli_num_rows($group_result) > 0) {
                            $insert_group = "INSERT INTO scheduled_message_groups (scheduled_message_id, group_id) 
                                           VALUES ($scheduled_id, $group_id)";
                            mysqli_query($conn, $insert_group);
                        }
                    }
                    
                    // Commit transaction
                    mysqli_commit($conn);
                    
                    set_flash_message("success", "Message scheduled successfully!");
                    header("Location: scheduled_messages.php");
                    exit;
                } else {
                    throw new Exception(mysqli_error($conn));
                }
            } catch (Exception $e) {
                // Rollback on error
                mysqli_rollback($conn);
                $errors[] = "Error: " . $e->getMessage();
            }
        } else {
            $errors[] = "Invalid template selection";
        }
    }
}

// Process cancellation of scheduled message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_schedule'])) {
    $id = clean_input($_POST["id"]);
    
    // Check if the scheduled message belongs to the current user
    $check_query = "SELECT sm.id 
                    FROM scheduled_messages sm 
                    JOIN message_templates mt ON sm.template_id = mt.id 
                    JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
                    WHERE sm.id = $id AND wn.user_id = " . $_SESSION['user_id'] . "
                    AND sm.status = 'pending'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 1) {
        $query = "DELETE FROM scheduled_messages WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            set_flash_message("success", "Scheduled message cancelled successfully!");
            header("Location: scheduled_messages.php");
            exit;
        } else {
            set_flash_message("danger", "Error: " . mysqli_error($conn));
            header("Location: scheduled_messages.php");
            exit;
        }
    } else {
        set_flash_message("danger", "You don't have permission to cancel this message or it has already been sent");
        header("Location: scheduled_messages.php");
        exit;
    }
}

// Process multi-template schedule submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['multi_template_schedule'])) {
    $account_id = clean_input($_POST["account_id"]);
    $group_id = clean_input($_POST["group_id"]);
    $template_ids = $_POST["template_ids"] ?? [];
    $schedule_dates = $_POST["schedule_dates"] ?? [];
    $schedule_times = $_POST["schedule_times"] ?? [];
    
    $errors = [];
    $success_count = 0;
    
    // Validation
    if (empty($account_id)) {
        $errors[] = "WhatsApp account is required";
    }
    
    if (empty($group_id)) {
        $errors[] = "Group selection is required";
    }
    
    if (count($template_ids) === 0) {
        $errors[] = "At least one template is required";
    }
    
    // Verify group belongs to user
    $group_check = "SELECT wg.id 
                    FROM whatsapp_groups wg 
                    JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id 
                    WHERE wg.id = $group_id AND wn.user_id = " . $_SESSION['user_id'];
    $group_result = mysqli_query($conn, $group_check);
    
    if (mysqli_num_rows($group_result) === 0) {
        $errors[] = "Invalid group selection";
    }
    
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        
        try {
            for ($i = 0; $i < count($template_ids); $i++) {
                if (!empty($template_ids[$i]) && !empty($schedule_dates[$i]) && !empty($schedule_times[$i])) {
                    $template_id = clean_input($template_ids[$i]);
                    $schedule_date = clean_input($schedule_dates[$i]);
                    $schedule_time = clean_input($schedule_times[$i]);
                    
                    // Check if template belongs to user
                    $template_check = "SELECT mt.id 
                                      FROM message_templates mt 
                                      JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
                                      WHERE mt.id = $template_id AND wn.user_id = " . $_SESSION['user_id'];
                    $template_result = mysqli_query($conn, $template_check);
                    
                    if (mysqli_num_rows($template_result) > 0) {
                        // Check if scheduled time is in the future
                        $scheduled_datetime = strtotime($schedule_date . ' ' . $schedule_time);
                        $current_datetime = time();
                        
                        if ($scheduled_datetime > $current_datetime) {
                            // Insert into scheduled_messages
                            $query = "INSERT INTO scheduled_messages (template_id, schedule_date, schedule_time, status) 
                                      VALUES ('$template_id', '$schedule_date', '$schedule_time', 'pending')";
                            
                            if (mysqli_query($conn, $query)) {
                                $scheduled_id = mysqli_insert_id($conn);
                                
                                // Link to the group
                                $insert_group = "INSERT INTO scheduled_message_groups (scheduled_message_id, group_id) 
                                               VALUES ($scheduled_id, $group_id)";
                                
                                if (mysqli_query($conn, $insert_group)) {
                                    $success_count++;
                                }
                            }
                        }
                    }
                }
            }
            
            mysqli_commit($conn);
            set_flash_message("success", "Successfully scheduled $success_count templates to the group!");
            header("Location: scheduled_messages.php");
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Error scheduling templates: " . $e->getMessage();
        }
    }
}

// Process bulk deletion of sent messages
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_delete_sent'])) {
    // Check if user confirmed the deletion
    $confirm = isset($_POST['confirm_delete']) ? clean_input($_POST['confirm_delete']) : '';
    
    if ($confirm !== 'DELETE') {
        set_flash_message("danger", "Confirmation text is incorrect. Please type 'DELETE' to confirm.");
        header("Location: scheduled_messages.php");
        exit;
    }
    
    // Get filter parameters to delete only filtered records
    $filter_account = isset($_POST['filter_account']) ? clean_input($_POST['filter_account']) : '';
    $filter_date = isset($_POST['filter_date']) ? clean_input($_POST['filter_date']) : '';
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // First, get all sent message IDs that belong to the user and match filters
        $query = "SELECT sm.id 
                  FROM scheduled_messages sm 
                  JOIN message_templates mt ON sm.template_id = mt.id 
                  JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
                  WHERE wn.user_id = " . $_SESSION['user_id'] . " 
                  AND sm.status = 'sent'";
        
        if (!empty($filter_account)) {
            $query .= " AND wn.id = '$filter_account'";
        }
        
        if (!empty($filter_date)) {
            $query .= " AND sm.schedule_date = '$filter_date'";
        }
        
        $result = mysqli_query($conn, $query);
        $deleted_count = 0;
        
        if (mysqli_num_rows($result) > 0) {
            // Delete from scheduled_message_groups first (foreign key constraint)
            while ($row = mysqli_fetch_assoc($result)) {
                $message_id = $row['id'];
                $delete_groups = "DELETE FROM scheduled_message_groups WHERE scheduled_message_id = $message_id";
                mysqli_query($conn, $delete_groups);
                
                // Then delete from scheduled_messages
                $delete_message = "DELETE FROM scheduled_messages WHERE id = $message_id";
                if (mysqli_query($conn, $delete_message)) {
                    $deleted_count++;
                }
            }
        }
        
        mysqli_commit($conn);
        set_flash_message("success", "Successfully deleted $deleted_count sent messages!");
        header("Location: scheduled_messages.php");
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        set_flash_message("danger", "Error deleting sent messages: " . $e->getMessage());
        header("Location: scheduled_messages.php");
        exit;
    }
}

// Get all WhatsApp accounts for the current user
$accounts_query = "SELECT id, account_name FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . " AND active = 1 ORDER BY account_name ASC";
$accounts_result = mysqli_query($conn, $accounts_query);

// Get filter parameters
$filter_account = isset($_GET['account']) ? clean_input($_GET['account']) : '';
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$filter_date = isset($_GET['date']) ? clean_input($_GET['date']) : '';

// Base query for scheduled messages
$query = "SELECT sm.*, mt.template_name, wn.account_name, wn.id as account_id 
          FROM scheduled_messages sm 
          JOIN message_templates mt ON sm.template_id = mt.id 
          JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
          WHERE wn.user_id = " . $_SESSION['user_id'];

// Apply filters
if (!empty($filter_account)) {
    $query .= " AND wn.id = '$filter_account'";
}

if (!empty($filter_status)) {
    $query .= " AND sm.status = '$filter_status'";
}

if (!empty($filter_date)) {
    $query .= " AND sm.schedule_date = '$filter_date'";
}

$query .= " ORDER BY sm.schedule_date ASC, sm.schedule_time ASC";
$result = mysqli_query($conn, $query);

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="whatsapp_accounts.php">
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
                        <a class="nav-link active" href="group_settings.php">
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
        </div>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
				<h1 class="h2">Scheduled Messages</h1>
				<div>
					<button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal" <?php echo mysqli_num_rows($accounts_result) == 0 ? 'disabled' : ''; ?>>
						<i class="bi bi-trash"></i>
					</button>
					<button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#multiTemplateScheduleModal" <?php echo mysqli_num_rows($accounts_result) == 0 ? 'disabled' : ''; ?>>
						<i class="bi bi-collection-play"></i> Bulk Schedule
					</button>
					<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleMessageModal" <?php echo mysqli_num_rows($accounts_result) == 0 ? 'disabled' : ''; ?>>
						<i class="bi bi-calendar-plus"></i> Single Schedule
					</button>
				</div>
			</div>
            
            <?php if (mysqli_num_rows($accounts_result) == 0): ?>
                <div class="alert alert-warning">
                    <p class="mb-0">You need to add a WhatsApp account first before scheduling messages. <a href="whatsapp_accounts.php" class="alert-link">Add WhatsApp Account</a></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Filter Scheduled Messages</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-3">
                            <label for="account" class="form-label">WhatsApp Account</label>
                            <select class="form-select" id="account" name="account">
                                <option value="">All Accounts</option>
                                <?php
                                mysqli_data_seek($accounts_result, 0);
                                while($account = mysqli_fetch_assoc($accounts_result)): 
                                ?>
                                    <option value="<?php echo $account['id']; ?>" <?php echo $filter_account == $account['id'] ? 'selected' : ''; ?>>
                                        <?php echo $account['account_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="sent" <?php echo $filter_status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo $filter_date; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Template</th>
                                        <th>Groups</th>
                                        <th>WhatsApp Account</th>
                                        <th>Status</th>
                                        <th width="100">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($result)): 
                                        // Get groups for this scheduled message
                                        $groups_query = "SELECT wg.group_name 
                                                        FROM scheduled_message_groups smg 
                                                        JOIN whatsapp_groups wg ON smg.group_id = wg.id 
                                                        WHERE smg.scheduled_message_id = " . $row['id'];
                                        $groups_result = mysqli_query($conn, $groups_query);
                                        
                                        $groups = [];
                                        while ($group = mysqli_fetch_assoc($groups_result)) {
                                            $groups[] = $group['group_name'];
                                        }
                                        $groups_count = count($groups);
                                        $groups_text = implode(", ", array_slice($groups, 0, 2));
                                        if ($groups_count > 2) {
                                            $groups_text .= " and " . ($groups_count - 2) . " more";
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($row['schedule_date'])) . ' ' . date('H:i', strtotime($row['schedule_time'])); ?></td>
                                            <td><?php echo $row['template_name']; ?></td>
                                            <td>
                                                <?php echo $groups_text; ?>
                                                <span class="badge bg-secondary"><?php echo $groups_count; ?></span>
                                            </td>
                                            <td><?php echo $row['account_name']; ?></td>
                                            <td>
                                                <?php if ($row['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($row['status'] == 'sent'): ?>
                                                    <span class="badge bg-success">Sent</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['status'] == 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-danger cancel-schedule" 
                                                            data-id="<?php echo $row['id']; ?>"
                                                            data-template-name="<?php echo $row['template_name']; ?>"
                                                            data-groups-text="<?php echo $groups_text; ?>"
                                                            data-schedule-date="<?php echo date('d M Y', strtotime($row['schedule_date'])); ?>"
                                                            data-schedule-time="<?php echo date('H:i', strtotime($row['schedule_time'])); ?>"
                                                            data-bs-toggle="modal" data-bs-target="#cancelScheduleModal">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <p class="mb-0">No scheduled messages found. Click "Schedule New Message" to schedule your first message.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Schedule Message Modal -->
<div class="modal fade" id="scheduleMessageModal" tabindex="-1" aria-labelledby="scheduleMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleMessageModalLabel">Schedule New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="" id="scheduleForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="account_select" class="form-label">WhatsApp Account</label>
                        <select class="form-select" id="account_select" required>
                            <option value="">Select WhatsApp Account</option>
                            <?php
                            mysqli_data_seek($accounts_result, 0);
                            while($account = mysqli_fetch_assoc($accounts_result)): 
                            ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo $account['account_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="template_id" class="form-label">Message Template</label>
                        <select class="form-select" id="template_id" name="template_id" required disabled>
                            <option value="">Select Account First</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="group_selection" class="form-label">WhatsApp Groups</label>
                        <div id="group_container" class="border rounded p-3 bg-light">
                            <p class="text-center mb-0">Please select a WhatsApp account first</p>
                        </div>
                        <div class="form-text">Select one or more groups to send the message to.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="schedule_date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="schedule_date" name="schedule_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="schedule_time" class="form-label">Time</label>
                            <input type="time" class="form-control" id="schedule_time" name="schedule_time" required>
                            <div class="form-text">The message will be sent at this specific time (24-hour format).</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="schedule_message" class="btn btn-primary">Schedule Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Schedule Modal -->
<div class="modal fade" id="cancelScheduleModal" tabindex="-1" aria-labelledby="cancelScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelScheduleModalLabel">Cancel Scheduled Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="cancel_id" name="id">
                    <p>Are you sure you want to cancel this scheduled message?</p>
                    <div class="card">
                        <div class="card-body">
                            <p><strong>Template:</strong> <span id="cancel_template_name"></span></p>
                            <p><strong>Groups:</strong> <span id="cancel_groups_text"></span></p>
                            <p><strong>Scheduled for:</strong> <span id="cancel_schedule_datetime"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="cancel_schedule" class="btn btn-danger">Cancel Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Multi-Template Schedule Modal -->
<div class="modal fade" id="multiTemplateScheduleModal" tabindex="-1" aria-labelledby="multiTemplateScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="multiTemplateScheduleModalLabel">Schedule Multiple Templates to Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="" id="multiTemplateForm">
                <input type="hidden" name="account_id" id="multi_account_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="multi_account_select" class="form-label">WhatsApp Account</label>
                        <select class="form-select" id="multi_account_select" required>
                            <option value="">Select WhatsApp Account</option>
                            <?php
                            mysqli_data_seek($accounts_result, 0);
                            while($account = mysqli_fetch_assoc($accounts_result)): 
                            ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo $account['account_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="multi_group_select" class="form-label">Select Group</label>
                        <select class="form-select" id="multi_group_select" name="group_id" required disabled>
                            <option value="">Select Account First</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Templates Schedule</label>
                        <div id="templatesContainer">
                            <div class="template-row border p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Template</label>
                                        <select class="form-select template-select" name="template_ids[]" disabled>
                                            <option value="">Select Account First</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Date</label>
                                        <input type="date" class="form-control" name="schedule_dates[]" min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">Time</label>
                                        <input type="time" class="form-control" name="schedule_times[]">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-danger remove-template" style="display: none;">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                        <button type="button" id="addTemplateRow" class="btn btn-sm btn-primary mt-2">
                            <i class="bi bi-plus-circle"></i> Add Another Template
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="multi_template_schedule" class="btn btn-primary">Schedule All Templates</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Delete Sent Messages Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkDeleteModalLabel">Bulk Delete Sent Messages</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="filter_account" value="<?php echo $filter_account; ?>">
                    <input type="hidden" name="filter_date" value="<?php echo $filter_date; ?>">
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will permanently delete all sent messages.
                    </div>
                    
                    <p>This will delete <strong>all sent messages</strong> that match your current filters:</p>
                    
                    <ul>
                        <li>Status: <span class="badge bg-success">Sent</span></li>
                        <?php if (!empty($filter_account)): 
                            $account_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT account_name FROM whatsapp_numbers WHERE id = '$filter_account'"))['account_name'];
                        ?>
                            <li>Account: <?php echo $account_name; ?></li>
                        <?php endif; ?>
                        <?php if (!empty($filter_date)): ?>
                            <li>Date: <?php echo date('d M Y', strtotime($filter_date)); ?></li>
                        <?php endif; ?>
                    </ul>
                    
                    <p>To confirm, please type <strong>DELETE</strong> in the box below:</p>
                    
                    <div class="mb-3">
                        <input type="text" class="form-control" name="confirm_delete" placeholder="Type DELETE here" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="bulk_delete_sent" class="btn btn-danger">Delete All Sent Messages</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Initialize min date for schedule date (today)
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('schedule_date').min = today;
    });
    
    // Load templates and groups based on selected account
    document.getElementById('account_select').addEventListener('change', function() {
        const accountId = this.value;
        const templateSelect = document.getElementById('template_id');
        const groupContainer = document.getElementById('group_container');
        
        // Reset and disable selects if no account is selected
        if (!accountId) {
            templateSelect.innerHTML = '<option value="">Select Account First</option>';
            templateSelect.disabled = true;
            groupContainer.innerHTML = '<p class="text-center mb-0">Please select a WhatsApp account first</p>';
            return;
        }
        
        // Fetch templates for selected account
        fetch(`get_templates.php?account_id=${accountId}`)
            .then(response => response.json())
            .then(data => {
                templateSelect.innerHTML = '<option value="">Select Template</option>';
                data.forEach(template => {
                    const option = document.createElement('option');
                    option.value = template.id;
                    option.textContent = template.template_name;
                    templateSelect.appendChild(option);
                });
                templateSelect.disabled = false;
            })
            .catch(error => {
                console.error('Error fetching templates:', error);
                templateSelect.innerHTML = '<option value="">Error loading templates</option>';
            });
        
        // Fetch groups for selected account
        fetch(`get_groups.php?account_id=${accountId}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    groupContainer.innerHTML = '<p class="text-center mb-0">No groups found for this account. <a href="whatsapp_groups.php">Add Groups</a></p>';
                    return;
                }
                
                // Create checkboxes for each group
                let html = '<div class="row">';
                html += '<div class="col-12 mb-2"><div class="form-check">';
                html += '<input class="form-check-input" type="checkbox" id="select_all_groups">';
                html += '<label class="form-check-label fw-bold" for="select_all_groups">Select All Groups</label>';
                html += '</div></div>';
                
                data.forEach(group => {
                    html += '<div class="col-md-6 mb-2"><div class="form-check">';
                    html += `<input class="form-check-input group-checkbox" type="checkbox" name="group_ids[]" value="${group.id}" id="group_${group.id}">`;
                    html += `<label class="form-check-label" for="group_${group.id}">${group.group_name}</label>`;
                    html += '</div></div>';
                });
                html += '</div>';
                
                groupContainer.innerHTML = html;
                
                // Add select all functionality
                document.getElementById('select_all_groups').addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.group-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            })
            .catch(error => {
                console.error('Error fetching groups:', error);
                groupContainer.innerHTML = '<p class="text-center mb-0 text-danger">Error loading groups. Please try again.</p>';
            });
    });
    
    // Form validation before submit
    document.getElementById('scheduleForm').addEventListener('submit', function(e) {
        const groupCheckboxes = document.querySelectorAll('input[name="group_ids[]"]:checked');
        
        if (groupCheckboxes.length === 0) {
            e.preventDefault();
            alert('Please select at least one group to send the message to.');
        }
    });
    
    // Cancel schedule button click
    document.querySelectorAll('.cancel-schedule').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('cancel_id').value = this.getAttribute('data-id');
            document.getElementById('cancel_template_name').textContent = this.getAttribute('data-template-name');
            document.getElementById('cancel_groups_text').textContent = this.getAttribute('data-groups-text');
            document.getElementById('cancel_schedule_datetime').textContent = 
                this.getAttribute('data-schedule-date') + ' at ' + this.getAttribute('data-schedule-time');
        });
    });
	
	// Load groups based on selected account (Multi-Template Modal)
document.getElementById('multi_account_select').addEventListener('change', function() {
    const accountId = this.value;
    document.getElementById('multi_account_id').value = accountId;
    const groupSelect = document.getElementById('multi_group_select');
    const templateSelects = document.querySelectorAll('.template-select');
    
    if (!accountId) {
        groupSelect.innerHTML = '<option value="">Select Account First</option>';
        groupSelect.disabled = true;
        templateSelects.forEach(select => {
            select.innerHTML = '<option value="">Select Account First</option>';
            select.disabled = true;
        });
        return;
    }
    
    // Fetch groups for selected account
    fetch(`get_groups.php?account_id=${accountId}`)
        .then(response => response.json())
        .then(data => {
            groupSelect.innerHTML = '<option value="">Select Group</option>';
            data.forEach(group => {
                const option = document.createElement('option');
                option.value = group.id;
                option.textContent = group.group_name;
                groupSelect.appendChild(option);
            });
            groupSelect.disabled = false;
        })
        .catch(error => {
            console.error('Error fetching groups:', error);
            groupSelect.innerHTML = '<option value="">Error loading groups</option>';
        });
    
    // Fetch templates for selected account
    fetch(`get_templates.php?account_id=${accountId}`)
        .then(response => response.json())
        .then(data => {
            templateSelects.forEach(select => {
                select.innerHTML = '<option value="">Select Template</option>';
                data.forEach(template => {
                    const option = document.createElement('option');
                    option.value = template.id;
                    option.textContent = template.template_name;
                    select.appendChild(option);
                });
                select.disabled = false;
            });
        })
        .catch(error => {
            console.error('Error fetching templates:', error);
            templateSelects.forEach(select => {
                select.innerHTML = '<option value="">Error loading templates</option>';
            });
        });
});

// Add new template row
document.getElementById('addTemplateRow').addEventListener('click', function() {
    const container = document.getElementById('templatesContainer');
    const accountId = document.getElementById('multi_account_select').value;
    
    if (!accountId) {
        alert('Please select an account first');
        return;
    }
    
    const newRow = document.createElement('div');
    newRow.className = 'template-row border p-3 mb-3';
    newRow.innerHTML = `
        <div class="row">
            <div class="col-md-6 mb-2">
                <label class="form-label">Template</label>
                <select class="form-select template-select" name="template_ids[]" ${accountId ? '' : 'disabled'}>
                    <option value="">Select Template</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="schedule_dates[]" min="${new Date().toISOString().split('T')[0]}">
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Time</label>
                <input type="time" class="form-control" name="schedule_times[]">
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-danger remove-template">
            <i class="bi bi-trash"></i> Remove
        </button>
    `;
    
    // Populate templates if account already selected
    if (accountId) {
        fetch(`get_templates.php?account_id=${accountId}`)
            .then(response => response.json())
            .then(data => {
                const select = newRow.querySelector('.template-select');
                select.innerHTML = '<option value="">Select Template</option>';
                data.forEach(template => {
                    const option = document.createElement('option');
                    option.value = template.id;
                    option.textContent = template.template_name;
                    select.appendChild(option);
                });
                select.disabled = false;
            })
            .catch(error => {
                console.error('Error fetching templates:', error);
                const select = newRow.querySelector('.template-select');
                select.innerHTML = '<option value="">Error loading templates</option>';
            });
    }
    
    container.appendChild(newRow);
    
    // Show remove buttons if there's more than one row
    const removeButtons = document.querySelectorAll('.remove-template');
    if (removeButtons.length > 0) {
        removeButtons.forEach(btn => {
            btn.style.display = 'block';
        });
    }
});

// Remove template row
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-template')) {
        const row = e.target.closest('.template-row');
        const allRows = document.querySelectorAll('.template-row');
        
        if (allRows.length > 1) {
            row.remove();
            
            // Hide remove button if only one row left
            if (document.querySelectorAll('.template-row').length === 1) {
                document.querySelector('.remove-template').style.display = 'none';
            }
        }
    }
});

// Form validation for multi-template form
document.getElementById('multiTemplateForm').addEventListener('submit', function(e) {
    let hasError = false;
    const templateRows = document.querySelectorAll('.template-row');
    const groupSelect = document.getElementById('multi_group_select');
    
    // Validate group selection
    if (!groupSelect.value) {
        hasError = true;
        groupSelect.style.borderColor = 'red';
    } else {
        groupSelect.style.borderColor = '';
    }
    
    // Validate each template row
    templateRows.forEach((row, index) => {
        const templateSelect = row.querySelector('select[name="template_ids[]"]');
        const dateInput = row.querySelector('input[name="schedule_dates[]"]');
        const timeInput = row.querySelector('input[name="schedule_times[]"]');
        
        if (!templateSelect.value || !dateInput.value || !timeInput.value) {
            hasError = true;
            row.style.borderColor = 'red';
        } else {
            row.style.borderColor = '';
            
            // Validate date is not in the past
            const scheduledDatetime = new Date(dateInput.value + 'T' + timeInput.value);
            if (scheduledDatetime < new Date()) {
                hasError = true;
                row.style.borderColor = 'red';
                alert('Scheduled time must be in the future for all templates');
            }
        }
    });
    
    if (hasError) {
        e.preventDefault();
        alert('Please fill in all fields correctly for each template schedule.');
    }
});

// Reset multi-template modal when closed
document.getElementById('multiTemplateScheduleModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('multi_account_select').selectedIndex = 0;
    document.getElementById('multi_group_select').innerHTML = '<option value="">Select Account First</option>';
    document.getElementById('multi_group_select').disabled = true;
    
    // Reset to single template row
    const container = document.getElementById('templatesContainer');
    container.innerHTML = `
        <div class="template-row border p-3 mb-3">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">Template</label>
                    <select class="form-select template-select" name="template_ids[]" disabled>
                        <option value="">Select Account First</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="schedule_dates[]" min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Time</label>
                    <input type="time" class="form-control" name="schedule_times[]">
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-danger remove-template" style="display: none;">
                <i class="bi bi-trash"></i> Remove
            </button>
        </div>
    `;
});
	
	// Bulk Delete Modal - Update message based on filters
	document.getElementById('bulkDeleteModal').addEventListener('show.bs.modal', function () {
		const modal = this;
		const filterAccount = "<?php echo $filter_account; ?>";
		const filterDate = "<?php echo $filter_date; ?>";

		// Fetch count of sent messages that match current filters
		let url = 'get_sent_count.php?status=sent';

		if (filterAccount) {
			url += '&account=' + filterAccount;
		}

		if (filterDate) {
			url += '&date=' + filterDate;
		}

		fetch(url)
			.then(response => response.json())
			.then(data => {
				if (data.count !== undefined) {
					const messageElement = modal.querySelector('.modal-body p:first-child');
					messageElement.innerHTML = `This will delete <strong>${data.count} sent messages</strong> that match your current filters:`;
				}
			})
			.catch(error => {
				console.error('Error fetching sent count:', error);
			});
	});

	// Confirm delete validation
	document.querySelector('form[action=""]').addEventListener('submit', function(e) {
		if (e.submitter && e.submitter.name === 'bulk_delete_sent') {
			const confirmInput = document.querySelector('input[name="confirm_delete"]');
			if (confirmInput.value !== 'DELETE') {
				e.preventDefault();
				alert('Please type DELETE in the confirmation box to proceed.');
				confirmInput.focus();
			}
		}
	});
</script>

<?php include('../includes/footer.php'); ?>