<?php
/**
 * Discover Pakistan UHD TV - Digital Fresh Programs API
 * Weekly Program Editing, Multi-Editor Assignment, Clip & Reel Extraction, and Locking System
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$data = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

$currentUserId = (int)($_SESSION['user_id'] ?? ($data['employee_id'] ?? 0));
if (!$currentUserId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in to continue.']);
    exit;
}

// Fetch user profile & roles
$stmtUser = $pdo->prepare("
    SELECT e.*, d.name as department_name, t.name as team_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id 
    LEFT JOIN teams t ON e.team_id = t.id 
    WHERE e.id = ?
");
$stmtUser->execute([$currentUserId]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];

$userRole = strtolower($currentUser['role'] ?? 'employee');
$userDeptName = strtolower($currentUser['department_name'] ?? '');
$userDeptId = (int)($currentUser['department_id'] ?? 0);

$isGlobalAdmin = in_array($userRole, ['super_admin', 'admin', 'manager']);
$isHod = ($isGlobalAdmin || in_array($userRole, ['hod', 'deputy_hod']));
$isDigitalMember = (stripos($userDeptName, 'digital') !== false || $isGlobalAdmin);

// Digital HOD managed departments
$managedDeptIds = getHodManagedDepartmentIds($pdo, $currentUserId, $userDeptId);

/**
 * Increment Episode Number smartly
 * Handles: "5" -> "6", "Ep 4" -> "Ep 5", "Episode 12" -> "Episode 13", "31 A" -> "32 A"
 */
function incrementEpisodeNumber($ep) {
    $ep = trim((string)$ep);
    if ($ep === '') return '1';
    if (is_numeric($ep)) return (string)((int)$ep + 1);

    if (preg_match('/^(.*?)(\d+)$/', $ep, $matches)) {
        $prefix = $matches[1];
        $num = (int)$matches[2];
        $padLen = strlen($matches[2]);
        $newNum = str_pad($num + 1, $padLen, '0', STR_PAD_LEFT);
        return $prefix . $newNum;
    }

    if (preg_match('/^(\d+)(\s*[a-zA-Z]*)$/', $ep, $matches)) {
        $num = (int)$matches[1];
        $suffix = $matches[2];
        return ($num + 1) . $suffix;
    }

    if (preg_match('/\d+/', $ep, $m)) {
        return preg_replace('/\d+/', (int)$m[0] + 1, $ep, 1);
    }

    return $ep . ' (Next)';
}

/**
 * Auto-roll over a recurring program to the next week (+7 days)
 * Matches broadcast day, increments episode number, and keeps same editor assignments
 */
function autoRollOverProgram($pdo, $progId, $currentUserId) {
    $stmt = $pdo->prepare("SELECT * FROM digital_fresh_programs WHERE id = ?");
    $stmt->execute([$progId]);
    $prog = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prog || (int)($prog['is_recurring'] ?? 1) !== 1) {
        return null;
    }

    $currentDate = $prog['program_date'];
    $nextDate = date('Y-m-d', strtotime($currentDate . ' +7 days'));
    $nextDay = date('l', strtotime($nextDate));
    $nextEp = incrementEpisodeNumber($prog['episode_no']);

    // Check if next episode already exists for this program name on that date
    $stmtChk = $pdo->prepare("SELECT id FROM digital_fresh_programs WHERE program_name = ? AND program_date = ?");
    $stmtChk->execute([$prog['program_name'], $nextDate]);
    $existingId = $stmtChk->fetchColumn();

    if ($existingId) {
        return (int)$existingId;
    }

    // Insert next week's episode on that broadcast day
    $stmtIns = $pdo->prepare("
        INSERT INTO digital_fresh_programs (program_date, program_name, episode_no, source_url, status, is_locked, notes, assigned_by, broadcast_day, is_recurring)
        VALUES (?, ?, ?, ?, 'pending', 0, ?, ?, ?, 1)
    ");
    $stmtIns->execute([
        $nextDate,
        $prog['program_name'],
        $nextEp,
        $prog['source_url'] ?? null,
        $prog['notes'] ?? null,
        $currentUserId,
        $nextDay
    ]);
    $newProgId = (int)$pdo->lastInsertId();

    // Copy assigned editors
    $stmtEds = $pdo->prepare("SELECT employee_id FROM digital_program_editors WHERE program_id = ?");
    $stmtEds->execute([$progId]);
    $editorIds = $stmtEds->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($editorIds)) {
        $stmtInsEd = $pdo->prepare("INSERT IGNORE INTO digital_program_editors (program_id, employee_id) VALUES (?, ?)");
        foreach ($editorIds as $eid) {
            $stmtInsEd->execute([$newProgId, $eid]);
        }
    }

    return [
        'id' => $newProgId,
        'date' => $nextDate,
        'episode_no' => $nextEp,
        'program_name' => $prog['program_name'],
        'day' => $nextDay
    ];
}

