<?php
/*
 * S-RCS Default Configuration Template
 *
 * Bu fayl installer üçün default konfiqurasiya şablonudur.
 * `config/config.php` yoxdursa (ilk dəfə quraşdırma), bu istifadə olunur.
 *
 * DİQQƏT: config/config.php istifadəçinin real datasını saxlayır
 * (secret_key, şifrələr, license) və GITHUB-A GETMƏMƏLİDİR.
 * Bu fayl (default-config.php) isə git-də saxlanır — hər dəfə təmiz şablondur.
 */
return [
    'installation' => [
        'installed'    => false,
        'date'         => null,
        'version'      => null,
        'last_update'  => null,
        'installer'    => null,
        'install_hash' => null,
    ],
    'system_identifiers' => [
        'machine_id'     => null,
        'disk_serial'    => null,
        'motherboard_id' => null,
        'server_ip'      => null,
        'mac_address'    => null,
    ],
    'license' => [
        'key'          => null,
        'generated_at' => null,
        'status'       => null,
    ],
    'password_settings' => [
        'default_temp_password' => 'Welcome2024!1111',
        'min_length'            => 8,
        'complexity'            => true,
    ],
    'ad_settings' => [
        'domain_controllers' => [],
        'domain_name'        => '',
        'base_dn'            => '',
        'account_suffix'     => '',
        'use_ssl'            => true,
        'port'               => 636,
        'admin_group'        => 'Administrators',
        'allowed_groups'     => ['Administrators'],
        'timeout'            => 10,
        'ssl_options'        => [
            'verify_cert'       => false,
            'allow_self_signed' => true,
            'ca_cert'           => null,
            'peer_name'         => null,
        ],
    ],
    'db_settings' => [
        'host'     => 'mysql',
        'database' => 'ldap_auth',
        'username' => 'srcs_admin',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
    'server_settings' => [
        'environment' => 'production',
        'debug'       => false,
        'timezone'    => 'UTC',
    ],
    'language_settings' => [
        'default_language' => 'en',
    ],
    'pagination_settings' => [
        'default_page_size' => 15,
        'page_size_options' => [5, 10, 15, 25, 50, 100, -1],
    ],
    'security_settings' => [
        'secret_key' => null,
    ],
];
