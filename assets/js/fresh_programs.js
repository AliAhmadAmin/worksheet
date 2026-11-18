/**
 * Discover Pakistan UHD TV - Digital Fresh Programs Controller
 * Weekly Program Editing, Episode Breakdowns, Multi-Editor Allocation, Clip & Reel Extractors
 */

(function () {
    let currentMonth = new Date().toISOString().substring(0, 7); // 'YYYY-MM'
    let selectedDate = null;
    let selectedWeek = null; // Default to Entire Month
    let selectedEditor = 'all';
    let selectedStatus = 'all';
    let selectedProgram = 'all';
    let searchQuery = '';
    let isViewMonth = true; // DEFAULT TO ENTIRE MONTH
    let isViewAll = false;

    let allPrograms = [];
    let allProgramNames = [];
    let allEditors = [];
    let calendarCounts = {};
    let searchTimeout = null;
    let isHod = false;

    // Popover Active State
    let activePopoverProgId = null;
    let activePopoverType = 'clip';

    // Helper: Escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getTodayDateStr() {
        return new Date().toISOString().substring(0, 10);
    }
    window.getTodayDateStr = getTodayDateStr;

    function getCurrentWeekNum() {
        const todayDay = new Date().getDate();
        if (todayDay <= 7) return 1;
        if (todayDay <= 14) return 2;
        if (todayDay <= 21) return 3;
        if (todayDay <= 28) return 4;
        return 5;
    }

    function getWeekDateRange(monthStr, weekNum) {
        const [yearStr, monthNumStr] = monthStr.split('-');
        const y = parseInt(yearStr, 10);
        const m = parseInt(monthNumStr, 10);
        const totalDays = new Date(y, m, 0).getDate();

        let startDay = 1;
        let endDay = 7;

        if (weekNum === 1) {
            startDay = 1;
            endDay = 7;
        } else if (weekNum === 2) {
            startDay = 8;
            endDay = 14;
        } else if (weekNum === 3) {
            startDay = 15;
            endDay = 21;
        } else if (weekNum === 4) {
            startDay = 22;
            endDay = 28;
        } else {
            startDay = 29;
            endDay = totalDays;
        }

        const sStr = `${monthStr}-${String(startDay).padStart(2, '0')}`;
        const eStr = `${monthStr}-${String(endDay).padStart(2, '0')}`;

        return { startDay, endDay, totalDays, startDate: sStr, endDate: eStr };
    }

    function formatShortDate(dateStr) {
        if (!dateStr) return '';
        try {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
        } catch (e) {
            return dateStr;
        }
    }

    // Initialize on DOM Ready
    document.addEventListener('DOMContentLoaded', async () => {
        await loadEditorsList();
        renderWeekSelectorBar();
        await loadCalendarMonthData();
        await loadFreshPrograms();
    });

    // 1. Fetch Digital Editors List
    async function loadEditorsList() {
        try {
            const res = await fetch('api/digital_programs.php?action=get_editors');
            const data = await res.json();
            if (data.success && data.editors) {
                allEditors = data.editors;
                populateEditorFilter(allEditors);
                populateModalEditors(allEditors);
            }
        } catch (e) {
            console.error('Failed to load editors:', e);
        }
    }

    function populateEditorFilter(editors) {
        const select = document.getElementById('filter-editor');
        if (!select) return;
        let html = '<option value="all">All Editors</option>';
        editors.forEach(ed => {
            html += `<option value="${ed.id}">${escapeHtml(ed.name)}</option>`;
        });
        select.innerHTML = html;
    }

    function populateModalEditors(editors, selectedIds = []) {
        const container = document.getElementById('assign-editors-container');
        if (!container) return;

        if (editors.length === 0) {
            container.innerHTML = '<div style="color: var(--text-muted); font-size: 12px; padding: 10px;">No editors found.</div>';
            return;
        }

        let html = '';
        editors.forEach(ed => {
            const isChecked = selectedIds.includes(parseInt(ed.id, 10));
            const initial = (ed.name || 'E').substring(0, 1).toUpperCase();
            const avatarHtml = ed.avatar
                ? `<img src="${escapeHtml(ed.avatar)}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">`
                : `<div style="width: 24px; height: 24px; border-radius: 50%; background: #3b82f6; color: #fff; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center;">${initial}</div>`;

            html += `
                <label class="editor-select-chip ${isChecked ? 'selected' : ''}" style="display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 8px; border: 1px solid ${isChecked ? 'var(--primary, #10b981)' : 'var(--border-color)'}; background: ${isChecked ? 'rgba(16, 185, 129, 0.08)' : 'var(--bg-card)'}; cursor: pointer; user-select: none; transition: all 0.15s ease;">
                    <input type="checkbox" name="assign_editors[]" value="${ed.id}" ${isChecked ? 'checked' : ''} onchange="toggleEditorChip(this)" style="display: none;">
                    ${avatarHtml}
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 12px; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(ed.name)}</div>
                        <div style="font-size: 10.5px; color: var(--text-muted);">${escapeHtml(ed.designation || 'Digital Editor')}</div>
                    </div>
                    <span class="chip-check" style="font-size: 14px; color: #10b981; display: ${isChecked ? 'inline' : 'none'};">✔</span>
                </label>
            `;
        });
        container.innerHTML = html;
    }

    window.toggleEditorChip = function (checkbox) {
        const chip = checkbox.closest('.editor-select-chip');
        const checkIcon = chip.querySelector('.chip-check');
        if (checkbox.checked) {
            chip.classList.add('selected');
            chip.style.borderColor = 'var(--primary, #10b981)';
            chip.style.background = 'rgba(16, 185, 129, 0.08)';
            if (checkIcon) checkIcon.style.display = 'inline';
        } else {
            chip.classList.remove('selected');
            chip.style.borderColor = 'var(--border-color)';
            chip.style.background = 'var(--bg-card)';
            if (checkIcon) checkIcon.style.display = 'none';
        }
    };

    // 2. Calendar Operations
    async function loadCalendarMonthData() {
        try {
            const res = await fetch(`api/digital_programs.php?action=calendar_counts&month=${currentMonth}`);
            const data = await res.json();
            if (data.success) {
                calendarCounts = data.days || {};
            }
        } catch (e) {
            console.error('Failed to load calendar counts:', e);
        }
        renderCalendar();
    }

    function renderWeekSelectorBar() {
        const select = document.getElementById('filter-week');
        const bar = document.getElementById('week-selector-bar');

        const [yearStr, monthNumStr] = currentMonth.split('-');
        const y = parseInt(yearStr, 10);
        const m = parseInt(monthNumStr, 10);
        const totalDays = new Date(y, m, 0).getDate();
        const monthShort = new Date(y, m - 1, 1).toLocaleString('en-US', { month: 'short' });
        const curWeek = getCurrentWeekNum();
        const isCurrentMonth = (currentMonth === new Date().toISOString().substring(0, 7));

        const maxWeeks = totalDays > 28 ? 5 : 4;

        if (select) {
            let html = '';
            if (selectedDate) {
                html += `<option value="date_${selectedDate}" selected>📍 Date: ${selectedDate}</option>`;
            }

            html += `<option value="month" ${(!selectedDate && isViewMonth) ? 'selected' : ''}>📆 Entire Month (${monthShort})</option>`;

            for (let w = 1; w <= maxWeeks; w++) {
                const range = getWeekDateRange(currentMonth, w);
                const isCur = (isCurrentMonth && curWeek === w);
                const curLabel = isCur ? ' (Current)' : '';
                const isSelected = (!selectedDate && !isViewMonth && !isViewAll && selectedWeek === w);
                html += `<option value="week_${w}" ${isSelected ? 'selected' : ''}>📅 Week ${w} (${monthShort} ${range.startDay}–${range.endDay})${curLabel}</option>`;
            }

            html += `<option value="all" ${(!selectedDate && isViewAll) ? 'selected' : ''}>⭐ All Time</option>`;

            select.innerHTML = html;
        }

        if (bar) {
            bar.innerHTML = '';
            bar.style.display = 'none';
        }
    }

    window.handleWeekDropdownChange = function (val) {
        if (!val) return;
        if (val.startsWith('week_')) {
            const w = parseInt(val.replace('week_', ''), 10);
            selectWeek(w);
        } else if (val === 'month') {
            selectCalendarMonth();
        } else if (val === 'all') {
            selectAllPrograms();
        } else if (val.startsWith('date_')) {
            const d = val.replace('date_', '');
            selectCalendarDate(d);
        }
    };

    function renderCalendar() {
        const titleEl = document.getElementById('cal-month-title');
        const gridEl = document.getElementById('cal-days-grid');
        if (!gridEl) return;

        const [yearStr, monthStr] = currentMonth.split('-');
        const year = parseInt(yearStr, 10);
        const month = parseInt(monthStr, 10) - 1; // 0-indexed

        const dateObj = new Date(year, month, 1);
        const monthName = dateObj.toLocaleString('en-US', { month: 'long', year: 'numeric' });
        if (titleEl) titleEl.textContent = monthName;

        const firstDayIndex = (dateObj.getDay() + 6) % 7; // Monday = 0
        const totalDays = new Date(year, month + 1, 0).getDate();
        const todayStr = getTodayDateStr();

        const activeWeekRange = (selectedWeek && !selectedDate && !isViewMonth && !isViewAll)
            ? getWeekDateRange(currentMonth, selectedWeek)
            : null;

        let html = '';

        // Empty padding cells for days before the 1st
        for (let i = 0; i < firstDayIndex; i++) {
            html += `<div style="padding: 6px; opacity: 0.2;"></div>`;
        }

        // Days of month
        for (let day = 1; day <= totalDays; day++) {
            const dayStr = day < 10 ? '0' + day : '' + day;
            const fullDateStr = `${currentMonth}-${dayStr}`;

            const isSelected = (!isViewMonth && !isViewAll && selectedDate === fullDateStr);
            const inActiveWeek = activeWeekRange && (day >= activeWeekRange.startDay && day <= activeWeekRange.endDay);
            const isToday = (fullDateStr === todayStr);

            const dayStats = calendarCounts[fullDateStr];
            let dotHtml = '';
            if (dayStats && dayStats.total > 0) {
                const isAllCompleted = (dayStats.completed === dayStats.total);
                const dotColor = isAllCompleted ? '#10b981' : '#f59e0b';
                dotHtml = `<span style="position: absolute; bottom: 2px; left: 50%; transform: translateX(-50%); width: 5px; height: 5px; border-radius: 50%; background: ${dotColor};"></span>`;
            }

            let bgStyle = 'transparent';
            let textColor = 'var(--text-main)';
            let borderStyle = '1px solid transparent';

            if (isSelected) {
                bgStyle = 'linear-gradient(135deg, #10b981, #059669)';
                textColor = '#ffffff';
            } else if (inActiveWeek) {
                bgStyle = 'rgba(16, 185, 129, 0.14)';
                textColor = '#047857';
                borderStyle = '1px solid rgba(16, 185, 129, 0.35)';
            } else if (isToday) {
                borderStyle = '1px solid var(--primary, #10b981)';
                textColor = 'var(--primary, #10b981)';
                bgStyle = 'rgba(16, 185, 129, 0.06)';
            }

            html += `
                <div onclick="selectCalendarDate('${fullDateStr}')" style="position: relative; padding: 7px 2px; border-radius: 6px; cursor: pointer; background: ${bgStyle}; color: ${textColor}; border: ${borderStyle}; font-size: 11.5px; font-weight: ${isSelected || inActiveWeek || isToday ? '700' : '500'}; transition: all 0.15s ease;" title="${dayStats ? dayStats.total + ' programs' : 'No programs'}">
                    ${day}
                    ${dotHtml}
                </div>
            `;
        }

        gridEl.innerHTML = html;
    }

    window.changeCalendarMonth = function (offset) {
        const [y, m] = currentMonth.split('-').map(Number);
        const newDate = new Date(y, m - 1 + offset, 1);
        const newYear = newDate.getFullYear();
        const newMonth = (newDate.getMonth() + 1).toString().padStart(2, '0');
        currentMonth = `${newYear}-${newMonth}`;
        renderWeekSelectorBar();
        loadCalendarMonthData();
        loadFreshPrograms();
    };

    window.selectWeek = function (w) {
        selectedWeek = w;
        selectedDate = null;
        isViewMonth = false;
        isViewAll = false;
        renderWeekSelectorBar();
        renderCalendar();
        loadFreshPrograms();
    };

    window.selectCalendarDate = function (dateStr) {
        selectedDate = dateStr;
        selectedWeek = null;
        isViewMonth = false;
        isViewAll = false;
        renderWeekSelectorBar();
        renderCalendar();
        loadFreshPrograms();
    };

    window.selectCalendarMonth = function () {
        isViewMonth = true;
        isViewAll = false;
        selectedDate = null;
        selectedWeek = null;
        renderWeekSelectorBar();
        renderCalendar();
        loadFreshPrograms();
    };

    window.selectAllPrograms = function () {
        isViewAll = true;
        isViewMonth = false;
        selectedDate = null;
        selectedWeek = null;
        renderWeekSelectorBar();
        renderCalendar();
        loadFreshPrograms();
    };

    // 3. Load Fresh Programs Board Data
    async function loadFreshPrograms(force = false) {
        const tbody = document.getElementById('fresh-programs-tbody');
        if (!tbody) return;

        selectedEditor = document.getElementById('filter-editor')?.value || 'all';
        selectedStatus = document.getElementById('filter-status')?.value || 'all';
        selectedProgram = document.getElementById('filter-program')?.value || 'all';

        let url = `api/digital_programs.php?action=list&status=${encodeURIComponent(selectedStatus)}`;
        if (selectedProgram !== 'all') {
            url += `&program_name=${encodeURIComponent(selectedProgram)}`;
        }

        if (isViewAll) {
            updateDateBadge('⭐ All Programs (All Time)');
        } else if (isViewMonth) {
            url += `&month=${encodeURIComponent(currentMonth)}`;
            const [y, m] = currentMonth.split('-');
            const mName = new Date(parseInt(y, 10), parseInt(m, 10) - 1, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' });
            updateDateBadge(`📆 Entire Month: ${mName}`);
        } else if (selectedWeek) {
            const range = getWeekDateRange(currentMonth, selectedWeek);
            url += `&date_from=${encodeURIComponent(range.startDate)}&date_to=${encodeURIComponent(range.endDate)}`;
            updateDateBadge(`📅 Week ${selectedWeek} (${formatShortDate(range.startDate)} – ${formatShortDate(range.endDate)})`);
        } else {
            const d = selectedDate || getTodayDateStr();
            url += `&date=${encodeURIComponent(d)}`;
            updateDateBadge(`📅 ${formatFriendlyDate(d)}`);
        }

        if (selectedEditor !== 'all') {
            url += `&editor_id=${encodeURIComponent(selectedEditor)}`;
        }
        if (searchQuery) {
            url += `&search=${encodeURIComponent(searchQuery)}`;
        }

        try {
            const res = await fetch(url);
            const data = await res.json();
            if (!data.success) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #dc2626; padding: 30px;">${escapeHtml(data.message || 'Error loading programs')}</td></tr>`;
                return;
            }

            allPrograms = data.programs || [];
            isHod = !!data.is_hod;
            if (data.program_names) {
                allProgramNames = data.program_names;
                populateProgramFilters(allProgramNames);
            }
            updateKpiStats(data.stats || {});
            renderProgramsTable(allPrograms);
        } catch (e) {
            console.error('Failed to load fresh programs:', e);
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: #dc2626; padding: 30px;">Network error loading programs. Please retry.</td></tr>`;
        }
    }
    window.loadFreshPrograms = loadFreshPrograms;

    function populateProgramFilters(names) {
        if (!Array.isArray(names)) return;

        // 1. Top Toolbar Program Filter
        const filterSelect = document.getElementById('filter-program');
        if (filterSelect) {
            const curVal = selectedProgram || filterSelect.value || 'all';
            let html = '<option value="all">All Programs</option>';
            names.forEach(n => {
                const isSel = (n === curVal) ? 'selected' : '';
                html += `<option value="${escapeHtml(n)}" ${isSel}>${escapeHtml(n)}</option>`;
            });
            filterSelect.innerHTML = html;
        }

        // 2. Assign Program Modal Auto-Complete Datalist
        const datalist = document.getElementById('program-names-list');
        if (datalist) {
            let dlHtml = '';
            names.forEach(n => {
                dlHtml += `<option value="${escapeHtml(n)}">`;
            });
            datalist.innerHTML = dlHtml;
        }
    }

    function formatFriendlyDate(dateStr) {
        if (!dateStr) return '';
        try {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-US', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        } catch (e) {
            return dateStr;
        }
    }

    function updateDateBadge(text) {
        const badge = document.getElementById('active-date-badge');
        if (badge) badge.textContent = text;
    }

    function updateKpiStats(stats) {
        document.getElementById('kpi-prog-total').textContent = stats.total || 0;
        document.getElementById('kpi-prog-pending').textContent = stats.pending || 0;
        document.getElementById('kpi-prog-inprogress').textContent = stats.in_progress || 0;
        document.getElementById('kpi-prog-completed').textContent = stats.completed || 0;
        document.getElementById('kpi-prog-clips').textContent = stats.clips || 0;
        document.getElementById('kpi-prog-reels').textContent = stats.reels || 0;

        const countLabel = document.getElementById('active-count-label');
        if (countLabel) countLabel.textContent = `${stats.total || 0} programs found`;

        // Highlight the active status card
        document.querySelectorAll('.fresh-kpi-card').forEach(c => c.classList.remove('active-card'));
        if (selectedStatus === 'pending') document.getElementById('card-kpi-pending')?.classList.add('active-card');
        else if (selectedStatus === 'in_progress') document.getElementById('card-kpi-inprogress')?.classList.add('active-card');
        else if (selectedStatus === 'completed') document.getElementById('card-kpi-completed')?.classList.add('active-card');
        else document.getElementById('card-kpi-total')?.classList.add('active-card');
    }

    // 4. Render Table (Grouped by Day like Google Sheet)
    function renderProgramsTable(programs) {
        const tbody = document.getElementById('fresh-programs-tbody');
        if (!tbody) return;

        if (programs.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                        <div style="font-size: 32px; margin-bottom: 8px;">🎬</div>
                        <div style="font-size: 14px; font-weight: 700; color: var(--text-main);">No fresh programs found</div>
                        <div style="font-size: 12px; margin-top: 4px;">Click "+ Assign New Program" to schedule an episode for this week.</div>
                    </td>
                </tr>
            `;
            return;
        }

        // Group programs by program_date
        const groups = {};
        programs.forEach(prog => {
            const d = prog.program_date;
            if (!groups[d]) groups[d] = [];
            groups[d].push(prog);
        });

        const sortedDates = Object.keys(groups).sort();
        let html = '';
        let currentRenderedWeek = null;

        sortedDates.forEach(dateStr => {
            const dateObj = new Date(dateStr + 'T00:00:00');
            const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
            const friendlyDate = dateObj.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
            const dayPrograms = groups[dateStr];

            // Calculate week number of the month (1-7 = Week 1, 8-14 = Week 2, 15-21 = Week 3, 22-28 = Week 4, 29+ = Week 5)
            const dayNum = dateObj.getDate();
            const weekNum = Math.floor((dayNum - 1) / 7) + 1;
            const ym = dateStr.substring(0, 7);
            const weekKey = `${ym}_w${weekNum}`;

            // Week Heading: Displayed ONCE above all days belonging to that week
            if (weekKey !== currentRenderedWeek) {
                currentRenderedWeek = weekKey;
                const weekRange = getWeekDateRange(ym, weekNum);
                const monthShort = dateObj.toLocaleString('en-US', { month: 'short' });
                const yearVal = dateObj.getFullYear();

                html += `
                    <tr class="week-section-header-row">
                        <td colspan="7" class="week-section-header" style="background: #ecfdf5 !important; color: #065f46 !important; padding: 10px 16px; font-weight: 800; font-size: 13px; letter-spacing: 0.8px; border-left: 5px solid #10b981 !important; border-top: 1px solid #d1fae5; border-bottom: 1px solid #d1fae5;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 14px;">📅</span>
                                    <span style="letter-spacing: 1px; color: #047857;">WEEK ${weekNum}</span>
                                    <span style="font-size: 11.5px; font-weight: 600; color: #065f46; opacity: 0.85; text-transform: none;">(${monthShort} ${weekRange.startDay} – ${weekRange.endDay}, ${yearVal})</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            }

            // Render each program row directly under its week heading
            dayPrograms.forEach((prog, idx) => {
                html += renderSingleProgramRow(prog, dayName, idx);
            });
        });

        tbody.innerHTML = html;
    }

    function renderSingleProgramRow(prog, dayName, idx) {
        const pid = prog.id;
        const isLocked = (prog.is_locked === 1);
        const canManage = isHod;
        const canWork = prog.can_work;

        // Date Display
        const dateStr = formatFriendlyDate(prog.program_date);

        // Row styling based on status (Newsroom aesthetic)
        let rowBg = idx % 2 === 0 ? 'transparent' : 'rgba(0,0,0,0.015)';
        let rowBorderLeft = '4px solid #f59e0b';
        if (prog.status === 'completed') {
            rowBg = 'rgba(16, 185, 129, 0.04)';
            rowBorderLeft = '4px solid #10b981';
        } else if (prog.status === 'in_progress') {
            rowBg = 'rgba(139, 92, 246, 0.04)';
            rowBorderLeft = '4px solid #8b5cf6';
        }

        // Instructions Button right after Episode #
        const instructionsIconHtml = prog.notes
            ? `<button type="button" onclick="showProgramInstructions(${pid})" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; color: #d97706; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 4px; padding: 2px 7px; cursor: pointer; transition: all 0.15s ease;" title="Click to read instructions from HOD">📝 Instructions</button>`
            : '';

        const driveLinkHtml = prog.source_url
            ? `<a href="${escapeHtml(prog.source_url)}" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: #2563eb; background: rgba(37, 99, 235, 0.08); border: 1px solid rgba(37, 99, 235, 0.25); border-radius: 4px; padding: 2px 7px; text-decoration: none;" title="Open Source Drive Footage">📁 Drive ↗</a>`
            : '';

        // Assigned Editors (Show only name, no avatar/initial icon)
        let editorsHtml = '';
        if (prog.editors && prog.editors.length > 0) {
            const names = prog.editors.map(ed => escapeHtml(ed.name)).join(', ');
            editorsHtml = `<span style="font-weight: 600; font-size: 12.5px; color: var(--text-main);">${names}</span>`;
        } else {
            editorsHtml = '<span style="color: var(--text-muted); font-size: 11.5px; font-style: italic;">Unassigned</span>';
        }

        // Status Dropdown / Badge (Newsroom Matrix Style)
        let statusHtml = '';
        if (isLocked && !canManage) {
            statusHtml = `
                <span class="news-status-pill" style="background: rgba(16, 185, 129, 0.12) !important; color: #059669 !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; min-width: 110px; justify-content: center; font-size: 11.5px; font-weight: 700; border-radius: 20px !important; padding: 4px 12px !important;">
                    🔒 Completed
                </span>
            `;
        } else {
            let sBg = 'rgba(245, 158, 11, 0.12)';
            let sCol = '#d97706';
            let sBor = '1px solid rgba(245, 158, 11, 0.35)';
            if (prog.status === 'completed') {
                sBg = 'rgba(16, 185, 129, 0.12)';
                sCol = '#059669';
                sBor = '1px solid rgba(16, 185, 129, 0.35)';
            } else if (prog.status === 'in_progress') {
                sBg = 'rgba(139, 92, 246, 0.12)';
                sCol = '#7c3aed';
                sBor = '1px solid rgba(139, 92, 246, 0.35)';
            }

            statusHtml = `
                <select class="news-status-select" style="background: ${sBg} !important; color: ${sCol} !important; border: ${sBor} !important; min-width: 115px; height: 30px; font-size: 11.5px; font-weight: 700; border-radius: 20px !important; padding: 4px 12px !important;" onchange="handleStatusChange(${pid}, this.value)" title="Change Status">
                    <option value="pending" ${prog.status === 'pending' ? 'selected' : ''}>⏳ Pending</option>
                    <option value="in_progress" ${prog.status === 'in_progress' ? 'selected' : ''}>⚡ In Progress</option>
                    <option value="completed" ${prog.status === 'completed' ? 'selected' : ''}>🔒 Completed</option>
                </select>
            `;
        }

        // Clips Column (Exact Count of Added Links)
        const clipsCount = prog.clips_count || 0;
        let clipsBtnHtml = '';
        if (clipsCount === 0) {
            clipsBtnHtml = `
                <button type="button" class="btn btn-outline" onclick="openLinksPopover(${pid}, 'clip')" style="padding: 4px 10px; font-size: 11px; font-weight: 600; border-radius: 20px; border-style: dashed; color: #ec4899; border-color: rgba(236, 72, 153, 0.4);" title="Click to add clip links">
                    ➕ Add Link
                </button>
            `;
        } else {
            clipsBtnHtml = `
                <button type="button" class="btn btn-outline" onclick="openLinksPopover(${pid}, 'clip')" style="padding: 4px 12px; font-size: 11.5px; font-weight: 700; border-radius: 20px; background: rgba(236, 72, 153, 0.1); color: #ec4899; border-color: rgba(236, 72, 153, 0.3);" title="Click to view/add clip links">
                    ✂️ ${clipsCount} ${clipsCount === 1 ? 'Clip' : 'Clips'}
                </button>
            `;
        }

        // Reels Column (Exact Count of Added Links)
        const reelsCount = prog.reels_count || 0;
        let reelsBtnHtml = '';
        if (reelsCount === 0) {
            reelsBtnHtml = `
                <button type="button" class="btn btn-outline" onclick="openLinksPopover(${pid}, 'reel')" style="padding: 4px 10px; font-size: 11px; font-weight: 600; border-radius: 20px; border-style: dashed; color: #06b6d4; border-color: rgba(6, 182, 212, 0.4);" title="Click to add reel links">
                    ➕ Add Link
                </button>
            `;
        } else {
            reelsBtnHtml = `
                <button type="button" class="btn btn-outline" onclick="openLinksPopover(${pid}, 'reel')" style="padding: 4px 12px; font-size: 11.5px; font-weight: 700; border-radius: 20px; background: rgba(6, 182, 212, 0.1); color: #0891b2; border-color: rgba(6, 182, 212, 0.3);" title="Click to view/add reel links">
                    📱 ${reelsCount} ${reelsCount === 1 ? 'Reel' : 'Reels'}
                </button>
            `;
        }

        // Actions Column
        let actionsHtml = '<div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center;">';
        if (canManage) {
            actionsHtml += `
                <button type="button" class="btn btn-outline" onclick="toggleProgramLock(${pid}, ${isLocked ? 0 : 1})" style="padding: 4px 7px; font-size: 11.5px; border-radius: 6px;" title="${isLocked ? 'Unlock program for editors' : 'Lock program'}">
                    ${isLocked ? '🔓' : '🔒'}
                </button>
                <button type="button" class="btn btn-outline" onclick="openEditProgramModal(${pid})" style="padding: 4px 7px; font-size: 11.5px; border-radius: 6px;" title="Edit program details & assignees">
                    ✏️
                </button>
                <button type="button" class="btn btn-outline" onclick="deleteProgram(${pid})" style="padding: 4px 7px; font-size: 11.5px; border-radius: 6px; color: #dc2626;" title="Delete program">
                    🗑️
                </button>
            `;
        } else {
            actionsHtml += `
                <span style="font-size: 11px; color: var(--text-muted);">${isLocked ? '🔒 Locked' : 'Active'}</span>
            `;
        }
        actionsHtml += '</div>';

        return `
            <tr id="prog-row-${pid}" style="border-bottom: 1px solid var(--border-color); background: ${rowBg}; border-left: ${rowBorderLeft}; transition: background 0.15s ease;">
                <td style="padding: 10px 12px; white-space: nowrap; font-weight: 600; color: var(--text-muted); font-size: 11.5px;">
                    ${dateStr}
                </td>
                <td style="padding: 10px 12px;">
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                        <span style="font-weight: 800; font-size: 13.5px; color: var(--text-main); line-height: 1.4;">
                            ${escapeHtml(prog.program_name)}
                        </span>
                        <span style="background: rgba(59, 130, 246, 0.12); color: #2563eb; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 4px; border: 1px solid rgba(59, 130, 246, 0.25); white-space: nowrap;">
                            Ep ${escapeHtml(prog.episode_no)}
                        </span>
                        ${instructionsIconHtml}
                        ${driveLinkHtml}
                    </div>
                </td>
                <td style="padding: 10px 12px;">
                    ${editorsHtml}
                </td>
                <td style="padding: 10px 8px; text-align: center;">
                    ${statusHtml}
                </td>
                <td style="padding: 10px 8px; text-align: center;">
                    <div id="prog-clips-wrap-${pid}">${clipsBtnHtml}</div>
                </td>
                <td style="padding: 10px 8px; text-align: center;">
                    <div id="prog-reels-wrap-${pid}">${reelsBtnHtml}</div>
                </td>
                <td style="padding: 10px 12px; text-align: right;">
                    ${actionsHtml}
                </td>
            </tr>
        `;
    }

    // 5. Status Update
    window.handleStatusChange = async function (progId, newStatus) {
        try {
            const res = await fetch('api/digital_programs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_status', id: progId, status: newStatus })
            });
            const data = await res.json();
            if (data.success) {
                if (data.rollover) {
                    alert(`Episode Completed! 🎉\n\nNext week's episode (${data.rollover.program_name} - ${data.rollover.episode_no}) has been automatically scheduled for ${data.rollover.date} (${data.rollover.day}) with the same editors.`);
                }
                await loadCalendarMonthData();
                await loadFreshPrograms();
            } else {
                alert(data.message || 'Failed to update status.');
                loadFreshPrograms();
            }
        } catch (e) {
            console.error('Status update error:', e);
            alert('Network error updating status.');
            loadFreshPrograms();
        }
    };

    // 6. Lock / Unlock Toggle
    window.toggleProgramLock = async function (progId, newLockVal) {
        try {
            const res = await fetch('api/digital_programs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_lock', id: progId, is_locked: newLockVal })
            });
            const data = await res.json();
            if (data.success) {
                loadFreshPrograms();
            } else {
                alert(data.message || 'Error updating lock.');
            }
        } catch (e) {
            console.error('Lock toggle error:', e);
        }
    };

    // 7. Delete Program
    window.deleteProgram = async function (progId) {
        if (!confirm('Are you sure you want to delete this program and all its linked clips/reels?')) {
            return;
        }

        try {
            const res = await fetch('api/digital_programs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_program', id: progId })
            });
            const data = await res.json();
            if (data.success) {
                await loadCalendarMonthData();
                await loadFreshPrograms();
            } else {
                alert(data.message || 'Failed to delete program.');
            }
        } catch (e) {
            console.error('Delete program error:', e);
        }
    };

    // 8. Clips / Reels Links Popover Modal
    window.openLinksPopover = function (progId, type) {
        activePopoverProgId = progId;
        activePopoverType = type;

        const prog = allPrograms.find(p => p.id === progId);
        if (!prog) return;

        const popover = document.getElementById('fresh-prog-links-popover');
        const iconEl = document.getElementById('popover-icon');
        const titleEl = document.getElementById('popover-title');
        const subtitleEl = document.getElementById('popover-subtitle');
        const addSec = document.getElementById('popover-add-section');
        const lockedNotice = document.getElementById('popover-locked-notice');

        if (type === 'clip') {
            iconEl.textContent = '✂️';
            titleEl.textContent = `Clips for ${prog.program_name}`;
        } else {
            iconEl.textContent = '📱';
            titleEl.textContent = `Reels for ${prog.program_name}`;
        }

        subtitleEl.textContent = `${prog.episode_no} • Air Date: ${formatFriendlyDate(prog.program_date)}`;

        const isLocked = (prog.is_locked === 1);
        if (isLocked && !isHod) {
            if (addSec) addSec.style.display = 'none';
            if (lockedNotice) lockedNotice.style.display = 'block';
        } else {
            if (addSec) addSec.style.display = 'block';
            if (lockedNotice) lockedNotice.style.display = 'none';
        }

        renderPopoverLinks(prog, type);

        document.getElementById('popover-link-url').value = '';
        document.getElementById('popover-link-title').value = '';

        if (popover) {
            popover.classList.add('active');
            popover.style.display = 'flex';
        }
        document.body.classList.add('modal-open');
    };

    window.closeLinksPopover = function () {
        const popover = document.getElementById('fresh-prog-links-popover');
        if (popover) {
            popover.classList.remove('active');
            popover.style.display = 'none';
        }
        document.body.classList.remove('modal-open');
        activePopoverProgId = null;
    };

    function renderPopoverLinks(prog, type) {
        const listEl = document.getElementById('popover-links-list');
        const countEl = document.getElementById('popover-links-count');
        if (!listEl) return;

        const links = (type === 'clip' ? prog.clips : prog.reels) || [];
        if (countEl) countEl.textContent = links.length;

        if (links.length === 0) {
            listEl.innerHTML = `
                <div style="text-align: center; padding: 24px 10px; color: var(--text-muted); font-size: 12px;">
                    No ${type === 'clip' ? 'clip' : 'reel'} links added yet. Paste a link below!
                </div>
            `;
            return;
        }

        const isLocked = (prog.is_locked === 1);
        let html = '';
        links.forEach(l => {
            const canDelete = isHod || (!isLocked);
            html += `
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px;">
                    <div style="flex: 1; min-width: 0;">
                        <a href="${escapeHtml(l.url)}" target="_blank" rel="noopener noreferrer" style="font-weight: 700; font-size: 12.5px; color: #2563eb; text-decoration: none; display: flex; align-items: center; gap: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <span>🔗</span>
                            <span>${escapeHtml(l.title || l.url)}</span>
                            <span style="font-size: 10px; opacity: 0.7;">↗</span>
                        </a>
                        <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;">
                            Added by ${escapeHtml(l.added_by_name || 'Editor')}
                        </div>
                    </div>
                    ${canDelete ? `
                        <button type="button" onclick="deleteLink(${l.id})" class="btn btn-outline" style="padding: 3px 6px; font-size: 11px; border-radius: 6px; color: #dc2626;" title="Remove link">
                            🗑️
                        </button>
                    ` : ''}
                </div>
            `;
        });

        listEl.innerHTML = html;
    }

    // 9. Submit New Clip / Reel Link
    window.submitNewLink = async function () {
        if (!activePopoverProgId) return;

        const urlInput = document.getElementById('popover-link-url');
        const titleInput = document.getElementById('popover-link-title');
        const url = urlInput?.value.trim();
        const title = titleInput?.value.trim();

        if (!url) {
            alert('Please paste a valid URL.');
            urlInput?.focus();
            return;
        }

        try {
            const res = await fetch('api/digital_programs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_link',
                    program_id: activePopoverProgId,
                    link_type: activePopoverType,
                    url: url,
                    title: title
                })
            });
            const data = await res.json();
            if (data.success) {
                // Update local memory and DOM instantly!
                const prog = allPrograms.find(p => p.id === activePopoverProgId);
                if (prog) {
                    if (activePopoverType === 'clip') {
                        prog.clips = data.links;
                        prog.clips_count = data.count;
                        updateRowBadge(prog.id, 'clip', data.count);
                    } else {
                        prog.reels = data.links;
                        prog.reels_count = data.count;
                        updateRowBadge(prog.id, 'reel', data.count);
                    }
                    renderPopoverLinks(prog, activePopoverType);
                }

                urlInput.value = '';
                titleInput.value = '';

                // Update top stats
                loadFreshPrograms();
            } else {
                alert(data.message || 'Failed to add link.');
            }
        } catch (e) {
            console.error('Add link error:', e);
            alert('Network error saving link.');
        }
    };

    // 10. Delete Link
    window.deleteLink = async function (linkId) {
        if (!confirm('Are you sure you want to remove this link?')) return;

        try {
            const res = await fetch('api/digital_programs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_link', link_id: linkId })
            });
            const data = await res.json();
            if (data.success) {
                const prog = allPrograms.find(p => p.id === activePopoverProgId);
                if (prog) {
                    if (activePopoverType === 'clip') {
                        prog.clips = prog.clips.filter(l => l.id !== linkId);
                        prog.clips_count = data.count;
                        updateRowBadge(prog.id, 'clip', data.count);
                    } else {
                        prog.reels = prog.reels.filter(l => l.id !== linkId);
                        prog.reels_count = data.count;
                        updateRowBadge(prog.id, 'reel', data.count);
                    }
                    renderPopoverLinks(prog, activePopoverType);
                }
                loadFreshPrograms();
            } else {
                alert(data.message || 'Failed to delete link.');
            }
        } catch (e) {
            console.error('Delete link error:', e);
        }
    };

    function updateRowBadge(progId, type, count) {
        if (type === 'clip') {
            const wrap = document.getElementById(`prog-clips-wrap-${progId}`);
            if (!wrap) return;
            if (count === 0) {
                wrap.innerHTML = `
                    <button type="button" class="btn btn-outline" onclick="openLinksPopover(${progId}, 'clip')" style="padding: 4px 10px; font-size: 11px; font-weight: 600; border-radius: 20px; border-style: dashed; color: #ec4899; border-color: rgba(236, 72, 153, 0.4);" title="Click to add clip links">
                        ➕ Add Link
                    </button>
                `;
            } else {
                wrap.innerHTML = `
                    <button type="button" class="btn btn-outline" onclick="openLinksPopover(${progId}, 'clip')" style="padding: 4px 12px; font-size: 11.5px; font-weight: 700; border-radius: 20px; background: rgba(236, 72, 153, 0.1); color: #ec4899; border-color: rgba(236, 72, 153, 0.3);" title="Click to view/add clip links">
                        ✂️ ${count} ${count === 1 ? 'Clip' : 'Clips'}
                    </button>
                `;
            }
        } else {
            const wrap = document.getElementById(`prog-reels-wrap-${progId}`);
            if (!wrap) return;
            if (count === 0) {
                wrap.innerHTML = `
                    <button type="button" class="btn btn-outline" onclick="openLinksPopover(${progId}, 'reel')" style="padding: 4px 10px; font-size: 11px; font-weight: 600; border-radius: 20px; border-style: dashed; color: #06b6d4; border-color: rgba(6, 182, 212, 0.4);" title="Click to add reel links">
                        ➕ Add Link
                    </button>
                `;
            } else {
                wrap.innerHTML = `
                    <button type="button" class="btn btn-outline" onclick="openLinksPopover(${progId}, 'reel')" style="padding: 4px 12px; font-size: 11.5px; font-weight: 700; border-radius: 20px; background: rgba(6, 182, 212, 0.1); color: #0891b2; border-color: rgba(6, 182, 212, 0.3);" title="Click to view/add reel links">
                        📱 ${count} ${count === 1 ? 'Reel' : 'Reels'}
                    </button>
                `;
            }
        }
    }

    // 11. Assign / Edit Program Modal
    window.openAssignProgramModal = function () {
        document.getElementById('assign-prog-id').value = '';
        document.getElementById('modal-assign-title').textContent = 'Assign Fresh Program';
        document.getElementById('assign-prog-date').value = selectedDate || getTodayDateStr();
        document.getElementById('assign-prog-name').value = '';
        document.getElementById('assign-prog-episode').value = '';
        document.getElementById('assign-prog-source').value = '';
        document.getElementById('assign-prog-notes').value = '';
        const recCheckbox = document.getElementById('assign-prog-recurring');
        if (recCheckbox) recCheckbox.checked = true;

        populateModalEditors(allEditors, []);

        const modal = document.getElementById('modal-assign-fresh-program');
        if (modal) {
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
        document.body.classList.add('modal-open');
    };

    window.openEditProgramModal = function (progId) {
        const prog = allPrograms.find(p => p.id === progId);
        if (!prog) return;

        document.getElementById('assign-prog-id').value = prog.id;
        document.getElementById('modal-assign-title').textContent = 'Edit Fresh Program';
        document.getElementById('assign-prog-date').value = prog.program_date;
        document.getElementById('assign-prog-name').value = prog.program_name;
        document.getElementById('assign-prog-episode').value = prog.episode_no;
        document.getElementById('assign-prog-source').value = prog.source_url || '';
        document.getElementById('assign-prog-notes').value = prog.notes || '';
        const recCheckbox = document.getElementById('assign-prog-recurring');
        if (recCheckbox) recCheckbox.checked = ((prog.is_recurring ?? 1) !== 0);

        const selectedIds = (prog.editors || []).map(e => e.id);
        populateModalEditors(allEditors, selectedIds);

        const modal = document.getElementById('modal-assign-fresh-program');
        if (modal) {
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
        document.body.classList.add('modal-open');
    };

    window.closeAssignProgramModal = function () {
        const modal = document.getElementById('modal-assign-fresh-program');
        if (modal) {
            modal.classList.remove('active');
            modal.style.display = 'none';
        }
        document.body.classList.remove('modal-open');
    };

    window.handleSaveProgram = async function (e) {
        e.preventDefault();

        const id = document.getElementById('assign-prog-id').value;
        const progDate = document.getElementById('assign-prog-date').value;
        const progName = document.getElementById('assign-prog-name').value.trim();
        const episodeNo = document.getElementById('assign-prog-episode').value.trim();
        const sourceUrl = document.getElementById('assign-prog-source').value.trim();
        const notes = document.getElementById('assign-prog-notes').value.trim();
        const isRecurring = document.getElementById('assign-prog-recurring')?.checked ? 1 : 0;

        const checkedEditors = Array.from(document.querySelectorAll('input[name="assign_editors[]"]:checked'))
            .map(cb => parseInt(cb.value, 10));

        if (checkedEditors.length === 0) {
            alert('Please select at least one editor for this program.');
            return;
        }

        const submitBtn = document.getElementById('btn-save-assign');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';

        try {
            const res = await fetch('api/digital_programs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_program',
                    id: id ? parseInt(id, 10) : 0,
                    program_date: progDate,
                    program_name: progName,
                    episode_no: episodeNo,
                    source_url: sourceUrl,
                    notes: notes,
                    is_recurring: isRecurring,
                    editor_ids: checkedEditors
                })
            });
            const data = await res.json();
            if (data.success) {
                closeAssignProgramModal();
                selectedDate = progDate;
                isViewMonth = false;
                isViewAll = false;
                await loadCalendarMonthData();
                await loadFreshPrograms();
            } else {
                alert(data.message || 'Failed to save program.');
            }
        } catch (e) {
            console.error('Save program error:', e);
            alert('Network error saving program.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Program';
        }
    };

    // Auto-Advance Week Rollover for HOD
    window.triggerWeekRollover = async function () {
        let range = null;
        if (selectedWeek) {
            range = getWeekDateRange(currentMonth, selectedWeek);
        } else if (selectedDate) {
            const d = new Date(selectedDate + 'T00:00:00');
            const w = Math.floor((d.getDate() - 1) / 7) + 1;
            range = getWeekDateRange(selectedDate.substring(0, 7), w);
        } else {
            const curW = getCurrentWeekNum();
            range = getWeekDateRange(currentMonth, curW);
        }

        if (!range) {
            alert('Please select a specific week first to roll over its schedule.');
            return;
        }

        const confirmMsg = `Auto-Advance Week Schedule:\n\nDo you want to roll over all active standing programs from:\nWeek range (${formatFriendlyDate(range.startDate)} to ${formatFriendlyDate(range.endDate)})\ninto next week (+7 days) with Episode numbers auto-incremented by 1?`;
        if (!confirm(confirmMsg)) {
            return;
        }

        try {
            const res = await fetch('api/digital_programs.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'rollover_week',
                    date_from: range.startDate,
                    date_to: range.endDate
                })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                await loadCalendarMonthData();
                await loadFreshPrograms();
            } else {
                alert(data.message || 'Failed to auto-advance week schedule.');
            }
        } catch (e) {
            console.error('Week rollover error:', e);
            alert('Network error rolling over week schedule.');
        }
    };

    // 12. Search & Filter Helpers
    window.debounceSearch = function () {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchQuery = document.getElementById('filter-search')?.value.trim() || '';
            loadFreshPrograms();
        }, 300);
    };

    window.filterByKpi = function (status) {
        const filterStatus = document.getElementById('filter-status');
        if (filterStatus) {
            filterStatus.value = status;
            loadFreshPrograms();
        }
    };

    window.resetAllFilters = function () {
        const searchEl = document.getElementById('filter-search');
        if (searchEl) searchEl.value = '';
        const statusEl = document.getElementById('filter-status');
        if (statusEl) statusEl.value = 'all';
        const progEl = document.getElementById('filter-program');
        if (progEl) progEl.value = 'all';
        const editorEl = document.getElementById('filter-editor');
        if (editorEl) editorEl.value = 'all';
        searchQuery = '';
        selectedEditor = 'all';
        selectedStatus = 'all';
        selectedProgram = 'all';
        selectedDate = null;
        selectedWeek = null;
        isViewMonth = true; // DEFAULT TO ENTIRE MONTH
        isViewAll = false;
        renderWeekSelectorBar();
        renderCalendar();
        loadFreshPrograms();
    };

    // Instructions Modal
    window.showProgramInstructions = function (progId) {
        const prog = allPrograms.find(p => p.id === progId);
        if (!prog) return;

        document.getElementById('instr-modal-title').textContent = `${prog.program_name}`;
        document.getElementById('instr-modal-subtitle').textContent = `Episode: ${prog.episode_no} • Air Date: ${formatFriendlyDate(prog.program_date)}`;
        document.getElementById('instr-modal-body').textContent = prog.notes || 'No specific instructions provided.';

        const modal = document.getElementById('modal-program-instructions');
        if (modal) {
            modal.classList.add('active');
            modal.style.display = 'flex';
        }
        document.body.classList.add('modal-open');
    };

    window.closeInstructionsModal = function () {
        const modal = document.getElementById('modal-program-instructions');
        if (modal) {
            modal.classList.remove('active');
            modal.style.display = 'none';
        }
        document.body.classList.remove('modal-open');
    };

    // Close modals on clicking backdrop
    document.addEventListener('mousedown', (e) => {
        const assignModal = document.getElementById('modal-assign-fresh-program');
        if (assignModal && e.target === assignModal) {
            closeAssignProgramModal();
        }
        const popoverModal = document.getElementById('fresh-prog-links-popover');
        if (popoverModal && e.target === popoverModal) {
            closeLinksPopover();
        }
        const instrModal = document.getElementById('modal-program-instructions');
        if (instrModal && e.target === instrModal) {
            closeInstructionsModal();
        }
    });
})();
