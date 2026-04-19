<?php
 /*
  * Copyright (C) [2025] [Ali Zeynalli] - All Rights Reserved
  * Unauthorized copying of this file, via any medium is strictly prohibited
  * Proprietary and confidential
  * Written by [Ali Zeynalli] <[https://linkedin.com/in/ali7zeynalli]> [2025]
  */

// Config faylını əlavə et
$config = require(__DIR__ . '/../config/config.php');

// Versiyanı VERSION faylından oxu (release-də avto yenilənir)
$version_file = __DIR__ . '/../VERSION';
if (file_exists($version_file)) {
    $version = trim(file_get_contents($version_file));
} else {
    $version = $config['installation']['version'] ?? '1.0.0';
}

?>
        </main>
    </div>
</div>

<footer class="footer mt-auto py-2 bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-4 text-start">
                <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-2">
                    <span class="text-muted small">
                        <i class="fas fa-copyright me-1"></i><?php echo __('footer_copyright'); ?>
                    </span>
                  
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="btn-group">
                    <a href="contact.php" class="btn btn-outline-primary btn-sm py-1 px-2">
                        <i class="fas fa-envelope me-1"></i>
                        <?php echo __('footer_contact'); ?>
                    </a>
                    &nbsp; &nbsp;
                    <a href="feedback.php" class="btn btn-outline-primary btn-sm py-1 px-2">
                        <i class="fas fa-comment-alt me-1"></i>
                        <?php echo __('footer_feedback'); ?>
                    </a>
                    &nbsp; &nbsp;
                    <a href="https://ali7zeynalli.github.io/SRCS/docs.html#intro" target="_blank" class="btn btn-outline-info btn-sm py-1 px-2">
                        <i class="fas fa-book me-1"></i>
                        <?php echo __('footer_docs'); ?>
                    </a>
                </div>
                <div class="mt-2 small text-nowrap" style="white-space:nowrap;">
                    <span class="text-muted"><?php echo __('footer_also_try'); ?></span>
                    <a href="https://github.com/Ali7Zeynalli/dockgate" target="_blank" rel="noopener" class="text-decoration-none ms-2" title="DockGate — <?php echo __('footer_dockgate_desc'); ?>">
                        <i class="fab fa-docker" style="color: #2496ED;"></i>
                        <strong class="text-primary">DockGate</strong>
                    </a>
                    <span class="text-muted mx-1">·</span>
                    <a href="https://github.com/Ali7Zeynalli/NovusGate" target="_blank" rel="noopener" class="text-decoration-none" title="NovusGate — <?php echo __('footer_novusgate_desc'); ?>">
                        <i class="fas fa-shield-halved" style="color: #88171A;"></i>
                        <strong style="color: #88171A;">NovusGate</strong>
                    </a>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <!-- LinkedIn -->
                <a href="https://linkedin.com/in/ali7zeynalli" target="_blank" rel="noopener" class="text-decoration-none me-2" title="LinkedIn — Ali Zeynalli">
                    <i class="fab fa-linkedin" style="color: #0A66C2;"></i>
                    <span class="text-primary small"><?php echo __('footer_website'); ?></span>
                </a>

                <!-- GitHub -->
                <a href="https://github.com/Ali7Zeynalli/S-RCS" target="_blank" rel="noopener" class="text-decoration-none me-2" title="GitHub — S-RCS Repository">
                    <i class="fab fa-github" style="color: #24292F;"></i>
                    <span class="small text-dark">GitHub</span>
                </a>

                <!-- Version -->
                <span class="text-muted small version-badge" id="srcsVersionBadge" data-current-version="<?php echo htmlspecialchars($version); ?>">
                    <i class="fas fa-code-branch me-1"></i><?php echo __('footer_version'); ?><?php echo htmlspecialchars($version); ?>
                </span>

                <!-- Update badge -->
                <a href="#" id="srcsUpdateBadge" class="badge bg-success text-decoration-none ms-2" style="display:none;" data-bs-toggle="modal" data-bs-target="#srcsUpdateModal" title="New update available">
                    <i class="fas fa-arrow-up me-1"></i>UPDATE
                </a>
            </div>
        </div>
    </div>
</footer>

<!-- Update styles to match system theme -->
<link href="temp/css/footer.css" rel="stylesheet">

