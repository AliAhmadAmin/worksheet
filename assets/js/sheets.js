/**
 * Hourly Worksheet Logic: Dynamic Rows, Check-in/out, Lock Enforcement & Print Trigger
 */

const defaultContentTypes = ['YT Videos', 'FB Videos', 'Reel', 'Post Card', 'Podcast'];
const defaultDepartments = ['News Room', 'Digital', 'Programming', 'Documentary', 'Others'];

const defaultTimeSlots = [
    '07:00 AM to 08:00 AM',
    '07:30 AM to 09:00 AM',
    '08:00 AM to 09:00 AM',
    '09:00 AM to 10:00 AM',
    '09:00 AM to 10:30 AM',
    '10:00 AM to 11:30 AM',
    '11:00 AM to 12:00 PM',
    '11:30 AM to 01:00 PM',
    '11:30 AM to 01:30 PM',
    '01:00 PM to 02:00 PM',
    '01:30 PM to 03:00 PM',
    '02:00 PM to 03:00 PM',
    '03:00 PM to 04:00 PM',
    '03:00 PM to 04:30 PM',
    '04:00 PM to 05:00 PM',
    '04:30 PM to 06:00 PM',
    '05:00 PM to 06:00 PM',
    '06:00 PM to 07:00 PM',
    '06:00 PM to 07:30 PM',
    '07:00 PM to 08:00 PM',
    '07:30 PM to 09:00 PM',
    '08:00 PM to 09:00 PM',
    '08:00 PM to 09:30 PM',
    '09:00 PM to 10:00 PM',
    '09:00 PM to 10:30 PM',
    '10:00 PM to 11:00 PM',
    '10:30 PM to 12:00 AM',
    '11:00 PM to 12:00 AM',
    '12:00 AM to 01:30 AM',
    '01:30 AM to 03:00 AM',
    '03:00 AM to 04:30 AM',
    '04:30 AM to 06:00 AM',
    '06:00 AM to 07:30 AM'
];

function getActiveWorksheetEmpId() {
    if (AppState.currentUser && AppState.currentUser.role === 'admin') {
        return AppState.adminSelectedEmpId || 2;
    }
    return AppState.currentUser ? AppState.currentUser.id : 2;
}

async function loadDailyWorksheet() {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;

    // Keep date picker inputs in sync
    document.querySelectorAll('.worksheet-date-input').forEach(input => {
        if (input.value !== date) input.value = date;
    });

    try {
        const res = await fetch(`api/sheets.php?action=get_sheet&employee_id=${empId}&date=${date}`);
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || "Failed to load worksheet", "error");
            return;
        }

        AppState.currentSheet = data.sheet;
        AppState.currentEntries = data.entries || [];
        AppState.isLocked = data.is_locked;

        renderWorksheetHero(data.sheet, data.employee);
        renderWorksheetTable(data.entries, data.can_edit);
        renderWorksheetSummary(data.sheet);
        updateLockBadge(data.is_locked, data.can_edit);

    } catch (err) {
        console.error("Error loading worksheet:", err);
        showToast("Error retrieving worksheet data.", "error");
    }
}

