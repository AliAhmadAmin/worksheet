<?php
/**
 * Standalone Printable Hourly Sheet
 * Discover Pakistan UHD TV Official Production & Hourly Work Log
 * Calibrated specifically for exact 1-Page A4 Printing & PDF export
 */
require_once __DIR__ . '/config/database.php';

$pdo = getDbConnection();

$empId = (int)($_GET['employee_id'] ?? 1);
$date = $_GET['date'] ?? date('Y-m-d');

// Fetch employee
$stmt = $pdo->prepare("
    SELECT e.*, d.name as department_name, t.name as team_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id 
    LEFT JOIN teams t ON e.team_id = t.id 
    WHERE e.id = ?
");
$stmt->execute([$empId]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found.");
}

// Fetch sheet
$sheetStmt = $pdo->prepare("SELECT * FROM daily_sheets WHERE employee_id = ? AND sheet_date = ?");
$sheetStmt->execute([$empId, $date]);
$sheet = $sheetStmt->fetch();

$entries = [];
if ($sheet) {
    $entriesStmt = $pdo->prepare("SELECT * FROM sheet_entries WHERE sheet_id = ? ORDER BY id ASC");
    $entriesStmt->execute([$sheet['id']]);
    $entries = $entriesStmt->fetchAll();
}

// Fetch dynamic columns and HOD verification policy for this department
$empDeptId = (int)($employee['department_id'] ?? 2);
if ($empDeptId <= 0) $empDeptId = 2;

$deptStmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
$deptStmt->execute([$empDeptId]);
$department = $deptStmt->fetch() ?: [];

$blockPrintBeforeHodCheck = (int)($department['block_print_before_hod_check'] ?? 0);
$isHodChecked = !empty($sheet['is_hod_checked']) ? 1 : 0;

// Security check if printing is restricted for employees until checked
$sessionUserId = $_SESSION['user_id'] ?? 0;
$sessionUserRole = strtolower($_SESSION['role'] ?? 'employee');
$isElevatedUser = in_array($sessionUserRole, ['super_admin', 'admin', 'hod']);

if ($blockPrintBeforeHodCheck && !$isHodChecked && !$isElevatedUser && $sessionUserId == $empId) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Print Restricted - HOD Check Required</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    </head>
    <body style="font-family: 'Plus Jakarta Sans', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; background: #f8fafc; margin: 0;">
        <div style="background: white; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 36px 32px; max-width: 480px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.06);">
            <div style="width: 58px; height: 58px; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 16px;">🔒</div>
            <h2 style="font-size: 19px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0;">HOD Verification Required</h2>
            <p style="font-size: 13.5px; color: #64748b; line-height: 1.55; margin: 0 0 22px 0;">
                Your department requires worksheets to be inspected and marked as <strong>"Checked"</strong> by your Head of Department on the system before physical printing is enabled.
            </p>
            <button onclick="window.close()" style="background: #2563eb; color: white; border: none; padding: 9px 24px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer;">Close Window</button>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Fetch HOD checker details
$hodCheckerName = null;
if ($isHodChecked && !empty($sheet['hod_checked_by'])) {
    $hodStmt = $pdo->prepare("SELECT name, designation FROM employees WHERE id = ?");
    $hodStmt->execute([(int)$sheet['hod_checked_by']]);
    $hodData = $hodStmt->fetch();
    if ($hodData) {
        $hodCheckerName = $hodData['name'];
    }
}
$hodCheckedFormatted = !empty($sheet['hod_checked_at']) ? date('d-M-Y h:i A', strtotime($sheet['hod_checked_at'])) : date('d-M-Y');

$stmtCols = $pdo->prepare("
    SELECT column_key, column_label, column_type 
    FROM department_worksheet_columns 
    WHERE department_id = ? AND is_visible = 1 
    ORDER BY sort_order ASC, id ASC
");
$stmtCols->execute([$empDeptId]);
$printCols = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

if (empty($printCols)) {
    $printCols = [
        ['column_key' => 'time_slot', 'column_label' => 'Time Slot', 'column_type' => 'time'],
        ['column_key' => 'content_type', 'column_label' => 'Content Type', 'column_type' => 'select'],
        ['column_key' => 'department', 'column_label' => 'Department', 'column_type' => 'select'],
        ['column_key' => 'link', 'column_label' => 'Link / URL', 'column_type' => 'link'],
        ['column_key' => 'title', 'column_label' => 'Work Description / Title', 'column_type' => 'text']
    ];
}

// Format date header
$sheetDateTime = strtotime($date);
$formattedFullDate = date('l, d F Y', $sheetDateTime);
$formattedShortDate = date('d-M-Y', $sheetDateTime);

$checkInTime = $sheet['check_in_time'] ?? '--:--';
$checkOutTime = $sheet['check_out_time'] ?? '--:--';
$totalDutyHours = $sheet['total_duty_hours'] ?? '0:00:00';
$remarks = $sheet['remarks'] ?? '';
$workSummary = $sheet['work_summary'] ?? '';

// Calibrated row count to guarantee 1 single page on standard A4
$entryCount = count($entries);
$emptyRowsNeeded = $entryCount < 8 ? (8 - $entryCount) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hourly Worksheet - <?= htmlspecialchars($employee['name']) ?> (<?= htmlspecialchars($formattedShortDate) ?>)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 10mm 12mm;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            background-color: #ffffff;
            color: #0f172a;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            line-height: 1.25;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            margin: 0;
            padding: 0;
        }

        /* Printable Container */
        .sheet-page {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
            padding: 4mm 0;
            background: #ffffff;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Corporate Header Banner */
        .doc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 8px;
            border-bottom: 2px solid #0f2b48;
            margin-bottom: 8px;
            gap: 12px;
        }

        .doc-header-left {
            flex-shrink: 0;
        }

        .brand-logo {
            width: 140px;
            max-width: 140px;
            height: auto;
            display: block;
            object-fit: contain;
        }

        .doc-header-center {
            text-align: center;
            flex-grow: 1;
        }

        .doc-main-title {
            font-size: 13.5pt;
            font-weight: 800;
            color: #0f2b48;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
            line-height: 1.1;
        }

        .doc-sub-title {
            font-size: 6.8pt;
            font-weight: 700;
            color: #475569;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        .doc-date-badge {
            display: inline-block;
            margin-top: 2px;
            background: #f1f5f9;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 7.5pt;
            font-weight: 700;
            color: #0284c7;
            border: 1px solid #cbd5e1;
        }

        .doc-header-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
            flex-shrink: 0;
        }

        .doc-meta-badge {
            font-size: 7pt;
            color: #64748b;
            text-align: right;
        }

        .meta-badge-label {
            font-weight: 700;
            margin-right: 3px;
        }

        .meta-badge-val {
            font-family: monospace;
            font-weight: 700;
            color: #0f172a;
        }

        .doc-status-badge {
            font-size: 7pt;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            letter-spacing: 0.2px;
            text-align: center;
        }

        .status-locked {
            background: #fef2f2 !important;
            color: #b91c1c !important;
            border: 1px solid #fca5a5;
        }

        .status-active {
            background: #ecfdf5 !important;
            color: #047857 !important;
            border: 1px solid #a7f3d0;
        }

        /* Info Matrix Grid */
        .info-matrix {
            display: grid;
            grid-template-columns: 1.4fr 1.1fr 1.3fr 0.9fr 1.3fr 1fr;
            border: 1px solid #94a3b8;
            border-radius: 3px;
            margin-bottom: 8px;
            background: #fafafa;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .info-cell {
            padding: 4px 6px;
            border-right: 1px solid #cbd5e1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .info-cell:last-child {
            border-right: none;
        }

        .highlight-cell {
            background: #eff6ff !important;
        }

        .info-lbl {
            font-size: 6pt;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.3px;
            margin-bottom: 1px;
        }

        .info-val {
            font-size: 7.8pt;
            font-weight: 600;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .strong-name {
            font-weight: 800;
            color: #0f172a;
        }

        .duty-val {
            font-weight: 800;
            color: #0284c7;
            font-size: 8.8pt;
        }

        /* Table Design */
        .table-container {
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .hourly-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.8pt;
            border: 1px solid #334155;
        }

        .hourly-table th {
            background-color: #0f2b48 !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 7.2pt;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 4px 6px;
            text-align: left;
            border: 1px solid #0f2b48;
        }

        .hourly-table td {
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            height: 20px;
            vertical-align: middle;
            line-height: 1.2;
        }

        .hourly-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .cell-time {
            font-weight: 700;
            color: #0369a1;
            white-space: nowrap;
            font-size: 7.5pt;
        }

        .cell-type {
            font-weight: 600;
            color: #0f172a;
        }

        .cell-dept {
            color: #475569;
        }

        .cell-link {
            color: #2563eb;
            word-break: break-all;
            font-size: 7pt;
            font-family: monospace;
        }

        .cell-title {
            color: #0f172a;
        }

        .empty-row td {
            height: 18px;
            border: 1px solid #e2e8f0;
        }

        /* Summary Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 8px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .summary-box {
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            background: #ffffff;
            overflow: hidden;
        }

        .summary-title {
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            padding: 3px 6px;
            font-size: 7pt;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .summary-content {
            padding: 4px 6px;
            font-size: 7.5pt;
            color: #1e293b;
            min-height: 30px;
            line-height: 1.25;
        }

        .placeholder-text {
            color: #94a3b8;
            font-style: italic;
        }

        /* Signatures Footer */
        .doc-footer {
            margin-top: 4px;
            padding-top: 6px;
            page-break-inside: avoid;
        }

        .doc-footer.manual-mode {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .doc-footer.verified-mode {
            display: flex;
            justify-content: flex-end;
            padding-right: 18px;
            margin-top: 6px;
        }

        .sig-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .sig-line-box {
            width: 80%;
            border-bottom: 1.2px solid #0f172a;
            margin-bottom: 4px;
            height: 16px;
        }

        .sig-title {
            font-size: 7.5pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .sig-sub {
            font-size: 6.8pt;
            color: #64748b;
        }

        /* Professional Official Approval Stamp */
        .official-approval-stamp {
            display: inline-block;
            border: 2.2px solid #047857;
            border-radius: 6px;
            padding: 3px;
            background: #f0fdf4;
            box-shadow: 0 0 0 1px #047857;
            transform: rotate(-1.5deg);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            page-break-inside: avoid;
        }

        .stamp-inner {
            border: 1.2px dashed #047857;
            border-radius: 4px;
            padding: 4px 14px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5px;
        }

        .stamp-top-ribbon {
            font-size: 5.8pt;
            font-weight: 800;
            color: #065f46;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stamp-star {
            color: #059669;
            font-size: 6pt;
        }

        .stamp-main-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 1px 0;
        }

        .stamp-check {
            font-size: 11pt;
            line-height: 1;
            font-weight: 900;
            color: #047857;
        }

        .stamp-title {
            font-size: 9.5pt;
            font-weight: 900;
            color: #047857;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            font-family: 'Arial Black', Impact, 'Segoe UI Black', sans-serif;
            border-top: 1.5px solid #047857;
            border-bottom: 1.5px solid #047857;
            padding: 1px 6px;
        }

        .stamp-meta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 6.2pt;
            color: #064e3b;
            margin-top: 2px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .stamp-meta strong {
            color: #047857;
        }

        .stamp-dot {
            color: #059669;
        }

        .stamp-bottom-seal {
            font-size: 5pt;
            color: #059669;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            font-weight: 800;
            margin-top: 1px;
        }

        @media print {
            html, body {
                width: 100% !important;
                height: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .sheet-page {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="sheet-page">
        <!-- Corporate Header Banner with Logo -->
        <header class="doc-header">
            <div class="doc-header-left">
                <img src="assets/img/logo.svg" alt="Discover Pakistan UHD TV Logo" width="140" height="44" class="brand-logo" style="width: 140px; max-width: 140px; height: auto; display: block;">
            </div>

            <div class="doc-header-center">
                <h1 class="doc-main-title">DAILY HOURLY WORK SHEET</h1>
                <p class="doc-sub-title">DISCOVER PAKISTAN UHD TV • DIGITAL OPERATIONS & PRODUCTION</p>
                <div class="doc-date-badge"><?= htmlspecialchars($formattedFullDate) ?></div>
            </div>

            <div class="doc-header-right">
                <div class="doc-meta-badge">
                    <span class="meta-badge-label">REF DOC #</span>
                    <span class="meta-badge-val">DP-WS-<?= date('Ymd', $sheetDateTime) ?>-<?= $employee['id'] ?></span>
                </div>
                <div class="doc-status-badge <?= ($sheet && $sheet['is_locked']) ? 'status-locked' : 'status-active' ?>">
                    <?= ($sheet && $sheet['is_locked']) ? '🔒 LOCKED & VERIFIED' : '🟢 ACTIVE SHEET' ?>
                </div>
            </div>
        </header>

        <!-- Employee & Shift Information Matrix -->
        <section class="info-matrix">
            <div class="info-cell">
                <span class="info-lbl">Employee Name</span>
                <span class="info-val strong-name"><?= htmlspecialchars($employee['name']) ?></span>
            </div>
            <div class="info-cell">
                <span class="info-lbl">Designation</span>
                <span class="info-val"><?= htmlspecialchars($employee['designation'] ?: 'Staff') ?></span>
            </div>
            <div class="info-cell">
                <span class="info-lbl">Department / Team</span>
                <span class="info-val"><?= htmlspecialchars($employee['department_name'] ?: 'Digital') ?> <?= $employee['team_name'] ? '• ' . htmlspecialchars($employee['team_name']) : '' ?></span>
            </div>
            <div class="info-cell">
                <span class="info-lbl">Date</span>
                <span class="info-val"><?= htmlspecialchars($formattedShortDate) ?></span>
            </div>
            <div class="info-cell">
                <span class="info-lbl">Shift Timings</span>
                <span class="info-val">In: <strong><?= htmlspecialchars($checkInTime) ?></strong> &nbsp;|&nbsp; Out: <strong><?= htmlspecialchars($checkOutTime) ?></strong></span>
            </div>
            <div class="info-cell highlight-cell">
                <span class="info-lbl">Total Duty Hours</span>
                <span class="info-val duty-val"><?= htmlspecialchars($totalDutyHours) ?></span>
            </div>
        </section>

        <!-- Hourly Work Log Table -->
        <main class="table-container">
            <table class="hourly-table">
                <thead>
                    <tr>
                        <?php foreach ($printCols as $col): ?>
                            <th><?= htmlspecialchars($col['column_label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($entries)): ?>
                        <?php foreach ($entries as $row): 
                            $cData = [];
                            if (!empty($row['custom_data'])) {
                                $cData = is_string($row['custom_data']) ? json_decode($row['custom_data'], true) : $row['custom_data'];
                                if (!is_array($cData)) $cData = [];
                            }
                        ?>
                            <tr>
                                <?php foreach ($printCols as $col): 
                                    $colKey = $col['column_key'];
                                    $val = '';
                                    if (array_key_exists($colKey, $row)) {
                                        $val = $row[$colKey] ?? '';
                                    } elseif (isset($cData[$colKey])) {
                                        $val = $cData[$colKey] ?? '';
                                    }
                                    if ($colKey === 'link' && $val === 'upload') {
                                        $val = '';
                                    }
                                    $cellClass = 'cell-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $colKey);
                                ?>
                                    <td class="<?= $cellClass ?>"><?= htmlspecialchars((string)$val) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php for ($i = 0; $i < $emptyRowsNeeded; $i++): ?>
                        <tr class="empty-row">
                            <?php foreach ($printCols as $col): ?>
                                <td>&nbsp;</td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </main>

        <!-- Summary and Remarks Dual Section -->
        <section class="summary-grid">
            <div class="summary-box">
                <div class="summary-title">📝 Daily Work Summary</div>
                <div class="summary-content">
                    <?= !empty($workSummary) ? nl2br(htmlspecialchars($workSummary)) : '<span class="placeholder-text">No additional work summary recorded.</span>' ?>
                </div>
            </div>
            <div class="summary-box">
                <div class="summary-title">📌 Remarks / Blockers</div>
                <div class="summary-content">
                    <?= !empty($remarks) ? nl2br(htmlspecialchars($remarks)) : '<span class="placeholder-text">None.</span>' ?>
                </div>
            </div>
        </section>

        <!-- Corporate Authorization / Signatures Footer -->
        <footer class="doc-footer <?= $isHodChecked ? 'verified-mode' : 'manual-mode' ?>">
            <?php if ($isHodChecked): ?>
                <div class="official-approval-stamp">
                    <div class="stamp-inner">
                        <div class="stamp-top-ribbon">
                            <span class="stamp-star">★</span>
                            <span>DISCOVER PAKISTAN UHD TV</span>
                            <span class="stamp-star">★</span>
                        </div>
                        <div class="stamp-main-badge">
                            <span class="stamp-check">✓</span>
                            <span class="stamp-title">DIGITALLY APPROVED</span>
                        </div>
                        <div class="stamp-meta">
                            <span><strong>BY:</strong> <?= strtoupper(htmlspecialchars($hodCheckerName ?: 'HEAD OF DEPARTMENT')) ?></span>
                            <span class="stamp-dot">•</span>
                            <span><strong>DATE:</strong> <?= htmlspecialchars($hodCheckedFormatted) ?></span>
                        </div>
                        <div class="stamp-bottom-seal">OFFICIAL HOD VERIFICATION SEAL</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="sig-card">
                    <div class="sig-line-box"></div>
                    <span class="sig-title">Employee Signature & Date</span>
                    <span class="sig-sub"><?= htmlspecialchars($employee['name']) ?></span>
                </div>
                <div class="sig-card">
                    <div class="sig-line-box"></div>
                    <span class="sig-title">HOD / Supervisor Approval</span>
                    <span class="sig-sub"><?= htmlspecialchars($employee['department_name'] ?: 'Digital Media') ?> Department</span>
                </div>
            <?php endif; ?>
        </footer>
    </div>

    <!-- Direct Print Trigger (Only when opened directly, to avoid double-firing when loaded via iframe) -->
    <script>
        if (window.self === window.top) {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 200);
            });
        }
    </script>
</body>
</html>
