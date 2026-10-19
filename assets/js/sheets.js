/**
 * Hourly Worksheet Logic: Dynamic Rows, Check-in/out, Lock Enforcement & Print Trigger
 */

const defaultContentTypes = ['YT Videos', 'FB Videos', 'Reel', 'Post Card', 'Podcast'];
const defaultDepartments = ['News Room', 'Digital', 'Programming', 'Documentary', 'Others'];

const defaultWorksheetColumns = [
    { id: 'c1', column_key: 'time_slot', column_label: 'Time Slot', column_type: 'time', is_core: 1, is_visible: 1, sort_order: 1 },
    { id: 'c2', column_key: 'content_type', column_label: 'Content Type', column_type: 'select', is_core: 1, is_visible: 1, sort_order: 2 },
    { id: 'c3', column_key: 'department', column_label: 'Department', column_type: 'select', is_core: 1, is_visible: 1, sort_order: 3 },
    { id: 'c4', column_key: 'link', column_label: 'Link / Upload', column_type: 'link', is_core: 1, is_visible: 1, sort_order: 4 },
    { id: 'c5', column_key: 'title', column_label: 'Work Description / Title', column_type: 'text', is_core: 1, is_visible: 1, sort_order: 5 }
];

function getVisibleWorksheetColumns() {
    if (AppState.worksheetColumns && Array.isArray(AppState.worksheetColumns) && AppState.worksheetColumns.length > 0) {
        return AppState.worksheetColumns.filter(c => parseInt(c.is_visible) === 1).sort((a, b) => parseInt(a.sort_order) - parseInt(b.sort_order));
    }
    return defaultWorksheetColumns;
}

async function loadWorksheetColumns(deptId, forceReload = false) {
    if (!deptId) {
        deptId = AppState.currentUser ? AppState.currentUser.department_id : 2;
    }
    deptId = parseInt(deptId) || 2;

    if (!forceReload && AppState.worksheetColumns && AppState.worksheetColumnsDeptId === deptId) {
        return AppState.worksheetColumns;
    }

    try {
        const res = await fetch(`api/worksheet_columns.php?action=get&all=1&department_id=${deptId}`);
        const data = await res.json();
        if (data.success) {
            AppState.worksheetColumns = data.columns || [];
            AppState.worksheetColumnsDeptId = deptId;
            AppState.worksheetColumnsCanManage = data.can_manage;
            return AppState.worksheetColumns;
        }
    } catch (err) {
        console.error("Failed to load worksheet columns:", err);
    }
    return defaultWorksheetColumns;
}


function getAvailableContentTypes() {
    if (AppState.trackingOptions && Array.isArray(AppState.trackingOptions.content_types) && AppState.trackingOptions.content_types.length > 0) {
        return AppState.trackingOptions.content_types.map(c => typeof c === 'string' ? c : c.name);
    }
    return defaultContentTypes;
}

function getAvailableTrackingDepts() {
    if (AppState.trackingOptions && Array.isArray(AppState.trackingOptions.tracking_departments) && AppState.trackingOptions.tracking_departments.length > 0) {
        return AppState.trackingOptions.tracking_departments.map(d => typeof d === 'string' ? d : d.name);
    }
    return defaultDepartments;
}

async function loadTrackingOptions(deptId, forceReload = false) {
    if (!deptId) {
        deptId = AppState.currentUser ? AppState.currentUser.department_id : 2;
    }
    deptId = parseInt(deptId) || 2;

    if (!forceReload && AppState.trackingOptions && AppState.trackingOptionsDeptId === deptId) {
        return AppState.trackingOptions;
    }

    try {
        const res = await fetch(`api/tracking_options.php?action=get&department_id=${deptId}`);
        const data = await res.json();
        if (data.success) {
            AppState.trackingOptions = {
                content_types: data.content_types || [],
                tracking_departments: data.tracking_departments || []
            };
            AppState.trackingOptionsDeptId = deptId;
            AppState.trackingOptionsCanManage = data.can_manage;
            AppState.trackingOptionsDeptName = data.department_name;
            return AppState.trackingOptions;
        }
    } catch (err) {
        console.error("Failed to load tracking options:", err);
    }
    return null;
}

function refreshWorksheetDropdowns() {
    const rows = document.querySelectorAll('#worksheet-table-body tr.sheet-entry-row');
    if (rows.length === 0) return;

    const availTypes = getAvailableContentTypes();
    const availDepts = getAvailableTrackingDepts();

    rows.forEach(row => {
        const typeSelect = row.querySelector('.select-content-type');
        const deptSelect = row.querySelector('.select-dept');

        if (typeSelect) {
            const currentVal = typeSelect.value;
            let typeOptions = `<option value="">-- Select Content Type --</option>`;
            let found = false;
            availTypes.forEach(t => {
                const isSel = (t.toLowerCase() === (currentVal || '').toLowerCase());
                if (isSel) found = true;
                typeOptions += `<option value="${escapeHtml(t)}" ${isSel ? 'selected' : ''}>${escapeHtml(t)}</option>`;
            });
            if (currentVal && !found) {
                typeOptions += `<option value="${escapeHtml(currentVal)}" selected>${escapeHtml(currentVal)} (Custom)</option>`;
            }
            typeSelect.innerHTML = typeOptions;
            if (currentVal) typeSelect.value = currentVal;
        }

        if (deptSelect) {
            const currentVal = deptSelect.value;
            let deptOptions = `<option value="">-- Select Department --</option>`;
            let found = false;
            availDepts.forEach(d => {
                const isSel = (d.toLowerCase() === (currentVal || '').toLowerCase());
                if (isSel) found = true;
                deptOptions += `<option value="${escapeHtml(d)}" ${isSel ? 'selected' : ''}>${escapeHtml(d)}</option>`;
            });
            if (currentVal && !found) {
                deptOptions += `<option value="${escapeHtml(currentVal)}" selected>${escapeHtml(currentVal)} (Custom)</option>`;
            }
            deptSelect.innerHTML = deptOptions;
            if (currentVal) deptSelect.value = currentVal;
        }
    });
}


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
    const isElevated = AppState.currentUser && (
        AppState.currentUser.role === 'admin' || 
        AppState.currentUser.role === 'super_admin' || 
        AppState.currentUser.role === 'hod' || 
        hasPermission('can_inspect_sheets')
    );
    if (isElevated && AppState.adminSelectedEmpId) {
        return AppState.adminSelectedEmpId;
    }
    return AppState.currentUser ? AppState.currentUser.id : 2;
}

async function loadDailyWorksheet(allowOvernightRedirect = true) {
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
        AppState.departmentSettings = data.department_settings || {};
        AppState.isHodChecked = data.is_hod_checked || 0;
        AppState.hodCheckedByName = data.hod_checked_by_name || null;
        AppState.hodCheckedAt = data.hod_checked_at || null;
        AppState.canPrint = data.can_print;

        const deptId = (data.employee && data.employee.department_id) ? data.employee.department_id : (AppState.currentUser?.department_id || 2);
        if (data.columns && data.columns.length > 0) {
            AppState.worksheetColumns = data.columns;
            AppState.worksheetColumnsDeptId = deptId;
        } else {
            await loadWorksheetColumns(deptId);
        }
        await loadTrackingOptions(deptId);

        renderWorksheetHero(data.sheet, data.employee, data);
        renderWorksheetTable(data.entries, data.can_edit);
        renderWorksheetSummary(data.sheet);
        updateLockBadge(data.is_locked, data.can_edit);
        renderHolidayView(data);

        // Draft Recovery Banner
        const draftBanner = document.getElementById('worksheet-draft-banner');

        if (!data.entries || data.entries.length === 0) {
            const draft = getLocalDraft(empId, date);
            if (draft && draft.entries && draft.entries.length > 0) {
                if (draftBanner) {
                    const timeStr = draft.timestamp ? new Date(draft.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'recently';
                    const timeEl = document.getElementById('draft-banner-time');
                    if (timeEl) timeEl.textContent = `${timeStr} (${draft.entries.length} unsaved rows)`;
                    draftBanner.style.display = 'flex';
                }
            } else if (draftBanner) {
                draftBanner.style.display = 'none';
            }
        } else {
            if (draftBanner) draftBanner.style.display = 'none';
            // Sync current saved entries to local cache
            saveLocalDraft();
        }

    } catch (err) {
        console.error("Error loading worksheet:", err);
        showToast("Error retrieving worksheet data.", "error");
    }
}

