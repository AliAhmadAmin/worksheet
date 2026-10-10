/**
 * HR Management Module: Leaves, Advance/Loans, Notices, Staff HR Records, and Automated Payroll
 */

let HrState = {
    overview: null,
    leaves: [],
    loans: [],
    notices: [],
    fines: [],
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
    const finesMonthInput = document.getElementById('hr-fines-month-filter');
    if (finesMonthInput && !finesMonthInput.value) {
        finesMonthInput.value = HrState.selectedMonth;
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
    } else if (document.getElementById('tab-hr-fines')?.classList.contains('active')) {
        await loadHrFines();
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
    const isAdmin = (currentUser.role === 'admin' || currentUser.role === 'super_admin' || (currentUser.can_manage_hr && currentUser.role !== 'hod'));
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

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || (AppState.currentUser.can_manage_hr && AppState.currentUser.role !== 'hod'));

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
                        PKR ${Math.round(parseFloat(l.amount || 0)).toLocaleString('en-US')} <span style="font-size: 11px; font-weight: 600; color: var(--text-muted);">(${l.repayment_months} mo @ PKR ${Math.round(parseFloat(l.monthly_deduction || 0)).toLocaleString('en-US')}/mo)</span>
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

async function populateHrEmployeeDropdowns() {
    if (!AppState.employees || AppState.employees.length === 0) {
        try {
            const res = await fetch('api/employees.php?action=list');
            const data = await res.json();
            if (data && data.success && data.employees) {
                AppState.employees = data.employees;
                AppState.departments = data.departments || [];
                AppState.teams = data.teams || [];
            }
        } catch (e) {
            console.error("Error fetching employees for dropdowns:", e);
        }
    }

    const employees = AppState.employees || [];

    // Filter dropdown in Leaves table
    const leaveFilter = document.getElementById('hr-leave-emp-filter');
    if (leaveFilter) {
        const currentVal = leaveFilter.value;
        leaveFilter.innerHTML = '<option value="">All Employees</option>';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
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
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
            loanFilter.appendChild(opt);
        });
        loanFilter.value = currentVal;
    }

    // Modal employee select for Leaves
    const modalLeaveEmpSelect = document.getElementById('hr-leave-form-emp-id');
    if (modalLeaveEmpSelect) {
        const currentVal = modalLeaveEmpSelect.value;
        modalLeaveEmpSelect.innerHTML = '';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
            if (currentVal ? emp.id == currentVal : (AppState.currentUser && emp.id == AppState.currentUser.id)) {
                opt.selected = true;
            }
            modalLeaveEmpSelect.appendChild(opt);
        });
    }

    // Modal employee select for Loans
    const modalLoanEmpSelect = document.getElementById('hr-loan-form-emp-id');
    if (modalLoanEmpSelect) {
        const currentVal = modalLoanEmpSelect.value;
        modalLoanEmpSelect.innerHTML = '';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
            if (currentVal ? emp.id == currentVal : (AppState.currentUser && emp.id == AppState.currentUser.id)) {
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

    // Filter dropdown in Fines table
    const finesFilter = document.getElementById('hr-fines-emp-filter');
    if (finesFilter) {
        const currentVal = finesFilter.value;
        finesFilter.innerHTML = '<option value="">All Employees</option>';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
            finesFilter.appendChild(opt);
        });
        finesFilter.value = currentVal;
    }

    // Modal employee select for Fines
    const modalFineEmpSelect = document.getElementById('hr-fine-form-emp-id');
    if (modalFineEmpSelect) {
        const currentVal = modalFineEmpSelect.value;
        modalFineEmpSelect.innerHTML = '';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
            if (currentVal ? emp.id == currentVal : (AppState.currentUser && emp.id == AppState.currentUser.id)) {
                opt.selected = true;
            }
            modalFineEmpSelect.appendChild(opt);
        });
    }

    // Filter dropdown in Claims table
    const claimsFilter = document.getElementById('hr-claims-emp-filter');
    if (claimsFilter) {
        const currentVal = claimsFilter.value;
        claimsFilter.innerHTML = '<option value="">All Employees</option>';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
            claimsFilter.appendChild(opt);
        });
        claimsFilter.value = currentVal;
    }

    // Modal employee select for Claims
    const modalClaimEmpSelect = document.getElementById('hr-claim-form-emp-id');
    if (modalClaimEmpSelect) {
        const currentVal = modalClaimEmpSelect.value;
        modalClaimEmpSelect.innerHTML = '';
        employees.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.id;
            opt.textContent = `${emp.name} (${emp.designation || 'Staff'})`;
            if (currentVal ? emp.id == currentVal : (AppState.currentUser && emp.id == AppState.currentUser.id)) {
                opt.selected = true;
            }
            modalClaimEmpSelect.appendChild(opt);
        });
    }

    // Populate Department filters across all HR subtabs
    const deptDropdownIds = [
        'hr-leave-dept-filter',
        'hr-loan-dept-filter',
        'hr-claims-dept-filter',
        'hr-notice-dept-filter',
        'hr-fines-dept-filter'
    ];
    deptDropdownIds.forEach(id => {
        const el = document.getElementById(id);
        if (el && AppState.departments) {
            const currentVal = el.value;
            el.innerHTML = '<option value="">🏢 All Departments</option>';
            AppState.departments.forEach(dept => {
                const opt = document.createElement('option');
                opt.value = dept.name;
                opt.textContent = `🏢 ${dept.name}`;
                el.appendChild(opt);
            });
            if (currentVal) el.value = currentVal;
        }
    });
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
            applyLeavesClientFilters();
        } else {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading leaves')}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading leave records.</td></tr>`;
    }
}

function applyLeavesClientFilters() {
    const search = (document.getElementById('hr-leave-search')?.value || '').toLowerCase().trim();
    const dept = (document.getElementById('hr-leave-dept-filter')?.value || '').toLowerCase().trim();
    const status = document.getElementById('hr-leave-status-filter')?.value || 'all';
    const type = document.getElementById('hr-leave-type-filter')?.value || 'all';
    const empId = document.getElementById('hr-leave-emp-filter')?.value || '';

    // Show/hide reset button
    const resetBtn = document.getElementById('hr-leave-reset-filters-btn');
    if (resetBtn) {
        const isFiltered = !!(search || dept || empId || status !== 'pending' || type !== 'all');
        resetBtn.style.display = isFiltered ? 'inline-flex' : 'none';
    }

    let list = HrState.leaves || [];

    if (dept) {
        list = list.filter(l => {
            const dName = (l.department_name || '').toLowerCase();
            return dName === dept || (l.department_id && String(l.department_id) === dept);
        });
    }

    if (search) {
        list = list.filter(l => {
            return (l.employee_name && l.employee_name.toLowerCase().includes(search)) ||
                   (l.leave_type && l.leave_type.toLowerCase().includes(search)) ||
                   (l.reason && l.reason.toLowerCase().includes(search)) ||
                   (l.department_name && l.department_name.toLowerCase().includes(search)) ||
                   (l.status && l.status.toLowerCase().includes(search));
        });
    }

    renderLeavesTable(list);
}

function resetLeavesFilters() {
    if (document.getElementById('hr-leave-search')) document.getElementById('hr-leave-search').value = '';
    if (document.getElementById('hr-leave-dept-filter')) document.getElementById('hr-leave-dept-filter').value = '';
    if (document.getElementById('hr-leave-status-filter')) document.getElementById('hr-leave-status-filter').value = 'pending';
    if (document.getElementById('hr-leave-type-filter')) document.getElementById('hr-leave-type-filter').value = 'all';
    if (document.getElementById('hr-leave-emp-filter')) document.getElementById('hr-leave-emp-filter').value = '';
    loadHrLeaves();
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
    const isAdmin = (currentUser.role === 'admin' || currentUser.role === 'super_admin' || (currentUser.can_manage_hr && currentUser.role !== 'hod'));
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

async function openApplyLeaveModal() {
    await populateHrEmployeeDropdowns();
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
            applyLoansClientFilters();
        } else {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading loan records')}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading loan records.</td></tr>`;
    }
}

function applyLoansClientFilters() {
    const search = (document.getElementById('hr-loan-search')?.value || '').toLowerCase().trim();
    const dept = (document.getElementById('hr-loan-dept-filter')?.value || '').toLowerCase().trim();
    const status = document.getElementById('hr-loan-status-filter')?.value || 'all';
    const type = document.getElementById('hr-loan-type-filter')?.value || 'all';
    const empId = document.getElementById('hr-loan-emp-filter')?.value || '';

    const resetBtn = document.getElementById('hr-loan-reset-filters-btn');
    if (resetBtn) {
        const isFiltered = !!(search || dept || empId || status !== 'pending' || type !== 'all');
        resetBtn.style.display = isFiltered ? 'inline-flex' : 'none';
    }

    let list = HrState.loans || [];

    if (dept) {
        list = list.filter(l => {
            const dName = (l.department_name || '').toLowerCase();
            return dName === dept || (l.department_id && String(l.department_id) === dept);
        });
    }

    if (search) {
        list = list.filter(l => {
            return (l.employee_name && l.employee_name.toLowerCase().includes(search)) ||
                   (l.request_type && l.request_type.toLowerCase().includes(search)) ||
                   (l.reason && l.reason.toLowerCase().includes(search)) ||
                   (l.department_name && l.department_name.toLowerCase().includes(search)) ||
                   (l.status && l.status.toLowerCase().includes(search)) ||
                   (l.amount && String(l.amount).includes(search));
        });
    }

    renderLoansTable(list);
}

function resetLoansFilters() {
    if (document.getElementById('hr-loan-search')) document.getElementById('hr-loan-search').value = '';
    if (document.getElementById('hr-loan-dept-filter')) document.getElementById('hr-loan-dept-filter').value = '';
    if (document.getElementById('hr-loan-status-filter')) document.getElementById('hr-loan-status-filter').value = 'pending';
    if (document.getElementById('hr-loan-type-filter')) document.getElementById('hr-loan-type-filter').value = 'all';
    if (document.getElementById('hr-loan-emp-filter')) document.getElementById('hr-loan-emp-filter').value = '';
    loadHrLoans();
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

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || (AppState.currentUser.can_manage_hr && AppState.currentUser.role !== 'hod'));
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
                    PKR ${Math.round(parseFloat(l.amount || 0)).toLocaleString('en-US')}
                </td>
                <td>
                    <div style="font-weight: 600; font-size: 12px;">${l.repayment_months} Mo @ PKR ${Math.round(parseFloat(l.monthly_deduction || 0)).toLocaleString('en-US')}/mo</div>
                    <div style="font-size: 10.5px; color: var(--primary);">Starts: ${escapeHtml(l.deduction_start_month)}</div>
                </td>
                <td>
                    <div style="font-size: 12px; font-weight: 700; color: #10b981;">Paid: PKR ${Math.round(parseFloat(l.paid_amount || 0)).toLocaleString('en-US')}</div>
                    <div style="font-size: 11px; font-weight: 700; color: #ef4444;">Rem: PKR ${Math.round(parseFloat(l.remaining_amount || 0)).toLocaleString('en-US')}</div>
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

