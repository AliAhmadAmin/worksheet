/**
 * Employee Management: Admin CRUD, Profile Updates, and Password Management
 */

async function loadEmployeeDirectory() {
    const tbody = document.getElementById('employee-directory-tbody');
    if (!tbody) return;

    try {
        const res = await fetch('api/employees.php?action=list');
        const data = await res.json();

        if (!data.success) return;

        AppState.employees = data.employees || [];
        AppState.departments = data.departments || [];
        AppState.teams = data.teams || [];

        // Update total employees count badge
        const totalBadge = document.getElementById('emp-total-badge');
        if (totalBadge) totalBadge.textContent = AppState.employees.length;

        tbody.innerHTML = '';
        if (AppState.employees.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 25px;">No employees registered.</td></tr>';
            return;
        }

        AppState.employees.forEach(emp => {
            const tr = document.createElement('tr');
            const isSelf = AppState.currentUser && AppState.currentUser.id === emp.id;
            const roleConfig = {
                admin: { label: '👑 Super Admin', bg: 'rgba(56, 189, 248, 0.15)', color: '#0284c7' },
                hod: { label: '🎖️ HOD / Manager', bg: 'rgba(245, 158, 11, 0.15)', color: '#d97706' },
                team_lead: { label: '⭐ Team Lead', bg: 'rgba(16, 185, 129, 0.15)', color: '#059669' },
                coordinator: { label: '🎯 Coordinator', bg: 'rgba(59, 130, 246, 0.15)', color: '#2563eb' },
                employee: { label: '👤 Staff Member', bg: 'rgba(148, 163, 184, 0.15)', color: '#64748b' }
            };

            const roleMeta = roleConfig[emp.role] || roleConfig.employee;
            const roleBadge = `<span class="user-role-tag" style="background: ${roleMeta.bg}; color: ${roleMeta.color}; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;">${roleMeta.label}</span>`;

            // Collect active permissions pills
            const permPills = [];
            if (emp.role === 'admin') {
                permPills.push('<span style="font-size: 10px; color: #0284c7; background: rgba(56, 189, 248, 0.1); padding: 1px 5px; border-radius: 4px;">Full Access</span>');
            } else {
                if (emp.can_assign_tasks) permPills.push('<span style="font-size: 10px; color: #3b82f6; background: rgba(59,130,246,0.1); padding: 1px 5px; border-radius: 4px;">Assign Tasks</span>');
                if (emp.can_edit_tasks) permPills.push('<span style="font-size: 10px; color: #f59e0b; background: rgba(245,158,11,0.1); padding: 1px 5px; border-radius: 4px;">Edit Tasks</span>');
                if (emp.can_unlock_sheets) permPills.push('<span style="font-size: 10px; color: #10b981; background: rgba(16,185,129,0.1); padding: 1px 5px; border-radius: 4px;">Unlock Sheets</span>');
                if (emp.can_inspect_sheets) permPills.push('<span style="font-size: 10px; color: #8b5cf6; background: rgba(139,92,246,0.1); padding: 1px 5px; border-radius: 4px;">Inspect Sheets</span>');
                if (emp.can_view_reports) permPills.push('<span style="font-size: 10px; color: #ec4899; background: rgba(236,72,153,0.1); padding: 1px 5px; border-radius: 4px;">Reports</span>');
                if (emp.can_view_attendance) permPills.push('<span style="font-size: 10px; color: #14b8a6; background: rgba(20,184,166,0.1); padding: 1px 5px; border-radius: 4px;">Attendance</span>');
                if (emp.can_manage_employees) permPills.push('<span style="font-size: 10px; color: #f97316; background: rgba(249,115,22,0.1); padding: 1px 5px; border-radius: 4px;">Manage Staff</span>');
            }

            const avatarHtml = emp.avatar 
                ? `<img src="${escapeHtml(emp.avatar)}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width: 32px; height: 32px; font-size: 12px;\\'>${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>';">`
                : `<div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px; flex-shrink: 0;">${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>`;

            tr.innerHTML = `
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        ${avatarHtml}
                        <div>
                            <strong>${escapeHtml(emp.name)}</strong> ${isSelf ? '<small style="color: var(--primary); font-weight: 700;">(You)</small>' : ''}
                        </div>
                    </div>
                </td>
                <td><code style="font-size: 12px; color: var(--text-muted);">${escapeHtml(emp.email)}</code></td>
                <td>
                    <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                        ${roleBadge}
                        ${permPills.length > 0 ? `<div style="display: flex; flex-wrap: wrap; gap: 3px; max-width: 140px;">${permPills.join('')}</div>` : ''}
                    </div>
                </td>
                <td>${escapeHtml(emp.designation || 'Staff')}</td>
                <td>${escapeHtml(emp.team_name || 'General')}</td>
                <td>${escapeHtml(emp.department_name || 'Digital')}</td>
                <td>
                    <div style="display: flex; align-items: center; gap: 6px; justify-content: flex-end; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; font-weight: 700; border-color: rgba(59, 130, 246, 0.4); color: var(--primary);" onclick="openPermissionsModal(${emp.id})" title="Role & Access Delegation">
                            🛡️ Roles
                        </button>
                        <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;" onclick="openEditEmployeeModal(${emp.id})" title="Edit Profile">
                            ✏️ Edit
                        </button>
                        <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;" onclick="openPasswordModal(${emp.id})" title="Change Password">
                            🔑 Pwd
                        </button>
                        ${!isSelf ? `
                            <button type="button" class="btn-icon-del" style="padding: 4px 8px; font-size: 12px;" onclick="deleteEmployee(${emp.id})" title="Remove Employee">
                                🗑️
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

    } catch (err) {
        console.error("Error loading employee directory:", err);
    }
}

// Avatar File Reader helper
function handleAvatarFileSelect(input, previewId, base64HiddenId) {
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
            preview.style.backgroundPosition = 'center';
        }
    };
    reader.readAsDataURL(file);
}

