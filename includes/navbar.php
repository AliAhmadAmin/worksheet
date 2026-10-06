<?php
$currentPortal = $currentPortal ?? 'digital';

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

        <!-- Department Workspace Portal Switcher Dropdown (Super Admin / Elevated Users) -->
        <div class="portal-switcher-wrapper admin-only" style="position: relative; display: none;">
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
    </div>

    <!-- Navigation Menu Scrollable Area -->
    <nav class="sidebar-nav">
    <?php if ($currentPortal === 'digital'): ?>
        <!-- Workspace Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">WORKSPACE</div>
            <button type="button" class="nav-btn active" data-tab="tab-worksheet" data-route="worksheet">
                <span class="nav-icon" id="main-nav-tab-icon">📝</span>
                <span class="nav-label" id="main-nav-tab-label">Hourly Sheet</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-tasks" data-route="tasks">
                <span class="nav-icon" id="tasks-nav-tab-icon">🎯</span>
                <span class="nav-label" id="tasks-nav-tab-label">Assigned Tasks</span>
                <span id="pending-task-badge" class="nav-badge" style="display: none;">0</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-newsroom" data-route="newsroom">
                <span class="nav-icon">📺</span>
                <span class="nav-label">News Handover</span>
                <span id="pending-news-badge" class="nav-badge badge-warning" style="display: none; background: #dc2626; color: #fff;">0</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-programming" data-route="programming">
                <span class="nav-icon">📡</span>
                <span class="nav-label">Program Handover</span>
                <span id="pending-programming-badge" class="nav-badge badge-warning" style="display: none; background: #d97706; color: #fff;">0</span>
            </button>
        </div>

        <!-- People & Services Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">PEOPLE & SERVICES</div>
            <button type="button" class="nav-btn" data-tab="tab-hr-leaves" data-route="leaves">
                <span class="nav-icon">🏖️</span>
                <span class="nav-label">Leave Portal</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-hr-loans" data-route="loans">
                <span class="nav-icon">💳</span>
                <span class="nav-label">Advance & Loans</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-hr-notices" data-route="notices">
                <span class="nav-icon">📢</span>
                <span class="nav-label">Notice Board</span>
            </button>
        </div>

        <!-- Department Management Category -->
        <div class="sidebar-nav-group admin-only" style="display: none;">
            <div class="sidebar-nav-title">MANAGEMENT</div>
            <button type="button" class="nav-btn admin-only" data-tab="tab-reports" data-route="reports">
                <span class="nav-icon">📊</span>
                <span class="nav-label">Matrix Reports</span>
            </button>
            <button type="button" class="nav-btn admin-only" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Live Attendance</span>
            </button>
            <button type="button" class="nav-btn admin-only" data-tab="tab-employees" data-route="employees">
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">Manage Staff</span>
            </button>
        </div>

    <?php elseif ($currentPortal === 'newsroom'): ?>
        <!-- News Room Operations Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">NEWS PIPELINE</div>
            <button type="button" class="nav-btn active" data-tab="tab-newsroom" data-route="newsroom">
                <span class="nav-icon">📺</span>
                <span class="nav-label">News Handover</span>
                <span id="pending-news-badge-nr" class="nav-badge badge-warning" style="display: none; background: #dc2626; color: #fff;">0</span>
            </button>
            <a href="index.php" class="nav-btn" style="text-decoration: none;">
                <span class="nav-icon">📝</span>
                <span class="nav-label">Digital Sheet</span>
            </a>
            <a href="index.php#tasks" class="nav-btn" style="text-decoration: none;">
                <span class="nav-icon">🎯</span>
                <span class="nav-label">My Tasks</span>
            </a>
        </div>

        <!-- Operations & Staff Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">STAFF & SERVICES</div>
            <button type="button" class="nav-btn" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Live Attendance</span>
            </button>
            <button type="button" class="nav-btn admin-only" data-tab="tab-employees" data-route="employees">
                <span class="nav-icon">📁</span>
                <span class="nav-label">Staff Directory</span>
            </button>
            <a href="hr.php#leaves" class="nav-btn" style="text-decoration: none;">
                <span class="nav-icon">🏖️</span>
                <span class="nav-label">Leave Portal</span>
            </a>
        </div>

    <?php elseif ($currentPortal === 'programming'): ?>
        <!-- Programming Operations Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">HANDOVER PIPELINE</div>
            <button type="button" class="nav-btn active" data-tab="tab-programming" data-route="programming">
                <span class="nav-icon">📡</span>
                <span class="nav-label">Handover Board</span>
                <span id="pending-prog-badge-pg" class="nav-badge badge-warning" style="display: none; background: #d97706; color: #fff;">0</span>
            </button>
            <a href="index.php" class="nav-btn" style="text-decoration: none;">
                <span class="nav-icon">📝</span>
                <span class="nav-label">Digital Sheet</span>
            </a>
            <a href="index.php#tasks" class="nav-btn" style="text-decoration: none;">
                <span class="nav-icon">🎯</span>
                <span class="nav-label">My Tasks</span>
            </a>
        </div>

        <!-- Operations & Staff Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">STAFF & SERVICES</div>
            <button type="button" class="nav-btn" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Live Attendance</span>
            </button>
            <button type="button" class="nav-btn admin-only" data-tab="tab-employees" data-route="employees">
                <span class="nav-icon">📁</span>
                <span class="nav-label">Staff Directory</span>
            </button>
            <a href="hr.php#leaves" class="nav-btn" style="text-decoration: none;">
                <span class="nav-icon">🏖️</span>
                <span class="nav-label">Leave Portal</span>
            </a>
        </div>

    <?php elseif ($currentPortal === 'hr'): ?>
        <!-- Executive HR Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">EXECUTIVE</div>
            <button type="button" class="nav-btn active" data-tab="tab-hr" data-route="hr">
                <span class="nav-icon">📊</span>
                <span class="nav-label">HR Dashboard</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-attendance" data-route="attendance">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Staff Attendance</span>
            </button>
        </div>

        <!-- Requests & Notices Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">REQUESTS & NOTICES</div>
            <button type="button" class="nav-btn" data-tab="tab-hr-leaves" data-route="leaves">
                <span class="nav-icon">🏖️</span>
                <span class="nav-label">Leave Requests</span>
                <span id="pending-leaves-badge" class="nav-badge badge-warning" style="display: none;">0</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-hr-loans" data-route="loans">
                <span class="nav-icon">💳</span>
                <span class="nav-label">Advance & Loans</span>
                <span id="pending-loans-badge" class="nav-badge badge-info" style="display: none;">0</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-hr-notices" data-route="notices">
                <span class="nav-icon">📢</span>
                <span class="nav-label">Notice Board</span>
            </button>
        </div>

        <!-- People & Payroll Category -->
        <div class="sidebar-nav-group">
            <div class="sidebar-nav-title">PEOPLE & FINANCE</div>
            <button type="button" class="nav-btn" data-tab="tab-hr-payroll" data-route="payroll">
                <span class="nav-icon">💰</span>
                <span class="nav-label">Payroll & Salaries</span>
            </button>
            <button type="button" class="nav-btn admin-only" data-tab="tab-employees" data-route="employees">
                <span class="nav-icon">📁</span>
                <span class="nav-label">Staff Directory</span>
            </button>
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
</aside>
