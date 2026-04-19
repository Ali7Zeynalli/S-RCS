<?php
require_once dirname(__DIR__) . '/functions.php';

 /*
  * Copyright (C) [2025] [Ali Zeynalli] - All Rights Reserved
  * Unauthorized copying of this file, via any medium is strictly prohibited
  * Proprietary and confidential
  * Written by [Ali Zeynalli] <[https://linkedin.com/in/ali7zeynalli]> [2025]
  */

function getAllOUs($ldap_conn) {
    $config = require(getConfigPath());
    $base_dn = $config['ad_settings']['base_dn'];

    // AD MaxPageSize=1000 bypass
    ldap_set_option($ldap_conn, LDAP_OPT_SIZELIMIT, 5000);

    $filter = "(|(objectClass=organizationalUnit)(objectClass=container))";
    $attributes = ["ou", "cn", "distinguishedname", "description", "member", "whenCreated", "objectClass", "showInAdvancedViewOnly"];

    // Pagination ilə bütün OU-ları çək
    $entries = ['count' => 0];
    $cookie = '';
    do {
        $result = ldap_search(
            $ldap_conn, $base_dn, $filter, $attributes,
            0, 0, 0, LDAP_DEREF_NEVER,
            [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => 1000, 'cookie' => $cookie]]]
        );
        if (!$result) break;

        ldap_parse_result($ldap_conn, $result, $errcode, $matcheddn, $errmsg, $referrals, $controls);
        $page = ldap_get_entries($ldap_conn, $result);

        if (is_array($page) && isset($page['count'])) {
            for ($i = 0; $i < $page['count']; $i++) {
                $entries[$entries['count']] = $page[$i];
                $entries['count']++;
            }
        }

        $cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
    } while (!empty($cookie));

    // System and advanced containers to hide
    $hiddenContainers = [
        'CN=System',
        'CN=Program Data',
        'CN=LostAndFound',
        'CN=ForeignSecurityPrincipals',
        'CN=Infrastructure',
        'CN=Microsoft',
        'CN=RpcServices',
        'CN=WinsockServices',
        'CN=FileLinks',
        'CN=Keys',
        'CN=MicrosoftDNS',
        'CN=RID Manager',
        'CN=Policies',
        'CN=NTDS Quotas',
        'CN=TPM Devices',
        'CN=PSPs',
        'CN=Deleted Objects',
        'CN=WinRas',
        'CN=ComputerObjects',
        'CN=AdminSDHolder',
        'CN=Managed Service Accounts'
    ];
    
    // Special containers to always show
    $visibleContainers = [
      
    ];
    
    $ous = [];
    for ($i = 0; $i < $entries['count']; $i++) {
        $entry = $entries[$i];
        $dn = $entry['distinguishedname'][0];
        
        // Skip if it's a hidden system container
        $isHidden = false;
        foreach ($hiddenContainers as $hiddenContainer) {
            if (stripos($dn, $hiddenContainer) === 0) {
                $isHidden = true;
                break;
            }
        }
        
        // Always show if it's in visible containers
        foreach ($visibleContainers as $visibleContainer) {
            if (stripos($dn, $visibleContainer) === 0) {
                $isHidden = false;
                break;
            }
        }
        
        // Skip if hidden
        if ($isHidden) {
            continue;
        }
        
        // Skip containers that start with $ or have showInAdvancedViewOnly=TRUE
        $name = isset($entry['ou'][0]) ? $entry['ou'][0] : ($entry['cn'][0] ?? '');
        if (strpos($name, '$') === 0 || 
            (isset($entry['showinadvancedviewonly']) && $entry['showinadvancedviewonly'][0] == 'TRUE')) {
            continue;
        }
        
        // Normal container processing - member count (with warning suppression)
        // Some OUs may not be readable; @ suppresses warnings that would break JSON
        $memberFilter = "(|(objectClass=user)(objectClass=group)(objectClass=computer))";
        $memberSearch = @ldap_search($ldap_conn, $dn, $memberFilter);
        $memberCount = $memberSearch ? @ldap_count_entries($ldap_conn, $memberSearch) : 0;
        
        // Format OU path for display
        $path = formatOUPath($dn);
        
        // Get parent OU
        $parent_ou = '';
        $dn_parts = explode(',', $dn);
        array_shift($dn_parts);
        foreach ($dn_parts as $part) {
            if (strpos($part, 'OU=') === 0) {
                $parent_ou = substr($part, 3); // "OU=" prefiksini silir
                break;
            } else if (strpos($part, 'CN=') === 0 && strpos($part, 'CN=Users') === 0) {
                $parent_ou = __('ou_type_users');
                break;
            }
        }
        
        // objectclass may be missing on some entries - safe access
        $objectClasses = isset($entry['objectclass']) && is_array($entry['objectclass']) ? $entry['objectclass'] : [];
        $isOU = in_array('organizationalUnit', $objectClasses);
        
        $ous[] = [
            'name' => $name,
            'dn' => $dn,
            'path' => $path,
            'description' => $entry['description'][0] ?? '',
            'memberCount' => $memberCount,
            'parentOU' => $parent_ou,
            'created' => formatLDAPDate($entry['whencreated'][0] ?? ''),
            'type' => $isOU ? __('ou_type_organizational_unit') : __('ou_type_container'),
            'isContainer' => !$isOU,
            'isSystem' => false,
            'isVisible' => true
        ];
    }
    
    return $ous;
}

function getContainerIcon($type, $isContainer) {
    if ($isContainer) {
        return 'fa-folder text-info';
    }
    switch($type) {
        case __('ou_type_user_container'):
            return 'fa-users text-primary';
        case __('ou_type_security_group_container'):
            return 'fa-shield-alt text-success';
        default:
            return 'fa-folder text-warning';
    }
}

function determineOUType($ou_dn, $ldap_conn) {
    // Check for specific group memberships or attributes to determine type
    $filter = "(&(objectClass=group)(memberOf=$ou_dn))";
    $result = ldap_search($ldap_conn, $ou_dn, $filter);
    $count = ldap_count_entries($ldap_conn, $result);
    
    if ($count > 0) {
        return 'Security Group Container';
    }
    
    // Check for user accounts
    $filter = "(&(objectClass=user)(objectCategory=person))";
    $result = ldap_search($ldap_conn, $ou_dn, $filter);
    $count = ldap_count_entries($ldap_conn, $result);
    
    if ($count > 0) {
        return 'User Container';
    }
    
    return 'Generic Container';
}

function getOUHierarchy($ous) {
    $hierarchy = [];
    $lookup = [];
    
    // First pass: create lookup table
    foreach ($ous as $ou) {
        $lookup[$ou['name']] = $ou;
        if (empty($ou['parentOU'])) {
            $hierarchy[] = [
                'ou' => $ou,
                'children' => []
            ];
        }
    }
    
    // Second pass: build hierarchy
    foreach ($ous as $ou) {
        if (!empty($ou['parentOU']) && isset($lookup[$ou['parentOU']])) {
            $parent = &$lookup[$ou['parentOU']];
            if (!isset($parent['children'])) {
                $parent['children'] = [];
            }
            $parent['children'][] = [
                'ou' => $ou,
                'children' => []
            ];
        }
    }
    
    return $hierarchy;
}
