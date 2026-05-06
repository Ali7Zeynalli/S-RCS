# Dəyişiklik Jurnalı

S-RCS üzərindəki bütün əhəmiyyətli dəyişikliklər bu faylda sənədləşdiriləcək.

---

## [1.3.4] - 2026-04-19

### 🐛 Kritik Bug Fix — GPO Səhifəsi Yüklənməsi

Group Policy səhifəsi bir çox domendə loading spinner-də əbədi qalırdı. Root cause analizi 4 problem aşkar etdi — hamısı bu release-də həll edildi.

#### Nə sınmışdı
- **Spinner heç vaxt yox olmurdu** — yükləmə göstəricisi əbədi qalırdı, istifadəçi heç bir xəta görmürdü
- **Frontend parse fail-i tutub səssiz loading kimi göstərirdi** — backend korlanmış cavab qaytarırdı

#### Kök səbəblər (hamısı düzəldildi)

1. **Binary AD atributları `json_encode()`-i sındırırdı** — `gPCMachineExtensionNames` və `gPCUserExtensionNames` Active Directory-də binary octet string kimi saxlanılır. PHP-nin `json_encode()` invalid UTF-8 görəndə `false` qaytarır, response body boş olur. Fix: `JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE` flag-ları əlavə edildi.

2. **`ob_clean()` `ob_start()` olmadan notice-i JSON-a sızdırırdı** — `api/gpo.php` `ob_clean()`-i buffer açmadan çağırırdı, PHP notice yaradılaraq JSON cavabının qarşısına yerləşirdi. Frontend `JSON.parse()` fail edirdi, amma xəta catch handler tərəfindən yutulurdu. Fix: düzgün `ob_start()` / `ob_end_clean()` pattern-i tətbiq olundu (digər API endpoint-lərinə uyğun).

3. **PHP error suppression yoxdu** — `display_errors` bu endpoint üçün açıq şəkildə `0`-a təyin edilməyib idi. Fix: `ini_set('display_errors', '0')` + `error_reporting(E_ALL)` əlavə (xətalar `error_log` ilə loglanır, sadəcə cavaba sızmır).

4. **LDAP timeout-ları yoxdu** — LDAP server yavaşlasa və ya əlçatmaz olsaydı, `ldap_search()` PHP-nin 30 saniyəlik `max_execution_time`-ından çox hang ola bilərdi. Fix: `LDAP_OPT_NETWORK_TIMEOUT=10` və `LDAP_OPT_TIMELIMIT=20` əlavə edildi.

#### Təsirlənən fayl
- `www/api/gpo.php` — yalnız bu bir fayl dəyişdi; frontend `gpo.php` və digər endpoint-lər toxunulmadı.

#### Təsir
- ✅ **Kiçik domenlər (< 100 GPO)** — səhifə artıq 1-3 saniyəyə yüklənir (əvvəl əbədi hang olurdu)
- ✅ **Böyük domenlər (1000+ GPO)** — səhifə 5-15 saniyəyə yüklənir, LDAP yavaş olduqda düzgün xəta feedback-i ilə
- ✅ **Binary AD atributları** — artıq səssiz uğursuzluğa səbəb olmur
- ⚠️ **Çox böyük domenlər (5000+ OU)** — Addım 2 (gPLink olan bütün OU-ları toplama) hələ də 20 saniyə limitinə yaxınlaşa bilər; gələcək release cache əlavə edəcək

### 📋 Necə Tətbiq Etmək

```bash
git pull
docker-compose down
docker-compose up -d --build
```

Footer "UPDATE" badge-i mövcud install-larda bu versiyanı avtomatik aşkar edəcək (24 saatlıq yoxlama dövrü).

### 🔐 Breaking Changes

Yoxdur. Sadə bug fix, tam geri-uyğun.

---

## [1.3.3] - 2026-04-19

### ✨ Yeni Xüsusiyyətlər
- 🧪 **Quraşdırma Sihirbazında LDAP Bağlantı Testi** — Domain Settings mərhələsinə "Test Connection" düyməsi əlavə edildi. İstifadəçilər artıq AD məlumatlarını sihirbazı bitirmədən yoxlaya bilərlər.
  - Əlçatanlıq yoxlanışı (LDAPS/LDAP port)
  - Bind doğrulanması (istifadəçi adı + şifrə)
  - rootDSE-dən `Base DN`-in avtomatik aşkarlanması
  - Admin istifadəçinin konfiqurasiya edilmiş admin qrupun üzvü olub-olmadığının yoxlanışı
  - Cavab vaxtının millisaniyələrlə göstərilməsi
  - Uğursuzluq zamanı: troubleshooting ipuclar (firewall, SSL, məlumatlar)

