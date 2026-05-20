<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Set pagination variables
$records_per_page = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Process form submission for adding new template
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_template'])) {
    $whatsapp_number_id = clean_input($_POST["whatsapp_number_id"]);
    $template_name = clean_input($_POST["template_name"]);
    $message_content = $_POST["message_content"];
    $image_url = clean_input($_POST["image_url"]);
    
    // Validation
    $errors = [];
    
    if (empty($whatsapp_number_id)) {
        $errors[] = "WhatsApp account is required";
    }
    
    if (empty($template_name)) {
        $errors[] = "Template name is required";
    }
    
    if (empty($message_content)) {
        $errors[] = "Message content is required";
    }
    
    // If no errors, add template
    if (empty($errors)) {
        // Check if the WhatsApp number belongs to the current user
        $check_query = "SELECT id FROM whatsapp_numbers WHERE id = $whatsapp_number_id AND user_id = " . $_SESSION['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 1) {
            // Escape message content properly for database
            $message_content = mysqli_real_escape_string($conn, $message_content);
            
            $query = "INSERT INTO message_templates (whatsapp_number_id, template_name, message_content, image_url) 
                      VALUES ('$whatsapp_number_id', '$template_name', '$message_content', " . ($image_url ? "'$image_url'" : "NULL") . ")";
            
            if (mysqli_query($conn, $query)) {
                set_flash_message("success", "Message template added successfully!");
                header("Location: message_templates.php");
                exit;
            } else {
                $errors[] = "Error: " . mysqli_error($conn);
            }
        } else {
            $errors[] = "Invalid WhatsApp account";
        }
    }
}

// Process template update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_template'])) {
    $id = clean_input($_POST["id"]);
    $whatsapp_number_id = clean_input($_POST["whatsapp_number_id"]);
    $template_name = clean_input($_POST["template_name"]);
    $message_content = $_POST["message_content"];
    $image_url = clean_input($_POST["image_url"]);
    
    // Validation
    $update_errors = [];
    
    if (empty($whatsapp_number_id)) {
        $update_errors[] = "WhatsApp account is required";
    }
    
    if (empty($template_name)) {
        $update_errors[] = "Template name is required";
    }
    
    if (empty($message_content)) {
        $update_errors[] = "Message content is required";
    }
    
    // If no errors, update template
    if (empty($update_errors)) {
        // Check if the template belongs to a WhatsApp number owned by the current user
        $check_query = "SELECT mt.id 
                        FROM message_templates mt 
                        JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
                        WHERE mt.id = $id AND wn.user_id = " . $_SESSION['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 1) {
            // Escape message content properly for database
            $message_content = mysqli_real_escape_string($conn, $message_content);
            
            $query = "UPDATE message_templates 
                      SET whatsapp_number_id = '$whatsapp_number_id', 
                          template_name = '$template_name', 
                          message_content = '$message_content', 
                          image_url = " . ($image_url ? "'$image_url'" : "NULL") . " 
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                set_flash_message("success", "Message template updated successfully!");
                header("Location: message_templates.php");
                exit;
            } else {
                $update_errors[] = "Error: " . mysqli_error($conn);
            }
        } else {
            $update_errors[] = "You don't have permission to edit this template";
        }
    }
}

// Process template deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_template'])) {
    $id = clean_input($_POST["id"]);
    
    // Check if the template belongs to a WhatsApp number owned by the current user
    $check_query = "SELECT mt.id 
                    FROM message_templates mt 
                    JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
                    WHERE mt.id = $id AND wn.user_id = " . $_SESSION['user_id'];
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 1) {
        $query = "DELETE FROM message_templates WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            set_flash_message("success", "Message template deleted successfully!");
            header("Location: message_templates.php");
            exit;
        } else {
            set_flash_message("danger", "Error: " . mysqli_error($conn));
            header("Location: message_templates.php");
            exit;
        }
    } else {
        set_flash_message("danger", "You don't have permission to delete this template");
        header("Location: message_templates.php");
        exit;
    }
}

// Get all WhatsApp accounts for the current user
$accounts_query = "SELECT id, account_name FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . " AND active = 1 ORDER BY account_name ASC";
$accounts_result = mysqli_query($conn, $accounts_query);

// Get filter parameters
$filter_account = isset($_GET['account']) ? clean_input($_GET['account']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

$count_query = "SELECT COUNT(*) as total 
                FROM message_templates mt 
                JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
                WHERE wn.user_id = " . $_SESSION['user_id'];

if (!empty($filter_account)) {
    $count_query .= " AND mt.whatsapp_number_id = '$filter_account'";
}

if (!empty($search)) {
    $count_query .= " AND (mt.template_name LIKE '%$search%' OR mt.message_content LIKE '%$search%')";
}

$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $records_per_page);

// Base query for templates
$query = "SELECT mt.*, wn.account_name 
          FROM message_templates mt 
          JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
          WHERE wn.user_id = " . $_SESSION['user_id'];

