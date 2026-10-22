<?php
/**
 * Professional HR & People Operations Command Center
 * Executive views for HR Dashboard, Leave Requests, Advance/Loans, Notices, and Monthly Payroll
 */
?>

<!-- ================= 1. HR EXECUTIVE DASHBOARD ================= -->
<section id="tab-hr" class="tab-section">
    <div class="worksheet-container">

        <!-- Executive Hero Header -->
        <div class="worksheet-card" style="margin-bottom: 20px; background: linear-gradient(135deg, rgba(14, 165, 233, 0.08) 0%, rgba(99, 102, 241, 0.05) 100%); border-left: 4px solid var(--primary); padding: 22px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h2 style="font-size: 22px; font-weight: 800; color: var(--text-main); margin-bottom: 4px; display: flex; align-items: center; gap: 8px;">
                        💼 HR & People Operations Command Center
                    </h2>
                    <p style="color: var(--text-muted); font-size: 13px; margin: 0;">
                        Workforce overview, attendance metrics, leave approvals, salary advances, notices, and payroll.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-primary" onclick="openAddClaimModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        ⛽ + Add Fuel / Food Claim
                    </button>
                    <button type="button" class="btn btn-outline" onclick="openCreateNoticeModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        📢 Post Notice
                    </button>
                    <button type="button" class="btn btn-outline" onclick="openIssueFineModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; color: #ef4444; border-color: rgba(239, 68, 68, 0.4);">
                        ⚠️ Issue Fine
                    </button>
                    <button type="button" class="btn btn-outline" onclick="navigateToTab('tab-hr-leaves')" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        🏖️ Leave Approvals
                    </button>
                    <button type="button" class="btn btn-outline" onclick="navigateToTab('tab-attendance')" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        👥 Staff Attendance
                    </button>
                    <button type="button" class="btn btn-outline" onclick="loadHrDashboard()" style="padding: 7px 12px; font-size: 12.5px;" title="Refresh HR Dashboard">
                        🔄 Refresh
                    </button>
                </div>
            </div>

            <!-- Executive Key Performance Indicators (KPIs) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-top: 20px;">
                <div class="stat-card" style="padding: 14px 18px; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11.5px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">👥 Total Staff</span>
                        <span style="font-size: 18px;">🏢</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 800; color: var(--primary); margin-top: 4px;" id="hr-dash-total-staff">0</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Active across all departments</div>
                </div>

                <div class="stat-card" style="padding: 14px 18px; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11.5px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">🟢 Present Today</span>
                        <span style="font-size: 18px;">⏱️</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 800; color: #10b981; margin-top: 4px;" id="hr-dash-today-present">0</div>
                    <div style="font-size: 11px; color: #10b981; font-weight: 600; margin-top: 2px;">Checked in for duty</div>
                </div>

                <div class="stat-card" style="padding: 14px 18px; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11.5px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">🌴 On Leave Today</span>
                        <span style="font-size: 18px;">🏖️</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 800; color: #3b82f6; margin-top: 4px;" id="hr-dash-today-on-leave">0</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Approved scheduled absence</div>
                </div>

                <div class="stat-card" style="padding: 14px 18px; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11.5px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">⏳ Pending Leaves</span>
                        <span style="font-size: 18px;">⚡</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 800; color: #f59e0b; margin-top: 4px;" id="hr-dash-pending-leaves">0</div>
                    <div style="font-size: 11px; color: #f59e0b; font-weight: 600; margin-top: 2px;">Leave requests awaiting review</div>
                </div>

                <div class="stat-card" style="padding: 14px 18px; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 11.5px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">💳 Pending Loans</span>
                        <span style="font-size: 18px;">💸</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 800; color: #8b5cf6; margin-top: 4px;" id="hr-dash-pending-loans">0</div>
                    <div style="font-size: 11px; color: #8b5cf6; font-weight: 600; margin-top: 2px;">Advance salary requests</div>
                </div>
            </div>
        </div>

        <!-- Dashboard 2-Column Grid -->
        <div style="display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start;">

            <!-- Left Column: Pending Approvals & Today's On-Leave -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Pending Leave Applications Queue -->
                <div class="worksheet-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <div>
                            <h3 style="font-size: 16px; font-weight: 800; color: var(--text-main); margin-bottom: 2px; display: flex; align-items: center; gap: 8px;">
                                ⚡ Pending Leave Requests
                            </h3>
                            <p style="color: var(--text-muted); font-size: 12px; margin: 0;">
                                Recent applications awaiting HR / Manager action
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline" style="font-size: 11.5px; padding: 5px 10px;" onclick="navigateToTab('tab-hr-leaves')">
                            View All Leaves →
                        </button>
                    </div>

                    <div id="hr-dash-pending-list" style="display: flex; flex-direction: column; gap: 10px;">
                        <div style="text-align: center; padding: 25px; color: var(--text-muted); font-size: 13px;">
                            Loading pending requests...
                        </div>
                    </div>
                </div>

                <!-- Pending Advance & Loan Applications Queue -->
                <div class="worksheet-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <div>
                            <h3 style="font-size: 16px; font-weight: 800; color: var(--text-main); margin-bottom: 2px; display: flex; align-items: center; gap: 8px;">
                                💳 Pending Advance & Loan Requests
                            </h3>
                            <p style="color: var(--text-muted); font-size: 12px; margin: 0;">
                                Emergency aid and salary advances awaiting approval
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline" style="font-size: 11.5px; padding: 5px 10px;" onclick="navigateToTab('tab-hr-loans')">
                            View All Loans →
                        </button>
                    </div>

                    <div id="hr-dash-pending-loans-list" style="display: flex; flex-direction: column; gap: 10px;">
                        <div style="text-align: center; padding: 25px; color: var(--text-muted); font-size: 13px;">
                            Loading loan applications...
                        </div>
                    </div>
                </div>

                <!-- Today's Absent / On-Leave Roster -->
                <div class="worksheet-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <div>
                            <h3 style="font-size: 16px; font-weight: 800; color: var(--text-main); margin-bottom: 2px; display: flex; align-items: center; gap: 8px;">
                                🌴 Today's On-Leave Staff
                            </h3>
                            <p style="color: var(--text-muted); font-size: 12px; margin: 0;">
                                Employees with approved leaves active today
                            </p>
                        </div>
                        <span class="badge-lock unlocked" id="hr-dash-onleave-badge" style="font-size: 11px;">0 on leave</span>
                    </div>

                    <div id="hr-dash-onleave-list" style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 13px;">
                            No staff on leave today. All active members expected.
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Notice Board, Company Leave Analytics, Department Attendance & Tools -->
            <div style="display: flex; flex-direction: column; gap: 20px;">

                <!-- Company Notice Board Widget -->
                <div class="worksheet-card" style="border-top: 3px solid #f59e0b;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <div>
                            <h3 style="font-size: 15px; font-weight: 800; color: var(--text-main); margin-bottom: 2px; display: flex; align-items: center; gap: 6px;">
                                📢 Notice Board & Alerts
                            </h3>
                            <p style="color: var(--text-muted); font-size: 11px; margin: 0;">
                                Important company announcements
                            </p>
                        </div>
                        <button type="button" class="btn btn-outline admin-only" style="font-size: 11px; padding: 4px 8px;" onclick="openCreateNoticeModal()">
                            + Post
                        </button>
                    </div>

                    <div id="hr-dash-notices-list" style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="font-size: 12px; color: var(--text-muted); text-align: center; padding: 15px;">
                            Loading announcements...
                        </div>
                    </div>
                </div>

                <!-- Company-Wide Monthly Leave & Absence Analytics Widget -->
                <div class="worksheet-card" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(16, 185, 129, 0.05) 100%);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <h3 style="font-size: 15px; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 6px;">
                            📊 Monthly Leave Analytics
                        </h3>
                        <span id="hr-dash-leave-month-label" style="font-size: 10.5px; font-weight: 700; color: var(--primary); background: rgba(0,179,0,0.1); padding: 2px 7px; border-radius: 4px;">This Month</span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 11.5px; margin-bottom: 12px;">
                        Approved staff leave utilization across company
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center;">
                        <div style="padding: 10px 6px; background: var(--bg-card); border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                            <div style="font-size: 11px; font-weight: 700; color: #10b981;">Casual</div>
                            <div style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-top: 2px;" id="hr-dash-company-casual">0</div>
                            <div style="font-size: 10px; color: var(--text-muted);">Days Logged</div>
                        </div>

                        <div style="padding: 10px 6px; background: var(--bg-card); border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                            <div style="font-size: 11px; font-weight: 700; color: #f59e0b;">Sick</div>
                            <div style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-top: 2px;" id="hr-dash-company-sick">0</div>
                            <div style="font-size: 10px; color: var(--text-muted);">Days Logged</div>
                        </div>

                        <div style="padding: 10px 6px; background: var(--bg-card); border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                            <div style="font-size: 11px; font-weight: 700; color: #3b82f6;">Annual</div>
                            <div style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-top: 2px;" id="hr-dash-company-annual">0</div>
                            <div style="font-size: 10px; color: var(--text-muted);">Days Logged</div>
                        </div>
                    </div>

                    <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 12px; color: var(--text-muted);">
                            Total Days: <strong id="hr-dash-company-total-days" style="color: var(--text-main);">0 Days</strong>
                        </span>
                        <button type="button" class="btn btn-outline" style="font-size: 11.5px; padding: 4px 10px;" onclick="navigateToTab('tab-hr-leaves')">
                            Leave Approvals →
                        </button>
                    </div>
                </div>

                <!-- Department Attendance & Staffing Breakdown -->
                <div class="worksheet-card">
                    <h3 style="font-size: 15px; font-weight: 800; color: var(--text-main); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                        🏢 Department Attendance Today
                    </h3>
                    <p style="color: var(--text-muted); font-size: 11.5px; margin-bottom: 12px;">
                        Present count vs Total active staff
                    </p>

                    <div id="hr-dash-dept-attendance-list" style="display: flex; flex-direction: column; gap: 10px;">
                        <!-- Populated dynamically -->
                    </div>
                </div>

                <!-- Quick Navigation Shortcuts -->
                <div class="worksheet-card">
                    <h3 style="font-size: 14px; font-weight: 800; color: var(--text-main); margin-bottom: 10px;">
                        🚀 Quick Portals & Tools
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <button type="button" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 8px 12px; font-size: 12.5px;" onclick="navigateToTab('tab-hr-leaves')">
                            🏖️ Leave Approvals & Records
                        </button>
                        <button type="button" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 8px 12px; font-size: 12.5px;" onclick="navigateToTab('tab-hr-loans')">
                            💳 Salary Advance & Loan Requests
                        </button>
                        <button type="button" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 8px 12px; font-size: 12.5px;" onclick="navigateToTab('tab-hr-notices')">
                            📢 Company Notice Board
                        </button>
                        <button type="button" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 8px 12px; font-size: 12.5px; color: #ef4444;" onclick="navigateToTab('tab-hr-fines')">
                            ⚠️ Disciplinary Fines & Penalties
                        </button>
                        <button type="button" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 8px 12px; font-size: 12.5px;" onclick="navigateToTab('tab-attendance')">
                            👥 Real-Time Staff Attendance
                        </button>
                        <button type="button" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 8px 12px; font-size: 12.5px;" onclick="navigateToTab('tab-hr-payroll')">
                            💰 Monthly Payroll Calculator
                        </button>
                        <button type="button" class="btn btn-outline" style="justify-content: flex-start; text-align: left; padding: 8px 12px; font-size: 12.5px;" onclick="navigateToTab('tab-employees')">
                            📁 Employee Directory & Settings
                        </button>
                    </div>
                </div>

            </div>

        </div>

    </div>
