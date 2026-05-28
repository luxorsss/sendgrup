<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Process form submission for adding new footer
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_footer'])) {
    $whatsapp_number_id = clean_input($_POST["whatsapp_number_id"]);
    $footer_name = clean_input($_POST["footer_name"]);
    $footer_content = $_POST["footer_content"]; // Don't clean line breaks
    $active = isset($_POST["active"]) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($whatsapp_number_id)) {
        $errors[] = "WhatsApp account is required";
    }
    
    if (empty($footer_name)) {
        $errors[] = "Footer name is required";
    }
    
    if (empty($footer_content)) {
        $errors[] = "Footer content is required";
    }
    
    // If no errors, add footer
    if (empty($errors)) {
        // Check if the WhatsApp number belongs to the current user
        $check_query = "SELECT id FROM whatsapp_numbers WHERE id = $whatsapp_number_id AND user_id = " . $_SESSION['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 1) {
            // Escape footer content properly for database
            $footer_content = mysqli_real_escape_string($conn, $footer_content);
            
            $query = "INSERT INTO footers (whatsapp_number_id, footer_name, footer_content, active) 
                      VALUES ('$whatsapp_number_id', '$footer_name', '$footer_content', $active)";
            
            if (mysqli_query($conn, $query)) {
                set_flash_message("success", "Footer added successfully!");
                header("Location: footers.php");
                exit;
            } else {
                $errors[] = "Error: " . mysqli_error($conn);
            }
        } else {
            $errors[] = "Invalid WhatsApp account";
        }
    }
}

// Process footer update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_footer'])) {
    $id = $_POST["id"];
    $whatsapp_number_id = $_POST["whatsapp_number_id"];
    $footer_name = $_POST["footer_name"];
    $footer_content = $_POST["footer_content"];
    $active = isset($_POST["active"]) ? 1 : 0;
    
    // Validation
    $update_errors = [];
    
    if (empty($whatsapp_number_id)) {
        $update_errors[] = "WhatsApp account is required";
    }
    
    if (empty($footer_name)) {
        $update_errors[] = "Footer name is required";
    }
    
    if (empty($footer_content)) {
        $update_errors[] = "Footer content is required";
    }
    
    // If no errors, update footer
    if (empty($update_errors)) {
        // Check if the footer belongs to a WhatsApp number owned by the current user
        $check_query = "SELECT f.id 
                        FROM footers f 
                        JOIN whatsapp_numbers wn ON f.whatsapp_number_id = wn.id 
                        WHERE f.id = $id AND wn.user_id = " . $_SESSION['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 1) {
            // Escape footer content properly for database
            $footer_content = mysqli_real_escape_string($conn, $footer_content);
            
            $query = "UPDATE footers 
                      SET whatsapp_number_id = '$whatsapp_number_id', 
                          footer_name = '$footer_name', 
                          footer_content = '$footer_content', 
                          active = $active 
                      WHERE id = $id";
            
            if (mysqli_query($conn, $query)) {
                set_flash_message("success", "Footer updated successfully!");
                header("Location: footers.php");
                exit;
            } else {
                $update_errors[] = "Error: " . mysqli_error($conn);
            }
        } else {
            $update_errors[] = "You don't have permission to edit this footer";
        }
    }
}

// Process footer deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_footer'])) {
    $id = clean_input($_POST["id"]);
    
    // Check if the footer belongs to a WhatsApp number owned by the current user
    $check_query = "SELECT f.id 
                    FROM footers f 
                    JOIN whatsapp_numbers wn ON f.whatsapp_number_id = wn.id 
                    WHERE f.id = $id AND wn.user_id = " . $_SESSION['user_id'];
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 1) {
        // Check if the footer is used in any group settings
        $used_query = "SELECT COUNT(*) as count FROM group_settings WHERE footer_id = $id";
        $used_result = mysqli_query($conn, $used_query);
        $used_row = mysqli_fetch_assoc($used_result);
        
        if ($used_row['count'] > 0) {
            set_flash_message("danger", "This footer is being used by group settings. Please remove those associations first.");
            header("Location: footers.php");
            exit;
        }
        
        $query = "DELETE FROM footers WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            set_flash_message("success", "Footer deleted successfully!");
            header("Location: footers.php");
            exit;
        } else {
            set_flash_message("danger", "Error: " . mysqli_error($conn));
            header("Location: footers.php");
            exit;
        }
    } else {
        set_flash_message("danger", "You don't have permission to delete this footer");
        header("Location: footers.php");
        exit;
    }
}