switch ($action) {
    case 'list':
        $date = $_GET['date'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $month = $_GET['month'] ?? null;
        $status = $_GET['status'] ?? 'all';
        $programName = trim($_GET['program_name'] ?? 'all');
        $editorId = !empty($_GET['editor_id']) ? (int)$_GET['editor_id'] : null;
        $search = trim($_GET['search'] ?? '');

        $where = ["1=1"];
        $params = [];

        if (!empty($date)) {
            $where[] = "p.program_date = ?";
            $params[] = $date;
        } elseif (!empty($dateFrom) && !empty($dateTo)) {
            $where[] = "p.program_date BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } elseif (!empty($month)) {
            $where[] = "p.program_date LIKE ?";
            $params[] = "{$month}%";
        }

        if ($status !== 'all' && in_array($status, ['pending', 'in_progress', 'completed'])) {
            $where[] = "p.status = ?";
            $params[] = $status;
        }

        if ($programName !== '' && $programName !== 'all') {
            $where[] = "p.program_name = ?";
            $params[] = $programName;
        }

        if ($editorId) {
            $where[] = "p.id IN (SELECT program_id FROM digital_program_editors WHERE employee_id = ?)";
            $params[] = $editorId;
        }

        if ($search !== '') {
            $where[] = "(p.program_name LIKE ? OR p.episode_no LIKE ? OR p.notes LIKE ? OR p.id IN (SELECT dpe.program_id FROM digital_program_editors dpe JOIN employees e ON dpe.employee_id = e.id WHERE e.name LIKE ?))";
            $s = "%{$search}%";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $sql = "
            SELECT p.*,
                   u.name as assigned_by_name,
                   (SELECT COUNT(*) FROM digital_program_links WHERE program_id = p.id AND link_type = 'clip') as clips_count,
                   (SELECT COUNT(*) FROM digital_program_links WHERE program_id = p.id AND link_type = 'reel') as reels_count
            FROM digital_fresh_programs p
            LEFT JOIN employees u ON p.assigned_by = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.program_date ASC, p.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all distinct program names ever added
        $stmtProgNames = $pdo->query("SELECT DISTINCT program_name FROM digital_fresh_programs WHERE program_name IS NOT NULL AND program_name != '' ORDER BY program_name ASC");
        $allProgramNames = $stmtProgNames->fetchAll(PDO::FETCH_COLUMN);

        if (empty($programs)) {
            echo json_encode([
                'success' => true,
                'programs' => [],
                'stats' => ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'clips' => 0, 'reels' => 0],
                'is_hod' => $isHod,
                'program_names' => $allProgramNames
            ]);
            exit;
        }

        $progIds = array_column($programs, 'id');
        $placeholders = implode(',', array_fill(0, count($progIds), '?'));

        // Fetch assigned editors
        $stmtEditors = $pdo->prepare("
            SELECT pe.program_id, e.id, e.name, e.designation, e.avatar, e.emp_code
            FROM digital_program_editors pe
            JOIN employees e ON pe.employee_id = e.id
            WHERE pe.program_id IN ($placeholders)
            ORDER BY e.name ASC
        ");
        $stmtEditors->execute($progIds);
        $allEditors = $stmtEditors->fetchAll(PDO::FETCH_ASSOC);

        $editorsMap = [];
        foreach ($allEditors as $ed) {
            $editorsMap[$ed['program_id']][] = [
                'id' => (int)$ed['id'],
                'name' => $ed['name'],
                'designation' => $ed['designation'],
                'avatar' => $ed['avatar'],
                'emp_code' => $ed['emp_code']
            ];
        }

        // Fetch all links (clips and reels)
        $stmtLinks = $pdo->prepare("
            SELECT l.*, e.name as added_by_name
            FROM digital_program_links l
            LEFT JOIN employees e ON l.added_by = e.id
            WHERE l.program_id IN ($placeholders)
            ORDER BY l.id ASC
        ");
        $stmtLinks->execute($progIds);
        $allLinks = $stmtLinks->fetchAll(PDO::FETCH_ASSOC);

        $linksMap = [];
        foreach ($allLinks as $lk) {
            $type = $lk['link_type'] === 'reel' ? 'reels' : 'clips';
            $linksMap[$lk['program_id']][$type][] = [
                'id' => (int)$lk['id'],
                'type' => $lk['link_type'],
                'url' => $lk['url'],
                'title' => $lk['title'],
                'added_by' => (int)$lk['added_by'],
                'added_by_name' => $lk['added_by_name'] ?: 'Team Member',
                'created_at' => $lk['created_at']
            ];
        }

        $stats = [
            'total' => count($programs),
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'clips' => 0,
            'reels' => 0
        ];

        foreach ($programs as &$prog) {
            $pid = (int)$prog['id'];
            $prog['id'] = $pid;
            $prog['is_locked'] = (int)$prog['is_locked'];
            $prog['clips_count'] = (int)$prog['clips_count'];
            $prog['reels_count'] = (int)$prog['reels_count'];
            $prog['editors'] = $editorsMap[$pid] ?? [];
            $prog['clips'] = $linksMap[$pid]['clips'] ?? [];
            $prog['reels'] = $linksMap[$pid]['reels'] ?? [];

            // Stats tally
            if ($prog['status'] === 'completed') $stats['completed']++;
            elseif ($prog['status'] === 'in_progress') $stats['in_progress']++;
            else $stats['pending']++;

            $stats['clips'] += $prog['clips_count'];
            $stats['reels'] += $prog['reels_count'];

            // Editor access logic:
            // Is current user assigned as an editor for this program?
            $isAssigned = false;
            foreach ($prog['editors'] as $ed) {
                if ($ed['id'] === $currentUserId) {
                    $isAssigned = true;
                    break;
                }
            }

            // Can edit program metadata (title, episode, date, editors): strictly HOD / Admin
            $prog['can_manage'] = $isHod;

            // Can update status / add links:
            // If not locked: HOD OR Assigned Editor
            // If locked: ONLY HOD
            if ($prog['is_locked']) {
                $prog['can_work'] = $isHod;
            } else {
                $prog['can_work'] = ($isHod || $isAssigned);
            }
            $prog['is_assigned_to_me'] = $isAssigned;
        }

        echo json_encode([
            'success' => true,
            'programs' => $programs,
            'stats' => $stats,
            'is_hod' => $isHod,
            'program_names' => $allProgramNames
        ]);
        break;

    case 'calendar_counts':
        // Returns counts for every day of the month for calendar dots
        $month = $_GET['month'] ?? date('Y-m');
        $stmt = $pdo->prepare("
            SELECT program_date,
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM digital_fresh_programs
            WHERE program_date LIKE ?
            GROUP BY program_date
        ");
        $stmt->execute(["{$month}%"]);
        $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $calendarData = [];
        foreach ($days as $d) {
            $calendarData[$d['program_date']] = [
                'total' => (int)$d['total'],
                'completed' => (int)$d['completed'],
                'in_progress' => (int)$d['in_progress'],
                'pending' => (int)$d['pending']
            ];
        }

        echo json_encode([
            'success' => true,
            'month' => $month,
            'days' => $calendarData
        ]);
        break;

    case 'save_program':
        // Only HOD / Admin can create or edit program assignments
        if (!$isHod) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Permission Denied: Only HOD or Administrator can assign or edit programs.']);
            exit;
        }

        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $progDate = trim($data['program_date'] ?? date('Y-m-d'));
        $progName = trim($data['program_name'] ?? '');
        $episodeNo = trim($data['episode_no'] ?? '');
        $sourceUrl = trim($data['source_url'] ?? '');
        $notes = trim($data['notes'] ?? '');
        $isRecurring = isset($data['is_recurring']) ? (int)$data['is_recurring'] : 1;
        $broadcastDay = date('l', strtotime($progDate));
        $editorIds = is_array($data['editor_ids'] ?? null) ? array_map('intval', $data['editor_ids']) : [];

        if (empty($progName)) {
            echo json_encode(['success' => false, 'message' => 'Program Name is required.']);
            exit;
        }
        if (empty($episodeNo)) {
            echo json_encode(['success' => false, 'message' => 'Episode # is required.']);
            exit;
        }

        if ($id > 0) {
            // Update existing
            $stmtUp = $pdo->prepare("
                UPDATE digital_fresh_programs 
                SET program_date = ?, program_name = ?, episode_no = ?, source_url = ?, notes = ?, broadcast_day = ?, is_recurring = ?
                WHERE id = ?
            ");
            $stmtUp->execute([$progDate, $progName, $episodeNo, $sourceUrl, $notes, $broadcastDay, $isRecurring, $id]);
            $progId = $id;
        } else {
            // Create new
            $stmtIns = $pdo->prepare("
                INSERT INTO digital_fresh_programs (program_date, program_name, episode_no, source_url, status, is_locked, notes, assigned_by, broadcast_day, is_recurring)
                VALUES (?, ?, ?, ?, 'pending', 0, ?, ?, ?, ?)
            ");
            $stmtIns->execute([$progDate, $progName, $episodeNo, $sourceUrl, $notes, $currentUserId, $broadcastDay, $isRecurring]);
            $progId = (int)$pdo->lastInsertId();
        }

        // Sync editors
        $pdo->prepare("DELETE FROM digital_program_editors WHERE program_id = ?")->execute([$progId]);
        if (!empty($editorIds)) {
            $stmtEditorIns = $pdo->prepare("INSERT IGNORE INTO digital_program_editors (program_id, employee_id) VALUES (?, ?)");
            foreach ($editorIds as $eid) {
                if ($eid > 0) {
                    $stmtEditorIns->execute([$progId, $eid]);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => $id > 0 ? 'Program updated successfully!' : 'Program assigned successfully!',
            'program_id' => $progId
        ]);
        break;

    case 'update_status':
        $progId = (int)($data['id'] ?? 0);
        $newStatus = trim($data['status'] ?? '');

        if (!$progId || !in_array($newStatus, ['pending', 'in_progress', 'completed'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid program ID or status.']);
            exit;
        }

        // Check program lock and user permission
        $stmtChk = $pdo->prepare("SELECT * FROM digital_fresh_programs WHERE id = ?");
        $stmtChk->execute([$progId]);
        $prog = $stmtChk->fetch(PDO::FETCH_ASSOC);

        if (!$prog) {
            echo json_encode(['success' => false, 'message' => 'Program not found.']);
            exit;
        }

        // Check if user is an assigned editor
        $stmtAssigned = $pdo->prepare("SELECT 1 FROM digital_program_editors WHERE program_id = ? AND employee_id = ?");
        $stmtAssigned->execute([$progId, $currentUserId]);
        $isAssigned = (bool)$stmtAssigned->fetchColumn();

        if (!$isHod && !$isAssigned) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not assigned to this program.']);
            exit;
        }

        // If currently locked, only HOD can unlock/change
        if ((int)$prog['is_locked'] === 1 && !$isHod) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'This program is completed and locked. Only HOD can unlock or modify it.']);
            exit;
        }

        // Auto-lock when set to 'completed'
        $isLocked = ($newStatus === 'completed') ? 1 : 0;

        $stmtUp = $pdo->prepare("UPDATE digital_fresh_programs SET status = ?, is_locked = ? WHERE id = ?");
        $stmtUp->execute([$newStatus, $isLocked, $progId]);

        // Auto-roll over to next week if marked completed and recurring
        $rolloverInfo = null;
        if ($newStatus === 'completed' && (int)($prog['is_recurring'] ?? 1) === 1) {
            $rolloverResult = autoRollOverProgram($pdo, $progId, $currentUserId);
            if (is_array($rolloverResult)) {
                $rolloverInfo = $rolloverResult;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Program status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . ($isLocked ? ' (Program Locked 🔒)' : '') . ($rolloverInfo ? " • Next week's episode ({$rolloverInfo['episode_no']}) auto-scheduled for {$rolloverInfo['date']} ({$rolloverInfo['day']})!" : ''),
            'status' => $newStatus,
            'is_locked' => $isLocked,
            'rollover' => $rolloverInfo
        ]);
        break;

    case 'rollover_week':
        if (!$isHod) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only HOD or Administrator can roll over week schedule.']);
            exit;
        }

        $dateFrom = trim($data['date_from'] ?? '');
        $dateTo = trim($data['date_to'] ?? '');

        if (empty($dateFrom) || empty($dateTo)) {
            echo json_encode(['success' => false, 'message' => 'Valid week date range is required.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT id FROM digital_fresh_programs 
            WHERE program_date BETWEEN ? AND ? AND is_recurring = 1
            ORDER BY program_date ASC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $created = [];
        foreach ($programs as $p) {
            $res = autoRollOverProgram($pdo, $p['id'], $currentUserId);
            if (is_array($res)) {
                $created[] = $res;
            }
        }

        $count = count($created);
        echo json_encode([
            'success' => true,
            'message' => $count > 0 ? "Successfully auto-advanced {$count} program(s) to next week with incremented episode numbers!" : "All recurring programs for next week are already up-to-date.",
            'created_count' => $count,
            'episodes' => $created
        ]);
        break;

    case 'toggle_lock':
        if (!$isHod) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only HOD or Administrator can lock/unlock programs.']);
            exit;
        }

        $progId = (int)($data['id'] ?? 0);
        $lockVal = !empty($data['is_locked']) ? 1 : 0;

        $stmtUp = $pdo->prepare("UPDATE digital_fresh_programs SET is_locked = ? WHERE id = ?");
        $stmtUp->execute([$lockVal, $progId]);

        echo json_encode([
            'success' => true,
            'message' => $lockVal ? 'Program locked successfully.' : 'Program unlocked successfully.',
            'is_locked' => $lockVal
        ]);
        break;

    case 'add_link':
        $progId = (int)($data['program_id'] ?? 0);
        $linkType = trim($data['link_type'] ?? 'clip');
        $url = trim($data['url'] ?? '');
        $title = trim($data['title'] ?? '');

        if (!$progId || empty($url) || !in_array($linkType, ['clip', 'reel'])) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid program ID, URL, and link type.']);
            exit;
        }

        $stmtChk = $pdo->prepare("SELECT * FROM digital_fresh_programs WHERE id = ?");
        $stmtChk->execute([$progId]);
        $prog = $stmtChk->fetch(PDO::FETCH_ASSOC);

        if (!$prog) {
            echo json_encode(['success' => false, 'message' => 'Program not found.']);
            exit;
        }

        // Check lock
        if ((int)$prog['is_locked'] === 1 && !$isHod) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Program is locked. Only HOD can modify locked programs.']);
            exit;
        }

        // Check if assigned editor or HOD
        $stmtAssigned = $pdo->prepare("SELECT 1 FROM digital_program_editors WHERE program_id = ? AND employee_id = ?");
        $stmtAssigned->execute([$progId, $currentUserId]);
        $isAssigned = (bool)$stmtAssigned->fetchColumn();

        if (!$isHod && !$isAssigned) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not assigned to edit this program.']);
            exit;
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO digital_program_links (program_id, link_type, added_by, url, title)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([$progId, $linkType, $currentUserId, $url, $title]);
        $newLinkId = (int)$pdo->lastInsertId();

        // Get updated links list and count
        $stmtLinks = $pdo->prepare("
            SELECT l.*, e.name as added_by_name 
            FROM digital_program_links l 
            LEFT JOIN employees e ON l.added_by = e.id 
            WHERE l.program_id = ? AND l.link_type = ? 
            ORDER BY l.id ASC
        ");
        $stmtLinks->execute([$progId, $linkType]);
        $links = $stmtLinks->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => ucfirst($linkType) . ' link added successfully!',
            'link_id' => $newLinkId,
            'count' => count($links),
            'links' => $links
        ]);
        break;

    case 'delete_link':
        $linkId = (int)($data['link_id'] ?? 0);
        if (!$linkId) {
            echo json_encode(['success' => false, 'message' => 'Invalid link ID.']);
            exit;
        }

        $stmtLink = $pdo->prepare("SELECT l.*, p.is_locked FROM digital_program_links l JOIN digital_fresh_programs p ON l.program_id = p.id WHERE l.id = ?");
        $stmtLink->execute([$linkId]);
        $link = $stmtLink->fetch(PDO::FETCH_ASSOC);

        if (!$link) {
            echo json_encode(['success' => false, 'message' => 'Link not found.']);
            exit;
        }

        // Lock check
        if ((int)$link['is_locked'] === 1 && !$isHod) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Program is locked. Only HOD can delete links.']);
            exit;
        }

        // Permission: HOD or owner of link
        if (!$isHod && (int)$link['added_by'] !== $currentUserId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You can only delete links that you added.']);
            exit;
        }

        $progId = (int)$link['program_id'];
        $linkType = $link['link_type'];

        $pdo->prepare("DELETE FROM digital_program_links WHERE id = ?")->execute([$linkId]);

        // Get new count
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM digital_program_links WHERE program_id = ? AND link_type = ?");
        $stmtCount->execute([$progId, $linkType]);
        $newCount = (int)$stmtCount->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => ucfirst($linkType) . ' link removed.',
            'count' => $newCount
        ]);
        break;

    case 'delete_program':
        if (!$isHod) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only HOD or Administrator can delete programs.']);
            exit;
        }

        $progId = (int)($data['id'] ?? 0);
        if (!$progId) {
            echo json_encode(['success' => false, 'message' => 'Invalid program ID.']);
            exit;
        }

        $pdo->prepare("DELETE FROM digital_program_links WHERE program_id = ?")->execute([$progId]);
        $pdo->prepare("DELETE FROM digital_program_editors WHERE program_id = ?")->execute([$progId]);
        $pdo->prepare("DELETE FROM digital_fresh_programs WHERE id = ?")->execute([$progId]);

        echo json_encode([
            'success' => true,
            'message' => 'Program deleted successfully.'
        ]);
        break;

    case 'get_editors':
        // Get active employees belonging to Digital or managed units
        $sql = "
            SELECT DISTINCT e.id, e.name, e.designation, e.avatar, e.emp_code, d.name as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.is_active = 1
              AND (
                  LOWER(d.name) LIKE '%digital%'
                  OR e.id IN (
                      SELECT ed.employee_id 
                      FROM employee_departments ed 
                      JOIN departments d2 ON ed.department_id = d2.id 
                      WHERE LOWER(d2.name) LIKE '%digital%'
                  )
              )
            ORDER BY e.name ASC
        ";
        $editors = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // If no explicit digital dept found, fallback to all active employees in managed dept or all active
        if (empty($editors) && $isHod) {
            $editors = $pdo->query("SELECT id, name, designation, avatar, emp_code FROM employees WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'editors' => $editors
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid API action.']);
        break;
}