</section>


<!-- ================= 2. DEDICATED LEAVE MANAGEMENT CENTER ================= -->
<section id="tab-hr-leaves" class="tab-section">
    <div class="worksheet-container">
        
        <div class="worksheet-card" style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 2px;">
                        🏖️ Leave Applications & Approval Center
                    </h2>
                    <p style="color: var(--text-muted); font-size: 12.5px; margin: 0;">
                        Review, approve, reject, or submit employee leave requests.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="btn btn-primary" onclick="openApplyLeaveModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        + Apply for Leave
                    </button>
                    <button type="button" class="btn btn-outline" onclick="loadHrLeaves()" title="Reload table">
                        🔄 Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="worksheet-card">
            <!-- Modern Responsive Filter Toolbar -->
            <div class="modern-filter-toolbar">
                <!-- Row 1: Search, Date Range & Actions -->
                <div class="filter-row-primary">
                    <div class="filter-search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="hr-leave-search" placeholder="Search employee, reason, notes..." oninput="applyLeavesClientFilters()">
                    </div>

                    <div class="filter-date-group">
                        <span class="date-label">📅 Dates:</span>
                        <input type="date" id="hr-leave-date-from" title="From Date" onchange="loadHrLeaves()" oninput="applyLeavesClientFilters()">
                        <span class="date-sep">to</span>
                        <input type="date" id="hr-leave-date-to" title="To Date" onchange="loadHrLeaves()" oninput="applyLeavesClientFilters()">
                        <button type="button" id="hr-leave-date-clear-btn" class="date-clear-btn" style="display: none;" onclick="clearLeaveDates()" title="Clear dates">✕</button>
                    </div>

                    <div class="filter-toolbar-actions">
                        <button type="button" id="hr-leave-reset-filters-btn" class="btn btn-outline" style="padding: 5px 10px; font-size: 11.5px; font-weight: 700; border-radius: var(--radius-md); display: none; color: #ef4444; border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.05);" onclick="resetLeavesFilters()" title="Reset all filters">
                            ↺ Reset
                        </button>
                        <span id="hr-leaves-count-label" class="filter-count-chip">
                            Showing 0 request(s)
                        </span>
                    </div>
                </div>

                <!-- Row 2: Secondary Dropdown Filters -->
                <div class="filter-row-secondary">
                    <select id="hr-leave-dept-filter" onchange="applyLeavesClientFilters()">
                        <option value="">🏢 All Departments</option>
                    </select>

                    <select id="hr-leave-status-filter" onchange="loadHrLeaves()">
                        <option value="all" selected>All Statuses</option>
                        <option value="pending">⏳ Pending</option>
                        <option value="approved_by_hod">🟡 Approved by HOD</option>
                        <option value="approved">✅ Approved</option>
                        <option value="rejected">❌ Rejected</option>
                        <option value="cancelled">🚫 Cancelled</option>
                    </select>

                    <select id="hr-leave-type-filter" onchange="loadHrLeaves()">
                        <option value="all">🌴 All Leave Types</option>
                        <option value="casual">🌴 Casual Leave</option>
                        <option value="sick">🩺 Sick Leave</option>
                        <option value="annual">🏖️ Annual Leave</option>
                        <option value="unpaid">🚫 Unpaid Leave</option>
                        <option value="other">📝 Other</option>
                    </select>

                    <select id="hr-leave-emp-filter" class="admin-only" onchange="loadHrLeaves()">
                        <option value="">👤 All Employees</option>
                    </select>
                </div>
            </div>

            <!-- Leaves Table -->
            <div class="table-responsive">
                <table class="interactive-table">
                    <thead>
                        <tr>
                            <th style="width: 20%; min-width: 170px;">Employee</th>
                            <th style="width: 11%; min-width: 100px;">Leave Type</th>
                            <th style="width: 16%; min-width: 130px;">Date Range</th>
                            <th style="width: 6%; min-width: 55px; text-align: center;">Days</th>
                            <th style="width: 23%; min-width: 160px;">Reason & Notes</th>
                            <th style="width: 12%; min-width: 130px; text-align: center; white-space: nowrap;">Status</th>
                            <th style="width: 12%; min-width: 140px; text-align: center; white-space: nowrap;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="hr-leaves-table-body">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                Loading leave applications...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>


