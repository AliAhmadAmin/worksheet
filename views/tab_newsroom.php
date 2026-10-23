<!-- TAB: NEWS ROOM & DIGITAL HANDOVER PIPELINE -->
<section id="tab-newsroom" class="tab-section <?= ($currentPortal === 'newsroom') ? 'active' : '' ?>">
    <!-- Hero Header -->
    <div class="worksheet-card" style="margin-bottom: 18px; border-top: 4px solid #dc2626;">
        <div class="card-header-flex" style="flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
            <div class="card-title-group">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 24px;">📺</span>
                    <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin: 0;">
                        News Room ➔ Digital Content Handover
                    </h2>
                </div>
            </div>

            <div class="controls-group" style="display: flex; gap: 10px; align-items: center;">
                <button type="button" id="btn-open-news-dispatch" class="btn btn-primary" style="background: linear-gradient(135deg, #dc2626, #b91c1c); border: none; font-weight: 700; padding: 8px 14px; border-radius: 6px; font-size: 13px;" onclick="openNewsDispatchModal()">
                    ➕ Dispatch New Story
                </button>
                <button type="button" class="btn btn-outline" style="padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 600;" onclick="exportNewsroomCsv()">
                    📊 Export CSV
                </button>
            </div>
        </div>

        <!-- KPI Metrics Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 16px;">
            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #dc2626;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">📺 Total Stories</div>
                <div id="news-stat-total" style="font-size: 24px; font-weight: 800; color: #dc2626; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #16a34a;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🟢 DP Published</div>
                <div id="news-stat-dp" style="font-size: 24px; font-weight: 800; color: #16a34a; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #dc2626;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🔴 PT Published</div>
                <div id="news-stat-pt" style="font-size: 24px; font-weight: 800; color: #dc2626; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #b45309;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🟤 Move to PT</div>
                <div id="news-stat-move-pt" style="font-size: 24px; font-weight: 800; color: #b45309; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #2563eb;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🔵 Corrections</div>
                <div id="news-stat-corrections" style="font-size: 24px; font-weight: 800; color: #2563eb; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 16px; border-left: 4px solid #eab308;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">⏳ In Queue</div>
                <div id="news-stat-pending" style="font-size: 24px; font-weight: 800; color: #eab308; margin-top: 4px; line-height: 1;">0</div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="worksheet-card" style="margin-bottom: 18px; padding: 12px 18px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md);">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <div style="flex: 0 0 140px;">
                <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">📅 Date</label>
                <input type="date" id="news-filter-date" class="input-control" value="<?= date('Y-m-d') ?>" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="handleNewsFilterChange('date')">
            </div>

            <div style="flex: 0 0 135px;">
                <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🗓️ Month</label>
                <input type="month" id="news-filter-month" class="input-control" value="<?= date('Y-m') ?>" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="handleNewsFilterChange('month')">
            </div>

            <div style="flex: 0 0 150px;">
                <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">📺 Channel</label>
                <select id="news-filter-channel" class="input-control" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="loadNewsroomDispatches()">
                    <option value="all">All Channels</option>
                    <option value="Discover Pakistan">Discover Pakistan</option>
                    <option value="Pakistan Today">Pakistan Today</option>
                    <option value="All Channels">Both Channels</option>
                </select>
            </div>

            <div style="flex: 0 0 160px;">
                <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">🏷️ Status</label>
                <select id="news-filter-status" class="input-control" style="font-size: 12.5px; padding: 7px 10px; height: 38px; border-radius: 6px;" onchange="loadNewsroomDispatches()">
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
                    <input type="text" id="news-search-input" class="input-control" placeholder="Search by title, slug, path, sender..." style="font-size: 12.5px; padding: 7px 12px; height: 38px; border-radius: 6px; width: 100%;" oninput="debounceNewsSearch()">
                </div>
            </div>

            <div style="flex: 0 0 auto;">
                <button type="button" class="btn btn-outline" style="height: 38px; padding: 0 14px; font-size: 12.5px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;" onclick="resetNewsFilters()" title="Reset All Filters">
                    ↺ Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Dual-Department Spreadsheet Table Matrix -->
    <div class="worksheet-card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive" style="max-height: 75vh; overflow-y: auto;">
            <table class="interactive-table news-matrix-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <!-- Multi-Tier Dual Header -->
                    <tr>
                        <th colspan="6" style="background: #b91c1c; color: #ffffff; text-align: center; font-size: 13px; font-weight: 800; padding: 10px; letter-spacing: 0.5px; border-right: 3px solid rgba(0,0,0,0.15);">
                            🟥 NEWS ROOM DEPARTMENT (DISPATCH)
                        </th>
                        <th colspan="6" style="background: #15803d; color: #ffffff; text-align: center; font-size: 13px; font-weight: 800; padding: 10px; letter-spacing: 0.5px;">
                            🟩 DIGITAL DEPARTMENT (PUBLISHING & STATUS)
                        </th>
                    </tr>
                    <tr style="background: #1e3a5f; color: #ffffff; font-size: 12px; border-bottom: 2px solid var(--border-color);">
                        <!-- News Room Columns -->
                        <th style="width: 88px; min-width: 85px; background: #1e3a5f; color: #ffffff;">Date</th>
                        <th style="width: 175px; min-width: 155px; background: #1e3a5f; color: #ffffff;">Title / Slug</th>
                        <th style="width: 125px; min-width: 115px; background: #1e3a5f; color: #ffffff;">Path / WhatsApp</th>
                        <th style="width: 70px; min-width: 65px; text-align: center; background: #1e3a5f; color: #ffffff;">Channel</th>
                        <th style="width: 120px; min-width: 110px; background: #1e3a5f; color: #ffffff;">News Sender</th>
                        <th style="width: 85px; min-width: 80px; text-align: center; white-space: nowrap; background: #1e3a5f; color: #ffffff; border-right: 3px solid rgba(0,0,0,0.2);">Links</th>

                        <!-- Digital Columns -->
                        <th style="width: 125px; min-width: 118px; background: #1e3a5f; color: #ffffff;">Status</th>
                        <th style="width: 88px; min-width: 82px; text-align: center; background: #1e3a5f; color: #ffffff;">Publish Time</th>
                        <th style="width: 115px; min-width: 105px; background: #1e3a5f; color: #ffffff;">Employee Name</th>
                        <th style="width: 130px; min-width: 115px; background: #1e3a5f; color: #ffffff;">Remarks</th>
                        <th style="width: 60px; min-width: 55px; text-align: center; background: #1e3a5f; color: #ffffff;">Rating</th>
                        <th style="width: 65px; min-width: 60px; text-align: center; background: #1e3a5f; color: #ffffff;">Action</th>
                    </tr>
                </thead>
                <tbody id="news-matrix-tbody">
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <div class="spinner" style="margin: 0 auto 10px;"></div>
                            Loading newsroom dispatches...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- MODAL: DISPATCH NEW STORY (NEWS ROOM TEAM) -->
