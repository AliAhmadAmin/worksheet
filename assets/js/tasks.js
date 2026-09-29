/**
 * Task Assigner & Tracker: Dedicated Task Command Center & Employee Portal
 */

function formatTaskDateTime(dateStr) {
    if (!dateStr) return null;
    try {
        const d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return dateStr;
        
        const day = String(d.getDate()).padStart(2, '0');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[d.getMonth()];
        const year = d.getFullYear();
        
        let hours = d.getHours();
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        const strHours = String(hours).padStart(2, '0');

        return `${day}-${month}-${year} ${strHours}:${minutes} ${ampm}`;
    } catch (e) {
        return dateStr;
    }
}

// Compact short date/time for concise table columns
function formatTaskDateTimeShort(dateStr) {
    if (!dateStr) return null;
    try {
        const d = new Date(dateStr.replace(/-/g, '/'));
        if (isNaN(d.getTime())) return { date: dateStr, time: '', full: dateStr };
        
        const day = String(d.getDate()).padStart(2, '0');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[d.getMonth()];
        const year = d.getFullYear();
        
        let hours = d.getHours();
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        const strHours = String(hours).padStart(2, '0');

        return {
            date: `${day} ${month} ${year}`,
            time: `${strHours}:${minutes} ${ampm}`,
            full: `${day} ${month} ${year}, ${strHours}:${minutes} ${ampm}`
        };
    } catch (e) {
        return { date: dateStr, time: '', full: dateStr };
    }
}

