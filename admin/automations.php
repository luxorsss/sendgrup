<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();
$user_id = $_SESSION['user_id'];

// ==========================================
// PROSES FORM SUBMISSION
// ==========================================

// 1. Tambah Kelompok Baru
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_list'])) {
    $list_name = clean_input($_POST["list_name"]);
    if (!empty($list_name)) {
        $query = "INSERT INTO automation_lists (user_id, list_name, is_active) VALUES ('$user_id', '$list_name', 1)";
        if (mysqli_query($conn, $query)) {
            set_flash_message("success", "Kelompok pesan berantai berhasil ditambahkan!");
        } else {
            set_flash_message("danger", "Error: " . mysqli_error($conn));
        }
        header("Location: automations.php");
        exit;
    }
}

// 2. Hapus Kelompok
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_list'])) {
    $id = clean_input($_POST["id"]);
    $check = mysqli_query($conn, "SELECT id FROM automation_lists WHERE id = $id AND user_id = $user_id");
    if (mysqli_num_rows($check) == 1) {
        if (mysqli_query($conn, "DELETE FROM automation_lists WHERE id = $id")) {
            set_flash_message("success", "Kelompok berhasil dihapus!");
        }
    }
    header("Location: automations.php");
    exit;
}

// 3. Toggle Status ON/OFF
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_status'])) {
    $id = clean_input($_POST["id"]);
    $current_status = clean_input($_POST["current_status"]);
    $new_status = ($current_status == 1) ? 0 : 1;
    
    $check = mysqli_query($conn, "SELECT id FROM automation_lists WHERE id = $id AND user_id = $user_id");
    if (mysqli_num_rows($check) == 1) {
        mysqli_query($conn, "UPDATE automation_lists SET is_active = $new_status WHERE id = $id");
        $status_text = $new_status == 1 ? "diaktifkan" : "dimatikan";
        set_flash_message("success", "Status kelompok berhasil $status_text!");
    }
    header("Location: automations.php");
    exit;
}

// 4. Update / Manage Kelompok (Grup & Jadwal)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['manage_list'])) {
    $list_id = clean_input($_POST["list_id"]);
    $list_name = clean_input($_POST["list_name"]);
    $group_ids = isset($_POST["group_ids"]) ? $_POST["group_ids"] : [];
    $days = isset($_POST["days"]) ? $_POST["days"] : [];
    $send_time = clean_input($_POST["send_time"]);
    
    mysqli_query($conn, "UPDATE automation_lists SET list_name = '$list_name' WHERE id = $list_id AND user_id = $user_id");
    
    mysqli_query($conn, "DELETE FROM automation_groups WHERE automation_list_id = $list_id");
    foreach ($group_ids as $gid) {
        $gid = clean_input($gid);
        mysqli_query($conn, "INSERT INTO automation_groups (automation_list_id, group_id) VALUES ($list_id, $gid)");
    }
    
    mysqli_query($conn, "DELETE FROM automation_schedules WHERE automation_list_id = $list_id");
    if(!empty($send_time)) {
        foreach ($days as $day) {
            $day = clean_input($day);
            mysqli_query($conn, "INSERT INTO automation_schedules (automation_list_id, send_day, send_time) VALUES ($list_id, '$day', '$send_time')");
        }
    }
    
    set_flash_message("success", "Pengaturan kelompok dan jadwal berhasil diperbarui!");
    header("Location: automations.php");
    exit;
}

// 5. Tandai Riwayat Manual (DIPERBARUI JADI SISTEM SYNC)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_manual_log'])) {
    $list_id = clean_input($_POST["list_id"]);
    $group_id = clean_input($_POST["group_id"]);
    $template_ids = isset($_POST["template_ids"]) ? $_POST["template_ids"] : [];
    
    if(empty($group_id)) {
        set_flash_message("warning", "Grup tujuan harus dipilih.");
    } else {
        // Hapus semua log lama untuk grup ini di kelompok ini
        mysqli_query($conn, "DELETE FROM automation_logs WHERE automation_list_id=$list_id AND group_id=$group_id");
        
        $success_count = 0;
        // Jika ada yang dicentang, masukkan ulang ke database
        if(!empty($template_ids)) {
            foreach ($template_ids as $tid) {
                $tid = clean_input($tid);
                mysqli_query($conn, "INSERT INTO automation_logs (automation_list_id, group_id, template_id) VALUES ($list_id, $group_id, $tid)");
                $success_count++;
            }
        }
        
        set_flash_message("success", "Riwayat grup berhasil diperbarui! $success_count pesan ditandai.");
    }
    header("Location: automations.php");
    exit;
}

