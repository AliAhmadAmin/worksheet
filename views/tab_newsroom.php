<!-- TAB: NEWS ROOM & DIGITAL HANDOVER PIPELINE -->
<section id="tab-newsroom" class="tab-section <?= ($currentPortal === 'newsroom') ? 'active' : '' ?>">
    <!-- Hero Header -->
    <div class="worksheet-card" style="margin-bottom: 20px; border-top: 4px solid #dc2626;">
        <div class="card-header-flex" style="flex-wrap: wrap; gap: 14px;">
            <div class="card-title-group">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 28px;">📺</span>
                    <div>
                        <h2 style="font-size: 20px; font-weight: 800; color: var(--text-main); margin: 0;">
                            News Room ➔ Digital Content Handover Command Board
                        </h2>
                        <p style="font-size: 13px; color: var(--text-muted); margin: 3px 0 0 0;">
                            Real-time news tickers, bulletins, reporter packages, and multi-channel digital publishing workflow.
                        </p>
                    </div>
                </div>
            </div>

            <div class="controls-group" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" style="background: linear-gradient(135deg, #dc2626, #b91c1c); border: none; font-weight: 700;" onclick="openNewsDispatchModal()">
                    ➕ Dispatch New Story
                </button>
                <button type="button" class="btn btn-outline" onclick="loadNewsroomDispatches()">
                    🔄 Refresh Board
                </button>
                <button type="button" class="btn btn-outline" onclick="exportNewsroomCsv()">
                    📊 Export CSV
                </button>
                <button type="button" class="btn btn-outline" onclick="printNewsroomReport()">
                    🖨️ Print Log
                </button>
            </div>
        </div>

        <!-- KPI Metrics Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 20px;">
            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #dc2626;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">📺 Total Stories</div>
                <div id="news-stat-total" style="font-size: 24px; font-weight: 800; color: #dc2626; margin-top: 4px;">0</div>
                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">From News Room</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #16a34a;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🟢 DP Published</div>
                <div id="news-stat-dp" style="font-size: 24px; font-weight: 800; color: #16a34a; margin-top: 4px;">0</div>
                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">Discover Pakistan</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #059669;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🟢 PT Published</div>
                <div id="news-stat-pt" style="font-size: 24px; font-weight: 800; color: #059669; margin-top: 4px;">0</div>
                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">Pakistan Today</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #2563eb;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🔵 Corrections / Issues</div>
                <div id="news-stat-corrections" style="font-size: 24px; font-weight: 800; color: #2563eb; margin-top: 4px;">0</div>
                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">Revisions requested</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #eab308;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">⏳ In Queue</div>
                <div id="news-stat-pending" style="font-size: 24px; font-weight: 800; color: #eab308; margin-top: 4px;">0</div>
                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">Awaiting Digital action</div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="worksheet-card" style="margin-bottom: 20px; padding: 14px 18px;">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
            <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <div class="form-group" style="margin-bottom: 0; min-width: 140px;">
                    <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">📅 Date Filter</label>
                    <input type="date" id="news-filter-date" class="input-control" style="font-size: 12.5px; padding: 6px 10px;" onchange="handleNewsFilterChange('date')">
                </div>

                <div class="form-group" style="margin-bottom: 0; min-width: 130px;">
                    <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">🗓️ Month</label>
                    <input type="month" id="news-filter-month" class="input-control" style="font-size: 12.5px; padding: 6px 10px;" onchange="handleNewsFilterChange('month')">
                </div>

                <div class="form-group" style="margin-bottom: 0; min-width: 160px;">
                    <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">📺 Channel</label>
                    <select id="news-filter-channel" class="input-control" style="font-size: 12.5px; padding: 6px 10px;" onchange="loadNewsroomDispatches()">
                        <option value="all">All Channels</option>
                        <option value="Discover Pakistan">Discover Pakistan</option>
                        <option value="Pakistan Today">Pakistan Today</option>
                        <option value="Discover Islam">Discover Islam</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0; min-width: 160px;">
                    <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">🏷️ Status</label>
                    <select id="news-filter-status" class="input-control" style="font-size: 12.5px; padding: 6px 10px;" onchange="loadNewsroomDispatches()">
                        <option value="all">All Statuses</option>
                        <option value="pending">⏳ Pending Queue</option>
                        <option value="published">🟢 Published</option>
                        <option value="Correction">🔵 Correction</option>
                        <option value="Violation">🔴 Violation</option>
                        <option value="Move to PT">🟤 Move to PT</option>
                        <option value="Expire">⛔ Expire</option>
                        <option value="Rejected">🟤 Rejected</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 8px; align-items: center; flex: 1; max-width: 320px; min-width: 200px;">
                <div style="position: relative; width: 100%;">
                    <input type="text" id="news-search-input" class="input-control" placeholder="🔍 Search slug, path, editor..." style="padding-left: 12px; font-size: 12.5px;" oninput="debounceNewsSearch()">
                </div>
                <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 12px;" onclick="resetNewsFilters()" title="Reset All Filters">
                    Reset
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
                        <th colspan="7" style="background: #b91c1c; color: #ffffff; text-align: center; font-size: 14px; font-weight: 800; padding: 10px; letter-spacing: 0.5px; border-right: 3px solid rgba(0,0,0,0.15);">
                            🟥 NEWS ROOM DEPARTMENT (DISPATCH)
                        </th>
                        <th colspan="8" style="background: #15803d; color: #ffffff; text-align: center; font-size: 14px; font-weight: 800; padding: 10px; letter-spacing: 0.5px;">
                            🟩 DIGITAL DEPARTMENT (PUBLISHING & STATUS)
                        </th>
                    </tr>
                    <tr style="background: var(--bg-card-elevated); font-size: 12px; border-bottom: 2px solid var(--border-color);">
                        <!-- News Room Columns -->
                        <th style="width: 95px; min-width: 95px;">Date</th>
                        <th style="width: 220px; min-width: 200px;">Title / Slug</th>
                        <th style="width: 260px; min-width: 240px;">Path / WhatsApp</th>
                        <th style="width: 130px; min-width: 120px;">Channel</th>
                        <th style="width: 140px; min-width: 130px;">News Sender</th>
                        <th style="width: 90px; min-width: 85px;">Time</th>
                        <th style="width: 60px; min-width: 50px; text-align: center; border-right: 3px solid var(--border-color);">Link</th>

                        <!-- Digital Columns -->
                        <th style="width: 165px; min-width: 160px;">DP Status</th>
                        <th style="width: 90px; min-width: 85px;">Time</th>
                        <th style="width: 165px; min-width: 160px;">PT Status</th>
                        <th style="width: 90px; min-width: 85px;">Time</th>
                        <th style="width: 140px; min-width: 130px;">Employee Name</th>
                        <th style="width: 180px; min-width: 160px;">Remarks</th>
                        <th style="width: 80px; min-width: 75px; text-align: center;">Rating</th>
                        <th style="width: 80px; min-width: 75px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="news-matrix-tbody">
                    <tr>
                        <td colspan="15" style="text-align: center; padding: 40px; color: var(--text-muted);">
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
    <div class="modal-card modal-card-lg">
        <div class="modal-header" style="border-bottom: 2px solid #dc2626;">
            <div>
                <h3 style="display: flex; align-items: center; gap: 8px; color: #b91c1c;">
                    📺 Dispatch New Story / Package to Digital
                </h3>
                <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">
                    Send breaking news bulletin, reporter package, or ticker story to Digital Media Team.
                </p>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeModal('dispatch-news-modal')">✕</button>
        </div>
        <form id="dispatch-news-form" onsubmit="handleNewsDispatchSubmit(event)">
            <div class="modal-body" style="gap: 14px;">
                <div class="form-group">
                    <label class="form-label">News Title / Slug *</label>
                    <input type="text" id="news-dispatch-title" class="input-control" placeholder="e.g. Breaking: Supreme Court Verdict Package, Prime Time News Rundown" required style="font-weight: 700; font-size: 13.5px;">
                </div>

                <div class="form-group">
                    <label class="form-label">Network Path / WhatsApp / Video Location *</label>
                    <input type="text" id="news-dispatch-path" class="input-control" placeholder="e.g. \\10.0.0.5\NEWSROOM\PACKAGES\2026-10-06\COURT_REPORT_V1" required style="font-family: monospace; font-size: 12px;">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Target Channel</label>
                        <select id="news-dispatch-channel" class="input-control" style="font-weight: 700;">
                            <option value="Discover Pakistan" selected>🟢 Discover Pakistan</option>
                            <option value="Pakistan Today">🔴 Pakistan Today</option>
                            <option value="Discover Islam">🔵 Discover Islam</option>
                            <option value="All Channels">🌐 All Channels</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">News Sender (Producer / Editor)</label>
                        <select id="news-dispatch-sender" class="input-control" style="font-weight: 600;">
                            <!-- Populated dynamically via newsroom.js -->
                        </select>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Dispatch Date</label>
                        <input type="date" id="news-dispatch-date" class="input-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dispatch Time</label>
                        <input type="text" id="news-dispatch-time" class="input-control" placeholder="Auto now (e.g. 03:23:51 PM)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Source Link (Optional)</label>
                        <input type="url" id="news-dispatch-link" class="input-control" placeholder="https://drive.google.com/...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('dispatch-news-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #b91c1c; border: none; font-weight: 700; padding: 9px 20px;">
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
                                <option value="Discover Islam">Discover Islam</option>
                                <option value="All Channels">All Channels</option>
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
                    <div style="font-size: 12px; font-weight: 800; color: #15803d; text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <span>🟩</span> Digital Department Publishing Status
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">DP Status (Discover Pakistan)</label>
                            <select id="edit-news-dp-status" class="input-control" style="font-weight: 700;" onchange="handleNewsModalDpStatusChange(this.value)">
                                <option value="" style="background: #334155; color: #f8fafc;">⏳ Pending</option>
                                <option value="Published" style="background: #16a34a; color: #fff;">🟢 Published</option>
                                <option value="Violation" style="background: #dc2626; color: #fff;">🔴 Violation</option>
                                <option value="Correction" style="background: #2563eb; color: #fff;">🔵 Correction</option>
                                <option value="Move to PT" style="background: #7f1d1d; color: #fff;">🟤 Move to PT</option>
                                <option value="Published on PT" style="background: #15803d; color: #fff;">🟢 Published on PT</option>
                                <option value="Expire" style="background: #b91c1c; color: #fff;">⛔ Expire</option>
                                <option value="Rejected" style="background: #78350f; color: #fff;">🟤 Rejected</option>
                                <option value="N/A" style="background: #1e293b; color: #94a3b8;">— N/A</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">DP Publishing Time</label>
                            <input type="text" id="edit-news-dp-time" class="input-control" placeholder="e.g. 04:15:00 PM">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">PT Status (Pakistan Today)</label>
                            <select id="edit-news-pt-status" class="input-control" style="font-weight: 700;" onchange="handleNewsModalPtStatusChange(this.value)">
                                <option value="" style="background: #334155; color: #f8fafc;">⏳ Pending</option>
                                <option value="Published" style="background: #16a34a; color: #fff;">🟢 Published</option>
                                <option value="Violation" style="background: #dc2626; color: #fff;">🔴 Violation</option>
                                <option value="Correction" style="background: #2563eb; color: #fff;">🔵 Correction</option>
                                <option value="Expire" style="background: #b91c1c; color: #fff;">⛔ Expire</option>
                                <option value="Rejected" style="background: #78350f; color: #fff;">🟤 Rejected</option>
                                <option value="N/A" style="background: #1e293b; color: #94a3b8;">— N/A</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">PT Publishing Time</label>
                            <input type="text" id="edit-news-pt-time" class="input-control" placeholder="e.g. 04:20:00 PM">
                        </div>
                    </div>

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

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Digital Remarks / Notes</label>
                        <textarea id="edit-news-remarks" class="input-control" rows="2" placeholder="e.g. Published on FB & YouTube Shorts at 04:30 PM. Audio clean."></textarea>
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
