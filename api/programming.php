<?php
/**
 * Programming & Digital Content Handover API
 * Dispatching, Live Publishing Queue, Status Tracking, and Inter-Department Coordination
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

$currentUserId = $_SESSION['user_id'] ?? ($data['employee_id'] ?? 0);
$stmtUserCheck = $pdo->prepare("
    SELECT e.*, d.name as department_name, t.name as team_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id 
    LEFT JOIN teams t ON e.team_id = t.id 
    WHERE e.id = ?
");
$stmtUserCheck->execute([$currentUserId]);
$currentUserObj = $stmtUserCheck->fetch() ?: [];

$currentUserRole = strtolower($currentUserObj['role'] ?? ($_SESSION['role'] ?? 'employee'));
$userDeptName = strtolower($currentUserObj['department_name'] ?? '');

$isAdmin = in_array($currentUserRole, ['super_admin', 'admin', 'manager']);
$isDigital = (stripos($userDeptName, 'digital') !== false);
$isProg = (stripos($userDeptName, 'prog') !== false);
$canManageDigital = ($isAdmin || $isDigital);

// Restrict access: strictly Programming, Digital Media, and Admins
if (!$isAdmin && !$isDigital && !$isProg) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied: Programming Handover is restricted to Programming and Digital teams.']);
    exit;
}

// Release session lock on read actions to allow parallel requests without locking
if (in_array($action, ['list', 'get_meta'])) {
    session_write_close();
}

switch ($action) {
    case 'list':
        $date = $_GET['date'] ?? null;
        $month = $_GET['month'] ?? null;
        $channel = $_GET['channel'] ?? 'all';
        $status = $_GET['status'] ?? 'all';
        $search = trim($_GET['search'] ?? '');

        $whereClauses = ["1=1"];
        $params = [];

        if (!empty($date)) {
            $whereClauses[] = "pd.dispatch_date = ?";
            $params[] = $date;
        } elseif (!empty($month)) {
            $whereClauses[] = "DATE_FORMAT(pd.dispatch_date, '%Y-%m') = ?";
            $params[] = $month;
        }

        if ($channel !== 'all' && !empty($channel)) {
            $whereClauses[] = "pd.channel = ?";
            $params[] = $channel;
        }

        if ($status !== 'all' && !empty($status)) {
            if ($status === 'pending') {
                $whereClauses[] = "((pd.dp_status = '' OR pd.dp_status IS NULL OR pd.dp_status = 'Pending' OR pd.dp_status = 'Move to PT') AND (pd.pt_status = '' OR pd.pt_status IS NULL OR pd.pt_status = 'Pending'))";
            } elseif ($status === 'published') {
                $whereClauses[] = "(pd.dp_status = 'Published' OR pd.pt_status = 'Published' OR pd.dp_status = 'Published on PT')";
            } elseif ($status === 'issues') {
                $whereClauses[] = "(pd.dp_status IN ('Violation', 'Correction', 'Expire', 'Rejected') OR pd.pt_status IN ('Violation', 'Correction', 'Expire', 'Rejected'))";
            } else {
                $whereClauses[] = "(pd.dp_status = ? OR pd.pt_status = ?)";
                $params[] = $status;
                $params[] = $status;
            }
        }

        if (!empty($search)) {
            $whereClauses[] = "(pd.title LIKE ? OR pd.path_whatsapp LIKE ? OR pd.sender_name LIKE ? OR pd.publisher_name LIKE ? OR pd.remarks LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $stmt = $pdo->prepare("
            SELECT pd.*,
                   se.avatar as sender_avatar,
                   pe.avatar as publisher_avatar
            FROM programming_dispatches pd
            LEFT JOIN employees se ON pd.sender_id = se.id
            LEFT JOIN employees pe ON pd.publisher_id = pe.id
            WHERE {$whereSql}
            ORDER BY pd.dispatch_date DESC, pd.id DESC
        ");
        $stmt->execute($params);
        $dispatches = $stmt->fetchAll();

        // Calculate Overview Summary Stats
        $statsStmt = $pdo->query("
            SELECT 
                COUNT(*) as total_dispatches,
                COALESCE(SUM(CASE 
                    WHEN (dp_status = 'Published' OR pt_status = 'Published') 
                     AND ((channel != 'Pakistan Today' AND (moved_to_pt = 0 OR moved_to_pt IS NULL)) OR channel = 'All Channels' OR channel = 'Both') 
                    THEN 1 ELSE 0 END), 0) as dp_published,
                COALESCE(SUM(CASE 
                    WHEN (pt_status = 'Published' OR dp_status = 'Published on PT' OR (dp_status = 'Published' AND (channel = 'Pakistan Today' OR moved_to_pt = 1))) 
                    THEN 1 ELSE 0 END), 0) as pt_published,
                COALESCE(SUM(CASE 
                    WHEN moved_to_pt = 1 OR dp_status = 'Move to PT' OR pt_status = 'Move to PT' 
                    THEN 1 ELSE 0 END), 0) as moved_to_pt_count,
                COALESCE(SUM(CASE WHEN dp_status IN ('Correction', 'Violation') OR pt_status IN ('Correction', 'Violation') THEN 1 ELSE 0 END), 0) as total_corrections,
                COALESCE(SUM(CASE 
                    WHEN (dp_status NOT IN ('Published', 'Published on PT', 'Expire', 'Rejected', 'N/A') AND pt_status NOT IN ('Published', 'Expire', 'Rejected', 'N/A'))
                    THEN 1 ELSE 0 END), 0) as pending_queue
            FROM programming_dispatches
        ");
        $stats = $statsStmt->fetch() ?: [
            'total_dispatches' => 0,
            'dp_published' => 0,
            'pt_published' => 0,
            'moved_to_pt_count' => 0,
            'total_corrections' => 0,
            'pending_queue' => 0
        ];

        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'dispatches' => $dispatches,
            'current_user' => [
                'id' => $currentUserObj['id'] ?? 0,
                'name' => $currentUserObj['name'] ?? 'Staff',
                'role' => $currentUserRole,
                'department' => $currentUserObj['department_name'] ?? '',
                'can_manage_digital' => $canManageDigital
            ]
        ]);
        break;

    case 'create':
        $title = trim($data['title'] ?? '');
        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Content title is required.']);
            exit;
        }

        $dispatchDate = !empty($data['dispatch_date']) ? $data['dispatch_date'] : date('Y-m-d');
        $pathWhatsapp = trim($data['path_whatsapp'] ?? '');
        $channel = trim($data['channel'] ?? 'Discover Pakistan');
        
        $isElevated = ($isAdmin || $currentUserRole === 'hod' || stripos($currentUserObj['designation'] ?? '', 'Director') !== false || stripos($currentUserObj['designation'] ?? '', 'HOD') !== false);
        
        if (!$isElevated) {
            $senderId = $currentUserId ?: null;
            $senderName = $currentUserObj['name'] ?? 'Programming Member';
        } else {
            $senderId = !empty($data['sender_id']) ? (int)$data['sender_id'] : ($currentUserId ?: null);
            $senderName = trim($data['sender_name'] ?? '');
            if (empty($senderName) && $senderId) {
                $stmtS = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
                $stmtS->execute([$senderId]);
                $senderName = $stmtS->fetchColumn() ?: ($currentUserObj['name'] ?? 'Programming Member');
            }
        }

        $dispatchTime = !empty($data['dispatch_time']) ? trim($data['dispatch_time']) : date('h:i:s A');
        $link = trim($data['link'] ?? '');
        $ptLink = trim($data['pt_link'] ?? '');

        // Channel-aware initial status assignment
        $initDpStatus = '';
        $initPtStatus = '';
        if ($channel === 'Discover Pakistan') {
            $initDpStatus = '';
            $initPtStatus = 'N/A';
        } elseif ($channel === 'Pakistan Today') {
            $initDpStatus = 'N/A';
            $initPtStatus = '';
        }

        $stmt = $pdo->prepare("
            INSERT INTO programming_dispatches 
            (dispatch_date, title, path_whatsapp, channel, sender_id, sender_name, dispatch_time, link, pt_link, dp_status, pt_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$dispatchDate, $title, $pathWhatsapp, $channel, $senderId, $senderName, $dispatchTime, $link, $ptLink, $initDpStatus, $initPtStatus]);
        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Content dispatched to Digital Department successfully.',
            'id' => $newId
        ]);
        break;

    case 'update_status':
        if (!$canManageDigital) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Access Denied: Programming department staff cannot change publishing statuses.'
            ]);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dispatch ID is required.']);
            exit;
        }

        $stmtGet = $pdo->prepare("SELECT * FROM programming_dispatches WHERE id = ?");
        $stmtGet->execute([$id]);
        $dispatch = $stmtGet->fetch();

        if (!$dispatch) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Dispatch record not found.']);
            exit;
        }

        $nowFormatted = date('h:i:s A');
        $movedToPt = (int)($dispatch['moved_to_pt'] ?? 0);
        if (isset($data['moved_to_pt'])) {
            $movedToPt = (int)$data['moved_to_pt'];
        }

        $dpStatus = isset($data['dp_status']) ? trim($data['dp_status']) : $dispatch['dp_status'];
        $dpTime = isset($data['dp_time']) ? trim($data['dp_time']) : $dispatch['dp_time'];
        $ptStatus = isset($data['pt_status']) ? trim($data['pt_status']) : $dispatch['pt_status'];
        $ptTime = isset($data['pt_time']) ? trim($data['pt_time']) : $dispatch['pt_time'];
        $publisherId = !empty($data['publisher_id']) ? (int)$data['publisher_id'] : ($dispatch['publisher_id'] ?: ($currentUserId ?: null));
        $publisherName = isset($data['publisher_name']) ? trim($data['publisher_name']) : $dispatch['publisher_name'];
        $remarks = isset($data['remarks']) ? trim($data['remarks']) : $dispatch['remarks'];
        $rating = isset($data['rating']) ? trim($data['rating']) : $dispatch['rating'];
        $link = isset($data['link']) ? trim($data['link']) : $dispatch['link'];
        $ptLink = isset($data['pt_link']) ? trim($data['pt_link']) : ($dispatch['pt_link'] ?? '');

        // If unified 'status' parameter is passed from the single status dropdown
        if (isset($data['status'])) {
            $unifiedStatus = trim($data['status']);
            if ($unifiedStatus === 'Move to PT') {
                $movedToPt = 1;
                $dpStatus = 'Move to PT';
                $ptStatus = 'Move to PT';
                if (empty($dpTime)) $dpTime = $nowFormatted;
            } elseif ($unifiedStatus === 'Published') {
                if ($dispatch['channel'] === 'Pakistan Today' || $movedToPt === 1) {
                    $ptStatus = 'Published';
                    $ptTime = $nowFormatted;
                    $dpStatus = 'Published on PT';
                    $dpTime = $nowFormatted;
                } elseif ($dispatch['channel'] === 'All Channels' || $dispatch['channel'] === 'Both') {
                    $dpStatus = 'Published';
                    $dpTime = $nowFormatted;
                    $ptStatus = 'Published';
                    $ptTime = $nowFormatted;
                } else {
                    $dpStatus = 'Published';
                    $dpTime = $nowFormatted;
                    $ptStatus = 'N/A';
                }
            } else {
                if ($dispatch['channel'] === 'Pakistan Today' || $movedToPt === 1) {
                    $ptStatus = $unifiedStatus;
                    if ($unifiedStatus !== 'Pending' && empty($ptTime)) $ptTime = $nowFormatted;
                } else {
                    $dpStatus = $unifiedStatus;
                    if ($unifiedStatus !== 'Pending' && empty($dpTime)) $dpTime = $nowFormatted;
                }
            }
        } else {
            // Auto-sync status: When published on PT, automatically mark DP status as 'Published on PT'
            if ($ptStatus === 'Published' || $ptStatus === 'Published on PT') {
                if (empty($dpStatus) || $dpStatus === 'Pending' || $dpStatus === 'Move to PT' || $dpStatus === 'N/A') {
                    $dpStatus = 'Published on PT';
                    if (empty($dpTime)) $dpTime = $nowFormatted;
                }
            } elseif ($dpStatus === 'Published on PT') {
                if (empty($ptStatus) || $ptStatus === 'Pending' || $ptStatus === 'N/A') {
                    $ptStatus = 'Published';
                    if (empty($ptTime)) $ptTime = $nowFormatted;
                }
            } elseif ($dpStatus === 'Published') {
                if ($dispatch['channel'] === 'Discover Pakistan' && (empty($ptStatus) || $ptStatus === 'Pending')) {
                    $ptStatus = 'N/A';
                }
            }
            if (!empty($dpStatus) && $dpStatus !== $dispatch['dp_status'] && empty($dpTime)) {
                $dpTime = $nowFormatted;
            }
            if (!empty($ptStatus) && $ptStatus !== $dispatch['pt_status'] && empty($ptTime)) {
                $ptTime = $nowFormatted;
            }
        }

        if (empty($publisherName) && $publisherId) {
            $stmtP = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmtP->execute([$publisherId]);
            $publisherName = $stmtP->fetchColumn() ?: ($currentUserObj['name'] ?? 'Digital Member');
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE programming_dispatches
            SET dp_status = ?, dp_time = ?, pt_status = ?, pt_time = ?, moved_to_pt = ?,
                publisher_id = ?, publisher_name = ?, remarks = ?, rating = ?, link = ?, pt_link = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmtUpdate->execute([$dpStatus, $dpTime, $ptStatus, $ptTime, $movedToPt, $publisherId, $publisherName, $remarks, $rating, $link, $ptLink, $id]);

        // Recalculate stats for live response
        $statsStmt = $pdo->query("
            SELECT 
                COUNT(*) as total_dispatches,
                COALESCE(SUM(CASE 
                    WHEN (dp_status = 'Published' OR pt_status = 'Published') 
                     AND ((channel != 'Pakistan Today' AND (moved_to_pt = 0 OR moved_to_pt IS NULL)) OR channel = 'All Channels' OR channel = 'Both') 
                    THEN 1 ELSE 0 END), 0) as dp_published,
                COALESCE(SUM(CASE 
                    WHEN (pt_status = 'Published' OR dp_status = 'Published on PT' OR (dp_status = 'Published' AND (channel = 'Pakistan Today' OR moved_to_pt = 1))) 
                    THEN 1 ELSE 0 END), 0) as pt_published,
                COALESCE(SUM(CASE 
                    WHEN moved_to_pt = 1 OR dp_status = 'Move to PT' OR pt_status = 'Move to PT' 
                    THEN 1 ELSE 0 END), 0) as moved_to_pt_count,
                COALESCE(SUM(CASE WHEN dp_status IN ('Correction', 'Violation') OR pt_status IN ('Correction', 'Violation') THEN 1 ELSE 0 END), 0) as total_corrections,
                COALESCE(SUM(CASE 
                    WHEN (dp_status NOT IN ('Published', 'Published on PT', 'Expire', 'Rejected', 'N/A') AND pt_status NOT IN ('Published', 'Expire', 'Rejected', 'N/A'))
                    THEN 1 ELSE 0 END), 0) as pending_queue
            FROM programming_dispatches
        ");
        $liveStats = $statsStmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => 'Digital publishing status updated successfully.',
            'dp_status' => $dpStatus,
            'dp_time' => $dpTime,
            'pt_status' => $ptStatus,
            'pt_time' => $ptTime,
            'moved_to_pt' => $movedToPt,
            'publisher_name' => $publisherName,
            'link' => $link,
            'pt_link' => $ptLink,
            'stats' => $liveStats
        ]);
        break;

    case 'update_link':
        $id = (int)($data['id'] ?? 0);
        $field = ($data['field'] ?? 'link') === 'pt_link' ? 'pt_link' : 'link';
        $val = trim($data['link'] ?? ($data['val'] ?? ''));
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dispatch ID is required.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE programming_dispatches SET {$field} = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$val, $id]);
        echo json_encode(['success' => true, 'message' => 'Story link updated successfully.', 'field' => $field, 'val' => $val]);
        break;

    case 'claim_story':
        if (!$canManageDigital) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access Denied: Only Digital department staff or Admins can claim stories.']);
            exit;
        }
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dispatch ID is required.']);
            exit;
        }
        $pubId = $currentUserObj['id'] ?? null;
        $pubName = $currentUserObj['name'] ?? 'Digital Member';
        $stmtClaim = $pdo->prepare("
            UPDATE programming_dispatches
            SET publisher_id = ?,
                publisher_name = ?,
                dp_status = CASE 
                    WHEN dp_status IS NULL OR dp_status = '' OR dp_status = 'Pending' 
                    THEN 'In Progress' 
                    ELSE dp_status 
                END,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmtClaim->execute([$pubId, $pubName, $id]);
        echo json_encode([
            'success' => true,
            'message' => 'Story claimed by ' . $pubName . '!',
            'publisher_id' => $pubId,
            'publisher_name' => $pubName
        ]);
        break;

    case 'update_dispatch':
        if (!$canManageDigital) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Access Denied: Programming employees cannot edit dispatch records once sent.'
            ]);
            exit;
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dispatch ID is required.']);
            exit;
        }

        $title = trim($data['title'] ?? '');
        $pathWhatsapp = trim($data['path_whatsapp'] ?? '');
        $channel = trim($data['channel'] ?? 'Discover Pakistan');
        $dispatchDate = !empty($data['dispatch_date']) ? $data['dispatch_date'] : date('Y-m-d');
        $senderId = !empty($data['sender_id']) ? (int)$data['sender_id'] : null;
        $senderName = trim($data['sender_name'] ?? '');
        $dispatchTime = trim($data['dispatch_time'] ?? '');
        $link = trim($data['link'] ?? '');
        $ptLink = trim($data['pt_link'] ?? '');
        $dpStatus = trim($data['dp_status'] ?? '');
        $dpTime = trim($data['dp_time'] ?? '');
        $ptStatus = trim($data['pt_status'] ?? '');
        $ptTime = trim($data['pt_time'] ?? '');
        $publisherId = !empty($data['publisher_id']) ? (int)$data['publisher_id'] : null;
        $remarks = trim($data['remarks'] ?? '');
        $rating = trim($data['rating'] ?? '');
        $movedToPt = isset($data['moved_to_pt']) ? (int)$data['moved_to_pt'] : 0;

        if (isset($data['status'])) {
            $unifiedStatus = trim($data['status']);
            if ($unifiedStatus === 'Move to PT') {
                $movedToPt = 1;
                $dpStatus = 'Move to PT';
                $ptStatus = 'Move to PT';
            } elseif ($unifiedStatus === 'Published') {
                if ($channel === 'Pakistan Today' || $movedToPt === 1) {
                    $ptStatus = 'Published';
                    $dpStatus = 'Published on PT';
                } elseif ($channel === 'All Channels' || $channel === 'Both') {
                    $dpStatus = 'Published';
                    $ptStatus = 'Published';
                } else {
                    $dpStatus = 'Published';
                    $ptStatus = 'N/A';
                }
            } else {
                if ($channel === 'Pakistan Today' || $movedToPt === 1) {
                    $ptStatus = $unifiedStatus;
                } else {
                    $dpStatus = $unifiedStatus;
                }
            }
        }

        $stmt = $pdo->prepare("
            UPDATE programming_dispatches
            SET title = ?, path_whatsapp = ?, channel = ?, dispatch_date = ?,
                sender_id = ?, sender_name = ?, dispatch_time = ?, link = ?, pt_link = ?,
                dp_status = ?, dp_time = ?, pt_status = ?, pt_time = ?, moved_to_pt = ?,
                publisher_id = ?, remarks = ?, rating = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $pathWhatsapp, $channel, $dispatchDate,
            $senderId, $senderName, $dispatchTime, $link, $ptLink,
            $dpStatus, $dpTime, $ptStatus, $ptTime, $movedToPt,
            $publisherId, $remarks, $rating, $id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Dispatch and publishing entry updated successfully.'
        ]);
        break;

    case 'delete':
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dispatch ID is required.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM programming_dispatches WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Dispatch record deleted successfully.'
        ]);
        break;

    case 'get_meta':
        // Fetch Programming staff (Senders)
        $stmtSenders = $pdo->query("
            SELECT e.id, e.name, e.designation, d.name as department_name 
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.is_active = 1 
              AND (d.name LIKE '%Programming%' OR d.name LIKE '%Archive%' OR e.role IN ('admin', 'super_admin'))
            ORDER BY e.name ASC
        ");
        $senders = $stmtSenders->fetchAll();

        // Fallback if department list is empty
        if (empty($senders)) {
            $senders = $pdo->query("SELECT id, name, designation FROM employees WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        }

        // Fetch Digital staff (Publishers)
        $stmtPublishers = $pdo->query("
            SELECT e.id, e.name, e.designation, d.name as department_name 
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.is_active = 1 
              AND (d.name LIKE '%Digital%' OR e.role IN ('admin', 'super_admin'))
            ORDER BY e.name ASC
        ");
        $publishers = $stmtPublishers->fetchAll();

        if (empty($publishers)) {
            $publishers = $pdo->query("SELECT id, name, designation FROM employees WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
        }

        $dpStatuses = [
            ['value' => 'In Progress', 'label' => '⚡ In Progress', 'bg' => '#d97706', 'color' => '#ffffff'],
            ['value' => 'Published', 'label' => 'Published', 'bg' => '#16a34a', 'color' => '#ffffff'],
            ['value' => 'Violation', 'label' => 'Violation', 'bg' => '#dc2626', 'color' => '#ffffff'],
            ['value' => 'Correction', 'label' => 'Correction', 'bg' => '#2563eb', 'color' => '#ffffff'],
            ['value' => 'Move to PT', 'label' => 'Move to PT', 'bg' => '#7f1d1d', 'color' => '#ffffff'],
            ['value' => 'Published on PT', 'label' => 'Published on PT', 'bg' => '#15803d', 'color' => '#ffffff'],
            ['value' => 'Expire', 'label' => 'Expire', 'bg' => '#b91c1c', 'color' => '#ffffff'],
            ['value' => 'Rejected', 'label' => 'Rejected', 'bg' => '#78350f', 'color' => '#ffffff']
        ];

        $ptStatuses = [
            ['value' => 'In Progress', 'label' => '⚡ In Progress', 'bg' => '#d97706', 'color' => '#ffffff'],
            ['value' => 'Published', 'label' => 'Published', 'bg' => '#16a34a', 'color' => '#ffffff'],
            ['value' => 'Violation', 'label' => 'Violation', 'bg' => '#dc2626', 'color' => '#ffffff'],
            ['value' => 'Correction', 'label' => 'Correction', 'bg' => '#2563eb', 'color' => '#ffffff'],
            ['value' => 'Expire', 'label' => 'Expire', 'bg' => '#b91c1c', 'color' => '#ffffff'],
            ['value' => 'Rejected', 'label' => 'Rejected', 'bg' => '#78350f', 'color' => '#ffffff']
        ];

        $channels = ['Discover Pakistan', 'Pakistan Today', 'Discover Islam', 'All Channels'];

        echo json_encode([
            'success' => true,
            'senders' => $senders,
            'publishers' => $publishers,
            'dp_statuses' => $dpStatuses,
            'pt_statuses' => $ptStatuses,
            'channels' => $channels
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
