<?php
 /*
  * Copyright (C) [2025] [Ali Zeynalli] - All Rights Reserved
  * Unauthorized copying of this file, via any medium is strictly prohibited
  * Proprietary and confidential
  * Written by [Ali Zeynalli] <[https://linkedin.com/in/ali7zeynalli]> [2025]
  */
class ReportGenerator {
    private $ldap;
    
    public function __construct($ldap_conn) {
        $this->ldap = $ldap_conn;
    }

    public function generateReport($sections) {
        // Map lowercase section name -> PascalCase key used in data array
        $sectionMap = [
            'users'     => 'Users',
            'groups'    => 'Groups',
            'computers' => 'Computers',
            'ous'       => 'OUs',
            'gpos'      => 'GPOs'
        ];

        $data = [];
        foreach ($sections as $section) {
            $key = $sectionMap[$section] ?? ucfirst($section);

            try {
                switch ($section) {
                    case 'users':
                        $users = getAllUsers($this->ldap);
                        $data[$key] = $this->formatUserData($users);
                        break;
                    case 'groups':
                        $groups = getAllGroups($this->ldap);
                        $data[$key] = $this->formatGroupData($groups);
                        break;
                    case 'computers':
                        $computersResult = getAllComputers($this->ldap);
                        // getAllComputers returns ['computers' => [...], 'stats' => [...]]
                        $computers = $computersResult['computers'] ?? $computersResult;
                        $data[$key] = $this->formatComputerData($computers);
                        break;
                    case 'ous':
                        $ous = getAllOUs($this->ldap);
                        $data[$key] = $this->formatOUData($ous);
                        break;
                    case 'gpos':
                        $gpos = $this->getAllGPOs();
                        $data[$key] = $this->formatGPOData($gpos);
                        break;
                    default:
                        // Unknown section
                        $data[$key] = [];
                        break;
                }
            } catch (Exception $e) {
                error_log("Report section '$section' failed: " . $e->getMessage());
                $data[$key] = []; // don't break the whole report
            }

            // Guarantee key exists and is an array
            if (!isset($data[$key]) || !is_array($data[$key])) {
                $data[$key] = [];
            }
        }
        return $data;
    }

    private function formatUserData($users) {
        if (empty($users) || !is_array($users)) {
            return [];
        }

        $formatted = [];
        $count = isset($users['count']) ? (int)$users['count'] : 0;

        // Iterate only real entries (skip 'count' key and other metadata)
        for ($i = 0; $i < $count; $i++) {
            if (!isset($users[$i]) || !is_array($users[$i])) continue;
            $user = $users[$i];
            if (!isset($user['samaccountname'][0])) continue;

            $uac = isset($user['useraccountcontrol'][0]) ? (int)$user['useraccountcontrol'][0] : 0;
            $lastLogonRaw = $user['lastlogon'][0] ?? 0;
            $lastLogon = 'Never';
            if ($lastLogonRaw && $lastLogonRaw > 0) {
                $ts = ((int)$lastLogonRaw / 10000000) - 11644473600;
                if ($ts > 0) $lastLogon = date('Y-m-d H:i:s', (int)$ts);
            }

            $formatted[] = [
                'Username'    => $user['samaccountname'][0],
                'Full Name'   => $user['displayname'][0] ?? '',
                'Email'       => $user['mail'][0] ?? '',
                'Department'  => $user['department'][0] ?? '',
                'Title'       => $user['title'][0] ?? '',
                'Status'      => ($uac & 2) ? 'Disabled' : 'Enabled',
                'Last Logon'  => $lastLogon
            ];
        }
        return $formatted;
    }

    private function formatGroupData($groups) {
        if (empty($groups) || !is_array($groups)) {
            return [];
        }
        
        return array_map(function($group) {
            return [
                'Name' => $group['name'] ?? '',
                'Members' => $group['memberCount'] ?? 0,
                'Type' => $group['type'] ?? 'Unknown',
                'Description' => $group['description'] ?? ''
            ];
        }, $groups);
    }

    private function formatComputerData($computers) {
        if (empty($computers) || !is_array($computers)) {
            return [];
        }

        // Handle both wrapped {computers: [...], stats: [...]} and raw array
        if (isset($computers['computers']) && is_array($computers['computers'])) {
            $computers = $computers['computers'];
        }

        $formatted = [];
        foreach ($computers as $computer) {
            if (!is_array($computer) || !isset($computer['name'])) continue;

            $formatted[] = [
                'Name'        => $computer['name'] ?? '',
                'DNS Name'    => $computer['deviceName'] ?? '',
                'OS'          => $computer['os'] ?? 'Unknown',
                'OS Version'  => $computer['osVersion'] ?? '',
                'Type'        => $computer['type'] ?? '',
                'OU'          => $computer['ou'] ?? '',
                'Last Logon'  => $computer['lastLogon'] ?? 'Never',
                'Status'      => isset($computer['enabled']) && $computer['enabled'] ? 'Enabled' : 'Disabled',
                'Description' => $computer['description'] ?? ''
            ];
        }
        return $formatted;
    }

    private function formatOUData($ous) {
        // Check if OUs array is empty
        if (empty($ous) || !is_array($ous)) {
            return [];
        }
        
        return array_map(function($ou) {
            return [
                'Name' => $ou['name'] ?? '',
                'Path' => $ou['path'] ?? '',
                'Description' => $ou['description'] ?? '',
                'Created' => $ou['created'] ?? '',
                'Members' => $ou['memberCount'] ?? 0,
                'Type' => $ou['type'] ?? 'Organizational Unit'
            ];
        }, $ous);
    }

