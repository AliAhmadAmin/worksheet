        <!-- TAB 4: LIVE ATTENDANCE (ADMIN ONLY) -->
        <section id="tab-attendance" class="tab-section">
            <div class="worksheet-card">
                <div class="card-header-flex">
                    <div class="card-title-group">
                        <h3>👥 Live Employee Attendance Roster</h3>
                        <p>Real-time check-in, check-out, duty hours, and worksheet status across all teams.</p>
                    </div>
                    <div class="controls-group">
                        <input type="date" id="attendance-board-date" class="input-control" value="<?= date('Y-m-d') ?>" onchange="loadLiveAttendance()">
                        <button type="button" class="btn btn-outline" onclick="loadLiveAttendance()">
                            🔄 Refresh
                        </button>
                    </div>
                </div>

                <!-- Stats Badges -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
                    <div style="background: var(--bg-card-elevated); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">TOTAL EMPLOYEES</span>
                        <h3 id="att-stat-total" style="font-size: 22px; color: var(--primary);">23</h3>
                    </div>
                    <div style="background: var(--bg-card-elevated); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">ON DUTY (WORKING)</span>
                        <h3 id="att-stat-working" style="font-size: 22px; color: #34d399;">0</h3>
                    </div>
                    <div style="background: var(--bg-card-elevated); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">CHECKED OUT (LOCKED)</span>
                        <h3 id="att-stat-out" style="font-size: 22px; color: #60a5fa;">0</h3>
                    </div>
                    <div style="background: var(--bg-card-elevated); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 700;">NOT ARRIVED / ABSENT</span>
                        <h3 id="att-stat-absent" style="font-size: 22px; color: #94a3b8;">0</h3>
                    </div>
                </div>

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
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="live-attendance-tbody">
                            <!-- Populated via reports.js -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