<!-- ================= 3. DEDICATED SALARY ADVANCE & LOANS CENTER ================= -->
<section id="tab-hr-loans" class="tab-section">
    <div class="worksheet-container">
        
        <div class="worksheet-card" style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 2px;">
                        💳 Salary Advance & Emergency Loan Management
                    </h2>
                    <p style="color: var(--text-muted); font-size: 12.5px; margin: 0;">
                        Apply for salary advances, approve loan applications, and manage automated monthly payroll deductions.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="btn btn-primary" onclick="openApplyLoanModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        + Request Advance / Loan
                    </button>
                    <button type="button" class="btn btn-outline" onclick="loadHrLoans()" title="Reload table">
                        🔄 Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="worksheet-card">
            <!-- Modern Responsive Filter Toolbar -->
            <div class="modern-filter-toolbar">
                <!-- Row 1: Search, Date Range & Actions -->
                <div class="filter-row-primary">
                    <div class="filter-search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="hr-loan-search" placeholder="Search employee, reason, amount..." oninput="applyLoansClientFilters()">
                    </div>

                    <div class="filter-date-group">
                        <span class="date-label">📅 Dates:</span>
                        <input type="date" id="hr-loan-date-from" title="From Date" onchange="loadHrLoans()" oninput="applyLoansClientFilters()">
                        <span class="date-sep">to</span>
                        <input type="date" id="hr-loan-date-to" title="To Date" onchange="loadHrLoans()" oninput="applyLoansClientFilters()">
                        <button type="button" id="hr-loan-date-clear-btn" class="date-clear-btn" style="display: none;" onclick="clearLoanDates()" title="Clear dates">✕</button>
                    </div>

                    <div class="filter-toolbar-actions">
                        <button type="button" id="hr-loan-reset-filters-btn" class="btn btn-outline" style="padding: 5px 10px; font-size: 11.5px; font-weight: 700; border-radius: var(--radius-md); display: none; color: #ef4444; border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.05);" onclick="resetLoansFilters()" title="Reset all filters">
                            ↺ Reset
                        </button>
                        <span id="hr-loans-count-label" class="filter-count-chip">
                            Showing 0 loan record(s)
                        </span>
                    </div>
                </div>

                <!-- Row 2: Secondary Dropdown Filters -->
                <div class="filter-row-secondary">
                    <select id="hr-loan-dept-filter" onchange="applyLoansClientFilters()">
                        <option value="">🏢 All Departments</option>
                    </select>

                    <select id="hr-loan-status-filter" onchange="loadHrLoans()">
                        <option value="all" selected>All Statuses</option>
                        <option value="pending">⏳ Pending Review</option>
                        <option value="approved_by_hod">🟡 Approved by HOD</option>
                        <option value="approved">✅ Active / Approved</option>
                        <option value="repaid">🎉 Fully Repaid</option>
                        <option value="rejected">❌ Rejected</option>
                        <option value="cancelled">🚫 Cancelled</option>
                    </select>

                    <select id="hr-loan-type-filter" onchange="loadHrLoans()">
                        <option value="all">💳 All Request Types</option>
                        <option value="advance_salary">💵 Advance Salary</option>
                        <option value="emergency_loan">🚨 Emergency Loan</option>
                        <option value="medical_aid">🩺 Medical Aid</option>
                    </select>

                    <select id="hr-loan-emp-filter" class="admin-only" onchange="loadHrLoans()">
                        <option value="">👤 All Employees</option>
                    </select>
                </div>
            </div>

            <!-- Loans Table -->
            <div class="table-responsive">
                <table class="interactive-table">
                    <thead>
                        <tr>
                            <th style="width: 20%; min-width: 170px;">Employee</th>
                            <th style="width: 12%; min-width: 120px;">Request Type</th>
                            <th style="width: 12%; min-width: 100px; font-weight: 800;">Total Amount</th>
                            <th style="width: 14%; min-width: 130px;">Repayment Plan</th>
                            <th style="width: 14%; min-width: 130px;">Paid / Remaining</th>
                            <th style="width: 16%; min-width: 150px;">Reason & Notes</th>
                            <th style="width: 6%; min-width: 90px; text-align: center;">Status</th>
                            <th style="width: 6%; min-width: 90px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="hr-loans-table-body">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                Loading loan records...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>


<!-- ================= 3. FUEL, TRAVEL, FOOD & INCENTIVE CLAIMS ================= -->
<section id="tab-hr-claims" class="tab-section">
    <div class="worksheet-container">
        
        <div class="worksheet-card" style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 2px;">
                        ⛽ Fuel, Travel, Food & Incentive Allowances
                    </h2>
                    <p style="color: var(--text-muted); font-size: 12.5px; margin: 0;">
                        Manage employee fuel allowances, travel expenses, food bills reimbursement, overtime meals, and performance incentives.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="btn btn-primary" onclick="openAddClaimModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        + Add Allowance / Claim
                    </button>
                    <button type="button" class="btn btn-outline" onclick="loadHrClaims()" title="Reload claims">
                        🔄 Refresh
                    </button>
                </div>
            </div>

            <!-- Summary KPI metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 14px;">
                <div style="padding: 12px 16px; background: rgba(14, 165, 233, 0.08); border-radius: var(--radius-sm); border: 1px solid rgba(14, 165, 233, 0.2);">
                    <div style="font-size: 11px; font-weight: 800; color: #0284c7; text-transform: uppercase;">⛽ Fuel / Travel Approved</div>
                    <div id="hr-claims-total-fuel" style="font-size: 19px; font-weight: 800; color: #0284c7; margin-top: 2px;">PKR 0</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Fuel & travel allowances</div>
                </div>

                <div style="padding: 12px 16px; background: rgba(16, 185, 129, 0.08); border-radius: var(--radius-sm); border: 1px solid rgba(16, 185, 129, 0.2);">
                    <div style="font-size: 11px; font-weight: 800; color: #059669; text-transform: uppercase;">🍲 Food Bills Approved</div>
                    <div id="hr-claims-total-food" style="font-size: 19px; font-weight: 800; color: #059669; margin-top: 2px;">PKR 0</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Meal reimbursements</div>
                </div>

                <div style="padding: 12px 16px; background: rgba(139, 92, 246, 0.08); border-radius: var(--radius-sm); border: 1px solid rgba(139, 92, 246, 0.2);">
                    <div style="font-size: 11px; font-weight: 800; color: #7c3aed; text-transform: uppercase;">🏆 Incentives & Bonus</div>
                    <div id="hr-claims-total-incentive" style="font-size: 19px; font-weight: 800; color: #7c3aed; margin-top: 2px;">PKR 0</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Performance rewards</div>
                </div>

                <div style="padding: 12px 16px; background: rgba(245, 158, 11, 0.08); border-radius: var(--radius-sm); border: 1px solid rgba(245, 158, 11, 0.2);">
                    <div style="font-size: 11px; font-weight: 800; color: #d97706; text-transform: uppercase;">⏳ Pending Review</div>
                    <div id="hr-claims-total-pending" style="font-size: 19px; font-weight: 800; color: #d97706; margin-top: 2px;">0 Claims</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Awaiting HR approval</div>
                </div>
            </div>
        </div>

        <div class="worksheet-card">
            <!-- Modern Responsive Filter Toolbar -->
            <div class="modern-filter-toolbar">
                <!-- Row 1: Search, Month/Date Range & Actions -->
                <div class="filter-row-primary">
                    <div class="filter-search-wrap">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="hr-claims-search" placeholder="Search employee, category, receipt, route, notes..." oninput="applyClaimsClientFilters()">
                    </div>

                    <div class="filter-date-group">
                        <span class="date-label">📅 Salary Month:</span>
                        <input type="month" id="hr-claims-month-filter" value="<?php echo date('Y-m'); ?>" onchange="loadHrClaims()" style="background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 4px 7px; font-size: 12px; font-weight: 600; color: var(--text-main); cursor: pointer; width: 140px;">
                    </div>

                    <div class="filter-date-group">
                        <span class="date-label">🗓️ Dates:</span>
                        <input type="date" id="hr-claims-date-from" title="From Date" onchange="loadHrClaims()" oninput="applyClaimsClientFilters()">
                        <span class="date-sep">to</span>
                        <input type="date" id="hr-claims-date-to" title="To Date" onchange="loadHrClaims()" oninput="applyClaimsClientFilters()">
                        <button type="button" id="hr-claims-date-clear-btn" class="date-clear-btn" style="display: none;" onclick="clearClaimsDates()" title="Clear dates">✕</button>
                    </div>

                    <div class="filter-toolbar-actions">
                        <button type="button" id="hr-claims-reset-filters-btn" class="btn btn-outline" style="padding: 5px 10px; font-size: 11.5px; font-weight: 700; border-radius: var(--radius-md); display: none; color: #ef4444; border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.05);" onclick="resetClaimsFilters()" title="Reset all filters">
                            ↺ Reset
                        </button>
                        <span id="hr-claims-count-label" class="filter-count-chip">
                            Showing 0 claim(s)
                        </span>
                    </div>
                </div>

                <!-- Row 2: Secondary Dropdown Filters -->
                <div class="filter-row-secondary">
                    <select id="hr-claims-dept-filter" onchange="applyClaimsClientFilters()">
                        <option value="">🏢 All Departments</option>
                    </select>

                    <select id="hr-claims-type-filter" onchange="loadHrClaims()">
                        <option value="all">⛽ All Claim Types</option>
                        <option value="fuel">⛽ Fuel & Mileage</option>
                        <option value="travel">✈️ Travel / Official Trip</option>
                        <option value="mobile">📱 Mobile / Internet</option>
                        <option value="food_bills">🍲 Food Bills & Meals</option>
                        <option value="incentive">🏆 Incentive / Reward</option>
                        <option value="bonus">🎁 Bonus & Others</option>
                    </select>

                    <select id="hr-claims-status-filter" onchange="loadHrClaims()">
                        <option value="all">All Statuses</option>
                        <option value="pending">⏳ Pending Review</option>
                        <option value="approved" selected>✅ Approved</option>
                        <option value="paid">💵 Paid</option>
                        <option value="rejected">❌ Rejected</option>
                    </select>

                    <select id="hr-claims-emp-filter" class="admin-only" onchange="loadHrClaims()">
                        <option value="">👤 All Employees</option>
                    </select>
                </div>
            </div>

            <!-- Claims Table -->
            <div class="table-responsive">
                <table class="interactive-table">
                    <thead>
                        <tr>
                            <th style="width: 22%; min-width: 180px;">Employee</th>
                            <th style="width: 14%; min-width: 130px;">Category</th>
                            <th style="width: 12%; min-width: 100px; font-weight: 800;">Amount (PKR)</th>
                            <th style="width: 12%; min-width: 110px;">Date & Month</th>
                            <th style="width: 22%; min-width: 180px;">Details / Route / Receipt</th>
                            <th style="width: 8%; min-width: 90px; text-align: center;">Status</th>
                            <th style="width: 10%; min-width: 95px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="hr-claims-table-body">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                Loading allowance and claim records...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>