// 6. Reset Riwayat
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_logs'])) {
    $list_id = clean_input($_POST["list_id"]);
    mysqli_query($conn, "DELETE FROM automation_logs WHERE automation_list_id = $list_id");
    set_flash_message("info", "Riwayat pengiriman kelompok berhasil di-reset. Semua grup akan mulai menerima dari awal.");
    header("Location: automations.php");
    exit;
}

// ==========================================
// AMBIL DATA KEBUTUHAN KONTEN
// ==========================================

$all_groups_query = "SELECT wg.id, wg.group_name, wn.account_name 
                     FROM whatsapp_groups wg 
                     JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id 
                     WHERE wn.user_id = $user_id ORDER BY wn.account_name, wg.group_name";
$all_groups_result = mysqli_query($conn, $all_groups_query);

$groups_by_account = [];
while($g = mysqli_fetch_assoc($all_groups_result)) {
    $acc = $g['account_name'];
    if(!isset($groups_by_account[$acc])) {
        $groups_by_account[$acc] = [];
    }
    $groups_by_account[$acc][] = $g;
}

$lists_query = "SELECT * FROM automation_lists WHERE user_id = $user_id ORDER BY created_at DESC";
$lists_result = mysqli_query($conn, $lists_query);
$automation_lists_data = [];
while($row = mysqli_fetch_assoc($lists_result)) {
    $automation_lists_data[] = $row;
}

// ==========================================
// AMBIL DATA LOGS UNTUK JAVASCRIPT (PRE-CHECK)
// ==========================================
$all_logs_query = "
    SELECT al.automation_list_id, al.group_id, al.template_id 
    FROM automation_logs al
    JOIN automation_lists list ON al.automation_list_id = list.id
    WHERE list.user_id = $user_id