function renderWorksheetHero(sheet, empData, data = null) {
    const isElevated = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hod' || hasPermission('can_inspect_sheets'));

    if (isElevated && empData) {
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

        // Display scheduled roster timing alongside actual in/out
        const schedShift = data?.scheduled_shift || null;
        if (inEl) {
            inEl.textContent = sheet?.check_in_time || '--:--';
            if (schedShift) {
                inEl.title = `Scheduled Shift: ${schedShift}. Actual Punch-In: ${sheet?.check_in_time || 'Pending arrival'}`;
            }
        }
        if (outEl) outEl.textContent = sheet?.check_out_time || '--:--';

        const checkIn = sheet ? sheet.check_in_time : null;
        const checkOut = sheet ? sheet.check_out_time : null;
        const isLocked = sheet ? (parseInt(sheet.is_locked) === 1) : false;

        const todayStr = getLocalDateString();
        const prevDayStr = (typeof getPrevDayString === 'function') ? getPrevDayString(todayStr) : null;
        const isToday = (AppState.selectedDate === todayStr);
        const isYesterday = (AppState.selectedDate === prevDayStr);
        const isFuture = (AppState.selectedDate > todayStr);
        const isOvernightActive = isYesterday && checkIn && !checkOut && !isLocked;

        // Active duty live counter for inspected employee (Today or Overnight Shift)
        if ((isToday || isOvernightActive) && checkIn && !checkOut && !isLocked && !isFuture) {
            const inDate = parseDateTime(AppState.selectedDate, checkIn);
            const now = new Date();
            const diffSecs = (inDate && !isNaN(inDate.getTime())) ? Math.max(0, Math.floor((now - inDate) / 1000)) : 0;
            startLiveTimer(diffSecs, checkIn, AppState.selectedDate);
        } else {
            stopLiveTimer();
            if (dutyHoursEl) {
                dutyHoursEl.textContent = sheet?.total_duty_hours || '0:00:00';
            }
        }

        if (select && AppState.adminSelectedEmpId) {
            select.value = AppState.adminSelectedEmpId;
        }

        const selectedNameSpan = document.getElementById('admin-emp-dropdown-selected-name');
        if (selectedNameSpan && empData) {
            selectedNameSpan.textContent = empData.name;
        }

        // Update Admin Check-in / Check-out button states (Conditional & Single Source of Truth)
        const btnAdminCheckIn = document.getElementById('admin-btn-check-in');
        const btnAdminCheckOut = document.getElementById('admin-btn-check-out');
        const adminAttendanceGroup = btnAdminCheckIn ? btnAdminCheckIn.closest('.admin-ctrl-attendance') : null;

        if (checkIn && checkOut) {
            // Shift is completed: hide attendance punch buttons from top controls.
            // In & Out times are displayed exclusively in the Shift Info Bar below.
            if (btnAdminCheckIn) btnAdminCheckIn.style.display = 'none';
            if (btnAdminCheckOut) btnAdminCheckOut.style.display = 'none';
            if (adminAttendanceGroup) adminAttendanceGroup.style.display = 'none';
        } else if (checkIn && !checkOut) {
            // Shift in progress: hide Check In, show Check Out
            if (adminAttendanceGroup) adminAttendanceGroup.style.display = 'inline-flex';
            if (btnAdminCheckIn) btnAdminCheckIn.style.display = 'none';
            if (btnAdminCheckOut) {
                btnAdminCheckOut.style.display = 'inline-flex';
                btnAdminCheckOut.textContent = '🔴 Check Out';
                btnAdminCheckOut.title = 'Record Check-Out and lock sheet for this employee';
                btnAdminCheckOut.className = 'btn btn-danger';
            }
        } else {
            // Fresh day: not checked in -> show Check In, hide Check Out
            if (adminAttendanceGroup) adminAttendanceGroup.style.display = 'inline-flex';
            if (btnAdminCheckIn) {
                btnAdminCheckIn.style.display = 'inline-flex';
                btnAdminCheckIn.textContent = '🟢 Check In';
                btnAdminCheckIn.title = 'Record Check-In for this employee';
                btnAdminCheckIn.className = 'btn btn-success';
            }
            if (btnAdminCheckOut) btnAdminCheckOut.style.display = 'none';
        }

        // Update HOD Verification Button and Header Appearance in Inspector
        const btnHodCheck = document.getElementById('admin-btn-hod-check');
        const btnHodCheckText = document.getElementById('admin-btn-hod-check-text');
        const verifiedHeaderBadge = document.getElementById('admin-verified-header-badge');
        const inspectorSubtitle = document.getElementById('admin-inspector-subtitle');
        const inspectorBox = document.getElementById('admin-inspector-box');

        const isHodChecked = parseInt(sheet?.is_hod_checked || data?.is_hod_checked) === 1;
        const deptSettings = data?.department_settings || {};
        const requireCheck = parseInt(deptSettings.require_hod_sheet_check) === 1;
        const checkerName = data?.hod_checked_by_name || 'HOD';
        const checkedAt = data?.hod_checked_at || '';

        if (btnHodCheck && btnHodCheckText) {
            if (requireCheck || isHodChecked) {
                btnHodCheck.style.display = 'inline-flex';
                if (isHodChecked) {
                    btnHodCheck.className = 'btn btn-outline';
                    btnHodCheck.style.borderColor = '#10b981';
                    btnHodCheck.style.color = '#10b981';
                    btnHodCheck.innerHTML = '<span>↩️</span> <span id="admin-btn-hod-check-text">Reopen Review</span>';
                    btnHodCheck.title = `Worksheet verified by ${checkerName} on ${checkedAt}. Click to reopen review.`;
                } else {
                    btnHodCheck.className = 'btn btn-success';
                    btnHodCheck.style.borderColor = '';
                    btnHodCheck.style.color = '';
                    btnHodCheck.innerHTML = '<span>✅</span> <span id="admin-btn-hod-check-text">Mark Checked</span>';
                    btnHodCheck.title = 'Review entries and mark sheet as Checked & Verified by HOD';
                }
            } else {
                btnHodCheck.style.display = 'none';
            }
        }

        // Visual header confirmation when marked checked
        if (isHodChecked) {
            if (verifiedHeaderBadge) {
                verifiedHeaderBadge.style.display = 'inline-flex';
                verifiedHeaderBadge.title = `Verified by ${checkerName} on ${checkedAt}`;
            }
            if (inspectorSubtitle) {
                inspectorSubtitle.innerHTML = `🛡️ <strong style="color: #10b981;">Verified & Approved</strong> by ${escapeHtml(checkerName)}${checkedAt ? ` on ${escapeHtml(checkedAt)}` : ''}. Ready for printing with official seal.`;
            }
            if (inspectorBox) {
                inspectorBox.classList.add('is-verified');
            }
        } else {
            if (verifiedHeaderBadge) {
                verifiedHeaderBadge.style.display = 'none';
            }
            if (inspectorSubtitle) {
                inspectorSubtitle.textContent = 'Reviewing, editing, and managing daily work entries for team members.';
            }
            if (inspectorBox) {
                inspectorBox.classList.remove('is-verified');
            }
        }

    } else {
        // Update Employee Personal Shift Hero
        const checkInTimeEl = document.getElementById('hero-checkin-time');
        const checkOutTimeEl = document.getElementById('hero-checkout-time');
        const btnCheckIn = document.getElementById('btn-check-in');
        const btnCheckOut = document.getElementById('btn-check-out');
        const btnSelfHoliday = document.getElementById('btn-self-holiday');
        const completedChip = document.getElementById('hero-shift-completed-chip');
        const nextDayBanner = document.getElementById('hero-next-day-bar');
        const shiftDateLabel = document.getElementById('hero-shift-date-label');

        if (shiftDateLabel) {
            shiftDateLabel.textContent = AppState.selectedDate;
        }

        const checkIn = sheet ? sheet.check_in_time : null;
        const checkOut = sheet ? sheet.check_out_time : null;

        if (checkInTimeEl) checkInTimeEl.textContent = checkIn || '--:--';
        if (checkOutTimeEl) checkOutTimeEl.textContent = checkOut || '--:--';

        // Update Employee HOD Verification Badge & Print Button State
        const empHodBadge = document.getElementById('employee-hod-check-badge');
        const empPrintBtn = document.getElementById('btn-employee-print-sheet');

        const isHodChecked = parseInt(sheet?.is_hod_checked || data?.is_hod_checked) === 1;
        const deptSettings = data?.department_settings || {};
        const requireCheck = parseInt(deptSettings.require_hod_sheet_check) === 1;
        const blockPrint = parseInt(deptSettings.block_print_before_hod_check) === 1;

        if (empHodBadge) {
            if (isHodChecked) {
                empHodBadge.style.display = 'inline-block';
                empHodBadge.className = 'badge badge-hod-checked';
                empHodBadge.textContent = `✓ HOD Verified`;
                empHodBadge.title = `Verified by ${data?.hod_checked_by_name || 'HOD'} on ${data?.hod_checked_at || ''}`;
            } else if (requireCheck) {
                empHodBadge.style.display = 'inline-block';
                empHodBadge.className = 'badge badge-hod-pending';
                empHodBadge.textContent = '⏳ Awaiting HOD';
                empHodBadge.title = 'Awaiting review and verification by Head of Department';
            } else {
                empHodBadge.style.display = 'none';
            }
        }

        if (empPrintBtn) {
            if (blockPrint && !isHodChecked) {
                empPrintBtn.innerHTML = '🔒 Print Sheet';
                empPrintBtn.className = 'btn btn-outline';
                empPrintBtn.style.opacity = '0.85';
                empPrintBtn.title = 'Print Restricted: Your worksheet must be verified and marked checked by your HOD before printing.';
            } else {
                empPrintBtn.innerHTML = '🖨️ Print Sheet';
                empPrintBtn.className = 'btn btn-primary';
                empPrintBtn.style.opacity = '1';
                empPrintBtn.title = 'Print official hourly worksheet';
            }
        }

        // Scheduled Shift from Duty Roster (Informational only: timer starts ONLY upon actual check-in)
        const schedShift = data?.scheduled_shift || null;
        const heroRosterPill = document.getElementById('hero-roster-pill');
        const heroRosterShiftText = document.getElementById('hero-roster-shift-text');
        if (heroRosterPill && heroRosterShiftText) {
            if (schedShift) {
                heroRosterPill.style.display = 'inline-flex';
                heroRosterShiftText.textContent = schedShift;
                heroRosterPill.title = `Your scheduled shift is ${schedShift}. Duty timer starts when you actually punch in.`;
            } else {
                heroRosterPill.style.display = 'none';
            }
        }

        // Date status calculation
        const todayStr = getLocalDateString();
        const isToday = (AppState.selectedDate === todayStr);
        const isFuture = (AppState.selectedDate > todayStr);
        const isPast = (AppState.selectedDate < todayStr);
        const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hod' || hasPermission('can_inspect_sheets'));

        const timerElem = document.getElementById('duty-timer-digits');

        // Check if this is an active overnight shift from yesterday (check-in exists and not checked out/locked)
        const isOvernightActive = checkIn && !checkOut && (!sheet || !sheet.is_locked);

        if (isFuture && !isAdmin) {
            // Future dates: completely disable live check-in/out
            stopLiveTimer();
            if (timerElem) timerElem.textContent = '0:00:00';
            if (btnCheckIn) {
                btnCheckIn.style.display = 'none';
                btnCheckIn.disabled = true;
                btnCheckIn.title = "Live check-in is not allowed for future dates.";
            }
            if (btnCheckOut) {
                btnCheckOut.style.display = 'none';
                btnCheckOut.disabled = true;
                btnCheckOut.title = "";
            }
            if (btnSelfHoliday) btnSelfHoliday.style.display = 'none';
            if (completedChip) completedChip.style.display = 'none';
            if (nextDayBanner) nextDayBanner.style.display = 'none';
        } else if (isPast && !isOvernightActive && !isAdmin) {
            // Past dates with completed shift: show completed duty time
            stopLiveTimer();
            if (timerElem) timerElem.textContent = sheet ? (sheet.total_duty_hours || '0:00:00') : '0:00:00';
            if (btnCheckIn) {
                btnCheckIn.style.display = 'none';
                btnCheckIn.disabled = true;
                btnCheckIn.title = "Past date live check-in is closed.";
            }
            if (btnCheckOut) {
                btnCheckOut.style.display = 'none';
                btnCheckOut.disabled = true;
                btnCheckOut.title = "";
            }
            if (btnSelfHoliday) btnSelfHoliday.style.display = 'none';
            if (checkIn && checkOut) {
                if (completedChip) completedChip.style.display = 'inline-flex';
            } else {
                if (completedChip) completedChip.style.display = 'none';
            }
            if (nextDayBanner) nextDayBanner.style.display = 'none';
        } else {
            // Today OR Active Overnight Shift from yesterday (or Admin override)
            if (checkIn && !checkOut) {
                // Active duty: user checked in -> Check-in disappears, Check-out comes!
                const inDate = parseDateTime(AppState.selectedDate, checkIn);
                const now = new Date();
                const diffSecs = (inDate && !isNaN(inDate.getTime())) ? Math.max(0, Math.floor((now - inDate) / 1000)) : 0;
                startLiveTimer(diffSecs, checkIn, AppState.selectedDate);

                if (btnCheckIn) {
                    btnCheckIn.style.display = 'none';
                    btnCheckIn.disabled = true;
                    btnCheckIn.title = isPast ? "Active Night Shift in progress." : "Currently active on duty.";
                }
                if (btnCheckOut) {
                    btnCheckOut.style.display = 'inline-flex';
                    btnCheckOut.disabled = false;
                    btnCheckOut.title = isPast ? "Click to finish your night shift and lock sheet" : "Click to finish shift and submit sheet";
                }
                if (btnSelfHoliday) btnSelfHoliday.style.display = 'none';
                if (completedChip) completedChip.style.display = 'none';
                if (nextDayBanner) nextDayBanner.style.display = 'none';
            } else if (checkIn && checkOut) {
                // Completed duty: after checkout, both Check-In and Check-Out disappear!
                stopLiveTimer();
                if (timerElem) timerElem.textContent = sheet.total_duty_hours || '0:00:00';

                if (btnCheckIn) {
                    btnCheckIn.style.display = 'none';
                    btnCheckIn.disabled = true;
                    btnCheckIn.title = "Shift completed.";
                }
                if (btnCheckOut) {
                    btnCheckOut.style.display = 'none';
                    btnCheckOut.disabled = true;
                }
                if (btnSelfHoliday) btnSelfHoliday.style.display = 'none';
                if (completedChip) completedChip.style.display = 'inline-flex';
                if (nextDayBanner) nextDayBanner.style.display = isToday ? 'flex' : 'none';
            } else {
                // Fresh day today: user did not check in -> Check-in button shows, Check-out disappears!
                stopLiveTimer();
                if (timerElem) timerElem.textContent = '0:00:00';

                if (btnCheckIn) {
                    btnCheckIn.style.display = 'inline-flex';
                    btnCheckIn.disabled = false;
                    btnCheckIn.title = "Click to Check In for today";
                }
                if (btnCheckOut) {
                    btnCheckOut.style.display = 'none';
                    btnCheckOut.disabled = true;
                }
                if (btnSelfHoliday) btnSelfHoliday.style.display = 'inline-flex';
                if (completedChip) completedChip.style.display = 'none';
                if (nextDayBanner) nextDayBanner.style.display = 'none';
            }
        }
    }
}