function renderWorksheetHero(sheet, empData) {
    const isAdmin = AppState.currentUser && AppState.currentUser.role === 'admin';

    if (isAdmin && empData) {
        // Update Admin Hero Inspector View
        const avatarEl = document.getElementById('admin-viewed-emp-avatar');
        const desigEl = document.getElementById('admin-meta-designation');
        const deptEl = document.getElementById('admin-meta-dept');
        const teamEl = document.getElementById('admin-meta-team');
        const inEl = document.getElementById('admin-meta-in');
        const outEl = document.getElementById('admin-meta-out');
        const dutyHoursEl = document.getElementById('admin-emp-duty-hours');
        const select = document.getElementById('admin-employee-select');

        if (avatarEl) {
            if (empData.avatar) {
                avatarEl.innerHTML = `<img src="${escapeHtml(empData.avatar)}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" alt="${escapeHtml(empData.name)}" onerror="this.onerror=null; this.parentElement.textContent='${escapeHtml(empData.name ? empData.name.charAt(0).toUpperCase() : '👤')}';">`;
            } else {
                avatarEl.textContent = empData.name ? empData.name.charAt(0).toUpperCase() : '👤';
            }
        }

        if (desigEl) desigEl.textContent = empData.designation || 'Staff';
        if (deptEl) deptEl.textContent = empData.department_name || 'Digital';
        if (teamEl) teamEl.textContent = empData.team_name || 'General';
        if (inEl) inEl.textContent = sheet?.check_in_time || '--:--';
        if (outEl) outEl.textContent = sheet?.check_out_time || '--:--';
        if (dutyHoursEl) dutyHoursEl.textContent = sheet?.total_duty_hours || '0:00:00';
        if (select && AppState.adminSelectedEmpId) {
            select.value = AppState.adminSelectedEmpId;
        }

        const selectedNameSpan = document.getElementById('admin-emp-dropdown-selected-name');
        if (selectedNameSpan && empData) {
            selectedNameSpan.textContent = empData.name;
        }

        // Update Admin Check-in / Check-out button states
        const btnAdminCheckIn = document.getElementById('admin-btn-check-in');
        const btnAdminCheckOut = document.getElementById('admin-btn-check-out');
        const checkIn = sheet ? sheet.check_in_time : null;
        const checkOut = sheet ? sheet.check_out_time : null;

        if (btnAdminCheckIn) {
            if (checkIn) {
                btnAdminCheckIn.textContent = `🟢 In (${checkIn})`;
                btnAdminCheckIn.title = `Employee checked in at ${checkIn}. Click to re-record or adjust.`;
                btnAdminCheckIn.className = 'btn btn-success';
            } else {
                btnAdminCheckIn.textContent = '🟢 Check In';
                btnAdminCheckIn.title = 'Record Check-In for this employee';
                btnAdminCheckIn.className = 'btn btn-success';
            }
        }

        if (btnAdminCheckOut) {
            if (checkOut) {
                btnAdminCheckOut.textContent = `🔴 Out (${checkOut})`;
                btnAdminCheckOut.title = `Employee checked out at ${checkOut}. Click to re-record or adjust.`;
                btnAdminCheckOut.className = 'btn btn-outline';
            } else if (checkIn) {
                btnAdminCheckOut.textContent = '🔴 Check Out';
                btnAdminCheckOut.title = 'Record Check-Out and lock sheet for this employee';
                btnAdminCheckOut.className = 'btn btn-danger';
            } else {
                btnAdminCheckOut.textContent = '🔴 Check Out';
                btnAdminCheckOut.title = 'Record Check-Out for this employee';
                btnAdminCheckOut.className = 'btn btn-outline';
            }
        }

    } else {
        // Update Employee Personal Shift Hero
        const checkInTimeEl = document.getElementById('hero-checkin-time');
        const checkOutTimeEl = document.getElementById('hero-checkout-time');
        const btnCheckIn = document.getElementById('btn-check-in');
        const btnCheckOut = document.getElementById('btn-check-out');
        const nextDayBanner = document.getElementById('hero-next-day-bar');
        const shiftDateLabel = document.getElementById('hero-shift-date-label');

        if (shiftDateLabel) {
            shiftDateLabel.textContent = AppState.selectedDate;
        }

        const checkIn = sheet ? sheet.check_in_time : null;
        const checkOut = sheet ? sheet.check_out_time : null;

        if (checkInTimeEl) checkInTimeEl.textContent = checkIn || '--:--';
        if (checkOutTimeEl) checkOutTimeEl.textContent = checkOut || '--:--';

        // Date status calculation
        const todayStr = getLocalDateString();
        const isToday = (AppState.selectedDate === todayStr);
        const isFuture = (AppState.selectedDate > todayStr);
        const isPast = (AppState.selectedDate < todayStr);
        const isAdmin = AppState.currentUser && AppState.currentUser.role === 'admin';

        const timerElem = document.getElementById('duty-timer-digits');

        if (isFuture && !isAdmin) {
            // Future dates: completely disable live check-in/out
            stopLiveTimer();
            if (timerElem) timerElem.textContent = '0:00:00';
            if (btnCheckIn) {
                btnCheckIn.disabled = true;
                btnCheckIn.title = "Live check-in is not allowed for future dates.";
            }
            if (btnCheckOut) {
                btnCheckOut.disabled = true;
                btnCheckOut.title = "";
            }
            if (nextDayBanner) nextDayBanner.style.display = 'none';
        } else if (isPast && !isAdmin) {
            // Past dates: show completed duty time if exists, disable live check in
            stopLiveTimer();
            if (timerElem) timerElem.textContent = sheet ? (sheet.total_duty_hours || '0:00:00') : '0:00:00';
            if (btnCheckIn) {
                btnCheckIn.disabled = true;
                btnCheckIn.title = "Past date live check-in is closed.";
            }
            if (btnCheckOut) {
                btnCheckOut.disabled = true;
                btnCheckOut.title = "";
            }
            if (nextDayBanner) nextDayBanner.style.display = 'none';
        } else {
            // Today (or Admin override)
            if (checkIn && !checkOut) {
                // Active duty today
                const inDate = new Date(`${AppState.selectedDate} ${checkIn}`);
                const now = new Date();
                const diffSecs = isNaN(inDate.getTime()) ? 0 : Math.max(0, Math.floor((now - inDate) / 1000));
                startLiveTimer(diffSecs);

                if (btnCheckIn) {
                    btnCheckIn.disabled = true;
                    btnCheckIn.title = "Currently active on duty.";
                }
                if (btnCheckOut) {
                    btnCheckOut.disabled = false;
                    btnCheckOut.title = "Click to finish shift and submit sheet";
                }
                if (nextDayBanner) nextDayBanner.style.display = 'none';
            } else if (checkIn && checkOut) {
                // Completed duty today
                stopLiveTimer();
                if (timerElem) timerElem.textContent = sheet.total_duty_hours || '0:00:00';

                if (btnCheckIn) {
                    btnCheckIn.disabled = true;
                    btnCheckIn.title = "Shift completed for today.";
                }
                if (btnCheckOut) {
                    btnCheckOut.disabled = true;
                }
                if (nextDayBanner) nextDayBanner.style.display = 'flex';
            } else {
                // Fresh day today: Ready to check in
                stopLiveTimer();
                if (timerElem) timerElem.textContent = '0:00:00';

                if (btnCheckIn) {
                    btnCheckIn.disabled = false;
                    btnCheckIn.title = "Click to Check In for today";
                }
                if (btnCheckOut) {
                    btnCheckOut.disabled = true;
                }
                if (nextDayBanner) nextDayBanner.style.display = 'none';
            }
        }
    }
}

