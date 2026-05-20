<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Process form submission for adding new account
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_account'])) {
    $account_name = clean_input($_POST["account_name"]);
    $api_key = clean_input($_POST["api_key"]);
    $api_url = clean_input($_POST["api_url"]);
    
    // Validation
    $errors = [];
    
    if (empty($account_name)) {
        $errors[] = "Account name is required";
    }
    
    if (empty($api_key)) {
        $errors[] = "API Key is required";
    }
    
    if (empty($api_url)) {
        $errors[] = "API URL is required";
    }
    
    // If no errors, add account
    if (empty($errors)) {
        $query = "INSERT INTO whatsapp_numbers (user_id, account_name, api_key, api_url) 
                  VALUES ('" . $_SESSION['user_id'] . "', '$account_name', '$api_key', '$api_url')";
        
        if (mysqli_query($conn, $query)) {
            set_flash_message("success", "WhatsApp account added successfully!");
            header("Location: whatsapp_accounts.php");
            exit;
        } else {
            $errors[] = "Error: " . mysqli_error($conn);
        }
    }
}

// Process account update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_account'])) {
    $id = clean_input($_POST["id"]);
    $account_name = clean_input($_POST["account_name"]);
    $api_key = clean_input($_POST["api_key"]);
    $api_url = clean_input($_POST["api_url"]);
    $active = isset($_POST["active"]) ? 1 : 0;
    
    // Validation
    $update_errors = [];
    
    if (empty($account_name)) {
        $update_errors[] = "Account name is required";
    }
    
    if (empty($api_key)) {
        $update_errors[] = "API Key is required";
    }
    
    if (empty($api_url)) {
        $update_errors[] = "API URL is required";
    }
    
    // If no errors, update account
    if (empty($update_errors)) {
        $query = "UPDATE whatsapp_numbers 
                  SET account_name = '$account_name', 
                      api_key = '$api_key', 
                      api_url = '$api_url', 
                      active = $active 
                  WHERE id = $id AND user_id = " . $_SESSION['user_id'];
        
        if (mysqli_query($conn, $query)) {
            set_flash_message("success", "WhatsApp account updated successfully!");
            header("Location: whatsapp_accounts.php");
            exit;
        } else {
            $update_errors[] = "Error: " . mysqli_error($conn);
        }
    }
}

// Process account deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_account'])) {
    $id = clean_input($_POST["id"]);
    
    // Check if account belongs to user
    $check_query = "SELECT id FROM whatsapp_numbers WHERE id = $id AND user_id = " . $_SESSION['user_id'];
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 1) {
        $query = "DELETE FROM whatsapp_numbers WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            set_flash_message("success", "WhatsApp account deleted successfully!");
            header("Location: whatsapp_accounts.php");
            exit;
        } else {
            set_flash_message("danger", "Error: " . mysqli_error($conn));
            header("Location: whatsapp_accounts.php");
            exit;
        }
    } else {
        set_flash_message("danger", "You don't have permission to delete this account");
        header("Location: whatsapp_accounts.php");
        exit;
    }
}

// Get all accounts for the current user
$query = "SELECT * FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY account_name ASC";
$result = mysqli_query($conn, $query);

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include('../includes/sidebar.php'); ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom gap-3">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h2 mb-0">WhatsApp Accounts</h1>
                </div>
                
                <div class="d-flex gap-2 flex-wrap justify-content-start justify-content-md-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                        <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Add New Account</span>
                    </button>
                </div>
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
            
            <div class="card">
                <div class="card-body">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Account Name</th>
                                        <th>API Key</th>
                                        <th>API URL</th>
                                        <th>Status</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?php echo $row['account_name']; ?></td>
                                            <td><?php echo substr($row['api_key'], 0, 10) . '...'; ?></td>
                                            <td><?php echo $row['api_url']; ?></td>
                                            <td>
                                                <?php if ($row['active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-account" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-account-name="<?php echo $row['account_name']; ?>"
                                                        data-api-key="<?php echo $row['api_key']; ?>"
                                                        data-api-url="<?php echo $row['api_url']; ?>"
                                                        data-active="<?php echo $row['active']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editAccountModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-account" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-account-name="<?php echo $row['account_name']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
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
                            <p class="mb-0">No WhatsApp accounts found. Click "Add New Account" to add your first account.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAccountModalLabel">Add New WhatsApp Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="account_name" class="form-label">Account Name</label>
                        <input type="text" class="form-control" id="account_name" name="account_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="api_key" class="form-label">API Key</label>
                        <input type="text" class="form-control" id="api_key" name="api_key" required>
                    </div>
                    <div class="mb-3">
                        <label for="api_url" class="form-label">API URL</label>
                        <input type="text" class="form-control" id="api_url" name="api_url" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_account" class="btn btn-primary">Add Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAccountModalLabel">Edit WhatsApp Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_account_name" class="form-label">Account Name</label>
                        <input type="text" class="form-control" id="edit_account_name" name="account_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_api_key" class="form-label">API Key</label>
                        <input type="text" class="form-control" id="edit_api_key" name="api_key" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_api_url" class="form-label">API URL</label>
                        <input type="text" class="form-control" id="edit_api_url" name="api_url" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_active" name="active" value="1">
                        <label class="form-check-label" for="edit_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_account" class="btn btn-primary">Update Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAccountModalLabel">Delete WhatsApp Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="delete_id" name="id">
                    <p>Are you sure you want to delete the account: <strong id="delete_account_name"></strong>?</p>
                    <p class="text-danger">This will also delete all groups, templates, and messages associated with this account!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_account" class="btn btn-danger">Delete Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Edit account button click
    document.querySelectorAll('.edit-account').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_account_name').value = this.getAttribute('data-account-name');
            document.getElementById('edit_api_key').value = this.getAttribute('data-api-key');
            document.getElementById('edit_api_url').value = this.getAttribute('data-api-url');
            document.getElementById('edit_active').checked = this.getAttribute('data-active') == '1';
        });
    });
    
    // Delete account button click
    document.querySelectorAll('.delete-account').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('delete_id').value = this.getAttribute('data-id');
            document.getElementById('delete_account_name').textContent = this.getAttribute('data-account-name');
        });
    });
</script>

<?php include('../includes/footer.php'); ?>