### 🐛 Bug Fixes
- **Installer POST 500 Error** — `detectWebServer()` `$modules` istifadə edirdi `global $modules;` deklarasiyası olmadan, mail göstərişləri qurularkən undefined variable crash-ı.
- **System Identifier Docker Uyğunluğu** — `getSystemIdentifiers()` minimal Docker container-lərində mövcud olmayan `/etc/machine-id`, `lsblk`, `sudo dmidecode` istifadə edirdi. Graceful fallback əlavə edildi.
- **Update Check Modal i18n** — Modal artıq dil sisteminə tabedir. 5 dil (EN/AZ/DE/RU/TR) üçün 25 yeni tərcümə açarı əlavə edildi.

### 🔧 Təkmilləşdirmələr
- **Update Modal — Addım-Addım Təlimatlar** — Tək sətir komanda 5 nömrəli addımla əvəz olundu, hər birinin öz copy-to-clipboard düyməsi var. Backup tövsiyələri, troubleshooting bölməsi və "All-in-one" birləşdirilmiş komanda daxildir.
- **config.php Təhlükəsizliyi** — `www/config/config.php` git tracking-dən silindi (real deploy-larda secret_key, şifrələr, license_key saxlayırdı). `www/includes/default-config.php` template ilə əvəzləndi. Entrypoint script konteyner ilk işə düşəndə `config.php`-ni yaradır.

---

## [1.3.2] - 2026-04-19

### 🐛 Bug Fixes — Deployment & İnfrastruktur

- **SSL sertifikatı düzgün server cert** — Dockerfile əvvəllər `basicConstraints=CA:TRUE` (default) və `subjectAltName` olmadan sertifikat yaradırdı. Düzgün OpenSSL config ilə əvəzləndi: `CA:FALSE`, `extendedKeyUsage=serverAuth`, SAN ilə (`localhost`, `*.localhost`, `srcs`, `*.srcs.local`, `127.0.0.1`, `0.0.0.0`).
- **ServerName direktivi** — Global və per-VirtualHost `ServerName localhost` əlavə edildi (AH00558 warning aradan qaldırıldı).
- **SSL Stapling söndürüldü** — Self-signed sertifikatlar issuer info çəkə bilmirdi, stapling tamamilə sınıq idi.
- **HTTP → HTTPS redirect port fix** — Apache `https://host:<HTTP_PORT>`-a yönləndirirdi (eyni port), TLS ClientHello byte-ları HTTP port-una düşür və 400 Bad Request flood yaradırdı. Yeni `SRCS_HTTPS_PORT` env var redirect-i düzgün HTTPS port-una yönləndirir.
- **Quraşdırma Sihirbazı "Cannot read properties of undefined"** — Frontend `install.php` optional chaining olmadan istifadə edirdi. Defensive access (`Array.isArray`, `||` fallback) + backend hər zaman `manual_steps`, `system_detected`, `server_detected` qaytarır.
- **Config Directory yaza bilmirdi** — Bind mount `./www:/var/www/html` host `ali:ali` ownership saxlayırdı, Apache `www-data` yaza bilmirdi. `entrypoint.sh` əlavə edildi — `config/`, `temp/`, `temp/secure_store/`, `reports/` icazələrini düzəldir.
- **Login səhifəsində 401 update check** — `/api/check-update.php` session tələb edirdi. Endpoint public edildi (5 saniyəlik IP-əsaslı rate limit ilə).
- **Favicon 500 error** — Çatışmayan favicon 404 → `error.php` → 500 zənciri. `.htaccess` 204 qaytarır + entrypoint logo-dan favicon yaradır.
- **SweetAlert2 case-sensitivity** — Real qovluq `SweetAlert2` (PascalCase), amma `header.php` və `users.php` `sweetalert2` (lowercase) istifadə edirdi. Docker Linux-da görünürdü.

### 🔧 Yeni İnfrastruktur

- **`php/entrypoint.sh`** — Apache başlamazdan əvvəl işə düşən custom container entrypoint:
  - Çatışmayan yazılabilən qovluqları avtomatik yaradır
  - Volume-mount qovluqlarda ownership/permission düzəldir
  - Logo-dan avtomatik favicon yaradır

---

## [1.3.1] - 2026-04-19

### 🐛 Bug Fixes
- **Böyük domenlərdə Groups listing düzəldildi** — `getAllGroups()` artıq LDAP pagination (`LDAP_CONTROL_PAGEDRESULTS`) istifadə edir, AD-nin default 1000 MaxPageSize limitini keçir. 1000+ qrupu olan böyük təşkilatlar artıq JSON parse error almır.
- **GPO listing düzəldildi + performans boost** — `api/gpo.php` artıq pagination istifadə edir və N+1 query problemini aradan qaldırır. Əvvəl 100 GPO = 101 LDAP query (timeout), indi yalnız 2 query.
- **Report generation bərpa olundu** — PHP `display_errors=0` JSON cavabının korlanmasının qarşısını alır. `ReportGenerator::getAllGPOs()` da pagination aldı.
- **`ou.php` və `computer.php` pagination** — Eyni MaxPageSize fix uyğunluq üçün tətbiq olundu.
- **Docker network alətləri** — Image-ə `iproute2`, `net-tools`, `iputils-ping`, `wget` əlavə edildi. System Health dashboard artıq `sh: 1: ip: not found` əvəzinə düzgün MAC/IP göstərir.
- **UPN istifadəçi adı warning aradan qaldırıldı** — `user@domain.com` formasındakı `@` simvolu artıq "Potentially dangerous characters" log warning-i tetikləmir.