// Clean and strip quotes from copied Windows paths (e.g. "C:\xampp\htdocs\Worksheet")
function sanitizePathString(str) {
    if (!str) return '';
    return String(str).replace(/^["']+|["']+$/g, '').trim();
}

// Smart Path / Link Renderer for Task Lists, Tables & Modals
function renderPathLinkHtml(rawLink, isCompact = false) {
    if (!rawLink) return '';
    const cleanLink = sanitizePathString(rawLink);
    if (!cleanLink) return '';

    const isWebUrl = cleanLink.startsWith('http://') || cleanLink.startsWith('https://');

    if (isWebUrl) {
        return `
            <div style="display: inline-flex; align-items: center; gap: 4px; max-width: 100%;">
                <a href="${escapeHtml(cleanLink)}" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="padding: 2px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; color: var(--primary); border-color: rgba(59, 130, 246, 0.4); text-decoration: none; border-radius: var(--radius-sm); font-weight: 600;" title="${escapeHtml(cleanLink)}">
                    🔗 <span>Open Link</span>
                </a>
            </div>
        `;
    } else {
        // Windows Local Path or Shared Network Drive
        const fileUrl = 'file:///' + cleanLink.replace(/\\/g, '/');
        return `
            <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: var(--radius-sm); padding: 3px 8px; max-width: 100%;">
                <a href="${escapeHtml(fileUrl)}" target="_blank" data-path="${escapeHtml(cleanLink)}" onclick="handleLocalPathClick(event, this)" style="font-size: 11.5px; font-weight: 600; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: ${isCompact ? '160px' : '320px'}; cursor: pointer;" title="Click to copy & open: ${escapeHtml(cleanLink)}">
                    📁 <span style="font-family: 'JetBrains Mono', monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(cleanLink)}</span>
                </a>
                <button type="button" class="btn btn-outline" data-path="${escapeHtml(cleanLink)}" style="padding: 1px 6px; font-size: 10.5px; border-radius: 4px; font-weight: 700; cursor: pointer; border-color: var(--primary); color: var(--primary); flex-shrink: 0; display: inline-flex; align-items: center; gap: 3px;" onclick="event.stopPropagation(); handleCopyButtonClick(this)" title="Copy clean path to clipboard">
                    📋 <span>Copy</span>
                </button>
            </div>
        `;
    }
}

function handleCopyButtonClick(btn) {
    const raw = btn.getAttribute('data-path') || '';
    copyPathToClipboard(raw);
}

function handleLocalPathClick(e, linkEl) {
    const raw = linkEl.getAttribute('data-path') || '';
    copyPathToClipboard(raw);
}

function copyPathToClipboard(path) {
    const clean = sanitizePathString(path);
    if (!clean) return;

    let successful = false;

    // Fallback method via textarea (works 100% reliably in all browsers/localhost/HTTP/HTTPS)
    try {
        const textarea = document.createElement('textarea');
        textarea.value = clean;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        textarea.style.left = '-9999px';
        textarea.style.top = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        successful = document.execCommand('copy');
        document.body.removeChild(textarea);
    } catch (err) {
        successful = false;
    }

    if (successful) {
        showToast(`📋 Copied: ${clean}`, "success");
        return;
    }

    // Modern Clipboard API if available
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(clean).then(() => {
            showToast(`📋 Copied: ${clean}`, "success");
        }).catch(() => {
            prompt("Copy path below (Ctrl+C):", clean);
        });
    } else {
        prompt("Copy path below (Ctrl+C):", clean);
    }
}

function openOrCopyLocalPath(path, e) {
    const clean = sanitizePathString(path);
    copyPathToClipboard(clean);
}

async function loadAssignedTasks() {
    const listContainer = document.getElementById('employee-task-list');
    const adminTbody = document.getElementById('admin-tasks-tbody');
    const countBadge = document.getElementById('pending-task-badge');

    const statusFilter = document.getElementById('task-filter-status')?.value || '';
    const empFilter = document.getElementById('task-filter-emp')?.value || '';

    let url = 'api/tasks.php?action=get_tasks';
    if (statusFilter) url += `&status=${encodeURIComponent(statusFilter)}`;
    if (empFilter) url += `&employee_id=${encodeURIComponent(empFilter)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) return;

        const tasks = data.tasks || [];
        AppState.tasks = tasks;
        const pendingTasks = tasks.filter(t => t.status !== 'completed');

        if (countBadge) {
            countBadge.textContent = pendingTasks.length;
            countBadge.style.display = pendingTasks.length > 0 ? 'inline-block' : 'none';
        }

        // Render KPI counters on Task Assigner page
        renderTaskMetrics(tasks);

        // Render Employee Sidebar View on Worksheet tab
        if (listContainer) {
            listContainer.innerHTML = '';
            if (tasks.length === 0) {
                listContainer.innerHTML = '<div style="color: var(--text-dim); font-size: 13px; text-align: center; padding: 20px 0;">No tasks assigned to you currently.</div>';
            } else {
                tasks.forEach(t => {
                    listContainer.appendChild(createTaskCard(t));
                });
            }
        }

        // Render Dedicated Admin Task Table on Task Assigner tab
        if (adminTbody) {
            renderAdminTaskList(tasks);
        }

    } catch (err) {
        console.error("Error loading tasks:", err);
    }
}

function renderTaskMetrics(tasks) {
    const totalEl = document.getElementById('task-metric-total');
    const pendingEl = document.getElementById('task-metric-pending');
    const progressEl = document.getElementById('task-metric-progress');
    const completedEl = document.getElementById('task-metric-completed');

    if (!totalEl) return;

    const total = tasks.length;
    const pending = tasks.filter(t => t.status === 'pending').length;
    const progress = tasks.filter(t => t.status === 'in_progress').length;
    const completed = tasks.filter(t => t.status === 'completed').length;

    totalEl.textContent = total;
    if (pendingEl) pendingEl.textContent = pending;
    if (progressEl) progressEl.textContent = progress;
    if (completedEl) completedEl.textContent = completed;

    const dashTasks = document.getElementById('dash-stat-tasks');
    if (dashTasks) {
        dashTasks.textContent = `${pending + progress} Active`;
    }
}

function createTaskCard(task) {
    const card = document.createElement('div');
    card.className = `task-item-card status-${task.status}`;

    const isCompleted = task.status === 'completed';
    const assignedFormatted = formatTaskDateTime(task.created_at);
    const completedFormatted = formatTaskDateTime(task.completed_at);

    card.innerHTML = `
        <div class="task-header" style="justify-content: flex-end;">
            <select class="input-control" style="font-size: 11px; padding: 2px 6px; font-weight: 600;" onchange="updateTaskStatus(${task.id}, this.value)">
                <option value="pending" ${task.status === 'pending' ? 'selected' : ''}>⏳ Pending</option>
                <option value="in_progress" ${task.status === 'in_progress' ? 'selected' : ''}>⚡ In Progress</option>
                <option value="completed" ${task.status === 'completed' ? 'selected' : ''}>✅ Completed</option>
            </select>
        </div>
        <div class="task-title" style="${isCompleted ? 'text-decoration: line-through; opacity: 0.7;' : ''}">${escapeHtml(task.title)}</div>
        ${task.description ? `<div class="task-desc">${escapeHtml(task.description)}</div>` : ''}
        ${task.link ? `<div style="margin-top: 6px;">${renderPathLinkHtml(task.link, false)}</div>` : ''}
        
        <div style="font-size: 11.5px; color: var(--text-dim); margin-top: 6px; display: flex; flex-direction: column; gap: 3px;">
            ${assignedFormatted ? `<div>🕒 <strong style="color: var(--text-muted);">Assigned:</strong> ${escapeHtml(assignedFormatted)}</div>` : ''}
            ${isCompleted && completedFormatted ? `<div style="color: #10b981;">✅ <strong>Completed:</strong> ${escapeHtml(completedFormatted)}</div>` : ''}
        </div>

        <div class="task-footer-bar" style="margin-top: 10px; justify-content: flex-end;">
            <button type="button" class="btn-convert-task" onclick="convertTaskToSheetEntry(${JSON.stringify(task).replace(/"/g, '&quot;')})">
                + Add to Sheet
            </button>
        </div>
    `;

    return card;
}

async function updateTaskStatus(taskId, newStatus) {
    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_status',
                task_id: taskId,
                status: newStatus
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            await loadAssignedTasks();
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast("Failed to update status", "error");
    }
}

// 1-Click Convert Task into Daily Worksheet Row
function convertTaskToSheetEntry(task) {
    if (AppState.isLocked && AppState.currentUser.role !== 'admin') {
        showToast("Cannot add entries because today's sheet is locked.", "error");
        return;
    }

    const cleanLink = sanitizePathString(task.link);

    addTableRow({
        time_slot: '',
        content_type: '',
        department: '',
        link: cleanLink,
        title: task.title
    }, true);

    showToast(`Added "${task.title}" to your daily worksheet!`, 'success');
}

// Helper: Format Date object to YYYY-MM-DD
function formatDateToYMD(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Date Stepper Adjusters for Assign Modal
function adjustAssignDueDate(deltaDays) {
    const input = document.getElementById('assign-task-due-date');
    if (!input) return;
    const current = input.value ? new Date(input.value + 'T00:00:00') : new Date();
    current.setDate(current.getDate() + deltaDays);
    input.value = formatDateToYMD(current);
}

function setAssignDueDateToday() {
    const input = document.getElementById('assign-task-due-date');
    if (input) input.value = formatDateToYMD(new Date());
}

// Date Stepper Adjusters for Edit Modal
function adjustEditDueDate(deltaDays) {
    const input = document.getElementById('edit-task-due-date');
    if (!input) return;
    const current = input.value ? new Date(input.value + 'T00:00:00') : new Date();
    current.setDate(current.getDate() + deltaDays);
    input.value = formatDateToYMD(current);
}

function setEditDueDateToday() {
    const input = document.getElementById('edit-task-due-date');
    if (input) input.value = formatDateToYMD(new Date());
}

// Searchable Employee Dropdown for Assign Task Modal
function renderAssignTaskEmpDropdownList(employees) {
    const list = document.getElementById('assign-task-emp-dropdown-list');
    if (!list) return;

    list.innerHTML = '';
    if (!employees || employees.length === 0) {
        list.innerHTML = '<div style="padding: 10px; text-align: center; color: var(--text-muted); font-size: 12px;">No employee found</div>';
        return;
    }

    const currentVal = document.getElementById('assign-task-emp-select')?.value;

    employees.forEach(emp => {
        const isSelected = currentVal == emp.id;
        const item = document.createElement('div');
        item.className = `searchable-emp-item ${isSelected ? 'selected' : ''}`;
        item.style.cssText = `padding: 7px 10px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 12.5px; transition: background 0.15s; ${isSelected ? 'background: rgba(59, 130, 246, 0.12); font-weight: 700;' : ''}`;
        item.onclick = () => selectAssignTaskEmpFromDropdown(emp.id);

        const avatarInitial = emp.name ? emp.name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = emp.avatar 
            ? `<img src="${escapeHtml(emp.avatar)}" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.onerror=null; this.outerHTML='<div style=\\'width:26px;height:26px;border-radius:50%;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;\\'>${escapeHtml(avatarInitial)}</div>';">`
            : `<div style="width: 26px; height: 26px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0;">${escapeHtml(avatarInitial)}</div>`;

        item.innerHTML = `
            ${avatarHtml}
            <div style="flex-grow: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <div style="color: var(--text-main); line-height: 1.2;">${escapeHtml(emp.name)}</div>
                <div style="font-size: 11px; color: var(--text-muted); font-weight: normal;">${escapeHtml(emp.designation || 'Staff')} • ${escapeHtml(emp.team_name || emp.department_name || 'Team')}</div>
            </div>
            ${isSelected ? '<span style="color: var(--primary); font-size: 12px; font-weight: 800;">✓</span>' : ''}
        `;
        list.appendChild(item);
    });
}

