/**
 * Employee Quality & Mistakes Management System
 * Discover Pakistan UHD TV Timesheet & Operations Portal
 */

let currentMistakeEmpId = null;
let currentMistakeEmpName = '';
let currentMistakeMonth = '';
let currentMistakeYear = '';

/**
 * Open Modal to Log a New Mistake (Row-Level or Sheet-Level)
 */
function openAddMistakeModal(entryId = null, targetEmpId = null, targetDate = null) {
    const modal = document.getElementById('modal-log-mistake');
    if (!modal) return;

    // Resolve employee and date
    let empId = targetEmpId;
    let empName = '';
    let sheetDate = targetDate;

    if (!empId && typeof AppState !== 'undefined') {
        empId = AppState.viewingEmployeeId || (AppState.currentUser ? AppState.currentUser.id : null);
        empName = AppState.viewingEmployeeName || (AppState.currentUser ? AppState.currentUser.name : '');
        sheetDate = AppState.currentDate || new Date().toISOString().split('T')[0];
    }

    if (!empName && typeof currentReportEmpName !== 'undefined') {
        empName = currentReportEmpName;
    }

    document.getElementById('mistake-input-emp-id').value = empId || '';
    document.getElementById('mistake-display-emp-name').textContent = empName || 'Employee';
    document.getElementById('mistake-input-date').value = sheetDate || new Date().toISOString().split('T')[0];
    document.getElementById('mistake-input-entry-id').value = entryId || '';

    // Task preview if row-level
    const taskPreviewWrap = document.getElementById('mistake-task-preview-wrap');
    const taskPreviewText = document.getElementById('mistake-task-preview-text');
    if (entryId && typeof AppState !== 'undefined' && Array.isArray(AppState.currentEntries)) {
        const found = AppState.currentEntries.find(e => e.id == entryId);
        if (found) {
            taskPreviewText.textContent = `${found.time_slot || ''} • [${found.content_type || 'Task'}] ${found.title || found.description || 'Entry'}`;
            taskPreviewWrap.style.display = 'block';
        } else {
            taskPreviewWrap.style.display = 'none';
        }
    } else {
        taskPreviewWrap.style.display = 'none';
    }

    // Reset inputs
    document.getElementById('mistake-input-title').value = '';
    document.getElementById('mistake-input-desc').value = '';
    document.getElementById('mistake-input-category').selectedIndex = 0;
    
    // Default severity to 'minor'
    setMistakeSeverity('minor');

    modal.classList.add('active');
    setTimeout(() => {
        const titleInput = document.getElementById('mistake-input-title');
        if (titleInput) titleInput.focus();
    }, 150);
}

function closeAddMistakeModal() {
    const modal = document.getElementById('modal-log-mistake');
    if (modal) modal.classList.remove('active');
}

function setMistakeSeverity(sev) {
    document.getElementById('mistake-input-severity').value = sev;
    document.querySelectorAll('.btn-mistake-sev').forEach(btn => {
        if (btn.dataset.sev === sev) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
}

/**
 * Submit New Mistake to Backend
 */
async function submitMistakeForm() {
    const empId = document.getElementById('mistake-input-emp-id').value;
    const mistakeDate = document.getElementById('mistake-input-date').value;
    const category = document.getElementById('mistake-input-category').value;
    const severity = document.getElementById('mistake-input-severity').value;
    const title = document.getElementById('mistake-input-title').value.trim();
    const description = document.getElementById('mistake-input-desc').value.trim();
    const entryId = document.getElementById('mistake-input-entry-id').value || null;
    const sheetId = (typeof AppState !== 'undefined' && AppState.currentSheet) ? AppState.currentSheet.id : null;

    if (!empId) {
        showToast("Please select an employee.", "warning");
        return;
    }
    if (!title) {
        showToast("Please provide a short summary of the mistake.", "warning");
        document.getElementById('mistake-input-title').focus();
        return;
    }

    const saveBtn = document.getElementById('btn-save-mistake');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span>⏳</span> Saving...';
    }

    try {
        const res = await fetch('api/mistakes.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                employee_id: empId,
                mistake_date: mistakeDate,
                category: category,
                severity: severity,
                title: title,
                description: description,
                entry_id: entryId,
                sheet_id: sheetId
            })
        });
        const json = await res.json();

        if (json.success) {
            showToast("Mistake successfully logged.", "success");
            closeAddMistakeModal();

            // Refresh Worksheet if active
            if (typeof loadDailySheet === 'function' && typeof AppState !== 'undefined' && AppState.viewingEmployeeId) {
                loadDailySheet(AppState.currentDate, AppState.viewingEmployeeId);
            }
            // Refresh Reports Matrix if active
            if (typeof fetchMatrixData === 'function') {
                fetchMatrixData();
            }
            // Refresh Attendance Report if active
            if (typeof loadDetailedAttendanceReport === 'function') {
                loadDetailedAttendanceReport();
            }
        } else {
            showToast(json.message || "Failed to log mistake.", "error");
        }
    } catch (e) {
        showToast("Network error while logging mistake.", "error");
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<span>💾</span> Save Mistake';
        }
    }
}

/**
 * Open View Mistakes History Modal
 */
