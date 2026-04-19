#!/bin/bash
set -e

# S-RCS Container Entrypoint
# Bu script konteyner başlayanda volume mount-dan sonra icra olunur.
# Volume mount host-dakı permission-ları saxlayır, ona görə burada
# www-data user-inə yazma icazəsi vermək lazımdır.

APP_DIR="/var/www/html"

echo "[entrypoint] Fixing ownership and permissions..."

# Yazma tələb edən qovluqlar
WRITABLE_DIRS=(
    "$APP_DIR/config"
    "$APP_DIR/temp"
    "$APP_DIR/temp/secure_store"
    "$APP_DIR/reports"
)

for dir in "${WRITABLE_DIRS[@]}"; do
    # Qovluq yoxdursa yarad
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir" 2>/dev/null || true
        echo "[entrypoint] Created: $dir"
    fi

    # Həm konteynerdəki www-data, həm host-dakı istifadəçi yaza bilsin:
    # - Group-u www-data et, group yazma icazəsi ver (775)
    # - Setgid bit (2) — alt-qovluqlar da group-u inherit etsin
    # Bu, host-dakı istifadəçi www-data group-a üzv olmasa belə işləyir
    # çünki fayllar onsuz da onun own-ındadır (volume mount).
    if [ -d "$dir" ]; then
        chgrp -R www-data "$dir" 2>/dev/null || true
        chmod -R ug+rwX,o+rX "$dir" 2>/dev/null || true
        chmod g+s "$dir" 2>/dev/null || true
    fi
done

# Əgər config.php yoxdursa, default şablondan yarad
# (fresh install: istifadəçi git-dən clone edib, config.php yoxdur)
# İnstaller ilk girişdə bunu istifadəçinin datasına yenidən yazacaq
if [ ! -f "$APP_DIR/config/config.php" ]; then
    if [ -f "$APP_DIR/includes/default-config.php" ]; then
        cp "$APP_DIR/includes/default-config.php" "$APP_DIR/config/config.php"
        echo "[entrypoint] Created config.php from default-config.php template"
    fi
fi

# Konfiqurasiya faylları üçün ayrıca — yaratma və yazma icazəsi
if [ -f "$APP_DIR/config/config.php" ]; then
    chgrp www-data "$APP_DIR/config/config.php" 2>/dev/null || true
    chmod ug+rw,o+r "$APP_DIR/config/config.php" 2>/dev/null || true
fi

# Favicon.ico yoxdursa, logo-nu kopyala (brauzer 500 error verməsin)
if [ ! -f "$APP_DIR/favicon.ico" ] && [ -f "$APP_DIR/temp/assets/images/logo.png" ]; then
    cp "$APP_DIR/temp/assets/images/logo.png" "$APP_DIR/favicon.ico" 2>/dev/null || true
    chown www-data:www-data "$APP_DIR/favicon.ico" 2>/dev/null || true
    echo "[entrypoint] Created favicon.ico from logo.png"
fi

echo "[entrypoint] Permissions fixed. Starting Apache..."

# Orijinal komanda-nı icra et (apache2-foreground)
exec "$@"