function filterAssignTaskEmpDropdown(query) {
    const q = (query || '').trim().toLowerCase();
    if (!q) {
        renderAssignTaskEmpDropdownList(AppState.employees);
        return;
    }
    const filtered = (AppState.employees || []).filter(emp => 
        emp.name.toLowerCase().includes(q) || 
        (emp.email && emp.email.toLowerCase().includes(q)) ||
        (emp.team_name && emp.team_name.toLowerCase().includes(q)) ||
        (emp.department_name && emp.department_name.toLowerCase().includes(q))
    );
    renderAssignTaskEmpDropdownList(filtered);
}

function toggleAssignTaskEmpDropdown() {
    const menu = document.getElementById('assign-task-emp-dropdown-menu');
    const input = document.getElementById('assign-task-emp-search-input');
    if (!menu) return;

    const isVisible = menu.style.display === 'block';
    if (isVisible) {
        menu.style.display = 'none';
    } else {
        menu.style.display = 'block';
        if (input) {
            input.value = '';
            filterAssignTaskEmpDropdown('');
            setTimeout(() => input.focus(), 60);
        }
    }
}

function selectAssignTaskEmpFromDropdown(empId) {
    const menu = document.getElementById('assign-task-emp-dropdown-menu');
    if (menu) menu.style.display = 'none';

    const hiddenInput = document.getElementById('assign-task-emp-select');
    if (hiddenInput) hiddenInput.value = empId;

    const emp = (AppState.employees || []).find(e => e.id == empId);
    const labelSpan = document.getElementById('assign-task-emp-selected-name');
    const avatarBox = document.getElementById('assign-task-emp-avatar');

    if (labelSpan && emp) {
        labelSpan.textContent = emp.name;
    }

    if (avatarBox && emp) {
        const initial = emp.name ? emp.name.charAt(0).toUpperCase() : '👤';
        if (emp.avatar) {
            avatarBox.innerHTML = `<img src="${escapeHtml(emp.avatar)}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" alt="${escapeHtml(emp.name)}" onerror="this.onerror=null; this.outerHTML='<span>${escapeHtml(initial)}</span>';">`;
        } else {
            avatarBox.innerHTML = `<span>${escapeHtml(initial)}</span>`;
        }
    }

    renderAssignTaskEmpDropdownList(AppState.employees);
}

