        <!-- TAB 2: DEDICATED FULL-PAGE TASK ASSIGNER COMMAND CENTER -->
        <section id="tab-tasks" class="tab-section">
            <div class="worksheet-card">
                <div class="card-header-flex">
                    <div class="card-title-group">
                        <h3>🎯 Task Assignment Command Center</h3>
                        <p>Assign specific tasks to individual team members, filter by status, and monitor real-time completion.</p>
                    </div>
                    <div class="controls-group">
                        <select id="task-filter-status" class="input-control" onchange="loadAssignedTasks()">
                            <option value="">All Statuses</option>
                            <option value="pending">⏳ Pending</option>
                            <option value="in_progress">⚡ In Progress</option>
                            <option value="completed">✅ Completed</option>
                        </select>

                        <!-- Searchable Employee Filter Combobox (Admin Only) -->
                        <div class="searchable-dropdown-wrapper admin-only" id="task-emp-search-wrapper" style="position: relative; display: inline-block;">
                            <button type="button" id="task-emp-dropdown-btn" class="input-control" style="font-weight: 600; font-size: 13px; padding: 7px 12px; min-width: 140px; width: auto; max-width: 210px; border-color: var(--border-color); background: var(--bg-card); cursor: pointer; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left;" onclick="toggleTaskEmpDropdown()">
                                <span id="task-emp-dropdown-selected-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">All Employees</span>
                                <span style="font-size: 10px; color: var(--text-muted);">▼</span>
                            </button>

                            <!-- Hidden select for form value binding -->
                            <select id="task-filter-emp" style="display: none;" onchange="loadAssignedTasks()">
                                <option value="">All Employees</option>
                            </select>

                            <!-- Floating Search Dropdown Menu -->
                            <div id="task-emp-dropdown-menu" class="searchable-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); right: 0; min-width: 240px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 1050; padding: 8px; text-align: left;">
                                <div style="padding-bottom: 6px; border-bottom: 1px solid var(--border-color); margin-bottom: 6px;">
                                    <input type="text" id="task-emp-search-input" class="input-control" placeholder="🔍 Search employee..." style="width: 100%; padding: 6px 10px; font-size: 12px;" oninput="filterTaskEmpDropdown(this.value)" autocomplete="off">
                                </div>
                                <div id="task-emp-dropdown-list" style="max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                                    <!-- Populated dynamically -->
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-primary admin-only" onclick="openAssignTaskModal()">
                            ➕ Assign New Task
                        </button>
                    </div>
                </div>

                <!-- Task Metrics KPI Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px;">
                    <div style="background: var(--bg-card-elevated); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">TOTAL TASKS</span>
                        <h3 id="task-metric-total" style="font-size: 22px; color: var(--primary);">0</h3>
                    </div>
                    <div style="background: var(--bg-card-elevated); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">PENDING</span>
                        <h3 id="task-metric-pending" style="font-size: 22px; color: #eab308;">0</h3>
                    </div>
                    <div style="background: var(--bg-card-elevated); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">IN PROGRESS</span>
                        <h3 id="task-metric-progress" style="font-size: 22px; color: #3b82f6;">0</h3>
                    </div>
                    <div style="background: var(--bg-card-elevated); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">COMPLETED</span>
                        <h3 id="task-metric-completed" style="font-size: 22px; color: #10b981;">0</h3>
                    </div>
                </div>

                <!-- Tasks Master Table -->
                <div class="table-responsive">
                    <table class="interactive-table">
                        <thead>
                            <tr>
                                <th style="width: 14%;">Assigned To</th>
                                <th style="width: 20%;">Task Title / Instructions</th>
                                <th style="width: 12%;">Category & Priority</th>
                                <th style="width: 15%;">Assigned Date & Time</th>
                                <th style="width: 15%;">Completion Date & Time</th>
                                <th style="width: 12%;">Status</th>
                                <th style="width: 12%; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="admin-tasks-tbody">
                            <!-- Populated via tasks.js -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
