<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

$edit_mode = false;
$edit_data = ['id' => '', 'whatsapp_number_id' => '', 'one_time_content' => '', 'one_time_image_url' => '', 'schedule_date' => '', 'schedule_time' => ''];
$edit_group_ids = [];

// =======================================================================
// 1. Logika Membatalkan / Menghapus Jadwal Pesan
// =======================================================================
if (isset($_GET['action']) && $_GET['action'] == 'delete_schedule' && isset($_GET['id'])) {
    $schedule_id = intval($_GET['id']);
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "DELETE FROM scheduled_message_groups WHERE scheduled_message_id = $schedule_id");
        mysqli_query($conn, "DELETE FROM scheduled_messages WHERE id = $schedule_id");
        mysqli_commit($conn);
        set_flash_message("success", "✅ Jadwal pesan berhasil dibatalkan.");
    } catch (Exception $e) {
        mysqli_rollback($conn);
        set_flash_message("danger", "Gagal membatalkan jadwal.");
    }
    header("Location: instant_message.php");
    exit;
}

// =======================================================================
// 2. Logika Mengambil Data Jadwal untuk Di-edit (Dimuat kembali ke Form)
// =======================================================================
if (isset($_GET['action']) && $_GET['action'] == 'edit_schedule' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $verify_query = "SELECT sm.*, (SELECT wg.whatsapp_number_id FROM scheduled_message_groups smg JOIN whatsapp_groups wg ON smg.group_id = wg.id WHERE smg.scheduled_message_id = sm.id LIMIT 1) as whatsapp_number_id 
                     FROM scheduled_messages sm WHERE sm.id = $edit_id AND sm.status = 'pending' LIMIT 1";
    $verify_result = mysqli_query($conn, $verify_query);
    
    if (mysqli_num_rows($verify_result) > 0) {
        $edit_mode = true;
        $edit_data = mysqli_fetch_assoc($verify_result);
        
        // Ambil ID grup yang sebelumnya dicentang
        $grp_q = "SELECT group_id FROM scheduled_message_groups WHERE scheduled_message_id = $edit_id";
        $grp_r = mysqli_query($conn, $grp_q);
        while ($g = mysqli_fetch_assoc($grp_r)) {
            $edit_group_ids[] = $g['group_id'];
        }
    }
}

