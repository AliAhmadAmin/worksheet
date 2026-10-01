        <!-- TAB 4: STAFF ATTENDANCE & TIMESHEET REPORTS (ADMIN / HR / HOD) -->
        <section id="tab-attendance" class="tab-section">
            <div class="worksheet-card">
                <!-- Header with View Mode Switcher -->
                <div class="card-header-flex" style="flex-wrap: wrap; gap: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px;">
                    <div class="card-title-group">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 22px;">👥</span>
                            <div>
                                <h3 style="margin: 0; font-size: 18px; font-weight: 800;">Staff Attendance & Duty Timesheet Management</h3>
                                <p style="margin: 2px 0 0; font-size: 12.5px; color: var(--text-muted);">
                                    Track staff shifts, daily duty hours, leave records, and monthly timesheets.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Dual View Mode Switcher Buttons -->
                    <div style="display: flex; background: var(--bg-input); padding: 4px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); gap: 4px;">
                        <button type="button" id="btn-att-view-live" class="btn btn-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700; border-radius: var(--radius-md);" onclick="switchAttendanceView('live')">
                            🔴 Live Shift Roster
                        </button>
                        <button type="button" id="btn-att-view-report" class="btn btn-outline" style="padding: 6px 14px; font-size: 12px; font-weight: 700; border-radius: var(--radius-md); border: none;" onclick="switchAttendanceView('report')">
                            📊 Detailed Attendance Report & Timesheet
                        </button>
                    </div>
                </div>

                <!-- ================= VIEW 1: LIVE ATTENDANCE ROSTER ================= -->
                <div id="att-view-live-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px;">
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <div class="form-group" style="margin-bottom: 0; min-width: 170px;">
                                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 3px;">SELECT DATE</label>
                                <input type="date" id="attendance-board-date" class="input-control" value="<?= date('Y-m-d') ?>" onchange="loadLiveAttendance()" style="padding: 6px 10px; font-size: 12.5px;">
                            </div>
                            <div class="form-group" style="margin-bottom: 0; min-width: 180px;">
                                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 3px;">FILTER DEPARTMENT</label>
                                <select id="live-att-dept-filter" class="input-control" onchange="loadLiveAttendance()" style="padding: 6px 10px; font-size: 12.5px;">
                                    <option value="all">🏢 All Departments</option>
                                    <option value="2">🎬 Digital Media</option>
                                    <option value="1">📺 News Room</option>
                                    <option value="3">📡 Programming</option>
                                    <option value="4">🎥 Documentary</option>
                                    <option value="16">💼 HR & Admin</option>
                                    <option value="5">🌐 Others</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button type="button" class="btn btn-outline" onclick="loadLiveAttendance()" style="display: inline-flex; align-items: center; gap: 5px; padding: 7px 14px; font-size: 12.5px;">
                                🔄 Refresh Roster
                            </button>
                        </div>
                    </div>

                    <!-- Live KPI Badges -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
                        <div style="background: var(--bg-card-elevated); padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                            <span style="font-size: 11px; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">👥 TOTAL EMPLOYEES</span>
                            <h3 id="att-stat-total" style="font-size: 24px; color: var(--primary); margin-top: 4px; font-weight: 800;">0</h3>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                            <span style="font-size: 11px; color: #10b981; font-weight: 800; text-transform: uppercase;">🟢 ON DUTY</span>
                            <h3 id="att-stat-working" style="font-size: 24px; color: #10b981; margin-top: 4px; font-weight: 800;">0</h3>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                            <span style="font-size: 11px; color: #3b82f6; font-weight: 800; text-transform: uppercase;">🔵 CHECKED OUT</span>
                            <h3 id="att-stat-out" style="font-size: 24px; color: #3b82f6; margin-top: 4px; font-weight: 800;">0</h3>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                            <span style="font-size: 11px; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">⚪ ABSENT / OFF DUTY</span>
                            <h3 id="att-stat-absent" style="font-size: 24px; color: #94a3b8; margin-top: 4px; font-weight: 800;">0</h3>
                        </div>
                    </div>

                    <!-- Live Roster Table -->
                    <div class="table-responsive">
                        <table class="interactive-table">
                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Team</th>
                                    <th>Department</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Duty Hours</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="live-attendance-tbody">
                                <!-- Populated via reports.js -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ================= VIEW 2: DETAILED ATTENDANCE TIMESHEET & REPORTS ================= -->
                <div id="att-view-report-section" style="display: none;">
                    <!-- Filter Controls Bar -->
                    <div style="background: var(--bg-card-elevated); padding: 18px 20px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 20px;">
                        <div style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; justify-content: space-between;">
                            
                            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; flex: 1;">
                                <!-- Single Searchable Target Employee Combobox -->
                                <div class="form-group" style="margin-bottom: 0; min-width: 230px; flex: 1; max-width: 300px;">
                                    <label class="form-label" style="font-size: 11px; font-weight: 700; margin-bottom: 4px;">TARGET EMPLOYEE</label>
                                    <div class="searchable-dropdown-wrapper" id="rep-att-emp-search-wrapper" style="position: relative; width: 100%;">
                                        <button type="button" id="rep-att-emp-dropdown-btn" class="input-control" style="font-weight: 700; font-size: 12.5px; padding: 7px 12px; width: 100%; border-color: var(--border-color); background: var(--bg-card); cursor: pointer; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left;" onclick="toggleRepAttEmpDropdown()">
                                            <span id="rep-att-emp-dropdown-selected-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">👥 All Employees Summary</span>
                                            <span style="font-size: 10px; color: var(--text-muted);">▼</span>
                                        </button>

                                        <!-- Hidden select for value synchronization -->
                                        <select id="rep-att-emp-select" style="display: none;" onchange="loadAttendanceReport()">
                                            <option value="all">👥 All Employees Summary</option>
                                        </select>

                                        <!-- Floating Search Dropdown Menu -->
                                        <div id="rep-att-emp-dropdown-menu" class="searchable-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; min-width: 280px; width: 100%; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 1050; padding: 8px; text-align: left;">
                                            <div style="padding-bottom: 6px; border-bottom: 1px solid var(--border-color); margin-bottom: 6px;">
                                                <input type="text" id="rep-att-search-input" class="input-control" placeholder="🔍 Search employee by name..." style="width: 100%; padding: 6px 10px; font-size: 12px;" oninput="filterRepAttEmpDropdown(this.value)" autocomplete="off">
                                            </div>
                                            <div id="rep-att-emp-dropdown-list" style="max-height: 240px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                                                <!-- Populated dynamically via reports.js -->
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Department Filter -->
                                <div class="form-group" style="margin-bottom: 0; min-width: 160px;">
                                    <label class="form-label" style="font-size: 11px; font-weight: 700; margin-bottom: 4px;">DEPARTMENT</label>
                                    <select id="rep-att-dept-select" class="input-control" style="font-size: 12.5px;" onchange="loadAttendanceReport()">
                                        <option value="all">🏢 All Departments</option>
                                        <option value="2">🎬 Digital Media</option>
                                        <option value="1">📺 News Room</option>
                                        <option value="3">📡 Programming</option>
                                        <option value="4">🎥 Documentary</option>
                                        <option value="16">💼 HR & Admin</option>
                                        <option value="5">🌐 Others</option>
                                    </select>
                                </div>

                                <!-- Month Selector -->
                                <div class="form-group" style="margin-bottom: 0; min-width: 140px;">
                                    <label class="form-label" style="font-size: 11px; font-weight: 700; margin-bottom: 4px;">REPORT MONTH</label>
                                    <input type="month" id="rep-att-month-select" class="input-control" value="<?= date('Y-m') ?>" onchange="loadAttendanceReport()" style="font-size: 12.5px;">
                                </div>

                                <!-- Expected Standard Duty Hours -->
                                <div class="form-group" style="margin-bottom: 0; min-width: 170px;">
                                    <label class="form-label" style="font-size: 11px; font-weight: 700; margin-bottom: 4px;">EVALUATION SHIFT</label>
                                    <select id="rep-att-standard-hours" class="input-control" style="font-size: 12.5px;" onchange="loadAttendanceReport()">
                                        <option value="auto" selected>⚙️ Auto (Staff Profile Shift)</option>
                                        <option value="8">⏱️ 8.0 Hours / Day</option>
                                        <option value="9">⏱️ 9.0 Hours / Day</option>
                                        <option value="7">⏱️ 7.0 Hours / Day</option>
                                        <option value="6">⏱️ 6.0 Hours / Day</option>
                                        <option value="open">🌐 Flexible / Open Shift</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Action Buttons (Export & Print) -->
                            <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 1px;">
                                <button type="button" class="btn btn-outline" onclick="exportAttendanceReportCsv()" style="padding: 8px 14px; font-size: 12.5px; font-weight: 700; border-color: rgba(16, 185, 129, 0.4); color: #10b981;" title="Export Current Report to CSV">
                                    📥 Export CSV
                                </button>
                                <button type="button" class="btn btn-primary" onclick="printAttendanceReport()" style="padding: 8px 16px; font-size: 12.5px; font-weight: 700;" title="Print Formatted Monthly Timesheet">
                                    🖨️ Print Timesheet
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Summary KPI Cards -->
                    <div id="rep-att-kpis-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
                        <!-- Rendered dynamically -->
                    </div>

                    <!-- Report Table Container -->
                    <div id="rep-att-table-container">
                        <!-- Rendered dynamically (Single Employee Day-by-Day or All Staff Matrix) -->
                        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                            Select an employee or filter by department/month to view the detailed attendance timesheet.
                        </div>
                    </div>
                </div>

            </div>
        </section>