<!-- S-RCS Update Modal -->
<div class="modal fade" id="srcsUpdateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-download me-2"></i><?php echo __('update_modal_title'); ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Version comparison -->
                <div class="d-flex justify-content-around text-center mb-4 p-3 bg-light rounded">
                    <div>
                        <small class="text-muted d-block"><?php echo __('update_current'); ?></small>
                        <h3 class="mb-0 text-secondary"><span id="srcsCurrentVer">—</span></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-arrow-right fa-2x text-success"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block"><?php echo __('update_new'); ?></small>
                        <h3 class="mb-0 text-success fw-bold"><span id="srcsRemoteVer">—</span></h3>
                    </div>
                </div>

                <!-- What's new -->
                <h6 class="border-bottom pb-2"><i class="fas fa-star text-warning me-2"></i><?php echo __('update_whats_new'); ?></h6>
                <ul id="srcsChangeList" class="small text-muted">
                    <li><?php echo __('loading'); ?></li>
                </ul>

                <!-- Backup warning -->
                <div class="alert alert-warning small mt-3 mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong><?php echo __('update_before_title'); ?></strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <li><?php echo __('update_before_sessions'); ?></li>
                        <li><?php echo __('update_before_backup'); ?> <code>tar czf backup.tar.gz mysql_data/ www/config/</code></li>
                    </ul>
                </div>

                <!-- Step-by-step instructions -->
                <h6 class="border-bottom pb-2 mt-4">
                    <i class="fas fa-terminal text-primary me-2"></i><?php echo __('update_howto_title'); ?>
                </h6>
                <p class="small text-muted mb-2"><?php echo __('update_howto_intro'); ?></p>

                <!-- Step 1 -->
                <div class="mb-2">
                    <div class="d-flex align-items-center mb-1">
                        <span class="badge bg-primary me-2">1</span>
                        <small class="text-muted"><?php echo __('update_step1'); ?></small>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace bg-dark text-light" value="cd /path/to/S-RCS" readonly>
                        <button class="btn btn-outline-secondary srcs-copy-cmd" type="button" data-cmd="cd /path/to/S-RCS">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="mb-2">
                    <div class="d-flex align-items-center mb-1">
                        <span class="badge bg-primary me-2">2</span>
                        <small class="text-muted"><?php echo __('update_step2'); ?></small>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace bg-dark text-light" value="git pull origin main" readonly>
                        <button class="btn btn-outline-secondary srcs-copy-cmd" type="button" data-cmd="git pull origin main">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="mb-2">
                    <div class="d-flex align-items-center mb-1">
                        <span class="badge bg-primary me-2">3</span>
                        <small class="text-muted"><?php echo __('update_step3'); ?></small>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace bg-dark text-light" value="docker-compose down" readonly>
                        <button class="btn btn-outline-secondary srcs-copy-cmd" type="button" data-cmd="docker-compose down">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-shield-alt text-success me-1"></i>
                        <?php echo __('update_step3_safe'); ?>
                    </small>
                </div>

                <!-- Step 4 -->
                <div class="mb-2">
                    <div class="d-flex align-items-center mb-1">
                        <span class="badge bg-primary me-2">4</span>
                        <small class="text-muted"><?php echo __('update_step4'); ?></small>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace bg-dark text-light" value="docker-compose up -d --build" readonly>
                        <button class="btn btn-outline-secondary srcs-copy-cmd" type="button" data-cmd="docker-compose up -d --build">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i><?php echo __('update_step4_time'); ?>
                    </small>
                </div>

                <!-- Step 5 -->
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-1">
                        <span class="badge bg-success me-2">5</span>
                        <small class="text-muted"><?php echo __('update_step5'); ?></small>
                    </div>
                </div>

                <!-- All-in-one command -->
                <div class="alert alert-info small mb-0">
                    <strong><i class="fas fa-bolt text-warning me-1"></i><?php echo __('update_all_in_one'); ?></strong>
                    <div class="input-group input-group-sm mt-2">
                        <input type="text" class="form-control font-monospace bg-dark text-light" id="srcsAllInOneCmd" value="cd /path/to/S-RCS && git pull origin main && docker-compose down && docker-compose up -d --build" readonly>
                        <button class="btn btn-primary srcs-copy-cmd" type="button" data-cmd="cd /path/to/S-RCS && git pull origin main && docker-compose down && docker-compose up -d --build">
                            <i class="fas fa-copy me-1"></i><?php echo __('update_copy'); ?>
                        </button>
                    </div>
                </div>

                <!-- Troubleshooting -->
                <details class="mt-3">
                    <summary class="small text-muted" style="cursor: pointer;">
                        <i class="fas fa-question-circle me-1"></i><?php echo __('update_troubleshoot'); ?>
                    </summary>
                    <div class="small text-muted mt-2 ps-3">
                        <p class="mb-1"><strong><?php echo __('update_troubleshoot_logs'); ?></strong></p>
                        <code>docker-compose logs -f apache</code>
                        <p class="mb-1 mt-2"><strong><?php echo __('update_troubleshoot_restore'); ?></strong></p>
                        <code>tar xzf backup.tar.gz</code>
                        <p class="mb-0 mt-2">
                            <?php echo __('update_troubleshoot_stuck'); ?> <a href="https://github.com/Ali7Zeynalli/S-RCS/issues" target="_blank"><?php echo __('update_troubleshoot_issue'); ?></a>
                        </p>
                    </div>
                </details>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-clock me-1"></i><?php echo __('update_btn_later'); ?>
                </button>
                <a href="#" id="srcsReleaseLink" target="_blank" class="btn btn-outline-primary">
                    <i class="fab fa-github me-1"></i><?php echo __('update_btn_github'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript faylları -->
<script src="temp/assets/lib/bootstrap/bootstrap.bundle.min.js"></script>
<script src="temp/assets/lib/jquery/jquery.min.js"></script>
<script src="temp/assets/lib/popper/popper.min.js"></script>

<!-- S-RCS Update Checker -->
<script>
(function() {
    'use strict';
    const CHECK_INTERVAL = 24 * 60 * 60 * 1000; // 24 saat
    const CACHE_KEY_LAST = 'srcs_update_last_check';
    const CACHE_KEY_INFO = 'srcs_update_info';

    async function checkForUpdates(force) {
        try {
            const now = Date.now();
            const lastCheck = parseInt(localStorage.getItem(CACHE_KEY_LAST) || '0', 10);

            if (!force && lastCheck && (now - lastCheck) < CHECK_INTERVAL) {
                const cached = localStorage.getItem(CACHE_KEY_INFO);
                if (cached) {
                    applyUpdateInfo(JSON.parse(cached));
                    return;
                }
            }

            const response = await fetch('api/check-update.php', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) return;

            const info = await response.json();
            localStorage.setItem(CACHE_KEY_LAST, String(now));
            localStorage.setItem(CACHE_KEY_INFO, JSON.stringify(info));
            applyUpdateInfo(info);
        } catch (e) {
            // Şəbəkə xətası — sakitcə keç
            console.debug('[S-RCS] Update check failed:', e.message);
        }
    }

    function applyUpdateInfo(info) {
        if (!info || !info.updateAvailable) {
            hideBadge();
            return;
        }

        showBadge();

        const curVer = document.getElementById('srcsCurrentVer');
        const remVer = document.getElementById('srcsRemoteVer');
        const changeList = document.getElementById('srcsChangeList');
        const releaseLink = document.getElementById('srcsReleaseLink');

        if (curVer) curVer.textContent = info.currentVersion || '—';
        if (remVer) remVer.textContent = info.remoteVersion || '—';
        if (releaseLink) releaseLink.href = info.releaseUrl || info.repoUrl;

        if (changeList) {
            if (info.changes && info.changes.length > 0) {
                changeList.innerHTML = info.changes
                    .map(c => '<li>' + escapeHtml(c) + '</li>')
                    .join('');
            } else {
                changeList.innerHTML = '<li>Detallar GitHub-da mövcuddur</li>';
            }
        }
    }

    function showBadge() {
        const badge = document.getElementById('srcsUpdateBadge');
        if (badge) badge.style.display = 'inline-block';
    }
    function hideBadge() {
        const badge = document.getElementById('srcsUpdateBadge');
        if (badge) badge.style.display = 'none';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    // Copy-to-clipboard funksionallığı
    function setupCopyButtons() {
        document.querySelectorAll('.srcs-copy-cmd').forEach(btn => {
            btn.addEventListener('click', async function() {
                const cmd = this.dataset.cmd;
                if (!cmd) return;

                try {
                    await navigator.clipboard.writeText(cmd);

                    // Visual feedback
                    const originalHtml = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i>';
                    this.classList.add('btn-success');
                    this.classList.remove('btn-outline-secondary', 'btn-primary');

                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                        this.classList.remove('btn-success');
                        if (originalHtml.includes('Copy')) {
                            this.classList.add('btn-primary');
                        } else {
                            this.classList.add('btn-outline-secondary');
                        }
                    }, 1500);
                } catch (e) {
                    // Fallback — əsas input-u seç
                    const input = this.previousElementSibling;
                    if (input && input.select) {
                        input.select();
                    }
                }
            });
        });
    }

    // Səhifə yüklənən kimi və hər 24 saatda bir
    document.addEventListener('DOMContentLoaded', () => {
        checkForUpdates(false);
        setupCopyButtons();
    });
    setInterval(() => checkForUpdates(false), CHECK_INTERVAL);
})();
</script>
</body>
</html>