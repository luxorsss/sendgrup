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
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content utilitarian-page">
            
            <style>
                .utilitarian-page .row-list {
                    display: flex;
                    flex-direction: column;
                }
                .utilitarian-page .row-item {
                    display: grid;
                    grid-template-columns: 2fr 1.5fr 2.5fr 1.5fr auto;
                    align-items: center;
                    gap: 1.5rem;
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
                
                /* Utilitarian Form Elements */
                .utilitarian-form-control {
                    border-radius: 4px;
                    border: 1px solid var(--border-color);
                    padding: 0.75rem 1rem;
                    font-family: 'Satoshi', sans-serif;
                    background-color: transparent;
                    transition: border-color 150ms var(--ease-out);
                }
                .utilitarian-form-control:focus {
                    border-color: var(--ink);
                    outline: none;
                    box-shadow: 3px 3px 0px rgba(10,10,10,0.1);
                }
                
                /* Modal Polish */
                .modal-content {
                    border-radius: 4px;
                    border: 1px solid var(--border-color);
                    box-shadow: 10px 10px 0px rgba(10,10,10,0.1); /* Brutalist shadow for modal */
                }
                .modal-header {
                    border-bottom: 2px solid var(--ink);
                    padding: 1.5rem;
                }
                .modal-title {
                    font-family: 'Clash Display', sans-serif;
                    font-weight: 600;
                    letter-spacing: -0.01em;
                }
                .modal-footer {
                    border-top: 1px solid var(--border-color);
                }
                .checklist-container {
                    border: 1px solid var(--border-color);
                    border-radius: 4px;
                    padding: 1rem;
                    max-height: 280px;
                    overflow-y: auto;
                    background: transparent;
                }
                .form-check-label {
                    cursor: pointer;
                }
                .badge-count {
                    background: var(--ink);
                    color: var(--surface);
                    border-radius: 2px;
                    font-family: 'Geist Mono', monospace;
                    padding: 3px 6px;
                    margin-left: 6px;
                    font-size: 0.7rem;
                }
            </style>

            <!-- HEADER EDITORIAL -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-end pt-4 pb-3 mb-4" style="border-bottom: 2px solid var(--ink);">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" style="border-radius: 4px;">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h2 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600;">Group Settings</h1>
                        <span class="font-mono text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">CONFIGURE FOOTERS & PROMOTIONS PER GROUP</span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert" style="background: #FFF1F0; border: 1px solid #FFCCC7; border-radius: 4px; color: #CF1322;">
                    <ul class="mb-0 font-mono" style="font-size: 0.85rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php display_flash_message(); ?>
            
            <!-- FILTER SECTION UTILITARIAN -->
            <div class="p-3 mb-4" style="border: 1px solid var(--border-color); border-radius: 4px; background: transparent;">
                <div style="font-family: 'Geist Mono', monospace; font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <i class="bi bi-funnel"></i> Filter Konfigurasi
                </div>
                <form method="get" action="" class="row g-3">
                    <div class="col-md-5">
                        <label for="account" class="form-label font-mono" style="font-size: 0.75rem; color: var(--ink); font-weight: 600;">WHATSAPP ACCOUNT</label>
                        <select class="form-select utilitarian-form-control" id="account" name="account">
                            <option value="">Semua Akun</option>
                            <?php
                            mysqli_data_seek($accounts_result, 0);
                            while($account = mysqli_fetch_assoc($accounts_result)): 
                            ?>
                                <option value="<?php echo $account['id']; ?>" <?php echo $filter_account == $account['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($account['account_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="search" class="form-label font-mono" style="font-size: 0.75rem; color: var(--ink); font-weight: 600;">PENCARIAN</label>
                        <input type="text" class="form-control utilitarian-form-control" id="search" name="search" placeholder="ID atau Nama Grup..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" style="border-radius: 4px; padding: 0.75rem 1rem;">Filter</button>
                    </div>
                </form>
            </div>
            
            <!-- FORMAT ROW-LIST MENGGUNAKAN QUERY ASLI -->
            <div class="mt-2 mb-5">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <div class="row-list">
                        
                        <!-- Header Baris -->
                        <div class="row-item py-2" style="border-bottom: 2px solid var(--ink); animation: none; opacity: 1; transform: none; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: var(--ink-muted);">
                            <div>Nama Grup</div>
                            <div>Akun WA</div>
                            <div>Modul Promosi</div>
                            <div>Footer / Signature</div>
                            <div class="text-end">Konfigurasi</div>
                        </div>

                        <?php 
                        $delay = 0;
                        while($row = mysqli_fetch_assoc($result)): 
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
                            <!-- Baris Data Cascade -->
                            <div class="row-item" style="animation-delay: <?php echo $delay; ?>ms">
                                
                                <!-- Kolom 1: Nama Grup -->
                                <div>
                                    <div style="font-weight: 600; font-size: 1.05rem; color: var(--ink);">
                                        <?php echo htmlspecialchars($row['group_name']); ?>
                                    </div>
                                </div>
                                
                                <!-- Kolom 2: Akun WA -->
                                <div class="font-mono" style="font-size: 0.85rem; color: var(--ink-muted);">
                                    <?php echo htmlspecialchars($row['account_name']); ?>
                                </div>
                                
                                <!-- Kolom 3: Promotions -->
                                <div style="font-size: 0.9rem;">
                                    <?php if (!empty($promotions)): ?>
                                        <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90%;">
                                            <?php echo htmlspecialchars(implode(", ", $promotions)); ?>
                                        </div>
                                        <span class="badge-count"><?php echo count($promotions); ?> ITEM</span>
                                    <?php else: ?>
                                        <span class="font-mono text-muted" style="border: 1px dashed var(--border-color); padding: 2px 6px; border-radius: 2px; font-size: 0.75rem;">EMPTY</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Kolom 4: Footer -->
                                <div style="font-size: 0.9rem; font-weight: 500;">
                                    <?php if (!empty($row['footer_name'])): ?>
                                        <i class="bi bi-card-text me-1 text-muted"></i> <?php echo htmlspecialchars($row['footer_name']); ?>
                                    <?php else: ?>
                                        <span class="font-mono text-muted" style="border: 1px dashed var(--border-color); padding: 2px 6px; border-radius: 2px; font-size: 0.75rem;">NONE</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Kolom 5: Aksi -->
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-sm btn-primary edit-settings" 
                                            style="border-radius: 4px; padding: 0.4rem 1rem;"
                                            data-group-id="<?php echo $row['group_id']; ?>"
                                            data-group-name="<?php echo htmlspecialchars($row['group_name']); ?>"
                                            data-account-name="<?php echo htmlspecialchars($row['account_name']); ?>"
                                            data-whatsapp-number-id="<?php echo $row['whatsapp_number_id']; ?>"
                                            data-footer-id="<?php echo $row['footer_id']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editSettingsModal">
                                        <i class="bi bi-sliders me-1"></i> Atur
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
                        <i class="bi bi-gear-wide-connected" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--ink);"></i>
                        <p class="mb-0 font-mono" style="font-size: 1rem; font-weight: 500;">Tidak ada grup yang ditemukan.</p>
                        <p class="font-mono text-muted mt-2" style="font-size: 0.85rem;">Tambahkan grup di menu <a href="whatsapp_groups.php" class="text-primary text-decoration-none">WhatsApp Groups</a> terlebih dahulu.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Edit Settings Modal dengan Styling Utilitarian -->
<div class="modal fade" id="editSettingsModal" tabindex="-1" aria-labelledby="editSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editSettingsModalLabel">Konfigurasi Modul Grup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body p-4">
                    <input type="hidden" id="group_id" name="group_id">
                    
                    <div class="mb-4 p-3" style="background: rgba(10,10,10,0.02); border-left: 3px solid var(--ink); border-radius: 0 4px 4px 0;">
                        <div class="font-mono text-muted mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">TARGET:</div>
                        <div style="font-size: 1.1rem; font-weight: 600; color: var(--ink);" id="group_name"></div>
                        <div class="font-mono text-muted mt-1" style="font-size: 0.8rem;"><i class="bi bi-broadcast me-1"></i> <span id="account_name"></span></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label font-mono" style="font-size: 0.85rem; font-weight: 600;"><i class="bi bi-megaphone me-2"></i>MODUL PROMOSI</label>
                        <div class="checklist-container">
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
                                <div class="mb-3 pb-2" style="border-bottom: 1px solid var(--border-color);">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select_all_promotions" style="border-color: var(--ink-muted); cursor: pointer;">
                                        <label class="form-check-label font-mono fw-bold" for="select_all_promotions" style="font-size: 0.85rem;">
                                            [PILIH / HAPUS SEMUA]
                                        </label>
                                    </div>
                                </div>
                                <div class="promotions-list">
                                    <?php while($promotion = mysqli_fetch_assoc($promotions_result)): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input promotion-checkbox" type="checkbox" name="promotion_ids[]" value="<?php echo $promotion['id']; ?>" id="promo_<?php echo $promotion['id']; ?>" style="border-color: var(--ink-muted); cursor: pointer;">
                                            <label class="form-check-label" for="promo_<?php echo $promotion['id']; ?>" style="font-size: 0.95rem;">
                                                <?php echo htmlspecialchars($promotion['promotion_name']); ?> 
                                                <span class="text-muted font-mono" style="font-size: 0.75rem; margin-left: 5px;">(<?php echo htmlspecialchars($promotion['account_name']); ?>)</span>
                                            </label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted font-mono mb-0" style="font-size: 0.85rem;">Tidak ada promosi aktif. <a href="promotions.php" target="_blank" class="text-primary text-decoration-none">Buat Promosi Baru &rarr;</a></p>
                            <?php endif; ?>
                        </div>
                        <div class="font-mono text-muted mt-2" style="font-size: 0.75rem;">* Promosi yang dipilih akan disisipkan ke dalam pesan sesuai urutan pilihan.</div>
                    </div>
                    
                    <div class="mb-2">
                        <label for="footer_id" class="form-label font-mono" style="font-size: 0.85rem; font-weight: 600;"><i class="bi bi-card-text me-2"></i>MODUL FOOTER</label>
                        <select class="form-select utilitarian-form-control" id="footer_id" name="footer_id">
                            <option value="">-- Tanpa Footer --</option>
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
                                    <?php echo htmlspecialchars($footer['footer_name']) . ' (' . htmlspecialchars($footer['account_name']) . ')'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 4px; padding: 0.5rem 1.25rem;">Batal</button>
                    <button type="submit" name="update_settings" class="btn btn-primary" style="border-radius: 4px; padding: 0.5rem 1.5rem; transition: transform 160ms var(--ease-out);" onclick="this.style.transform='scale(0.96)'">Simpan Konfigurasi</button>
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