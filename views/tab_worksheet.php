        <!-- TAB 1: DASHBOARD / HOURLY WORKSHEET (DEDICATED PAGE) -->
        <?php
        $userRoleWs = strtolower($authUser['role'] ?? '');
        $canInspectWs = in_array($userRoleWs, ['super_admin', 'admin', 'hod']) || !empty($authUser['can_inspect_sheets']);
        $isHodWs = in_array($userRoleWs, ['hod', 'deputy_hod']);
        ?>
        <section id="tab-worksheet" class="tab-section <?= (!$isHodWs && ($currentPortal ?? '') === 'digital') ? 'active' : '' ?>">
            <!-- EMPLOYEE PERSONAL SHIFT HERO (Hidden for Admin) -->
            <!-- EMPLOYEE PERSONAL SHIFT HERO (Hidden for Admin) -->
            <div id="hero-employee-container" class="shift-hero-card employee-only" style="<?= $canInspectWs ? 'display: none;' : '' ?>">
                <!-- Left: Profile Info, Shift Meta & Status Chips -->
                <div class="shift-left-meta">
                    <div id="hero-profile-avatar" class="meta-profile-circle" onclick="openMyProfileModal()" style="cursor: pointer; overflow: hidden; display: flex; align-items: center; justify-content: center; position: relative;" title="Click to edit profile & photo">
                        <?php if (!empty($authUser['avatar'])): ?>
                            <img src="<?= htmlspecialchars($authUser['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?= htmlspecialchars($authUser['name']) ?>" onerror="this.outerHTML='<span id=\'hero-profile-avatar-char\'>👤</span>';">
                        <?php else: ?>
                            <span id="hero-profile-avatar-char"><?= htmlspecialchars(strtoupper(substr($authUser['name'] ?? 'U', 0, 1))) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="shift-details">
                        <div class="shift-details-top">
                            <h2 id="hero-emp-name"><?= htmlspecialchars($authUser['name'] ?? 'Employee Name') ?></h2>
                            <span class="hero-dot-separator">•</span>
                            <span id="hero-emp-meta" class="hero-meta-subtitle"><?= htmlspecialchars(($authUser['designation'] ?? 'Staff') . ' • ' . ($authUser['department_name'] ?? 'Digital') . ' • ' . ($authUser['team_name'] ?? 'Team')) ?></span>
                        </div>
                        <div class="shift-meta-chips-row">
                            <span class="shift-chip shift-chip-punch">
                                🕒 In: <strong id="hero-checkin-time" style="color: #0284c7;">--:--</strong> • Out: <strong id="hero-checkout-time" style="color: #ea580c;">--:--</strong>
                            </span>
                            <span id="hero-roster-pill" class="shift-chip" style="display: none; background: rgba(168, 85, 247, 0.12); color: #c084fc; border-color: rgba(168, 85, 247, 0.25);">
                                📅 Shift: <span id="hero-roster-shift-text">--</span>
                            </span>
                            <span id="employee-sheet-lock-badge" class="badge-lock unlocked">🔓 Active</span>
                            <span id="employee-hod-check-badge" class="badge" style="display: none;"></span>
                        </div>
                    </div>
                </div>

                <!-- Right: Duty Hours Timer + Action Buttons Toolbar -->
                <div class="shift-right-controls">
                    <div class="shift-compact-timer" title="My Live Duty Hours for today's shift">
                        <span class="timer-indicator"></span>
                        <div class="timer-text-group">
                            <span class="timer-label">Duty Hours</span>
                            <span id="duty-timer-digits" class="timer-digits">0:00:00</span>
                        </div>
                    </div>

                    <div class="shift-actions">
                        <span id="hero-shift-completed-chip" class="shift-chip shift-chip-completed" style="display: none;">
                            ✓ Shift Completed
                        </span>
                        <button type="button" id="btn-self-holiday" class="btn btn-outline" style="border-color: #8b5cf6; color: #8b5cf6; font-weight: 700;" onclick="handleToggleHoliday()" title="Self-Check: Mark this date as your scheduled Day Off / Holiday">
                            🏖️ Day Off
                        </button>
                        <button type="button" id="btn-check-in" class="btn btn-success" onclick="handleCheckIn()">
                            🟢 Check In
                        </button>
                        <button type="button" id="btn-check-out" class="btn btn-danger" style="display: none;" onclick="handleCheckOut()">
                            🔴 Check Out
                        </button>
                        <button type="button" id="btn-employee-print-sheet" class="btn btn-primary" onclick="triggerPrintSheet()">
                            🖨️ Print Sheet
                        </button>
                    </div>
                </div>

                <!-- Next Day Check-In Transition Banner (Shows when current date is checked out) -->
                <div id="hero-next-day-bar" style="display: none; width: 100%; margin-top: 10px; padding: 10px 14px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: var(--radius-md); align-items: center; justify-content: space-between; font-size: 12.5px; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 15px;">✅</span>
                        <span><strong>Shift completed & locked for <span id="hero-shift-date-label">Today</span>.</strong> Ready for next day check-in.</span>
                    </div>
                    <button type="button" class="btn btn-outline" style="padding: 4px 12px; font-size: 11.5px; font-weight: 700; border-color: var(--primary);" onclick="goToNextDay()">
                        ⏭️ Advance to Next Day
                    </button>
                </div>
            </div>

            <!-- Main Worksheet Area (Full Width) -->
            <div class="worksheet-card">
                <!-- ADMIN INTEGRATED INSPECTOR & CONTROLLER HEADER -->
                <div class="admin-only" id="admin-inspector-header-container" style="<?= $canInspectWs ? '' : 'display: none;' ?>; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 18px; transition: all 0.3s ease;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 14px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <h3 id="admin-inspector-title" style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 0;">Employee Worksheet Inspector & Live Editor</h3>
                                <span id="admin-verified-header-badge" class="badge badge-hod-checked" style="display: none; font-size: 11px; padding: 3px 10px; border-radius: 20px;">✅ Checked by HOD</span>
                            </div>
                            <p id="admin-inspector-subtitle" style="font-size: 12.5px; color: var(--text-muted); margin-top: 3px;">Reviewing, editing, and managing daily work entries for team members.</p>
                        </div>

                        <div class="shift-center-timer" style="padding: 6px 16px; min-width: 170px;">
                            <span class="timer-label" style="font-size: 10px;">Employee Duty Hours</span>
                            <span id="admin-emp-duty-hours" class="timer-digits" style="font-size: 20px;">0:00:00</span>
                            <div style="display: flex; gap: 4px; justify-content: center; flex-wrap: wrap; margin-top: 2px;">
                                <span id="sheet-lock-badge" class="badge-lock unlocked" style="font-size: 10.5px; padding: 2px 8px;">🔓 Active / Unlocked</span>
                            </div>
                        </div>
                    </div>

                    <!-- Center-Aligned Inspector Showcase Box -->
                    <div id="admin-inspector-box" class="admin-inspector-box">
                        <!-- Top Row: Clustered Responsive Groups (Seamless single row on 90%/desktop, elegant wrapping on 100%/laptops/mobile) -->
                        <div class="admin-inspector-controls-row">
                            <!-- Group 1: Avatar & Employee Selector -->
                            <div class="admin-ctrl-group admin-ctrl-emp">
                                <div id="admin-viewed-emp-avatar" class="admin-inspector-avatar">👤</div>
                                
                                <!-- Searchable Employee Combobox Wrapper -->
                                <div class="searchable-dropdown-wrapper" id="admin-emp-search-wrapper" style="position: relative; display: inline-block;">
                                    <button type="button" id="admin-emp-dropdown-btn" class="input-control" style="font-weight: 700; font-size: 13.5px; padding: 7px 12px; min-width: 140px; width: auto; max-width: 210px; border-color: var(--primary); background: var(--bg-card); cursor: pointer; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left;" onclick="toggleAdminEmpDropdown()">
                                        <span id="admin-emp-dropdown-selected-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Select Employee</span>
                                        <span style="font-size: 10px; color: var(--text-muted);">▼</span>
                                    </button>

                                    <!-- Hidden select to preserve background synchronization -->
                                    <select id="admin-employee-select" style="display: none;" onchange="handleAdminSelectEmployee(this.value)">
                                        <!-- Populated dynamically -->
                                    </select>

                                    <!-- Floating Search Dropdown Menu -->
                                    <div id="admin-emp-dropdown-menu" class="searchable-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; min-width: 250px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 1050; padding: 8px; text-align: left;">
                                        <div style="padding-bottom: 6px; border-bottom: 1px solid var(--border-color); margin-bottom: 6px;">
                                            <input type="text" id="admin-emp-search-input" class="input-control" placeholder="🔍 Search employee..." style="width: 100%; padding: 6px 10px; font-size: 12.5px;" oninput="filterAdminEmpDropdown(this.value)" autocomplete="off">
                                        </div>
                                        <div id="admin-emp-dropdown-list" style="max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                                            <!-- Populated dynamically -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Group 2: Date Navigation -->
                            <div class="admin-ctrl-group admin-ctrl-date">
                                <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 12px;" onclick="goToPrevDay()" title="Previous Day">◀</button>
                                <input type="date" id="worksheet-date-picker" class="input-control worksheet-date-input" value="<?= date('Y-m-d') ?>" style="padding: 6px 10px; font-weight: 600;" onchange="changeWorksheetDate(this.value)">
                                <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 12px;" onclick="goToNextDay()" title="Next Day">▶</button>
                                <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 11.5px;" onclick="goToToday()" title="Jump to Today">Today</button>
                            </div>

                            <!-- Group 3: Admin Live Attendance Controls -->
                            <div class="admin-ctrl-group admin-ctrl-attendance">
                                <button type="button" id="admin-btn-check-in" class="btn btn-success" style="padding: 6px 12px; font-size: 12px; font-weight: 700;" onclick="handleAdminCheckIn()" title="Record Check-In for this employee">
                                    🟢 Check In
                                </button>
                                <button type="button" id="admin-btn-check-out" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px; font-weight: 700;" onclick="handleAdminCheckOut()" title="Record Check-Out and complete shift for this employee">
                                    🔴 Check Out
                                </button>
                            </div>

                            <!-- Group 4: Sheet Actions -->
                            <div class="admin-ctrl-group admin-ctrl-actions">
                                <button type="button" id="admin-btn-toggle-holiday" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; border-color: #8b5cf6; color: #8b5cf6; font-weight: 700;" onclick="handleToggleHoliday()" title="Mark this date as employee's rotational Day Off / Holiday">
                                    🏖️ Day Off
                                </button>
                                <button type="button" id="admin-unlock-btn" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;" onclick="toggleSheetLock()">
                                    🔒 Lock Sheet
                                </button>
                                <button type="button" id="admin-btn-hod-check" class="btn btn-success" style="padding: 6px 13px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;" onclick="handleToggleHodCheckSheet()" title="Mark this worksheet as Checked & Verified by HOD">
                                    <span>✅</span> <span id="admin-btn-hod-check-text">Mark Checked</span>
                                </button>
                                <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;" onclick="openTrackingOptionsModal()" title="Customize Content Types & Tracking Departments">
                                    ⚙️ Options
                                </button>
                                <button type="button" id="admin-btn-print" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;" onclick="triggerPrintSheet()">
                                    🖨️ Print
                                </button>
                            </div>
                        </div>

                        <!-- Below Selection: Dedicated Line for Designation, Department, Team & Shift -->
                        <div id="admin-viewed-emp-meta-container" class="admin-inspector-meta-bar">
                            <span class="admin-inspector-meta-item">💼 <span><strong>Designation:</strong> <span id="admin-meta-designation" style="color: var(--text-main); font-weight: 600;">Staff</span></span></span>
                            <span class="meta-separator" style="color: var(--border-color);">|</span>
                            <span class="admin-inspector-meta-item">🏢 <span><strong>Department:</strong> <span id="admin-meta-dept" style="color: var(--text-main); font-weight: 600;">Digital</span></span></span>
                            <span class="meta-separator" style="color: var(--border-color);">|</span>
                            <span class="admin-inspector-meta-item">👥 <span><strong>Team:</strong> <span id="admin-meta-team" style="color: var(--text-main); font-weight: 600;">Team</span></span></span>
                            <span class="meta-separator" style="color: var(--border-color);">|</span>
                            <span class="admin-inspector-meta-item">🕒 <span><strong>Shift:</strong> In: <strong id="admin-meta-in" style="color: #0284c7;">--:--</strong> • Out: <strong id="admin-meta-out" style="color: #ea580c;">--:--</strong></span> <button type="button" class="btn btn-outline" style="padding: 2px 8px; font-size: 10.5px; border-radius: 12px; margin-left: 6px; font-weight: 600;" onclick="openAdminShiftModal()" title="Manually edit or backdate shift check-in and check-out times">✏️ Edit Shift</button></span>
                        </div>
                    </div>
                </div>

                <!-- EMPLOYEE REGULAR HEADER -->
                <div class="employee-only">
                    <div class="card-header-flex">
                        <div class="card-title-group">
                            <h3 id="worksheet-title-heading">Hourly Work Log Sheet</h3>
                            <p id="worksheet-title-desc">Record your work batches, content types, departments, and links.</p>
                        </div>

                        <div class="controls-group">
                            <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 12px;" onclick="goToPrevDay()" title="Previous Day">◀</button>
                                <input type="date" class="input-control worksheet-date-input" value="<?= date('Y-m-d') ?>" style="padding: 6px 10px;" onchange="changeWorksheetDate(this.value)">
                                <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 12px;" onclick="goToNextDay()" title="Next Day">▶</button>
                                <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 11.5px;" onclick="goToToday()" title="Jump to Today">Today</button>
                            </div>
                            <button type="button" class="btn btn-outline" onclick="addNewRowBelow()">
                                ➕ Add Row
                            </button>
                            <?php if (in_array($authUser['role'] ?? '', ['admin', 'super_admin', 'hod'])): ?>
                            <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px;" onclick="openTrackingOptionsModal()" title="Customize Content Types & Tracking Departments">
                                ⚙️ Options
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary" onclick="saveCurrentWorksheet(true)">
                                💾 Save Sheet
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Offline Draft Recovery Notification Banner -->
                <div id="worksheet-draft-banner" style="display: none; margin-bottom: 14px; padding: 12px 16px; background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: var(--radius-md); align-items: center; justify-content: space-between; font-size: 13px; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">💾</span>
                        <span><strong>Unsaved Offline Draft Found:</strong> We detected unsynced entries saved locally in your browser for this date (<span id="draft-banner-time">recent</span>).</span>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-primary" style="padding: 4px 12px; font-size: 12px; font-weight: 700;" onclick="restoreLocalDraft()">
                            ⚡ Restore My Entries
                        </button>
                        <button type="button" class="btn btn-outline" style="padding: 4px 10px; font-size: 12px;" onclick="discardLocalDraft()">
                            Dismiss
                        </button>
                    </div>
                </div>

                <!-- Dedicated Holiday & Approved Leave Rest Day View -->
                <div id="worksheet-holiday-card" style="display: none; padding: 28px 20px; text-align: center; background: radial-gradient(circle at 50% 0%, rgba(139, 92, 246, 0.08), transparent 70%), var(--bg-card-elevated); border: 1.5px dashed rgba(139, 92, 246, 0.35); border-radius: var(--radius-xl); margin: 16px 0 22px;">
                    <div style="width: 58px; height: 58px; margin: 0 auto 12px; background: rgba(139, 92, 246, 0.12); border: 2px solid rgba(139, 92, 246, 0.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px;">
                        <span id="holiday-card-icon">🏖️</span>
                    </div>
                    <div id="holiday-card-badge" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.6px; background: rgba(139, 92, 246, 0.15); color: #8b5cf6; margin-bottom: 8px;">
                        🏖️ Day Off
                    </div>
                    <h2 id="holiday-card-title" style="font-size: 20px; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">
                        Day Off
                    </h2>
                    <p id="holiday-card-subtitle" style="font-size: 13.5px; color: var(--text-muted); max-width: 500px; margin: 0 auto 16px; line-height: 1.5;">
                        Scheduled rest day. No worksheet required today.
                    </p>

                    <!-- Simple Date & Off Tag -->
                    <div style="display: inline-flex; align-items: center; gap: 10px; background: var(--bg-card); border: 1px solid var(--border-color); padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-bottom: 18px; box-shadow: var(--shadow-sm); flex-wrap: wrap; justify-content: center;">
                        <span id="holiday-card-date-label">Today</span>
                        <span style="color: var(--border-color);">•</span>
                        <span id="holiday-card-detail-label" style="color: #8b5cf6; font-weight: 700;">Weekly Off</span>
                    </div>

                    <!-- Action Controls -->
                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline" style="border-color: #8b5cf6; color: #8b5cf6; font-weight: 700; padding: 7px 18px; font-size: 12.5px;" onclick="handleToggleHoliday('working')">
                            ↩️ Switch to Work Day
                        </button>
                        <button type="button" class="btn btn-primary" style="padding: 7px 18px; font-weight: 700; font-size: 12.5px;" onclick="handleShowOvertimeTable()">
                            📝 Log Work Anyway
                        </button>
                    </div>
                </div>

                <!-- Regular Worksheet Content Wrapper (Table, Add Row, Summary) -->
                <div id="worksheet-content-wrapper">
                    <!-- Worksheet Table -->
                    <div class="table-responsive">
                        <table class="interactive-table">
                            <thead id="worksheet-table-head">
                                <tr>
                                    <th class="col-time-slot" style="width: 18%; min-width: 160px;">Time Slot</th>
                                    <th class="col-content-type" style="width: 15%; min-width: 150px;">Content Type</th>
                                    <th class="col-department" style="width: 15%; min-width: 150px;">Department</th>
                                    <th class="col-link" style="width: 20%; min-width: 170px;">Link / Upload</th>
                                    <th class="col-description" style="width: 28%; min-width: 210px;">Work Description / Title</th>
                                    <th class="col-action" style="width: 4%; min-width: 45px; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="worksheet-table-body">
                                <!-- Dynamic rows rendered here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Action Bar Below Last Row: Prominent + Add Row & Auto-Save status -->
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                        <button type="button" id="btn-add-row-bottom" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 13px; border-style: dashed; border-width: 1.5px; padding: 8px 20px; border-color: var(--primary); color: var(--primary); background: rgba(59, 130, 246, 0.05); border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s ease;" onclick="addNewRowBelow()" title="Add a new row at the bottom">
                            ➕ Add Row
                        </button>

                        <div id="worksheet-save-indicator" style="display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text-muted); background: var(--bg-card-elevated); padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border-color);">
                            <span id="save-indicator-icon" style="font-size: 14px;">☁️</span>
                            <span id="save-indicator-text">Auto-save ready</span>
                        </div>
                    </div>

                    <!-- Summary & Remarks Grid -->
                    <div class="form-split-grid">
                        <div class="form-group">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                <label class="form-label" style="margin-bottom: 0;">Work Summary</label>
                                <button type="button" class="btn btn-sm btn-outline" style="font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; border-color: var(--primary); color: var(--primary);" onclick="autoCalculateWorkSummary()" title="Auto-calculate counts from logged work entries">
                                    ⚡ Auto-Calculate Summary
                                </button>
                            </div>
                            <textarea id="worksheet-work-summary" class="form-textarea" placeholder="Overall summary of the day's completed tasks..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Remarks</label>
                            <textarea id="worksheet-remarks" class="form-textarea" placeholder="Any special remarks or blockers..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </section>
