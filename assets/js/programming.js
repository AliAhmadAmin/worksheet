/**
 * Programming & Digital Handover Management Module
 * Real-time content dispatching, media path clipboard copying, and live digital publishing matrix
 */

let ProgState = {
    dispatches: [],
    stats: {},
    senders: [],
    publishers: [],
    dpStatuses: [],
    ptStatuses: [],
    channels: [],
    selectedDate: '',
    selectedMonth: '',
    selectedChannel: 'all',
    selectedStatus: 'all',
    searchQuery: '',
    searchTimeout: null,
    currentEditId: null
};

// Auto-initialize when tab is loaded or page opened
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('tab-programming')) {
        initProgrammingModule();
    }
});

async function initProgrammingModule() {
    await fetchProgMetadata();
    await loadProgrammingDispatches();
}

async function fetchProgMetadata() {
    try {
        const res = await fetch('api/programming.php?action=get_meta');
        const data = await res.json();
        if (data.success) {
            ProgState.senders = data.senders || [];
            ProgState.publishers = data.publishers || [];
            ProgState.dpStatuses = data.dp_statuses || [];
            ProgState.ptStatuses = data.pt_statuses || [];
            ProgState.channels = data.channels || [];

            populateProgDropdowns();
        }
    } catch (err) {
        console.error("Error fetching programming metadata:", err);
    }
}

function populateProgDropdowns() {
    const senderSelect = document.getElementById('dispatch-sender');
    if (senderSelect) {
        senderSelect.innerHTML = ProgState.senders.map(s => `
            <option value="${s.id}">${escapeHtml(s.name)} (${escapeHtml(s.designation || 'Producer')})</option>
        `).join('');

        // If current user is in senders, select them by default
        if (AppState.currentUser && AppState.currentUser.id) {
            const match = ProgState.senders.find(s => s.id === AppState.currentUser.id);
            if (match) senderSelect.value = match.id;
        }
    }

    const publisherSelect = document.getElementById('edit-prog-publisher');
    if (publisherSelect) {
        publisherSelect.innerHTML = `<option value="">-- Select Publisher --</option>` + ProgState.publishers.map(p => `
            <option value="${p.id}">${escapeHtml(p.name)} (${escapeHtml(p.designation || 'Digital Lead')})</option>
        `).join('');
    }
}

async function loadProgrammingDispatches() {
    const tbody = document.getElementById('prog-matrix-tbody');
    if (!tbody) return;

    let url = 'api/programming.php?action=list';
    if (ProgState.selectedDate) url += `&date=${encodeURIComponent(ProgState.selectedDate)}`;
    else if (ProgState.selectedMonth) url += `&month=${encodeURIComponent(ProgState.selectedMonth)}`;

    const channelVal = document.getElementById('prog-filter-channel')?.value || 'all';
    if (channelVal !== 'all') url += `&channel=${encodeURIComponent(channelVal)}`;

    const statusVal = document.getElementById('prog-filter-status')?.value || 'all';
    if (statusVal !== 'all') url += `&status=${encodeURIComponent(statusVal)}`;

    const searchVal = document.getElementById('prog-search-input')?.value.trim() || '';
    if (searchVal) url += `&search=${encodeURIComponent(searchVal)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="15" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading records.')}</td></tr>`;
            return;
        }

        ProgState.dispatches = data.dispatches || [];
        ProgState.stats = data.stats || {};
        ProgState.currentUser = data.current_user || {};
        ProgState.canManageDigital = (data.current_user && data.current_user.can_manage_digital === true);

        updateProgKpis(data.stats);
        renderProgrammingMatrix(ProgState.dispatches);

    } catch (err) {
        console.error("Error loading programming records:", err);
        tbody.innerHTML = `<tr><td colspan="15" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading dispatch pipeline.</td></tr>`;
    }
}

