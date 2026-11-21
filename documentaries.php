<?php
/**
 * Discover Pakistan UHD TV - Documentaries Desk
 * Programming Submissions & Digital Social Media Broadcast Links
 */
require_once __DIR__ . '/includes/auth_check.php';

$userRoleDoc = strtolower($authUser['role'] ?? '');
$deptNameDoc = strtolower($authUser['department_name'] ?? '');
$isGlobalAdminDoc = in_array($userRoleDoc, ['super_admin', 'admin', 'manager'], true);
$isHrMemberDoc = ($userRoleDoc === 'hr' || stripos($deptNameDoc, 'hr') !== false || !empty($authUser['can_manage_hr']));
$isDigitalMemberDoc = (stripos($deptNameDoc, 'digital') !== false);
$isProgMemberDoc = (stripos($deptNameDoc, 'prog') !== false);
$canAccess = $isGlobalAdminDoc || $isHrMemberDoc || $isDigitalMemberDoc || $isProgMemberDoc || in_array($userRoleDoc, ['hod', 'deputy_hod'], true);

if (!$canAccess) {
    header('Location: index.php');
    exit;
}

$currentPage = 'documentaries';
$currentPortal = ($isProgMemberDoc && !$isDigitalMemberDoc) ? 'programming' : 'digital';

$canFileDocumentary = $isGlobalAdminDoc || $isProgMemberDoc || ($userRoleDoc === 'hod' && !$isDigitalMemberDoc);
$canSelectProducer = $isGlobalAdminDoc;
$isDigitalOnly = $isDigitalMemberDoc && !$isGlobalAdminDoc && !$isProgMemberDoc;

$pageScripts = ['assets/js/documentaries.js'];

// 1. Header (HTML DocType, Head, Shared Styling)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Documentaries Application Workspace Container -->
<main class="main-content">
    <script>
        window.DOC_AUTH_CONFIG = {
            userId: <?= (int)($authUser['id'] ?? 0) ?>,
            userName: <?= json_encode($authUser['name'] ?? 'Staff') ?>,
            isAdmin: <?= $isGlobalAdminDoc ? 'true' : 'false' ?>,
            canFileDocs: <?= $canFileDocumentary ? 'true' : 'false' ?>,
            canSelectProducer: <?= $canSelectProducer ? 'true' : 'false' ?>,
            isDigitalOnly: <?= $isDigitalOnly ? 'true' : 'false' ?>
        };
    </script>
    <?php
    require_once __DIR__ . '/views/tab_documentaries.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Page Scripts & Dynamic Loaders
require_once __DIR__ . '/includes/footer.php';
?>