function updateLockBadge(isLocked, canEdit) {
    const adminLockBadge = document.getElementById('sheet-lock-badge');
    const empLockBadge = document.getElementById('employee-sheet-lock-badge');
    const adminLockCtrl = document.getElementById('admin-unlock-btn');

    const badgeText = isLocked ? '🔒 Locked' : '🔓 Active';
    const badgeClass = isLocked ? 'badge-lock locked' : 'badge-lock unlocked';

    const allowedCols = (AppState.worksheetColumns || []).filter(c => c.is_visible && (c.editable_after_lock === 1 || c.editable_after_lock === '1')).map(c => c.column_label);
    const postLockHint = allowedCols.length > 0 
        ? `Post-checkout editing permitted for: ${allowedCols.join(', ')}` 
        : 'Worksheet is completely locked.';

    if (adminLockBadge) {
        adminLockBadge.className = badgeClass;
        adminLockBadge.innerHTML = badgeText;
        if (isLocked) adminLockBadge.title = postLockHint;
    }
    if (empLockBadge) {
        empLockBadge.className = badgeClass;
        empLockBadge.innerHTML = badgeText;
        if (isLocked) empLockBadge.title = postLockHint;
    }

    if (adminLockCtrl) {
        adminLockCtrl.innerHTML = isLocked ? '🔓 Unlock Sheet' : '🔒 Lock Sheet';
        adminLockCtrl.className = isLocked ? 'btn btn-success' : 'btn btn-outline';
    }

    // Save button stays enabled so employees can paste/save links even when sheet is locked!
    const btnSave = document.getElementById('btn-save-sheet');
    if (btnSave) {
        btnSave.disabled = false;
        if (isLocked && AppState.currentUser?.role !== 'admin' && AppState.currentUser?.role !== 'super_admin') {
            btnSave.innerHTML = '💾 Save Links / Sheet';
            btnSave.title = 'Save your added links/updates to this locked sheet';
        } else {
            btnSave.innerHTML = '💾 Save Sheet';
            btnSave.title = 'Save all entries';
        }
    }

    const btnAddRow = document.getElementById('btn-add-row');
    const btnAddRowBottom = document.getElementById('btn-add-row-bottom');
    if (btnAddRow) {
        btnAddRow.disabled = !canEdit;
        if (!canEdit && isLocked) {
            btnAddRow.title = "Worksheet is locked after checkout. Adding new rows is disabled.";
        } else {
            btnAddRow.title = "Add a new work row";
        }
    }
    if (btnAddRowBottom) {
        btnAddRowBottom.disabled = !canEdit;
        if (!canEdit && isLocked) {
            btnAddRowBottom.title = "Worksheet is locked after checkout. Adding new rows is disabled.";
        }
    }
}

