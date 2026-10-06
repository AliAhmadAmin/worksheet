/**
 * Core Application Manager: Authentication, State, Routing, Notifications, Live Duty Timer
 */

function getLocalDateString(d = new Date()) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getNextDayString(fromStr = AppState.selectedDate) {
    const parts = (fromStr || getLocalDateString()).split('-');
    const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    d.setDate(d.getDate() + 1);
    return getLocalDateString(d);
}

function getPrevDayString(fromStr = AppState.selectedDate) {
    const parts = (fromStr || getLocalDateString()).split('-');
    const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
    d.setDate(d.getDate() - 1);
    return getLocalDateString(d);
}

function changeWorksheetDate(newDateStr) {
    if (!newDateStr) return;
    AppState.selectedDate = newDateStr;
    document.querySelectorAll('.worksheet-date-input').forEach(input => {
        if (input.value !== newDateStr) input.value = newDateStr;
    });
    loadDailyWorksheet();
}

function goToNextDay() {
    changeWorksheetDate(getNextDayString(AppState.selectedDate));
}

function goToPrevDay() {
    changeWorksheetDate(getPrevDayString(AppState.selectedDate));
}

function goToToday() {
    changeWorksheetDate(getLocalDateString());
}

const AppState = {
    currentUser: null,
    employees: [],
    departments: [],
    teams: [],
    contentTypes: [],
    selectedDate: getLocalDateString(),
    currentSheet: null,
    currentEntries: [],
    isLocked: 0,
    timerInterval: null,
    elapsedSeconds: 0
};