// Get all WhatsApp accounts for the current user
$accounts_query = "SELECT id, account_name FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . " AND active = 1 ORDER BY account_name ASC";
$accounts_result = mysqli_query($conn, $accounts_query);

// Get filter parameters
$filter_account = isset($_GET['account']) ? clean_input($_GET['account']) : '';
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Base query for footers
$query = "SELECT f.*, wn.account_name 
          FROM footers f 
          JOIN whatsapp_numbers wn ON f.whatsapp_number_id = wn.id 
          WHERE wn.user_id = " . $_SESSION['user_id'];

// Apply filters
if (!empty($filter_account)) {
    $query .= " AND f.whatsapp_number_id = '$filter_account'";
}

if ($filter_status !== '') {
    $query .= " AND f.active = '$filter_status'";
}

if (!empty($search)) {
    $query .= " AND (f.footer_name LIKE '%$search%' OR f.footer_content LIKE '%$search%')";
}

$query .= " ORDER BY wn.account_name ASC, f.footer_name ASC";
$result = mysqli_query($conn, $query);

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include('../includes/sidebar.php'); ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content utilitarian-page">
            
            <style>
                .utilitarian-page .row-list {
                    display: flex;
                    flex-direction: column;
                }
                .utilitarian-page .row-item {
                    display: grid;
                    grid-template-columns: 40px 2fr 3fr 1.5fr 100px auto;
                    align-items: center;
                    gap: 1rem;
                    padding: 1.25rem 0.5rem;
                    border-bottom: 1px solid var(--border-color);
                    transition: background-color 200ms var(--ease-out);
                    
                    opacity: 0;
                    transform: translateY(12px);
                    animation: fadeInRow 400ms var(--ease-out) forwards;
                }
                @media (hover: hover) and (pointer: fine) {
                    .utilitarian-page .row-item:hover {
                        background-color: rgba(0, 56, 255, 0.02);
                    }
                }
                .content-preview {
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    font-size: 0.9rem;
                    color: var(--ink-muted);
                    font-style: italic;
                    padding-right: 1rem;
                }
            </style>

            <!-- HEADER EDITORIAL -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-end pt-4 pb-3 mb-4" style="border-bottom: 2px solid var(--ink);">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" style="border-radius: 4px;">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h2 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600;">Footer Modules</h1>
                        <span class="font-mono text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">MANAGE MESSAGE SIGNATURES</span>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFooterModal" style="border-radius: 4px; padding: 0.6rem 1.25rem;">
                        <i class="bi bi-plus-lg me-1"></i> Buat Footer Baru
                    </button>
                </div>
            </div>

            <?php display_flash_message(); ?>
            
            <div class="mt-2 mb-5">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <div class="row-list">
                        
                        <!-- Header Baris -->
                        <div class="row-item py-2" style="border-bottom: 2px solid var(--ink); animation: none; opacity: 1; transform: none; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: var(--ink-muted);">
                            <div><input type="checkbox" id="selectAll" class="form-check-input" style="border-color: var(--ink-muted);"></div>
                            <div>Nama Footer</div>
                            <div>Preview Konten</div>
                            <div>Akun Tertaut</div>
                            <div>Status</div>
                            <div class="text-end">Aksi</div>
                        </div>

                        <?php 
                        $delay = 0;
                        while($row = mysqli_fetch_assoc($result)): 
                        ?>
                            <!-- Baris Data dengan Efek Cascade -->
                            <div class="row-item" style="animation-delay: <?php echo $delay; ?>ms">
                                
                                <!-- Checkbox -->
                                <div>
                                    <input type="checkbox" class="form-check-input item-checkbox" value="<?php echo $row['id']; ?>" style="border-color: var(--ink-muted); cursor: pointer;">
                                </div>
                                
                                <!-- Nama & ID -->
                                <div>
                                    <div style="font-weight: 600; font-size: 1.05rem; color: var(--ink);">
                                        <?php echo htmlspecialchars($row['footer_name']); ?>
                                    </div>
                                    <div class="font-mono text-muted" style="font-size: 0.75rem; margin-top: 4px;">
                                        ID: FTR_<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?>
                                    </div>
                                </div>
                                
                                <!-- Konten -->
                                <div class="content-preview" title="<?php echo htmlspecialchars($row['footer_content']); ?>">
                                    "<?php echo htmlspecialchars(substr($row['footer_content'], 0, 60)) . (strlen($row['footer_content']) > 60 ? '...' : ''); ?>"
                                </div>
                                
                                <!-- Akun -->
                                <div class="font-mono" style="font-size: 0.85rem; color: var(--ink);">
                                    <?php echo htmlspecialchars($row['account_name']); ?>
                                </div>
                                
                                <!-- Status -->
                                <div>
                                    <?php if ($row['active']): ?>
                                        <span class="badge" style="background: #E8F5E9; color: #2E7D32; border-radius: 2px; font-family: 'Geist Mono', monospace; font-size: 0.65rem; letter-spacing: 0.05em; padding: 4px 6px;">ACTIVE</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: transparent; color: var(--ink-muted); border: 1px dashed var(--border-color); border-radius: 2px; font-family: 'Geist Mono', monospace; font-size: 0.65rem; letter-spacing: 0.05em; padding: 4px 6px;">INACTIVE</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Aksi -->
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm" 
                                            style="background: rgba(0, 56, 255, 0.05); color: var(--accent); border: 1px solid rgba(0, 56, 255, 0.1); border-radius: 4px; padding: 0.35rem 0.6rem; transition: transform 150ms ease;"
                                            onmousedown="this.style.transform='scale(0.95)'" onmouseup="this.style.transform='scale(1)'" onmouseleave="this.style.transform='scale(1)'"
                                            data-id="<?php echo $row['id']; ?>" 
                                            data-whatsapp-number-id="<?php echo $row['whatsapp_number_id']; ?>"
                                            data-footer-name="<?php echo htmlspecialchars($row['footer_name']); ?>" 
                                            data-footer-content="<?php echo htmlspecialchars($row['footer_content']); ?>"
                                            data-active="<?php echo $row['active']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editFooterModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-footer" 
                                            style="border-radius: 4px; padding: 0.35rem 0.6rem; transition: transform 150ms ease;"
                                            onmousedown="this.style.transform='scale(0.95)'" onmouseup="this.style.transform='scale(1)'" onmouseleave="this.style.transform='scale(1)'"
                                            data-id="<?php echo $row['id']; ?>" 
                                            data-footer-name="<?php echo htmlspecialchars($row['footer_name']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteFooterModal">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php 
                        $delay += 40; 
                        endwhile; 
                        ?>
                    </div>
                <?php else: ?>
                    <div class="alert mt-3" style="border-radius: 4px; border: 1px dashed var(--border-color); background: transparent; color: var(--ink-muted); text-align: center; padding: 4rem 1rem;">
                        <i class="bi bi-card-text" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--ink);"></i>
                        <p class="mb-0 font-mono" style="font-size: 1rem; font-weight: 500;">Modul Footer masih kosong.</p>
                        <p class="font-mono text-muted mt-2" style="font-size: 0.85rem;">Buat footer standar (seperti info kontak atau penafian) untuk disematkan di akhir pesan.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Add Footer Modal -->
