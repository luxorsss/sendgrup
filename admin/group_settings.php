<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Process form submission for updating group settings
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_settings'])) {
    $group_id = clean_input($_POST["group_id"]);
    $promotion_ids = isset($_POST["promotion_ids"]) ? $_POST["promotion_ids"] : [];
    $footer_id = !empty($_POST["footer_id"]) ? clean_input($_POST["footer_id"]) : null;
    
    // Check if the group belongs to a WhatsApp number owned by the current user
    $check_query = "SELECT wg.id 
                    FROM whatsapp_groups wg 
                    JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id 
                    WHERE wg.id = $group_id AND wn.user_id = " . $_SESSION['user_id'];
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 1) {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Check if settings already exist for this group
            $exists_query = "SELECT id FROM group_settings WHERE group_id = $group_id";
            $exists_result = mysqli_query($conn, $exists_query);
            
            if (mysqli_num_rows($exists_result) > 0) {
                // Update existing settings (just the footer)
                $row = mysqli_fetch_assoc($exists_result);
                $settings_id = $row['id'];
                
                $query = "UPDATE group_settings 
                          SET footer_id = " . ($footer_id ? $footer_id : "NULL") . " 
                          WHERE id = $settings_id";
                mysqli_query($conn, $query);
            } else {
                // Insert new settings
                $query = "INSERT INTO group_settings (group_id, footer_id) 
                          VALUES ($group_id, " . ($footer_id ? $footer_id : "NULL") . ")";
                mysqli_query($conn, $query);
            }
            
            // Remove all existing promotions for this group
            $delete_query = "DELETE FROM group_promotions WHERE group_id = $group_id";
            mysqli_query($conn, $delete_query);
            
            // Add new promotions with order
            $display_order = 1;
            foreach ($promotion_ids as $promotion_id) {
                $promotion_id = clean_input($promotion_id);
                
                $insert_query = "INSERT INTO group_promotions (group_id, promotion_id, display_order) 
                                VALUES ($group_id, $promotion_id, $display_order)";
                mysqli_query($conn, $insert_query);
                
                $display_order++;
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            set_flash_message("success", "Group settings updated successfully!");
            header("Location: group_settings.php");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $errors = ["Error: " . $e->getMessage()];
        }
    } else {
        $errors = ["You don't have permission to edit settings for this group"];
    }
}

// Get all WhatsApp accounts for the current user
$accounts_query = "SELECT id, account_name FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . " AND active = 1 ORDER BY account_name ASC";
$accounts_result = mysqli_query($conn, $accounts_query);

