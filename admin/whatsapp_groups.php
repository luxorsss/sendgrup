<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Process form submission for adding new group
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_group'])) {
    $whatsapp_number_id = clean_input($_POST["whatsapp_number_id"]);
    $group_name = clean_input($_POST["group_name"]);
    $group_wa_id = clean_input($_POST["group_wa_id"]);
    
    // Validation
    $errors = [];
    
    if (empty($whatsapp_number_id)) {
        $errors[] = "WhatsApp account is required";
    }
    
    if (empty($group_name)) {
        $errors[] = "Group name is required";
    }
    
    if (empty($group_wa_id)) {
        $errors[] = "Group ID is required";
    }
    
    // If no errors, add group
    if (empty($errors)) {
        // Check if the WhatsApp number belongs to the current user
        $check_query = "SELECT id FROM whatsapp_numbers WHERE id = $whatsapp_number_id AND user_id = " . $_SESSION['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 1) {
            $query = "INSERT INTO whatsapp_groups (whatsapp_number_id, group_name, group_wa_id) 
                      VALUES ('$whatsapp_number_id', '$group_name', '$group_wa_id')";
            
            if (mysqli_query($conn, $query)) {
                set_flash_message("success", "WhatsApp group added successfully!");
                header("Location: whatsapp_groups.php");
                exit;
            } else {
                $errors[] = "Error: " . mysqli_error($conn);
            }
        } else {
            $errors[] = "Invalid WhatsApp account";
        }
    }
}

// Process group update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_group'])) {
    $id = clean_input($_POST["id"]);
    $whatsapp_number_id = clean_input($_POST["whatsapp_number_id"]);
    $group_name = clean_input($_POST["group_name"]);
    $group_wa_id = clean_input($_POST["group_wa_id"]);
    
    // Validation
    $update_errors = [];
    
    if (empty($whatsapp_number_id)) {
        $update_errors[] = "WhatsApp account is required";
    }
    
    if (empty($group_name)) {
        $update_errors[] = "Group name is required";
    }
    
    if (empty($group_wa_id)) {
        $update_errors[] = "Group ID is required";
    }
    
    // If no errors, update group
    if (empty($update_errors)) {
        // Check if the group belongs to a WhatsApp number owned by the current user
        $check_query = "SELECT wg.id 
                        FROM whatsapp_groups wg 
                        JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id 
                        WHERE wg.id = $id AND wn.user_id = " . $_SESSION['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 1) {
            $query = "UPDATE whatsapp_groups 
                      SET whatsapp_number_id = '$whatsapp_number_id', 
                          group_name = '$group_name', 
                          group_wa_id = '$group_wa_id' 
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                set_flash_message("success", "WhatsApp group updated successfully!");
                header("Location: whatsapp_groups.php");
                exit;
            } else {
                $update_errors[] = "Error: " . mysqli_error($conn);
            }
        } else {
            $update_errors[] = "You don't have permission to edit this group";
        }
    }
}

// Process group deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_group'])) {
    $id = clean_input($_POST["id"]);
    
    // Check if the group belongs to a WhatsApp number owned by the current user
    $check_query = "SELECT wg.id 
                    FROM whatsapp_groups wg 
                    JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id 
                    WHERE wg.id = $id AND wn.user_id = " . $_SESSION['user_id'];
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 1) {
        $query = "DELETE FROM whatsapp_groups WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            set_flash_message("success", "WhatsApp group deleted successfully!");
            header("Location: whatsapp_groups.php");
            exit;
        } else {
            set_flash_message("danger", "Error: " . mysqli_error($conn));
            header("Location: whatsapp_groups.php");
            exit;
        }
    } else {
        set_flash_message("danger", "You don't have permission to delete this group");
        header("Location: whatsapp_groups.php");
        exit;
    }
}

// Get all WhatsApp accounts for the current user
$accounts_query = "SELECT id, account_name FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . " AND active = 1 ORDER BY account_name ASC";
$accounts_result = mysqli_query($conn, $accounts_query);

