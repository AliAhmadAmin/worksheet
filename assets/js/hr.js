/**
 * HR Management Module: Leaves, Advance/Loans, Notices, Staff HR Records, and Automated Payroll
 */

let HrState = {
    overview: null,
    leaves: [],
    loans: [],
    notices: [],
    profiles: [],
    payroll: [],
    currentSubTab: 'leaves',
    selectedMonth: new Date().toISOString().slice(0, 7)
};

async function loadHrDashboard() {
    const monthInput = document.getElementById('hr-payroll-month');
    if (monthInput && !monthInput.value) {
        monthInput.value = HrState.selectedMonth;
    }

    populateHrEmployeeDropdowns();
    await fetchHrOverview();
    
    // Auto load current view if active
    if (document.getElementById('tab-hr-leaves')?.classList.contains('active')) {
        await loadHrLeaves();
    } else if (document.getElementById('tab-hr-loans')?.classList.contains('active')) {
        await loadHrLoans();
    } else if (document.getElementById('tab-hr-notices')?.classList.contains('active')) {
        await loadHrNotices();
    } else if (document.getElementById('tab-hr-payroll')?.classList.contains('active')) {
        await loadHrPayroll();
    }
}

function refreshHrData() {
    showToast("Refreshing HR records...", "info");
    loadHrDashboard();
}

async function fetchHrOverview() {
    try {
        const month = document.getElementById('hr-payroll-month')?.value || HrState.selectedMonth;
        const res = await fetch(`api/hr.php?action=get_overview&month=${month}`);
        const data = await res.json();

        if (data.success && data.overview) {
            HrState.overview = data.overview;
            const ov = data.overview;

            // 1. Executive Top KPI Cards
            const totalStaffEl = document.getElementById('hr-dash-total-staff');
            const todayPresentEl = document.getElementById('hr-dash-today-present');
            const todayOnLeaveEl = document.getElementById('hr-dash-today-on-leave');
            const pendingLeavesEl = document.getElementById('hr-dash-pending-leaves');
            const pendingLoansEl = document.getElementById('hr-dash-pending-loans');

            if (totalStaffEl) totalStaffEl.textContent = ov.active_staff || 0;
            if (todayPresentEl) todayPresentEl.textContent = ov.today_present_count || 0;
            if (todayOnLeaveEl) todayOnLeaveEl.textContent = ov.today_on_leave_count || 0;
            if (pendingLeavesEl) pendingLeavesEl.textContent = ov.pending_leaves || 0;
            if (pendingLoansEl) pendingLoansEl.textContent = ov.pending_loans || 0;

            // Update pending badge in navbar
            const navLeavesBadge = document.getElementById('pending-leaves-badge');
            if (navLeavesBadge) {
                if (ov.pending_leaves > 0) {
                    navLeavesBadge.textContent = ov.pending_leaves;
                    navLeavesBadge.style.display = 'inline-block';
                } else {
                    navLeavesBadge.style.display = 'none';
                }
            }

            const navLoansBadge = document.getElementById('pending-loans-badge');
            if (navLoansBadge) {
                if (ov.pending_loans > 0) {
                    navLoansBadge.textContent = ov.pending_loans;
                    navLoansBadge.style.display = 'inline-block';
                } else {
                    navLoansBadge.style.display = 'none';
                }
            }

            // 2. Company-Wide Monthly Leave Analytics
            const compLeaves = ov.company_leave_stats || {};
            const compCas = document.getElementById('hr-dash-company-casual');
            const compSick = document.getElementById('hr-dash-company-sick');
            const compAnn = document.getElementById('hr-dash-company-annual');
            const compTotal = document.getElementById('hr-dash-company-total-days');
            const monthLabel = document.getElementById('hr-dash-leave-month-label');

            if (compCas) compCas.textContent = compLeaves.casual || 0;
            if (compSick) compSick.textContent = compLeaves.sick || 0;
            if (compAnn) compAnn.textContent = compLeaves.annual || 0;
            if (compTotal) compTotal.textContent = `${compLeaves.total_days || 0} Days`;
            if (monthLabel && ov.current_month_name) monthLabel.textContent = ov.current_month_name;

            // 3. Render Dashboard Pending Queues
            renderDashboardPendingQueue(ov.pending_leaves_list || []);
            renderDashboardPendingLoansQueue(ov.pending_loans_list || []);

            // 4. Render Today's On-Leave Staff
            renderDashboardOnLeaveRoster(ov.today_on_leave_staff || []);

            // 5. Render Active Company Notices Widget
            renderDashboardNoticesWidget(ov.active_notices || []);

            // 6. Render Department Attendance Breakdown
            renderDashboardDeptAttendance(ov.department_attendance_stats || []);
        }
    } catch (err) {
        console.error("Failed to fetch HR overview:", err);
    }
}

