        <!-- TAB 5: EMPLOYEE & DEPARTMENT HIERARCHY MANAGEMENT -->
        <section id="tab-employees" class="tab-section">
            <div class="worksheet-card">
                <!-- Sub-Navigation Switcher -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; gap: 8px;">
                        <button type="button" id="btn-emp-subtab-directory" class="btn btn-primary" style="font-size: 13px; font-weight: 700; padding: 7px 16px;" onclick="switchEmployeeSubView('directory')">
                            👥 Staff Directory <span id="emp-total-badge" class="badge" style="background: rgba(255,255,255,0.25); color: #fff; margin-left: 5px; font-size: 11px;">0</span>
                        </button>
                        <button type="button" id="btn-emp-subtab-departments" class="btn btn-outline" style="font-size: 13px; font-weight: 700; padding: 7px 16px;" onclick="switchEmployeeSubView('departments')">
                            🏢 Departments & Teams <span id="dept-total-badge" class="badge" style="background: var(--primary-light); color: var(--primary); margin-left: 5px; font-size: 11px;">0</span>
                        </button>
                    </div>

                    <!-- Header Action Buttons -->
                    <div class="controls-group" id="emp-view-controls">
                        <!-- Populated dynamically based on active subview and role -->
                    </div>
                </div>

                <!-- SUBVIEW 1: STAFF DIRECTORY -->
                <div id="emp-subview-directory">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; background: var(--bg-card-elevated); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <!-- Left: Search Box -->
                        <div style="position: relative; flex: 1; min-width: 220px; max-width: 320px;">
                            <input type="text" id="emp-dir-search" class="input-control" placeholder="🔍 Search name, email, role..." style="width: 100%; padding: 7px 12px; font-size: 12.5px; border-radius: var(--radius-md);" oninput="applyDirectoryFilters()">
                        </div>

                        <!-- Right: Compact Filter Dropdowns & Reset -->
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
                            <select id="emp-dir-filter-dept" class="input-control" style="width: auto; padding: 7px 11px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyDirectoryFilters()">
                                <option value="">🏢 All Departments</option>
                            </select>

                            <select id="emp-dir-filter-team" class="input-control" style="width: auto; padding: 7px 11px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyDirectoryFilters()">
                                <option value="">👥 All Teams</option>
                                <option value="assigned">✅ Assigned to Team</option>
                                <option value="unassigned">⚠️ Unassigned Staff</option>
                            </select>

                            <select id="emp-dir-filter-status" class="input-control" style="width: auto; padding: 7px 11px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyDirectoryFilters()">
                                <option value="active">🟢 Active Staff</option>
                                <option value="inactive">⛔ Deactivated</option>
                                <option value="all">🌐 All Status</option>
                            </select>

                            <select id="emp-dir-filter-login" class="input-control" style="width: auto; padding: 7px 11px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md);" onchange="applyDirectoryFilters()">
                                <option value="">🔑 All Access</option>
                                <option value="login_enabled">🔑 Login Enabled</option>
                                <option value="roster_only">👤 Roster Only</option>
                            </select>

                            <button type="button" id="emp-dir-reset-filters-btn" class="btn btn-outline" style="padding: 7px 10px; font-size: 12px; font-weight: 600; border-radius: var(--radius-md); display: none; color: var(--text-muted);" onclick="resetDirectoryFilters()" title="Reset all filters">
                                ↺ Reset
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="interactive-table">
                            <thead>
                                <tr>
                                    <th style="width: 21%;">Employee Name</th>
                                    <th style="width: 16%;">Official Email</th>
                                    <th style="width: 15%;">Role & Access</th>
                                    <th style="width: 14%;">Designation</th>
                                    <th style="width: 16%;">Department / Team</th>
                                    <th style="width: 18%; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="employee-directory-tbody">
                                <!-- Populated via employees.js -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SUBVIEW 2: DEPARTMENTS & TEAMS HIERARCHY -->
                <div id="emp-subview-departments" style="display: none;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.06) 0%, rgba(16, 185, 129, 0.06) 100%); padding: 14px 18px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <div>
                            <h4 style="margin: 0; font-size: 14px; color: var(--text-main); font-weight: 700;">🏢 Departmental Organization & HOD Team Control</h4>
                            <p style="margin: 3px 0 0 0; font-size: 12px; color: var(--text-muted);">HR creates company departments and employee accounts. Respective HODs build their own teams and assign departmental staff.</p>
                        </div>
                    </div>

                    <div id="departments-grid-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px;">
                        <!-- Populated via employees.js renderDepartmentsView() -->
                    </div>
                </div>
            </div>
        </section>
