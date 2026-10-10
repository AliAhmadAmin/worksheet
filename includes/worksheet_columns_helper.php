<?php
/**
 * Worksheet Columns Helper Functions
 */

if (!function_exists('ensureDepartmentDefaultColumns')) {
    function ensureDepartmentDefaultColumns($pdo, $departmentId, $force = false) {
        if ($departmentId <= 0) return;

        if (!$force) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM department_worksheet_columns WHERE department_id = ?");
            $check->execute([$departmentId]);
            if ((int)$check->fetchColumn() > 0) {
                return;
            }
        } else {
            $pdo->prepare("DELETE FROM department_worksheet_columns WHERE department_id = ?")->execute([$departmentId]);
        }

        $defaults = [
            ['time_slot', 'Time Slot', 'time', null, 1, 1, 1],
            ['content_type', 'Content Type', 'select', null, 2, 1, 1],
            ['department', 'Department', 'select', null, 3, 1, 1],
            ['link', 'Link / Upload', 'link', null, 4, 1, 1],
            ['title', 'Work Description / Title', 'text', null, 5, 1, 1]
        ];

        $ins = $pdo->prepare("
            INSERT INTO department_worksheet_columns 
            (department_id, column_key, column_label, column_type, options_json, sort_order, is_visible, is_core)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($defaults as $col) {
            $ins->execute([
                $departmentId,
                $col[0],
                $col[1],
                $col[2],
                $col[3],
                $col[4],
                $col[5],
                $col[6]
            ]);
        }
    }
}

if (!function_exists('getDepartmentWorksheetColumns')) {
    function getDepartmentWorksheetColumns($pdo, $departmentId, $includeHidden = false) {
        $deptId = (int)$departmentId;
        if ($deptId <= 0) $deptId = 2;

        ensureDepartmentDefaultColumns($pdo, $deptId);

        $whereSql = $includeHidden ? "WHERE department_id = ?" : "WHERE department_id = ? AND is_visible = 1";
        $stmtCols = $pdo->prepare("
            SELECT id, column_key, column_label, column_type, options_json, sort_order, is_visible, is_core 
            FROM department_worksheet_columns 
            {$whereSql}
            ORDER BY sort_order ASC, id ASC
        ");
        $stmtCols->execute([$deptId]);
        $columns = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as &$col) {
            $col['is_visible'] = (int)$col['is_visible'];
            $col['is_core'] = (int)$col['is_core'];
            $col['sort_order'] = (int)$col['sort_order'];
            if (!empty($col['options_json'])) {
                $decoded = json_decode($col['options_json'], true);
                $col['options'] = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $col['options_json'])));
            } else {
                $col['options'] = [];
            }
        }
        unset($col);

        return $columns;
    }
}
