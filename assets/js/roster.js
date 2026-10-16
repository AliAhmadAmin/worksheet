/**
 * Duty Roster Management
 * Discover Pakistan UHD TV Network
 * Rotational 24/7 Shifts & Assigned Weekly Off Schedules
 */

let currentRosterData = null;

async function loadDutyRoster() {
    const monthPicker = document.getElementById('roster-month-picker');
    const deptSelect = document.getElementById('roster-dept-select');
    const container = document.getElementById('roster-tables-container');

    const month = monthPicker ? monthPicker.value : new Date().toISOString().slice(0, 7);
    const deptId = deptSelect ? deptSelect.value : 2;

    if (container) {
        container.innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <div class="spinner" style="margin: 0 auto 12px; width: 28px; height: 28px; border: 3px solid rgba(56, 189, 248, 0.2); border-top-color: #38bdf8; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                <p style="margin: 0; font-size: 13.5px;">Loading duty roster for ${month}...</p>
            </div>
        `;
    }

    try {
        const res = await fetch(`api/roster.php?action=get_roster&month=${encodeURIComponent(month)}&department_id=${encodeURIComponent(deptId)}`);
        const json = await res.json();

        if (!json.success) {
            if (container) {
                container.innerHTML = `<div class="alert alert-danger" style="margin: 20px;">${escapeHtml(json.message || 'Failed to load roster.')}</div>`;
            }
            return;
        }

        currentRosterData = json;
        renderDutyRoster(json);
    } catch (err) {
        console.error('Failed to load duty roster:', err);
        if (container) {
            container.innerHTML = `<div class="alert alert-danger" style="margin: 20px;">Network error loading duty roster. Please try again.</div>`;
        }
    }
}

function renderDutyRoster(data) {
    const container = document.getElementById('roster-tables-container');
    const docTitle = document.getElementById('roster-document-title');
    const metaSubtitle = document.getElementById('roster-meta-subtitle');

    if (!container) return;

    // Format Month for Title e.g. "OCTOBER 2026"
    const [year, monthNum] = (data.month || '').split('-');
    const monthDate = new Date(parseInt(year, 10), parseInt(monthNum, 10) - 1, 1);
    const monthName = !isNaN(monthDate.getTime()) 
        ? monthDate.toLocaleString('en-US', { month: 'short' }).toUpperCase() 
        : data.month;
    
    let deptNameUpper = (data.department_name || 'DIGITAL').toUpperCase();
    if (deptNameUpper === 'DIGITAL MEDIA') deptNameUpper = 'DIGITAL';

    if (docTitle) {
        docTitle.textContent = `${deptNameUpper} DUTY ROSTER (${monthName} ${year})`;
    }
    if (metaSubtitle) {
        metaSubtitle.textContent = `Official Monthly Shift Timings & Weekly Off Allocations • ${data.items.length} Team Members`;
    }

    // Group items by team
    const teamGroups = {};
    let morningCount = 0;
    let afternoonCount = 0;
    let nightCount = 0;
    let offCount = 0;

    (data.items || []).forEach(item => {
        const tName = item.team_name || 'General Staff';
        if (!teamGroups[tName]) teamGroups[tName] = [];
        teamGroups[tName].push(item);

        // Stats classification
        const shiftStr = (item.shift_timing || '').toUpperCase();
        if (shiftStr.includes('OFF')) {
            offCount++;
        } else if (shiftStr.includes('AM TO') || shiftStr.startsWith('8:') || shiftStr.startsWith('9:') || shiftStr.startsWith('10:') || shiftStr.startsWith('08:') || shiftStr.startsWith('09:')) {
            morningCount++;
        } else if (shiftStr.includes('11:00 PM') || shiftStr.includes('12:00 AM') || shiftStr.includes('01:00 AM')) {
            nightCount++;
        } else {
            afternoonCount++;
        }
    });

    // Update Quick Stats
    const totalStaffEl = document.getElementById('stat-total-staff');
    const mornEl = document.getElementById('stat-morning-shifts');
    const aftEl = document.getElementById('stat-afternoon-shifts');
    const nightEl = document.getElementById('stat-night-shifts');
    const offsEl = document.getElementById('stat-configured-offs');

    if (totalStaffEl) totalStaffEl.textContent = data.items.length;
    if (mornEl) mornEl.textContent = morningCount;
    if (aftEl) aftEl.textContent = afternoonCount;
    if (nightEl) nightEl.textContent = nightCount;
    if (offsEl) offsEl.textContent = data.items.filter(i => i.weekly_off).length;

    // Common shift options presets
    const shiftPresets = [
        '8:00 AM TO 4:00 PM',
        '9:00 AM TO 5:00 PM',
        '10:00 AM TO 6:00 PM',
        '12:00 PM TO 8:00 PM',
        '1:00 PM TO 9:00 PM',
        '2:00 PM TO 10:00 PM',
        '2:00 PM TO 12:00 AM',
        '3:00 PM TO 11:00 PM',
        '4:00 PM TO 12:00 AM',
        '11:00 PM TO 7:00 AM',
        'OFF'
    ];

    const weeklyOffOptions = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Once in Month',
        'Friday & Saturday',
        'Saturday & Sunday'
    ];

    let html = '';

    const canEdit = Boolean(data.can_edit);
    let overallSr = 1;

    for (const [teamName, teamMembers] of Object.entries(teamGroups)) {
        html += `
            <div class="roster-team-block" style="margin-bottom: 24px;">
                <div style="background: var(--bg-card-elevated); border-left: 4px solid var(--primary); padding: 8px 14px; font-weight: 800; font-size: 13.5px; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; border-radius: 4px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                    <span>👥 ${escapeHtml(teamName)}</span>
                    <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); text-transform: none;">${teamMembers.length} staff</span>
                </div>

                <div class="table-responsive" style="border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; background: var(--bg-card);">
                    <table class="data-table roster-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: var(--bg-card-elevated); border-bottom: 2px solid var(--border-color);">
                                <th style="width: 45px; text-align: center; padding: 10px 8px;">Sr.</th>
                                <th style="min-width: 180px; padding: 10px 12px; text-align: left;">Name</th>
                                <th style="min-width: 160px; padding: 10px 12px; text-align: left;">Designation</th>
                                <th style="min-width: 220px; padding: 10px 12px; text-align: left;">Shift Timing</th>
                                <th style="min-width: 180px; padding: 10px 12px; text-align: left;">Weekly Off</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        teamMembers.forEach(emp => {
            const currentShift = emp.shift_timing || '10:00 AM TO 6:00 PM';
            const currentOff = emp.weekly_off || 'Sunday';

            html += `
                <tr class="roster-row" data-emp-id="${emp.employee_id}" data-team-id="${emp.team_id || 0}" data-notes="${escapeHtml(emp.notes || '')}" style="border-bottom: 1px solid var(--border-color);">
                    <td style="text-align: center; font-weight: 700; color: var(--text-muted); padding: 8px;">
                        ${overallSr++}
                    </td>
                    <td style="padding: 8px 12px; font-weight: 700; color: var(--text-main);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 14px;">👤</span>
                            <span>${escapeHtml(emp.name)}</span>
                        </div>
                    </td>
                    <td style="padding: 8px 12px; color: var(--text-muted);">
                        ${canEdit ? `
                            <input type="text" class="input-control roster-desig-input" value="${escapeHtml(emp.designation || '')}" style="padding: 5px 8px; font-size: 12.5px; width: 100%;" placeholder="Designation">
                        ` : `
                            <span>${escapeHtml(emp.designation || 'Staff')}</span>
                        `}
                    </td>
                    <td style="padding: 8px 12px;">
                        ${canEdit ? `
                            <div style="display: flex; gap: 6px; align-items: center;">
                                <input type="text" class="input-control roster-shift-input" list="shift-preset-list" value="${escapeHtml(currentShift)}" style="padding: 5px 8px; font-size: 12.5px; font-weight: 600; width: 100%;" placeholder="e.g. 2:00 PM TO 12:00 AM">
                            </div>
                        ` : `
                            <span class="badge" style="background: rgba(56, 189, 248, 0.12); color: #38bdf8; font-weight: 700; padding: 4px 8px; border-radius: 4px;">
                                ⏱️ ${escapeHtml(currentShift)}
                            </span>
                        `}
                    </td>
                    <td style="padding: 8px 12px;">
                        ${canEdit ? `
                            <select class="input-control roster-off-select" style="padding: 5px 8px; font-size: 12.5px; font-weight: 600; width: 100%;">
                                ${weeklyOffOptions.map(opt => `
                                    <option value="${escapeHtml(opt)}" ${opt.toLowerCase() === currentOff.toLowerCase() ? 'selected' : ''}>
                                        ${escapeHtml(opt)}
                                    </option>
                                `).join('')}
                            </select>
                        ` : `
                            <span class="badge" style="background: rgba(139, 92, 246, 0.12); color: #a78bfa; font-weight: 700; padding: 4px 8px; border-radius: 4px;">
                                🏖️ ${escapeHtml(currentOff)}
                            </span>
                        `}
                    </td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // Add shared datalist for shift presets
    html += `
        <datalist id="shift-preset-list">
            ${shiftPresets.map(s => `<option value="${escapeHtml(s)}"></option>`).join('')}
        </datalist>
    `;

    container.innerHTML = html;
}

async function saveDutyRoster() {
    if (!currentRosterData) return;

    const btnSave = document.getElementById('btn-save-roster');
    const originalText = btnSave ? btnSave.innerHTML : '💾 Save Roster';
    if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerHTML = '⏳ Saving...';
    }

    const rows = document.querySelectorAll('.roster-row');
    const items = [];

    rows.forEach(row => {
        const empId = parseInt(row.getAttribute('data-emp-id'), 10);
        const desigInput = row.querySelector('.roster-desig-input');
        const shiftInput = row.querySelector('.roster-shift-input');
        const offSelect = row.querySelector('.roster-off-select');

        items.push({
            employee_id: empId,
            designation: desigInput ? desigInput.value.trim() : '',
            shift_timing: shiftInput ? shiftInput.value.trim() : '10:00 AM TO 6:00 PM',
            weekly_off: offSelect ? offSelect.value.trim() : 'Sunday',
            notes: row.getAttribute('data-notes') || ''
        });
    });

    try {
        const res = await fetch('api/roster.php?action=save_roster', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                month: currentRosterData.month,
                department_id: currentRosterData.department_id,
                items: items
            })
        });

        const json = await res.json();
        if (json.success) {
            if (typeof showToast === 'function') {
                showToast(`✅ Duty Roster for ${currentRosterData.month} successfully saved! (${json.saved_count} staff)`, 'success');
            } else {
                alert(`✅ Duty Roster saved successfully!`);
            }
            loadDutyRoster();
        } else {
            alert('Failed to save roster: ' + (json.message || 'Unknown error'));
        }
    } catch (err) {
        console.error('Error saving roster:', err);
        alert('Network error while saving roster.');
    } finally {
        if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = originalText;
        }
    }
}

async function copyPreviousMonthRoster() {
    if (!currentRosterData) return;

    const targetMonth = currentRosterData.month;
    const prevMonth = currentRosterData.prev_month;

    if (!confirm(`Are you sure you want to roll forward the roster schedule from ${prevMonth} to ${targetMonth}? Existing entries for ${targetMonth} will be updated.`)) {
        return;
    }

    try {
        const res = await fetch('api/roster.php?action=copy_previous', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_month: targetMonth,
                department_id: currentRosterData.department_id
            })
        });

        const json = await res.json();
        if (json.success) {
            if (typeof showToast === 'function') {
                showToast(`✅ Successfully copied ${json.copied_count} staff schedule records from ${json.from_month}!`, 'success');
            } else {
                alert(`✅ Roster copied from previous month!`);
            }
            loadDutyRoster();
        } else {
            alert('Could not copy roster: ' + (json.message || 'No previous roster found'));
        }
    } catch (err) {
        console.error('Error copying roster:', err);
        alert('Network error while copying roster.');
    }
}

function printOfficialDutyRoster() {
    window.print();
}