function updateProgKpis(stats) {
    if (!stats) return;
    const elTotal = document.getElementById('prog-stat-total');
    const elDp = document.getElementById('prog-stat-dp');
    const elPt = document.getElementById('prog-stat-pt');
    const elCorr = document.getElementById('prog-stat-corrections');
    const elPend = document.getElementById('prog-stat-pending');

    if (elTotal) elTotal.textContent = stats.total_dispatches || 0;
    if (elDp) elDp.textContent = stats.dp_published || 0;
    if (elPt) elPt.textContent = stats.pt_published || 0;
    if (elCorr) elCorr.textContent = stats.total_corrections || 0;
    if (elPend) elPend.textContent = stats.pending_queue || 0;

    // Update sidebar badge for Programming Handover
    const progBadges = [document.getElementById('pending-programming-badge'), document.getElementById('pending-prog-badge-pg')].filter(Boolean);
    const count = parseInt(stats.pending_queue) || 0;
    progBadges.forEach(badge => {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';
    });
}

function getStatusBadgeStyle(status) {
    const map = {
        'Published': { bg: '#16a34a', color: '#ffffff' },
        'Published on PT': { bg: '#15803d', color: '#ffffff' },
        'Violation': { bg: '#dc2626', color: '#ffffff' },
        'Correction': { bg: '#2563eb', color: '#ffffff' },
        'Move to PT': { bg: '#7f1d1d', color: '#ffffff' },
        'Expire': { bg: '#b91c1c', color: '#ffffff' },
        'Rejected': { bg: '#78350f', color: '#ffffff' },
        'N/A': { bg: 'rgba(100, 116, 139, 0.15)', color: '#94a3b8' }
    };
    return map[status] || { bg: 'rgba(148, 163, 184, 0.15)', color: '#64748b' };
}

