    <!-- MODAL: MANDATORY LOGIN WITH FRESH & UPLIFTING UI -->
    <div id="login-modal" class="modal-overlay login-overlay-backdrop">
        <!-- Floating ambient glowing orbs -->
        <div class="login-ambient-orb orb-1"></div>
        <div class="login-ambient-orb orb-2"></div>
        <div class="login-ambient-orb orb-3"></div>

        <div class="modal-card login-card-fresh">
            <!-- Joyful Header Banner -->
            <div class="login-header-fresh">
                <div class="login-brand-pill">
                    <img src="assets/img/logo.svg" alt="Discover Pakistan UHD TV" class="login-logo-img">
                </div>
                <h3 id="login-greeting-title" class="login-greeting-title">Welcome Back, Creator! ✨</h3>
                <p class="login-greeting-sub">Ready to make today impactful? Sign in to your workspace.</p>
            </div>

            <form id="login-form" onsubmit="handleLoginSubmit(event)">
                <div class="modal-body login-body-fresh">
                    <!-- Quick Account Selector (Pill / Avatar styled) -->
                    <div class="form-group login-field-group">
                        <label class="form-label login-label">
                            <span>⚡ Quick Choose Profile</span>
                            <span class="login-optional-tag">1-Click Auto Fill</span>
                        </label>
                        <div class="login-input-wrapper">
                            <span class="login-input-icon">👤</span>
                            <select id="login-quick-email" class="input-control login-input-control" onchange="handleQuickAccountSelect(this.value)">
                                <!-- Populated via app.js -->
                            </select>
                        </div>
                    </div>

                    <!-- Email Input -->
                    <div class="form-group login-field-group">
                        <label class="form-label login-label">Official Employee Email *</label>
                        <div class="login-input-wrapper">
                            <span class="login-input-icon">✉️</span>
                            <input type="email" id="login-email" class="input-control login-input-control" placeholder="e.g. yourname@discoverpakistan.tv" value="zeeeguest@gmail.com" required autocomplete="username">
                        </div>
                    </div>

                    <!-- Password Input with Show/Hide Toggle -->
                    <div class="form-group login-field-group">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <label class="form-label login-label" style="margin-bottom: 0;">Password *</label>
                            <span class="login-default-hint" onclick="fillDefaultPassword()" title="Click to auto-fill default password">
                                🔑 Default: <code>DiscoverPakistan123</code>
                            </span>
                        </div>
                        <div class="login-input-wrapper" style="margin-top: 6px;">
                            <span class="login-input-icon">🔒</span>
                            <input type="password" id="login-password" class="input-control login-input-control" placeholder="Enter password" value="DiscoverPakistan123" required autocomplete="current-password">
                            <button type="button" class="btn-toggle-password" onclick="toggleLoginPasswordVisibility()" title="Show/Hide Password">
                                <span id="login-password-eye-icon">👁️</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer login-footer-fresh">
                    <button type="submit" id="login-submit-btn" class="btn btn-primary login-cta-btn">
                        <span>🚀</span>
                        <span>Sign In to WorkSheet Pro</span>
                    </button>
                    <div class="login-motivational-quote">
                        🌟 Discover Pakistan UHD TV • Teamwork & Creativity
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- MODAL: ASSIGN NEW TASK -->
    <div id="assign-task-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>🎯 Assign Task to Employee</h3>
                <button type="button" class="btn-modal-close" onclick="closeModal('assign-task-modal')">✕</button>
            </div>
            <form id="assign-task-form" onsubmit="handleCreateTaskSubmit(event)">
                <div class="modal-body">
                    <!-- Assign to Employee (Searchable with Avatar like Dashboard) -->
                    <div class="form-group">
                        <label class="form-label">Assign To Employee *</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                            <div id="assign-task-emp-avatar" class="admin-inspector-avatar" style="width: 38px; height: 38px; font-size: 16px;">👤</div>
                            <div class="searchable-dropdown-wrapper" id="assign-task-emp-search-wrapper" style="position: relative; flex: 1;">
                                <button type="button" id="assign-task-emp-dropdown-btn" class="input-control" style="font-weight: 700; font-size: 13.5px; padding: 8px 12px; width: 100%; border-color: var(--primary); background: var(--bg-card); cursor: pointer; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left;" onclick="toggleAssignTaskEmpDropdown()">
                                    <span id="assign-task-emp-selected-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-- Select Employee --</span>
                                    <span style="font-size: 10px; color: var(--text-muted);">▼</span>
                                </button>
                                <input type="hidden" id="assign-task-emp-select" value="" required>

                                <!-- Floating Search Dropdown Menu -->
                                <div id="assign-task-emp-dropdown-menu" class="searchable-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 1050; padding: 8px; text-align: left;">
                                    <div style="padding-bottom: 6px; border-bottom: 1px solid var(--border-color); margin-bottom: 6px;">
                                        <input type="text" id="assign-task-emp-search-input" class="input-control" placeholder="🔍 Search employee by name, team..." style="width: 100%; padding: 6px 10px; font-size: 12.5px;" oninput="filterAssignTaskEmpDropdown(this.value)" autocomplete="off">
                                    </div>
                                    <div id="assign-task-emp-dropdown-list" style="max-height: 200px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                                        <!-- Populated dynamically -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Task Title / Output Name *</label>
                        <input type="text" id="assign-task-title" class="input-control" placeholder="e.g. Create 10 Reels on Breaking News" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Instructions / Description</label>
                        <textarea id="assign-task-desc" class="form-textarea" placeholder="Specific guidelines, format, or tags..." rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Path / Link (Local Folder or Web URL)</label>
                        <input type="text" id="assign-task-link" class="input-control" placeholder="e.g. C:\xampp\htdocs\Worksheet or https://drive.google.com/..." oninput="this.value = this.value.replace(/^[&quot;']+|[&quot;']+$/g, '').trim()" style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('assign-task-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT ASSIGNED TASK -->
    <div id="edit-task-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>✏️ Edit Assigned Task</h3>
                <button type="button" class="btn-modal-close" onclick="closeModal('edit-task-modal')">✕</button>
            </div>
            <form id="edit-task-form" onsubmit="handleEditTaskSubmit(event)">
                <input type="hidden" id="edit-task-id">
                <div class="modal-body">
                    <!-- Assign to Employee (Searchable with Avatar like Dashboard) -->
                    <div class="form-group">
                        <label class="form-label">Assign To Employee *</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                            <div id="edit-task-emp-avatar" class="admin-inspector-avatar" style="width: 38px; height: 38px; font-size: 16px;">👤</div>
                            <div class="searchable-dropdown-wrapper" id="edit-task-emp-search-wrapper" style="position: relative; flex: 1;">
                                <button type="button" id="edit-task-emp-dropdown-btn" class="input-control" style="font-weight: 700; font-size: 13.5px; padding: 8px 12px; width: 100%; border-color: var(--primary); background: var(--bg-card); cursor: pointer; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left;" onclick="toggleEditTaskEmpDropdown()">
                                    <span id="edit-task-emp-selected-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-- Select Employee --</span>
                                    <span style="font-size: 10px; color: var(--text-muted);">▼</span>
                                </button>
                                <input type="hidden" id="edit-task-emp-select" value="" required>

                                <!-- Floating Search Dropdown Menu -->
                                <div id="edit-task-emp-dropdown-menu" class="searchable-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 1050; padding: 8px; text-align: left;">
                                    <div style="padding-bottom: 6px; border-bottom: 1px solid var(--border-color); margin-bottom: 6px;">
                                        <input type="text" id="edit-task-emp-search-input" class="input-control" placeholder="🔍 Search employee by name, team..." style="width: 100%; padding: 6px 10px; font-size: 12.5px;" oninput="filterEditTaskEmpDropdown(this.value)" autocomplete="off">
                                    </div>
                                    <div id="edit-task-emp-dropdown-list" style="max-height: 200px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                                        <!-- Populated dynamically -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Task Title / Output Name *</label>
                        <input type="text" id="edit-task-title" class="input-control" placeholder="e.g. Create 10 Reels on Breaking News" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Instructions / Description</label>
                        <textarea id="edit-task-desc" class="form-textarea" placeholder="Specific guidelines, format, or tags..." rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Path / Link (Local Folder or Web URL)</label>
                        <input type="text" id="edit-task-link" class="input-control" placeholder="e.g. C:\xampp\htdocs\Worksheet or https://drive.google.com/..." oninput="this.value = this.value.replace(/^[&quot;']+|[&quot;']+$/g, '').trim()" style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="edit-task-status" class="input-control" style="padding: 7px 12px; font-weight: 600;">
                            <option value="pending">⏳ Pending</option>
                            <option value="in_progress">⚡ In Progress</option>
                            <option value="completed">✅ Completed</option>
                        </select>
                    </div>

                    <!-- Timestamps Log Display -->
                    <div style="margin-top: 10px; padding: 12px 14px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 12px; display: flex; flex-direction: column; gap: 6px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="color: var(--text-muted); font-weight: 600;">📅 Assigned Date & Time:</span>
                            <span id="edit-task-created-at-display" style="font-weight: 700; color: var(--text-main); font-family: 'JetBrains Mono', monospace;">—</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="color: var(--text-muted); font-weight: 600;">🏁 Completed Date & Time:</span>
                            <span id="edit-task-completed-at-display" style="font-weight: 700; color: #10b981; font-family: 'JetBrains Mono', monospace;">— Not Completed</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('edit-task-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: VIEW FULL TASK DETAILS -->
    <div id="view-task-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 540px;">
            <div class="modal-header">
                <h3>📌 Task Details</h3>
                <button type="button" class="btn-modal-close" onclick="closeModal('view-task-modal')">✕</button>
            </div>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Task Title</div>
                    <div id="view-task-title" style="font-size: 16px; font-weight: 800; color: var(--text-main); margin-top: 3px;">—</div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Assigned To</div>
                        <div id="view-task-emp-name" style="font-weight: 700; color: var(--text-main); font-size: 13.5px;">—</div>
                    </div>
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Status</div>
                        <div id="view-task-status-badge">—</div>
                    </div>
                </div>

                <div>
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Instructions / Description</div>
                    <div id="view-task-desc" style="margin-top: 4px; padding: 10px 12px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 13px; color: var(--text-main); white-space: pre-wrap; line-height: 1.5; min-height: 48px;">—</div>
                </div>

                <div>
                    <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Path / Link</div>
                    <div id="view-task-link" style="margin-top: 4px;">—</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px; padding: 10px 12px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                    <div>
                        <span style="color: var(--text-muted); font-weight: 600;">🕒 Assigned:</span>
                        <div id="view-task-created-at" style="font-weight: 700; color: var(--text-main); font-family: 'JetBrains Mono', monospace; margin-top: 2px;">—</div>
                    </div>
                    <div>
                        <span style="color: var(--text-muted); font-weight: 600;">✅ Completed:</span>
                        <div id="view-task-completed-at" style="font-weight: 700; color: #10b981; font-family: 'JetBrains Mono', monospace; margin-top: 2px;">—</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <button type="button" id="view-task-edit-btn" class="btn btn-outline" onclick="">✏️ Edit Task</button>
                <button type="button" class="btn btn-primary" onclick="closeModal('view-task-modal')">Close</button>
            </div>
        </div>
    </div>

    <!-- MODAL: ADD EMPLOYEE -->
    <div id="add-employee-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>➕ Add New Employee</h3>
                <button type="button" class="btn-modal-close" onclick="closeModal('add-employee-modal')">✕</button>
            </div>
            <form id="add-employee-form" onsubmit="handleAddEmployeeSubmit(event)">
                <div class="modal-body">
                    <!-- Avatar Upload & Live Preview -->
                    <div class="form-group">
                        <label class="form-label">Employee Profile Picture (Image)</label>
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div id="add-emp-avatar-preview" class="user-avatar" style="width: 52px; height: 52px; font-size: 22px; flex-shrink: 0; background-size: cover; background-position: center; border: 2px solid var(--border-color);">👤</div>
                            <div style="flex: 1;">
                                <input type="file" id="add-emp-avatar-file" class="input-control" accept="image/*" onchange="handleAvatarFileSelect(this, 'add-emp-avatar-preview', 'add-emp-avatar-base64')">
                                <input type="hidden" id="add-emp-avatar-base64">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" id="add-emp-name" class="input-control" placeholder="e.g. Ali Raza" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Official Email Address *</label>
                        <input type="email" id="add-emp-email" class="input-control" placeholder="e.g. employee@discoverpakistan.tv" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial Password *</label>
                        <input type="text" id="add-emp-password" class="input-control" value="DiscoverPakistan123" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">System Role</label>
                            <select id="add-emp-role" class="input-control">
                                <option value="employee" selected>Employee</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Designation / Title</label>
                            <input type="text" id="add-emp-designation" class="input-control" placeholder="e.g. Video Editor" value="Content Creator">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select id="add-emp-dept" class="input-control">
                                <!-- Populated dynamically -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assigned Team</label>
                            <select id="add-emp-team" class="input-control">
                                <!-- Populated dynamically -->
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('add-employee-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Employee Profile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT EMPLOYEE -->
    <div id="edit-employee-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>✏️ Edit Employee Details</h3>
                <button type="button" class="btn-modal-close" onclick="closeModal('edit-employee-modal')">✕</button>
            </div>
            <form id="edit-employee-form" onsubmit="handleEditEmployeeSubmit(event)">
                <input type="hidden" id="edit-emp-id">
                <div class="modal-body">
                    <!-- Avatar Upload & Live Preview -->
                    <div class="form-group">
                        <label class="form-label">Employee Profile Picture (Image)</label>
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div id="edit-emp-avatar-preview" class="user-avatar" style="width: 52px; height: 52px; font-size: 22px; flex-shrink: 0; background-size: cover; background-position: center; border: 2px solid var(--border-color);">👤</div>
                            <div style="flex: 1;">
                                <input type="file" id="edit-emp-avatar-file" class="input-control" accept="image/*" onchange="handleAvatarFileSelect(this, 'edit-emp-avatar-preview', 'edit-emp-avatar-base64')">
                                <input type="hidden" id="edit-emp-avatar-base64">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" id="edit-emp-name" class="input-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Official Email Address *</label>
                        <input type="email" id="edit-emp-email" class="input-control" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">System Role</label>
                            <select id="edit-emp-role" class="input-control">
                                <option value="employee">Employee</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Designation / Title</label>
                            <input type="text" id="edit-emp-designation" class="input-control">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select id="edit-emp-dept" class="input-control">
                                <!-- Populated dynamically -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Assigned Team</label>
                            <select id="edit-emp-team" class="input-control">
                                <!-- Populated dynamically -->
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('edit-employee-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: UPDATE PASSWORD -->
    <div id="password-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 440px;">
            <div class="modal-header">
                <h3>🔑 Update Employee Password</h3>
                <button type="button" class="btn-modal-close" onclick="closeModal('password-modal')">✕</button>
            </div>
            <form id="update-password-form" onsubmit="handlePasswordSubmit(event)">
                <input type="hidden" id="pwd-emp-id">
                <div class="modal-body">
                    <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
                        Set a new login password for <strong id="pwd-emp-name-label" style="color: var(--primary);">Employee</strong>:
                    </p>
                    <div class="form-group">
                        <label class="form-label">New Password *</label>
                        <input type="text" id="pwd-new-password" class="input-control" placeholder="Enter new password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('password-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EMPLOYEE MY PROFILE & ACCOUNT SETTINGS -->
    <div id="my-profile-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 520px;">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">👤</span>
                    <h3 style="font-size: 17px; font-weight: 800;">My Profile & Settings</h3>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('my-profile-modal')">✕</button>
            </div>
            <form id="my-profile-form" onsubmit="handleMyProfileSubmit(event)">
                <div class="modal-body" style="padding: 20px; display: flex; flex-direction: column; gap: 16px;">
                    <!-- Avatar Upload & Live Preview Section -->
                    <div style="display: flex; align-items: center; gap: 18px; padding: 14px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                        <div id="my-profile-avatar-preview" class="user-avatar" style="width: 68px; height: 68px; font-size: 26px; flex-shrink: 0; background-size: cover; background-position: center; border: 3px solid var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">👤</div>
                        <div style="flex: 1;">
                            <label class="form-label" style="font-size: 13px; font-weight: 700; margin-bottom: 4px;">Profile Photo</label>
                            <p style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 8px;">Upload your official profile photo (JPG/PNG)</p>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <label class="btn btn-outline" style="padding: 5px 12px; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                    📷 Change Photo
                                    <input type="file" id="my-profile-avatar-file" accept="image/*" style="display: none;" onchange="handleMyProfilePhotoSelect(this)">
                                </label>
                                <button type="button" class="btn btn-outline" style="padding: 5px 10px; font-size: 12px; color: #ef4444; border-color: rgba(239, 68, 68, 0.3);" onclick="handleRemoveMyProfilePhoto()">
                                    🗑️ Remove
                                </button>
                            </div>
                            <input type="hidden" id="my-profile-avatar-base64">
                            <input type="hidden" id="my-profile-avatar-action" value="keep">
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" id="my-profile-name" class="input-control" required placeholder="Your full name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Official Email Address *</label>
                        <input type="email" id="my-profile-email" class="input-control" required placeholder="your.email@discoverpakistan.tv">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">Designation / Title</label>
                            <input type="text" id="my-profile-designation" class="input-control" placeholder="e.g. Social Lead, Video Editor">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department & Team</label>
                            <input type="text" id="my-profile-dept-team" class="input-control" readonly disabled style="background: var(--bg-hover); opacity: 0.85; font-size: 12px;">
                        </div>
                    </div>

                    <!-- Optional Password Update -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 14px; margin-top: 4px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <label class="form-label" style="font-weight: 700; margin: 0;">Change Login Password <span style="font-weight: normal; color: var(--text-dim); font-size: 11.5px;">(Optional)</span></label>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="password" id="my-profile-new-password" class="input-control" placeholder="New Password">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="password" id="my-profile-confirm-password" class="input-control" placeholder="Confirm Password">
                            </div>
                        </div>
                        <small style="font-size: 11px; color: var(--text-dim); display: block; margin-top: 4px;">Leave blank to keep your existing password.</small>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 14px 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('my-profile-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 18px;">💾 Save Profile Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ROLE & ACCESS DELEGATION (ADMIN / HOD ONLY) -->
    <div id="permissions-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 660px;">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 19px;">🛡️</span>
                    <h3 style="font-size: 16.5px; font-weight: 800;">Role & Access Permissions Delegation</h3>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('permissions-modal')">✕</button>
            </div>
            <form id="permissions-form" onsubmit="handleSavePermissionsSubmit(event)">
                <input type="hidden" id="perm-emp-id">
                <div class="modal-body" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 14px;">
                    <!-- Employee Info Banner -->
                    <div style="display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-lg);">
                        <div id="perm-emp-avatar" class="user-avatar" style="width: 40px; height: 40px; font-size: 16px; flex-shrink: 0; background-size: cover; background-position: center; border: 2px solid var(--border-color);">👤</div>
                        <div style="flex: 1; overflow: hidden; min-width: 0;">
                            <div id="perm-emp-name" style="font-weight: 800; font-size: 14.5px; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Employee Name</div>
                            <div id="perm-emp-meta" style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Designation • Department • Team</div>
                        </div>
                    </div>

                    <!-- Role Preset Selector -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px; margin-bottom: 5px;">Assign Organizational Role Preset *</label>
                        <select id="perm-role-select" class="input-control" onchange="applyRolePreset(this.value)" style="font-weight: 600; font-size: 13px;">
                            <option value="admin">👑 Super Admin (Full Company-Wide Control)</option>
                            <option value="hod">🎖️ Head of Department / HOD (Manage Tasks, Sheets & Reports)</option>
                            <option value="team_lead">⭐ Team Lead / Supervisor (Assign Tasks & Unlock Team Sheets)</option>
                            <option value="coordinator">🎯 Task Coordinator (Create & Manage Tasks Across Teams)</option>
                            <option value="employee" selected>👤 Staff Member (Standard Creator / Daily Worksheet)</option>
                        </select>
                        <small style="font-size: 11px; color: var(--text-dim); display: block; margin-top: 3px;">
                            Selecting a preset will automatically configure the standard recommended capabilities below. You can also fine-tune individual permissions.
                        </small>
                    </div>

                    <!-- Granular Permission Toggles Matrix -->
                    <div>
                        <label class="form-label" style="font-weight: 700; font-size: 12.5px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                            <span>Granular Delegated Capabilities</span>
                            <span style="font-weight: normal; font-size: 11px; color: var(--text-dim);">Custom check/uncheck allowed</span>
                        </label>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 8px;">
                            <!-- Perm 1: Assign Tasks -->
                            <label class="perm-checkbox-card" style="display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease;">
                                <input type="checkbox" id="perm-can-assign-tasks" style="margin-top: 2px; cursor: pointer; transform: scale(1.1);">
                                <div>
                                    <strong style="font-size: 12.5px; color: var(--text-main); display: block;">🎯 Assign New Tasks</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can create and assign work tasks and deliverables to any employee.</span>
                                </div>
                            </label>

                            <!-- Perm 2: Edit & Delete Tasks -->
                            <label class="perm-checkbox-card" style="display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease;">
                                <input type="checkbox" id="perm-can-edit-tasks" style="margin-top: 2px; cursor: pointer; transform: scale(1.1);">
                                <div>
                                    <strong style="font-size: 12.5px; color: var(--text-main); display: block;">✏️ Edit & Delete Tasks</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can modify task instructions, change assigned staff, and remove tasks.</span>
                                </div>
                            </label>

                            <!-- Perm 3: Lock & Unlock Sheets -->
                            <label class="perm-checkbox-card" style="display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease;">
                                <input type="checkbox" id="perm-can-unlock-sheets" style="margin-top: 2px; cursor: pointer; transform: scale(1.1);">
                                <div>
                                    <strong style="font-size: 12.5px; color: var(--text-main); display: block;">🔓 Lock & Unlock Sheets</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can lock or reopen locked worksheets for editing and corrections.</span>
                                </div>
                            </label>

                            <!-- Perm 4: Inspect & Edit Team Sheets -->
                            <label class="perm-checkbox-card" style="display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease;">
                                <input type="checkbox" id="perm-can-inspect-sheets" style="margin-top: 2px; cursor: pointer; transform: scale(1.1);">
                                <div>
                                    <strong style="font-size: 12.5px; color: var(--text-main); display: block;">📝 Inspect Other Sheets</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can use Employee Inspector to view, add rows, and edit worksheets.</span>
                                </div>
                            </label>

                            <!-- Perm 5: View Matrix Reports -->
                            <label class="perm-checkbox-card" style="display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease;">
                                <input type="checkbox" id="perm-can-view-reports" style="margin-top: 2px; cursor: pointer; transform: scale(1.1);">
                                <div>
                                    <strong style="font-size: 12.5px; color: var(--text-main); display: block;">📊 Matrix Analytics & Reports</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can access Matrix Reports to monitor outputs and statistics.</span>
                                </div>
                            </label>

                            <!-- Perm 6: View Live Attendance -->
                            <label class="perm-checkbox-card" style="display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease;">
                                <input type="checkbox" id="perm-can-view-attendance" style="margin-top: 2px; cursor: pointer; transform: scale(1.1);">
                                <div>
                                    <strong style="font-size: 12.5px; color: var(--text-main); display: block;">🟢 Live Attendance Board</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can access live shift roster to monitor active duty timers.</span>
                                </div>
                            </label>

                            <!-- Perm 7: Manage Employees -->
                            <label class="perm-checkbox-card" style="display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease; grid-column: 1 / -1;">
                                <input type="checkbox" id="perm-can-manage-employees" style="margin-top: 2px; cursor: pointer; transform: scale(1.1);">
                                <div>
                                    <strong style="font-size: 12.5px; color: var(--text-main); display: block;">⚙️ Manage Employees & Directory</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can access Manage Employees tab, add new staff, and configure team members.</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 12px 20px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('permissions-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 18px; font-weight: 700;">💾 Save Role & Permissions</button>
                </div>
            </form>
        </div>
    </div>


    <!-- MODAL: ADMIN ADJUST SHIFT & DUTY HOURS -->
    <div id="admin-shift-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 500px;">
            <div class="modal-header">
                <h3>⏱️ Manage Employee Shift Attendance</h3>
                <button type="button" class="btn-modal-close" onclick="closeModal('admin-shift-modal')">✕</button>
            </div>
            <form id="admin-shift-form" onsubmit="handleAdminSaveShiftSubmit(event)">
                <input type="hidden" id="admin-shift-emp-id">
                <input type="hidden" id="admin-shift-date">
                <div class="modal-body">
                    <div style="background: var(--bg-card-elevated); padding: 12px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div id="admin-shift-modal-avatar" style="width: 38px; height: 38px; border-radius: 50%; background: var(--primary); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; overflow: hidden;">👤</div>
                            <div>
                                <h4 id="admin-shift-modal-emp-name" style="margin: 0; font-size: 14px; color: var(--text-main);">Employee Name</h4>
                                <p style="margin: 2px 0 0; font-size: 12px; color: var(--text-muted);"><span id="admin-shift-modal-meta">Designation</span> • Date: <strong id="admin-shift-modal-date-label" style="color: var(--primary);">YYYY-MM-DD</strong></p>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                            <label class="form-label" style="margin: 0;">Check-In Time</label>
                            <div style="display: flex; gap: 4px;">
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="setShiftTimePreset('in', 'now')">Now</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="setShiftTimePreset('in', '09:00 AM')">09:00 AM</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="setShiftTimePreset('in', '10:00 AM')">10:00 AM</button>
                            </div>
                        </div>
                        <input type="text" id="admin-shift-in-time" class="input-control" placeholder="e.g. 09:00 AM" autocomplete="off">
                        <small style="font-size: 11px; color: var(--text-dim);">Enter formatted time (e.g. 09:30 AM or 1:45 PM) or click quick presets above.</small>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                            <label class="form-label" style="margin: 0;">Check-Out Time</label>
                            <div style="display: flex; gap: 4px;">
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="setShiftTimePreset('out', 'now')">Now</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="setShiftTimePreset('out', '06:00 PM')">06:00 PM</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px;" onclick="setShiftTimePreset('out', '07:00 PM')">07:00 PM</button>
                                <button type="button" class="btn btn-outline" style="padding: 2px 7px; font-size: 10.5px; color: var(--text-dim);" onclick="setShiftTimePreset('out', '')">Clear</button>
                            </div>
                        </div>
                        <input type="text" id="admin-shift-out-time" class="input-control" placeholder="e.g. 06:00 PM (Leave empty if currently on duty)" autocomplete="off">
                        <small style="font-size: 11px; color: var(--text-dim);">Leave empty if employee is still currently on duty.</small>
                    </div>

                    <div class="form-group" style="margin-top: 14px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 12.5px; color: var(--text-main);">
                            <input type="checkbox" id="admin-shift-lock-checkbox" style="cursor: pointer; transform: scale(1.15);">
                            <span><strong>Lock Sheet</strong> (Prevents further edits by employee after check-out)</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <button type="button" class="btn btn-outline" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.4);" onclick="handleAdminClearShift()" title="Completely remove check-in and check-out records for this date">
                        🗑️ Reset Shift
                    </button>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('admin-shift-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="padding: 8px 18px; font-weight: 700;">💾 Save Shift Times</button>
                    </div>
                </div>
            </form>
        </div>
    </div>