function updateLockBadge(isLocked, canEdit) {
    const adminLockBadge = document.getElementById('sheet-lock-badge');
    const empLockBadge = document.getElementById('employee-sheet-lock-badge');
    const adminLockCtrl = document.getElementById('admin-unlock-btn');

    const badgeText = isLocked ? '🔒 Locked (Checked Out)' : '🔓 Active / Unlocked';
    const badgeClass = isLocked ? 'badge-lock locked' : 'badge-lock unlocked';

    if (adminLockBadge) {
        adminLockBadge.className = badgeClass;
        adminLockBadge.innerHTML = badgeText;
    }
    if (empLockBadge) {
        empLockBadge.className = badgeClass;
        empLockBadge.innerHTML = badgeText;
    }

    if (adminLockCtrl) {
        adminLockCtrl.innerHTML = isLocked ? '🔓 Unlock Sheet' : '🔒 Lock Sheet';
        adminLockCtrl.className = isLocked ? 'btn btn-success' : 'btn btn-outline';
    }

    // Save button stays enabled so employees can paste/save links even when sheet is locked!
    const btnSave = document.getElementById('btn-save-sheet');
    if (btnSave) {
        btnSave.disabled = false;
        if (isLocked && AppState.currentUser?.role !== 'admin') {
            btnSave.innerHTML = '💾 Save Links / Sheet';
            btnSave.title = 'Save your added links to this locked sheet';
        } else {
            btnSave.innerHTML = '💾 Save Sheet';
            btnSave.title = 'Save all entries';
        }
    }

    const btnAddRow = document.getElementById('btn-add-row');
    const btnAddRowBottom = document.getElementById('btn-add-row-bottom');
    if (btnAddRow) {
        btnAddRow.disabled = !canEdit;
    }
    if (btnAddRowBottom) {
        btnAddRowBottom.disabled = !canEdit;
    }
}

