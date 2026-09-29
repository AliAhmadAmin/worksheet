    <!-- App Navbar -->
    <header class="navbar">
        <div class="nav-brand">
            <img src="assets/img/logo.svg" alt="Discover Pakistan UHD TV" class="brand-logo-img">
        </div>

        <!-- Navigation Tabs with Direct Hash Routing -->
        <nav class="nav-links">
            <button type="button" class="nav-btn active" data-tab="tab-worksheet" data-route="worksheet">
                <span id="main-nav-tab-label">📝 Hourly Sheet</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-tasks" data-route="tasks">
                🎯 Task Assigner <span id="pending-task-badge" class="nav-badge" style="display: none;">0</span>
            </button>
            <button type="button" class="nav-btn" data-tab="tab-reports" data-route="reports">
                📊 Matrix Reports
            </button>
            <button type="button" class="nav-btn admin-only" data-tab="tab-attendance" data-route="attendance">
                👥 Live Attendance
            </button>
            <button type="button" class="nav-btn admin-only" data-tab="tab-employees" data-route="employees">
                ⚙️ Manage Employees
            </button>
        </nav>

        <!-- User Profile & Controls -->
        <div class="nav-user-ctrl">
            <button type="button" id="theme-toggle-btn" class="btn-switcher" title="Toggle Dark/Light Mode">
                🌙
            </button>

            <div class="user-badge-container" id="nav-user-badge" onclick="openMyProfileModal()" title="Click to edit your profile & settings">
                <div id="nav-user-avatar" class="user-avatar">Z</div>
                <div class="user-info">
                    <span id="nav-user-name" class="user-name">Muhammad Zeeshan</span>
                    <span id="nav-user-role" class="user-role-tag admin">ADMIN (Admin Manager)</span>
                </div>
            </div>

            <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;" onclick="handleLogout()" title="Log out">
                🚪 Logout
            </button>
        </div>
    </header>