function renderProgrammingMatrix(dispatches) {
    const tbody = document.getElementById('prog-matrix-tbody');
    if (!tbody) return;

    if (!dispatches || dispatches.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="15" style="text-align: center; padding: 45px 20px; color: var(--text-muted);">
                    <div style="font-size: 32px; margin-bottom: 8px;">📡</div>
                    <div style="font-weight: 700; font-size: 15px; color: var(--text-main);">No Content Dispatches Found</div>
                    <div style="font-size: 12.5px; margin-top: 3px;">Click "➕ Dispatch New Content" above to send new program clips to Digital.</div>
                </td>
            </tr>
        `;
        return;
    }

    const canManage = (ProgState.canManageDigital === true) || (AppState.currentUser && (['admin', 'super_admin'].includes(AppState.currentUser.role) || (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase().includes('digital'))));

    let html = '';
    dispatches.forEach((d, idx) => {
        const dpStyle = getStatusBadgeStyle(d.dp_status);
        const ptStyle = getStatusBadgeStyle(d.pt_status);

        const channelBadge = d.channel === 'Pakistan Today'
            ? `<span style="background: rgba(220, 38, 38, 0.12); color: #dc2626; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;">🔴 PT</span>`
            : `<span style="background: rgba(22, 163, 74, 0.12); color: #16a34a; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;">🟢 DP</span>`;

        // Format Date nicely
        const dateFormatted = d.dispatch_date ? new Date(d.dispatch_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';

        // Star rating
        let ratingHtml = '-';
        if (d.rating) {
            ratingHtml = `<span style="color: #f59e0b; font-weight: 700; font-size: 12px;" title="${escapeHtml(d.rating)}/5">⭐ ${escapeHtml(d.rating)}</span>`;
        }

        const linkHtml = d.link
            ? `<a href="${escapeHtml(d.link)}" target="_blank" rel="noopener noreferrer" style="color: var(--primary); font-size: 14px;" title="Open Content Link">🔗</a>`
            : `<span style="color: var(--text-dim);">-</span>`;

        // Sender & Publisher Avatars
        const senderAvatar = d.sender_avatar 
            ? `<img src="${escapeHtml(d.sender_avatar)}" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;" alt="">`
            : `<span style="width: 22px; height: 22px; border-radius: 50%; background: #d97706; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700;">${(d.sender_name || 'P').charAt(0).toUpperCase()}</span>`;

        const pubAvatar = d.publisher_avatar 
            ? `<img src="${escapeHtml(d.publisher_avatar)}" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;" alt="">`
            : `<span style="width: 22px; height: 22px; border-radius: 50%; background: #15803d; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700;">${(d.publisher_name || 'D').charAt(0).toUpperCase()}</span>`;

        // Status Elements: Interactive for Digital/Admin, Read-only Badges for Programming Staff
        const dpStatusHtml = canManage 
            ? `
                <select class="prog-status-select" style="background: ${dpStyle.bg}; color: ${dpStyle.color};" onchange="quickUpdateProgStatus(${d.id}, 'dp_status', this.value)" title="Change DP Status">
                    <option value="" ${!d.dp_status ? 'selected' : ''} style="background: #334155; color: #f8fafc;">⏳ Pending</option>
                    <option value="Published" ${d.dp_status === 'Published' ? 'selected' : ''} style="background: #16a34a; color: #ffffff;">🟢 Published</option>
                    <option value="Violation" ${d.dp_status === 'Violation' ? 'selected' : ''} style="background: #dc2626; color: #ffffff;">🔴 Violation</option>
                    <option value="Correction" ${d.dp_status === 'Correction' ? 'selected' : ''} style="background: #2563eb; color: #ffffff;">🔵 Correction</option>
                    <option value="Move to PT" ${d.dp_status === 'Move to PT' ? 'selected' : ''} style="background: #7f1d1d; color: #ffffff;">🟤 Move to PT</option>
                    <option value="Published on PT" ${d.dp_status === 'Published on PT' ? 'selected' : ''} style="background: #15803d; color: #ffffff;">🟢 Published on PT</option>
                    <option value="Expire" ${d.dp_status === 'Expire' ? 'selected' : ''} style="background: #b91c1c; color: #ffffff;">⛔ Expire</option>
                    <option value="Rejected" ${d.dp_status === 'Rejected' ? 'selected' : ''} style="background: #78350f; color: #ffffff;">🟤 Rejected</option>
                    <option value="N/A" ${d.dp_status === 'N/A' ? 'selected' : ''} style="background: #1e293b; color: #94a3b8;">— N/A</option>
                </select>
            `
            : `
                <span class="prog-status-pill" style="background: ${dpStyle.bg}; color: ${dpStyle.color};" title="Publishing status is managed by Digital Department">
                    ${d.dp_status === 'N/A' ? '— N/A' : escapeHtml(d.dp_status || '⏳ Pending')}
                </span>
            `;

        const ptStatusHtml = canManage 
            ? `
                <select class="prog-status-select" style="background: ${ptStyle.bg}; color: ${ptStyle.color};" onchange="quickUpdateProgStatus(${d.id}, 'pt_status', this.value)" title="Change PT Status">
                    <option value="" ${!d.pt_status ? 'selected' : ''} style="background: #334155; color: #f8fafc;">⏳ Pending</option>
                    <option value="Published" ${d.pt_status === 'Published' ? 'selected' : ''} style="background: #16a34a; color: #ffffff;">🟢 Published</option>
                    <option value="Violation" ${d.pt_status === 'Violation' ? 'selected' : ''} style="background: #dc2626; color: #ffffff;">🔴 Violation</option>
                    <option value="Correction" ${d.pt_status === 'Correction' ? 'selected' : ''} style="background: #2563eb; color: #ffffff;">🔵 Correction</option>
                    <option value="Expire" ${d.pt_status === 'Expire' ? 'selected' : ''} style="background: #b91c1c; color: #ffffff;">⛔ Expire</option>
                    <option value="Rejected" ${d.pt_status === 'Rejected' ? 'selected' : ''} style="background: #78350f; color: #ffffff;">🟤 Rejected</option>
                    <option value="N/A" ${d.pt_status === 'N/A' ? 'selected' : ''} style="background: #1e293b; color: #94a3b8;">— N/A</option>
                </select>
            `
            : `
                <span class="prog-status-pill" style="background: ${ptStyle.bg}; color: ${ptStyle.color};" title="Publishing status is managed by Digital Department">
                    ${d.pt_status === 'N/A' ? '— N/A' : escapeHtml(d.pt_status || '⏳ Pending')}
                </span>
            `;

        const actionHtml = canManage
            ? `
                <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; font-weight: 600;" onclick="openEditDispatchModal(${d.id})" title="Edit Publishing & Handover Record">
                    ✏️ Edit
                </button>
            `
            : `
                <span style="color: var(--text-dim); font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;" title="Dispatched to Digital Department">
                    🔒 Dispatched
                </span>
            `;

        html += `
            <tr style="border-bottom: 1px solid var(--border-color); background: ${idx % 2 === 0 ? 'transparent' : 'rgba(0,0,0,0.015)'};">
                <!-- 1. Date -->
                <td style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); white-space: nowrap;">
                    ${escapeHtml(dateFormatted)}
                </td>

                <!-- 2. Title -->
                <td style="font-weight: 700; color: var(--text-main); font-size: 13px;">
                    <div style="max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(d.title)}">
                        ${escapeHtml(d.title)}
                    </div>
                </td>

                <!-- 3. Path / WhatsApp (with Copy Button) -->
                <td>
                    <div style="display: flex; align-items: center; gap: 6px; max-width: 260px;">
                        <code style="font-size: 11px; font-family: monospace; color: var(--text-muted); background: var(--bg-input); padding: 2px 6px; border-radius: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;" title="${escapeHtml(d.path_whatsapp || '')}">
                            ${escapeHtml(d.path_whatsapp || '—')}
                        </code>
                        ${d.path_whatsapp ? `
                            <button type="button" class="btn-icon" style="padding: 2px 5px; font-size: 11px; background: transparent; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer;" onclick="copyProgPath(event, '${escapeHtml(d.path_whatsapp).replace(/'/g, "\\'")}')" title="Copy Path to Clipboard">
                                📋
                            </button>
                        ` : ''}
                    </div>
                </td>

                <!-- 4. Channel -->
                <td style="white-space: nowrap;">
                    ${channelBadge}
                </td>

                <!-- 5. News Sender -->
                <td style="white-space: nowrap;">
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600;">
                        ${senderAvatar}
                        <span>${escapeHtml(d.sender_name || 'Producer')}</span>
                    </div>
                </td>

                <!-- 6. Dispatch Time -->
                <td style="font-size: 11px; font-family: monospace; color: var(--text-dim); white-space: nowrap;">
                    ${escapeHtml(d.dispatch_time || '-')}
                </td>

                <!-- 7. Link (Border separator to Digital Zone) -->
                <td style="text-align: center; border-right: 3px solid var(--border-color);">
                    ${linkHtml}
                </td>

                <!-- 8. DP Status -->
                <td>
                    ${dpStatusHtml}
                </td>

                <!-- 9. DP Time -->
                <td style="font-size: 11px; font-family: monospace; color: ${d.dp_time ? '#16a34a' : 'var(--text-dim)'}; font-weight: 600; white-space: nowrap;">
                    ${escapeHtml(d.dp_time || '-')}
                </td>

                <!-- 10. PT Status -->
                <td>
                    ${ptStatusHtml}
                </td>

                <!-- 11. PT Time -->
                <td style="font-size: 11px; font-family: monospace; color: ${d.pt_time ? '#059669' : 'var(--text-dim)'}; font-weight: 600; white-space: nowrap;">
                    ${escapeHtml(d.pt_time || '-')}
                </td>

                <!-- 12. Digital Publisher -->
                <td style="white-space: nowrap;">
                    <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600;">
                        ${pubAvatar}
                        <span>${escapeHtml(d.publisher_name || '—')}</span>
                    </div>
                </td>

                <!-- 13. Remarks -->
                <td>
                    <div style="font-size: 11.5px; color: var(--text-muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(d.remarks || '')}">
                        ${escapeHtml(d.remarks || '—')}
                    </div>
                </td>

                <!-- 14. Rating -->
                <td style="text-align: center; white-space: nowrap;">
                    ${ratingHtml}
                </td>

                <!-- 15. Action -->
                <td style="text-align: center; white-space: nowrap;">
                    ${actionHtml}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

// 📋 Fast Clipboard Path Copying
function copyProgPath(event, text) {
    if (event) event.stopPropagation();
    if (!text) return;

    navigator.clipboard.writeText(text).then(() => {
        showToast("📋 Network Path copied to clipboard!", "success");
    }).catch(() => {
        // Fallback
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        showToast("📋 Path copied!", "success");
    });
}

// ⚡ 1-Click Status Quick Update from Table Dropdown
async function quickUpdateProgStatus(id, field, value) {
    try {
        const payload = {
            action: 'update_status',
            id: id,
            [field]: value
        };

        const res = await fetch('api/programming.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast("Publishing status updated.", "success");
            loadProgrammingDispatches();
            if (typeof updateGlobalSidebarBadges === 'function') updateGlobalSidebarBadges();
        } else {
            showToast(data.message || "Failed to update status", "error");
        }
    } catch (err) {
        showToast("Status update failed.", "error");
    }
}

// ➕ Open Dispatch Content Modal
function openDispatchModal() {
    const modal = document.getElementById('dispatch-content-modal');
    const form = document.getElementById('dispatch-content-form');
    if (form) form.reset();

    const dateInput = document.getElementById('dispatch-date');
    if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];

    const timeInput = document.getElementById('dispatch-time');
    if (timeInput) timeInput.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    // Handle sender selection: auto-lock to logged-in user unless HOD/Admin
    const senderSelect = document.getElementById('dispatch-sender');
    if (senderSelect) {
        const user = AppState.currentUser || ProgState.currentUser;
        if (user && user.id) {
            senderSelect.value = user.id;
        }
        const isElevated = user && (['hod', 'admin', 'super_admin'].includes(user.role) || (user.designation && (user.designation.toLowerCase().includes('hod') || user.designation.toLowerCase().includes('director'))));
        senderSelect.disabled = !isElevated;
        if (!isElevated) {
            senderSelect.style.background = 'var(--bg-input)';
            senderSelect.style.cursor = 'not-allowed';
            senderSelect.title = 'Sender is automatically locked to your user account';
        } else {
            senderSelect.style.background = '';
            senderSelect.style.cursor = '';
            senderSelect.title = '';
        }
    }

    openModal('dispatch-content-modal');
}

// 🚀 Handle New Dispatch Submission
async function handleDispatchSubmit(event) {
    event.preventDefault();

    const title = document.getElementById('dispatch-title')?.value.trim();
    const path = document.getElementById('dispatch-path')?.value.trim();
    const channel = document.getElementById('dispatch-channel')?.value;
    const senderSelect = document.getElementById('dispatch-sender');
    const senderId = (senderSelect ? senderSelect.value : null) || AppState.currentUser?.id;
    const date = document.getElementById('dispatch-date')?.value;
    const time = document.getElementById('dispatch-time')?.value.trim();
    const link = document.getElementById('dispatch-link')?.value.trim();

    if (!title) {
        showToast("Title is required.", "error");
        return;
    }

    try {
        const res = await fetch('api/programming.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create',
                title: title,
                path_whatsapp: path,
                channel: channel,
                sender_id: senderId,
                dispatch_date: date,
                dispatch_time: time,
                link: link
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            closeModal('dispatch-content-modal');
            loadProgrammingDispatches();
        } else {
            showToast(data.message || "Dispatch failed.", "error");
        }
    } catch (err) {
        showToast("Network error creating dispatch.", "error");
    }
}

// ✏️ Open Full Edit Modal
function openEditDispatchModal(id) {
    const canManage = (ProgState.canManageDigital === true) || (AppState.currentUser && (['admin', 'super_admin'].includes(AppState.currentUser.role) || (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase().includes('digital'))));
    if (!canManage) {
        showToast("Access Denied: Programming staff cannot edit records once dispatched to Digital.", "warning");
        return;
    }

    const item = ProgState.dispatches.find(d => d.id === id);
    if (!item) return;

    ProgState.currentEditId = id;

    document.getElementById('edit-prog-id').value = item.id;
    document.getElementById('edit-prog-title').value = item.title || '';
    document.getElementById('edit-prog-channel').value = item.channel || 'Discover Pakistan';
    document.getElementById('edit-prog-path').value = item.path_whatsapp || '';
    document.getElementById('edit-prog-date').value = item.dispatch_date || '';
    document.getElementById('edit-prog-sender').value = item.sender_name || '';
    document.getElementById('edit-prog-time').value = item.dispatch_time || '';
    document.getElementById('edit-prog-dp-status').value = item.dp_status || '';
    document.getElementById('edit-prog-dp-time').value = item.dp_time || '';
    document.getElementById('edit-prog-pt-status').value = item.pt_status || '';
    document.getElementById('edit-prog-pt-time').value = item.pt_time || '';
    document.getElementById('edit-prog-publisher').value = item.publisher_id || '';
    document.getElementById('edit-prog-rating').value = item.rating || '';
    document.getElementById('edit-prog-remarks').value = item.remarks || '';

    openModal('edit-dispatch-modal');
}

// Auto-fill time in edit modal when status is picked
function handleModalDpStatusChange(status) {
    const timeInput = document.getElementById('edit-prog-dp-time');
    if (timeInput && status && !timeInput.value) {
        timeInput.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
}

function handleModalPtStatusChange(status) {
    const timeInput = document.getElementById('edit-prog-pt-time');
    if (timeInput && status && !timeInput.value) {
        timeInput.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    if (status === 'Published') {
        const dpSelect = document.getElementById('edit-prog-dp-status');
        if (dpSelect && (!dpSelect.value || dpSelect.value === 'Pending' || dpSelect.value === 'Move to PT')) {
            dpSelect.value = 'Published on PT';
            const dpTime = document.getElementById('edit-prog-dp-time');
            if (dpTime && !dpTime.value) {
                dpTime.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }
    }
}

// 💾 Save Full Edit Submission
async function handleEditDispatchSubmit(event) {
    event.preventDefault();

    const id = document.getElementById('edit-prog-id')?.value;
    if (!id) return;

    const payload = {
        action: 'update_status',
        id: id,
        title: document.getElementById('edit-prog-title')?.value.trim(),
        channel: document.getElementById('edit-prog-channel')?.value,
        path_whatsapp: document.getElementById('edit-prog-path')?.value.trim(),
        dispatch_date: document.getElementById('edit-prog-date')?.value,
        sender_name: document.getElementById('edit-prog-sender')?.value.trim(),
        dispatch_time: document.getElementById('edit-prog-time')?.value.trim(),
        dp_status: document.getElementById('edit-prog-dp-status')?.value,
        dp_time: document.getElementById('edit-prog-dp-time')?.value.trim(),
        pt_status: document.getElementById('edit-prog-pt-status')?.value,
        pt_time: document.getElementById('edit-prog-pt-time')?.value.trim(),
        publisher_id: document.getElementById('edit-prog-publisher')?.value,
        rating: document.getElementById('edit-prog-rating')?.value,
        remarks: document.getElementById('edit-prog-remarks')?.value.trim()
    };

    try {
        const res = await fetch('api/programming.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast("Record updated successfully.", "success");
            closeModal('edit-dispatch-modal');
            loadProgrammingDispatches();
        } else {
            showToast(data.message || "Failed to update record.", "error");
        }
    } catch (err) {
        showToast("Update failed.", "error");
    }
}

// 🗑️ Delete Dispatch
async function deleteCurrentDispatch() {
    const id = ProgState.currentEditId;
    if (!id) return;

    if (!confirm("Are you sure you want to delete this dispatch record permanently?")) {
        return;
    }

    try {
        const res = await fetch('api/programming.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        const data = await res.json();

        if (data.success) {
            showToast("Record deleted.", "info");
            closeModal('edit-dispatch-modal');
            loadProgrammingDispatches();
        } else {
            showToast(data.message || "Delete failed.", "error");
        }
    } catch (err) {
        showToast("Error deleting record.", "error");
    }
}

// Filter change handling
function handleProgFilterChange(type) {
    if (type === 'date') {
        ProgState.selectedDate = document.getElementById('prog-filter-date')?.value || '';
        ProgState.selectedMonth = '';
        const monthInput = document.getElementById('prog-filter-month');
        if (monthInput) monthInput.value = '';
    } else if (type === 'month') {
        ProgState.selectedMonth = document.getElementById('prog-filter-month')?.value || '';
        ProgState.selectedDate = '';
        const dateInput = document.getElementById('prog-filter-date');
        if (dateInput) dateInput.value = '';
    }
    loadProgrammingDispatches();
}

function resetProgFilters() {
    ProgState.selectedDate = '';
    ProgState.selectedMonth = '';
    const dateInput = document.getElementById('prog-filter-date');
    if (dateInput) dateInput.value = '';
    const monthInput = document.getElementById('prog-filter-month');
    if (monthInput) monthInput.value = '';
    const channelSelect = document.getElementById('prog-filter-channel');
    if (channelSelect) channelSelect.value = 'all';
    const statusSelect = document.getElementById('prog-filter-status');
    if (statusSelect) statusSelect.value = 'all';
    const searchInput = document.getElementById('prog-search-input');
    if (searchInput) searchInput.value = '';

    loadProgrammingDispatches();
}

function debounceProgSearch() {
    clearTimeout(ProgState.searchTimeout);
    ProgState.searchTimeout = setTimeout(() => {
        loadProgrammingDispatches();
    }, 300);
}

// 📊 Export to CSV
function exportProgrammingCsv() {
    if (!ProgState.dispatches || ProgState.dispatches.length === 0) {
        showToast("No records available to export.", "warning");
        return;
    }

    const headers = [
        "Date", "Title", "Path / WhatsApp", "Channel", "News Sender", "Dispatch Time", "Link",
        "DP Status", "DP Time", "PT Status", "PT Time", "Digital Publisher", "Remarks", "Rating"
    ];

    const rows = ProgState.dispatches.map(d => [
        `"${d.dispatch_date || ''}"`,
        `"${(d.title || '').replace(/"/g, '""')}"`,
        `"${(d.path_whatsapp || '').replace(/"/g, '""')}"`,
        `"${d.channel || ''}"`,
        `"${d.sender_name || ''}"`,
        `"${d.dispatch_time || ''}"`,
        `"${d.link || ''}"`,
        `"${d.dp_status || ''}"`,
        `"${d.dp_time || ''}"`,
        `"${d.pt_status || ''}"`,
        `"${d.pt_time || ''}"`,
        `"${d.publisher_name || ''}"`,
        `"${(d.remarks || '').replace(/"/g, '""')}"`,
        `"${d.rating || ''}"`
    ]);

    const csvContent = "data:text/csv;charset=utf-8," + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `programming_digital_handover_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showToast("📊 CSV file exported successfully!", "success");
}

// 🖨️ Print Dispatch Matrix
function printProgrammingReport() {
    window.print();
}
