<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Process form submission for updating API settings
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_api'])) {
    $id = clean_input($_POST["id"]);
    $api_key = clean_input($_POST["api_key"]);
    $api_url = clean_input($_POST["api_url"]);
    $webhook_url = !empty($_POST["webhook_url"]) ? clean_input($_POST["webhook_url"]) : null;
    $webhook_token = !empty($_POST["webhook_token"]) ? clean_input($_POST["webhook_token"]) : null;
    
    // Validation
    $errors = [];
    
    if (empty($api_key)) {
        $errors[] = "API Key is required";
    }
    
    if (empty($api_url)) {
        $errors[] = "API URL is required";
    }
    
    // Check if the account belongs to the current user
    $check_query = "SELECT id FROM whatsapp_numbers WHERE id = $id AND user_id = " . $_SESSION['user_id'];
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) != 1) {
        $errors[] = "You don't have permission to update this account";
    }
    
    // If no errors, update the API settings
    if (empty($errors)) {
        $query = "UPDATE whatsapp_numbers 
                  SET api_key = '$api_key', 
                      api_url = '$api_url', 
                      webhook_url = " . ($webhook_url ? "'$webhook_url'" : "NULL") . ", 
                      webhook_token = " . ($webhook_token ? "'$webhook_token'" : "NULL") . "  
                  WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            // Test API connection
            $test_result = test_api_connection($api_url, $api_key);
            
            if ($test_result['success']) {
                set_flash_message("success", "API settings updated successfully! Connection test passed.");
            } else {
                set_flash_message("warning", "API settings updated but connection test failed: " . $test_result['message']);
            }
            
            header("Location: api_settings.php");
            exit;
        } else {
            $errors[] = "Error updating API settings: " . mysqli_error($conn);
        }
    }
}

