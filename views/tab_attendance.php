<?php
// Load all active departments from DB dynamically
$deptList = [];
try {
    $dbAtt = function_exists('getDbConnection') ? getDbConnection() : null;
    if ($dbAtt) {
        $stmtDepts = $dbAtt->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC");
        $deptList = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}
?>
        <!-- TAB 4: STAFF ATTENDANCE & TIMESHEET REPORTS (ADMIN / HR / HOD) -->
        <section id="tab-attendance" class="tab-section">
            <div class="worksheet-card">
                <!-- Header with View Mode Switcher -->
                <div class="card-header-flex" style="flex-wrap: wrap; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
                    <div class="card-title-group">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 20px;">👥</span>
                            <div>
                                <h3 style="margin: 0; font-size: 17px; font-weight: 800;">Staff Attendance & Duty Timesheet Management</h3>
                                <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);">
                                    Track staff shifts, daily duty hours, leave records, and monthly timesheets.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Triple View Mode Switcher Buttons -->
                    <div style="display: flex; background: var(--bg-input); padding: 3px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); gap: 4px; flex-wrap: wrap;">
                        <button type="button" id="btn-att-view-live" class="btn btn-primary" style="padding: 6px 14px; font-size: 12px; font-weight: 700; border-radius: var(--radius-md); border: none;" onclick="switchAttendanceView('live')">
                            🔴 Live Shift Roster
                        </button>
                        <button type="button" id="btn-att-view-report" class="btn btn-outline" style="padding: 6px 14px; font-size: 12px; font-weight: 700; border-radius: var(--radius-md); border: none;" onclick="switchAttendanceView('report')">
                            📊 Detailed Attendance Report & Timesheet
                        </button>
                        <button type="button" id="btn-att-view-whatsapp" class="btn btn-outline" style="padding: 6px 14px; font-size: 12px; font-weight: 700; border-radius: var(--radius-md); border: none;" onclick="switchAttendanceView('whatsapp')">
                            💬 WhatsApp Group Attendance & OrbitSend
                        </button>
                    </div>
                </div>

                <!-- ================= VIEW 1: LIVE ATTENDANCE ROSTER ================= -->
                <div id="att-view-live-section">
                    <!-- Compact Filter Bar for Live Roster -->
                    <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; box-shadow: var(--shadow-sm);">
                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <!-- Date Stepper Widget -->
                            <div style="display: inline-flex; align-items: center; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1px 3px;">
                                <button type="button" class="btn btn-outline" style="padding: 4px 7px; font-size: 11px; font-weight: 800; border: none; background: transparent; cursor: pointer;" onclick="stepLiveDate(-1)" title="Previous Day">◀</button>
                                <input type="date" id="attendance-board-date" class="input-control" value="<?= date('Y-m-d') ?>" onchange="loadLiveAttendance()" style="padding: 4px 6px; font-size: 11.5px; font-weight: 700; border: none; background: transparent; width: 125px;">
                                <button type="button" class="btn btn-outline" style="padding: 4px 7px; font-size: 11px; font-weight: 800; border: none; background: transparent; cursor: pointer;" onclick="stepLiveDate(1)" title="Next Day">▶</button>
                            </div>
                            <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; font-weight: 700;" onclick="jumpLiveDateToday()" title="Jump to Today">⚡ Today</button>

                            <!-- Dynamic Department Filter -->
                            <select id="live-att-dept-filter" class="input-control" onchange="loadLiveAttendance()" style="padding: 4px 8px; font-size: 11.5px; max-width: 180px; border-radius: var(--radius-md);">
                                <option value="all">🏢 All Departments</option>
                                <?php foreach ($deptList as $d): ?>
                                    <option value="<?= htmlspecialchars($d['id']) ?>">🏢 <?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <button type="button" class="btn btn-primary" onclick="loadLiveAttendance()" style="padding: 5px 12px; font-size: 11.5px; font-weight: 700;">
                                🔄 Refresh Roster
                            </button>
                        </div>
                    </div>

                    <!-- Live KPI Badges -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; margin-bottom: 14px;">
                        <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <span style="font-size: 9.5px; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">TOTAL STAFF</span>
                                <h3 id="att-stat-total" style="font-size: 18px; color: var(--primary); margin: 2px 0 0; font-weight: 800; line-height: 1.1;">0</h3>
                            </div>
                            <span style="font-size: 18px; opacity: 0.8;">👥</span>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <span style="font-size: 9.5px; color: #10b981; font-weight: 800; text-transform: uppercase;">ON DUTY</span>
                                <h3 id="att-stat-working" style="font-size: 18px; color: #10b981; margin: 2px 0 0; font-weight: 800; line-height: 1.1;">0</h3>
                            </div>
                            <span style="font-size: 18px; opacity: 0.8;">🟢</span>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <span style="font-size: 9.5px; color: #3b82f6; font-weight: 800; text-transform: uppercase;">CHECKED OUT</span>
                                <h3 id="att-stat-out" style="font-size: 18px; color: #3b82f6; margin: 2px 0 0; font-weight: 800; line-height: 1.1;">0</h3>
                            </div>
                            <span style="font-size: 18px; opacity: 0.8;">🔵</span>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <span style="font-size: 9.5px; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">ABSENT / OFF</span>
                                <h3 id="att-stat-absent" style="font-size: 18px; color: #94a3b8; margin: 2px 0 0; font-weight: 800; line-height: 1.1;">0</h3>
                            </div>
                            <span style="font-size: 18px; opacity: 0.8;">⚪</span>
                        </div>
                    </div>

                    <!-- Live Roster Table -->
                    <div class="table-responsive">
                        <table class="interactive-table">
                            <thead>
                                <tr>
                                    <th style="width: 26%;">Employee Name</th>
                                    <th style="width: 14%;">Department</th>
                                    <th style="width: 11%;">Check In</th>
                                    <th style="width: 11%;">Check Out</th>
                                    <th style="width: 11%;">Duty Hours</th>
                                    <th style="width: 16%;">Status</th>
                                    <th style="width: 11%; text-align: right;">Action</th>
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
                    <!-- Compact Filter Controls Bar -->
                    <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; box-shadow: var(--shadow-sm);">
                        
                        <!-- Left Group: Compact Filter Inputs -->
                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; flex: 1;">
                            
                            <!-- Searchable Target Employee Combobox -->
                            <div class="searchable-dropdown-wrapper" id="rep-att-emp-search-wrapper" style="position: relative; min-width: 190px; max-width: 250px; flex: 1;">
                                <button type="button" id="rep-att-emp-dropdown-btn" class="input-control" style="font-weight: 700; font-size: 11.5px; padding: 4px 8px; width: 100%; border-color: var(--border-color); background: var(--bg-card); cursor: pointer; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 6px; text-align: left;" onclick="toggleRepAttEmpDropdown()">
                                    <span id="rep-att-emp-dropdown-selected-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">👥 All Employees Summary</span>
                                    <span style="font-size: 9px; color: var(--text-muted);">▼</span>
                                </button>

                                <select id="rep-att-emp-select" style="display: none;" onchange="loadAttendanceReport()">
                                    <option value="all">👥 All Employees Summary</option>
                                </select>

                                <!-- Floating Search Dropdown Menu -->
                                <div id="rep-att-emp-dropdown-menu" class="searchable-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; min-width: 260px; width: 100%; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); z-index: 1050; padding: 6px; text-align: left;">
                                    <div style="padding-bottom: 4px; border-bottom: 1px solid var(--border-color); margin-bottom: 4px;">
                                        <input type="text" id="rep-att-search-input" class="input-control" placeholder="🔍 Search employee..." style="width: 100%; padding: 4px 8px; font-size: 11.5px;" oninput="filterRepAttEmpDropdown(this.value)" autocomplete="off">
                                    </div>
                                    <div id="rep-att-emp-dropdown-list" style="max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                                        <!-- Populated dynamically via reports.js -->
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic Department Filter -->
                            <select id="rep-att-dept-select" class="input-control" style="padding: 4px 8px; font-size: 11.5px; max-width: 170px; border-radius: var(--radius-md);" onchange="loadAttendanceReport()">
                                <option value="all">🏢 All Departments</option>
                                <?php foreach ($deptList as $d): ?>
                                    <option value="<?= htmlspecialchars($d['id']) ?>">🏢 <?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Month Selector -->
                            <input type="month" id="rep-att-month-select" class="input-control" value="<?= date('Y-m') ?>" onchange="loadAttendanceReport()" style="padding: 4px 8px; font-size: 11.5px; width: 140px; border-radius: var(--radius-md);">

                            <!-- Expected Standard Duty Hours -->
                            <select id="rep-att-standard-hours" class="input-control" style="padding: 4px 8px; font-size: 11.5px; max-width: 155px; border-radius: var(--radius-md);" onchange="loadAttendanceReport()">
                                <option value="auto" selected>⚙️ Auto (Shift)</option>
                                <option value="8">⏱️ 8.0 Hours / Day</option>
                                <option value="9">⏱️ 9.0 Hours / Day</option>
                                <option value="7">⏱️ 7.0 Hours / Day</option>
                                <option value="6">⏱️ 6.0 Hours / Day</option>
                                <option value="open">🌐 Flexible Shift</option>
                            </select>
                        </div>

                        <!-- Right Group: Action Buttons -->
                        <div style="display: flex; gap: 6px; align-items: center; flex-shrink: 0;">
                            <button type="button" class="btn btn-outline" onclick="exportAttendanceReportCsv()" style="padding: 5px 10px; font-size: 11.5px; font-weight: 700; border-color: rgba(16, 185, 129, 0.4); color: #10b981;" title="Export Current Report to CSV">
                                📥 CSV
                            </button>
                            <button type="button" class="btn btn-primary" onclick="printAttendanceReport()" style="padding: 5px 12px; font-size: 11.5px; font-weight: 700;" title="Print Formatted Monthly Timesheet">
                                🖨️ Print
                            </button>
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


                <!-- ================= VIEW 3: WHATSAPP GROUP ATTENDANCE & ORBITSEND ================= -->
                <div id="att-view-whatsapp-section" style="display: none;">
                    
                    <!-- Compact Top Action Bar -->
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
                        <div>
                            <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                                <span style="display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></span>
                                WhatsApp Group Attendance Stream
                            </h4>
                            <p style="margin: 2px 0 0; font-size: 11.5px; color: var(--text-muted);">
                                Live Check-ins, Check-outs & Early Leaves via OrbitSend webhook gateway.
                            </p>
                        </div>

                        <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                            <button type="button" class="btn btn-outline" style="border-color: #8b5cf6; color: #8b5cf6; font-weight: 700; font-size: 11.5px; padding: 5px 10px;" onclick="openModal('wa-sim-modal')">
                                🧪 Test Simulator
                            </button>
                            <button type="button" class="btn btn-outline" style="border-color: #10b981; color: #10b981; font-weight: 700; font-size: 11.5px; padding: 5px 10px;" onclick="openModal('wa-settings-modal')">
                                ⚙️ OrbitSend Setup
                            </button>
                            <button type="button" class="btn btn-primary" style="font-weight: 700; font-size: 11.5px; padding: 5px 12px;" onclick="WhatsAppAtt.loadLogs()">
                                🔄 Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Smart Compact KPI Pills -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; margin-bottom: 14px;">
                        <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 9.5px; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">MESSAGES</div>
                                <div id="wa-stat-total" style="font-size: 18px; color: var(--primary); font-weight: 800; line-height: 1.1;">0</div>
                            </div>
                            <span style="font-size: 18px; opacity: 0.8;">💬</span>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 9.5px; color: #10b981; font-weight: 800; text-transform: uppercase;">CHECKED IN</div>
                                <div id="wa-stat-in" style="font-size: 18px; color: #10b981; font-weight: 800; line-height: 1.1;">0</div>
                            </div>
                            <span style="font-size: 18px; opacity: 0.8;">🟢</span>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 9.5px; color: #3b82f6; font-weight: 800; text-transform: uppercase;">CHECKED OUT</div>
                                <div id="wa-stat-out" style="font-size: 18px; color: #3b82f6; font-weight: 800; line-height: 1.1;">0</div>
                            </div>
                            <span style="font-size: 18px; opacity: 0.8;">🔵</span>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 9.5px; color: #f59e0b; font-weight: 800; text-transform: uppercase;">EARLY LEAVES</div>
                                <div id="wa-stat-early" style="font-size: 18px; color: #f59e0b; font-weight: 800; line-height: 1.1;">0</div>
                            </div>
                            <span style="font-size: 18px; opacity: 0.8;">⚠️</span>
                        </div>
                        <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-size: 9.5px; color: #ef4444; font-weight: 800; text-transform: uppercase;">UNMATCHED</div>
                                <div id="wa-stat-unmatched" style="font-size: 18px; color: #ef4444; font-weight: 800; line-height: 1.1;">0</div>
                            </div>
                            <span style="font-size: 18px; opacity: 0.8;">❓</span>
                        </div>
                    </div>

                    <!-- Smart Single-Line Compact Filter Bar -->
                    <div style="background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; box-shadow: var(--shadow-sm);">
                        
                        <!-- Left Group: Date & Filters -->
                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; flex: 1;">
                            
                            <!-- Date Stepper Widget -->
                            <div style="display: inline-flex; align-items: center; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1px 3px;">
                                <button type="button" class="btn btn-outline" style="padding: 4px 7px; font-size: 11px; font-weight: 800; border: none; background: transparent; cursor: pointer;" onclick="WhatsAppAtt.stepDate(-1)" title="Previous Day">
                                    ◀
                                </button>
                                <input type="date" id="wa-logs-date-filter" class="input-control" value="<?= date('Y-m-d') ?>" style="padding: 4px 6px; font-size: 11.5px; font-weight: 700; border: none; background: transparent; width: 125px;" onchange="WhatsAppAtt.onDateChange()">
                                <button type="button" class="btn btn-outline" style="padding: 4px 7px; font-size: 11px; font-weight: 800; border: none; background: transparent; cursor: pointer;" onclick="WhatsAppAtt.stepDate(1)" title="Next Day">
                                    ▶
                                </button>
                            </div>

                            <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; font-weight: 700;" onclick="WhatsAppAtt.jumpToday()" title="Jump to Today">
                                ⚡ Today
                            </button>

                            <button type="button" id="wa-btn-all-dates" class="btn btn-outline" style="padding: 4px 8px; font-size: 11px; font-weight: 700;" onclick="WhatsAppAtt.toggleAllDates()" title="Show all dates">
                                🌐 All
                            </button>

                            <!-- Instant Search Box -->
                            <div style="min-width: 170px; max-width: 220px; flex: 1;">
                                <input type="text" id="wa-logs-search-filter" class="input-control" placeholder="🔍 Search name, code, msg..." style="padding: 4px 9px; font-size: 11.5px; width: 100%; border-radius: var(--radius-md);" oninput="WhatsAppAtt.onSearchInput(this.value)">
                            </div>

                            <!-- Staff Filter Dropdown -->
                            <select id="wa-logs-emp-filter" class="input-control" style="padding: 4px 8px; font-size: 11.5px; max-width: 180px; border-radius: var(--radius-md);" onchange="WhatsAppAtt.loadLogs()">
                                <option value="">👤 All Employees</option>
                            </select>

                            <!-- Action Filter Dropdown -->
                            <select id="wa-logs-action-filter" class="input-control" style="padding: 4px 8px; font-size: 11.5px; max-width: 145px; border-radius: var(--radius-md);" onchange="WhatsAppAtt.loadLogs()">
                                <option value="all">⚡ All Actions</option>
                                <option value="check_in">🟢 Check In</option>
                                <option value="check_out">🔵 Check Out</option>
                                <option value="field_visit">🚗 Field Visit</option>
                                <option value="back_in_office">🏢 Back in Office</option>
                                <option value="leave_notice">🌴 Leave Notice</option>
                                <option value="leaving_early">⚠️ Early Leave</option>
                                <option value="short_leave">⏱️ Short Leave</option>
                            </select>

                            <!-- Status Filter Dropdown -->
                            <select id="wa-logs-status-filter" class="input-control" style="padding: 4px 8px; font-size: 11.5px; max-width: 125px; border-radius: var(--radius-md);" onchange="WhatsAppAtt.loadLogs()">
                                <option value="all">📋 All Statuses</option>
                                <option value="applied">Applied ✅</option>
                                <option value="unmatched">Unmatched ⚠️</option>
                                <option value="ignored">Ignored ⚪</option>
                            </select>

                        </div>

                        <!-- Right Group: Counter & Reset -->
                        <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                            <span id="wa-filter-count-badge" style="font-size: 11.5px; color: var(--text-muted); font-weight: 600; white-space: nowrap;">
                                Loading...
                            </span>
                            <button type="button" class="btn btn-outline" style="font-size: 11px; padding: 4px 8px; font-weight: 700;" onclick="WhatsAppAtt.resetFilters()" title="Reset all filters">
                                ↺ Reset
                            </button>
                        </div>

                    </div>

                    <!-- Live Stream Table -->
                    <div class="table-responsive">
                        <table class="interactive-table">
                            <thead>
                                <tr>
                                    <th>Logged At</th>
                                    <th>Sender / Staff</th>
                                    <th>Parsed Action</th>
                                    <th>Extracted Time</th>
                                    <th>Raw WhatsApp Message & Remarks</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="wa-logs-tbody">
                                <!-- Populated dynamically via whatsapp_attendance.js -->
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
        </section>

        <!-- ================= MODAL: ORBITSEND SETTINGS ================= -->
        <!-- ================= MODAL: ORBITSEND SETTINGS ================= -->
        <div id="wa-settings-modal" class="modal-overlay">
            <div class="modal-card" style="max-width: 620px;">
                <div class="modal-header">
                    <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                        <span>⚙️</span> OrbitSend WhatsApp Gateway & AI Configuration
                    </h3>
                    <button type="button" class="modal-close" onclick="closeModal('wa-settings-modal')">&times;</button>
                </div>
                <form id="wa-settings-form" onsubmit="WhatsAppAtt.saveSettings(event)">
                    <div class="modal-body" style="gap: 14px;">
                        
                        <!-- Webhook URL Box -->
                        <div style="background: rgba(16, 185, 129, 0.08); padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.25);">
                            <label style="font-size: 11px; font-weight: 800; color: #059669; text-transform: uppercase; display: block; margin-bottom: 4px;">
                                🔗 OrbitSend Webhook URL (Copy & paste into OrbitSend Webhooks)
                            </label>
                            <div style="display: flex; gap: 6px;">
                                <input type="text" id="wa-webhook-url" class="input-control" readonly style="flex: 1; font-family: monospace; font-size: 11.5px; background: #fff; font-weight: 600;">
                                <button type="button" class="btn btn-primary" style="padding: 5px 12px; font-size: 11.5px;" onclick="WhatsAppAtt.copyWebhookUrl()">📋 Copy</button>
                            </div>
                        </div>

                        <!-- OrbitSend API Configuration Section -->
                        <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; display: flex; flex-direction: column; gap: 10px;">
                            <div style="font-size: 12px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
                                📡 OrbitSend API Connection
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 11px;">OrbitSend API URL</label>
                                <input type="text" id="wa-api-url" class="input-control" placeholder="https://app.orbitsend.com/api/send/whatsapp" style="width: 100%; font-family: monospace; font-size: 12px;">
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 11px;">OrbitSend Secret Key</label>
                                    <input type="password" id="wa-api-secret" class="input-control" placeholder="Enter API Secret" style="width: 100%; font-family: monospace; font-size: 12px;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 11px;">Webhook Secret Token</label>
                                    <input type="password" id="wa-webhook-secret" class="input-control" placeholder="Enter Webhook Secret" style="width: 100%; font-family: monospace; font-size: 12px;">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 11px;">OrbitSend Account / Unique ID</label>
                                    <input type="text" id="wa-unique-id" class="input-control" placeholder="e.g. WHATSAPP_ACCOUNT_UNIQUE_ID" style="width: 100%; font-family: monospace; font-size: 12px;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                        <label class="form-label" style="font-size: 11px; margin-bottom: 0;">Target WhatsApp Group JID</label>
                                        <button type="button" class="btn btn-outline" style="font-size: 10px; padding: 1px 6px; line-height: 1.2;" onclick="WhatsAppAtt.fetchGroupsFromOrbitSend()" title="Auto-load groups from OrbitSend">🔄 Fetch Groups</button>
                                    </div>
                                    <input type="text" id="wa-group-jid" class="input-control" placeholder="e.g. 12036304... or leave empty for all" style="width: 100%; font-family: monospace; font-size: 12px;">
                                </div>
                            </div>
                        </div>

                        <!-- AI Configuration Section -->
                        <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; display: flex; flex-direction: column; gap: 10px;">
                            <div style="font-size: 12px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 6px;">
                                🤖 AI Attendance Parser (Optional)
                            </div>
                            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 11px;">AI Provider</label>
                                    <select id="wa-ai-provider" class="input-control" style="font-size: 12px;">
                                        <option value="auto">⚡ Auto (Fast NLP)</option>
                                        <option value="gemini">✨ Google Gemini</option>
                                        <option value="openai">🧠 OpenAI GPT</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-size: 11px;">AI API Key</label>
                                    <input type="password" id="wa-ai-api-key" class="input-control" placeholder="Enter Gemini or OpenAI API Key" style="width: 100%; font-family: monospace; font-size: 12px;">
                                </div>
                            </div>
                            <small style="font-size: 10.5px; color: var(--text-muted);">Auto-NLP runs instantly locally. Adding an AI key enables deep Roman Urdu/conversational text understanding.</small>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 10px 12px; background: var(--bg-card-elevated); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 12px; cursor: pointer; font-weight: 700;">
                                    <input type="checkbox" id="wa-is-enabled" checked>
                                    Enable WhatsApp Attendance
                                </label>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 12px; cursor: pointer;">
                                    <input type="checkbox" id="wa-auto-reply">
                                    Send Confirmation Reply
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('wa-settings-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">💾 Save Configuration</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: TEST SIMULATOR ================= -->
        <div id="wa-sim-modal" class="modal-overlay">
            <div class="modal-card" style="max-width: 520px;">
                <div class="modal-header">
                    <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                        <span>🧪</span> WhatsApp Attendance Test Simulator
                    </h3>
                    <button type="button" class="modal-close" onclick="closeModal('wa-sim-modal')">&times;</button>
                </div>
                <form id="wa-sim-form" onsubmit="WhatsAppAtt.runSimulator(event)">
                    <div class="modal-body">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label">Employee / Sender</label>
                            <select id="wa-sim-employee" class="input-control" style="width: 100%;">
                                <!-- Populated dynamically -->
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div class="form-group">
                                <label class="form-label">Sender Phone Number</label>
                                <input type="text" id="wa-sim-phone" class="input-control" placeholder="03001234567" style="width: 100%;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Simulation Date</label>
                                <input type="date" id="wa-sim-date" class="input-control" value="<?= date('Y-m-d') ?>" style="width: 100%;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label">Simulated WhatsApp Message Text</label>
                            <input type="text" id="wa-sim-message" class="input-control" placeholder="e.g. in at 4:46 OR Going to Lahore Organic Village OR unable to join" style="width: 100%; font-weight: 700;" required>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 8px;">
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="document.getElementById('wa-sim-message').value='in at 4:46'">🟢 "in at 4:46"</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="document.getElementById('wa-sim-message').value='Going to Lahore Organic Village for shoot'">🚗 "Going to Lahore Organic Village"</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="document.getElementById('wa-sim-message').value='Back in office'">🏢 "Back in office"</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="document.getElementById('wa-sim-message').value='Respected HR Unable to join office because some emergency at home already informed HOD'">🌴 "Emergency Leave (Informed HOD)"</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="document.getElementById('wa-sim-message').value='Respected HR, Today I am on alternate leave against 6-9-2026. Informed Sir Ghulam Abbas.'">🌴 "Alternate Leave (Against 6-9-2026)"</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="document.getElementById('wa-sim-message').value='leaving early at 5:00 pm due to emergency'">⚠️ "Leaving early"</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="document.getElementById('wa-sim-message').value='out'">🔵 "out"</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px; opacity: 0.7;" onclick="document.getElementById('wa-sim-message').value='Good morning everyone! Have a great day.'">⚪ "Good morning (Noise)"</button>
                            </div>
                        </div>

                        <div id="wa-sim-result-box" style="display: none; padding: 12px; border-radius: var(--radius-md); background: var(--bg-card-elevated); border: 1px solid var(--border-color); margin-top: 14px;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('wa-sim-modal')">Close</button>
                        <button type="submit" class="btn btn-primary">⚡ Parse & Apply Attendance</button>
                    </div>
                </form>
            </div>
        </div>

