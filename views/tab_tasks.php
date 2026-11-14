        <!-- TAB 2: DEDICATED FULL-PAGE ASSIGN TASK COMMAND CENTER -->
        <section id="tab-tasks" class="tab-section <?= (($currentPage ?? '') === 'tasks' || !isset($currentPage)) ? 'active' : '' ?>">
            <div class="worksheet-card">
                <!-- Header with Title and Primary Actions -->
                <div class="card-header-flex" style="flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                    <div class="card-title-group">
                        <h3 id="tasks-page-title" style="display: flex; align-items: center; gap: 8px; margin: 0; font-size: 18px; font-weight: 800;">
                            <span>🎯</span> <span>Assign Task Command Center</span>
                        </h3>
                        <p id="tasks-page-desc" style="margin: 3px 0 0 0; font-size: 12.5px; color: var(--text-muted);">Assign specific tasks to individual team members, filter by status and date range, and monitor real-time completion.</p>
                    </div>

                    <div class="controls-group" style="display: flex; align-items: center; gap: 10px;">
                        <button type="button" class="btn btn-outline admin-only" onclick="openHodWhatsAppModal()" title="Configure your personal OrbitSend WhatsApp credentials & AI Vision" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-size: 12.5px; padding: 7px 13px; border-radius: 6px;">
                            <span>💬</span> <span>WhatsApp & AI</span>
                        </button>

                        <button type="button" class="btn btn-primary admin-only" onclick="openAssignTaskModal()" style="font-weight: 700; font-size: 12.5px; padding: 7px 16px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                            ➕ Assign New Task
                        </button>
                    </div>
                </div>

                <!-- Interactive KPI Metrics Cards -->
                <style>
                    .task-kpi-card {
                        cursor: pointer;
                        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
                        user-select: none;
                    }
                    .task-kpi-card:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                    }
                    .task-kpi-card.active-card {
                        box-shadow: 0 0 0 2.5px var(--primary, #3b82f6), 0 4px 14px rgba(0,0,0,0.12);
                        transform: translateY(-2px);
                    }
                </style>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 18px;">
                    <div id="card-task-total" class="task-kpi-card" onclick="filterTasksByCard('all')" style="background: var(--bg-card-elevated); padding: 14px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); border-left: 4px solid var(--primary, #3b82f6);" title="Click to view all tasks">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">📋 TOTAL TASKS</span>
                        <h3 id="task-metric-total" style="font-size: 24px; font-weight: 800; color: var(--primary, #3b82f6); margin: 4px 0 0 0; line-height: 1;">0</h3>
                    </div>
                    <div id="card-task-pending" class="task-kpi-card" onclick="filterTasksByCard('pending')" style="background: var(--bg-card-elevated); padding: 14px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); border-left: 4px solid #eab308;" title="Click to filter Pending tasks">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">⏳ PENDING</span>
                        <h3 id="task-metric-pending" style="font-size: 24px; font-weight: 800; color: #eab308; margin: 4px 0 0 0; line-height: 1;">0</h3>
                    </div>
                    <div id="card-task-progress" class="task-kpi-card" onclick="filterTasksByCard('in_progress')" style="background: var(--bg-card-elevated); padding: 14px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); border-left: 4px solid #6366f1;" title="Click to filter In Progress tasks">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">⚡ IN PROGRESS</span>
                        <h3 id="task-metric-progress" style="font-size: 24px; font-weight: 800; color: #6366f1; margin: 4px 0 0 0; line-height: 1;">0</h3>
                    </div>
                    <div id="card-task-completed" class="task-kpi-card" onclick="filterTasksByCard('completed')" style="background: var(--bg-card-elevated); padding: 14px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); border-left: 4px solid #10b981;" title="Click to filter Completed tasks">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">✅ COMPLETED</span>
                        <h3 id="task-metric-completed" style="font-size: 24px; font-weight: 800; color: #10b981; margin: 4px 0 0 0; line-height: 1;">0</h3>
                    </div>
                </div>

                <!-- Dedicated Filters & Search Toolbar -->
                <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: flex-end; flex-wrap: wrap; gap: 10px;">
                        <!-- Live Search Input -->
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🔍 Search</label>
                            <input type="text" id="task-search-input" class="input-control" placeholder="Search person, code, designation, title..." oninput="handleTaskSearchInput(this.value)" style="padding: 7px 11px; font-size: 12.5px; height: 38px; border-radius: 6px; width: 100%;">
                        </div>

                        <!-- Date From -->
                        <div style="flex: 0 0 140px;">
                            <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">📅 Date From</label>
                            <input type="date" id="task-filter-from" class="input-control" value="" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="loadAssignedTasks()">
                        </div>

                        <!-- Date To -->
                        <div style="flex: 0 0 140px;">
                            <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">📅 Date To</label>
                            <input type="date" id="task-filter-to" class="input-control" value="" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="loadAssignedTasks()">
                        </div>

                        <!-- Hidden inputs for state preservation (status filter driven by KPI cards) -->
                        <input type="hidden" id="task-filter-status" value="">
                        <input type="hidden" id="task-filter-emp" value="">

                        <!-- View Toggle: By Member vs Task List -->
                        <div class="admin-only" style="flex: 0 0 auto;">
                            <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">👁️ View</label>
                            <div style="display: inline-flex; background: var(--bg-card); padding: 3px; border-radius: 6px; border: 1px solid var(--border-color); gap: 2px; height: 38px; align-items: center;">
                                <button type="button" id="btn-tasks-view-team" class="btn btn-sm btn-primary" onclick="switchTasksView('team')" style="padding: 5px 12px; font-size: 12px; font-weight: 700; border-radius: 4px; border: none; height: 30px;">
                                    👥 By Member
                                </button>
                                <button type="button" id="btn-tasks-view-list" class="btn btn-sm btn-outline" onclick="switchTasksView('list')" style="padding: 5px 12px; font-size: 12px; font-weight: 700; border-radius: 4px; border: none; height: 30px;">
                                    📋 Task List
                                </button>
                            </div>
                        </div>

                        <!-- Reset Button -->
                        <div style="flex: 0 0 auto;">
                            <label style="display: block; font-size: 11px; font-weight: 700; color: transparent; margin-bottom: 4px;">Reset</label>
                            <button type="button" class="btn btn-outline" style="height: 38px; padding: 0 12px; font-size: 12.5px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;" onclick="resetTaskFilters()" title="Reset all task filters">
                                ↺ Reset
                            </button>
                        </div>
                    </div>
                </div>

                <!-- VIEW 1: Team Members Dashboard (Grouped by Person - Default) -->
                <div id="tasks-team-view" class="table-responsive">
                    <table class="interactive-table">
                        <thead>
                            <tr>
                                <th style="width: 28%;">Team Member</th>
                                <th style="width: 14%;">Department</th>
                                <th style="width: 10%; text-align: center;">Total Tasks</th>
                                <th style="width: 11%; text-align: center;">Pending</th>
                                <th style="width: 11%; text-align: center;">In Progress</th>
                                <th style="width: 11%; text-align: center;">Completed</th>
                                <th style="width: 15%; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="team-tasks-tbody">
                            <!-- Populated via tasks.js -->
                        </tbody>
                    </table>
                </div>

                <!-- VIEW 2: All Tasks Flat Table -->
                <div id="tasks-list-view" class="table-responsive" style="display: none;">
                    <table class="interactive-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;" id="th-task-person">Assigned To</th>
                                <th style="width: 20%;">Task Title</th>
                                <th style="width: 25%;">Description & Link</th>
                                <th style="width: 10%;">Assigned</th>
                                <th style="width: 10%;">Completed</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 10%; text-align: center;" id="th-task-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="admin-tasks-tbody">
                            <!-- Populated via tasks.js -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
