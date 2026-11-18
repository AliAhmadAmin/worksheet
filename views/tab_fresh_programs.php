<?php
/**
 * Tab / View: Digital Fresh Programs & Episode Breakdown Pipeline
 */
$userRolePg = strtolower($authUser['role'] ?? '');
$isHodFresh = in_array($userRolePg, ['hod', 'deputy_hod', 'super_admin', 'admin', 'manager']);
$currentEmpId = (int)($authUser['id'] ?? 0);
?>
<section id="tab-fresh-programs" class="tab-section active">
    <!-- Hero Header Card -->
    <div class="worksheet-card" style="margin-bottom: 18px; border-top: 4px solid #10b981; padding: 18px 20px;">
        <div class="card-header-flex" style="flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
            <div class="card-title-group">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.25)); display: flex; align-items: center; justify-content: center; font-size: 22px; color: #10b981;">
                        🎬
                    </div>
                    <div>
                        <h2 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                            Digital Fresh Programs & Episode Breakdown
                            <span style="font-size: 11px; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #059669; padding: 2px 8px; border-radius: 20px; text-transform: uppercase;">Weekly Editing</span>
                        </h2>
                        <p style="font-size: 12.5px; color: var(--text-muted); margin: 3px 0 0 0;">
                            Assign weekly TV programs to editors, extract viral clips and social reels, track completion, and lock verified episodes.
                        </p>
                    </div>
                </div>
            </div>

            <div class="controls-group" style="display: flex; gap: 10px; align-items: center;">
                <?php if ($isHodFresh): ?>
                <button type="button" class="btn btn-primary" onclick="openAssignProgramModal()" style="background: linear-gradient(135deg, #10b981, #059669); border: none; font-weight: 700; padding: 8px 16px; border-radius: 8px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);">
                    <span>➕</span> Assign New Program
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline" onclick="loadFreshPrograms(true)" style="padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <span>🔄</span> Refresh Board
                </button>
            </div>
        </div>

        <!-- KPI Metrics Summary Cards -->
        <style>
            .fresh-kpi-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            }
            .fresh-kpi-card.active-card {
                box-shadow: 0 0 0 2px var(--primary, #10b981), 0 4px 14px rgba(16, 185, 129, 0.25) !important;
                transform: translateY(-2px);
            }
        </style>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap: 12px; margin-top: 18px;">
            <div id="card-kpi-total" class="fresh-kpi-card" onclick="filterByKpi('all')" style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; border-left: 4px solid #3b82f6; cursor: pointer; transition: all 0.2s ease;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🎬 Total Programs</div>
                <div id="kpi-prog-total" style="font-size: 24px; font-weight: 800; color: #3b82f6; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div id="card-kpi-pending" class="fresh-kpi-card" onclick="filterByKpi('pending')" style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; border-left: 4px solid #f59e0b; cursor: pointer; transition: all 0.2s ease;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">⏳ Pending</div>
                <div id="kpi-prog-pending" style="font-size: 24px; font-weight: 800; color: #f59e0b; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div id="card-kpi-inprogress" class="fresh-kpi-card" onclick="filterByKpi('in_progress')" style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; border-left: 4px solid #8b5cf6; cursor: pointer; transition: all 0.2s ease;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">⚡ In Progress</div>
                <div id="kpi-prog-inprogress" style="font-size: 24px; font-weight: 800; color: #8b5cf6; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div id="card-kpi-completed" class="fresh-kpi-card" onclick="filterByKpi('completed')" style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; border-left: 4px solid #10b981; cursor: pointer; transition: all 0.2s ease;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">🔒 Completed</div>
                <div id="kpi-prog-completed" style="font-size: 24px; font-weight: 800; color: #10b981; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div class="fresh-kpi-card" style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; border-left: 4px solid #ec4899;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">✂️ Total Clips</div>
                <div id="kpi-prog-clips" style="font-size: 24px; font-weight: 800; color: #ec4899; margin-top: 4px; line-height: 1;">0</div>
            </div>

            <div class="fresh-kpi-card" style="background: var(--bg-card-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; border-left: 4px solid #06b6d4;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">📱 Total Reels</div>
                <div id="kpi-prog-reels" style="font-size: 24px; font-weight: 800; color: #06b6d4; margin-top: 4px; line-height: 1;">0</div>
            </div>
        </div>
    </div>

    <!-- Main Two-Column Interactive Layout -->
    <div style="display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap;">
        <!-- Left Sidebar: Interactive Month Calendar (~290px) -->
        <div style="flex: 0 0 290px; width: 290px; display: flex; flex-direction: column; gap: 14px;">
            <!-- Calendar Card -->
            <div class="worksheet-card" style="padding: 16px; border-radius: var(--radius-md); background: var(--bg-card); border: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <button type="button" class="btn btn-outline" onclick="changeCalendarMonth(-1)" style="padding: 4px 8px; font-size: 11px; border-radius: 6px;" title="Previous Month">◀</button>
                    <span id="cal-month-title" style="font-size: 13.5px; font-weight: 800; color: var(--text-main);">November 2026</span>
                    <button type="button" class="btn btn-outline" onclick="changeCalendarMonth(1)" style="padding: 4px 8px; font-size: 11px; border-radius: 6px;" title="Next Month">▶</button>
                </div>

                <!-- Calendar Weekdays Header -->
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 10.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 6px;">
                    <div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div><div>Su</div>
                </div>

                <!-- Calendar Days Grid -->
                <div id="cal-days-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; text-align: center;">
                    <!-- Injected by fresh_programs.js -->
                </div>

                <div style="display: flex; gap: 6px; margin-top: 14px;">
                    <button type="button" class="btn btn-outline" onclick="selectCalendarDate(getTodayDateStr())" style="flex: 1; padding: 6px 8px; font-size: 11px; font-weight: 700; border-radius: 6px; text-align: center;">
                        Today
                    </button>
                    <button type="button" class="btn btn-outline" onclick="selectCalendarMonth()" style="flex: 1; padding: 6px 8px; font-size: 11px; font-weight: 600; border-radius: 6px; text-align: center;">
                        This Month
                    </button>
                    <button type="button" class="btn btn-outline" onclick="selectAllPrograms()" style="flex: 1; padding: 6px 8px; font-size: 11px; font-weight: 600; border-radius: 6px; text-align: center;">
                        All Time
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Main Board: Programs Roster Table (Flex 1) -->
        <div style="flex: 1; min-width: 580px; display: flex; flex-direction: column; gap: 14px;">
            <div class="worksheet-card" style="padding: 14px 18px; border-radius: var(--radius-md); background: var(--bg-card); border: 1px solid var(--border-color);">
                <!-- Week Selector Navigation Tabs -->
                <style>
                    .btn-week-tab {
                        display: inline-flex;
                        align-items: center;
                        gap: 6px;
                        padding: 6px 14px;
                        border-radius: 20px;
                        border: 1px solid var(--border-color);
                        background: var(--bg-card-elevated);
                        color: var(--text-muted);
                        font-size: 12px;
                        font-weight: 600;
                        cursor: pointer;
                        white-space: nowrap;
                        transition: all 0.15s ease;
                        user-select: none;
                    }
                    .btn-week-tab:hover {
                        border-color: var(--primary, #10b981);
                        color: var(--text-main);
                        transform: translateY(-1px);
                    }
                    .btn-week-tab.active {
                        background: linear-gradient(135deg, #10b981, #059669);
                        border-color: #059669;
                        color: #ffffff;
                        font-weight: 700;
                        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
                    }
                    .week-section-header {
                        background: #ecfdf5 !important;
                        color: #065f46 !important;
                        padding: 10px 16px;
                        font-weight: 800;
                        font-size: 13px;
                        letter-spacing: 0.8px;
                        text-transform: uppercase;
                        border-left: 5px solid #10b981 !important;
                        border-top: 1px solid #d1fae5;
                        border-bottom: 1px solid #d1fae5;
                    }
                    [data-theme="dark"] .week-section-header {
                        background: rgba(16, 185, 129, 0.15) !important;
                        color: #6ee7b7 !important;
                        border-left: 5px solid #10b981 !important;
                        border-top: 1px solid rgba(16, 185, 129, 0.25);
                        border-bottom: 1px solid rgba(16, 185, 129, 0.25);
                    }
                    /* Rounded Pill Style for Statuses */
                    #fresh-programs-tbody .news-status-select,
                    #fresh-programs-tbody .news-status-pill {
                        border-radius: 20px !important;
                        padding: 4px 12px !important;
                    }
                    .day-section-header {
                        background: #f1f5f9;
                        border-left: 4px solid #3b82f6;
                        padding: 8px 14px;
                        font-weight: 800;
                        font-size: 12px;
                        color: #1e3a5f;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }
                    [data-theme="dark"] .day-section-header {
                        background: rgba(30, 58, 95, 0.35);
                        color: #93c5fd;
                        border-left-color: #3b82f6;
                    }
                </style>
                <!-- Top Toolbar with Week, Status Filters and Search -->
                <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <select id="filter-week" class="input-control" onchange="handleWeekDropdownChange(this.value)" style="font-size: 12px; height: 34px; border-radius: 6px; min-width: 195px; padding: 4px 8px; background: var(--bg-card); font-weight: 700; color: var(--text-main);">
                            <!-- Populated dynamically by fresh_programs.js -->
                        </select>

                        <select id="filter-status" class="input-control" onchange="loadFreshPrograms()" style="font-size: 12px; height: 34px; border-radius: 6px; min-width: 130px; padding: 4px 8px; background: var(--bg-card);">
                            <option value="all">All Statuses</option>
                            <option value="pending">⏳ Pending</option>
                            <option value="in_progress">⚡ In Progress</option>
                            <option value="completed">🔒 Completed</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" id="filter-search" class="input-control" placeholder="Search program, ep, editor..." oninput="debounceSearch()" style="width: 220px; font-size: 12px; height: 34px; border-radius: 6px; padding: 6px 10px;">
                        <button type="button" class="btn btn-outline" onclick="resetAllFilters()" style="padding: 6px 12px; font-size: 12px; border-radius: 6px;" title="Reset filters">
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Programs Roster Table -->
                <div class="table-responsive" style="overflow-x: auto;">
                    <table class="interactive-table news-matrix-table" style="width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px;">
                        <thead>
                            <tr style="background: #1e3a5f; color: #ffffff; font-size: 12px; border-bottom: 2px solid var(--border-color);">
                                <th style="width: 14%; min-width: 110px; padding: 10px 12px; text-align: left; font-weight: 700; background: #1e3a5f; color: #ffffff; letter-spacing: 0.5px; border-top-left-radius: 6px; white-space: nowrap;">Air Date</th>
                                <th style="width: 27%; min-width: 190px; padding: 10px 12px; text-align: left; font-weight: 700; background: #1e3a5f; color: #ffffff; letter-spacing: 0.5px;">Program & Episode</th>
                                <th style="width: 21%; min-width: 140px; padding: 10px 12px; text-align: left; font-weight: 700; background: #1e3a5f; color: #ffffff; letter-spacing: 0.5px;">Assigned Editors</th>
                                <th style="width: 13%; min-width: 120px; padding: 10px 8px; text-align: center; font-weight: 700; background: #1e3a5f; color: #ffffff; letter-spacing: 0.5px;">Status</th>
                                <th style="width: 8%; min-width: 85px; padding: 10px 8px; text-align: center; font-weight: 700; background: #1e3a5f; color: #ffffff; letter-spacing: 0.5px;">Clips</th>
                                <th style="width: 8%; min-width: 85px; padding: 10px 8px; text-align: center; font-weight: 700; background: #1e3a5f; color: #ffffff; letter-spacing: 0.5px;">Reels</th>
                                <th style="width: 9%; min-width: 80px; padding: 10px 12px; text-align: right; font-weight: 700; background: #1e3a5f; color: #ffffff; letter-spacing: 0.5px; border-top-right-radius: 6px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="fresh-programs-tbody">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <div style="font-size: 24px; margin-bottom: 8px;">⏳</div>
                                    Loading fresh programs...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal: View Program Instructions -->
<div id="modal-program-instructions" class="modal-overlay" style="display: none; z-index: 10250; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="modal-card" style="max-width: 500px; width: 92%; border-radius: 16px; padding: 0; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.25);">
        <div style="padding: 16px 22px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; background: var(--bg-card-elevated);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 22px;">📝</span>
                <div>
                    <h3 id="instr-modal-title" style="margin: 0; font-size: 15px; font-weight: 800; color: var(--text-main);">Program Instructions</h3>
                    <div id="instr-modal-subtitle" style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">Episode # • Air Date</div>
                </div>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeInstructionsModal()" style="background: transparent; border: none; font-size: 18px; cursor: pointer; color: var(--text-muted);">✕</button>
        </div>
        <div style="padding: 22px;">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                📋 Instructions from HOD:
            </div>
            <div id="instr-modal-body" style="background: rgba(245, 158, 11, 0.08); border-left: 4px solid #f59e0b; border-radius: 6px; padding: 14px 16px; font-size: 13.5px; line-height: 1.5; color: var(--text-main); white-space: pre-wrap; word-break: break-word;">
            </div>
        </div>
        <div style="background: var(--bg-card-elevated); padding: 12px 22px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-outline" onclick="closeInstructionsModal()" style="padding: 6px 18px; font-size: 12.5px; font-weight: 600; border-radius: 6px;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Popover Modal for Adding & Viewing Links (Clips / Reels) -->
<div id="fresh-prog-links-popover" class="modal-overlay" style="display: none; z-index: 10200; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="modal-card" style="max-width: 540px; width: 92%; border-radius: 16px; padding: 0; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.25);">
        <div style="padding: 18px 22px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; background: var(--bg-card-elevated);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span id="popover-icon" style="font-size: 22px;">✂️</span>
                <div>
                    <h3 id="popover-title" style="margin: 0; font-size: 15px; font-weight: 800; color: var(--text-main);">Clips for Program</h3>
                    <div id="popover-subtitle" style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">Episode # • Air Date</div>
                </div>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeLinksPopover()" style="background: transparent; border: none; font-size: 18px; cursor: pointer; color: var(--text-muted);">✕</button>
        </div>

        <!-- Links List -->
        <div style="padding: 18px 22px; max-height: 300px; overflow-y: auto;">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                Saved Links (<span id="popover-links-count">0</span>)
            </div>
            <div id="popover-links-list" style="display: flex; flex-direction: column; gap: 8px;">
                <!-- Injected by fresh_programs.js -->
            </div>
        </div>

        <!-- Add Link Form (if not locked) -->
        <div id="popover-add-section" style="padding: 14px 22px 18px; border-top: 1px solid var(--border-color); background: var(--bg-card);">
            <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                ➕ Add New Link
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <input type="url" id="popover-link-url" class="input-control" placeholder="Paste YouTube, Drive, Facebook, or Instagram link..." style="font-size: 12.5px; height: 36px; border-radius: 6px;">
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="popover-link-title" class="input-control" placeholder="Optional title or caption (e.g. Part 1, Highlight)..." style="flex: 1; font-size: 12px; height: 34px; border-radius: 6px;">
                    <button type="button" class="btn btn-primary" onclick="submitNewLink()" style="padding: 6px 16px; font-size: 12.5px; font-weight: 700; border-radius: 6px; white-space: nowrap;">
                        Save Link
                    </button>
                </div>
            </div>
        </div>
        <div id="popover-locked-notice" style="display: none; padding: 12px 22px; background: rgba(239, 68, 68, 0.08); color: #dc2626; font-size: 12px; font-weight: 600; border-top: 1px solid rgba(239, 68, 68, 0.2); text-align: center;">
            🔒 This program is completed and locked. Only HOD can add or delete links.
        </div>
    </div>
</div>

<!-- Modal: Assign / Edit Fresh Program -->
<div id="modal-assign-fresh-program" class="modal-overlay" style="display: none; z-index: 10100; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="modal-card" style="max-width: 580px; width: 92%; border-radius: 16px; padding: 0; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.25);">
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; background: var(--bg-card-elevated);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 22px;">🎬</span>
                <div>
                    <h3 id="modal-assign-title" style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">Assign Fresh Program</h3>
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Allocate television broadcast program to digital editors</p>
                </div>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeAssignProgramModal()" style="background: transparent; border: none; font-size: 18px; cursor: pointer; color: var(--text-muted);">✕</button>
        </div>

        <form id="form-assign-fresh-program" onsubmit="handleSaveProgram(event)" style="padding: 22px 24px; display: flex; flex-direction: column; gap: 14px;">
            <input type="hidden" id="assign-prog-id" value="">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 5px;">📅 Program Air Date *</label>
                    <input type="date" id="assign-prog-date" class="input-control" required style="width: 100%; font-size: 13px; height: 38px; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 5px;"># Episode Number *</label>
                    <input type="text" id="assign-prog-episode" class="input-control" placeholder="e.g. Ep 12 or Episode 45" required style="width: 100%; font-size: 13px; height: 38px; border-radius: 6px;">
                </div>
            </div>

            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 5px;">📺 Program Name *</label>
                <input type="text" id="assign-prog-name" class="input-control" placeholder="e.g. Discover Pakistan Morning, Food Trail, Wanderers..." list="program-names-list" required style="width: 100%; font-size: 13px; height: 38px; border-radius: 6px;">
                <datalist id="program-names-list">
                    <option value="Discover Pakistan Morning">
                    <option value="Food Trail">
                    <option value="Wanderers">
                    <option value="History of Lahore">
                    <option value="Flavors of Pakistan">
                    <option value="Northern Tales">
                    <option value="Wild Frontiers">
                    <option value="Discovering Heritage">
                    <option value="Culture & Colors">
                    <option value="Docu Discovery">
                </datalist>
            </div>

            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 5px;">👥 Assigned Editors (Select 1 or Multiple) *</label>
                <div id="assign-editors-container" style="max-height: 160px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px; background: var(--bg-card-elevated);">
                    <!-- Injected by fresh_programs.js with avatar pills -->
                </div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Click any editor to assign/unassign. Multiple editors can collaborate on this episode.</div>
            </div>

            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 5px;">🔗 Source Footage / Drive Link (Optional)</label>
                <input type="url" id="assign-prog-source" class="input-control" placeholder="Google Drive, NAS, or Server folder link..." style="width: 100%; font-size: 12.5px; height: 38px; border-radius: 6px;">
            </div>

            <div>
                <label style="display: block; font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 5px;">📝 Editing Instructions / Notes (Optional)</label>
                <textarea id="assign-prog-notes" class="input-control" rows="2" placeholder="Specific guidelines, sound sync instructions, promo cut notes..." style="width: 100%; font-size: 12.5px; border-radius: 6px; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px;">
                <button type="button" class="btn btn-outline" onclick="closeAssignProgramModal()" style="padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 8px;">
                    Cancel
                </button>
                <button type="submit" id="btn-save-assign" class="btn btn-primary" style="background: linear-gradient(135deg, #10b981, #059669); border: none; padding: 8px 22px; font-size: 13px; font-weight: 700; border-radius: 8px;">
                    Save Program
                </button>
            </div>
        </form>
    </div>
</div>
