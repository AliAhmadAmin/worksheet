<?php
/**
 * Reports & Matrix Analytics API
 * Replicates and enhances the exact Google Sheet cross-tabulation reports
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json');
}
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'matrix';

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$currentUserId = $_SESSION['user_id'] ?? 0;
$stmtUserCheck = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$stmtUserCheck->execute([$currentUserId]);
$currentUserObj = $stmtUserCheck->fetch() ?: [];
$currentUserRole = $currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee');
$isSuperAdmin = ($currentUserRole === 'super_admin' || $currentUserRole === 'admin');
$isHod = ($currentUserRole === 'hod');
$userDeptId = (int)($currentUserObj['department_id'] ?? 0);

$managedDeptIds = $userDeptId > 0 ? [$userDeptId] : [];
if ($isHod) {
    $stmtM = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
    $stmtM->execute([$currentUserId]);
    $extraDepts = $stmtM->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($extraDepts)) {
        $managedDeptIds = array_unique(array_merge($managedDeptIds, array_map('intval', $extraDepts)));
    }
}

// Helper to normalize content types for matching columns
function normalizeMatrixContentType($cType) {
    $t = strtolower(trim($cType ?? ''));
    if ($t === 'reel' || $t === 'reels' || str_contains($t, 'reel')) return 'Reels';
    if ($t === 'yt videos' || $t === 'yt video' || $t === 'youtube' || str_contains($t, 'yt') || str_contains($t, 'youtube')) return 'YT Videos';
    if ($t === 'post card' || $t === 'post cards' || $t === 'postcard' || str_contains($t, 'card') || str_contains($t, 'post')) return 'Post Cards';
    if ($t === 'fb videos' || $t === 'fb video' || $t === 'facebook' || str_contains($t, 'fb') || str_contains($t, 'facebook')) return 'FB Videos';
    if ($t === 'podcast' || $t === 'podcasts' || str_contains($t, 'podcast')) return 'Podcast';
    return 'Others';
}

switch ($action) {
    case 'matrix':
        // Determine target department
        $targetDeptId = (!$isSuperAdmin && !empty($managedDeptIds)) ? $managedDeptIds[0] : (isset($_GET['department_id']) && (int)$_GET['department_id'] > 0 ? (int)$_GET['department_id'] : $userDeptId);
        if ($targetDeptId <= 0) $targetDeptId = 2;

        // 1. Fetch configured Tracking Departments (Rows) & Content Types (Columns) for this department
        $trackingDepts = [];
        try {
            $stmtTD = $pdo->prepare("
                SELECT option_name 
                FROM department_tracking_options 
                WHERE department_id = ? AND option_type = 'tracking_dept' AND is_active = 1 
                ORDER BY sort_order ASC, id ASC
            ");
            $stmtTD->execute([$targetDeptId]);
            $trackingDepts = $stmtTD->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}

        if (empty($trackingDepts)) {
            $trackingDepts = ['Digital', 'News Room', 'Programming', 'Documentary', 'Others'];
        }

        $contentTypes = [];
        try {
            $stmtCT = $pdo->prepare("
                SELECT option_name 
                FROM department_tracking_options 
                WHERE department_id = ? AND option_type = 'content_type' AND is_active = 1 
                ORDER BY sort_order ASC, id ASC
            ");
            $stmtCT->execute([$targetDeptId]);
            $contentTypes = $stmtCT->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}

        if (empty($contentTypes)) {
            $contentTypes = ['Reels', 'YT Videos', 'Post Cards', 'FB Videos', 'Podcast'];
        }

        // 2. Query all entries in the date range
        $sql = "
            SELECT 
                se.department,
                se.content_type,
                SUM(COALESCE(se.count_val, 1)) as total_count
            FROM sheet_entries se
            JOIN daily_sheets ds ON se.sheet_id = ds.id
            JOIN employees e ON ds.employee_id = e.id
            WHERE ds.sheet_date BETWEEN ? AND ?
        ";
        $sqlParams = [$startDate, $endDate];
        if (!$isSuperAdmin && !empty($managedDeptIds)) {
            $inD = implode(',', array_fill(0, count($managedDeptIds), '?'));
            $sql .= " AND e.department_id IN ($inD)";
            foreach ($managedDeptIds as $md) {
                $sqlParams[] = $md;
            }
        }
        $sql .= " GROUP BY se.department, se.content_type";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($sqlParams);
        $rawRows = $stmt->fetchAll();

        // Dynamically include any existing raw tracking departments or content types from entries
        foreach ($rawRows as $r) {
            $rDept = trim($r['department'] ?? '');
            if ($rDept !== '') {
                $foundDept = false;
                foreach ($trackingDepts as $td) {
                    if (strcasecmp($td, $rDept) === 0) { $foundDept = true; break; }
                }
                if (!$foundDept) { $trackingDepts[] = $rDept; }
            }

            $rType = trim($r['content_type'] ?? '');
            if ($rType !== '') {
                $foundType = false;
                foreach ($contentTypes as $ct) {
                    if (strcasecmp($ct, $rType) === 0) { $foundType = true; break; }
                }
                if (!$foundType) { $contentTypes[] = $rType; }
            }
        }

        // 3. Build Department Matrix
        $deptMatrix = [];
        $colTotals = array_fill_keys($contentTypes, 0);
        $grandTotal = 0;

        foreach ($trackingDepts as $dept) {
            $deptMatrix[$dept] = [
                'department' => $dept,
                'counts' => array_fill_keys($contentTypes, 0),
                'row_total' => 0
            ];
        }

        foreach ($rawRows as $r) {
            $rawDept = trim($r['department'] ?? 'Others');
            $matchedDept = $trackingDepts[0] ?? 'Others';
            foreach ($trackingDepts as $d) {
                if (strcasecmp($d, $rawDept) === 0) {
                    $matchedDept = $d;
                    break;
                }
            }

            $rawCol = trim($r['content_type'] ?? 'Others');
            $matchedCol = $contentTypes[0] ?? 'Others';
            foreach ($contentTypes as $c) {
                if (strcasecmp($c, $rawCol) === 0) {
                    $matchedCol = $c;
                    break;
                }
            }

            $count = (int)$r['total_count'];

            if (!isset($deptMatrix[$matchedDept])) {
                $deptMatrix[$matchedDept] = [
                    'department' => $matchedDept,
                    'counts' => array_fill_keys($contentTypes, 0),
                    'row_total' => 0
                ];
            }

            if (!isset($deptMatrix[$matchedDept]['counts'][$matchedCol])) {
                $deptMatrix[$matchedDept]['counts'][$matchedCol] = 0;
            }
            $deptMatrix[$matchedDept]['counts'][$matchedCol] += $count;
            $deptMatrix[$matchedDept]['row_total'] += $count;

            if (!isset($colTotals[$matchedCol])) {
                $colTotals[$matchedCol] = 0;
            }
            $colTotals[$matchedCol] += $count;
            $grandTotal += $count;
        }

        // 4. Build Team & Employee Breakdown
        if (!$isSuperAdmin && !empty($managedDeptIds)) {
            $inDStr = implode(',', array_map('intval', $managedDeptIds));
            $empWhere = "WHERE e.is_active = 1 AND e.department_id IN ({$inDStr})";
        } else {
            $empWhere = "WHERE e.is_active = 1";
        }

        $empSql = "
            SELECT 
                t.name as team_name,
                e.id as employee_id,
                e.name as employee_name,
                se.content_type,
                SUM(COALESCE(se.count_val, 1)) as total_count
            FROM employees e
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN daily_sheets ds ON ds.employee_id = e.id AND ds.sheet_date BETWEEN ? AND ?
            LEFT JOIN sheet_entries se ON se.sheet_id = ds.id
            {$empWhere}
            GROUP BY t.name, e.id, e.name, se.content_type
            ORDER BY t.id ASC, e.name ASC
        ";
        $empStmt = $pdo->prepare($empSql);
        $empStmt->execute([$startDate, $endDate]);
        $empRaw = $empStmt->fetchAll();

        $empMap = [];

        // Pre-initialize active employees for this department
        $allEmps = $pdo->query("SELECT e.id, e.name, t.name as team_name FROM employees e LEFT JOIN teams t ON e.team_id = t.id {$empWhere} ORDER BY t.id ASC, e.name ASC")->fetchAll();
        foreach ($allEmps as $emp) {
            $empMap[$emp['id']] = [
                'team_name' => $emp['team_name'] ?? 'General',
                'employee_name' => $emp['name'],
                'counts' => array_fill_keys($contentTypes, 0),
                'total' => 0
            ];
        }

        foreach ($empRaw as $r) {
            $empId = $r['employee_id'];
            $cType = $r['content_type'];
            $count = (int)$r['total_count'];

            if (!$cType || $count <= 0) continue;

            $rawCol = trim($cType);
            $matchedCol = $contentTypes[0] ?? 'Others';
            foreach ($contentTypes as $c) {
                if (strcasecmp($c, $rawCol) === 0) {
                    $matchedCol = $c;
                    break;
                }
            }

            if (!isset($empMap[$empId]['counts'][$matchedCol])) {
                $empMap[$empId]['counts'][$matchedCol] = 0;
            }
            $empMap[$empId]['counts'][$matchedCol] += $count;
            $empMap[$empId]['total'] += $count;
        }

        // Summary Duty Hours
        $dutySql = "
            SELECT 
                COUNT(DISTINCT ds.employee_id) as active_employees,
                SUM(ds.total_duty_seconds) as total_seconds,
                COUNT(ds.id) as total_sheets
            FROM daily_sheets ds
            JOIN employees e ON ds.employee_id = e.id
            WHERE ds.sheet_date BETWEEN ? AND ?
        ";
        $dutyParams = [$startDate, $endDate];
        if (!$isSuperAdmin && !empty($managedDeptIds)) {
            $inD = implode(',', array_fill(0, count($managedDeptIds), '?'));
            $dutySql .= " AND e.department_id IN ($inD)";
            foreach ($managedDeptIds as $md) {
                $dutyParams[] = $md;
            }
        }
        $dutyStmt = $pdo->prepare($dutySql);
        $dutyStmt->execute($dutyParams);
        $dutySummary = $dutyStmt->fetch();

        // Total all-time content count in entire database
        if (!$isSuperAdmin && !empty($managedDeptIds)) {
            $inD = implode(',', array_fill(0, count($managedDeptIds), '?'));
            $allTimeStmt = $pdo->prepare("
                SELECT COALESCE(SUM(se.count_val), COUNT(*)) 
                FROM sheet_entries se
                JOIN daily_sheets ds ON se.sheet_id = ds.id
                JOIN employees e ON ds.employee_id = e.id
                WHERE e.department_id IN ($inD)
            ");
            $allTimeStmt->execute($managedDeptIds);
            $allTimeContent = (int)$allTimeStmt->fetchColumn();
        } else {
            $allTimeContent = (int)$pdo->query("SELECT COALESCE(SUM(count_val), COUNT(*)) FROM sheet_entries")->fetchColumn();
        }

        echo json_encode([
            'success' => true,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'content_types' => $contentTypes,
            'department_matrix' => array_values($deptMatrix),
            'column_totals' => $colTotals,
            'grand_total' => $grandTotal,
            'all_time_content' => $allTimeContent,
            'employee_breakdown' => array_values($empMap),
            'duty_summary' => [
                'active_employees' => (int)($dutySummary['active_employees'] ?? 0),
                'total_sheets' => (int)($dutySummary['total_sheets'] ?? 0),
                'total_hours' => round(($dutySummary['total_seconds'] ?? 0) / 3600, 1)
            ]
        ]);
        break;

    case 'export_csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=worksheet_report_' . $startDate . '_to_' . $endDate . '.csv');
        $output = fopen('php://output', 'w');

        $targetDeptId = (!$isSuperAdmin && !empty($managedDeptIds)) ? $managedDeptIds[0] : (isset($_GET['department_id']) && (int)$_GET['department_id'] > 0 ? (int)$_GET['department_id'] : $userDeptId);
        if ($targetDeptId <= 0) $targetDeptId = 2;

        $trackingDepts = [];
        try {
            $stmtTD = $pdo->prepare("SELECT option_name FROM department_tracking_options WHERE department_id = ? AND option_type = 'tracking_dept' AND is_active = 1 ORDER BY sort_order ASC, id ASC");
            $stmtTD->execute([$targetDeptId]);
            $trackingDepts = $stmtTD->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}
        if (empty($trackingDepts)) $trackingDepts = ['Digital', 'News Room', 'Programming', 'Documentary', 'Others'];

        $contentTypes = [];
        try {
            $stmtCT = $pdo->prepare("SELECT option_name FROM department_tracking_options WHERE department_id = ? AND option_type = 'content_type' AND is_active = 1 ORDER BY sort_order ASC, id ASC");
            $stmtCT->execute([$targetDeptId]);
            $contentTypes = $stmtCT->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}
        if (empty($contentTypes)) $contentTypes = ['Reels', 'YT Videos', 'Post Cards', 'FB Videos', 'Podcast'];

        $deptSql = "
            SELECT 
                se.department,
                se.content_type,
                SUM(COALESCE(se.count_val, 1)) as total_count
            FROM sheet_entries se
            JOIN daily_sheets ds ON se.sheet_id = ds.id
            JOIN employees e ON ds.employee_id = e.id
            WHERE ds.sheet_date BETWEEN ? AND ?
        ";
        $deptSqlParams = [$startDate, $endDate];
        if (!$isSuperAdmin && !empty($managedDeptIds)) {
            $inD = implode(',', array_fill(0, count($managedDeptIds), '?'));
            $deptSql .= " AND e.department_id IN ($inD)";
            foreach ($managedDeptIds as $md) {
                $deptSqlParams[] = $md;
            }
        }
        $deptSql .= " GROUP BY se.department, se.content_type";
        $stmt = $pdo->prepare($deptSql);
        $stmt->execute($deptSqlParams);
        $rawRows = $stmt->fetchAll();

        foreach ($rawRows as $r) {
            $rDept = trim($r['department'] ?? '');
            if ($rDept !== '') {
                $foundDept = false;
                foreach ($trackingDepts as $td) {
                    if (strcasecmp($td, $rDept) === 0) { $foundDept = true; break; }
                }
                if (!$foundDept) { $trackingDepts[] = $rDept; }
            }

            $rType = trim($r['content_type'] ?? '');
            if ($rType !== '') {
                $foundType = false;
                foreach ($contentTypes as $ct) {
                    if (strcasecmp($ct, $rType) === 0) { $foundType = true; break; }
                }
                if (!$foundType) { $contentTypes[] = $rType; }
            }
        }

        // CSV Header
        fputcsv($output, ['Report: Department Content Matrix', 'Start Date: ' . $startDate, 'End Date: ' . $endDate]);
        fputcsv($output, array_merge(['Tracking Department'], $contentTypes, ['Total']));

        $matrix = [];
        foreach ($trackingDepts as $dept) {
            $matrix[$dept] = array_fill_keys($contentTypes, 0);
        }

        foreach ($rawRows as $r) {
            $rawDept = trim($r['department'] ?? 'Others');
            $matchedDept = $trackingDepts[0] ?? 'Others';
            foreach ($trackingDepts as $d) {
                if (strcasecmp($d, $rawDept) === 0) {
                    $matchedDept = $d;
                    break;
                }
            }

            $rawCol = trim($r['content_type'] ?? 'Others');
            $matchedCol = $contentTypes[0] ?? 'Others';
            foreach ($contentTypes as $c) {
                if (strcasecmp($c, $rawCol) === 0) {
                    $matchedCol = $c;
                    break;
                }
            }

            if (!isset($matrix[$matchedDept][$matchedCol])) {
                $matrix[$matchedDept][$matchedCol] = 0;
            }
            $matrix[$matchedDept][$matchedCol] += (int)$r['total_count'];
        }

        foreach ($matrix as $dept => $vals) {
            $rowTot = array_sum($vals);
            fputcsv($output, array_merge([$dept], array_values($vals), [$rowTot]));
        }

        fputcsv($output, []);
        fputcsv($output, ['Team & Employee Performance Breakdown']);
        fputcsv($output, array_merge(['Team', 'Employee Name'], $contentTypes, ['Total']));

        $empExportWhere = (!$isSuperAdmin && !empty($managedDeptIds)) ? "WHERE e.is_active = 1 AND e.department_id IN (" . implode(',', array_map('intval', $managedDeptIds)) . ")" : "WHERE e.is_active = 1";
        $empSql = "
            SELECT 
                t.name as team_name,
                e.name as employee_name,
                se.content_type,
                SUM(COALESCE(se.count_val, 1)) as total_count
            FROM employees e
            LEFT JOIN teams t ON e.team_id = t.id
            LEFT JOIN daily_sheets ds ON ds.employee_id = e.id AND ds.sheet_date BETWEEN ? AND ?
            LEFT JOIN sheet_entries se ON se.sheet_id = ds.id
            {$empExportWhere}
            GROUP BY t.name, e.name, se.content_type
            ORDER BY t.id ASC, e.name ASC
        ";
        $empStmt = $pdo->prepare($empSql);
        $empStmt->execute([$startDate, $endDate]);
        $empRows = $empStmt->fetchAll();

        $emps = [];
        foreach ($empRows as $r) {
            $key = ($r['team_name'] ?? 'General') . '|' . $r['employee_name'];
            if (!isset($emps[$key])) {
                $emps[$key] = [
                    'team' => $r['team_name'] ?? 'General',
                    'name' => $r['employee_name'],
                    'counts' => array_fill_keys($contentTypes, 0)
                ];
            }
            $rawCol = trim($r['content_type'] ?? 'Others');
            $matchedCol = $contentTypes[0] ?? 'Others';
            foreach ($contentTypes as $c) {
                if (strcasecmp($c, $rawCol) === 0) {
                    $matchedCol = $c;
                    break;
                }
            }
            if (!isset($emps[$key]['counts'][$matchedCol])) {
                $emps[$key]['counts'][$matchedCol] = 0;
            }
            $emps[$key]['counts'][$matchedCol] += (int)$r['total_count'];
        }

        foreach ($emps as $e) {
            $tot = array_sum($e['counts']);
            fputcsv($output, array_merge([$e['team'], $e['name']], array_values($e['counts']), [$tot]));
        }

        fclose($output);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