// Searchable Employee Dropdown for Edit Task Modal
function renderEditTaskEmpDropdownList(employees) {
    const list = document.getElementById('edit-task-emp-dropdown-list');
    if (!list) return;

    list.innerHTML = '';
    if (!employees || employees.length === 0) {
        list.innerHTML = '<div style="padding: 10px; text-align: center; color: var(--text-muted); font-size: 12px;">No employee found</div>';
        return;
    }

    const currentVal = document.getElementById('edit-task-emp-select')?.value;

    employees.forEach(emp => {
        const isSelected = currentVal == emp.id;
        const item = document.createElement('div');
        item.className = `searchable-emp-item ${isSelected ? 'selected' : ''}`;
        item.style.cssText = `padding: 7px 10px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 12.5px; transition: background 0.15s; ${isSelected ? 'background: rgba(59, 130, 246, 0.12); font-weight: 700;' : ''}`;
        item.onclick = () => selectEditTaskEmpFromDropdown(emp.id);

        const avatarInitial = emp.name ? emp.name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = emp.avatar 
            ? `<img src="${escapeHtml(emp.avatar)}" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.onerror=null; this.outerHTML='<div style=\\'width:26px;height:26px;border-radius:50%;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;\\'>${escapeHtml(avatarInitial)}</div>';">`
            : `<div style="width: 26px; height: 26px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0;">${escapeHtml(avatarInitial)}</div>`;

        item.innerHTML = `
            ${avatarHtml}
            <div style="flex-grow: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <div style="color: var(--text-main); line-height: 1.2;">${escapeHtml(emp.name)}</div>
                <div style="font-size: 11px; color: var(--text-muted); font-weight: normal;">${escapeHtml(emp.designation || 'Staff')} • ${escapeHtml(emp.team_name || emp.department_name || 'Team')}</div>
            </div>
            ${isSelected ? '<span style="color: var(--primary); font-size: 12px; font-weight: 800;">✓</span>' : ''}
        `;
        list.appendChild(item);
    });
}

function filterEditTaskEmpDropdown(query) {
    const q = (query || '').trim().toLowerCase();
    if (!q) {
        renderEditTaskEmpDropdownList(AppState.employees);
        return;
    }
    const filtered = (AppState.employees || []).filter(emp => 
        emp.name.toLowerCase().includes(q) || 
        (emp.email && emp.email.toLowerCase().includes(q)) ||
        (emp.team_name && emp.team_name.toLowerCase().includes(q)) ||
        (emp.department_name && emp.department_name.toLowerCase().includes(q))
    );
    renderEditTaskEmpDropdownList(filtered);
}

