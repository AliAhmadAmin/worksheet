/**
 * Reports & Matrix Analytics: Replicating Google Sheets Cross-Tabulation & Performance Rosters
 */

async function loadReports() {
    const startDate = document.getElementById('report-start-date')?.value || new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0];
    const endDate = document.getElementById('report-end-date')?.value || new Date().toISOString().split('T')[0];

    try {
        const res = await fetch(`api/reports.php?action=matrix&start_date=${startDate}&end_date=${endDate}`);
        const data = await res.json();

        if (!data.success) {
            showToast("Failed to load reports.", "error");
            return;
        }

        renderDepartmentMatrix(data);
        renderEmployeeBreakdown(data);
        renderDutyStats(data.duty_summary);

    } catch (err) {
        console.error("Error loading reports:", err);
    }
}

// Render Matrix 1: Department vs Content Type (Matching Screenshot 4)
function renderDepartmentMatrix(data) {
    const thead = document.getElementById('matrix-dept-thead');
    const tbody = document.getElementById('matrix-dept-tbody');
    if (!thead || !tbody) return;

    const contentTypes = data.content_types || ['Reels', 'YT Videos', 'Post Cards', 'FB Videos', 'Podcast'];

    // Header row
    thead.innerHTML = `
        <tr>
            <th class="col-dept">Departments</th>
            ${contentTypes.map(ct => `<th>${ct}</th>`).join('')}
            <th class="col-total">Total</th>
        </tr>
    `;

    // Body rows
    tbody.innerHTML = '';
    const deptRows = data.department_matrix || [];

    deptRows.forEach(dept => {
        const tr = document.createElement('tr');
        const countsHtml = contentTypes.map(ct => `<td>${dept.counts[ct] || 0}</td>`).join('');
        tr.innerHTML = `
            <td class="cell-dept-name">${escapeHtml(dept.department)}</td>
            ${countsHtml}
            <td class="cell-row-total">${dept.row_total || 0}</td>
        `;
        tbody.appendChild(tr);
    });

    // Totals bottom row
    const totalsRow = document.createElement('tr');
    totalsRow.className = 'row-column-totals';
    const colTotalsHtml = contentTypes.map(ct => `<td>${data.column_totals[ct] || 0}</td>`).join('');
    totalsRow.innerHTML = `
        <td>Total</td>
        ${colTotalsHtml}
        <td class="cell-grand-total">${data.grand_total || 0}</td>
    `;
    tbody.appendChild(totalsRow);

    // Update Dashboard Top Stat Card to match the exact Matrix Grand Total
    const dashContent = document.getElementById('dash-stat-content');
    if (dashContent) {
        const totalVal = data.grand_total || 0;
        dashContent.textContent = `${Number(totalVal).toLocaleString()} Units`;
    }
}

// Render Matrix 2: Team & Employee Performance (Matching Screenshot 5)
function renderEmployeeBreakdown(data) {
    const thead = document.getElementById('matrix-employee-thead');
    const tbody = document.getElementById('matrix-employee-tbody');
    if (!thead || !tbody) return;

    const contentTypes = data.content_types || ['Reels', 'YT Videos', 'Post Cards', 'FB Videos', 'Podcast'];

    thead.innerHTML = `
        <tr>
            <th style="width: 20%; text-align: left;">Team</th>
            <th style="width: 25%; text-align: left;">Employee Name</th>
            ${contentTypes.map(ct => `<th>${ct}</th>`).join('')}
            <th class="col-total">Total</th>
        </tr>
    `;

    tbody.innerHTML = '';
    const emps = data.employee_breakdown || [];

    emps.forEach(emp => {
        const tr = document.createElement('tr');
        const countsHtml = contentTypes.map(ct => `<td>${emp.counts[ct] || 0}</td>`).join('');
        tr.innerHTML = `
            <td style="text-align: left; font-weight: 600;">${escapeHtml(emp.team_name)}</td>
            <td style="text-align: left;">${escapeHtml(emp.employee_name)}</td>
            ${countsHtml}
            <td class="cell-row-total">${emp.total || 0}</td>
        `;
        tbody.appendChild(tr);
    });
}

function renderDutyStats(summary) {
    if (!summary) return;
    const hoursEl = document.getElementById('stat-total-hours');
    const sheetsEl = document.getElementById('stat-total-sheets');
    const empsEl = document.getElementById('stat-active-emps');

    if (hoursEl) hoursEl.textContent = summary.total_hours + ' hrs';
    if (sheetsEl) sheetsEl.textContent = summary.total_sheets;
    if (empsEl) empsEl.textContent = summary.active_employees;
}

// Export CSV trigger
function exportReportCSV() {
    const startDate = document.getElementById('report-start-date')?.value || new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0];
    const endDate = document.getElementById('report-end-date')?.value || new Date().toISOString().split('T')[0];
    window.location.href = `api/reports.php?action=export_csv&start_date=${startDate}&end_date=${endDate}`;
}

// Live Attendance Board & Timesheet Analytics System
let currentAttReportData = null;

function switchAttendanceView(mode) {
    const liveSec = document.getElementById('att-view-live-section');
    const repSec = document.getElementById('att-view-report-section');
    const waSec = document.getElementById('att-view-whatsapp-section');

    const btnLive = document.getElementById('btn-att-view-live');
    const btnReport = document.getElementById('btn-att-view-report');
    const btnWa = document.getElementById('btn-att-view-whatsapp');

    if (liveSec) liveSec.style.display = (mode === 'live') ? 'block' : 'none';
    if (repSec) repSec.style.display = (mode === 'report') ? 'block' : 'none';
    if (waSec) waSec.style.display = (mode === 'whatsapp') ? 'block' : 'none';

    [
        { el: btnLive, active: mode === 'live' },
        { el: btnReport, active: mode === 'report' },
        { el: btnWa, active: mode === 'whatsapp' }
    ].forEach(b => {
        if (b.el) {
            if (b.active) {
                b.el.className = 'btn btn-primary';
                b.el.style.color = '#ffffff';
                b.el.style.background = 'var(--primary)';
                b.el.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.15)';
            } else {
                b.el.className = 'btn btn-outline';
                b.el.style.color = 'var(--text-main)';
                b.el.style.background = 'transparent';
                b.el.style.boxShadow = 'none';
            }
        }
    });

    if (mode === 'live' && typeof loadLiveAttendance === 'function') {
        loadLiveAttendance();
    } else if (mode === 'report') {
        populateAttendanceReportEmployees();
        loadAttendanceReport();
    } else if (mode === 'whatsapp' && typeof WhatsAppAtt !== 'undefined') {
        WhatsAppAtt.init();
    }
}

// Live Attendance Board for Admin & HR
async function loadLiveAttendance() {
    const tbody = document.getElementById('live-attendance-tbody');
    if (!tbody) return;

    const targetDate = document.getElementById('attendance-board-date')?.value || new Date().toISOString().split('T')[0];
    const deptFilter = document.getElementById('live-att-dept-filter')?.value || 'all';

    let url = `api/attendance.php?action=live_board&date=${targetDate}`;
    if (deptFilter !== 'all') {
        url += `&department_id=${encodeURIComponent(deptFilter)}`;
    }

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) return;

        // Render stats badges
        const totalEl = document.getElementById('att-stat-total');
        const workingEl = document.getElementById('att-stat-working');
        const outEl = document.getElementById('att-stat-out');
        const absentEl = document.getElementById('att-stat-absent');
        const checkedEl = document.getElementById('att-stat-checked') || document.getElementById('att-stat-verified');
        const pendingEl = document.getElementById('att-stat-pending');

        if (totalEl) totalEl.textContent = data.stats.total_employees;
        if (workingEl) workingEl.textContent = data.stats.checked_in;
        if (outEl) outEl.textContent = data.stats.checked_out;
        if (absentEl) absentEl.textContent = data.stats.not_arrived;
        if (checkedEl) checkedEl.textContent = data.stats.sheets_checked ?? (data.stats.sheets_verified || 0);
        if (pendingEl) pendingEl.textContent = data.stats.sheets_pending || 0;

        const dashTeam = document.getElementById('dash-stat-team');
        if (dashTeam) dashTeam.textContent = `${data.stats.total_employees} Employees`;
        const dashWorking = document.getElementById('dash-stat-working');
        if (dashWorking) dashWorking.textContent = `${data.stats.checked_in} Working`;

        const showHodCheck = (data.has_hod_check_enabled !== undefined) ? !!data.has_hod_check_enabled : true;

        const cardChecked = document.getElementById('card-stat-checked');
        const cardPending = document.getElementById('card-stat-pending');
        const thSheetStatus = document.getElementById('th-live-sheet-status');

        if (cardChecked) cardChecked.style.display = showHodCheck ? 'flex' : 'none';
        if (cardPending) cardPending.style.display = showHodCheck ? 'flex' : 'none';
        if (thSheetStatus) thSheetStatus.style.display = showHodCheck ? '' : 'none';

        tbody.innerHTML = '';
        if (data.employees.length === 0) {
            const colSpan = showHodCheck ? 8 : 7;
            tbody.innerHTML = `<tr><td colspan="${colSpan}" style="text-align: center; color: var(--text-muted); padding: 30px;">No staff records found for this date/department.</td></tr>`;
            return;
        }

        const isSuperOrHr = AppState.currentUser && (
            AppState.currentUser.role === 'super_admin' ||
            AppState.currentUser.role === 'admin' ||
            AppState.currentUser.role === 'hr' ||
            (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase() === 'hr')
        );

        const canInspect = AppState.currentUser && (
            AppState.currentUser.role === 'super_admin' ||
            AppState.currentUser.role === 'admin' ||
            AppState.currentUser.role === 'hod' ||
            AppState.currentUser.can_inspect_sheets
        );

        data.employees.forEach(emp => {
            const tr = document.createElement('tr');

            let statusBadge = '';
            if (emp.status === 'holiday') {
                statusBadge = '<span class="attendance-status-pill holiday" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3); font-weight: 700; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">🏖️ Day Off</span>';
            } else if (emp.status === 'working') {
                statusBadge = '<span class="attendance-status-pill working">🟢 On Duty</span>';
            } else if (emp.status === 'checked_out') {
                statusBadge = '<span class="attendance-status-pill checked_out">🔵 Checked Out</span>';
            } else if (emp.status === 'not_arrived') {
                statusBadge = '<span class="attendance-status-pill not-arrived" style="background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 700; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">⏳ Shift Pending</span>';
            } else {
                statusBadge = '<span class="attendance-status-pill absent">🔴 Absent</span>';
            }

            let sheetBadge = '';
            const isHodChecked = (emp.is_hod_checked == 1 || emp.is_hod_checked === '1');

            if (isHodChecked) {
                sheetBadge = `<span class="badge badge-hod-checked" style="background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700; padding: 3px 9px; border-radius: 12px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" title="Checked by HOD at ${escapeHtml(emp.hod_checked_at || '')}">✅ Checked</span>`;
            } else if (emp.status === 'checked_out' || emp.sheet_status === 'pending') {
                sheetBadge = `<span class="badge badge-hod-pending" style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.35); font-weight: 700; padding: 3px 9px; border-radius: 12px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" title="Shift completed and checked out, pending HOD check">⏳ Pending Check</span>`;
            } else if (emp.status === 'working' || emp.check_in_time) {
                sheetBadge = `<span style="color: var(--text-muted); font-size: 11px; display: inline-flex; align-items: center; gap: 3px;" title="Working / On Duty">📝 In Progress</span>`;
            } else {
                sheetBadge = `<span style="color: var(--text-dim); font-size: 11px; display: inline-flex; align-items: center; gap: 3px;">⚪ Not Checked</span>`;
            }

            const sheetStatusTd = showHodCheck ? `<td>${sheetBadge}</td>` : '';

            const avatarHtml = emp.avatar
                ? `<img src="${escapeHtml(emp.avatar)}" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width: 30px; height: 30px; font-size: 11px;\\'>${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>';">`
                : `<div class="user-avatar" style="width: 30px; height: 30px; font-size: 11px; flex-shrink: 0;">${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>`;

            tr.innerHTML = `
                <td>
                    <div style="display: flex; align-items: center; gap: 8px; ${canInspect ? 'cursor: pointer;' : ''}" ${canInspect ? `onclick="inspectEmployeeWorksheet(${emp.employee_id}, '${targetDate}')"` : ''} title="${canInspect ? 'Click to inspect ' + escapeHtml(emp.name) + '\'s worksheet' : ''}">
                        ${avatarHtml}
                        <div>
                            <strong style="color: var(--text-main); font-size: 13px;">${escapeHtml(emp.name)}</strong>
                            <div style="font-size: 11px; color: var(--text-dim);">${escapeHtml(emp.designation || 'Staff')}</div>
                        </div>
                    </div>
                </td>
                <td><span class="user-role-tag" style="background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600;">${escapeHtml(emp.department_name || 'Digital')}</span></td>
                <td><code style="font-size: 12px; font-weight: 700; color: ${emp.check_in_time ? '#10b981' : 'var(--text-dim)'};">${escapeHtml(emp.check_in_time || '-')}</code></td>
                <td><code style="font-size: 12px; font-weight: 700; color: ${emp.check_out_time ? '#3b82f6' : 'var(--text-dim)'};">${escapeHtml(emp.check_out_time || '-')}</code></td>
                <td><strong style="font-size: 13px; color: ${emp.total_duty_hours ? 'var(--text-main)' : 'var(--text-dim)'};">${escapeHtml(emp.total_duty_hours || '-')}</strong></td>
                <td>${statusBadge}</td>
                ${sheetStatusTd}
                <td>
                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                        ${canInspect ? `
                            <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; font-weight: 700; border-color: rgba(16, 185, 129, 0.4); color: #10b981; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;" onclick="inspectEmployeeWorksheet(${emp.employee_id}, '${targetDate}')" title="Inspect & Check Employee Worksheet">
                                📝 Inspect
                            </button>
                        ` : ''}
                        ${isSuperOrHr ? `
                            <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; border-color: rgba(59,130,246,0.4); color: var(--primary); white-space: nowrap; font-weight: 600;" onclick="openAdminShiftModal(${emp.employee_id}, '${targetDate}', '${escapeHtml(emp.name)}', '${escapeHtml(emp.designation || 'Staff')}', '${escapeHtml(emp.department_name || 'Digital')}', '${emp.check_in_time || ''}', '${emp.check_out_time || ''}', ${emp.is_locked ? 1 : 0})" title="Adjust Check-In/Out Times">
                                ⏱️
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

    } catch (err) {
        console.error("Error loading live attendance:", err);
    }
}

