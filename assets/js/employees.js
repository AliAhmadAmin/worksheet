/**
 * Employee Management & Department/Team Organizational Hierarchy
 */

let currentEmployeeSubView = 'directory';

function switchEmployeeSubView(viewName) {
    currentEmployeeSubView = viewName;
    const dirSub = document.getElementById('emp-subview-directory');
    const deptSub = document.getElementById('emp-subview-departments');
    const btnDir = document.getElementById('btn-emp-subtab-directory');
    const btnDept = document.getElementById('btn-emp-subtab-departments');

    if (viewName === 'departments') {
        if (dirSub) dirSub.style.display = 'none';
        if (deptSub) deptSub.style.display = '';
        if (btnDir) { btnDir.className = 'btn btn-outline'; }
        if (btnDept) { btnDept.className = 'btn btn-primary'; }
        renderDepartmentsView();
    } else {
        if (dirSub) dirSub.style.display = '';
        if (deptSub) deptSub.style.display = 'none';
        if (btnDir) { btnDir.className = 'btn btn-primary'; }
        if (btnDept) { btnDept.className = 'btn btn-outline'; }
        applyDirectoryFilters();
    }

    renderEmployeeViewControls();
}

function renderEmployeeViewControls() {
    const controls = document.getElementById('emp-view-controls');
    if (!controls) return;

    const isSuperAdmin = AppState.currentUser && (AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'admin');
    const isHr = AppState.currentUser && (AppState.currentUser.role === 'hr' || (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase() === 'hr') || AppState.currentUser.can_manage_hr);
    const isHod = AppState.currentUser && AppState.currentUser.role === 'hod';
    const canManage = isSuperAdmin || isHr || (AppState.currentUser && AppState.currentUser.can_manage_employees);

    controls.innerHTML = '';

    if (currentEmployeeSubView === 'directory') {
        if (canManage || isHr) {
            controls.innerHTML += `
                <button type="button" class="btn btn-primary" onclick="openAddEmployeeModal()">
                    ➕ Add New Employee
                </button>
            `;
        }
        controls.innerHTML += `
            <button type="button" class="btn btn-outline" onclick="loadEmployeeDirectory()">
                🔄 Refresh
            </button>
        `;
    } else {
        if (canManage || isHr) {
            controls.innerHTML += `
                <button type="button" class="btn btn-primary" onclick="openAddDepartmentModal()" style="background: #2563eb; border-color: #2563eb;">
                    🏢 Add Department
                </button>
            `;
        }
        if (canManage || isHr || isHod) {
            controls.innerHTML += `
                <button type="button" class="btn btn-outline" onclick="openAddTeamModal()" style="border-color: #10b981; color: #059669; font-weight: 700;">
                    👥 Add Team
                </button>
            `;
        }
        controls.innerHTML += `
            <button type="button" class="btn btn-outline" onclick="loadEmployeeDirectory()">
                🔄 Refresh Hierarchy
            </button>
        `;
    }
}

async function loadEmployeeDirectory() {
    try {
        const res = await fetch('api/employees.php?action=list&include_inactive=1');
        const data = await res.json();

        if (!data.success) return;

        AppState.employees = data.employees || [];
        AppState.departments = data.departments || [];
        AppState.teams = data.teams || [];

        // Update counts (count active staff)
        const totalBadge = document.getElementById('emp-total-badge');
        if (totalBadge) {
            const activeCount = (AppState.employees || []).filter(e => e.is_active != 0).length;
            totalBadge.textContent = activeCount;
        }

        const deptBadge = document.getElementById('dept-total-badge');
        if (deptBadge) deptBadge.textContent = AppState.departments.length;

        populateDirectoryDeptFilter();

        if (currentEmployeeSubView === 'departments') {
            renderDepartmentsView();
        } else {
            applyDirectoryFilters();
        }

        renderEmployeeViewControls();
    } catch (err) {
        console.error("Error loading employee directory:", err);
    }
}

function populateDirectoryDeptFilter() {
    const filterDept = document.getElementById('emp-dir-filter-dept');
    if (!filterDept) return;

    const currentVal = filterDept.value;
    filterDept.innerHTML = '<option value="">🏢 All Departments</option>';

    (AppState.departments || []).forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.id;
        opt.textContent = `🏢 ${d.name} (${d.member_count || 0})`;
        filterDept.appendChild(opt);
    });

    if (currentVal) filterDept.value = currentVal;
}

function resetDirectoryFilters() {
    const searchInput = document.getElementById('emp-dir-search');
    const deptSelect = document.getElementById('emp-dir-filter-dept');
    const teamSelect = document.getElementById('emp-dir-filter-team');
    const statusSelect = document.getElementById('emp-dir-filter-status');
    const loginSelect = document.getElementById('emp-dir-filter-login');

    if (searchInput) searchInput.value = '';
    if (deptSelect) deptSelect.value = '';
    if (teamSelect) teamSelect.value = '';
    if (statusSelect) statusSelect.value = 'active';
    if (loginSelect) loginSelect.value = '';

    applyDirectoryFilters();
}

function applyDirectoryFilters() {
    const deptId = document.getElementById('emp-dir-filter-dept')?.value;
    const teamStatus = document.getElementById('emp-dir-filter-team')?.value;
    const statusVal = document.getElementById('emp-dir-filter-status')?.value || 'active';
    const loginStatus = document.getElementById('emp-dir-filter-login')?.value;
    const search = (document.getElementById('emp-dir-search')?.value || '').toLowerCase().trim();

    // Toggle reset button visibility
    const resetBtn = document.getElementById('emp-dir-reset-filters-btn');
    if (resetBtn) {
        const isFiltered = !!(deptId || teamStatus || (statusVal && statusVal !== 'active') || loginStatus || search);
        resetBtn.style.display = isFiltered ? 'inline-flex' : 'none';
    }

    let list = AppState.employees || [];

    if (statusVal === 'active') {
        list = list.filter(e => e.is_active != 0);
    } else if (statusVal === 'inactive') {
        list = list.filter(e => e.is_active == 0);
    }

    if (deptId) {
        list = list.filter(e => String(e.department_id) === String(deptId));
    }

    if (teamStatus === 'unassigned') {
        list = list.filter(e => !e.team_id || e.team_id == 0);
    } else if (teamStatus === 'assigned') {
        list = list.filter(e => e.team_id && e.team_id > 0);
    }

    if (loginStatus === 'login_enabled') {
        list = list.filter(e => e.can_login != 0);
    } else if (loginStatus === 'roster_only') {
        list = list.filter(e => e.can_login == 0);
    }

    if (search) {
        list = list.filter(e => 
            (e.name || '').toLowerCase().includes(search) ||
            (e.email || '').toLowerCase().includes(search) ||
            (e.designation || '').toLowerCase().includes(search) ||
            (e.department_name || '').toLowerCase().includes(search) ||
            (e.team_name || '').toLowerCase().includes(search)
        );
    }

    renderDirectoryTable(list);
}

