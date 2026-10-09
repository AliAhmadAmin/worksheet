/**
 * WhatsApp Attendance & OrbitSend Gateway Integration Module
 */

const WhatsAppAtt = {
    settings: {},
    logs: [],
    stats: {},
    searchTimer: null,

    async init() {
        this.updateDateBadge();
        await this.loadSettings();
        await this.loadLogs();
        await this.populateEmployeesDropdown();
    },

    async loadSettings() {
        try {
            const res = await fetch('api/whatsapp_attendance.php?action=get_settings');
            const data = await res.json();
            if (data.success) {
                this.settings = data.settings || {};
                const webhookInput = document.getElementById('wa-webhook-url');
                if (webhookInput) {
                    webhookInput.value = data.suggested_webhook_url || '';
                }
                const urlInput = document.getElementById('wa-api-url');
                if (urlInput) {
                    urlInput.value = this.settings.api_url || 'https://app.orbitsend.com/api/send/whatsapp';
                }
                const secretInput = document.getElementById('wa-api-secret');
                if (secretInput) {
                    secretInput.value = '';
                    secretInput.placeholder = data.masked_secret || 'Enter OrbitSend Secret';
                }
                const webhookSecretInput = document.getElementById('wa-webhook-secret');
                if (webhookSecretInput) {
                    webhookSecretInput.value = this.settings.webhook_token || '';
                }
                const uniqueInput = document.getElementById('wa-unique-id');
                if (uniqueInput) {
                    uniqueInput.value = this.settings.unique_id || this.settings.account || '';
                }
                const groupInput = document.getElementById('wa-group-jid');
                if (groupInput) {
                    groupInput.value = this.settings.group_jid || '';
                }
                const aiProvider = document.getElementById('wa-ai-provider');
                if (aiProvider) {
                    aiProvider.value = this.settings.ai_provider || 'auto';
                }
                const aiApiKey = document.getElementById('wa-ai-api-key');
                if (aiApiKey) {
                    aiApiKey.value = this.settings.ai_api_key || '';
                }
                const enabledToggle = document.getElementById('wa-is-enabled');
                if (enabledToggle) {
                    enabledToggle.checked = !!parseInt(this.settings.is_enabled);
                }
                const replyToggle = document.getElementById('wa-auto-reply');
                if (replyToggle) {
                    replyToggle.checked = !!parseInt(this.settings.auto_reply);
                }
            }
        } catch (err) {
            console.error('Error loading WhatsApp settings:', err);
        }
    },

    async loadLogs() {
        const date = document.getElementById('wa-logs-date-filter')?.value || '';
        const status = document.getElementById('wa-logs-status-filter')?.value || 'all';
        const action = document.getElementById('wa-logs-action-filter')?.value || 'all';
        const empId = document.getElementById('wa-logs-emp-filter')?.value || '';
        const search = document.getElementById('wa-logs-search-filter')?.value || '';

        try {
            const countBadge = document.getElementById('wa-filter-count-badge');
            if (countBadge) countBadge.textContent = 'Refreshing stream...';

            const params = new URLSearchParams({
                action: 'get_logs',
                date: date,
                status: status,
                parsed_action: action,
                employee_id: empId,
                search: search
            });

            const res = await fetch(`api/whatsapp_attendance.php?${params.toString()}`);
            const data = await res.json();
            if (data.success) {
                this.logs = data.logs || [];
                this.stats = data.stats || {};
                this.renderStats();
                this.renderLogsTable();

                if (countBadge) {
                    countBadge.innerHTML = `Showing <b>${this.logs.length}</b> activity log${this.logs.length === 1 ? '' : 's'}`;
                }
            }
        } catch (err) {
            console.error('Error loading WhatsApp logs:', err);
        }
    },

    stepDate(direction) {
        const dateInput = document.getElementById('wa-logs-date-filter');
        if (!dateInput) return;

        let cur = dateInput.value ? new Date(dateInput.value + 'T00:00:00') : new Date();
        if (isNaN(cur.getTime())) cur = new Date();
        
        cur.setDate(cur.getDate() + direction);
        const yyyy = cur.getFullYear();
        const mm = String(cur.getMonth() + 1).padStart(2, '0');
        const dd = String(cur.getDate()).padStart(2, '0');
        dateInput.value = `${yyyy}-${mm}-${dd}`;

        this.onDateChange();
    },

    jumpToday() {
        const dateInput = document.getElementById('wa-logs-date-filter');
        if (!dateInput) return;
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        dateInput.value = `${yyyy}-${mm}-${dd}`;
        this.onDateChange();
    },

    toggleAllDates() {
        const dateInput = document.getElementById('wa-logs-date-filter');
        const allBtn = document.getElementById('wa-btn-all-dates');
        if (!dateInput) return;

        if (dateInput.value) {
            dateInput.value = '';
            if (allBtn) {
                allBtn.className = 'btn btn-primary';
            }
        } else {
            this.jumpToday();
            if (allBtn) {
                allBtn.className = 'btn btn-outline';
            }
            return;
        }
        this.onDateChange();
    },

    onDateChange() {
        this.updateDateBadge();
        this.loadLogs();
    },

    updateDateBadge() {
        const dateInput = document.getElementById('wa-logs-date-filter');
        const badge = document.getElementById('wa-date-label-badge');
        const allBtn = document.getElementById('wa-btn-all-dates');

        if (!dateInput || !dateInput.value) {
            if (badge) {
                badge.textContent = '🌐 All Dates (All History)';
                badge.style.color = '#8b5cf6';
                badge.style.background = 'rgba(139, 92, 246, 0.1)';
                badge.style.borderColor = 'rgba(139, 92, 246, 0.2)';
            }
            if (allBtn) allBtn.className = 'btn btn-primary';
            return;
        }

        if (allBtn) allBtn.className = 'btn btn-outline';

        if (badge) {
            const d = new Date(dateInput.value + 'T00:00:00');
            const today = new Date();
            today.setHours(0,0,0,0);
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);

            const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
            const formatted = d.toLocaleDateString('en-US', options);

            if (d.getTime() === today.getTime()) {
                badge.textContent = `Today · ${formatted}`;
                badge.style.color = 'var(--primary)';
                badge.style.background = 'rgba(59, 130, 246, 0.1)';
                badge.style.borderColor = 'rgba(59, 130, 246, 0.2)';
            } else if (d.getTime() === yesterday.getTime()) {
                badge.textContent = `Yesterday · ${formatted}`;
                badge.style.color = '#f59e0b';
                badge.style.background = 'rgba(245, 158, 11, 0.1)';
                badge.style.borderColor = 'rgba(245, 158, 11, 0.2)';
            } else {
                badge.textContent = formatted;
                badge.style.color = 'var(--text-main)';
                badge.style.background = 'var(--bg-input)';
                badge.style.borderColor = 'var(--border-color)';
            }
        }
    },

    onSearchInput(val) {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => {
            this.loadLogs();
        }, 300);
    },

    resetFilters() {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');

        const dateInput = document.getElementById('wa-logs-date-filter');
        const statusInput = document.getElementById('wa-logs-status-filter');
        const actionInput = document.getElementById('wa-logs-action-filter');
        const empInput = document.getElementById('wa-logs-emp-filter');
        const searchInput = document.getElementById('wa-logs-search-filter');

        if (dateInput) dateInput.value = `${yyyy}-${mm}-${dd}`;
        if (statusInput) statusInput.value = 'all';
        if (actionInput) actionInput.value = 'all';
        if (empInput) empInput.value = '';
        if (searchInput) searchInput.value = '';

        this.updateDateBadge();
        this.loadLogs();
        showToast('Filters reset to today', 'info');
    },

    renderStats() {
        const totalEl = document.getElementById('wa-stat-total');
        const inEl = document.getElementById('wa-stat-in');
        const outEl = document.getElementById('wa-stat-out');
        const earlyEl = document.getElementById('wa-stat-early');
        const unmatchedEl = document.getElementById('wa-stat-unmatched');

        if (totalEl) totalEl.textContent = this.stats.total_today || 0;
        if (inEl) inEl.textContent = this.stats.in_today || 0;
        if (outEl) outEl.textContent = this.stats.out_today || 0;
        if (earlyEl) earlyEl.textContent = this.stats.early_today || 0;
        if (unmatchedEl) unmatchedEl.textContent = this.stats.unmatched_today || 0;
    },

    renderLogsTable() {
        const tbody = document.getElementById('wa-logs-tbody');
        if (!tbody) return;

        if (!this.logs || this.logs.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No WhatsApp attendance activity logged for the selected criteria.</td></tr>`;
            return;
        }

        const actionBadges = {
            check_in: { label: '🟢 Check In', bg: 'rgba(16, 185, 129, 0.12)', color: '#059669' },
            check_out: { label: '🔵 Check Out', bg: 'rgba(59, 130, 246, 0.12)', color: '#2563eb' },
            field_visit: { label: '🚗 Field Visit', bg: 'rgba(14, 165, 233, 0.12)', color: '#0284c7' },
            back_in_office: { label: '🏢 Back in Office', bg: 'rgba(16, 185, 129, 0.12)', color: '#059669' },
            leave_notice: { label: '🌴 Leave Notice', bg: 'rgba(168, 85, 247, 0.12)', color: '#9333ea' },
            leaving_early: { label: '⚠️ Early Leave', bg: 'rgba(245, 158, 11, 0.12)', color: '#d97706' },
            short_leave: { label: '⏱️ Short Leave', bg: 'rgba(139, 92, 246, 0.12)', color: '#8b5cf6' },
            unknown: { label: '⚪ Chat / Noise', bg: 'rgba(107, 114, 128, 0.12)', color: '#6b7280' }
        };

        const statusBadges = {
            applied: { label: 'Applied ✅', bg: 'rgba(16, 185, 129, 0.15)', color: '#059669' },
            unmatched: { label: 'Unmatched ⚠️', bg: 'rgba(239, 68, 68, 0.15)', color: '#dc2626' },
            ignored: { label: 'Ignored ⚪', bg: 'rgba(107, 114, 128, 0.12)', color: '#6b7280' }
        };

        let html = '';
        this.logs.forEach(l => {
            const act = actionBadges[l.parsed_action] || actionBadges.unknown;
            const st = statusBadges[l.status] || statusBadges.applied;
            const deptBadge = l.department_name ? `<span style="background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;">${escapeHtml(l.department_name)}</span>` : '';
            const codeBadge = l.emp_code ? `<span style="background: rgba(100, 116, 139, 0.12); color: var(--text-muted); padding: 1px 5px; border-radius: 4px; font-size: 9.5px; font-weight: 700; font-family: monospace;">${escapeHtml(l.emp_code)}</span>` : '';
            const phoneVal = l.display_phone || l.sender_phone || '';

            // Format logged timestamp into clean time + date
            let timeStr = l.created_at || '';
            let dateStr = '';
            if (l.created_at && l.created_at.includes(' ')) {
                const parts = l.created_at.split(' ');
                dateStr = parts[0];
                timeStr = parts[1];
            }

            html += `
                <tr>
                    <td style="white-space: nowrap; line-height: 1.2;">
                        <div style="font-weight: 700; font-size: 12px; color: var(--text-main); font-family: monospace;">${escapeHtml(timeStr)}</div>
                        <div style="font-size: 10px; color: var(--text-muted); font-family: monospace;">${escapeHtml(dateStr)}</div>
                    </td>
                    <td>
                        <div style="font-weight: 700; font-size: 12.5px; color: var(--text-main); display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span>${escapeHtml(l.employee_name || l.sender_name || 'Unknown')}</span>
                            ${codeBadge}
                            ${deptBadge}
                        </div>
                        <div style="font-size: 10.5px; color: var(--text-muted); font-family: monospace; margin-top: 2px;">
                            📱 ${escapeHtml(phoneVal || '--')}
                        </div>
                    </td>
                    <td style="white-space: nowrap;">
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; background: ${act.bg}; color: ${act.color}; white-space: nowrap;">
                            ${act.label}
                        </span>
                    </td>
                    <td style="white-space: nowrap;">
                        <span style="font-weight: 800; font-size: 12.5px; color: var(--text-main);">${escapeHtml(l.extracted_time || '--')}</span>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span style="background: var(--bg-card-elevated); padding: 3px 7px; border-radius: 4px; border: 1px solid var(--border-color); font-size: 11.5px; color: var(--text-main); font-family: monospace; font-weight: 600;">
                                💬 "${escapeHtml(l.raw_message || '')}"
                            </span>
                            ${l.remarks ? `<span style="font-size: 10.5px; color: var(--text-muted);">${escapeHtml(l.remarks)}</span>` : ''}
                        </div>
                    </td>
                    <td style="text-align: center; white-space: nowrap;">
                        <span style="display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 10.5px; font-weight: 700; background: ${st.bg}; color: ${st.color}; white-space: nowrap;">
                            ${st.label}
                        </span>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    },

    async saveSettings(e) {
        if (e) e.preventDefault();
        const apiUrl = document.getElementById('wa-api-url')?.value || 'https://app.orbitsend.com/api/send/whatsapp';
        const apiSecret = document.getElementById('wa-api-secret')?.value || '';
        const webhookToken = document.getElementById('wa-webhook-secret')?.value || '';
        const uniqueId = document.getElementById('wa-unique-id')?.value || '';
        const groupJid = document.getElementById('wa-group-jid')?.value || '';
        const aiProvider = document.getElementById('wa-ai-provider')?.value || 'auto';
        const aiApiKey = document.getElementById('wa-ai-api-key')?.value || '';
        const isEnabled = document.getElementById('wa-is-enabled')?.checked ? 1 : 0;
        const autoReply = document.getElementById('wa-auto-reply')?.checked ? 1 : 0;

        try {
            const res = await fetch('api/whatsapp_attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_settings',
                    api_url: apiUrl,
                    api_secret: apiSecret,
                    webhook_token: webhookToken,
                    unique_id: uniqueId,
                    group_jid: groupJid,
                    ai_provider: aiProvider,
                    ai_api_key: aiApiKey,
                    is_enabled: isEnabled,
                    auto_reply: autoReply
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast('OrbitSend WhatsApp & AI settings updated successfully.', 'success');
                closeModal('wa-settings-modal');
                await this.loadSettings();
            } else {
                showToast(data.message || 'Failed to save settings', 'error');
            }
        } catch (err) {
            showToast('Error saving settings', 'error');
        }
    },

    async runSimulator(e) {
        if (e) e.preventDefault();
        const msg = document.getElementById('wa-sim-message')?.value || '';
        const phone = document.getElementById('wa-sim-phone')?.value || '';
        const empSelect = document.getElementById('wa-sim-employee');
        const empName = empSelect ? empSelect.options[empSelect.selectedIndex]?.text : '';
        const date = document.getElementById('wa-sim-date')?.value || '';

        if (!msg.trim()) {
            showToast('Please type a message to simulate (e.g. "in at 4:46")', 'warning');
            return;
        }

        try {
            const res = await fetch('api/whatsapp_attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'simulate_message',
                    message: msg,
                    phone: phone,
                    sender_name: empName,
                    date: date
                })
            });
            const data = await res.json();
            if (data.success && data.result) {
                const r = data.result;
                const resultBox = document.getElementById('wa-sim-result-box');
                if (resultBox) {
                    resultBox.style.display = 'block';
                    resultBox.innerHTML = `
                        <div style="font-weight: 800; color: ${r.status === 'applied' ? '#10b981' : '#ef4444'}; margin-bottom: 4px;">
                            ${r.status === 'applied' ? '✅ Parsed & Applied Successfully' : '⚠️ ' + r.status.toUpperCase()}
                        </div>
                        <div style="font-size: 12px; color: var(--text-main);">
                            👤 <b>Employee:</b> ${escapeHtml(r.employee)}<br>
                            ⚡ <b>Detected Action:</b> <span style="text-transform: capitalize; font-weight: 700;">${escapeHtml(r.action)}</span><br>
                            🕒 <b>Extracted Time:</b> <b>${escapeHtml(r.extracted_time)}</b><br>
                            📝 <b>Remarks:</b> ${escapeHtml(r.remarks)}
                        </div>
                    `;
                }
                showToast('Simulated message processed successfully!', 'success');
                await this.loadLogs();
            } else {
                showToast(data.message || 'Simulation failed', 'error');
            }
        } catch (err) {
            showToast('Error during simulation', 'error');
        }
    },

    async populateEmployeesDropdown() {
        const empSelect = document.getElementById('wa-sim-employee');
        const filterSelect = document.getElementById('wa-logs-emp-filter');
        if (!empSelect && !filterSelect) return;

        try {
            const res = await fetch('api/employees.php?action=get_all');
            const data = await res.json();
            const list = data.employees || [];

            let options = '<option value="">-- Select Employee (Optional) --</option>';
            let filterOptions = '<option value="">👥 All Employees</option>';

            list.forEach(emp => {
                const phone = emp.phone || emp.whatsapp_number || '';
                const code = emp.emp_code ? `[${emp.emp_code}] ` : '';
                const dept = emp.department_name ? ` (${emp.department_name})` : '';
                options += `<option value="${emp.id}" data-phone="${escapeHtml(phone)}">${escapeHtml(code + emp.name + dept)}</option>`;
                filterOptions += `<option value="${emp.id}">${escapeHtml(code + emp.name + dept)}</option>`;
            });

            if (empSelect) {
                empSelect.innerHTML = options;
                empSelect.onchange = function() {
                    const selected = empSelect.options[empSelect.selectedIndex];
                    const p = selected.getAttribute('data-phone');
                    const phoneInput = document.getElementById('wa-sim-phone');
                    if (phoneInput && p) phoneInput.value = p;
                };
            }
            if (filterSelect) {
                filterSelect.innerHTML = filterOptions;
            }
        } catch (err) {
            console.error('Error populating employees dropdown:', err);
        }
    },

    copyWebhookUrl() {
        const urlInput = document.getElementById('wa-webhook-url');
        if (urlInput) {
            urlInput.select();
            document.execCommand('copy');
            showToast('Webhook URL copied to clipboard!', 'success');
        }
    },

    async fetchGroupsFromOrbitSend() {
        try {
            showToast('Fetching WhatsApp groups from OrbitSend...', 'info');
            const res = await fetch('api/whatsapp_attendance.php?action=fetch_groups');
            const data = await res.json();
            if (data.success && data.data && data.data.length > 0) {
                const groupInput = document.getElementById('wa-group-jid');
                const firstGroup = data.data[0].id || data.data[0].jid || '';
                if (groupInput && !groupInput.value) {
                    groupInput.value = firstGroup;
                }
                showToast(`Found ${data.data.length} group(s)! Selected: ${firstGroup}`, 'success');
            } else {
                showToast(data.message || (data.raw ? data.raw.message : 'No WhatsApp groups found in OrbitSend account.'), 'warning');
            }
        } catch (err) {
            showToast('Failed to fetch groups from OrbitSend', 'error');
        }
    }
};

