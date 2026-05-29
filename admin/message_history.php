<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Get all WhatsApp accounts for the current user
$accounts_query = "SELECT id, account_name FROM whatsapp_numbers WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY account_name ASC";
$accounts_result = mysqli_query($conn, $accounts_query);

// Get filter parameters
$filter_account = isset($_GET['account']) ? clean_input($_GET['account']) : '';
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$filter_type = isset($_GET['type']) ? clean_input($_GET['type']) : '';
$filter_date_from = isset($_GET['date_from']) ? clean_input($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? clean_input($_GET['date_to']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Base query for message history
$query = "SELECT mh.*, wg.group_name, wn.account_name, mt.template_name 
          FROM message_history mh 
          JOIN whatsapp_groups wg ON mh.group_id = wg.id 
          JOIN whatsapp_numbers wn ON mh.whatsapp_number_id = wn.id 
          LEFT JOIN message_templates mt ON mh.template_id = mt.id 
          WHERE wn.user_id = " . $_SESSION['user_id'];

// Apply filters
if (!empty($filter_account)) {
    $query .= " AND mh.whatsapp_number_id = '$filter_account'";
}

if (!empty($filter_status)) {
    $query .= " AND mh.status = '$filter_status'";
}

if (!empty($filter_type)) {
    if ($filter_type == 'instant') {
        $query .= " AND mh.is_instant = 1";
    } else if ($filter_type == 'scheduled') {
        $query .= " AND mh.scheduled_id IS NOT NULL";
    } else if ($filter_type == 'template') {
        $query .= " AND mh.template_id IS NOT NULL";
    }
}

if (!empty($filter_date_from)) {
    $query .= " AND DATE(mh.sent_at) >= '$filter_date_from'";
}

if (!empty($filter_date_to)) {
    $query .= " AND DATE(mh.sent_at) <= '$filter_date_to'";
}

if (!empty($search)) {
    $query .= " AND (wg.group_name LIKE '%$search%' OR mh.message_content LIKE '%$search%' OR mt.template_name LIKE '%$search%')";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // 20 records per page
$offset = ($page - 1) * $limit;

// Get total records count
$count_query = str_replace("SELECT mh.*, wg.group_name, wn.account_name, mt.template_name", "SELECT COUNT(*) as total", $query);
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $limit);

// Add pagination to query
$query .= " ORDER BY mh.sent_at DESC LIMIT $offset, $limit";
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
                    grid-template-columns: 1.2fr 2fr 1.5fr 3fr 1fr auto;
                    align-items: center;
                    gap: 1.25rem;
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
                .content-preview {
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    font-size: 0.9rem;
                    color: var(--ink-muted);
                }
                .inspector-box {
                    border: 1px solid var(--border-color);
                    background: rgba(10,10,10,0.01);
                    border-radius: 4px;
                    padding: 1rem;
                    font-family: 'Geist Mono', monospace;
                    font-size: 0.85rem;
                    white-space: pre-wrap;
                    color: var(--ink);
                    max-height: 250px;
                    overflow-y: auto;
                }
                .inspector-label {
                    font-family: 'Geist Mono', monospace;
                    font-size: 0.75rem;
                    font-weight: 600;
                    color: var(--ink-muted);
                    margin-bottom: 0.5rem;
                    letter-spacing: 0.05em;
                }
                
                /* Utilitarian Pagination */
                .pagination .page-link {
                    border: 1px solid var(--border-color);
                    color: var(--ink);
                    margin: 0 2px;
                    border-radius: 4px;
                    font-family: 'Geist Mono', monospace;
                    transition: all 150ms ease;
                }
                .pagination .page-link:hover {
                    background-color: rgba(10,10,10,0.05);
                }
                .pagination .page-item.active .page-link {
                    background-color: var(--ink);
                    border-color: var(--ink);
                    color: var(--surface);
                }
            </style>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-end pt-4 pb-3 mb-4" style="border-bottom: 2px solid var(--ink);">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" style="border-radius: 4px;">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h2 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600;">Transmission Logs</h1>
                        <span class="font-mono text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">SYSTEM ARCHIVE // HISTORICAL DATA</span>
                    </div>
                </div>
            </div>
            
            <div class="p-3 mb-4" style="border: 1px solid var(--border-color); border-radius: 4px; background: transparent;">
                <div style="font-family: 'Geist Mono', monospace; font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <i class="bi bi-funnel"></i> Filter Arsip Data
                </div>
                <form method="get" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="account" class="form-label font-mono" style="font-size: 0.75rem; font-weight: 600;">SENDER ACCOUNT</label>
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
                    <div class="col-md-2">
                        <label for="status" class="form-label font-mono" style="font-size: 0.75rem; font-weight: 600;">STATUS</label>
                        <select class="form-select utilitarian-form-control" id="status" name="status">
                            <option value="">Semua</option>
                            <option value="sent" <?php echo $filter_status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="delivered" <?php echo $filter_status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="read" <?php echo $filter_status === 'read' ? 'selected' : ''; ?>>Read</option>
                            <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label font-mono" style="font-size: 0.75rem; font-weight: 600;">TYPE</label>
                        <select class="form-select utilitarian-form-control" id="type" name="type">
                            <option value="">Semua</option>
                            <option value="instant" <?php echo $filter_type === 'instant' ? 'selected' : ''; ?>>Instant</option>
                            <option value="scheduled" <?php echo $filter_type === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="template" <?php echo $filter_type === 'template' ? 'selected' : ''; ?>>Template</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="search" class="form-label font-mono" style="font-size: 0.75rem; font-weight: 600;">KEYWORD SEARCH</label>
                        <input type="text" class="form-control utilitarian-form-control" id="search" name="search" placeholder="Cari konten pesan atau grup..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label for="date_from" class="form-label font-mono" style="font-size: 0.75rem; font-weight: 600;">DATE FROM</label>
                        <input type="date" class="form-control utilitarian-form-control" id="date_from" name="date_from" value="<?php echo $filter_date_from; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="date_to" class="form-label font-mono" style="font-size: 0.75rem; font-weight: 600;">DATE TO</label>
                        <input type="date" class="form-control utilitarian-form-control" id="date_to" name="date_to" value="<?php echo $filter_date_to; ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="d-grid gap-2 d-md-flex w-100">
                            <button type="submit" class="btn btn-primary w-100" style="border-radius: 4px;">Terapkan Filter</button>
                            <a href="message_history.php" class="btn btn-outline-secondary w-100" style="border-radius: 4px;">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="mt-2 mb-5">
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <div class="row-list">
                        <div class="row-item py-2" style="border-bottom: 2px solid var(--ink); animation: none; opacity: 1; transform: none; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: var(--ink-muted);">
                            <div>Waktu Eksekusi</div>
                            <div>Target Penerima</div>
                            <div>Sumber Tipe</div>
                            <div>Pratinjau Pesan</div>
                            <div>Status</div>
                            <div class="text-end">Aksi</div>
                        </div>

                        <?php 
                        $delay = 0;
                        while($row = mysqli_fetch_assoc($result)): 
                        ?>
                            <div class="row-item" style="animation-delay: <?php echo $delay; ?>ms">
                                
                                <div>
                                    <div class="font-mono" style="font-size: 1.1rem; font-weight: 600; color: var(--ink);">
                                        <?php echo date('H:i:s', strtotime($row['sent_at'])); ?>
                                    </div>
                                    <div class="font-mono text-muted" style="font-size: 0.75rem; margin-top: 2px;">
                                        <?php echo date('d M Y', strtotime($row['sent_at'])); ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <div style="font-weight: 600; font-size: 0.95rem; color: var(--ink);">
                                        <?php echo htmlspecialchars($row['group_name']); ?>
                                    </div>
                                    <div class="font-mono text-muted" style="font-size: 0.75rem; margin-top: 2px;">
                                        <i class="bi bi-broadcast"></i> <?php echo htmlspecialchars($row['account_name']); ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <?php if ($row['is_instant'] == 1): ?>
                                        <span class="font-mono" style="border: 1px solid #17A2B8; color: #17A2B8; padding: 2px 6px; border-radius: 2px; font-size: 0.7rem;">INSTANT</span>
                                    <?php elseif (!empty($row['scheduled_id'])): ?>
                                        <span class="font-mono" style="border: 1px solid #F57F17; color: #F57F17; padding: 2px 6px; border-radius: 2px; font-size: 0.7rem;">SCHEDULED</span>
                                    <?php else: ?>
                                        <span class="font-mono" style="border: 1px solid var(--ink-muted); color: var(--ink-muted); padding: 2px 6px; border-radius: 2px; font-size: 0.7rem;">TEMPLATE</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($row['template_name'])): ?>
                                        <div class="text-muted mt-1" style="font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;">
                                            <?php echo htmlspecialchars($row['template_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="content-preview">
                                    "<?php echo htmlspecialchars(substr($row['message_content'], 0, 50)) . (strlen($row['message_content']) > 50 ? '...' : ''); ?>"
                                </div>
                                
                                <div>
                                    <?php if ($row['status'] == 'sent'): ?>
                                        <span class="badge" style="background: #E3F2FD; color: #1565C0; border-radius: 2px; font-family: 'Geist Mono', monospace; font-size: 0.65rem; padding: 4px 6px;">SENT</span>
                                    <?php elseif ($row['status'] == 'delivered'): ?>
                                        <span class="badge" style="background: #E0F7FA; color: #0277BD; border-radius: 2px; font-family: 'Geist Mono', monospace; font-size: 0.65rem; padding: 4px 6px;">DELIVERED</span>
                                    <?php elseif ($row['status'] == 'read'): ?>
                                        <span class="badge" style="background: #E8F5E9; color: #2E7D32; border-radius: 2px; font-family: 'Geist Mono', monospace; font-size: 0.65rem; padding: 4px 6px;">READ</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #FFEBEE; color: #C62828; border-radius: 2px; font-family: 'Geist Mono', monospace; font-size: 0.65rem; padding: 4px 6px;">FAILED</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-sm view-message" 
                                            style="background: rgba(0, 56, 255, 0.05); color: var(--accent); border: 1px solid rgba(0, 56, 255, 0.1); border-radius: 4px; padding: 0.35rem 0.6rem; transition: transform 150ms ease;"
                                            onmousedown="this.style.transform='scale(0.95)'" onmouseup="this.style.transform='scale(1)'" onmouseleave="this.style.transform='scale(1)'"
                                            data-id="<?php echo $row['id']; ?>"
                                            data-group-name="<?php echo htmlspecialchars($row['group_name']); ?>"
                                            data-account-name="<?php echo htmlspecialchars($row['account_name']); ?>"
                                            data-message-content="<?php echo htmlspecialchars($row['message_content']); ?>"
                                            data-image-url="<?php echo htmlspecialchars($row['image_url']); ?>"
                                            data-promotion-content="<?php echo htmlspecialchars($row['promotion_content']); ?>"
                                            data-footer-content="<?php echo htmlspecialchars($row['footer_content']); ?>"
                                            data-status="<?php echo $row['status']; ?>"
                                            data-sent-at="<?php echo date('d M Y - H:i:s', strtotime($row['sent_at'])); ?>"
                                            data-error-message="<?php echo htmlspecialchars($row['error_message']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#viewMessageModal" title="Inspect Log">
                                        <i class="bi bi-search"></i> Inspect
                                    </button>
                                </div>
                            </div>
                        <?php 
                        $delay += 25; // Delay lebih cepat karena data history biasanya banyak
                        endwhile; 
                        ?>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-5">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?php echo (!empty($filter_account) ? '&account='.$filter_account : '') . (!empty($filter_status) ? '&status='.$filter_status : '') . (!empty($filter_type) ? '&type='.$filter_type : '') . (!empty($filter_date_from) ? '&date_from='.$filter_date_from : '') . (!empty($filter_date_to) ? '&date_to='.$filter_date_to : '') . (!empty($search) ? '&search='.$search : ''); ?>" aria-label="First">
                                            <span aria-hidden="true">&laquo;&laquo;</span>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo (!empty($filter_account) ? '&account='.$filter_account : '') . (!empty($filter_status) ? '&status='.$filter_status : '') . (!empty($filter_type) ? '&type='.$filter_type : '') . (!empty($filter_date_from) ? '&date_from='.$filter_date_from : '') . (!empty($filter_date_to) ? '&date_to='.$filter_date_to : '') . (!empty($search) ? '&search='.$search : ''); ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo (!empty($filter_account) ? '&account='.$filter_account : '') . (!empty($filter_status) ? '&status='.$filter_status : '') . (!empty($filter_type) ? '&type='.$filter_type : '') . (!empty($filter_date_from) ? '&date_from='.$filter_date_from : '') . (!empty($filter_date_to) ? '&date_to='.$filter_date_to : '') . (!empty($search) ? '&search='.$search : ''); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo (!empty($filter_account) ? '&account='.$filter_account : '') . (!empty($filter_status) ? '&status='.$filter_status : '') . (!empty($filter_type) ? '&type='.$filter_type : '') . (!empty($filter_date_from) ? '&date_from='.$filter_date_from : '') . (!empty($filter_date_to) ? '&date_to='.$filter_date_to : '') . (!empty($search) ? '&search='.$search : ''); ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo (!empty($filter_account) ? '&account='.$filter_account : '') . (!empty($filter_status) ? '&status='.$filter_status : '') . (!empty($filter_type) ? '&type='.$filter_type : '') . (!empty($filter_date_from) ? '&date_from='.$filter_date_from : '') . (!empty($filter_date_to) ? '&date_to='.$filter_date_to : '') . (!empty($search) ? '&search='.$search : ''); ?>" aria-label="Last">
                                            <span aria-hidden="true">&raquo;&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert mt-3" style="border-radius: 4px; border: 1px dashed var(--border-color); background: transparent; color: var(--ink-muted); text-align: center; padding: 4rem 1rem;">
                        <i class="bi bi-clock-history" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--ink);"></i>
                        <p class="mb-0 font-mono" style="font-size: 1rem; font-weight: 500;">Tidak ada riwayat pesan ditemukan.</p>
                        <p class="font-mono text-muted mt-2" style="font-size: 0.85rem;">Coba sesuaikan filter pencarian atau kirim pesan baru.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 4px; border: 1px solid var(--border-color); box-shadow: 10px 10px 0px rgba(10,10,10,0.1);">
            <div class="modal-header" style="border-bottom: 2px solid var(--ink); padding: 1.5rem; background: rgba(10,10,10,0.02);">
                <h5 class="modal-title font-mono fw-bold" id="viewMessageModalLabel"><i class="bi bi-search me-2"></i>LOG_INSPECTOR</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                
                <div class="row mb-4 pb-3" style="border-bottom: 1px dashed var(--border-color);">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="inspector-label">DESTINATION</div>
                        <div style="font-weight: 600; font-size: 1.05rem;" id="view_group_name"></div>
                        <div class="font-mono text-muted mt-1" style="font-size: 0.8rem;"><i class="bi bi-broadcast me-1"></i><span id="view_account_name"></span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="inspector-label">TIMESTAMP & STATUS</div>
                        <div class="font-mono" style="font-size: 1.05rem;" id="view_sent_at"></div>
                        <div class="mt-2" id="view_status"></div>
                        
                        <div id="view_error_container" style="display: none; margin-top: 0.75rem; padding: 0.5rem; background: #FFEBEE; border-left: 3px solid #C62828; font-family: 'Geist Mono', monospace; font-size: 0.75rem;">
                            <span class="text-danger fw-bold">EXCEPTION:</span> <span id="view_error_message" class="text-danger"></span>
                        </div>
                    </div>
                </div>
                
                <div id="view_image_container" class="mb-4" style="display: none;">
                    <div class="inspector-label">ATTACHED MEDIA</div>
                    <div style="border: 1px solid var(--border-color); padding: 0.5rem; border-radius: 4px; display: inline-block;">
                        <img id="view_image" src="" alt="Attachment" style="max-height: 200px; object-fit: contain;">
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="inspector-label">PRIMARY PAYLOAD</div>
                    <div class="inspector-box" id="view_message_content"></div>
                </div>
                
                <div class="row">
                    <div class="col-md-6" id="view_promotion_container" style="display: none;">
                        <div class="inspector-label">PROMOTION MODULE</div>
                        <div class="inspector-box" id="view_promotion_content" style="background: rgba(0, 56, 255, 0.02); border-color: rgba(0, 56, 255, 0.1);"></div>
                    </div>
                    
                    <div class="col-md-6" id="view_footer_container" style="display: none;">
                        <div class="inspector-label">FOOTER MODULE</div>
                        <div class="inspector-box" id="view_footer_content" style="color: var(--ink-muted);"></div>
                    </div>
                </div>
                
            </div>
            <div class="modal-footer p-3" style="border-top: 1px solid var(--border-color);">
                <button type="button" class="btn btn-outline-secondary w-100 font-mono" data-bs-dismiss="modal" style="border-radius: 4px; letter-spacing: 0.05em;">[ CLOSE INSPECTOR ]</button>
            </div>
        </div>
    </div>
</div>

<script>
    // View message button click
    document.querySelectorAll('.view-message').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('view_group_name').textContent = this.getAttribute('data-group-name');
            document.getElementById('view_account_name').textContent = this.getAttribute('data-account-name');
            document.getElementById('view_sent_at').textContent = this.getAttribute('data-sent-at');
            document.getElementById('view_message_content').textContent = this.getAttribute('data-message-content');
            
            // Set status with utilitarian badge
            const status = this.getAttribute('data-status');
            let statusBadge = '';
            
            if (status === 'sent') {
                statusBadge = '<span class="badge" style="background: #E3F2FD; color: #1565C0; border-radius: 2px; font-family: \'Geist Mono\', monospace; padding: 4px 6px;">SENT</span>';
            } else if (status === 'delivered') {
                statusBadge = '<span class="badge" style="background: #E0F7FA; color: #0277BD; border-radius: 2px; font-family: \'Geist Mono\', monospace; padding: 4px 6px;">DELIVERED</span>';
            } else if (status === 'read') {
                statusBadge = '<span class="badge" style="background: #E8F5E9; color: #2E7D32; border-radius: 2px; font-family: \'Geist Mono\', monospace; padding: 4px 6px;">READ</span>';
            } else {
                statusBadge = '<span class="badge" style="background: #FFEBEE; color: #C62828; border-radius: 2px; font-family: \'Geist Mono\', monospace; padding: 4px 6px;">FAILED</span>';
            }
            
            document.getElementById('view_status').innerHTML = statusBadge;
            
            // Handle error message
            const errorMessage = this.getAttribute('data-error-message');
            if (errorMessage && errorMessage.trim() !== '') {
                document.getElementById('view_error_message').textContent = errorMessage;
                document.getElementById('view_error_container').style.display = 'block';
            } else {
                document.getElementById('view_error_container').style.display = 'none';
            }
            
            // Handle image
            const imageUrl = this.getAttribute('data-image-url');
            const imageContainer = document.getElementById('view_image_container');
            const imageElement = document.getElementById('view_image');
            
            if (imageUrl && imageUrl.trim() !== '') {
                imageElement.src = imageUrl;
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }
            
            // Handle promotion content
            const promotionContent = this.getAttribute('data-promotion-content');
            const promotionContainer = document.getElementById('view_promotion_container');
            
            if (promotionContent && promotionContent.trim() !== '') {
                document.getElementById('view_promotion_content').textContent = promotionContent;
                promotionContainer.style.display = 'block';
            } else {
                promotionContainer.style.display = 'none';
            }
            
            // Handle footer content
            const footerContent = this.getAttribute('data-footer-content');
            const footerContainer = document.getElementById('view_footer_container');
            
            if (footerContent && footerContent.trim() !== '') {
                document.getElementById('view_footer_content').textContent = footerContent;
                footerContainer.style.display = 'block';
            } else {
                footerContainer.style.display = 'none';
            }
        });
    });
</script>

<?php include('../includes/footer.php'); ?>