function toggleEditTaskEmpDropdown() {
    const menu = document.getElementById('edit-task-emp-dropdown-menu');
    const input = document.getElementById('edit-task-emp-search-input');
    if (!menu) return;

    const isVisible = menu.style.display === 'block';
    if (isVisible) {
        menu.style.display = 'none';
    } else {
        menu.style.display = 'block';
        if (input) {
            input.value = '';
            filterEditTaskEmpDropdown('');
            setTimeout(() => input.focus(), 60);
        }
    }
}

function selectEditTaskEmpFromDropdown(empId) {
    const menu = document.getElementById('edit-task-emp-dropdown-menu');
    if (menu) menu.style.display = 'none';

    const hiddenInput = document.getElementById('edit-task-emp-select');
    if (hiddenInput) hiddenInput.value = empId;

    const emp = (AppState.employees || []).find(e => e.id == empId);
    const labelSpan = document.getElementById('edit-task-emp-selected-name');
    const avatarBox = document.getElementById('edit-task-emp-avatar');

    if (labelSpan && emp) {
        labelSpan.textContent = emp.name;
    }

    if (avatarBox && emp) {
        const initial = emp.name ? emp.name.charAt(0).toUpperCase() : '👤';
        if (emp.avatar) {
            avatarBox.innerHTML = `<img src="${escapeHtml(emp.avatar)}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" alt="${escapeHtml(emp.name)}" onerror="this.onerror=null; this.outerHTML='<span>${escapeHtml(initial)}</span>';">`;
        } else {
            avatarBox.innerHTML = `<span>${escapeHtml(initial)}</span>`;
        }
    }

    renderEditTaskEmpDropdownList(AppState.employees);
}

// Admin Task Creation Modal & Actions
function openAssignTaskModal() {
    // Default to the currently inspected admin employee or the first available employee
    const defaultEmpId = AppState.adminSelectedEmpId || (AppState.employees && AppState.employees[0] ? AppState.employees[0].id : '');
    
    const hiddenInput = document.getElementById('assign-task-emp-select');
    if (hiddenInput) hiddenInput.value = defaultEmpId;

    const titleInput = document.getElementById('assign-task-title');
    if (titleInput) titleInput.value = '';

    const descInput = document.getElementById('assign-task-desc');
    if (descInput) descInput.value = '';

    const linkInput = document.getElementById('assign-task-link');
    if (linkInput) linkInput.value = '';

    const dateInput = document.getElementById('assign-task-due-date');
    if (dateInput) dateInput.value = formatDateToYMD(new Date());

    if (defaultEmpId) {
        selectAssignTaskEmpFromDropdown(defaultEmpId);
    } else {
        const labelSpan = document.getElementById('assign-task-emp-selected-name');
        if (labelSpan) labelSpan.textContent = '-- Select Employee --';
        const avatarBox = document.getElementById('assign-task-emp-avatar');
        if (avatarBox) avatarBox.innerHTML = '👤';
    }

    renderAssignTaskEmpDropdownList(AppState.employees);
    openModal('assign-task-modal');
}

async function handleCreateTaskSubmit(e) {
    if (e) e.preventDefault();

    const empId = document.getElementById('assign-task-emp-select')?.value;
    const title = document.getElementById('assign-task-title')?.value;
    const desc = document.getElementById('assign-task-desc')?.value;
    const rawLink = document.getElementById('assign-task-link')?.value || '';
    const cleanLink = sanitizePathString(rawLink);

    if (!empId || !title) {
        showToast("Please select an employee and enter a task title.", "error");
        return;
    }

    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_task',
                assigned_to: empId,
                title: title,
                description: desc,
                link: cleanLink
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('assign-task-modal');
            document.getElementById('assign-task-form')?.reset();
            await loadAssignedTasks();
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast("Failed to assign task.", "error");
    }
}

// Admin Task Edit Modal & Action
function openEditTaskModal(taskId) {
    if (!hasPermission('can_edit_tasks')) {
        showToast("You do not have permission to edit tasks.", "error");
        return;
    }

    const task = (AppState.tasks || []).find(t => t.id == taskId);
    if (!task) {
        showToast("Task not found", "error");
        return;
    }

    // Pre-select employee in searchable dropdown
    selectEditTaskEmpFromDropdown(task.assigned_to);

    // Pre-fill inputs
    document.getElementById('edit-task-id').value = task.id;
    document.getElementById('edit-task-title').value = task.title || '';
    document.getElementById('edit-task-desc').value = task.description || '';
    document.getElementById('edit-task-link').value = sanitizePathString(task.link);
    document.getElementById('edit-task-status').value = task.status || 'pending';

    // Set Timestamps Display
    const createdAtFormatted = formatTaskDateTime(task.created_at);
    const completedAtFormatted = formatTaskDateTime(task.completed_at);

    const createdEl = document.getElementById('edit-task-created-at-display');
    if (createdEl) {
        createdEl.textContent = createdAtFormatted || '—';
    }

    const completedEl = document.getElementById('edit-task-completed-at-display');
    if (completedEl) {
        if (task.status === 'completed' && completedAtFormatted) {
            completedEl.textContent = completedAtFormatted;
            completedEl.style.color = '#10b981';
        } else {
            completedEl.textContent = '— Not Completed (Pending)';
            completedEl.style.color = 'var(--text-dim)';
        }
    }

    renderEditTaskEmpDropdownList(AppState.employees);
    openModal('edit-task-modal');
}

