<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Process form submission for updating API settings
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_api'])) {
    $id = clean_input($_POST["id"]);
    $api_key = clean_input($_POST["api_key"]);
    $api_url = clean_input($_POST["api_url"]);
    $webhook_url = !empty($_POST["webhook_url"]) ? clean_input($_POST["webhook_url"]) : null;
    $webhook_token = !empty($_POST["webhook_token"]) ? clean_input($_POST["webhook_token"]) : null;
    
    // Validation
    $errors = [];
    
    if (empty($api_key)) {
        $errors[] = "API Key is required";
    }
    
    if (empty($api_url)) {
        $errors[] = "API URL is required";
    }
    
    // Check if the account belongs to the current user
    $check_query = "SELECT id FROM whatsapp_numbers WHERE id = $id AND user_id = " . $_SESSION['user_id'];
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) != 1) {
        $errors[] = "You don't have permission to update this account";
    }
    
    // If no errors, update the API settings
    if (empty($errors)) {
        $query = "UPDATE whatsapp_numbers 
                  SET api_key = '$api_key', 
                      api_url = '$api_url', 
                      webhook_url = " . ($webhook_url ? "'$webhook_url'" : "NULL") . ", 
                      webhook_token = " . ($webhook_token ? "'$webhook_token'" : "NULL") . "  
                  WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            // Test API connection
            $test_result = test_api_connection($api_url, $api_key);
            
            if ($test_result['success']) {
                set_flash_message("success", "API settings updated successfully! Connection test passed.");
            } else {
                set_flash_message("warning", "API settings updated but connection test failed: " . $test_result['message']);
            }
            
            header("Location: api_settings.php");
            exit;
        } else {
            $errors[] = "Error updating API settings: " . mysqli_error($conn);
        }
    }
}

// Get all WhatsApp accounts for the current user
$query = "SELECT wn.*, COUNT(wg.id) as group_count 
          FROM whatsapp_numbers wn 
          LEFT JOIN whatsapp_groups wg ON wn.id = wg.whatsapp_number_id 
          WHERE wn.user_id = " . $_SESSION['user_id'] . " 
          GROUP BY wn.id 
          ORDER BY wn.account_name ASC";
