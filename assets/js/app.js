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
            closeModal('login-modal');
            renderUserBar();
            loadUserData();
        } else {
            // Show mandatory login modal
            openModal('login-modal');
        }

        setupTabNavigation();

    } catch (err) {
        console.error("App initialization failed:", err);
        showToast("Error connecting to server / database.", "error");
    }
}

function populateEmailSelectors() {
    const loginEmailSelect = document.getElementById('login-quick-email');
    if (loginEmailSelect) {
        loginEmailSelect.innerHTML = '<option value="">-- Or Choose Employee Account --</option>';
        AppState.employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.email;
            opt.textContent = `${emp.name} — ${emp.email} [${emp.team_name || 'Team'}]`;
            loginEmailSelect.appendChild(opt);
        });
    }

    if (typeof populateTaskEmployeeFilter === 'function') {
        populateTaskEmployeeFilter();
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
            showToast(`Welcome back, ${data.user.name}!`, "success");
            renderUserBar();
            loadUserData();
        } else {
            showToast(data.message || "Invalid credentials", "error");
        }
    } catch (err) {
        showToast("Login failed. Please verify MySQL database connection.", "error");
    }
}

async function handleLogout() {
    try {
        await fetch('api/auth.php?action=logout');
        AppState.currentUser = null;
        stopLiveTimer();
        showToast("Logged out successfully.", "info");
        openModal('login-modal');
    } catch (err) {
        location.reload();
    }
}

function hasPermission(permKey) {
    if (!AppState.currentUser) return false;
    if (AppState.currentUser.role === 'admin') return true;
    return !!parseInt(AppState.currentUser[permKey] || 0);
}

async function loadUserData() {
    if (!AppState.currentUser) return;

    const isAdmin = AppState.currentUser.role === 'admin';
    const canInspect = hasPermission('can_inspect_sheets');
    const canManage = hasPermission('can_manage_employees');
    const canAttendance = hasPermission('can_view_attendance');
    const canReports = hasPermission('can_view_reports');

    if (isAdmin || canInspect) {
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = '');
        document.querySelectorAll('.employee-only').forEach(el => el.style.display = 'none');
        populateAdminEmployeeSelector();
        if (typeof populateTaskEmployeeFilter === 'function') {
            populateTaskEmployeeFilter();
        }
        // Default inspected employee to first staff member (e.g. Zahra Kazmi id=2 or Abaid)
        if (!AppState.adminSelectedEmpId) {
            const firstStaff = AppState.employees.find(e => e.role !== 'admin');
            AppState.adminSelectedEmpId = firstStaff ? firstStaff.id : 2;
        }
        const adminSelect = document.getElementById('admin-employee-select');
        if (adminSelect) adminSelect.value = AppState.adminSelectedEmpId;
    } else {
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.employee-only').forEach(el => el.style.display = '');
        AppState.adminSelectedEmpId = null;
    }

    // Toggle specific navigation tab buttons based on permissions
    const btnAttendance = document.querySelector('.nav-btn[data-tab="tab-attendance"]');
    if (btnAttendance) {
        btnAttendance.style.display = (isAdmin || canAttendance) ? '' : 'none';
    }

    const btnEmployees = document.querySelector('.nav-btn[data-tab="tab-employees"]');
    if (btnEmployees) {
        btnEmployees.style.display = (isAdmin || canManage) ? '' : 'none';
    }

    const btnReports = document.querySelector('.nav-btn[data-tab="tab-reports"]');
    if (btnReports) {
        btnReports.style.display = (isAdmin || canReports) ? '' : 'none';
    }

    await loadDailyWorksheet();
    await loadAssignedTasks();
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
    const wrapper = document.getElementById('admin-emp-search-wrapper');
    const menu = document.getElementById('admin-emp-dropdown-menu');
    if (wrapper && menu && !wrapper.contains(e.target)) {
        menu.style.display = 'none';
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
    const navTabLabel = document.getElementById('main-nav-tab-label');
    const wsHeading = document.getElementById('worksheet-title-heading');
    const wsDesc = document.getElementById('worksheet-title-desc');
    const sideHeading = document.getElementById('sidebar-tasks-heading');
    const sideDesc = document.getElementById('sidebar-tasks-desc');

    const canInspect = hasPermission('can_inspect_sheets');

    if (user.role === 'admin' || canInspect) {
        if (navTabLabel) navTabLabel.innerHTML = '🏠 Dashboard';
        if (wsHeading) wsHeading.textContent = 'Employee Worksheet Inspector & Live Editor';
        if (wsDesc) wsDesc.textContent = 'Reviewing, editing, and managing daily work entries for team members.';
        if (sideHeading) sideHeading.textContent = '🎯 Team Tasks & Quick Actions';
        if (sideDesc) sideDesc.textContent = 'Monitor team tasks or assign new tasks to creators.';
    } else {
        if (navTabLabel) navTabLabel.innerHTML = '📝 Hourly Sheet';
        if (wsHeading) wsHeading.textContent = 'Hourly Work Log Sheet';
        if (wsDesc) wsDesc.textContent = 'Record your work batches, content types, departments, and links.';
        if (sideHeading) sideHeading.textContent = '🎯 My Assigned Tasks';
        if (sideDesc) sideDesc.textContent = 'Tasks assigned exclusively to you. Click "+ Add to Sheet" when done.';
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

// Tab Navigation & URL Hash Routing
function navigateToTab(tabId, updateHash = true) {
    const targetSection = document.getElementById(tabId);
    if (!targetSection) return;

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
    } else if (tabId === 'tab-employees') {
        loadEmployeeDirectory();
    } else if (tabId === 'tab-tasks') {
        loadAssignedTasks();
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
            }
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

function startLiveTimer(initialSeconds = 0) {
    stopLiveTimer();
    AppState.elapsedSeconds = initialSeconds;
    const timerElem = document.getElementById('duty-timer-digits');
    if (timerElem) timerElem.textContent = formatDuration(AppState.elapsedSeconds);

    AppState.timerInterval = setInterval(() => {
        AppState.elapsedSeconds++;
        if (timerElem) timerElem.textContent = formatDuration(AppState.elapsedSeconds);
    }, 1000);
}

function stopLiveTimer() {
    if (AppState.timerInterval) {
        clearInterval(AppState.timerInterval);
        AppState.timerInterval = null;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Ensure Light Mode by default
    const savedTheme = localStorage.getItem('worksheet_theme') || 'light';
    document.body.setAttribute('data-theme', savedTheme);

    const themeBtn = document.getElementById('theme-toggle-btn');
    if (themeBtn) {
        themeBtn.textContent = savedTheme === 'dark' ? '🌙' : '☀️';
        themeBtn.addEventListener('click', () => {
            const currentTheme = document.body.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('worksheet_theme', newTheme);
            themeBtn.textContent = newTheme === 'dark' ? '🌙' : '☀️';
        });
    }

    initApp();

    const dateInput = document.getElementById('worksheet-date-picker');
    if (dateInput) {
        dateInput.value = AppState.selectedDate;
        dateInput.addEventListener('change', (e) => {
            AppState.selectedDate = e.target.value;
            loadDailyWorksheet();
        });
    }
});
