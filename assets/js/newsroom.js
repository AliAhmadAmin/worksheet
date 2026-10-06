/**
 * News Room & Digital Handover Management Module
 * Real-time story dispatching, media path clipboard copying, and live digital publishing matrix
 */

let NewsState = {
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
    currentEditId: null,
    canManageDigital: false
};

// Auto-initialize when tab is loaded or page opened
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('tab-newsroom')) {
        initNewsroomModule();
    }
});

async function initNewsroomModule() {
    await fetchNewsMetadata();
    await loadNewsroomDispatches();
}

async function fetchNewsMetadata() {
    try {
        const res = await fetch('api/newsroom.php?action=get_meta');
        const data = await res.json();
        if (data.success) {
            NewsState.senders = data.senders || [];
            NewsState.publishers = data.publishers || [];
            NewsState.dpStatuses = data.dp_statuses || [];
            NewsState.ptStatuses = data.pt_statuses || [];
            NewsState.channels = data.channels || [];

            populateNewsDropdowns();
        }
    } catch (err) {
        console.error("Error fetching newsroom metadata:", err);
    }
}

function populateNewsDropdowns() {
    const senderSelect = document.getElementById('news-dispatch-sender');
    if (senderSelect) {
        senderSelect.innerHTML = NewsState.senders.map(s => `
            <option value="${s.id}">${escapeHtml(s.name)} (${escapeHtml(s.designation || 'News Member')})</option>
        `).join('');

        if (AppState.currentUser && AppState.currentUser.id) {
            const match = NewsState.senders.find(s => s.id === AppState.currentUser.id);
            if (match) senderSelect.value = match.id;
        }
    }

    const publisherSelect = document.getElementById('edit-news-publisher');
    if (publisherSelect) {
        publisherSelect.innerHTML = `<option value="">-- Select Publisher --</option>` + NewsState.publishers.map(p => `
            <option value="${p.id}">${escapeHtml(p.name)} (${escapeHtml(p.designation || 'Digital Lead')})</option>
        `).join('');
    }
}

async function loadNewsroomDispatches() {
    const tbody = document.getElementById('news-matrix-tbody');
    if (!tbody) return;

    let url = 'api/newsroom.php?action=list';
    if (NewsState.selectedDate) url += `&date=${encodeURIComponent(NewsState.selectedDate)}`;
    else if (NewsState.selectedMonth) url += `&month=${encodeURIComponent(NewsState.selectedMonth)}`;

    const channelVal = document.getElementById('news-filter-channel')?.value || 'all';
    if (channelVal !== 'all') url += `&channel=${encodeURIComponent(channelVal)}`;

    const statusVal = document.getElementById('news-filter-status')?.value || 'all';
    if (statusVal !== 'all') url += `&status=${encodeURIComponent(statusVal)}`;

    const searchVal = document.getElementById('news-search-input')?.value.trim() || '';
    if (searchVal) url += `&search=${encodeURIComponent(searchVal)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="15" style="text-align: center; color: #ef4444; padding: 25px;">${escapeHtml(data.message || 'Error loading news records.')}</td></tr>`;
            return;
        }

        NewsState.dispatches = data.dispatches || [];
        NewsState.stats = data.stats || {};
        NewsState.currentUser = data.current_user || {};
        NewsState.canManageDigital = (data.current_user && data.current_user.can_manage_digital === true);

        updateNewsKpis(data.stats);
        renderNewsroomMatrix(NewsState.dispatches);

    } catch (err) {
        console.error("Error loading newsroom records:", err);
        tbody.innerHTML = `<tr><td colspan="15" style="text-align: center; color: #ef4444; padding: 25px;">Network error loading news dispatch pipeline.</td></tr>`;
    }
}

