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
                    <button type="button" class="btn btn-primary" onclick="openCreateNoticeModal()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700;">
                        📢 Post Notice
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
            <!-- Filter Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <select id="hr-leave-status-filter" class="input-control" style="font-size: 12.5px; padding: 6px 12px; min-width: 160px;" onchange="loadHrLeaves()">
                        <option value="all">All Statuses</option>
                        <option value="pending" selected>⏳ Pending HOD Approval</option>
                        <option value="approved_by_hod">🟡 Approved by HOD (Pending HR)</option>
                        <option value="approved">✅ Approved by HR</option>
                        <option value="rejected">❌ Rejected</option>
                    </select>

                    <select id="hr-leave-type-filter" class="input-control" style="font-size: 12.5px; padding: 6px 12px; min-width: 140px;" onchange="loadHrLeaves()">
                        <option value="all">All Leave Types</option>
                        <option value="casual">🌴 Casual Leave</option>
                        <option value="sick">🩺 Sick Leave</option>
                        <option value="annual">🏖️ Annual Leave</option>
                        <option value="unpaid">🚫 Unpaid Leave</option>
                        <option value="other">📝 Other</option>
                    </select>

                    <select id="hr-leave-emp-filter" class="input-control admin-only" style="font-size: 12.5px; padding: 6px 12px; min-width: 160px;" onchange="loadHrLeaves()">
                        <option value="">All Employees</option>
                    </select>
                </div>

                <div style="font-size: 12px; color: var(--text-muted); font-weight: 600;" id="hr-leaves-count-label">
                    Showing 0 requests
                </div>
            </div>

            <!-- Leaves Table -->
            <div class="table-responsive">
                <table class="interactive-table">
                    <thead>
                        <tr>
                            <th style="width: 22%; min-width: 180px;">Employee</th>
                            <th style="width: 12%; min-width: 110px;">Leave Type</th>
                            <th style="width: 18%; min-width: 160px;">Date Range</th>
                            <th style="width: 8%; min-width: 70px; text-align: center;">Days</th>
                            <th style="width: 24%; min-width: 180px;">Reason & Notes</th>
                            <th style="width: 10%; min-width: 100px; text-align: center;">Status</th>
                            <th style="width: 6%; min-width: 90px; text-align: center;">Actions</th>
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
            <!-- Filter Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <select id="hr-loan-status-filter" class="input-control" style="font-size: 12.5px; padding: 6px 12px; min-width: 140px;" onchange="loadHrLoans()">
                        <option value="all">All Statuses</option>
                        <option value="pending" selected>⏳ Pending Review</option>
                        <option value="approved">✅ Active / Approved</option>
                        <option value="repaid">🎉 Fully Repaid</option>
                        <option value="rejected">❌ Rejected</option>
                    </select>

                    <select id="hr-loan-type-filter" class="input-control" style="font-size: 12.5px; padding: 6px 12px; min-width: 150px;" onchange="loadHrLoans()">
                        <option value="all">All Loan Types</option>
                        <option value="advance_salary">💵 Advance Salary</option>
                        <option value="emergency_loan">🚨 Emergency Loan</option>
                        <option value="medical_aid">🩺 Medical Aid</option>
                    </select>

                    <select id="hr-loan-emp-filter" class="input-control admin-only" style="font-size: 12.5px; padding: 6px 12px; min-width: 160px;" onchange="loadHrLoans()">
                        <option value="">All Employees</option>
                    </select>
                </div>

                <div style="font-size: 12px; color: var(--text-muted); font-weight: 600;" id="hr-loans-count-label">
                    Showing 0 loan records
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


<!-- ================= 4. DEDICATED COMPANY NOTICE BOARD ================= -->
<section id="tab-hr-notices" class="tab-section">
    <div class="worksheet-container">
        
        <div class="worksheet-card" style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 2px;">
                        📢 Company Notice Board & Broadcasts
                    </h2>
                    <p style="color: var(--text-muted); font-size: 12.5px; margin: 0;">
                        Official announcements, holiday notices, urgent alerts, and event updates.
                    </p>
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
        </div>

        <div id="hr-notices-feed" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
            <div style="text-align: center; padding: 40px; color: var(--text-muted); grid-column: 1 / -1;">
                Loading company notices...
            </div>
        </div>

    </div>