// Apply filters
if (!empty($filter_account)) {
    $query .= " AND mt.whatsapp_number_id = '$filter_account'";
}

if (!empty($search)) {
    $query .= " AND (mt.template_name LIKE '%$search%' OR mt.message_content LIKE '%$search%')";
}

$query .= " ORDER BY wn.account_name ASC, mt.template_name ASC LIMIT $records_per_page OFFSET $offset";
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
                        <a class="nav-link active" href="message_templates.php">
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
        </div>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Message Templates</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal" <?php echo mysqli_num_rows($accounts_result) == 0 ? 'disabled' : ''; ?>>
                    <i class="bi bi-plus-circle"></i> Add New Template
                </button>
            </div>
            
            <?php if (mysqli_num_rows($accounts_result) == 0): ?>
                <div class="alert alert-warning">
                    <p class="mb-0">You need to add a WhatsApp account first before creating templates. <a href="whatsapp_accounts.php" class="alert-link">Add WhatsApp Account</a></p>
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
            
            <?php if (!empty($update_errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($update_errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Filter Templates</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-4">
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
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by template name or content" value="<?php echo $search; ?>">
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
                                        <th>Template Name</th>
                                        <th>Content Preview</th>
                                        <th>Image</th>
                                        <th>WhatsApp Account</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?php echo $row['template_name']; ?></td>
                                            <td>
                                                <?php 
                                                // Show a preview of the message content (first 50 characters)
                                                $preview = $row['message_content'];
                                                echo strlen($preview) > 50 ? substr($preview, 0, 50) . '...' : $preview; 
                                                ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['image_url'])): ?>
                                                    <img src="<?php echo $row['image_url']; ?>" alt="Template Image" class="img-thumbnail" style="max-width: 50px; max-height: 50px;" onerror="this.src='../assets/img/image-placeholder.png';">
                                                <?php else: ?>
                                                    <span class="text-muted">No image</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $row['account_name']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info view-template" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-template-name="<?php echo $row['template_name']; ?>"
                                                        data-message-content="<?php echo htmlspecialchars($row['message_content']); ?>"
                                                        data-image-url="<?php echo $row['image_url']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#viewTemplateModal">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary edit-template" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-whatsapp-number-id="<?php echo $row['whatsapp_number_id']; ?>"
                                                        data-template-name="<?php echo $row['template_name']; ?>"
                                                        data-message-content="<?php echo htmlspecialchars($row['message_content']); ?>"
                                                        data-image-url="<?php echo $row['image_url']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editTemplateModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-template" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-template-name="<?php echo $row['template_name']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#deleteTemplateModal">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <p class="mb-0">No message templates found. Click "Add New Template" to create your first template.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1" aria-labelledby="addTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTemplateModalLabel">Add New Message Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="whatsapp_number_id" class="form-label">WhatsApp Account</label>
                        <select class="form-select" id="whatsapp_number_id" name="whatsapp_number_id" required>
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
                        <label for="template_name" class="form-label">Template Name</label>
                        <input type="text" class="form-control" id="template_name" name="template_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="message_content" class="form-label">Message Content</label>
                        <textarea class="form-control" id="message_content" name="message_content" rows="8" required placeholder="Enter your message here..."></textarea>
                        <div class="form-text mt-2">
                            <strong>Formatting Tips:</strong><br>
                            - Use *asterisks* for <strong>bold text</strong><br>
                            - Use _underscores_ for <em>italic text</em><br>
                            - Use ~tildes~ for <del>strikethrough</del><br>
                            - Use ```three backticks``` for <code>monospace</code><br>
                            - Press Enter for new line
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="image_url" class="form-label">Image URL (Optional)</label>
                        <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg">
                        <div class="form-text">Enter a direct URL to an image (JPG, PNG, etc.) to include with the message.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_template" class="btn btn-primary">Add Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1" aria-labelledby="editTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTemplateModalLabel">Edit Message Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_whatsapp_number_id" class="form-label">WhatsApp Account</label>
                        <select class="form-select" id="edit_whatsapp_number_id" name="whatsapp_number_id" required>
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
                        <label for="edit_template_name" class="form-label">Template Name</label>
                        <input type="text" class="form-control" id="edit_template_name" name="template_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_message_content" class="form-label">Message Content</label>
                        <textarea class="form-control" id="edit_message_content" name="message_content" rows="8" required></textarea>
                        <div class="form-text mt-2">
                            <strong>Formatting Tips:</strong><br>
                            - Use *asterisks* for <strong>bold text</strong><br>
                            - Use _underscores_ for <em>italic text</em><br>
                            - Use ~tildes~ for <del>strikethrough</del><br>
                            - Use ```three backticks``` for <code>monospace</code><br>
                            - Press Enter for new line
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_image_url" class="form-label">Image URL (Optional)</label>
                        <input type="url" class="form-control" id="edit_image_url" name="image_url" placeholder="https://example.com/image.jpg">
                        <div class="form-text">Enter a direct URL to an image (JPG, PNG, etc.) to include with the message.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_template" class="btn btn-primary">Update Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Template Modal -->
<div class="modal fade" id="viewTemplateModal" tabindex="-1" aria-labelledby="viewTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTemplateModalLabel">View Message Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h5 id="view_template_name" class="mb-3"></h5>
                
                <div id="view_image_container" class="text-center mb-3" style="display: none;">
                    <img id="view_image" src="" alt="Template Image" class="img-fluid" style="max-height: 300px;" onerror="this.src='../assets/img/image-placeholder.png';">
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <pre id="view_message_content" class="mb-0" style="white-space: pre-wrap; font-family: inherit;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary edit-from-view" data-bs-toggle="modal" data-bs-target="#editTemplateModal">
                    <i class="bi bi-pencil"></i> Edit Template
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Template Modal -->
<div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-labelledby="deleteTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteTemplateModalLabel">Delete Message Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="delete_id" name="id">
                    <p>Are you sure you want to delete the template: <strong id="delete_template_name"></strong>?</p>
                    <p class="text-danger">This will also delete all scheduled messages using this template!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_template" class="btn btn-danger">Delete Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($total_pages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo !empty($filter_account) ? '&account='.$filter_account : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            
            <?php
            // Calculate range of pages to show
            $range = 2; // Number of pages to show before and after current page
            $start_page = max(1, $page - $range);
            $end_page = min($total_pages, $page + $range);

            // Show first page if not in range
            if ($start_page > 1) {
                echo '<li class="page-item"><a class="page-link" href="?page=1'.(!empty($filter_account) ? '&account='.$filter_account : '').(!empty($search) ? '&search='.$search : '').'">1</a></li>';
                if ($start_page > 2) {
                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                }
            }

            // Show pages in range
            for ($i = $start_page; $i <= $end_page; $i++) {
                echo '<li class="page-item '.($page == $i ? 'active' : '').'"><a class="page-link" href="?page='.$i.(!empty($filter_account) ? '&account='.$filter_account : '').(!empty($search) ? '&search='.$search : '').'">'.$i.'</a></li>';
            }

            // Show last page if not in range
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.(!empty($filter_account) ? '&account='.$filter_account : '').(!empty($search) ? '&search='.$search : '').'">'.$total_pages.'</a></li>';
            }
            ?>
            
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo !empty($filter_account) ? '&account='.$filter_account : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="text-center text-muted small">
        Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> templates
    </div>
