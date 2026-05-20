<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Process form submission for sending instant message OR scheduling
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
    
    if (empty($whatsapp_number_id)) {
        $errors[] = "WhatsApp account is required";
    }
    
    if (empty($group_ids)) {
        $errors[] = "At least one group must be selected";
    }
    
    if (empty($message_content)) {
        $errors[] = "Message content is required";
    }
    
    // Validasi khusus untuk jadwal
    if ($is_scheduling) {
        if (empty($schedule_date)) {
            $errors[] = "Schedule date is required";
        }
        if (empty($schedule_time)) {
            $errors[] = "Schedule time is required";
        }
        if (!empty($schedule_date) && !empty($schedule_time)) {
            $schedule_datetime = strtotime($schedule_date . ' ' . $schedule_time);
            if ($schedule_datetime <= time()) {
                $errors[] = "Schedule date and time must be in the future";
            }
        }
    }
    
    // If no errors, process the request
    if (empty($errors)) {
        // Check if the WhatsApp number belongs to the current user
        $check_query = "SELECT id, api_key, api_url FROM whatsapp_numbers 
                        WHERE id = $whatsapp_number_id AND user_id = " . $_SESSION['user_id'] . " AND active = 1";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) == 1) {
            $whatsapp_account = mysqli_fetch_assoc($check_result);
            
            if ($is_scheduling) {
                // 🕒 SIMPAN SEBAGAI JADWAL
                mysqli_begin_transaction($conn);
                
                try {
                    // Insert into scheduled_messages
                    $insert_query = "INSERT INTO scheduled_messages 
                                    (template_id, schedule_date, schedule_time, status, is_one_time, 
                                     one_time_content, one_time_image_url) 
                                    VALUES 
                                    (NULL, '$schedule_date', '$schedule_time', 'pending', 1, 
                                    '" . mysqli_real_escape_string($conn, $message_content) . "', 
                                    " . ($image_url ? "'" . mysqli_real_escape_string($conn, $image_url) . "'" : "NULL") . ")";
                    
                    if (mysqli_query($conn, $insert_query)) {
                        $scheduled_message_id = mysqli_insert_id($conn);
                        
                        // Insert group associations
                        foreach ($group_ids as $group_id) {
                            $group_id = clean_input($group_id);
                            
                            // Verify group belongs to the selected WhatsApp account
                            $group_check = "SELECT id FROM whatsapp_groups 
                                           WHERE id = $group_id AND whatsapp_number_id = $whatsapp_number_id";
                            $group_result = mysqli_query($conn, $group_check);
                            
                            if (mysqli_num_rows($group_result) == 1) {
                                $group_insert = "INSERT INTO scheduled_message_groups 
                                                (scheduled_message_id, group_id) 
                                                VALUES 
                                                ($scheduled_message_id, $group_id)";
                                mysqli_query($conn, $group_insert);
                            }
                        }
                        
                        mysqli_commit($conn);
                        set_flash_message("success", "✅ Message scheduled successfully for " . date('d M Y H:i', strtotime($schedule_date . ' ' . $schedule_time)));
                        header("Location: instant_message.php");
                        exit;
                    } else {
                        throw new Exception("Failed to save scheduled message");
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $errors[] = "Failed to schedule message: " . $e->getMessage();
                }
                
            } else {
                // 🚀 KIRIM SEBAGAI INSTANT MESSAGE (kode yang sudah ada)
                $api_key = $whatsapp_account['api_key'];
                $api_url = $whatsapp_account['api_url'];
                
                $success_count = 0;
                $fail_count = 0;
                
                // Process each selected group (kode original tetap sama)
                foreach ($group_ids as $group_id) {
                $group_id = clean_input($group_id);
                
                // Get group details
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

                    // Ambil semua promotion untuk grup ini dalam query terpisah
                    $promotion_query = "SELECT p.promotion_content
                                        FROM group_promotions gp
                                        JOIN promotions p ON gp.promotion_id = p.id AND p.active = 1
                                        WHERE gp.group_id = ?
                                        ORDER BY gp.display_order ASC";

                    $stmt_promo = mysqli_prepare($conn, $promotion_query);
                    mysqli_stmt_bind_param($stmt_promo, "i", $group_id);
                    mysqli_stmt_execute($stmt_promo);
                    $promotion_result = mysqli_stmt_get_result($stmt_promo);
                    
                    // Construct the full message with promotion and footer if requested
                    $full_message = $message_content;
                    $promotion_content = null;
                    $all_promotions = [];
					$footer_content = null;
                    
					if ($include_footer && !empty($group['footer_content'])) {
                        $full_message .= "\n\n" . $group['footer_content'];
                        $footer_content = $group['footer_content'];
                    }
					
                    // Gabungkan semua promotions
                    if ($include_promotion && mysqli_num_rows($promotion_result) > 0) {
                        while ($promo = mysqli_fetch_assoc($promotion_result)) {
                            $all_promotions[] = $promo['promotion_content'];
                        }
                        
                        if (!empty($all_promotions)) {
                            $promotion_content = implode("\n\n", $all_promotions);
                            $full_message .= "\n\n" . $promotion_content;
                        }
                    }
                    
                    // Send message using the OneSender API
                    $response = send_whatsapp_message($api_url, $api_key, $group['group_wa_id'], $full_message, $image_url);
                    
                    $now = date('Y-m-d H:i:s');
                    
                    // Check response and log to history
                    if (isset($response['error'])) {
                        // Failed to send
                        $fail_count++;
                        $error_message = mysqli_real_escape_string($conn, substr($response['error'], 0, 255));
                        
                        // Add to message history
                        $history_query = "INSERT INTO message_history 
                                          (whatsapp_number_id, group_id, template_id, message_content, image_url, 
                                           promotion_content, footer_content, status, error_message, is_instant, sent_at) 
                                          VALUES 
                                          ($whatsapp_number_id, $group_id, NULL, 
                                           '" . mysqli_real_escape_string($conn, $message_content) . "', 
                                           " . ($image_url ? "'" . mysqli_real_escape_string($conn, $image_url) . "'" : "NULL") . ", 
                                           " . ($promotion_content ? "'" . mysqli_real_escape_string($conn, $promotion_content) . "'" : "NULL") . ", 
                                           " . ($footer_content ? "'" . mysqli_real_escape_string($conn, $footer_content) . "'" : "NULL") . ", 
                                           'failed', '$error_message', 1, '$now')";
                        mysqli_query($conn, $history_query);
                    } else {
                        // Message sent successfully
                        $success_count++;
                        
                        // Add to message history
                        $history_query = "INSERT INTO message_history 
                                          (whatsapp_number_id, group_id, template_id, message_content, image_url, 
                                           promotion_content, footer_content, status, is_instant, sent_at) 
                                          VALUES 
                                          ($whatsapp_number_id, $group_id, NULL, 
                                           '" . mysqli_real_escape_string($conn, $message_content) . "', 
                                           " . ($image_url ? "'" . mysqli_real_escape_string($conn, $image_url) . "'" : "NULL") . ", 
                                           " . ($promotion_content ? "'" . mysqli_real_escape_string($conn, $promotion_content) . "'" : "NULL") . ", 
                                           " . ($footer_content ? "'" . mysqli_real_escape_string($conn, $footer_content) . "'" : "NULL") . ", 
                                           'sent', 1, '$now')";
                        mysqli_query($conn, $history_query);
                    }
                }
            }
                
                if ($success_count > 0) {
                    $message = "Message sent successfully to $success_count group(s)";
                    if ($fail_count > 0) {
                        $message .= " and failed to send to $fail_count group(s).";
                    } else {
                        $message .= ".";
                    }
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
        <!-- Sidebar -->
        <?php include('../includes/sidebar.php'); ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Send Instant Message</h1>
            </div>
            
            <?php if (mysqli_num_rows($accounts_result) == 0): ?>
                <div class="alert alert-warning">
                    <p class="mb-0">You need to add a WhatsApp account first before sending messages. <a href="whatsapp_accounts.php" class="alert-link">Add WhatsApp Account</a></p>
                </div>
            <?php else: ?>
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
                    <div class="card-header">
                        <h5 class="mb-0">Compose Message</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" id="instantMessageForm">
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
                                <label for="group_selection" class="form-label">WhatsApp Groups</label>
                                <div id="group_container" class="border rounded p-3 bg-light">
                                    <p class="text-center mb-0">Please select a WhatsApp account first</p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message_content" class="form-label">Message Content</label>
                                <textarea class="form-control" id="message_content" name="message_content" rows="6" required placeholder="Enter your message here..."></textarea>
                                <div class="form-text mt-2">
                                    <strong>Formatting Tips:</strong><br>
                                    - Use *asterisks* for <strong>bold text</strong><br>
                                    - Use _underscores_ for <em>italic text</em><br>
                                    - Use ~tildes~ for <del>strikethrough</del><br>
                                    - Use ```three backticks``` for <code>monospace</code>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image_url" class="form-label">Image URL (Optional)</label>
                                <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg">
                                <div class="form-text">Enter a direct URL to an image (JPG, PNG, etc.) to include with the message.</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_promotion" name="include_promotion" value="1" checked>
                                    <label class="form-check-label" for="include_promotion">
                                        Include Promotion (if available for the group)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_footer" name="include_footer" value="1" checked>
                                    <label class="form-check-label" for="include_footer">
                                        Include Footer (if available for the group)
                                    </label>
                                </div>
                            </div>
							
							<!-- SCHEDULE OPTION -->
							<div class="mb-3">
								<div class="form-check">
									<input class="form-check-input" type="checkbox" id="schedule_message" name="schedule_message" value="1">
									<label class="form-check-label" for="schedule_message">
										📅 Jadwalkan Pesan (Kirim Nanti)
									</label>
								</div>
							</div>

							<!-- SCHEDULE FIELDS (Awalnya tersembunyi) -->
							<div id="schedule_fields" class="mb-3" style="display: none;">
								<div class="card">
									<div class="card-header bg-light">
										<h6 class="mb-0">Jadwal Pengiriman</h6>
									</div>
									<div class="card-body">
										<div class="row">
											<div class="col-md-6">
												<label for="schedule_date" class="form-label">Tanggal</label>
												<input type="date" class="form-control" id="schedule_date" name="schedule_date" 
													   min="<?php echo date('Y-m-d'); ?>">
											</div>
											<div class="col-md-6">
												<label for="schedule_time" class="form-label">Waktu</label>
												<input type="time" class="form-control" id="schedule_time" name="schedule_time">
											</div>
										</div>
										<div class="form-text">Pilih tanggal dan waktu di masa depan untuk mengirim pesan</div>
									</div>
								</div>
							</div>
                            
                            <div class="d-grid gap-2">
								<button type="submit" name="send_message" class="btn btn-primary" id="submit_button">
									<i class="bi bi-send"></i> Send Message Now
								</button>
							</div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    // Load groups based on selected account
    document.getElementById('whatsapp_number_id').addEventListener('change', function() {
        const accountId = this.value;
        const groupContainer = document.getElementById('group_container');
        
        if (!accountId) {
            groupContainer.innerHTML = '<p class="text-center mb-0">Please select a WhatsApp account first</p>';
            return;
        }
        
        // Show loading indicator
        groupContainer.innerHTML = '<p class="text-center mb-0"><i class="bi bi-hourglass-split me-2"></i>Loading groups...</p>';
        
        // Fetch groups for selected account
        fetch(`get_groups.php?account_id=${accountId}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    groupContainer.innerHTML = '<p class="text-center mb-0">No groups found for this account. <a href="whatsapp_groups.php">Add Groups</a></p>';
                    return;
                }
                
                // Create checkboxes for each group
                let html = '<div class="row">';
                html += '<div class="col-12 mb-2"><div class="form-check">';
                html += '<input class="form-check-input" type="checkbox" id="select_all_groups">';
                html += '<label class="form-check-label fw-bold" for="select_all_groups">Select All Groups</label>';
                html += '</div></div>';
                
                data.forEach(group => {
                    html += '<div class="col-md-6 mb-2"><div class="form-check">';
                    html += `<input class="form-check-input group-checkbox" type="checkbox" name="group_ids[]" value="${group.id}" id="group_${group.id}">`;
                    html += `<label class="form-check-label" for="group_${group.id}">${group.group_name}</label>`;
                    html += '</div></div>';
                });
                html += '</div>';
                
                groupContainer.innerHTML = html;
                
                // Add select all functionality
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

		// Validasi untuk jadwal
		if (isScheduling) {
			const scheduleDate = document.getElementById('schedule_date').value;
			const scheduleTime = document.getElementById('schedule_time').value;

			if (!scheduleDate || !scheduleTime) {
				e.preventDefault();
				alert('Please select both date and time for scheduled message.');
				return;
			}

			// Validasi tanggal tidak boleh masa lalu
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

		// Set minimal date to today
		if (this.checked) {
			const today = new Date().toISOString().split('T')[0];
			document.getElementById('schedule_date').min = today;
		}
	});
	
	// Update button text based on schedule selection
	document.getElementById('schedule_message').addEventListener('change', function() {
		const submitButton = document.getElementById('submit_button');
		if (this.checked) {
			submitButton.innerHTML = '<i class="bi bi-calendar-check"></i> Schedule Message';
			submitButton.name = 'schedule_message';
		} else {
			submitButton.innerHTML = '<i class="bi bi-send"></i> Send Message Now';
			submitButton.name = 'send_message';
		}
	});
	
	// ... kode JavaScript sebelumnya ...

// Toggle schedule fields
document.getElementById('schedule_message').addEventListener('change', function() {
    const scheduleFields = document.getElementById('schedule_fields');
    scheduleFields.style.display = this.checked ? 'block' : 'none';

    // Set minimal date to today
    if (this.checked) {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('schedule_date').min = today;
    }
});

// Update button text based on schedule selection
document.getElementById('schedule_message').addEventListener('change', function() {
    const submitButton = document.getElementById('submit_button');
    if (this.checked) {
        submitButton.innerHTML = '<i class="bi bi-calendar-check"></i> Schedule Message';
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
    
    // Tambahin format ke teks yang diselect atau tambahin kosong aja
    const newText = start + selectedText + end;
    
    // Ganti teks yang diselect dengan format baru
    textarea.value = textarea.value.substring(0, startCursor) + newText + textarea.value.substring(endCursor);
    
    // Set cursor ke posisi yang bener
    const newCursorPos = startCursor + start.length + selectedText.length;
    textarea.setSelectionRange(newCursorPos, newCursorPos);
    textarea.focus();
}

// Nangkep keyboard shortcut
document.getElementById('message_content').addEventListener('keydown', function(e) {
    // Cek apakah Ctrl ditekan
    if (e.ctrlKey || e.metaKey) { // metaKey buat Mac (Cmd)
        switch(e.key.toLowerCase()) {
            case 'b': // Ctrl + B
                e.preventDefault();
                wrapText(this, '*', '*');
                break;
            case 'i': // Ctrl + I
                e.preventDefault();
                wrapText(this, '_', '_');
                break;
            case 'u': // Ctrl + U (kalo mau tambahin underline)
                e.preventDefault();
                wrapText(this, '~', '~');
                break;
        }
    }
});
</script>

<?php include('../includes/footer.php'); ?>