";
$all_logs_result = mysqli_query($conn, $all_logs_query);
$logs_data = [];
while($log = mysqli_fetch_assoc($all_logs_result)) {
    // Membuat kunci unik misal: "ListID_GroupID" -> [TemplateID1, TemplateID2]
    $key = $log['automation_list_id'] . '_' . $log['group_id'];
    if(!isset($logs_data[$key])) {
        $logs_data[$key] = [];
    }
    $logs_data[$key][] = $log['template_id'];
}

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
                    grid-template-columns: 2fr 1fr 2.5fr 1fr auto;
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
                .status-toggle-btn {
                    padding: 0.35rem 0.85rem;
                    border-radius: 4px;
                    font-family: 'Geist Mono', monospace;
                    font-size: 0.75rem;
                    font-weight: 600;
                    letter-spacing: 0.05em;
                    transition: transform 150ms var(--ease-out), background 150ms ease;
                }
                .status-toggle-btn:active {
                    transform: scale(0.95);
                }
                .status-on {
                    background: #E8F5E9;
                    color: #2E7D32;
                    border: 1px solid #A5D6A7;
                }
                .status-off {
                    background: transparent;
                    color: var(--ink-muted);
                    border: 1px dashed var(--border-color);
                }
            </style>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-end pt-4 pb-3 mb-4" style="border-bottom: 2px solid var(--ink);">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" style="border-radius: 4px;">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h2 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600;">Automation Engine</h1>
                        <span class="font-mono text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">MANAGE SEQUENCES & SCHEDULES</span>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addListModal" style="border-radius: 4px; padding: 0.6rem 1.25rem;">
                        <i class="bi bi-plus-lg me-1"></i> Buat Kelompok
                    </button>
                </div>
            </div>
            
            <?php display_flash_message(); ?>
            
            <div class="mt-2 mb-5">
                <?php if (count($automation_lists_data) > 0): ?>
                    <div class="row-list">
                        <div class="row-item py-2" style="border-bottom: 2px solid var(--ink); animation: none; opacity: 1; transform: none; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: var(--ink-muted);">
                            <div>Nama Kelompok</div>
                            <div>Total Grup</div>
                            <div>Jadwal Operasi</div>
                            <div>Status Mesin</div>
                            <div class="text-end">Konfigurasi</div>
                        </div>

                        <?php 
                        $delay = 0;
                        foreach($automation_lists_data as $row): 
                            $list_id = $row['id'];
                            
                            $gc_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM automation_groups WHERE automation_list_id = $list_id");
                            $group_count = mysqli_fetch_assoc($gc_res)['c'];
                            
                            $sch_res = mysqli_query($conn, "SELECT send_day, send_time FROM automation_schedules WHERE automation_list_id = $list_id");
                            $days_indo = ['Monday'=>'Sen', 'Tuesday'=>'Sel', 'Wednesday'=>'Rab', 'Thursday'=>'Kam', 'Friday'=>'Jum', 'Saturday'=>'Sab', 'Sunday'=>'Min'];
                            $selected_days = [];
                            $send_time = '';
                            while($sch = mysqli_fetch_assoc($sch_res)) {
                                $selected_days[] = $days_indo[$sch['send_day']];
                                $send_time = date('H:i', strtotime($sch['send_time']));
                            }
                            // Memformat jadwal dengan gaya monospace
                            $schedule_text = empty($selected_days) ? '<span class="text-muted" style="border: 1px dashed var(--border-color); padding: 2px 6px; border-radius: 2px;">NOT_SET</span>' : '<span style="color: var(--accent);">['.implode(",", $selected_days).']</span> @ '.$send_time;
                        ?>
                            <div class="row-item" style="animation-delay: <?php echo $delay; ?>ms">
                                
                                <div>
                                    <div style="font-weight: 600; font-size: 1.05rem; color: var(--ink);">
                                        <?php echo htmlspecialchars($row['list_name']); ?>
                                    </div>
                                    <div class="font-mono text-muted" style="font-size: 0.75rem; margin-top: 4px;">ID: SEQ_00<?php echo $list_id; ?></div>
                                </div>
                                
                                <div>
                                    <span class="font-mono" style="background: rgba(10,10,10,0.05); padding: 4px 8px; border-radius: 2px; font-size: 0.85rem; color: var(--ink);">
                                        <?php echo $group_count; ?> TARGET
                                    </span>
                                </div>
                                
                                <div class="font-mono" style="font-size: 0.85rem;">
                                    <?php echo $schedule_text; ?>
                                </div>
                                
                                <div>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="id" value="<?php echo $list_id; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $row['is_active']; ?>">
                                        <?php if ($row['is_active'] == 1): ?>
                                            <button type="submit" name="toggle_status" class="status-toggle-btn status-on" title="Matikan Mesin">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> ONLINE
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="toggle_status" class="status-toggle-btn status-off" title="Hidupkan Mesin">
                                                <i class="bi bi-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> OFFLINE
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                                
                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                            style="border-radius: 4px; padding: 0.35rem 0.6rem;"
                                            data-bs-toggle="modal" data-bs-target="#manualLogModal_<?php echo $list_id; ?>" title="Tandai Riwayat">
                                        <i class="bi bi-journal-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm" 
                                            style="background: rgba(0, 56, 255, 0.05); color: var(--accent); border: 1px solid rgba(0, 56, 255, 0.1); border-radius: 4px; padding: 0.35rem 0.6rem;"
                                            data-bs-toggle="modal" data-bs-target="#manageModal_<?php echo $list_id; ?>" title="Kelola">
                                        <i class="bi bi-sliders"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            style="border-radius: 4px; padding: 0.35rem 0.6rem;"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal_<?php echo $list_id; ?>" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php 
                        $delay += 40; 
                        endforeach; 
                        ?>
                    </div>
                <?php else: ?>
                    <div class="alert mt-3" style="border-radius: 4px; border: 1px dashed var(--border-color); background: transparent; color: var(--ink-muted); text-align: center; padding: 3rem 1rem;">
                        <i class="bi bi-robot" style="font-size: 2rem; display: block; margin-bottom: 1rem; color: var(--ink);"></i>
                        <p class="mb-0 font-mono">Mesin automasi belum dikonfigurasi.</p>
                        <p class="font-mono text-muted" style="font-size: 0.85rem;">Klik "Buat Kelompok" untuk menyusun urutan pesan berantai.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="addListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Kelompok Automasi Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kelompok</label>
                        <input type="text" class="form-control" name="list_name" placeholder="Misal: Edukasi Reseller Baru" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_list" class="btn btn-primary">Buat Kelompok</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
