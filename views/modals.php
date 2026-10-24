<?php
$userRoleModal = strtolower($authUser['role'] ?? '');
$userDeptModal = strtolower($authUser['department_name'] ?? '');
$canViewSalaryModal = in_array($userRoleModal, ['super_admin', 'admin', 'hr']) || (stripos($userDeptModal, 'hr') !== false) || (!empty($authUser['can_manage_hr']) && $userRoleModal !== 'hod');
?>
    <!-- MODAL: ASSIGN NEW TASK -->
    <div id="assign-task-modal" class="modal-overlay">
        <div class="modal-card modal-card-lg" style="max-width: 650px;">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px;">🎯 Assign Task to Employee</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Assign tasks directly to departmental staff with instant WhatsApp notification.</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('assign-task-modal')">✕</button>
            </div>
            <form id="assign-task-form" onsubmit="handleCreateTaskSubmit(event)">
                <div class="modal-body" style="gap: 14px;">

                    <!-- Assign to Employee (Searchable with Avatar like Dashboard) -->
                    <div class="form-group">
                        <label class="form-label">Assign To Employee (Search Name or Code e.g. DP1114) *</label>
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
                                        <input type="text" id="assign-task-emp-search-input" class="input-control" placeholder="🔍 Search employee by Code (DP1114), name, team..." style="width: 100%; padding: 6px 10px; font-size: 12.5px;" oninput="filterAssignTaskEmpDropdown(this.value)" autocomplete="off">
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
                        <label class="form-label">Path / Link (Local Folder or Web URL)</label>
                        <input type="text" id="assign-task-link" class="input-control" placeholder="e.g. C:\xampp\htdocs\Worksheet or D:\Footage\Episode 10" oninput="this.value = this.value.replace(/^[&quot;']+|[&quot;']+$/g, '').trim()" style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Instructions / Description</label>
                        <textarea id="assign-task-desc" class="form-textarea" placeholder="Specific guidelines, format, or tags..." rows="3"></textarea>
                    </div>

                    <!-- WhatsApp Notification Toggle -->
                    <div style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-card-elevated); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <label for="assign-task-send-wa" style="font-size: 12.5px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                            <span>💬</span> <span>Notify Employee via WhatsApp (OrbitSend)</span>
                        </label>
                        <input type="checkbox" id="assign-task-send-wa" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary);">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('assign-task-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 700; padding: 9px 20px;">🚀 Assign Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: HOD WHATSAPP & AI SETTINGS -->
    <div id="hod-whatsapp-modal" class="modal-overlay">
        <div class="modal-card modal-card-lg" style="max-width: 660px;">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px;">💬 HOD WhatsApp & AI Gateway</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Connect your personal OrbitSend WhatsApp account & AI Vision to automate employee task dispatch.</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('hod-whatsapp-modal')">✕</button>
            </div>
            <form id="hod-whatsapp-form" autocomplete="off" onsubmit="handleHodWhatsAppSettingsSubmit(event)">
                <div class="modal-body" style="gap: 16px;">
                    <!-- Section 1: OrbitSend WhatsApp Credentials -->
                    <div class="modal-section">
                        <div class="modal-section-title">📡 OrbitSend WhatsApp Gateway</div>
                        <p style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 10px;">
                            Each HOD can attach their own OrbitSend credentials so tasks are dispatched directly from your own WhatsApp.
                        </p>
                        <div class="form-group">
                            <label class="form-label">OrbitSend API URL</label>
                            <input type="text" id="hod-wa-api-url" class="input-control" value="https://app.orbitsend.com/api/send/whatsapp" style="font-family: monospace; font-size: 12px;" autocomplete="off">
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">OrbitSend Secret Key *</label>
                                <input type="password" id="hod-wa-api-secret" class="input-control" placeholder="Enter OrbitSend Secret Key" style="font-family: monospace;" autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Account / Unique ID *</label>
                                <input type="text" id="hod-wa-unique-id" class="input-control" placeholder="e.g. 1784542798c74d..." style="font-family: monospace;">
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px; background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                            <label for="hod-wa-auto-send" style="font-size: 12px; font-weight: 600; cursor: pointer; margin: 0; display: flex; align-items: center; gap: 6px;">
                                ⚡ Auto-send WhatsApp message whenever a new task is assigned
                            </label>
                            <input type="checkbox" id="hod-wa-auto-send" checked style="width: 17px; height: 17px; cursor: pointer; accent-color: var(--primary);">
                        </div>
                    </div>

                    <!-- Section 2: AI Vision & Text Parsing (Gemini / OpenAI) -->
                    <div class="modal-section">
                        <div class="modal-section-title">✨ AI Multimodal Vision (Image & Path Reader)</div>
                        <p style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 10px;">
                            Used by the Task Scanner to automatically read file paths (e.g. C:\..., D:\Footage), employee codes, and instructions from screenshots or photos.
                        </p>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">AI Engine Provider</label>
                                <select id="hod-ai-provider" class="input-control">
                                    <option value="auto">Auto Detect (Gemini / OpenAI)</option>
                                    <option value="gemini">Google Gemini Flash (Recommended)</option>
                                    <option value="openai">OpenAI GPT-4o-mini</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">AI API Key</label>
                                <input type="password" id="hod-ai-api-key" class="input-control" placeholder="AI API Key (or leave empty for company key)" style="font-family: monospace;" autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Connection Test & Webhook URL -->
                    <div class="modal-section">
                        <div class="modal-section-title">🧪 Connection Test & Incoming Webhook</div>
                        <div class="form-group">
                            <label class="form-label">OrbitSend Webhook URL (Optional: Paste into OrbitSend Webhooks)</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="hod-wa-webhook-url" class="input-control" readonly style="font-family: monospace; font-size: 11.5px; background: var(--bg-card-elevated);">
                                <button type="button" class="btn btn-outline" onclick="copyHodWebhookUrl()" style="white-space: nowrap; font-size: 11.5px;">📋 Copy</button>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: flex-end; margin-top: 10px;">
                            <div class="form-group" style="flex: 1; margin: 0;">
                                <label class="form-label" style="font-size: 11px;">Test Phone Number</label>
                                <input type="text" id="hod-wa-test-phone" class="input-control" placeholder="e.g. 03001234567">
                            </div>
                            <button type="button" class="btn btn-outline" id="hod-wa-test-btn" onclick="testHodWhatsAppConnection()" style="height: 38px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                                📱 Send Test WhatsApp
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('hod-whatsapp-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 700; padding: 8px 20px;">💾 Save WhatsApp & AI Settings</button>
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
        <div class="modal-card modal-card-lg" style="max-width: 740px;">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px;">➕ Add New Employee</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Create employee credentials, role assignments, shift hours, and basic compensation.</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('add-employee-modal')">✕</button>
            </div>
            <form id="add-employee-form" onsubmit="handleAddEmployeeSubmit(event)">
                <div class="modal-body" style="gap: 16px;">
                    <!-- Section 1: Identity & Legal Master Data -->
                    <div class="modal-section">
                        <div class="modal-section-title">📋 Identity & Legal Information</div>
                        
                        <div style="display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap;">
                            <!-- Passport-Size Photo Box -->
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 6px; flex-shrink: 0;">
                                <div style="position: relative;">
                                    <div id="add-emp-avatar-preview" onclick="document.getElementById('add-emp-avatar-file').click()" style="width: 112px; height: 140px; border-radius: 8px; border: 2px dashed #94a3b8; background: var(--bg-card-elevated); display: flex; flex-direction: column; align-items: center; justify-content: center; background-size: cover; background-position: center center; cursor: pointer; transition: all 0.2s ease; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06);" title="Click to upload passport size photo">
                                        <div style="font-size: 32px; color: var(--text-muted); line-height: 1;">📷</div>
                                        <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-top: 6px; text-align: center; line-height: 1.2;">Passport Size<br>Photo</div>
                                        <div style="font-size: 10px; color: var(--primary); margin-top: 4px; font-weight: 700;">Click to upload</div>
                                    </div>
                                    <button type="button" onclick="clearAvatarPreview('add')" id="add-emp-avatar-clear-btn" style="display: none; position: absolute; top: -6px; right: -6px; width: 22px; height: 22px; border-radius: 50%; background: #ef4444; color: #fff; border: 2px solid #fff; font-size: 11px; cursor: pointer; align-items: center; justify-content: center; line-height: 1; box-shadow: 0 2px 4px rgba(0,0,0,0.25);" title="Remove photo">✕</button>
                                </div>
                                <input type="file" id="add-emp-avatar-file" accept="image/*" style="display: none;" onchange="handleAvatarFileSelect(this, 'add-emp-avatar-preview', 'add-emp-avatar-base64', 'add')">
                                <input type="hidden" id="add-emp-avatar-base64">
                                <button type="button" class="btn btn-outline" style="font-size: 11px; padding: 4px 10px; border-radius: var(--radius-sm); font-weight: 600;" onclick="document.getElementById('add-emp-avatar-file').click()">
                                    📁 Choose Photo
                                </button>
                            </div>

                            <!-- Identity Form Fields -->
                            <div style="flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 10px;">
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" id="add-emp-name" class="input-control" placeholder="e.g. Ali Raza" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Father / Husband Name</label>
                                        <input type="text" id="add-emp-father-name" class="input-control" placeholder="e.g. Muhammad Raza">
                                    </div>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Employee Code</label>
                                        <input type="text" id="add-emp-code" class="input-control" placeholder="e.g. DP-104 (Auto)" style="font-weight: 700; color: var(--primary);">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">📱 Phone / WhatsApp</label>
                                        <input type="text" id="add-emp-phone" class="input-control" placeholder="e.g. 03001234567" style="font-weight: 600;">
                                        <small style="font-size: 10px; color: var(--text-muted); display: block; margin-top: 2px;">For WhatsApp Attendance</small>
                                    </div>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">CNIC No.</label>
                                        <input type="text" id="add-emp-cnic" class="input-control" placeholder="e.g. 35202-1234567-1">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Account Status</label>
                                        <select id="add-emp-status" class="input-control" style="font-weight: 600;">
                                            <option value="1" selected>🟢 Active (Normal Working Staff)</option>
                                            <option value="0">⛔ Deactivated</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Portal Login Access (Optional) -->
                    <div class="modal-section">
                        <div class="modal-section-title">👤 System Login Access</div>
                        
                        <!-- Row 1: Enable System Login Access & System Role & Access -->
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Portal Authentication</label>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card-elevated); padding: 0 14px; height: 38px; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-sizing: border-box;">
                                    <label for="add-emp-can-login" style="font-size: 13px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; user-select: none;">
                                        🔑 Enable System Login Access
                                    </label>
                                    <input type="checkbox" id="add-emp-can-login" style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary);" onchange="toggleLoginCredentialsSection('add', this.checked)">
                                </div>
                            </div>
                            <div class="form-group" id="add-emp-role-group">
                                <label class="form-label">System Role & Access</label>
                                <select id="add-emp-role" class="input-control" style="font-weight: 600;">
                                    <option value="employee" selected>👤 Staff Member</option>
                                    <option value="super_admin">👑 Super Admin</option>
                                    <option value="hr">👥 HR Manager</option>
                                    <option value="hod">🏢 Head of Department (HOD)</option>
                                    <option value="deputy_hod">📋 Deputy HOD / Asst. Manager</option>
                                    <option value="shift_supervisor">🛡️ Shift / Floor Supervisor</option>
                                    <option value="team_lead">⭐ Team Lead</option>
                                    <option value="coordinator">🎯 Task Coordinator</option>
                                    <option value="quality_auditor">🔍 Quality Auditor</option>
                                </select>
                                <div id="add-emp-role-badge" style="display: none; height: 38px; align-items: center; padding: 0 12px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-weight: 600; font-size: 13px; color: var(--text-main); user-select: none;">
                                    👤 Staff Member
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Official Email Address * & Initial Password * -->
                        <div id="add-emp-login-fields" style="display: none; margin-top: 10px;">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">Official Email Address <span id="add-emp-email-required-mark" style="color: #ef4444;">*</span></label>
                                    <input type="email" id="add-emp-email" class="input-control" placeholder="e.g. employee@discoverpakistan.tv">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="add-emp-password">🔑 Initial Password <span style="color: #ef4444;">*</span></label>
                                    <div class="password-input-wrapper" style="position: relative; width: 100%; display: flex; align-items: center;">
                                        <input type="password" id="add-emp-password" class="input-control" value="DiscoverPakistan123" placeholder="Enter initial password" autocomplete="new-password" style="width: 100%; padding-right: 38px; box-sizing: border-box;">
                                        <button type="button" class="btn-eye-toggle" onclick="togglePasswordInputVisibility('add-emp-password', this)" title="Show/Hide Password">
                                            👁️
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Department, Team & Shift -->
                    <div class="modal-section">
                        <div class="modal-section-title">🏢 Department & Shift Schedule</div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Designation / Title</label>
                                <input type="text" id="add-emp-designation" class="input-control" placeholder="e.g. Content Creator, Video Editor">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <select id="add-emp-dept" class="input-control">
                                    <option value="">-- Select Department --</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Assigned Team</label>
                                <select id="add-emp-team" class="input-control">
                                    <option value="">-- Unassigned (To be assigned by HOD) --</option>
                                    <!-- Populated dynamically based on selected department -->
                                </select>
                                <small style="font-size: 10.5px; color: var(--text-muted); margin-top: 3px; display: block;">Optional: HOD will assign team.</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Expected Shift / Duty Hours</label>
                                <select id="add-emp-shift-hours" class="input-control" style="font-weight: 700; color: var(--primary);">
                                    <option value="8.0" selected>⏱️ 8.0 Hours / Day (Standard Shift)</option>
                                    <option value="9.0">⏱️ 9.0 Hours / Day</option>
                                    <option value="7.0">⏱️ 7.0 Hours / Day</option>
                                    <option value="6.0">⏱️ 6.0 Hours / Day</option>
                                    <option value="10.0">⏱️ 10.0 Hours / Day</option>
                                    <option value="12.0">⏱️ 12.0 Hours / Day</option>
                                    <option value="0.0">🌐 Flexible / Open Shift (No fixed hours)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2" style="margin-top: 10px;">
                            <div class="form-group">
                                <label class="form-label">⏱️ Shift Timing (Roster Hours)</label>
                                <select id="add-emp-shift-timing-select" class="input-control" style="font-weight: 600;" onchange="handleShiftTimingPresetChange('add')">
                                    <option value="10:00 AM TO 6:00 PM" selected>10:00 AM TO 6:00 PM (Regular Day • 8h)</option>
                                    <option value="9:00 AM TO 5:00 PM">9:00 AM TO 5:00 PM (General Office • 8h)</option>
                                    <option value="8:00 AM TO 4:00 PM">8:00 AM TO 4:00 PM (Early Morning • 8h)</option>
                                    <option value="12:00 PM TO 8:00 PM">12:00 PM TO 8:00 PM (Mid Shift • 8h)</option>
                                    <option value="1:00 PM TO 9:00 PM">1:00 PM TO 9:00 PM (Afternoon Shift • 8h)</option>
                                    <option value="2:00 PM TO 10:00 PM">2:00 PM TO 10:00 PM (Evening Shift • 8h)</option>
                                    <option value="4:00 PM TO 12:00 AM">4:00 PM TO 12:00 AM (Evening / Broadcast • 8h)</option>
                                    <option value="6:00 PM TO 2:00 AM">6:00 PM TO 2:00 AM (Night Shift • 8h)</option>
                                    <option value="11:00 PM TO 7:00 AM">11:00 PM TO 7:00 AM (Overnight • 8h)</option>
                                    <option value="12:00 AM TO 8:00 AM">12:00 AM TO 8:00 AM (Graveyard Shift • 8h)</option>
                                    <option value="Flexible Hours">🌐 Flexible / Open Timing (No fixed schedule)</option>
                                    <option value="custom">✏️ Custom Timing...</option>
                                </select>
                                <div id="add-emp-custom-timing-box" style="display: none; margin-top: 6px; padding: 6px 10px; background: rgba(59, 130, 246, 0.05); border: 1px dashed var(--primary); border-radius: var(--radius-sm); gap: 8px; align-items: center;">
                                    <div style="flex: 1;">
                                        <label style="font-size: 10px; color: var(--text-muted); display: block; margin-bottom: 2px;">Start Time</label>
                                        <input type="time" id="add-emp-custom-start" class="input-control" style="font-size: 12px; padding: 4px 6px;" value="10:00" onchange="syncCustomShiftTiming('add')">
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-muted); margin-top: 14px; font-weight: 700;">TO</span>
                                    <div style="flex: 1;">
                                        <label style="font-size: 10px; color: var(--text-muted); display: block; margin-bottom: 2px;">End Time</label>
                                        <input type="time" id="add-emp-custom-end" class="input-control" style="font-size: 12px; padding: 4px 6px;" value="18:00" onchange="syncCustomShiftTiming('add')">
                                    </div>
                                </div>
                                <input type="hidden" id="add-emp-shift-timing" value="10:00 AM TO 6:00 PM">
                                <small style="font-size: 10px; color: var(--text-muted);">Standardized shift timing for attendance & roster sync</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">🏖️ Weekly Off Day</label>
                                <select id="add-emp-weekly-off" class="input-control" style="font-weight: 600;">
                                    <option value="Sunday" selected>Sunday</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Once in Month">Once in Month (Rotational)</option>
                                    <option value="Friday & Saturday">Friday & Saturday</option>
                                    <option value="Saturday & Sunday">Saturday & Sunday</option>
                                </select>
                                <small style="font-size: 10px; color: var(--text-muted);">Syncs with Monthly Duty Roster</small>
                            </div>
                        </div>

                        <div class="form-grid-2" style="margin-top: 10px;">
                            <div class="form-group">
                                <label class="form-label">🕒 Attendance & Late Policy</label>
                                <select id="add-emp-shift-policy" class="input-control" style="font-weight: 600;" onchange="handleShiftPolicyChange('add', this.value)">
                                    <option value="fixed" selected>⏱️ Fixed / Roster Shift (Count Lates from Shift Time)</option>
                                    <option value="rotational">🔄 Rotational Shift (Auto-Match Nearest Shift)</option>
                                    <option value="flexible">🟢 Flexible Hours (No Lateness Tracked • Exempt)</option>
                                </select>
                                <small style="font-size: 10px; color: var(--text-muted);">Flexible staff are exempt from late arrival tracking</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">⏱️ Late Grace Period (Minutes)</label>
                                <input type="number" id="add-emp-late-grace" class="input-control" value="15" min="0" max="120" style="font-weight: 600;">
                                <small style="font-size: 10px; color: var(--text-muted);">Grace buffer before late arrival is counted (e.g. 15 mins)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Salary, Bank & Entitlements (Confidential - HR & Super Admin Only) -->
                    <div class="modal-section" id="add-emp-salary-section" style="<?= $canViewSalaryModal ? '' : 'display: none !important;' ?>">
                        <div class="modal-section-title">💵 Salary, Allowances & Bank Disbursal <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Confidential • HR / Admin Only)</span></div>
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label">Gross / Base Salary (PKR)</label>
                                <input type="number" id="add-emp-salary" class="input-control" placeholder="e.g. 75000" min="0" step="any" style="font-weight: 700;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fixed Fuel / Mobile Allow.</label>
                                <input type="number" id="add-emp-fixed-allowance" class="input-control" placeholder="e.g. 5000" min="0" step="any">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Joining Date</label>
                                <input type="date" id="add-emp-joining-date" class="input-control">
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Bank Name</label>
                                <select id="add-emp-bank-name" class="input-control">
                                    <option value="UBL" selected>🏦 UBL (United Bank Limited)</option>
                                    <option value="Meezan Bank">🏦 Meezan Bank</option>
                                    <option value="HBL">🏦 HBL (Habib Bank Limited)</option>
                                    <option value="MCB">🏦 MCB Bank</option>
                                    <option value="Allied Bank">🏦 Allied Bank</option>
                                    <option value="Bank Alfalah">🏦 Bank Alfalah</option>
                                    <option value="Faysal Bank">🏦 Faysal Bank</option>
                                    <option value="Other Bank">🏛️ Other Commercial Bank</option>
                                    <option value="Cash">💵 Cash / Direct Counter</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Bank Account / IBAN No.</label>
                                <input type="text" id="add-emp-bank-account" class="input-control" placeholder="e.g. 0123456789012 or PK00UNIL...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('add-employee-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="padding: 9px 20px; font-weight: 700;">✨ Create Employee Profile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT EMPLOYEE -->
    <div id="edit-employee-modal" class="modal-overlay">
        <div class="modal-card modal-card-lg" style="max-width: 740px;">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px;">✏️ Edit Employee Details</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Update staff master profile, bank info, role, department, salary, and leave quotas.</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('edit-employee-modal')">✕</button>
            </div>
            <form id="edit-employee-form" onsubmit="handleEditEmployeeSubmit(event)">
                <input type="hidden" id="edit-emp-id">
                <div class="modal-body" style="gap: 16px;">
                    <!-- Section 1: Identity & Legal Master Data -->
                    <div class="modal-section">
                        <div class="modal-section-title">📋 Identity & Legal Information</div>
                        
                        <div style="display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap;">
                            <!-- Passport-Size Photo Box -->
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 6px; flex-shrink: 0;">
                                <div style="position: relative;">
                                    <div id="edit-emp-avatar-preview" onclick="document.getElementById('edit-emp-avatar-file').click()" style="width: 112px; height: 140px; border-radius: 8px; border: 2px dashed #94a3b8; background: var(--bg-card-elevated); display: flex; flex-direction: column; align-items: center; justify-content: center; background-size: cover; background-position: center center; cursor: pointer; transition: all 0.2s ease; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06);" title="Click to upload passport size photo">
                                        <div style="font-size: 32px; color: var(--text-muted); line-height: 1;">📷</div>
                                        <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-top: 6px; text-align: center; line-height: 1.2;">Passport Size<br>Photo</div>
                                        <div style="font-size: 10px; color: var(--primary); margin-top: 4px; font-weight: 700;">Click to change</div>
                                    </div>
                                    <button type="button" onclick="clearAvatarPreview('edit')" id="edit-emp-avatar-clear-btn" style="display: none; position: absolute; top: -6px; right: -6px; width: 22px; height: 22px; border-radius: 50%; background: #ef4444; color: #fff; border: 2px solid #fff; font-size: 11px; cursor: pointer; align-items: center; justify-content: center; line-height: 1; box-shadow: 0 2px 4px rgba(0,0,0,0.25);" title="Remove photo">✕</button>
                                </div>
                                <input type="file" id="edit-emp-avatar-file" accept="image/*" style="display: none;" onchange="handleAvatarFileSelect(this, 'edit-emp-avatar-preview', 'edit-emp-avatar-base64', 'edit')">
                                <input type="hidden" id="edit-emp-avatar-base64">
                                <button type="button" class="btn btn-outline" style="font-size: 11px; padding: 4px 10px; border-radius: var(--radius-sm); font-weight: 600;" onclick="document.getElementById('edit-emp-avatar-file').click()">
                                    📷 Change Photo
                                </button>
                            </div>

                            <!-- Identity Form Fields -->
                            <div style="flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 10px;">
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" id="edit-emp-name" class="input-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Father / Husband Name</label>
                                        <input type="text" id="edit-emp-father-name" class="input-control" placeholder="e.g. Muhammad Raza">
                                    </div>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">Employee Code</label>
                                        <input type="text" id="edit-emp-code" class="input-control" placeholder="e.g. DP-104" style="font-weight: 700; color: var(--primary);">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">📱 Phone / WhatsApp</label>
                                        <input type="text" id="edit-emp-phone" class="input-control" placeholder="e.g. 03001234567" style="font-weight: 600;">
                                        <small style="font-size: 10px; color: var(--text-muted); display: block; margin-top: 2px;">For WhatsApp Attendance</small>
                                    </div>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label class="form-label">CNIC No.</label>
                                        <input type="text" id="edit-emp-cnic" class="input-control" placeholder="e.g. 35202-1234567-1">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Account Status</label>
                                        <select id="edit-emp-status" class="input-control" style="font-weight: 600;">
                                            <option value="1">🟢 Active (Normal Working Staff)</option>
                                            <option value="0">⛔ Deactivated</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Portal Login Access (Optional) -->
                    <div class="modal-section">
                        <div class="modal-section-title">👤 System Login Access</div>
                        
                        <!-- Row 1: Enable System Login Access & System Role & Access -->
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Portal Authentication</label>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card-elevated); padding: 0 14px; height: 38px; border-radius: var(--radius-md); border: 1px solid var(--border-color); box-sizing: border-box;">
                                    <label for="edit-emp-can-login" style="font-size: 13px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; user-select: none;">
                                        🔑 Enable System Login Access
                                    </label>
                                    <input type="checkbox" id="edit-emp-can-login" checked style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary);" onchange="toggleLoginCredentialsSection('edit', this.checked)">
                                </div>
                            </div>
                            <div class="form-group" id="edit-emp-role-group">
                                <label class="form-label">System Role & Access</label>
                                <select id="edit-emp-role" class="input-control" style="font-weight: 600;">
                                    <option value="employee">👤 Staff Member</option>
                                    <option value="super_admin">👑 Super Admin</option>
                                    <option value="hr">👥 HR Manager</option>
                                    <option value="hod">🏢 Head of Department (HOD)</option>
                                    <option value="deputy_hod">📋 Deputy HOD / Asst. Manager</option>
                                    <option value="shift_supervisor">🛡️ Shift / Floor Supervisor</option>
                                    <option value="team_lead">⭐ Team Lead</option>
                                    <option value="coordinator">🎯 Task Coordinator</option>
                                    <option value="quality_auditor">🔍 Quality Auditor</option>
                                </select>
                                <div id="edit-emp-role-badge" style="display: none; height: 38px; align-items: center; padding: 0 12px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-weight: 600; font-size: 13px; color: var(--text-main); user-select: none;">
                                    👤 Staff Member
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Official Email Address * & Set / Reset Password -->
                        <div id="edit-emp-login-fields" style="display: block; margin-top: 10px;">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">Official Email Address <span id="edit-emp-email-required-mark" style="color: #ef4444;">*</span></label>
                                    <input type="email" id="edit-emp-email" class="input-control" placeholder="e.g. employee@discoverpakistan.tv">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="edit-emp-password">🔑 Set / Reset Password <small style="color: var(--text-muted); font-weight: 400;">(Leave blank to keep)</small></label>
                                    <div class="password-input-wrapper" style="position: relative; width: 100%; display: flex; align-items: center;">
                                        <input type="password" id="edit-emp-password" class="input-control" placeholder="Enter new password (optional)" autocomplete="new-password" style="width: 100%; padding-right: 38px; box-sizing: border-box;">
                                        <button type="button" class="btn-eye-toggle" onclick="togglePasswordInputVisibility('edit-emp-password', this)" title="Show/Hide Password">
                                            👁️
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Department, Team & Shift -->
                    <div class="modal-section">
                        <div class="modal-section-title">🏢 Department & Shift Schedule</div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Designation / Title</label>
                                <input type="text" id="edit-emp-designation" class="input-control" placeholder="e.g. Content Creator, Video Editor">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <select id="edit-emp-dept" class="input-control">
                                    <!-- Populated dynamically -->
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Assigned Team</label>
                                <select id="edit-emp-team" class="input-control">
                                    <option value="">-- Unassigned (To be assigned by HOD) --</option>
                                    <!-- Populated dynamically -->
                                </select>
                                <small style="font-size: 10.5px; color: var(--text-muted); margin-top: 3px; display: block;">Optional: HOD can assign staff into teams.</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Expected Shift / Duty Hours</label>
                                <select id="edit-emp-shift-hours" class="input-control" style="font-weight: 700; color: var(--primary);">
                                    <option value="8.0">⏱️ 8.0 Hours / Day (Standard Shift)</option>
                                    <option value="9.0">⏱️ 9.0 Hours / Day</option>
                                    <option value="7.0">⏱️ 7.0 Hours / Day</option>
                                    <option value="6.0">⏱️ 6.0 Hours / Day</option>
                                    <option value="10.0">⏱️ 10.0 Hours / Day</option>
                                    <option value="12.0">⏱️ 12.0 Hours / Day</option>
                                    <option value="0.0">🌐 Flexible / Open Shift (No fixed hours)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2" style="margin-top: 10px;">
                            <div class="form-group">
                                <label class="form-label">⏱️ Shift Timing (Roster Hours)</label>
                                <select id="edit-emp-shift-timing-select" class="input-control" style="font-weight: 600;" onchange="handleShiftTimingPresetChange('edit')">
                                    <option value="10:00 AM TO 6:00 PM">10:00 AM TO 6:00 PM (Regular Day • 8h)</option>
                                    <option value="9:00 AM TO 5:00 PM">9:00 AM TO 5:00 PM (General Office • 8h)</option>
                                    <option value="8:00 AM TO 4:00 PM">8:00 AM TO 4:00 PM (Early Morning • 8h)</option>
                                    <option value="12:00 PM TO 8:00 PM">12:00 PM TO 8:00 PM (Mid Shift • 8h)</option>
                                    <option value="1:00 PM TO 9:00 PM">1:00 PM TO 9:00 PM (Afternoon Shift • 8h)</option>
                                    <option value="2:00 PM TO 10:00 PM">2:00 PM TO 10:00 PM (Evening Shift • 8h)</option>
                                    <option value="4:00 PM TO 12:00 AM">4:00 PM TO 12:00 AM (Evening / Broadcast • 8h)</option>
                                    <option value="6:00 PM TO 2:00 AM">6:00 PM TO 2:00 AM (Night Shift • 8h)</option>
                                    <option value="11:00 PM TO 7:00 AM">11:00 PM TO 7:00 AM (Overnight • 8h)</option>
                                    <option value="12:00 AM TO 8:00 AM">12:00 AM TO 8:00 AM (Graveyard Shift • 8h)</option>
                                    <option value="Flexible Hours">🌐 Flexible / Open Timing (No fixed schedule)</option>
                                    <option value="custom">✏️ Custom Timing...</option>
                                </select>
                                <div id="edit-emp-custom-timing-box" style="display: none; margin-top: 6px; padding: 6px 10px; background: rgba(59, 130, 246, 0.05); border: 1px dashed var(--primary); border-radius: var(--radius-sm); gap: 8px; align-items: center;">
                                    <div style="flex: 1;">
                                        <label style="font-size: 10px; color: var(--text-muted); display: block; margin-bottom: 2px;">Start Time</label>
                                        <input type="time" id="edit-emp-custom-start" class="input-control" style="font-size: 12px; padding: 4px 6px;" value="10:00" onchange="syncCustomShiftTiming('edit')">
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-muted); margin-top: 14px; font-weight: 700;">TO</span>
                                    <div style="flex: 1;">
                                        <label style="font-size: 10px; color: var(--text-muted); display: block; margin-bottom: 2px;">End Time</label>
                                        <input type="time" id="edit-emp-custom-end" class="input-control" style="font-size: 12px; padding: 4px 6px;" value="18:00" onchange="syncCustomShiftTiming('edit')">
                                    </div>
                                </div>
                                <input type="hidden" id="edit-emp-shift-timing" value="10:00 AM TO 6:00 PM">
                                <small style="font-size: 10px; color: var(--text-muted);">Standardized shift timing for attendance & roster sync</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">🏖️ Weekly Off Day</label>
                                <select id="edit-emp-weekly-off" class="input-control" style="font-weight: 600;">
                                    <option value="Sunday">Sunday</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Once in Month">Once in Month (Rotational)</option>
                                    <option value="Friday & Saturday">Friday & Saturday</option>
                                    <option value="Saturday & Sunday">Saturday & Sunday</option>
                                </select>
                                <small style="font-size: 10px; color: var(--text-muted);">Syncs with Monthly Duty Roster</small>
                            </div>
                        </div>

                        <div class="form-grid-2" style="margin-top: 10px;">
                            <div class="form-group">
                                <label class="form-label">🕒 Attendance & Late Policy</label>
                                <select id="edit-emp-shift-policy" class="input-control" style="font-weight: 600;" onchange="handleShiftPolicyChange('edit', this.value)">
                                    <option value="fixed">⏱️ Fixed / Roster Shift (Count Lates from Shift Time)</option>
                                    <option value="rotational">🔄 Rotational Shift (Auto-Match Nearest Shift)</option>
                                    <option value="flexible">🟢 Flexible Hours (No Lateness Tracked • Exempt)</option>
                                </select>
                                <small style="font-size: 10px; color: var(--text-muted);">Flexible staff are exempt from late arrival tracking</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">⏱️ Late Grace Period (Minutes)</label>
                                <input type="number" id="edit-emp-late-grace" class="input-control" value="15" min="0" max="120" style="font-weight: 600;">
                                <small style="font-size: 10px; color: var(--text-muted);">Grace buffer before late arrival is counted (e.g. 15 mins)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Salary, Bank & Entitlements (Confidential - HR & Super Admin Only) -->
                    <div class="modal-section" id="edit-emp-salary-section" style="<?= $canViewSalaryModal ? '' : 'display: none !important;' ?>">
                        <div class="modal-section-title">💵 Salary, Allowances & Bank Disbursal <span style="font-size: 11px; font-weight: normal; color: var(--text-muted);">(Confidential • HR / Admin Only)</span></div>
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label">Gross / Base Salary (PKR)</label>
                                <input type="number" id="edit-emp-salary" class="input-control" placeholder="e.g. 75000" min="0" step="any" style="font-weight: 700;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fixed Fuel / Mobile Allow.</label>
                                <input type="number" id="edit-emp-fixed-allowance" class="input-control" placeholder="e.g. 5000" min="0" step="any">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Joining Date</label>
                                <input type="date" id="edit-emp-joining-date" class="input-control">
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Bank Name</label>
                                <select id="edit-emp-bank-name" class="input-control">
                                    <option value="UBL">🏦 UBL (United Bank Limited)</option>
                                    <option value="Meezan Bank">🏦 Meezan Bank</option>
                                    <option value="HBL">🏦 HBL (Habib Bank Limited)</option>
                                    <option value="MCB">🏦 MCB Bank</option>
                                    <option value="Allied Bank">🏦 Allied Bank</option>
                                    <option value="Bank Alfalah">🏦 Bank Alfalah</option>
                                    <option value="Faysal Bank">🏦 Faysal Bank</option>
                                    <option value="Other Bank">🏛️ Other Commercial Bank</option>
                                    <option value="Cash">💵 Cash / Direct Counter</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Bank Account / IBAN No.</label>
                                <input type="text" id="edit-emp-bank-account" class="input-control" placeholder="e.g. 0123456789012 or PK00UNIL...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="btn btn-outline" style="color: #dc2626; border-color: rgba(220, 38, 38, 0.4); font-weight: 700; padding: 8px 14px;" onclick="handleDeleteEmployeeFromModal()" title="Permanently delete duplicate or unwanted employee">
                        🗑️ Delete Employee
                    </button>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-outline" onclick="closeModal('edit-employee-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="padding: 9px 20px; font-weight: 700;">💾 Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT MONTHLY PAYROLL ITEM -->
    <div id="edit-payroll-modal" class="modal-overlay">
        <div class="modal-card modal-card-lg">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px;">💵 Adjust Monthly Payroll & Deductions</h3>
                    <p id="edit-payroll-subtitle" style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Update salary additions, advances, loan deductions, fines, taxes, and payment status.</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('edit-payroll-modal')">✕</button>
            </div>
            <form id="edit-payroll-form" onsubmit="handleSavePayrollItem(event)">
                <input type="hidden" id="edit-pr-employee-id">
                <input type="hidden" id="edit-pr-salary-month">
                <input type="hidden" id="edit-pr-working-days">
                <input type="hidden" id="edit-pr-present-days">
                <input type="hidden" id="edit-pr-approved-leaves">
                <input type="hidden" id="edit-pr-unpaid-leaves">
                <input type="hidden" id="edit-pr-duty-hours">

                <div class="modal-body" style="gap: 16px;">
                    <!-- Employee Summary Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; background: var(--bg-card-elevated); padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <div>
                            <div id="edit-pr-emp-display" style="font-size: 14px; font-weight: 700; color: var(--text-main);">Ali Raza (DP-101)</div>
                            <div id="edit-pr-meta-display" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">News Room | Bank: UBL (0123456789) | CNIC: 35202-1234567-1</div>
                        </div>
                        <div style="text-align: right;">
                            <div id="edit-pr-month-display" class="badge" style="background: var(--primary-light); color: var(--primary); font-size: 12px; font-weight: 700;">Month: 2026-10</div>
                            <div id="edit-pr-days-display" style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">30 Days (26 Present, 2 Leaves)</div>
                        </div>
                    </div>

                    <!-- Row 1: Base Salary & Form # -->
                    <div class="modal-section">
                        <div class="modal-section-title">💼 Earnings & Additions</div>
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label">Gross / Base Salary (PKR) *</label>
                                <input type="number" id="edit-pr-basic-salary" class="input-control" step="any" min="0" required oninput="calculatePayrollModalTotals()" style="font-weight: 700;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fuel / Travel / Mobile (PKR)</label>
                                <input type="number" id="edit-pr-fuel" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Incentive / Performance (PKR)</label>
                                <input type="number" id="edit-pr-incentive" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()">
                            </div>
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Other Bonus (PKR)</label>
                                <input type="number" id="edit-pr-bonus" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Bonus / Incentive Remarks</label>
                                <input type="text" id="edit-pr-bonus-reason" class="input-control" placeholder="e.g. Eid Bonus, Performance target achieved">
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Deductions -->
                    <div class="modal-section">
                        <div class="modal-section-title">📉 Deductions & Taxes</div>
                        
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label" id="edit-pr-unpaid-label">Unpaid Leaves (PKR)</label>
                                <input type="number" id="edit-pr-unpaid-deduction" class="input-control" step="any" min="0" oninput="handleUnpaidDeductionManualInput()" style="color: #dc2626; font-weight: 700;">
                                <div style="margin-top: 6px;">
                                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; font-weight: 600; color: #059669; user-select: none;">
                                        <input type="checkbox" id="edit-pr-waive-unpaid" onchange="toggleWaiveUnpaidDeduction()" style="width: 15px; height: 15px; accent-color: #10b981; cursor: pointer;">
                                        <span>Waive off deduction</span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Advance Salary (PKR)</label>
                                <input type="number" id="edit-pr-advance" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Loan Deduction (PKR)</label>
                                <input type="number" id="edit-pr-loan" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()">
                            </div>
                        </div>

                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label">🍲 Canteen Food Bills (PKR)</label>
                                <input type="number" id="edit-pr-food-bills" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()" style="color: #dc2626; font-weight: 700;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">WHT / Income Tax (PKR)</label>
                                <input type="number" id="edit-pr-wht" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fines / Penalties (PKR)</label>
                                <input type="number" id="edit-pr-fines" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()">
                            </div>
                        </div>

                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label">Other Deductions (PKR)</label>
                                <input type="number" id="edit-pr-deductions" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fine Reason</label>
                                <input type="text" id="edit-pr-fine-reason" class="input-control" placeholder="e.g. Late Arrival Penalty">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Deduction Reason</label>
                                <input type="text" id="edit-pr-deduction-reason" class="input-control" placeholder="e.g. Special adjustment">
                            </div>
                        </div>
                    </div>

                    <!-- Live Summary Calculation Banner -->
                    <div style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(16, 185, 129, 0.08) 100%); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 18px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; text-align: center;">
                            <div>
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">TOTAL ADDITIONS</div>
                                <div id="modal-calc-additions" style="font-size: 15px; font-weight: 700; color: #059669;">PKR 0</div>
                            </div>
                            <div>
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">TOTAL DEDUCTIONS</div>
                                <div id="modal-calc-deductions" style="font-size: 15px; font-weight: 700; color: #dc2626;">PKR 0</div>
                            </div>
                            <div>
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">NET SALARY</div>
                                <div id="modal-calc-net" style="font-size: 17px; font-weight: 800; color: var(--primary);">PKR 0</div>
                            </div>
                            <div>
                                <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">SALARY PAYABLE</div>
                                <div id="modal-calc-payable" style="font-size: 17px; font-weight: 800; color: #d97706;">PKR 0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Disbursal, Form # & Increment Remarks -->
                    <div class="modal-section">
                        <div class="modal-section-title">🏛️ Disbursal, Form # & Audit Remarks</div>
                        <div class="form-grid-3">
                            <div class="form-group">
                                <label class="form-label">Form # / Voucher Ref</label>
                                <input type="text" id="edit-pr-form-no" class="input-control" placeholder="e.g. VCH-2026-104">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Salary Paid Amount (PKR)</label>
                                <input type="number" id="edit-pr-paid-amount" class="input-control" step="any" min="0" oninput="calculatePayrollModalTotals()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Payment Method</label>
                                <select id="edit-pr-payment-method" class="input-control">
                                    <option value="Bank Transfer">🏦 Bank Transfer / UBL</option>
                                    <option value="Cheque">📜 Cheque</option>
                                    <option value="Cash">💵 Cash</option>
                                    <option value="Other">🏛️ Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Payment Status</label>
                                <select id="edit-pr-payment-status" class="input-control" style="font-weight: 700;">
                                    <option value="draft">🟡 Draft (Pending Approval)</option>
                                    <option value="approved">🔵 Approved</option>
                                    <option value="paid">🟢 Disbursed / Paid</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Payment Date</label>
                                <input type="date" id="edit-pr-payment-date" class="input-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Salary Increment Remarks / Audit Notes</label>
                            <textarea id="edit-pr-remarks" class="input-control" rows="2" placeholder="e.g. Annual increment of 10% effective this month..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('edit-payroll-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="padding: 9px 24px; font-weight: 700;">💾 Save Payroll Adjustment</button>
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
                        <input type="email" id="my-profile-email" class="input-control" required placeholder="your.email@discoverpakistan.tv" autocomplete="username">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">Designation / Title</label>
                            <input type="text" id="my-profile-designation" class="input-control" placeholder="e.g. Social Lead, Video Editor" autocomplete="organization-title">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department & Team</label>
                            <input type="text" id="my-profile-dept-team" class="input-control" readonly disabled style="background: var(--bg-hover); opacity: 0.85; font-size: 12px;" autocomplete="off">
                        </div>
                    </div>

                    <!-- Optional Password Update -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 14px; margin-top: 4px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <label class="form-label" style="font-weight: 700; margin: 0;">Change Login Password <span style="font-weight: normal; color: var(--text-dim); font-size: 11.5px;">(Optional)</span></label>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="password" id="my-profile-new-password" class="input-control" placeholder="New Password" autocomplete="new-password">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <input type="password" id="my-profile-confirm-password" class="input-control" placeholder="Confirm Password" autocomplete="new-password">
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
                            <option value="super_admin">👑 Super Admin (Full Company-Wide Control)</option>
                            <option value="hr">👥 HR Manager (Manage HR, Staff Directory & Attendance)</option>
                            <option value="hod">🏢 Head of Department / HOD (Manage Tasks, Sheets & Reports)</option>
                            <option value="deputy_hod">📋 Deputy HOD / Asst. Manager (Assign Tasks, Unlock Sheets & Reports)</option>
                            <option value="shift_supervisor">🛡️ Shift Supervisor (Live Roster Ops, Assign Tasks & Unlock Sheets)</option>
                            <option value="team_lead">⭐ Team Lead / Supervisor (Assign Tasks & Unlock Team Sheets)</option>
                            <option value="coordinator">🎯 Task Coordinator (Create & Manage Tasks Across Teams)</option>
                            <option value="quality_auditor">🔍 Quality Auditor (Inspect Sheets [View & Print] & View Matrix Reports)</option>
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
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can lock or reopen locked worksheets and edit team entries (cannot mark HOD check).</span>
                                </div>
                            </label>

                            <!-- Perm 4: Inspect Other Sheets -->
                            <label class="perm-checkbox-card" style="display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease;">
                                <input type="checkbox" id="perm-can-inspect-sheets" style="margin-top: 2px; cursor: pointer; transform: scale(1.1);">
                                <div>
                                    <strong style="font-size: 12.5px; color: var(--text-main); display: block;">📝 Inspect Other Sheets</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can use Employee Inspector to view worksheets (read-only view; printing requires HOD verification).</span>
                                </div>
                            </label>

                            <!-- Perm 5: Print Unverified Sheets -->
                            <label class="perm-checkbox-card" style="display: flex; align-items: flex-start; gap: 9px; padding: 9px 12px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease;">
                                <input type="checkbox" id="perm-can-print-unverified-sheets" style="margin-top: 2px; cursor: pointer; transform: scale(1.1);">
                                <div>
                                    <strong style="font-size: 12.5px; color: var(--text-main); display: block;">🖨️ Print Unverified Sheets</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); line-height: 1.3; display: block; margin-top: 2px;">Can bypass HOD verification check and print worksheets before HOD approval.</span>
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

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" style="font-weight: 700;">Day Schedule & Status</label>
                        <select id="admin-shift-day-type" class="input-control" onchange="handleAdminShiftDayTypeChange()" style="font-weight: 600;">
                            <option value="working">💼 Normal Working Shift</option>
                            <option value="absent">🔴 Mark Absent (Unexcused / Absent)</option>
                            <option value="swapped_off">🔄 Swapped Weekly Off (Rotated Day Off)</option>
                            <option value="alt_off">⭐ Alternate Day Off (Comp Off for Extra Day Worked)</option>
                            <option value="holiday">🏖️ Scheduled Day Off / Rest Day</option>
                        </select>
                    </div>

                    <div id="admin-shift-reason-group" class="form-group" style="display: none; margin-bottom: 14px;">
                        <label id="admin-shift-reason-label" class="form-label" style="font-weight: 700;">Swap / Day Off Notes & Reference</label>
                        <input type="text" id="admin-shift-holiday-reason" class="input-control" placeholder="e.g. Swapped with Saturday (HOD approved)">
                        <small id="admin-shift-reason-hint" style="font-size: 11px; color: var(--text-dim);">Reference date worked or HOD rotation approval reason.</small>
                    </div>

                    <div id="admin-shift-times-section">
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

                    <div id="admin-shift-late-section" style="margin-top: 14px; padding: 12px 14px; background: rgba(244, 63, 94, 0.05); border: 1px dashed rgba(244, 63, 94, 0.35); border-radius: var(--radius-md);">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="font-size: 13px; font-weight: 700; color: var(--text-main);">🛡️ Late Arrival & Excusal</span>
                                <span id="admin-shift-late-badge" class="badge" style="font-size: 11px; padding: 2px 7px; border-radius: 4px; display: none;"></span>
                            </div>
                            <label for="admin-shift-late-excuse-toggle" style="font-size: 12px; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; user-select: none;">
                                <input type="checkbox" id="admin-shift-late-excuse-toggle" style="width: 17px; height: 17px; accent-color: var(--primary); cursor: pointer;" onchange="toggleAdminShiftExcuseReason(this.checked)">
                                Excuse / Waive Late
                            </label>
                        </div>
                        <div id="admin-shift-late-reason-box" style="display: none; margin-top: 10px;">
                            <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">Excusal Note / Reason</label>
                            <input type="text" id="admin-shift-late-excuse-reason" class="input-control" placeholder="e.g. Traffic Jam, Official Meeting, Rain / Weather, Approved by HOD" style="font-size: 12px;">
                        </div>
                    </div>
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

    <!-- MODAL: ADD DEPARTMENT (HR & ADMIN) -->
    <div id="add-department-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px;">🏢 Add New Department</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Create an organizational department and assign a Head of Department (HOD).</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('add-department-modal')">✕</button>
            </div>
            <form id="add-department-form" onsubmit="handleSaveDepartmentSubmit(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Department Name *</label>
                        <input type="text" id="add-dept-name" class="input-control" placeholder="e.g. Creative Media, Digital, News Room" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700;">Parent Department (Optional - for Sub-Departments)</label>
                        <select id="add-dept-parent" class="input-control" style="font-weight: 600;">
                            <option value="">🏢 None (Top-Level Division / Main Department)</option>
                        </select>
                        <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                            If this is an operational sub-unit (e.g. <em>Archive</em> or <em>NLE</em> under <em>Programming</em>), select its parent division.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Head of Department (HOD)</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                            <div id="add-dept-hod-avatar" class="admin-inspector-avatar" style="width: 38px; height: 38px; font-size: 15px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 700;">👤</div>
                            <div class="searchable-dropdown-wrapper" id="add-dept-hod-search-wrapper" style="position: relative; flex: 1;">
                                <button type="button" id="add-dept-hod-dropdown-btn" class="input-control" style="font-weight: 600; font-size: 13px; padding: 8px 12px; width: 100%; border-color: var(--primary); background: var(--bg-card); cursor: pointer; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left;" onclick="toggleAddDeptHodDropdown()">
                                    <span id="add-dept-hod-selected-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-- No HOD Appointed Yet --</span>
                                    <span style="font-size: 10px; color: var(--text-muted);">▼</span>
                                </button>
                                <input type="hidden" id="add-dept-hod" value="">

                                <!-- Floating Search Dropdown Menu -->
                                <div id="add-dept-hod-dropdown-menu" class="searchable-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 1050; padding: 8px; text-align: left;">
                                    <div style="padding-bottom: 6px; border-bottom: 1px solid var(--border-color); margin-bottom: 6px;">
                                        <input type="text" id="add-dept-hod-search-input" class="input-control" placeholder="🔍 Search employee by name, designation, email..." style="width: 100%; padding: 6px 10px; font-size: 12.5px;" oninput="filterAddDeptHodDropdown(this.value)" autocomplete="off">
                                    </div>
                                    <div id="add-dept-hod-dropdown-list" style="max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                                        <!-- Populated dynamically -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 5px;">The assigned HOD will manage teams and staff assignments within this department.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department Description / Scope</label>
                        <textarea id="add-dept-description" class="input-control" rows="2" placeholder="Brief outline of operations, goals, or duties..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('add-department-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 700;">✨ Create Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT DEPARTMENT -->
    <div id="edit-department-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px;">✏️ Edit Department</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Update department details and leadership assignments.</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('edit-department-modal')">✕</button>
            </div>
            <form id="edit-department-form" onsubmit="handleEditDepartmentSubmit(event)">
                <input type="hidden" id="edit-dept-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Department Name *</label>
                        <input type="text" id="edit-dept-name" class="input-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="font-weight: 700;">Parent Department (Optional - for Sub-Departments)</label>
                        <select id="edit-dept-parent" class="input-control" style="font-weight: 600;">
                            <option value="">🏢 None (Top-Level Division / Main Department)</option>
                        </select>
                        <small style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;">
                            If this is an operational sub-unit (e.g. <em>Archive</em> or <em>NLE</em> under <em>Programming</em>), select its parent division.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Head of Department (HOD)</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                            <div id="edit-dept-hod-avatar" class="admin-inspector-avatar" style="width: 38px; height: 38px; font-size: 15px; border-radius: 50%; background: #3b82f6; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 700;">👤</div>
                            <div class="searchable-dropdown-wrapper" id="edit-dept-hod-search-wrapper" style="position: relative; flex: 1;">
                                <button type="button" id="edit-dept-hod-dropdown-btn" class="input-control" style="font-weight: 600; font-size: 13px; padding: 8px 12px; width: 100%; border-color: var(--primary); background: var(--bg-card); cursor: pointer; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left;" onclick="toggleEditDeptHodDropdown()">
                                    <span id="edit-dept-hod-selected-name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-- No HOD Appointed Yet --</span>
                                    <span style="font-size: 10px; color: var(--text-muted);">▼</span>
                                </button>
                                <input type="hidden" id="edit-dept-hod" value="">

                                <!-- Floating Search Dropdown Menu -->
                                <div id="edit-dept-hod-dropdown-menu" class="searchable-dropdown-menu" style="display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); z-index: 1050; padding: 8px; text-align: left;">
                                    <div style="padding-bottom: 6px; border-bottom: 1px solid var(--border-color); margin-bottom: 6px;">
                                        <input type="text" id="edit-dept-hod-search-input" class="input-control" placeholder="🔍 Search employee by name, designation, email..." style="width: 100%; padding: 6px 10px; font-size: 12.5px;" oninput="filterEditDeptHodDropdown(this.value)" autocomplete="off">
                                    </div>
                                    <div id="edit-dept-hod-dropdown-list" style="max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                                        <!-- Populated dynamically -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department Description / Scope</label>
                        <textarea id="edit-dept-description" class="input-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('edit-department-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 700;">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD TEAM (HOD & ADMIN) -->
    <div id="add-team-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px;">👥 Create New Team</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Form a specialized operational unit under your department.</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('add-team-modal')">✕</button>
            </div>
            <form id="add-team-form" onsubmit="handleSaveTeamSubmit(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Parent Department *</label>
                        <select id="add-team-dept" class="input-control" required>
                            <!-- Populated dynamically -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Team Name *</label>
                        <input type="text" id="add-team-name" class="input-control" placeholder="e.g. Facebook Team, YouTube Lead, NLE Unit" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Team Focus / Scope</label>
                        <textarea id="add-team-description" class="input-control" rows="2" placeholder="Tasks, platform responsibilities, or workflow goals..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('add-team-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 700;">✨ Create Team</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT TEAM -->
    <div id="edit-team-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px;">✏️ Edit Team Details</h3>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Update team title, parent department, or description.</p>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('edit-team-modal')">✕</button>
            </div>
            <form id="edit-team-form" onsubmit="handleEditTeamSubmit(event)">
                <input type="hidden" id="edit-team-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Parent Department *</label>
                        <select id="edit-team-dept" class="input-control" required>
                            <!-- Populated dynamically -->
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Team Name *</label>
                        <input type="text" id="edit-team-name" class="input-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Team Focus / Scope</label>
                        <textarea id="edit-team-description" class="input-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('edit-team-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 700;">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: DEPARTMENT DRILLDOWN (VIEW TEAMS & ROSTER) -->
    <div id="department-drilldown-modal" class="modal-overlay">
        <div class="modal-card modal-card-lg" style="max-width: 860px;">
            <div class="modal-header" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%); border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div id="drilldown-dept-icon" style="width: 44px; height: 44px; border-radius: 12px; background: var(--primary); color: #fff; font-size: 22px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(37,99,235,0.25);">🏢</div>
                    <div>
                        <h3 id="drilldown-dept-name" style="margin: 0; font-size: 18px; color: var(--text-main); display: flex; align-items: center; gap: 8px;">Department Name</h3>
                        <p id="drilldown-dept-meta" style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">HOD • Staff Members • Teams</p>
                    </div>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('department-drilldown-modal')">✕</button>
            </div>

            <div class="modal-body" style="padding: 16px 20px; gap: 18px;">
                <!-- Header Stats Bar & Actions -->
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 12px 16px; background: var(--bg-card-elevated); border-radius: 10px; border: 1px solid var(--border-color);">
                    <div style="display: flex; gap: 18px; flex-wrap: wrap;" id="drilldown-dept-stats">
                        <!-- Populated dynamically -->
                    </div>
                    <div id="drilldown-actions-bar" style="display: flex; gap: 8px;">
                        <!-- "+ Add Team to Dept" button populated dynamically -->
                    </div>
                </div>

                <!-- Unassigned Staff Box in this Department -->
                <div id="drilldown-unassigned-container" style="display: none;">
                    <!-- Populated dynamically if there are unassigned staff in this department -->
                </div>

                <!-- Department Teams & Roster List -->
                <div>
                    <h4 style="font-size: 14px; margin: 0 0 10px 0; color: var(--text-main); display: flex; align-items: center; justify-content: space-between;">
                        <span>📋 Operational Teams & Staff Roster</span>
                        <span id="drilldown-team-count-badge" class="badge" style="font-size: 11px; background: var(--primary-light); color: var(--primary);">0 Teams</span>
                    </h4>
                    <div id="drilldown-teams-list" style="display: flex; flex-direction: column; gap: 14px;">
                        <!-- Populated dynamically with teams & their assigned staff -->
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding: 12px 20px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('department-drilldown-modal')">Close</button>
            </div>
        </div>
    </div>

    <!-- MODAL: DEPARTMENT TRACKING OPTIONS & CONTENT MATRIX SETTINGS -->
    <div id="tracking-options-modal" class="modal-overlay">
        <div class="modal-card modal-card-lg" style="max-width: 920px;">
            <div class="modal-header" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.08) 0%, rgba(16, 185, 129, 0.08) 100%); border-bottom: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #2563eb, #0ea5e9); color: #fff; font-size: 22px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(37,99,235,0.25);">⚙️</div>
                    <div>
                        <h3 style="margin: 0; font-size: 18px; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                            <span>Department Tracking & Matrix Options</span>
                            <span id="tracking-modal-dept-badge" class="badge" style="background: var(--primary-light); color: var(--primary); font-size: 11px;">Digital Media</span>
                        </h3>
                        <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">
                            Configure Content Types & Tracking Desks for worksheets and the Content Matrix.
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-modal-close" onclick="closeModal('tracking-options-modal')">✕</button>
            </div>

            <div class="modal-body" style="padding: 18px 22px; gap: 16px; max-height: calc(85vh - 120px); overflow-y: auto;">
                <!-- Super Admin Department Selector (Hidden for HOD) -->
                <div id="tracking-admin-dept-picker-row" style="display: none; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 14px;">🏢</span>
                        <strong style="font-size: 13px;">Manage Options for Department:</strong>
                    </div>
                    <select id="tracking-admin-dept-select" class="input-control" style="width: auto; min-width: 200px; padding: 5px 10px; font-size: 13px; font-weight: 600;" onchange="handleTrackingDeptSwitch(this.value)">
                        <!-- Populated dynamically -->
                    </select>
                </div>

                <!-- Modal Sub-Tabs Navigation -->
                <div style="display: flex; gap: 8px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; flex-wrap: wrap;">
                    <button type="button" id="tab-btn-worksheet-cols" class="btn btn-primary tracking-modal-tab-btn" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;" onclick="switchTrackingModalTab('cols')">
                        📋 Worksheet Columns & Layout
                    </button>
                    <button type="button" id="tab-btn-content-types" class="btn btn-outline tracking-modal-tab-btn" style="padding: 6px 14px; font-size: 12.5px; font-weight: 600;" onclick="switchTrackingModalTab('content_types')">
                        🎬 Content Types
                    </button>
                    <button type="button" id="tab-btn-tracking-depts" class="btn btn-outline tracking-modal-tab-btn" style="padding: 6px 14px; font-size: 12.5px; font-weight: 600;" onclick="switchTrackingModalTab('tracking_depts')">
                        🏢 Tracking Desks / Depts
                    </button>
                    <button type="button" id="tab-btn-hod-settings" class="btn btn-outline tracking-modal-tab-btn" style="padding: 6px 14px; font-size: 12.5px; font-weight: 600;" onclick="switchTrackingModalTab('hod_settings')">
                        ✍️ HOD Verification & Print Rules
                    </button>
                </div>

                <!-- ================= TAB 1: WORKSHEET COLUMNS BUILDER ================= -->
                <div id="tracking-tab-section-cols" class="tracking-tab-section" style="display: flex; flex-direction: column; gap: 14px;">
                    <!-- Informational Tip Banner -->
                    <div style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: var(--radius-md); font-size: 12px; line-height: 1.5; color: var(--text-main);">
                        <span style="font-size: 16px; line-height: 1;">💡</span>
                        <div>
                            <strong>Worksheet Columns & Post-Checkout Rules:</strong> Customize worksheet column headers, reorder columns (<strong>◀ Left</strong> / <strong>▶ Right</strong>), or toggle visibility. You can also specify which fields employees can edit <strong>after shift checkout</strong> (e.g. <strong>Link / Upload</strong> is editable by default so employees can paste URLs when stories are published, while duty time and task titles remain safely locked).
                        </div>
                    </div>

                    <!-- Add New Column Card -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 14px 16px; box-shadow: var(--shadow-sm);">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <h4 style="margin: 0; font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                                <span>➕ Add Custom Column to Worksheet</span>
                            </h4>
                        </div>

                        <form onsubmit="handleWorksheetColumnAddSubmit(event)" style="display: flex; flex-direction: column; gap: 10px;">
                            <div style="display: grid; grid-template-columns: 2fr 1.5fr auto; gap: 10px; align-items: end;">
                                <div>
                                    <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">Column Name / Header *</label>
                                    <input type="text" id="new-col-label" class="input-control" placeholder="e.g. Story Slug, Guest Name, Status..." style="font-size: 12.5px; width: 100%;" required>
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">Column Type *</label>
                                    <select id="new-col-type" class="input-control" style="font-size: 12.5px; width: 100%;" onchange="toggleNewColumnOptionsInput(this.value)">
                                        <option value="text">📝 Text Input</option>
                                        <option value="select">🔽 Dropdown Selection</option>
                                        <option value="link">🔗 Link / URL</option>
                                        <option value="number">🔢 Number</option>
                                    </select>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary" style="padding: 7px 16px; font-size: 12.5px; font-weight: 700; white-space: nowrap;">
                                        ➕ Add Column
                                    </button>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 8px;">
                                <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: var(--text-muted); cursor: pointer;">
                                    <input type="checkbox" id="new-col-editable-after-lock">
                                    <span>Allow employees to edit this column after shift checkout (e.g. links or updates)</span>
                                </label>
                            </div>

                            <!-- Additional options input when 'select' type is chosen -->
                            <div id="new-col-options-row" style="display: none; background: var(--bg-card-elevated); padding: 8px 12px; border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                                <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">Dropdown Choices (Comma-separated)</label>
                                <input type="text" id="new-col-options-input" class="input-control" placeholder="e.g. Draft, In Review, Ready to Post, Published" style="font-size: 12px; width: 100%;">
                            </div>
                        </form>
                    </div>

                    <!-- Columns Reorder & Rename List -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 14px 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                            <h4 style="margin: 0; font-size: 13.5px; font-weight: 700;">
                                Active Columns (Ordered Left to Right in Worksheet)
                            </h4>
                            <span id="worksheet-columns-count-badge" class="badge" style="font-size: 11px;">5 Columns</span>
                        </div>

                        <div id="worksheet-columns-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 380px; overflow-y: auto;">
                            <div style="text-align: center; color: var(--text-muted); padding: 20px; font-size: 12px;">Loading columns...</div>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 2: CONTENT TYPES ONLY ================= -->
                <div id="tracking-tab-section-content-types" class="tracking-tab-section" style="display: none; flex-direction: column; gap: 14px;">
                    <!-- Informational Tip Banner -->
                    <div style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px; background: rgba(147, 51, 234, 0.08); border: 1px solid rgba(147, 51, 234, 0.2); border-radius: var(--radius-md); font-size: 12px; line-height: 1.5; color: var(--text-main);">
                        <span style="font-size: 16px; line-height: 1;">🎬</span>
                        <div>
                            <strong>Department Content Types:</strong> These categories define the type of output produced by your team (e.g. <em>Reels, YT Videos, Post Cards, FB Videos, Podcast</em>). They populate the <strong>Content Type</strong> dropdown in worksheet rows and serve as the columns in your <strong>Department Content Matrix</strong> report.
                        </div>
                    </div>

                    <!-- Add New Content Type Card -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 14px 16px; box-shadow: var(--shadow-sm);">
                        <h4 style="margin: 0 0 10px 0; font-size: 13.5px; font-weight: 700;">➕ Add New Content Type</h4>
                        <form onsubmit="handleTrackingOptionAddSubmit(event, 'content_type')" style="display: flex; gap: 8px;">
                            <input type="text" id="new-content-type-name" class="input-control" placeholder="Add type name (e.g. Shorts, Live Stream, Highlights, Promo)..." style="flex: 1; font-size: 12.5px;" required>
                            <button type="submit" class="btn btn-primary" style="padding: 7px 16px; font-size: 12.5px; font-weight: 700; white-space: nowrap;">
                                ➕ Add Type
                            </button>
                        </form>
                    </div>

                    <!-- Content Types List Card -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 14px 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                            <h4 style="margin: 0; font-size: 13.5px; font-weight: 700;">Active Content Types for This Department</h4>
                            <span id="content-types-count-badge" class="badge" style="font-size: 11px;">0 items</span>
                        </div>
                        <div id="tracking-content-types-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 380px; overflow-y: auto; padding-right: 4px;">
                            <div style="text-align: center; color: var(--text-muted); padding: 20px; font-size: 12px;">Loading options...</div>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 3: TRACKING DEPARTMENTS / DESKS ONLY ================= -->
                <div id="tracking-tab-section-tracking-depts" class="tracking-tab-section" style="display: none; flex-direction: column; gap: 14px;">
                    <!-- Informational Tip Banner -->
                    <div style="display: flex; align-items: flex-start; gap: 10px; padding: 10px 14px; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: var(--radius-md); font-size: 12px; line-height: 1.5; color: var(--text-main);">
                        <span style="font-size: 16px; line-height: 1;">🏢</span>
                        <div>
                            <strong>Tracking Desks / Departments:</strong> These define the assignment desks, target channels, or internal units your team creates work for (e.g. <em>Digital, News Room, Programming, Documentary, Others</em>). They populate the <strong>Department</strong> dropdown in worksheet rows and serve as the rows in your <strong>Department Content Matrix</strong> report.
                        </div>
                    </div>

                    <!-- Add New Tracking Desk Card -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 14px 16px; box-shadow: var(--shadow-sm);">
                        <h4 style="margin: 0 0 10px 0; font-size: 13.5px; font-weight: 700;">➕ Add New Tracking Desk / Department</h4>
                        <form onsubmit="handleTrackingOptionAddSubmit(event, 'tracking_dept')" style="display: flex; gap: 8px;">
                            <input type="text" id="new-tracking-dept-name" class="input-control" placeholder="Add desk name (e.g. Discover Pakistan, Web Portal, Satellite Feed)..." style="flex: 1; font-size: 12.5px;" required>
                            <button type="submit" class="btn btn-primary" style="padding: 7px 16px; font-size: 12.5px; font-weight: 700; white-space: nowrap;">
                                ➕ Add Desk
                            </button>
                        </form>
                    </div>

                    <!-- Tracking Desks List Card -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 14px 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                            <h4 style="margin: 0; font-size: 13.5px; font-weight: 700;">Active Tracking Desks for This Department</h4>
                            <span id="tracking-depts-count-badge" class="badge" style="font-size: 11px;">0 items</span>
                        </div>
                        <div id="tracking-depts-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 380px; overflow-y: auto; padding-right: 4px;">
                            <div style="text-align: center; color: var(--text-muted); padding: 20px; font-size: 12px;">Loading options...</div>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 4: HOD VERIFICATION & PRINT RESTRICTION SETTINGS ================= -->
                <div id="tracking-tab-section-hod-settings" class="tracking-tab-section" style="display: none; flex-direction: column; gap: 16px;">
                    <!-- Informational Tip Banner -->
                    <div style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; background: rgba(14, 165, 233, 0.08); border: 1px solid rgba(14, 165, 233, 0.25); border-radius: var(--radius-md); font-size: 12.5px; line-height: 1.55; color: var(--text-main);">
                        <span style="font-size: 20px; line-height: 1;">🛡️</span>
                        <div>
                            <strong>Department-Specific HOD Verification Workflow:</strong> Customize how worksheets in this department are reviewed and printed. Each HOD has full autonomy to choose if they want to verify sheets on the system, restrict employees from printing until approved, and automatically embed an official <strong>Digital Verification & Approval Seal</strong> on the printed sheet!
                        </div>
                    </div>

                    <!-- Configuration Card -->
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 18px 20px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 18px;">
                        <h4 style="margin: 0; font-size: 14.5px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; justify-content: space-between;">
                            <span>⚙️ Verification & Print Policy for <span id="hod-settings-dept-label" style="color: var(--primary);">This Department</span></span>
                            <span id="hod-settings-assigned-hod" class="badge" style="font-size: 11px; background: var(--bg-hover);">HOD: --</span>
                        </h4>

                        <!-- Policy 1: Enable HOD Sheet Verification -->
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 14px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                            <div style="flex: 1;">
                                <label for="setting-require-hod-check" style="font-size: 13.5px; font-weight: 700; color: var(--text-main); cursor: pointer; display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span>✅ Enable HOD Digital Verification & Approval</span>
                                </label>
                                <p style="margin: 0; font-size: 12px; color: var(--text-muted); line-height: 1.45;">
                                    Allows HOD to inspect daily sheets on the system and click <strong>"Mark as Checked"</strong>. When marked checked, an official corporate verification stamp with HOD name & timestamp will automatically print on the sheet.
                                </p>
                            </div>
                            <label class="switch-toggle" style="margin-top: 4px; cursor: pointer;">
                                <input type="checkbox" id="setting-require-hod-check" onchange="handleHodCheckSettingChange(this)">
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <!-- Policy 2: Block Printing until Checked -->
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 14px; background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                            <div style="flex: 1;">
                                <label for="setting-block-print-before-check" style="font-size: 13.5px; font-weight: 700; color: var(--text-main); cursor: pointer; display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span>🔒 Restrict Employee Printing (Must be Checked by HOD First)</span>
                                </label>
                                <p style="margin: 0; font-size: 12px; color: var(--text-muted); line-height: 1.45;">
                                    Employees in this department <strong>cannot print</strong> their worksheet until their HOD inspects and marks it checked on the system. If turned off, employees can print anytime, and the sheet will show the digital stamp if already checked or physical signing line if not yet checked.
                                </p>
                            </div>
                            <label class="switch-toggle" style="margin-top: 4px; cursor: pointer;">
                                <input type="checkbox" id="setting-block-print-before-check">
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <!-- Official Printout Seal Preview Card -->
                        <div style="border: 1px dashed var(--border-color); border-radius: var(--radius-md); padding: 14px 18px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.04), rgba(59, 130, 246, 0.04));">
                            <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; display: block; margin-bottom: 8px;">Printout Preview (HOD Signature Section)</span>
                            <div style="display: flex; align-items: center; justify-content: space-between; max-width: 380px; padding: 10px 14px; background: #fff; color: #0f172a; border: 1.5px solid #10b981; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px;">✓</div>
                                    <div>
                                        <div style="font-size: 11.5px; font-weight: 800; color: #065f46; letter-spacing: 0.3px;">DIGITALLY VERIFIED & APPROVED</div>
                                        <div style="font-size: 10px; color: #475569; font-weight: 600;">Verified by: <span id="preview-hod-name" style="font-weight: 700;">HOD Name</span></div>
                                        <div style="font-size: 9px; color: #64748b;">Automated Time-Stamped System Audit</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
                            <button type="button" class="btn btn-primary" style="padding: 8px 22px; font-weight: 700; font-size: 13px;" onclick="saveHodVerificationSettings()">
                                💾 Save Department Verification Rules
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer" style="padding: 12px 22px; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-outline" style="color: var(--danger, #ef4444); border-color: rgba(239, 68, 68, 0.3); font-size: 12px;" onclick="handleResetActiveTrackingTabDefaults()">
                        🔄 Reset to Defaults
                    </button>
                </div>
                <button type="button" class="btn btn-primary" style="padding: 6px 18px; font-size: 13px;" onclick="closeModal('tracking-options-modal')">
                    Done
                </button>
            </div>
        </div>
    </div>

    <!-- GLOBAL PROFESSIONAL CONFIRMATION DIALOG -->
    <div id="app-confirm-dialog-modal" class="modal-overlay" style="z-index: 10100;">
        <div class="modal-card app-dialog-card" style="max-width: 440px; border-radius: 18px; text-align: center; padding: 0;">
            <div style="padding: 26px 24px 20px; display: flex; flex-direction: column; align-items: center;">
                <div id="app-confirm-icon-wrap" style="width: 58px; height: 58px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; margin-bottom: 16px; background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1.5px solid rgba(239, 68, 68, 0.25); transition: all 0.2s ease;">
                    <span id="app-confirm-icon">⚠️</span>
                </div>
                <h3 id="app-confirm-title" style="margin: 0 0 8px 0; font-size: 18px; font-weight: 800; color: var(--text-main); letter-spacing: -0.2px;">Confirm Action</h3>
                <div id="app-confirm-message" style="font-size: 13.5px; color: var(--text-muted); line-height: 1.55; word-break: break-word; white-space: pre-line; max-height: 240px; overflow-y: auto;">
                    Are you sure you want to proceed?
                </div>
                <div id="app-confirm-subnote" style="display: none; margin-top: 12px; padding: 10px 14px; background: var(--bg-card-elevated); border-radius: 8px; font-size: 11.5px; color: var(--text-muted); border: 1px dashed var(--border-color); text-align: left; width: 100%;">
                </div>
            </div>
            <div style="background: var(--bg-card-elevated, #f8fafc); padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                <button type="button" id="app-confirm-cancel-btn" class="btn btn-outline" style="min-width: 90px; padding: 8px 18px; font-size: 13px; font-weight: 600; border-radius: 8px;">
                    Cancel
                </button>
                <button type="button" id="app-confirm-proceed-btn" class="btn btn-danger" style="min-width: 110px; padding: 8px 20px; font-size: 13px; font-weight: 700; border-radius: 8px;">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- GLOBAL PROFESSIONAL PROMPT DIALOG -->
    <div id="app-prompt-dialog-modal" class="modal-overlay" style="z-index: 10100;">
        <div class="modal-card app-dialog-card" style="max-width: 480px; border-radius: 18px; padding: 0;">
            <div style="padding: 22px 24px 16px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div id="app-prompt-icon-wrap" style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; background: rgba(59, 130, 246, 0.12); color: var(--accent-blue, #2563eb); border: 1px solid rgba(59, 130, 246, 0.25);">
                        <span id="app-prompt-icon">📝</span>
                    </div>
                    <div style="flex: 1;">
                        <h3 id="app-prompt-title" style="margin: 0; font-size: 16.5px; font-weight: 800; color: var(--text-main);">Input Required</h3>
                        <p id="app-prompt-message" style="margin: 3px 0 0 0; font-size: 12.5px; color: var(--text-muted); line-height: 1.4;">Please provide details below:</p>
                    </div>
                    <button type="button" class="btn-modal-close" id="app-prompt-close-btn" style="align-self: flex-start;">✕</button>
                </div>
                <div style="margin-top: 14px;">
                    <textarea id="app-prompt-input" class="input-control" rows="3" style="width: 100%; font-size: 13px; resize: vertical; border-radius: 8px;" placeholder=""></textarea>
                </div>
            </div>
            <div style="background: var(--bg-card-elevated, #f8fafc); padding: 12px 20px; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                <button type="button" id="app-prompt-cancel-btn" class="btn btn-outline" style="min-width: 85px; padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 8px;">
                    Cancel
                </button>
                <button type="button" id="app-prompt-submit-btn" class="btn btn-primary" style="min-width: 95px; padding: 8px 18px; font-size: 13px; font-weight: 700; border-radius: 8px;">
                    Submit
                </button>
            </div>
        </div>
    </div>