function renderDirectoryTable(employees) {
    const tbody = document.getElementById('employee-directory-tbody');
    if (!tbody) return;

    tbody.innerHTML = '';
    if (!employees || employees.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">No employees match the current filter criteria.</td></tr>';
        return;
    }

    const isSuperAdmin = AppState.currentUser && (AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'admin');
    const isHr = AppState.currentUser && (AppState.currentUser.role === 'hr' || (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase() === 'hr'));
    const isHod = AppState.currentUser && AppState.currentUser.role === 'hod';
    const userDeptId = AppState.currentUser ? parseInt(AppState.currentUser.department_id || 0, 10) : 0;

    employees.forEach(emp => {
        const tr = document.createElement('tr');
        const isSelf = AppState.currentUser && AppState.currentUser.id === emp.id;
        const hasLogin = (emp.can_login != 0 && emp.can_login !== '0');
        const isActive = (emp.is_active != 0 && emp.is_active !== '0');

        if (!isActive) {
            tr.style.opacity = '0.78';
            tr.style.background = 'rgba(0,0,0,0.015)';
        }

        const roleConfig = {
            super_admin: { label: '👑 Super Admin', bg: 'rgba(56, 189, 248, 0.15)', color: '#0284c7' },
            admin: { label: '👑 Super Admin', bg: 'rgba(56, 189, 248, 0.15)', color: '#0284c7' },
            hr: { label: '👥 HR Manager', bg: 'rgba(236, 72, 153, 0.15)', color: '#db2777' },
            hod: { label: '🏢 HOD / Manager', bg: 'rgba(245, 158, 11, 0.15)', color: '#d97706' },
            team_lead: { label: '⭐ Team Lead', bg: 'rgba(16, 185, 129, 0.15)', color: '#059669' },
            coordinator: { label: '🎯 Coordinator', bg: 'rgba(59, 130, 246, 0.15)', color: '#2563eb' },
            employee: { label: '👤 Staff Member', bg: 'rgba(148, 163, 184, 0.15)', color: '#64748b' }
        };

        const roleMeta = roleConfig[emp.role] || roleConfig.employee;
        const roleBadge = hasLogin
            ? `<span class="user-role-tag" style="background: ${roleMeta.bg}; color: ${roleMeta.color}; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; white-space: nowrap; display: inline-block;">${roleMeta.label}</span>`
            : `<span class="user-role-tag" style="background: rgba(148, 163, 184, 0.15); color: #64748b; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 11px; white-space: nowrap; display: inline-block;">👤 Roster Only</span>`;

        const avatarHtml = emp.avatar 
            ? `<img src="${escapeHtml(emp.avatar)}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width: 32px; height: 32px; font-size: 12px;\\'>${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>';">`
            : `<div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px; flex-shrink: 0;">${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>`;

        const isFlexible = (emp.shift_policy === 'open_flexible' || parseFloat(emp.expected_hours || 8) === 0);
        const shiftBadge = isFlexible
            ? `<span style="display: inline-block; font-size: 10.5px; color: #0369a1; background: #e0f2fe; padding: 2px 6px; border-radius: 4px; font-weight: 600; white-space: nowrap;">🌐 Flexible (Open)</span>`
            : `<span style="display: inline-block; font-size: 10.5px; color: #475569; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-weight: 600; white-space: nowrap;">⏱️ ${parseFloat(emp.expected_hours || 8)}h Shift</span>`;

        const statusBadge = !isActive 
            ? `<span class="badge" style="background: #fee2e2; color: #dc2626; font-size: 10px; font-weight: 700; white-space: nowrap;">⛔ Deactivated</span>`
            : '';

        const salaryBadge = (parseFloat(emp.basic_salary) > 0 && (isSuperAdmin || isHr))
            ? `<div style="font-size: 10.5px; color: #15803d; font-weight: 700; margin-top: 2px; white-space: nowrap;">💵 PKR ${Number(emp.basic_salary).toLocaleString()}</div>`
            : '';

        let teamHtml = '';
        const canAssignThisEmp = isSuperAdmin || isHr || (isHod && parseInt(emp.department_id || 0, 10) === userDeptId);
        const canEditThisEmp = isSuperAdmin || isHr || (isHod && parseInt(emp.department_id || 0, 10) === userDeptId);

        if (canEditThisEmp) {
            tr.style.cursor = 'pointer';
            tr.title = 'Click row to edit employee profile';
            tr.onclick = (e) => {
                if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || e.target.closest('select')) return;
                openEditEmployeeModal(emp.id);
            };
        }

        if (emp.team_name) {
            teamHtml = `<span style="font-size: 11px; color: var(--text-muted); font-weight: 600; white-space: nowrap;">👥 ${escapeHtml(emp.team_name)}</span>`;
        } else {
            teamHtml = `
                <span style="display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; color: #b45309; background: #fef3c7; padding: 1px 6px; border-radius: 4px; font-weight: 700; white-space: nowrap;">
                    ⚠️ Unassigned
                </span>
            `;
        }

        const emailDisplay = emp.email 
            ? `<code style="font-size: 12px; color: var(--text-muted);">${escapeHtml(emp.email)}</code>`
            : `<span style="font-size: 11.5px; color: var(--text-muted); font-style: italic;">No login email</span>`;

        tr.innerHTML = `
            <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                    ${avatarHtml}
                    <div>
                        <strong>${escapeHtml(emp.name)}</strong> ${isSelf ? '<small style="color: var(--primary); font-weight: 700;">(You)</small>' : ''}
                        <div style="display: flex; align-items: center; gap: 4px; margin-top: 3px; flex-wrap: wrap;">
                            ${shiftBadge}
                            ${statusBadge}
                        </div>
                    </div>
                </div>
            </td>
            <td>${emailDisplay}</td>
            <td>${roleBadge}</td>
            <td>
                <div style="font-weight: 500;">${escapeHtml(emp.designation || 'Staff')}</div>
                ${salaryBadge}
            </td>
            <td>
                <div style="font-weight: 600; font-size: 12.5px; color: var(--text-main); white-space: nowrap;">
                    🏢 ${escapeHtml(emp.department_name || 'General')}
                </div>
                <div style="display: flex; align-items: center; gap: 5px; margin-top: 3px;">
                    ${teamHtml}
                    ${canAssignThisEmp ? `
                        <button type="button" class="btn btn-outline" style="padding: 1px 5px; font-size: 9.5px; border-radius: 4px; line-height: 1;" onclick="event.stopPropagation(); openDepartmentDrilldown(${emp.department_id || 0})" title="Manage team assignment in department">
                            ⚙️
                        </button>
                    ` : ''}
                </div>
            </td>
            <td>
                <div style="display: flex; align-items: center; gap: 5px; justify-content: flex-end; flex-wrap: nowrap;">
                    ${(isSuperAdmin && hasLogin && isActive) ? `
                        <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; font-weight: 700; border-color: rgba(59, 130, 246, 0.4); color: var(--primary); white-space: nowrap;" onclick="event.stopPropagation(); openPermissionsModal(${emp.id})" title="Role & Access Delegation">
                            🛡️ Roles
                        </button>
                    ` : ''}
                    ${(hasLogin && isActive) ? `
                        <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; white-space: nowrap;" onclick="event.stopPropagation(); openPasswordModal(${emp.id})" title="Change Password">
                            🔑 Pwd
                        </button>
                    ` : ''}
                    ${((isSuperAdmin || isHr) && !isSelf && emp.role !== 'super_admin' && emp.role !== 'admin') ? (
                        isActive ? `
                            <button type="button" class="btn btn-outline" style="padding: 3px 7px; font-size: 11px; color: #d97706; border-color: rgba(217, 119, 6, 0.35); white-space: nowrap;" onclick="event.stopPropagation(); toggleEmployeeStatus(${emp.id}, 0)" title="Deactivate Employee (Preserves All History)">
                                ⏸️ Deactivate
                            </button>
                        ` : `
                            <button type="button" class="btn btn-outline" style="padding: 3px 7px; font-size: 11px; color: #16a34a; border-color: rgba(22, 163, 74, 0.35); font-weight: 700; white-space: nowrap;" onclick="event.stopPropagation(); toggleEmployeeStatus(${emp.id}, 1)" title="Reactivate Employee Account">
                                ▶️ Activate
                            </button>
                        `
                    ) : ''}
                    ${((isSuperAdmin || isHr) && !isSelf && emp.role !== 'super_admin') ? `
                        <button type="button" class="btn btn-outline" style="padding: 3px 7px; font-size: 11px; color: #dc2626; border-color: rgba(220, 38, 38, 0.35); white-space: nowrap;" onclick="event.stopPropagation(); deleteEmployeePermanent(${emp.id})" title="Permanently Delete Duplicate / Unwanted Employee">
                            🗑️
                        </button>
                    ` : ''}
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// ==========================================
// DEPARTMENTS & TEAMS HIERARCHY VIEW
// ==========================================

function renderDepartmentsView() {
    const container = document.getElementById('departments-grid-container');
    if (!container) return;

    container.innerHTML = '';
    const departments = AppState.departments || [];

    if (departments.length === 0) {
        container.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: var(--bg-card-elevated); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                <div style="font-size: 32px; margin-bottom: 8px;">🏢</div>
                <h4 style="margin: 0 0 6px 0; color: var(--text-main);">No Departments Created Yet</h4>
                <p style="margin: 0 0 16px 0; font-size: 13px; color: var(--text-muted);">HR Managers and Super Admins can add organizational departments.</p>
                <button type="button" class="btn btn-primary" onclick="openAddDepartmentModal()">➕ Create First Department</button>
            </div>
        `;
        return;
    }

    const isSuperAdmin = AppState.currentUser && (AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'admin');
    const isHr = AppState.currentUser && (AppState.currentUser.role === 'hr' || (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase() === 'hr'));
    const isHod = AppState.currentUser && AppState.currentUser.role === 'hod';
    const userDeptId = AppState.currentUser ? parseInt(AppState.currentUser.department_id || 0, 10) : 0;

    departments.forEach(dept => {
        const deptTeams = (AppState.teams || []).filter(t => parseInt(t.department_id, 10) === parseInt(dept.id, 10));
        const isMyDept = (isHod && userDeptId === parseInt(dept.id, 10)) || isSuperAdmin || isHr;

        const card = document.createElement('div');
        card.className = 'worksheet-card';
        card.style.margin = '0';
        card.style.padding = '18px';
        card.style.display = 'flex';
        card.style.flexDirection = 'column';
        card.style.justifyContent = 'space-between';
        card.style.border = isMyDept ? '1px solid rgba(37, 99, 235, 0.4)' : '1px solid var(--border-color)';
        card.style.boxShadow = isMyDept ? '0 4px 14px rgba(37, 99, 235, 0.08)' : 'none';

        let hodContent = '';
        if (dept.hod_name) {
            const hodAvatar = dept.hod_avatar
                ? `<img src="${escapeHtml(dept.hod_avatar)}" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover;" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width: 26px; height: 26px; font-size: 10px;\\'>${escapeHtml(dept.hod_name.charAt(0).toUpperCase())}</div>';">`
                : `<div class="user-avatar" style="width: 26px; height: 26px; font-size: 10px;">${escapeHtml(dept.hod_name.charAt(0).toUpperCase())}</div>`;

            hodContent = `
                <div style="display: flex; align-items: center; gap: 8px; background: rgba(245, 158, 11, 0.08); padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(245, 158, 11, 0.2);">
                    ${hodAvatar}
                    <div style="font-size: 12px; line-height: 1.2;">
                        <span style="color: #b45309; font-weight: 700;">HOD:</span> <strong>${escapeHtml(dept.hod_name)}</strong>
                    </div>
                </div>
            `;
        } else {
            hodContent = `
                <div style="font-size: 12px; color: var(--text-muted); background: var(--bg-card-elevated); padding: 6px 10px; border-radius: 6px; border: 1px dashed var(--border-color);">
                    👤 <em>No HOD Appointed</em>
                </div>
            `;
        }

        let teamsChipsHtml = '';
        if (deptTeams.length > 0) {
            teamsChipsHtml = deptTeams.slice(0, 4).map(t => 
                `<span style="display: inline-block; font-size: 11px; background: var(--bg-card-elevated); color: var(--text-main); border: 1px solid var(--border-color); padding: 3px 8px; border-radius: 5px; font-weight: 500;">
                    👥 ${escapeHtml(t.name)} <small style="color: var(--text-muted); font-weight: 700;">(${t.member_count || 0})</small>
                </span>`
            ).join('');
            if (deptTeams.length > 4) {
                teamsChipsHtml += `<span style="font-size: 11px; color: var(--primary); font-weight: 600; padding: 3px 4px;">+${deptTeams.length - 4} more</span>`;
            }
        } else {
            teamsChipsHtml = `<span style="font-size: 11.5px; color: var(--text-muted); font-style: italic;">No teams created yet.</span>`;
        }

        const unassignedBadge = dept.unassigned_count > 0
            ? `<span style="display: inline-block; font-size: 11px; color: #b45309; background: #fef3c7; padding: 2px 7px; border-radius: 4px; font-weight: 700;">⚠️ ${dept.unassigned_count} Unassigned</span>`
            : `<span style="display: inline-block; font-size: 11px; color: #16a34a; background: #dcfce7; padding: 2px 7px; border-radius: 4px; font-weight: 600;">✓ All Assigned</span>`;

        card.innerHTML = `
            <div>
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                    <div>
                        <h4 style="margin: 0; font-size: 16px; color: var(--text-main); font-weight: 700; display: flex; align-items: center; gap: 6px;">
                            🏢 ${escapeHtml(dept.name)}
                        </h4>
                        ${dept.description ? `<p style="margin: 3px 0 0 0; font-size: 12px; color: var(--text-muted);">${escapeHtml(dept.description)}</p>` : ''}
                    </div>
                    ${(isSuperAdmin || isHr) ? `
                        <div style="display: flex; gap: 4px;">
                            <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 11px;" onclick="openEditDepartmentModal(${dept.id})" title="Edit Department">✏️</button>
                            ${(dept.member_count == 0 && dept.team_count == 0) ? `
                                <button type="button" class="btn-icon-del" style="padding: 2px 7px; font-size: 11px;" onclick="deleteDepartment(${dept.id})" title="Remove Department">🗑️</button>
                            ` : ''}
                        </div>
                    ` : ''}
                </div>

                <div style="margin-bottom: 12px;">
                    ${hodContent}
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; background: var(--bg-card-elevated); border-radius: 6px; margin-bottom: 12px; font-size: 12px;">
                    <div>
                        <strong>${dept.member_count || 0}</strong> <span style="color: var(--text-muted);">Staff</span>
                        <span style="margin: 0 5px; color: var(--border-color);">|</span>
                        <strong>${dept.team_count || 0}</strong> <span style="color: var(--text-muted);">Teams</span>
                    </div>
                    <div>${unassignedBadge}</div>
                </div>

                <div style="margin-bottom: 14px;">
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase;">Teams:</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 5px; align-items: center;">
                        ${teamsChipsHtml}
                    </div>
                </div>
            </div>

            <div style="border-top: 1px solid var(--border-color); padding-top: 12px; margin-top: 6px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                <button type="button" class="btn btn-primary" style="flex: 1; font-size: 12px; font-weight: 700; padding: 6px 12px;" onclick="openDepartmentDrilldown(${dept.id})">
                    🔍 Manage Teams & Staff (${dept.team_count || 0})
                </button>
                ${(isMyDept) ? `
                    <button type="button" class="btn btn-outline" style="font-size: 12px; font-weight: 700; padding: 6px 10px; color: #059669; border-color: #10b981;" onclick="openAddTeamModal(${dept.id})" title="Add new team to ${escapeHtml(dept.name)}">
                        ➕ Team
                    </button>
                ` : ''}
            </div>
        `;

        container.appendChild(card);
    });
}