// Populate Departments Dropdown dynamically from AppState or API
async function populateAttendanceDepartments() {
    const liveDept = document.getElementById('live-att-dept-filter');
    const repDept = document.getElementById('rep-att-dept-select');
    if (!liveDept && !repDept) return;

    let depts = (typeof AppState !== 'undefined' && AppState.departments) ? AppState.departments : [];
    if (!depts || depts.length === 0) {
        try {
            const res = await fetch('api/employees.php?action=list');
            const data = await res.json();
            if (data.departments && data.departments.length > 0) {
                depts = data.departments;
                if (typeof AppState !== 'undefined') AppState.departments = depts;
            }
        } catch (e) { }
    }

    const isHod = AppState.currentUser && AppState.currentUser.role === 'hod';
    const isGlobalAdmin = AppState.currentUser && (AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'admin');

    if (depts && depts.length > 0) {
        [liveDept, repDept].forEach(sel => {
            if (!sel) return;
            let opts = '';
            if (isGlobalAdmin) {
                opts += '<option value="all">🏢 All Departments</option>';
            }
            depts.forEach(d => {
                opts += `<option value="${d.id}">🏢 ${escapeHtml(d.name)}</option>`;
            });
            sel.innerHTML = opts;
            if (isHod || !isGlobalAdmin) {
                sel.value = depts[0].id;
                if (depts.length <= 1) {
                    sel.disabled = true;
                    sel.style.opacity = '0.85';
                    sel.title = 'Restricted to your department';
                }
            } else {
                const cur = sel.value || 'all';
                if (cur && Array.from(sel.options).some(o => o.value === cur)) {
                    sel.value = cur;
                }
            }
        });
    }
}

// Populate & Render Searchable Employee Dropdown for Attendance Report
function populateAttendanceReportEmployees() {
    populateAttendanceDepartments();
    const select = document.getElementById('rep-att-emp-select');
    if (!select) return;

    const currentVal = select.value || 'all';
    select.innerHTML = '<option value="all">👥 All Employees Summary</option>';

    const allKnown = (AppState.employees && AppState.employees.length > 0)
        ? AppState.employees
        : ((currentAttReportData && currentAttReportData.report_data) ? currentAttReportData.report_data.map(r => r.employee) : []);

    if (allKnown && allKnown.length > 0) {
        allKnown.forEach(emp => {
            if (!emp) return;
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'} • ${emp.department_name || 'Digital'})`;
            select.appendChild(opt);
        });
    }

    if (currentVal && Array.from(select.options).some(o => o.value == currentVal)) {
        select.value = currentVal;
    }

    renderRepAttEmpDropdownList(allKnown || []);
}

function renderRepAttEmpDropdownList(list) {
    const container = document.getElementById('rep-att-emp-dropdown-list');
    if (!container) return;

    const currentVal = document.getElementById('rep-att-emp-select')?.value || 'all';
    const isHod = AppState.currentUser && AppState.currentUser.role === 'hod';

    let html = `
        <div class="dropdown-emp-item ${currentVal === 'all' ? 'active' : ''}" onclick="selectRepAttEmpFromDropdown('all')" style="padding: 8px 10px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all 0.15s; background: ${currentVal === 'all' ? 'rgba(0, 179, 0, 0.1)' : 'transparent'};">
            <div style="width: 28px; height: 28px; border-radius: 50%; background: var(--bg-card); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0;">👥</div>
            <div style="flex: 1; min-width: 0;">
                <div style="font-weight: 700; font-size: 12.5px; color: ${currentVal === 'all' ? 'var(--primary)' : 'var(--text-main)'};">All Employees Summary</div>
                <div style="font-size: 10.5px; color: var(--text-muted);">${isHod ? 'Department staff attendance overview' : 'Company-wide attendance overview'}</div>
            </div>
            ${currentVal === 'all' ? '<span style="color: var(--primary); font-size: 13px; font-weight: 800;">✓</span>' : ''}
        </div>
    `;

    if (list && list.length > 0) {
        list.forEach(emp => {
            const isSelected = currentVal == emp.id;
            const avatarHtml = emp.avatar
                ? `<img src="${escapeHtml(emp.avatar)}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width: 28px; height: 28px; font-size: 11px;\\'>${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>';">`
                : `<div class="user-avatar" style="width: 28px; height: 28px; font-size: 11px; flex-shrink: 0;">${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>`;

            html += `
                <div class="dropdown-emp-item ${isSelected ? 'active' : ''}" onclick="selectRepAttEmpFromDropdown(${emp.id})" style="padding: 7px 10px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all 0.15s; background: ${isSelected ? 'rgba(0, 179, 0, 0.1)' : 'transparent'};">
                    ${avatarHtml}
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 700; font-size: 12.5px; color: ${isSelected ? 'var(--primary)' : 'var(--text-main)'}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(emp.name)}</div>
                        <div style="font-size: 10.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(emp.designation || 'Staff')} • ${escapeHtml(emp.department_name || 'Digital')}</div>
                    </div>
                    ${isSelected ? '<span style="color: var(--primary); font-size: 13px; font-weight: 800;">✓</span>' : ''}
                </div>
            `;
        });
    } else {
        html += '<div style="padding: 12px; text-align: center; color: var(--text-muted); font-size: 11.5px;">No matching employees found</div>';
    }

    container.innerHTML = html;
}

function toggleRepAttEmpDropdown() {
    const menu = document.getElementById('rep-att-emp-dropdown-menu');
    const input = document.getElementById('rep-att-search-input');
    if (!menu) return;

    const isVisible = menu.style.display === 'block';
    if (isVisible) {
        menu.style.display = 'none';
    } else {
        menu.style.display = 'block';
        if (input) {
            input.value = '';
            filterRepAttEmpDropdown('');
            setTimeout(() => input.focus(), 60);
        }
    }
}

function filterRepAttEmpDropdown(query) {
    const q = (query || '').toLowerCase().trim();
    if (!q) {
        renderRepAttEmpDropdownList(AppState.employees || []);
        return;
    }

    const filtered = (AppState.employees || []).filter(e =>
        (e.name || '').toLowerCase().includes(q) ||
        (e.designation || '').toLowerCase().includes(q) ||
        (e.department_name || '').toLowerCase().includes(q) ||
        (e.email || '').toLowerCase().includes(q)
    );
    renderRepAttEmpDropdownList(filtered);
}

function selectRepAttEmpFromDropdown(empId) {
    const menu = document.getElementById('rep-att-emp-dropdown-menu');
    if (menu) menu.style.display = 'none';

    const select = document.getElementById('rep-att-emp-select');
    if (select) {
        if (empId !== 'all') {
            let opt = Array.from(select.options).find(o => o.value == empId);
            if (!opt) {
                opt = document.createElement('option');
                opt.value = empId;
                opt.textContent = `Employee #${empId}`;
                select.appendChild(opt);
            }
            select.value = empId;
        } else {
            select.value = 'all';
        }
    }

    const allKnown = [
        ...(AppState.employees || []),
        ...((currentAttReportData && currentAttReportData.report_data) ? currentAttReportData.report_data.map(r => r.employee) : [])
    ];
    const currentSelectedEmp = allKnown.find(e => e && e.id == empId);

    const btnLabel = document.getElementById('rep-att-emp-dropdown-selected-name');
    if (btnLabel) {
        if (empId === 'all') {
            btnLabel.textContent = '👥 All Employees Summary';
        } else if (currentSelectedEmp) {
            btnLabel.textContent = `👤 ${currentSelectedEmp.name} (${currentSelectedEmp.designation || 'Staff'})`;
            const opt = select?.querySelector(`option[value="${empId}"]`);
            if (opt) opt.textContent = `${currentSelectedEmp.name} (${currentSelectedEmp.designation || 'Staff'})`;
        } else {
            btnLabel.textContent = `👤 Employee #${empId}`;
        }
    }

    renderRepAttEmpDropdownList(AppState.employees || []);
    loadAttendanceReport(empId);
}

