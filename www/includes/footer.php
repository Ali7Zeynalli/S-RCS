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
            </div>
            <div class="col-md-4 text-end">
                <a href="https://linkedin.com/in/ali7zeynalli" target="_blank" class="text-decoration-none">
                    <span class="text-primary small">
                        <i class="fas fa-external-link-alt me-1"></i>
                        <?php echo __('footer_website'); ?>
                    </span>
                    &nbsp; &nbsp;
                </a>
                <span class="text-muted small version-badge" id="srcsVersionBadge" data-current-version="<?php echo htmlspecialchars($version); ?>">
                    <i class="fas fa-code-branch me-1"></i><?php echo __('footer_version'); ?><?php echo htmlspecialchars($version); ?>
                </span>
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-download me-2"></i>S-RCS — Yeni Versiya Mövcuddur
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-around text-center mb-3">
                    <div>
                        <small class="text-muted">Cari versiya</small>
                        <h4 class="mb-0"><span id="srcsCurrentVer">—</span></h4>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-arrow-right fa-2x text-success"></i>
                    </div>
                    <div>
                        <small class="text-muted">Yeni versiya</small>
                        <h4 class="mb-0 text-success"><span id="srcsRemoteVer">—</span></h4>
                    </div>
                </div>
                <h6><i class="fas fa-list me-2"></i>Yeniliklər:</h6>
                <ul id="srcsChangeList" class="small text-muted">
                    <li>Məlumat yüklənir...</li>
                </ul>
                <div class="alert alert-info small mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Necə yeniləmək:</strong>
                    <code>git pull && docker-compose up -d --build</code>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Sonra</button>
                <a href="#" id="srcsReleaseLink" target="_blank" class="btn btn-primary">
                    <i class="fab fa-github me-1"></i>GitHub-da Bax
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

    // Səhifə yüklənən kimi və hər 24 saatda bir
    document.addEventListener('DOMContentLoaded', () => checkForUpdates(false));
    setInterval(() => checkForUpdates(false), CHECK_INTERVAL);
})();
</script>
</body>
</html>