<!-- TAB: PROGRAMMING & DIGITAL HANDOVER PIPELINE -->
<section id="tab-programming" class="tab-section <?= ($currentPortal === 'programming') ? 'active' : '' ?>">
    <!-- Hero Header -->
    <div class="worksheet-card" style="margin-bottom: 20px; border-top: 4px solid #d97706;">
        <div class="card-header-flex" style="flex-wrap: wrap; gap: 14px;">
            <div class="card-title-group">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 28px;">📡</span>
                    <div>
                        <h2 style="font-size: 20px; font-weight: 800; color: var(--text-main); margin: 0;">
                            Programming ➔ Digital Content Handover Command Board
                        </h2>
                        <p style="font-size: 13px; color: var(--text-muted); margin: 3px 0 0 0;">
                            Real-time broadcast dispatch, media path tracking, and multi-channel digital publishing workflow.
                        </p>
                    </div>
                </div>
            </div>

            <div class="controls-group" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" style="background: linear-gradient(135deg, #d97706, #b45309); border: none; font-weight: 700;" onclick="openDispatchModal()">
                    ➕ Dispatch New Content
                </button>
                <button type="button" class="btn btn-outline" onclick="loadProgrammingDispatches()">
                    🔄 Refresh Board
                </button>
                <button type="button" class="btn btn-outline" onclick="exportProgrammingCsv()">
                    📊 Export CSV
                </button>
                <button type="button" class="btn btn-outline" onclick="printProgrammingReport()">
                    🖨️ Print Log
                </button>
            </div>
        </div>

        <!-- KPI Metrics Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top: 20px;">
            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #d97706;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">📡 Total Dispatches</div>
                <div id="prog-stat-total" style="font-size: 24px; font-weight: 800; color: #d97706; margin-top: 4px;">0</div>
                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">From Programming</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #16a34a;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🟢 DP Published</div>
                <div id="prog-stat-dp" style="font-size: 24px; font-weight: 800; color: #16a34a; margin-top: 4px;">0</div>
                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">Discover Pakistan</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #059669;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🟢 PT Published</div>
                <div id="prog-stat-pt" style="font-size: 24px; font-weight: 800; color: #059669; margin-top: 4px;">0</div>
                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">Pakistan Today</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #2563eb;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🔵 Corrections / Issues</div>
                <div id="prog-stat-corrections" style="font-size: 24px; font-weight: 800; color: #2563eb; margin-top: 4px;">0</div>
                <div style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">Revisions needed</div>
            </div>

            <div style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; border-left: 4px solid #eab308;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">⏳ In Queue</div>
                <div id="prog-stat-pending" style="font-size: 24px; font-weight: 800; color: #eab308; margin-top: 4px;">0</div>
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
                    <input type="date" id="prog-filter-date" class="input-control" style="font-size: 12.5px; padding: 6px 10px;" onchange="handleProgFilterChange('date')">
                </div>

                <div class="form-group" style="margin-bottom: 0; min-width: 130px;">
                    <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">🗓️ Month</label>
                    <input type="month" id="prog-filter-month" class="input-control" style="font-size: 12.5px; padding: 6px 10px;" onchange="handleProgFilterChange('month')">
                </div>

                <div class="form-group" style="margin-bottom: 0; min-width: 160px;">
                    <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">📺 Channel</label>
                    <select id="prog-filter-channel" class="input-control" style="font-size: 12.5px; padding: 6px 10px;" onchange="loadProgrammingDispatches()">
                        <option value="all">All Channels</option>
                        <option value="Discover Pakistan">Discover Pakistan</option>
                        <option value="Pakistan Today">Pakistan Today</option>
                        <option value="Discover Islam">Discover Islam</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0; min-width: 160px;">
                    <label class="form-label" style="font-size: 11px; margin-bottom: 3px;">🏷️ Status</label>
                    <select id="prog-filter-status" class="input-control" style="font-size: 12.5px; padding: 6px 10px;" onchange="loadProgrammingDispatches()">
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
                    <input type="text" id="prog-search-input" class="input-control" placeholder="🔍 Search title, path, sender..." style="padding-left: 12px; font-size: 12.5px;" oninput="debounceProgSearch()">
                </div>
                <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 12px;" onclick="resetProgFilters()" title="Reset All Filters">
                    Reset
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
                        <th colspan="7" style="background: #b45309; color: #ffffff; text-align: center; font-size: 14px; font-weight: 800; padding: 10px; letter-spacing: 0.5px; border-right: 3px solid rgba(0,0,0,0.15);">
                            🟨 PROGRAMMING DEPARTMENT (DISPATCH)
                        </th>
                        <th colspan="8" style="background: #15803d; color: #ffffff; text-align: center; font-size: 14px; font-weight: 800; padding: 10px; letter-spacing: 0.5px;">
                            🟩 DIGITAL DEPARTMENT (PUBLISHING & STATUS)
                        </th>
                    </tr>
                    <tr style="background: var(--bg-card-elevated); font-size: 12px; border-bottom: 2px solid var(--border-color);">
                        <!-- Programming Columns -->
                        <th style="width: 95px; min-width: 95px;">Date</th>
                        <th style="width: 220px; min-width: 200px;">Title</th>
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
                <tbody id="prog-matrix-tbody">
                    <!-- Loaded dynamically via programming.js -->
                    <tr>
                        <td colspan="15" style="text-align: center; padding: 40px; color: var(--text-muted);">
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
    <div class="modal-card modal-card-lg">
        <div class="modal-header" style="border-bottom: 2px solid #d97706;">
            <div>
                <h3 style="display: flex; align-items: center; gap: 8px; color: #b45309;">
                    📡 Dispatch New Content to Digital
                </h3>
                <p style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">
                    Export and send video clips, reels, promos, or program segments to Digital Media Team.
                </p>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeModal('dispatch-content-modal')">✕</button>
        </div>
        <form id="dispatch-content-form" onsubmit="handleDispatchSubmit(event)">
            <div class="modal-body" style="gap: 14px;">
                <div class="form-group">
                    <label class="form-label">Content Title / Segment Name *</label>
                    <input type="text" id="dispatch-title" class="input-control" placeholder="e.g. Fashion Dairy AI segments 03, Discover Islam Promo" required style="font-weight: 700; font-size: 13.5px;">
                </div>

                <div class="form-group">
                    <label class="form-label">Network Path / WhatsApp / Archive Location *</label>
                    <input type="text" id="dispatch-path" class="input-control" placeholder="e.g. \\10.0.0.4\transmission2\PROGRAMS\FLAVOR OF PAKISTAN\EPISODE 02" required style="font-family: monospace; font-size: 12px;">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Target Channel</label>
                        <select id="dispatch-channel" class="input-control" style="font-weight: 700;">
                            <option value="Discover Pakistan" selected>🟢 Discover Pakistan</option>
                            <option value="Pakistan Today">🔴 Pakistan Today</option>
                            <option value="Discover Islam">🔵 Discover Islam</option>
                            <option value="All Channels">🌐 All Channels</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">News Sender (Programming Producer)</label>
                        <select id="dispatch-sender" class="input-control" style="font-weight: 600;">
                            <!-- Populated dynamically via programming.js -->
                        </select>
                    </div>
                </div>

                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Dispatch Date</label>
                        <input type="date" id="dispatch-date" class="input-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dispatch Time</label>
                        <input type="text" id="dispatch-time" class="input-control" placeholder="Auto now (e.g. 03:23:51 PM)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Source Link (Optional)</label>
                        <input type="url" id="dispatch-link" class="input-control" placeholder="https://drive.google.com/...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('dispatch-content-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #b45309; border: none; font-weight: 700; padding: 9px 20px;">
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
                                <option value="Discover Islam">Discover Islam</option>
                                <option value="All Channels">All Channels</option>
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
                    <div class="modal-section-title" style="color: #15803d;">🟩 Digital Publishing & Status</div>
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">DP Status (Discover Pakistan)</label>
                            <select id="edit-prog-dp-status" class="input-control" style="font-weight: 700;" onchange="handleModalDpStatusChange(this.value)">
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
                            <input type="text" id="edit-prog-dp-time" class="input-control" placeholder="e.g. 06:37:50 PM">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">PT Status (Pakistan Today)</label>
                            <select id="edit-prog-pt-status" class="input-control" style="font-weight: 700;" onchange="handleModalPtStatusChange(this.value)">
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
                            <input type="text" id="edit-prog-pt-time" class="input-control" placeholder="e.g. 05:48:11 PM">
                        </div>
                    </div>

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