// Utility Toast Notifications
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
    toast.innerHTML = `<span>${icon}</span> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

function formatDuration(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Initialize Application
async function initApp() {
    try {
        // 1. Fetch Master Data first (to populate email dropdowns)
        const masterRes = await fetch('api/employees.php?action=list');
        const masterData = await masterRes.json();
        if (masterData.success) {
            AppState.employees = masterData.employees;
            AppState.departments = masterData.departments;
            AppState.teams = masterData.teams;
            AppState.contentTypes = masterData.content_types;
            populateEmailSelectors();
        }

        // 2. Check Authentication Status
        const userRes = await fetch('api/auth.php?action=current_user');
        const userData = await userRes.json();

        if (userData.success && userData.user) {
            AppState.currentUser = userData.user;
            renderUserBar();
            loadUserData();
        } else {
            // Redirect to dedicated login page
            window.location.href = 'login.php';
            return;
        }

        setupTabNavigation();

    } catch (err) {
        console.error("App initialization failed:", err);
        showToast("Error connecting to server / database.", "error");
    }
}

function updateLoginGreeting() {
    const titleEl = document.getElementById('login-greeting-title');
    if (!titleEl) return;
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) {
        titleEl.textContent = 'Good morning, Superstar! ☀️';
    } else if (hour >= 12 && hour < 17) {
        titleEl.textContent = 'Good afternoon, Creator! 🚀';
    } else {
        titleEl.textContent = 'Welcome Back, Champion! ✨';
    }
}

function toggleLoginPasswordVisibility() {
    const passInput = document.getElementById('login-password');
    const eyeIcon = document.getElementById('login-password-eye-icon');
    if (!passInput) return;

    if (passInput.type === 'password') {
        passInput.type = 'text';
        if (eyeIcon) eyeIcon.textContent = '🙈';
    } else {
        passInput.type = 'password';
        if (eyeIcon) eyeIcon.textContent = '👁️';
    }
}

function populateEmailSelectors() {
    updateLoginGreeting();
    if (typeof populateTaskEmployeeFilter === 'function') {
        populateTaskEmployeeFilter();
    }
}

let selectedLoginWorkspace = (window.location.pathname.includes('hr.php')) ? 'hr' : 'digital';

function selectLoginWorkspace(ws) {
    selectedLoginWorkspace = ws;
    document.querySelectorAll('.login-dept-pill').forEach(pill => pill.classList.remove('active'));
    const targetPill = document.getElementById(`login-dept-pill-${ws}`);
    if (targetPill) targetPill.classList.add('active');
    const radio = document.getElementById(`login-ws-${ws}`);
    if (radio) radio.checked = true;
}

function togglePortalMenu() {
    const menu = document.getElementById('portal-dropdown-menu');
    if (menu) {
        menu.style.display = (menu.style.display === 'none' || !menu.style.display) ? 'flex' : 'none';
    }
}

async function handleLoginSubmit(e) {
    if (e) e.preventDefault();

    const email = document.getElementById('login-email')?.value.trim();
    const password = document.getElementById('login-password')?.value.trim();

    if (!email) {
        showToast("Email address is required.", "error");
        return;
    }
    if (!password) {
        showToast("Password is required.", "error");
        return;
    }

    try {
        const res = await fetch('api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', email, password })
        });
        const data = await res.json();

        if (data.success && data.user) {
            AppState.currentUser = data.user;
            closeModal('login-modal');

            // Handle Remember My Email preference
            const rememberMe = document.getElementById('login-remember-me')?.checked;
            if (rememberMe) {
                localStorage.setItem('worksheet_remembered_email', email);
            } else {
                localStorage.removeItem('worksheet_remembered_email');
            }

            // Clear password field for security
            const passInput = document.getElementById('login-password');
            if (passInput) passInput.value = '';

            showToast(`Welcome back, ${data.user.name}!`, "success");

            // Redirect to appropriate workspace page if needed
            const isHrPage = window.location.pathname.includes('hr.php');
            if (selectedLoginWorkspace === 'hr' && !isHrPage) {
                window.location.href = 'hr.php';
                return;
            } else if (selectedLoginWorkspace === 'digital' && isHrPage) {
                window.location.href = 'index.php';
                return;
            }

            renderUserBar();
            loadUserData();
        } else {
            showToast(data.message || "Invalid credentials", "error");
        }
    } catch (err) {
        showToast("Login failed. Please verify MySQL database connection.", "error");
    }
}

async function handleLogout(e) {
    if (e) {
        e.preventDefault();
    }
    try {
        if (typeof saveLocalDraft === 'function') {
            saveLocalDraft();
        }
        if (typeof saveCurrentWorksheet === 'function') {
            await saveCurrentWorksheet(false, false);
        }
        await fetch('api/auth.php?action=logout');
    } catch (err) {}
    window.location.href = 'logout.php';
    return false;
}

function hasPermission(permKey) {
    if (!AppState.currentUser) return false;
    if (AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'hod') return true;
    return !!parseInt(AppState.currentUser[permKey] || 0);
}

async function loadUserData() {
    if (!AppState.currentUser) return;

    const isSuperAdmin = AppState.currentUser.role === 'super_admin';
    const isHod = AppState.currentUser.role === 'hod' || AppState.currentUser.role === 'admin';
    const canInspect = isSuperAdmin || isHod || hasPermission('can_inspect_sheets');
    const canManage = isSuperAdmin || isHod || hasPermission('can_manage_employees');
    const canAttendance = isSuperAdmin || isHod || hasPermission('can_view_attendance');
    const canReports = isSuperAdmin || isHod || hasPermission('can_view_reports');

    if (canInspect) {
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = '');
        document.querySelectorAll('.employee-only').forEach(el => el.style.display = 'none');
        populateAdminEmployeeSelector();
        if (typeof populateTaskEmployeeFilter === 'function') {
            populateTaskEmployeeFilter();
        }
        // Default inspected employee to first staff member of their department
        if (!AppState.adminSelectedEmpId) {
            const firstStaff = (AppState.employees || []).find(e => e.id !== AppState.currentUser.id && e.role !== 'super_admin');
            AppState.adminSelectedEmpId = firstStaff ? firstStaff.id : (AppState.employees[0] ? AppState.employees[0].id : null);
        }
        const adminSelect = document.getElementById('admin-employee-select');
        if (adminSelect && AppState.adminSelectedEmpId) adminSelect.value = AppState.adminSelectedEmpId;
    } else {
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.employee-only').forEach(el => el.style.display = '');
        AppState.adminSelectedEmpId = null;
    }

    // Toggle specific navigation tab buttons based on permissions
    const btnAttendance = document.querySelector('.nav-btn[data-tab="tab-attendance"]');
    if (btnAttendance) {
        btnAttendance.style.display = canAttendance ? '' : 'none';
    }

    const btnEmployees = document.querySelector('.nav-btn[data-tab="tab-employees"]');
    if (btnEmployees) {
        btnEmployees.style.display = canManage ? '' : 'none';
    }

    const btnReports = document.querySelector('.nav-btn[data-tab="tab-reports"]');
    if (btnReports) {
        btnReports.style.display = canReports ? '' : 'none';
    }

    const portalSwitcher = document.querySelector('.portal-switcher-wrapper');
    if (portalSwitcher) {
        // Portal switcher available for Super Admin, Admin, and HR Managers
        const canSwitchPortal = isSuperAdmin || AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'hr' || hasPermission('can_manage_hr');
        portalSwitcher.style.display = canSwitchPortal ? '' : 'none';
    }

    await loadDailyWorksheet();
    await loadAssignedTasks();
    await updateGlobalSidebarBadges();
    if (typeof loadHrDashboard === 'function') {
        loadHrDashboard();
    }
    if (isAdmin || canAttendance) {
        loadLiveAttendance();
    }
    if (isAdmin || canReports) {
        loadReports();
    }
    if (isAdmin || canManage) {
        loadEmployeeDirectory();
    }
}

async function updateGlobalSidebarBadges() {
    try {
        // 1. News Room Handover Pending Badge
        const newsBadges = [document.getElementById('pending-news-badge'), document.getElementById('pending-news-badge-nr')].filter(Boolean);
        if (newsBadges.length > 0) {
            const newsRes = await fetch('api/newsroom.php?action=list');
            const newsData = await newsRes.json();
            if (newsData.success && newsData.stats) {
                const count = parseInt(newsData.stats.pending_queue) || 0;
                newsBadges.forEach(badge => {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'inline-block' : 'none';
                });
            }
        }

        // 2. Programming Handover Pending Badge
        const progBadges = [document.getElementById('pending-programming-badge'), document.getElementById('pending-prog-badge-pg')].filter(Boolean);
        if (progBadges.length > 0) {
            const progRes = await fetch('api/programming.php?action=list');
            const progData = await progRes.json();
            if (progData.success && progData.stats) {
                const count = parseInt(progData.stats.pending_queue) || 0;
                progBadges.forEach(badge => {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'inline-block' : 'none';
                });
            }
        }
    } catch (e) {
        // Ignore background badge errors
    }
}

function populateAdminEmployeeSelector() {
    const select = document.getElementById('admin-employee-select');
    const selectedNameSpan = document.getElementById('admin-emp-dropdown-selected-name');

    if (select) {
        select.innerHTML = '';
        AppState.employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = emp.name;
            if (AppState.adminSelectedEmpId == emp.id) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    renderAdminEmpDropdownList(AppState.employees);

    // Update selected employee label in dropdown button
    const currentSelectedEmp = AppState.employees.find(e => e.id == AppState.adminSelectedEmpId);
    if (selectedNameSpan && currentSelectedEmp) {
        selectedNameSpan.textContent = currentSelectedEmp.name;
    }
}

function renderAdminEmpDropdownList(employees) {
    const list = document.getElementById('admin-emp-dropdown-list');
    if (!list) return;

    list.innerHTML = '';
    if (!employees || employees.length === 0) {
        list.innerHTML = '<div style="padding: 10px; text-align: center; color: var(--text-muted); font-size: 12px;">No employee found</div>';
        return;
    }

    employees.forEach(emp => {
        const isSelected = AppState.adminSelectedEmpId == emp.id;
        const item = document.createElement('div');
        item.className = `searchable-emp-item ${isSelected ? 'selected' : ''}`;
        item.style.cssText = `padding: 6px 8px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 12.5px; transition: background 0.15s; ${isSelected ? 'background: rgba(59, 130, 246, 0.12); font-weight: 700;' : ''}`;
        item.onclick = () => selectAdminEmpFromDropdown(emp.id);

        const avatarInitial = emp.name ? emp.name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = emp.avatar 
            ? `<img src="${escapeHtml(emp.avatar)}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.onerror=null; this.outerHTML='<div style=\\'width:24px;height:24px;border-radius:50%;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;\\'>${escapeHtml(avatarInitial)}</div>';">`
            : `<div style="width: 24px; height: 24px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0;">${escapeHtml(avatarInitial)}</div>`;

        item.innerHTML = `
            ${avatarHtml}
            <div style="flex-grow: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <div style="color: var(--text-main); line-height: 1.2;">${escapeHtml(emp.name)}</div>
                <div style="font-size: 10.5px; color: var(--text-muted); font-weight: normal;">${escapeHtml(emp.team_name || emp.department_name || 'Staff')}</div>
            </div>
            ${isSelected ? '<span style="color: var(--primary); font-size: 12px; font-weight: 800;">✓</span>' : ''}
        `;
        list.appendChild(item);
    });
}

function filterAdminEmpDropdown(query) {
    const q = (query || '').trim().toLowerCase();
    if (!q) {
        renderAdminEmpDropdownList(AppState.employees);
        return;
    }
    const filtered = AppState.employees.filter(emp => 
        emp.name.toLowerCase().includes(q) || 
        (emp.team_name && emp.team_name.toLowerCase().includes(q)) ||
        (emp.department_name && emp.department_name.toLowerCase().includes(q))
    );
    renderAdminEmpDropdownList(filtered);
}

function toggleAdminEmpDropdown() {
    const menu = document.getElementById('admin-emp-dropdown-menu');
    const input = document.getElementById('admin-emp-search-input');
    if (!menu) return;

    const isVisible = menu.style.display === 'block';
    if (isVisible) {
        menu.style.display = 'none';
    } else {
        menu.style.display = 'block';
        if (input) {
            input.value = '';
            filterAdminEmpDropdown('');
            setTimeout(() => input.focus(), 60);
        }
    }
}

function selectAdminEmpFromDropdown(empId) {
    const menu = document.getElementById('admin-emp-dropdown-menu');
    if (menu) menu.style.display = 'none';

    AppState.adminSelectedEmpId = parseInt(empId);

    const select = document.getElementById('admin-employee-select');
    if (select) select.value = empId;

    const currentSelectedEmp = AppState.employees.find(e => e.id == empId);
    const selectedNameSpan = document.getElementById('admin-emp-dropdown-selected-name');
    if (selectedNameSpan && currentSelectedEmp) {
        selectedNameSpan.textContent = currentSelectedEmp.name;
    }

    renderAdminEmpDropdownList(AppState.employees);
    loadDailyWorksheet();
}

// Global click outside listener to auto-close dropdown
document.addEventListener('click', (e) => {
    const adminWrapper = document.getElementById('admin-emp-search-wrapper');
    const adminMenu = document.getElementById('admin-emp-dropdown-menu');
    if (adminWrapper && adminMenu && !adminWrapper.contains(e.target)) {
        adminMenu.style.display = 'none';
    }

    const repAttWrapper = document.getElementById('rep-att-emp-search-wrapper');
    const repAttMenu = document.getElementById('rep-att-emp-dropdown-menu');
    if (repAttWrapper && repAttMenu && !repAttWrapper.contains(e.target)) {
        repAttMenu.style.display = 'none';
    }

    const taskWrapper = document.getElementById('task-emp-search-wrapper');
    const taskMenu = document.getElementById('task-emp-dropdown-menu');
    if (taskWrapper && taskMenu && !taskWrapper.contains(e.target)) {
        taskMenu.style.display = 'none';
    }

    const loginWrapper = document.getElementById('login-emp-search-wrapper');
    const loginMenu = document.getElementById('login-emp-dropdown-menu');
    if (loginWrapper && loginMenu && !loginWrapper.contains(e.target)) {
        loginMenu.style.display = 'none';
    }

    const portalWrapper = document.querySelector('.portal-switcher-wrapper');
    const portalMenu = document.getElementById('portal-dropdown-menu');
    if (portalWrapper && portalMenu && !portalWrapper.contains(e.target)) {
        portalMenu.style.display = 'none';
    }
});

function renderUserBar() {
    const user = AppState.currentUser;
    if (!user) return;

    const roleLabels = {
        admin: 'SUPER ADMIN',
        hod: 'HOD / MANAGER',
        team_lead: 'TEAM LEAD',
        coordinator: 'COORDINATOR',
        employee: 'STAFF MEMBER'
    };

    const roleTitle = roleLabels[user.role] || (user.role ? user.role.toUpperCase() : 'STAFF MEMBER');
    document.getElementById('nav-user-name').textContent = user.name;
    document.getElementById('nav-user-role').textContent = `${roleTitle} (${user.designation || 'Staff'})`;
    document.getElementById('nav-user-role').className = `user-role-tag ${user.role}`;

    const navAvatar = document.getElementById('nav-user-avatar');
    if (navAvatar) {
        if (user.avatar) {
            navAvatar.innerHTML = `<img src="${escapeHtml(user.avatar)}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" alt="${escapeHtml(user.name)}" onerror="this.outerHTML='${escapeHtml(user.name.charAt(0).toUpperCase())}';">`;
        } else {
            navAvatar.textContent = user.name.charAt(0).toUpperCase();
        }
    }

    // Dynamic Tab & Section Labeling
    const mainNavIcon = document.getElementById('main-nav-tab-icon');
    const navTabLabel = document.getElementById('main-nav-tab-label');
    const tasksNavIcon = document.getElementById('tasks-nav-tab-icon');
    const tasksNavLabel = document.getElementById('tasks-nav-tab-label');
    const wsHeading = document.getElementById('worksheet-title-heading');
    const wsDesc = document.getElementById('worksheet-title-desc');
    const sideHeading = document.getElementById('sidebar-tasks-heading');
    const sideDesc = document.getElementById('sidebar-tasks-desc');
    const tasksPageTitle = document.getElementById('tasks-page-title');
    const tasksPageDesc = document.getElementById('tasks-page-desc');

    const canInspect = hasPermission('can_inspect_sheets');
    const canAssign = user.role === 'admin' || hasPermission('can_assign_tasks');

    if (user.role === 'admin' || user.role === 'super_admin' || canInspect) {
        if (mainNavIcon) mainNavIcon.textContent = '🏠';
        if (navTabLabel) navTabLabel.textContent = 'Dashboard';
        if (wsHeading) wsHeading.textContent = 'Employee Worksheet Inspector & Live Editor';
        if (wsDesc) wsDesc.textContent = 'Reviewing, editing, and managing daily work entries for team members.';
        if (sideHeading) sideHeading.textContent = '🎯 Team Tasks & Quick Actions';
        if (sideDesc) sideDesc.textContent = 'Monitor team tasks or assign new tasks to creators.';
    } else {
        if (mainNavIcon) mainNavIcon.textContent = '📝';
        if (navTabLabel) navTabLabel.textContent = 'Hourly Sheet';
        if (wsHeading) wsHeading.textContent = 'Hourly Work Log Sheet';
        if (wsDesc) wsDesc.textContent = 'Record your work batches, content types, departments, and links.';
        if (sideHeading) sideHeading.textContent = '🎯 My Assigned Tasks';
        if (sideDesc) sideDesc.textContent = 'Tasks assigned exclusively to you. Click "+ Add to Sheet" when done.';
    }

    if (canAssign) {
        if (tasksNavIcon) tasksNavIcon.textContent = '🎯';
        if (tasksNavLabel) tasksNavLabel.textContent = 'Task Assigner';
        if (tasksPageTitle) tasksPageTitle.textContent = '🎯 Task Assignment Command Center';
        if (tasksPageDesc) tasksPageDesc.textContent = 'Assign specific tasks to individual team members, filter by status, and monitor real-time completion.';
    } else {
        if (tasksNavIcon) tasksNavIcon.textContent = '🎯';
        if (tasksNavLabel) tasksNavLabel.textContent = 'Assigned Tasks';
        if (tasksPageTitle) tasksPageTitle.textContent = '🎯 My Assigned Tasks';
        if (tasksPageDesc) tasksPageDesc.textContent = 'View your assigned tasks, access project paths/links, and track completion.';
    }

    // User Badge Interactivity: Opens My Profile & Settings for all users
    const userBadge = document.getElementById('nav-user-badge');
    if (userBadge) {
        userBadge.style.cursor = 'pointer';
        userBadge.title = 'Click to edit your profile, photo & settings';
    }

    // Sheet Hero Info
    const heroName = document.getElementById('hero-emp-name');
    if (heroName) heroName.textContent = user.name;

    const heroMeta = document.getElementById('hero-emp-meta');
    if (heroMeta) heroMeta.textContent = `${user.designation || 'Staff'} • ${user.department_name || 'Digital'} • ${user.team_name || 'Team'}`;

    const heroAvatar = document.getElementById('hero-profile-avatar');
    if (heroAvatar) {
        if (user.avatar) {
            heroAvatar.innerHTML = `<img src="${escapeHtml(user.avatar)}" style="width: 100%; height: 100%; object-fit: cover;" alt="${escapeHtml(user.name)}" onerror="this.outerHTML='<span>${escapeHtml(user.name.charAt(0).toUpperCase())}</span>';">`;
        } else {
            heroAvatar.innerHTML = `<span>${escapeHtml(user.name.charAt(0).toUpperCase())}</span>`;
        }
    }
}

function handleUserBadgeClick() {
    if (typeof openMyProfileModal === 'function') {
        openMyProfileModal();
    }
}

function handleAdminSelectEmployee(empId) {
    AppState.adminSelectedEmpId = parseInt(empId);
    loadDailyWorksheet();
}

function toggleMobileSidebar() {
    document.body.classList.toggle('sidebar-open');
}

function closeMobileSidebar() {
    document.body.classList.remove('sidebar-open');
}

// Tab Navigation & URL Hash Routing
function navigateToTab(tabId, updateHash = true) {
    const targetSection = document.getElementById(tabId);
    if (!targetSection) return;

    closeMobileSidebar();

    const tabButtons = document.querySelectorAll('.nav-btn');
    tabButtons.forEach(b => {
        if (b.getAttribute('data-tab') === tabId) {
            b.classList.add('active');
            if (updateHash) {
                const route = b.getAttribute('data-route') || tabId.replace('tab-', '');
                history.replaceState(null, '', `#${route}`);
            }
        } else {
            b.classList.remove('active');
        }
    });

    document.querySelectorAll('.tab-section').forEach(sec => sec.classList.remove('active'));
    targetSection.classList.add('active');

    if (tabId === 'tab-reports') {
        loadReports();
    } else if (tabId === 'tab-attendance') {
        loadLiveAttendance();
    } else if (tabId === 'tab-hr') {
        if (typeof loadHrDashboard === 'function') loadHrDashboard();
    } else if (tabId === 'tab-hr-leaves') {
        if (typeof loadHrLeaves === 'function') loadHrLeaves();
    } else if (tabId === 'tab-hr-loans') {
        if (typeof loadHrLoans === 'function') loadHrLoans();
    } else if (tabId === 'tab-hr-notices') {
        if (typeof loadHrNotices === 'function') loadHrNotices();
    } else if (tabId === 'tab-hr-payroll') {
        if (typeof loadHrPayroll === 'function') loadHrPayroll();
    } else if (tabId === 'tab-employees') {
        loadEmployeeDirectory();
    } else if (tabId === 'tab-tasks') {
        loadAssignedTasks();
    } else if (tabId === 'tab-newsroom') {
        if (typeof initNewsroomModule === 'function') initNewsroomModule();
        else if (typeof loadNewsroomDispatches === 'function') loadNewsroomDispatches();
    } else if (tabId === 'tab-programming') {
        if (typeof initProgrammingModule === 'function') initProgrammingModule();
        else if (typeof loadProgrammingDispatches === 'function') loadProgrammingDispatches();
    }
}

