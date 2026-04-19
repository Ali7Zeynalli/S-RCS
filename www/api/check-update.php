<?php
/*
 * Copyright (C) [2025] [Ali Zeynalli] - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by [Ali Zeynalli] <[https://linkedin.com/in/ali7zeynalli]> [2025]
 */

// S-RCS Update Check Endpoint
// GitHub repo-sundakı VERSION faylını çəkib cari versiya ilə müqayisə edir.
// Cavab JSON: { updateAvailable, currentVersion, remoteVersion, changes[], repoUrl }

session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Update check açıqdır (public) — versiya məlumatı sensitive deyil.
// Login səhifəsində də işləsin (footer.php hər yerdə include olunur).
// Amma brute-force qarşısı üçün sadə rate limit tətbiq edək:
$rate_key = 'update_check_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (isset($_SESSION[$rate_key]) && (time() - $_SESSION[$rate_key]) < 5) {
    // 5 saniyə ərzində ikinci sorğu yoxdur
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests']);
    exit;
}
$_SESSION[$rate_key] = time();

// Konfiqurasiya
$REPO_OWNER = 'Ali7Zeynalli';
$REPO_NAME  = 'S-RCS';
$BRANCH     = 'main';
$REPO_URL   = "https://github.com/$REPO_OWNER/$REPO_NAME";

// Cari versiyanı oxu
function getCurrentVersion() {
    $version_file = __DIR__ . '/../VERSION';
    if (file_exists($version_file)) {
        return trim(file_get_contents($version_file));
    }
    // Fallback: config-dən
    $config = @require(__DIR__ . '/../config/config.php');
    return $config['installation']['version'] ?? '0.0.0';
}

// GitHub-dan raw fayl çək (curl ilə — timeout qorunması)
function githubRawFetch($file, $owner, $repo, $branch) {
    $url = "https://raw.githubusercontent.com/$owner/$repo/$branch/$file";

    if (!function_exists('curl_init')) {
        // curl yoxdursa file_get_contents istifadə et
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'S-RCS-UpdateCheck']]);
        $content = @file_get_contents($url, false, $ctx);
        return $content !== false ? $content : null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT      => 'S-RCS-UpdateCheck/1.0',
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($http_code === 200 && $content !== false) ? $content : null;
}

// Semver müqayisə: remote > current olarsa true
function isNewerVersion($current, $remote) {
    $c = array_map('intval', explode('.', $current));
    $r = array_map('intval', explode('.', $remote));
    for ($i = 0; $i < 3; $i++) {
        $cv = $c[$i] ?? 0;
        $rv = $r[$i] ?? 0;
        if ($rv > $cv) return true;
        if ($rv < $cv) return false;
    }
    return false;
}

// CHANGELOG.md-dən son dəyişiklikləri çıxart
function extractRecentChanges($changelog_content) {
    if (!$changelog_content) return [];

    $lines = explode("\n", $changelog_content);
    $changes = [];
    $section_started = false;

    foreach ($lines as $line) {
        $line = trim($line);

        // İlk versiya başlığından sonra başla
        if (preg_match('/^##\s+\[?\d+\.\d+\.\d+/', $line)) {
            if ($section_started) break; // ikinci versiyaya çatanda dayan
            $section_started = true;
            continue;
        }

        if ($section_started) {
            // Bullet point-ları yığ
            if (preg_match('/^[-*]\s+(.+)/', $line, $m)) {
                $changes[] = $m[1];
                if (count($changes) >= 10) break;
            }
        }
    }

    return $changes;
}

try {
    $currentVersion = getCurrentVersion();

    // GitHub-dan remote VERSION faylını çək
    $remoteContent = githubRawFetch('www/VERSION', $REPO_OWNER, $REPO_NAME, $BRANCH);
    if ($remoteContent === null) {
        throw new Exception('GitHub-a bağlantı mümkün olmadı');
    }

    $remoteVersion = trim($remoteContent);
    if (!preg_match('/^\d+\.\d+\.\d+$/', $remoteVersion)) {
        throw new Exception('Uzaq versiya formatı yanlışdır: ' . $remoteVersion);
    }

    $hasUpdate = isNewerVersion($currentVersion, $remoteVersion);

    // Update varsa CHANGELOG-u da çək
    $changes = [];
    if ($hasUpdate) {
        $changelog = githubRawFetch('CHANGELOG.md', $REPO_OWNER, $REPO_NAME, $BRANCH);
        $changes = extractRecentChanges($changelog);
    }

    echo json_encode([
        'updateAvailable' => $hasUpdate,
        'currentVersion'  => $currentVersion,
        'remoteVersion'   => $remoteVersion,
        'changes'         => $changes,
        'repoUrl'         => $REPO_URL,
        'releaseUrl'      => "$REPO_URL/releases/tag/v$remoteVersion",
        'checkedAt'       => date('c')
    ]);

} catch (Exception $e) {
    error_log('Update check failed: ' . $e->getMessage());
    echo json_encode([
        'updateAvailable' => false,
        'currentVersion'  => getCurrentVersion(),
        'error'           => $e->getMessage(),
        'repoUrl'         => $REPO_URL
    ]);
}