$result = mysqli_query($conn, $query);

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <?php include('../includes/sidebar.php'); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content utilitarian-page">
            
            <style>
                /* Utilitarian Server Rack Design */
                .rack-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
                    gap: 1.5rem;
                }
                .server-rack {
                    border: 1px solid var(--border-color);
                    border-radius: 4px;
                    background: transparent;
                    display: flex;
                    flex-direction: column;
                    transition: border-color 200ms var(--ease-out), box-shadow 200ms var(--ease-out);
                    
                    opacity: 0;
                    transform: translateY(15px);
                    animation: fadeInRack 500ms var(--ease-out) forwards;
                }
                .server-rack:hover {
                    border-color: var(--ink);
                    box-shadow: 6px 6px 0px rgba(10,10,10,0.04); /* Brutalist shadow */
                }
                @keyframes fadeInRack {
                    to { opacity: 1; transform: translateY(0); }
                }
                .rack-header {
                    border-bottom: 1px solid var(--border-color);
                    padding: 1rem 1.25rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: rgba(10,10,10,0.02);
                }
                .rack-body {
                    padding: 1.25rem;
                    flex-grow: 1;
                }
                .rack-footer {
                    border-top: 1px dashed var(--border-color);
                    padding: 1rem 1.25rem;
                    display: flex;
                    gap: 0.75rem;
                }
                .data-row {
                    display: flex;
                    flex-direction: column;
                    margin-bottom: 1rem;
                }
                .data-row:last-child {
                    margin-bottom: 0;
                }
                .data-label {
                    font-family: 'Geist Mono', monospace;
                    font-size: 0.75rem;
                    color: var(--ink-muted);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    margin-bottom: 0.25rem;
                }
                .data-value {
                    font-family: 'Geist Mono', monospace;
                    font-size: 0.9rem;
                    color: var(--ink);
                    word-break: break-all;
                }
                .data-value.empty {
                    color: rgba(10,10,10,0.3);
                    font-style: italic;
                }
                
                /* Terminal Modal Styling */
                .terminal-console {
                    background: #0A0A0A;
                    color: #00FF41;
                    font-family: 'Geist Mono', monospace;
                    padding: 1.5rem;
                    border-radius: 4px;
                    border: 1px solid #333;
                }
                .blink-cursor {
                    animation: blink 1s step-end infinite;
                }
                @keyframes blink { 50% { opacity: 0; } }
            </style>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-end pt-4 pb-3 mb-4" style="border-bottom: 2px solid var(--ink);">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" style="border-radius: 4px;">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h2 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600;">API Configurations</h1>
                        <span class="font-mono text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">ONESENDER ENDPOINT & WEBHOOK MANAGEMENT</span>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="whatsapp_accounts.php" class="btn btn-primary" style="border-radius: 4px; padding: 0.6rem 1.25rem;">
                        <i class="bi bi-hdd-network me-1"></i> Kelola Devices
                    </a>
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
            
            <div class="mb-5">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <div class="rack-grid">
                        <?php 
                        $delay = 0;
                        while($row = mysqli_fetch_assoc($result)): 
                        ?>
                            <div class="server-rack" style="animation-delay: <?php echo $delay; ?>ms;">
                                <div class="rack-header">
                                    <div style="font-weight: 600; font-size: 1.1rem; color: var(--ink);">
                                        <i class="bi bi-hdd-rack me-2 text-muted"></i><?php echo htmlspecialchars($row['account_name']); ?>
                                    </div>
                                    <div>
                                        <?php if($row['active']): ?>
                                            <span class="badge" style="background: #E8F5E9; color: #2E7D32; border: 1px solid #A5D6A7; border-radius: 2px; font-family: 'Geist Mono', monospace; font-size: 0.65rem; padding: 4px 6px; letter-spacing: 0.05em;"><i class="bi bi-circle-fill me-1" style="font-size: 0.4rem; vertical-align: middle;"></i> ONLINE</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: transparent; color: var(--ink-muted); border: 1px dashed var(--border-color); border-radius: 2px; font-family: 'Geist Mono', monospace; font-size: 0.65rem; padding: 4px 6px; letter-spacing: 0.05em;"><i class="bi bi-circle me-1" style="font-size: 0.4rem; vertical-align: middle;"></i> OFFLINE</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="rack-body">
                                    <div class="data-row">
                                        <div class="data-label">ENDPOINT URL</div>
                                        <div class="data-value <?php echo empty($row['api_url']) ? 'empty' : ''; ?>">
                                            <?php echo !empty($row['api_url']) ? htmlspecialchars($row['api_url']) : 'NULL'; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="data-row">
                                        <div class="data-label">AUTHENTICATION KEY</div>
                                        <div class="data-value <?php echo empty($row['api_key']) ? 'empty' : ''; ?>">
                                            <?php 
                                            if(!empty($row['api_key'])) {
                                                echo substr(htmlspecialchars($row['api_key']), 0, 10) . str_repeat('•', 15); 
                                            } else {
                                                echo 'NULL';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <div class="data-row">
                                        <div class="data-label">WEBHOOK TARGET (OPSIONAL)</div>
                                        <div class="data-value <?php echo empty($row['webhook_url']) ? 'empty' : ''; ?>">
                                            <?php echo !empty($row['webhook_url']) ? htmlspecialchars($row['webhook_url']) : 'NOT_CONFIGURED'; ?>
                                        </div>
                                    </div>

                                    <div class="data-row mt-3 pt-3" style="border-top: 1px solid rgba(10,10,10,0.05);">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="font-mono text-muted" style="font-size: 0.75rem;">
                                                <i class="bi bi-diagram-3 me-1"></i> <?php echo $row['group_count']; ?> NODE ROUTES
                                            </div>
                                            <div class="font-mono text-muted" style="font-size: 0.75rem;">
                                                SYNC: <?php echo date('d/m/y H:i', strtotime($row['updated_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="rack-footer">
                                    <button type="button" class="btn flex-grow-1 edit-api-btn" 
                                            style="background: rgba(0, 56, 255, 0.05); color: var(--accent); border: 1px solid rgba(0, 56, 255, 0.2); border-radius: 4px; font-weight: 500; transition: transform 160ms var(--ease-out);"
                                            onmousedown="this.style.transform='scale(0.97)'" onmouseup="this.style.transform='scale(1)'" onmouseleave="this.style.transform='scale(1)'"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-account-name="<?php echo htmlspecialchars($row['account_name']); ?>"
                                            data-api-key="<?php echo htmlspecialchars($row['api_key']); ?>"
                                            data-api-url="<?php echo htmlspecialchars($row['api_url']); ?>"
                                            data-webhook-url="<?php echo htmlspecialchars($row['webhook_url']); ?>"
                                            data-webhook-token="<?php echo htmlspecialchars($row['webhook_token']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#editApiModal">
                                        <i class="bi bi-sliders me-1"></i> Konfigurasi
                                    </button>
                                    
                                    <button type="button" class="btn test-connection-btn"
                                            style="background: transparent; color: var(--ink); border: 1px solid var(--border-color); border-radius: 4px; font-weight: 500; transition: transform 160ms var(--ease-out);"
                                            onmousedown="this.style.transform='scale(0.97)'" onmouseup="this.style.transform='scale(1)'" onmouseleave="this.style.transform='scale(1)'"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-api-key="<?php echo htmlspecialchars($row['api_key']); ?>"
                                            data-api-url="<?php echo htmlspecialchars($row['api_url']); ?>">
                                        <i class="bi bi-lightning-charge"></i> Ping
                                    </button>
                                </div>
                            </div>
                        <?php 
                        $delay += 50;
                        endwhile; 
                        ?>
                    </div>
                <?php else: ?>
                    <div class="alert mt-3" style="border-radius: 4px; border: 1px dashed var(--border-color); background: transparent; color: var(--ink-muted); text-align: center; padding: 4rem 1rem;">
                        <i class="bi bi-hdd-network" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--ink);"></i>
                        <p class="mb-0 font-mono" style="font-size: 1rem; font-weight: 500;">Tidak ada Device yang tertaut.</p>
                        <p class="font-mono text-muted mt-2" style="font-size: 0.85rem;">Tambahkan akun WhatsApp di menu <a href="whatsapp_accounts.php" class="text-primary text-decoration-none">Manage WhatsApp Accounts</a>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="editApiModal" tabindex="-1" aria-labelledby="editApiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 4px; border: 1px solid var(--border-color); box-shadow: 10px 10px 0px rgba(10,10,10,0.1);">
            <div class="modal-header" style="border-bottom: 2px solid var(--ink); padding: 1.5rem;">
                <h5 class="modal-title font-mono fw-bold" id="editApiModalLabel"><i class="bi bi-terminal me-2"></i>API_CONFIG</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body p-4">
                    <input type="hidden" id="api_id" name="id">
                    
                    <div class="mb-4 p-3" style="background: rgba(10,10,10,0.02); border-left: 3px solid var(--ink); border-radius: 0 4px 4px 0;">
                        <div class="font-mono text-muted mb-1" style="font-size: 0.75rem; letter-spacing: 0.05em;">TARGET DEVICE:</div>
                        <div style="font-size: 1.1rem; font-weight: 600; color: var(--ink);"><i class="bi bi-hdd-rack me-2"></i><span id="account_name_display"></span></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="api_url" class="form-label font-mono" style="font-size: 0.85rem; font-weight: 600; color: var(--ink);">API ENDPOINT URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="api_url" name="api_url" required style="border-radius: 4px; border: 1px solid var(--border-color); font-family: 'Geist Mono', monospace;">
                            <div class="font-mono text-muted mt-1" style="font-size: 0.7rem;">Format: https://domain.com/api/v1/messages</div>
                        </div>
                        
                        <div class="col-md-12 mb-4">
                            <label for="api_key" class="form-label font-mono" style="font-size: 0.85rem; font-weight: 600; color: var(--ink);">API AUTHENTICATION KEY <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="api_key" name="api_key" required style="border-radius: 4px; border: 1px solid var(--border-color); font-family: 'Geist Mono', monospace;">
                        </div>
                    </div>

                    <hr style="border-color: rgba(10,10,10,0.1); margin: 1rem 0 1.5rem 0;">
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="webhook_url" class="form-label font-mono" style="font-size: 0.85rem; font-weight: 600; color: var(--ink);">WEBHOOK URL <span class="text-muted fw-normal">(OPTIONAL)</span></label>
                            <input type="url" class="form-control" id="webhook_url" name="webhook_url" style="border-radius: 4px; border: 1px solid var(--border-color); font-family: 'Geist Mono', monospace;">
                            <div class="font-mono text-muted mt-1" style="font-size: 0.7rem;">Endpoint untuk menerima event dari OneSender</div>
                        </div>
                        
                        <div class="col-md-12 mb-2">
                            <label for="webhook_token" class="form-label font-mono" style="font-size: 0.85rem; font-weight: 600; color: var(--ink);">WEBHOOK VERIFICATION TOKEN <span class="text-muted fw-normal">(OPTIONAL)</span></label>
                            <input type="text" class="form-control" id="webhook_token" name="webhook_token" style="border-radius: 4px; border: 1px solid var(--border-color); font-family: 'Geist Mono', monospace;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-3" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 4px; padding: 0.5rem 1.25rem;">Cancel</button>
                    <button type="submit" name="update_api" class="btn btn-primary" style="border-radius: 4px; padding: 0.5rem 1.5rem; transition: transform 160ms var(--ease-out);" onclick="this.style.transform='scale(0.96)'">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="connectionResultModal" tabindex="-1" aria-labelledby="connectionResultModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 4px; border: 1px solid var(--border-color); box-shadow: 10px 10px 0px rgba(10,10,10,0.1);">
            <div class="modal-header" style="border-bottom: 2px solid var(--ink); padding: 1rem 1.5rem; background: rgba(10,10,10,0.02);">
                <h5 class="modal-title font-mono fw-bold" style="font-size: 1rem;"><i class="bi bi-activity me-2"></i>PING_DIAGNOSTICS</h5>
            </div>
            <div class="modal-body p-0">
                <div class="terminal-console m-3">
                    <div id="connection_test_loading">
                        <span style="color: #00FF41;">> pinging endpoint...</span><span class="blink-cursor">_</span>
                    </div>
                    
                    <div id="connection_test_result" style="display: none;">
                        <div id="connection_test_success" style="display: none;">
                            <span style="color: #00FF41;">> SUCCESS. HANDSHAKE ESTABLISHED.</span><br>
                            <span style="color: #00FF41;">> <span id="success_message"></span></span>
                        </div>
                        <div id="connection_test_error" style="display: none;">
                            <span style="color: #FF3B30;">> ERROR. CONNECTION REFUSED.</span><br>
                            <span style="color: #FF3B30;">> <span id="error_message"></span></span>
                        </div>
                        
                        <div class="mt-4 pt-3" style="border-top: 1px dashed #333;">
                            <span style="color: #A0A0A0; font-size: 0.75rem;">--- RAW TRACE DATA ---</span>
                            <pre id="response_details" style="color: #A0A0A0; font-size: 0.75rem; max-height: 150px; overflow-y: auto; margin-top: 0.5rem;"></pre>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: none; padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-outline-secondary w-100 font-mono" data-bs-dismiss="modal" style="border-radius: 4px;">[ TERMINATE SESSION ]</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Edit API button click
    document.querySelectorAll('.edit-api-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('api_id').value = this.getAttribute('data-id');
            document.getElementById('account_name_display').textContent = this.getAttribute('data-account-name');
            document.getElementById('api_key').value = this.getAttribute('data-api-key');
            document.getElementById('api_url').value = this.getAttribute('data-api-url');
            document.getElementById('webhook_url').value = this.getAttribute('data-webhook-url') || '';
            document.getElementById('webhook_token').value = this.getAttribute('data-webhook-token') || '';
        });
    });
    
    // Test connection (PING) button click
    document.querySelectorAll('.test-connection-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const apiKey = this.getAttribute('data-api-key');
            const apiUrl = this.getAttribute('data-api-url');
            
            // Show loading and reset previous results
            document.getElementById('connection_test_loading').style.display = 'block';
            document.getElementById('connection_test_result').style.display = 'none';
            document.getElementById('connection_test_success').style.display = 'none';
            document.getElementById('connection_test_error').style.display = 'none';
            
            // Show modal
            var resultModal = new bootstrap.Modal(document.getElementById('connectionResultModal'));
            resultModal.show();
            
            // Test connection via AJAX
            fetch('test_api_connection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `api_key=${encodeURIComponent(apiKey)}&api_url=${encodeURIComponent(apiUrl)}`
            })
            .then(response => response.json())
            .then(data => {
                // Sengaja diberi delay 500ms agar efek animasi terminal terasa
                setTimeout(() => {
                    document.getElementById('connection_test_loading').style.display = 'none';
                    document.getElementById('connection_test_result').style.display = 'block';
                    
                    if (data.success) {
                        document.getElementById('connection_test_success').style.display = 'block';
                        document.getElementById('success_message').textContent = data.message;
                    } else {
                        document.getElementById('connection_test_error').style.display = 'block';
                        document.getElementById('error_message').textContent = data.message;
                    }
                    
                    document.getElementById('response_details').textContent = JSON.stringify(data.details, null, 2);
                }, 600);
            })
            .catch(error => {
                setTimeout(() => {
                    document.getElementById('connection_test_loading').style.display = 'none';
                    document.getElementById('connection_test_result').style.display = 'block';
                    document.getElementById('connection_test_error').style.display = 'block';
                    document.getElementById('error_message').textContent = 'FATAL EXCEPTION: ' + error.message;
                    document.getElementById('response_details').textContent = 'NULL TRACE';
                }, 600);
            });
        });
    });
</script>

<?php include('../includes/footer.php'); ?>