<!-- ================= 4. DEDICATED COMPANY NOTICE BOARD ================= -->
<section id="tab-hr-notices" class="tab-section">
    <div class="worksheet-container">
        
        <div class="worksheet-card" style="margin-bottom: 20px;">
            <!-- Notice Board Header (Properly closed flex row) -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 18px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="font-size: 26px;">📢</div>
                    <div>
                        <h2 style="font-size: 19px; font-weight: 800; color: var(--text-main); margin: 0 0 2px 0;">
                            Company Notice Board & Broadcasts
                        </h2>
                        <p style="color: var(--text-muted); font-size: 12.5px; margin: 0;">
                            Official announcements, policy circulars, urgent alerts, and event updates.
                        </p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" class="btn btn-primary admin-only" onclick="openCreateNoticeModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        + Post Announcement
                    </button>
                    <button type="button" class="btn btn-outline" onclick="loadHrNotices()" title="Reload notices">
                        🔄 Refresh
                    </button>
                </div>
            </div>

            <!-- Unified Filter Toolbar (Full width) -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; background: var(--bg-card-elevated); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); width: 100%;">
                <!-- Search Box -->
                <div style="position: relative; flex: 1; min-width: 240px; max-width: 360px;">
                    <input type="text" id="hr-notice-search" class="input-control" placeholder="🔍 Search notices, announcements..." style="width: 100%; padding: 8px 14px; font-size: 12.5px; border-radius: var(--radius-md);" oninput="applyNoticesClientFilters()">
                </div>

                <!-- Dropdown Filter Pills -->
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                    <select id="hr-notice-dept-filter" class="input-control" style="width: auto; padding: 8px 12px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyNoticesClientFilters()">
                        <option value="">🏢 All Departments</option>
                    </select>

                    <select id="hr-notice-priority-filter" class="input-control" style="width: auto; padding: 8px 12px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyNoticesClientFilters()">
                        <option value="all">📢 All Priorities</option>
                        <option value="urgent">🚨 Urgent / Important</option>
                        <option value="holiday">🎉 Holiday Schedule</option>
                        <option value="event">🏆 Company Event</option>
                        <option value="normal">📢 General Notice</option>
                    </select>

                    <button type="button" id="hr-notice-reset-filters-btn" class="btn btn-outline" style="padding: 8px 12px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md); display: none; color: var(--text-muted);" onclick="resetNoticesFilters()" title="Reset all filters">
                        ↺ Reset
                    </button>
                    
                    <span style="font-size: 12px; color: var(--text-muted); font-weight: 600; padding-left: 4px;" id="hr-notices-count-label">
                        Showing 0 notices
                    </span>
                </div>
            </div>

            <!-- Feed Grid (Full width across card) -->
            <div id="hr-notices-feed" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 16px; width: 100%;">
                <div style="text-align: center; padding: 50px 20px; color: var(--text-muted); grid-column: 1 / -1; width: 100%;">
                    Loading company notices...
                </div>
            </div>
        </div>

    </div>
</section>