<div class="modal fade" id="addFooterModal" tabindex="-1" aria-labelledby="addFooterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFooterModalLabel">Add New Footer</h5>
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
                        <label for="footer_name" class="form-label">Footer Name</label>
                        <input type="text" class="form-control" id="footer_name" name="footer_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="footer_content" class="form-label">Footer Content</label>
                        <textarea class="form-control" id="footer_content" name="footer_content" rows="3" required placeholder="Enter your footer text here..."></textarea>
                        <div class="form-text mt-2">
                            <strong>Formatting Tips:</strong><br>
                            - Use *asterisks* for <strong>bold text</strong><br>
                            - Use _underscores_ for <em>italic text</em><br>
                            - Use ~tildes~ for <del>strikethrough</del><br>
                            - Press Enter for new line <!-- Baris ini ditambahkan untuk kejelasan -->
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="active" name="active" value="1" checked>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_footer" class="btn btn-primary">Add Footer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Footer Modal -->
<div class="modal fade" id="editFooterModal" tabindex="-1" aria-labelledby="editFooterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFooterModalLabel">Edit Footer</h5>
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
                        <label for="edit_footer_name" class="form-label">Footer Name</label>
                        <input type="text" class="form-control" id="edit_footer_name" name="footer_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_footer_content" class="form-label">Footer Content</label>
                        <textarea class="form-control" id="edit_footer_content" name="footer_content" rows="3" required></textarea>
                        <div class="form-text mt-2">
                            <strong>Formatting Tips:</strong><br>
                            - Use *asterisks* for <strong>bold text</strong><br>
                            - Use _underscores_ for <em>italic text</em><br>
                            - Use ~tildes~ for <del>strikethrough</del>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_active" name="active" value="1">
                        <label class="form-check-label" for="edit_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_footer" class="btn btn-primary">Update Footer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Footer Modal -->