function setupTabNavigation() {
    const tabButtons = document.querySelectorAll('.nav-btn');
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetTab = btn.getAttribute('data-tab');
            if (targetTab) {
                navigateToTab(targetTab, true);
            }
        });
    });

    // Handle browser hash navigation (initial load & back/forward history)
    function handleHashChange() {
        const hash = window.location.hash.replace('#', '').trim();
        if (hash) {
            const matchedBtn = document.querySelector(`.nav-btn[data-route="${hash}"]`) || document.querySelector(`.nav-btn[data-tab="tab-${hash}"]`);
            if (matchedBtn) {
                const tabId = matchedBtn.getAttribute('data-tab');
                navigateToTab(tabId, false);
                return;
            }
        }

        // Default: activate whichever tab button has .active on page render
        const defaultActiveBtn = document.querySelector('.nav-btn.active');
        if (defaultActiveBtn) {
            const tabId = defaultActiveBtn.getAttribute('data-tab');
            if (tabId) navigateToTab(tabId, false);
        }
    }

    window.addEventListener('hashchange', handleHashChange);
    // Initial check on load
    handleHashChange();
}

function openModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.add('active');
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove('active');
}

function parseDateTime(dateStr, timeStr) {
    if (!dateStr || !timeStr) return null;
    let d = new Date(`${dateStr} ${timeStr}`);
    if (!isNaN(d.getTime())) return d;
    
    // Parse manual formats like 'HH:mm:ss', 'HH:mm', 'hh:mm A', etc.
    const match = timeStr.trim().match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i);
    if (match) {
        let [_, hour, minute, second, meridiem] = match;
        hour = parseInt(hour, 10);
        minute = parseInt(minute, 10);
        second = second ? parseInt(second, 10) : 0;
        if (meridiem) {
            if (meridiem.toUpperCase() === 'PM' && hour < 12) hour += 12;
            if (meridiem.toUpperCase() === 'AM' && hour === 12) hour = 0;
        }
        const [y, m, day] = dateStr.split('-').map(n => parseInt(n, 10));
        return new Date(y, m - 1, day, hour, minute, second);
    }
    return null;
}