<!-- ================= 5. DEDICATED DISCIPLINARY FINES & PENALTIES ================= -->
<section id="tab-hr-fines" class="tab-section">
    <div class="worksheet-container">

        <!-- Fines & Disciplinary Header Card -->
        <div class="worksheet-card" style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 2px; display: flex; align-items: center; gap: 8px;">
                        ⚠️ Disciplinary & Show Cause Inquiry Center
                    </h2>
                    <p style="color: var(--text-muted); font-size: 12.5px; margin: 0;">
                        Issue formal show cause notices, receive employee explanations, conduct inquiry reviews, and enforce payroll fine deductions.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn" onclick="openIssueShowCauseModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; background: #d97706; color: #ffffff; border: 1px solid #b45309; box-shadow: 0 1px 3px rgba(217, 119, 6, 0.3);">
                        📜 Issue Show Cause Notice
                    </button>
                    <button type="button" class="btn btn-primary" onclick="openIssueFineModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        ➕ Issue Direct Fine
                    </button>
                    <button type="button" class="btn btn-outline" onclick="refreshDisciplinaryCenter()" title="Refresh All Disciplinary Records">
                        🔄 Refresh
                    </button>
                </div>
            </div>

            <!-- Disciplinary Summary Metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-top: 16px;">
                <div style="padding: 14px 18px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(217, 119, 6, 0.03) 100%); border-radius: var(--radius-md); border: 1px solid rgba(245, 158, 11, 0.28); box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 11px; font-weight: 800; color: #d97706; text-transform: uppercase; letter-spacing: 0.5px;">Show Cause Inquiries</span>
                        <span style="font-size: 16px;">📜</span>
                    </div>
                    <div id="hr-sc-stat-total" style="font-size: 22px; font-weight: 800; color: #b45309; margin-top: 4px; line-height: 1.2;">0 Inquiries</div>
                    <div id="hr-sc-stat-sub" style="font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 500;">0 pending employee reply</div>
                </div>

                <div style="padding: 14px 18px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(220, 38, 38, 0.03) 100%); border-radius: var(--radius-md); border: 1px solid rgba(239, 68, 68, 0.25); box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 11px; font-weight: 800; color: #dc2626; text-transform: uppercase; letter-spacing: 0.5px;">Active Fine Deductions</span>
                        <span style="font-size: 16px;">🔴</span>
                    </div>
                    <div id="hr-fines-total-applied-amount" style="font-size: 22px; font-weight: 800; color: #dc2626; margin-top: 4px; line-height: 1.2;">PKR 0</div>
                    <div id="hr-fines-total-applied-count" style="font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 500;">0 penalties applied to payroll</div>
                </div>

                <div style="padding: 14px 18px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.03) 100%); border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.25); box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 11px; font-weight: 800; color: #059669; text-transform: uppercase; letter-spacing: 0.5px;">Waived / Cleared Cases</span>
                        <span style="font-size: 16px;">🟢</span>
                    </div>
                    <div id="hr-fines-total-waived-amount" style="font-size: 22px; font-weight: 800; color: #059669; margin-top: 4px; line-height: 1.2;">PKR 0</div>
                    <div id="hr-fines-total-waived-count" style="font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 500;">0 forgiven penalties</div>
                </div>

                <div style="padding: 14px 18px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(37, 99, 235, 0.03) 100%); border-radius: var(--radius-md); border: 1px solid rgba(59, 130, 246, 0.25); box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 11px; font-weight: 800; color: #2563eb; text-transform: uppercase; letter-spacing: 0.5px;">Total Disciplinary Actions</span>
                        <span style="font-size: 16px;">📋</span>
                    </div>
                    <div id="hr-fines-total-count" style="font-size: 22px; font-weight: 800; color: #2563eb; margin-top: 4px; line-height: 1.2;">0 Records</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 500;">Logged incidents & inquiries</div>
                </div>
            </div>
        </div>

        <!-- Disciplinary Center Tabs Card -->
        <div class="worksheet-card">
            <!-- Sub-Navigation View Toggle -->
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
                <div style="display: inline-flex; background: var(--bg-card-elevated); padding: 4px; border-radius: var(--radius-md); border: 1px solid var(--border-color); gap: 6px;">
                    <button type="button" id="hr-disc-tab-btn-showcause" class="btn btn-primary" onclick="switchDisciplinarySubView('showcause')" style="font-weight: 700; font-size: 12.5px; border-radius: var(--radius-sm); padding: 7px 16px; border: none; background: #d97706; color: #ffffff; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(217, 119, 6, 0.3);">
                        📜 Show Cause Notices & Inquiries 
                        <span id="hr-sc-pending-badge" style="background: rgba(255,255,255,0.28); color: #fff; padding: 1px 7px; border-radius: 10px; font-size: 11px; font-weight: 800;">0</span>
                    </button>
                    <button type="button" id="hr-disc-tab-btn-fines" class="btn btn-outline" onclick="switchDisciplinarySubView('fines')" style="font-weight: 600; font-size: 12.5px; border-radius: var(--radius-sm); padding: 7px 16px; border: 1px solid transparent; background: transparent; color: var(--text-muted); cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;">
                        ⚠️ Disciplinary Fines & Deductions 
                        <span id="hr-fines-count-badge" style="background: rgba(0,0,0,0.06); color: var(--text-muted); padding: 1px 7px; border-radius: 10px; font-size: 11px; font-weight: 700;">0</span>
                    </button>
                </div>
            </div>

            <!-- SUBVIEW 1: SHOW CAUSE NOTICES & INQUIRIES -->
            <div id="hr-disc-view-showcause">
                <!-- Unified Filter Toolbar for Show Cause Notices -->
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; background: var(--bg-card-elevated); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                    <!-- Search Box (Flex-1 so it contracts gracefully when Reset button appears) -->
                    <div style="position: relative; flex: 1 1 180px; min-width: 150px;">
                        <input type="text" id="hr-sc-search" class="input-control" placeholder="🔍 Search employee, notice, allegation..." style="width: 100%; padding: 7px 12px; font-size: 12px; border-radius: var(--radius-md);" oninput="applyShowCauseClientFilters()">
                    </div>

                    <input type="month" id="hr-sc-month-filter" class="input-control" style="width: auto; padding: 6px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" value="<?php echo date('Y-m'); ?>" onchange="loadHrShowCauseNotices()">

                    <select id="hr-sc-dept-filter" class="input-control" style="width: auto; padding: 7px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyShowCauseClientFilters()">
                        <option value="">🏢 All Departments</option>
                    </select>

                    <select id="hr-sc-status-filter" class="input-control" style="width: auto; padding: 7px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="loadHrShowCauseNotices()">
                        <option value="all" selected>All Inquiry Statuses</option>
                        <option value="pending_reply">⏳ Awaiting Employee Reply</option>
                        <option value="under_review">🔍 Response Under Review</option>
                        <option value="satisfied_closed">🟢 Explanation Accepted (No Fine)</option>
                        <option value="fine_issued">🔴 Fine Imposed</option>
                    </select>

                    <select id="hr-sc-cat-filter" class="input-control" style="width: auto; padding: 7px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="loadHrShowCauseNotices()">
                        <option value="all">⚠️ All Violations</option>
                        <option value="late_arrival">⏱️ Late Arrival</option>
                        <option value="unauthorized_absence">🚫 Unauthorized Absence</option>
                        <option value="sop_violation">⚠️ SOP / Policy Violation</option>
                        <option value="negligence">⚠️ Negligence / Damage</option>
                        <option value="misconduct">🛑 Misconduct</option>
                        <option value="other">📝 Other Reason</option>
                    </select>

                    <button type="button" id="hr-sc-reset-filters-btn" class="btn btn-outline" style="padding: 6px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md); display: none; color: var(--text-muted); white-space: nowrap; flex-shrink: 0;" onclick="resetShowCauseFilters()" title="Reset all filters">
                        ↺ Reset
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="interactive-table">
                        <thead>
                            <tr>
                                <th style="width: 18%; min-width: 170px;">Employee</th>
                                <th style="width: 9%; min-width: 90px;">Notice Date</th>
                                <th style="width: 16%; min-width: 140px;">Violation Category & Title</th>
                                <th style="width: 21%; min-width: 180px;">Allegation & Incident Details</th>
                                <th style="width: 9%; min-width: 85px;">Reply Deadline</th>
                                <th style="width: 14%; min-width: 130px;">Employee Explanation</th>
                                <th style="width: 13%; min-width: 110px; text-align: center;">Inquiry Status</th>
                                <th style="width: 10%; min-width: 100px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="hr-sc-table-body">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    Loading show cause notices...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SUBVIEW 2: DIRECT FINES & DEDUCTIONS -->
            <div id="hr-disc-view-fines" style="display: none;">
                <!-- Unified Filter Toolbar for Fines -->
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; background: var(--bg-card-elevated); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                    <!-- Search Box (Flex-1 so it contracts gracefully when Reset button appears) -->
                    <div style="position: relative; flex: 1 1 180px; min-width: 150px;">
                        <input type="text" id="hr-fines-search" class="input-control" placeholder="🔍 Search employee, violation, reason..." style="width: 100%; padding: 7px 12px; font-size: 12px; border-radius: var(--radius-md);" oninput="applyFinesClientFilters()">
                    </div>

                    <input type="month" id="hr-fines-month-filter" class="input-control" style="width: auto; padding: 6px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" value="<?php echo date('Y-m'); ?>" onchange="loadHrFines()">

                    <select id="hr-fines-dept-filter" class="input-control" style="width: auto; padding: 7px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyFinesClientFilters()">
                        <option value="">🏢 All Departments</option>
                    </select>

                    <select id="hr-fines-status-filter" class="input-control" style="width: auto; padding: 7px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="loadHrFines()">
                        <option value="all" selected>All Fine Statuses</option>
                        <option value="applied">🔴 Applied</option>
                        <option value="waived">🟢 Waived</option>
                    </select>

                    <select id="hr-fines-cat-filter" class="input-control" style="width: auto; padding: 7px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="loadHrFines()">
                        <option value="all">⚠️ All Violations</option>
                        <option value="late_arrival">⏱️ Late Arrival</option>
                        <option value="unauthorized_absence">🚫 Unauthorized Absence</option>
                        <option value="sop_violation">⚠️ SOP / Policy Violation</option>
                        <option value="negligence">⚠️ Negligence / Damage</option>
                        <option value="misconduct">🛑 Misconduct</option>
                        <option value="other">📝 Other Reason</option>
                    </select>

                    <button type="button" id="hr-fines-reset-filters-btn" class="btn btn-outline" style="padding: 6px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md); display: none; color: var(--text-muted); white-space: nowrap; flex-shrink: 0;" onclick="resetFinesFilters()" title="Reset all filters">
                        ↺ Reset
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="interactive-table">
                        <thead>
                            <tr>
                                <th style="width: 20%; min-width: 170px;">Employee</th>
                                <th style="width: 10%; min-width: 95px;">Incident Date</th>
                                <th style="width: 14%; min-width: 130px;">Violation Category</th>
                                <th style="width: 22%; min-width: 180px;">Reason & Incident Details</th>
                                <th style="width: 10%; min-width: 90px;">Salary Month</th>
                                <th style="width: 10%; min-width: 95px;">Fine Amount</th>
                                <th style="width: 8%; min-width: 85px; text-align: center;">Status</th>
                                <th style="width: 6%; min-width: 75px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="hr-fines-table-body">
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                    Loading disciplinary fine records...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</section>