async function handleEditTaskSubmit(e) {
    if (e) e.preventDefault();

    if (!hasPermission('can_edit_tasks')) {
        showToast("You do not have permission to edit tasks.", "error");
        return;
    }

    const taskId = document.getElementById('edit-task-id')?.value;
    const empId = document.getElementById('edit-task-emp-select')?.value;
    const title = document.getElementById('edit-task-title')?.value;
    const desc = document.getElementById('edit-task-desc')?.value;
    const rawLink = document.getElementById('edit-task-link')?.value || '';
    const cleanLink = sanitizePathString(rawLink);
    const status = document.getElementById('edit-task-status')?.value || 'pending';

    if (!taskId || !empId || !title) {
        showToast("Please select an employee and enter a task title.", "error");
        return;
    }

    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_task',
                task_id: taskId,
                assigned_to: empId,
                title: title,
                description: desc,
                link: cleanLink,
                status: status
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('edit-task-modal');
            await loadAssignedTasks();
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast("Failed to update task.", "error");
    }
}

// View Full Task Details Modal
function openViewTaskModal(taskId) {
    const task = (AppState.tasks || []).find(t => t.id == taskId);
    if (!task) return;

    const titleEl = document.getElementById('view-task-title');
    const empEl = document.getElementById('view-task-emp-name');
    const statusBadgeEl = document.getElementById('view-task-status-badge');
    const descEl = document.getElementById('view-task-desc');
    const linkEl = document.getElementById('view-task-link');
    const createdEl = document.getElementById('view-task-created-at');
    const completedEl = document.getElementById('view-task-completed-at');
    const editBtn = document.getElementById('view-task-edit-btn');

    if (titleEl) titleEl.textContent = task.title || '—';
    if (empEl) empEl.textContent = `${task.employee_name || 'Unassigned'} • ${task.employee_designation || 'Staff'}`;

    const statusBadges = {
        'pending': '<span class="status-badge pending" style="background: rgba(234, 179, 8, 0.15); color: #ca8a04; border: 1px solid rgba(234, 179, 8, 0.3); padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 11.5px;">⏳ Pending</span>',
        'in_progress': '<span class="status-badge progress" style="background: rgba(59, 130, 246, 0.15); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3); padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 11.5px;">⚡ In Progress</span>',
        'completed': '<span class="status-badge completed" style="background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 11.5px;">✅ Completed</span>'
    };
    if (statusBadgeEl) statusBadgeEl.innerHTML = statusBadges[task.status] || task.status;

    if (descEl) descEl.textContent = task.description ? task.description : 'No description provided.';
    if (linkEl) linkEl.innerHTML = task.link ? renderPathLinkHtml(task.link, false) : '<span style="color: var(--text-dim); font-size: 12px;">No path or link attached</span>';

    const shortCreated = formatTaskDateTimeShort(task.created_at);
    const shortCompleted = formatTaskDateTimeShort(task.completed_at);

    if (createdEl) createdEl.textContent = shortCreated ? shortCreated.full : '—';
    if (completedEl) {
        if (task.status === 'completed' && shortCompleted) {
            completedEl.textContent = shortCompleted.full;
            completedEl.style.color = '#10b981';
        } else {
            completedEl.textContent = '— Not Completed (Pending)';
            completedEl.style.color = 'var(--text-dim)';
        }
    }

    if (editBtn) {
        if (hasPermission('can_edit_tasks')) {
            editBtn.style.display = 'inline-flex';
            editBtn.onclick = () => {
                closeModal('view-task-modal');
                openEditTaskModal(task.id);
            };
        } else {
            editBtn.style.display = 'none';
        }
    }

    openModal('view-task-modal');
}