async function openApplyLoanModal() {
    await populateHrEmployeeDropdowns();
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


// ================= 3. FUEL, TRAVEL, FOOD & INCENTIVE CLAIMS =================

async function loadHrClaims() {
    const tbody = document.getElementById('hr-claims-table-body');
    if (!tbody) return;

    const typeFilter = document.getElementById('hr-claims-type-filter')?.value || 'all';
    const monthFilter = document.getElementById('hr-claims-month-filter')?.value || '';
    const statusFilter = document.getElementById('hr-claims-status-filter')?.value || 'all';
    const empFilter = document.getElementById('hr-claims-emp-filter')?.value || '';

    let url = `api/hr.php?action=get_claims&claim_type=${encodeURIComponent(typeFilter)}&status=${encodeURIComponent(statusFilter)}`;
    if (monthFilter) url += `&month=${encodeURIComponent(monthFilter)}`;
    if (empFilter) url += `&employee_id=${encodeURIComponent(empFilter)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            HrState.claims = data.claims || [];
            
            // Update stats
            const stats = data.stats || {};
            const fuelEl = document.getElementById('hr-claims-total-fuel');
            const foodEl = document.getElementById('hr-claims-total-food');
            const incEl = document.getElementById('hr-claims-total-incentive');
            const pendEl = document.getElementById('hr-claims-total-pending');

            if (fuelEl) fuelEl.textContent = `PKR ${Math.round(stats.total_fuel_amount || 0).toLocaleString('en-US')}`;
            if (foodEl) foodEl.textContent = `PKR ${Math.round(stats.total_food_amount || 0).toLocaleString('en-US')}`;
            if (incEl) incEl.textContent = `PKR ${Math.round(stats.total_incentive_amount || 0).toLocaleString('en-US')}`;
            if (pendEl) pendEl.textContent = `${stats.pending_count || 0} Claim(s)`;

            applyClaimsClientFilters();
        } else {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading claims')}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading claims.</td></tr>`;
    }
}

function applyClaimsClientFilters() {
    const search = (document.getElementById('hr-claims-search')?.value || '').toLowerCase().trim();
    const dept = (document.getElementById('hr-claims-dept-filter')?.value || '').toLowerCase().trim();
    const type = document.getElementById('hr-claims-type-filter')?.value || 'all';
    const status = document.getElementById('hr-claims-status-filter')?.value || 'all';
    const month = document.getElementById('hr-claims-month-filter')?.value || '';
    const empId = document.getElementById('hr-claims-emp-filter')?.value || '';

    const currentMonth = new Date().toISOString().slice(0, 7);
    const resetBtn = document.getElementById('hr-claims-reset-filters-btn');
    if (resetBtn) {
        const isFiltered = !!(search || dept || empId || type !== 'all' || status !== 'approved' || (month && month !== currentMonth));
        resetBtn.style.display = isFiltered ? 'inline-flex' : 'none';
    }

    let list = HrState.claims || [];

    if (dept) {
        list = list.filter(c => {
            const dName = (c.department_name || '').toLowerCase();
            return dName === dept || (c.department_id && String(c.department_id) === dept);
        });
    }

    if (search) {
        list = list.filter(c => {
            return (c.employee_name && c.employee_name.toLowerCase().includes(search)) ||
                   (c.claim_type && c.claim_type.toLowerCase().includes(search)) ||
                   (c.description && c.description.toLowerCase().includes(search)) ||
                   (c.route_details && c.route_details.toLowerCase().includes(search)) ||
                   (c.receipt_number && c.receipt_number.toLowerCase().includes(search)) ||
                   (c.department_name && c.department_name.toLowerCase().includes(search)) ||
                   (c.status && c.status.toLowerCase().includes(search)) ||
                   (c.amount && String(c.amount).includes(search));
        });
    }

    renderHrClaimsTable(list);
}

function resetClaimsFilters() {
    const currentMonth = new Date().toISOString().slice(0, 7);
    if (document.getElementById('hr-claims-search')) document.getElementById('hr-claims-search').value = '';
    if (document.getElementById('hr-claims-dept-filter')) document.getElementById('hr-claims-dept-filter').value = '';
    if (document.getElementById('hr-claims-type-filter')) document.getElementById('hr-claims-type-filter').value = 'all';
    if (document.getElementById('hr-claims-status-filter')) document.getElementById('hr-claims-status-filter').value = 'approved';
    if (document.getElementById('hr-claims-month-filter')) document.getElementById('hr-claims-month-filter').value = currentMonth;
    if (document.getElementById('hr-claims-emp-filter')) document.getElementById('hr-claims-emp-filter').value = '';
    loadHrClaims();
}

function renderHrClaimsTable(claims) {
    const tbody = document.getElementById('hr-claims-table-body');
    const countLabel = document.getElementById('hr-claims-count-label');
    if (!tbody) return;

    if (countLabel) {
        countLabel.textContent = `Showing ${claims.length} claim(s)`;
    }

    if (!claims || claims.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <div style="font-size: 32px; margin-bottom: 8px;">⛽</div>
                    <div style="font-weight: 700; font-size: 14px;">No claims or allowances found</div>
                    <div style="font-size: 12px; margin-top: 4px;">Click "+ Add Allowance / Claim" above to record a new entry.</div>
                </td>
            </tr>
        `;
        return;
    }

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hr' || (AppState.currentUser.can_manage_hr && AppState.currentUser.role !== 'hod'));

    const typeBadges = {
        fuel: { label: '⛽ Fuel / Mileage', bg: 'rgba(14, 165, 233, 0.12)', color: '#0284c7' },
        travel: { label: '✈️ Travel / Trip', bg: 'rgba(59, 130, 246, 0.12)', color: '#2563eb' },
        mobile: { label: '📱 Mobile / Net', bg: 'rgba(107, 114, 128, 0.12)', color: '#4b5563' },
        food_bills: { label: '🍲 Food & Meals', bg: 'rgba(16, 185, 129, 0.12)', color: '#059669' },
        incentive: { label: '🏆 Incentive', bg: 'rgba(139, 92, 246, 0.12)', color: '#7c3aed' },
        bonus: { label: '🎁 Special Bonus', bg: 'rgba(236, 72, 153, 0.12)', color: '#db2777' },
        other: { label: '📝 Other', bg: 'rgba(107, 114, 128, 0.12)', color: '#6b7280' }
    };

    const statusBadges = {
        pending: { label: '⏳ Pending', bg: 'rgba(245, 158, 11, 0.12)', color: '#d97706' },
        approved: { label: '✅ Approved', bg: 'rgba(16, 185, 129, 0.12)', color: '#059669' },
        paid: { label: '💵 Paid', bg: 'rgba(59, 130, 246, 0.12)', color: '#2563eb' },
        rejected: { label: '❌ Rejected', bg: 'rgba(239, 68, 68, 0.12)', color: '#ef4444' }
    };

    let html = '';
    claims.forEach(c => {
        const typeBadge = typeBadges[c.claim_type] || typeBadges.other;
        const statBadge = statusBadges[c.status] || statusBadges.pending;

        const avatarInitial = c.employee_name ? c.employee_name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = c.avatar
            ? `<img src="${escapeHtml(c.avatar)}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;" alt="${escapeHtml(c.employee_name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width:28px;height:28px;font-size:12px;\\'>${escapeHtml(avatarInitial)}</div>';">`
            : `<div class="user-avatar" style="width: 28px; height: 28px; font-size: 12px;">${escapeHtml(avatarInitial)}</div>`;

        let actionHtml = '';
        if (isAdmin || (AppState.currentUser && AppState.currentUser.role === 'hod')) {
            if (c.status === 'pending') {
                actionHtml = `
                    <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: #059669; border-color: rgba(16, 185, 129, 0.4);" onclick="handleUpdateClaimStatus(${c.id}, 'approved')" title="Approve & Apply to Payroll">✅ Approve</button>
                    <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: #dc2626; border-color: rgba(220, 38, 38, 0.4);" onclick="handleUpdateClaimStatus(${c.id}, 'rejected')" title="Reject Claim">❌</button>
                `;
            } else if (c.status === 'approved') {
                actionHtml = `
                    <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; color: #2563eb;" onclick="handleUpdateClaimStatus(${c.id}, 'paid')" title="Mark as Paid">💵 Mark Paid</button>
                    <button type="button" class="btn btn-outline" style="padding: 3px 7px; font-size: 11px; color: #ef4444;" onclick="handleDeleteClaim(${c.id})" title="Delete Claim">🗑️</button>
                `;
            } else {
                actionHtml = `
                    <button type="button" class="btn btn-outline" style="padding: 3px 7px; font-size: 11px; color: #ef4444;" onclick="handleDeleteClaim(${c.id})" title="Delete Claim">🗑️</button>
                `;
            }
        } else {
            actionHtml = `<span style="font-size: 11px; color: var(--text-muted);">${statBadge.label}</span>`;
        }

        html += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${avatarHtml}
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: var(--text-main); display: flex; align-items: center; gap: 5px;">
                                ${escapeHtml(c.employee_name)}
                                <span style="font-size: 10px; background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 1px 4px; border-radius: 4px; font-family: monospace;">${escapeHtml(c.emp_code || '')}</span>
                            </div>
                            <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(c.designation || 'Staff')} • ${escapeHtml(c.department_name || 'General')}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: ${typeBadge.bg}; color: ${typeBadge.color};">
                        ${typeBadge.label}
                    </span>
                    ${c.receipt_no ? `<div style="font-size: 10.5px; color: var(--text-muted); margin-top: 3px;">📄 ${escapeHtml(c.receipt_no)}</div>` : ''}
                </td>
                <td style="font-weight: 800; font-size: 13.5px; color: #059669;">
                    PKR ${Math.round(parseFloat(c.amount || 0)).toLocaleString('en-US')}
                </td>
                <td>
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-main);">${escapeHtml(c.claim_date)}</div>
                    <div style="font-size: 11px; color: var(--primary); font-weight: 600;">Salary: ${escapeHtml(c.salary_month)}</div>
                </td>
                <td>
                    <div style="font-size: 12px; color: var(--text-main); line-height: 1.4;">${escapeHtml(c.reason)}</div>
                    ${c.action_by_name ? `<div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">Action: ${escapeHtml(c.action_by_name)}</div>` : ''}
                </td>
                <td style="text-align: center;">
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: ${statBadge.bg}; color: ${statBadge.color};">
                        ${statBadge.label}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap;">
                        ${actionHtml}
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

async function openAddClaimModal() {
    await populateHrEmployeeDropdowns();
    const today = new Date().toISOString().slice(0, 10);
    const month = today.slice(0, 7);

    const dateInput = document.getElementById('hr-claim-form-date');
    const monthInput = document.getElementById('hr-claim-form-month');
    const amountInput = document.getElementById('hr-claim-form-amount');
    const receiptInput = document.getElementById('hr-claim-form-receipt');
    const reasonInput = document.getElementById('hr-claim-form-reason');

    if (dateInput) dateInput.value = today;
    if (monthInput) monthInput.value = month;
    if (amountInput) amountInput.value = '';
    if (receiptInput) receiptInput.value = '';
    if (reasonInput) reasonInput.value = '';

    openModal('hr-add-claim-modal');
}

function updateClaimFormSalaryMonth() {
    const dateVal = document.getElementById('hr-claim-form-date')?.value;
    if (dateVal) {
        const monthInput = document.getElementById('hr-claim-form-month');
        if (monthInput) monthInput.value = dateVal.slice(0, 7);
    }
}

async function handleSaveClaimSubmit(e) {
    if (e) e.preventDefault();

    const empId = document.getElementById('hr-claim-form-emp-id')?.value;
    const claimType = document.getElementById('hr-claim-form-type')?.value || 'fuel';
    const amount = parseFloat(document.getElementById('hr-claim-form-amount')?.value) || 0;
    const claimDate = document.getElementById('hr-claim-form-date')?.value;
    const salaryMonth = document.getElementById('hr-claim-form-month')?.value;
    const receiptNo = document.getElementById('hr-claim-form-receipt')?.value.trim();
    const reason = document.getElementById('hr-claim-form-reason')?.value.trim();
    const autoApprove = document.getElementById('hr-claim-form-auto-approve')?.checked ? 1 : 0;

    if (!empId || amount <= 0 || !reason) {
        showToast("Please enter employee, valid amount, and purpose description.", "error");
        return;
    }

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_claim',
                employee_id: empId,
                claim_type: claimType,
                amount: amount,
                claim_date: claimDate,
                salary_month: salaryMonth,
                receipt_no: receiptNo,
                reason: reason,
                auto_approve: autoApprove
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || "Allowance / Claim recorded successfully.", "success");
            closeModal('hr-add-claim-modal');
            await loadHrClaims();
            // If payroll tab is active, refresh payroll too
            if (typeof loadHrPayroll === 'function') loadHrPayroll();
        } else {
            showToast(data.message || "Failed to record claim.", "error");
        }
    } catch (err) {
        showToast("Network error submitting claim.", "error");
    }
}

