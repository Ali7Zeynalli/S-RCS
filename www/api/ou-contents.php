<?php
/*
 * Copyright (C) [2025] [Ali Zeynalli] - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by [Ali Zeynalli] <[https://linkedin.com/in/ali7zeynalli]> [2025]
 */
session_start();

// CRITICAL: prevent PHP warnings from corrupting JSON
// (AD returns "Sizelimit exceeded" warning for OUs with 1000+ objects)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../includes/functions.php');

// Buffer any accidental output
ob_start();

header('Content-Type: application/json');

if (!isset($_SESSION['ad_username'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => __('ou_unauthorized')]);
    exit;
}

try {
    if (!isset($_GET['dn'])) {
        throw new Exception(__('ou_dn_required'));
    }

    $ldap_conn = getLDAPConnection();
    $dn = $_GET['dn'];

    // Raise AD sizelimit (default 1000) for large OUs
    ldap_set_option($ldap_conn, LDAP_OPT_SIZELIMIT, 10000);

    // First, get the OU details
    $filter = "(objectClass=*)";
    $result = @ldap_read($ldap_conn, $dn, $filter, [
        "ou", "cn", "objectClass", "distinguishedName", "whenCreated",
        "description", "showInAdvancedViewOnly"
    ]);

    if ($result === false) {
        throw new Exception(__('ou_read_failed') . ': ' . ldap_error($ldap_conn));
    }

    $entries = @ldap_get_entries($ldap_conn, $result);

    if (!is_array($entries) || $entries['count'] === 0) {
        throw new Exception(__('ou_not_found'));
    }

    $entry = $entries[0];
    $objectClasses = isset($entry['objectclass']) && is_array($entry['objectclass']) ? $entry['objectclass'] : [];
    $isOU = in_array('organizationalUnit', $objectClasses);

    $name = isset($entry['ou'][0]) ? $entry['ou'][0] : ($entry['cn'][0] ?? '');
    $path = formatOUPath($dn);

    // Get parent OU
    $parent_ou = '';
    $dn_parts = explode(',', $dn);
    array_shift($dn_parts);
    foreach ($dn_parts as $part) {
        if (strpos($part, 'OU=') === 0) {
            $parent_ou = substr($part, 3);
            break;
        } else if (strpos($part, 'CN=') === 0 && strpos($part, 'CN=Users') === 0) {
            $parent_ou = 'Users';
            break;
        }
    }

    $memberFilter = "(|(objectClass=user)(objectClass=group)(objectClass=computer))";

    // Search for all objects in this OU with PAGINATION (bypass AD 1000 limit)
    // NOTE: member count is derived from $contents after loop - no separate query needed
    $contents = [];
    $typeStats = ['user' => 0, 'group' => 0, 'computer' => 0];
    $cookie = '';
    do {
        $listResult = @ldap_list(
            $ldap_conn, $dn, $memberFilter,
            ["name", "cn", "sAMAccountName", "objectClass", "distinguishedName", "whenCreated", "member", "memberOf", "description"],
            0, 0, 0, LDAP_DEREF_NEVER,
            [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => 1000, 'cookie' => $cookie]]]
        );

        if (!$listResult) break;

        @ldap_parse_result($ldap_conn, $listResult, $errcode, $matcheddn, $errmsg, $referrals, $controls);
        $pageEntries = @ldap_get_entries($ldap_conn, $listResult);

        if (!is_array($pageEntries) || !isset($pageEntries['count'])) break;

        for ($i = 0; $i < $pageEntries['count']; $i++) {
            $e = $pageEntries[$i];
            $objClasses = isset($e['objectclass']) && is_array($e['objectclass']) ? $e['objectclass'] : [];
            $type = getObjectType($objClasses);
            if (!$type) continue;

            $itemName = $e['cn'][0] ?? $e['name'][0] ?? $e['samaccountname'][0] ?? '';

            $contents[] = [
                'name' => $itemName,
                'type' => strtolower($type),
                'dn' => $e['distinguishedname'][0] ?? '',
                'created' => formatLDAPDate($e['whencreated'][0] ?? ''),
                'memberCount' => isset($e['member']) ? $e['member']['count'] : null,
                'description' => $e['description'][0] ?? ''
            ];

            if (isset($typeStats[strtolower($type)])) {
                $typeStats[strtolower($type)]++;
            }
        }

        $cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
    } while (!empty($cookie));

    // Member count derived from paginated results (accurate count, not limited by AD 1000)
    $memberCount = count($contents);

    $ou = [
        'name' => $name,
        'dn' => $dn,
        'path' => $path,
        'description' => $entry['description'][0] ?? '',
        'memberCount' => $memberCount,
        'typeCounts' => $typeStats,
        'parentOU' => $parent_ou,
        'created' => formatLDAPDate($entry['whencreated'][0] ?? ''),
        'type' => $isOU ? 'Organizational Unit' : 'Container',
        'isContainer' => !$isOU
    ];

    // Sort by type then name
    usort($contents, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            $typeOrder = ['user' => 1, 'group' => 2, 'computer' => 3];
            return $typeOrder[$a['type']] <=> $typeOrder[$b['type']];
        }
        return $a['name'] <=> $b['name'];
    });

    // Clean output buffer before emitting JSON
    ob_end_clean();

    echo json_encode([
        'ou' => $ou,
        'contents' => $contents
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("OU-contents API error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($ldap_conn) && $ldap_conn) {
        @ldap_unbind($ldap_conn);
    }
}

function getObjectType($objectClasses) {
    if (in_array('user', $objectClasses)) return 'user';
    if (in_array('group', $objectClasses)) return 'group';
    if (in_array('computer', $objectClasses)) return 'computer';
    return null;
}