// Generate Detailed Attendance Timesheet & Reports
async function loadAttendanceReport(targetEmpId = null) {
    const container = document.getElementById('rep-att-table-container');
    const kpisGrid = document.getElementById('rep-att-kpis-grid');
    if (!container) return;

    let empSelect = targetEmpId !== null ? targetEmpId : (document.getElementById('rep-att-emp-select')?.value || 'all');
    if (!empSelect || empSelect === '') empSelect = 'all';

    const deptSelect = document.getElementById('rep-att-dept-select')?.value || 'all';
    const monthSelect = document.getElementById('rep-att-month-select')?.value || new Date().toISOString().slice(0, 7);
    const standardHours = document.getElementById('rep-att-standard-hours')?.value || '8';

    if (kpisGrid) {
        kpisGrid.innerHTML = '';
        kpisGrid.style.display = 'none';
    }

    container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-muted);"><div class="spinner" style="margin: 0 auto 10px;"></div>Loading attendance timesheet...</div>';

    let url = `api/attendance.php?action=attendance_report&month=${encodeURIComponent(monthSelect)}&standard_hours=${encodeURIComponent(standardHours)}`;
    if (empSelect !== 'all') {
        url += `&employee_id=${encodeURIComponent(empSelect)}`;
    } else if (deptSelect !== 'all') {
        url += `&department_id=${encodeURIComponent(deptSelect)}`;
    }

    try {
        const res = await fetch(url);
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseErr) {
            console.error("Server output:", text);
            container.innerHTML = `<div style="text-align: center; padding: 30px; color: #ef4444;">Server error: ${escapeHtml(text.slice(0, 200))}</div>`;
            return;
        }

        if (!data.success) {
            container.innerHTML = `<div style="text-align: center; padding: 30px; color: #ef4444;">${escapeHtml(data.message || 'Failed to load report.')}</div>`;
            return;
        }

        currentAttReportData = data;

        // Render Detailed View: Single Employee or Multi-Employee Summary Matrix
        if (empSelect !== 'all') {
            if (data.report_data && data.report_data.length > 0) {
                renderSingleEmployeeTimesheet(data.report_data[0], data);
            } else {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: var(--text-muted); background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-top: 10px;">
                        <p style="font-size: 14px; margin-bottom: 12px; font-weight: 600;">No attendance records found for this employee in the selected month.</p>
                        <button type="button" class="btn btn-outline" onclick="resetToAllStaffReport()">← Back to All Staff</button>
                    </div>
                `;
            }
        } else {
            renderMultiEmployeeAttendanceMatrix(data);
        }

    } catch (err) {
        console.error("Error generating attendance report:", err);
        container.innerHTML = `<div style="text-align: center; padding: 30px; color: #ef4444;">Error generating attendance report: ${escapeHtml(err.message || 'Network error')}</div>`;
    }
}

// Render Single Employee Detailed Day-by-Day Timesheet (Clean & Executive UI)
function renderSingleEmployeeTimesheet(item, globalData) {
    const container = document.getElementById('rep-att-table-container');
    if (!container) return;

    const emp = item.employee;
    const sum = item.summary;
    const records = item.daily_records;

    const avatarHtml = emp.avatar
        ? `<img src="${escapeHtml(emp.avatar)}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width: 40px; height: 40px; font-size: 15px; font-weight: 800;\\'>${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>';">`
        : `<div class="user-avatar" style="width: 40px; height: 40px; font-size: 15px; font-weight: 800; flex-shrink: 0;">${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>`;

    // Compact Weekly Pills + Comp-Off Bank Pills
    let weeklyPillsHtml = '';
    for (let w = 1; w <= 5; w++) {
        const hrs = sum.weekly_hours[w] || 0;
        weeklyPillsHtml += `
            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; font-size: 11.5px;">
                <span style="color: var(--text-muted); font-weight: 700;">W${w}:</span>
                <strong style="color: ${hrs > 0 ? 'var(--primary)' : 'var(--text-dim)'};">${hrs.toFixed(1)}h</strong>
            </span>
        `;
    }

    if (sum.off_days_worked > 0 || sum.alt_days_taken > 0) {
        weeklyPillsHtml += `
            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 6px; font-size: 11.5px; font-weight: 700;" title="Days worked on scheduled weekly off / rest days">
                ⭐ Off Days Worked: <strong>${sum.off_days_worked}</strong>
            </span>
            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; background: rgba(139, 92, 246, 0.12); color: #7c3aed; border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 6px; font-size: 11.5px; font-weight: 700;" title="Rotated weekly days off or compensatory days taken">
                🔄 Swapped / Alt Off: <strong>${sum.alt_days_taken}</strong>
            </span>
            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; background: ${(sum.comp_off_balance || 0) >= 0 ? 'rgba(16, 185, 129, 0.12)' : 'rgba(239, 68, 68, 0.12)'}; color: ${(sum.comp_off_balance || 0) >= 0 ? '#059669' : '#dc2626'}; border: 1px solid ${(sum.comp_off_balance || 0) >= 0 ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)'}; border-radius: 6px; font-size: 11.5px; font-weight: 700;" title="Extra off-days worked minus alternate days taken">
                🎯 Comp-Off Bank: <strong>${(sum.comp_off_balance || 0) >= 0 ? '+' : ''}${sum.comp_off_balance || 0}</strong>
            </span>
        `;
    }

    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    let rowsHtml = '';
    records.forEach(r => {
        let fullDay = r.day;
        if (r.date) {
            const parts = r.date.split('-');
            if (parts.length === 3) {
                const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                fullDay = dayNames[d.getDay()];
            }
        }

        let badgeStyle = 'background: rgba(148, 163, 184, 0.12); color: #64748b; border: 1px solid rgba(148, 163, 184, 0.25);';
        if (r.status === 'full_day' || r.status === 'working') {
            badgeStyle = 'background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 700;';
        } else if (r.status === 'short_leave') {
            badgeStyle = 'background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 700;';
        } else if (r.status === 'half_leave') {
            badgeStyle = 'background: rgba(249, 115, 22, 0.12); color: #ea580c; border: 1px solid rgba(249, 115, 22, 0.3); font-weight: 700;';
        } else if (r.status === 'approved_leave') {
            badgeStyle = 'background: rgba(59, 130, 246, 0.12); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3); font-weight: 700;';
        } else if (r.status === 'today_pending') {
            badgeStyle = 'background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 700;';
        } else if (r.status === 'incomplete' || r.status === 'absent') {
            badgeStyle = 'background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.25); font-weight: 600;';
        }

        const rowBg = r.is_weekend ? 'style="background: rgba(0,0,0,0.015);"' : '';

        const isLeaveDay = (r.status === 'approved_leave' || !!r.leave_type);
        const isSwappedOff = (r.status === 'swapped_off' || r.is_swapped_off);
        const isAltOff = (r.status === 'alt_off' || r.is_alt_off);
        const isDayOff = (r.status === 'day_off' || r.status === 'holiday' || isSwappedOff || isAltOff);
        const isWeekendOff = (r.is_weekend && !r.check_in && !r.check_out && !isSwappedOff && !isAltOff);
        const isMergedDay = (isLeaveDay || isDayOff || isWeekendOff);

        const adjustArgs = `${emp.id}, '${r.date}', '${escapeHtml(emp.name)}', '${escapeHtml(emp.designation || 'Staff')}', '${escapeHtml(emp.department_name || 'Digital')}', '${r.check_in || ''}', '${r.check_out || ''}', ${r.is_locked ? 1 : 0}, '${r.day_type || (isSwappedOff ? 'swapped_off' : (isAltOff ? 'alt_off' : ''))}', '${escapeHtml(r.holiday_reason || r.swapped_note || '')}', ${isSwappedOff ? 1 : 0}, '${escapeHtml(r.swapped_with_date || '')}'`;

        if (isMergedDay) {
            let mergedContentHtml = '';
            if (isLeaveDay) {
                const lType = (r.leave_type || 'Leave').toUpperCase();
                const reasonStr = r.leave_reason ? ` • ${escapeHtml(r.leave_reason)}` : ' (HR Approved)';
                mergedContentHtml = `<span style="background: rgba(59, 130, 246, 0.12); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3); font-weight: 700; padding: 4px 14px; border-radius: 6px; font-size: 11.5px; display: inline-block;">🌴 ${lType} LEAVE${reasonStr}</span>`;
            } else if (isSwappedOff) {
                const noteText = r.swapped_note ? ` • ${escapeHtml(r.swapped_note)}` : '';
                mergedContentHtml = `<span style="background: rgba(139, 92, 246, 0.12); color: #7c3aed; border: 1px solid rgba(139, 92, 246, 0.35); font-weight: 700; padding: 4px 14px; border-radius: 6px; font-size: 11.5px; display: inline-flex; align-items: center; gap: 6px;">🔄 Swapped Weekly Off${noteText}</span>`;
            } else if (isAltOff) {
                const noteText = r.swapped_note ? ` • ${escapeHtml(r.swapped_note)}` : '';
                mergedContentHtml = `<span style="background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.35); font-weight: 700; padding: 4px 14px; border-radius: 6px; font-size: 11.5px; display: inline-flex; align-items: center; gap: 6px;">⭐ Alternate Day Off (Comp Off)${noteText}</span>`;
            } else if (r.status === 'day_off' || r.status === 'holiday') {
                mergedContentHtml = `<span style="background: rgba(139, 92, 246, 0.12); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.3); font-weight: 700; padding: 4px 14px; border-radius: 6px; font-size: 11.5px; display: inline-block;">🏖️ Scheduled Day Off (Duty Roster)</span>`;
            } else if (isWeekendOff) {
                mergedContentHtml = `<span style="color: #d97706; font-weight: 700; font-size: 11.5px;">Weekly Off</span>`;
            }

            rowsHtml += `
                <tr ${rowBg}>
                    <td style="white-space: nowrap;">
                        <strong style="font-size: 12.5px;">${r.date}</strong> 
                        <span style="font-size: 11px; color: ${r.is_weekend ? '#f59e0b' : 'var(--text-muted)'}; font-weight: 600; margin-left: 3px;">(${escapeHtml(fullDay)})</span>
                    </td>
                    <td colspan="4" style="text-align: center; padding: 8px;">
                        ${mergedContentHtml}
                    </td>
                    <td style="text-align: right;">
                        <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; font-weight: 600;" onclick="openAdminShiftModal(${adjustArgs})" title="Adjust or Add Remarks to Shift Record">
                            ⏱️ Adjust
                        </button>
                    </td>
                </tr>
            `;
        } else {
            let cleanStatusText = (r.status_label || 'Scheduled').replace(/\s*\([^)]*\)/g, '').trim();
            if (r.status === 'full_day') cleanStatusText = '🟢 Full Day';
            else if (r.status === 'working') cleanStatusText = r.status_label || '🟢 Working';
            else if (r.status === 'short_leave') cleanStatusText = '🟡 Short Leave';
            else if (r.status === 'half_leave') cleanStatusText = '🟠 Half Day';
            else if (r.status === 'today_pending') cleanStatusText = '⏳ Shift Pending';
            else if (r.status === 'incomplete') cleanStatusText = '🔴 Incomplete';
            else if (r.status === 'absent') cleanStatusText = '🔴 Absent';

            const offWorkedPill = r.is_off_day_worked
                ? `<span style="background: rgba(245, 158, 11, 0.15); color: #b45309; border: 1px solid rgba(245, 158, 11, 0.35); font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-left: 4px; white-space: nowrap;" title="Worked on scheduled weekly off / rest day (Earned compensatory day off credit)">⭐ Off Worked</span>`
                : '';

            rowsHtml += `
                <tr ${rowBg}>
                    <td style="white-space: nowrap;">
                        <strong style="font-size: 12.5px;">${r.date}</strong> 
                        <span style="font-size: 11px; color: ${r.is_weekend ? '#f59e0b' : 'var(--text-muted)'}; font-weight: 600; margin-left: 3px;">(${escapeHtml(fullDay)})</span>
                    </td>
                    <td><code style="font-size: 12px; color: ${r.check_in ? '#10b981' : 'var(--text-dim)'}; font-weight: 700;">${r.check_in || '-'}</code></td>
                    <td><code style="font-size: 12px; color: ${r.check_out ? '#3b82f6' : 'var(--text-dim)'}; font-weight: 700;">${r.check_out || '-'}</code></td>
                    <td><strong style="font-size: 13px; color: ${r.duty_seconds > 0 ? 'var(--text-main)' : 'var(--text-dim)'}; font-family: monospace;">${r.duty_formatted}</strong></td>
                    <td style="white-space: nowrap;">
                        <span style="padding: 3px 8px; border-radius: 6px; font-size: 11px; display: inline-flex; align-items: center; ${badgeStyle}">
                            ${escapeHtml(cleanStatusText)}
                        </span>
                        ${offWorkedPill}
                    </td>
                    <td style="text-align: right;">
                        <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; font-weight: 600;" onclick="openAdminShiftModal(${adjustArgs})" title="Adjust or Add Remarks to Shift Record">
                            ⏱️ Adjust
                        </button>
                    </td>
                </tr>
            `;
        }
    });

    const isOvertime = sum.hour_balance >= 0;
    const balanceSign = isOvertime ? '+' : '';
    const balanceColor = isOvertime ? '#10b981' : '#ef4444';
    const balanceBg = isOvertime ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';
    const balanceBorder = isOvertime ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)';
    const balanceText = `${balanceSign}${sum.hour_balance}h`;

    container.innerHTML = `
        <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; margin-bottom: 20px; box-shadow: var(--shadow-sm);">
            
            <!-- Employee Profile & Action Bar -->
            <div style="padding: 14px 18px; background: var(--bg-card-elevated); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    ${avatarHtml}
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">${escapeHtml(emp.name)}</h3>
                            <span style="background: rgba(59, 130, 246, 0.1); color: var(--primary); font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 5px;">${escapeHtml(emp.designation || 'Staff')}</span>
                            <span style="background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-muted); font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: 5px;">${escapeHtml(emp.department_name || 'Digital')}</span>
                        </div>
                        <div style="margin-top: 2px; font-size: 11.5px; color: var(--text-muted);">
                            ${escapeHtml(emp.email)}
                        </div>
                    </div>
                </div>

                <div>
                    <button type="button" class="btn btn-outline" onclick="resetToAllStaffReport()" style="font-size: 11.5px; padding: 5px 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                        ← Back to All Staff
                    </button>
                </div>
            </div>

            <!-- Key Metrics Toolbar (Clean & Streamlined) -->
            <div style="padding: 10px 18px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: rgba(0,0,0,0.015);">
                <!-- Stat Pills -->
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <div style="padding: 4px 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; font-size: 12px;">
                        <span style="color: var(--text-muted); font-weight: 600;">Total Duty:</span>
                        <strong style="color: var(--primary); font-weight: 800; margin-left: 4px;">${sum.duty_formatted}</strong>
                    </div>

                    <div style="padding: 4px 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; font-size: 12px;">
                        <span style="color: #10b981; font-weight: 600;">Full Days:</span>
                        <strong style="color: #10b981; font-weight: 800; margin-left: 4px;">${sum.full_days}</strong>
                    </div>

                    <div style="padding: 4px 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px;">
                        <span><span style="color: #f59e0b; font-weight: 600;">Short:</span> <b style="color: #f59e0b;">${sum.short_leaves}</b></span>
                        <span style="color: var(--border-color);">|</span>
                        <span><span style="color: #ea580c; font-weight: 600;">Half:</span> <b style="color: #ea580c;">${sum.half_leaves}</b></span>
                        <span style="color: var(--border-color);">|</span>
                        <span><span style="color: #3b82f6; font-weight: 600;">Leaves:</span> <b style="color: #3b82f6;">${sum.approved_leaves}</b></span>
                    </div>

                    <div style="padding: 4px 10px; background: ${balanceBg}; border: 1px solid ${balanceBorder}; border-radius: 6px; font-size: 12px;">
                        <span style="color: ${balanceColor}; font-weight: 600;">Balance:</span>
                        <strong style="color: ${balanceColor}; font-weight: 800; margin-left: 4px;">${balanceText}</strong>
                    </div>
                </div>

                <!-- Weekly Hours Pills -->
                <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                    <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-right: 2px;">WEEKLY:</span>
                    ${weeklyPillsHtml}
                    <span style="font-size: 11px; color: var(--text-muted); margin-left: 4px;">(Avg: <b style="color: var(--primary);">${sum.avg_weekly_hours}h</b>/wk)</span>
                </div>
            </div>

            <!-- Timesheet Table -->
            <div class="table-responsive">
                <table class="interactive-table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Date / Day</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Duty Hours</th>
                            <th>Shift Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

// Render Multi-Employee Company-Wide Attendance Summary Matrix
function renderMultiEmployeeAttendanceMatrix(data) {
    const container = document.getElementById('rep-att-table-container');
    if (!container) return;

    if (data.report_data.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-muted);">No employee records found matching current filters.</div>';
        return;
    }

    let rowsHtml = '';
    data.report_data.forEach(item => {
        const emp = item.employee;
        const sum = item.summary;

        const avatarHtml = emp.avatar
            ? `<img src="${escapeHtml(emp.avatar)}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width: 28px; height: 28px; font-size: 11px;\\'>${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>';">`
            : `<div class="user-avatar" style="width: 28px; height: 28px; font-size: 11px; flex-shrink: 0;">${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>`;

        rowsHtml += `
            <tr onclick="selectEmployeeForReport(${emp.id})" style="cursor: pointer; transition: background 0.15s ease;" title="Click to view detailed monthly timesheet for ${escapeHtml(emp.name)}">
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${avatarHtml}
                        <div>
                            <strong style="font-size: 13px; color: var(--text-main);">${escapeHtml(emp.name)}</strong>
                            <div style="font-size: 11px; color: var(--text-dim);">${escapeHtml(emp.designation || 'Staff')}</div>
                        </div>
                    </div>
                </td>
                <td><span class="user-role-tag" style="background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 2px 7px; border-radius: 4px; font-size: 11px;">${escapeHtml(emp.department_name || 'Digital')}</span></td>
                <td><strong style="color: #10b981; font-size: 13px;">${sum.full_days}</strong></td>
                <td><strong style="color: #f59e0b; font-size: 13px;">${sum.short_leaves}</strong></td>
                <td><strong style="color: #f97316; font-size: 13px;">${sum.half_leaves}</strong></td>
                <td><strong style="color: #3b82f6; font-size: 13px;">${sum.approved_leaves}</strong></td>
                <td><strong style="color: #ef4444; font-size: 13px;">${sum.absences + sum.incomplete}</strong></td>
                <td><strong style="color: var(--primary); font-size: 13.5px;">${sum.duty_formatted}</strong></td>
                <td><span style="font-size: 12px; color: var(--text-muted);">${sum.expected_duty_hours} hrs</span></td>
                <td>
                    <strong style="font-size: 12.5px; color: ${sum.hour_balance >= 0 ? '#10b981' : '#ef4444'};">
                        ${sum.hour_balance >= 0 ? '+' + sum.hour_balance : sum.hour_balance}h
                    </strong>
                </td>
                <td style="text-align: right;">
                    <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; font-weight: 700; white-space: nowrap;" onclick="event.stopPropagation(); selectEmployeeForReport(${emp.id})">
                        👁️ View
                    </button>
                </td>
            </tr>
        `;
    });

    container.innerHTML = `
        <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden;">
            <div style="padding: 12px 18px; background: var(--bg-card-elevated); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <h4 style="margin: 0; font-size: 14px; font-weight: 800; color: var(--text-main);">
                    🏢 Company-Wide Staff Attendance & Duty Summary (${data.report_data.length} Employees)
                </h4>
                <small style="color: var(--primary); font-size: 11.5px; font-weight: 600;">👆 Click any employee row or "View" to open their full monthly timesheet</small>
            </div>
            <div class="table-responsive">
                <table class="interactive-table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th title="Full Duty Shifts">Full</th>
                            <th title="Short Duty Shifts (~6h)">Short (6h)</th>
                            <th title="Half Duty Shifts (~4h)">Half (4h)</th>
                            <th title="Official Approved Leaves">Leaves</th>
                            <th title="Absences / Minimal Duty">Absent</th>
                            <th>Duty Hours</th>
                            <th>Expected</th>
                            <th>Balance</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function selectEmployeeForReport(empId) {
    selectRepAttEmpFromDropdown(empId);
}

function resetToAllStaffReport() {
    selectRepAttEmpFromDropdown('all');
}

// Export Report as CSV
function exportAttendanceReportCsv() {
    if (!currentAttReportData || !currentAttReportData.report_data || currentAttReportData.report_data.length === 0) {
        showToast("Please generate an attendance report first.", "error");
        return;
    }

    const data = currentAttReportData;
    let csvContent = "data:text/csv;charset=utf-8,";
    const hasDailyRecords = data.report_data[0].daily_records && data.report_data[0].daily_records.length > 0;

    if (hasDailyRecords) {
        // Detailed Timesheet CSV
        csvContent += "Employee Name,Email,Designation,Department,Date,Day,Check In,Check Out,Duty Hours,Status,Leave Reason / Remarks\n";

        data.report_data.forEach(item => {
            const emp = item.employee;
            (item.daily_records || []).forEach(r => {
                let note = r.remarks || '';
                if (r.status === 'approved_leave') {
                    note = (r.leave_type ? r.leave_type.toUpperCase() + ' LEAVE: ' : 'APPROVED LEAVE: ') + (r.leave_reason || 'Approved by HR');
                } else if (!note) {
                    note = r.status_label;
                }

                const row = [
                    `"${(emp.name || '').replace(/"/g, '""')}"`,
                    `"${(emp.email || '').replace(/"/g, '""')}"`,
                    `"${(emp.designation || 'Staff').replace(/"/g, '""')}"`,
                    `"${(emp.department_name || 'Digital').replace(/"/g, '""')}"`,
                    `"${r.date}"`,
                    `"${r.day}"`,
                    `"${r.check_in || ''}"`,
                    `"${r.check_out || ''}"`,
                    `"${r.duty_formatted || '0:00:00'}"`,
                    `"${(r.status_label || '').replace(/"/g, '""')}"`,
                    `"${note.replace(/"/g, '""')}"`
                ];
                csvContent += row.join(",") + "\n";
            });
        });
    } else {
        // Company-Wide Summary Matrix CSV
        csvContent += "Employee Name,Email,Designation,Department,Full Shifts,Short Shifts (6h),Half Shifts (4h),Approved Leaves,Absent Days,Duty Hours,Expected Hours,Balance Hours\n";

        data.report_data.forEach(item => {
            const emp = item.employee;
            const sum = item.summary;
            const row = [
                `"${(emp.name || '').replace(/"/g, '""')}"`,
                `"${(emp.email || '').replace(/"/g, '""')}"`,
                `"${(emp.designation || 'Staff').replace(/"/g, '""')}"`,
                `"${(emp.department_name || 'Digital').replace(/"/g, '""')}"`,
                `"${sum.full_days}"`,
                `"${sum.short_leaves}"`,
                `"${sum.half_leaves}"`,
                `"${sum.approved_leaves}"`,
                `"${sum.absences + sum.incomplete}"`,
                `"${sum.duty_formatted}"`,
                `"${sum.expected_duty_hours} hrs"`,
                `"${sum.hour_balance >= 0 ? '+' + sum.hour_balance : sum.hour_balance}h"`
            ];
            csvContent += row.join(",") + "\n";
        });
    }

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", hasDailyRecords
        ? `attendance_timesheet_${data.start_date}_to_${data.end_date}.csv`
        : `attendance_summary_${data.start_date}_to_${data.end_date}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast("Attendance CSV exported successfully!", "success");
}

// Print Timesheet Report (Executive Publication-Grade Print Format)
function printAttendanceReport() {
    const data = currentAttReportData;
    if (!data || !data.report_data || data.report_data.length === 0) {
        showToast("Please generate or select an attendance report first.", "error");
        return;
    }

    const isSingleEmployee = data.report_data.length === 1;
    const nowStr = new Date().toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true
    });

    const logoSvg = `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 520 180" style="height: 48px; width: auto; max-width: 170px;">
            <rect x="25" y="25" width="48" height="100" fill="#00b300" rx="2" />
            <text x="95" y="68" font-family="'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-weight="900" font-size="52" fill="#00b300" letter-spacing="1">DISCOVER</text>
            <text x="95" y="122" font-family="'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-weight="900" font-size="52" fill="#00b300" letter-spacing="1">PAKISTAN</text>
            <text x="365" y="152" font-family="'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-weight="900" font-size="24" fill="#1e293b" letter-spacing="0.5">UHD TV</text>
        </svg>
    `;

    let reportBodyHtml = '';
    let centerHeading = 'Staff Attendance & Duty Report';

    if (isSingleEmployee) {
        const item = data.report_data[0];
        const emp = item.employee;
        const sum = item.summary;
        const records = item.daily_records || [];
        centerHeading = `${emp.name} - Attendance Report`;

        const isOvertime = sum.hour_balance >= 0;
        const balanceColor = isOvertime ? '#059669' : '#dc2626';
        const balanceText = `${isOvertime ? '+' : ''}${sum.hour_balance} hrs`;

        let rowsHtml = '';
        records.forEach((r, idx) => {
            const rowClass = r.is_weekend ? 'weekend-row' : (idx % 2 === 0 ? 'even-row' : 'odd-row');

            // Ensure full weekday name (e.g. Monday, Tuesday)
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            let fullDay = r.day;
            if (r.date) {
                const parts = r.date.split('-');
                if (parts.length === 3) {
                    const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                    fullDay = dayNames[d.getDay()];
                }
            }

            const isLeaveDay = (r.status === 'approved_leave' || !!r.leave_type);
            const isDayOff = (r.status === 'day_off' || r.status === 'holiday');
            const isWeekendOff = (r.is_weekend && !r.check_in && !r.check_out);
            const isMergedDay = (isLeaveDay || isDayOff || isWeekendOff);

            if (isMergedDay) {
                let mergedContentHtml = '';
                let mergedStyle = '';
                if (isLeaveDay) {
                    const lType = (r.leave_type || 'Leave').toUpperCase();
                    const reasonStr = r.leave_reason ? ` • ${escapeHtml(r.leave_reason)}` : ' (HR Approved)';
                    mergedContentHtml = `<span class="status-badge badge-leave" style="font-size: 11px; padding: 3px 12px;">🌴 ${lType} LEAVE${reasonStr}</span>`;
                    mergedStyle = 'background: #eff6ff;';
                } else if (isDayOff) {
                    mergedContentHtml = `<span class="status-badge badge-holiday" style="font-size: 11px; padding: 3px 12px;">🏖️ Scheduled Day Off (Duty Roster)</span>`;
                    mergedStyle = 'background: #faf5ff;';
                } else if (isWeekendOff) {
                    mergedContentHtml = `<span style="color: #d97706; font-weight: 700; font-size: 11px;">Weekly Off</span>`;
                    mergedStyle = 'background: #fffbeb;';
                }

                rowsHtml += `
                    <tr class="${rowClass}">
                        <td style="text-align: center; font-weight: 700;">${idx + 1}</td>
                        <td style="white-space: nowrap; font-weight: 700;">${r.date}</td>
                        <td style="text-align: center; color: ${r.is_weekend ? '#d97706' : '#475569'}; font-weight: 600;">${escapeHtml(fullDay)}</td>
                        <td colspan="4" style="text-align: center; ${mergedStyle}">
                            ${mergedContentHtml}
                        </td>
                    </tr>
                `;
            } else {
                let cleanStatusText = (r.status_label || 'Scheduled').replace(/\s*\([^)]*\)/g, '').trim();
                let statusClass = 'text-muted';
                if (r.status === 'full_day') {
                    cleanStatusText = '● Full Day';
                    statusClass = 'badge-full';
                } else if (r.status === 'working') {
                    cleanStatusText = '● Working';
                    statusClass = 'badge-full';
                } else if (r.status === 'short_leave') {
                    cleanStatusText = '● Short Leave';
                    statusClass = 'badge-short';
                } else if (r.status === 'half_leave') {
                    cleanStatusText = '● Half Day';
                    statusClass = 'badge-half';
                } else if (r.status === 'today_pending') {
                    cleanStatusText = '⏳ Shift Pending';
                    statusClass = 'badge-pending';
                } else if (r.status === 'incomplete') {
                    cleanStatusText = '● Incomplete';
                    statusClass = 'badge-absent';
                } else if (r.status === 'absent') {
                    cleanStatusText = '● Absent';
                    statusClass = 'badge-absent';
                }

                rowsHtml += `
                    <tr class="${rowClass}">
                        <td style="text-align: center; font-weight: 700;">${idx + 1}</td>
                        <td style="white-space: nowrap; font-weight: 700;">${r.date}</td>
                        <td style="text-align: center; color: ${r.is_weekend ? '#d97706' : '#475569'}; font-weight: 600;">${escapeHtml(fullDay)}</td>
                        <td style="text-align: center; font-family: monospace; font-weight: 700; color: ${r.check_in ? '#059669' : '#94a3b8'};">${r.check_in || '-'}</td>
                        <td style="text-align: center; font-family: monospace; font-weight: 700; color: ${r.check_out ? '#2563eb' : '#94a3b8'};">${r.check_out || '-'}</td>
                        <td style="text-align: center; font-family: monospace; font-weight: 700;">${r.duty_formatted || '0:00:00'}</td>
                        <td style="text-align: center;"><span class="status-badge ${statusClass}">${escapeHtml(cleanStatusText)}</span></td>
                    </tr>
                `;
            }
        });

        const totalDutyHoursNum = (typeof sum.total_duty_hours === 'number')
            ? (Number.isInteger(sum.total_duty_hours) ? sum.total_duty_hours : sum.total_duty_hours.toFixed(1))
            : (parseFloat(sum.total_duty_hours || 0).toFixed(1));
        const totalDutyHoursStr = `${totalDutyHoursNum} hrs`;

        reportBodyHtml = `
            <!-- Employee Info Header Card (Single Row) -->
            <div class="meta-card">
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Designation:</td>
                        <td class="meta-value"><strong>${escapeHtml(emp.designation || 'Staff')}</strong></td>
                        <td class="meta-label">Department:</td>
                        <td class="meta-value"><strong>${escapeHtml(emp.department_name || 'Digital')}</strong></td>
                        <td class="meta-label">Report Period:</td>
                        <td class="meta-value" style="white-space: nowrap;"><strong>${escapeHtml(data.start_date)} to ${escapeHtml(data.end_date)}</strong></td>
                    </tr>
                </table>
            </div>

            <!-- Executive Metric Summary Grid -->
            <div class="kpi-container">
                <div class="kpi-box">
                    <div class="kpi-title">TOTAL DUTY HOURS</div>
                    <div class="kpi-val" style="color: #00b300;">${totalDutyHoursStr}</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-title">FULL SHIFTS</div>
                    <div class="kpi-val" style="color: #059669;">${sum.full_days}</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-title">SHORT / HALF LEAVES</div>
                    <div class="kpi-val" style="color: #d97706;">${sum.short_leaves} / ${sum.half_leaves}</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-title">APPROVED LEAVES</div>
                    <div class="kpi-val" style="color: #2563eb;">${sum.approved_leaves}</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-title">HOURLY BALANCE</div>
                    <div class="kpi-val" style="color: ${balanceColor};">${balanceText}</div>
                </div>
            </div>

            <!-- Day-by-Day Detailed Log -->
            <h4 class="section-title">DAILY ATTENDANCE & DUTY LOG</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 32px; text-align: center;">#</th>
                        <th style="width: 95px;">Date</th>
                        <th style="width: 105px; text-align: center;">Day</th>
                        <th style="width: 85px; text-align: center;">Check In</th>
                        <th style="width: 85px; text-align: center;">Check Out</th>
                        <th style="width: 90px; text-align: center;">Duty Hours</th>
                        <th style="width: 110px; text-align: center;">Shift Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>
        `;
    } else {
        // Multi-Employee Summary Matrix
        let rowsHtml = '';
        data.report_data.forEach((item, idx) => {
            const emp = item.employee;
            const sum = item.summary;
            const isOvertime = sum.hour_balance >= 0;
            const balanceColor = isOvertime ? '#059669' : '#dc2626';

            rowsHtml += `
                <tr class="${idx % 2 === 0 ? 'even-row' : 'odd-row'}">
                    <td style="text-align: center; font-weight: 700;">${idx + 1}</td>
                    <td>
                        <strong>${escapeHtml(emp.name)}</strong>
                        <div style="font-size: 10px; color: #64748b;">${escapeHtml(emp.email)}</div>
                    </td>
                    <td>${escapeHtml(emp.designation || 'Staff')}</td>
                    <td>${escapeHtml(emp.department_name || 'Digital')}</td>
                    <td style="text-align: center; font-weight: 700; color: #059669;">${sum.full_days}</td>
                    <td style="text-align: center; font-weight: 700; color: #d97706;">${sum.short_leaves}</td>
                    <td style="text-align: center; font-weight: 700; color: #ea580c;">${sum.half_leaves}</td>
                    <td style="text-align: center; font-weight: 700; color: #2563eb;">${sum.approved_leaves}</td>
                    <td style="text-align: center; font-weight: 700; color: #dc2626;">${sum.absences + sum.incomplete}</td>
                    <td style="text-align: center; font-family: monospace; font-weight: 800; color: #00b300;">${sum.duty_formatted}</td>
                    <td style="text-align: center; font-weight: 800; color: ${balanceColor};">
                        ${isOvertime ? '+' : ''}${sum.hour_balance}h
                    </td>
                </tr>
            `;
        });

        reportBodyHtml = `
            <div class="meta-card">
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Department:</td>
                        <td class="meta-value"><strong>All Departments / Company-Wide</strong></td>
                        <td class="meta-label">Total Staff Count:</td>
                        <td class="meta-value"><strong>${data.report_data.length} Employees</strong></td>
                    </tr>
                    <tr>
                        <td class="meta-label">Report Period:</td>
                        <td class="meta-value">${escapeHtml(data.start_date)} to ${escapeHtml(data.end_date)}</td>
                        <td class="meta-label">Standard Shift:</td>
                        <td class="meta-value">${data.standard_hours} Hours / Day</td>
                    </tr>
                </table>
            </div>

            <h4 class="section-title">COMPANY-WIDE STAFF ATTENDANCE SUMMARY</h4>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 30px; text-align: center;">#</th>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th style="text-align: center;">Full</th>
                        <th style="text-align: center;">Short</th>
                        <th style="text-align: center;">Half</th>
                        <th style="text-align: center;">Leaves</th>
                        <th style="text-align: center;">Absent</th>
                        <th style="text-align: center;">Duty Hours</th>
                        <th style="text-align: center;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>
        `;
    }

    const printDocumentHtml = `
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Attendance Timesheet Report - Discover Pakistan</title>
            <style>
                @page {
                    size: A4 portrait;
                    margin: 12mm 12mm 14mm 12mm;
                }
                * {
                    box-sizing: border-box;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
                body {
                    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
                    font-size: 11.5px;
                    line-height: 1.4;
                    color: #0f172a;
                    background: #ffffff;
                    margin: 0;
                    padding: 0;
                }
                .report-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding-bottom: 8px;
                    border-bottom: 3px solid #00b300;
                    margin-bottom: 12px;
                }
                .header-logo-col {
                    flex: 0 0 170px;
                }
                .header-center-col {
                    flex: 1;
                    text-align: center;
                    padding: 0 10px;
                }
                .report-center-heading {
                    margin: 0;
                    font-size: 17px;
                    font-weight: 900;
                    color: #0f172a;
                    letter-spacing: -0.2px;
                }
                .doc-meta {
                    flex: 0 0 170px;
                    text-align: right;
                }
                .doc-badge {
                    display: inline-block;
                    background: #00b300;
                    color: #ffffff;
                    font-size: 10px;
                    font-weight: 800;
                    padding: 3px 8px;
                    border-radius: 4px;
                    letter-spacing: 0.5px;
                    text-transform: uppercase;
                    margin-bottom: 4px;
                }
                .meta-card {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    padding: 7px 12px;
                    margin-bottom: 12px;
                }
                .meta-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .meta-table td {
                    padding: 3px 6px;
                    font-size: 11.5px;
                }
                .meta-label {
                    color: #64748b;
                    font-weight: 700;
                    white-space: nowrap;
                    width: 1%;
                }
                .meta-value {
                    color: #0f172a;
                    padding-right: 20px;
                }
                .kpi-container {
                    display: grid;
                    grid-template-columns: repeat(5, 1fr);
                    gap: 8px;
                    margin-bottom: 12px;
                }
                .kpi-box {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    padding: 8px 6px;
                    text-align: center;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                }
                .kpi-title {
                    font-size: 9px;
                    font-weight: 800;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                    margin-bottom: 3px;
                }
                .kpi-val {
                    font-size: 15.5px;
                    font-weight: 900;
                    margin: 0;
                }
                .kpi-sub {
                    font-size: 9.5px;
                    color: #64748b;
                }
                .weekly-bar {
                    background: #f1f5f9;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    padding: 6px 10px;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    flex-wrap: wrap;
                    margin-bottom: 14px;
                }
                .weekly-chip {
                    display: inline-block;
                    background: #ffffff;
                    border: 1px solid #cbd5e1;
                    border-radius: 4px;
                    padding: 2px 6px;
                    font-size: 10.5px;
                    color: #334155;
                }
                .section-title {
                    margin: 0 0 8px;
                    font-size: 12px;
                    font-weight: 800;
                    color: #1e293b;
                    letter-spacing: 0.3px;
                    text-transform: uppercase;
                }
                .data-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 11px;
                    margin-bottom: 20px;
                }
                .data-table th {
                    background: #0f172a;
                    color: #ffffff;
                    font-weight: 700;
                    padding: 6px 8px;
                    border: 1px solid #0f172a;
                    text-align: left;
                    font-size: 10.5px;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                .data-table td {
                    padding: 5px 8px;
                    border: 1px solid #e2e8f0;
                    color: #1e293b;
                }
                .even-row { background: #ffffff; }
                .odd-row { background: #f8fafc; }
                .weekend-row { background: #fffbeb; }
                .status-badge {
                    display: inline-block;
                    padding: 2px 6px;
                    border-radius: 4px;
                    font-size: 10px;
                    font-weight: 700;
                    text-align: center;
                }
                .badge-full { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
                .badge-short { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
                .badge-half { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
                .badge-leave { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; }
                .badge-holiday { background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd; }
                .badge-absent { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
                .badge-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
                .text-muted { color: #64748b; }
                
                /* Sign-off Block */
                .signature-section {
                    margin-top: 25px;
                    padding-top: 15px;
                    border-top: 1px dashed #cbd5e1;
                    display: flex;
                    justify-content: space-between;
                    page-break-inside: avoid;
                }
                .signature-box {
                    width: 30%;
                    text-align: center;
                }
                .sig-line {
                    border-bottom: 1.5px solid #334155;
                    height: 38px;
                    margin-bottom: 6px;
                }
                .sig-title {
                    font-size: 11px;
                    font-weight: 800;
                    color: #0f172a;
                }
                .sig-sub {
                    font-size: 9.5px;
                    color: #64748b;
                }
                .report-footer {
                    margin-top: 20px;
                    padding-top: 8px;
                    border-top: 1px solid #e2e8f0;
                    display: flex;
                    justify-content: space-between;
                    font-size: 9.5px;
                    color: #94a3b8;
                    page-break-inside: avoid;
                }
            </style>
        </head>
        <body>
            <!-- Official Header -->
            <div class="report-header">
                <div class="header-logo-col">
                    ${logoSvg}
                </div>
                <div class="header-center-col">
                    <h2 class="report-center-heading">${escapeHtml(centerHeading)}</h2>
                </div>
                <div class="doc-meta">
                    <span class="doc-badge">Official HR Report</span>
                    <div style="font-size: 10.5px; color: #475569; font-weight: 600; margin-top: 2px;">
                       <strong>${nowStr}</strong>
                    </div>
                </div>
            </div>

            <!-- Report Body -->
            ${reportBodyHtml}

            <!-- Formal Sign-Off Section -->
            <div class="signature-section">
                <div class="signature-box">
                    <div class="sig-line"></div>
                    <div class="sig-title">Employee Signature</div>
                    <div class="sig-sub">Verification & Acknowledgement</div>
                </div>
                <div class="signature-box">
                    <div class="sig-line"></div>
                    <div class="sig-title">Head of Department (HOD)</div>
                    <div class="sig-sub">Departmental Review & Sign-off</div>
                </div>
                <div class="signature-box">
                    <div class="sig-line"></div>
                    <div class="sig-title">HR & Admin Division</div>
                    <div class="sig-sub">Official Seal & Approval</div>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="report-footer">
                <div>Discover Pakistan UHD TV HR Portal • Automated Timesheet System</div>
                <div>Confidential Document • Page 1 of 1</div>
            </div>
        </body>
        </html>
    `;

    // Render in hidden iframe for silent, high-definition print preview
    let printIframe = document.getElementById('att-print-iframe');
    if (!printIframe) {
        printIframe = document.createElement('iframe');
        printIframe.id = 'att-print-iframe';
        printIframe.style.position = 'fixed';
        printIframe.style.right = '0';
        printIframe.style.bottom = '0';
        printIframe.style.width = '0';
        printIframe.style.height = '0';
        printIframe.style.border = '0';
        document.body.appendChild(printIframe);
    }

    const doc = printIframe.contentWindow.document;
    doc.open();
    doc.write(printDocumentHtml);
    doc.close();

    setTimeout(() => {
        printIframe.contentWindow.focus();
        printIframe.contentWindow.print();
    }, 250);
}

function viewEmployeeSheet(empId, date) {
    if (AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hr' || AppState.currentUser.role === 'hod' || hasPermission('can_inspect_sheets'))) {
        AppState.adminSelectedEmpId = parseInt(empId);
        if (date) AppState.selectedDate = date;
        const dateInput = document.getElementById('worksheet-date-picker');
        if (dateInput && date) dateInput.value = date;

        const select = document.getElementById('admin-employee-select');
        if (select) select.value = empId;

        loadDailyWorksheet();
    } else {
        switchEmployeeByEmail(empId);
    }

    // Switch to worksheet tab
    const wsTabBtn = document.querySelector('[data-tab="tab-worksheet"]');
    if (wsTabBtn) wsTabBtn.click();
}

/**
 * -------------------------------------------------------------
 * Department Tracking Options & Worksheet Column Builder Handlers
 * -------------------------------------------------------------
 */

let currentTrackingModalDeptId = null;
let currentTrackingModalActiveTab = 'cols';

async function openTrackingOptionsModal(deptId = null) {
    if (!deptId) {
        if (AppState.worksheetColumnsDeptId) {
            deptId = AppState.worksheetColumnsDeptId;
        } else if (AppState.trackingOptionsDeptId) {
            deptId = AppState.trackingOptionsDeptId;
        } else if (AppState.currentUser && AppState.currentUser.department_id) {
            deptId = AppState.currentUser.department_id;
        } else {
            deptId = 2; // Default Digital
        }
    }
    currentTrackingModalDeptId = parseInt(deptId) || 2;

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin');
    const adminPickerRow = document.getElementById('tracking-admin-dept-picker-row');
    const adminDeptSelect = document.getElementById('tracking-admin-dept-select');

    if (isAdmin && adminPickerRow && adminDeptSelect) {
        adminPickerRow.style.display = 'flex';
        if (AppState.departments && AppState.departments.length > 0) {
            adminDeptSelect.innerHTML = AppState.departments.map(d =>
                `<option value="${d.id}" ${parseInt(d.id) === currentTrackingModalDeptId ? 'selected' : ''}>${escapeHtml(d.name)}</option>`
            ).join('');
        }
    } else if (adminPickerRow) {
        adminPickerRow.style.display = 'none';
    }

    openModal('tracking-options-modal');
    switchTrackingModalTab(currentTrackingModalActiveTab || 'cols');
    await loadWorksheetColumnsModalData(currentTrackingModalDeptId);
    await loadTrackingOptionsModalData(currentTrackingModalDeptId);
}

function switchTrackingModalTab(tabKey) {
    currentTrackingModalActiveTab = tabKey;

    const btnCols = document.getElementById('tab-btn-worksheet-cols');
    const btnTypes = document.getElementById('tab-btn-content-types');
    const btnDepts = document.getElementById('tab-btn-tracking-depts');
    const btnHodSettings = document.getElementById('tab-btn-hod-settings');

    const secCols = document.getElementById('tracking-tab-section-cols');
    const secTypes = document.getElementById('tracking-tab-section-content-types');
    const secDepts = document.getElementById('tracking-tab-section-tracking-depts');
    const secHodSettings = document.getElementById('tracking-tab-section-hod-settings');

    // Reset button states
    [btnCols, btnTypes, btnDepts, btnHodSettings].forEach(b => {
        if (b) {
            b.className = 'btn btn-outline tracking-modal-tab-btn';
            b.style.fontWeight = '600';
        }
    });

    // Hide all sections first
    if (secCols) secCols.style.display = 'none';
    if (secTypes) secTypes.style.display = 'none';
    if (secDepts) secDepts.style.display = 'none';
    if (secHodSettings) secHodSettings.style.display = 'none';

    if (tabKey === 'cols') {
        if (btnCols) { btnCols.className = 'btn btn-primary tracking-modal-tab-btn'; btnCols.style.fontWeight = '700'; }
        if (secCols) secCols.style.display = 'flex';
        loadWorksheetColumnsModalData(currentTrackingModalDeptId);
    } else if (tabKey === 'content_types') {
        if (btnTypes) { btnTypes.className = 'btn btn-primary tracking-modal-tab-btn'; btnTypes.style.fontWeight = '700'; }
        if (secTypes) secTypes.style.display = 'flex';
        loadTrackingOptionsModalData(currentTrackingModalDeptId);
    } else if (tabKey === 'tracking_depts') {
        if (btnDepts) { btnDepts.className = 'btn btn-primary tracking-modal-tab-btn'; btnDepts.style.fontWeight = '700'; }
        if (secDepts) secDepts.style.display = 'flex';
        loadTrackingOptionsModalData(currentTrackingModalDeptId);
    } else if (tabKey === 'hod_settings') {
        if (btnHodSettings) { btnHodSettings.className = 'btn btn-primary tracking-modal-tab-btn'; btnHodSettings.style.fontWeight = '700'; }
        if (secHodSettings) secHodSettings.style.display = 'flex';
        loadHodSettingsModalData(currentTrackingModalDeptId);
    }
}

function toggleNewColumnOptionsInput(colType) {
    const row = document.getElementById('new-col-options-row');
    if (row) {
        row.style.display = (colType === 'select') ? 'block' : 'none';
    }
}

async function handleTrackingDeptSwitch(deptId) {
    currentTrackingModalDeptId = parseInt(deptId) || 2;
    await loadWorksheetColumnsModalData(currentTrackingModalDeptId);
    await loadTrackingOptionsModalData(currentTrackingModalDeptId);
    await loadHodSettingsModalData(currentTrackingModalDeptId);
}

async function loadHodSettingsModalData(deptId) {
    const deptLabel = document.getElementById('hod-settings-dept-label');
    const hodLabel = document.getElementById('hod-settings-assigned-hod');
    const previewHodName = document.getElementById('preview-hod-name');
    const chkRequire = document.getElementById('setting-require-hod-check');
    const chkBlock = document.getElementById('setting-block-print-before-check');

    try {
        const res = await fetch(`api/tracking_options.php?action=get&department_id=${deptId}`);
        const data = await res.json();
        if (!data.success) return;

        if (deptLabel) deptLabel.textContent = data.department_name || 'Department';
        const hodSettings = data.hod_settings || {};
        const hodName = hodSettings.hod_name || 'HOD';

        if (hodLabel) hodLabel.textContent = `HOD: ${hodName}`;
        if (previewHodName) previewHodName.textContent = hodName;

        if (chkRequire) chkRequire.checked = (parseInt(hodSettings.require_hod_sheet_check) === 1);
        if (chkBlock) chkBlock.checked = (parseInt(hodSettings.block_print_before_hod_check) === 1);

    } catch (err) {
        console.error('Error loading HOD settings:', err);
    }
}

function handleHodCheckSettingChange(chk) {
    const chkBlock = document.getElementById('setting-block-print-before-check');
    if (!chk.checked && chkBlock) {
        chkBlock.checked = false;
    }
}

async function saveHodVerificationSettings() {
    const deptId = currentTrackingModalDeptId || (AppState.currentUser?.department_id || 2);
    const chkRequire = document.getElementById('setting-require-hod-check');
    const chkBlock = document.getElementById('setting-block-print-before-check');

    const requireCheck = chkRequire && chkRequire.checked ? 1 : 0;
    const blockPrint = chkBlock && chkBlock.checked ? 1 : 0;

    try {
        const res = await fetch('api/tracking_options.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_hod_settings',
                department_id: deptId,
                require_hod_sheet_check: requireCheck,
                block_print_before_hod_check: blockPrint
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast('Department verification & print rules saved successfully!', 'success');
            // Refresh active sheet so any print block / badges update immediately
            if (typeof loadSheetData === 'function') {
                const curEmpId = getActiveWorksheetEmpId();
                const curDate = AppState.selectedDate;
                loadSheetData(curEmpId, curDate);
            }
        } else {
            showToast(data.message || 'Failed to save settings', 'error');
        }
    } catch (err) {
        console.error('Error saving HOD settings:', err);
        showToast('Error communicating with server.', 'error');
    }
}

// ---------------------------------------------------------------------
// WORKSHEET COLUMNS BUILDER CRUD & REORDER
// ---------------------------------------------------------------------

function renderColumnTypeBadge(type) {
    if (type === 'select') return '<span class="badge" style="background: rgba(147, 51, 234, 0.1); color: #9333ea; font-size: 11px;">🔽 Dropdown</span>';
    if (type === 'link') return '<span class="badge" style="background: rgba(14, 165, 233, 0.1); color: #0284c7; font-size: 11px;">🔗 Link/URL</span>';
    if (type === 'number') return '<span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #d97706; font-size: 11px;">🔢 Number</span>';
    if (type === 'time') return '<span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #059669; font-size: 11px;">🕒 Time</span>';
    return '<span class="badge" style="background: rgba(59, 130, 246, 0.1); color: #2563eb; font-size: 11px;">📝 Text</span>';
}

async function loadWorksheetColumnsModalData(deptId) {
    const listContainer = document.getElementById('worksheet-columns-list');
    const countBadge = document.getElementById('worksheet-columns-count-badge');
    const badge = document.getElementById('tracking-modal-dept-badge');

    if (listContainer) listContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 15px; font-size: 12px;">Loading columns...</div>';

    try {
        const res = await fetch(`api/worksheet_columns.php?action=get&all=1&department_id=${deptId}`);
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to load columns', 'error');
            return;
        }

        if (badge) badge.textContent = data.department_name || 'Department';

        const cols = data.columns || [];
        AppState.worksheetColumns = cols;
        AppState.worksheetColumnsDeptId = deptId;

        const visibleCount = cols.filter(c => c.is_visible).length;
        if (countBadge) countBadge.textContent = `${visibleCount} Active Columns (${cols.length} total)`;

        if (listContainer) {
            if (cols.length === 0) {
                listContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 15px; font-size: 12px;">No columns found. Resetting defaults...</div>';
            } else {
                listContainer.innerHTML = cols.map((col, idx) => `
                    <div class="worksheet-col-row" data-id="${col.id}" style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); opacity: ${col.is_visible ? '1' : '0.55'}; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 280px; flex-wrap: wrap;">
                            <span class="badge" style="font-size: 11px; background: var(--primary); color: #fff; min-width: 22px; text-align: center;">#${idx + 1}</span>
                            ${renderColumnTypeBadge(col.column_type)}
                            <input type="text" class="input-control" value="${escapeHtml(col.column_label)}" style="flex: 1; min-width: 130px; max-width: 260px; font-weight: 700; font-size: 12.5px; padding: 4px 8px;" onkeydown="if(event.key==='Enter'){event.preventDefault();handleRenameWorksheetColumn(${col.id}, this.value, '${escapeHtml(col.column_label)}');}" onblur="handleRenameWorksheetColumn(${col.id}, this.value, '${escapeHtml(col.column_label)}')">
                            ${col.is_core ? '<span style="font-size: 10px; color: var(--text-muted); background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px;">System</span>' : '<span style="font-size: 10px; color: var(--primary); background: var(--primary-light); padding: 2px 6px; border-radius: 4px;">Custom</span>'}
                            ${col.column_type === 'select' && !col.is_core ? `
                                <input type="text" class="input-control" placeholder="Choices: A, B, C" value="${escapeHtml((col.options || []).join(', '))}" style="flex: 1; min-width: 140px; font-size: 11.5px; padding: 4px 8px;" title="Comma-separated dropdown choices" onchange="handleUpdateColumnOptions(${col.id}, this.value)">
                            ` : ''}
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                            <button type="button" 
                                    class="btn btn-outline" 
                                    style="padding: 3px 8px; font-size: 11px; font-weight: 700; ${col.editable_after_lock ? 'color: var(--success); border-color: rgba(16, 185, 129, 0.4); background: rgba(16, 185, 129, 0.08);' : 'color: var(--text-muted); border-color: var(--border-color);'}" 
                                    onclick="handleToggleWorksheetColumnPostLock(${col.id}, ${col.editable_after_lock ? 0 : 1})" 
                                    title="Toggle whether employees can edit this field after shift checkout (e.g. adding story URLs once published)">
                                ${col.editable_after_lock ? '🔓 Post-Checkout: Editable' : '🔒 Post-Checkout: Locked'}
                            </button>
                            <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px;" onclick="handleMoveWorksheetColumn(${col.id}, 'left')" title="Move column Left (earlier)">◀ Left</button>
                            <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px;" onclick="handleMoveWorksheetColumn(${col.id}, 'right')" title="Move column Right (later)">▶ Right</button>
                            <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px;" onclick="handleToggleWorksheetColumnVisibility(${col.id}, ${col.is_visible ? 0 : 1})" title="${col.is_visible ? 'Hide column from worksheet' : 'Show column in worksheet'}">
                                ${col.is_visible ? '👁️ Hide' : '🚫 Show'}
                            </button>
                            ${!col.is_core ? `
                                <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: var(--danger, #ef4444); border-color: rgba(239,68,68,0.25);" onclick="handleDeleteWorksheetColumn(${col.id}, '${escapeHtml(col.column_label)}')" title="Delete custom column">🗑️</button>
                            ` : ''}
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (err) {
        console.error("Error loading columns data:", err);
        showToast("Error retrieving worksheet columns", "error");
    }
}

async function handleToggleWorksheetColumnPostLock(id, newState) {
    try {
        const res = await fetch('api/worksheet_columns.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_editable_after_lock',
                id: id,
                editable_after_lock: newState
            })
        });
        const data = await res.json();
        if (!data.success) {
            showToast(data.message || 'Failed to update rule', 'error');
            return;
        }

        showToast(data.message || 'Updated post-checkout rule', 'success');
        await loadWorksheetColumnsModalData(currentTrackingModalDeptId);
        await loadWorksheetColumns(currentTrackingModalDeptId, true);
        if (typeof renderWorksheetTable === 'function') {
            const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hod' || hasPermission('can_inspect_sheets'));
            renderWorksheetTable(AppState.currentEntries || [], isAdmin || !AppState.isLocked);
        }
    } catch (err) {
        console.error("Error updating post-lock status:", err);
        showToast("Error updating post-lock status", "error");
    }
}
window.handleToggleWorksheetColumnPostLock = handleToggleWorksheetColumnPostLock;

async function handleWorksheetColumnAddSubmit(event) {
    if (event) event.preventDefault();

    const labelInput = document.getElementById('new-col-label');
    const typeSelect = document.getElementById('new-col-type');
    const optionsInput = document.getElementById('new-col-options-input');
    const editableAfterLockCheckbox = document.getElementById('new-col-editable-after-lock');

    if (!labelInput) return;
    const label = labelInput.value.trim();
    if (!label) {
        showToast("Please enter a column name", "warning");
        return;
    }

    const colType = typeSelect ? typeSelect.value : 'text';
    const options = (colType === 'select' && optionsInput) ? optionsInput.value.trim() : '';
    const editableAfterLock = (editableAfterLockCheckbox && editableAfterLockCheckbox.checked) ? 1 : 0;

    try {
        const res = await fetch('api/worksheet_columns.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add',
                department_id: currentTrackingModalDeptId,
                column_label: label,
                column_type: colType,
                options: options,
                editable_after_lock: editableAfterLock
            })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to add column', 'error');
            return;
        }

        labelInput.value = '';
        if (optionsInput) optionsInput.value = '';
        if (editableAfterLockCheckbox) editableAfterLockCheckbox.checked = false;
        showToast(`Added column "${label}" successfully!`, 'success');

        await loadWorksheetColumnsModalData(currentTrackingModalDeptId);
        await loadWorksheetColumns(currentTrackingModalDeptId, true);
        if (typeof renderWorksheetTable === 'function') {
            const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hod' || hasPermission('can_inspect_sheets'));
            renderWorksheetTable(AppState.currentEntries || [], isAdmin || !AppState.isLocked);
        }
    } catch (err) {
        console.error("Error adding column:", err);
        showToast("Error adding column", "error");
    }
}

async function handleRenameWorksheetColumn(id, newLabel, oldLabel) {
    const trimmed = (newLabel || '').trim();
    if (!trimmed || trimmed === oldLabel) return;

    try {
        const res = await fetch('api/worksheet_columns.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                id: id,
                column_label: trimmed
            })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to rename column', 'error');
            return;
        }

        showToast(`Renamed column to "${trimmed}"`, 'success');
        await loadWorksheetColumnsModalData(currentTrackingModalDeptId);
        await loadWorksheetColumns(currentTrackingModalDeptId, true);
        if (typeof renderWorksheetTable === 'function') {
            renderWorksheetTable(AppState.currentEntries || [], true);
        }
    } catch (err) {
        console.error("Error renaming column:", err);
        showToast("Error renaming column", "error");
    }
}