// Add New Employee Modal
function openAddEmployeeModal() {
    populateModalDeptTeamSelects('add-emp-dept', 'add-emp-team');
    document.getElementById('add-employee-form')?.reset();
    
    // Reset avatar preview
    const prev = document.getElementById('add-emp-avatar-preview');
    if (prev) {
        prev.style.backgroundImage = 'none';
        prev.textContent = '👤';
    }
    const hiddenBase64 = document.getElementById('add-emp-avatar-base64');
    if (hiddenBase64) hiddenBase64.value = '';

    openModal('add-employee-modal');
}

async function handleAddEmployeeSubmit(e) {
    if (e) e.preventDefault();

    const name = document.getElementById('add-emp-name')?.value.trim();
    const email = document.getElementById('add-emp-email')?.value.trim();
    const password = document.getElementById('add-emp-password')?.value.trim() || 'DiscoverPakistan123';
    const role = document.getElementById('add-emp-role')?.value;
    const designation = document.getElementById('add-emp-designation')?.value.trim();
    const deptId = document.getElementById('add-emp-dept')?.value;
    const teamId = document.getElementById('add-emp-team')?.value;
    const avatar = document.getElementById('add-emp-avatar-base64')?.value || '';

    if (!name || !email) {
        showToast("Name and email are required.", "error");
        return;
    }

    try {
        const res = await fetch('api/employees.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_employee',
                name,
                email,
                password,
                role,
                designation,
                department_id: deptId,
                team_id: teamId,
                avatar
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

// Edit Existing Employee Modal
function openEditEmployeeModal(empId) {
    const emp = AppState.employees.find(e => e.id == empId);
    if (!emp) return;

    populateModalDeptTeamSelects('edit-emp-dept', 'edit-emp-team');

    document.getElementById('edit-emp-id').value = emp.id;
    document.getElementById('edit-emp-name').value = emp.name;
    document.getElementById('edit-emp-email').value = emp.email;
    document.getElementById('edit-emp-role').value = emp.role;
    document.getElementById('edit-emp-designation').value = emp.designation || '';
    document.getElementById('edit-emp-dept').value = emp.department_id || 2;
    document.getElementById('edit-emp-team').value = emp.team_id || 2;

    const prev = document.getElementById('edit-emp-avatar-preview');
    const hiddenBase64 = document.getElementById('edit-emp-avatar-base64');
    const fileInput = document.getElementById('edit-emp-avatar-file');
    if (fileInput) fileInput.value = '';
    if (hiddenBase64) hiddenBase64.value = '';

    if (prev) {
        if (emp.avatar) {
            prev.innerHTML = '';
            prev.style.backgroundImage = `url(${emp.avatar})`;
            prev.style.backgroundSize = 'cover';
            prev.style.backgroundPosition = 'center';
        } else {
            prev.style.backgroundImage = 'none';
            prev.textContent = emp.name.charAt(0).toUpperCase();
        }
    }

    openModal('edit-employee-modal');
}

async function handleEditEmployeeSubmit(e) {
    if (e) e.preventDefault();

    const id = document.getElementById('edit-emp-id')?.value;
    const name = document.getElementById('edit-emp-name')?.value.trim();
    const email = document.getElementById('edit-emp-email')?.value.trim();
    const role = document.getElementById('edit-emp-role')?.value;
    const designation = document.getElementById('edit-emp-designation')?.value.trim();
    const deptId = document.getElementById('edit-emp-dept')?.value;
    const teamId = document.getElementById('edit-emp-team')?.value;
    const avatar = document.getElementById('edit-emp-avatar-base64')?.value || '';

    if (!id || !name || !email) {
        showToast("ID, name and email are required.", "error");
        return;
    }

    try {
        const payload = {
            action: 'update_employee',
            id,
            name,
            email,
            role,
            designation,
            department_id: deptId,
            team_id: teamId
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

// Password Update Modal
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

// Delete Employee
async function deleteEmployee(empId) {
    const emp = AppState.employees.find(e => e.id == empId);
    const empName = emp ? emp.name : 'this employee';

    if (!confirm(`Are you sure you want to remove ${empName} from the employee directory? This will remove their profile.`)) {
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
            showToast(data.message, "info");
            await loadEmployeeDirectory();
            populateEmailSelectors();
            populateAdminEmployeeSelector();
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to remove employee.", "error");
    }
}

// Helper to populate Dept and Team select elements
function populateModalDeptTeamSelects(deptSelectId, teamSelectId) {
    const deptEl = document.getElementById(deptSelectId);
    const teamEl = document.getElementById(teamSelectId);

    if (deptEl) {
        deptEl.innerHTML = '';
        AppState.departments.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.name;
            deptEl.appendChild(opt);
        });
    }

    if (teamEl) {
        teamEl.innerHTML = '';
        AppState.teams.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            teamEl.appendChild(opt);
        });
    }
}

// Employee Self-Service: My Profile Modal & Updates
function openMyProfileModal() {
    const user = AppState.currentUser;
    if (!user) {
        showToast("Please log in first.", "error");
        return;
    }

    document.getElementById('my-profile-name').value = user.name || '';
    document.getElementById('my-profile-email').value = user.email || '';
    document.getElementById('my-profile-designation').value = user.designation || '';
    document.getElementById('my-profile-dept-team').value = `${user.department_name || 'Digital'} • ${user.team_name || 'General Team'}`;
    
    // Clear password inputs
    const newPwd = document.getElementById('my-profile-new-password');
    const confPwd = document.getElementById('my-profile-confirm-password');
    if (newPwd) newPwd.value = '';
    if (confPwd) confPwd.value = '';

    // Reset avatar controls
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

            // Update AppState
            if (data.user) {
                AppState.currentUser = data.user;
                
                // Update in employee array
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

// Role & Permissions Delegation Modal Handler
function openPermissionsModal(empId) {
    const emp = AppState.employees.find(e => e.id == empId);
    if (!emp) {
        showToast("Employee record not found.", "error");
        return;
    }

    document.getElementById('perm-emp-id').value = emp.id;
    document.getElementById('perm-emp-name').textContent = emp.name;
    document.getElementById('perm-emp-meta').textContent = `${emp.designation || 'Staff'} • ${emp.department_name || 'Digital'} • ${emp.team_name || 'General'}`;

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

    // Set Role Select
    const roleSelect = document.getElementById('perm-role-select');
    if (roleSelect) {
        roleSelect.value = emp.role || 'employee';
    }

    // Set Checkboxes
    document.getElementById('perm-can-assign-tasks').checked = !!parseInt(emp.can_assign_tasks || (emp.role === 'admin' ? 1 : 0));
    document.getElementById('perm-can-edit-tasks').checked = !!parseInt(emp.can_edit_tasks || (emp.role === 'admin' ? 1 : 0));
    document.getElementById('perm-can-unlock-sheets').checked = !!parseInt(emp.can_unlock_sheets || (emp.role === 'admin' ? 1 : 0));
    document.getElementById('perm-can-inspect-sheets').checked = !!parseInt(emp.can_inspect_sheets || (emp.role === 'admin' ? 1 : 0));
    document.getElementById('perm-can-view-reports').checked = !!parseInt(emp.can_view_reports || (emp.role === 'admin' ? 1 : 0));
    document.getElementById('perm-can-view-attendance').checked = !!parseInt(emp.can_view_attendance || (emp.role === 'admin' ? 1 : 0));
    document.getElementById('perm-can-manage-employees').checked = !!parseInt(emp.can_manage_employees || (emp.role === 'admin' ? 1 : 0));

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

    if (roleValue === 'admin') {
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
        // Regular employee
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

            // If updating current logged in user, refresh their session data
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


