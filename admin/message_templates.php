<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Set pagination variables
$records_per_page = 200; // Menampilkan 20 data per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// ==========================================
// PROSES FORM SUBMISSION
// ==========================================

// 1. Process form submission for adding new template
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_template'])) {
    $whatsapp_number_id = clean_input($_POST["whatsapp_number_id"]);
    $template_name = clean_input($_POST["template_name"]);
    $message_content = $_POST["message_content"];
    $image_url = clean_input($_POST["image_url"]);
    
    // Ambil automation_list_id (jika kosong, set NULL)
    $automation_list_id = !empty($_POST["automation_list_id"]) ? clean_input($_POST["automation_list_id"]) : "NULL";
    
    $errors = [];
    if (empty($whatsapp_number_id)) $errors[] = "WhatsApp account is required";
    if (empty($template_name)) $errors[] = "Template name is required";
    if (empty($message_content)) $errors[] = "Message content is required";
    
    if (empty($errors)) {
        $check_query = "SELECT id FROM whatsapp_numbers WHERE id = $whatsapp_number_id AND user_id = " . $_SESSION['user_id'];
        if (mysqli_num_rows(mysqli_query($conn, $check_query)) == 1) {
            $message_content = mysqli_real_escape_string($conn, $message_content);
            $query = "INSERT INTO message_templates (whatsapp_number_id, template_name, message_content, image_url, automation_list_id) 
                      VALUES ('$whatsapp_number_id', '$template_name', '$message_content', " . ($image_url ? "'$image_url'" : "NULL") . ", $automation_list_id)";
            
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

// 2. Process template update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_template'])) {
    $id = clean_input($_POST["id"]);
    $whatsapp_number_id = clean_input($_POST["whatsapp_number_id"]);
    $template_name = clean_input($_POST["template_name"]);
    $message_content = $_POST["message_content"];
    $image_url = clean_input($_POST["image_url"]);
    
    $automation_list_id = !empty($_POST["automation_list_id"]) ? clean_input($_POST["automation_list_id"]) : "NULL";
    
    $update_errors = [];
    if (empty($whatsapp_number_id)) $update_errors[] = "WhatsApp account is required";
    if (empty($template_name)) $update_errors[] = "Template name is required";
    if (empty($message_content)) $update_errors[] = "Message content is required";
    
    if (empty($update_errors)) {
        $check_query = "SELECT mt.id FROM message_templates mt JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id WHERE mt.id = $id AND wn.user_id = " . $_SESSION['user_id'];
        if (mysqli_num_rows(mysqli_query($conn, $check_query)) == 1) {
            $message_content = mysqli_real_escape_string($conn, $message_content);
            $query = "UPDATE message_templates 
                      SET whatsapp_number_id = '$whatsapp_number_id', template_name = '$template_name', 
                          message_content = '$message_content', image_url = " . ($image_url ? "'$image_url'" : "NULL") . ",
                          automation_list_id = $automation_list_id
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

// 3. Process Bulk Set Automation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_set_automation'])) {
    $template_ids = isset($_POST['template_ids']) ? $_POST['template_ids'] : [];
    $bulk_automation_list_id = !empty($_POST['bulk_automation_list_id']) ? clean_input($_POST['bulk_automation_list_id']) : "NULL";
    
    if (!empty($template_ids)) {
        $ids_str = implode(',', array_map('intval', $template_ids));
        $query = "UPDATE message_templates mt 
                  JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
                  SET mt.automation_list_id = $bulk_automation_list_id 
                  WHERE mt.id IN ($ids_str) AND wn.user_id = " . $_SESSION['user_id'];
        
        if (mysqli_query($conn, $query)) {
            $count = count($template_ids);
            $status_msg = $bulk_automation_list_id == "NULL" ? "dilepas dari Kelompok Automasi." : "berhasil dimasukkan ke Kelompok Automasi.";
            set_flash_message("success", "$count template $status_msg");
        } else {
            set_flash_message("danger", "Error updating templates: " . mysqli_error($conn));
        }
    } else {
        set_flash_message("warning", "Tidak ada template yang dipilih.");
    }
    header("Location: message_templates.php");
    exit;
}

// 4. Process Bulk Delete (Fitur Baru)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_delete_templates'])) {
    $template_ids = isset($_POST['template_ids']) ? $_POST['template_ids'] : [];
    
    if (!empty($template_ids)) {
        $ids_str = implode(',', array_map('intval', $template_ids));
        
        // Eksekusi DELETE dengan validasi multi-tabel (agar hanya milik user yang terhapus)
        $query = "DELETE mt FROM message_templates mt 
                  JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
                  WHERE mt.id IN ($ids_str) AND wn.user_id = " . $_SESSION['user_id'];
        
        if (mysqli_query($conn, $query)) {
            $count = mysqli_affected_rows($conn);
            set_flash_message("success", "$count template berhasil dihapus secara massal!");
        } else {
            set_flash_message("danger", "Error deleting templates: " . mysqli_error($conn));
        }
    } else {
        set_flash_message("warning", "Tidak ada template yang dipilih untuk dihapus.");
    }
    header("Location: message_templates.php");
    exit;
}

// 5. Process template deletion (Single)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_template'])) {
    $id = clean_input($_POST["id"]);
    $check_query = "SELECT mt.id FROM message_templates mt JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id WHERE mt.id = $id AND wn.user_id = " . $_SESSION['user_id'];
    
    if (mysqli_num_rows(mysqli_query($conn, $check_query)) == 1) {
        if (mysqli_query($conn, "DELETE FROM message_templates WHERE id = $id")) {
            set_flash_message("success", "Message template deleted successfully!");
        } else {
            set_flash_message("danger", "Error: " . mysqli_error($conn));
        }
    } else {
        set_flash_message("danger", "You don't have permission to delete this template");
    }
    header("Location: message_templates.php");
    exit;
}

// ==========================================
// AMBIL DATA UNTUK DITAMPILKAN
// ==========================================

$accounts_query = "SELECT id, account_name FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . " AND active = 1 ORDER BY account_name ASC";
$accounts_result = mysqli_query($conn, $accounts_query);

$automations_query = "SELECT id, list_name FROM automation_lists WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY list_name ASC";
$automations_result = mysqli_query($conn, $automations_query);

$filter_account = isset($_GET['account']) ? clean_input($_GET['account']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

$count_query = "SELECT COUNT(*) as total FROM message_templates mt JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id WHERE wn.user_id = " . $_SESSION['user_id'];
if (!empty($filter_account)) $count_query .= " AND mt.whatsapp_number_id = '$filter_account'";
if (!empty($search)) $count_query .= " AND (mt.template_name LIKE '%$search%' OR mt.message_content LIKE '%$search%')";

$count_row = mysqli_fetch_assoc(mysqli_query($conn, $count_query));
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $records_per_page);

$query = "SELECT mt.*, wn.account_name, al.list_name as automation_list_name 
          FROM message_templates mt 
          JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id 
          LEFT JOIN automation_lists al ON mt.automation_list_id = al.id
          WHERE wn.user_id = " . $_SESSION['user_id'];

if (!empty($filter_account)) $query .= " AND mt.whatsapp_number_id = '$filter_account'";
if (!empty($search)) $query .= " AND (mt.template_name LIKE '%$search%' OR mt.message_content LIKE '%$search%')";

$query .= " ORDER BY wn.account_name ASC, mt.template_name ASC LIMIT $records_per_page OFFSET $offset";
$result = mysqli_query($conn, $query);

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <?php include('../includes/sidebar.php'); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                        <i class="bi bi-list"></i> Menu
                    </button>
                    <h1 class="h2 mb-0">Message Templates</h1>
                </div>
                
                <div class="d-flex gap-2 flex-wrap justify-content-end">
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bulkDeleteTemplatesModal" id="btnBulkDelete" disabled>
                        <i class="bi bi-trash"></i> <span class="d-none d-md-inline">Hapus Terpilih</span>
                    </button>
                    
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkAutomationModal" id="btnBulkAutomation" disabled>
                        <i class="bi bi-collection-fill"></i> <span class="d-none d-md-inline">Set Kelompok Massal</span>
                    </button>
                    
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal" <?php echo mysqli_num_rows($accounts_result) == 0 ? 'disabled' : ''; ?>>
                        <i class="bi bi-plus-circle"></i> <span class="d-none d-md-inline">Add Template</span>
                    </button>
                </div>
            </div>
            
            <?php display_flash_message(); ?>
            <?php if (mysqli_num_rows($accounts_result) == 0): ?>
                <div class="alert alert-warning"><p class="mb-0">You need to add a WhatsApp account first before creating templates.</p></div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Filter Templates</h5></div>
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
                                    <option value="<?php echo $account['id']; ?>" <?php echo $filter_account == $account['id'] ? 'selected' : ''; ?>><?php echo $account['account_name']; ?></option>
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
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th width="40"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                        <th>Template Name</th>
                                        <th>Kelompok Automasi</th>
                                        <th>Content Preview</th>
                                        <th>Image</th>
                                        <th>WhatsApp Account</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><input type="checkbox" class="form-check-input template-checkbox" value="<?php echo $row['id']; ?>"></td>
                                            <td class="fw-bold"><?php echo $row['template_name']; ?></td>
                                            <td>
                                                <?php echo !empty($row['automation_list_name']) ? '<span class="badge bg-success">'.$row['automation_list_name'].'</span>' : '<span class="text-muted fst-italic">Tidak ada</span>'; ?>
                                            </td>
                                            <td>
                                                <?php 
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
                                                <button type="button" class="btn btn-sm btn-info text-white view-template" 
                                                        data-id="<?php echo $row['id']; ?>" data-template-name="<?php echo $row['template_name']; ?>"
                                                        data-message-content="<?php echo htmlspecialchars($row['message_content']); ?>"
                                                        data-image-url="<?php echo $row['image_url']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#viewTemplateModal">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary edit-template" 
                                                        data-id="<?php echo $row['id']; ?>" data-whatsapp-number-id="<?php echo $row['whatsapp_number_id']; ?>"
                                                        data-template-name="<?php echo $row['template_name']; ?>" data-message-content="<?php echo htmlspecialchars($row['message_content']); ?>"
                                                        data-image-url="<?php echo $row['image_url']; ?>" data-automation-list-id="<?php echo $row['automation_list_id']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editTemplateModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-template" 
                                                        data-id="<?php echo $row['id']; ?>" data-template-name="<?php echo $row['template_name']; ?>"
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
                        <div class="alert alert-info mb-0">No message templates found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="bulkDeleteTemplatesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Template Massal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="" id="bulkDeleteForm">
                <div class="modal-body">
                    <div id="bulk_delete_ids_container"></div>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> Peringatan! Anda akan menghapus <strong id="bulk_delete_count_text">0</strong> template secara permanen.
                    </div>
                    <p>Tindakan ini tidak dapat dibatalkan dan semua riwayat yang menggunakan template ini mungkin terpengaruh. Apakah Anda yakin ingin melanjutkan?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="bulk_delete_templates" class="btn btn-danger">Ya, Hapus Semua</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkAutomationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Set Kelompok Automasi Massal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="" id="bulkAutomationForm">
                <div class="modal-body">
                    <div id="bulk_ids_container"></div>
                    <p>Pilih Kelompok Automasi untuk <strong id="bulk_count_text">0</strong> template yang Anda centang:</p>
                    <div class="mb-3">
                        <select class="form-select" name="bulk_automation_list_id">
                            <option value="">-- Lepas dari Kelompok (Jadi Template Biasa) --</option>
                            <?php
                            mysqli_data_seek($automations_result, 0);
                            while($al = mysqli_fetch_assoc($automations_result)): 
                            ?>
                                <option value="<?php echo $al['id']; ?>"><?php echo htmlspecialchars($al['list_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="bulk_set_automation" class="btn btn-success">Terapkan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Message Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="whatsapp_number_id" class="form-label">WhatsApp Account</label>
                            <select class="form-select" id="whatsapp_number_id" name="whatsapp_number_id" required>
                                <option value="">Select WhatsApp Account</option>
                                <?php
                                mysqli_data_seek($accounts_result, 0);
                                while($account = mysqli_fetch_assoc($accounts_result)): 
                                ?>
                                    <option value="<?php echo $account['id']; ?>"><?php echo $account['account_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="automation_list_id" class="form-label">Kelompok Automasi <small class="text-muted">(Opsional)</small></label>
                            <select class="form-select" id="automation_list_id" name="automation_list_id">
                                <option value="">-- Template Biasa --</option>
                                <?php
                                mysqli_data_seek($automations_result, 0);
                                while($al = mysqli_fetch_assoc($automations_result)): 
                                ?>
                                    <option value="<?php echo $al['id']; ?>"><?php echo htmlspecialchars($al['list_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="template_name" class="form-label">Template Name</label>
                        <input type="text" class="form-control" id="template_name" name="template_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="message_content" class="form-label">Message Content</label>
                        <textarea class="form-control" id="message_content" name="message_content" rows="8" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="image_url" class="form-label">Image URL (Optional)</label>
                        <input type="url" class="form-control" id="image_url" name="image_url">
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

<div class="modal fade" id="editTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Message Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_whatsapp_number_id" class="form-label">WhatsApp Account</label>
                            <select class="form-select" id="edit_whatsapp_number_id" name="whatsapp_number_id" required>
                                <?php
                                mysqli_data_seek($accounts_result, 0);
                                while($account = mysqli_fetch_assoc($accounts_result)): 
                                ?>
                                    <option value="<?php echo $account['id']; ?>"><?php echo $account['account_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_automation_list_id" class="form-label">Kelompok Automasi <small class="text-muted">(Opsional)</small></label>
                            <select class="form-select" id="edit_automation_list_id" name="automation_list_id">
                                <option value="">-- Template Biasa --</option>
                                <?php
                                mysqli_data_seek($automations_result, 0);
                                while($al = mysqli_fetch_assoc($automations_result)): 
                                ?>
                                    <option value="<?php echo $al['id']; ?>"><?php echo htmlspecialchars($al['list_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_template_name" class="form-label">Template Name</label>
                        <input type="text" class="form-control" id="edit_template_name" name="template_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_message_content" class="form-label">Message Content</label>
                        <textarea class="form-control" id="edit_message_content" name="message_content" rows="8" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_image_url" class="form-label">Image URL (Optional)</label>
                        <input type="url" class="form-control" id="edit_image_url" name="image_url">
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

<div class="modal fade" id="viewTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTemplateModalLabel">View Message Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h5 id="view_template_name" class="mb-3"></h5>
                <div id="view_image_container" class="text-center mb-3" style="display: none;">
                    <img id="view_image" src="" alt="Template Image" class="img-fluid" style="max-height: 300px;">
                </div>
                <div class="card"><div class="card-body"><pre id="view_message_content" class="mb-0" style="white-space: pre-wrap;"></pre></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Message Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" id="delete_id" name="id">
                    <p>Are you sure you want to delete the template: <strong id="delete_template_name"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_template" class="btn btn-danger">Delete</button>
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
            $range = 2;
            $start_page = max(1, $page - $range);
            $end_page = min($total_pages, $page + $range);

            if ($start_page > 1) {
                echo '<li class="page-item"><a class="page-link" href="?page=1'.(!empty($filter_account) ? '&account='.$filter_account : '').(!empty($search) ? '&search='.$search : '').'">1</a></li>';
                if ($start_page > 2) echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                echo '<li class="page-item '.($page == $i ? 'active' : '').'"><a class="page-link" href="?page='.$i.(!empty($filter_account) ? '&account='.$filter_account : '').(!empty($search) ? '&search='.$search : '').'">'.$i.'</a></li>';
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
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
    <div class="text-center text-muted small mt-2">
        Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> templates
    </div>
<?php endif; ?>

<script>
    // JS Logic untuk Checkbox & Bulk Actions
    const selectAllCheckbox = document.getElementById('selectAll');
    const templateCheckboxes = document.querySelectorAll('.template-checkbox');
    
    // Tombol Bulk
    const btnBulkAutomation = document.getElementById('btnBulkAutomation');
    const btnBulkDelete = document.getElementById('btnBulkDelete');
    
    // Teks Counter
    const bulkCountText = document.getElementById('bulk_count_text');
    const bulkDeleteCountText = document.getElementById('bulk_delete_count_text');
    
    // Form Container
    const bulkIdsContainer = document.getElementById('bulk_ids_container');
    const bulkAutomationForm = document.getElementById('bulkAutomationForm');
    
    const bulkDeleteIdsContainer = document.getElementById('bulk_delete_ids_container');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');

    function updateBulkButtonState() {
        const checkedCount = document.querySelectorAll('.template-checkbox:checked').length;
        
        // Aktifkan/Matikan Tombol
        btnBulkAutomation.disabled = checkedCount === 0;
        btnBulkDelete.disabled = checkedCount === 0;
        
        // Update Teks Angka
        bulkCountText.textContent = checkedCount;
        bulkDeleteCountText.textContent = checkedCount;
    }

    if(selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            templateCheckboxes.forEach(cb => cb.checked = this.checked);
            updateBulkButtonState();
        });
    }

    templateCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkButtonState);
    });

    // Populate Form Set Kelompok
    if(bulkAutomationForm) {
        bulkAutomationForm.addEventListener('submit', function() {
            bulkIdsContainer.innerHTML = ''; 
            document.querySelectorAll('.template-checkbox:checked').forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'template_ids[]';
                input.value = cb.value;
                bulkIdsContainer.appendChild(input);
            });
        });
    }

    // Populate Form Hapus Massal
    if(bulkDeleteForm) {
        bulkDeleteForm.addEventListener('submit', function() {
            bulkDeleteIdsContainer.innerHTML = ''; 
            document.querySelectorAll('.template-checkbox:checked').forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'template_ids[]';
                input.value = cb.value;
                bulkDeleteIdsContainer.appendChild(input);
            });
        });
    }

    // Modal Edit/View/Delete Logic 
    document.querySelectorAll('.edit-template').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_whatsapp_number_id').value = this.getAttribute('data-whatsapp-number-id');
            document.getElementById('edit_template_name').value = this.getAttribute('data-template-name');
            document.getElementById('edit_message_content').value = this.getAttribute('data-message-content');
            document.getElementById('edit_image_url').value = this.getAttribute('data-image-url') || '';
            
            const automationId = this.getAttribute('data-automation-list-id');
            document.getElementById('edit_automation_list_id').value = automationId ? automationId : '';
        });
    });
    
    document.querySelectorAll('.delete-template').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('delete_id').value = this.getAttribute('data-id');
            document.getElementById('delete_template_name').textContent = this.getAttribute('data-template-name');
        });
    });
    
    document.querySelectorAll('.view-template').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('view_template_name').textContent = this.getAttribute('data-template-name');
            document.getElementById('view_message_content').innerHTML = this.getAttribute('data-message-content').replace(/\n/g, '<br>');
        });
    });
</script>

<?php include('../includes/footer.php'); ?>