async function handleUpdateClaimStatus(claimId, status) {
    let adminNotes = '';
    if (status === 'rejected') {
        adminNotes = prompt("Enter reason for rejection (optional):") || '';
    }

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_claim_status',
                claim_id: claimId,
                status: status,
                admin_notes: adminNotes
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || `Claim status updated to ${status}.`, "success");
            await loadHrClaims();
            if (typeof loadHrPayroll === 'function') loadHrPayroll();
        } else {
            showToast(data.message || "Failed to update claim status.", "error");
        }
    } catch (err) {
        showToast("Network error updating claim status.", "error");
    }
}

async function handleDeleteClaim(claimId) {
    if (!confirm("Are you sure you want to delete this allowance / claim entry?")) return;

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_claim', claim_id: claimId })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || "Claim entry deleted.", "info");
            await loadHrClaims();
            if (typeof loadHrPayroll === 'function') loadHrPayroll();
        } else {
            showToast(data.message || "Failed to delete claim.", "error");
        }
    } catch (err) {
        showToast("Network error deleting claim.", "error");
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
            applyNoticesClientFilters();
        } else {
            feed.innerHTML = `<div style="text-align: center; color: #ef4444; padding: 30px; grid-column: 1 / -1;">${escapeHtml(data.message || 'Error loading notices')}</div>`;
        }
    } catch (err) {
        feed.innerHTML = `<div style="text-align: center; color: #ef4444; padding: 30px; grid-column: 1 / -1;">Network error loading notices.</div>`;
    }
}

function applyNoticesClientFilters() {
    const search = (document.getElementById('hr-notice-search')?.value || '').toLowerCase().trim();
    const dept = (document.getElementById('hr-notice-dept-filter')?.value || '').toLowerCase().trim();
    const priority = document.getElementById('hr-notice-priority-filter')?.value || 'all';

    const resetBtn = document.getElementById('hr-notice-reset-filters-btn');
    if (resetBtn) {
        const isFiltered = !!(search || dept || priority !== 'all');
        resetBtn.style.display = isFiltered ? 'inline-flex' : 'none';
    }

    let list = HrState.notices || [];

    if (priority && priority !== 'all') {
        list = list.filter(n => (n.priority || '').toLowerCase() === priority.toLowerCase());
    }

    if (dept) {
        list = list.filter(n => {
            const dName = (n.target_department_name || '').toLowerCase();
            return !n.target_department_id || dName === dept || String(n.target_department_id) === dept;
        });
    }

    if (search) {
        list = list.filter(n => {
            return (n.title && n.title.toLowerCase().includes(search)) ||
                   (n.message && n.message.toLowerCase().includes(search)) ||
                   (n.posted_by_name && n.posted_by_name.toLowerCase().includes(search));
        });
    }

    const countLabel = document.getElementById('hr-notices-count-label');
    if (countLabel) {
        countLabel.textContent = `Showing ${list.length} notice(s)`;
    }

    renderNoticesFeed(list);
}

function resetNoticesFilters() {
    if (document.getElementById('hr-notice-search')) document.getElementById('hr-notice-search').value = '';
    if (document.getElementById('hr-notice-dept-filter')) document.getElementById('hr-notice-dept-filter').value = '';
    if (document.getElementById('hr-notice-priority-filter')) document.getElementById('hr-notice-priority-filter').value = 'all';
    applyNoticesClientFilters();
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

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || (AppState.currentUser.can_manage_hr && AppState.currentUser.role !== 'hod'));

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


// ================= 4. DISCIPLINARY FINES & PENALTIES =================

async function loadHrFines() {
    const tbody = document.getElementById('hr-fines-table-body');
    if (!tbody) return;

    const monthFilter = document.getElementById('hr-fines-month-filter')?.value || '';
    const statusFilter = document.getElementById('hr-fines-status-filter')?.value || 'all';
    const catFilter = document.getElementById('hr-fines-cat-filter')?.value || 'all';
    const empFilter = document.getElementById('hr-fines-emp-filter')?.value || '';

    let url = `api/hr.php?action=get_fines&status=${encodeURIComponent(statusFilter)}&fine_category=${encodeURIComponent(catFilter)}`;
    if (monthFilter) url += `&month=${encodeURIComponent(monthFilter)}`;
    if (empFilter) url += `&employee_id=${encodeURIComponent(empFilter)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            HrState.fines = data.fines || [];
            
            // Update stats
            const stats = data.stats || {};
            const appliedAmtEl = document.getElementById('hr-fines-total-applied-amount');
            const appliedCntEl = document.getElementById('hr-fines-total-applied-count');
            const waivedAmtEl = document.getElementById('hr-fines-total-waived-amount');
            const waivedCntEl = document.getElementById('hr-fines-total-waived-count');
            const totalCntEl = document.getElementById('hr-fines-total-count');

            if (appliedAmtEl) appliedAmtEl.textContent = `PKR ${Math.round(parseFloat(stats.total_applied_amount || 0)).toLocaleString('en-US')}`;
            if (appliedCntEl) appliedCntEl.textContent = `${stats.applied_count || 0} penalties applied to payroll`;
            if (waivedAmtEl) waivedAmtEl.textContent = `PKR ${Math.round(parseFloat(stats.total_waived_amount || 0)).toLocaleString('en-US')}`;
            if (waivedCntEl) waivedCntEl.textContent = `${stats.waived_count || 0} forgiven penalties`;
            if (totalCntEl) totalCntEl.textContent = `${stats.total_fines || 0} Records`;

            applyFinesClientFilters();
        } else {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading fines')}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading fines.</td></tr>`;
    }
}

function applyFinesClientFilters() {
    const search = (document.getElementById('hr-fines-search')?.value || '').toLowerCase().trim();
    const dept = (document.getElementById('hr-fines-dept-filter')?.value || '').toLowerCase().trim();
    const status = document.getElementById('hr-fines-status-filter')?.value || 'all';
    const cat = document.getElementById('hr-fines-cat-filter')?.value || 'all';
    const month = document.getElementById('hr-fines-month-filter')?.value || '';
    const empId = document.getElementById('hr-fines-emp-filter')?.value || '';

    const currentMonth = new Date().toISOString().slice(0, 7);
    const resetBtn = document.getElementById('hr-fines-reset-filters-btn');
    if (resetBtn) {
        const isFiltered = !!(search || dept || empId || status !== 'applied' || cat !== 'all' || (month && month !== currentMonth));
        resetBtn.style.display = isFiltered ? 'inline-flex' : 'none';
    }

    let list = HrState.fines || [];

    if (dept) {
        list = list.filter(f => {
            const dName = (f.department_name || '').toLowerCase();
            return dName === dept || (f.department_id && String(f.department_id) === dept);
        });
    }

    if (search) {
        list = list.filter(f => {
            return (f.employee_name && f.employee_name.toLowerCase().includes(search)) ||
                   (f.reason && f.reason.toLowerCase().includes(search)) ||
                   (f.fine_category && f.fine_category.toLowerCase().includes(search)) ||
                   (f.department_name && f.department_name.toLowerCase().includes(search)) ||
                   (f.status && f.status.toLowerCase().includes(search)) ||
                   (f.amount && String(f.amount).includes(search));
        });
    }

    renderFinesTable(list);
}

function resetFinesFilters() {
    const currentMonth = new Date().toISOString().slice(0, 7);
    if (document.getElementById('hr-fines-search')) document.getElementById('hr-fines-search').value = '';
    if (document.getElementById('hr-fines-dept-filter')) document.getElementById('hr-fines-dept-filter').value = '';
    if (document.getElementById('hr-fines-status-filter')) document.getElementById('hr-fines-status-filter').value = 'applied';
    if (document.getElementById('hr-fines-cat-filter')) document.getElementById('hr-fines-cat-filter').value = 'all';
    if (document.getElementById('hr-fines-month-filter')) document.getElementById('hr-fines-month-filter').value = currentMonth;
    if (document.getElementById('hr-fines-emp-filter')) document.getElementById('hr-fines-emp-filter').value = '';
    loadHrFines();
}

