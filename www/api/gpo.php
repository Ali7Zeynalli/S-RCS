<?php
/*
 * Copyright (C) [2025] [Ali Zeynalli] - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by [Ali Zeynalli] <[https://linkedin.com/in/ali7zeynalli]> [2025]
 */
session_start();
require_once(__DIR__ . '/../includes/functions.php');

header('Content-Type: application/json');
ob_clean();

if (!isset($_SESSION['ad_username'])) {
    http_response_code(401);
    die(json_encode(['error' => __('gpo_unauthorized')]));
}

try {
    $ldap_conn = getLDAPConnection();
    $config = require(__DIR__ . '/../config/config.php');
    $base_dn = $config['ad_settings']['base_dn'];
    $gpo_container = "CN=Policies,CN=System," . $base_dn;

    // AD default page limit 1000 — pagination ilə böyük domen-də partial result alınmasın
    ldap_set_option($ldap_conn, LDAP_OPT_SIZELIMIT, 5000);

    $gpo_attributes = [
        "displayName", "distinguishedName", "flags", "gPCFileSysPath",
        "versionNumber", "whenCreated", "whenChanged", "description",
        "gPCMachineExtensionNames", "gPCUserExtensionNames", "objectClass"
    ];

    // --- Addım 1: Bütün GPO-ları pagination ilə çək ---
    $gpo_entries = [];
    $cookie = '';
    do {
        $result = ldap_search(
            $ldap_conn, $gpo_container, "(objectClass=groupPolicyContainer)",
            $gpo_attributes, 0, 0, 0, LDAP_DEREF_NEVER,
            [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => 1000, 'cookie' => $cookie]]]
        );

        if (!$result) {
            throw new Exception(__('gpo_search_failed') . ": " . ldap_error($ldap_conn));
        }

        ldap_parse_result($ldap_conn, $result, $errcode, $matcheddn, $errmsg, $referrals, $controls);
        $entries = ldap_get_entries($ldap_conn, $result);

        if (is_array($entries) && isset($entries['count'])) {
            for ($i = 0; $i < $entries['count']; $i++) {
                $gpo_entries[] = $entries[$i];
            }
        }

        $cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
    } while (!empty($cookie));

    // --- Addım 2: Bütün OU-ları bir dəfə çək (N+1 problem həlli) ---
    $ou_links_map = []; // dn_fragment => [ou_paths...]
    $ou_cookie = '';
    do {
        $ou_result = ldap_search(
            $ldap_conn, $base_dn, "(&(objectClass=organizationalUnit)(gPLink=*))",
            ["distinguishedName", "gplink"], 0, 0, 0, LDAP_DEREF_NEVER,
            [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => 1000, 'cookie' => $ou_cookie]]]
        );

        if (!$ou_result) break;

        ldap_parse_result($ldap_conn, $ou_result, $oec, $omd, $oem, $oref, $ou_controls);
        $ou_entries = ldap_get_entries($ldap_conn, $ou_result);

        if (is_array($ou_entries) && isset($ou_entries['count'])) {
            for ($k = 0; $k < $ou_entries['count']; $k++) {
                $ou_dn = $ou_entries[$k]['distinguishedname'][0] ?? '';
                $ou_gplink = $ou_entries[$k]['gplink'][0] ?? '';
                if (!$ou_dn || !$ou_gplink) continue;

                // gPLink format: [LDAP://cn={GUID},cn=policies,...;0][LDAP://...;0]
                if (preg_match_all('/LDAP:\/\/([^;\]]+)/i', $ou_gplink, $matches)) {
                    foreach ($matches[1] as $linked_gpo_dn) {
                        $key = strtolower(trim($linked_gpo_dn));
                        $ou_links_map[$key][] = $ou_dn;
                    }
                }
            }
        }

        $ou_cookie = $ou_controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
    } while (!empty($ou_cookie));

    // --- Addım 3: GPO-ları formatla + OU link-lərini PHP-də match et ---
    $gpos = [];
    $linkedOUsCount = 0;
    $computerPoliciesCount = 0;
    $userPoliciesCount = 0;

    foreach ($gpo_entries as $entry) {
        $dn = $entry['distinguishedname'][0] ?? '';
        if (!$dn) continue;

        $flags = intval($entry['flags'][0] ?? 0);
        $linked_ous_raw = $ou_links_map[strtolower($dn)] ?? [];
        $linked_ous = array_map('formatOUPath', $linked_ous_raw);
        $linkedOUsCount += count($linked_ous);

        $type = determineGPOType($flags);
        if ($type === 'Computer') {
            $computerPoliciesCount++;
        } elseif ($type === 'User') {
            $userPoliciesCount++;
        }

        $status = [
            'enabled' => !($flags & 1),
            'enforced' => ($flags & 2) === 2,
            'block_inheritance' => ($flags & 4) === 4
        ];

        $versionNumber = intval($entry['versionnumber'][0] ?? 0);
        $userVersion = $versionNumber >> 16;
        $computerVersion = $versionNumber & 0xFFFF;

        $gpos[] = [
            'name' => $entry['displayname'][0] ?? __('gpo_unknown'),
            'type' => $type,
            'path' => $entry['gpcfilesyspath'][0] ?? '',
            'version' => [
                'user' => $userVersion,
                'computer' => $computerVersion,
                'combined' => $versionNumber
            ],
            'created' => isset($entry['whencreated']) ? formatLDAPDate($entry['whencreated'][0]) : 'N/A',
            'modified' => isset($entry['whenchanged']) ? formatLDAPDate($entry['whenchanged'][0]) : 'N/A',
            'description' => $entry['description'][0] ?? '',
            'dn' => $dn,
            'linkedOUs' => $linked_ous,
            'status' => $status
        ];
    }

    usort($gpos, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    die(json_encode([
        'gpos' => $gpos,
        'stats' => [
            'total' => count($gpos),
            'linked_ous' => $linkedOUsCount
        ]
    ]));

} catch (Exception $e) {
    error_log("GPO API Error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => $e->getMessage()]));
}

function determineGPOType($flags) {
    $flags = intval($flags);
    if ($flags & 1) return 'User';
    if ($flags & 2) return 'Computer';
    return 'Both';
}