<div id="dispatch-news-modal" class="modal-overlay">
    <div class="modal-card" style="max-width: 500px;">
        <div class="modal-header" style="border-bottom: 2px solid #dc2626; padding: 12px 18px;">
            <h3 style="display: flex; align-items: center; gap: 8px; color: #b91c1c; margin: 0; font-size: 16px;">
                📺 Dispatch New Story
            </h3>
            <button type="button" class="btn-modal-close" onclick="closeModal('dispatch-news-modal')">✕</button>
        </div>
        <form id="dispatch-news-form" onsubmit="handleNewsDispatchSubmit(event)">
            <!-- Auto-populated Date & Time & Sender -->
            <input type="hidden" id="news-dispatch-date">
            <input type="hidden" id="news-dispatch-time">
            <input type="hidden" id="news-dispatch-sender-id">

            <div class="modal-body" style="gap: 12px; padding: 16px 18px;">
                <div class="form-group">
                    <label class="form-label" style="font-size: 12px; font-weight: 700;">Story Title / Slug *</label>
                    <input type="text" id="news-dispatch-title" class="input-control" placeholder="Story title or slug" required style="font-weight: 700; font-size: 13px;">
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size: 12px; font-weight: 700;">Path / WhatsApp *</label>
                    <input type="text" id="news-dispatch-path" class="input-control" placeholder="\\Server path or WhatsApp link" required style="font-family: monospace; font-size: 12px;">
                </div>

                <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 700;">Channel</label>
                        <select id="news-dispatch-channel" class="input-control" style="font-weight: 700; font-size: 12px;">
                            <option value="Discover Pakistan" selected>🟢 Discover Pakistan</option>
                            <option value="Pakistan Today">🔴 Pakistan Today</option>
                            <option value="All Channels">🌐 Both Channels</option>
                        </select>
                    </div>

                    <!-- Sender dropdown: shown only for HOD; auto-hidden for regular employees -->
                    <div id="news-sender-group" class="form-group">
                        <label class="form-label" style="font-size: 12px; font-weight: 700;">Sender</label>
                        <select id="news-dispatch-sender" class="input-control" style="font-weight: 600; font-size: 12px;">
                            <!-- Populated dynamically via newsroom.js -->
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size: 12px; font-weight: 700;">Story Link (Optional)</label>
                    <input type="url" id="news-dispatch-link" class="input-control" placeholder="Optional link" style="font-size: 12px;">
                </div>
            </div>
            <div class="modal-footer" style="padding: 12px 18px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('dispatch-news-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #b91c1c; border: none; font-weight: 700; padding: 8px 18px;">
                    🚀 Dispatch Story
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT NEWS DISPATCH & DIGITAL PUBLISHING RECORD -->
<div id="edit-news-dispatch-modal" class="modal-overlay">
    <div class="modal-card modal-card-xl">
        <div class="modal-header" style="border-bottom: 2px solid #15803d;">
            <div>
                <h3 style="display: flex; align-items: center; gap: 8px; color: #15803d;">
                    ✏️ Edit News Handover & Publishing Record
                </h3>
                <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">
                    Update story details, DP/PT publishing status, timestamps, and publisher remarks.
                </p>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeModal('edit-news-dispatch-modal')">✕</button>
        </div>
        <form id="edit-news-dispatch-form" onsubmit="handleEditNewsSubmit(event)">
            <input type="hidden" id="edit-news-id">
            <div class="modal-body" style="gap: 16px;">
                <!-- Section 1: News Room Dispatch Info -->
                <div style="background: rgba(220, 38, 38, 0.04); border: 1px solid rgba(220, 38, 38, 0.2); border-radius: var(--radius-md); padding: 14px 16px;">
                    <div style="font-size: 12px; font-weight: 800; color: #b91c1c; text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <span>📺</span> News Room Dispatch Details
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Story Title / Slug</label>
                        <input type="text" id="edit-news-title" class="input-control" required style="font-weight: 700;">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Channel</label>
                            <select id="edit-news-channel" class="input-control">
                                <option value="Discover Pakistan">Discover Pakistan</option>
                                <option value="Pakistan Today">Pakistan Today</option>
                                <option value="All Channels">Both Channels</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">News Sender</label>
                            <input type="text" id="edit-news-sender" class="input-control" readonly style="background: var(--bg-input);">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Network Path / Location</label>
                        <input type="text" id="edit-news-path" class="input-control" style="font-family: monospace; font-size: 12px;">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Dispatch Date</label>
                            <input type="date" id="edit-news-date" class="input-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dispatch Time</label>
                            <input type="text" id="edit-news-time" class="input-control">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Digital Department Publishing Info -->
                <div style="background: rgba(22, 163, 74, 0.04); border: 1px solid rgba(22, 163, 74, 0.2); border-radius: var(--radius-md); padding: 14px 16px;">
                    <div style="font-size: 12px; font-weight: 800; color: #15803d; text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                        <span style="display: flex; align-items: center; gap: 6px;"><span>🟩</span> Digital Department Publishing Status</span>
                        <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; color: #b45309; cursor: pointer; text-transform: none;">
                            <input type="checkbox" id="edit-news-moved-to-pt" style="accent-color: #b45309; width: 14px; height: 14px;">
                            <span>🟤 Moved to Pakistan Today (PT)</span>
                        </label>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Publishing Status</label>
                            <select id="edit-news-status" class="input-control" style="font-weight: 700;">
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
                            <input type="text" id="edit-news-publish-time" class="input-control" placeholder="e.g. 03:45:00 PM">
                        </div>
                    </div>

                    <!-- Hidden compatibility fields -->
                    <input type="hidden" id="edit-news-dp-status">
                    <input type="hidden" id="edit-news-dp-time">
                    <input type="hidden" id="edit-news-pt-status">
                    <input type="hidden" id="edit-news-pt-time">

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Digital Publisher</label>
                            <select id="edit-news-publisher" class="input-control">
                                <!-- Populated dynamically -->
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Content Rating (1 - 5 Stars)</label>
                            <select id="edit-news-rating" class="input-control">
                                <option value="">-- No Rating --</option>
                                <option value="5">⭐⭐⭐⭐⭐ 5 - Outstanding</option>
                                <option value="4">⭐⭐⭐⭐ 4 - Great</option>
                                <option value="3">⭐⭐⭐ 3 - Average</option>
                                <option value="2">⭐⭐ 2 - Needs Work</option>
                                <option value="1">⭐ 1 - Poor</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">🟢 Discover Pakistan Story Link (URL)</label>
                            <div style="display: flex; gap: 6px;">
                                <input type="url" id="edit-news-link" class="input-control" placeholder="https://..." style="flex: 1; font-family: monospace; font-size: 11.5px;" oninput="updateEditNewsLinkButton(this.value, 'edit-news-link-btn')">
                                <a id="edit-news-link-btn" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="display: none; align-items: center; padding: 0 10px; font-size: 11px; white-space: nowrap;" title="Open DP link in new tab">
                                    🔗 Open
                                </a>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">🔴 Pakistan Today Story Link (URL)</label>
                            <div style="display: flex; gap: 6px;">
                                <input type="url" id="edit-news-pt-link" class="input-control" placeholder="https://..." style="flex: 1; font-family: monospace; font-size: 11.5px;" oninput="updateEditNewsLinkButton(this.value, 'edit-news-pt-link-btn')">
                                <a id="edit-news-pt-link-btn" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="display: none; align-items: center; padding: 0 10px; font-size: 11px; white-space: nowrap;" title="Open PT link in new tab">
                                    🔗 Open
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Digital Remarks / Notes</label>
                        <textarea id="edit-news-remarks" class="input-control" rows="2" placeholder="Remarks or publishing notes..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: space-between;">
                <button type="button" class="btn btn-danger btn-sm" onclick="deleteNewsDispatch()" style="background: #ef4444; color: #fff;">
                    🗑️ Delete Story
                </button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('edit-news-dispatch-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #15803d; border: none; font-weight: 700; padding: 9px 20px;">
                        💾 Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
