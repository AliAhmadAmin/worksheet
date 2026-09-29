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

// Live Attendance Board for Admin
async function loadLiveAttendance() {
    const tbody = document.getElementById('live-attendance-tbody');
    if (!tbody) return;

    const targetDate = document.getElementById('attendance-board-date')?.value || new Date().toISOString().split('T')[0];

    try {
        const res = await fetch(`api/attendance.php?action=live_board&date=${targetDate}`);
        const data = await res.json();

        if (!data.success) return;

        // Render stats badges
        document.getElementById('att-stat-total').textContent = data.stats.total_employees;
        document.getElementById('att-stat-working').textContent = data.stats.checked_in;
        document.getElementById('att-stat-out').textContent = data.stats.checked_out;
        document.getElementById('att-stat-absent').textContent = data.stats.not_arrived;

        const dashTeam = document.getElementById('dash-stat-team');
        if (dashTeam) dashTeam.textContent = `${data.stats.total_employees} Employees`;
        const dashWorking = document.getElementById('dash-stat-working');
        if (dashWorking) dashWorking.textContent = `${data.stats.checked_in} Working`;

        tbody.innerHTML = '';
        data.employees.forEach(emp => {
            const tr = document.createElement('tr');
            
            let statusBadge = '';
            if (emp.status === 'working') {
                statusBadge = '<span class="attendance-status-pill working">🟢 Working (On Duty)</span>';
            } else if (emp.status === 'checked_out') {
                statusBadge = '<span class="attendance-status-pill checked_out">🔵 Checked Out (Locked)</span>';
            } else {
                statusBadge = '<span class="attendance-status-pill absent">⚪ Absent / Not In</span>';
            }

            const avatarHtml = emp.avatar 
                ? `<img src="${escapeHtml(emp.avatar)}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(emp.name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width: 28px; height: 28px; font-size: 11px;\\'>${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>';">`
                : `<div class="user-avatar" style="width: 28px; height: 28px; font-size: 11px; flex-shrink: 0;">${escapeHtml(emp.name.charAt(0).toUpperCase())}</div>`;

            tr.innerHTML = `
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${avatarHtml}
                        <div>
                            <strong>${escapeHtml(emp.name)}</strong> <small style="color:var(--text-dim);">(${escapeHtml(emp.designation || 'Staff')})</small>
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(emp.team_name || 'General')}</td>
                <td>${escapeHtml(emp.department_name || 'Digital')}</td>
                <td>${escapeHtml(emp.check_in_time || '-')}</td>
                <td>${escapeHtml(emp.check_out_time || '-')}</td>
                <td><strong>${escapeHtml(emp.total_duty_hours || '-')}</strong></td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-outline" style="padding: 4px 8px; font-size: 11px;" onclick="viewEmployeeSheet(${emp.employee_id}, '${targetDate}')">
                        📄 View Sheet
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });

    } catch (err) {
        console.error("Error loading live attendance:", err);
    }
}

function viewEmployeeSheet(empId, date) {
    if (AppState.currentUser && AppState.currentUser.role === 'admin') {
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