// =======================================================================
// 3. Process form submission for sending instant message OR scheduling
// =======================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['send_message']) || isset($_POST['schedule_message']))) {
    $whatsapp_number_id = clean_input($_POST["whatsapp_number_id"]);
    $group_ids = isset($_POST["group_ids"]) ? $_POST["group_ids"] : [];
    $message_content = $_POST["message_content"]; 
    $image_url = clean_input($_POST["image_url"]);
    $include_promotion = isset($_POST["include_promotion"]) ? 1 : 0;
    $include_footer = isset($_POST["include_footer"]) ? 1 : 0;
    $is_scheduling = isset($_POST["schedule_message"]) ? 1 : 0;
    $schedule_date = isset($_POST["schedule_date"]) ? clean_input($_POST["schedule_date"]) : '';
    $schedule_time = isset($_POST["schedule_time"]) ? clean_input($_POST["schedule_time"]) : '';
    
    // Validation
    $errors = [];
    if (empty($whatsapp_number_id)) $errors[] = "WhatsApp account is required";
    if (empty($group_ids)) $errors[] = "At least one group must be selected";
    if (empty($message_content)) $errors[] = "Message content is required";
    
    // Validasi khusus untuk jadwal
    if ($is_scheduling) {
        if (empty($schedule_date)) $errors[] = "Schedule date is required";
        if (empty($schedule_time)) $errors[] = "Schedule time is required";
        if (!empty($schedule_date) && !empty($schedule_time)) {
            $schedule_datetime = strtotime($schedule_date . ' ' . $schedule_time);
            if ($schedule_datetime <= time()) {
                $errors[] = "Schedule date and time must be in the future";
            }
        }
    }
    
    // If no errors, process the request
    if (empty($errors)) {
        $check_query = "SELECT id, api_key, api_url FROM whatsapp_numbers 
                        WHERE id = $whatsapp_number_id AND user_id = " . $_SESSION['user_id'] . " AND active = 1";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 1) {
            $whatsapp_account = mysqli_fetch_assoc($check_result);
            
            if ($is_scheduling) {
                // 🕒 SIMPAN / UPDATE JADWAL
                mysqli_begin_transaction($conn);
                
                try {
                    // Cek apakah ini mode EDIT (Update) atau BARU (Insert)
                    if (isset($_POST['scheduled_id']) && !empty($_POST['scheduled_id'])) {
                        $scheduled_message_id = intval($_POST['scheduled_id']);
                        $save_query = "UPDATE scheduled_messages SET 
                                        schedule_date = '$schedule_date', 
                                        schedule_time = '$schedule_time', 
                                        one_time_content = '" . mysqli_real_escape_string($conn, $message_content) . "', 
                                        one_time_image_url = " . ($image_url ? "'" . mysqli_real_escape_string($conn, $image_url) . "'" : "NULL") . "
                                       WHERE id = $scheduled_message_id";
                        mysqli_query($conn, $save_query);
                        
                        // Bersihkan relasi grup lama
                        mysqli_query($conn, "DELETE FROM scheduled_message_groups WHERE scheduled_message_id = $scheduled_message_id");
                        $log_message = "✅ Jadwal pesan berhasil diperbarui untuk " . date('d M Y H:i', strtotime($schedule_date . ' ' . $schedule_time));
                        
                    } else {
                        // Insert baru
                        $insert_query = "INSERT INTO scheduled_messages 
                                        (template_id, schedule_date, schedule_time, status, is_one_time, 
                                         one_time_content, one_time_image_url) 
                                        VALUES 
                                        (NULL, '$schedule_date', '$schedule_time', 'pending', 1, 
                                        '" . mysqli_real_escape_string($conn, $message_content) . "', 
                                        " . ($image_url ? "'" . mysqli_real_escape_string($conn, $image_url) . "'" : "NULL") . ")";
                        
                        if (mysqli_query($conn, $insert_query)) {
                            $scheduled_message_id = mysqli_insert_id($conn);
                            $log_message = "✅ Message scheduled successfully for " . date('d M Y H:i', strtotime($schedule_date . ' ' . $schedule_time));
                        } else {
                            throw new Exception("Failed to save scheduled message");
                        }
                    }
                    
                    // Insert group associations (berlaku untuk Insert maupun Update)
                    if (isset($scheduled_message_id)) {
                        foreach ($group_ids as $group_id) {
                            $group_id = clean_input($group_id);
                            // Verify group belongs to the selected WhatsApp account
                            $group_check = "SELECT id FROM whatsapp_groups WHERE id = $group_id AND whatsapp_number_id = $whatsapp_number_id";
                            $group_result = mysqli_query($conn, $group_check);
                            
                            if (mysqli_num_rows($group_result) == 1) {
                                $group_insert = "INSERT INTO scheduled_message_groups (scheduled_message_id, group_id) VALUES ($scheduled_message_id, $group_id)";
                                mysqli_query($conn, $group_insert);
                            }
                        }
                        
                        mysqli_commit($conn);
                        set_flash_message("success", $log_message);
                        header("Location: instant_message.php");
                        exit;
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $errors[] = "Failed to schedule message: " . $e->getMessage();
                }
                
            } else {
                // 🚀 KIRIM SEBAGAI INSTANT MESSAGE
                $api_key = $whatsapp_account['api_key'];
                $api_url = $whatsapp_account['api_url'];
                $success_count = 0;
                $fail_count = 0;
                
                foreach ($group_ids as $group_id) {
                    $group_id = clean_input($group_id);
                    $group_query = "SELECT wg.id, wg.group_name, wg.group_wa_id, gs.footer_id, f.footer_content
                                    FROM whatsapp_groups wg
                                    LEFT JOIN group_settings gs ON wg.id = gs.group_id
                                    LEFT JOIN footers f ON gs.footer_id = f.id AND f.active = 1
                                    WHERE wg.id = ? AND wg.whatsapp_number_id = ?";

                    $stmt = mysqli_prepare($conn, $group_query);
                    mysqli_stmt_bind_param($stmt, "ii", $group_id, $whatsapp_number_id);
                    mysqli_stmt_execute($stmt);
                    $group_result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($group_result) == 1) {
                        $group = mysqli_fetch_assoc($group_result);
                        $promotion_query = "SELECT p.promotion_content FROM group_promotions gp
                                            JOIN promotions p ON gp.promotion_id = p.id AND p.active = 1
                                            WHERE gp.group_id = ? ORDER BY gp.display_order ASC";
                        $stmt_promo = mysqli_prepare($conn, $promotion_query);
                        mysqli_stmt_bind_param($stmt_promo, "i", $group_id);
                        mysqli_stmt_execute($stmt_promo);
                        $promotion_result = mysqli_stmt_get_result($stmt_promo);
                        
                        $full_message = $message_content;
                        $promotion_content = null;
                        $all_promotions = [];
                        $footer_content = null;
                        
                        if ($include_footer && !empty($group['footer_content'])) {
                            $full_message .= "\n\n" . $group['footer_content'];
                            $footer_content = $group['footer_content'];
                        }
                        
                        if ($include_promotion && mysqli_num_rows($promotion_result) > 0) {
                            while ($promo = mysqli_fetch_assoc($promotion_result)) {
                                $all_promotions[] = $promo['promotion_content'];
                            }
                            if (!empty($all_promotions)) {
                                $promotion_content = implode("\n\n", $all_promotions);
                                $full_message .= "\n\n" . $promotion_content;
                            }
                        }
                        
                        $response = send_whatsapp_message($api_url, $api_key, $group['group_wa_id'], $full_message, $image_url);
                        $now = date('Y-m-d H:i:s');
                        
                        if (isset($response['error'])) {
                            $fail_count++;
                            $error_message = mysqli_real_escape_string($conn, substr($response['error'], 0, 255));
                            $history_query = "INSERT INTO message_history 
                                              (whatsapp_number_id, group_id, template_id, message_content, image_url, promotion_content, footer_content, status, error_message, is_instant, sent_at) 
                                              VALUES 
                                              ($whatsapp_number_id, $group_id, NULL, '" . mysqli_real_escape_string($conn, $message_content) . "', " . ($image_url ? "'" . mysqli_real_escape_string($conn, $image_url) . "'" : "NULL") . ", " . ($promotion_content ? "'" . mysqli_real_escape_string($conn, $promotion_content) . "'" : "NULL") . ", " . ($footer_content ? "'" . mysqli_real_escape_string($conn, $footer_content) . "'" : "NULL") . ", 'failed', '$error_message', 1, '$now')";
                            mysqli_query($conn, $history_query);
                        } else {
                            $success_count++;
                            $history_query = "INSERT INTO message_history 
                                              (whatsapp_number_id, group_id, template_id, message_content, image_url, promotion_content, footer_content, status, is_instant, sent_at) 
                                              VALUES 
                                              ($whatsapp_number_id, $group_id, NULL, '" . mysqli_real_escape_string($conn, $message_content) . "', " . ($image_url ? "'" . mysqli_real_escape_string($conn, $image_url) . "'" : "NULL") . ", " . ($promotion_content ? "'" . mysqli_real_escape_string($conn, $promotion_content) . "'" : "NULL") . ", " . ($footer_content ? "'" . mysqli_real_escape_string($conn, $footer_content) . "'" : "NULL") . ", 'sent', 1, '$now')";
                            mysqli_query($conn, $history_query);
                        }
                    }
                }
                
                if ($success_count > 0) {
                    $message = "Message sent successfully to $success_count group(s)";
                    if ($fail_count > 0) $message .= " and failed to send to $fail_count group(s).";
                    else $message .= ".";
                    set_flash_message("success", $message);
                } else {
                    set_flash_message("danger", "Failed to send message to all selected groups.");
                }
                
                header("Location: instant_message.php");
                exit;
            }
        } else {
            $errors[] = "Invalid WhatsApp account";
        }
    }
}

