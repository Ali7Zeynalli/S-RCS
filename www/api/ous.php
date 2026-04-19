<?php
 /*
  * Copyright (C) [2025] [Ali Zeynalli] - All Rights Reserved
  * Unauthorized copying of this file, via any medium is strictly prohibited
  * Proprietary and confidential
  * Written by [Ali Zeynalli] <[https://linkedin.com/in/ali7zeynalli]> [2025]
  */
session_start();

// CRITICAL: Prevent PHP warnings from corrupting JSON response
// (PHP renders warnings as HTML: <br /><b>Warning</b>... which breaks JSON.parse)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../includes/functions.php');

// Start output buffering - any accidental output before JSON will be cleared
ob_start();

header('Content-Type: application/json');

if (!isset($_SESSION['ad_username'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $ldap_conn = getLDAPConnection();
    $ous = getAllOUs($ldap_conn);
    $hierarchy = getOUHierarchy($ous);

    // Clean any buffered output (warnings, notices) before sending JSON
    ob_end_clean();

    echo json_encode([
        'ous' => $ous,
        'hierarchy' => $hierarchy,
        'stats' => [
            'total' => count($ous),
            'types' => array_count_values(array_column($ous, 'type'))
        ]
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("OUs API error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