<!-- ================= 6. DEDICATED MONTHLY PAYROLL & SALARIES ================= -->
<section id="tab-hr-payroll" class="tab-section">
    <div class="worksheet-container">

        <div class="worksheet-card" style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 2px;">
                        💰 Monthly Payroll & Compensation Command Center
                    </h2>
                    <p style="color: var(--text-muted); font-size: 12.5px; margin: 0;">
                        Auto-calculated based on daily attendance records, duty hours, approved leaves, active loan deductions, and disciplinary fines.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <label for="hr-payroll-month" style="font-size: 12px; font-weight: 700; color: var(--text-muted);">Salary Month:</label>
                    <input type="month" id="hr-payroll-month" class="input-control" value="<?php echo date('Y-m'); ?>" style="font-size: 13px; font-weight: 700; padding: 6px 12px; border-radius: var(--radius-md);" onchange="loadHrPayroll()">
                    <button type="button" class="btn btn-outline" onclick="loadHrPayroll()" title="Recalculate stats" style="padding: 6px 12px; font-size: 12.5px; font-weight: 600;">
                        🔄 Recalculate
                    </button>
                    <button type="button" class="btn btn-outline" onclick="exportPayrollCSV()" style="padding: 6px 12px; font-size: 12.5px; font-weight: 700; color: #10b981; border-color: rgba(16, 185, 129, 0.4);" title="Export complete monthly payroll sheet to CSV / Excel">
                        📊 Export CSV / Excel
                    </button>
                </div>
            </div>

            <!-- Payroll Quick Summary Stats Row -->
            <div id="payroll-summary-bar" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 14px;">
                <div style="background: var(--bg-card-elevated); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Staff</div>
                    <div id="payroll-stat-total-emp" style="font-size: 17px; font-weight: 800; color: var(--text-main); margin-top: 2px;">0</div>
                </div>
                <div style="background: var(--bg-card-elevated); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Gross Pay</div>
                    <div id="payroll-stat-total-gross" style="font-size: 17px; font-weight: 800; color: var(--text-main); margin-top: 2px;">PKR 0</div>
                </div>
                <div style="background: var(--bg-card-elevated); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Total Deductions</div>
                    <div id="payroll-stat-total-deductions" style="font-size: 17px; font-weight: 800; color: #ef4444; margin-top: 2px;">PKR 0</div>
                </div>
                <div style="background: var(--bg-card-elevated); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Net Payout</div>
                    <div id="payroll-stat-total-net" style="font-size: 17px; font-weight: 800; color: #059669; margin-top: 2px;">PKR 0</div>
                </div>
            </div>

            <!-- Single-Row Unified Filter Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; background: var(--bg-card-elevated); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <!-- Search Box -->
                <div style="position: relative; flex: 1; min-width: 220px; max-width: 320px;">
                    <input type="text" id="hr-payroll-search" class="input-control" placeholder="🔍 Search staff, code, CNIC, designation..." style="width: 100%; padding: 7px 12px; font-size: 12.5px; border-radius: var(--radius-md);" oninput="applyPayrollFilters()">
                </div>

                <!-- Dropdown Filter Pills -->
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                    <select id="hr-payroll-filter-dept" class="input-control" style="width: auto; padding: 7px 11px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyPayrollFilters()">
                        <option value="">🏢 All Departments</option>
                    </select>

                    <select id="hr-payroll-filter-status" class="input-control" style="width: auto; padding: 7px 11px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyPayrollFilters()">
                        <option value="">💳 All Payment Status</option>
                        <option value="draft">⏳ Draft</option>
                        <option value="approved">🔵 Approved</option>
                        <option value="paid">✅ Paid</option>
                    </select>

                    <select id="hr-payroll-filter-bank" class="input-control" style="width: auto; padding: 7px 11px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyPayrollFilters()">
                        <option value="">🏦 All Banks / Cash</option>
                        <option value="UBL">🏦 UBL</option>
                        <option value="Meezan Bank">🏦 Meezan</option>
                        <option value="HBL">🏦 HBL</option>
                        <option value="MCB">🏦 MCB</option>
                        <option value="Allied Bank">🏦 Allied</option>
                        <option value="Bank Alfalah">🏦 Alfalah</option>
                        <option value="Faysal Bank">🏦 Faysal</option>
                        <option value="Cash">💵 Cash</option>
                    </select>

                    <button type="button" id="hr-payroll-reset-filters-btn" class="btn btn-outline" style="padding: 7px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md); display: none; color: var(--text-muted);" onclick="resetPayrollFilters()" title="Reset all filters">
                        ↺ Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="worksheet-card">
            <div class="table-responsive">
                <table class="interactive-table">
                    <thead>
                        <tr>
                            <th style="min-width: 150px;">Staff & Code</th>
                            <th style="min-width: 140px;">Designation</th>
                            <th style="min-width: 130px;">Father / CNIC</th>
                            <th style="text-align: center; min-width: 110px;" title="Approved Paid & Unpaid Leaves">Leaves / P. Leaves</th>
                            <th style="min-width: 100px;">Gross Salary</th>
                            <th style="min-width: 90px;">Fuel / Travel</th>
                            <th style="min-width: 95px;">Incentive / Food</th>
                            <th style="min-width: 100px;">Advance / Loan</th>
                            <th style="min-width: 85px; color: #ef4444;">Fines</th>
                            <th style="min-width: 80px;">WHT</th>
                            <th style="min-width: 110px; font-weight: 800;">Net Salary</th>
                            <th style="min-width: 130px;">Bank / Form #</th>
                            <th style="text-align: center; min-width: 85px;">Status</th>
                            <th style="text-align: right; min-width: 85px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="hr-payroll-table-body">
                        <tr>
                            <td colspan="14" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                Select month to calculate payroll...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>


<!-- ================= MODALS FOR HR OPERATIONS ================= -->

<!-- 1. Apply Leave Modal -->
<div id="hr-apply-leave-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title">🏖️ Apply for Leave</h3>
            <button type="button" class="modal-close" onclick="closeModal('hr-apply-leave-modal')">&times;</button>
        </div>
        <form id="hr-apply-leave-form" onsubmit="handleApplyLeaveSubmit(event)">
            <div class="modal-body">
                <div id="hr-leave-form-emp-wrapper" class="form-group" style="margin-bottom: 14px; display: none;">
                    <label class="form-label">Employee</label>
                    <select id="hr-leave-form-emp-id" class="input-control" style="width: 100%;">
                        <!-- Dynamic Employees -->
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Leave Type</label>
                    <select id="hr-leave-form-type" class="input-control" style="width: 100%; font-weight: 600;" required>
                        <option value="casual">🌴 Casual Leave</option>
                        <option value="sick">🩺 Sick Leave</option>
                        <option value="annual">🏖️ Annual Leave</option>
                        <option value="unpaid">🚫 Unpaid Leave</option>
                        <option value="other">📝 Other</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" id="hr-leave-form-start-date" class="input-control" style="width: 100%;" required onchange="calculateLeaveFormDays()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" id="hr-leave-form-end-date" class="input-control" style="width: 100%;" required onchange="calculateLeaveFormDays()">
                    </div>
                </div>

                <div style="margin-bottom: 14px; font-size: 12.5px; color: var(--primary); font-weight: 700;" id="hr-leave-form-calculated-days">
                    Duration: 1 Day(s)
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Reason / Notes</label>
                    <textarea id="hr-leave-form-reason" class="input-control" rows="3" placeholder="Explain the reason for leave request..." style="width: 100%; resize: vertical;" required></textarea>
                </div>

                <div id="hr-leave-form-auto-approve-wrapper" class="form-group" style="margin-bottom: 10px; display: none;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer;">
                        <input type="checkbox" id="hr-leave-form-auto-approve" value="1">
                        <b>Directly approve this leave application</b>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-apply-leave-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Application</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Apply Advance / Loan Modal -->
<div id="hr-apply-loan-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">💳 Request Salary Advance / Loan</h3>
            <button type="button" class="modal-close" onclick="closeModal('hr-apply-loan-modal')">&times;</button>
        </div>
        <form id="hr-apply-loan-form" onsubmit="handleApplyLoanSubmit(event)">
            <div class="modal-body">
                <div id="hr-loan-form-emp-wrapper" class="form-group" style="margin-bottom: 14px; display: none;">
                    <label class="form-label">Employee</label>
                    <select id="hr-loan-form-emp-id" class="input-control" style="width: 100%;">
                        <!-- Dynamic Employees -->
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Request Type</label>
                    <select id="hr-loan-form-type" class="input-control" style="width: 100%; font-weight: 600;" required>
                        <option value="advance_salary">💵 Advance Salary (Deducted from upcoming payroll)</option>
                        <option value="emergency_loan">🚨 Emergency Loan (Multi-month installment)</option>
                        <option value="medical_aid">🩺 Medical Aid / Health Support</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Requested Amount (PKR)</label>
                        <input type="number" step="any" min="0" id="hr-loan-form-amount" class="input-control" placeholder="e.g. 25000" style="width: 100%; font-weight: 700;" required oninput="calculateLoanFormDeduction()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Repayment Tenure (Months)</label>
                        <select id="hr-loan-form-months" class="input-control" style="width: 100%; font-weight: 600;" onchange="calculateLoanFormDeduction()">
                            <option value="1">1 Month (Full Deduction)</option>
                            <option value="2">2 Months</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">12 Months</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Deduction Starting Month</label>
                    <input type="month" id="hr-loan-form-start-month" class="input-control" style="width: 100%; font-weight: 700;" required>
                </div>

                <div style="padding: 10px 14px; background: rgba(59, 130, 246, 0.08); border-radius: var(--radius-sm); border: 1px solid rgba(59, 130, 246, 0.2); margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 12.5px; font-weight: 700; color: var(--text-main);">Estimated Monthly Installment:</span>
                    <span id="hr-loan-form-monthly-display" style="font-size: 15px; font-weight: 800; color: var(--primary);">PKR 0.00 / mo</span>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Reason / Emergency Purpose</label>
                    <textarea id="hr-loan-form-reason" class="input-control" rows="3" placeholder="Explain the purpose for this advance / loan request..." style="width: 100%; resize: vertical;" required></textarea>
                </div>

                <div id="hr-loan-form-auto-approve-wrapper" class="form-group" style="margin-bottom: 10px; display: none;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer;">
                        <input type="checkbox" id="hr-loan-form-auto-approve" value="1">
                        <b>Directly approve and activate this loan request</b>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-apply-loan-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Create Notice Modal (Admin Only) -->