async function handleUpdateColumnOptions(id, optionsStr) {
    try {
        const res = await fetch('api/worksheet_columns.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                id: id,
                options: optionsStr
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast("Dropdown options updated", "success");
            await loadWorksheetColumns(currentTrackingModalDeptId, true);
            if (typeof renderWorksheetTable === 'function') {
                renderWorksheetTable(AppState.currentEntries || [], true);
            }
        }
    } catch (err) {
        console.error("Error updating options:", err);
    }
}

async function handleMoveWorksheetColumn(id, direction) {
    try {
        const res = await fetch('api/worksheet_columns.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'move',
                id: id,
                direction: direction
            })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Could not move column', 'error');
            return;
        }

        await loadWorksheetColumnsModalData(currentTrackingModalDeptId);
        await loadWorksheetColumns(currentTrackingModalDeptId, true);
        if (typeof renderWorksheetTable === 'function') {
            renderWorksheetTable(AppState.currentEntries || [], true);
        }
    } catch (err) {
        console.error("Error moving column:", err);
        showToast("Error moving column", "error");
    }
}

async function handleToggleWorksheetColumnVisibility(id, newVis) {
    try {
        const res = await fetch('api/worksheet_columns.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                id: id,
                is_visible: newVis
            })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to update visibility', 'error');
            return;
        }

        showToast(newVis ? 'Column shown in worksheet' : 'Column hidden from worksheet', 'info');
        await loadWorksheetColumnsModalData(currentTrackingModalDeptId);
        await loadWorksheetColumns(currentTrackingModalDeptId, true);
        if (typeof renderWorksheetTable === 'function') {
            renderWorksheetTable(AppState.currentEntries || [], true);
        }
    } catch (err) {
        console.error("Error toggling column visibility:", err);
        showToast("Error updating visibility", "error");
    }
}