// Get all WhatsApp accounts for the current user
$query = "SELECT wn.*, COUNT(wg.id) as group_count 
          FROM whatsapp_numbers wn 
          LEFT JOIN whatsapp_groups wg ON wn.id = wg.whatsapp_number_id 
          WHERE wn.user_id = " . $_SESSION['user_id'] . " 
          GROUP BY wn.id 
          ORDER BY wn.account_name ASC";
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
                        <a class="nav-link active" href="api_settings.php">
                            <i class="bi bi-key"></i> API Settings
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">API Settings</h1>
                <a href="whatsapp_accounts.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Manage WhatsApp Accounts
                </a>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <p class="mb-0"><i class="bi bi-info-circle"></i> Configure your OneSender API settings here for each WhatsApp account. Proper configuration is essential for sending messages successfully.</p>
            </div>
            
            <div class="row">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?php echo $row['account_name']; ?></h5>
                                    <span class="badge <?php echo $row['active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $row['active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p><strong>Connected Groups:</strong> <?php echo $row['group_count']; ?></p>
                                    <p><strong>API Key:</strong> 
                                        <span class="text-muted">
                                            <?php echo !empty($row['api_key']) ? substr($row['api_key'], 0, 10) . '...' : 'Not set'; ?>
                                        </span>
                                    </p>
                                    <p><strong>API URL:</strong> 
                                        <span class="text-muted">
                                            <?php echo !empty($row['api_url']) ? $row['api_url'] : 'Not set'; ?>
                                        </span>
                                    </p>
                                    <p><strong>Last Updated:</strong> 
                                        <span class="text-muted">
                                            <?php echo date('d M Y H:i', strtotime($row['updated_at'])); ?>
                                        </span>
                                    </p>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-primary edit-api-btn" 
                                                data-id="<?php echo $row['id']; ?>"
                                                data-account-name="<?php echo $row['account_name']; ?>"
                                                data-api-key="<?php echo $row['api_key']; ?>"
                                                data-api-url="<?php echo $row['api_url']; ?>"
                                                data-webhook-url="<?php echo $row['webhook_url']; ?>"
                                                data-webhook-token="<?php echo $row['webhook_token']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editApiModal">
                                            <i class="bi bi-pencil"></i> Edit API Settings
                                        </button>
                                        <button type="button" class="btn btn-outline-info test-connection-btn"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-api-key="<?php echo $row['api_key']; ?>"
                                                data-api-url="<?php echo $row['api_url']; ?>">
                                            <i class="bi bi-lightning"></i> Test Connection
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <p class="mb-0">No WhatsApp accounts found. <a href="whatsapp_accounts.php" class="alert-link">Add a WhatsApp account</a> first to configure API settings.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Edit API Settings Modal -->
<div class="modal fade" id="editApiModal" tabindex="-1" aria-labelledby="editApiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editApiModalLabel">Edit API Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="api_id" name="id">
                    
                    <div class="mb-3">
                        <p><strong>Account:</strong> <span id="account_name"></span></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="api_key" class="form-label">API Key <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="api_key" name="api_key" required>
                        <div class="form-text">Your OneSender API key for authentication</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="api_url" class="form-label">API URL <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" id="api_url" name="api_url" required>
                        <div class="form-text">The URL of the OneSender API endpoint (e.g., https://example.api-wa.my.id/api/v1/messages)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="webhook_url" class="form-label">Webhook URL (Optional)</label>
                        <input type="url" class="form-control" id="webhook_url" name="webhook_url">
                        <div class="form-text">The URL where OneSender will send webhook events (optional)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="webhook_token" class="form-label">Webhook Token (Optional)</label>
                        <input type="text" class="form-control" id="webhook_token" name="webhook_token">
                        <div class="form-text">Security token for webhook verification (optional)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_api" class="btn btn-primary">Update API Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Test Connection Result Modal -->
<div class="modal fade" id="connectionResultModal" tabindex="-1" aria-labelledby="connectionResultModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="connectionResultModalLabel">Connection Test Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="connection_test_loading" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Testing connection to OneSender API...</p>
                </div>
                
                <div id="connection_test_result" style="display: none;">
                    <div id="connection_test_success" class="alert alert-success" style="display: none;">
                        <i class="bi bi-check-circle-fill me-2"></i> <span id="success_message"></span>
                    </div>
                    <div id="connection_test_error" class="alert alert-danger" style="display: none;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <span id="error_message"></span>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Response Details:</h6>
                        <pre id="response_details" class="bg-light p-3 rounded" style="max-height: 200px; overflow-y: auto;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Edit API button click
    document.querySelectorAll('.edit-api-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('api_id').value = this.getAttribute('data-id');
            document.getElementById('account_name').textContent = this.getAttribute('data-account-name');
            document.getElementById('api_key').value = this.getAttribute('data-api-key');
            document.getElementById('api_url').value = this.getAttribute('data-api-url');
            document.getElementById('webhook_url').value = this.getAttribute('data-webhook-url') || '';
            document.getElementById('webhook_token').value = this.getAttribute('data-webhook-token') || '';
        });
    });
    
    // Test connection button click
    document.querySelectorAll('.test-connection-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const apiKey = this.getAttribute('data-api-key');
            const apiUrl = this.getAttribute('data-api-url');
            
            // Show loading and reset previous results
            document.getElementById('connection_test_loading').style.display = 'block';
            document.getElementById('connection_test_result').style.display = 'none';
            document.getElementById('connection_test_success').style.display = 'none';
            document.getElementById('connection_test_error').style.display = 'none';
            
            // Show modal
            var resultModal = new bootstrap.Modal(document.getElementById('connectionResultModal'));
            resultModal.show();
            
            // Test connection via AJAX
            fetch('test_api_connection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `api_key=${encodeURIComponent(apiKey)}&api_url=${encodeURIComponent(apiUrl)}`
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading
                document.getElementById('connection_test_loading').style.display = 'none';
                document.getElementById('connection_test_result').style.display = 'block';
                
                // Show appropriate message
                if (data.success) {
                    document.getElementById('connection_test_success').style.display = 'block';
                    document.getElementById('success_message').textContent = data.message;
                } else {
                    document.getElementById('connection_test_error').style.display = 'block';
                    document.getElementById('error_message').textContent = data.message;
                }
                
                // Show response details
                document.getElementById('response_details').textContent = JSON.stringify(data.details, null, 2);
            })
            .catch(error => {
                document.getElementById('connection_test_loading').style.display = 'none';
                document.getElementById('connection_test_result').style.display = 'block';
                document.getElementById('connection_test_error').style.display = 'block';
                document.getElementById('error_message').textContent = 'Failed to test connection: ' + error.message;
                document.getElementById('response_details').textContent = 'Error processing request. Please try again.';
            });
        });
    });
</script>

<?php include('../includes/footer.php'); ?>