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
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content utilitarian-page">
            
            <style>
                .utilitarian-page .row-list {
                    display: flex;
                    flex-direction: column;
                }
                .utilitarian-page .row-item {
                    display: grid;
                    /* Checkbox | Nama Template | Kelompok Automasi | Preview Pesan | Akun | Aksi */
                    grid-template-columns: 40px 2fr 1.5fr 3fr 1.5fr auto;
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
                        background-color: rgba(0, 56, 255, 0.02); /* Highlight biru sangat tipis */
                    }
                }
                .template-preview {
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    font-size: 0.9rem;
                    color: var(--ink-muted);
                    font-style: italic;
                }
                .template-badge {
                    background: rgba(10,10,10,0.05);
                    color: var(--ink);
                    padding: 4px 8px;
                    border-radius: 2px;
                    font-size: 0.75rem;
                    font-family: 'Geist Mono', monospace;
                    letter-spacing: 0.05em;
                }
            </style>

            <!-- HEADER EDITORIAL -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-end pt-4 pb-3 mb-4" style="border-bottom: 2px solid var(--ink);">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" style="border-radius: 4px;">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h2 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600;">Message Templates</h1>
                        <span class="font-mono text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">CANNED RESPONSES & BROADCAST FORMATS</span>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal" style="border-radius: 4px; padding: 0.6rem 1.25rem;">
                        <i class="bi bi-plus-lg me-1"></i> Buat Template Baru
                    </button>
                </div>
            </div>

            <?php display_flash_message(); ?>

            <!-- FILTER SECTION UTILITARIAN -->
            <div class="p-3 mb-4" style="border: 1px solid var(--border-color); border-radius: 4px; background: transparent;">
                <div style="font-family: 'Geist Mono', monospace; font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <i class="bi bi-search"></i> Pencarian Arsip
                </div>
                <form method="get" action="" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="search" placeholder="Cari nama template atau isi pesan..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="border-radius: 4px; border: 1px solid var(--border-color); font-family: 'Satoshi', sans-serif;">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100" style="border-radius: 4px;">Terapkan</button>
                    </div>
                </form>
            </div>
            
            <!-- FORMAT ROW-LIST TEMPLATE MENGGUNAKAN QUERY ASLI -->
            <div class="mt-2 mb-5">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <div class="row-list">
                        
                        <!-- Header Baris -->
                        <div class="row-item py-2" style="border-bottom: 2px solid var(--ink); animation: none; opacity: 1; transform: none; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: var(--ink-muted);">
                            <div><input type="checkbox" id="selectAll" class="form-check-input" style="border-color: var(--ink-muted);"></div>
                            <div>Nama & ID</div>
                            <div>Automasi Terkait</div>
                            <div>Preview Konten</div>
                            <div>Akun Pengirim</div>
                            <div class="text-end">Aksi</div>
                        </div>

                        <?php 
                        $delay = 0;
                        while($row = mysqli_fetch_assoc($result)): 
                        ?>
                            <!-- Baris Data dengan Animasi Cascade -->
                            <div class="row-item" style="animation-delay: <?php echo $delay; ?>ms">
                                
                                <!-- Kolom 1: Checkbox -->
                                <div>
                                    <input type="checkbox" class="form-check-input template-checkbox" value="<?php echo $row['id']; ?>" style="border-color: var(--ink-muted); cursor: pointer;">
                                </div>
                                
                                <!-- Kolom 2: Nama Template & ID -->
                                <div>
                                    <div style="font-weight: 600; font-size: 1.05rem; color: var(--ink);">
                                        <?php echo htmlspecialchars($row['template_name']); ?>
                                    </div>
                                    <div class="font-mono text-muted" style="font-size: 0.75rem; margin-top: 4px;">
                                        ID: TPL_<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?>
                                    </div>
                                </div>
                                
                                <!-- Kolom 3: Automasi Terkait (Jika ada) -->
                                <div>
                                    <?php if (!empty($row['automation_list_name'])): ?>
                                        <span class="template-badge">
                                            <?php echo htmlspecialchars($row['automation_list_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="font-mono text-muted" style="border: 1px dashed var(--border-color); padding: 2px 6px; border-radius: 2px; font-size: 0.75rem;">NONE</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Kolom 4: Preview Konten -->
                                <div class="template-preview" title="<?php echo htmlspecialchars($row['message_content']); ?>">
                                    "<?php echo htmlspecialchars(substr($row['message_content'], 0, 70)) . (strlen($row['message_content']) > 70 ? '...' : ''); ?>"
                                </div>
                                
                                <!-- Kolom 5: Akun WA -->
                                <div class="font-mono" style="font-size: 0.85rem; color: var(--ink);">
                                    <?php echo htmlspecialchars($row['account_name']); ?>
                                </div>
                                
                                <!-- Kolom 6: Aksi (View/Edit/Delete) -->
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary view-template" 
                                            style="border-radius: 4px; padding: 0.35rem 0.6rem;"
                                            data-id="<?php echo $row['id']; ?>" 
                                            data-template-name="<?php echo htmlspecialchars($row['template_name']); ?>"
                                            data-message-content="<?php echo htmlspecialchars($row['message_content']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#viewTemplateModal" title="Lihat Penuh">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm" 
                                            style="background: rgba(0, 56, 255, 0.05); color: var(--accent); border: 1px solid rgba(0, 56, 255, 0.1); border-radius: 4px; padding: 0.35rem 0.6rem;"
                                            data-id="<?php echo $row['id']; ?>" 
                                            data-whatsapp-number-id="<?php echo $row['whatsapp_number_id']; ?>"
                                            data-template-name="<?php echo htmlspecialchars($row['template_name']); ?>" 
                                            data-message-content="<?php echo htmlspecialchars($row['message_content']); ?>"
                                            data-automation-list-id="<?php echo $row['automation_list_id'] ?? ''; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editTemplateModal" title="Edit Template">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger delete-template" 
                                            style="border-radius: 4px; padding: 0.35rem 0.6rem;"
                                            data-id="<?php echo $row['id']; ?>" 
                                            data-template-name="<?php echo htmlspecialchars($row['template_name']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteTemplateModal" title="Hapus">
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
                    <!-- Empty State -->
                    <div class="alert mt-3" style="border-radius: 4px; border: 1px dashed var(--border-color); background: transparent; color: var(--ink-muted); text-align: center; padding: 4rem 1rem;">
                        <i class="bi bi-file-earmark-break" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--ink);"></i>
                        <p class="mb-0 font-mono" style="font-size: 1rem; font-weight: 500;">Arsip template kosong.</p>
                        <p class="font-mono text-muted mt-2" style="font-size: 0.85rem;">Klik "Buat Template Baru" untuk menyusun pesan standar Anda.</p>
                    </div>
                <?php endif; ?>
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