<?php
require_once('../config/db_connect.php');
require_once('../includes/functions.php');

// Check if user is logged in
check_login();

// Ambil semua template milik user
$templates_query = "
    SELECT mt.id, mt.template_name, mt.message_content, wn.account_name
    FROM message_templates mt
    JOIN whatsapp_numbers wn ON mt.whatsapp_number_id = wn.id
    WHERE wn.user_id = " . $_SESSION['user_id'] . "
    ORDER BY mt.template_name ASC";
$templates_result = mysqli_query($conn, $templates_query);

// Ambil semua footer aktif milik user
$footers_query = "
    SELECT f.id, f.footer_name, f.footer_content
    FROM footers f
    JOIN whatsapp_numbers wn ON f.whatsapp_number_id = wn.id
    WHERE wn.user_id = " . $_SESSION['user_id'] . " AND f.active = 1
    ORDER BY f.footer_name ASC";
$footers_result = mysqli_query($conn, $footers_query);

// Ambil semua promosi aktif milik user
$promotions_query = "
    SELECT p.id, p.promotion_name, p.promotion_content
    FROM promotions p
    JOIN whatsapp_numbers wn ON p.whatsapp_number_id = wn.id
    WHERE wn.user_id = " . $_SESSION['user_id'] . " AND p.active = 1
    ORDER BY p.promotion_name ASC";
$promotions_result = mysqli_query($conn, $promotions_query);

include('../includes/header.php');
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include('../includes/sidebar.php'); ?>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Copy Content</h1>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (mysqli_num_rows($templates_result) == 0): ?>
                        <div class="alert alert-warning">
                            <p class="mb-0">You need to create at least one <a href="message_templates.php" class="alert-link">Message Template</a> first.</p>
                        </div>
                    <?php else: ?>
                        <form id="copyContentForm">
                            <!-- Template Selection -->
                            <div class="mb-3">
                                <label for="templateSelect" class="form-label">Select Message Template *</label>
                                <select class="form-select" id="templateSelect" required>
                                    <option value="">-- Choose a template --</option>
                                    <?php while($row = mysqli_fetch_assoc($templates_result)): ?>
                                        <option value="<?php echo htmlspecialchars($row['message_content']); ?>" data-name="<?php echo $row['template_name']; ?>">
                                            <?php echo $row['template_name']; ?> (<?php echo $row['account_name']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Footer Checkbox -->
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="includeFooter">
                                    <label class="form-check-label" for="includeFooter">Include Footer</label>
                                </div>
                                <div id="footerSelectContainer" class="mt-2" style="display:none;">
                                    <select class="form-select" id="footerSelect">
                                        <option value="">-- Choose a footer --</option>
                                        <?php mysqli_data_seek($footers_result, 0); while($row = mysqli_fetch_assoc($footers_result)): ?>
                                            <option value="<?php echo htmlspecialchars($row['footer_content']); ?>">
                                                <?php echo $row['footer_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Promotion Checkbox -->
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="includePromotion">
                                    <label class="form-check-label" for="includePromotion">Include Promotion</label>
                                </div>
                                <div id="promotionSelectContainer" class="mt-2" style="display:none;">
                                    <select class="form-select" id="promotionSelect">
                                        <option value="">-- Choose a promotion --</option>
                                        <?php mysqli_data_seek($promotions_result, 0); while($row = mysqli_fetch_assoc($promotions_result)): ?>
                                            <option value="<?php echo htmlspecialchars($row['promotion_content']); ?>">
                                                <?php echo $row['promotion_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Preview -->
                            <div class="mb-3">
                                <label class="form-label">Preview</label>
                                <div class="card">
                                    <div class="card-body" id="previewContent" style="min-height: 100px; white-space: pre-wrap; font-family: inherit;">
                                        <em>Select a template to preview.</em>
                                    </div>
                                </div>
                            </div>

                            <!-- Copy Button -->
                            <button type="button" class="btn btn-success" id="copyButton" disabled>
                                <i class="bi bi-clipboard"></i> Copy to Clipboard
                            </button>
                            <span id="copyStatus" class="ms-2 text-success" style="display:none;">Copied!</span>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const templateSelect = document.getElementById('templateSelect');
    const includeFooter = document.getElementById('includeFooter');
    const includePromotion = document.getElementById('includePromotion');
    const footerSelectContainer = document.getElementById('footerSelectContainer');
    const promotionSelectContainer = document.getElementById('promotionSelectContainer');
    const footerSelect = document.getElementById('footerSelect');
    const promotionSelect = document.getElementById('promotionSelect');
    const previewContent = document.getElementById('previewContent');
    const copyButton = document.getElementById('copyButton');
    const copyStatus = document.getElementById('copyStatus');

    function updatePreview() {
        let content = '';
        const selectedTemplate = templateSelect.value;

        if (!selectedTemplate) {
            previewContent.innerHTML = '<em>Select a template to preview.</em>';
            copyButton.disabled = true;
            return;
        }

        content += selectedTemplate;

        if (includeFooter.checked && footerSelect.value) {
            content += '\n\n' + footerSelect.value;
        }

        if (includePromotion.checked && promotionSelect.value) {
            content += '\n\n' + promotionSelect.value;
        }

        previewContent.textContent = content;
        copyButton.disabled = false;
    }

    // Toggle footer select
    includeFooter.addEventListener('change', function () {
        footerSelectContainer.style.display = this.checked ? 'block' : 'none';
        updatePreview();
    });

    // Toggle promotion select
    includePromotion.addEventListener('change', function () {
        promotionSelectContainer.style.display = this.checked ? 'block' : 'none';
        updatePreview();
    });

    // Update on any select change
    templateSelect.addEventListener('change', updatePreview);
    footerSelect.addEventListener('change', updatePreview);
    promotionSelect.addEventListener('change', updatePreview);

    // Copy to clipboard
    copyButton.addEventListener('click', function () {
        const textToCopy = previewContent.textContent;
        if (!textToCopy || textToCopy.includes('Select a template')) return;

        navigator.clipboard.writeText(textToCopy).then(() => {
            copyStatus.style.display = 'inline';
            setTimeout(() => {
                copyStatus.style.display = 'none';
            }, 2000);
        }).catch(err => {
            alert('Failed to copy: ' + err);
        });
    });
});
</script>

<?php include('../includes/footer.php'); ?>