async function handleDeleteWorksheetColumn(id, label) {
    const confirmed = await showConfirmDialog({
        title: "Delete Custom Column",
        message: `Permanently delete custom column "${label}"?`,
        detail: "This column will no longer appear in worksheet tables for this department.",
        confirmText: "Delete Column",
        cancelText: "Cancel",
        type: "danger",
        icon: "🗑️"
    });
    if (!confirmed) return;

    try {
        const res = await fetch('api/worksheet_columns.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                id: id
            })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to delete column', 'error');
            return;
        }

        showToast(`Column "${label}" deleted`, 'success');
        await loadWorksheetColumnsModalData(currentTrackingModalDeptId);
        await loadWorksheetColumns(currentTrackingModalDeptId, true);
        if (typeof renderWorksheetTable === 'function') {
            renderWorksheetTable(AppState.currentEntries || [], true);
        }
    } catch (err) {
        console.error("Error deleting column:", err);
        showToast("Error deleting column", "error");
    }
}

async function handleResetActiveTrackingTabDefaults() {
    if (currentTrackingModalActiveTab === 'cols') {
        const confirmed = await showConfirmDialog({
            title: "Reset Column Configuration",
            message: "Reset Worksheet Columns for this department to standard system defaults?",
            detail: "Custom column labels and ordering will be restored to default values.",
            confirmText: "Reset Columns",
            cancelText: "Cancel",
            type: "warning",
            icon: "🔄"
        });
        if (!confirmed) return;
        try {
            const res = await fetch('api/worksheet_columns.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reset_defaults',
                    department_id: currentTrackingModalDeptId
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast("Worksheet columns reset to defaults", "success");
                await loadWorksheetColumnsModalData(currentTrackingModalDeptId);
                await loadWorksheetColumns(currentTrackingModalDeptId, true);
                if (typeof renderWorksheetTable === 'function') {
                    renderWorksheetTable(AppState.currentEntries || [], true);
                }
            }
        } catch (e) {
            showToast("Failed to reset columns", "error");
        }
    } else {
        const confirmed = await showConfirmDialog({
            title: "Reset Tracking Options",
            message: "Reset Content Types and Tracking Desks for this department to system defaults?",
            detail: "Existing past entries will remain intact, and options list will revert to defaults.",
            confirmText: "Reset Options",
            cancelText: "Cancel",
            type: "warning",
            icon: "🔄"
        });
        if (!confirmed) return;
        try {
            const res = await fetch('api/tracking_options.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reset_defaults',
                    department_id: currentTrackingModalDeptId
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast("Tracking options reset to defaults", "success");
                await loadTrackingOptionsModalData(currentTrackingModalDeptId);
                await loadTrackingOptions(currentTrackingModalDeptId, true);
                refreshWorksheetDropdowns();
                loadReports();
            }
        } catch (e) {
            showToast("Failed to reset tracking options", "error");
        }
    }
}