foreach($automation_lists_data as $row): 
    $list_id = $row['id'];
    
    $sch_res = mysqli_query($conn, "SELECT send_day, send_time FROM automation_schedules WHERE automation_list_id = $list_id");
    $raw_days = [];
    $send_time = '';
    while($sch = mysqli_fetch_assoc($sch_res)) {
        $raw_days[] = $sch['send_day'];
        $send_time = date('H:i', strtotime($sch['send_time']));
    }
    
    $sel_g_res = mysqli_query($conn, "SELECT group_id FROM automation_groups WHERE automation_list_id = $list_id");
    $selected_groups = [];
    while($sg = mysqli_fetch_assoc($sel_g_res)) {
        $selected_groups[] = $sg['group_id'];
    }

    $list_groups_query = "SELECT wg.id, wg.group_name, wn.account_name FROM automation_groups ag JOIN whatsapp_groups wg ON ag.group_id = wg.id JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id WHERE ag.automation_list_id = $list_id ORDER BY wn.account_name, wg.group_name";
    $list_groups_res = mysqli_query($conn, $list_groups_query);
    
    $list_templates_query = "SELECT id, template_name FROM message_templates WHERE automation_list_id = $list_id ORDER BY template_name ASC";
    $list_templates_res = mysqli_query($conn, $list_templates_query);