<div id="hr-create-notice-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 550px;">
        <div class="modal-header">
            <h3 class="modal-title">📢 Post Announcement / Notice</h3>
            <button type="button" class="modal-close" onclick="closeModal('hr-create-notice-modal')">&times;</button>
        </div>
        <form id="hr-create-notice-form" onsubmit="handleSaveNoticeSubmit(event)">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Notice Title</label>
                    <input type="text" id="hr-notice-title" class="input-control" placeholder="e.g. Eid-ul-Fitr Holiday Schedule" style="width: 100%; font-weight: 700;" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Priority / Category</label>
                        <select id="hr-notice-priority" class="input-control" style="width: 100%; font-weight: 600;">
                            <option value="normal">Normal Announcement</option>
                            <option value="urgent">🚨 Urgent / Important</option>
                            <option value="holiday">🎉 Holiday / Office Closed</option>
                            <option value="event">🏆 Event / Company Activity</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Department</label>
                        <select id="hr-notice-target-dept" class="input-control" style="width: 100%;">
                            <option value="">🏢 All Departments</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Announcement Content</label>
                    <textarea id="hr-notice-message" class="input-control" rows="4" placeholder="Write the full message details for the workforce..." style="width: 100%; resize: vertical;" required></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 6px;">
                    <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>📎 Attachment (PDF, Document, Image)</span>
                        <span id="hr-notice-file-info" style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Optional</span>
                    </label>
                    <div style="border: 2px dashed var(--border-color); border-radius: var(--radius-md); padding: 14px; text-align: center; background: var(--bg-card-elevated); cursor: pointer; transition: border-color 0.2s;" onclick="document.getElementById('hr-notice-file').click()" id="hr-notice-dropzone">
                        <input type="file" id="hr-notice-file" style="display: none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.txt,.zip" onchange="handleNoticeFileSelected(this)">
                        <div id="hr-notice-file-display">
                            <span style="font-size: 20px; display: block; margin-bottom: 4px;">📄</span>
                            <span style="font-size: 12.5px; font-weight: 700; color: var(--primary);">Choose File (PDF, DOCX, Images)</span>
                            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">Max 25MB • Circulars, policies, memos</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-create-notice-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 700;">📢 Broadcast Notice</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Edit Payroll Item Modal (Admin Only) -->
<div id="hr-edit-payroll-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title">💰 Edit Payroll Adjustments</h3>
            <button type="button" class="modal-close" onclick="closeModal('hr-edit-payroll-modal')">&times;</button>
        </div>
        <form id="hr-edit-payroll-form" onsubmit="handleSavePayrollItemSubmit(event)">
            <input type="hidden" id="hr-pay-emp-id">
            <input type="hidden" id="hr-pay-month">
            <div class="modal-body">
                <div style="padding: 10px; background: var(--bg-card-elevated); border-radius: var(--radius-md); margin-bottom: 14px;">
                    <div id="hr-pay-emp-name-display" style="font-weight: 700; font-size: 14px;">Employee Name</div>
                    <div id="hr-pay-month-display" style="font-size: 12px; color: var(--primary); font-weight: 600;">Month: 2026-09</div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Basic Salary (PKR)</label>
                    <input type="number" step="any" min="0" id="hr-pay-basic-salary" class="input-control" style="width: 100%; font-weight: 700;" required oninput="calculateModalNetSalary()">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label class="form-label">Bonus (PKR)</label>
                        <input type="number" step="any" min="0" id="hr-pay-bonus" class="input-control" value="0.00" style="width: 100%; color: #10b981; font-weight: 700;" oninput="calculateModalNetSalary()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-size: 11px;">Bonus Reason</label>
                        <input type="text" id="hr-pay-bonus-reason" class="input-control" placeholder="e.g. Performance / Overtime" style="width: 100%;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label class="form-label">Deductions / Loans (PKR)</label>
                        <input type="number" step="any" min="0" id="hr-pay-deductions" class="input-control" value="0.00" style="width: 100%; color: #ef4444; font-weight: 700;" oninput="calculateModalNetSalary()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-size: 11px;">Deduction Reason</label>
                        <input type="text" id="hr-pay-deduction-reason" class="input-control" placeholder="e.g. Loan repayment" style="width: 100%;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label class="form-label" style="color: #ef4444;">⚠️ Disciplinary Fines (PKR)</label>
                        <input type="number" step="any" min="0" id="hr-pay-fines" class="input-control" value="0.00" style="width: 100%; color: #ef4444; font-weight: 700; border-color: rgba(239, 68, 68, 0.4);" oninput="calculateModalNetSalary()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-size: 11px; color: #ef4444;">Fine Reason / Notes</label>
                        <input type="text" id="hr-pay-fine-reason" class="input-control" placeholder="e.g. Late Arrival / Violation" style="width: 100%;">
                    </div>
                </div>

                <div style="padding: 10px 14px; background: rgba(16, 185, 129, 0.1); border-radius: var(--radius-sm); border: 1px solid rgba(16, 185, 129, 0.3); margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 13px; font-weight: 700; color: var(--text-main);">Computed Net Pay:</span>
                    <span id="hr-pay-net-salary-display" style="font-size: 16px; font-weight: 800; color: #10b981;">PKR 0.00</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
                    <div class="form-group">
                        <label class="form-label">Payment Status</label>
                        <select id="hr-pay-status" class="input-control" style="width: 100%; font-weight: 600;">
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Method</label>
                        <input type="text" id="hr-pay-method" class="input-control" value="Bank Transfer" style="width: 100%;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-edit-payroll-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Payroll Item</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Issue Disciplinary Fine Modal -->
<div id="hr-issue-fine-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">⚠️ Issue Disciplinary Fine / Penalty</h3>
            <button type="button" class="modal-close" onclick="closeModal('hr-issue-fine-modal')">&times;</button>
        </div>
        <form id="hr-issue-fine-form" onsubmit="handleIssueFineSubmit(event)">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Employee</label>
                    <select id="hr-fine-form-emp-id" class="input-control" style="width: 100%;" required>
                        <!-- Dynamic Staff List -->
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Incident Date</label>
                        <input type="date" id="hr-fine-form-date" class="input-control" style="width: 100%;" required onchange="updateFineFormSalaryMonth()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Salary Deduction Month</label>
                        <input type="month" id="hr-fine-form-month" class="input-control" style="width: 100%; font-weight: 600;" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Violation Category</label>
                        <select id="hr-fine-form-category" class="input-control" style="width: 100%; font-weight: 600;" required>
                            <option value="late_arrival">⏱️ Late Arrival</option>
                            <option value="unauthorized_absence">🚫 Unauthorized Absence</option>
                            <option value="sop_violation" selected>⚠️ SOP / Policy Violation</option>
                            <option value="negligence">⚠️ Negligence / Damage</option>
                            <option value="misconduct">🛑 Misconduct</option>
                            <option value="other">📝 Other Reason</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fine Amount (PKR)</label>
                        <input type="number" step="any" min="0" id="hr-fine-form-amount" class="input-control" placeholder="e.g. 1000" style="width: 100%; font-weight: 700; color: #ef4444;" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Reason & Violation Incident Details</label>
                    <textarea id="hr-fine-form-reason" class="input-control" rows="3" placeholder="Explain the exact incident, policy breach, or disciplinary notice..." style="width: 100%; resize: vertical;" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-issue-fine-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #dc2626; border-color: #dc2626;">⚠️ Issue Disciplinary Fine</button>
            </div>
        </form>
    </div>
</div>

<!-- 6. Add Fuel, Travel, Food & Incentive Claim Modal -->
<div id="hr-add-claim-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title">⛽ Add Allowance / Expense Claim</h3>
            <button type="button" class="modal-close" onclick="closeModal('hr-add-claim-modal')">&times;</button>
        </div>
        <form id="hr-add-claim-form" onsubmit="handleSaveClaimSubmit(event)">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Employee</label>
                    <select id="hr-claim-form-emp-id" class="input-control" style="width: 100%;" required>
                        <!-- Dynamic Staff List -->
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Claim / Allowance Category</label>
                        <select id="hr-claim-form-type" class="input-control" style="width: 100%; font-weight: 600;" required>
                            <option value="fuel" selected>⛽ Fuel / Mileage</option>
                            <option value="travel">✈️ Travel / Official Trip</option>
                            <option value="mobile">📱 Mobile / Internet</option>
                            <option value="food_bills">🍲 Food Bills / Meals</option>
                            <option value="incentive">🏆 Performance Incentive</option>
                            <option value="bonus">🎁 Special Bonus</option>
                            <option value="other">📝 Other Allowance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (PKR)</label>
                        <input type="number" step="any" min="0" id="hr-claim-form-amount" class="input-control" placeholder="e.g. 5000" style="width: 100%; font-weight: 700; color: #059669;" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Expense / Claim Date</label>
                        <input type="date" id="hr-claim-form-date" class="input-control" style="width: 100%;" required onchange="updateClaimFormSalaryMonth()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Salary Month to Apply</label>
                        <input type="month" id="hr-claim-form-month" class="input-control" style="width: 100%; font-weight: 600;" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Receipt / Bill # / Route Details</label>
                    <input type="text" id="hr-claim-form-receipt" class="input-control" placeholder="e.g. Slip #9821 / Lahore to Isb route" style="width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Purpose / Description & Remarks</label>
                    <textarea id="hr-claim-form-reason" class="input-control" rows="3" placeholder="Provide full details, client visit, night shift meal, performance target..." style="width: 100%; resize: vertical;" required></textarea>
                </div>

                <div class="admin-only" style="margin-bottom: 10px;">
                    <label style="font-size: 12.5px; display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" id="hr-claim-form-auto-approve" value="1" checked>
                        <b>Directly approve and apply this allowance to payroll</b>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-add-claim-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save & Record Allowance</button>
            </div>
        </form>
    </div>
</div>