<div class="modal fade" id="viewFooterModal" tabindex="-1" aria-labelledby="viewFooterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewFooterModalLabel">View Footer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h5 id="view_footer_name" class="mb-3"></h5>
                
                <div class="card">
                    <div class="card-body">
                        <pre id="view_footer_content" class="mb-0" style="white-space: pre-wrap; font-family: inherit;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary edit-from-view" data-bs-toggle="modal" data-bs-target="#editFooterModal">
                    <i class="bi bi-pencil"></i> Edit Footer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Footer Modal -->
<div class="modal fade" id="deleteFooterModal" tabindex="-1" aria-labelledby="deleteFooterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteFooterModalLabel">Delete Footer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="delete_id" name="id">
                    <p>Are you sure you want to delete the footer: <strong id="delete_footer_name"></strong>?</p>
                    <p class="text-danger">If this footer is used in any group settings, you will need to remove those associations first.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_footer" class="btn btn-danger">Delete Footer</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    // View footer button click
    document.querySelectorAll('.view-footer').forEach(function(button) {
        button.addEventListener('click', function() {
            const footerId = this.getAttribute('data-id');
            const footerName = this.getAttribute('data-footer-name');
            const footerContent = this.getAttribute('data-footer-content');
            
            document.getElementById('view_footer_name').textContent = footerName;
            const contentDiv = document.getElementById('view_footer_content');
            // Replace \r\n, \n\r, \r, \n with <br> for HTML display
            contentDiv.innerHTML = footerContent
                .replace(/\\r\\n|\\n\\r|\\n|\\r/g, '<br>')
                .replace(/\r\n|\n\r|\r|\n/g, '<br>');
            
            // Set the footer ID for the edit button
            document.querySelector('.edit-from-view').setAttribute('data-id', footerId);
        });
    });
    
    // Edit from view modal
    document.querySelector('.edit-from-view').addEventListener('click', function() {
        const footerId = this.getAttribute('data-id');
        const editButton = document.querySelector(`.edit-footer[data-id="${footerId}"]`);
        if (editButton) {
            // Trigger the click event on the edit button
            editButton.click();
        }
        // Close the view modal
        bootstrap.Modal.getInstance(document.getElementById('viewFooterModal')).hide();
    });
    
    // Edit footer button click
    document.querySelectorAll('.edit-footer').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_whatsapp_number_id').value = this.getAttribute('data-whatsapp-number-id');
            document.getElementById('edit_footer_name').value = this.getAttribute('data-footer-name');
            document.getElementById('edit_footer_content').value = this.getAttribute('data-footer-content');
            document.getElementById('edit_active').checked = this.getAttribute('data-active') == '1';
        });
    });
    
    // Delete footer button click
    document.querySelectorAll('.delete-footer').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('delete_id').value = this.getAttribute('data-id');
            document.getElementById('delete_footer_name').textContent = this.getAttribute('data-footer-name');
        });
    });
</script>

<?php include('../includes/footer.php'); ?>