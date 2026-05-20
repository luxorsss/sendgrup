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
        <!-- Sidebar -->
        <?php include('../includes/sidebar.php'); ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom gap-3">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h2 mb-0">Message History</h1>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Filter Messages</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-3">
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
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="sent" <?php echo $filter_status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="delivered" <?php echo $filter_status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="read" <?php echo $filter_status === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">All Types</option>
                                <option value="instant" <?php echo $filter_type === 'instant' ? 'selected' : ''; ?>>Instant</option>
                                <option value="scheduled" <?php echo $filter_type === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="template" <?php echo $filter_type === 'template' ? 'selected' : ''; ?>>Template</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by group or content" value="<?php echo $search; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $filter_date_from; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $filter_date_to; ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="d-grid gap-2 d-md-flex w-100">
                                <button type="submit" class="btn btn-primary me-md-2">Filter</button>
                                <a href="message_history.php" class="btn btn-outline-secondary">Reset Filters</a>
                            </div>
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
                                        <th>Date & Time</th>
                                        <th>Group</th>
                                        <th>Message Type</th>
                                        <th>Content Preview</th>
                                        <th>Status</th>
                                        <th width="100">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?php echo date('d M Y H:i', strtotime($row['sent_at'])); ?></td>
                                            <td><?php echo $row['group_name']; ?></td>
                                            <td>
                                                <?php if ($row['is_instant'] == 1): ?>
                                                    <span class="badge bg-info">Instant</span>
                                                <?php elseif (!empty($row['scheduled_id'])): ?>
                                                    <span class="badge bg-warning">Scheduled</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Template</span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($row['template_name'])): ?>
                                                    <br><small class="text-muted"><?php echo $row['template_name']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                // Show a preview of the message content
                                                $preview = $row['message_content'];
                                                echo strlen($preview) > 50 ? substr($preview, 0, 50) . '...' : $preview; 
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($row['status'] == 'sent'): ?>
                                                    <span class="badge bg-primary">Sent</span>
                                                <?php elseif ($row['status'] == 'delivered'): ?>
                                                    <span class="badge bg-info">Delivered</span>
                                                <?php elseif ($row['status'] == 'read'): ?>
                                                    <span class="badge bg-success">Read</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info view-message" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-group-name="<?php echo $row['group_name']; ?>"
                                                        data-account-name="<?php echo $row['account_name']; ?>"
                                                        data-message-content="<?php echo htmlspecialchars($row['message_content']); ?>"
                                                        data-image-url="<?php echo $row['image_url']; ?>"
                                                        data-promotion-content="<?php echo htmlspecialchars($row['promotion_content']); ?>"
                                                        data-footer-content="<?php echo htmlspecialchars($row['footer_content']); ?>"
                                                        data-status="<?php echo $row['status']; ?>"
                                                        data-sent-at="<?php echo date('d M Y H:i:s', strtotime($row['sent_at'])); ?>"
                                                        data-error-message="<?php echo htmlspecialchars($row['error_message']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#viewMessageModal">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mt-4">
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
                        <div class="alert alert-info mb-0">
                            <p class="mb-0">No message history found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMessageModalLabel">View Message Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Group:</strong> <span id="view_group_name"></span></p>
                        <p><strong>WhatsApp Account:</strong> <span id="view_account_name"></span></p>
                        <p><strong>Status:</strong> <span id="view_status"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Sent At:</strong> <span id="view_sent_at"></span></p>
                        <p id="view_error_container" style="display: none;">
                            <strong>Error:</strong> <span id="view_error_message" class="text-danger"></span>
                        </p>
                    </div>
                </div>
                
                <div id="view_image_container" class="text-center mb-3" style="display: none;">
                    <img id="view_image" src="" alt="Message Image" class="img-fluid" style="max-height: 300px;" onerror="this.src='../assets/img/image-placeholder.png';">
                </div>
                
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0">Message Content</h6>
                    </div>
                    <div class="card-body">
                        <pre id="view_message_content" class="mb-0" style="white-space: pre-wrap; font-family: inherit;"></pre>
                    </div>
                </div>
                
                <div id="view_promotion_container" class="card mb-3" style="display: none;">
                    <div class="card-header">
                        <h6 class="mb-0">Promotion Content</h6>
                    </div>
                    <div class="card-body">
                        <pre id="view_promotion_content" class="mb-0" style="white-space: pre-wrap; font-family: inherit;"></pre>
                    </div>
                </div>
                
                <div id="view_footer_container" class="card" style="display: none;">
                    <div class="card-header">
                        <h6 class="mb-0">Footer Content</h6>
                    </div>
                    <div class="card-body">
                        <pre id="view_footer_content" class="mb-0" style="white-space: pre-wrap; font-family: inherit;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            
            // Set status with badge
            const status = this.getAttribute('data-status');
            let statusBadge = '';
            
            if (status === 'sent') {
                statusBadge = '<span class="badge bg-primary">Sent</span>';
            } else if (status === 'delivered') {
                statusBadge = '<span class="badge bg-info">Delivered</span>';
            } else if (status === 'read') {
                statusBadge = '<span class="badge bg-success">Read</span>';
            } else {
                statusBadge = '<span class="badge bg-danger">Failed</span>';
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