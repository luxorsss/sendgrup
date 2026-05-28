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
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content utilitarian-builder">
            
            <style>
                .utilitarian-builder .form-control,
                .utilitarian-builder .form-select {
                    border-radius: 4px;
                    border: 1px solid var(--border-color);
                    padding: 0.85rem 1rem;
                    font-family: 'Satoshi', sans-serif;
                    background-color: transparent;
                    transition: border-color 150ms var(--ease-out), box-shadow 150ms var(--ease-out);
                }
                .utilitarian-builder .form-control:focus,
                .utilitarian-builder .form-select:focus {
                    border-color: var(--ink);
                    box-shadow: 3px 3px 0px rgba(10,10,10,0.1); 
                    outline: none;
                }
                .utilitarian-builder .form-label {
                    font-weight: 600;
                    font-size: 0.85rem;
                    margin-bottom: 0.5rem;
                    color: var(--ink);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    font-family: 'Geist Mono', monospace;
                }
                .module-box {
                    border: 1px solid var(--border-color);
                    border-radius: 4px;
                    padding: 1.25rem;
                    margin-bottom: 1.25rem;
                    background: transparent;
                    transition: border-color 200ms ease;
                }
                .module-box:focus-within {
                    border-color: var(--ink);
                }
                .terminal-preview {
                    background: #111111; /* Gelap khas terminal */
                    color: #00FF41; /* Hijau matrix */
                    font-family: 'Geist Mono', monospace;
                    font-size: 0.85rem;
                    padding: 1.5rem;
                    border-radius: 4px;
                    min-height: 250px;
                    white-space: pre-wrap;
                    border: 2px solid var(--ink);
                    box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
                    line-height: 1.6;
                }
                .terminal-preview:empty::before {
                    content: "> MENUNGGU INPUT PAYLOAD...";
                    color: rgba(0, 255, 65, 0.4);
                }
                .copy-action-btn {
                    background-color: var(--accent);
                    color: var(--surface);
                    border: none;
                    padding: 1rem 2rem;
                    font-family: 'Geist Mono', monospace;
                    font-weight: 600;
                    font-size: 1rem;
                    border-radius: 4px;
                    cursor: pointer;
                    width: 100%;
                    transition: transform 160ms var(--ease-out), background-color 160ms var(--ease-out);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }
                .copy-action-btn:active {
                    transform: scale(0.98);
                }
                .copy-action-btn:disabled {
                    background-color: var(--border-color);
                    color: var(--ink-muted);
                    cursor: not-allowed;
                    transform: none;
                }
            </style>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-end pt-4 pb-3 mb-4" style="border-bottom: 2px solid var(--ink);">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" style="border-radius: 4px;">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h2 mb-0" style="font-family: 'Clash Display', sans-serif; font-weight: 600;">Payload Generator</h1>
                        <span class="font-mono text-muted" style="font-size: 0.85rem; display: block; margin-top: 5px;">ASSEMBLE & COPY TRANSMISSION</span>
                    </div>
                </div>
            </div>

            <?php if (mysqli_num_rows($templates_result) == 0): ?>
                <div class="alert mt-3" style="border-radius: 4px; border: 1px dashed var(--border-color); background: transparent; color: var(--ink-muted); text-align: center; padding: 4rem 1rem;">
                    <i class="bi bi-file-earmark-x" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--ink);"></i>
                    <p class="mb-0 font-mono" style="font-size: 1rem; font-weight: 500;">Tidak ada template yang tersedia.</p>
                    <p class="font-mono text-muted mt-2" style="font-size: 0.85rem;">Buat <a href="message_templates.php" class="text-primary text-decoration-none">Message Template</a> terlebih dahulu untuk menggunakan fitur ini.</p>
                </div>
            <?php else: ?>
                <div class="row mb-5">
                    <div class="col-lg-5 mb-4 mb-lg-0">
                        <div style="font-family: 'Geist Mono', monospace; font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            <i class="bi bi-tools me-2"></i> Konfigurasi Pesan
                        </div>
                        
                        <form id="copyContentForm">
                            <div class="module-box">
                                <label for="templateSelect" class="form-label"><i class="bi bi-file-text me-2"></i>1. Base Template *</label>
                                <select class="form-select" id="templateSelect" required>
                                    <option value="">-- Pilih Template Dasar --</option>
                                    <?php while($row = mysqli_fetch_assoc($templates_result)): ?>
                                        <option value="<?php echo htmlspecialchars($row['message_content']); ?>" data-name="<?php echo htmlspecialchars($row['template_name']); ?>">
                                            <?php echo htmlspecialchars($row['template_name']); ?> (<?php echo htmlspecialchars($row['account_name']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="module-box">
                                <div class="form-check mb-0 d-flex align-items-center">
                                    <input class="form-check-input me-2" type="checkbox" id="includePromotion" style="width: 1.2rem; height: 1.2rem; border-color: var(--ink); cursor: pointer;">
                                    <label class="form-check-label form-label mb-0" for="includePromotion" style="cursor: pointer;"><i class="bi bi-megaphone me-2"></i>2. Sisipkan Promosi</label>
                                </div>
                                <div id="promotionSelectContainer" class="mt-3" style="display:none; animation: fadeInRow 200ms ease-out;">
                                    <select class="form-select" id="promotionSelect">
                                        <option value="">-- Pilih Konten Promosi --</option>
                                        <?php mysqli_data_seek($promotions_result, 0); while($row = mysqli_fetch_assoc($promotions_result)): ?>
                                            <option value="<?php echo htmlspecialchars($row['promotion_content']); ?>">
                                                <?php echo htmlspecialchars($row['promotion_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="module-box">
                                <div class="form-check mb-0 d-flex align-items-center">
                                    <input class="form-check-input me-2" type="checkbox" id="includeFooter" style="width: 1.2rem; height: 1.2rem; border-color: var(--ink); cursor: pointer;">
                                    <label class="form-check-label form-label mb-0" for="includeFooter" style="cursor: pointer;"><i class="bi bi-card-text me-2"></i>3. Sisipkan Footer</label>
                                </div>
                                <div id="footerSelectContainer" class="mt-3" style="display:none; animation: fadeInRow 200ms ease-out;">
                                    <select class="form-select" id="footerSelect">
                                        <option value="">-- Pilih Footer / Signature --</option>
                                        <?php mysqli_data_seek($footers_result, 0); while($row = mysqli_fetch_assoc($footers_result)): ?>
                                            <option value="<?php echo htmlspecialchars($row['footer_content']); ?>">
                                                <?php echo htmlspecialchars($row['footer_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-lg-7">
                        <div style="font-family: 'Geist Mono', monospace; font-size: 0.85rem; color: var(--ink-muted); margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between;">
                            <span><i class="bi bi-terminal me-2"></i> Output Preview</span>
                            <span id="charCount" style="color: var(--accent);">0 CHARS</span>
                        </div>
                        
                        <div class="mb-4">
                            <div id="previewContent" class="terminal-preview"></div>
                        </div>

                        <button type="button" class="copy-action-btn" id="copyButton" disabled>
                            <i class="bi bi-clipboard me-2"></i> Copy Payload
                        </button>
                    </div>
                </div>
            <?php endif; ?>
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
    const charCount = document.getElementById('charCount');

    function updatePreview() {
        let content = '';
        const selectedTemplate = templateSelect.value;

        if (!selectedTemplate) {
            previewContent.textContent = '';
            charCount.textContent = '0 CHARS';
            copyButton.disabled = true;
            return;
        }

        content += selectedTemplate;

        if (includePromotion.checked && promotionSelect.value) {
            content += '\n\n' + promotionSelect.value;
        }

        if (includeFooter.checked && footerSelect.value) {
            content += '\n\n' + footerSelect.value;
        }

        previewContent.textContent = content;
        charCount.textContent = content.length + ' CHARS';
        copyButton.disabled = false;
    }

    // Toggle logic with visual feedback
    includeFooter.addEventListener('change', function () {
        footerSelectContainer.style.display = this.checked ? 'block' : 'none';
        if(!this.checked) footerSelect.value = ''; // Reset if unchecked
        updatePreview();
    });

    includePromotion.addEventListener('change', function () {
        promotionSelectContainer.style.display = this.checked ? 'block' : 'none';
        if(!this.checked) promotionSelect.value = ''; // Reset if unchecked
        updatePreview();
    });

    // Event listeners
    templateSelect.addEventListener('change', updatePreview);
    footerSelect.addEventListener('change', updatePreview);
    promotionSelect.addEventListener('change', updatePreview);

    // Copy to clipboard with UI Polish
    copyButton.addEventListener('click', function () {
        const textToCopy = previewContent.textContent;
        if (!textToCopy) return;

        navigator.clipboard.writeText(textToCopy).then(() => {
            // Animasi berhasil ala Design Engineering
            const originalText = this.innerHTML;
            const originalBg = this.style.backgroundColor;
            
            this.innerHTML = '<i class="bi bi-check2-all me-2"></i> PAYLOAD COPIED!';
            this.style.backgroundColor = '#2E7D32'; // Hijau Sukses
            this.style.transform = 'scale(0.98)';
            
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);

            setTimeout(() => {
                this.innerHTML = originalText;
                this.style.backgroundColor = originalBg;
            }, 2500);
            
        }).catch(err => {
            alert('Failed to copy: ' + err);
        });
    });
});
</script>

<?php include('../includes/footer.php'); ?>