async function openMistakesModal(empId = null, empName = null, filterMonth = null, filterYear = null, startDate = null, endDate = null) {
    const modal = document.getElementById('modal-view-mistakes');
    if (!modal) return;

    if (!empId && typeof AppState !== 'undefined') {
        empId = AppState.viewingEmployeeId || (AppState.currentUser ? AppState.currentUser.id : null);
        empName = AppState.viewingEmployeeName || (AppState.currentUser ? AppState.currentUser.name : '');
    }

    if (!empId) return;

    currentMistakeEmpId = empId;
    currentMistakeEmpName = empName || 'Employee';

    const now = new Date();
    currentMistakeMonth = filterMonth || (typeof AppState !== 'undefined' && AppState.currentDate ? AppState.currentDate.substring(0, 7) : `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
    currentMistakeYear = filterYear || currentMistakeMonth.substring(0, 4);

    document.getElementById('view-mistakes-emp-name').textContent = currentMistakeEmpName;
    document.getElementById('view-mistakes-month-label').textContent = formatMonthDisplay(currentMistakeMonth);
    document.getElementById('view-mistakes-year-label').textContent = currentMistakeYear;

    modal.classList.add('active');

    loadMistakesList(empId, currentMistakeMonth, currentMistakeYear, startDate, endDate);
}

function closeMistakesModal() {
    const modal = document.getElementById('modal-view-mistakes');
    if (modal) modal.classList.remove('active');
}

/**
 * Load Mistakes List and Summary from Backend
 */
async function loadMistakesList(empId, month, year, startDate = null, endDate = null) {
    const tbody = document.getElementById('view-mistakes-tbody');
    const loading = document.getElementById('view-mistakes-loading');
    const emptyState = document.getElementById('view-mistakes-empty');
    if (tbody) tbody.innerHTML = '';
    if (loading) loading.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';

    try {
        let listUrl = `api/mistakes.php?action=list&employee_id=${empId}`;
        if (startDate && endDate) {
            listUrl += `&start_date=${startDate}&end_date=${endDate}`;
        } else if (month) {
            listUrl += `&month=${month}`;
        }

        const [listRes, sumRes] = await Promise.all([
            fetch(listUrl).then(r => r.json()),
            fetch(`api/mistakes.php?action=summary&employee_id=${empId}&month=${month}&year=${year}`).then(r => r.json())
        ]);

        if (loading) loading.style.display = 'none';

        // Update Summary Cards
        if (sumRes.success) {
            document.getElementById('stat-mistakes-month').textContent = sumRes.count_month || 0;
            document.getElementById('stat-mistakes-year').textContent = sumRes.count_year || 0;

            const sev = sumRes.by_severity || {};
            document.getElementById('stat-mistakes-critical').textContent = sev.critical || 0;
            document.getElementById('stat-mistakes-moderate').textContent = sev.moderate || 0;
            document.getElementById('stat-mistakes-minor').textContent = sev.minor || 0;
        }

        const mistakes = (listRes.success && Array.isArray(listRes.mistakes)) ? listRes.mistakes : [];

        if (mistakes.length === 0) {
            if (emptyState) emptyState.style.display = 'block';
            return;
        }

        if (tbody) {
            tbody.innerHTML = mistakes.map(m => {
                const sevBadge = m.severity === 'critical'
                    ? '<span class="badge" style="background:#fee2e2; color:#dc2626; font-weight:700;">🔴 Critical</span>'
                    : (m.severity === 'moderate'
                        ? '<span class="badge" style="background:#ffedd5; color:#c2410c; font-weight:700;">🟠 Moderate</span>'
                        : '<span class="badge" style="background:#fef3c7; color:#b45309; font-weight:700;">🟡 Minor</span>');

                const taskInfo = m.entry_title 
                    ? `<div style="font-size:11.5px; color:var(--text-muted); margin-top:3px;">Task: <strong>${escapeHtml(m.entry_time_slot || '')}</strong> [${escapeHtml(m.entry_content_type || '')}] ${escapeHtml(m.entry_title)}</div>`
                    : '';

                return `
                    <tr>
                        <td style="white-space: nowrap; font-weight:600; font-size:12.5px;">${m.mistake_date}</td>
                        <td>${sevBadge}</td>
                        <td style="font-size:12.5px; font-weight:600;">${escapeHtml(m.category)}</td>
                        <td>
                            <div style="font-weight:700; font-size:13px; color:var(--text-main);">${escapeHtml(m.title)}</div>
                            ${m.description ? `<div style="font-size:12px; color:var(--text-muted); margin-top:2px; line-height:1.4;">${escapeHtml(m.description)}</div>` : ''}
                            ${taskInfo}
                        </td>
                        <td style="font-size:12px; color:var(--text-muted);">${escapeHtml(m.logged_by_name || 'System')}</td>
                        <td style="text-align: center;">
                            <button type="button" class="btn-icon" style="color:#ef4444;" onclick="deleteMistakeRecord(${m.id})" title="Delete mistake record">🗑️</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }
    } catch (e) {
        if (loading) loading.style.display = 'none';
        showToast("Failed to load mistakes record.", "error");
    }
}

/**
 * Delete a Mistake Record
 */
async function deleteMistakeRecord(id) {
    if (!confirm("Are you sure you want to remove this mistake record?")) return;

    try {
        const res = await fetch('api/mistakes.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const json = await res.json();

        if (json.success) {
            showToast("Mistake record removed.", "success");
            loadMistakesList(currentMistakeEmpId, currentMistakeMonth, currentMistakeYear);

            if (typeof loadDailySheet === 'function' && typeof AppState !== 'undefined' && AppState.viewingEmployeeId) {
                loadDailySheet(AppState.currentDate, AppState.viewingEmployeeId);
            }
            if (typeof fetchMatrixData === 'function') {
                fetchMatrixData();
            }
            if (typeof loadDetailedAttendanceReport === 'function') {
                loadDetailedAttendanceReport();
            }
        } else {
            showToast(json.message || "Failed to remove mistake record.", "error");
        }
    } catch (e) {
        showToast("Network error while removing mistake.", "error");
    }
}

function formatMonthDisplay(ym) {
    if (!ym) return '';
    const parts = ym.split('-');
    if (parts.length < 2) return ym;
    const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
    return d.toLocaleString('default', { month: 'long', year: 'numeric' });
}