### ✨ Yeni Xüsusiyyətlər
- 🔄 **Avtomatik Yenilik Yoxlaması Sistemi** ([dockgate](https://github.com/Ali7Zeynalli/dockgate)-dan ilhamlanaraq)
  - GitHub-da yeni versiya olduqda footer-də yaşıl **"UPDATE"** badge göstərilir
  - Badge-ə klik → modal: cari→remote versiya + CHANGELOG preview
  - 24 saatlıq localStorage cache (aqressiv polling yoxdur)
  - Yeni endpoint: `GET /api/check-update.php`
  - Yeni fayl: `www/VERSION` — cari versiya üçün vahid mənbə
  - Release notes üçün CHANGELOG.md avtomatik parse edilir

### 🔧 Təkmilləşdirmələr
- Bütün dəyişdirilən fayllarda PHP syntax doğrulandı
- API endpoint-lərində error logging yaxşılaşdırıldı (`log_errors=1`, `display_errors=0`)

---

## [1.3.0] - 2026-01-16

### 🔧 Təkmilləşdirmələr
- ⚙️ **Installer: Ətraf Mühit Əsaslı Konfiqurasiya**
  - Database parametrləri indi `.env` faylından Docker mühit dəyişənləri vasitəsilə avtomatik yüklənir
  - Installer-də database input sahələri artıq yalnız oxunur (read-only)
  - İstifadəçilərə quraşdırmadan əvvəl `.env` faylını düzəltmələri barədə xəbərdarlıq əlavə edildi
  - Credential idarəetməsinin `.env`-də mərkəzləşdirilməsi ilə təhlükəsizlik yaxşılaşdırıldı
- 🔒 **Yeni Təhlükəsizlik Kilidi (Security Lock)**
  - Məcburi "Uninstall Wizard" sistemi fayl əsaslı kilid sistemi (`.installed`) ilə əvəz olundu
  - Faylları arxivləmədən təkrar quraşdırmanın qarşısını alır
  - Quraşdırmadan sonra installerə girilərsə "System Locked" ekranı göstərilir

---

## [1.3.0] - 2026-01-15

### ✨ Yeni Xüsusiyyətlər
- 🎫 **Tapşırıq İdarəetməsi (Helpdesk)** modulu əlavə edildi
  - Dəstək biletləri yaratma, redaktə etmə və silmə
  - Biletləri administratorlara təyin etmə
  - Status iş axını: Yeni → Təyin Edildi → Davam Edir → Həll Edildi → Bağlandı
  - İctimai şərhlər və daxili qeydlər
  - Kateqoriya idarəetməsi (Hardware, Software, Network və s.)
- 👤 **Təsirlənən İstifadəçi İnteqrasiyası** - Biletləri birbaşa AD istifadəçilərinə bağlama
  - Active Directory-dən istifadəçi axtarışı və seçimi
  - Ətraflı istifadəçi məlumatları (OU, Qruplar, Email)
  - Mövcud biletlərdə təsirlənən istifadəçini dəyişdirmə
- 📝 **Tam Audit Loglama** - Bütün bilet əməliyyatları Activity Logs-a yazılır
  - TICKET_CREATE - yeni bilet yaradıldıqda
  - TICKET_UPDATE - bilet detalları dəyişdikdə
  - TICKET_DELETE - bilet silindikdə
  - TICKET_ASSIGN - bilet təyin edildikdə
  - TICKET_STATUS - status dəyişdikdə
  - TICKET_COMMENT - şərh/qeyd əlavə edildikdə

### 🔧 Təkmilləşdirmələr
- İstifadəçi axtarışı display name və username ilə təkmilləşdirildi
- Bilet yaratma və redaktə modalları üçün UI yaxşılaşdırıldı
- Bütün SQL şemaları təmiz quraşdırma üçün tək `schema.sql` faylında birləşdirildi

### 📚 Sənədləşdirmə
- README.md-ə Task Management bölməsi əlavə edildi
- README_AZ.md-ə Tapşırıq İdarəetməsi bölməsi əlavə edildi
- Versiya izləmə üçün CHANGELOG.md yaradıldı
- Hər iki README-yə "Yeniliklər" bölməsi əlavə edildi