function renderWorksheetTable(entries, canEdit) {
    const thead = document.getElementById('worksheet-table-head');
    const tbody = document.getElementById('worksheet-table-body');
    if (!tbody) return;

    const visibleCols = getVisibleWorksheetColumns();
    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hod' || hasPermission('can_inspect_sheets'));
    const isLocked = !!(AppState.isLocked && !isAdmin);

    // Dynamically render table headers matching HOD custom order & labels
    if (thead) {
        thead.innerHTML = `
            <tr>
                ${visibleCols.map(col => {
                    let w = 'auto';
                    let minW = '120px';
                    if (col.column_key === 'time_slot') { w = '16%'; minW = '150px'; }
                    else if (col.column_key === 'content_type') { w = '15%'; minW = '145px'; }
                    else if (col.column_key === 'department') { w = '15%'; minW = '145px'; }
                    else if (col.column_key === 'link') { w = '18%'; minW = '160px'; }
                    else if (col.column_key === 'title') { w = '26%'; minW = '190px'; }
                    const isPostLock = isLocked && (col.editable_after_lock === 1 || col.editable_after_lock === '1');
                    const headerBadge = isPostLock ? ' <span style="font-size: 10px; color: var(--success); font-weight: 700;">(Editable)</span>' : '';
                    return `<th class="col-${escapeHtml(col.column_key)}" style="width: ${w}; min-width: ${minW};">${escapeHtml(col.column_label)}${headerBadge}</th>`;
                }).join('')}
                <th class="col-action" style="width: 4%; min-width: 45px; text-align: center;">Action</th>
            </tr>
        `;
    }

    tbody.innerHTML = '';
    const colCount = visibleCols.length + 1;

    if (!entries || entries.length === 0) {
        if (canEdit && !isLocked) {
            addTableRow({}, canEdit);
            addTableRow({}, canEdit);
            addTableRow({}, canEdit);
        } else {
            tbody.innerHTML = `<tr><td colspan="${colCount}" style="text-align: center; color: var(--text-muted); padding: 20px;">No entries logged for this date.</td></tr>`;
        }
    } else {
        entries.forEach(entry => {
            addTableRow(entry, canEdit);
        });
    }

    setupWorksheetAutoSaveListeners();
}

// Helper to calculate consecutive next hour slot for adding rows seamlessly
function getSuggestedNextTimeSlot() {
    const rows = document.querySelectorAll('#worksheet-table-body tr.sheet-entry-row');
    if (rows.length === 0) return '';
    const lastRow = rows[rows.length - 1];
    const timeInput = lastRow.querySelector('.input-time');
    if (!timeInput || !timeInput.value.trim()) return '';

    const prevSlot = timeInput.value.trim();
    const match = prevSlot.match(/^(\d{1,2}(?::\d{1,2})?)\s*(AM|PM)?\s*(?:to|-)\s*(\d{1,2}(?::\d{1,2})?)\s*(AM|PM)?$/i);
    if (match) {
        let eTime = match[3];
        const sAmpm = (match[2] || 'PM').toUpperCase();
        const eAmpm = (match[4] || sAmpm || 'PM').toUpperCase();

        const parts = eTime.split(':');
        let h = parseInt(parts[0], 10);
        let m = parts[1] || '00';
        if (!isNaN(h)) {
            let nextH = h + 1;
            let nextAmpm = eAmpm;
            if (nextH === 12) {
                if (eAmpm === 'AM') nextAmpm = 'PM';
                else if (eAmpm === 'PM') nextAmpm = 'AM';
            } else if (nextH > 12) {
                nextH = 1;
            }
            const nextEndStr = (parts.length > 1) ? `${String(nextH).padStart(2, '0')}:${m}` : `${nextH}:00`;
            return `${eTime} to ${nextEndStr} ${nextAmpm}`;
        }
    }
    return '';
}

