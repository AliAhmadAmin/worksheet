<?php
if (empty($currentPortal)) {
    if (!empty($_SESSION['active_portal'])) {
        $currentPortal = $_SESSION['active_portal'];
    } else {
        $userRolePortal = strtolower($authUser['role'] ?? '');
        $userDeptPortal = strtolower($authUser['department_name'] ?? '');
        if ($userRolePortal === 'hr' || stripos($userDeptPortal, 'hr') !== false) {
            $currentPortal = 'hr';
        } elseif (stripos($userDeptPortal, 'news') !== false) {
            $currentPortal = 'newsroom';
        } elseif (stripos($userDeptPortal, 'prog') !== false) {
            $currentPortal = 'programming';
        } else {
            $currentPortal = 'digital';
        }
    }
}

$navUserName = htmlspecialchars($authUser['name'] ?? 'Employee');
$navInitial = !empty($authUser['name']) ? strtoupper(mb_substr($authUser['name'], 0, 1)) : '👤';
$navRoleTag = strtoupper($authUser['role'] ?? 'EMPLOYEE');
$navDesig = htmlspecialchars($authUser['designation'] ?? ($authUser['role'] ?? 'Staff'));
$navAvatar = $authUser['avatar'] ?? null;
$navRoleClass = strtolower($authUser['role'] ?? 'employee');
?>

<!-- Mobile Top Header Bar -->
<header class="mobile-top-bar">
    <div class="mobile-top-left">
        <button type="button" class="sidebar-toggle-btn" onclick="toggleMobileSidebar()" aria-label="Toggle Navigation Menu">
            <span class="toggle-bar"></span>
            <span class="toggle-bar"></span>
            <span class="toggle-bar"></span>
        </button>
        <a href="index.php" class="mobile-brand">
            <img src="assets/img/logo.svg" alt="Discover Pakistan UHD TV" class="mobile-brand-logo">
        </a>
    </div>
    <div class="mobile-top-right">
        <button type="button" class="btn-switcher mobile-theme-btn" onclick="toggleThemeQuick()" title="Toggle Dark/Light Mode">
            <span class="theme-icon-indicator">🌙</span>
        </button>
        <div class="mobile-user-avatar" onclick="openMyProfileModal()" title="<?= $navUserName ?> (Click for profile)">
            <?php if ($navAvatar): ?>
                <img src="<?= htmlspecialchars($navAvatar) ?>" alt="<?= $navUserName ?>" onerror="this.outerHTML='<?= $navInitial ?>'">
            <?php else: ?>
                <?= $navInitial ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Mobile Sidebar Backdrop Overlay -->
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleMobileSidebar()"></div>