</section>


<!-- ================= 5. DEDICATED MONTHLY PAYROLL & SALARIES ================= -->
<section id="tab-hr-payroll" class="tab-section">
    <div class="worksheet-container">

        <div class="worksheet-card" style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 2px;">
                        💰 Monthly Payroll & Compensation Command Center
                    </h2>
                    <p style="color: var(--text-muted); font-size: 12.5px; margin: 0;">
                        Auto-calculated based on daily attendance records, duty hours, approved leaves, and active loan deductions.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <label for="hr-payroll-month" style="font-size: 12.5px; font-weight: 700; color: var(--text-muted);">Salary Month:</label>
                    <input type="month" id="hr-payroll-month" class="input-control" style="font-size: 13px; font-weight: 700; padding: 6px 12px;" onchange="loadHrPayroll()">
                    <button type="button" class="btn btn-outline" onclick="loadHrPayroll()" title="Recalculate stats">
                        🔄 Recalculate
                    </button>
                </div>
            </div>
        </div>

        <div class="worksheet-card">
            <div class="table-responsive">
                <table class="interactive-table">
                    <thead>
                        <tr>
                            <th style="width: 18%; min-width: 160px;">Employee</th>
                            <th style="width: 8%; min-width: 70px; text-align: center;" title="Attended working days">Present</th>
                            <th style="width: 8%; min-width: 70px; text-align: center;" title="Approved paid leaves">Leaves</th>
                            <th style="width: 9%; min-width: 80px; text-align: center;" title="Total recorded duty hours">Hours</th>
                            <th style="width: 12%; min-width: 100px;">Basic Pay</th>
                            <th style="width: 10%; min-width: 90px;">Bonus</th>
                            <th style="width: 12%; min-width: 100px;">Deductions (Loans)</th>
                            <th style="width: 12%; min-width: 110px; font-weight: 800;">Net Salary</th>
                            <th style="width: 6%; min-width: 80px; text-align: center;">Status</th>
                            <th style="width: 5%; min-width: 75px; text-align: center;">Slip</th>
                        </tr>
                    </thead>
                    <tbody id="hr-payroll-table-body">
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 30px; color: var(--text-muted);">
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
                <div class="admin-only form-group" style="margin-bottom: 14px;">
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

                <div class="admin-only form-group" style="margin-bottom: 10px;">
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
                <div class="admin-only form-group" style="margin-bottom: 14px;">
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
                        <input type="number" step="100" min="500" id="hr-loan-form-amount" class="input-control" placeholder="e.g. 25000" style="width: 100%; font-weight: 700;" required oninput="calculateLoanFormDeduction()">
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

                <div class="admin-only form-group" style="margin-bottom: 10px;">
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
    <div class="modal-card" style="max-width: 500px;">
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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('hr-create-notice-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">📢 Broadcast Notice</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Edit Payroll Item Modal (Admin Only) -->
<div id="hr-edit-payroll-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 500px;">
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
                    <input type="number" step="0.01" id="hr-pay-basic-salary" class="input-control" style="width: 100%; font-weight: 700;" required oninput="calculateModalNetSalary()">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label class="form-label">Bonus (PKR)</label>
                        <input type="number" step="0.01" id="hr-pay-bonus" class="input-control" value="0.00" style="width: 100%; color: #10b981; font-weight: 700;" oninput="calculateModalNetSalary()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deductions (PKR)</label>
                        <input type="number" step="0.01" id="hr-pay-deductions" class="input-control" value="0.00" style="width: 100%; color: #ef4444; font-weight: 700;" oninput="calculateModalNetSalary()">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group">
                        <label class="form-label" style="font-size: 11px;">Bonus Reason</label>
                        <input type="text" id="hr-pay-bonus-reason" class="input-control" placeholder="e.g. Performance / Overtime" style="width: 100%;">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-size: 11px;">Deduction Reason</label>
                        <input type="text" id="hr-pay-deduction-reason" class="input-control" placeholder="e.g. Loan repayment / Late" style="width: 100%;">
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

<!-- 5. Printable Pay Slip Modal -->
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