?>
    
    <div class="modal fade" id="manualLogModal_<?php echo $list_id; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tandai Riwayat: <?php echo htmlspecialchars($row['list_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="alert alert-info py-2">
                            <small><i class="bi bi-info-circle"></i> Pilih grup di bawah ini. Jika ada template yang tercentang, artinya pesan tersebut <strong>sudah pernah</strong> dikirim.</small>
                        </div>
                        <input type="hidden" name="list_id" value="<?php echo $list_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Grup Tujuan</label>
                            <select name="group_id" class="form-select group-select" data-list-id="<?php echo $list_id; ?>" required>
                                <option value="">-- Pilih Grup Untuk Dilihat --</option>
                                <?php 
                                $current_acc = "";
                                while($lg = mysqli_fetch_assoc($list_groups_res)): 
                                    if($current_acc != $lg['account_name']) {
                                        if($current_acc != "") echo '</optgroup>';
                                        $current_acc = $lg['account_name'];
                                        echo '<optgroup label="Akun: '.htmlspecialchars($current_acc).'">';
                                    }
                                ?>
                                    <option value="<?php echo $lg['id']; ?>"><?php echo htmlspecialchars($lg['group_name']); ?></option>
                                <?php endwhile; if($current_acc != "") echo '</optgroup>'; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Template (Sudah Terkirim)</label>
                            <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto;">
                                <?php 
                                if(mysqli_num_rows($list_templates_res) > 0) {
                                    while($lt = mysqli_fetch_assoc($list_templates_res)): 
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input template-checkbox" type="checkbox" name="template_ids[]" value="<?php echo $lt['id']; ?>" id="lt_<?php echo $list_id.'_'.$lt['id']; ?>">
                                        <label class="form-check-label" for="lt_<?php echo $list_id.'_'.$lt['id']; ?>">
                                            <?php echo htmlspecialchars(html_entity_decode($lt['template_name'], ENT_QUOTES)); ?>
                                        </label>
                                    </div>
                                <?php 
                                    endwhile; 
                                } else {
                                    echo '<small class="text-muted">Belum ada template di kelompok ini.</small>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#resetLogModal_<?php echo $list_id; ?>">Reset Riwayat</button>
                        <div>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="mark_manual_log" class="btn btn-warning">Simpan & Sinkronisasi</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetLogModal_<?php echo $list_id; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Reset Riwayat Pengiriman</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="list_id" value="<?php echo $list_id; ?>">
                        <p>Apakah Anda yakin ingin me-reset seluruh riwayat pengiriman untuk kelompok <strong><?php echo htmlspecialchars($row['list_name']); ?></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="reset_logs" class="btn btn-danger">Ya, Reset Sekarang</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="manageModal_<?php echo $list_id; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kelola: <?php echo htmlspecialchars($row['list_name']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="list_id" value="<?php echo $list_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Kelompok</label>
                            <input type="text" class="form-control" name="list_name" value="<?php echo htmlspecialchars($row['list_name']); ?>" required>
                        </div>
                        
                        <hr>
                        <h6 class="fw-bold mb-3">1. Jadwal Pengiriman Mingguan</h6>
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Pilih Hari</label><br>
                                <?php 
                                $days_indo = ['Monday'=>'Senin', 'Tuesday'=>'Selasa', 'Wednesday'=>'Rabu', 'Thursday'=>'Kamis', 'Friday'=>'Jumat', 'Saturday'=>'Sabtu', 'Sunday'=>'Minggu'];
                                foreach($days_indo as $en => $id_name): 
                                ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="days[]" value="<?php echo $en; ?>" id="day_<?php echo $list_id.'_'.$en; ?>" <?php echo in_array($en, $raw_days) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="day_<?php echo $list_id.'_'.$en; ?>"><?php echo $id_name; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jam Eksekusi</label>
                                <input type="time" class="form-control" name="send_time" value="<?php echo $send_time; ?>">
                            </div>
                        </div>
                        
                        <hr>
                        <h6 class="fw-bold mb-3">2. Target Grup WhatsApp</h6>
                        <div class="border rounded p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                            <?php if(empty($groups_by_account)): ?>
                                <p class="text-muted mb-0">Belum ada grup WhatsApp. Silakan tambah di menu WhatsApp Groups.</p>
                            <?php else: ?>
                                <?php foreach($groups_by_account as $account_name => $groups_list): ?>
                                    <div class="mb-3">
                                        <h6 class="text-primary border-bottom pb-1 mb-2"><i class="bi bi-whatsapp"></i> Akun: <?php echo htmlspecialchars($account_name); ?></h6>
                                        <div class="row">
                                            <?php foreach($groups_list as $g): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="group_ids[]" value="<?php echo $g['id']; ?>" id="g_<?php echo $list_id.'_'.$g['id']; ?>" <?php echo in_array($g['id'], $selected_groups) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="g_<?php echo $list_id.'_'.$g['id']; ?>">
                                                            <?php echo htmlspecialchars($g['group_name']); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="form-text mt-1">Centang grup yang akan dimasukkan ke putaran kelompok pesan ini.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="manage_list" class="btn btn-primary">Simpan Pengaturan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal_<?php echo $list_id; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Hapus Kelompok</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?php echo $list_id; ?>">
                        <p>Apakah Anda yakin ingin menghapus kelompok <strong><?php echo htmlspecialchars($row['list_name']); ?></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="delete_list" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
// Data Log diambil dari Database dan diubah ke Format JSON
const automationLogs = <?php echo json_encode($logs_data); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const groupSelects = document.querySelectorAll('.group-select');
    
    groupSelects.forEach(select => {
        select.addEventListener('change', function() {
            const groupId = this.value;
            const listId = this.getAttribute('data-list-id');
            const modal = document.getElementById('manualLogModal_' + listId);
            
            // 1. Matikan (uncheck) semua centang di modal ini terlebih dahulu
            modal.querySelectorAll('.template-checkbox').forEach(cb => {
                cb.checked = false;
            });
            
            // 2. Cek apakah ada riwayat untuk kombinasi List + Grup ini
            if(groupId && listId) {
                const key = listId + '_' + groupId; // Format kunci: "ListID_GroupID"
                
                // Jika kunci ditemukan di data JSON, centang kotaknya
                if(automationLogs[key]) {
                    automationLogs[key].forEach(templateId => {
                        const checkboxId = 'lt_' + listId + '_' + templateId;
                        const cb = document.getElementById(checkboxId);
                        if(cb) {
                            cb.checked = true; // Centang otomatis!
                        }
                    });
                }
            }
        });
    });
});
</script>

<?php include('../includes/footer.php'); ?>