<!-- Main Application Sidebar -->
<aside class="app-sidebar" id="app-sidebar">
    <!-- Sidebar Header (Brand & Portal Selector) -->
    <div class="sidebar-header">
        <div class="sidebar-brand-wrapper">
            <a href="index.php" class="sidebar-brand-link">
                <img src="assets/img/logo.svg" alt="Discover Pakistan UHD TV" class="brand-logo-img">
            </a>
            <button type="button" class="sidebar-close-btn" onclick="toggleMobileSidebar()" aria-label="Close sidebar">✕</button>
        </div>

        <!-- Department Workspace Portal Switcher Dropdown (Super Admin / Global Admins Only) -->
        <?php 
        $userRole = strtolower($authUser['role'] ?? 'employee');
        $userDept = strtolower($authUser['department_name'] ?? '');
        $isGlobalAdmin = in_array($userRole, ['super_admin', 'admin']);
        $isNewsDept = (stripos($userDept, 'news') !== false);
        $isProgDept = (stripos($userDept, 'prog') !== false);
        $isDigitalDept = (stripos($userDept, 'digital') !== false);

        // Access permissions:
        // - News Handover: visible to News Room, Digital Media, or Global Admin (Programming cannot see)
        $canViewNewsHandover = $isGlobalAdmin || ($isNewsDept && !$isProgDept) || $isDigitalDept;

        // - Program Handover: visible to Programming, Digital Media, or Global Admin (News Room cannot see)
        $canViewProgHandover = $isGlobalAdmin || ($isProgDept && !$isNewsDept) || $isDigitalDept;
        $canViewManagement = $isGlobalAdmin || $userRole === 'hod' || !empty($authUser['can_view_reports']) || !empty($authUser['can_view_attendance']) || !empty($authUser['can_manage_employees']);
        $isHodNav = in_array($userRole, ['hod', 'deputy_hod']);
        ?>
        <?php if ($isGlobalAdmin): ?>
        <div class="portal-switcher-wrapper admin-only" style="position: relative;">
            <button type="button" class="portal-switcher-btn" onclick="togglePortalMenu()" id="portal-switcher-btn" title="Switch Department Workspace Portal">
                <span class="portal-icon"><?= $currentPortal === 'hr' ? '💼' : ($currentPortal === 'newsroom' ? '📺' : ($currentPortal === 'programming' ? '📡' : '🎬')) ?></span>
                <div class="portal-text-block">
                    <span class="portal-eyebrow">PORTAL</span>
                    <span class="portal-name"><?= $currentPortal === 'hr' ? 'HR & People' : ($currentPortal === 'newsroom' ? 'News Room' : ($currentPortal === 'programming' ? 'Programming' : 'Digital Media')) ?></span>
                </div>
                <span class="portal-chevron">▾</span>
            </button>

            <div class="portal-dropdown-menu" id="portal-dropdown-menu" style="display: none;">
                <div class="portal-dropdown-header">
                    <span>🏢 Active Department Portals</span>
                </div>
                <a href="index.php" class="portal-menu-item <?= $currentPortal === 'digital' ? 'active' : '' ?>">
                    <span class="item-icon">🎬</span>
                    <div class="item-info">
                        <div class="item-title">Digital Media Portal</div>
                        <div class="item-desc">Hourly sheet, tasks & video links</div>
                    </div>
                    <?php if ($currentPortal === 'digital'): ?><span class="item-check">✓</span><?php endif; ?>
                </a>
                <a href="newsroom.php" class="portal-menu-item <?= $currentPortal === 'newsroom' ? 'active' : '' ?>">
                    <span class="item-icon">📺</span>
                    <div class="item-info">
                        <div class="item-title">News Room Portal</div>
                        <div class="item-desc">Bulletins, tickers & dispatch matrix</div>
                    </div>
                    <?php if ($currentPortal === 'newsroom'): ?><span class="item-check">✓</span><?php endif; ?>
                </a>
                <a href="programming.php" class="portal-menu-item <?= $currentPortal === 'programming' ? 'active' : '' ?>">
                    <span class="item-icon">📡</span>
                    <div class="item-info">
                        <div class="item-title">Programming Portal</div>
                        <div class="item-desc">Content handover & dispatch matrix</div>
                    </div>
                    <?php if ($currentPortal === 'programming'): ?><span class="item-check">✓</span><?php endif; ?>
                </a>
                <a href="hr.php" class="portal-menu-item <?= $currentPortal === 'hr' ? 'active' : '' ?>">
                    <span class="item-icon">💼</span>
                    <div class="item-info">
                        <div class="item-title">HR & People Portal</div>
                        <div class="item-desc">Leaves, staff records & payroll</div>
                    </div>
                    <?php if ($currentPortal === 'hr'): ?><span class="item-check">✓</span><?php endif; ?>
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="portal-switcher-wrapper static-portal" style="position: relative;">
            <div class="portal-switcher-btn" style="cursor: default; pointer-events: none;" title="<?= htmlspecialchars($authUser['department_name'] ?? 'Department') ?> Workspace">
                <span class="portal-icon"><?= $currentPortal === 'hr' ? '💼' : ($currentPortal === 'newsroom' ? '📺' : ($currentPortal === 'programming' ? '📡' : '🎬')) ?></span>
                <div class="portal-text-block">
                    <span class="portal-eyebrow">DEPARTMENT</span>
                    <span class="portal-name"><?= htmlspecialchars($authUser['department_name'] ?? ($currentPortal === 'hr' ? 'HR & People' : ($currentPortal === 'newsroom' ? 'News Room' : ($currentPortal === 'programming' ? 'Programming' : 'Digital Media')))) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Navigation Menu Scrollable Area -->
    <nav class="sidebar-nav">
    <?php if ($currentPortal === 'digital'): ?>
        <?php 
        $isHodNav = in_array($userRole, ['hod', 'deputy_hod']); 
        $activeNavPage = $currentPage ?? ($isHodNav ? 'attendance' : 'worksheet');
        ?>
        <!-- Workspace Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">WORKSPACE</div>
            <?php if ($isHodNav): ?>
            <a href="attendance.php" class="nav-btn <?= ($activeNavPage === 'attendance') ? 'active' : '' ?>" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Team Dashboard</span>
            </a>
            <a href="index.php" class="nav-btn <?= ($activeNavPage === 'worksheet') ? 'active' : '' ?>" data-tab="tab-worksheet" data-route="worksheet">
                <span class="nav-icon" id="main-nav-tab-icon">📝</span>
                <span class="nav-label" id="main-nav-tab-label">WorkSheets</span>
            </a>
            <?php else: ?>
            <a href="index.php" class="nav-btn <?= ($activeNavPage === 'worksheet') ? 'active' : '' ?>" data-tab="tab-worksheet" data-route="worksheet">
                <span class="nav-icon" id="main-nav-tab-icon">📝</span>
                <span class="nav-label" id="main-nav-tab-label">WorkSheets</span>
            </a>
            <?php endif; ?>
            <a href="tasks.php" class="nav-btn <?= ($activeNavPage === 'tasks') ? 'active' : '' ?>" data-tab="tab-tasks" data-route="tasks">
                <span class="nav-icon" id="tasks-nav-tab-icon">🎯</span>
                <span class="nav-label" id="tasks-nav-tab-label">Assign Task</span>
                <span id="pending-task-badge" class="nav-badge" style="display: none;">0</span>
            </a>
            <?php if ($canViewNewsHandover): ?>
            <a href="newsroom.php" class="nav-btn <?= ($activeNavPage === 'newsroom') ? 'active' : '' ?>" data-tab="tab-newsroom" data-route="newsroom">
                <span class="nav-icon">📺</span>
                <span class="nav-label">Newsroom</span>
                <span id="pending-news-badge" class="nav-badge badge-warning" style="display: none; background: #dc2626; color: #fff;">0</span>
            </a>
            <?php endif; ?>
            <?php if ($canViewProgHandover): ?>
            <?php 
            $isProgSectionActive = in_array($activeNavPage, ['programming', 'fresh_programs']); 
            ?>
            <div class="sidebar-nav-dropdown <?= $isProgSectionActive ? 'open' : '' ?>" id="nav-dropdown-prog">
                <button type="button" class="nav-btn nav-dropdown-btn <?= $isProgSectionActive ? 'active' : '' ?>" onclick="toggleNavDropdown('nav-dropdown-prog')" style="width: 100%; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0;">
                        <span class="nav-icon">📡</span>
                        <span class="nav-label">Programming</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span id="pending-programming-badge" class="nav-badge badge-warning" style="display: none; background: #d97706; color: #fff;">0</span>
                        <span class="dropdown-arrow" style="font-size: 9px; opacity: 0.7; transition: transform 0.2s ease; <?= $isProgSectionActive ? 'transform: rotate(180deg);' : '' ?>">▼</span>
                    </div>
                </button>
                <div class="nav-submenu" style="<?= $isProgSectionActive ? 'display: block;' : 'display: none;' ?>">
                    <a href="fresh_programs.php" class="nav-sub-btn <?= ($activeNavPage === 'fresh_programs') ? 'active' : '' ?>">
                        <span class="nav-sub-icon">🎬</span>
                        <span class="nav-sub-label">Fresh Programs</span>
                    </a>
                    <a href="programming.php" class="nav-sub-btn <?= ($activeNavPage === 'programming') ? 'active' : '' ?>">
                        <span class="nav-sub-icon">📤</span>
                        <span class="nav-sub-label">Handover Dispatches</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- People & Services Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">PEOPLE & SERVICES</div>
            <a href="leaves.php" class="nav-btn <?= ($activeNavPage === 'leaves') ? 'active' : '' ?>" data-tab="tab-hr-leaves" data-route="leaves">
                <span class="nav-icon">🏖️</span>
                <span class="nav-label">Leave Portal</span>
                <span class="nav-badge badge-warning pending-leaves-badge" style="display: none; background: #ea580c; color: #fff;">0</span>
            </a>
            <a href="loans.php" class="nav-btn <?= ($activeNavPage === 'loans') ? 'active' : '' ?>" data-tab="tab-hr-loans" data-route="loans">
                <span class="nav-icon">💳</span>
                <span class="nav-label">Advance Salary</span>
                <span class="nav-badge badge-info pending-loans-badge" style="display: none; background: #0284c7; color: #fff;">0</span>
            </a>
            <a href="claims.php" class="nav-btn <?= ($activeNavPage === 'claims') ? 'active' : '' ?>" data-tab="tab-hr-claims" data-route="claims">
                <span class="nav-icon">⛽</span>
                <span class="nav-label">Fuel, Travel & Food</span>
            </a>
            <a href="notices.php" class="nav-btn <?= ($activeNavPage === 'notices') ? 'active' : '' ?>" data-tab="tab-hr-notices" data-route="notices">
                <span class="nav-icon">📢</span>
                <span class="nav-label">Notice Board</span>
            </a>
            <a href="fines.php" class="nav-btn <?= ($activeNavPage === 'fines') ? 'active' : '' ?>" data-tab="tab-hr-fines" data-route="fines">
                <span class="nav-icon">⚠️</span>
                <span class="nav-label">Disciplinary & Fines</span>
            </a>
        </div>

        <!-- Department Management Category -->
        <div class="sidebar-nav-group admin-only" style="<?= $canViewManagement ? '' : 'display: none;' ?>">
            <div class="sidebar-nav-title">MANAGEMENT</div>
            <a href="roster.php" class="nav-btn admin-only <?= ($activeNavPage === 'roster') ? 'active' : '' ?>" data-tab="tab-roster" data-route="roster">
                <span class="nav-icon">📅</span>
                <span class="nav-label">Duty Roster</span>
            </a>
            <a href="reports.php" class="nav-btn admin-only <?= ($activeNavPage === 'reports') ? 'active' : '' ?>" data-tab="tab-reports" data-route="reports">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Matrix Reports</span>
            </a>
            <?php if (!$isHodNav): ?>
            <a href="attendance.php" class="nav-btn admin-only <?= ($activeNavPage === 'attendance') ? 'active' : '' ?>" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Live Attendance</span>
            </a>
            <?php endif; ?>
            <a href="employees.php" class="nav-btn admin-only <?= ($activeNavPage === 'employees') ? 'active' : '' ?>" data-tab="tab-employees" data-route="employees">
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">Manage Staff</span>
            </a>
        </div>

    <?php elseif ($currentPortal === 'newsroom'): ?>
        <?php 
        $isHodNav = in_array($userRole, ['hod', 'deputy_hod']); 
        $activeNavPage = $currentPage ?? ($isHodNav ? 'attendance' : 'worksheet');
        ?>
        <!-- News Room Operations Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">WORKSPACE</div>
            <?php if ($isHodNav): ?>
            <a href="attendance.php" class="nav-btn <?= ($activeNavPage === 'attendance') ? 'active' : '' ?>" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Team Dashboard</span>
            </a>
            <a href="index.php" class="nav-btn <?= ($activeNavPage === 'worksheet') ? 'active' : '' ?>" data-tab="tab-worksheet" data-route="worksheet">
                <span class="nav-icon" id="main-nav-tab-icon">📝</span>
                <span class="nav-label" id="main-nav-tab-label">WorkSheets</span>
            </a>
            <a href="newsroom.php" class="nav-btn <?= ($activeNavPage === 'newsroom') ? 'active' : '' ?>" data-tab="tab-newsroom" data-route="newsroom">
                <span class="nav-icon">📺</span>
                <span class="nav-label">Dispatch News</span>
                <span id="pending-news-badge" class="nav-badge badge-warning" style="display: none; background: #dc2626; color: #fff;">0</span>
            </a>
            <?php else: ?>
            <a href="index.php" class="nav-btn <?= ($activeNavPage === 'worksheet') ? 'active' : '' ?>" data-tab="tab-worksheet" data-route="worksheet">
                <span class="nav-icon" id="main-nav-tab-icon">📝</span>
                <span class="nav-label" id="main-nav-tab-label">WorkSheets</span>
            </a>
            <a href="newsroom.php" class="nav-btn <?= ($activeNavPage === 'newsroom') ? 'active' : '' ?>" data-tab="tab-newsroom" data-route="newsroom">
                <span class="nav-icon">📺</span>
                <span class="nav-label">Dispatch News</span>
                <span id="pending-news-badge" class="nav-badge badge-warning" style="display: none; background: #dc2626; color: #fff;">0</span>
            </a>
            <?php endif; ?>
            <a href="tasks.php" class="nav-btn <?= ($activeNavPage === 'tasks') ? 'active' : '' ?>" data-tab="tab-tasks" data-route="tasks">
                <span class="nav-icon" id="tasks-nav-tab-icon">🎯</span>
                <span class="nav-label" id="tasks-nav-tab-label">Assign Task</span>
                <span id="pending-task-badge" class="nav-badge" style="display: none;">0</span>
            </a>
            <?php if ($canViewProgHandover): ?>
            <a href="programming.php" class="nav-btn" data-tab="tab-programming" data-route="programming">
                <span class="nav-icon">📡</span>
                <span class="nav-label">Programming</span>
                <span id="pending-programming-badge" class="nav-badge badge-warning" style="display: none; background: #d97706; color: #fff;">0</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- People & Services Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">PEOPLE & SERVICES</div>
            <a href="leaves.php" class="nav-btn <?= ($activeNavPage === 'leaves') ? 'active' : '' ?>" data-tab="tab-hr-leaves" data-route="leaves">
                <span class="nav-icon">🏖️</span>
                <span class="nav-label">Leave Portal</span>
                <span class="nav-badge badge-warning pending-leaves-badge" style="display: none; background: #ea580c; color: #fff;">0</span>
            </a>
            <a href="loans.php" class="nav-btn <?= ($activeNavPage === 'loans') ? 'active' : '' ?>" data-tab="tab-hr-loans" data-route="loans">
                <span class="nav-icon">💳</span>
                <span class="nav-label">Advance Salary</span>
                <span class="nav-badge badge-info pending-loans-badge" style="display: none; background: #0284c7; color: #fff;">0</span>
            </a>
            <a href="claims.php" class="nav-btn <?= ($activeNavPage === 'claims') ? 'active' : '' ?>" data-tab="tab-hr-claims" data-route="claims">
                <span class="nav-icon">⛽</span>
                <span class="nav-label">Fuel, Travel & Food</span>
            </a>
            <a href="notices.php" class="nav-btn <?= ($activeNavPage === 'notices') ? 'active' : '' ?>" data-tab="tab-hr-notices" data-route="notices">
                <span class="nav-icon">📢</span>
                <span class="nav-label">Notice Board</span>
            </a>
            <a href="fines.php" class="nav-btn <?= ($activeNavPage === 'fines') ? 'active' : '' ?>" data-tab="tab-hr-fines" data-route="fines">
                <span class="nav-icon">⚠️</span>
                <span class="nav-label">Disciplinary & Fines</span>
            </a>
        </div>

        <!-- Department Management Category -->
        <div class="sidebar-nav-group admin-only" style="<?= $canViewManagement ? '' : 'display: none;' ?>">
            <div class="sidebar-nav-title">MANAGEMENT</div>
            <a href="roster.php" class="nav-btn admin-only <?= ($activeNavPage === 'roster') ? 'active' : '' ?>" data-tab="tab-roster" data-route="roster">
                <span class="nav-icon">📅</span>
                <span class="nav-label">Duty Roster</span>
            </a>
            <a href="reports.php" class="nav-btn admin-only <?= ($activeNavPage === 'reports') ? 'active' : '' ?>" data-tab="tab-reports" data-route="reports">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Matrix Reports</span>
            </a>
            <?php if (!$isHodNav): ?>
            <a href="attendance.php" class="nav-btn admin-only <?= ($activeNavPage === 'attendance') ? 'active' : '' ?>" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Live Attendance</span>
            </a>
            <?php endif; ?>
            <a href="employees.php" class="nav-btn admin-only <?= ($activeNavPage === 'employees') ? 'active' : '' ?>" data-tab="tab-employees" data-route="employees">
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">Manage Staff</span>
            </a>
        </div>

    <?php elseif ($currentPortal === 'programming'): ?>
        <?php 
        $isHodNav = in_array($userRole, ['hod', 'deputy_hod']); 
        $activeNavPage = $currentPage ?? ($isHodNav ? 'attendance' : 'worksheet');
        ?>
        <!-- Programming Operations Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">WORKSPACE</div>
            <?php if ($isHodNav): ?>
            <a href="attendance.php" class="nav-btn <?= ($activeNavPage === 'attendance') ? 'active' : '' ?>" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Team Dashboard</span>
            </a>
            <a href="index.php" class="nav-btn <?= ($activeNavPage === 'worksheet') ? 'active' : '' ?>" data-tab="tab-worksheet" data-route="worksheet">
                <span class="nav-icon" id="main-nav-tab-icon">📝</span>
                <span class="nav-label" id="main-nav-tab-label">WorkSheets</span>
            </a>
            <a href="programming.php" class="nav-btn <?= ($activeNavPage === 'programming') ? 'active' : '' ?>" data-tab="tab-programming" data-route="programming">
                <span class="nav-icon">📡</span>
                <span class="nav-label">Dispatch Programming</span>
                <span id="pending-programming-badge" class="nav-badge badge-warning" style="display: none; background: #d97706; color: #fff;">0</span>
            </a>
            <?php else: ?>
            <a href="index.php" class="nav-btn <?= ($activeNavPage === 'worksheet') ? 'active' : '' ?>" data-tab="tab-worksheet" data-route="worksheet">
                <span class="nav-icon" id="main-nav-tab-icon">📝</span>
                <span class="nav-label" id="main-nav-tab-label">WorkSheets</span>
            </a>
            <a href="programming.php" class="nav-btn <?= ($activeNavPage === 'programming') ? 'active' : '' ?>" data-tab="tab-programming" data-route="programming">
                <span class="nav-icon">📡</span>
                <span class="nav-label">Dispatch Programming</span>
                <span id="pending-programming-badge" class="nav-badge badge-warning" style="display: none; background: #d97706; color: #fff;">0</span>
            </a>
            <?php endif; ?>
            <a href="tasks.php" class="nav-btn <?= ($activeNavPage === 'tasks') ? 'active' : '' ?>" data-tab="tab-tasks" data-route="tasks">
                <span class="nav-icon" id="tasks-nav-tab-icon">🎯</span>
                <span class="nav-label" id="tasks-nav-tab-label">Assign Task</span>
                <span id="pending-task-badge" class="nav-badge" style="display: none;">0</span>
            </a>
            <?php if ($canViewNewsHandover): ?>
            <a href="newsroom.php" class="nav-btn" data-tab="tab-newsroom" data-route="newsroom">
                <span class="nav-icon">📺</span>
                <span class="nav-label">Newsroom</span>
                <span id="pending-news-badge" class="nav-badge badge-warning" style="display: none; background: #dc2626; color: #fff;">0</span>
            </a>
            <?php endif; ?>
        </div>

        <!-- People & Services Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">PEOPLE & SERVICES</div>
            <a href="leaves.php" class="nav-btn <?= ($activeNavPage === 'leaves') ? 'active' : '' ?>" data-tab="tab-hr-leaves" data-route="leaves">
                <span class="nav-icon">🏖️</span>
                <span class="nav-label">Leave Portal</span>
                <span class="nav-badge badge-warning pending-leaves-badge" style="display: none; background: #ea580c; color: #fff;">0</span>
            </a>
            <a href="loans.php" class="nav-btn <?= ($activeNavPage === 'loans') ? 'active' : '' ?>" data-tab="tab-hr-loans" data-route="loans">
                <span class="nav-icon">💳</span>
                <span class="nav-label">Advance Salary</span>
                <span class="nav-badge badge-info pending-loans-badge" style="display: none; background: #0284c7; color: #fff;">0</span>
            </a>
            <a href="claims.php" class="nav-btn <?= ($activeNavPage === 'claims') ? 'active' : '' ?>" data-tab="tab-hr-claims" data-route="claims">
                <span class="nav-icon">⛽</span>
                <span class="nav-label">Fuel, Travel & Food</span>
            </a>
            <a href="notices.php" class="nav-btn <?= ($activeNavPage === 'notices') ? 'active' : '' ?>" data-tab="tab-hr-notices" data-route="notices">
                <span class="nav-icon">📢</span>
                <span class="nav-label">Notice Board</span>
            </a>
            <a href="fines.php" class="nav-btn <?= ($activeNavPage === 'fines') ? 'active' : '' ?>" data-tab="tab-hr-fines" data-route="fines">
                <span class="nav-icon">⚠️</span>
                <span class="nav-label">Disciplinary & Fines</span>
            </a>
        </div>

        <!-- Department Management Category -->
        <div class="sidebar-nav-group admin-only" style="<?= $canViewManagement ? '' : 'display: none;' ?>">
            <div class="sidebar-nav-title">MANAGEMENT</div>
            <a href="roster.php" class="nav-btn admin-only <?= ($activeNavPage === 'roster') ? 'active' : '' ?>" data-tab="tab-roster" data-route="roster">
                <span class="nav-icon">📅</span>
                <span class="nav-label">Duty Roster</span>
            </a>
            <a href="reports.php" class="nav-btn admin-only <?= ($activeNavPage === 'reports') ? 'active' : '' ?>" data-tab="tab-reports" data-route="reports">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Matrix Reports</span>
            </a>
            <?php if (!$isHodNav): ?>
            <a href="attendance.php" class="nav-btn admin-only <?= ($activeNavPage === 'attendance') ? 'active' : '' ?>" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Live Attendance</span>
            </a>
            <?php endif; ?>
            <a href="employees.php" class="nav-btn admin-only <?= ($activeNavPage === 'employees') ? 'active' : '' ?>" data-tab="tab-employees" data-route="employees">
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">Manage Staff</span>
            </a>
        </div>

    <?php elseif ($currentPortal === 'hr'): ?>
        <?php $activeNavPage = $currentPage ?? 'hr'; ?>
        <!-- Executive HR Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">EXECUTIVE</div>
            <a href="hr.php" class="nav-btn <?= ($activeNavPage === 'hr') ? 'active' : '' ?>" data-tab="tab-hr" data-route="hr">
                <span class="nav-icon">📊</span>
                <span class="nav-label">HR Dashboard</span>
            </a>
            <a href="roster.php" class="nav-btn <?= ($activeNavPage === 'roster') ? 'active' : '' ?>" data-tab="tab-roster" data-route="roster">
                <span class="nav-icon">📅</span>
                <span class="nav-label">Duty Roster</span>
            </a>
            <a href="attendance.php" class="nav-btn <?= ($activeNavPage === 'attendance') ? 'active' : '' ?>" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Staff Attendance</span>
            </a>
        </div>

        <!-- Requests & Notices Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">REQUESTS & NOTICES</div>
            <a href="leaves.php" class="nav-btn <?= ($activeNavPage === 'leaves') ? 'active' : '' ?>" data-tab="tab-hr-leaves" data-route="leaves">
                <span class="nav-icon">🏖️</span>
                <span class="nav-label">Leave Requests</span>
                <span id="pending-leaves-badge" class="nav-badge badge-warning pending-leaves-badge" style="display: none; background: #ea580c; color: #fff;">0</span>
            </a>
            <a href="loans.php" class="nav-btn <?= ($activeNavPage === 'loans') ? 'active' : '' ?>" data-tab="tab-hr-loans" data-route="loans">
                <span class="nav-icon">💳</span>
                <span class="nav-label">Advance Salary</span>
                <span id="pending-loans-badge" class="nav-badge badge-info pending-loans-badge" style="display: none; background: #0284c7; color: #fff;">0</span>
            </a>
            <a href="claims.php" class="nav-btn <?= ($activeNavPage === 'claims') ? 'active' : '' ?>" data-tab="tab-hr-claims" data-route="claims">
                <span class="nav-icon">⛽</span>
                <span class="nav-label">Fuel, Travel & Food</span>
                <span id="pending-claims-badge" class="nav-badge badge-info" style="display: none;">0</span>
            </a>
            <a href="notices.php" class="nav-btn <?= ($activeNavPage === 'notices') ? 'active' : '' ?>" data-tab="tab-hr-notices" data-route="notices">
                <span class="nav-icon">📢</span>
                <span class="nav-label">Notice Board</span>
            </a>
            <a href="fines.php" class="nav-btn <?= ($activeNavPage === 'fines') ? 'active' : '' ?>" data-tab="tab-hr-fines" data-route="fines">
                <span class="nav-icon">⚠️</span>
                <span class="nav-label">Disciplinary & Fines</span>
            </a>
        </div>

        <!-- People & Payroll Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">PEOPLE & FINANCE</div>
            <a href="payroll.php" class="nav-btn <?= ($activeNavPage === 'payroll') ? 'active' : '' ?>" data-tab="tab-hr-payroll" data-route="payroll">
                <span class="nav-icon">💰</span>
                <span class="nav-label">Payroll & Salaries</span>
            </a>
            <a href="employees.php" class="nav-btn admin-only <?= ($activeNavPage === 'employees') ? 'active' : '' ?>" data-tab="tab-employees" data-route="employees">
                <span class="nav-icon">📁</span>
                <span class="nav-label">Staff Directory</span>
            </a>
        </div>
    <?php endif; ?>
    </nav>

    <!-- Sidebar Footer: Profile Badge & Action Bar -->
    <div class="sidebar-footer">
        <!-- User Profile Card -->
        <div class="user-badge-container" id="nav-user-badge" onclick="openMyProfileModal()" title="Click to view & edit your profile">
            <div id="nav-user-avatar" class="user-avatar">
                <?php if ($navAvatar): ?>
                    <img src="<?= htmlspecialchars($navAvatar) ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" alt="<?= $navUserName ?>" onerror="this.outerHTML='<?= $navInitial ?>'">
                <?php else: ?>
                    <?= $navInitial ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <span id="nav-user-name" class="user-name" title="<?= $navUserName ?>"><?= $navUserName ?></span>
                <span id="nav-user-role" class="user-role-tag <?= $navRoleClass ?>"><?= $navRoleTag ?></span>
            </div>
            <span class="user-profile-arrow" title="Profile Settings">⚙️</span>
        </div>

        <!-- Sidebar Action Footer (Theme Switcher + Logout) -->
        <div class="sidebar-bottom-actions">
            <button type="button" id="theme-toggle-btn" class="sidebar-action-btn" title="Toggle Dark/Light Mode">
                <span class="theme-icon">🌙</span>
                <span class="theme-label">Theme</span>
            </button>
            <a href="logout.php" class="sidebar-action-btn logout-btn" onclick="return handleLogout(event)" title="Log out of session">
                <span class="action-icon">🚪</span>
                <span class="action-label">Logout</span>
            </a>
        </div>
    </div>

    <style>
    .sidebar-nav-dropdown {
        width: 100%;
    }
    .sidebar-nav-dropdown.open .dropdown-arrow {
        transform: rotate(180deg);
    }
    .nav-submenu {
        padding-left: 10px;
        margin-top: 3px;
        margin-bottom: 5px;
        border-left: 2px solid rgba(0, 179, 0, 0.28);
        margin-left: 16px;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .nav-sub-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: var(--radius-sm, 6px);
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .nav-sub-btn:hover {
        background: var(--bg-card-elevated);
        color: var(--text-main);
        transform: translateX(2px);
    }
    .nav-sub-btn.active {
        background: linear-gradient(135deg, rgba(0, 179, 0, 0.14), rgba(0, 179, 0, 0.05));
        color: var(--primary, #00b300);
        font-weight: 700;
    }
    .nav-sub-icon {
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    </style>
    <script>
    function toggleNavDropdown(id) {
        const el = document.getElementById(id);
        if (!el) return;
        const submenu = el.querySelector('.nav-submenu');
        const arrow = el.querySelector('.dropdown-arrow');
        const isOpen = el.classList.contains('open');
        if (isOpen) {
            el.classList.remove('open');
            if (submenu) submenu.style.display = 'none';
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        } else {
            el.classList.add('open');
            if (submenu) submenu.style.display = 'flex';
            if (arrow) arrow.style.transform = 'rotate(180deg)';
        }
    }
    </script>
</aside>
