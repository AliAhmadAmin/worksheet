        <!-- TAB 5: EMPLOYEE MANAGEMENT & DIRECTORY (ADMIN ONLY) -->
        <section id="tab-employees" class="tab-section">
            <div class="worksheet-card">
                <div class="card-header-flex">
                    <div class="card-title-group">
                        <h3>⚙️ Employee Directory & Roster Management</h3>
                        <p>Add new employees, edit profiles, update email addresses, change login passwords, or remove team members.</p>
                    </div>
                    <div class="controls-group">
                        <button type="button" class="btn btn-primary" onclick="openAddEmployeeModal()">
                            ➕ Add New Employee
                        </button>
                        <button type="button" class="btn btn-outline" onclick="loadEmployeeDirectory()">
                            🔄 Refresh List
                        </button>
                    </div>
                </div>

                <div class="table-responsive" style="margin-top: 15px;">
                    <table class="interactive-table">
                        <thead>
                            <tr>
                                <th style="width: 17%;">Employee Name</th>
                                <th style="width: 17%;">Official Email</th>
                                <th style="width: 12%;">Role & Access</th>
                                <th style="width: 12%;">Designation</th>
                                <th style="width: 11%;">Team</th>
                                <th style="width: 9%;">Department</th>
                                <th style="width: 22%; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employee-directory-tbody">
                            <!-- Populated via employees.js -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