function renderFinesTable(fines) {
    const tbody = document.getElementById('hr-fines-table-body');
    const countLabel = document.getElementById('hr-fines-count-label');
    if (!tbody) return;

    if (countLabel) {
        countLabel.textContent = `Showing ${fines.length} fine record(s)`;
    }

    if (!fines || fines.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <div style="font-size: 32px; margin-bottom: 8px;">✨</div>
                    <div style="font-weight: 700; font-size: 14px;">No Disciplinary Fines Recorded</div>
                    <div style="font-size: 12px; margin-top: 4px;">Click "+ Issue Fine" above to log a penalty or violation.</div>
                </td>
            </tr>
        `;
        return;
    }

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || (AppState.currentUser.can_manage_hr && AppState.currentUser.role !== 'hod'));

    const categoryBadges = {
        late_arrival: { label: '⏱️ Late Arrival', bg: 'rgba(245, 158, 11, 0.12)', color: '#d97706' },
        unauthorized_absence: { label: '🚫 Unauthorized Absence', bg: 'rgba(239, 68, 68, 0.12)', color: '#ef4444' },
        sop_violation: { label: '⚠️ Policy / SOP Breach', bg: 'rgba(139, 92, 246, 0.12)', color: '#8b5cf6' },
        negligence: { label: '⚠️ Negligence / Damage', bg: 'rgba(249, 115, 22, 0.12)', color: '#ea580c' },
        misconduct: { label: '🛑 Misconduct', bg: 'rgba(220, 38, 38, 0.12)', color: '#dc2626' },
        other: { label: '📝 Disciplinary Note', bg: 'rgba(107, 114, 128, 0.12)', color: '#6b7280' }
    };

    let html = '';
    fines.forEach(f => {
        const catBadge = categoryBadges[f.fine_category] || categoryBadges.sop_violation;
        const isWaived = f.status === 'waived';

        const avatarInitial = f.employee_name ? f.employee_name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = f.employee_avatar
            ? `<img src="${escapeHtml(f.employee_avatar)}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;" alt="${escapeHtml(f.employee_name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width:28px;height:28px;font-size:12px;\\'>${escapeHtml(avatarInitial)}</div>';">`
            : `<div class="user-avatar" style="width: 28px; height: 28px; font-size: 12px;">${escapeHtml(avatarInitial)}</div>`;

        let actionHtml = '';
        if (isAdmin) {
            if (isWaived) {
                actionHtml = `
                    <div style="display: flex; gap: 4px; justify-content: center;">
                        <button type="button" class="btn btn-outline" style="padding: 3px 6px; font-size: 11px; color: #ef4444;" onclick="handleFineAction(${f.id}, 'applied')" title="Re-apply Fine to Payroll">
                            Re-Apply
                        </button>
                        <button type="button" class="btn-icon-del" style="padding: 3px 6px;" onclick="handleDeleteFine(${f.id})" title="Delete fine record">🗑️</button>
                    </div>
                `;
            } else {
                actionHtml = `
                    <div style="display: flex; gap: 4px; justify-content: center;">
                        <button type="button" class="btn btn-outline" style="padding: 3px 6px; font-size: 11px; color: #10b981;" onclick="handleFineAction(${f.id}, 'waived')" title="Waive / Forgive Fine">
                            Waive
                        </button>
                        <button type="button" class="btn-icon-del" style="padding: 3px 6px;" onclick="handleDeleteFine(${f.id})" title="Delete fine record">🗑️</button>
                    </div>
                `;
            }
        } else {
            actionHtml = `<span style="color: var(--text-muted); font-size: 11px;">View Only</span>`;
        }

        html += `
            <tr style="${isWaived ? 'opacity: 0.65; background: rgba(107, 114, 128, 0.03);' : ''}">
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${avatarHtml}
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: var(--text-main);">${escapeHtml(f.employee_name)}</div>
                            <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(f.employee_designation || 'Staff')} • ${escapeHtml(f.department_name || 'General')}</div>
                        </div>
                    </div>
                </td>
                <td style="font-size: 12px; font-weight: 600; color: var(--text-main);">
                    ${escapeHtml(f.fine_date)}
                </td>
                <td>
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: ${catBadge.bg}; color: ${catBadge.color};">
                        ${catBadge.label}
                    </span>
                </td>
                <td>
                    <div style="font-size: 12.5px; color: var(--text-main); font-weight: 500; line-height: 1.4;">${escapeHtml(f.reason)}</div>
                    ${f.waived_reason ? `<div style="font-size: 11px; color: #10b981; margin-top: 2px;"><b>Waived Note:</b> ${escapeHtml(f.waived_reason)}</div>` : ''}
                    <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 3px;">Issued by ${escapeHtml(f.issued_by_name || 'HR')}</div>
                </td>
                <td style="font-weight: 700; font-size: 12px; color: var(--primary);">
                    ${escapeHtml(f.salary_month)}
                </td>
                <td style="font-weight: 800; font-size: 13.5px; color: ${isWaived ? '#6b7280; text-decoration: line-through;' : '#ef4444;'}">
                    PKR ${Math.round(parseFloat(f.amount || 0)).toLocaleString('en-US')}
                </td>
                <td style="text-align: center;">
                    ${isWaived 
                        ? `<span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #10b981;">Waived</span>`
                        : `<span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: rgba(239, 68, 68, 0.12); color: #ef4444;">Applied</span>`
                    }
                </td>
                <td style="text-align: center;">
                    ${actionHtml}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

async function openIssueFineModal() {
    await populateHrEmployeeDropdowns();
    const today = new Date().toISOString().slice(0, 10);
    const currentMonth = today.slice(0, 7);

    const dateInput = document.getElementById('hr-fine-form-date');
    const monthInput = document.getElementById('hr-fine-form-month');
    const amountInput = document.getElementById('hr-fine-form-amount');
    const reasonInput = document.getElementById('hr-fine-form-reason');
    const catInput = document.getElementById('hr-fine-form-category');

    if (dateInput) dateInput.value = today;
    if (monthInput) monthInput.value = currentMonth;
    if (amountInput) amountInput.value = '';
    if (reasonInput) reasonInput.value = '';
    if (catInput) catInput.value = 'sop_violation';

    openModal('hr-issue-fine-modal');
}

function updateFineFormSalaryMonth() {
    const dateVal = document.getElementById('hr-fine-form-date')?.value;
    const monthInput = document.getElementById('hr-fine-form-month');
    if (dateVal && monthInput) {
        monthInput.value = dateVal.slice(0, 7);
    }
}

async function handleIssueFineSubmit(e) {
    if (e) e.preventDefault();

    const empId = document.getElementById('hr-fine-form-emp-id')?.value;
    const fineDate = document.getElementById('hr-fine-form-date')?.value;
    const salaryMonth = document.getElementById('hr-fine-form-month')?.value;
    const fineCategory = document.getElementById('hr-fine-form-category')?.value;
    const amount = document.getElementById('hr-fine-form-amount')?.value;
    const reason = document.getElementById('hr-fine-form-reason')?.value.trim();

    if (!empId) {
        showToast("Please select an employee.", "error");
        return;
    }
    if (!amount || parseFloat(amount) <= 0) {
        showToast("Please enter a valid fine amount.", "error");
        return;
    }
    if (!reason) {
        showToast("Please provide incident details / violation reason.", "error");
        return;
    }

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_fine',
                employee_id: empId,
                fine_date: fineDate,
                salary_month: salaryMonth,
                fine_category: fineCategory,
                amount: amount,
                reason: reason
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast("Disciplinary fine issued successfully.", "success");
            closeModal('hr-issue-fine-modal');
            await loadHrFines();
            if (document.getElementById('tab-hr-payroll')?.classList.contains('active')) {
                await loadHrPayroll();
            }
        } else {
            showToast(data.message || "Failed to issue fine.", "error");
        }
    } catch (err) {
        showToast("Network error issuing fine.", "error");
    }
}

async function handleFineAction(fineId, status) {
    let waivedReason = '';
    if (status === 'waived') {
        waivedReason = prompt("Enter reason for waiving / forgiving this fine (optional):", "Waived upon managerial review") || '';
    }

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_fine_status',
                fine_id: fineId,
                status: status,
                waived_reason: waivedReason
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || "Fine status updated.", "success");
            await loadHrFines();
            if (document.getElementById('tab-hr-payroll')?.classList.contains('active')) {
                await loadHrPayroll();
            }
        } else {
            showToast(data.message || "Failed to update fine.", "error");
        }
    } catch (err) {
        showToast("Network error updating fine status.", "error");
    }
}

async function handleDeleteFine(fineId) {
    if (!confirm("Are you sure you want to delete this fine record?")) return;

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_fine', fine_id: fineId })
        });
        const data = await res.json();

        if (data.success) {
            showToast("Fine record removed.", "info");
            await loadHrFines();
            if (document.getElementById('tab-hr-payroll')?.classList.contains('active')) {
                await loadHrPayroll();
            }
        } else {
            showToast(data.message || "Failed to delete fine record.", "error");
        }
    } catch (err) {
        showToast("Network error deleting fine.", "error");
    }
}


// ================= 5. MONTHLY PAYROLL & SALARIES =================