function renderDashboardPendingQueue(pendingList) {
    const container = document.getElementById('hr-dash-pending-list');
    if (!container) return;

    if (!pendingList || pendingList.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 22px 15px; color: var(--text-muted); background: var(--bg-card-elevated); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                <span style="font-size: 20px;">✨</span>
                <div style="font-weight: 700; font-size: 13px; margin-top: 2px; color: var(--text-main);">No Pending Leave Requests</div>
                <div style="font-size: 11px; color: var(--text-muted);">All employee leave applications are currently processed.</div>
            </div>
        `;
        return;
    }

    const currentUser = HrState.currentUser || AppState.currentUser || {};
    const isAdmin = currentUser.role === 'admin' || currentUser.role === 'super_admin' || currentUser.can_manage_hr;
    const isHod = currentUser.is_hod === true || currentUser.role === 'hod' || (currentUser.designation && (currentUser.designation.includes('HOD') || currentUser.designation.includes('Director')));
    const currentUserId = currentUser.id || 0;
    const userDeptId = currentUser.department_id || (AppState.currentUser ? AppState.currentUser.department_id : 0);

    let html = '';
    pendingList.forEach(l => {
        const avatarInitial = l.employee_name ? l.employee_name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = l.avatar
            ? `<img src="${escapeHtml(l.avatar)}" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(l.employee_name)}">`
            : `<div class="user-avatar" style="width: 34px; height: 34px; font-size: 13px; flex-shrink: 0;">${escapeHtml(avatarInitial)}</div>`;

        const isHodForDept = (isHod && l.department_id && l.department_id == userDeptId && l.employee_id != currentUserId);

        let actionBtnHtml = '';
        if (l.status === 'approved_by_hod') {
            if (isAdmin) {
                actionBtnHtml = `
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-success" style="padding: 5px 10px; font-size: 11px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'approved')" title="Final HR Approval">
                            ✓ Final Approve (HR)
                        </button>
                        <button type="button" class="btn btn-danger" style="padding: 5px 10px; font-size: 11px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'rejected')" title="Reject Leave">
                            ✕ Reject
                        </button>
                    </div>
                `;
            } else {
                actionBtnHtml = `<span style="font-size: 11px; color: #0284c7; font-weight: 700;">🟡 Approved by HOD (Awaiting HR)</span>`;
            }
        } else if (l.status === 'pending') {
            if (isHodForDept) {
                actionBtnHtml = `
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-success" style="padding: 5px 10px; font-size: 11px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'approved_by_hod')" title="Endorse Leave (HOD)">
                            ✓ Approve (HOD)
                        </button>
                        <button type="button" class="btn btn-danger" style="padding: 5px 10px; font-size: 11px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'rejected')" title="Reject Leave">
                            ✕ Reject
                        </button>
                    </div>
                `;
            } else if (isAdmin) {
                actionBtnHtml = `
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-success" style="padding: 5px 10px; font-size: 11px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'approved')" title="Direct HR Approval">
                            ✓ Approve (HR)
                        </button>
                        <button type="button" class="btn btn-danger" style="padding: 5px 10px; font-size: 11px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'rejected')" title="Reject Leave">
                            ✕ Reject
                        </button>
                    </div>
                `;
            } else {
                actionBtnHtml = `<span style="font-size: 11px; color: #f59e0b; font-weight: 700;">⏳ In Review by HOD</span>`;
            }
        }

        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); gap: 12px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px; min-width: 180px;">
                    ${avatarHtml}
                    <div>
                        <div style="font-weight: 800; font-size: 13px; color: var(--text-main);">${escapeHtml(l.employee_name)}</div>
                        <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(l.designation || 'Staff')} • ${escapeHtml(l.department_name || 'General')}</div>
                    </div>
                </div>

                <div style="flex: 1; min-width: 150px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
                        ${l.leave_type.toUpperCase()} LEAVE (${l.days_count} Day${l.days_count > 1 ? 's' : ''})
                        ${l.status === 'approved_by_hod' ? `<span style="font-size: 10px; background: rgba(14, 165, 233, 0.12); color: #0284c7; padding: 1px 6px; border-radius: 4px; font-weight: 800;">HOD Approved</span>` : ''}
                    </div>
                    <div style="font-size: 11px; color: var(--primary); font-weight: 600;">
                        📅 ${escapeHtml(l.start_date)} ${l.end_date !== l.start_date ? 'to ' + escapeHtml(l.end_date) : ''}
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                        "${escapeHtml(l.reason)}"
                    </div>
                    ${l.hod_name ? `<div style="font-size: 10.5px; color: #0284c7; margin-top: 2px;"><b>HOD Endorsed:</b> ${escapeHtml(l.hod_name)}</div>` : ''}
                </div>

                ${actionBtnHtml}
            </div>
        `;
    });

    container.innerHTML = html;
}

function renderDashboardPendingLoansQueue(loanList) {
    const container = document.getElementById('hr-dash-pending-loans-list');
    if (!container) return;

    if (!loanList || loanList.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 22px 15px; color: var(--text-muted); background: var(--bg-card-elevated); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                <span style="font-size: 20px;">💳</span>
                <div style="font-weight: 700; font-size: 13px; margin-top: 2px; color: var(--text-main);">No Pending Loan Requests</div>
                <div style="font-size: 11px; color: var(--text-muted);">No advance salary or emergency aid requests awaiting review.</div>
            </div>
        `;
        return;
    }

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.can_manage_hr);

    let html = '';
    loanList.forEach(l => {
        const avatarInitial = l.employee_name ? l.employee_name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = l.avatar
            ? `<img src="${escapeHtml(l.avatar)}" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(l.employee_name)}">`
            : `<div class="user-avatar" style="width: 34px; height: 34px; font-size: 13px; flex-shrink: 0;">${escapeHtml(avatarInitial)}</div>`;

        const typeTitle = l.request_type === 'advance_salary' ? '💵 Advance Salary' : (l.request_type === 'emergency_loan' ? '🚨 Emergency Loan' : '🩺 Medical Aid');

        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); gap: 12px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px; min-width: 180px;">
                    ${avatarHtml}
                    <div>
                        <div style="font-weight: 800; font-size: 13px; color: var(--text-main);">${escapeHtml(l.employee_name)}</div>
                        <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(l.designation || 'Staff')} • ${escapeHtml(l.department_name || 'General')}</div>
                    </div>
                </div>

                <div style="flex: 1; min-width: 150px;">
                    <div style="font-size: 12.5px; font-weight: 800; color: #8b5cf6;">
                        PKR ${parseFloat(l.amount).toLocaleString()} <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">(${l.repayment_months} mo @ PKR ${parseFloat(l.monthly_deduction).toLocaleString()}/mo)</span>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                        ${typeTitle} • "${escapeHtml(l.reason)}"
                    </div>
                </div>

                ${isAdmin ? `
                <div style="display: flex; gap: 6px;">
                    <button type="button" class="btn btn-success" style="padding: 5px 10px; font-size: 11px; font-weight: 700;" onclick="handleLoanAction(${l.id}, 'approved')">
                        ✓ Approve
                    </button>
                    <button type="button" class="btn btn-danger" style="padding: 5px 10px; font-size: 11px; font-weight: 700;" onclick="handleLoanAction(${l.id}, 'rejected')">
                        ✕ Reject
                    </button>
                </div>
                ` : `<span style="font-size: 11px; color: #f59e0b; font-weight: 700;">⏳ In Review</span>`}
            </div>
        `;
    });

    container.innerHTML = html;
}

function renderDashboardOnLeaveRoster(onLeaveList) {
    const container = document.getElementById('hr-dash-onleave-list');
    const badge = document.getElementById('hr-dash-onleave-badge');
    if (!container) return;

    if (badge) {
        badge.textContent = `${onLeaveList.length} on leave today`;
    }

    if (!onLeaveList || onLeaveList.length === 0) {
        container.innerHTML = `
            <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 12px; background: var(--bg-card-elevated); border-radius: var(--radius-sm);">
                🟢 No staff members on leave today. Full team attendance expected.
            </div>
        `;
        return;
    }

    let html = '';
    onLeaveList.forEach(m => {
        const avatarInitial = m.employee_name ? m.employee_name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = m.avatar
            ? `<img src="${escapeHtml(m.avatar)}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;" alt="${escapeHtml(m.employee_name)}">`
            : `<div class="user-avatar" style="width: 28px; height: 28px; font-size: 11px;">${escapeHtml(avatarInitial)}</div>`;

        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    ${avatarHtml}
                    <div>
                        <div style="font-weight: 700; font-size: 12.5px; color: var(--text-main);">${escapeHtml(m.employee_name)}</div>
                        <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(m.department_name || 'General')}</div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 11px; font-weight: 700; color: #3b82f6; background: rgba(59, 130, 246, 0.1); padding: 2px 8px; border-radius: 10px;">
                        ${escapeHtml(m.leave_type)}
                    </span>
                    <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;">Until ${escapeHtml(m.end_date)}</div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function renderDashboardNoticesWidget(notices) {
    const container = document.getElementById('hr-dash-notices-list');
    if (!container) return;

    if (!notices || notices.length === 0) {
        container.innerHTML = `
            <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 12px;">
                No active company announcements posted.
            </div>
        `;
        return;
    }

    const priorityBadges = {
        urgent: { label: '🚨 Urgent', bg: 'rgba(239, 68, 68, 0.12)', color: '#ef4444' },
        holiday: { label: '🎉 Holiday', bg: 'rgba(16, 185, 129, 0.12)', color: '#10b981' },
        event: { label: '🏆 Event', bg: 'rgba(139, 92, 246, 0.12)', color: '#8b5cf6' },
        normal: { label: '📢 Notice', bg: 'rgba(59, 130, 246, 0.12)', color: '#3b82f6' }
    };

    let html = '';
    notices.forEach(n => {
        const badge = priorityBadges[n.priority] || priorityBadges.normal;
        html += `
            <div style="padding: 10px 12px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-sm); border-left: 3px solid ${badge.color};">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                    <span style="font-weight: 800; font-size: 12.5px; color: var(--text-main);">${escapeHtml(n.title)}</span>
                    <span style="font-size: 10.5px; font-weight: 700; color: ${badge.color}; background: ${badge.bg}; padding: 1px 6px; border-radius: 4px;">
                        ${badge.label}
                    </span>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted); line-height: 1.4;">
                    ${escapeHtml(n.message)}
                </div>
                <div style="font-size: 10px; color: var(--text-dim); margin-top: 4px;">
                    Posted by ${escapeHtml(n.posted_by_name || 'Admin')} • ${escapeHtml(n.created_at?.slice(0, 10) || '')}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function renderDashboardDeptAttendance(deptList) {
    const container = document.getElementById('hr-dash-dept-attendance-list');
    if (!container) return;

    if (!deptList || deptList.length === 0) {
        container.innerHTML = '<div style="font-size: 12px; color: var(--text-muted);">No department attendance records available.</div>';
        return;
    }

    let html = '';
    deptList.forEach(d => {
        const total = parseInt(d.total_staff) || 0;
        const present = parseInt(d.present_staff) || 0;
        const pct = total > 0 ? Math.round((present / total) * 100) : 0;
        const color = pct >= 80 ? '#10b981' : (pct >= 50 ? '#f59e0b' : '#ef4444');

        html += `
            <div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; margin-bottom: 4px;">
                    <span style="color: var(--text-main);">${escapeHtml(d.department_name)}</span>
                    <span style="color: ${color};">${present} / ${total} Present (${pct}%)</span>
                </div>
                <div style="width: 100%; height: 7px; background: var(--bg-card-elevated); border-radius: 4px; overflow: hidden; border: 1px solid var(--border-color);">
                    <div style="width: ${pct}%; height: 100%; background: ${color}; border-radius: 4px; transition: width 0.3s ease;"></div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function populateHrEmployeeDropdowns() {
    const employees = AppState.employees || [];

    // Filter dropdown in Leaves table
    const leaveFilter = document.getElementById('hr-leave-emp-filter');
    if (leaveFilter) {
        const currentVal = leaveFilter.value;
        leaveFilter.innerHTML = '<option value="">All Employees</option>';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = emp.name;
            leaveFilter.appendChild(opt);
        });
        leaveFilter.value = currentVal;
    }

    // Filter dropdown in Loans table
    const loanFilter = document.getElementById('hr-loan-emp-filter');
    if (loanFilter) {
        const currentVal = loanFilter.value;
        loanFilter.innerHTML = '<option value="">All Employees</option>';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = emp.name;
            loanFilter.appendChild(opt);
        });
        loanFilter.value = currentVal;
    }

    // Modal employee select for Leaves
    const modalLeaveEmpSelect = document.getElementById('hr-leave-form-emp-id');
    if (modalLeaveEmpSelect) {
        modalLeaveEmpSelect.innerHTML = '';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
            if (AppState.currentUser && emp.id == AppState.currentUser.id) {
                opt.selected = true;
            }
            modalLeaveEmpSelect.appendChild(opt);
        });
    }

    // Modal employee select for Loans
    const modalLoanEmpSelect = document.getElementById('hr-loan-form-emp-id');
    if (modalLoanEmpSelect) {
        modalLoanEmpSelect.innerHTML = '';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
            if (AppState.currentUser && emp.id == AppState.currentUser.id) {
                opt.selected = true;
            }
            modalLoanEmpSelect.appendChild(opt);
        });
    }

    // Target department select for Notices
    const noticeDeptSelect = document.getElementById('hr-notice-target-dept');
    if (noticeDeptSelect && AppState.departments) {
        noticeDeptSelect.innerHTML = '<option value="">🏢 All Departments</option>';
        AppState.departments.forEach(dept => {
            const opt = document.createElement('option');
            opt.value = dept.id;
            opt.textContent = dept.name;
            noticeDeptSelect.appendChild(opt);
        });
    }
}

// ================= 1. LEAVE MANAGEMENT =================

async function loadHrLeaves() {
    const tbody = document.getElementById('hr-leaves-table-body');
    if (!tbody) return;

    const statusFilter = document.getElementById('hr-leave-status-filter')?.value || 'pending';
    const typeFilter = document.getElementById('hr-leave-type-filter')?.value || 'all';
    const empFilter = document.getElementById('hr-leave-emp-filter')?.value || '';

    let url = `api/hr.php?action=get_leaves&status=${encodeURIComponent(statusFilter)}&leave_type=${encodeURIComponent(typeFilter)}`;
    if (empFilter) url += `&employee_id=${encodeURIComponent(empFilter)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            HrState.leaves = data.leaves || [];
            HrState.currentUser = data.current_user || {};
            renderLeavesTable(HrState.leaves);
        } else {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading leaves')}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading leave records.</td></tr>`;
    }
}

function renderLeavesTable(leaves) {
    const tbody = document.getElementById('hr-leaves-table-body');
    const countLabel = document.getElementById('hr-leaves-count-label');
    if (!tbody) return;

    if (countLabel) {
        countLabel.textContent = `Showing ${leaves.length} request(s)`;
    }

    if (!leaves || leaves.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <div style="font-size: 32px; margin-bottom: 8px;">🌴</div>
                    <div style="font-weight: 700; font-size: 14px;">No leave applications found</div>
                    <div style="font-size: 12px; margin-top: 4px;">Click "+ Apply for Leave" above to submit a new request.</div>
                </td>
            </tr>
        `;
        return;
    }

    const currentUser = HrState.currentUser || AppState.currentUser || {};
    const isAdmin = currentUser.role === 'admin' || currentUser.role === 'super_admin' || currentUser.can_manage_hr;
    const isHod = currentUser.is_hod === true || currentUser.role === 'hod' || (currentUser.designation && (currentUser.designation.includes('HOD') || currentUser.designation.includes('Director')));
    const currentUserId = currentUser.id || 0;
    const userDeptId = currentUser.department_id || (AppState.currentUser ? AppState.currentUser.department_id : 0);

    const leaveTypeBadges = {
        annual: { label: '🏖️ Annual', bg: 'rgba(59, 130, 246, 0.1)', color: '#3b82f6', border: 'rgba(59, 130, 246, 0.3)' },
        casual: { label: '🌴 Casual', bg: 'rgba(16, 185, 129, 0.1)', color: '#10b981', border: 'rgba(16, 185, 129, 0.3)' },
        sick: { label: '🩺 Sick', bg: 'rgba(245, 158, 11, 0.1)', color: '#f59e0b', border: 'rgba(245, 158, 11, 0.3)' },
        unpaid: { label: '🚫 Unpaid', bg: 'rgba(239, 68, 68, 0.1)', color: '#ef4444', border: 'rgba(239, 68, 68, 0.3)' },
        other: { label: '📝 Other', bg: 'rgba(107, 114, 128, 0.1)', color: '#6b7280', border: 'rgba(107, 114, 128, 0.3)' }
    };

    const statusBadges = {
        pending: { label: '⏳ Pending HOD', bg: 'rgba(245, 158, 11, 0.12)', color: '#f59e0b', border: 'rgba(245, 158, 11, 0.35)' },
        approved_by_hod: { label: '🟡 Approved by HOD', bg: 'rgba(14, 165, 233, 0.12)', color: '#0284c7', border: 'rgba(14, 165, 233, 0.35)' },
        approved: { label: '✅ Approved by HR', bg: 'rgba(16, 185, 129, 0.12)', color: '#10b981', border: 'rgba(16, 185, 129, 0.35)' },
        rejected: { label: '❌ Rejected', bg: 'rgba(239, 68, 68, 0.12)', color: '#ef4444', border: 'rgba(239, 68, 68, 0.35)' },
        cancelled: { label: '🚫 Cancelled', bg: 'rgba(107, 114, 128, 0.12)', color: '#6b7280', border: 'rgba(107, 114, 128, 0.35)' }
    };

    let html = '';
    leaves.forEach(l => {
        const typeBadge = leaveTypeBadges[l.leave_type] || leaveTypeBadges.other;
        const statBadge = statusBadges[l.status] || statusBadges.pending;

        const avatarInitial = l.employee_name ? l.employee_name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = l.employee_avatar
            ? `<img src="${escapeHtml(l.employee_avatar)}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;" alt="${escapeHtml(l.employee_name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width:28px;height:28px;font-size:12px;\\'>${escapeHtml(avatarInitial)}</div>';">`
            : `<div class="user-avatar" style="width: 28px; height: 28px; font-size: 12px;">${escapeHtml(avatarInitial)}</div>`;

        const isDeptStaff = (l.department_id && l.department_id == userDeptId);

        let actionHtml = '';
        if (isAdmin) {
            if (l.status === 'approved_by_hod') {
                actionHtml = `
                    <div style="display: flex; gap: 4px; justify-content: center;">
                        <button type="button" class="btn btn-success" style="padding: 4px 8px; font-size: 11px; border-radius: 4px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'approved')" title="Grant Final HR Approval">
                            ✓ Final Approve
                        </button>
                        <button type="button" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px; border-radius: 4px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'rejected')" title="Reject Leave">
                            ✕ Reject
                        </button>
                    </div>
                `;
            } else if (l.status === 'pending') {
                actionHtml = `
                    <div style="display: flex; gap: 4px; justify-content: center;">
                        <button type="button" class="btn btn-success" style="padding: 4px 8px; font-size: 11px; border-radius: 4px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'approved')" title="Direct HR Approval">
                            ✓ Approve (HR)
                        </button>
                        <button type="button" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px; border-radius: 4px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'rejected')" title="Reject Leave">
                            ✕ Reject
                        </button>
                    </div>
                `;
            } else {
                actionHtml = `
                    <div style="display: flex; gap: 4px; justify-content: center;">
                        <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px;" onclick="handleLeaveAction(${l.id}, '${l.status === 'approved' ? 'rejected' : 'approved'}')" title="Toggle Approval">
                            Toggle
                        </button>
                        <button type="button" class="btn-icon-del" style="padding: 3px 6px;" onclick="handleDeleteLeave(${l.id})" title="Delete record">🗑️</button>
                    </div>
                `;
            }
        } else if (isHod && isDeptStaff && l.employee_id != currentUserId) {
            if (l.status === 'pending') {
                actionHtml = `
                    <div style="display: flex; gap: 4px; justify-content: center;">
                        <button type="button" class="btn btn-success" style="padding: 4px 8px; font-size: 11px; border-radius: 4px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'approved_by_hod')" title="Approve Leave (Forward to HR)">
                            ✓ Approve (HOD)
                        </button>
                        <button type="button" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px; border-radius: 4px; font-weight: 700;" onclick="handleLeaveAction(${l.id}, 'rejected')" title="Reject Leave">
                            ✕ Reject
                        </button>
                    </div>
                `;
            } else if (l.status === 'approved_by_hod') {
                actionHtml = `<span style="color: #0284c7; font-size: 11px; font-weight: 700;">✓ Approved by HOD (Sent to HR)</span>`;
            } else if (l.status === 'approved') {
                actionHtml = `<span style="color: #10b981; font-size: 11px; font-weight: 700;">✅ Final Approved</span>`;
            } else {
                actionHtml = `<span style="color: var(--text-muted); font-size: 11px;">Completed</span>`;
            }
        } else if (l.employee_id == currentUserId && (l.status === 'pending' || l.status === 'approved_by_hod')) {
            actionHtml = `
                <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: #ef4444; border-color: #ef4444;" onclick="handleDeleteLeave(${l.id})" title="Cancel Application">
                    Cancel
                </button>
            `;
        } else {
            actionHtml = `<span style="color: var(--text-muted); font-size: 11px;">Completed</span>`;
        }

        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${avatarHtml}
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: var(--text-main);">${escapeHtml(l.employee_name)}</div>
                            <div style="font-size: 11.5px; color: var(--text-muted);">${escapeHtml(l.employee_designation || 'Staff')} • ${escapeHtml(l.department_name || 'General')}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: ${typeBadge.bg}; color: ${typeBadge.color}; border: 1px solid ${typeBadge.border};">
                        ${typeBadge.label}
                    </span>
                </td>
                <td>
                    <div style="font-weight: 600; font-size: 12.5px;">${escapeHtml(l.start_date)} ${l.end_date !== l.start_date ? 'to ' + escapeHtml(l.end_date) : ''}</div>
                </td>
                <td style="text-align: center; font-weight: 700; font-size: 13px;">
                    ${l.days_count}
                </td>
                <td>
                    <div style="font-size: 12.5px; color: var(--text-main);">${escapeHtml(l.reason)}</div>
                    ${l.hod_name ? `<div style="font-size: 11px; color: #0284c7; margin-top: 3px;"><b>HOD Endorsed:</b> ${escapeHtml(l.hod_name)} ${l.hod_notes ? '— "' + escapeHtml(l.hod_notes) + '"' : ''}</div>` : ''}
                    ${l.admin_notes ? `<div style="font-size: 11px; color: #6366f1; margin-top: 2px;"><b>HR note:</b> ${escapeHtml(l.admin_notes)}</div>` : ''}
                </td>
                <td style="text-align: center;">
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: ${statBadge.bg}; color: ${statBadge.color}; border: 1px solid ${statBadge.border};" title="${l.status === 'approved_by_hod' ? 'Endorsed by HOD (' + (l.hod_name || 'HOD') + ') - Pending HR Final Approval' : ''}">
                        ${statBadge.label}
                    </span>
                </td>
                <td style="text-align: center;">
                    ${actionHtml}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function openApplyLeaveModal() {
    const today = getLocalDateString();
    const startInput = document.getElementById('hr-leave-form-start-date');
    const endInput = document.getElementById('hr-leave-form-end-date');
    const reasonInput = document.getElementById('hr-leave-form-reason');

    if (startInput) startInput.value = today;
    if (endInput) endInput.value = today;
    if (reasonInput) reasonInput.value = '';

    calculateLeaveFormDays();
    openModal('hr-apply-leave-modal');
}

function calculateLeaveFormDays() {
    const sVal = document.getElementById('hr-leave-form-start-date')?.value;
    const eVal = document.getElementById('hr-leave-form-end-date')?.value;
    const label = document.getElementById('hr-leave-form-calculated-days');
    if (!sVal || !eVal || !label) return;

    const s = new Date(sVal);
    const e = new Date(eVal);
    if (e < s) {
        label.textContent = "⚠️ End date cannot be before start date.";
        label.style.color = '#ef4444';
        return;
    }

    const diffTime = Math.abs(e - s);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
    label.textContent = `Duration: ${diffDays} Day(s)`;
    label.style.color = 'var(--primary)';
}

async function handleApplyLeaveSubmit(e) {
    if (e) e.preventDefault();

    const empId = document.getElementById('hr-leave-form-emp-id')?.value || AppState.currentUser?.id;
    const leaveType = document.getElementById('hr-leave-form-type')?.value || 'casual';
    const startDate = document.getElementById('hr-leave-form-start-date')?.value;
    const endDate = document.getElementById('hr-leave-form-end-date')?.value || startDate;
    const reason = document.getElementById('hr-leave-form-reason')?.value.trim();
    const autoApprove = document.getElementById('hr-leave-form-auto-approve')?.checked ? 1 : 0;

    if (!startDate || !reason) {
        showToast("Please fill in all required fields.", "error");
        return;
    }

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'apply_leave',
                employee_id: empId,
                leave_type: leaveType,
                start_date: startDate,
                end_date: endDate,
                reason: reason,
                auto_approve: autoApprove
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || "Leave application submitted successfully.", "success");
            closeModal('hr-apply-leave-modal');
            await fetchHrOverview();
            await loadHrLeaves();
        } else {
            showToast(data.message || "Failed to submit leave.", "error");
        }
    } catch (err) {
        showToast("Network error submitting leave.", "error");
    }
}

async function handleLeaveAction(leaveId, status) {
    let notes = '';
    if (status === 'rejected') {
        notes = prompt("Enter reason for rejection (optional):") || '';
    } else if (status === 'approved_by_hod') {
        notes = prompt("Enter HOD endorsement note (optional):", "Recommended for approval") || '';
    }

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_leave_status',
                leave_id: leaveId,
                status: status,
                admin_notes: notes,
                hod_notes: notes
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || `Leave application updated.`, "success");
            await fetchHrOverview();
            await loadHrLeaves();
            if (typeof updateGlobalSidebarBadges === 'function') updateGlobalSidebarBadges();
        } else {
            showToast(data.message || "Failed to update leave status.", "error");
        }
    } catch (err) {
        showToast("Network error updating leave status.", "error");
    }
}

async function handleDeleteLeave(leaveId) {
    if (!confirm("Are you sure you want to cancel/remove this leave record?")) return;

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_leave', leave_id: leaveId })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || "Leave application removed.", "info");
            await fetchHrOverview();
            await loadHrLeaves();
        } else {
            showToast(data.message || "Failed to delete leave.", "error");
        }
    } catch (err) {
        showToast("Network error deleting leave.", "error");
    }
}


// ================= 2. SALARY ADVANCE & EMERGENCY LOANS =================

async function loadHrLoans() {
    const tbody = document.getElementById('hr-loans-table-body');
    if (!tbody) return;

    const statusFilter = document.getElementById('hr-loan-status-filter')?.value || 'pending';
    const typeFilter = document.getElementById('hr-loan-type-filter')?.value || 'all';
    const empFilter = document.getElementById('hr-loan-emp-filter')?.value || '';

    let url = `api/hr.php?action=get_loans&status=${encodeURIComponent(statusFilter)}&request_type=${encodeURIComponent(typeFilter)}`;
    if (empFilter) url += `&employee_id=${encodeURIComponent(empFilter)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            HrState.loans = data.loans || [];
            renderLoansTable(HrState.loans);
        } else {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading loan records')}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading loan records.</td></tr>`;
    }
}

function renderLoansTable(loans) {
    const tbody = document.getElementById('hr-loans-table-body');
    const countLabel = document.getElementById('hr-loans-count-label');
    if (!tbody) return;

    if (countLabel) {
        countLabel.textContent = `Showing ${loans.length} loan record(s)`;
    }

    if (!loans || loans.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <div style="font-size: 32px; margin-bottom: 8px;">💳</div>
                    <div style="font-weight: 700; font-size: 14px;">No loan or advance requests found</div>
                    <div style="font-size: 12px; margin-top: 4px;">Click "+ Request Advance / Loan" above to create an application.</div>
                </td>
            </tr>
        `;
        return;
    }

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.can_manage_hr);
    const currentUserId = AppState.currentUser ? AppState.currentUser.id : 0;

    const loanTypeBadges = {
        advance_salary: { label: '💵 Advance Salary', bg: 'rgba(14, 165, 233, 0.1)', color: '#0ea5e9' },
        emergency_loan: { label: '🚨 Emergency Loan', bg: 'rgba(139, 92, 246, 0.1)', color: '#8b5cf6' },
        medical_aid: { label: '🩺 Medical Aid', bg: 'rgba(16, 185, 129, 0.1)', color: '#10b981' }
    };

    const statusBadges = {
        pending: { label: '⏳ Pending', bg: 'rgba(245, 158, 11, 0.12)', color: '#f59e0b' },
        approved: { label: '✅ Active / Approved', bg: 'rgba(16, 185, 129, 0.12)', color: '#10b981' },
        repaid: { label: '🎉 Fully Repaid', bg: 'rgba(59, 130, 246, 0.12)', color: '#3b82f6' },
        rejected: { label: '❌ Rejected', bg: 'rgba(239, 68, 68, 0.12)', color: '#ef4444' },
        cancelled: { label: '🚫 Cancelled', bg: 'rgba(107, 114, 128, 0.12)', color: '#6b7280' }
    };

    let html = '';
    loans.forEach(l => {
        const typeBadge = loanTypeBadges[l.request_type] || loanTypeBadges.advance_salary;
        const statBadge = statusBadges[l.status] || statusBadges.pending;

        const avatarInitial = l.employee_name ? l.employee_name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = l.employee_avatar
            ? `<img src="${escapeHtml(l.employee_avatar)}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;" alt="${escapeHtml(l.employee_name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width:28px;height:28px;font-size:12px;\\'>${escapeHtml(avatarInitial)}</div>';">`
            : `<div class="user-avatar" style="width: 28px; height: 28px; font-size: 12px;">${escapeHtml(avatarInitial)}</div>`;

        let actionHtml = '';
        if (isAdmin && l.status === 'pending') {
            actionHtml = `
                <div style="display: flex; gap: 4px; justify-content: center;">
                    <button type="button" class="btn btn-success" style="padding: 4px 8px; font-size: 11px;" onclick="handleLoanAction(${l.id}, 'approved')" title="Approve Request">
                        ✓ Approve
                    </button>
                    <button type="button" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;" onclick="handleLoanAction(${l.id}, 'rejected')" title="Reject Request">
                        ✕ Reject
                    </button>
                </div>
            `;
        } else if (isAdmin && l.status === 'approved') {
            actionHtml = `
                <div style="display: flex; gap: 4px; justify-content: center;">
                    <button type="button" class="btn btn-outline" style="padding: 3px 6px; font-size: 11px; color: #10b981;" onclick="handleLoanAction(${l.id}, 'repaid')" title="Mark as Fully Repaid">
                        Mark Repaid
                    </button>
                    <button type="button" class="btn-icon-del" style="padding: 3px 6px;" onclick="handleDeleteLoan(${l.id})" title="Delete record">🗑️</button>
                </div>
            `;
        } else if (isAdmin) {
            actionHtml = `
                <button type="button" class="btn-icon-del" style="padding: 3px 6px;" onclick="handleDeleteLoan(${l.id})" title="Delete record">🗑️</button>
            `;
        } else if (l.employee_id == currentUserId && l.status === 'pending') {
            actionHtml = `
                <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; color: #ef4444; border-color: #ef4444;" onclick="handleDeleteLoan(${l.id})" title="Cancel Request">
                    Cancel
                </button>
            `;
        } else {
            actionHtml = `<span style="color: var(--text-muted); font-size: 11px;">Completed</span>`;
        }

        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${avatarHtml}
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: var(--text-main);">${escapeHtml(l.employee_name)}</div>
                            <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(l.employee_designation || 'Staff')} • ${escapeHtml(l.department_name || 'General')}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: ${typeBadge.bg}; color: ${typeBadge.color};">
                        ${typeBadge.label}
                    </span>
                </td>
                <td style="font-weight: 800; font-size: 13.5px; color: var(--text-main);">
                    PKR ${parseFloat(l.amount).toLocaleString()}
                </td>
                <td>
                    <div style="font-weight: 600; font-size: 12px;">${l.repayment_months} Mo @ PKR ${parseFloat(l.monthly_deduction).toLocaleString()}/mo</div>
                    <div style="font-size: 10.5px; color: var(--primary);">Starts: ${escapeHtml(l.deduction_start_month)}</div>
                </td>
                <td>
                    <div style="font-size: 12px; font-weight: 700; color: #10b981;">Paid: PKR ${parseFloat(l.paid_amount || 0).toLocaleString()}</div>
                    <div style="font-size: 11px; font-weight: 700; color: #ef4444;">Rem: PKR ${parseFloat(l.remaining_amount || 0).toLocaleString()}</div>
                </td>
                <td>
                    <div style="font-size: 12px; color: var(--text-main);">${escapeHtml(l.reason)}</div>
                    ${l.admin_notes ? `<div style="font-size: 10.5px; color: #6366f1; margin-top: 2px;"><b>Admin note:</b> ${escapeHtml(l.admin_notes)}</div>` : ''}
                </td>
                <td style="text-align: center;">
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: ${statBadge.bg}; color: ${statBadge.color};">
                        ${statBadge.label}
                    </span>
                </td>
                <td style="text-align: center;">
                    ${actionHtml}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function openApplyLoanModal() {
    const currentMonth = new Date().toISOString().slice(0, 7);
    const startMonthInput = document.getElementById('hr-loan-form-start-month');
    const amountInput = document.getElementById('hr-loan-form-amount');
    const reasonInput = document.getElementById('hr-loan-form-reason');

    if (startMonthInput) startMonthInput.value = currentMonth;
    if (amountInput) amountInput.value = '';
    if (reasonInput) reasonInput.value = '';

    calculateLoanFormDeduction();
    openModal('hr-apply-loan-modal');
}

function calculateLoanFormDeduction() {
    const amount = parseFloat(document.getElementById('hr-loan-form-amount')?.value) || 0;
    const months = parseInt(document.getElementById('hr-loan-form-months')?.value) || 1;
    const monthly = Math.round(amount / months);

    const display = document.getElementById('hr-loan-form-monthly-display');
    if (display) {
        display.textContent = `PKR ${monthly.toLocaleString()} / mo`;
    }
}

async function handleApplyLoanSubmit(e) {
    if (e) e.preventDefault();

    const empId = document.getElementById('hr-loan-form-emp-id')?.value || AppState.currentUser?.id;
    const reqType = document.getElementById('hr-loan-form-type')?.value || 'advance_salary';
    const amount = parseFloat(document.getElementById('hr-loan-form-amount')?.value) || 0;
    const months = parseInt(document.getElementById('hr-loan-form-months')?.value) || 1;
    const startMonth = document.getElementById('hr-loan-form-start-month')?.value;
    const reason = document.getElementById('hr-loan-form-reason')?.value.trim();
    const autoApprove = document.getElementById('hr-loan-form-auto-approve')?.checked ? 1 : 0;

    if (amount <= 0 || !reason) {
        showToast("Please enter a valid requested amount and reason.", "error");
        return;
    }

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'apply_loan',
                employee_id: empId,
                request_type: reqType,
                amount: amount,
                repayment_months: months,
                deduction_start_month: startMonth,
                reason: reason,
                auto_approve: autoApprove
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || "Advance/Loan application submitted successfully.", "success");
            closeModal('hr-apply-loan-modal');
            await fetchHrOverview();
            await loadHrLoans();
        } else {
            showToast(data.message || "Failed to submit request.", "error");
        }
    } catch (err) {
        showToast("Network error submitting request.", "error");
    }
}

async function handleLoanAction(loanId, status) {
    let adminNotes = '';
    let paidAmount = null;

    if (status === 'rejected') {
        adminNotes = prompt("Enter reason for rejection (optional):") || '';
    } else if (status === 'repaid') {
        if (!confirm("Are you sure you want to mark this loan as fully repaid?")) return;
    }

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_loan_status',
                loan_id: loanId,
                status: status,
                admin_notes: adminNotes,
                paid_amount: paidAmount
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || `Request ${status}.`, "success");
            await fetchHrOverview();
            await loadHrLoans();
        } else {
            showToast(data.message || "Failed to update request.", "error");
        }
    } catch (err) {
        showToast("Network error updating request.", "error");
    }
}

async function handleDeleteLoan(loanId) {
    if (!confirm("Are you sure you want to cancel/remove this loan application?")) return;

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_loan', loan_id: loanId })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || "Loan application removed.", "info");
            await fetchHrOverview();
            await loadHrLoans();
        } else {
            showToast(data.message || "Failed to delete request.", "error");
        }
    } catch (err) {
        showToast("Network error deleting request.", "error");
    }
}


// ================= 3. COMPANY NOTICE BOARD =================

async function loadHrNotices() {
    const feed = document.getElementById('hr-notices-feed');
    if (!feed) return;

    try {
        const res = await fetch('api/hr.php?action=get_notices');
        const data = await res.json();

        if (data.success) {
            HrState.notices = data.notices || [];
            renderNoticesFeed(HrState.notices);
        } else {
            feed.innerHTML = `<div style="text-align: center; color: #ef4444; padding: 30px; grid-column: 1 / -1;">${escapeHtml(data.message || 'Error loading notices')}</div>`;
        }
    } catch (err) {
        feed.innerHTML = `<div style="text-align: center; color: #ef4444; padding: 30px; grid-column: 1 / -1;">Network error loading notices.</div>`;
    }
}

function renderNoticesFeed(notices) {
    const feed = document.getElementById('hr-notices-feed');
    if (!feed) return;

    if (!notices || notices.length === 0) {
        feed.innerHTML = `
            <div style="text-align: center; padding: 50px 20px; color: var(--text-muted); grid-column: 1 / -1;">
                <div style="font-size: 36px; margin-bottom: 8px;">📢</div>
                <div style="font-weight: 700; font-size: 15px;">No Company Notices Posted</div>
                <div style="font-size: 12.5px; margin-top: 4px;">Click "+ Post Announcement" above to broadcast a new update.</div>
            </div>
        `;
        return;
    }

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.can_manage_hr);

    const priorityThemes = {
        urgent: { label: '🚨 Urgent / Important', bg: 'rgba(239, 68, 68, 0.08)', border: '#ef4444', text: '#ef4444' },
        holiday: { label: '🎉 Holiday Schedule', bg: 'rgba(16, 185, 129, 0.08)', border: '#10b981', text: '#10b981' },
        event: { label: '🏆 Company Event', bg: 'rgba(139, 92, 246, 0.08)', border: '#8b5cf6', text: '#8b5cf6' },
        normal: { label: '📢 General Notice', bg: 'rgba(14, 165, 233, 0.08)', border: '#0ea5e9', text: '#0ea5e9' }
    };

    let html = '';
    notices.forEach(n => {
        const theme = priorityThemes[n.priority] || priorityThemes.normal;
        html += `
            <div class="worksheet-card" style="border-left: 4px solid ${theme.border}; background: var(--bg-card); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px;">
                        <span style="font-size: 11px; font-weight: 800; color: ${theme.text}; background: ${theme.bg}; padding: 3px 8px; border-radius: 4px;">
                            ${theme.label}
                        </span>
                        ${n.target_department_name ? `<span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">🏢 ${escapeHtml(n.target_department_name)}</span>` : '<span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">🏢 All Departments</span>'}
                    </div>

                    <h3 style="font-size: 16px; font-weight: 800; color: var(--text-main); margin-bottom: 8px; line-height: 1.3;">
                        ${escapeHtml(n.title)}
                    </h3>

                    <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; white-space: pre-line; margin-bottom: 16px;">
                        ${escapeHtml(n.message)}
                    </p>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 10px; margin-top: auto; font-size: 11.5px; color: var(--text-dim);">
                    <div>
                        Posted by <b>${escapeHtml(n.posted_by_name || 'Admin')}</b> • ${escapeHtml(n.created_at || '')}
                    </div>
                    ${isAdmin ? `
                    <button type="button" class="btn-icon-del" style="padding: 3px 6px;" onclick="handleDeleteNotice(${n.id})" title="Archive Notice">🗑️</button>
                    ` : ''}
                </div>
            </div>
        `;
    });

    feed.innerHTML = html;
}

function openCreateNoticeModal() {
    populateHrEmployeeDropdowns();
    document.getElementById('hr-notice-title').value = '';
    document.getElementById('hr-notice-message').value = '';
    document.getElementById('hr-notice-priority').value = 'normal';
    openModal('hr-create-notice-modal');
}

async function handleSaveNoticeSubmit(e) {
    if (e) e.preventDefault();

    const title = document.getElementById('hr-notice-title')?.value.trim();
    const message = document.getElementById('hr-notice-message')?.value.trim();
    const priority = document.getElementById('hr-notice-priority')?.value || 'normal';
    const targetDeptId = document.getElementById('hr-notice-target-dept')?.value;

    if (!title || !message) {
        showToast("Please enter title and announcement content.", "error");
        return;
    }

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_notice',
                title: title,
                message: message,
                priority: priority,
                target_department_id: targetDeptId
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast("Announcement broadcasted successfully.", "success");
            closeModal('hr-create-notice-modal');
            await fetchHrOverview();
            await loadHrNotices();
        } else {
            showToast(data.message || "Failed to post notice.", "error");
        }
    } catch (err) {
        showToast("Network error broadcasting notice.", "error");
    }
}

async function handleDeleteNotice(noticeId) {
    if (!confirm("Are you sure you want to archive this notice?")) return;

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_notice', notice_id: noticeId })
        });
        const data = await res.json();

        if (data.success) {
            showToast("Notice archived.", "info");
            await fetchHrOverview();
            await loadHrNotices();
        } else {
            showToast(data.message || "Failed to archive notice.", "error");
        }
    } catch (err) {
        showToast("Network error archiving notice.", "error");
    }
}


// ================= 4. MONTHLY PAYROLL & SALARIES =================

async function loadHrPayroll() {
    const tbody = document.getElementById('hr-payroll-table-body');
    if (!tbody) return;

    const month = document.getElementById('hr-payroll-month')?.value || HrState.selectedMonth;

    try {
        const res = await fetch(`api/hr.php?action=get_payroll&month=${encodeURIComponent(month)}`);
        const data = await res.json();

        if (data.success) {
            HrState.payroll = data.payroll || [];
            renderHrPayrollTable(HrState.payroll);
        } else {
            tbody.innerHTML = `<tr><td colspan="10" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading payroll')}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="10" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading payroll records.</td></tr>`;
    }
}

function renderHrPayrollTable(payroll) {
    const tbody = document.getElementById('hr-payroll-table-body');
    if (!tbody) return;

    if (!payroll || payroll.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" style="text-align: center; padding: 30px; color: var(--text-muted);">No staff records found for selected month.</td></tr>`;
        return;
    }

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.can_manage_hr);

    const statusBadges = {
        draft: { label: 'Draft', bg: 'rgba(107, 114, 128, 0.1)', color: '#6b7280' },
        approved: { label: 'Approved', bg: 'rgba(59, 130, 246, 0.1)', color: '#3b82f6' },
        paid: { label: 'Paid ✅', bg: 'rgba(16, 185, 129, 0.12)', color: '#10b981' }
    };

    let html = '';
    payroll.forEach(p => {
        const stBadge = statusBadges[p.payment_status] || statusBadges.draft;
        const avatarInitial = p.employee_name ? p.employee_name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = p.avatar
            ? `<img src="${escapeHtml(p.avatar)}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;" alt="${escapeHtml(p.employee_name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width:28px;height:28px;font-size:12px;\\'>${escapeHtml(avatarInitial)}</div>';">`
            : `<div class="user-avatar" style="width: 28px; height: 28px; font-size: 12px;">${escapeHtml(avatarInitial)}</div>`;

        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${avatarHtml}
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: var(--text-main);">${escapeHtml(p.employee_name)}</div>
                            <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(p.designation || 'Staff')} • ${escapeHtml(p.department_name || 'General')}</div>
                        </div>
                    </div>
                </td>
                <td style="text-align: center; font-weight: 700; color: #10b981;">
                    ${p.present_days} / ${p.working_days}
                </td>
                <td style="text-align: center; font-weight: 600;">
                    ${p.approved_leaves > 0 ? `<span style="color: #3b82f6;">${p.approved_leaves}d</span>` : '0'}
                </td>
                <td style="text-align: center; font-weight: 600; font-size: 12px;">
                    ${p.total_duty_hours} hrs
                </td>
                <td style="font-size: 12.5px; font-weight: 600;">
                    PKR ${parseFloat(p.basic_salary).toLocaleString()}
                </td>
                <td style="font-size: 12.5px; color: #10b981; font-weight: 600;">
                    ${p.bonus > 0 ? `+${parseFloat(p.bonus).toLocaleString()}` : '0'}
                </td>
                <td style="font-size: 12.5px; color: #ef4444; font-weight: 600;">
                    ${p.deductions > 0 ? `-${parseFloat(p.deductions).toLocaleString()}` : '0'}
                    ${p.auto_loan_deduction > 0 ? `<div style="font-size: 10px; color: #8b5cf6;">💳 Loan: ${parseFloat(p.auto_loan_deduction).toLocaleString()}</div>` : ''}
                </td>
                <td style="font-size: 13.5px; font-weight: 800; color: #10b981;">
                    PKR ${parseFloat(p.net_salary).toLocaleString()}
                </td>
                <td style="text-align: center;">
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: ${stBadge.bg}; color: ${stBadge.color};">
                        ${stBadge.label}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 4px; justify-content: center;">
                        ${isAdmin ? `<button type="button" class="btn btn-outline" style="padding: 3px 6px; font-size: 11px;" onclick='openEditPayrollModal(${JSON.stringify(p)})' title="Edit Adjustments">✏️</button>` : ''}
                        <button type="button" class="btn btn-outline" style="padding: 3px 6px; font-size: 11px;" onclick='printSalarySlip(${JSON.stringify(p)})' title="Print Pay Slip">🖨️ Slip</button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function openEditPayrollModal(item) {
    document.getElementById('hr-pay-emp-id').value = item.employee_id;
    document.getElementById('hr-pay-month').value = item.salary_month;
    document.getElementById('hr-pay-emp-name-display').textContent = item.employee_name;
    document.getElementById('hr-pay-month-display').textContent = `Month: ${item.salary_month} (${item.present_days} days present, ${item.total_duty_hours} hrs duty)`;

    document.getElementById('hr-pay-basic-salary').value = item.basic_salary;
    document.getElementById('hr-pay-bonus').value = item.bonus || 0;
    document.getElementById('hr-pay-deductions').value = item.deductions || 0;
    document.getElementById('hr-pay-bonus-reason').value = item.bonus_reason || '';
    document.getElementById('hr-pay-deduction-reason').value = item.deduction_reason || '';
    document.getElementById('hr-pay-status').value = item.payment_status || 'draft';
    document.getElementById('hr-pay-method').value = item.payment_method || 'Bank Transfer';

    calculateModalNetSalary();
    openModal('hr-edit-payroll-modal');
}

function calculateModalNetSalary() {
    const basic = parseFloat(document.getElementById('hr-pay-basic-salary')?.value) || 0;
    const bonus = parseFloat(document.getElementById('hr-pay-bonus')?.value) || 0;
    const deductions = parseFloat(document.getElementById('hr-pay-deductions')?.value) || 0;
    const net = Math.max(0, basic + bonus - deductions);

    const netDisplay = document.getElementById('hr-pay-net-salary-display');
    if (netDisplay) {
        netDisplay.textContent = `PKR ${net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
}

async function handleSavePayrollItemSubmit(e) {
    if (e) e.preventDefault();

    const empId = document.getElementById('hr-pay-emp-id')?.value;
    const month = document.getElementById('hr-pay-month')?.value;
    const basic = document.getElementById('hr-pay-basic-salary')?.value;
    const bonus = document.getElementById('hr-pay-bonus')?.value;
    const deductions = document.getElementById('hr-pay-deductions')?.value;
    const bonusReason = document.getElementById('hr-pay-bonus-reason')?.value.trim();
    const deductionReason = document.getElementById('hr-pay-deduction-reason')?.value.trim();
    const status = document.getElementById('hr-pay-status')?.value;
    const method = document.getElementById('hr-pay-method')?.value.trim();

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_payroll_item',
                employee_id: empId,
                salary_month: month,
                basic_salary: basic,
                bonus: bonus,
                deductions: deductions,
                bonus_reason: bonusReason,
                deduction_reason: deductionReason,
                payment_status: status,
                payment_method: method
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast("Payroll adjustment saved successfully.", "success");
            closeModal('hr-edit-payroll-modal');
            await loadHrPayroll();
        } else {
            showToast(data.message || "Failed to save adjustments.", "error");
        }
    } catch (err) {
        showToast("Network error saving payroll.", "error");
    }
}

function printSalarySlip(item) {
    const printWindow = window.open('', '_blank', 'width=800,height=900');
    if (!printWindow) {
        showToast("Please allow popups to view and print salary slips.", "error");
        return;
    }

    const monthName = new Date(item.salary_month + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

    const slipHtml = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Salary Pay Slip - ${escapeHtml(item.employee_name)} - ${monthName}</title>
        <style>
            * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; }
            body { padding: 40px; color: #1e293b; background: #fff; }
            .slip-card { max-width: 720px; margin: 0 auto; border: 2px solid #0f172a; padding: 30px; border-radius: 8px; }
            .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 20px; }
            .header h1 { font-size: 22px; text-transform: uppercase; letter-spacing: 1px; color: #0284c7; }
            .header p { font-size: 13px; color: #64748b; margin-top: 4px; }
            .badge-month { display: inline-block; background: #f1f5f9; padding: 4px 14px; border-radius: 20px; font-weight: 700; font-size: 13px; margin-top: 8px; border: 1px solid #cbd5e1; }
            .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
            .meta-box { background: #f8fafc; padding: 12px 16px; border-radius: 6px; border: 1px solid #e2e8f0; }
            .meta-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
            .meta-label { color: #64748b; font-weight: 600; }
            .meta-val { font-weight: 700; color: #0f172a; }
            .table-box { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
            .table-box th, .table-box td { border: 1px solid #cbd5e1; padding: 10px 14px; font-size: 13px; }
            .table-box th { background: #f1f5f9; font-weight: 700; text-align: left; }
            .table-box td.amount { text-align: right; font-weight: 700; }
            .net-box { background: #ecfdf5; border: 2px solid #10b981; padding: 14px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
            .net-title { font-size: 15px; font-weight: 800; color: #065f46; }
            .net-amount { font-size: 20px; font-weight: 800; color: #047857; }
            .sig-row { display: flex; justify-content: space-between; margin-top: 60px; padding: 0 20px; }
            .sig-line { width: 200px; border-top: 1.5px solid #475569; text-align: center; padding-top: 6px; font-size: 12px; font-weight: 700; color: #475569; }
            @media print {
                body { padding: 0; }
                .slip-card { border: none; padding: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="slip-card">
            <div class="header">
                <h1>Discover Pakistan HD TV</h1>
                <p>Digital Media & Operations Department • Official Salary Pay Slip</p>
                <div class="badge-month">Salary Month: ${monthName}</div>
            </div>

            <div class="grid-2">
                <div class="meta-box">
                    <div class="meta-row"><span class="meta-label">Employee Name:</span><span class="meta-val">${escapeHtml(item.employee_name)}</span></div>
                    <div class="meta-row"><span class="meta-label">Designation:</span><span class="meta-val">${escapeHtml(item.designation || 'Staff Member')}</span></div>
                    <div class="meta-row"><span class="meta-label">Department:</span><span class="meta-val">${escapeHtml(item.department_name || 'General')}</span></div>
                </div>
                <div class="meta-box">
                    <div class="meta-row"><span class="meta-label">Present Days:</span><span class="meta-val">${item.present_days} / ${item.working_days} Days</span></div>
                    <div class="meta-row"><span class="meta-label">Approved Leaves:</span><span class="meta-val">${item.approved_leaves} Day(s)</span></div>
                    <div class="meta-row"><span class="meta-label">Duty Hours:</span><span class="meta-val">${item.total_duty_hours} Hours</span></div>
                </div>
            </div>

            <table class="table-box">
                <thead>
                    <tr>
                        <th>Earnings & Allowances</th>
                        <th style="width: 30%; text-align: right;">Amount (PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic Salary</td>
                        <td class="amount">PKR ${parseFloat(item.basic_salary).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                    </tr>
                    ${item.bonus > 0 ? `
                    <tr>
                        <td>Bonus / Performance Incentive ${item.bonus_reason ? `(${escapeHtml(item.bonus_reason)})` : ''}</td>
                        <td class="amount" style="color: #059669;">+ PKR ${parseFloat(item.bonus).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                    </tr>` : ''}
                    ${item.deductions > 0 ? `
                    <tr>
                        <td>Deductions ${item.deduction_reason ? `(${escapeHtml(item.deduction_reason)})` : ''}</td>
                        <td class="amount" style="color: #dc2626;">- PKR ${parseFloat(item.deductions).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                    </tr>` : ''}
                </tbody>
            </table>

            <div class="net-box">
                <span class="net-title">TOTAL NET SALARY PAYABLE:</span>
                <span class="net-amount">PKR ${parseFloat(item.net_salary).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
            </div>

            <div class="sig-row">
                <div class="sig-line">Employee Signature</div>
                <div class="sig-line">HR & Accounts Manager</div>
            </div>
        </div>

        <script>
            window.addEventListener('load', function() {
                setTimeout(function() { window.print(); }, 250);
            });
        </script>
    </body>
    </html>
    `;

    printWindow.document.open();
    printWindow.document.write(slipHtml);
    printWindow.document.close();
}