function startLiveTimer(initialSeconds = 0, checkInTimeStr = null, shiftDateStr = null) {
    stopLiveTimer();
    
    let startTimestamp = null;
    if (shiftDateStr && checkInTimeStr) {
        const inDate = parseDateTime(shiftDateStr, checkInTimeStr);
        if (inDate && !isNaN(inDate.getTime())) {
            startTimestamp = inDate.getTime();
        }
    }
    
    if (!startTimestamp) {
        startTimestamp = Date.now() - (Math.max(0, initialSeconds) * 1000);
    }
    
    AppState.timerStartTimestamp = startTimestamp;

    const tick = () => {
        if (!AppState.timerStartTimestamp) return;
        const now = Date.now();
        const elapsed = Math.max(0, Math.floor((now - AppState.timerStartTimestamp) / 1000));
        AppState.elapsedSeconds = elapsed;
        const timerElem = document.getElementById('duty-timer-digits');
        if (timerElem) {
            timerElem.textContent = formatDuration(elapsed);
        }
    };

    tick();
    AppState.timerInterval = setInterval(tick, 1000);
}

function stopLiveTimer() {
    if (AppState.timerInterval) {
        clearInterval(AppState.timerInterval);
        AppState.timerInterval = null;
    }
    AppState.timerStartTimestamp = null;
}