function renderWorksheetTable(entries, canEdit) {
    const tbody = document.getElementById('worksheet-table-body');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!entries || entries.length === 0) {
        if (canEdit) {
            addTableRow({ time_slot: '', content_type: '', department: '', link: '', title: '' }, canEdit);
            addTableRow({ time_slot: '', content_type: '', department: '', link: '', title: '' }, canEdit);
            addTableRow({ time_slot: '', content_type: '', department: '', link: '', title: '' }, canEdit);
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">No entries logged for this date.</td></tr>';
        }
    } else {
        entries.forEach(entry => {
            addTableRow(entry, canEdit);
        });
    }

    setupWorksheetAutoSaveListeners();
}

function addTableRow(data = {}, canEdit = true, focusNew = false) {
    const tbody = document.getElementById('worksheet-table-body');
    if (!tbody) return;

    const row = document.createElement('tr');
    row.className = 'sheet-entry-row';

    const timeVal = data.time_slot || '';
    const selectedType = data.content_type || '';
    const selectedDept = data.department || '';
    const rawLink = data.link || '';
    const linkVal = (rawLink === 'upload') ? '' : rawLink;
    const titleVal = data.title || '';

    // Generate 12-Hour Time Slot options with default unselected prompt
    let timeOptions = `<option value="" ${!timeVal ? 'selected' : ''}>-- Select Time Slot --</option>`;
    const isCustomTime = timeVal && !defaultTimeSlots.includes(timeVal);
    if (isCustomTime) {
        timeOptions += `<option value="${escapeHtml(timeVal)}" selected>${escapeHtml(timeVal)}</option>`;
    }
    timeOptions += defaultTimeSlots.map(t => 
        `<option value="${t}" ${t === timeVal ? 'selected' : ''}>${t}</option>`
    ).join('');

    // Generate Content Type options with default unselected prompt
    let typeOptions = `<option value="" ${!selectedType ? 'selected' : ''}>-- Select Content Type --</option>`;
    typeOptions += defaultContentTypes.map(t => 
        `<option value="${t}" ${t === selectedType ? 'selected' : ''}>${t}</option>`
    ).join('');

    // Generate Department options with default unselected prompt
    let deptOptions = `<option value="" ${!selectedDept ? 'selected' : ''}>-- Select Department --</option>`;
    deptOptions += defaultDepartments.map(d => 
        `<option value="${d}" ${d === selectedDept ? 'selected' : ''}>${d}</option>`
    ).join('');

    const disabledAttr = canEdit ? '' : 'disabled';

    row.innerHTML = `
        <td class="col-time-slot">
            <select class="select-time input-control" style="width: 100%; font-weight: 600;" ${disabledAttr}>
                ${timeOptions}
            </select>
        </td>
        <td class="col-content-type">
            <select class="select-content-type input-control" style="width: 100%;" ${disabledAttr}>
                ${typeOptions}
            </select>
        </td>
        <td class="col-department">
            <select class="select-dept input-control" style="width: 100%;" ${disabledAttr}>
                ${deptOptions}
            </select>
        </td>
        <td class="col-link">
            <input type="text" class="input-link input-control" placeholder="Paste link or drive URL..." value="${escapeHtml(linkVal)}" style="width: 100%;">
        </td>
        <td class="col-description">
            <input type="text" class="input-title input-control" placeholder="Description of task/video" value="${escapeHtml(titleVal)}" style="width: 100%;" ${disabledAttr}>
        </td>
        <td class="col-action" style="text-align: center;">
            ${canEdit ? '<button type="button" class="btn-icon-del" onclick="removeTableRow(this)" title="Delete Row">🗑️</button>' : ''}
        </td>
    `;

    tbody.appendChild(row);

    if (focusNew) {
        setTimeout(() => {
            const focusTarget = row.querySelector('.select-time') || row.querySelector('.input-title');
            if (focusTarget) {
                focusTarget.focus();
                focusTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 50);
    }
}

// Add a new row directly below the last row
function addNewRowBelow() {
    const tbody = document.getElementById('worksheet-table-body');
    if (!tbody) return;

    const isAdmin = AppState.currentUser && AppState.currentUser.role === 'admin';
    const canEdit = isAdmin || !AppState.isLocked;

    if (!canEdit) {
        showToast("Worksheet is locked. Admin can unlock it.", "error");
        return;
    }

    addTableRow({
        time_slot: '',
        content_type: '',
        department: '',
        link: '',
        title: ''
    }, canEdit, true);

    // Auto-save the new row immediately
    triggerAutoSave(300);
}

function removeTableRow(btn) {
    const row = btn.closest('tr');
    if (row) {
        row.remove();
        triggerAutoSave(200);
    }
}

// Auto-Save Management & Indicators
let autoSaveTimer = null;

function updateSaveIndicator(status, text) {
    const iconEl = document.getElementById('save-indicator-icon');
    const textEl = document.getElementById('save-indicator-text');
    if (!textEl) return;

    if (status === 'saving') {
        if (iconEl) iconEl.textContent = '💾';
        textEl.textContent = text || 'Saving changes...';
        textEl.style.color = 'var(--primary)';
    } else if (status === 'saved') {
        if (iconEl) iconEl.textContent = '✅';
        textEl.textContent = text || `Auto-saved at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
        textEl.style.color = '#10b981';
    } else if (status === 'error') {
        if (iconEl) iconEl.textContent = '⚠️';
        textEl.textContent = text || 'Failed to auto-save';
        textEl.style.color = '#ef4444';
    } else {
        if (iconEl) iconEl.textContent = '☁️';
        textEl.textContent = text || 'Auto-save ready';
        textEl.style.color = 'var(--text-muted)';
    }
}

function triggerAutoSave(delayMs = 700) {
    updateSaveIndicator('saving', 'Saving changes...');
    if (autoSaveTimer) clearTimeout(autoSaveTimer);

    autoSaveTimer = setTimeout(async () => {
        try {
            await saveCurrentWorksheet(false, true);
        } catch (e) {
            updateSaveIndicator('error', 'Auto-save failed');
        }
    }, delayMs);
}

function setupWorksheetAutoSaveListeners() {
    const tbody = document.getElementById('worksheet-table-body');
    if (tbody && !tbody._hasAutoSaveListeners) {
        tbody._hasAutoSaveListeners = true;

        tbody.addEventListener('input', (e) => {
            if (e.target.matches('.input-link, .input-title')) {
                triggerAutoSave(700);
            }
        });

        tbody.addEventListener('change', (e) => {
            if (e.target.matches('.select-time, .select-content-type, .select-dept')) {
                triggerAutoSave(200);
            }
        });
    }

    const summaryInput = document.getElementById('worksheet-work-summary');
    const remarksInput = document.getElementById('worksheet-remarks');

    if (summaryInput && !summaryInput._hasAutoSave) {
        summaryInput._hasAutoSave = true;
        summaryInput.addEventListener('input', () => triggerAutoSave(800));
    }
    if (remarksInput && !remarksInput._hasAutoSave) {
        remarksInput._hasAutoSave = true;
        remarksInput.addEventListener('input', () => triggerAutoSave(800));
    }
}

function renderWorksheetSummary(sheet) {
    const summaryInput = document.getElementById('worksheet-work-summary');
    const remarksInput = document.getElementById('worksheet-remarks');

    if (summaryInput) summaryInput.value = sheet ? (sheet.work_summary || '') : '';
    if (remarksInput) remarksInput.value = sheet ? (sheet.remarks || '') : '';
}

// Employee Check-In Action
async function handleCheckIn() {
    const todayStr = getLocalDateString();
    if (AppState.selectedDate !== todayStr && AppState.currentUser?.role !== 'admin') {
        showToast(`Check-In is only permitted on Today's date (${todayStr}).`, "error");
        return;
    }

    const empId = AppState.currentUser ? AppState.currentUser.id : 1;
    const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    try {
        const res = await fetch('api/attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'check_in',
                employee_id: empId,
                date: AppState.selectedDate,
                time: nowTime
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            await loadDailyWorksheet();
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast("Check-in failed.", "error");
    }
}

// Employee Check-Out Action
async function handleCheckOut() {
    const todayStr = getLocalDateString();
    if (AppState.selectedDate !== todayStr && AppState.currentUser?.role !== 'admin') {
        showToast(`Check-Out is only permitted on Today's date (${todayStr}).`, "error");
        return;
    }

    if (!confirm("Are you sure you want to Check Out? Once you check out, your sheet will be LOCKED and only Admin can unlock or edit it.")) {
        return;
    }

    const empId = AppState.currentUser ? AppState.currentUser.id : 1;
    const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    // First auto-save current rows
    await saveCurrentWorksheet(false, true);

    try {
        const res = await fetch('api/attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'check_out',
                employee_id: empId,
                date: AppState.selectedDate,
                time: nowTime
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast("Checked out successfully! Sheet is locked. Next day check-in will automatically appear tomorrow.", 'success');
            await loadDailyWorksheet();
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast("Check-out failed.", "error");
    }
}

// Admin Direct Check-In Action for Selected Employee
async function handleAdminCheckIn() {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const empName = document.getElementById('admin-emp-dropdown-selected-name')?.textContent || 'Employee';

    // If already checked in, give choice to update now or open shift editor
    if (AppState.currentSheet && AppState.currentSheet.check_in_time) {
        if (!confirm(`${empName} is currently recorded as checked in at ${AppState.currentSheet.check_in_time}.\n\nDo you want to update the check-in time to NOW (${nowTime})?\n(Click Cancel to open the custom Shift Editor instead)`)) {
            openAdminShiftModal();
            return;
        }
    }

    try {
        const res = await fetch('api/attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'check_in',
                employee_id: empId,
                date: date,
                time: nowTime
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || `Checked in ${empName} successfully at ${nowTime}`, 'success');
            await loadDailyWorksheet();
            if (typeof loadLiveAttendance === 'function') loadLiveAttendance();
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast("Admin check-in failed.", "error");
    }
}

// Admin Direct Check-Out Action for Selected Employee
async function handleAdminCheckOut() {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const empName = document.getElementById('admin-emp-dropdown-selected-name')?.textContent || 'Employee';

    if (!AppState.currentSheet || !AppState.currentSheet.check_in_time) {
        // No check-in record: offer to open shift editor or auto check-in
        if (confirm(`${empName} has not checked in yet for ${date}.\n\nWould you like to open the Shift Editor to specify both Check-In and Check-Out times?`)) {
            openAdminShiftModal();
            return;
        }
    }

    if (!confirm(`Check out ${empName} for ${date} at ${nowTime}?\nThis will calculate total duty hours and lock the worksheet.`)) {
        return;
    }

    try {
        const res = await fetch('api/attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'check_out',
                employee_id: empId,
                date: date,
                time: nowTime
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || `Checked out ${empName} successfully.`, 'success');
            await loadDailyWorksheet();
            if (typeof loadLiveAttendance === 'function') loadLiveAttendance();
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast("Admin check-out failed.", "error");
    }
}

// Open Detailed Shift & Duty Modal for Admin
function openAdminShiftModal() {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    const empName = document.getElementById('admin-emp-dropdown-selected-name')?.textContent || 'Employee';
    const desig = document.getElementById('admin-meta-designation')?.textContent || 'Staff';
    const dept = document.getElementById('admin-meta-dept')?.textContent || 'Digital';

    const empIdInput = document.getElementById('admin-shift-emp-id');
    const dateInput = document.getElementById('admin-shift-date');
    const nameLabel = document.getElementById('admin-shift-modal-emp-name');
    const metaLabel = document.getElementById('admin-shift-modal-meta');
    const dateLabel = document.getElementById('admin-shift-modal-date-label');
    const avatarEl = document.getElementById('admin-shift-modal-avatar');

    if (empIdInput) empIdInput.value = empId;
    if (dateInput) dateInput.value = date;
    if (nameLabel) nameLabel.textContent = empName;
    if (metaLabel) metaLabel.textContent = `${desig} • ${dept}`;
    if (dateLabel) dateLabel.textContent = date;

    const currentIn = AppState.currentSheet ? (AppState.currentSheet.check_in_time || '') : '';
    const currentOut = AppState.currentSheet ? (AppState.currentSheet.check_out_time || '') : '';
    const isLocked = AppState.currentSheet ? (parseInt(AppState.currentSheet.is_locked) === 1) : false;

    const inInput = document.getElementById('admin-shift-in-time');
    const outInput = document.getElementById('admin-shift-out-time');
    const lockCheckbox = document.getElementById('admin-shift-lock-checkbox');

    if (inInput) inInput.value = currentIn;
    if (outInput) outInput.value = currentOut;
    if (lockCheckbox) lockCheckbox.checked = isLocked;

    if (avatarEl) {
        avatarEl.textContent = empName ? empName.charAt(0).toUpperCase() : '👤';
    }

    openModal('admin-shift-modal');
}

// Preset helper for shift time inputs in modal
function setShiftTimePreset(type, val) {
    let finalVal = val;
    if (val === 'now') {
        finalVal = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    if (type === 'in') {
        const inInput = document.getElementById('admin-shift-in-time');
        if (inInput) inInput.value = finalVal;
    } else if (type === 'out') {
        const outInput = document.getElementById('admin-shift-out-time');
        if (outInput) {
            outInput.value = finalVal;
            const lockCheckbox = document.getElementById('admin-shift-lock-checkbox');
            if (lockCheckbox && finalVal) {
                lockCheckbox.checked = true;
            }
        }
    }
}

// Save Shift Form Submit for Admin
async function handleAdminSaveShiftSubmit(e) {
    if (e) e.preventDefault();
    const empId = document.getElementById('admin-shift-emp-id')?.value || getActiveWorksheetEmpId();
    const date = document.getElementById('admin-shift-date')?.value || AppState.selectedDate;
    const inTime = document.getElementById('admin-shift-in-time')?.value.trim() || '';
    const outTime = document.getElementById('admin-shift-out-time')?.value.trim() || '';
    const isLocked = document.getElementById('admin-shift-lock-checkbox')?.checked ? 1 : 0;

    try {
        const res = await fetch('api/attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'admin_set_shift',
                employee_id: empId,
                date: date,
                check_in_time: inTime,
                check_out_time: outTime,
                is_locked: isLocked
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || "Shift attendance saved successfully.", "success");
            closeModal('admin-shift-modal');
            await loadDailyWorksheet();
            if (typeof loadLiveAttendance === 'function') loadLiveAttendance();
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to save shift attendance.", "error");
    }
}

// Reset / Clear Shift for Admin
async function handleAdminClearShift() {
    const empId = document.getElementById('admin-shift-emp-id')?.value || getActiveWorksheetEmpId();
    const date = document.getElementById('admin-shift-date')?.value || AppState.selectedDate;
    const empName = document.getElementById('admin-shift-modal-emp-name')?.textContent || 'Employee';

    if (!confirm(`Are you sure you want to clear/reset the check-in and check-out records for ${empName} on ${date}?`)) {
        return;
    }

    try {
        const res = await fetch('api/attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'admin_set_shift',
                employee_id: empId,
                date: date,
                check_in_time: '',
                check_out_time: '',
                is_locked: 0
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast("Shift cleared successfully.", "info");
            closeModal('admin-shift-modal');
            await loadDailyWorksheet();
            if (typeof loadLiveAttendance === 'function') loadLiveAttendance();
        } else {
            showToast(data.message, "error");
        }
    } catch (err) {
        showToast("Failed to clear shift.", "error");
    }
}

// Save Current Worksheet Entries (Admin can save for any employee; Employee can save their own)
async function saveCurrentWorksheet(notify = true, isAuto = false) {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;

    const rows = document.querySelectorAll('#worksheet-table-body tr.sheet-entry-row');
    const entries = [];

    rows.forEach(r => {
        const timeSlot = r.querySelector('.select-time')?.value || r.querySelector('.input-time')?.value || '';
        const contentType = r.querySelector('.select-content-type')?.value || '';
        const department = r.querySelector('.select-dept')?.value || '';
        const rawLink = r.querySelector('.input-link')?.value || '';
        const link = (rawLink === 'upload') ? '' : rawLink;
        const title = r.querySelector('.input-title')?.value || '';

        if (title.trim() || timeSlot.trim() || link.trim()) {
            entries.push({
                time_slot: timeSlot,
                content_type: contentType,
                department: department,
                link: link,
                title: title,
                count_val: 1
            });
        }
    });

    const workSummary = document.getElementById('worksheet-work-summary')?.value || '';
    const remarks = document.getElementById('worksheet-remarks')?.value || '';

    try {
        const res = await fetch('api/sheets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_entries',
                employee_id: empId,
                date: date,
                entries: entries,
                work_summary: workSummary,
                remarks: remarks
            })
        });
        const data = await res.json();

        if (data.success) {
            updateSaveIndicator('saved');
            if (typeof loadReports === 'function') {
                loadReports();
            }
            if (notify) {
                showToast("Worksheet saved successfully!", "success");
                await loadDailyWorksheet();
            } else if (!isAuto) {
                await loadDailyWorksheet();
            }
        } else {
            updateSaveIndicator('error', data.message || 'Auto-save error');
            if (notify) showToast(data.message, "error");
        }
    } catch (err) {
        updateSaveIndicator('error', 'Network error on save');
        if (notify) showToast("Failed to save worksheet.", "error");
    }
}

// Admin/Supervisor Lock/Unlock Toggle for the inspected employee sheet
async function toggleSheetLock() {
    if (!hasPermission('can_unlock_sheets')) {
        showToast("You do not have permission to lock or unlock worksheets.", "error");
        return;
    }

    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    const newLockState = AppState.isLocked ? 0 : 1;

    try {
        const res = await fetch('api/sheets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_lock',
                employee_id: empId,
                date: date,
                is_locked: newLockState
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'info');
            await loadDailyWorksheet();
            if (typeof loadLiveAttendance === 'function') loadLiveAttendance();
        } else {
            showToast(data.message, 'error');
        }
    } catch (err) {
        showToast("Error updating lock status.", "error");
    }
}

// Print Current Day Sheet (Direct Print: Opens system print dialog directly)
function triggerPrintSheet() {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    const printUrl = `print.php?employee_id=${empId}&date=${date}`;

    let printIframe = document.getElementById('direct-print-frame');
    if (!printIframe) {
        printIframe = document.createElement('iframe');
        printIframe.id = 'direct-print-frame';
        printIframe.style.position = 'fixed';
        printIframe.style.right = '0';
        printIframe.style.bottom = '0';
        printIframe.style.width = '0';
        printIframe.style.height = '0';
        printIframe.style.border = 'none';
        printIframe.style.visibility = 'hidden';
        document.body.appendChild(printIframe);
    }

    printIframe.onload = function() {
        try {
            printIframe.contentWindow.focus();
            printIframe.contentWindow.print();
        } catch (err) {
            window.open(printUrl, '_blank');
        }
    };

    printIframe.src = printUrl;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