// ---------------------------------------------------------------------
// CONTENT TYPES & TRACKING DESKS CRUD
// ---------------------------------------------------------------------

async function loadTrackingOptionsModalData(deptId) {
    const ctContainer = document.getElementById('tracking-content-types-list');
    const tdContainer = document.getElementById('tracking-depts-list');
    const badge = document.getElementById('tracking-modal-dept-badge');
    const ctCountBadge = document.getElementById('content-types-count-badge');
    const tdCountBadge = document.getElementById('tracking-depts-count-badge');

    if (ctContainer) ctContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 15px; font-size: 12px;">Loading...</div>';
    if (tdContainer) tdContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 15px; font-size: 12px;">Loading...</div>';

    try {
        const res = await fetch(`api/tracking_options.php?action=get&department_id=${deptId}`);
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to load tracking options', 'error');
            return;
        }

        if (badge) badge.textContent = data.department_name || 'Department';

        // Render Content Types
        const contentTypes = data.content_types || [];
        if (ctCountBadge) ctCountBadge.textContent = `${contentTypes.length} items`;
        if (ctContainer) {
            if (contentTypes.length === 0) {
                ctContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 16px; font-size: 12px;">No content types configured yet. Add one above!</div>';
            } else {
                ctContainer.innerHTML = contentTypes.map(item => `
                    <div class="tracking-item-row" data-id="${item.id}" style="display: flex; align-items: center; gap: 8px; padding: 7px 10px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <span style="font-size: 13px; color: var(--text-muted); user-select: none;">🎬</span>
                        <input type="text" class="input-control tracking-item-input" value="${escapeHtml(item.name)}" style="flex: 1; padding: 4px 8px; font-size: 12.5px; font-weight: 600;" onkeydown="if(event.key==='Enter'){event.preventDefault();handleTrackingOptionRename(${item.id}, this.value, '${escapeHtml(item.name)}');}">
                        <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px;" onclick="handleTrackingOptionRename(${item.id}, this.previousElementSibling.value, '${escapeHtml(item.name)}')" title="Save rename">💾 Save</button>
                        <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: var(--danger, #ef4444); border-color: rgba(239,68,68,0.25);" onclick="handleTrackingOptionDelete(${item.id}, '${escapeHtml(item.name)}')" title="Delete this option">🗑️</button>
                    </div>
                `).join('');
            }
        }

        // Render Tracking Departments / Desks
        const trackingDepts = data.tracking_departments || [];
        if (tdCountBadge) tdCountBadge.textContent = `${trackingDepts.length} items`;
        if (tdContainer) {
            if (trackingDepts.length === 0) {
                tdContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 16px; font-size: 12px;">No tracking desks configured yet. Add one above!</div>';
            } else {
                tdContainer.innerHTML = trackingDepts.map(item => `
                    <div class="tracking-item-row" data-id="${item.id}" style="display: flex; align-items: center; gap: 8px; padding: 7px 10px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <span style="font-size: 13px; color: var(--text-muted); user-select: none;">🏢</span>
                        <input type="text" class="input-control tracking-item-input" value="${escapeHtml(item.name)}" style="flex: 1; padding: 4px 8px; font-size: 12.5px; font-weight: 600;" onkeydown="if(event.key==='Enter'){event.preventDefault();handleTrackingOptionRename(${item.id}, this.value, '${escapeHtml(item.name)}');}">
                        <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px;" onclick="handleTrackingOptionRename(${item.id}, this.previousElementSibling.value, '${escapeHtml(item.name)}')" title="Save rename">💾 Save</button>
                        <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: var(--danger, #ef4444); border-color: rgba(239,68,68,0.25);" onclick="handleTrackingOptionDelete(${item.id}, '${escapeHtml(item.name)}')" title="Delete this option">🗑️</button>
                    </div>
                `).join('');
            }
        }

    } catch (err) {
        console.error("Error loading tracking options data:", err);
        showToast("Error retrieving tracking options", "error");
    }
}