<!-- 6. Printable Pay Slip Modal -->
<div id="hr-payslip-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">📄 Official Salary Slip</h3>
            <button type="button" class="modal-close" onclick="closeModal('hr-payslip-modal')">&times;</button>
        </div>
        <div class="modal-body" id="hr-payslip-content" style="background: #ffffff; color: #0f172a; padding: 25px; border-radius: 8px;">
            <!-- Rendered Dynamically -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('hr-payslip-modal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="printPaySlip()">🖨️ Print Slip</button>
        </div>
    </div>
</div>

<!-- ================= 7. SHOW CAUSE NOTICE MODALS ================= -->

<!-- Modal A: Issue Show Cause Notice Modal -->
<div id="hr-issue-showcause-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 560px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">📜 Issue Formal Show Cause Notice</h3>
            <button type="button" class="btn-modal-close" onclick="closeModal('hr-issue-showcause-modal')">&times;</button>
        </div>
        <form id="hr-issue-showcause-form" onsubmit="handleIssueShowCauseSubmit(event)">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Employee</label>
                    <select id="hr-sc-form-emp-id" class="input-control" style="width: 100%; font-weight: 600;" required>
                        <!-- Dynamic Staff List -->
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Notice Date</label>
                        <input type="date" id="hr-sc-form-notice-date" class="input-control" style="width: 100%;" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Incident / Violation Date</label>
                        <input type="date" id="hr-sc-form-incident-date" class="input-control" style="width: 100%; font-weight: 600;" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Violation Category</label>
                        <select id="hr-sc-form-category" class="input-control" style="width: 100%; font-weight: 600;" required>
                            <option value="late_arrival">⏱️ Late Arrival</option>
                            <option value="unauthorized_absence">🚫 Unauthorized Absence</option>
                            <option value="sop_violation" selected>⚠️ SOP / Policy Violation</option>
                            <option value="negligence">⚠️ Negligence / Damage</option>
                            <option value="misconduct">🛑 Misconduct</option>
                            <option value="other">📝 Other Incident</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Response Deadline Date</label>
                        <input type="date" id="hr-sc-form-deadline" class="input-control" style="width: 100%; font-weight: 600;" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Notice Subject / Allegation Title</label>
                    <input type="text" id="hr-sc-form-title" class="input-control" placeholder="e.g. Explanation called regarding unannounced absence on Oct 12" style="width: 100%; font-weight: 600;" required>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Allegation & Incident Details (Fact Finding)</label>
                    <textarea id="hr-sc-form-details" class="input-control" rows="4" placeholder="Detail the specific incident, negligence, time of breach, or failure to perform duties. Ask the employee to submit their written justification before the deadline..." style="width: 100%; resize: vertical;" required></textarea>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">💡 Spaces, line breaks, and paragraphs are preserved. Use <b>**bold text**</b> for emphasis.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-issue-showcause-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #d97706; border-color: #d97706;">📜 Issue Notice to Employee</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal B: Submit Employee Response / Explanation Modal -->
<div id="hr-reply-showcause-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">✍️ Submit Response to Show Cause Notice</h3>
            <button type="button" class="btn-modal-close" onclick="closeModal('hr-reply-showcause-modal')">&times;</button>
        </div>
        <form id="hr-reply-showcause-form" onsubmit="handleSubmitShowCauseReply(event)">
            <input type="hidden" id="hr-reply-sc-notice-id" value="">
            <div class="modal-body">
                <!-- Summary of Allegation -->
                <div id="hr-reply-sc-summary-box" style="background: rgba(217, 119, 6, 0.08); border: 1px solid rgba(217, 119, 6, 0.25); border-radius: var(--radius-sm); padding: 12px 14px; margin-bottom: 16px;">
                    <!-- Rendered Dynamically -->
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label" style="font-weight: 700;">Employee Explanation / Justification *</label>
                    <textarea id="hr-reply-sc-text" class="input-control" rows="6" placeholder="State your detailed clarification, circumstances, evidence, reasons for absence/delay, or defense..." style="width: 100%; resize: vertical;" required></textarea>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">💡 Spaces, line breaks, and paragraphs are preserved. Use <b>**bold text**</b> for emphasis.</div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Supporting Evidence / Attachment (Optional)</label>
                    <input type="file" id="hr-reply-sc-file" class="input-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt" style="width: 100%; padding: 6px;">
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Upload medical certificate, screenshot, gate pass, or signed explanation (Max 20MB).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-reply-showcause-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #2563eb; border-color: #2563eb;">📤 Submit Formal Explanation</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal C: Review & Disciplinary Decision Modal -->
<div id="hr-decide-showcause-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">⚖️ Review & Disciplinary Decision</h3>
            <button type="button" class="btn-modal-close" onclick="closeModal('hr-decide-showcause-modal')">&times;</button>
        </div>
        <form id="hr-decide-showcause-form" onsubmit="handleShowCauseDecisionSubmit(event)">
            <input type="hidden" id="hr-decide-sc-notice-id" value="">
            <div class="modal-body">
                <!-- Case Summary Review Box -->
                <div id="hr-decide-sc-summary-box" style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px 14px; margin-bottom: 16px;">
                    <!-- Rendered Dynamically -->
                </div>

                <!-- Decision Toggle -->
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight: 800; font-size: 13px;">Management Inquiry Findings *</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 6px;">
                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: rgba(16, 185, 129, 0.08); border: 2px solid #10b981; border-radius: var(--radius-sm); cursor: pointer;">
                            <input type="radio" name="hr_sc_decision" value="satisfied" checked onchange="toggleDecisionFineInputs()">
                            <div>
                                <b style="color: #10b981; font-size: 13px;">🟢 Accept Explanation</b>
                                <div style="font-size: 11px; color: var(--text-muted);">Satisfied with reply. Close without fine penalty.</div>
                            </div>
                        </label>

                        <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: rgba(239, 68, 68, 0.08); border: 2px solid #ef4444; border-radius: var(--radius-sm); cursor: pointer;">
                            <input type="radio" name="hr_sc_decision" value="fine_issued" onchange="toggleDecisionFineInputs()">
                            <div>
                                <b style="color: #ef4444; font-size: 13px;">🔴 Not Satisfied</b>
                                <div style="font-size: 11px; color: var(--text-muted);">Reject reply and issue disciplinary payroll fine.</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Conditional Fine Imposition Section -->
                <div id="hr-decide-sc-fine-section" style="display: none; background: rgba(239, 68, 68, 0.05); border: 1px dashed rgba(239, 68, 68, 0.4); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 16px;">
                    <div style="font-size: 12px; font-weight: 800; color: #ef4444; text-transform: uppercase; margin-bottom: 10px;">
                        ⚠️ Disciplinary Fine Imposition Details
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
                        <div class="form-group">
                            <label class="form-label">Fine Amount (PKR) *</label>
                            <input type="number" step="any" min="0" id="hr-decide-sc-fine-amount" class="input-control" placeholder="e.g. 1500" style="width: 100%; font-weight: 800; color: #ef4444;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Deduction Month *</label>
                            <input type="month" id="hr-decide-sc-fine-month" class="input-control" style="width: 100%; font-weight: 600;" value="<?php echo date('Y-m'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select id="hr-decide-sc-fine-cat" class="input-control" style="width: 100%; font-weight: 600;">
                            <option value="late_arrival">⏱️ Late Arrival</option>
                            <option value="unauthorized_absence">🚫 Unauthorized Absence</option>
                            <option value="sop_violation" selected>⚠️ SOP / Policy Violation</option>
                            <option value="negligence">⚠️ Negligence / Damage</option>
                            <option value="misconduct">🛑 Misconduct</option>
                            <option value="other">📝 Other</option>
                        </select>
                    </div>
                </div>

                <!-- Final Decision Remarks -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Official Decision Remarks / Findings</label>
                    <textarea id="hr-decide-sc-remarks" class="input-control" rows="3" placeholder="State management findings, verbal counseling, warning issued, or grounds for fine imposition..." style="width: 100%; resize: vertical;"></textarea>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 5px;">💡 Spaces, line breaks, and paragraphs are preserved. Use <b>**bold text**</b> for emphasis.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-decide-showcause-modal')">Cancel</button>
                <button type="submit" id="hr-decide-sc-submit-btn" class="btn btn-primary" style="background: #10b981; border-color: #10b981;">
                    ⚖️ Confirm Decision
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal D: View Full Show Cause Inquiry Record -->
<div id="hr-view-showcause-modal" class="modal-overlay">
    <div class="modal-card modal-card-lg" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">📜 Show Cause Notice & Inquiry Record</h3>
            <button type="button" class="btn-modal-close" onclick="closeModal('hr-view-showcause-modal')">&times;</button>
        </div>
        <div class="modal-body" id="hr-view-showcause-content" style="padding: 20px 22px;">
            <!-- Rendered Dynamically -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('hr-view-showcause-modal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="printShowCauseRecord()">🖨️ Print Inquiry Summary</button>
        </div>
    </div>
</div>