async function loadHrPayroll() {
    const tbody = document.getElementById('hr-payroll-table-body');
    if (!tbody) return;

    const currentYearMonth = new Date().toISOString().slice(0, 7);
    const monthInput = document.getElementById('hr-payroll-month');
    if (monthInput && !monthInput.value) {
        monthInput.value = HrState.selectedMonth || currentYearMonth;
    }
    const month = monthInput?.value || HrState.selectedMonth || currentYearMonth;

    try {
        const res = await fetch(`api/hr.php?action=get_payroll&month=${encodeURIComponent(month)}`);
        const data = await res.json();

        if (data.success) {
            HrState.payroll = data.payroll || [];
            populatePayrollDeptFilter();
            applyPayrollFilters();
        } else {
            tbody.innerHTML = `<tr><td colspan="14" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading payroll')}</td></tr>`;
            updatePayrollSummaryStats([]);
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="14" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading payroll records.</td></tr>`;
        updatePayrollSummaryStats([]);
    }
}

function populatePayrollDeptFilter() {
    const filterDept = document.getElementById('hr-payroll-filter-dept');
    if (!filterDept) return;

    const currentVal = filterDept.value;
    filterDept.innerHTML = '<option value="">🏢 All Departments</option>';

    // Collect distinct departments from HrState.payroll
    const depts = new Map();
    (HrState.payroll || []).forEach(p => {
        if (p.department_name) {
            depts.set(p.department_name, (depts.get(p.department_name) || 0) + 1);
        }
    });

    depts.forEach((count, name) => {
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = `🏢 ${name} (${count})`;
        filterDept.appendChild(opt);
    });

    if (currentVal) filterDept.value = currentVal;
}

function resetPayrollFilters() {
    if (document.getElementById('hr-payroll-search')) document.getElementById('hr-payroll-search').value = '';
    if (document.getElementById('hr-payroll-filter-dept')) document.getElementById('hr-payroll-filter-dept').value = '';
    if (document.getElementById('hr-payroll-filter-status')) document.getElementById('hr-payroll-filter-status').value = '';
    if (document.getElementById('hr-payroll-filter-bank')) document.getElementById('hr-payroll-filter-bank').value = '';
    applyPayrollFilters();
}

function updatePayrollSummaryStats(list) {
    let totalGross = 0;
    let totalDeductions = 0;
    let totalNet = 0;

    (list || []).forEach(p => {
        const gross = parseFloat(p.basic_salary || 0) + parseFloat(p.fuel_allowance || 0) + parseFloat(p.incentive || 0) + parseFloat(p.bonus || 0);
        const deductions = parseFloat(p.advance_salary || 0) + parseFloat(p.loan_deduction || 0) + parseFloat(p.food_bills || 0) + parseFloat(p.fines || 0) + parseFloat(p.wht_amount || 0) + parseFloat(p.deductions || 0) + parseFloat(p.unpaid_leave_deduction || 0);
        const net = parseFloat(p.net_salary || 0);

        totalGross += gross;
        totalDeductions += deductions;
        totalNet += net;
    });

    const empCountEl = document.getElementById('payroll-stat-total-emp');
    const grossEl = document.getElementById('payroll-stat-total-gross');
    const dedEl = document.getElementById('payroll-stat-total-deductions');
    const netEl = document.getElementById('payroll-stat-total-net');

    if (empCountEl) empCountEl.textContent = (list || []).length;
    if (grossEl) grossEl.textContent = `PKR ${Math.round(totalGross).toLocaleString('en-US')}`;
    if (dedEl) dedEl.textContent = `PKR ${Math.round(totalDeductions).toLocaleString('en-US')}`;
    if (netEl) netEl.textContent = `PKR ${Math.round(totalNet).toLocaleString('en-US')}`;
}

function applyPayrollFilters() {
    const search = (document.getElementById('hr-payroll-search')?.value || '').toLowerCase().trim();
    const deptName = document.getElementById('hr-payroll-filter-dept')?.value;
    const paymentStatus = document.getElementById('hr-payroll-filter-status')?.value;
    const bankFilter = document.getElementById('hr-payroll-filter-bank')?.value;

    // Toggle reset button visibility
    const resetBtn = document.getElementById('hr-payroll-reset-filters-btn');
    if (resetBtn) {
        const isFiltered = !!(search || deptName || paymentStatus || bankFilter);
        resetBtn.style.display = isFiltered ? 'inline-flex' : 'none';
    }

    let list = HrState.payroll || [];

    if (deptName) {
        list = list.filter(p => (p.department_name || '').toLowerCase() === deptName.toLowerCase());
    }

    if (paymentStatus) {
        list = list.filter(p => (p.payment_status || 'draft') === paymentStatus);
    }

    if (bankFilter) {
        if (bankFilter === 'Cash') {
            list = list.filter(p => {
                const b = (p.bank_name || '').toLowerCase();
                const acc = (p.bank_account_no || '').toLowerCase();
                return b === 'cash' || acc.includes('cash');
            });
        } else {
            list = list.filter(p => (p.bank_name || '').toLowerCase().includes(bankFilter.toLowerCase()));
        }
    }

    if (search) {
        list = list.filter(p => 
            (p.employee_name || '').toLowerCase().includes(search) ||
            (p.emp_code || '').toLowerCase().includes(search) ||
            (p.cnic_no || '').toLowerCase().includes(search) ||
            (p.father_husband_name || '').toLowerCase().includes(search) ||
            (p.designation || '').toLowerCase().includes(search) ||
            (p.department_name || '').toLowerCase().includes(search) ||
            (p.bank_name || '').toLowerCase().includes(search) ||
            (p.bank_account_no || '').toLowerCase().includes(search) ||
            (p.form_no || '').toLowerCase().includes(search)
        );
    }

    updatePayrollSummaryStats(list);
    renderHrPayrollTable(list);
}

function renderHrPayrollTable(payroll) {
    const tbody = document.getElementById('hr-payroll-table-body');
    if (!tbody) return;

    if (!payroll || payroll.length === 0) {
        tbody.innerHTML = `<tr><td colspan="14" style="text-align: center; padding: 30px; color: var(--text-muted);">No staff records found for selected month.</td></tr>`;
        return;
    }

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hr' || (AppState.currentUser.can_manage_hr && AppState.currentUser.role !== 'hod'));

    const statusBadges = {
        draft: { label: 'Draft', bg: 'rgba(107, 114, 128, 0.12)', color: '#6b7280' },
        approved: { label: 'Approved', bg: 'rgba(59, 130, 246, 0.12)', color: '#2563eb' },
        paid: { label: 'Paid ✅', bg: 'rgba(16, 185, 129, 0.15)', color: '#059669' }
    };

    let html = '';
    payroll.forEach(p => {
        const stBadge = statusBadges[p.payment_status] || statusBadges.draft;
        const avatarInitial = p.employee_name ? p.employee_name.charAt(0).toUpperCase() : '👤';
        const avatarHtml = p.avatar
            ? `<img src="${escapeHtml(p.avatar)}" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" alt="${escapeHtml(p.employee_name)}" onerror="this.outerHTML='<div class=\\'user-avatar\\' style=\\'width:30px;height:30px;font-size:12px;\\'>${escapeHtml(avatarInitial)}</div>';">`
            : `<div class="user-avatar" style="width: 30px; height: 30px; font-size: 12px; flex-shrink: 0;">${escapeHtml(avatarInitial)}</div>`;

        const empCode = p.emp_code || `DP-${String(p.employee_id).padStart(3, '0')}`;

        const paidLeaves = parseFloat(p.approved_leaves || 0);
        const unpaidLeaves = parseFloat(p.unpaid_leaves || 0);
        const unpaidDeduction = parseFloat(p.unpaid_leave_deduction || 0);

        let leavesHtml = '<span style="color: var(--text-muted); font-size: 11.5px;">0 Leaves</span>';
        if (paidLeaves > 0 || unpaidLeaves > 0) {
            let unpaidLabel = '';
            if (unpaidLeaves > 0) {
                if (unpaidDeduction > 0) {
                    unpaidLabel = `<div style="font-weight: 700; color: #dc2626; font-size: 11px;" title="Unpaid Leave Deducted: PKR ${Math.round(unpaidDeduction).toLocaleString('en-US')}">⚠️ ${unpaidLeaves} Unpaid (-${Math.round(unpaidDeduction).toLocaleString('en-US')})</div>`;
                } else {
                    unpaidLabel = `<div style="font-weight: 700; color: #059669; font-size: 11px;" title="Unpaid Leave Waived / Excused by HR (0 PKR deduction)">⚠️ ${unpaidLeaves} Unpaid (Excused)</div>`;
                }
            }
            leavesHtml = `
                ${paidLeaves > 0 ? `<div style="font-weight: 700; color: #059669; font-size: 11.5px;">🌴 ${paidLeaves} Paid</div>` : ''}
                ${unpaidLabel}
            `;
        }

        html += `
            <tr ${isAdmin ? `onclick="openEditPayrollModalById(${p.employee_id})"` : ''} style="${isAdmin ? 'cursor: pointer;' : ''}" title="${isAdmin ? 'Click row to adjust payroll & deductions' : ''}">
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        ${avatarHtml}
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: var(--text-main);">${escapeHtml(p.employee_name)}</div>
                            <div style="font-size: 10.5px; color: var(--text-muted); font-family: monospace; font-weight: 600;">${escapeHtml(empCode)}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="font-size: 12.5px; font-weight: 600; color: var(--text-main);">${escapeHtml(p.designation || 'Staff')}</div>
                    <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(p.department_name || 'General')}</div>
                </td>
                <td>
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-main);">${escapeHtml(p.father_husband_name || '--')}</div>
                    <div style="font-size: 10.5px; color: var(--text-muted); font-family: monospace;">${escapeHtml(p.cnic_no || 'No CNIC')}</div>
                </td>
                <td style="text-align: center;">
                    ${leavesHtml}
                </td>
                <td style="font-size: 12.5px; font-weight: 700; color: var(--text-main);">
                    PKR ${Math.round(parseFloat(p.basic_salary || 0)).toLocaleString('en-US')}
                </td>
                <td style="font-size: 12px; font-weight: 600; color: #0284c7;">
                    ${parseFloat(p.fuel_allowance || 0) > 0 ? `+${Math.round(parseFloat(p.fuel_allowance)).toLocaleString('en-US')}` : '--'}
                </td>
                <td style="font-size: 12px; font-weight: 600; color: #059669;">
                    ${(parseFloat(p.incentive || 0) + parseFloat(p.bonus || 0)) > 0 ? `+${Math.round(parseFloat(p.incentive || 0) + parseFloat(p.bonus || 0)).toLocaleString('en-US')}` : '--'}
                    ${parseFloat(p.bonus || 0) > 0 ? `<div style="font-size: 10px; color: #10b981;">Bonus: +${Math.round(parseFloat(p.bonus)).toLocaleString('en-US')}</div>` : ''}
                </td>
                <td style="font-size: 12px; font-weight: 600; color: #dc2626;">
                    ${(parseFloat(p.advance_salary || 0) + parseFloat(p.loan_deduction || 0) + parseFloat(p.food_bills || 0) + parseFloat(p.deductions || 0)) > 0 ? `-${Math.round(parseFloat(p.advance_salary || 0) + parseFloat(p.loan_deduction || 0) + parseFloat(p.food_bills || 0) + parseFloat(p.deductions || 0)).toLocaleString('en-US')}` : '--'}
                    ${parseFloat(p.advance_salary || 0) > 0 ? `<div style="font-size: 10px; color: #b91c1c;">Adv: -${Math.round(parseFloat(p.advance_salary)).toLocaleString('en-US')}</div>` : ''}
                    ${parseFloat(p.loan_deduction || 0) > 0 ? `<div style="font-size: 10px; color: #7c3aed;">Loan: -${Math.round(parseFloat(p.loan_deduction)).toLocaleString('en-US')}</div>` : ''}
                    ${parseFloat(p.food_bills || 0) > 0 ? `<div style="font-size: 10px; color: #dc2626;">🍲 Food: -${Math.round(parseFloat(p.food_bills)).toLocaleString('en-US')}</div>` : ''}
                    ${parseFloat(p.deductions || 0) > 0 ? `<div style="font-size: 10px; color: #dc2626;">Other: -${Math.round(parseFloat(p.deductions)).toLocaleString('en-US')}</div>` : ''}
                </td>
                <td style="font-size: 12px; color: #dc2626; font-weight: 600;" title="${p.fine_reason ? escapeHtml(p.fine_reason) : ''}">
                    ${parseFloat(p.fines || 0) > 0 ? `-${Math.round(parseFloat(p.fines)).toLocaleString('en-US')}` : '--'}
                </td>
                <td style="font-size: 12px; color: #d97706; font-weight: 600;">
                    ${parseFloat(p.wht_amount || 0) > 0 ? `-${Math.round(parseFloat(p.wht_amount)).toLocaleString('en-US')}` : '--'}
                </td>
                <td style="font-size: 13.5px; font-weight: 800; color: #059669;">
                    PKR ${Math.round(parseFloat(p.net_salary || 0)).toLocaleString('en-US')}
                    ${parseFloat(p.paid_amount || 0) > 0 ? `<div style="font-size: 10.5px; color: #2563eb; font-weight: 600;">Paid: PKR ${Math.round(parseFloat(p.paid_amount)).toLocaleString('en-US')}</div>` : ''}
                </td>
                <td>
                    <div style="font-size: 11.5px; font-weight: 700; color: var(--text-main);">🏦 ${escapeHtml(p.bank_name || 'UBL')}</div>
                    <div style="font-size: 10.5px; color: var(--text-muted); font-family: monospace;">${escapeHtml(p.bank_account_no || 'Cash')}</div>
                    ${p.form_no ? `<div style="font-size: 10.5px; color: var(--primary); font-weight: 600;">📄 Form #${escapeHtml(p.form_no)}</div>` : ''}
                </td>
                <td style="text-align: center;">
                    <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; background: ${stBadge.bg}; color: ${stBadge.color};">
                        ${stBadge.label}
                    </span>
                </td>
                <td style="text-align: right;">
                    <div style="display: flex; gap: 4px; justify-content: flex-end;">
                        <button type="button" class="btn btn-outline" style="padding: 3px 7px; font-size: 11px;" onclick='event.stopPropagation(); printSalarySlip(HrState.payroll.find(x => x.employee_id == ${p.employee_id}))' title="Print Detailed Pay Slip">🖨️</button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function openEditPayrollModalById(empId) {
    const item = (HrState.payroll || []).find(p => p.employee_id == empId);
    if (item) {
        openEditPayrollModal(item);
    }
}

function openEditPayrollModal(item) {
    document.getElementById('edit-pr-employee-id').value = item.employee_id;
    document.getElementById('edit-pr-salary-month').value = item.salary_month;
    document.getElementById('edit-pr-working-days').value = item.working_days || 30;
    document.getElementById('edit-pr-present-days').value = item.present_days || 0;
    document.getElementById('edit-pr-approved-leaves').value = item.approved_leaves || 0;
    document.getElementById('edit-pr-unpaid-leaves').value = item.unpaid_leaves || 0;
    document.getElementById('edit-pr-duty-hours').value = item.total_duty_hours || 0;

    const empCode = item.emp_code || `DP-${String(item.employee_id).padStart(3, '0')}`;
    document.getElementById('edit-pr-emp-display').textContent = `${item.employee_name} (${empCode})`;
    document.getElementById('edit-pr-meta-display').textContent = `${item.designation || 'Staff'} • ${item.department_name || 'General'} | Bank: ${item.bank_name || 'UBL'} (${item.bank_account_no || 'Cash'}) | CNIC: ${item.cnic_no || 'N/A'}`;
    document.getElementById('edit-pr-month-display').textContent = `Month: ${item.salary_month}`;
    document.getElementById('edit-pr-days-display').textContent = `${item.working_days || 30} Days (${item.present_days || 0} Present, ${item.approved_leaves || 0} Paid Leaves, ${item.unpaid_leaves || 0} Unpaid Leaves, ${item.total_duty_hours || 0}h Duty)`;

    document.getElementById('edit-pr-basic-salary').value = item.basic_salary || 0;
    document.getElementById('edit-pr-fuel').value = item.fuel_allowance || 0;
    document.getElementById('edit-pr-incentive').value = item.incentive || 0;
    document.getElementById('edit-pr-food-bills').value = item.food_bills || 0;
    document.getElementById('edit-pr-bonus').value = item.bonus || 0;
    document.getElementById('edit-pr-bonus-reason').value = item.bonus_reason || '';

    // Calculate / populate unpaid leave deduction
    const basic = parseFloat(item.basic_salary || 0);
    const unpaidLeaves = parseFloat(item.unpaid_leaves || 0);
    const dailyRate = basic > 0 ? (basic / 30.0) : 0;
    const autoUnpaidDed = unpaidLeaves > 0 ? (Math.round((unpaidLeaves * dailyRate) * 100) / 100) : 0;

    let unpaidDedVal = autoUnpaidDed;
    let isWaived = false;
    if (item.unpaid_leave_deduction !== null && item.unpaid_leave_deduction !== undefined && item.unpaid_leave_deduction !== '') {
        unpaidDedVal = parseFloat(item.unpaid_leave_deduction);
        if (unpaidDedVal === 0 && unpaidLeaves > 0) {
            isWaived = true;
        }
    }
    const unpaidInput = document.getElementById('edit-pr-unpaid-deduction');
    if (unpaidInput) {
        unpaidInput.value = unpaidDedVal.toFixed(2);
        unpaidInput.dataset.autoAmount = autoUnpaidDed.toFixed(2);
    }

    const waiveCheckbox = document.getElementById('edit-pr-waive-unpaid');
    if (waiveCheckbox) {
        waiveCheckbox.checked = isWaived;
    }

    const labelEl = document.getElementById('edit-pr-unpaid-label');
    if (labelEl) {
        labelEl.textContent = unpaidLeaves > 0 ? `Unpaid Leaves (${unpaidLeaves})` : 'Unpaid Leaves (0)';
    }

    document.getElementById('edit-pr-advance').value = item.advance_salary || 0;
    document.getElementById('edit-pr-loan').value = item.loan_deduction || 0;
    document.getElementById('edit-pr-wht').value = item.wht_amount || 0;
    document.getElementById('edit-pr-fines').value = item.fines || 0;
    document.getElementById('edit-pr-fine-reason').value = item.fine_reason || '';
    document.getElementById('edit-pr-deductions').value = item.deductions || 0;
    document.getElementById('edit-pr-deduction-reason').value = item.deduction_reason || '';

    document.getElementById('edit-pr-form-no').value = item.form_no || '';
    document.getElementById('edit-pr-paid-amount').value = item.paid_amount || 0;
    document.getElementById('edit-pr-payment-method').value = item.payment_method || 'Bank Transfer';
    document.getElementById('edit-pr-payment-status').value = item.payment_status || 'draft';
    document.getElementById('edit-pr-payment-date').value = item.payment_date || '';
    document.getElementById('edit-pr-remarks').value = item.increment_remarks || '';

    calculatePayrollModalTotals();
    openModal('edit-payroll-modal');
}

function toggleWaiveUnpaidDeduction() {
    const waiveCheckbox = document.getElementById('edit-pr-waive-unpaid');
    const input = document.getElementById('edit-pr-unpaid-deduction');
    if (!input || !waiveCheckbox) return;

    if (waiveCheckbox.checked) {
        input.value = '0.00';
    } else {
        const autoAmt = parseFloat(input.dataset.autoAmount || 0);
        input.value = autoAmt.toFixed(2);
    }
    calculatePayrollModalTotals();
}

function handleUnpaidDeductionManualInput() {
    const input = document.getElementById('edit-pr-unpaid-deduction');
    const waiveCheckbox = document.getElementById('edit-pr-waive-unpaid');
    if (!input || !waiveCheckbox) return;

    const val = parseFloat(input.value) || 0;
    const unpaidLeaves = parseFloat(document.getElementById('edit-pr-unpaid-leaves')?.value) || 0;

    if (val === 0 && unpaidLeaves > 0) {
        waiveCheckbox.checked = true;
    } else {
        waiveCheckbox.checked = false;
    }
    calculatePayrollModalTotals();
}

function calculatePayrollModalTotals() {
    const basic = parseFloat(document.getElementById('edit-pr-basic-salary')?.value) || 0;
    const fuel = parseFloat(document.getElementById('edit-pr-fuel')?.value) || 0;
    const incentive = parseFloat(document.getElementById('edit-pr-incentive')?.value) || 0;
    const bonus = parseFloat(document.getElementById('edit-pr-bonus')?.value) || 0;

    const unpaidDed = parseFloat(document.getElementById('edit-pr-unpaid-deduction')?.value) || 0;
    const advance = parseFloat(document.getElementById('edit-pr-advance')?.value) || 0;
    const loan = parseFloat(document.getElementById('edit-pr-loan')?.value) || 0;
    const food = parseFloat(document.getElementById('edit-pr-food-bills')?.value) || 0;
    const wht = parseFloat(document.getElementById('edit-pr-wht')?.value) || 0;
    const fines = parseFloat(document.getElementById('edit-pr-fines')?.value) || 0;
    const otherDed = parseFloat(document.getElementById('edit-pr-deductions')?.value) || 0;

    const paid = parseFloat(document.getElementById('edit-pr-paid-amount')?.value) || 0;

    const totalAdditions = fuel + incentive + bonus;
    const totalDeductions = advance + loan + food + wht + fines + unpaidDed + otherDed;
    const netSalary = Math.max(0, basic + totalAdditions - totalDeductions);
    const payable = Math.max(0, netSalary - paid);

    const addEl = document.getElementById('modal-calc-additions');
    if (addEl) addEl.textContent = `PKR ${totalAdditions.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;

    const dedEl = document.getElementById('modal-calc-deductions');
    if (dedEl) dedEl.textContent = `PKR ${totalDeductions.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;

    const netEl = document.getElementById('modal-calc-net');
    if (netEl) netEl.textContent = `PKR ${netSalary.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;

    const payEl = document.getElementById('modal-calc-payable');
    if (payEl) payEl.textContent = `PKR ${payable.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
}

async function handleSavePayrollItem(e) {
    if (e) e.preventDefault();

    const empId = document.getElementById('edit-pr-employee-id')?.value;
    const month = document.getElementById('edit-pr-salary-month')?.value;
    const workingDays = document.getElementById('edit-pr-working-days')?.value;
    const presentDays = document.getElementById('edit-pr-present-days')?.value;
    const approvedLeaves = document.getElementById('edit-pr-approved-leaves')?.value;
    const unpaidLeaves = document.getElementById('edit-pr-unpaid-leaves')?.value;
    const dutyHours = document.getElementById('edit-pr-duty-hours')?.value;

    const basicSalary = document.getElementById('edit-pr-basic-salary')?.value;
    const fuelAllowance = document.getElementById('edit-pr-fuel')?.value;
    const incentive = document.getElementById('edit-pr-incentive')?.value;
    const foodBills = document.getElementById('edit-pr-food-bills')?.value;
    const bonus = document.getElementById('edit-pr-bonus')?.value;
    const bonusReason = document.getElementById('edit-pr-bonus-reason')?.value.trim();

    const unpaidLeaveDeduction = document.getElementById('edit-pr-unpaid-deduction')?.value;
    const advanceSalary = document.getElementById('edit-pr-advance')?.value;
    const loanDeduction = document.getElementById('edit-pr-loan')?.value;
    const whtAmount = document.getElementById('edit-pr-wht')?.value;
    const fines = document.getElementById('edit-pr-fines')?.value;
    const fineReason = document.getElementById('edit-pr-fine-reason')?.value.trim();
    const deductions = document.getElementById('edit-pr-deductions')?.value;
    const deductionReason = document.getElementById('edit-pr-deduction-reason')?.value.trim();

    const formNo = document.getElementById('edit-pr-form-no')?.value.trim();
    const paidAmount = document.getElementById('edit-pr-paid-amount')?.value;
    const paymentMethod = document.getElementById('edit-pr-payment-method')?.value;
    const paymentStatus = document.getElementById('edit-pr-payment-status')?.value;
    const paymentDate = document.getElementById('edit-pr-payment-date')?.value;
    const incrementRemarks = document.getElementById('edit-pr-remarks')?.value.trim();

    try {
        const res = await fetch('api/hr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_payroll_item',
                employee_id: empId,
                salary_month: month,
                working_days: workingDays,
                present_days: presentDays,
                approved_leaves: approvedLeaves,
                unpaid_leaves: unpaidLeaves,
                unpaid_leave_deduction: unpaidLeaveDeduction,
                total_duty_hours: dutyHours,
                basic_salary: basicSalary,
                fuel_allowance: fuelAllowance,
                incentive: incentive,
                food_bills: foodBills,
                bonus: bonus,
                bonus_reason: bonusReason,
                advance_salary: advanceSalary,
                loan_deduction: loanDeduction,
                fines: fines,
                fine_reason: fineReason,
                wht_amount: whtAmount,
                deductions: deductions,
                deduction_reason: deductionReason,
                form_no: formNo,
                paid_amount: paidAmount,
                payment_method: paymentMethod,
                payment_status: paymentStatus,
                payment_date: paymentDate,
                increment_remarks: incrementRemarks
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast("Payroll record & breakdown saved successfully!", "success");
            closeModal('edit-payroll-modal');
            await loadHrPayroll();
        } else {
            showToast(data.message || "Failed to save payroll record.", "error");
        }
    } catch (err) {
        showToast("Network error saving payroll adjustments.", "error");
    }
}

function printSalarySlip(item) {
    const printWindow = window.open('', '_blank', 'width=840,height=960');
    if (!printWindow) {
        showToast("Please allow popups to view and print salary slips.", "error");
        return;
    }

    const monthName = new Date(item.salary_month + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const empCode = item.emp_code || `DP-${String(item.employee_id).padStart(3, '0')}`;

    const basicSalary = parseFloat(item.basic_salary || 0);
    const fuel = parseFloat(item.fuel_allowance || 0);
    const incentive = parseFloat(item.incentive || 0);
    const bonus = parseFloat(item.bonus || 0);

    const unpaidDed = parseFloat(item.unpaid_leave_deduction || 0);
    const unpaidLeaves = parseFloat(item.unpaid_leaves || 0);
    const advance = parseFloat(item.advance_salary || 0);
    const loan = parseFloat(item.loan_deduction || 0);
    const foodBills = parseFloat(item.food_bills || 0);
    const fines = parseFloat(item.fines || 0);
    const wht = parseFloat(item.wht_amount || 0);
    const deductions = parseFloat(item.deductions || 0);

    const totalAdditions = fuel + incentive + bonus;
    const totalDeductions = advance + loan + foodBills + fines + wht + unpaidDed + deductions;
    const netSalary = Math.max(0, basicSalary + totalAdditions - totalDeductions);
    const paidAmount = parseFloat(item.paid_amount || 0);
    const payableAmount = Math.max(0, netSalary - paidAmount);

    const slipHtml = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Salary Pay Slip - ${escapeHtml(item.employee_name)} (${escapeHtml(empCode)}) - ${monthName}</title>
        <style>
            * { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; }
            body { padding: 30px; color: #0f172a; background: #fff; }
            .slip-card { max-width: 760px; margin: 0 auto; border: 2px solid #0f172a; padding: 25px 30px; border-radius: 8px; }
            .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 18px; }
            .header h1 { font-size: 24px; text-transform: uppercase; letter-spacing: 1px; color: #0284c7; font-weight: 800; }
            .header p { font-size: 13px; color: #64748b; margin-top: 3px; font-weight: 600; }
            .badge-month { display: inline-block; background: #f1f5f9; padding: 4px 16px; border-radius: 20px; font-weight: 700; font-size: 13px; margin-top: 8px; border: 1px solid #cbd5e1; color: #0369a1; }
            
            .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
            .meta-box { background: #f8fafc; padding: 12px 14px; border-radius: 6px; border: 1px solid #e2e8f0; }
            .meta-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12.5px; }
            .meta-label { color: #64748b; font-weight: 600; }
            .meta-val { font-weight: 700; color: #0f172a; }

            .table-split { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
            .table-box { width: 100%; border-collapse: collapse; }
            .table-box th, .table-box td { border: 1px solid #cbd5e1; padding: 8px 12px; font-size: 12.5px; }
            .table-box th { background: #f1f5f9; font-weight: 700; text-align: left; }
            .table-box td.amount { text-align: right; font-weight: 700; }
            
            .net-box { background: #ecfdf5; border: 2px solid #10b981; padding: 14px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
            .net-title { font-size: 15px; font-weight: 800; color: #065f46; }
            .net-amount { font-size: 22px; font-weight: 800; color: #047857; }

            .remarks-box { background: #fefce8; border: 1px dashed #ca8a04; border-radius: 6px; padding: 8px 12px; font-size: 12px; color: #854d0e; margin-bottom: 24px; }
            
            .sig-row { display: flex; justify-content: space-between; margin-top: 45px; padding: 0 15px; }
            .sig-line { width: 190px; border-top: 1.5px solid #475569; text-align: center; padding-top: 6px; font-size: 11.5px; font-weight: 700; color: #475569; }
            
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
                <p>Digital Media & Satellite Operations • Official Monthly Salary Slip</p>
                <div class="badge-month">Salary Month: ${monthName} ${item.form_no ? `• Form #${escapeHtml(item.form_no)}` : ''}</div>
            </div>

            <div class="meta-grid">
                <div class="meta-box">
                    <div class="meta-row"><span class="meta-label">Employee Code:</span><span class="meta-val">${escapeHtml(empCode)}</span></div>
                    <div class="meta-row"><span class="meta-label">Employee Name:</span><span class="meta-val">${escapeHtml(item.employee_name)}</span></div>
                    <div class="meta-row"><span class="meta-label">Father / Husband:</span><span class="meta-val">${escapeHtml(item.father_husband_name || '--')}</span></div>
                    <div class="meta-row"><span class="meta-label">CNIC Number:</span><span class="meta-val">${escapeHtml(item.cnic_no || '--')}</span></div>
                    <div class="meta-row"><span class="meta-label">Designation:</span><span class="meta-val">${escapeHtml(item.designation || 'Staff Member')}</span></div>
                    <div class="meta-row"><span class="meta-label">Department:</span><span class="meta-val">${escapeHtml(item.department_name || 'General')}</span></div>
                </div>
                <div class="meta-box">
                    <div class="meta-row"><span class="meta-label">Joining Date:</span><span class="meta-val">${escapeHtml(item.joining_date || '--')}</span></div>
                    <div class="meta-row"><span class="meta-label">Working / Present:</span><span class="meta-val">${item.present_days} / ${item.working_days} Days</span></div>
                    <div class="meta-row"><span class="meta-label">Leaves:</span><span class="meta-val">${item.approved_leaves || 0} Paid, ${item.unpaid_leaves || 0} Unpaid</span></div>
                    <div class="meta-row"><span class="meta-label">Duty Hours:</span><span class="meta-val">${item.total_duty_hours || 0} Hours</span></div>
                    <div class="meta-row"><span class="meta-label">Bank Name:</span><span class="meta-val">${escapeHtml(item.bank_name || 'UBL')}</span></div>
                    <div class="meta-row"><span class="meta-label">Account / IBAN:</span><span class="meta-val">${escapeHtml(item.bank_account_no || 'Cash / Direct')}</span></div>
                </div>
            </div>

            <div class="table-split">
                <!-- Earnings -->
                <table class="table-box">
                    <thead>
                        <tr>
                            <th>➕ Earnings & Allowances</th>
                            <th style="width: 35%; text-align: right;">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic / Gross Salary</td>
                            <td class="amount">${Math.round(basicSalary).toLocaleString('en-US')}</td>
                        </tr>
                        ${fuel > 0 ? `
                        <tr>
                            <td>Fuel / Travel / Mobile Allowance</td>
                            <td class="amount" style="color: #0284c7;">+${Math.round(fuel).toLocaleString('en-US')}</td>
                        </tr>` : ''}
                        ${incentive > 0 ? `
                        <tr>
                            <td>Incentive / Performance</td>
                            <td class="amount" style="color: #059669;">+${Math.round(incentive).toLocaleString('en-US')}</td>
                        </tr>` : ''}
                        ${bonus > 0 ? `
                        <tr>
                            <td>Bonus ${item.bonus_reason ? `(${escapeHtml(item.bonus_reason)})` : ''}</td>
                            <td class="amount" style="color: #059669;">+${Math.round(bonus).toLocaleString('en-US')}</td>
                        </tr>` : ''}
                        <tr style="background: #f8fafc; font-weight: 800;">
                            <td>Total Gross Earnings</td>
                            <td class="amount" style="color: #059669;">PKR ${Math.round(basicSalary + totalAdditions).toLocaleString('en-US')}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Deductions -->
                <table class="table-box">
                    <thead>
                        <tr>
                            <th>➖ Deductions & Taxes</th>
                            <th style="width: 35%; text-align: right;">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${unpaidDed > 0 ? `
                        <tr>
                            <td>Unpaid Leave Deduction (${unpaidLeaves} day${unpaidLeaves > 1 ? 's' : ''})</td>
                            <td class="amount" style="color: #dc2626;">-${Math.round(unpaidDed).toLocaleString('en-US')}</td>
                        </tr>` : (unpaidLeaves > 0 ? `
                        <tr>
                            <td>Unpaid Leaves (${unpaidLeaves} day${unpaidLeaves > 1 ? 's' : ''})</td>
                            <td class="amount" style="color: #059669;">Excused / 0</td>
                        </tr>` : '')}
                        ${advance > 0 ? `
                        <tr>
                            <td>Advance Salary Deduction</td>
                            <td class="amount" style="color: #dc2626;">-${Math.round(advance).toLocaleString('en-US')}</td>
                        </tr>` : ''}
                        ${loan > 0 ? `
                        <tr>
                            <td>Loan Installment</td>
                            <td class="amount" style="color: #dc2626;">-${Math.round(loan).toLocaleString('en-US')}</td>
                        </tr>` : ''}
                        ${foodBills > 0 ? `
                        <tr>
                            <td>Canteen Food Bills / Mess</td>
                            <td class="amount" style="color: #dc2626;">-${Math.round(foodBills).toLocaleString('en-US')}</td>
                        </tr>` : ''}
                        ${fines > 0 ? `
                        <tr>
                            <td>Disciplinary Fines ${item.fine_reason ? `(${escapeHtml(item.fine_reason)})` : ''}</td>
                            <td class="amount" style="color: #dc2626;">-${Math.round(fines).toLocaleString('en-US')}</td>
                        </tr>` : ''}
                        ${wht > 0 ? `
                        <tr>
                            <td>WHT / Income Tax</td>
                            <td class="amount" style="color: #dc2626;">-${Math.round(wht).toLocaleString('en-US')}</td>
                        </tr>` : ''}
                        ${deductions > 0 ? `
                        <tr>
                            <td>Other Deductions ${item.deduction_reason ? `(${escapeHtml(item.deduction_reason)})` : ''}</td>
                            <td class="amount" style="color: #dc2626;">-${Math.round(deductions).toLocaleString('en-US')}</td>
                        </tr>` : ''}
                        ${totalDeductions === 0 ? `
                        <tr>
                            <td colspan="2" style="text-align: center; color: #64748b; font-style: italic;">No deductions applied</td>
                        </tr>` : ''}
                        <tr style="background: #f8fafc; font-weight: 800;">
                            <td>Total Deductions</td>
                            <td class="amount" style="color: #dc2626;">PKR ${Math.round(totalDeductions).toLocaleString('en-US')}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="net-box">
                <div>
                    <div class="net-title">TOTAL NET SALARY:</div>
                    <div style="font-size: 12px; color: #065f46; margin-top: 2px;">
                        Payment Mode: ${escapeHtml(item.payment_method || 'Bank Transfer')} ${paidAmount > 0 ? `• Disbursed: PKR ${Math.round(paidAmount).toLocaleString('en-US')}` : ''}
                    </div>
                </div>
                <div class="net-amount">PKR ${Math.round(netSalary).toLocaleString('en-US')}</div>
            </div>

            ${item.increment_remarks ? `
            <div class="remarks-box">
                <strong>📝 Salary Remarks / Audit Note:</strong> ${escapeHtml(item.increment_remarks)}
            </div>` : ''}

            <div class="sig-row">
                <div class="sig-line">Employee Signature</div>
                <div class="sig-line">HR Manager</div>
                <div class="sig-line">Accounts & Finance Director</div>
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

/**
 * Export full monthly payroll sheet to CSV / Excel
 */
function exportPayrollCSV() {
    let payroll = HrState.payroll || [];
    if (!payroll || payroll.length === 0) {
        showToast("No payroll records available for the selected month to export.", "warning");
        return;
    }

    const search = (document.getElementById('hr-payroll-search')?.value || '').toLowerCase().trim();
    const deptName = document.getElementById('hr-payroll-filter-dept')?.value;
    const paymentStatus = document.getElementById('hr-payroll-filter-status')?.value;
    const bankFilter = document.getElementById('hr-payroll-filter-bank')?.value;

    if (search || deptName || paymentStatus || bankFilter) {
        if (deptName) {
            payroll = payroll.filter(p => (p.department_name || '').toLowerCase() === deptName.toLowerCase());
        }
        if (paymentStatus) {
            payroll = payroll.filter(p => (p.payment_status || 'draft') === paymentStatus);
        }
        if (bankFilter) {
            if (bankFilter === 'Cash') {
                payroll = payroll.filter(p => {
                    const b = (p.bank_name || '').toLowerCase();
                    const acc = (p.bank_account_no || '').toLowerCase();
                    return b === 'cash' || acc.includes('cash');
                });
            } else {
                payroll = payroll.filter(p => (p.bank_name || '').toLowerCase().includes(bankFilter.toLowerCase()));
            }
        }
        if (search) {
            payroll = payroll.filter(p => 
                (p.employee_name || '').toLowerCase().includes(search) ||
                (p.emp_code || '').toLowerCase().includes(search) ||
                (p.cnic_no || '').toLowerCase().includes(search) ||
                (p.father_husband_name || '').toLowerCase().includes(search) ||
                (p.designation || '').toLowerCase().includes(search) ||
                (p.department_name || '').toLowerCase().includes(search) ||
                (p.bank_name || '').toLowerCase().includes(search) ||
                (p.bank_account_no || '').toLowerCase().includes(search) ||
                (p.form_no || '').toLowerCase().includes(search)
            );
        }
    }

    const month = document.getElementById('hr-payroll-month')?.value || HrState.selectedMonth || 'current';

    const headers = [
        "EMPLOYEE CODE",
        "NAME",
        "DESIGNATION",
        "DEPARTMENT",
        "FATHER / HUSBAND NAME",
        "CNIC NO.",
        "DATE OF JOINING",
        "GROSS SALARY",
        "PAID LEAVES",
        "UNPAID LEAVES",
        "UNPAID LEAVE DEDUCTION",
        "FUEL / TRAV. / MOBILE",
        "INCENTIVE",
        "BONUS",
        "TOTAL ADDITIONS",
        "ADVANCE SALARY",
        "LOAN DEDUCTION",
        "CANTEEN FOOD BILLS",
        "FINES",
        "WHT TAX",
        "OTHER DEDUCTIONS",
        "TOTAL DEDUCTIONS",
        "NET SALARY",
        "SALARY PAID",
        "SALARY PAYABLE",
        "FORM #",
        "BANK NAME",
        "BANK ACCOUNT / IBAN",
        "PAYMENT STATUS",
        "SALARY INCREMENT / AUDIT REMARKS"
    ];

    const escapeCsvValue = (val) => {
        if (val === null || val === undefined) return '""';
        const str = String(val).replace(/"/g, '""');
        return `"${str}"`;
    };

    const rows = [headers.join(',')];

    payroll.forEach(p => {
        const empCode = p.emp_code || `DP-${String(p.employee_id).padStart(3, '0')}`;
        const basic = parseFloat(p.basic_salary || 0);
        const fuel = parseFloat(p.fuel_allowance || 0);
        const incentive = parseFloat(p.incentive || 0);
        const bonus = parseFloat(p.bonus || 0);
        const totalAdditions = fuel + incentive + bonus;

        const unpaidDed = parseFloat(p.unpaid_leave_deduction || 0);
        const advance = parseFloat(p.advance_salary || 0);
        const loan = parseFloat(p.loan_deduction || 0);
        const foodBills = parseFloat(p.food_bills || 0);
        const fines = parseFloat(p.fines || 0);
        const wht = parseFloat(p.wht_amount || 0);
        const deductions = parseFloat(p.deductions || 0);
        const totalDeductions = advance + loan + foodBills + fines + wht + unpaidDed + deductions;

        const netSalary = parseFloat(p.net_salary || (basic + totalAdditions - totalDeductions));
        const paidAmount = parseFloat(p.paid_amount || 0);
        const payableAmount = parseFloat(p.payable_amount || Math.max(0, netSalary - paidAmount));

        const row = [
            escapeCsvValue(empCode),
            escapeCsvValue(p.employee_name || ''),
            escapeCsvValue(p.designation || ''),
            escapeCsvValue(p.department_name || ''),
            escapeCsvValue(p.father_husband_name || ''),
            escapeCsvValue(p.cnic_no || ''),
            escapeCsvValue(p.joining_date || ''),
            escapeCsvValue(basic.toFixed(2)),
            escapeCsvValue(p.approved_leaves || 0),
            escapeCsvValue(p.unpaid_leaves || 0),
            escapeCsvValue(unpaidDed.toFixed(2)),
            escapeCsvValue(fuel.toFixed(2)),
            escapeCsvValue(incentive.toFixed(2)),
            escapeCsvValue(bonus.toFixed(2)),
            escapeCsvValue(totalAdditions.toFixed(2)),
            escapeCsvValue(advance.toFixed(2)),
            escapeCsvValue(loan.toFixed(2)),
            escapeCsvValue(foodBills.toFixed(2)),
            escapeCsvValue(fines.toFixed(2)),
            escapeCsvValue(wht.toFixed(2)),
            escapeCsvValue(deductions.toFixed(2)),
            escapeCsvValue(totalDeductions.toFixed(2)),
            escapeCsvValue(netSalary.toFixed(2)),
            escapeCsvValue(paidAmount.toFixed(2)),
            escapeCsvValue(payableAmount.toFixed(2)),
            escapeCsvValue(p.form_no || ''),
            escapeCsvValue(p.bank_name || ''),
            escapeCsvValue(p.bank_account_no || ''),
            escapeCsvValue((p.payment_status || 'draft').toUpperCase()),
            escapeCsvValue(p.increment_remarks || '')
        ];

        rows.push(row.join(','));
    });

    const csvContent = "\uFEFF" + rows.join("\r\n");
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", `payroll_sheet_${month}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    showToast(`Payroll sheet exported successfully for ${month}!`, "success");
}