    private function getAllGPOs() {
        $config = require(__DIR__ . '/../config/config.php');
        $base_dn = $config['ad_settings']['base_dn'];
        $gpo_container = "CN=Policies,CN=System," . $base_dn;

        // Böyük domen-də 1000+ GPO üçün pagination
        ldap_set_option($this->ldap, LDAP_OPT_SIZELIMIT, 5000);

        $filter = "(objectClass=groupPolicyContainer)";
        $attributes = ["displayName", "flags", "gPCFileSysPath", "whenCreated", "whenChanged", "description"];

        $all = ['count' => 0];
        $cookie = '';

        do {
            $result = ldap_search(
                $this->ldap, $gpo_container, $filter, $attributes,
                0, 0, 0, LDAP_DEREF_NEVER,
                [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => 1000, 'cookie' => $cookie]]]
            );

            if (!$result) break;

            ldap_parse_result($this->ldap, $result, $errcode, $matcheddn, $errmsg, $referrals, $controls);
            $entries = ldap_get_entries($this->ldap, $result);

            if (is_array($entries) && isset($entries['count'])) {
                for ($i = 0; $i < $entries['count']; $i++) {
                    $all[$all['count']] = $entries[$i];
                    $all['count']++;
                }
            }

            $cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
        } while (!empty($cookie));

        return $all;
    }

    private function formatGPOData($gpos) {
        if (empty($gpos) || !is_array($gpos) || !isset($gpos['count'])) {
            return [];
        }
        
        $formatted = [];
        for ($i = 0; $i < $gpos['count']; $i++) {
            $gpo = $gpos[$i];
            $formatted[] = [
                'Name' => $gpo['displayname'][0] ?? 'Unknown',
                'Type' => $this->determineGPOType($gpo['flags'][0] ?? 0),
                'Path' => $gpo['gpcfilesyspath'][0] ?? '',
                'Created' => isset($gpo['whencreated'][0]) ? formatLDAPDate($gpo['whencreated'][0]) : '',
                'Modified' => isset($gpo['whenchanged'][0]) ? formatLDAPDate($gpo['whenchanged'][0]) : '',
                'Description' => $gpo['description'][0] ?? ''
            ];
        }
        return $formatted;
    }

    private function determineGPOType($flags) {
        $flags = intval($flags);
        if ($flags & 1) return 'User';
        if ($flags & 2) return 'Computer';
        return 'Both';
    }

    public function saveToCSV($data, $filename) {
        if (!is_dir(__DIR__ . '/../reports')) {
            mkdir(__DIR__ . '/../reports', 0777, true);
        }

        $filepath = __DIR__ . '/../reports/' . $filename;
        
        // Add BOM for Excel UTF-8 support
        $output = "\xEF\xBB\xBF";
        $fp = fopen($filepath, 'w');
        fwrite($fp, $output);

        foreach ($data as $section => $items) {
            fputcsv($fp, [$section]); // Section header
            if (!empty($items)) {
                // Make sure we have at least one item before accessing array keys
                $headers = !empty($items[0]) && is_array($items[0]) ? array_keys($items[0]) : ['No data available'];
                fputcsv($fp, $headers); // Column headers
                
                foreach ($items as $item) {
                    fputcsv($fp, array_values($item));
                }
            } else {
                // Handle empty data
                fputcsv($fp, ['No data available']);
            }
            fputcsv($fp, []); // Empty line between sections
        }

        fclose($fp);
        chmod($filepath, 0644);
        
        return [
            'path' => '/reports/' . $filename,
            'fullpath' => $filepath,
            'filename' => $filename
        ];
    }

    public function saveToExcel($data, $filename) {
        if (!is_dir(__DIR__ . '/../reports')) {
            mkdir(__DIR__ . '/../reports', 0777, true);
        }

        $filepath = __DIR__ . '/../reports/' . $filename;
        
        // Create Excel-compatible HTML file
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets>';
        
        foreach ($data as $sheetName => $items) {
            $html .= '<x:ExcelWorksheet><x:Name>' . $sheetName . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
        }
        
        $html .= '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
        </head><body>';
        
        foreach ($data as $section => $items) {
            $html .= '<table border="1">';
            $html .= '<tr><th colspan="5">' . $section . '</th></tr>';
            
            // Headers
            if (!empty($items)) {
                $html .= '<tr>';
                // Make sure we have at least one item before accessing array keys
                $headers = !empty($items[0]) && is_array($items[0]) ? array_keys($items[0]) : ['No data available'];
                foreach ($headers as $header) {
                    $html .= '<th style="background-color: #f0f0f0; font-weight: bold;">' . htmlspecialchars($header) . '</th>';
                }
                $html .= '</tr>';
                
                // Data
                foreach ($items as $item) {
                    $html .= '<tr>';
                    foreach ($item as $value) {
                        $html .= '<td>' . htmlspecialchars($value) . '</td>';
                    }
                    $html .= '</tr>';
                }
            } else {
                // Handle empty data
                $html .= '<tr><td>No data available</td></tr>';
            }
            $html .= '</table><br>';
        }
        
        $html .= '</body></html>';
        
        // Save file
        file_put_contents($filepath, $html);
        chmod($filepath, 0644);
        
        return [
            'path' => '/reports/' . $filename,
            'fullpath' => $filepath,
            'filename' => $filename
        ];
    }
}
