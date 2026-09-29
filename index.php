<?php
/**
 * Hourly Work Sheet, Task Assigner & Reporting System
 * Main Application Dashboard (Modular View Architecture)
 */
require_once __DIR__ . '/config/database.php';
// Initialize MySQL database connection & seed records
$pdo = getDbConnection();

// 1. Header (HTML DocType, Head, Styles)
require_once __DIR__ . '/includes/header.php';

// 2. Navigation Bar (Logo, Route Tabs, User Profile, Switcher)
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Main Application Workspace Container -->
<main class="main-content">
    <?php
    // Modular Feature Views
    require_once __DIR__ . '/views/tab_worksheet.php';
    require_once __DIR__ . '/views/tab_tasks.php';
    require_once __DIR__ . '/views/tab_reports.php';
    require_once __DIR__ . '/views/tab_attendance.php';
    require_once __DIR__ . '/views/tab_employees.php';
    ?>
</main>

<?php
// 3. Interactive Modals (Auth, Task Assigner, Switcher, Employee CRUD, Password Reset)
require_once __DIR__ . '/views/modals.php';

// 4. Footer Scripts & Closing Tags
require_once __DIR__ . '/includes/footer.php';
?>
