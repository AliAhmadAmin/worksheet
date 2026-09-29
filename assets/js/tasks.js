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
        hours = hours ? hours : 12; // the hour '0' should be '12'
        const strHours = String(hours).padStart(2, '0');

        return `${day}-${month}-${year} ${strHours}:${minutes} ${ampm}`;
    } catch (e) {
        return dateStr;
    }
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

    const priorityClass = task.priority || 'medium';
    const isCompleted = task.status === 'completed';
    const assignedFormatted = formatTaskDateTime(task.created_at);
    const completedFormatted = formatTaskDateTime(task.completed_at);

    card.innerHTML = `
        <div class="task-header">
            <span class="task-priority-pill ${priorityClass}">${task.priority}</span>
            <select class="input-control" style="font-size: 11px; padding: 2px 6px;" onchange="updateTaskStatus(${task.id}, this.value)">
                <option value="pending" ${task.status === 'pending' ? 'selected' : ''}>⏳ Pending</option>
                <option value="in_progress" ${task.status === 'in_progress' ? 'selected' : ''}>⚡ In Progress</option>
                <option value="completed" ${task.status === 'completed' ? 'selected' : ''}>✅ Completed</option>
            </select>
        </div>
        <div class="task-title" style="${isCompleted ? 'text-decoration: line-through; opacity: 0.7;' : ''}">${escapeHtml(task.title)}</div>
        ${task.description ? `<div class="task-desc">${escapeHtml(task.description)}</div>` : ''}
        
        <div style="font-size: 11px; color: var(--text-dim); margin-top: 6px; display: flex; flex-direction: column; gap: 2px;">
            ${assignedFormatted ? `<div>📅 <strong style="color: var(--text-muted);">Assigned:</strong> ${escapeHtml(assignedFormatted)}</div>` : ''}
            ${isCompleted && completedFormatted ? `<div style="color: #10b981;">✅ <strong>Completed:</strong> ${escapeHtml(completedFormatted)}</div>` : ''}
        </div>

        <div class="task-footer-bar" style="margin-top: 10px;">
            <span class="task-category-tag">📂 ${escapeHtml(task.content_type || 'General')} • ${escapeHtml(task.department || 'Digital')}</span>
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

    const defaultTimeSlot = "11:30 AM to 01:00 PM";
    addTableRow({
        time_slot: defaultTimeSlot,
        content_type: task.content_type || 'FB Videos',
        department: task.department || 'Digital',
        link: '',
        title: task.title
    }, true);

    showToast(`Added "${task.title}" to your daily worksheet!`, 'success');
}

// Admin Task Creation Modal & Actions
function openAssignTaskModal() {
    const empSelect = document.getElementById('assign-task-emp-select');
    if (empSelect) {
        empSelect.innerHTML = '<option value="">-- Select Employee --</option>';
        AppState.employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.email}) [${emp.team_name || 'General'}]`;
            empSelect.appendChild(opt);
        });
    }

    openModal('assign-task-modal');
}

