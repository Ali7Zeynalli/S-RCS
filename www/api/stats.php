<?php
/*
 * Copyright (C) [2025] [Ali Zeynalli] - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by [Ali Zeynalli] <[https://linkedin.com/in/ali7zeynalli]> [2025]
 */

session_start();

// Prevent warnings from corrupting JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/classes/Task.php');

// gzip compression - 3MB → 300KB
if (!ob_start('ob_gzhandler')) ob_start();

header('Content-Type: application/json');

if (!isset($_SESSION['ad_username'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

try {
    $ldap = getLDAPConnection();

    // Calculate user stats with SESSION CACHE (10 min TTL)
    $statsCacheKey = 'user_stats';
    $statsCacheTime = 'user_stats_time';
    $statsCacheTTL = 600;  // 10 minutes

    $needsRefresh = !isset($_SESSION[$statsCacheKey])
                  || (time() - ($_SESSION[$statsCacheTime] ?? 0)) > $statsCacheTTL;

    if ($needsRefresh) {
        // Cache maxPwdAge once for the whole loop (was: called 6455 times!)
        $maxPwdAge = getDomainMaxPwdAge($ldap);

        $users = getAllUsers($ldap);
        $lockedUsers = getLockedUsers($ldap);
        $userCount = (isset($users['count']) ? $users['count'] : count($users)) - 1;
        if ($userCount < 0) $userCount = 0;

        $stats = [
            'total' => $userCount,
            'active' => 0,
            'inactive' => 0,
            'expired_password' => 0,
            'locked' => count($lockedUsers),
            'never_expires' => 0,
            'must_change' => 0
        ];

        // FAST loop — NO LDAP call per user, uses cached $maxPwdAge
        $now = time();
        for ($i = 0; $i < $userCount; $i++) {
            $user = $users[$i];
            $uac = isset($user['useraccountcontrol'][0]) ? (int)$user['useraccountcontrol'][0] : 0;
            $pwdLastSet = isset($user['pwdlastset'][0]) ? $user['pwdlastset'][0] : '0';

            // Enabled/disabled
            if (($uac & 2) !== 2) $stats['active']++;
            else $stats['inactive']++;

            // Password status (computed locally, no LDAP call)
            if ($uac & 65536) {
                $stats['never_expires']++;
            } elseif ($pwdLastSet === '0' || $pwdLastSet === 0) {
                $stats['must_change']++;
            } elseif ($maxPwdAge > 0) {
                $pwdSetTs = ($pwdLastSet / 10000000) - 11644473600;
                if ($now > ($pwdSetTs + $maxPwdAge)) {
                    $stats['expired_password']++;
                }
            }
        }

        $_SESSION[$statsCacheKey] = [
            'total' => $stats['total'],
            'active' => $stats['active'],
            'inactive' => $stats['inactive'],
            'locked' => $stats['locked'],
            'password_status' => [
                'expired' => $stats['expired_password'],
                'never_expires' => $stats['never_expires'],
                'must_change' => $stats['must_change']
            ]
        ];
        $_SESSION[$statsCacheTime] = time();
    }

    $userStats = $_SESSION[$statsCacheKey];

    // Get groups stats
    $groups = getAllGroups($ldap);
    $security_groups = 0;
    $distribution_groups = 0;

    foreach ($groups as $group) {
        if ($group['type'] === 'Security') {
            $security_groups++;
        } else {
            $distribution_groups++;
        }
    }

    $groupStats = [
        'total' => count($groups),
        'security' => $security_groups,
        'distribution' => $distribution_groups
    ];

    // Get computers stats
    $computers = getAllComputers($ldap);
    $server_computers = 0;
    $workstation_computers = 0;

    foreach ($computers as $computer) {
        // Check if computer is a server by checking operatingSystem attribute
        if (isset($computer['operatingsystem'][0])) {
            $os = strtolower($computer['operatingsystem'][0]);
            if (strpos($os, 'server') !== false) {
                $server_computers++;
            } else {
                $workstation_computers++;
            }
        } else {
            // If no OS info, assume it's a workstation
            $workstation_computers++;
        }
    }

    $computerStats = [
        'total' => count($computers),
        'servers' => $server_computers,
        'workstations' => $workstation_computers
    ];

    // Get Task Stats
    try {
        $taskObj = new Task();
        $taskStats = $taskObj->getStats();
    } catch (Exception $e) {
        $taskStats = ['total' => 0, 'open' => 0, 'unassigned' => 0];
    }


    echo json_encode([
        'success' => true,
        'stats' => [
            'users' => $userStats,
            'groups' => $groupStats,
            'computers' => $computerStats,
            'tasks' => $taskStats
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