// Get all WhatsApp accounts for the current user
$accounts_query = "SELECT id, account_name FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . " AND active = 1 ORDER BY account_name ASC";
$accounts_result = mysqli_query($conn, $accounts_query);

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <?php include('../includes/sidebar.php'); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content utilitarian-form">
            
            <style>
                .utilitarian-form .form-control,
                .utilitarian-form .form-select {
                    border-radius: 4px;
                    border: 1px solid var(--border-color);
                    padding: 0.85rem 1rem;
                    font-family: 'Satoshi', sans-serif;
                    background-color: transparent;
                    transition: border-color 150ms var(--ease-out), box-shadow 150ms var(--ease-out);
                }
                .utilitarian-form .form-control:focus,
                .utilitarian-form .form-select:focus {
                    border-color: var(--ink);
                    box-shadow: 3px 3px 0px rgba(10,10,10,0.1); 
                    outline: none;
                }
                .utilitarian-form .form-label {
                    font-weight: 600;
                    font-size: 0.9rem;
                    margin-bottom: 0.5rem;
                    color: var(--ink);
                    text-transform: uppercase;
                    letter-spacing: 0.03em;
                    font-family: 'Geist Mono', monospace;
                }
                .utilitarian-form .form-check-input {
                    border-color: var(--ink-muted);
                    cursor: pointer;
                }
                .utilitarian-form .form-check-input:checked {
                    background-color: var(--ink);
                    border-color: var(--ink);
                }
                .utilitarian-form .form-check-label {
                    cursor: pointer;
                    user-select: none;
                }
                .section-divider {
                    border-top: 2px solid var(--ink);
                    margin: 2.5rem 0 1.5rem 0;
                    padding-top: 1rem;
                }
            </style>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-end pt-4 pb-3 mb-4" style="border-bottom: 2px solid var(--ink);">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" style="border-radius: 4px;">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h2 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600;">Compose Message</h1>
                        <span class="font-mono text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">INSTANT TRANSMISSION PROTOCOL</span>
                    </div>
                </div>
            </div>
            
            <?php if (mysqli_num_rows($accounts_result) == 0): ?>
                <div class="alert mt-3" style="border-radius: 4px; border: 1px dashed var(--border-color); background: transparent; color: var(--ink-muted); text-align: center; padding: 3rem 1rem;">
                    <i class="bi bi-phone-vibrate" style="font-size: 2rem; display: block; margin-bottom: 1rem; color: var(--ink);"></i>
                    <p class="mb-0 font-mono">Koneksi WhatsApp tidak ditemukan.</p>
                    <a href="whatsapp_accounts.php" class="font-mono text-primary text-decoration-none" style="font-size: 0.85rem; font-weight: 600;">TAMBAHKAN AKUN SEKARANG &rarr;</a>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert" style="background: #FFF1F0; border: 1px solid #FFCCC7; border-radius: 4px; color: #CF1322;">
                        <ul class="mb-0 font-mono" style="font-size: 0.85rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" id="instantMessageForm" style="max-width: 800px;">
                    <input type="hidden" name="scheduled_id" value="<?php echo $edit_mode ? $edit_data['id'] : ''; ?>">
                    
                    <div class="row mb-4">
                        <div class="col-md-5 mb-3 mb-md-0">
                            <label for="whatsapp_number_id" class="form-label"><i class="bi bi-broadcast me-2"></i>Pengirim (Sender)</label>
                            <select class="form-select" id="whatsapp_number_id" name="whatsapp_number_id" required>
                                <option value="">Pilih Akun WhatsApp...</option>
                                <?php
                                mysqli_data_seek($accounts_result, 0);
                                while($account = mysqli_fetch_assoc($accounts_result)): 
                                ?>
                                    <option value="<?php echo $account['id']; ?>" <?php echo ($edit_mode && $edit_data['whatsapp_number_id'] == $account['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($account['account_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label for="group_selection" class="form-label"><i class="bi bi-people me-2"></i>Target Grup</label>
                            <div id="group_container" class="p-3" style="border: 1px solid var(--border-color); border-radius: 4px; background: transparent; min-height: 52px; display: flex; align-items: center;">
                                <p class="text-muted mb-0 font-mono" style="font-size: 0.85rem;">Pilih pengirim terlebih dahulu...</p>
                            </div>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-end mb-2">
                            <label for="message_content" class="form-label mb-0"><i class="bi bi-chat-text me-2"></i>Isi Pesan</label>
                            <div class="font-mono text-muted d-none d-sm-flex gap-3" style="font-size: 0.75rem;">
                                <span>*<b>Tebal</b>*</span>
                                <span>_<i>Miring</i>_</span>
                                <span>~<del>Coret</del>~</span>
                                <span>Ctrl+B / Ctrl+I</span>
                            </div>
                        </div>
                        <textarea class="form-control" id="message_content" name="message_content" rows="8" required placeholder="Ketik pesan Anda di sini..."><?php echo $edit_mode ? htmlspecialchars($edit_data['one_time_content'] ?? '') : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="image_url" class="form-label"><i class="bi bi-image me-2"></i>Lampiran Gambar (Opsional)</label>
                        <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://domain.com/gambar.jpg" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['one_time_image_url'] ?? '') : ''; ?>">
                        <div class="font-mono text-muted mt-2" style="font-size: 0.75rem;">Harus berupa URL langsung berakhiran .jpg, .png, dsb.</div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-2">
                            <div class="form-check p-3" style="border: 1px solid var(--border-color); border-radius: 4px; transition: background 150ms ease;">
                                <input class="form-check-input ms-1" type="checkbox" id="include_promotion" name="include_promotion" value="1" checked>
                                <label class="form-check-label fw-bold ms-2" for="include_promotion">Sisipkan Promosi</label>
                                <div class="font-mono text-muted ms-4 ps-2 mt-1" style="font-size: 0.75rem;">Jika diatur untuk grup ini</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="form-check p-3" style="border: 1px solid var(--border-color); border-radius: 4px; transition: background 150ms ease;">
                                <input class="form-check-input ms-1" type="checkbox" id="include_footer" name="include_footer" value="1" checked>
                                <label class="form-check-label fw-bold ms-2" for="include_footer">Sisipkan Footer</label>
                                <div class="font-mono text-muted ms-4 ps-2 mt-1" style="font-size: 0.75rem;">Signature / Penutup pesan</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section-divider"></div>

                    <div class="mb-4">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="schedule_message" name="schedule_message" value="1" <?php echo $edit_mode ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold font-mono" for="schedule_message" style="color: var(--accent);">
                                <i class="bi bi-clock-history me-1"></i> JADWALKAN UNTUK NANTI
                            </label>
                        </div>

                        <div id="schedule_fields" class="p-3 mb-4" style="display: <?php echo $edit_mode ? 'block' : 'none'; ?>; background: rgba(0, 56, 255, 0.03); border: 1px solid rgba(0, 56, 255, 0.2); border-radius: 4px;">
                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="schedule_date" class="form-label" style="color: var(--accent);">Tanggal Eksekusi</label>
                                    <input type="date" class="form-control" id="schedule_date" name="schedule_date" min="<?php echo date('Y-m-d'); ?>" style="border-color: rgba(0, 56, 255, 0.3);" value="<?php echo $edit_mode ? $edit_data['schedule_date'] : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="schedule_time" class="form-label" style="color: var(--accent);">Waktu Eksekusi</label>
                                    <input type="time" class="form-control" id="schedule_time" name="schedule_time" style="border-color: rgba(0, 56, 255, 0.3);" value="<?php echo $edit_mode ? $edit_data['schedule_time'] : ''; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-5">
                        <?php if($edit_mode): ?>
                            <a href="instant_message.php" class="btn btn-outline-secondary" style="padding: 1rem 1.5rem;"><i class="bi bi-x-lg"></i> Batal Edit</a>
                        <?php else: ?>
                            <div></div> <?php endif; ?>
                        
                        <button type="submit" name="send_message" class="btn btn-primary" id="submit_button" style="padding: 1rem 2.5rem; font-size: 1.1rem;">
                            KIRIM SEKARANG &rarr;
                        </button>
                    </div>
                </form>

                <?php
                $scheduled_query = "SELECT sm.*, wn.account_name, GROUP_CONCAT(wg.group_name SEPARATOR ', ') AS target_groups
                                    FROM scheduled_messages sm
                                    JOIN scheduled_message_groups smg ON sm.id = smg.scheduled_message_id
                                    JOIN whatsapp_groups wg ON smg.group_id = wg.id
                                    JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id
                                    WHERE wn.user_id = " . $_SESSION['user_id'] . " 
                                    AND sm.status = 'pending' 
                                    AND sm.is_one_time = 1
                                    GROUP BY sm.id, wn.account_name ORDER BY sm.schedule_date ASC, sm.schedule_time ASC";
                $scheduled_result = mysqli_query($conn, $scheduled_query);
                ?>

                <div class="section-divider"></div>
                <div class="mb-5">
                    <div class="mb-3">
                        <h3 class="h4 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600;">Antrean Pesan Terjadwal</h3>
                        <span class="font-mono text-muted" style="font-size: 0.8rem; display: block; margin-top: 3px;">PENDING TRANSMISSION QUEUE</span>
                    </div>

                    <?php if (mysqli_num_rows($scheduled_result) == 0): ?>
                        <div class="p-4 text-center font-mono text-muted" style="border: 1px dashed var(--border-color); border-radius: 4px; font-size: 0.85rem;">
                            Tidak ada pesan yang sedang mengantre saat ini.
                        </div>
                    <?php else: ?>
                        <div style="border-top: 2px solid var(--ink);">
                            <?php while($s_row = mysqli_fetch_assoc($scheduled_result)): ?>
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center py-3 gap-3" style="border-bottom: 1px solid var(--border-color);">
                                    <div style="flex: 1;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-dark font-mono" style="font-size: 0.72rem; border-radius: 2px; padding: 0.2rem 0.4rem;"><?php echo htmlspecialchars($s_row['account_name']); ?></span>
                                            <span class="font-mono text-primary fw-bold" style="font-size: 0.85rem;">
                                                <i class="bi bi-calendar-event me-1"></i> <?php echo date('d M Y', strtotime($s_row['schedule_date'])); ?> @ <?php echo date('H:i', strtotime($s_row['schedule_time'])); ?>
                                            </span>
                                        </div>
                                        <p class="mb-1 fw-semibold text-truncate" style="max-width: 550px; font-family: 'Satoshi', sans-serif; font-size: 0.95rem; color: var(--ink);">
                                            <?php echo htmlspecialchars($s_row['one_time_content'] ?? ''); ?>
                                        </p>
                                        <small class="font-mono text-muted" style="font-size: 0.75rem;">
                                            <i class="bi bi-arrow-right-short"></i> Grup Target: <?php echo htmlspecialchars($s_row['target_groups']); ?>
                                        </small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="instant_message.php?action=edit_schedule&id=<?php echo $s_row['id']; ?>" class="btn btn-sm btn-outline-dark font-mono" style="border-radius: 4px; font-size: 0.78rem; padding: 0.4rem 0.8rem;">
                                            <i class="bi bi-pencil-square"></i> EDIT
                                        </a>
                                        <a href="instant_message.php?action=delete_schedule&id=<?php echo $s_row['id']; ?>" class="btn btn-sm btn-outline-danger font-mono" onclick="return confirm('Batalkan jadwal pengiriman ini?')" style="border-radius: 4px; font-size: 0.78rem; padding: 0.4rem 0.8rem;">
                                            <i class="bi bi-trash"></i> BATAL
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    // Ambil target ID grup dari PHP (dipakai saat Edit Mode)
    const targetGroupIds = <?php echo json_encode($edit_group_ids); ?>;

    // Load groups based on selected account
    document.getElementById('whatsapp_number_id').addEventListener('change', function() {
        const accountId = this.value;
        const groupContainer = document.getElementById('group_container');
        
        if (!accountId) {
            groupContainer.innerHTML = '<p class="text-center mb-0">Please select a WhatsApp account first</p>';
            return;
        }
        
        groupContainer.innerHTML = '<p class="text-center mb-0"><i class="bi bi-hourglass-split me-2"></i>Loading groups...</p>';
        
        fetch(`get_groups.php?account_id=${accountId}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    groupContainer.innerHTML = '<p class="text-center mb-0">No groups found for this account. <a href="whatsapp_groups.php">Add Groups</a></p>';
                    return;
                }
                
                let html = '<div class="row">';
                html += '<div class="col-12 mb-2"><div class="form-check">';
                html += '<input class="form-check-input" type="checkbox" id="select_all_groups">';
                html += '<label class="form-check-label fw-bold" for="select_all_groups">Select All Groups</label>';
                html += '</div></div>';
                
                data.forEach(group => {
                    // Cek otomatis jika ID grup ada di dalam array targetGroupIds (Mode Edit)
                    const isChecked = targetGroupIds.includes(String(group.id)) || targetGroupIds.includes(Number(group.id)) ? 'checked' : '';
                    html += '<div class="col-md-6 mb-2"><div class="form-check">';
                    html += `<input class="form-check-input group-checkbox" type="checkbox" name="group_ids[]" value="${group.id}" id="group_${group.id}" ${isChecked}>`;
                    html += `<label class="form-check-label" for="group_${group.id}">${group.group_name}</label>`;
                    html += '</div></div>';
                });
                html += '</div>';
                
                groupContainer.innerHTML = html;
                
                document.getElementById('select_all_groups').addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.group-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            })
            .catch(error => {
                console.error('Error fetching groups:', error);
                groupContainer.innerHTML = '<p class="text-center mb-0 text-danger">Error loading groups. Please try again.</p>';
            });
    });
    
    // Form validation before submit
    document.getElementById('instantMessageForm').addEventListener('submit', function(e) {
        const groupCheckboxes = document.querySelectorAll('input[name="group_ids[]"]:checked');
        const isScheduling = document.getElementById('schedule_message').checked;

        if (groupCheckboxes.length === 0) {
            e.preventDefault();
            alert('Please select at least one group to send the message to.');
            return;
        }

        if (isScheduling) {
            const scheduleDate = document.getElementById('schedule_date').value;
            const scheduleTime = document.getElementById('schedule_time').value;

            if (!scheduleDate || !scheduleTime) {
                e.preventDefault();
                alert('Please select both date and time for scheduled message.');
                return;
            }
            const scheduleDateTime = new Date(scheduleDate + 'T' + scheduleTime);
            if (scheduleDateTime <= new Date()) {
                e.preventDefault();
                alert('Schedule date and time must be in the future.');
                return;
            }
        }
    });
    
    // Toggle schedule fields
    document.getElementById('schedule_message').addEventListener('change', function() {
        const scheduleFields = document.getElementById('schedule_fields');
        scheduleFields.style.display = this.checked ? 'block' : 'none';

        if (this.checked && !this.dataset.edited) {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('schedule_date').min = today;
        }
    });
    
    // Update button text based on schedule selection
    document.getElementById('schedule_message').addEventListener('change', function() {
        const submitButton = document.getElementById('submit_button');
        if (this.checked) {
            submitButton.innerHTML = '<i class="bi bi-calendar-check"></i> <?php echo $edit_mode ? 'Update Schedule' : 'Schedule Message'; ?>';
            submitButton.name = 'schedule_message';
        } else {
            submitButton.innerHTML = '<i class="bi bi-send"></i> Send Message Now';
            submitButton.name = 'send_message';
        }
    });
    
    // Fungsi untuk menambahkan format ke textarea
    function wrapText(textarea, start, end) {
        const startCursor = textarea.selectionStart;
        const endCursor = textarea.selectionEnd;
        const selectedText = textarea.value.substring(startCursor, endCursor);
        const newText = start + selectedText + end;
        textarea.value = textarea.value.substring(0, startCursor) + newText + textarea.value.substring(endCursor);
        const newCursorPos = startCursor + start.length + selectedText.length;
        textarea.setSelectionRange(newCursorPos, newCursorPos);
        textarea.focus();
    }

    // Nangkep keyboard shortcut
    document.getElementById('message_content').addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) { 
            switch(e.key.toLowerCase()) {
                case 'b': e.preventDefault(); wrapText(this, '*', '*'); break;
                case 'i': e.preventDefault(); wrapText(this, '_', '_'); break;
                case 'u': e.preventDefault(); wrapText(this, '~', '~'); break;
            }
        }
    });

    // Auto-trigger on page load if edit mode is active
    document.addEventListener("DOMContentLoaded", function() {
        const scheduleCb = document.getElementById('schedule_message');
        if (scheduleCb && scheduleCb.checked) {
            scheduleCb.dispatchEvent(new Event('change'));
        }

        const senderSelect = document.getElementById('whatsapp_number_id');
        if (senderSelect && senderSelect.value !== "") {
            senderSelect.dispatchEvent(new Event('change'));
        }
    });
</script>

<?php include('../includes/footer.php'); ?>