async function handleCreateTaskSubmit(e) {
    if (e) e.preventDefault();

    const empId = document.getElementById('assign-task-emp-select')?.value;
    const title = document.getElementById('assign-task-title')?.value;
    const desc = document.getElementById('assign-task-desc')?.value;
    const contentType = document.getElementById('assign-task-content-type')?.value;
    const department = document.getElementById('assign-task-dept')?.value;
    const priority = document.getElementById('assign-task-priority')?.value;
    const dueDate = document.getElementById('assign-task-due-date')?.value || new Date().toISOString().split('T')[0];

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
                content_type: contentType,
                department: department,
                priority: priority,
                due_date: dueDate
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

    // Populate employee select options
    const empSelect = document.getElementById('edit-task-emp-select');
    if (empSelect) {
        empSelect.innerHTML = '<option value="">-- Select Employee --</option>';
        AppState.employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.email}) [${emp.team_name || 'General'}]`;
            if (emp.id == task.assigned_to) {
                opt.selected = true;
            }
            empSelect.appendChild(opt);
        });
    }

    // Pre-fill inputs
    document.getElementById('edit-task-id').value = task.id;
    document.getElementById('edit-task-title').value = task.title || '';
    document.getElementById('edit-task-desc').value = task.description || '';
    document.getElementById('edit-task-content-type').value = task.content_type || 'FB Videos';
    document.getElementById('edit-task-dept').value = task.department || 'Digital';
    document.getElementById('edit-task-priority').value = task.priority || 'medium';
    document.getElementById('edit-task-due-date').value = task.due_date || '';
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
    const contentType = document.getElementById('edit-task-content-type')?.value;
    const department = document.getElementById('edit-task-dept')?.value;
    const priority = document.getElementById('edit-task-priority')?.value;
    const dueDate = document.getElementById('edit-task-due-date')?.value || '';
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
                content_type: contentType,
                department: department,
                priority: priority,
                due_date: dueDate,
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
        const assignedTimeFormatted = formatTaskDateTime(t.created_at) || '—';
        const completedTimeFormatted = formatTaskDateTime(t.completed_at);
        const isCompleted = t.status === 'completed';

        tr.innerHTML = `
            <td>
                <strong>${escapeHtml(t.employee_name)}</strong>
                <div style="font-size: 11px; color: var(--text-dim);">${escapeHtml(t.employee_designation || 'Staff')}</div>
            </td>
            <td>
                <div style="font-weight: 700; ${isCompleted ? 'text-decoration: line-through; opacity: 0.75;' : ''}">${escapeHtml(t.title)}</div>
                ${t.description ? `<div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">${escapeHtml(t.description)}</div>` : ''}
                ${t.due_date ? `<div style="font-size: 11px; color: var(--text-dim); margin-top: 3px;">📅 Due: ${escapeHtml(t.due_date)}</div>` : ''}
            </td>
            <td>
                <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                    <span class="task-priority-pill ${t.priority}">${t.priority}</span>
                    <span style="font-size: 11px; color: var(--text-muted);">${escapeHtml(t.content_type || 'General')} • ${escapeHtml(t.department || 'Digital')}</span>
                </div>
            </td>
            <td>
                <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--text-main);">
                    <span>📅</span>
                    <span>${escapeHtml(assignedTimeFormatted)}</span>
                </div>
            </td>
            <td>
                ${isCompleted && completedTimeFormatted ? `
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #10b981;">
                        <span>✅</span>
                        <span>${escapeHtml(completedTimeFormatted)}</span>
                    </div>
                ` : `
                    <span style="font-size: 11.5px; color: var(--text-dim); background: var(--bg-card-elevated); padding: 3px 8px; border-radius: 4px; border: 1px solid var(--border-color);">
                        ⏳ ${t.status === 'in_progress' ? 'In Progress' : 'Pending'}
                    </span>
                `}
            </td>
            <td>
                <select class="input-control" style="font-size: 11.5px; padding: 4px 8px; font-weight: 600;" onchange="updateTaskStatus(${t.id}, this.value)">
                    <option value="pending" ${t.status === 'pending' ? 'selected' : ''}>⏳ Pending</option>
                    <option value="in_progress" ${t.status === 'in_progress' ? 'selected' : ''}>⚡ In Progress</option>
                    <option value="completed" ${t.status === 'completed' ? 'selected' : ''}>✅ Completed</option>
                </select>
            </td>
            <td>
                ${canEdit ? `
                    <div style="display: flex; align-items: center; gap: 8px; justify-content: center; white-space: nowrap;">
                        <button type="button" class="btn btn-outline" style="padding: 5px 10px; font-size: 11.5px; white-space: nowrap;" onclick="openEditTaskModal(${t.id})" title="Edit Task Details">
                            ✏️ Edit
                        </button>
                        <button type="button" class="btn-icon-del" style="padding: 5px 9px; font-size: 13px;" onclick="deleteTask(${t.id})" title="Delete Task">
                            🗑️
                        </button>
                    </div>
                ` : `
                    <div style="text-align: center; color: var(--text-muted); font-size: 11.5px; opacity: 0.6;">
                        —
                    </div>
                `}
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

document.addEventListener('click', (e) => {
    const wrapper = document.getElementById('task-emp-search-wrapper');
    const menu = document.getElementById('task-emp-dropdown-menu');
    if (wrapper && menu && !wrapper.contains(e.target)) {
        menu.style.display = 'none';
    }
});