<?php endif; ?>

<style>
    .preview-content {
        white-space: pre-wrap;
        font-family: inherit;
        border: 1px solid #ddd;
        border-radius: 0.25rem;
        padding: 10px;
        background-color: #f8f9fa;
        min-height: 100px;
    }
</style>

<script>
    // View template button click
    document.querySelectorAll('.view-template').forEach(function(button) {
        button.addEventListener('click', function() {
            const templateId = this.getAttribute('data-id');
            const templateName = this.getAttribute('data-template-name');
            const messageContent = this.getAttribute('data-message-content');
            const imageUrl = this.getAttribute('data-image-url');
            
            document.getElementById('view_template_name').textContent = templateName;
            const contentDiv = document.getElementById('view_message_content');
            contentDiv.innerHTML = messageContent
                .replace(/\\r\\n|\\n\\r|\\n|\\r/g, '<br>')
                .replace(/\r\n|\n\r|\r|\n/g, '<br>');
            
            // Handle image display
            const imageContainer = document.getElementById('view_image_container');
            const imageElement = document.getElementById('view_image');
            
            if (imageUrl && imageUrl.trim() !== '') {
                imageElement.src = imageUrl;
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }
            
            // Set the template ID for the edit button
            document.querySelector('.edit-from-view').setAttribute('data-id', templateId);
        });
    });
    
    // Edit from view modal
    document.querySelector('.edit-from-view').addEventListener('click', function() {
        const templateId = this.getAttribute('data-id');
        const editButton = document.querySelector(`.edit-template[data-id="${templateId}"]`);
        if (editButton) {
            // Trigger the click event on the edit button
            editButton.click();
        }
        // Close the view modal
        bootstrap.Modal.getInstance(document.getElementById('viewTemplateModal')).hide();
    });
    
    // Edit template button click
    document.querySelectorAll('.edit-template').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_whatsapp_number_id').value = this.getAttribute('data-whatsapp-number-id');
            document.getElementById('edit_template_name').value = this.getAttribute('data-template-name');
            document.getElementById('edit_message_content').value = this.getAttribute('data-message-content');
            document.getElementById('edit_image_url').value = this.getAttribute('data-image-url') || '';
        });
    });
    
    // Delete template button click
    document.querySelectorAll('.delete-template').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('delete_id').value = this.getAttribute('data-id');
            document.getElementById('delete_template_name').textContent = this.getAttribute('data-template-name');
        });
    });
</script>

<?php include('../includes/footer.php'); ?>