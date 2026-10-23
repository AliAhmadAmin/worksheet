<!-- TAB: PROGRAMMING & DIGITAL HANDOVER PIPELINE -->
<section id="tab-programming" class="tab-section <?= ($currentPortal === 'programming') ? 'active' : '' ?>">
    <!-- Hero Header -->
    <div class="worksheet-card" style="margin-bottom: 18px; border-top: 4px solid #d97706;">
        <div class="card-header-flex" style="flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
            <div class="card-title-group">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">📡</span>
                    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin: 0;">
                        Programming ➔ Digital Content Handover
                    </h2>
                </div>
            </div>

            <div class="controls-group" style="display: flex; gap: 10px; align-items: center;">
                <button type="button" id="btn-open-prog-dispatch" class="btn btn-primary" style="background: linear-gradient(135deg, #d97706, #b45309); border: none; font-weight: 700; padding: 8px 14px; border-radius: 6px; font-size: 13px;" onclick="openDispatchModal()">
                    ➕ Dispatch New Content
                </button>
                <button type="button" class="btn btn-outline" style="padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 600;" onclick="exportProgrammingCsv()">
                    📊 Export CSV
                </button>
            </div>
        </div>

        <!-- KPI Metrics Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 16px;">
            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #d97706;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">📡 Total Dispatches</div>
                <div id="prog-stat-total" style="font-size: 24px; font-weight: 800; color: #d97706; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #16a34a;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🟢 DP Published</div>
                <div id="prog-stat-dp" style="font-size: 24px; font-weight: 800; color: #16a34a; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #dc2626;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🔴 PT Published</div>
                <div id="prog-stat-pt" style="font-size: 24px; font-weight: 800; color: #dc2626; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #b45309;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🟤 Move to PT</div>
                <div id="prog-stat-move-pt" style="font-size: 24px; font-weight: 800; color: #b45309; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #2563eb;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🔵 Corrections / Issues</div>
                <div id="prog-stat-corrections" style="font-size: 24px; font-weight: 800; color: #2563eb; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #eab308;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">⏳ In Queue</div>
                <div id="prog-stat-pending" style="font-size: 24px; font-weight: 800; color: #eab308; margin-top: 4px; line-height: 1;">0</div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="worksheet-card" style="margin-bottom: 18px; padding: 12px 18px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <div style="flex: 0 0 140px;">
                <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">📅 Date</label>
                <input type="date" id="prog-filter-date" class="input-control" value="<?= date('Y-m-d') ?>" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="handleProgFilterChange('date')">
            </div>

            <div style="flex: 0 0 135px;">
                <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🗓️ Month</label>
                <input type="month" id="prog-filter-month" class="input-control" value="<?= date('Y-m') ?>" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="handleProgFilterChange('month')">
            </div>

            <div style="flex: 0 0 150px;">
                <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">📺 Channel</label>
                <select id="prog-filter-channel" class="input-control" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="loadProgrammingDispatches()">
                    <option value="all">All Channels</option>
                    <option value="Discover Pakistan">Discover Pakistan</option>
                    <option value="Pakistan Today">Pakistan Today</option>
                    <option value="All Channels">Both Channels</option>
                </select>
            </div>

            <div style="flex: 0 0 160px;">
                <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🏷️ Status</label>
                <select id="prog-filter-status" class="input-control" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="loadProgrammingDispatches()">
                    <option value="all">All Statuses</option>
                    <option value="pending">⏳ Pending Queue</option>
                    <option value="In Progress">⚡ In Progress</option>
                    <option value="published">🟢 Published</option>
                    <option value="Correction">🔵 Correction</option>
                    <option value="Violation">🔴 Violation</option>
                    <option value="Move to PT">🟤 Move to PT</option>
                    <option value="Expire">⛔ Expire</option>
                    <option value="Rejected">🟤 Rejected</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 220px;">
                <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🔍 Search</label>
                <div style="position: relative;">
                    <input type="text" id="prog-search-input" class="input-control" placeholder="Search by title, path, sender..." style="font-size: 12.5px; padding: 7px 12px; height: 38px; border-radius: 6px; width: 100%;" oninput="debounceProgSearch()">
                </div>
            </div>

            <div style="flex: 0 0 auto;">
                <button type="button" class="btn btn-outline" style="height: 38px; padding: 0 14px; font-size: 12.5px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;" onclick="resetProgFilters()" title="Reset All Filters">
                    ↺ Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Dual-Department Spreadsheet Table Matrix -->
    <div class="worksheet-card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive" style="max-height: 75vh; overflow-y: auto;">
            <table class="interactive-table prog-matrix-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <!-- Multi-Tier Dual Header -->
                    <tr>
                        <th colspan="7" style="background: #b45309; color: #ffffff; text-align: center; font-size: 13px; font-weight: 800; padding: 10px; letter-spacing: 0.5px; border-right: 3px solid rgba(0,0,0,0.15);">
                            🟨 PROGRAMMING DEPARTMENT (DISPATCH)
                        </th>
                        <th colspan="6" style="background: #15803d; color: #ffffff; text-align: center; font-size: 13px; font-weight: 800; padding: 10px; letter-spacing: 0.5px;">
                            🟩 DIGITAL DEPARTMENT (PUBLISHING & STATUS)
                        </th>
                    </tr>
                    <tr style="background: #1e3a5f; color: #ffffff; font-size: 12px; border-bottom: 2px solid var(--border-color);">
                        <!-- Programming Columns -->
                        <th class="sticky-col-date" style="width: 88px; min-width: 85px; background: #1e3a5f; color: #ffffff;">Date</th>
                        <th class="sticky-col-title" style="width: 175px; min-width: 155px; background: #1e3a5f; color: #ffffff;">Title</th>
                        <th style="width: 125px; min-width: 115px; background: #1e3a5f; color: #ffffff;">Path / WhatsApp</th>
                        <th style="width: 70px; min-width: 65px; text-align: center; background: #1e3a5f; color: #ffffff;">Channel</th>
                        <th style="width: 120px; min-width: 110px; background: #1e3a5f; color: #ffffff;">News Sender</th>
                        <th style="width: 75px; min-width: 70px; text-align: center; white-space: nowrap; background: #1e3a5f; color: #ffffff;">DP Link</th>
                        <th style="width: 75px; min-width: 70px; text-align: center; white-space: nowrap; background: #1e3a5f; color: #ffffff; border-right: 3px solid rgba(0,0,0,0.2);">PT Link</th>

                        <!-- Digital Columns -->
                        <th style="width: 125px; min-width: 118px; background: #1e3a5f; color: #ffffff;">Status</th>
                        <th style="width: 88px; min-width: 82px; text-align: center; background: #1e3a5f; color: #ffffff;">Publish Time</th>
                        <th style="width: 115px; min-width: 105px; background: #1e3a5f; color: #ffffff;">Employee Name</th>
                        <th style="width: 130px; min-width: 115px; background: #1e3a5f; color: #ffffff;">Remarks</th>
                        <th style="width: 60px; min-width: 55px; text-align: center; background: #1e3a5f; color: #ffffff;">Rating</th>
                        <th style="width: 65px; min-width: 60px; text-align: center; background: #1e3a5f; color: #ffffff;">Action</th>
                    </tr>
                </thead>
                <tbody id="prog-matrix-tbody">
                    <!-- Loaded dynamically via programming.js -->
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <div class="spinner" style="margin: 0 auto 10px;"></div>
                            Loading programming dispatches...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- MODAL: DISPATCH NEW CONTENT (PROGRAMMING TEAM) -->
