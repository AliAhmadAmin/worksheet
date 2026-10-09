/**
 * ZKTeco Biometric Attendance Frontend Controller
 * Handles live punch stream, device status monitoring, ADMS setup, and simulation.
 */

const ZKTecoAtt = {
    logs: [],
    stats: {},
    devices: [],
    settings: {},
    searchTimer: null,

    init() {
        this.loadSettings();
        this.loadDevices();
        this.loadLogs();
        this.populateSimEmployees();
    },

    async loadSettings() {
        try {
            const res = await fetch('api/zkteco_manage.php?action=get_settings');
            const data = await res.json();
            if (data.success) {
                this.settings = data;
                this.renderSetupInfo(data);
            }
        } catch (e) {
            console.error('Error loading ZKTeco settings:', e);
        }
    },

    async loadDevices() {
        try {
            const res = await fetch('api/zkteco_manage.php?action=get_devices');
            const data = await res.json();
            if (data.success) {
                this.devices = data.devices || [];
                this.renderDevices();
            }
        } catch (e) {
            console.error('Error loading ZKTeco devices:', e);
        }
    },

    async loadLogs() {
        try {
            const dateInput = document.getElementById('zk-logs-date-filter');
            const statusInput = document.getElementById('zk-logs-status-filter');
            const empInput = document.getElementById('zk-logs-emp-filter');
            const searchInput = document.getElementById('zk-logs-search-filter');
            const countBadge = document.getElementById('zk-logs-count-badge');

            const params = new URLSearchParams({
                action: 'get_logs',
                date: dateInput ? dateInput.value : '',
                status: statusInput ? statusInput.value : 'all',
                employee_id: empInput ? empInput.value : '',
                search: searchInput ? searchInput.value : '',
                limit: 200
            });

            const res = await fetch(`api/zkteco_manage.php?${params.toString()}`);
            const data = await res.json();
            if (data.success) {
                this.logs = data.logs || [];
                this.stats = data.stats || {};
                this.renderStats();
                this.renderLogsTable();

                if (countBadge) {
                    countBadge.innerHTML = `Showing <b>${this.logs.length}</b> biometric punch${this.logs.length === 1 ? '' : 'es'}`;
                }
            }
        } catch (e) {
            console.error('Error loading ZKTeco logs:', e);
        }
    },

    stepDate(dir) {
        const input = document.getElementById('zk-logs-date-filter');
        if (!input) return;
        let cur = input.value ? new Date(input.value + 'T00:00:00') : new Date();
        if (isNaN(cur.getTime())) cur = new Date();
        cur.setDate(cur.getDate() + dir);
        const yyyy = cur.getFullYear();
        const mm = String(cur.getMonth() + 1).padStart(2, '0');
        const dd = String(cur.getDate()).padStart(2, '0');
        input.value = `${yyyy}-${mm}-${dd}`;
        this.onDateChange();
    },

    jumpToday() {
        const input = document.getElementById('zk-logs-date-filter');
        if (!input) return;
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        input.value = `${yyyy}-${mm}-${dd}`;
        this.onDateChange();
    },

    toggleAllDates() {
        const input = document.getElementById('zk-logs-date-filter');
        const btn = document.getElementById('zk-btn-all-dates');
        if (!input) return;

        if (input.value) {
            input.value = '';
            if (btn) btn.className = 'btn btn-primary';
        } else {
            this.jumpToday();
            if (btn) btn.className = 'btn btn-outline';
            return;
        }
        this.onDateChange();
    },

    onDateChange() {
        this.updateDateBadge();
        this.loadLogs();
    },

    updateDateBadge() {
        const input = document.getElementById('zk-logs-date-filter');
        const badge = document.getElementById('zk-date-display-badge');
        const allBtn = document.getElementById('zk-btn-all-dates');
        if (!badge) return;

        if (!input || !input.value) {
            badge.textContent = 'Showing All Dates';
            badge.style.color = 'var(--primary)';
            badge.style.background = 'rgba(59, 130, 246, 0.12)';
            badge.style.borderColor = 'rgba(59, 130, 246, 0.3)';
            if (allBtn) allBtn.className = 'btn btn-primary';
            return;
        }

        if (allBtn) allBtn.className = 'btn btn-outline';

        const sel = new Date(input.value + 'T00:00:00');
        const today = new Date();
        const isToday = sel.getFullYear() === today.getFullYear() && sel.getMonth() === today.getMonth() && sel.getDate() === today.getDate();

        if (isToday) {
            badge.textContent = 'Today, ' + sel.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            badge.style.color = '#059669';
            badge.style.background = 'rgba(16, 185, 129, 0.12)';
            badge.style.borderColor = 'rgba(16, 185, 129, 0.3)';
        } else {
            badge.textContent = sel.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            badge.style.color = 'var(--text-main)';
            badge.style.background = 'var(--bg-input)';
            badge.style.borderColor = 'var(--border-color)';
        }
    },

    onSearchInput() {
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

        const dateInput = document.getElementById('zk-logs-date-filter');
        const statusInput = document.getElementById('zk-logs-status-filter');
        const empInput = document.getElementById('zk-logs-emp-filter');
        const searchInput = document.getElementById('zk-logs-search-filter');

        if (dateInput) dateInput.value = `${yyyy}-${mm}-${dd}`;
        if (statusInput) statusInput.value = 'all';
        if (empInput) empInput.value = '';
        if (searchInput) searchInput.value = '';

        this.updateDateBadge();
        this.loadLogs();
        showToast('Biometric filters reset to today', 'info');
    },

    renderStats() {
        const totalEl = document.getElementById('zk-stat-total');
        const appliedEl = document.getElementById('zk-stat-applied');
        const unmatchedEl = document.getElementById('zk-stat-unmatched');
        const dupEl = document.getElementById('zk-stat-duplicate');

        if (totalEl) totalEl.textContent = this.stats.total_punches || 0;
        if (appliedEl) appliedEl.textContent = this.stats.applied_count || 0;
        if (unmatchedEl) unmatchedEl.textContent = this.stats.unmatched_count || 0;
        if (dupEl) dupEl.textContent = this.stats.duplicate_count || 0;
    },

    renderDevices() {
        const container = document.getElementById('zk-devices-list');
        if (!container) return;

        if (this.devices.length === 0) {
            container.innerHTML = `<div style="font-size: 12px; color: var(--text-muted); padding: 8px;">No ZKTeco biometric devices registered yet.</div>`;
            return;
        }

        let html = '';
        this.devices.forEach(d => {
            const isOnline = d.is_online || d.status === 'online';
            const statusDot = isOnline ? '🟢 Online' : '⚪ Offline / Standby';
            const statusBg = isOnline ? 'rgba(16, 185, 129, 0.12)' : 'rgba(107, 114, 128, 0.12)';
            const statusColor = isOnline ? '#059669' : '#6b7280';

            html += `
                <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">📟</span>
                        <div>
                            <div style="font-weight: 800; font-size: 13.5px; color: var(--text-main);">${escapeHtml(d.device_name)}</div>
                            <div style="font-size: 11px; color: var(--text-muted); font-family: monospace; display: flex; gap: 8px; flex-wrap: wrap; margin-top: 2px;">
                                <span>SN: <b>${escapeHtml(d.serial_number || 'Auto-Detect')}</b></span>
                                <span>IP: <b>${escapeHtml(d.ip_address || '--')}</b></span>
                                <span>Port: <b>${d.port || 4370}</b></span>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; background: ${statusBg}; color: ${statusColor};">
                            ${statusDot}
                        </span>
                        <div style="font-size: 11px; color: var(--text-muted);">
                            Last seen: <b>${d.last_activity ? escapeHtml(d.last_activity) : 'Never'}</b>
                        </div>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    },

    renderLogsTable() {
        const tbody = document.getElementById('zk-logs-tbody');
        if (!tbody) return;

        if (!this.logs || this.logs.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No biometric punch activity logged for the selected criteria.</td></tr>`;
            return;
        }

        const verifyIcons = {
            fingerprint: '👆 Fingerprint',
            face: '👤 Face Recognition',
            card: '💳 RFID Card',
            password: '🔢 Password PIN',
            manual: '✍️ Manual'
        };

        const stateBadges = {
            check_in: { label: '🟢 Check In', bg: 'rgba(16, 185, 129, 0.12)', color: '#059669' },
            check_out: { label: '🔵 Check Out', bg: 'rgba(59, 130, 246, 0.12)', color: '#2563eb' },
            break_out: { label: '☕ Break Out', bg: 'rgba(245, 158, 11, 0.12)', color: '#d97706' },
            break_in: { label: '🏢 Break In', bg: 'rgba(16, 185, 129, 0.12)', color: '#059669' },
            auto: { label: '⚡ Smart Punch', bg: 'rgba(59, 130, 246, 0.12)', color: '#2563eb' }
        };

        const statusBadges = {
            applied: { label: 'Applied ✅', bg: 'rgba(16, 185, 129, 0.15)', color: '#059669' },
            unmatched: { label: 'Unmatched ⚠️', bg: 'rgba(239, 68, 68, 0.15)', color: '#dc2626' },
            duplicate: { label: 'Duplicate ⚪', bg: 'rgba(107, 114, 128, 0.12)', color: '#6b7280' },
            ignored: { label: 'Ignored ⚪', bg: 'rgba(107, 114, 128, 0.12)', color: '#6b7280' }
        };

        let html = '';
        this.logs.forEach(l => {
            const st = statusBadges[l.status] || statusBadges.applied;
            const stateObj = stateBadges[l.punch_state] || stateBadges.auto;
            const vIcon = verifyIcons[l.verify_type] || '👆 Biometric';
            const deptBadge = l.department_name ? `<span style="background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700;">${escapeHtml(l.department_name)}</span>` : '';
            const codeBadge = l.emp_code ? `<span style="background: rgba(100, 116, 139, 0.12); color: var(--text-muted); padding: 1px 5px; border-radius: 4px; font-size: 9.5px; font-weight: 700; font-family: monospace;">${escapeHtml(l.emp_code)}</span>` : '';

            // Format punch time
            let timeStr = l.punch_time || '';
            let dateStr = l.punch_date || '';
            if (timeStr && timeStr.includes(' ')) {
                const p = timeStr.split(' ');
                dateStr = p[0];
                timeStr = p[1];
            }

            html += `
                <tr>
                    <td style="white-space: nowrap; line-height: 1.2;">
                        <div style="font-weight: 700; font-size: 12px; color: var(--text-main); font-family: monospace;">${escapeHtml(timeStr)}</div>
                        <div style="font-size: 10px; color: var(--text-muted); font-family: monospace;">${escapeHtml(dateStr)}</div>
                    </td>
                    <td>
                        <div style="font-weight: 700; font-size: 12.5px; color: var(--text-main); display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span>${escapeHtml(l.employee_name || 'Unknown')}</span>
                            ${codeBadge}
                            ${deptBadge}
                        </div>
                        <div style="font-size: 10.5px; color: var(--text-muted); font-family: monospace; margin-top: 2px;">
                            PIN: <b>${escapeHtml(l.device_user_id)}</b> ${l.phone ? `| 📱 ${escapeHtml(l.phone)}` : ''}
                        </div>
                    </td>
                    <td style="white-space: nowrap;">
                        <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; background: ${stateObj.bg}; color: ${stateObj.color}; white-space: nowrap;">
                            ${stateObj.label}
                        </span>
                    </td>
                    <td style="white-space: nowrap; font-size: 11.5px; font-weight: 600; color: var(--text-main);">
                        ${vIcon}
                    </td>
                    <td>
                        <span style="font-size: 11px; color: var(--text-muted);">${escapeHtml(l.remarks || '--')}</span>
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

    renderSetupInfo(data) {
        const localIpEl = document.getElementById('zk-setup-local-ip');
        const portEl = document.getElementById('zk-setup-port');
        const urlEl = document.getElementById('zk-setup-url');

        if (localIpEl) localIpEl.textContent = data.detected_local_ip || '192.168.1.X';
        if (portEl) portEl.textContent = data.detected_port || '80';
        if (urlEl) urlEl.textContent = data.adms_cdata_url || 'http://localhost/Worksheet/iclock/cdata.php';
    },

    async populateSimEmployees() {
        const select = document.getElementById('zk-sim-employee');
        if (!select) return;

        try {
            const res = await fetch('api/employees.php?action=list');
            const data = await res.json();
            const emps = Array.isArray(data) ? data : (data.employees || []);

            let options = '<option value="">-- Select Employee --</option>';
            emps.forEach(e => {
                const code = e.emp_code || `DP-${String(e.id).padStart(3, '0')}`;
                options += `<option value="${e.id}" data-code="${escapeHtml(code)}" data-name="${escapeHtml(e.name)}">${escapeHtml(e.name)} (${escapeHtml(code)})</option>`;
            });
            select.innerHTML = options;

            select.addEventListener('change', () => {
                const opt = select.options[select.selectedIndex];
                const pinInput = document.getElementById('zk-sim-pin');
                if (opt && opt.value && pinInput) {
                    pinInput.value = opt.value; // set PIN to employee ID or code
                }
            });
        } catch (e) {
            console.error('Error populating sim employees:', e);
        }
    },

    async runSimulator(e) {
        if (e) e.preventDefault();
        const pin = document.getElementById('zk-sim-pin').value.trim();
        const verifyType = document.getElementById('zk-sim-verify-type').value;
        const state = document.getElementById('zk-sim-state').value;
        const timeInput = document.getElementById('zk-sim-time').value;
        const resultBox = document.getElementById('zk-sim-result-box');

        if (!pin) {
            showToast('Please enter an Employee PIN or Code (e.g. 25 or DP-025)', 'error');
            return;
        }

        try {
            const res = await fetch('api/zkteco_manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'simulate_punch',
                    pin: pin,
                    verify_type: verifyType,
                    punch_state: state,
                    punch_time: timeInput ? (document.getElementById('zk-sim-date').value + ' ' + timeInput + ':00') : ''
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                if (resultBox) {
                    resultBox.style.display = 'block';
                    resultBox.innerHTML = `
                        <div style="font-weight: 800; color: #059669; font-size: 13px; margin-bottom: 4px;">✅ Punch Simulated Successfully</div>
                        <div style="font-size: 11.5px; color: var(--text-main);"><b>Employee:</b> ${escapeHtml(data.result.employee)} (${escapeHtml(data.result.emp_code)})</div>
                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;"><b>Remarks:</b> ${escapeHtml(data.result.remarks)}</div>
                    `;
                }
                this.loadLogs();
            } else {
                showToast(data.message || 'Simulation failed', 'error');
            }
        } catch (err) {
            showToast('Error running biometric simulation: ' + err.message, 'error');
        }
    },

    openSetupModal() {
        openModal('zk-setup-modal');
        this.loadSettings();
    },

    openSimModal() {
        openModal('zk-sim-modal');
    },

    copyAdmsUrl() {
        const urlEl = document.getElementById('zk-setup-url');
        if (urlEl && urlEl.textContent) {
            navigator.clipboard.writeText(urlEl.textContent.trim());
            showToast('ADMS Webhook URL copied to clipboard!', 'success');
        }
    }
};

// Global helper for opening/closing modals safely
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}