// Auto-resync timer immediately when switching tabs or focusing window
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && AppState.timerStartTimestamp) {
        const now = Date.now();
        const elapsed = Math.max(0, Math.floor((now - AppState.timerStartTimestamp) / 1000));
        AppState.elapsedSeconds = elapsed;
        const timerElem = document.getElementById('duty-timer-digits');
        if (timerElem) {
            timerElem.textContent = formatDuration(elapsed);
        }
    }
});

window.addEventListener('focus', () => {
    if (AppState.timerStartTimestamp) {
        const now = Date.now();
        const elapsed = Math.max(0, Math.floor((now - AppState.timerStartTimestamp) / 1000));
        AppState.elapsedSeconds = elapsed;
        const timerElem = document.getElementById('duty-timer-digits');
        if (timerElem) {
            timerElem.textContent = formatDuration(elapsed);
        }
    }
});

function applyTheme(theme) {
    document.body.setAttribute('data-theme', theme);
    localStorage.setItem('worksheet_theme', theme);
    
    // Update theme toggle icons
    const themeIcon = theme === 'dark' ? '☀️' : '🌙';
    const themeLabel = theme === 'dark' ? 'Light' : 'Dark';
    
    const themeBtn = document.getElementById('theme-toggle-btn');
    if (themeBtn) {
        const iconSpan = themeBtn.querySelector('.theme-icon');
        const labelSpan = themeBtn.querySelector('.theme-label');
        if (iconSpan) iconSpan.textContent = themeIcon;
        if (labelSpan) labelSpan.textContent = themeLabel;
        if (!iconSpan && !labelSpan) themeBtn.textContent = themeIcon;
    }
    
    document.querySelectorAll('.theme-icon-indicator').forEach(el => {
        el.textContent = themeIcon;
    });
}