// Global switcher integration for views/tab_attendance.php
function switchAttendanceView(view) {
    const liveSec = document.getElementById('att-view-live-section');
    const repSec = document.getElementById('att-view-report-section');
    const waSec = document.getElementById('att-view-whatsapp-section');
    const zkSec = document.getElementById('att-view-zkteco-section');

    const btnLive = document.getElementById('btn-att-view-live');
    const btnRep = document.getElementById('btn-att-view-report');
    const btnWa = document.getElementById('btn-att-view-whatsapp');
    const btnZk = document.getElementById('btn-att-view-zkteco');

    if (liveSec) liveSec.style.display = (view === 'live') ? 'block' : 'none';
    if (repSec) repSec.style.display = (view === 'report') ? 'block' : 'none';
    if (waSec) waSec.style.display = (view === 'whatsapp') ? 'block' : 'none';
    if (zkSec) zkSec.style.display = (view === 'zkteco') ? 'block' : 'none';

    // Update active button styling with high-contrast active and inactive states
    [
        { el: btnLive, active: view === 'live' },
        { el: btnRep, active: view === 'report' },
        { el: btnWa, active: view === 'whatsapp' },
        { el: btnZk, active: view === 'zkteco' }
    ].forEach(b => {
        if (b.el) {
            if (b.active) {
                b.el.className = 'btn btn-primary';
                b.el.style.color = '#ffffff';
                b.el.style.background = 'var(--primary)';
                b.el.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.15)';
            } else {
                b.el.className = 'btn btn-outline';
                b.el.style.color = 'var(--text-main)';
                b.el.style.background = 'transparent';
                b.el.style.boxShadow = 'none';
            }
        }
    });

    if (view === 'live' && typeof loadLiveAttendance === 'function') {
        loadLiveAttendance();
    } else if (view === 'report' && typeof loadAttendanceReport === 'function') {
        loadAttendanceReport();
    } else if (view === 'whatsapp') {
        WhatsAppAtt.init();
    } else if (view === 'zkteco' && typeof ZKTecoAtt !== 'undefined') {
        ZKTecoAtt.init();
    }
}

// Helpers for Live Shift Roster Date Navigation
function stepLiveDate(direction) {
    const dateInput = document.getElementById('attendance-board-date');
    if (!dateInput) return;
    let cur = dateInput.value ? new Date(dateInput.value + 'T00:00:00') : new Date();
    if (isNaN(cur.getTime())) cur = new Date();
    cur.setDate(cur.getDate() + direction);
    const yyyy = cur.getFullYear();
    const mm = String(cur.getMonth() + 1).padStart(2, '0');
    const dd = String(cur.getDate()).padStart(2, '0');
    dateInput.value = `${yyyy}-${mm}-${dd}`;
    if (typeof loadLiveAttendance === 'function') loadLiveAttendance();
}

function jumpLiveDateToday() {
    const dateInput = document.getElementById('attendance-board-date');
    if (!dateInput) return;
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    dateInput.value = `${yyyy}-${mm}-${dd}`;
    if (typeof loadLiveAttendance === 'function') loadLiveAttendance();
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    WhatsAppAtt.init();
});