// ==========================================
// DEPARTMENT DRILLDOWN MODAL
// ==========================================

function openDepartmentDrilldown(deptId) {
    const dept = (AppState.departments || []).find(d => parseInt(d.id, 10) === parseInt(deptId, 10));
    if (!dept) {
        showToast("Department not found.", "error");
        return;
    }

    const isSuperAdmin = AppState.currentUser && (AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'admin');
    const isHr = AppState.currentUser && (AppState.currentUser.role === 'hr' || (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase() === 'hr'));
    const isHod = AppState.currentUser && AppState.currentUser.role === 'hod';
    const userDeptId = AppState.currentUser ? parseInt(AppState.currentUser.department_id || 0, 10) : 0;
    const canManageDept = isSuperAdmin || isHr || (isHod && userDeptId === parseInt(dept.id, 10));

    document.getElementById('drilldown-dept-name').textContent = dept.name;
    document.getElementById('drilldown-dept-meta').textContent = `${dept.hod_name ? 'HOD: ' + dept.hod_name : 'No HOD appointed'} • ${dept.description || 'Departmental operational roster'}`;

    const statsContainer = document.getElementById('drilldown-dept-stats');
    if (statsContainer) {
        statsContainer.innerHTML = `
            <div><span style="color: var(--text-muted); font-size: 11px;">TOTAL STAFF:</span> <strong style="color: var(--text-main); font-size: 14px; margin-left: 4px;">${dept.member_count || 0}</strong></div>
            <div><span style="color: var(--text-muted); font-size: 11px;">TEAMS:</span> <strong style="color: var(--primary); font-size: 14px; margin-left: 4px;">${dept.team_count || 0}</strong></div>
            <div><span style="color: var(--text-muted); font-size: 11px;">UNASSIGNED:</span> <strong style="color: ${dept.unassigned_count > 0 ? '#d97706' : '#16a34a'}; font-size: 14px; margin-left: 4px;">${dept.unassigned_count || 0}</strong></div>
        `;
    }

    const actionsBar = document.getElementById('drilldown-actions-bar');
    if (actionsBar) {
        actionsBar.innerHTML = '';
        if (canManageDept) {
            actionsBar.innerHTML += `
                <button type="button" class="btn btn-primary" style="font-size: 12px; font-weight: 700; padding: 6px 12px;" onclick="openAddTeamModal(${dept.id})">
                    ➕ Create New Team
                </button>
            `;
        }
    }

    const unassignedContainer = document.getElementById('drilldown-unassigned-container');
    const deptMembers = (AppState.employees || []).filter(e => parseInt(e.department_id, 10) === parseInt(dept.id, 10));
    const unassignedMembers = deptMembers.filter(e => !e.team_id || e.team_id == 0);
    const deptTeams = (AppState.teams || []).filter(t => parseInt(t.department_id, 10) === parseInt(dept.id, 10));

    if (unassignedMembers.length > 0) {
        unassignedContainer.style.display = '';
        unassignedContainer.innerHTML = `
            <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 12px 16px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <div style="font-size: 13px; font-weight: 700; color: #b45309; display: flex; align-items: center; gap: 6px;">
                        ⚠️ Unassigned Staff Members (${unassignedMembers.length})
                    </div>
                    <small style="color: #b45309; font-size: 11px;">Assign them to operational teams below</small>
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    ${unassignedMembers.map(emp => `
                        <div style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-card-elevated); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="user-avatar" style="width: 28px; height: 28px; font-size: 11px;">${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>
                                <div>
                                    <strong style="font-size: 13px;">${escapeHtml(emp.name)}</strong>
                                    <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(emp.designation || 'Staff')} ${emp.email ? '• <code>' + escapeHtml(emp.email) + '</code>' : ''}</div>
                                </div>
                            </div>
                            ${canManageDept ? `
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <select class="input-control" style="font-size: 12px; padding: 4px 8px; width: auto;" onchange="assignEmployeeToTeam(${emp.id}, this.value, ${dept.id})">
                                        <option value="">➕ Assign to Team...</option>
                                        ${deptTeams.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('')}
                                    </select>
                                </div>
                            ` : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    } else {
        unassignedContainer.style.display = 'none';
        unassignedContainer.innerHTML = '';
    }

    const teamsBadge = document.getElementById('drilldown-team-count-badge');
    if (teamsBadge) teamsBadge.textContent = `${deptTeams.length} Teams`;

    const teamsList = document.getElementById('drilldown-teams-list');
    if (teamsList) {
        teamsList.innerHTML = '';
        if (deptTeams.length === 0) {
            teamsList.innerHTML = `
                <div style="text-align: center; padding: 30px; background: var(--bg-card-elevated); border-radius: 8px; border: 1px dashed var(--border-color);">
                    <p style="color: var(--text-muted); margin: 0 0 10px 0; font-size: 13px;">No teams created in ${escapeHtml(dept.name)} yet.</p>
                    ${canManageDept ? `<button type="button" class="btn btn-primary" onclick="openAddTeamModal(${dept.id})">➕ Add First Team</button>` : ''}
                </div>
            `;
        } else {
            deptTeams.forEach(team => {
                const teamMembers = deptMembers.filter(e => parseInt(e.team_id, 10) === parseInt(team.id, 10));

                const teamBlock = document.createElement('div');
                teamBlock.style.background = 'var(--bg-card-elevated)';
                teamBlock.style.border = '1px solid var(--border-color)';
                teamBlock.style.borderRadius = '8px';
                teamBlock.style.padding = '14px 16px';

                let membersListHtml = '';
                if (teamMembers.length > 0) {
                    membersListHtml = teamMembers.map(emp => `
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; background: var(--bg-card); border-radius: 6px; border: 1px solid var(--border-color); flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div class="user-avatar" style="width: 26px; height: 26px; font-size: 11px;">${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>
                                <div>
                                    <strong style="font-size: 12.5px;">${escapeHtml(emp.name)}</strong>
                                    <small style="color: var(--text-muted); margin-left: 6px; font-size: 11px;">${escapeHtml(emp.designation || 'Member')}</small>
                                </div>
                            </div>
                            ${canManageDept ? `
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <select class="input-control" style="font-size: 11px; padding: 3px 6px; width: auto;" onchange="assignEmployeeToTeam(${emp.id}, this.value, ${dept.id})" title="Switch team">
                                        <option value="${team.id}" selected>Team: ${escapeHtml(team.name)}</option>
                                        <option value="">✕ Remove to Unassigned</option>
                                        ${deptTeams.filter(ot => ot.id !== team.id).map(ot => `<option value="${ot.id}">Move to: ${escapeHtml(ot.name)}</option>`).join('')}
                                    </select>
                                </div>
                            ` : ''}
                        </div>
                    `).join('');
                } else {
                    membersListHtml = `<div style="font-size: 12px; color: var(--text-muted); font-style: italic; padding: 6px 0;">No staff assigned to this team yet.</div>`;
                }

                teamBlock.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 15px; font-weight: 700; color: var(--text-main);">👥 ${escapeHtml(team.name)}</span>
                            <span class="badge" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-size: 11px; font-weight: 700;">
                                ${teamMembers.length} Members
                            </span>
                            ${team.description ? `<span style="font-size: 11.5px; color: var(--text-muted); margin-left: 4px;">• ${escapeHtml(team.description)}</span>` : ''}
                        </div>
                        ${canManageDept ? `
                            <div style="display: flex; gap: 4px;">
                                <button type="button" class="btn btn-outline" style="padding: 2px 6px; font-size: 11px;" onclick="openEditTeamModal(${team.id})" title="Edit Team">✏️</button>
                                <button type="button" class="btn-icon-del" style="padding: 2px 6px; font-size: 11px;" onclick="deleteTeam(${team.id}, ${dept.id})" title="Remove Team">🗑️</button>
                            </div>
                        ` : ''}
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        ${membersListHtml}
                    </div>
                `;

                teamsList.appendChild(teamBlock);
            });
        }
    }

    openModal('department-drilldown-modal');
}

async function assignEmployeeToTeam(empId, teamId, deptIdToRefresh) {
    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'assign_team',
                employee_id: empId,
                team_id: teamId || null
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            await loadEmployeeDirectory();
            if (deptIdToRefresh) {
                openDepartmentDrilldown(deptIdToRefresh);
            }
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to update team assignment.", "error");
    }
}

// ==========================================
// DEPARTMENT CRUD MODALS & HANDLERS
// ==========================================

function openAddDepartmentModal() {
    const form = document.getElementById('add-department-form');
    if (form) form.reset();

    const hodSelect = document.getElementById('add-dept-hod');
    if (hodSelect) {
        hodSelect.innerHTML = '<option value="">-- No HOD Appointed Yet --</option>';
        (AppState.employees || []).forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.id;
            opt.textContent = `${e.name} ${e.email ? '(' + e.email + ')' : ''}`;
            hodSelect.appendChild(opt);
        });
    }

    openModal('add-department-modal');
}

async function handleSaveDepartmentSubmit(e) {
    if (e) e.preventDefault();

    const name = document.getElementById('add-dept-name')?.value.trim();
    const description = document.getElementById('add-dept-description')?.value.trim();
    const hodId = document.getElementById('add-dept-hod')?.value;

    if (!name) {
        showToast("Department name is required.", "error");
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_department',
                name,
                description,
                hod_id: hodId || null
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('add-department-modal');
            await loadEmployeeDirectory();
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to create department.", "error");
    }
}

function openEditDepartmentModal(deptId) {
    const dept = (AppState.departments || []).find(d => parseInt(d.id, 10) === parseInt(deptId, 10));
    if (!dept) return;

    document.getElementById('edit-dept-id').value = dept.id;
    document.getElementById('edit-dept-name').value = dept.name;
    document.getElementById('edit-dept-description').value = dept.description || '';

    const hodSelect = document.getElementById('edit-dept-hod');
    if (hodSelect) {
        hodSelect.innerHTML = '<option value="">-- No HOD Appointed Yet --</option>';
        (AppState.employees || []).forEach(e => {
            const opt = document.createElement('option');
            opt.value = e.id;
            opt.textContent = `${e.name} ${e.email ? '(' + e.email + ')' : ''}`;
            if (parseInt(e.id, 10) === parseInt(dept.hod_id, 10)) {
                opt.selected = true;
            }
            hodSelect.appendChild(opt);
        });
    }

    openModal('edit-department-modal');
}

async function handleEditDepartmentSubmit(e) {
    if (e) e.preventDefault();

    const id = document.getElementById('edit-dept-id')?.value;
    const name = document.getElementById('edit-dept-name')?.value.trim();
    const description = document.getElementById('edit-dept-description')?.value.trim();
    const hodId = document.getElementById('edit-dept-hod')?.value;

    if (!id || !name) {
        showToast("Department name is required.", "error");
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_department',
                id,
                name,
                description,
                hod_id: hodId || null
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('edit-department-modal');
            await loadEmployeeDirectory();
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to update department.", "error");
    }
}

async function deleteDepartment(deptId) {
    const dept = (AppState.departments || []).find(d => parseInt(d.id, 10) === parseInt(deptId, 10));
    const deptName = dept ? dept.name : 'this department';

    if (!confirm(`Are you sure you want to remove ${deptName}?`)) return;

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_department',
                id: deptId
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "info");
            await loadEmployeeDirectory();
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to delete department.", "error");
    }
}

// ==========================================
// TEAM CRUD MODALS & HANDLERS
// ==========================================

function openAddTeamModal(preselectedDeptId) {
    const form = document.getElementById('add-team-form');
    if (form) form.reset();

    const deptSelect = document.getElementById('add-team-dept');
    if (deptSelect) {
        deptSelect.innerHTML = '';
        const isSuperAdmin = AppState.currentUser && (AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'admin');
        const isHr = AppState.currentUser && (AppState.currentUser.role === 'hr' || (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase() === 'hr'));
        const userDeptId = AppState.currentUser ? parseInt(AppState.currentUser.department_id || 0, 10) : 0;

        (AppState.departments || []).forEach(d => {
            if (isSuperAdmin || isHr || parseInt(d.id, 10) === userDeptId) {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name;
                if (preselectedDeptId && parseInt(d.id, 10) === parseInt(preselectedDeptId, 10)) {
                    opt.selected = true;
                } else if (!preselectedDeptId && parseInt(d.id, 10) === userDeptId) {
                    opt.selected = true;
                }
                deptSelect.appendChild(opt);
            }
        });
    }

    openModal('add-team-modal');
}

async function handleSaveTeamSubmit(e) {
    if (e) e.preventDefault();

    const deptId = document.getElementById('add-team-dept')?.value;
    const name = document.getElementById('add-team-name')?.value.trim();
    const description = document.getElementById('add-team-description')?.value.trim();

    if (!name || !deptId) {
        showToast("Team name and parent department are required.", "error");
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_team',
                department_id: deptId,
                name,
                description
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('add-team-modal');
            await loadEmployeeDirectory();

            const drilldownModal = document.getElementById('department-drilldown-modal');
            if (drilldownModal && drilldownModal.classList.contains('active')) {
                openDepartmentDrilldown(deptId);
            }
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to create team.", "error");
    }
}

function openEditTeamModal(teamId) {
    const team = (AppState.teams || []).find(t => parseInt(t.id, 10) === parseInt(teamId, 10));
    if (!team) return;

    document.getElementById('edit-team-id').value = team.id;
    document.getElementById('edit-team-name').value = team.name;
    document.getElementById('edit-team-description').value = team.description || '';

    const deptSelect = document.getElementById('edit-team-dept');
    if (deptSelect) {
        deptSelect.innerHTML = '';
        (AppState.departments || []).forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.name;
            if (parseInt(d.id, 10) === parseInt(team.department_id, 10)) {
                opt.selected = true;
            }
            deptSelect.appendChild(opt);
        });
    }

    openModal('edit-team-modal');
}

async function handleEditTeamSubmit(e) {
    if (e) e.preventDefault();

    const id = document.getElementById('edit-team-id')?.value;
    const deptId = document.getElementById('edit-team-dept')?.value;
    const name = document.getElementById('edit-team-name')?.value.trim();
    const description = document.getElementById('edit-team-description')?.value.trim();

    if (!id || !name) {
        showToast("Team name is required.", "error");
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_team',
                id,
                department_id: deptId,
                name,
                description
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('edit-team-modal');
            await loadEmployeeDirectory();

            const drilldownModal = document.getElementById('department-drilldown-modal');
            if (drilldownModal && drilldownModal.classList.contains('active')) {
                openDepartmentDrilldown(deptId);
            }
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to update team.", "error");
    }
}

async function deleteTeam(teamId, deptId) {
    const team = (AppState.teams || []).find(t => parseInt(t.id, 10) === parseInt(teamId, 10));
    const teamName = team ? team.name : 'this team';

    if (!confirm(`Are you sure you want to remove ${teamName}? Assigned staff will be reset to Unassigned.`)) return;

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_team',
                id: teamId
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "info");
            await loadEmployeeDirectory();
            if (deptId) {
                openDepartmentDrilldown(deptId);
            }
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to delete team.", "error");
    }
}

// ==========================================
// DYNAMIC DEPT & TEAM SELECTOR POPULATOR
// ==========================================

function populateModalDeptTeamSelects(deptSelectId, teamSelectId, selectedDeptId, selectedTeamId) {
    const deptEl = document.getElementById(deptSelectId);
    const teamEl = document.getElementById(teamSelectId);

    if (deptEl) {
        deptEl.innerHTML = '<option value="">-- Select Department --</option>';
        (AppState.departments || []).forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.name;
            if (selectedDeptId && parseInt(d.id, 10) === parseInt(selectedDeptId, 10)) {
                opt.selected = true;
            }
            deptEl.appendChild(opt);
        });

        deptEl.onchange = function() {
            filterModalTeamsByDepartment(this.value, teamSelectId, null);
        };
    }

    const initialDeptId = selectedDeptId || (deptEl ? deptEl.value : null);
    filterModalTeamsByDepartment(initialDeptId, teamSelectId, selectedTeamId);
}

function filterModalTeamsByDepartment(deptId, teamSelectId, selectedTeamId) {
    const teamEl = document.getElementById(teamSelectId);
    if (!teamEl) return;

    teamEl.innerHTML = '<option value="">-- Unassigned (To be assigned by HOD) --</option>';

    if (!deptId) return;

    const filteredTeams = (AppState.teams || []).filter(t => 
        t.department_id && parseInt(t.department_id, 10) === parseInt(deptId, 10)
    );

    filteredTeams.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.name;
        if (selectedTeamId && parseInt(t.id, 10) === parseInt(selectedTeamId, 10)) {
            opt.selected = true;
        }
        teamEl.appendChild(opt);
    });
}

// ==========================================
// LOGIN CREDENTIALS TOGGLE HELPER
// ==========================================

function toggleLoginCredentialsSection(prefix, isEnabled) {
    const fields = document.getElementById(`${prefix}-emp-login-fields`);
    const notice = document.getElementById(`${prefix}-emp-no-login-notice`);
    const emailReq = document.getElementById(`${prefix}-emp-email-required-mark`);
    const emailInput = document.getElementById(`${prefix}-emp-email`);
    const pwdInput = document.getElementById(`${prefix}-emp-password`);

    if (fields) fields.style.display = isEnabled ? 'block' : 'none';
    if (notice) notice.style.display = isEnabled ? 'none' : 'block';
    if (emailReq) emailReq.style.display = isEnabled ? 'inline' : 'none';
    if (emailInput) emailInput.required = !!isEnabled;
    if (pwdInput) pwdInput.required = !!isEnabled;
}

// ==========================================
// EMPLOYEE CRUD MODALS & SUBMISSIONS
// ==========================================

function handleAvatarFileSelect(input, previewId, base64HiddenId, mode = 'add') {
    const file = input.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        showToast("Image size must be under 5MB.", "error");
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;
        const hiddenInput = document.getElementById(base64HiddenId);
        if (hiddenInput) hiddenInput.value = base64;
        const preview = document.getElementById(previewId);
        if (preview) {
            preview.innerHTML = '';
            preview.style.backgroundImage = `url(${base64})`;
            preview.style.backgroundSize = 'cover';
            preview.style.backgroundPosition = 'center center';
            preview.style.border = '2px solid var(--primary)';
        }
        const clearBtn = document.getElementById(`${mode}-emp-avatar-clear-btn`);
        if (clearBtn) clearBtn.style.display = 'inline-flex';
    };
    reader.readAsDataURL(file);
}

function clearAvatarPreview(mode = 'add') {
    const fileInput = document.getElementById(`${mode}-emp-avatar-file`);
    const hiddenInput = document.getElementById(`${mode}-emp-avatar-base64`);
    const preview = document.getElementById(`${mode}-emp-avatar-preview`);
    const clearBtn = document.getElementById(`${mode}-emp-avatar-clear-btn`);

    if (fileInput) fileInput.value = '';
    if (hiddenInput) hiddenInput.value = (mode === 'edit') ? '__REMOVE__' : '';
    if (clearBtn) clearBtn.style.display = 'none';

    if (preview) {
        preview.style.backgroundImage = 'none';
        preview.style.border = '2px dashed #94a3b8';
        preview.innerHTML = `
            <div style="font-size: 32px; color: var(--text-muted); line-height: 1;">📷</div>
            <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-top: 6px; text-align: center; line-height: 1.2;">Passport Size<br>Photo</div>
            <div style="font-size: 10px; color: var(--primary); margin-top: 4px; font-weight: 700;">${mode === 'edit' ? 'Click to change' : 'Click to upload'}</div>
        `;
    }
}

function openAddEmployeeModal() {
    document.getElementById('add-employee-form')?.reset();
    populateModalDeptTeamSelects('add-emp-dept', 'add-emp-team', null, null);

    const canLoginCheck = document.getElementById('add-emp-can-login');
    if (canLoginCheck) canLoginCheck.checked = false;
    toggleLoginCredentialsSection('add', false);

    const desigInput = document.getElementById('add-emp-designation');
    if (desigInput) desigInput.value = '';

    const codeInput = document.getElementById('add-emp-code');
    if (codeInput) codeInput.value = '';

    const fatherInput = document.getElementById('add-emp-father-name');
    if (fatherInput) fatherInput.value = '';

    const cnicInput = document.getElementById('add-emp-cnic');
    if (cnicInput) cnicInput.value = '';

    const bankSelect = document.getElementById('add-emp-bank-name');
    if (bankSelect) bankSelect.value = 'UBL';

    const accountInput = document.getElementById('add-emp-bank-account');
    if (accountInput) accountInput.value = '';

    const fixedAllowInput = document.getElementById('add-emp-fixed-allowance');
    if (fixedAllowInput) fixedAllowInput.value = '';
    
    clearAvatarPreview('add');

    const shiftSelect = document.getElementById('add-emp-shift-hours');
    if (shiftSelect) shiftSelect.value = '8.0';

    const salaryInput = document.getElementById('add-emp-salary');
    if (salaryInput) salaryInput.value = '';

    const joinInput = document.getElementById('add-emp-joining-date');
    if (joinInput) joinInput.value = new Date().toISOString().slice(0, 10);

    const annInput = document.getElementById('add-emp-annual-quota');
    if (annInput) annInput.value = '14';

    const casInput = document.getElementById('add-emp-casual-quota');
    if (casInput) casInput.value = '10';

    const sickInput = document.getElementById('add-emp-sick-quota');
    if (sickInput) sickInput.value = '8';

    openModal('add-employee-modal');
}

async function handleAddEmployeeSubmit(e) {
    if (e) e.preventDefault();

    const name = document.getElementById('add-emp-name')?.value.trim();
    const canLogin = document.getElementById('add-emp-can-login')?.checked ? 1 : 0;
    const email = document.getElementById('add-emp-email')?.value.trim();
    const password = document.getElementById('add-emp-password')?.value.trim() || 'DiscoverPakistan123';
    const role = document.getElementById('add-emp-role')?.value || 'employee';
    const designation = document.getElementById('add-emp-designation')?.value.trim() || '';
    const deptId = document.getElementById('add-emp-dept')?.value;
    const teamId = document.getElementById('add-emp-team')?.value;
    const avatar = document.getElementById('add-emp-avatar-base64')?.value || '';

    const empCode = document.getElementById('add-emp-code')?.value.trim();
    const fatherName = document.getElementById('add-emp-father-name')?.value.trim();
    const cnic = document.getElementById('add-emp-cnic')?.value.trim();
    const bankName = document.getElementById('add-emp-bank-name')?.value || 'UBL';
    const bankAccount = document.getElementById('add-emp-bank-account')?.value.trim();
    const fixedAllowance = parseFloat(document.getElementById('add-emp-fixed-allowance')?.value || '0');

    const expectedHours = parseFloat(document.getElementById('add-emp-shift-hours')?.value || '8.0');
    const basicSalary = parseFloat(document.getElementById('add-emp-salary')?.value || '0');
    const joiningDate = document.getElementById('add-emp-joining-date')?.value || '';
    const annualQuota = parseInt(document.getElementById('add-emp-annual-quota')?.value || '14', 10);
    const casualQuota = parseInt(document.getElementById('add-emp-casual-quota')?.value || '10', 10);
    const sickQuota = parseInt(document.getElementById('add-emp-sick-quota')?.value || '8', 10);

    if (!name) {
        showToast("Employee name is required.", "error");
        return;
    }

    if (canLogin && !email) {
        showToast("Official email address is required when portal login access is enabled.", "error");
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_employee',
                name,
                emp_code: empCode,
                father_husband_name: fatherName,
                cnic_no: cnic,
                bank_name: bankName,
                bank_account_no: bankAccount,
                fixed_allowance: fixedAllowance,
                can_login: canLogin,
                email: email || null,
                password,
                role,
                designation,
                department_id: deptId,
                team_id: teamId || null,
                avatar,
                expected_hours: expectedHours,
                basic_salary: basicSalary,
                joining_date: joiningDate,
                annual_leave_quota: annualQuota,
                casual_leave_quota: casualQuota,
                sick_leave_quota: sickQuota
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('add-employee-modal');
            await loadEmployeeDirectory();
            populateEmailSelectors();
            populateAdminEmployeeSelector();
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to create employee.", "error");
    }
}

function openEditEmployeeModal(empId) {
    const emp = AppState.employees.find(e => e.id == empId);
    if (!emp) return;

    populateModalDeptTeamSelects('edit-emp-dept', 'edit-emp-team', emp.department_id, emp.team_id);

    const hasLogin = (emp.can_login != 0 && emp.can_login !== '0');
    const canLoginCheck = document.getElementById('edit-emp-can-login');
    if (canLoginCheck) canLoginCheck.checked = hasLogin;
    toggleLoginCredentialsSection('edit', hasLogin);

    document.getElementById('edit-emp-id').value = emp.id;
    document.getElementById('edit-emp-name').value = emp.name;
    document.getElementById('edit-emp-email').value = emp.email || '';
    let editRole = emp.role || 'employee';
    if (editRole === 'admin') editRole = 'super_admin';
    document.getElementById('edit-emp-role').value = editRole;
    document.getElementById('edit-emp-designation').value = emp.designation || '';

    const codeInput = document.getElementById('edit-emp-code');
    if (codeInput) codeInput.value = emp.emp_code || `DP-${String(emp.id).padStart(3, '0')}`;

    const fatherInput = document.getElementById('edit-emp-father-name');
    if (fatherInput) fatherInput.value = emp.father_husband_name || '';

    const cnicInput = document.getElementById('edit-emp-cnic');
    if (cnicInput) cnicInput.value = emp.cnic_no || '';

    const bankSelect = document.getElementById('edit-emp-bank-name');
    if (bankSelect) bankSelect.value = emp.bank_name || 'UBL';

    const accountInput = document.getElementById('edit-emp-bank-account');
    if (accountInput) accountInput.value = emp.bank_account_no || '';

    const fixedAllowInput = document.getElementById('edit-emp-fixed-allowance');
    if (fixedAllowInput) fixedAllowInput.value = (emp.fixed_allowance && parseFloat(emp.fixed_allowance) > 0) ? parseFloat(emp.fixed_allowance) : '';

    const shiftSelect = document.getElementById('edit-emp-shift-hours');
    if (shiftSelect) {
        const empHours = emp.expected_hours !== undefined ? parseFloat(emp.expected_hours).toFixed(1) : '8.0';
        shiftSelect.value = empHours;
        if (!shiftSelect.value) shiftSelect.value = '8.0';
    }

    const salaryInput = document.getElementById('edit-emp-salary');
    if (salaryInput) {
        salaryInput.value = (emp.basic_salary && parseFloat(emp.basic_salary) > 0) ? parseFloat(emp.basic_salary) : '';
    }

    const joinInput = document.getElementById('edit-emp-joining-date');
    if (joinInput) {
        joinInput.value = emp.joining_date || '';
    }

    const annInput = document.getElementById('edit-emp-annual-quota');
    if (annInput) annInput.value = emp.annual_leave_quota !== undefined ? emp.annual_leave_quota : 14;

    const casInput = document.getElementById('edit-emp-casual-quota');
    if (casInput) casInput.value = emp.casual_leave_quota !== undefined ? emp.casual_leave_quota : 10;

    const sickInput = document.getElementById('edit-emp-sick-quota');
    if (sickInput) sickInput.value = emp.sick_leave_quota !== undefined ? emp.sick_leave_quota : 8;

    const statusSelect = document.getElementById('edit-emp-status');
    if (statusSelect) {
        statusSelect.value = (emp.is_active != 0 && emp.is_active !== '0') ? '1' : '0';
    }

    const prev = document.getElementById('edit-emp-avatar-preview');
    const hiddenBase64 = document.getElementById('edit-emp-avatar-base64');
    const fileInput = document.getElementById('edit-emp-avatar-file');
    const clearBtn = document.getElementById('edit-emp-avatar-clear-btn');
    if (fileInput) fileInput.value = '';
    if (hiddenBase64) hiddenBase64.value = '';

    if (prev) {
        if (emp.avatar) {
            prev.innerHTML = '';
            prev.style.backgroundImage = `url(${emp.avatar})`;
            prev.style.backgroundSize = 'cover';
            prev.style.backgroundPosition = 'center center';
            prev.style.border = '2px solid var(--primary)';
            if (clearBtn) clearBtn.style.display = 'inline-flex';
        } else {
            clearAvatarPreview('edit');
        }
    }

    openModal('edit-employee-modal');
}

async function handleEditEmployeeSubmit(e) {
    if (e) e.preventDefault();

    const id = document.getElementById('edit-emp-id')?.value;
    const name = document.getElementById('edit-emp-name')?.value.trim();
    const canLogin = document.getElementById('edit-emp-can-login')?.checked ? 1 : 0;
    const email = document.getElementById('edit-emp-email')?.value.trim();
    const role = document.getElementById('edit-emp-role')?.value || 'employee';
    const designation = document.getElementById('edit-emp-designation')?.value.trim() || '';
    const deptId = document.getElementById('edit-emp-dept')?.value;
    const teamId = document.getElementById('edit-emp-team')?.value;
    const avatar = document.getElementById('edit-emp-avatar-base64')?.value || '';
    const isActive = parseInt(document.getElementById('edit-emp-status')?.value || '1', 10);

    const empCode = document.getElementById('edit-emp-code')?.value.trim();
    const fatherName = document.getElementById('edit-emp-father-name')?.value.trim();
    const cnic = document.getElementById('edit-emp-cnic')?.value.trim();
    const bankName = document.getElementById('edit-emp-bank-name')?.value || 'UBL';
    const bankAccount = document.getElementById('edit-emp-bank-account')?.value.trim();
    const fixedAllowance = parseFloat(document.getElementById('edit-emp-fixed-allowance')?.value || '0');

    const expectedHours = parseFloat(document.getElementById('edit-emp-shift-hours')?.value || '8.0');
    const basicSalary = parseFloat(document.getElementById('edit-emp-salary')?.value || '0');
    const joiningDate = document.getElementById('edit-emp-joining-date')?.value || '';
    const annualQuota = parseInt(document.getElementById('edit-emp-annual-quota')?.value || '14', 10);
    const casualQuota = parseInt(document.getElementById('edit-emp-casual-quota')?.value || '10', 10);
    const sickQuota = parseInt(document.getElementById('edit-emp-sick-quota')?.value || '8', 10);

    if (!id || !name) {
        showToast("ID and employee name are required.", "error");
        return;
    }

    if (canLogin && !email) {
        showToast("Official email address is required when portal login access is enabled.", "error");
        return;
    }

    try {
        const payload = {
            action: 'update_employee',
            id,
            name,
            emp_code: empCode,
            father_husband_name: fatherName,
            cnic_no: cnic,
            bank_name: bankName,
            bank_account_no: bankAccount,
            fixed_allowance: fixedAllowance,
            can_login: canLogin,
            email: email || null,
            role,
            designation,
            department_id: deptId,
            team_id: teamId || null,
            expected_hours: expectedHours,
            basic_salary: basicSalary,
            joining_date: joiningDate,
            annual_leave_quota: annualQuota,
            casual_leave_quota: casualQuota,
            sick_leave_quota: sickQuota,
            is_active: isActive
        };
        if (avatar) payload.avatar = avatar;

        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('edit-employee-modal');
            await loadEmployeeDirectory();
            populateEmailSelectors();
            populateAdminEmployeeSelector();
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to update employee.", "error");
    }
}

function openPasswordModal(empId) {
    const emp = AppState.employees.find(e => e.id == empId);
    const empName = emp ? emp.name : 'Employee';
    document.getElementById('pwd-emp-id').value = empId;
    document.getElementById('pwd-emp-name-label').textContent = empName;
    document.getElementById('pwd-new-password').value = '';
    openModal('password-modal');
}

async function handlePasswordSubmit(e) {
    if (e) e.preventDefault();

    const id = document.getElementById('pwd-emp-id')?.value;
    const password = document.getElementById('pwd-new-password')?.value.trim();

    if (!id || !password) {
        showToast("Please enter a new password.", "error");
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_password',
                id,
                password
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('password-modal');
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to update password.", "error");
    }
}

async function toggleEmployeeStatus(empId, newStatus) {
    const emp = (AppState.employees || []).find(e => e.id == empId);
    const empName = emp ? emp.name : 'this employee';
    const actionText = newStatus === 1 ? 'reactivate' : 'deactivate';

    const confirmMsg = newStatus === 1
        ? `Reactivate account for ${empName}? They will be restored to active status.`
        : `Deactivate ${empName}?\n\nThey will no longer be able to log in, but all historical records (payroll, attendance, loans, tasks) will remain safely intact.`;

    if (!confirm(confirmMsg)) {
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_status',
                id: empId,
                is_active: newStatus
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "info");
            await loadEmployeeDirectory();
            populateEmailSelectors();
            populateAdminEmployeeSelector();
        } else {
            showToast(data.message || `Failed to ${actionText} employee.`, "error");
        }
    } catch (err) {
        showToast(`Failed to ${actionText} employee.`, "error");
    }
}

async function handleDeleteEmployeeFromModal() {
    const id = document.getElementById('edit-emp-id')?.value;
    if (!id) {
        showToast("No employee selected.", "error");
        return;
    }
    await deleteEmployeePermanent(id);
}

async function deleteEmployee(empId) {
    await deleteEmployeePermanent(empId);
}

async function deleteEmployeePermanent(empId) {
    const emp = (AppState.employees || []).find(e => e.id == empId);
    const empName = emp ? emp.name : 'this employee';

    if (AppState.currentUser && AppState.currentUser.id == empId) {
        showToast("You cannot delete your own logged-in account.", "error");
        return;
    }

    const confirmMsg = `⚠️ PERMANENTLY DELETE EMPLOYEE?\n\nEmployee: ${empName} (ID: ${empId})\n\nThis will permanently delete this employee record and cleanly remove all associated records (profiles, sheets, payroll entries, tasks, fines, loans).\n\nThis is irreversible. Do you want to proceed?`;

    if (!confirm(confirmMsg)) {
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_employee',
                id: empId
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || `Employee ${empName} deleted successfully.`, "success");
            closeModal('edit-employee-modal');
            await loadEmployeeDirectory();
            populateEmailSelectors();
            populateAdminEmployeeSelector();
            if (typeof loadPayroll === 'function') {
                loadPayroll();
            }
        } else {
            showToast(data.message || "Failed to delete employee.", "error");
        }
    } catch (err) {
        showToast("Network error deleting employee.", "error");
    }
}

// ==========================================
// MY PROFILE & PERMISSIONS MODALS
// ==========================================

function openMyProfileModal() {
    const user = AppState.currentUser;
    if (!user) {
        showToast("Please log in first.", "error");
        return;
    }

    document.getElementById('my-profile-name').value = user.name || '';
    document.getElementById('my-profile-email').value = user.email || '';
    document.getElementById('my-profile-designation').value = user.designation || '';
    document.getElementById('my-profile-dept-team').value = `${user.department_name || 'General'} • ${user.team_name || 'Unassigned'}`;
    
    const newPwd = document.getElementById('my-profile-new-password');
    const confPwd = document.getElementById('my-profile-confirm-password');
    if (newPwd) newPwd.value = '';
    if (confPwd) confPwd.value = '';

    const hiddenBase64 = document.getElementById('my-profile-avatar-base64');
    const avatarAction = document.getElementById('my-profile-avatar-action');
    const fileInput = document.getElementById('my-profile-avatar-file');
    const preview = document.getElementById('my-profile-avatar-preview');

    if (hiddenBase64) hiddenBase64.value = '';
    if (avatarAction) avatarAction.value = 'keep';
    if (fileInput) fileInput.value = '';

    if (preview) {
        if (user.avatar) {
            preview.innerHTML = '';
            preview.style.backgroundImage = `url(${user.avatar})`;
            preview.style.backgroundSize = 'cover';
            preview.style.backgroundPosition = 'center';
        } else {
            preview.style.backgroundImage = 'none';
            preview.textContent = user.name ? user.name.charAt(0).toUpperCase() : '👤';
        }
    }

    openModal('my-profile-modal');
}

function handleMyProfilePhotoSelect(input) {
    const file = input.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        showToast("Profile image size must be under 5MB.", "error");
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const base64 = e.target.result;
        const hiddenInput = document.getElementById('my-profile-avatar-base64');
        const avatarAction = document.getElementById('my-profile-avatar-action');
        const preview = document.getElementById('my-profile-avatar-preview');

        if (hiddenInput) hiddenInput.value = base64;
        if (avatarAction) avatarAction.value = 'update';

        if (preview) {
            preview.innerHTML = '';
            preview.style.backgroundImage = `url(${base64})`;
            preview.style.backgroundSize = 'cover';
            preview.style.backgroundPosition = 'center';
        }
    };
    reader.readAsDataURL(file);
}

function handleRemoveMyProfilePhoto() {
    const preview = document.getElementById('my-profile-avatar-preview');
    const hiddenBase64 = document.getElementById('my-profile-avatar-base64');
    const avatarAction = document.getElementById('my-profile-avatar-action');
    const fileInput = document.getElementById('my-profile-avatar-file');

    if (hiddenBase64) hiddenBase64.value = '';
    if (avatarAction) avatarAction.value = 'remove';
    if (fileInput) fileInput.value = '';

    if (preview) {
        preview.style.backgroundImage = 'none';
        const user = AppState.currentUser;
        preview.textContent = user && user.name ? user.name.charAt(0).toUpperCase() : '👤';
    }
}

async function handleMyProfileSubmit(e) {
    if (e) e.preventDefault();

    const name = document.getElementById('my-profile-name')?.value.trim();
    const email = document.getElementById('my-profile-email')?.value.trim();
    const designation = document.getElementById('my-profile-designation')?.value.trim();
    const avatar = document.getElementById('my-profile-avatar-base64')?.value || '';
    const avatarAction = document.getElementById('my-profile-avatar-action')?.value || 'keep';
    const newPassword = document.getElementById('my-profile-new-password')?.value.trim();
    const confirmPassword = document.getElementById('my-profile-confirm-password')?.value.trim();

    if (!name || !email) {
        showToast("Full name and email address are required.", "error");
        return;
    }

    if (newPassword || confirmPassword) {
        if (newPassword !== confirmPassword) {
            showToast("Passwords do not match! Please check your new password.", "error");
            return;
        }
        if (newPassword.length < 6) {
            showToast("Password must be at least 6 characters.", "error");
            return;
        }
    }

    try {
        const payload = {
            action: 'update_profile',
            name: name,
            email: email,
            designation: designation,
            avatar: avatar,
            avatar_action: avatarAction
        };
        if (newPassword) {
            payload.password = newPassword;
        }

        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('my-profile-modal');

            if (data.user) {
                AppState.currentUser = data.user;
                const idx = AppState.employees.findIndex(e => e.id == data.user.id);
                if (idx !== -1) {
                    AppState.employees[idx] = { ...AppState.employees[idx], ...data.user };
                }
            }

            renderUserBar();
            populateEmailSelectors();
            if (typeof populateAdminEmployeeSelector === 'function') {
                populateAdminEmployeeSelector();
            }
            if (typeof populateTaskEmployeeFilter === 'function') {
                populateTaskEmployeeFilter();
            }
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to update profile.", "error");
    }
}

function openPermissionsModal(empId) {
    if (!AppState.currentUser || (AppState.currentUser.role !== 'super_admin' && AppState.currentUser.role !== 'admin')) {
        showToast("Only Super Admin can manage roles & permissions.", "error");
        return;
    }

    const emp = AppState.employees.find(e => e.id == empId);
    if (!emp) {
        showToast("Employee record not found.", "error");
        return;
    }

    document.getElementById('perm-emp-id').value = emp.id;
    document.getElementById('perm-emp-name').textContent = emp.name;
    document.getElementById('perm-emp-meta').textContent = `${emp.designation || 'Staff'} • ${emp.department_name || 'General'} • ${emp.team_name || 'Unassigned'}`;

    const avatarEl = document.getElementById('perm-emp-avatar');
    if (avatarEl) {
        if (emp.avatar) {
            avatarEl.innerHTML = '';
            avatarEl.style.backgroundImage = `url(${emp.avatar})`;
            avatarEl.style.backgroundSize = 'cover';
        } else {
            avatarEl.style.backgroundImage = 'none';
            avatarEl.textContent = emp.name ? emp.name.charAt(0).toUpperCase() : '👤';
        }
    }

    const roleSelect = document.getElementById('perm-role-select');
    if (roleSelect) {
        let r = emp.role || 'employee';
        if (r === 'admin') r = 'super_admin';
        roleSelect.value = r;
    }

    const isFullAccess = (emp.role === 'super_admin' || emp.role === 'admin' || emp.role === 'hr');

    document.getElementById('perm-can-assign-tasks').checked = !!parseInt(emp.can_assign_tasks || (isFullAccess ? 1 : 0));
    document.getElementById('perm-can-edit-tasks').checked = !!parseInt(emp.can_edit_tasks || (isFullAccess ? 1 : 0));
    document.getElementById('perm-can-unlock-sheets').checked = !!parseInt(emp.can_unlock_sheets || (isFullAccess ? 1 : 0));
    document.getElementById('perm-can-inspect-sheets').checked = !!parseInt(emp.can_inspect_sheets || (isFullAccess ? 1 : 0));
    document.getElementById('perm-can-view-reports').checked = !!parseInt(emp.can_view_reports || (isFullAccess ? 1 : 0));
    document.getElementById('perm-can-view-attendance').checked = !!parseInt(emp.can_view_attendance || (isFullAccess ? 1 : 0));
    document.getElementById('perm-can-manage-employees').checked = !!parseInt(emp.can_manage_employees || (isFullAccess ? 1 : 0));

    openModal('permissions-modal');
}

function applyRolePreset(roleValue) {
    const isAssign = document.getElementById('perm-can-assign-tasks');
    const isEdit = document.getElementById('perm-can-edit-tasks');
    const isUnlock = document.getElementById('perm-can-unlock-sheets');
    const isInspect = document.getElementById('perm-can-inspect-sheets');
    const isReports = document.getElementById('perm-can-view-reports');
    const isAttendance = document.getElementById('perm-can-view-attendance');
    const isManageEmp = document.getElementById('perm-can-manage-employees');

    if (roleValue === 'super_admin' || roleValue === 'admin' || roleValue === 'hr') {
        if (isAssign) isAssign.checked = true;
        if (isEdit) isEdit.checked = true;
        if (isUnlock) isUnlock.checked = true;
        if (isInspect) isInspect.checked = true;
        if (isReports) isReports.checked = true;
        if (isAttendance) isAttendance.checked = true;
        if (isManageEmp) isManageEmp.checked = true;
    } else if (roleValue === 'hod') {
        if (isAssign) isAssign.checked = true;
        if (isEdit) isEdit.checked = true;
        if (isUnlock) isUnlock.checked = true;
        if (isInspect) isInspect.checked = true;
        if (isReports) isReports.checked = true;
        if (isAttendance) isAttendance.checked = true;
        if (isManageEmp) isManageEmp.checked = false;
    } else if (roleValue === 'team_lead') {
        if (isAssign) isAssign.checked = true;
        if (isEdit) isEdit.checked = true;
        if (isUnlock) isUnlock.checked = true;
        if (isInspect) isInspect.checked = true;
        if (isReports) isReports.checked = false;
        if (isAttendance) isAttendance.checked = true;
        if (isManageEmp) isManageEmp.checked = false;
    } else if (roleValue === 'coordinator') {
        if (isAssign) isAssign.checked = true;
        if (isEdit) isEdit.checked = true;
        if (isUnlock) isUnlock.checked = false;
        if (isInspect) isInspect.checked = false;
        if (isReports) isReports.checked = false;
        if (isAttendance) isAttendance.checked = false;
        if (isManageEmp) isManageEmp.checked = false;
    } else {
        if (isAssign) isAssign.checked = false;
        if (isEdit) isEdit.checked = false;
        if (isUnlock) isUnlock.checked = false;
        if (isInspect) isInspect.checked = false;
        if (isReports) isReports.checked = false;
        if (isAttendance) isAttendance.checked = false;
        if (isManageEmp) isManageEmp.checked = false;
    }
}

async function handleSavePermissionsSubmit(e) {
    if (e) e.preventDefault();

    const empId = document.getElementById('perm-emp-id')?.value;
    const role = document.getElementById('perm-role-select')?.value;
    const canAssign = document.getElementById('perm-can-assign-tasks')?.checked ? 1 : 0;
    const canEdit = document.getElementById('perm-can-edit-tasks')?.checked ? 1 : 0;
    const canUnlock = document.getElementById('perm-can-unlock-sheets')?.checked ? 1 : 0;
    const canInspect = document.getElementById('perm-can-inspect-sheets')?.checked ? 1 : 0;
    const canReports = document.getElementById('perm-can-view-reports')?.checked ? 1 : 0;
    const canAttendance = document.getElementById('perm-can-view-attendance')?.checked ? 1 : 0;
    const canManageEmp = document.getElementById('perm-can-manage-employees')?.checked ? 1 : 0;

    if (!empId) {
        showToast("Employee ID missing.", "error");
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_permissions',
                id: empId,
                role: role,
                can_assign_tasks: canAssign,
                can_edit_tasks: canEdit,
                can_unlock_sheets: canUnlock,
                can_inspect_sheets: canInspect,
                can_view_reports: canReports,
                can_view_attendance: canAttendance,
                can_manage_employees: canManageEmp
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('permissions-modal');
            await loadEmployeeDirectory();

            if (AppState.currentUser && AppState.currentUser.id == empId) {
                const userRes = await fetch('api/auth.php?action=current_user');
                const userData = await userRes.json();
                if (userData.success && userData.user) {
                    AppState.currentUser = userData.user;
                    renderUserBar();
                    loadUserData();
                }
            }
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to save permissions.", "error");
    }
}