<div id="dispatch-content-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header" style="border-bottom: 2px solid #d97706; padding: 12px 18px;">
            <h3 style="display: flex; align-items: center; gap: 8px; color: #b45309; margin: 0; font-size: 16px;">
                📡 Dispatch Content
            </h3>
            <button type="button" class="btn-modal-close" onclick="closeModal('dispatch-content-modal')">✕</button>
        </div>
        <form id="dispatch-content-form" onsubmit="handleDispatchSubmit(event)">
            <!-- Auto-populated Date & Time & Sender -->
            <input type="hidden" id="dispatch-date">
            <input type="hidden" id="dispatch-time">
            <input type="hidden" id="dispatch-sender-id">

            <div class="modal-body" style="gap: 12px; padding: 16px 18px;">
                <div class="form-group">
                    <label class="form-label" style="font-size: 12px; font-weight: 700;">Content Title *</label>
                    <input type="text" id="dispatch-title" class="input-control" placeholder="Segment or program name" required style="font-weight: 700; font-size: 13px;">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size: 12px; font-weight: 700;">Path / WhatsApp *</label>
                    <input type="text" id="dispatch-path" class="input-control" placeholder="\\Server path or WhatsApp link" required style="font-family: monospace; font-size: 12px;">
                </div>

                <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 700;">Channel</label>
                        <select id="dispatch-channel" class="input-control" style="font-weight: 700; font-size: 12px;">
                            <option value="Discover Pakistan" selected>🟢 Discover Pakistan</option>
                            <option value="Pakistan Today">🔴 Pakistan Today</option>
                            <option value="All Channels">🌐 Both Channels</option>
                        </select>
                    </div>

                    <!-- Sender dropdown: shown only for HOD; auto-hidden for regular employees -->
                    <div id="prog-sender-group" class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 700;">Sender</label>
                        <select id="dispatch-sender" class="input-control" style="font-weight: 600; font-size: 12px;">
                            <!-- Populated dynamically via programming.js -->
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size: 12px; font-weight: 700;">Story Link (Optional)</label>
                    <input type="url" id="dispatch-link" class="input-control" placeholder="Optional link" style="font-size: 12px;">
                </div>
            </div>
            <div class="modal-footer" style="padding: 12px 18px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('dispatch-content-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #b45309; border: none; font-weight: 700; padding: 8px 18px;">
                    🚀 Dispatch Content
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT DISPATCH & DIGITAL PUBLISHING RECORD -->
<div id="edit-dispatch-modal" class="modal-overlay">
    <div class="modal-card modal-card-xl">
        <div class="modal-header" style="border-bottom: 2px solid #15803d;">
            <div>
                <h3 style="display: flex; align-items: center; gap: 8px; color: #15803d;">
                    ✏️ Edit Handover & Publishing Record
                </h3>
                <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">
                    Update dispatch details, DP/PT status, publishing timestamp, and publisher remarks.
                </p>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeModal('edit-dispatch-modal')">✕</button>
        </div>
        <form id="edit-dispatch-form" onsubmit="handleEditDispatchSubmit(event)">
            <input type="hidden" id="edit-prog-id">
            <div class="modal-body" style="gap: 16px;">
                <!-- Section 1: Programming Info -->
                <div class="modal-section" style="border-left: 4px solid #d97706;">
                    <div class="modal-section-title" style="color: #d97706;">🟨 Programming Dispatch Details</div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Title *</label>
                            <input type="text" id="edit-prog-title" class="input-control" required style="font-weight: 700;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Channel</label>
                            <select id="edit-prog-channel" class="input-control">
                                <option value="Discover Pakistan">Discover Pakistan</option>
                                <option value="Pakistan Today">Pakistan Today</option>
                                <option value="All Channels">Both Channels</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Path / WhatsApp</label>
                        <input type="text" id="edit-prog-path" class="input-control" style="font-family: monospace; font-size: 12px;">
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" id="edit-prog-date" class="input-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sender</label>
                            <input type="text" id="edit-prog-sender" class="input-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Time</label>
                            <input type="text" id="edit-prog-time" class="input-control">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Digital Publishing -->
                <div class="modal-section" style="border-left: 4px solid #15803d;">
                    <div class="modal-section-title" style="color: #15803d; display: flex; align-items: center; justify-content: space-between;">
                        <span>🟩 Digital Publishing & Status</span>
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; color: #b45309; cursor: pointer; text-transform: none;">
                            <input type="checkbox" id="edit-prog-moved-to-pt" style="accent-color: #b45309; width: 14px; height: 14px;">
                            <span>🟤 Moved to Pakistan Today (PT)</span>
                        </label>
                    </div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Publishing Status</label>
                            <select id="edit-prog-status" class="input-control" style="font-weight: 700;">
                                <option value="" style="background: #334155; color: #f8fafc;">⏳ Pending</option>
                                <option value="In Progress" style="background: #d97706; color: #fff;">⚡ In Progress</option>
                                <option value="Published" style="background: #16a34a; color: #fff;">🟢 Published</option>
                                <option value="Move to PT" style="background: #b45309; color: #fff;">🟤 Move to PT</option>
                                <option value="Violation" style="background: #dc2626; color: #fff;">🔴 Violation</option>
                                <option value="Correction" style="background: #2563eb; color: #fff;">🔵 Correction</option>
                                <option value="Expire" style="background: #b91c1c; color: #fff;">⛔ Expire</option>
                                <option value="Rejected" style="background: #78350f; color: #fff;">🟤 Rejected</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Publishing Time</label>
                            <input type="text" id="edit-prog-publish-time" class="input-control" placeholder="e.g. 06:37:50 PM">
                        </div>
                    </div>

                    <!-- Hidden compatibility fields -->
                    <input type="hidden" id="edit-prog-dp-status">
                    <input type="hidden" id="edit-prog-dp-time">
                    <input type="hidden" id="edit-prog-pt-status">
                    <input type="hidden" id="edit-prog-pt-time">

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Digital Publisher (Employee)</label>
                            <select id="edit-prog-publisher" class="input-control">
                                <!-- Populated dynamically -->
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Editorial / Quality Rating</label>
                            <select id="edit-prog-rating" class="input-control">
                                <option value="">-- Unrated --</option>
                                <option value="5">⭐⭐⭐⭐⭐ Excellent (5/5)</option>
                                <option value="4">⭐⭐⭐⭐ Good (4/5)</option>
                                <option value="3">⭐⭐⭐ Average (3/5)</option>
                                <option value="2">⭐⭐ Needs Improvement (2/5)</option>
                                <option value="1">⭐ Poor (1/5)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">🟢 Discover Pakistan Story Link (URL)</label>
                            <div style="display: flex; gap: 6px;">
                                <input type="url" id="edit-prog-link" class="input-control" placeholder="https://..." style="flex: 1; font-family: monospace; font-size: 11.5px;" oninput="updateEditProgLinkButton(this.value, 'edit-prog-link-btn')">
                                <a id="edit-prog-link-btn" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="display: none; align-items: center; padding: 0 10px; font-size: 11px; white-space: nowrap;" title="Open DP link in new tab">
                                    🔗 Open
                                </a>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">🔴 Pakistan Today Story Link (URL)</label>
                            <div style="display: flex; gap: 6px;">
                                <input type="url" id="edit-prog-pt-link" class="input-control" placeholder="https://..." style="flex: 1; font-family: monospace; font-size: 11.5px;" oninput="updateEditProgLinkButton(this.value, 'edit-prog-pt-link-btn')">
                                <a id="edit-prog-pt-link-btn" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="display: none; align-items: center; padding: 0 10px; font-size: 11px; white-space: nowrap;" title="Open PT link in new tab">
                                    🔗 Open
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Remarks & Editorial Feedback</label>
                        <textarea id="edit-prog-remarks" class="input-control" rows="2" placeholder="e.g. Posted on Facebook Reels and YouTube Shorts"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <button type="button" class="btn-icon-del" style="padding: 8px 14px; font-size: 13px;" onclick="deleteCurrentDispatch()">
                    🗑️ Delete Record
                </button>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('edit-dispatch-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #15803d; border: none; font-weight: 700; padding: 9px 20px;">
                        💾 Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