function updateNewsKpis(stats) {
    if (!stats) return;
    const elTotal = document.getElementById('news-stat-total');
    const elDp = document.getElementById('news-stat-dp');
    const elPt = document.getElementById('news-stat-pt');
    const elCorr = document.getElementById('news-stat-corrections');
    const elPend = document.getElementById('news-stat-pending');

    if (elTotal) elTotal.textContent = stats.total_dispatches || 0;
    if (elDp) elDp.textContent = stats.dp_published || 0;
    if (elPt) elPt.textContent = stats.pt_published || 0;
    if (elCorr) elCorr.textContent = stats.total_corrections || 0;
    if (elPend) elPend.textContent = stats.pending_queue || 0;

    // Update sidebar badge for News Handover
    const newsBadges = [document.getElementById('pending-news-badge'), document.getElementById('pending-news-badge-nr')].filter(Boolean);
    const count = parseInt(stats.pending_queue) || 0;
    newsBadges.forEach(badge => {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';
    });
}

function getNewsStatusBadgeStyle(status) {
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

function renderNewsroomMatrix(dispatches) {
    const tbody = document.getElementById('news-matrix-tbody');
    if (!tbody) return;

    if (!dispatches || dispatches.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="15" style="text-align: center; padding: 45px 20px; color: var(--text-muted);">
                    <div style="font-size: 32px; margin-bottom: 8px;">📺</div>
                    <div style="font-weight: 700; font-size: 15px; color: var(--text-main);">No News Dispatches Found</div>
                    <div style="font-size: 12.5px; margin-top: 3px;">Click "➕ Dispatch New Story" above to send new news packages to Digital.</div>
                </td>
            </tr>
        `;
        return;
    }

    const canManage = (NewsState.canManageDigital === true) || (AppState.currentUser && (['admin', 'super_admin'].includes(AppState.currentUser.role) || (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase().includes('digital'))));

    let html = '';
    dispatches.forEach((d, idx) => {
        const dpStyle = getNewsStatusBadgeStyle(d.dp_status);
        const ptStyle = getNewsStatusBadgeStyle(d.pt_status);

        const channelBadge = d.channel === 'Pakistan Today'
            ? `<span style="background: rgba(220, 38, 38, 0.12); color: #dc2626; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;">🔴 PT</span>`
            : `<span style="background: rgba(22, 163, 74, 0.12); color: #16a34a; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;">🟢 DP</span>`;

        const dateFormatted = d.dispatch_date ? new Date(d.dispatch_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';

        let ratingHtml = '-';
        if (d.rating) {
            ratingHtml = `<span style="color: #f59e0b; font-weight: 700; font-size: 12px;" title="${escapeHtml(d.rating)}/5">⭐ ${escapeHtml(d.rating)}</span>`;
        }

        const linkHtml = d.link
            ? `<a href="${escapeHtml(d.link)}" target="_blank" rel="noopener noreferrer" style="color: var(--primary); font-size: 14px;" title="Open News Link">🔗</a>`
            : `<span style="color: var(--text-dim);">-</span>`;

        const senderAvatar = d.sender_avatar 
            ? `<img src="${escapeHtml(d.sender_avatar)}" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;" alt="">`
            : `<span style="width: 22px; height: 22px; border-radius: 50%; background: #dc2626; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700;">${(d.sender_name || 'N').charAt(0).toUpperCase()}</span>`;

        const pubAvatar = d.publisher_avatar 
            ? `<img src="${escapeHtml(d.publisher_avatar)}" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;" alt="">`
            : `<span style="width: 22px; height: 22px; border-radius: 50%; background: #15803d; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700;">${(d.publisher_name || 'D').charAt(0).toUpperCase()}</span>`;

        const dpStatusHtml = canManage 
            ? `
                <select class="news-status-select" style="background: ${dpStyle.bg}; color: ${dpStyle.color};" onchange="quickUpdateNewsStatus(${d.id}, 'dp_status', this.value)" title="Change DP Status">
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
                <span class="news-status-pill" style="background: ${dpStyle.bg}; color: ${dpStyle.color};" title="Publishing status is managed by Digital Department">
                    ${d.dp_status === 'N/A' ? '— N/A' : escapeHtml(d.dp_status || '⏳ Pending')}
                </span>
            `;

        const ptStatusHtml = canManage 
            ? `
                <select class="news-status-select" style="background: ${ptStyle.bg}; color: ${ptStyle.color};" onchange="quickUpdateNewsStatus(${d.id}, 'pt_status', this.value)" title="Change PT Status">
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
                <span class="news-status-pill" style="background: ${ptStyle.bg}; color: ${ptStyle.color};" title="Publishing status is managed by Digital Department">
                    ${d.pt_status === 'N/A' ? '— N/A' : escapeHtml(d.pt_status || '⏳ Pending')}
                </span>
            `;

        const actionHtml = canManage
            ? `
                <button type="button" class="btn btn-outline" style="padding: 3px 8px; font-size: 11px; font-weight: 600;" onclick="openEditNewsDispatchModal(${d.id})" title="Edit Publishing & Handover Record">
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

                <!-- 2. Title / Slug -->
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
                            <button type="button" class="btn-icon" style="padding: 2px 5px; font-size: 11px; background: transparent; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer;" onclick="copyNewsPath(event, '${escapeHtml(d.path_whatsapp).replace(/'/g, "\\'")}')" title="Copy Path to Clipboard">
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
                        <span>${escapeHtml(d.sender_name || 'News Producer')}</span>
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

// 📋 Clipboard Path Copying
function copyNewsPath(event, text) {
    if (event) event.stopPropagation();
    if (!text) return;

    navigator.clipboard.writeText(text).then(() => {
        showToast("📋 Network Path copied to clipboard!", "success");
    }).catch(() => {
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        showToast("📋 Path copied!", "success");
    });
}

// ⚡ Quick Status Update
async function quickUpdateNewsStatus(id, field, value) {
    try {
        const payload = {
            action: 'update_status',
            id: id,
            [field]: value
        };

        const res = await fetch('api/newsroom.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast("Publishing status updated.", "success");
            loadNewsroomDispatches();
            if (typeof updateGlobalSidebarBadges === 'function') updateGlobalSidebarBadges();
        } else {
            showToast(data.message || "Failed to update status", "error");
        }
    } catch (err) {
        showToast("Status update failed.", "error");
    }
}

// ➕ Open News Dispatch Modal
function openNewsDispatchModal() {
    const form = document.getElementById('dispatch-news-form');
    if (form) form.reset();

    const dateInput = document.getElementById('news-dispatch-date');
    if (dateInput) dateInput.value = new Date().toISOString().split('T')[0];

    const timeInput = document.getElementById('news-dispatch-time');
    if (timeInput) timeInput.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    // Handle sender selection: auto-lock to logged-in user unless HOD/Admin
    const senderSelect = document.getElementById('news-dispatch-sender');
    if (senderSelect) {
        const user = AppState.currentUser || NewsState.currentUser;
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

    openModal('dispatch-news-modal');
}

// 🚀 Handle New Story Submission
async function handleNewsDispatchSubmit(event) {
    event.preventDefault();

    const title = document.getElementById('news-dispatch-title')?.value.trim();
    const path = document.getElementById('news-dispatch-path')?.value.trim();
    const channel = document.getElementById('news-dispatch-channel')?.value;
    const senderSelect = document.getElementById('news-dispatch-sender');
    const senderId = (senderSelect ? senderSelect.value : null) || AppState.currentUser?.id;
    const date = document.getElementById('news-dispatch-date')?.value;
    const time = document.getElementById('news-dispatch-time')?.value.trim();
    const link = document.getElementById('news-dispatch-link')?.value.trim();

    if (!title) {
        showToast("Story title is required.", "error");
        return;
    }

    try {
        const res = await fetch('api/newsroom.php', {
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
            closeModal('dispatch-news-modal');
            loadNewsroomDispatches();
        } else {
            showToast(data.message || "Dispatch failed.", "error");
        }
    } catch (err) {
        showToast("Network error dispatching story.", "error");
    }
}

// ✏️ Open Full Edit Modal
function openEditNewsDispatchModal(id) {
    const canManage = (NewsState.canManageDigital === true) || (AppState.currentUser && (['admin', 'super_admin'].includes(AppState.currentUser.role) || (AppState.currentUser.department_name && AppState.currentUser.department_name.toLowerCase().includes('digital'))));
    if (!canManage) {
        showToast("Access Denied: News Room staff cannot edit records once dispatched to Digital.", "warning");
        return;
    }

    const item = NewsState.dispatches.find(d => d.id === id);
    if (!item) return;

    NewsState.currentEditId = id;

    document.getElementById('edit-news-id').value = item.id;
    document.getElementById('edit-news-title').value = item.title || '';
    document.getElementById('edit-news-channel').value = item.channel || 'Discover Pakistan';
    document.getElementById('edit-news-path').value = item.path_whatsapp || '';
    document.getElementById('edit-news-date').value = item.dispatch_date || '';
    document.getElementById('edit-news-sender').value = item.sender_name || '';
    document.getElementById('edit-news-time').value = item.dispatch_time || '';
    document.getElementById('edit-news-dp-status').value = item.dp_status || '';
    document.getElementById('edit-news-dp-time').value = item.dp_time || '';
    document.getElementById('edit-news-pt-status').value = item.pt_status || '';
    document.getElementById('edit-news-pt-time').value = item.pt_time || '';
    document.getElementById('edit-news-publisher').value = item.publisher_id || '';
    document.getElementById('edit-news-rating').value = item.rating || '';
    document.getElementById('edit-news-remarks').value = item.remarks || '';

    openModal('edit-news-dispatch-modal');
}

function handleNewsModalDpStatusChange(status) {
    const timeInput = document.getElementById('edit-news-dp-time');
    if (timeInput && status && !timeInput.value) {
        timeInput.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
}

function handleNewsModalPtStatusChange(status) {
    const timeInput = document.getElementById('edit-news-pt-time');
    if (timeInput && status && !timeInput.value) {
        timeInput.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    if (status === 'Published') {
        const dpSelect = document.getElementById('edit-news-dp-status');
        if (dpSelect && (!dpSelect.value || dpSelect.value === 'Pending' || dpSelect.value === 'Move to PT')) {
            dpSelect.value = 'Published on PT';
            const dpTime = document.getElementById('edit-news-dp-time');
            if (dpTime && !dpTime.value) {
                dpTime.value = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }
    }
}

// 💾 Save Edit Changes
async function handleEditNewsSubmit(event) {
    event.preventDefault();
    const id = document.getElementById('edit-news-id')?.value;
    if (!id) return;

    const payload = {
        action: 'update_dispatch',
        id: id,
        title: document.getElementById('edit-news-title')?.value.trim(),
        channel: document.getElementById('edit-news-channel')?.value,
        path_whatsapp: document.getElementById('edit-news-path')?.value.trim(),
        dispatch_date: document.getElementById('edit-news-date')?.value,
        dispatch_time: document.getElementById('edit-news-time')?.value.trim(),
        dp_status: document.getElementById('edit-news-dp-status')?.value,
        dp_time: document.getElementById('edit-news-dp-time')?.value.trim(),
        pt_status: document.getElementById('edit-news-pt-status')?.value,
        pt_time: document.getElementById('edit-news-pt-time')?.value.trim(),
        publisher_id: document.getElementById('edit-news-publisher')?.value,
        rating: document.getElementById('edit-news-rating')?.value,
        remarks: document.getElementById('edit-news-remarks')?.value.trim()
    };

    try {
        const res = await fetch('api/newsroom.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast("Record updated successfully.", "success");
            closeModal('edit-news-dispatch-modal');
            loadNewsroomDispatches();
        } else {
            showToast(data.message || "Failed to update record.", "error");
        }
    } catch (err) {
        showToast("Network error updating dispatch record.", "error");
    }
}

// 🗑️ Delete Story Dispatch
async function deleteNewsDispatch() {
    if (!NewsState.currentEditId) return;
    if (!confirm("Are you sure you want to permanently delete this news dispatch record?")) return;

    try {
        const res = await fetch('api/newsroom.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                id: NewsState.currentEditId
            })
        });
        const data = await res.json();

        if (data.success) {
            showToast("Record deleted successfully.", "success");
            closeModal('edit-news-dispatch-modal');
            loadNewsroomDispatches();
        } else {
            showToast(data.message || "Failed to delete record.", "error");
        }
    } catch (err) {
        showToast("Error deleting record.", "error");
    }
}

// 🔍 Filter Controls
function handleNewsFilterChange(type) {
    if (type === 'date') {
        NewsState.selectedDate = document.getElementById('news-filter-date').value;
        NewsState.selectedMonth = '';
        const mEl = document.getElementById('news-filter-month');
        if (mEl) mEl.value = '';
    } else if (type === 'month') {
        NewsState.selectedMonth = document.getElementById('news-filter-month').value;
        NewsState.selectedDate = '';
        const dEl = document.getElementById('news-filter-date');
        if (dEl) dEl.value = '';
    }
    loadNewsroomDispatches();
}

function debounceNewsSearch() {
    clearTimeout(NewsState.searchTimeout);
    NewsState.searchTimeout = setTimeout(() => {
        loadNewsroomDispatches();
    }, 300);
}

function resetNewsFilters() {
    NewsState.selectedDate = '';
    NewsState.selectedMonth = '';
    const dEl = document.getElementById('news-filter-date');
    if (dEl) dEl.value = '';
    const mEl = document.getElementById('news-filter-month');
    if (mEl) mEl.value = '';
    const cEl = document.getElementById('news-filter-channel');
    if (cEl) cEl.value = 'all';
    const sEl = document.getElementById('news-filter-status');
    if (sEl) sEl.value = 'all';
    const qEl = document.getElementById('news-search-input');
    if (qEl) qEl.value = '';
    loadNewsroomDispatches();
}

// 📊 Export to CSV
function exportNewsroomCsv() {
    if (!NewsState.dispatches || NewsState.dispatches.length === 0) {
        showToast("No records available to export.", "warning");
        return;
    }

    const headers = [
        "ID", "Date", "Title", "Path_WhatsApp", "Channel",
        "Sender", "Dispatch_Time", "DP_Status", "DP_Time",
        "PT_Status", "PT_Time", "Publisher", "Remarks", "Rating"
    ];

    const rows = NewsState.dispatches.map(d => [
        d.id,
        `"${(d.dispatch_date || '').replace(/"/g, '""')}"`,
        `"${(d.title || '').replace(/"/g, '""')}"`,
        `"${(d.path_whatsapp || '').replace(/"/g, '""')}"`,
        `"${(d.channel || '').replace(/"/g, '""')}"`,
        `"${(d.sender_name || '').replace(/"/g, '""')}"`,
        `"${(d.dispatch_time || '').replace(/"/g, '""')}"`,
        `"${(d.dp_status || '').replace(/"/g, '""')}"`,
        `"${(d.dp_time || '').replace(/"/g, '""')}"`,
        `"${(d.pt_status || '').replace(/"/g, '""')}"`,
        `"${(d.pt_time || '').replace(/"/g, '""')}"`,
        `"${(d.publisher_name || '').replace(/"/g, '""')}"`,
        `"${(d.remarks || '').replace(/"/g, '""')}"`,
        `"${(d.rating || '').replace(/"/g, '""')}"`
    ]);

    const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + [headers.join(","), ...rows.map(r => r.join(","))].join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `NewsRoom_Digital_Handover_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast("📊 CSV file exported successfully!", "success");
}

// 🖨️ Print Report
function printNewsroomReport() {
    window.print();
}