async function handleTrackingOptionAddSubmit(event, optionType) {
    if (event) event.preventDefault();
    const inputId = (optionType === 'content_type') ? 'new-content-type-name' : 'new-tracking-dept-name';
    const input = document.getElementById(inputId);
    if (!input) return;

    const name = input.value.trim();
    if (!name) {
        showToast("Please enter an option name", "warning");
        return;
    }

    try {
        const res = await fetch('api/tracking_options.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add',
                department_id: currentTrackingModalDeptId,
                option_type: optionType,
                option_name: name
            })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to add option', 'error');
            return;
        }

        input.value = '';
        showToast(`Added "${name}" successfully`, 'success');

        await loadTrackingOptionsModalData(currentTrackingModalDeptId);
        await loadTrackingOptions(currentTrackingModalDeptId, true);
        refreshWorksheetDropdowns();
        loadReports();
    } catch (err) {
        console.error("Error adding tracking option:", err);
        showToast("Error adding tracking option", "error");
    }
}

async function handleTrackingOptionRename(id, newName, oldName) {
    const trimmed = (newName || '').trim();
    if (!trimmed) {
        showToast("Option name cannot be empty", "warning");
        return;
    }
    if (trimmed.toLowerCase() === (oldName || '').toLowerCase()) {
        return; // No change
    }

    try {
        const res = await fetch('api/tracking_options.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                id: id,
                option_name: trimmed,
                update_entries: true
            })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to update option', 'error');
            return;
        }

        const histMsg = data.history_updated_rows > 0 ? ` (${data.history_updated_rows} past entries synced)` : '';
        showToast(`Renamed to "${trimmed}"${histMsg}`, 'success');

        await loadTrackingOptionsModalData(currentTrackingModalDeptId);
        await loadTrackingOptions(currentTrackingModalDeptId, true);
        refreshWorksheetDropdowns();
        loadReports();
    } catch (err) {
        console.error("Error renaming tracking option:", err);
        showToast("Error renaming tracking option", "error");
    }
}

async function handleTrackingOptionDelete(id, name) {
    const confirmed = await showConfirmDialog({
        title: "Remove Tracking Option",
        message: `Remove "${name}" from tracking options?`,
        detail: "Existing past worksheet rows will remain unaffected.",
        confirmText: "Remove Option",
        cancelText: "Cancel",
        type: "danger",
        icon: "🗑️"
    });
    if (!confirmed) return;

    try {
        const res = await fetch('api/tracking_options.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                id: id
            })
        });
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to delete option', 'error');
            return;
        }

        showToast(`Removed "${name}" from tracking options`, 'success');

        await loadTrackingOptionsModalData(currentTrackingModalDeptId);
        await loadTrackingOptions(currentTrackingModalDeptId, true);
        refreshWorksheetDropdowns();
        loadReports();
    } catch (err) {
        console.error("Error deleting tracking option:", err);
        showToast("Error deleting tracking option", "error");
    }
}