function renderAdminTaskList(tasks) {
    const tbody = document.getElementById('admin-tasks-tbody');
    if (!tbody) return;

    const canEdit = hasPermission('can_edit_tasks');

    tbody.innerHTML = '';
    if (tasks.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-dim); padding: 30px;">No assigned tasks matching your filters.</td></tr>';
        return;
    }

    tasks.forEach(t => {
        const tr = document.createElement('tr');
        const assignedShort = formatTaskDateTimeShort(t.created_at);
        const completedShort = formatTaskDateTimeShort(t.completed_at);
        const isCompleted = t.status === 'completed';

        tr.innerHTML = `
            <td>
                <strong style="color: var(--text-main); font-size: 12.5px;">${escapeHtml(t.employee_name)}</strong>
                <div style="font-size: 11px; color: var(--text-dim);">${escapeHtml(t.employee_designation || 'Staff')}</div>
            </td>
            <td>
                <div style="font-weight: 700; font-size: 13px; color: var(--primary); cursor: pointer; ${isCompleted ? 'text-decoration: line-through; opacity: 0.75;' : ''}" onclick="openViewTaskModal(${t.id})" title="Click to view complete details">
                    ${escapeHtml(t.title)}
                </div>
            </td>
            <td>
                ${t.description ? `
                    <div style="font-size: 12.5px; color: var(--text-main); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.35; cursor: pointer;" onclick="openViewTaskModal(${t.id})" title="Click to view full description: ${escapeHtml(t.description)}">
                        ${escapeHtml(t.description)}
                    </div>
                ` : `<div style="color: var(--text-dim); font-size: 11.5px; font-style: italic;">No description</div>`}
                ${t.link ? `<div style="margin-top: 6px;">${renderPathLinkHtml(t.link, false)}</div>` : ''}
            </td>
            <td>
                ${assignedShort ? `
                    <div style="line-height: 1.25;">
                        <div style="font-weight: 700; font-size: 11.5px; color: var(--text-main);">${escapeHtml(assignedShort.date)}</div>
                        <div style="font-size: 10.5px; color: var(--text-dim); font-family: 'JetBrains Mono', monospace;">${escapeHtml(assignedShort.time)}</div>
                    </div>
                ` : `<span style="color: var(--text-dim); font-size: 11.5px;">—</span>`}
            </td>
            <td>
                ${isCompleted && completedShort ? `
                    <div style="line-height: 1.25; color: #10b981;">
                        <div style="font-weight: 700; font-size: 11.5px;">${escapeHtml(completedShort.date)}</div>
                        <div style="font-size: 10.5px; font-family: 'JetBrains Mono', monospace;">${escapeHtml(completedShort.time)}</div>
                    </div>
                ` : `
                    <span style="font-size: 13px; color: var(--text-dim); font-weight: 600;">—</span>
                `}
            </td>
            <td>
                <select class="input-control" style="font-size: 11.5px; padding: 4px 8px; font-weight: 700; width: 100%; min-width: 115px; border-radius: var(--radius-sm); cursor: pointer;" onchange="updateTaskStatus(${t.id}, this.value)">
                    <option value="pending" ${t.status === 'pending' ? 'selected' : ''}>⏳ Pending</option>
                    <option value="in_progress" ${t.status === 'in_progress' ? 'selected' : ''}>⚡ In Progress</option>
                    <option value="completed" ${t.status === 'completed' ? 'selected' : ''}>✅ Completed</option>
                </select>
            </td>
            <td>
                <div style="display: flex; align-items: center; gap: 4px; justify-content: center; white-space: nowrap;">
                    <button type="button" class="btn btn-outline" style="padding: 4px 7px; font-size: 11px;" onclick="openViewTaskModal(${t.id})" title="View Details">
                        👁️
                    </button>
                    ${canEdit ? `
                        <button type="button" class="btn btn-outline" style="padding: 4px 7px; font-size: 11px;" onclick="openEditTaskModal(${t.id})" title="Edit Task">
                            ✏️
                        </button>
                        <button type="button" class="btn-icon-del" style="padding: 4px 6px; font-size: 11.5px;" onclick="deleteTask(${t.id})" title="Delete Task">
                            🗑️
                        </button>
                    ` : ''}
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function deleteTask(taskId) {
    if (!hasPermission('can_edit_tasks')) {
        showToast("You do not have permission to delete tasks.", "error");
        return;
    }

    if (!confirm("Are you sure you want to delete this assigned task?")) return;
    try {
        const res = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_task', task_id: taskId })
        });
        const data = await res.json();
        if (data.success) {
            showToast("Task deleted successfully", "info");
            await loadAssignedTasks();
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to delete task", "error");
    }
}

// Searchable Dropdown for Task Assigner Employee Filter
function populateTaskEmployeeFilter() {
    const select = document.getElementById('task-filter-emp');
    const selectedNameSpan = document.getElementById('task-emp-dropdown-selected-name');

    if (select) {
        select.innerHTML = '<option value="">All Employees</option>';
        AppState.employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = emp.name;
            select.appendChild(opt);
        });
    }

    renderTaskEmpDropdownList(AppState.employees);

    if (selectedNameSpan && !AppState.taskFilterEmpId) {
        selectedNameSpan.textContent = 'All Employees';
    }
}

function renderTaskEmpDropdownList(employees) {
    const list = document.getElementById('task-emp-dropdown-list');
    if (!list) return;

    list.innerHTML = '';

    // "All Employees" default item
    const allSelected = !AppState.taskFilterEmpId;
    const allItem = document.createElement('div');
    allItem.className = `searchable-emp-item ${allSelected ? 'selected' : ''}`;
    allItem.style.cssText = `padding: 6px 8px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 12.5px; transition: background 0.15s; ${allSelected ? 'background: rgba(59, 130, 246, 0.12); font-weight: 700;' : ''}`;
    allItem.onclick = () => selectTaskEmpFromDropdown('');
    allItem.innerHTML = `
        <div style="width: 24px; height: 24px; border-radius: 50%; background: #64748b; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0;">👥</div>
        <div style="flex-grow: 1;">
            <div style="color: var(--text-main); font-weight: 700;">All Employees</div>
        </div>
        ${allSelected ? '<span style="color: var(--primary); font-size: 12px; font-weight: 800;">✓</span>' : ''}
    `;
    list.appendChild(allItem);

    if (!employees || employees.length === 0) return;

    employees.forEach(emp => {
        const isSelected = AppState.taskFilterEmpId == emp.id;
        const item = document.createElement('div');
        item.className = `searchable-emp-item ${isSelected ? 'selected' : ''}`;
        item.style.cssText = `padding: 6px 8px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 12.5px; transition: background 0.15s; ${isSelected ? 'background: rgba(59, 130, 246, 0.12); font-weight: 700;' : ''}`;
        item.onclick = () => selectTaskEmpFromDropdown(emp.id);

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

function filterTaskEmpDropdown(query) {
    const q = (query || '').trim().toLowerCase();
    if (!q) {
        renderTaskEmpDropdownList(AppState.employees);
        return;
    }
    const filtered = AppState.employees.filter(emp => 
        emp.name.toLowerCase().includes(q) || 
        (emp.team_name && emp.team_name.toLowerCase().includes(q)) ||
        (emp.department_name && emp.department_name.toLowerCase().includes(q))
    );
    renderTaskEmpDropdownList(filtered);
}

function toggleTaskEmpDropdown() {
    const menu = document.getElementById('task-emp-dropdown-menu');
    const input = document.getElementById('task-emp-search-input');
    if (!menu) return;

    const isVisible = menu.style.display === 'block';
    if (isVisible) {
        menu.style.display = 'none';
    } else {
        menu.style.display = 'block';
        if (input) {
            input.value = '';
            filterTaskEmpDropdown('');
            setTimeout(() => input.focus(), 60);
        }
    }
}

function selectTaskEmpFromDropdown(empId) {
    const menu = document.getElementById('task-emp-dropdown-menu');
    if (menu) menu.style.display = 'none';

    AppState.taskFilterEmpId = empId ? parseInt(empId) : '';

    const select = document.getElementById('task-filter-emp');
    if (select) select.value = AppState.taskFilterEmpId;

    const selectedNameSpan = document.getElementById('task-emp-dropdown-selected-name');
    if (selectedNameSpan) {
        if (empId) {
            const currentEmp = AppState.employees.find(e => e.id == empId);
            selectedNameSpan.textContent = currentEmp ? currentEmp.name : 'All Employees';
        } else {
            selectedNameSpan.textContent = 'All Employees';
        }
    }

    renderTaskEmpDropdownList(AppState.employees);
    loadAssignedTasks();
}

// Global click outside listener to auto-close dropdowns
document.addEventListener('click', (e) => {
    // Task Filter Dropdown
    const filterWrapper = document.getElementById('task-emp-search-wrapper');
    const filterMenu = document.getElementById('task-emp-dropdown-menu');
    if (filterWrapper && filterMenu && !filterWrapper.contains(e.target)) {
        filterMenu.style.display = 'none';
    }

    // Assign Task Modal Dropdown
    const assignWrapper = document.getElementById('assign-task-emp-search-wrapper');
    const assignMenu = document.getElementById('assign-task-emp-dropdown-menu');
    if (assignWrapper && assignMenu && !assignWrapper.contains(e.target)) {
        assignMenu.style.display = 'none';
    }

    // Edit Task Modal Dropdown
    const editWrapper = document.getElementById('edit-task-emp-search-wrapper');
    const editMenu = document.getElementById('edit-task-emp-dropdown-menu');
    if (editWrapper && editMenu && !editWrapper.contains(e.target)) {
        editMenu.style.display = 'none';
    }
});