// Get filter parameters
$filter_account = isset($_GET['account']) ? clean_input($_GET['account']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Base query for groups with settings
$query = "SELECT wg.id as group_id, wg.group_name, wg.whatsapp_number_id, wg.group_wa_id, 
                 wn.account_name, gs.id as settings_id, gs.footer_id, f.footer_name
          FROM whatsapp_groups wg
          JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id
          LEFT JOIN group_settings gs ON wg.id = gs.group_id
          LEFT JOIN footers f ON gs.footer_id = f.id
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
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Group Settings</h1>
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
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Filter Groups</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-5">
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
                        <div class="col-md-5">
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
                                        <th>WhatsApp Account</th>
                                        <th>Promotions</th>
                                        <th>Footer</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($result)): 
                                        // Get promotions for this group
                                        $promo_query = "SELECT p.promotion_name 
                                                      FROM group_promotions gp 
                                                      JOIN promotions p ON gp.promotion_id = p.id 
                                                      WHERE gp.group_id = " . $row['group_id'] . " 
                                                      ORDER BY gp.display_order ASC";
                                        $promo_result = mysqli_query($conn, $promo_query);
                                        $promotions = [];
                                        while ($promo = mysqli_fetch_assoc($promo_result)) {
                                            $promotions[] = $promo['promotion_name'];
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo $row['group_name']; ?></td>
                                            <td><?php echo $row['account_name']; ?></td>
                                            <td>
                                                <?php if (!empty($promotions)): ?>
                                                    <?php echo implode(", ", $promotions); ?>
                                                    <span class="badge bg-primary"><?php echo count($promotions); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">No promotions</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['footer_name'])): ?>
                                                    <?php echo $row['footer_name']; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No footer</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary edit-settings" 
                                                        data-group-id="<?php echo $row['group_id']; ?>"
                                                        data-group-name="<?php echo $row['group_name']; ?>"
                                                        data-account-name="<?php echo $row['account_name']; ?>"
                                                        data-whatsapp-number-id="<?php echo $row['whatsapp_number_id']; ?>"
                                                        data-footer-id="<?php echo $row['footer_id']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editSettingsModal">
                                                    <i class="bi bi-gear"></i> Edit Settings
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <p class="mb-0">No groups found. <a href="whatsapp_groups.php" class="alert-link">Add WhatsApp Groups</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Edit Settings Modal -->
<div class="modal fade" id="editSettingsModal" tabindex="-1" aria-labelledby="editSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSettingsModalLabel">Edit Group Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="group_id" name="group_id">
                    
                    <div class="mb-3">
                        <p><strong>Group:</strong> <span id="group_name"></span></p>
                        <p><strong>WhatsApp Account:</strong> <span id="account_name"></span></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Promotions</label>
                        <div class="card">
                            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                <?php
                                // Get all active promotions
                                $promotions_query = "SELECT p.id, p.promotion_name, wn.account_name 
                                                    FROM promotions p 
                                                    JOIN whatsapp_numbers wn ON p.whatsapp_number_id = wn.id 
                                                    WHERE wn.user_id = " . $_SESSION['user_id'] . " AND p.active = 1
                                                    ORDER BY wn.account_name ASC, p.promotion_name ASC";
                                $promotions_result = mysqli_query($conn, $promotions_query);
                                
                                if (mysqli_num_rows($promotions_result) > 0):
                                ?>
                                    <div class="mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="select_all_promotions">
                                            <label class="form-check-label fw-bold" for="select_all_promotions">
                                                Select/Deselect All
                                            </label>
                                        </div>
                                    </div>
                                    <div class="promotions-list">
                                        <?php while($promotion = mysqli_fetch_assoc($promotions_result)): ?>
                                            <div class="form-check">
                                                <input class="form-check-input promotion-checkbox" type="checkbox" name="promotion_ids[]" value="<?php echo $promotion['id']; ?>" id="promo_<?php echo $promotion['id']; ?>">
                                                <label class="form-check-label" for="promo_<?php echo $promotion['id']; ?>">
                                                    <?php echo $promotion['promotion_name'] . ' (' . $promotion['account_name'] . ')'; ?>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No active promotions available. <a href="promotions.php" target="_blank">Create a Promotion</a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="form-text">Selected promotions will be included in messages in the order selected.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="footer_id" class="form-label">Footer</label>
                        <select class="form-select" id="footer_id" name="footer_id">
                            <option value="">No Footer</option>
                            <?php
                            // Get all active footers
                            $footers_query = "SELECT f.id, f.footer_name, wn.account_name 
                                            FROM footers f 
                                            JOIN whatsapp_numbers wn ON f.whatsapp_number_id = wn.id 
                                            WHERE wn.user_id = " . $_SESSION['user_id'] . " AND f.active = 1
                                            ORDER BY wn.account_name ASC, f.footer_name ASC";
                            $footers_result = mysqli_query($conn, $footers_query);
                            
                            while($footer = mysqli_fetch_assoc($footers_result)): 
                            ?>
                                <option value="<?php echo $footer['id']; ?>">
                                    <?php echo $footer['footer_name'] . ' (' . $footer['account_name'] . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_settings" class="btn btn-primary">Update Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Edit settings button click
    document.querySelectorAll('.edit-settings').forEach(function(button) {
        button.addEventListener('click', function() {
            const groupId = this.getAttribute('data-group-id');
            const groupName = this.getAttribute('data-group-name');
            const accountName = this.getAttribute('data-account-name');
            const footerId = this.getAttribute('data-footer-id');
            
            document.getElementById('group_id').value = groupId;
            document.getElementById('group_name').textContent = groupName;
            document.getElementById('account_name').textContent = accountName;
            
            // Reset all promotion checkboxes
            document.querySelectorAll('.promotion-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Set footer selection
            const footerSelect = document.getElementById('footer_id');
            if (footerId) {
                footerSelect.value = footerId;
            } else {
                footerSelect.value = '';
            }
            
            // Fetch and set selected promotions
            fetch(`get_group_promotions.php?group_id=${groupId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(promoId => {
                        const checkbox = document.getElementById(`promo_${promoId}`);
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                })
                .catch(error => console.error('Error fetching promotions:', error));
        });
    });
    
    // Select all promotions checkbox
    document.getElementById('select_all_promotions')?.addEventListener('change', function() {
        document.querySelectorAll('.promotion-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
</script>

<?php include('../includes/footer.php'); ?>