function toggleThemeQuick() {
    const currentTheme = document.body.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme);
}

document.addEventListener('DOMContentLoaded', () => {
    // Ensure Light Mode by default unless user prefers dark
    const savedTheme = localStorage.getItem('worksheet_theme') || 'light';
    applyTheme(savedTheme);

    const themeBtn = document.getElementById('theme-toggle-btn');
    if (themeBtn) {
        themeBtn.addEventListener('click', toggleThemeQuick);
    }

    initApp();
    updateGlobalSidebarBadges();

    const dateInput = document.getElementById('worksheet-date-picker');
    if (dateInput) {
        dateInput.value = AppState.selectedDate;
        dateInput.addEventListener('change', (e) => {
            AppState.selectedDate = e.target.value;
            loadDailyWorksheet();
        });
    }

    // Keep session alive and refresh sidebar queue badges periodically (every 30s)
    setInterval(async () => {
        if (AppState.currentUser) {
            try {
                await fetch('api/auth.php?action=current_user');
            } catch (e) {}
            updateGlobalSidebarBadges();
        }
    }, 30000);

    // Save offline draft on window close / tab navigation
    window.addEventListener('beforeunload', () => {
        if (typeof saveLocalDraft === 'function') {
            saveLocalDraft();
        }
    });
});
