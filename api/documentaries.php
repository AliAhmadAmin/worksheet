<?php
/**
 * Discover Pakistan UHD TV - Documentaries Management API
 * Collaborative Desk between Programming (Filing/Meta) & Digital (Social Video Distribution)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$currentUserId = $_SESSION['user_id'] ?? 0;

$stmtUser = $pdo->prepare("SELECT e.*, d.name as department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.id = ?");
$stmtUser->execute([$currentUserId]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: [];

$userRole = strtolower($currentUser['role'] ?? 'employee');
$deptName = strtolower($currentUser['department_name'] ?? '');
$isSuperAdmin = in_array($userRole, ['super_admin', 'admin', 'manager'], true);
$isHod = in_array($userRole, ['hod', 'deputy_hod'], true);
$isProgramming = (stripos($deptName, 'prog') !== false);
$isDigital = (stripos($deptName, 'digit') !== false);
$isHr = (stripos($deptName, 'hr') !== false || !empty($currentUser['can_manage_hr']));

$canAccess = $isSuperAdmin || $isHod || $isProgramming || $isDigital || $isHr;
if (!$canAccess && !empty($currentUserId)) {
    // Default allowed for logged in staff of DP TV
    $canAccess = true;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$data = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

switch ($action) {
    case 'list':
        $search = trim($_GET['search'] ?? '');
        $typeFilter = trim($_GET['type'] ?? 'all');
        $statusFilter = trim($_GET['status'] ?? 'all');
        $producerFilter = trim($_GET['producer'] ?? 'all');
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate = trim($_GET['end_date'] ?? '');
        $month = trim($_GET['month'] ?? '');

        $sql = "
            SELECT d.*, 
                   u1.name as filed_by_name,
                   u2.name as digital_updated_by_name
            FROM documentaries d
            LEFT JOIN employees u1 ON d.filed_by = u1.id
            LEFT JOIN employees u2 ON d.digital_updated_by = u2.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($search)) {
            $wild = '%' . $search . '%';
            $sql .= " AND (d.title LIKE ? OR d.topic_guest LIKE ? OR d.producer_name LIKE ? OR d.city LIKE ? OR d.sr_no LIKE ?)";
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
        }

        if ($typeFilter !== 'all' && in_array($typeFilter, ['Full', 'Half', 'Short'], true)) {
            $sql .= " AND d.doc_type = ?";
            $params[] = $typeFilter;
        }

        if ($statusFilter === 'pending_digital') {
            $sql .= " AND (d.youtube_url IS NULL OR d.youtube_url = '' OR d.facebook_url IS NULL OR d.facebook_url = '')";
        } elseif ($statusFilter === 'completed_digital') {
            $sql .= " AND ((d.youtube_url IS NOT NULL AND d.youtube_url != '') AND (d.facebook_url IS NOT NULL AND d.facebook_url != ''))";
        } elseif ($statusFilter === 'any_published') {
            $sql .= " AND ((d.youtube_url IS NOT NULL AND d.youtube_url != '') OR (d.facebook_url IS NOT NULL AND d.facebook_url != ''))";
        }

        if ($producerFilter !== 'all' && !empty($producerFilter)) {
            $sql .= " AND d.producer_name = ?";
            $params[] = $producerFilter;
        }

        if (!empty($startDate) && !empty($endDate)) {
            $sql .= " AND d.doc_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        } elseif (!empty($month)) {
            $sql .= " AND d.doc_date LIKE ?";
            $params[] = $month . '%';
        }

        // Order descending by doc_date, then id
        $sql .= " ORDER BY d.doc_date DESC, d.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $documentaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Compute overall and breakdown stats
        $statsStmt = $pdo->query("
            SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN doc_type = 'Full' THEN 1 ELSE 0 END) as full_count,
                SUM(CASE WHEN doc_type = 'Half' THEN 1 ELSE 0 END) as half_count,
                SUM(CASE WHEN doc_type = 'Short' THEN 1 ELSE 0 END) as short_count,
                SUM(CASE WHEN (youtube_url IS NULL OR youtube_url = '') OR (facebook_url IS NULL OR facebook_url = '') THEN 1 ELSE 0 END) as pending_digital_count,
                SUM(CASE WHEN (youtube_url IS NOT NULL AND youtube_url != '') AND (facebook_url IS NOT NULL AND facebook_url != '') THEN 1 ELSE 0 END) as complete_digital_count
            FROM documentaries
        ");
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_count' => 0,
            'full_count' => 0,
            'half_count' => 0,
            'short_count' => 0,
            'pending_digital_count' => 0,
            'complete_digital_count' => 0
        ];

        // Fetch distinct producers for filtering
        $producersStmt = $pdo->query("SELECT DISTINCT producer_name FROM documentaries WHERE producer_name IS NOT NULL AND producer_name != '' ORDER BY producer_name ASC");
        $producers = $producersStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        echo json_encode([
            'success' => true,
            'documentaries' => $documentaries,
            'stats' => $stats,
            'producers' => $producers,
            'total_filtered' => count($documentaries)
        ]);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM documentaries WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Documentary not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'documentary' => $doc]);
        break;

    case 'save':
        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $srNo = trim($data['sr_no'] ?? '');
        $title = trim($data['title'] ?? '');
        $topicGuest = trim($data['topic_guest'] ?? '');
        $docType = trim($data['doc_type'] ?? 'Full');
        if (!in_array($docType, ['Full', 'Half', 'Short'], true)) {
            $docType = 'Full';
        }
        $docDate = trim($data['doc_date'] ?? date('Y-m-d'));
        $docTime = trim($data['doc_time'] ?? '');
        $city = trim($data['city'] ?? 'Lahore');
        if (empty($city)) $city = 'Lahore';
        $producerId = !empty($data['producer_id']) ? (int)$data['producer_id'] : null;
        $producerName = trim($data['producer_name'] ?? '');
        $youtubeUrl = trim($data['youtube_url'] ?? '');
        $facebookUrl = trim($data['facebook_url'] ?? '');
        $notes = trim($data['notes'] ?? '');

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Documentary title / file path is required.']);
            exit;
        }

        // Auto-resolve producer name if producer_id provided
        if ($producerId && empty($producerName)) {
            $pStmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $pStmt->execute([$producerId]);
            $producerName = $pStmt->fetchColumn() ?: '';
        }

        if ($id) {
            // Update existing
            $stmtUp = $pdo->prepare("
                UPDATE documentaries 
                SET title = ?,
                    topic_guest = ?,
                    doc_type = ?,
                    doc_date = ?,
                    doc_time = ?,
                    city = ?,
                    producer_id = ?,
                    producer_name = ?,
                    youtube_url = ?,
                    facebook_url = ?,
                    notes = ?,
                    digital_updated_by = IF(? != '', ?, digital_updated_by)
                WHERE id = ?
            ");
            $stmtUp->execute([
                $title,
                $topicGuest ?: null,
                $docType,
                $docDate,
                $docTime ?: null,
                $city,
                $producerId ?: null,
                $producerName ?: null,
                $youtubeUrl ?: null,
                $facebookUrl ?: null,
                $notes ?: null,
                ($youtubeUrl || $facebookUrl) ? $currentUserId : '',
                $currentUserId,
                $id
            ]);

            echo json_encode(['success' => true, 'message' => 'Documentary record updated successfully.', 'id' => $id]);
        } else {
            // Auto-generate sequential Sr # (comes automatically)
            $maxSr = (int)$pdo->query("SELECT COALESCE(MAX(CAST(sr_no AS UNSIGNED)), 0) FROM documentaries")->fetchColumn();
            $srNo = (string)($maxSr + 1);

            $stmtIn = $pdo->prepare("
                INSERT INTO documentaries 
                    (sr_no, title, topic_guest, doc_type, doc_date, doc_time, city, producer_id, producer_name, youtube_url, facebook_url, filed_by, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtIn->execute([
                $srNo,
                $title,
                $topicGuest ?: null,
                $docType,
                $docDate,
                $docTime ?: null,
                $city,
                $producerId ?: null,
                $producerName ?: null,
                $youtubeUrl ?: null,
                $facebookUrl ?: null,
                $currentUserId ?: null,
                $notes ?: null
            ]);

            $newId = (int)$pdo->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Documentary successfully filed.', 'id' => $newId, 'sr_no' => $srNo]);
        }
        break;

    case 'update_links':
        // Lightweight endpoint specifically for Digital Team to paste links
        $id = (int)($data['id'] ?? 0);
        $youtubeUrl = trim($data['youtube_url'] ?? '');
        $facebookUrl = trim($data['facebook_url'] ?? '');

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Documentary ID is required.']);
            exit;
        }

        $stmtUp = $pdo->prepare("
            UPDATE documentaries 
            SET youtube_url = ?, 
                facebook_url = ?, 
                digital_updated_by = ? 
            WHERE id = ?
        ");
        $stmtUp->execute([
            $youtubeUrl ?: null,
            $facebookUrl ?: null,
            $currentUserId ?: null,
            $id
        ]);

        echo json_encode([
            'success' => true, 
            'message' => 'Social media links updated successfully.',
            'id' => $id,
            'youtube_url' => $youtubeUrl,
            'facebook_url' => $facebookUrl
        ]);
        break;

    case 'delete':
        $id = (int)($data['id'] ?? $_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Documentary ID is required.']);
            exit;
        }

        $stmtCheck = $pdo->prepare("SELECT filed_by FROM documentaries WHERE id = ?");
        $stmtCheck->execute([$id]);
        $filedBy = (int)$stmtCheck->fetchColumn();

        $canDelete = $isSuperAdmin || $isHod || ($filedBy === (int)$currentUserId);
        if (!$canDelete) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this record.']);
            exit;
        }

        $pdo->prepare("DELETE FROM documentaries WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Documentary record deleted successfully.']);
        break;

    case 'clear_all':
        $pdo->exec("TRUNCATE TABLE documentaries");
        echo json_encode(['success' => true, 'message' => 'All documentary records cleared successfully.']);
        break;

    case 'export_csv':
        $sql = "SELECT sr_no, title, topic_guest, doc_type, doc_date, doc_time, city, producer_name, youtube_url, facebook_url FROM documentaries ORDER BY doc_date DESC, id DESC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=documentaries_' . date('Y-m-d') . '.csv');

        $out = fopen('php://output', 'w');
        // Excel UTF-8 BOM
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Sr #', 'Title', 'Topic & Guest', 'Type', 'Date', 'Time', 'City', 'Producer', 'YouTube Status', 'Facebook Status']);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['sr_no'] ?: '',
                $r['title'] ?: '',
                $r['topic_guest'] ?: '',
                $r['doc_type'] ?: 'Full',
                $r['doc_date'] ?: '',
                $r['doc_time'] ?: '',
                $r['city'] ?: 'Lahore',
                $r['producer_name'] ?: '',
                $r['youtube_url'] ?: 'Pending',
                $r['facebook_url'] ?: 'Pending'
            ]);
        }
        fclose($out);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