// Get filter parameters
$filter_account = isset($_GET['account']) ? clean_input($_GET['account']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Base query for groups
$query = "SELECT wg.*, wn.account_name 
          FROM whatsapp_groups wg 
          JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id 
          WHERE wn.user_id = " . $_SESSION['user_id'];

// Apply filters
if (!empty($filter_account)) {
    $query .= " AND wg.whatsapp_number_id = '$filter_account'";
}

if (!empty($search)) {
    $query .= " AND (wg.group_name LIKE '%$search%' OR wg.group_wa_id LIKE '%$search%')";
}

$query .= " ORDER BY wn.account_name ASC, wg.group_name ASC";
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
                    <h1 class="h2 mb-0">WhatsApp Groups</h1>
                </div>
                
                <div class="d-flex gap-2 flex-wrap justify-content-start justify-content-md-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                        <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Add New Group</span>
                    </button>
                </div>
            </div>
            
            <?php if (mysqli_num_rows($accounts_result) == 0): ?>
                <div class="alert alert-warning">
                    <p class="mb-0">You need to add a WhatsApp account first before adding groups. <a href="whatsapp_accounts.php" class="alert-link">Add WhatsApp Account</a></p>
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
                    <h5 class="mb-0">Filter Groups</h5>
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
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by group name or ID" value="<?php echo $search; ?>">
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
                                        <th>Group Name</th>
                                        <th>Group ID</th>
                                        <th>WhatsApp Account</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?php echo $row['group_name']; ?></td>
                                            <td><?php echo $row['group_wa_id']; ?></td>
                                            <td><?php echo $row['account_name']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-group" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-whatsapp-number-id="<?php echo $row['whatsapp_number_id']; ?>"
                                                        data-group-name="<?php echo $row['group_name']; ?>"
                                                        data-group-wa-id="<?php echo $row['group_wa_id']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editGroupModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-group" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-group-name="<?php echo $row['group_name']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#deleteGroupModal">
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
                            <p class="mb-0">No WhatsApp groups found. Click "Add New Group" to add your first group.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-labelledby="addGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGroupModalLabel">Add New WhatsApp Group</h5>
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
                        <label for="group_name" class="form-label">Group Name</label>
                        <input type="text" class="form-control" id="group_name" name="group_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="group_wa_id" class="form-label">Group ID</label>
                        <input type="text" class="form-control" id="group_wa_id" name="group_wa_id" required>
                        <div class="form-text">Enter the WhatsApp Group ID (usually ends with @g.us)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_group" class="btn btn-primary">Add Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGroupModalLabel">Edit WhatsApp Group</h5>
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
                        <label for="edit_group_name" class="form-label">Group Name</label>
                        <input type="text" class="form-control" id="edit_group_name" name="group_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_group_wa_id" class="form-label">Group ID</label>
                        <input type="text" class="form-control" id="edit_group_wa_id" name="group_wa_id" required>
                        <div class="form-text">Enter the WhatsApp Group ID (usually ends with @g.us)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_group" class="btn btn-primary">Update Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Group Modal -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-labelledby="deleteGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteGroupModalLabel">Delete WhatsApp Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="delete_id" name="id">
                    <p>Are you sure you want to delete the group: <strong id="delete_group_name"></strong>?</p>
                    <p class="text-danger">This will also delete all scheduled messages and history associated with this group!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_group" class="btn btn-danger">Delete Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Edit group button click
    document.querySelectorAll('.edit-group').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_whatsapp_number_id').value = this.getAttribute('data-whatsapp-number-id');
            document.getElementById('edit_group_name').value = this.getAttribute('data-group-name');
            document.getElementById('edit_group_wa_id').value = this.getAttribute('data-group-wa-id');
        });
    });
    
    // Delete group button click
    document.querySelectorAll('.delete-group').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('delete_id').value = this.getAttribute('data-id');
            document.getElementById('delete_group_name').textContent = this.getAttribute('data-group-name');
        });
    });
</script>

<?php include('../includes/footer.php'); ?>