function addTableRow(data = {}, canEdit = true, focusNew = false) {
    const tbody = document.getElementById('worksheet-table-body');
    if (!tbody) return;

    const row = document.createElement('tr');
    row.className = 'sheet-entry-row';

    const visibleCols = getVisibleWorksheetColumns();
    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hod' || hasPermission('can_inspect_sheets'));
    const isLocked = !!(AppState.isLocked && !isAdmin);
    const customData = data.custom_data || {};

    let cellsHtml = '';

    visibleCols.forEach(col => {
        const key = col.column_key;
        const type = col.column_type;
        const label = col.column_label;

        // Check if this column is permitted to be edited after checkout
        const isPostLockEditable = isLocked && (col.editable_after_lock === 1 || col.editable_after_lock === '1');
        const isColEditable = canEdit || isPostLockEditable;
        const disabledAttr = isColEditable ? '' : 'disabled';
        const postLockStyle = isPostLockEditable ? 'border-color: rgba(16, 185, 129, 0.6); box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.15);' : '';
        const postLockTitle = isPostLockEditable ? ' title="Editable after checkout (post-duty update permitted)"' : (isLocked ? ' title="Locked (Duty checkout finalized)"' : '');

        // Extract value
        let val = '';
        if (key === 'time_slot') val = data.time_slot || '';
        else if (key === 'content_type') val = data.content_type || '';
        else if (key === 'department') val = data.department || '';
        else if (key === 'link') {
            const rawL = data.link || '';
            val = (rawL === 'upload' ? '' : rawL);
        }
        else if (key === 'title') val = data.title || '';
        else {
            val = customData[key] !== undefined ? customData[key] : (data[key] || '');
        }

        if (key === 'time_slot' || type === 'time') {
            cellsHtml += `
                <td class="col-time-slot" data-col="${escapeHtml(key)}">
                    <input type="text" class="input-time input-control input-sheet-field" data-key="${escapeHtml(key)}" placeholder="e.g. 5:00 to 6:00 AM" value="${escapeHtml(val)}" style="width: 100%; font-weight: 600; ${postLockStyle}" ${disabledAttr}${postLockTitle}>
                </td>
            `;
        } else if (key === 'content_type') {
            const availTypes = getAvailableContentTypes();
            let typeOptions = `<option value="" ${!val ? 'selected' : ''}>-- Select ${escapeHtml(label)} --</option>`;
            let typeFound = false;
            availTypes.forEach(t => {
                const isSel = (t.toLowerCase() === val.toLowerCase());
                if (isSel) typeFound = true;
                typeOptions += `<option value="${escapeHtml(t)}" ${isSel ? 'selected' : ''}>${escapeHtml(t)}</option>`;
            });
            if (val && !typeFound) {
                typeOptions += `<option value="${escapeHtml(val)}" selected>${escapeHtml(val)} (Custom)</option>`;
            }
            cellsHtml += `
                <td class="col-content-type" data-col="${escapeHtml(key)}">
                    <select class="select-content-type input-control input-sheet-field" data-key="${escapeHtml(key)}" style="width: 100%; ${postLockStyle}" ${disabledAttr}${postLockTitle}>
                        ${typeOptions}
                    </select>
                </td>
            `;
        } else if (key === 'department') {
            const availDepts = getAvailableTrackingDepts();
            let deptOptions = `<option value="" ${!val ? 'selected' : ''}>-- Select ${escapeHtml(label)} --</option>`;
            let deptFound = false;
            availDepts.forEach(d => {
                const isSel = (d.toLowerCase() === val.toLowerCase());
                if (isSel) deptFound = true;
                deptOptions += `<option value="${escapeHtml(d)}" ${isSel ? 'selected' : ''}>${escapeHtml(d)}</option>`;
            });
            if (val && !deptFound) {
                deptOptions += `<option value="${escapeHtml(val)}" selected>${escapeHtml(val)} (Custom)</option>`;
            }
            cellsHtml += `
                <td class="col-department" data-col="${escapeHtml(key)}">
                    <select class="select-dept input-control input-sheet-field" data-key="${escapeHtml(key)}" style="width: 100%; ${postLockStyle}" ${disabledAttr}${postLockTitle}>
                        ${deptOptions}
                    </select>
                </td>
            `;
        } else if (type === 'select') {
            const opts = Array.isArray(col.options) ? col.options : [];
            let optHtml = `<option value="" ${!val ? 'selected' : ''}>-- Select ${escapeHtml(label)} --</option>`;
            opts.forEach(op => {
                const isSel = (String(op).toLowerCase() === String(val).toLowerCase());
                optHtml += `<option value="${escapeHtml(op)}" ${isSel ? 'selected' : ''}>${escapeHtml(op)}</option>`;
            });
            if (val && !opts.some(o => String(o).toLowerCase() === String(val).toLowerCase())) {
                optHtml += `<option value="${escapeHtml(val)}" selected>${escapeHtml(val)}</option>`;
            }
            cellsHtml += `
                <td class="col-custom-select" data-col="${escapeHtml(key)}">
                    <select class="input-control input-sheet-field" data-key="${escapeHtml(key)}" style="width: 100%; ${postLockStyle}" ${disabledAttr}${postLockTitle}>
                        ${optHtml}
                    </select>
                </td>
            `;
        } else if (type === 'link' || key === 'link') {
            cellsHtml += `
                <td class="col-link" data-col="${escapeHtml(key)}">
                    <input type="text" class="input-link input-control input-sheet-field" data-key="${escapeHtml(key)}" placeholder="Paste link or drive URL..." value="${escapeHtml(val)}" style="width: 100%; ${postLockStyle}" ${disabledAttr}${postLockTitle}>
                </td>
            `;
        } else if (type === 'number') {
            cellsHtml += `
                <td class="col-number" data-col="${escapeHtml(key)}">
                    <input type="number" class="input-control input-sheet-field" data-key="${escapeHtml(key)}" placeholder="0" value="${escapeHtml(val)}" style="width: 100%; font-weight: 600; ${postLockStyle}" ${disabledAttr}${postLockTitle}>
                </td>
            `;
        } else {
            // Default text input
            cellsHtml += `
                <td class="col-description" data-col="${escapeHtml(key)}">
                    <input type="text" class="input-title input-control input-sheet-field" data-key="${escapeHtml(key)}" placeholder="${escapeHtml(label)}..." value="${escapeHtml(val)}" style="width: 100%; ${postLockStyle}" ${disabledAttr}${postLockTitle}>
                </td>
            `;
        }
    });

    // Action column
    cellsHtml += `
        <td class="col-action" style="text-align: center;">
            ${(canEdit && !isLocked) ? '<button type="button" class="btn-icon-del" onclick="removeTableRow(this)" title="Delete Row">🗑️</button>' : (isLocked ? '<span style="color: var(--text-dim); font-size: 11px;" title="Rows cannot be deleted after shift checkout">🔒</span>' : '')}
        </td>
    `;

    row.innerHTML = cellsHtml;
    tbody.appendChild(row);

    if (focusNew) {
        setTimeout(() => {
            const firstInput = row.querySelector('.input-sheet-field');
            if (firstInput) {
                firstInput.focus();
                firstInput.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 50);
    }
}

// Add a new row directly below the last row
function addNewRowBelow() {
    const tbody = document.getElementById('worksheet-table-body');
    if (!tbody) return;

    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hod' || hasPermission('can_inspect_sheets'));
    const canEdit = isAdmin || !AppState.isLocked;

    if (!canEdit) {
        showToast("Worksheet is locked after checkout. Adding new rows is disabled.", "warning");
        return;
    }

    const suggestedTimeSlot = getSuggestedNextTimeSlot();

    addTableRow({
        time_slot: suggestedTimeSlot,
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
        textEl.textContent = text || 'Saved locally in browser';
        textEl.style.color = '#f59e0b';
    } else {
        if (iconEl) iconEl.textContent = '☁️';
        textEl.textContent = text || 'Auto-save ready';
        textEl.style.color = 'var(--text-muted)';
    }
}

// Local Draft & Offline Protection Helpers
function getDraftStorageKey(empId, date) {
    return `worksheet_draft_${empId || 1}_${date || getLocalDateString()}`;
}

function saveLocalDraft() {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    if (!empId || !date) return;

    const rows = document.querySelectorAll('#worksheet-table-body tr.sheet-entry-row');
    const entries = [];
    rows.forEach(r => {
        const entry = {
            time_slot: '',
            content_type: '',
            department: '',
            link: '',
            title: '',
            count_val: 1,
            custom_data: {}
        };
        const fields = r.querySelectorAll('.input-sheet-field');
        if (fields.length > 0) {
            fields.forEach(f => {
                const k = f.getAttribute('data-key');
                const v = f.value || '';
                if (k === 'time_slot') entry.time_slot = v;
                else if (k === 'content_type') entry.content_type = v;
                else if (k === 'department') entry.department = v;
                else if (k === 'link') entry.link = (v === 'upload' ? '' : v);
                else if (k === 'title') entry.title = v;
                else {
                    entry.custom_data[k] = v;
                    entry[k] = v;
                }
            });
        } else {
            entry.time_slot = r.querySelector('.input-time')?.value || '';
            entry.content_type = r.querySelector('.select-content-type')?.value || '';
            entry.department = r.querySelector('.select-dept')?.value || '';
            const rawLink = r.querySelector('.input-link')?.value || '';
            entry.link = (rawLink === 'upload' ? '' : rawLink);
            entry.title = r.querySelector('.input-title')?.value || '';
        }

        const hasCore = entry.title.trim() || entry.time_slot.trim() || entry.link.trim();
        const hasCustom = Object.values(entry.custom_data).some(x => String(x).trim() !== '');

        if (hasCore || hasCustom) {
            entries.push(entry);
        }
    });

    const workSummary = document.getElementById('worksheet-work-summary')?.value || '';
    const remarks = document.getElementById('worksheet-remarks')?.value || '';

    try {
        const draft = {
            entries,
            work_summary: workSummary,
            remarks: remarks,
            timestamp: Date.now()
        };
        localStorage.setItem(getDraftStorageKey(empId, date), JSON.stringify(draft));
    } catch (e) {
        console.warn("Could not save local draft:", e);
    }
}

function getLocalDraft(empId, date) {
    try {
        const raw = localStorage.getItem(getDraftStorageKey(empId, date));
        if (!raw) return null;
        return JSON.parse(raw);
    } catch (e) {
        return null;
    }
}

function clearLocalDraft(empId, date) {
    try {
        localStorage.removeItem(getDraftStorageKey(empId, date));
    } catch (e) {}
}

function restoreLocalDraft() {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    const draft = getLocalDraft(empId, date);
    if (!draft || !draft.entries || draft.entries.length === 0) {
        showToast("No offline draft entries found to restore.", "info");
        return;
    }

    renderWorksheetTable(draft.entries, true);
    if (draft.work_summary !== undefined) {
        const summaryEl = document.getElementById('worksheet-work-summary');
        if (summaryEl) summaryEl.value = draft.work_summary;
    }
    if (draft.remarks !== undefined) {
        const remarksEl = document.getElementById('worksheet-remarks');
        if (remarksEl) remarksEl.value = draft.remarks;
    }

    const banner = document.getElementById('worksheet-draft-banner');
    if (banner) banner.style.display = 'none';

    showToast("Draft entries restored! Syncing to database...", "success");
    saveCurrentWorksheet(true);
}

function discardLocalDraft() {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    clearLocalDraft(empId, date);
    const banner = document.getElementById('worksheet-draft-banner');
    if (banner) banner.style.display = 'none';
    showToast("Offline draft dismissed.", "info");
}

function triggerAutoSave(delayMs = 700) {
    // 1. Instantly write to browser local storage on every single keystroke (Zero data loss guarantee)
    saveLocalDraft();

    updateSaveIndicator('saving', 'Saving changes...');
    if (autoSaveTimer) clearTimeout(autoSaveTimer);

    autoSaveTimer = setTimeout(async () => {
        try {
            await saveCurrentWorksheet(false, true);
        } catch (e) {
            updateSaveIndicator('error', 'Saved offline in browser');
        }
    }, delayMs);
}

function setupWorksheetAutoSaveListeners() {
    const tbody = document.getElementById('worksheet-table-body');
    if (tbody && !tbody._hasAutoSaveListeners) {
        tbody._hasAutoSaveListeners = true;

        tbody.addEventListener('input', (e) => {
            if (e.target.matches('.input-sheet-field, .input-time, .input-link, .input-title')) {
                triggerAutoSave(700);
            }
        });

        tbody.addEventListener('change', (e) => {
            if (e.target.matches('.input-sheet-field, .select-content-type, .select-dept, select')) {
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
    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hod');
    if (AppState.selectedDate !== todayStr && !isAdmin) {
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

// Employee Check-Out Action (Supports Overnight / Cross-Midnight Shifts)
async function handleCheckOut() {
    const todayStr = getLocalDateString();
    const isAdmin = AppState.currentUser && (AppState.currentUser.role === 'admin' || AppState.currentUser.role === 'super_admin' || AppState.currentUser.role === 'hod');
    const isOngoingShift = AppState.currentSheet && AppState.currentSheet.check_in_time && !AppState.currentSheet.check_out_time && (!AppState.currentSheet.is_locked);

    // Allow check-out if it is an active ongoing shift (e.g. night shift started yesterday) or if viewing today
    if (!isOngoingShift && AppState.selectedDate !== todayStr && !isAdmin) {
        showToast(`Check-Out is only permitted for active ongoing shifts or today's date (${todayStr}).`, "error");
        return;
    }

    const confirmed = await showConfirmDialog({
        title: "Confirm Duty Shift Check-Out",
        message: "Are you sure you want to check out now?",
        detail: "<strong>Notice:</strong> Your duty hours and logged entries will be finalized for this shift, and the worksheet will be <strong>LOCKED</strong>.",
        confirmText: "Check Out",
        cancelText: "Keep Working",
        type: "danger",
        icon: "⏱️"
    });
    if (!confirmed) return;

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
            showToast(data.message || "Checked out successfully! Sheet is locked.", 'success');
            await loadDailyWorksheet(false);
        } else {
            showToast(data.message || "Check-out failed.", 'error');
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
        const updateNow = await showConfirmDialog({
            title: "Shift Already Checked In",
            message: `${empName} is currently recorded as checked in at ${AppState.currentSheet.check_in_time}.`,
            detail: `Would you like to overwrite check-in time with <strong>NOW (${nowTime})</strong>, or open the custom Shift Editor instead?`,
            confirmText: `Update to NOW (${nowTime})`,
            cancelText: "Open Shift Editor",
            type: "warning",
            icon: "🔄"
        });
        if (!updateNow) {
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
        const openEditor = await showConfirmDialog({
            title: "Check-In Record Missing",
            message: `${empName} has not checked in yet for ${date}.`,
            detail: "Would you like to open the Shift Editor to specify both Check-In and Check-Out times?",
            confirmText: "Open Shift Editor",
            cancelText: "Cancel",
            type: "warning",
            icon: "⚠️"
        });
        if (openEditor) {
            openAdminShiftModal();
        }
        return;
    }

    const confirmed = await showConfirmDialog({
        title: "Confirm Admin Check-Out",
        message: `Check out ${empName} for ${date} at ${nowTime}?`,
        detail: "This will calculate total duty hours and lock the worksheet for this shift.",
        confirmText: "Check Out",
        cancelText: "Cancel",
        type: "danger",
        icon: "⏱️"
    });
    if (!confirmed) return;

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

// Open Detailed Shift & Duty Modal for Admin / HR
function openAdminShiftModal(empId, date, empName, desig, dept, inTime, outTime, isLocked, dayType, holidayReason, isSwappedOff, swappedWithDate) {
    if (!empId) empId = getActiveWorksheetEmpId();
    if (!date) date = AppState.selectedDate || new Date().toISOString().split('T')[0];
    if (!empName) empName = document.getElementById('admin-emp-dropdown-selected-name')?.textContent || 'Employee';
    if (!desig) desig = document.getElementById('admin-meta-designation')?.textContent || 'Staff';
    if (!dept) dept = document.getElementById('admin-meta-dept')?.textContent || 'Digital';

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

    const currentIn = inTime !== undefined ? inTime : (AppState.currentSheet ? (AppState.currentSheet.check_in_time || '') : '');
    const currentOut = outTime !== undefined ? outTime : (AppState.currentSheet ? (AppState.currentSheet.check_out_time || '') : '');

    const inInput = document.getElementById('admin-shift-in-time');
    const outInput = document.getElementById('admin-shift-out-time');

    if (inInput) inInput.value = currentIn;
    if (outInput) outInput.value = currentOut;

    // Day schedule type & notes
    const dayTypeSelect = document.getElementById('admin-shift-day-type');
    const reasonInput = document.getElementById('admin-shift-holiday-reason');

    let effectiveType = 'working';
    if (dayType && (dayType === 'swapped_off' || dayType === 'alt_off' || dayType === 'holiday')) {
        effectiveType = dayType;
    } else if (isSwappedOff) {
        effectiveType = 'swapped_off';
    }

    if (dayTypeSelect) {
        dayTypeSelect.value = effectiveType;
    }

    let defaultReason = holidayReason || '';
    if (!defaultReason && isSwappedOff && swappedWithDate) {
        defaultReason = `Swapped with ${swappedWithDate} (HOD approved)`;
    }
    if (reasonInput) {
        reasonInput.value = defaultReason;
    }

    handleAdminShiftDayTypeChange();

    if (avatarEl) {
        avatarEl.textContent = empName ? empName.charAt(0).toUpperCase() : '👤';
    }

    openModal('admin-shift-modal');
}

// Day Type Selector Handler in Admin Shift Modal
function handleAdminShiftDayTypeChange() {
    const dayType = document.getElementById('admin-shift-day-type')?.value || 'working';
    const reasonGroup = document.getElementById('admin-shift-reason-group');
    const timesSection = document.getElementById('admin-shift-times-section');

    const isOffType = (dayType === 'swapped_off' || dayType === 'alt_off' || dayType === 'holiday');
    if (reasonGroup) {
        reasonGroup.style.display = isOffType ? 'block' : 'none';
    }

    if (timesSection) {
        timesSection.style.opacity = isOffType ? '0.65' : '1.0';
    }
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
    const dayType = document.getElementById('admin-shift-day-type')?.value || 'working';
    const holidayReason = document.getElementById('admin-shift-holiday-reason')?.value.trim() || '';

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
                day_type: dayType,
                holiday_reason: holidayReason
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || "Shift schedule and attendance saved successfully.", "success");
            closeModal('admin-shift-modal');
            await loadDailyWorksheet();
            if (typeof loadLiveAttendance === 'function') loadLiveAttendance();
            if (typeof loadAttendanceReport === 'function') {
                const repEmpId = document.getElementById('rep-att-emp-select')?.value || empId;
                loadAttendanceReport(repEmpId);
            }
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

    const confirmed = await showConfirmDialog({
        title: "Reset Shift Records",
        message: `Clear attendance shift records for ${empName} on ${date}?`,
        detail: "This will remove the Check-In and Check-Out timestamps and unlock the daily worksheet.",
        confirmText: "Reset Shift",
        cancelText: "Cancel",
        type: "danger",
        icon: "🔄"
    });
    if (!confirmed) return;

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
        const entry = {
            time_slot: '',
            content_type: '',
            department: '',
            link: '',
            title: '',
            count_val: 1,
            custom_data: {}
        };

        const fields = r.querySelectorAll('.input-sheet-field');
        if (fields.length > 0) {
            fields.forEach(f => {
                const k = f.getAttribute('data-key');
                const v = f.value || '';
                if (k === 'time_slot') entry.time_slot = v;
                else if (k === 'content_type') entry.content_type = v;
                else if (k === 'department') entry.department = v;
                else if (k === 'link') entry.link = (v === 'upload' ? '' : v);
                else if (k === 'title') entry.title = v;
                else {
                    entry.custom_data[k] = v;
                    entry[k] = v;
                }
            });
        } else {
            entry.time_slot = r.querySelector('.input-time')?.value || '';
            entry.content_type = r.querySelector('.select-content-type')?.value || '';
            entry.department = r.querySelector('.select-dept')?.value || '';
            const rawLink = r.querySelector('.input-link')?.value || '';
            entry.link = (rawLink === 'upload' ? '' : rawLink);
            entry.title = r.querySelector('.input-title')?.value || '';
        }

        const hasCore = entry.title.trim() || entry.time_slot.trim() || entry.link.trim();
        const hasCustom = Object.values(entry.custom_data).some(x => String(x).trim() !== '');

        if (hasCore || hasCustom) {
            entries.push(entry);
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
let isPrintingSheet = false;

function triggerPrintSheet() {
    if (isPrintingSheet) return;

    // Check if department enforces HOD check before printing
    const isAdminOrHod = AppState.currentUser && (
        AppState.currentUser.role === 'admin' || 
        AppState.currentUser.role === 'super_admin' || 
        AppState.currentUser.role === 'hod' || 
        hasPermission('can_inspect_sheets')
    );
    const isHodChecked = parseInt(AppState.currentSheet?.is_hod_checked) === 1;
    const blockPrint = parseInt(AppState.departmentSettings?.block_print_before_hod_check) === 1;

    if (!isAdminOrHod && blockPrint && !isHodChecked) {
        showToast("⚠️ Sheet must be checked by your HOD before printing.", "error");
        if (typeof openConfirmDialog === 'function') {
            openConfirmDialog({
                title: 'HOD Verification Required',
                message: 'Your department has enabled pre-print verification.\n\nPlease request your Head of Department (HOD) to inspect and mark your worksheet as "Checked" on the system. Once marked, your sheet will automatically be enabled for printing with the digital verification seal.',
                icon: '🔒',
                confirmText: 'Understood',
                confirmClass: 'btn-primary',
                showCancel: false,
                onConfirm: () => {}
            });
        }
        return;
    }

    isPrintingSheet = true;
    setTimeout(() => { isPrintingSheet = false; }, 2000);

    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    const printUrl = `print.php?employee_id=${empId}&date=${date}`;

    let printIframe = document.getElementById('direct-print-frame');
    if (printIframe) {
        printIframe.remove();
    }

    printIframe = document.createElement('iframe');
    printIframe.id = 'direct-print-frame';
    printIframe.style.position = 'fixed';
    printIframe.style.right = '0';
    printIframe.style.bottom = '0';
    printIframe.style.width = '0';
    printIframe.style.height = '0';
    printIframe.style.border = 'none';
    printIframe.style.visibility = 'hidden';

    printIframe.onload = function() {
        setTimeout(() => {
            try {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            } catch (err) {
                window.open(printUrl, '_blank');
            }
        }, 150);
    };

    document.body.appendChild(printIframe);
    printIframe.src = printUrl;
}

async function handleToggleHodCheckSheet() {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    const sheetId = AppState.currentSheet?.id || 0;
    const currentChecked = parseInt(AppState.currentSheet?.is_hod_checked || 0) === 1;
    const nextState = currentChecked ? 0 : 1;

    const btn = document.getElementById('admin-btn-hod-check');
    if (btn) btn.disabled = true;

    try {
        const res = await fetch('api/sheets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_hod_check',
                sheet_id: sheetId,
                employee_id: empId,
                date: date,
                is_checked: nextState
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            await loadDailyWorksheet(false);
        } else {
            showToast(data.message || 'Failed to update check status', 'error');
        }
    } catch (err) {
        console.error('Error toggling HOD check:', err);
        showToast('Error communicating with server.', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Flexible Rotational Holiday & Day Off Management
 */
function renderHolidayView(data) {
    const holidayCard = document.getElementById('worksheet-holiday-card');
    const contentWrapper = document.getElementById('worksheet-content-wrapper');
    const btnSelfHoliday = document.getElementById('btn-self-holiday');
    const btnAdminHoliday = document.getElementById('admin-btn-toggle-holiday');

    if (!holidayCard || !contentWrapper) return;

    const sheet = data.sheet || {};
    const isHoliday = (data.effective_day_type === 'holiday' || (sheet.day_type === 'holiday'));
    const approvedLeave = data.approved_leave;

    const cardIcon = document.getElementById('holiday-card-icon');
    const cardBadge = document.getElementById('holiday-card-badge');
    const cardTitle = document.getElementById('holiday-card-title');
    const cardSubtitle = document.getElementById('holiday-card-subtitle');
    const cardDateLabel = document.getElementById('holiday-card-date-label');
    const cardDetailLabel = document.getElementById('holiday-card-detail-label');

    // Date display string
    const selectedDateObj = new Date(AppState.selectedDate + 'T00:00:00');
    const dateFormatted = !isNaN(selectedDateObj.getTime())
        ? selectedDateObj.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
        : AppState.selectedDate;

    if (cardDateLabel) cardDateLabel.textContent = dateFormatted;

    if (isHoliday) {
        // Scheduled Rest Day
        holidayCard.style.display = 'block';
        contentWrapper.style.display = 'none';

        if (cardIcon) cardIcon.textContent = '🏖️';
        if (cardBadge) {
            cardBadge.textContent = '🏖️ Day Off';
            cardBadge.style.background = 'rgba(139, 92, 246, 0.15)';
            cardBadge.style.color = '#8b5cf6';
        }
        if (cardTitle) cardTitle.textContent = `${data.employee?.name || 'Employee'} is on Day Off`;
        if (cardSubtitle) cardSubtitle.textContent = 'Scheduled rest day. No worksheet required today.';

        if (cardDetailLabel) {
            cardDetailLabel.textContent = data.scheduled_weekly_off ? `Weekly Off (${data.scheduled_weekly_off})` : 'Weekly Off';
            cardDetailLabel.style.color = '#8b5cf6';
        }

        // Update button text
        if (btnSelfHoliday) {
            btnSelfHoliday.style.display = 'inline-flex';
            btnSelfHoliday.innerHTML = '↩️ Work Day';
            btnSelfHoliday.className = 'btn btn-outline';
            btnSelfHoliday.title = 'Switch back to regular working day';
        }
        if (btnAdminHoliday) {
            btnAdminHoliday.innerHTML = '↩️ Work Day';
            btnAdminHoliday.className = 'btn btn-outline';
            btnAdminHoliday.title = 'Switch back to regular working day';
        }
        const heroCheckIn = document.getElementById('btn-check-in');
        const heroCheckOut = document.getElementById('btn-check-out');
        const heroCompleted = document.getElementById('hero-shift-completed-chip');
        if (heroCheckIn) heroCheckIn.style.display = 'none';
        if (heroCheckOut) heroCheckOut.style.display = 'none';
        if (heroCompleted) heroCompleted.style.display = 'none';
    } else if (approvedLeave) {
        // Approved Official HR Leave (Sick, Casual, Annual)
        holidayCard.style.display = 'block';
        contentWrapper.style.display = 'none';

        if (cardIcon) cardIcon.textContent = '🌴';
        const leaveTypeName = (approvedLeave.leave_type || 'Leave').toUpperCase();
        if (cardBadge) {
            cardBadge.textContent = `🏖️ ${leaveTypeName} Leave`;
            cardBadge.style.background = 'rgba(16, 185, 129, 0.15)';
            cardBadge.style.color = '#10b981';
        }
        if (cardTitle) cardTitle.textContent = `${data.employee?.name || 'Employee'} is on Leave`;
        if (cardSubtitle) cardSubtitle.textContent = `HR Approved (${approvedLeave.days_count || 1} day). No worksheet required today.`;
        if (cardDetailLabel) {
            cardDetailLabel.textContent = `Approved Leave (${approvedLeave.start_date || ''} - ${approvedLeave.end_date || ''})`;
            cardDetailLabel.style.color = '#10b981';
        }

        if (btnSelfHoliday) {
            btnSelfHoliday.style.display = 'inline-flex';
            btnSelfHoliday.innerHTML = '🏖️ On Leave';
            btnSelfHoliday.disabled = true;
        }
        if (btnAdminHoliday) {
            btnAdminHoliday.innerHTML = '🏖️ On Leave';
            btnAdminHoliday.disabled = true;
        }
        const heroCheckIn = document.getElementById('btn-check-in');
        const heroCheckOut = document.getElementById('btn-check-out');
        const heroCompleted = document.getElementById('hero-shift-completed-chip');
        if (heroCheckIn) heroCheckIn.style.display = 'none';
        if (heroCheckOut) heroCheckOut.style.display = 'none';
        if (heroCompleted) heroCompleted.style.display = 'none';
    } else {
        // Normal Working Day
        holidayCard.style.display = 'none';
        contentWrapper.style.display = 'block';

        if (btnSelfHoliday) {
            btnSelfHoliday.innerHTML = '🏖️ Day Off';
            btnSelfHoliday.disabled = false;
            btnSelfHoliday.className = 'btn btn-outline';
            btnSelfHoliday.title = 'Self-Check: Mark this date as your scheduled Day Off / Holiday';
        }
        if (btnAdminHoliday) {
            btnAdminHoliday.innerHTML = '🏖️ Day Off';
            btnAdminHoliday.disabled = false;
            btnAdminHoliday.className = 'btn btn-outline';
            btnAdminHoliday.title = 'Mark this date as employee\'s rotational Day Off / Holiday';
        }
    }
}

function handleShowOvertimeTable() {
    const contentWrapper = document.getElementById('worksheet-content-wrapper');
    if (contentWrapper) {
        contentWrapper.style.display = 'block';
        contentWrapper.scrollIntoView({ behavior: 'smooth' });
        showToast('Worksheet opened for overtime / extra work entry.', 'info');
    }
}

async function handleToggleHoliday(forcedState = null) {
    const empId = getActiveWorksheetEmpId();
    const date = AppState.selectedDate;
    const currentSheet = AppState.currentSheet || {};
    const isCurrentlyHoliday = (currentSheet.day_type === 'holiday');

    let targetType = 'holiday';
    if (forcedState === 'working' || (forcedState === null && isCurrentlyHoliday)) {
        targetType = 'working';
    }

    const empName = (AppState.currentUser && AppState.currentUser.id === empId) 
        ? 'your schedule' 
        : (document.getElementById('admin-emp-dropdown-selected-name')?.textContent || 'this employee');

    const actionText = targetType === 'holiday' 
        ? `Mark ${empName} as on Day Off / Holiday for ${date}?`
        : `Switch ${empName} back to a regular working day for ${date}?`;

    const confirmed = await showConfirmDialog({
        title: targetType === 'holiday' ? '🏖️ Confirm Day Off / Holiday' : '💼 Switch to Working Day',
        message: actionText,
        detail: targetType === 'holiday' 
            ? 'This will register as an official rest day in attendance without penalizing duty hours or deducting from HR leaves.'
            : 'The worksheet table will be restored for normal hourly task logging and shift check-in.',
        confirmText: targetType === 'holiday' ? 'Mark Day Off' : 'Switch to Work Day',
        type: targetType === 'holiday' ? 'primary' : 'warning',
        icon: targetType === 'holiday' ? '🏖️' : '💼'
    });

    if (!confirmed) return;

    try {
        const res = await fetch('api/sheets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'toggle_day_type',
                employee_id: empId,
                date: date,
                day_type: targetType,
                reason: targetType === 'holiday' ? 'Scheduled Rotational Day Off' : ''
            })
        });

        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            await loadDailyWorksheet();
        } else {
            showToast(data.message || 'Failed to update rest day status.', 'error');
        }
    } catch (err) {
        console.error('Failed to toggle holiday:', err);
        showToast('Error updating holiday status.', 'error');
    }
}
