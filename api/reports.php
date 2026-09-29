<?php
/**
 * Reports & Matrix Analytics API
 * Replicates and enhances the exact Google Sheet cross-tabulation reports
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'matrix';

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

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
        // 1. Fetch distinct Departments & Content Types
        $departments = $pdo->query("SELECT name FROM departments WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($departments)) {
            $departments = ['News Room', 'Digital', 'Programming', 'Documentary', 'Others'];
        }
        $contentTypes = ['Reels', 'YT Videos', 'Post Cards', 'FB Videos', 'Podcast'];

        // 2. Query all entries in the date range
        $sql = "
            SELECT 
                se.department,
                se.content_type,
                SUM(COALESCE(se.count_val, 1)) as total_count
            FROM sheet_entries se
            JOIN daily_sheets ds ON se.sheet_id = ds.id
            WHERE ds.sheet_date BETWEEN ? AND ?
            GROUP BY se.department, se.content_type
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        $rawRows = $stmt->fetchAll();

        // 3. Build Department Matrix
        $deptMatrix = [];
        $colTotals = array_fill_keys($contentTypes, 0);
        $grandTotal = 0;

        foreach ($departments as $dept) {
            $row = [
                'department' => $dept,
                'counts' => array_fill_keys($contentTypes, 0),
                'row_total' => 0
            ];
            $deptMatrix[$dept] = $row;
        }

        foreach ($rawRows as $r) {
            $rawDept = trim($r['department'] ?? 'Digital');
            // Canonical match department
            $matchedDept = 'Others';
            foreach ($departments as $d) {
                if (strcasecmp($d, $rawDept) === 0) {
                    $matchedDept = $d;
                    break;
                }
            }

            $matchedCol = normalizeMatrixContentType($r['content_type'] ?? '');
            $count = (int)$r['total_count'];

            if (!isset($deptMatrix[$matchedDept])) {
                $deptMatrix[$matchedDept] = [
                    'department' => $matchedDept,
                    'counts' => array_fill_keys($contentTypes, 0),
                    'row_total' => 0
                ];
            }

            if (isset($deptMatrix[$matchedDept]['counts'][$matchedCol])) {
                $deptMatrix[$matchedDept]['counts'][$matchedCol] += $count;
            } else {
                $deptMatrix[$matchedDept]['counts'][$matchedCol] = $count;
            }

            $deptMatrix[$matchedDept]['row_total'] += $count;
            if (isset($colTotals[$matchedCol])) {
                $colTotals[$matchedCol] += $count;
            }
            $grandTotal += $count;
        }

        // 4. Build Team & Employee Breakdown
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
            WHERE e.is_active = 1
            GROUP BY t.name, e.id, e.name, se.content_type
            ORDER BY t.id ASC, e.name ASC
        ";
        $empStmt = $pdo->prepare($empSql);
        $empStmt->execute([$startDate, $endDate]);
        $empRaw = $empStmt->fetchAll();

        $empMap = [];

        // Pre-initialize all active employees
        $allEmps = $pdo->query("SELECT e.id, e.name, t.name as team_name FROM employees e LEFT JOIN teams t ON e.team_id = t.id WHERE e.is_active = 1 ORDER BY t.id ASC, e.name ASC")->fetchAll();
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

            $matchedCol = normalizeMatrixContentType($cType);

            if (isset($empMap[$empId]['counts'][$matchedCol])) {
                $empMap[$empId]['counts'][$matchedCol] += $count;
                $empMap[$empId]['total'] += $count;
            }
        }

        // Summary Duty Hours
        $dutySql = "
            SELECT 
                COUNT(DISTINCT ds.employee_id) as active_employees,
                SUM(ds.total_duty_seconds) as total_seconds,
                COUNT(ds.id) as total_sheets
            FROM daily_sheets ds
            WHERE ds.sheet_date BETWEEN ? AND ?
        ";
        $dutyStmt = $pdo->prepare($dutySql);
        $dutyStmt->execute([$startDate, $endDate]);
        $dutySummary = $dutyStmt->fetch();

        // Total all-time content count in entire database
        $allTimeContent = (int)$pdo->query("SELECT COALESCE(SUM(count_val), COUNT(*)) FROM sheet_entries")->fetchColumn();

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

        // CSV Header
        fputcsv($output, ['Report: Department Content Matrix', 'Start Date: ' . $startDate, 'End Date: ' . $endDate]);
        fputcsv($output, ['Department', 'Reels', 'YT Videos', 'Post Cards', 'FB Videos', 'Podcast', 'Total']);

        $columns = ['Reels', 'YT Videos', 'Post Cards', 'FB Videos', 'Podcast'];
        $departments = ['News Room', 'Digital', 'Programming', 'Documentary', 'Others'];

        $deptSql = "
            SELECT 
                se.department,
                se.content_type,
                SUM(COALESCE(se.count_val, 1)) as total_count
            FROM sheet_entries se
            JOIN daily_sheets ds ON se.sheet_id = ds.id
            WHERE ds.sheet_date BETWEEN ? AND ?
            GROUP BY se.department, se.content_type
        ";
        $stmt = $pdo->prepare($deptSql);
        $stmt->execute([$startDate, $endDate]);
        $rawRows = $stmt->fetchAll();

        $matrix = [];
        foreach ($departments as $dept) {
            $matrix[$dept] = array_fill_keys($columns, 0);
        }

        foreach ($rawRows as $r) {
            $rawDept = trim($r['department'] ?? 'Digital');
            $matchedDept = 'Others';
            foreach ($departments as $d) {
                if (strcasecmp($d, $rawDept) === 0) {
                    $matchedDept = $d;
                    break;
                }
            }

            $ct = normalizeMatrixContentType($r['content_type'] ?? '');
            if (isset($matrix[$matchedDept][$ct])) {
                $matrix[$matchedDept][$ct] += (int)$r['total_count'];
            }
        }

        foreach ($matrix as $dept => $vals) {
            $rowTot = array_sum($vals);
            fputcsv($output, array_merge([$dept], array_values($vals), [$rowTot]));
        }

        fputcsv($output, []);
        fputcsv($output, ['Team & Employee Performance Breakdown']);
        fputcsv($output, ['Team', 'Employee Name', 'Reels', 'YT Videos', 'Post Cards', 'FB Videos', 'Podcast', 'Total']);

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
            WHERE e.is_active = 1
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
                    'counts' => array_fill_keys($columns, 0)
                ];
            }
            $ct = normalizeMatrixContentType($r['content_type'] ?? '');
            if (isset($emps[$key]['counts'][$ct])) {
                $emps[$key]['counts'][$ct] += (int)$r['total_count'];
            }
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
