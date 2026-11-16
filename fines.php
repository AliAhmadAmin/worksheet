<?php
/**
 * Discover Pakistan UHD TV - Disciplinary & Penalties Center
 * Dedicated Workspace for Disciplinary Show Cause Notices, Inquiries & Fines
 */
require_once __DIR__ . '/includes/auth_check.php';

$currentPage = 'fines';

// Page-specific script loading
$pageScripts = ['assets/js/hr.js'];

// 1. Header (HTML DocType, Head, Styles)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Dedicated Disciplinary & Penalties Workspace Container -->
<main class="main-content">
    <?php
    require_once __DIR__ . '/views/tab_hr.php';
    ?>
</main>

<?php
// 3. Interactive Modals
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
