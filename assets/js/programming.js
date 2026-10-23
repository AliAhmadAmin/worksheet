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
let progIsInitializing = false;
let progIsInitialized = false;
let progAutoRefreshInterval = null;
let progAutoRefreshCountdown = 30;
let progAutoRefreshActive = true;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('tab-programming')) {
        initProgrammingModule();
    }
});

async function initProgrammingModule(force = false) {
    if (!force && (progIsInitializing || progIsInitialized)) return;
    progIsInitializing = true;
    try {
        await fetchProgMetadata();
        await loadProgrammingDispatches();
        progIsInitialized = true;
    } finally {
        progIsInitializing = false;
    }
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
    const elMovePt = document.getElementById('prog-stat-move-pt');
    const elCorr = document.getElementById('prog-stat-corrections');
    const elPend = document.getElementById('prog-stat-pending');

    if (elTotal) elTotal.textContent = stats.total_dispatches || 0;
    if (elDp) elDp.textContent = stats.dp_published || 0;
    if (elPt) elPt.textContent = stats.pt_published || 0;
    if (elMovePt) elMovePt.textContent = stats.moved_to_pt_count || 0;
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

function getStatusBadgeStyle(status, isPT = false, isBoth = false) {
    if (status === 'Published') {
        if (isBoth) return { bg: 'rgba(99, 102, 241, 0.12)', border: '1px solid rgba(99, 102, 241, 0.3)', color: '#4f46e5', label: '● Published (Both)' };
        if (isPT) return { bg: 'rgba(220, 38, 38, 0.12)', border: '1px solid rgba(220, 38, 38, 0.3)', color: '#dc2626', label: '● Published (PT)' };
        return { bg: 'rgba(22, 163, 74, 0.12)', border: '1px solid rgba(22, 163, 74, 0.3)', color: '#16a34a', label: '● Published (DP)' };
    }
    const map = {
        'In Progress': { bg: 'rgba(217, 119, 6, 0.12)', border: '1px solid rgba(217, 119, 6, 0.3)', color: '#b45309', label: '⚡ In Progress' },
        'Move to PT': { bg: 'rgba(180, 83, 9, 0.12)', border: '1px solid rgba(180, 83, 9, 0.3)', color: '#92400e', label: '➔ Move to PT' },
        'Violation': { bg: 'rgba(220, 38, 38, 0.12)', border: '1px solid rgba(220, 38, 38, 0.3)', color: '#dc2626', label: '● Violation' },
        'Correction': { bg: 'rgba(37, 99, 235, 0.12)', border: '1px solid rgba(37, 99, 235, 0.3)', color: '#2563eb', label: '● Correction' },
        'Expire': { bg: 'rgba(100, 116, 139, 0.12)', border: '1px solid rgba(100, 116, 139, 0.3)', color: '#475569', label: '● Expire' },
        'Rejected': { bg: 'rgba(71, 85, 105, 0.12)', border: '1px solid rgba(71, 85, 105, 0.3)', color: '#334155', label: '● Rejected' },
        'Pending': { bg: 'rgba(148, 163, 184, 0.12)', border: '1px solid rgba(148, 163, 184, 0.25)', color: '#64748b', label: '⏳ Pending' },
        'N/A': { bg: 'rgba(100, 116, 139, 0.08)', border: '1px solid rgba(100, 116, 139, 0.2)', color: '#94a3b8', label: '— N/A' }
    };
    return map[status] || { bg: 'rgba(148, 163, 184, 0.12)', border: '1px solid rgba(148, 163, 184, 0.25)', color: '#64748b', label: status || '⏳ Pending' };
}

function renderProgrammingMatrix(dispatches) {
    const tbody = document.getElementById('prog-matrix-tbody');
    if (!tbody) return;

    if (!dispatches || dispatches.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="13" style="text-align: center; padding: 45px 20px; color: var(--text-muted);">
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
        const isMovedToPt = (d.moved_to_pt == 1 || d.dp_status === 'Move to PT' || d.pt_status === 'Move to PT');
        const isPTChannel = (d.channel === 'Pakistan Today' || isMovedToPt);
        const isBothChannel = (d.channel === 'All Channels' || d.channel === 'Both');

        let currentStatus = 'Pending';
        if (d.pt_status === 'Published' || d.dp_status === 'Published on PT' || d.dp_status === 'Published') {
            currentStatus = 'Published';
        } else if (isMovedToPt) {
            currentStatus = 'Move to PT';
        } else if (d.dp_status === 'In Progress' || d.pt_status === 'In Progress') {
            currentStatus = 'In Progress';
        } else if (d.dp_status === 'Correction' || d.pt_status === 'Correction') {
            currentStatus = 'Correction';
        } else if (d.dp_status === 'Violation' || d.pt_status === 'Violation') {
            currentStatus = 'Violation';
        } else if (d.dp_status === 'Expire' || d.pt_status === 'Expire') {
            currentStatus = 'Expire';
        } else if (d.dp_status === 'Rejected' || d.pt_status === 'Rejected') {
            currentStatus = 'Rejected';
        } else {
            currentStatus = d.dp_status || d.pt_status || 'Pending';
        }

        const isPublished = (currentStatus === 'Published');
        const publishTime = d.pt_time || d.dp_time || '-';

        // Executive Row background & border highlight
        let rowBg = idx % 2 === 0 ? 'transparent' : 'rgba(0,0,0,0.015)';
        let rowBorderLeft = 'none';

        if (isPublished) {
            if (isBothChannel) {
                // Both: elegant indigo/violet tint
                rowBg = 'rgba(99, 102, 241, 0.055)';
                rowBorderLeft = '4px solid #6366f1';
            } else if (isPTChannel) {
                // Pakistan Today: elegant soft red/rose tint
                rowBg = 'rgba(220, 38, 38, 0.055)';
                rowBorderLeft = '4px solid #dc2626';
            } else {
                // Discover Pakistan: elegant soft emerald/green tint
                rowBg = 'rgba(22, 163, 74, 0.055)';
                rowBorderLeft = '4px solid #16a34a';
            }
        } else if (isMovedToPt) {
            // Moved to PT: soft warm amber tint
            rowBg = 'rgba(245, 158, 11, 0.055)';
            rowBorderLeft = '4px solid #f59e0b';
        }

        const statusStyle = getStatusBadgeStyle(currentStatus, isPTChannel, isBothChannel);

        const channelBadge = isBothChannel
            ? `<span style="background: rgba(99, 102, 241, 0.12); color: #6366f1; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;">🌐 Both</span>`
            : (d.channel === 'Pakistan Today'
                ? `<span style="background: rgba(220, 38, 38, 0.12); color: #dc2626; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;">🔴 PT</span>`
                : `<span style="background: rgba(22, 163, 74, 0.12); color: #16a34a; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;">🟢 DP</span>`);

        // Format Date nicely
        const dateFormatted = d.dispatch_date ? new Date(d.dispatch_date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';

        // Star rating
        let ratingHtml = '-';
        if (d.rating) {
            ratingHtml = `<span style="color: #f59e0b; font-weight: 700; font-size: 12px;" title="${escapeHtml(d.rating)}/5">⭐ ${escapeHtml(d.rating)}</span>`;
        }

        const dpLinkHtml = d.link ? `
            <a href="${escapeHtml(d.link)}" target="_blank" rel="noopener noreferrer" 
               style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 4px; background: rgba(22, 163, 74, 0.12); color: #16a34a; font-weight: 700; font-size: 11px; text-decoration: none; border: 1px solid rgba(22, 163, 74, 0.25); white-space: nowrap;" 
               title="${escapeHtml(d.link)}">
                🔗 DP Link
            </a>
        ` : '<span style="color: var(--text-dim); font-size: 12px; white-space: nowrap;">—</span>';

        const ptLinkHtml = d.pt_link ? `
            <a href="${escapeHtml(d.pt_link)}" target="_blank" rel="noopener noreferrer" 
               style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 4px; background: rgba(220, 38, 38, 0.12); color: #dc2626; font-weight: 700; font-size: 11px; text-decoration: none; border: 1px solid rgba(220, 38, 38, 0.25); white-space: nowrap;" 
               title="${escapeHtml(d.pt_link)}">
                🔗 PT Link
            </a>
        ` : '<span style="color: var(--text-dim); font-size: 12px; white-space: nowrap;">—</span>';

        // Sender & Publisher Avatars
        const senderAvatar = d.sender_avatar 
            ? `<img src="${escapeHtml(d.sender_avatar)}" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;" alt="">`
            : `<span style="width: 22px; height: 22px; border-radius: 50%; background: #d97706; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700;">${(d.sender_name || 'P').charAt(0).toUpperCase()}</span>`;

        const pubAvatar = d.publisher_avatar 
            ? `<img src="${escapeHtml(d.publisher_avatar)}" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;" alt="">`
            : `<span style="width: 22px; height: 22px; border-radius: 50%; background: #15803d; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700;">${(d.publisher_name || 'D').charAt(0).toUpperCase()}</span>`;

        const isClaimable = canManage && (!d.publisher_id || !d.publisher_name || d.publisher_name === '—');
        const publisherHtml = isClaimable ? `
            <button type="button" class="btn-claim-story" onclick="claimProgStory(${d.id})" title="Claim this dispatch to your name and start digital production">
                ⚡ Claim
            </button>
        ` : `
            <span style="font-size: 12px; font-weight: 600; color: var(--text-main);">${escapeHtml(d.publisher_name || '—')}</span>
        `;

        const statusHtml = canManage 
            ? `
                <select class="prog-status-select" style="background: ${statusStyle.bg}; color: ${statusStyle.color}; border: ${statusStyle.border};" onchange="quickUpdateProgStatus(${d.id}, 'status', this.value)" title="Change Publishing Status">
                    <option value="Pending" ${currentStatus === 'Pending' ? 'selected' : ''}>⏳ Pending</option>
                    <option value="In Progress" ${currentStatus === 'In Progress' ? 'selected' : ''}>⚡ In Progress</option>
                    <option value="Published" ${currentStatus === 'Published' ? 'selected' : ''}>● ${isPTChannel ? 'Published (PT)' : (isBothChannel ? 'Published (Both)' : 'Published (DP)')}</option>
                    <option value="Move to PT" ${currentStatus === 'Move to PT' ? 'selected' : ''}>➔ Move to PT</option>
                    <option value="Violation" ${currentStatus === 'Violation' ? 'selected' : ''}>● Violation</option>
                    <option value="Correction" ${currentStatus === 'Correction' ? 'selected' : ''}>● Correction</option>
                    <option value="Expire" ${currentStatus === 'Expire' ? 'selected' : ''}>● Expire</option>
                    <option value="Rejected" ${currentStatus === 'Rejected' ? 'selected' : ''}>● Rejected</option>
                </select>
            `
            : `
                <span class="prog-status-pill" style="background: ${statusStyle.bg}; color: ${statusStyle.color}; border: ${statusStyle.border};" title="Publishing status is managed by Digital Department">
                    ${escapeHtml(statusStyle.label)}
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
            <tr style="border-bottom: 1px solid var(--border-color); background: ${rowBg}; border-left: ${rowBorderLeft};">
                <!-- 1. Date (Sticky Column) -->
                <td class="sticky-col-date" style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); white-space: nowrap;">
                    ${escapeHtml(dateFormatted)}
                </td>

                <!-- 2. Title (Sticky Column) -->
                <td class="sticky-col-title" style="font-weight: 700; color: var(--text-main); font-size: 12.5px;">
                    <div style="max-width: 175px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(d.title)}">
                        ${escapeHtml(d.title)}
                    </div>
                </td>

                <!-- 3. Path / WhatsApp (with Copy Button) -->
                <td>
                    <div style="display: flex; align-items: center; gap: 4px; max-width: 125px;">
                        <code style="font-size: 10.5px; font-family: monospace; color: var(--text-muted); background: var(--bg-input); padding: 2px 4px; border-radius: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 90px;" title="${escapeHtml(d.path_whatsapp || '')}">
                            ${escapeHtml(d.path_whatsapp || '—')}
                        </code>
                        ${d.path_whatsapp ? `
                            <button type="button" class="btn-icon" style="padding: 1px 4px; font-size: 10px; background: transparent; border: 1px solid var(--border-color); border-radius: 3px; cursor: pointer; flex-shrink: 0;" onclick="copyProgPath(event, '${escapeHtml(d.path_whatsapp).replace(/'/g, "\\'")}')" title="Copy Path">
                                📋
                            </button>
                        ` : ''}
                    </div>
                </td>

                <!-- 4. Channel -->
                <td style="text-align: center; white-space: nowrap; padding: 6px 4px;">
                    ${channelBadge}
                </td>

                <!-- 5. News Sender & Dispatch Time -->
                <td style="white-space: nowrap; padding: 6px 6px;">
                    <div style="display: flex; flex-direction: column; line-height: 1.2;">
                        <span style="font-size: 12px; font-weight: 700; color: var(--text-main);">${escapeHtml(d.sender_name || 'Producer')}</span>
                        <span style="font-size: 10px; font-family: monospace; color: var(--text-dim); margin-top: 1px;">
                            🕒 ${escapeHtml(d.dispatch_time || '-')}
                        </span>
                    </div>
                </td>

                <!-- 6. DP Link -->
                <td style="text-align: center; vertical-align: middle; white-space: nowrap;">
                    ${dpLinkHtml}
                </td>

                <!-- 7. PT Link (Border separator to Digital Zone) -->
                <td style="text-align: center; vertical-align: middle; white-space: nowrap; border-right: 3px solid rgba(0,0,0,0.2);">
                    ${ptLinkHtml}
                </td>

                <!-- 8. Publishing Status -->
                <td>
                    ${statusHtml}
                </td>

                <!-- 9. Publishing Time -->
                <td style="text-align: center; font-size: 11px; font-family: monospace; color: ${publishTime !== '-' ? 'var(--text-main, #334155)' : 'var(--text-dim)'}; font-weight: 600; white-space: nowrap; padding: 6px 4px;">
                    ${escapeHtml(publishTime)}
                </td>

                <!-- 10. Digital Publisher / Claim Action -->
                <td style="white-space: nowrap;">
                    ${publisherHtml}
                </td>

                <!-- 11. Remarks -->
                <td>
                    <div style="font-size: 11.5px; color: var(--text-muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(d.remarks || '')}">
                        ${escapeHtml(d.remarks || '—')}
                    </div>
                </td>

                <!-- 12. Rating -->
                <td style="text-align: center; white-space: nowrap;">
                    ${ratingHtml}
                </td>

                <!-- 13. Action -->
                <td style="text-align: center; white-space: nowrap;">
                    ${actionHtml}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

// ⚡ 1-Click Dispatch Claim for Digital Desk
async function claimProgStory(id) {
    if (!id) return;
    try {
        const res = await fetch('api/programming.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'claim_story', id: id })
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || "Dispatch claimed successfully!", "success");
            loadProgrammingDispatches(true);
        } else {
            showToast(data.message || "Failed to claim dispatch.", "error");
        }
    } catch (err) {
        console.error("Error claiming dispatch:", err);
        showToast("Network error claiming dispatch.", "error");
    }
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
        let payload = { action: 'update_status', id: id };
        if (field === 'status') {
            payload.status = value;
        } else {
            payload[field] = value;
        }

        const res = await fetch('api/programming.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            showToast("Publishing status updated.", "success");
            if (data.stats) updateProgKpis(data.stats);
            loadProgrammingDispatches(true);
            if (typeof updateGlobalSidebarBadges === 'function') updateGlobalSidebarBadges();
        } else {
            showToast(data.message || "Failed to update status", "error");
        }
    } catch (err) {
        showToast("Status update failed.", "error");
    }
}

// 🔗 1-Click / OnChange Quick Update for Published Story Link
async function quickUpdateProgLink(id, field, value) {
    if (!id) return;
    let updateField = 'link';
    let updateVal = value;
    if (typeof field === 'string' && (field === 'link' || field === 'pt_link')) {
        updateField = field;
    } else if (typeof field === 'string') {
        updateVal = field;
    }

    try {
        const res = await fetch('api/programming.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_link',
                id: id,
                field: updateField,
                val: (updateVal || '').trim()
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`Story ${updateField === 'pt_link' ? 'PT' : 'DP'} link saved.`, "success");
            const item = ProgState.dispatches.find(d => d.id == id);
            if (item) {
                if (updateField === 'pt_link') item.pt_link = (updateVal || '').trim();
                else item.link = (updateVal || '').trim();
                renderProgrammingMatrix(ProgState.dispatches);
            }
        } else {
            showToast(data.message || "Failed to save link", "error");
        }
    } catch (err) {
        showToast("Network error saving story link.", "error");
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

    const isMovedToPt = (item.moved_to_pt == 1 || item.dp_status === 'Move to PT' || item.pt_status === 'Move to PT');
    const chkMoved = document.getElementById('edit-prog-moved-to-pt');
    if (chkMoved) chkMoved.checked = isMovedToPt;

    let modalStatus = 'Pending';
    if (item.pt_status === 'Published' || item.dp_status === 'Published on PT' || item.dp_status === 'Published') {
        modalStatus = 'Published';
    } else if (isMovedToPt) {
        modalStatus = 'Move to PT';
    } else if (item.dp_status === 'In Progress' || item.pt_status === 'In Progress') {
        modalStatus = 'In Progress';
    } else if (item.dp_status === 'Correction' || item.pt_status === 'Correction') {
        modalStatus = 'Correction';
    } else if (item.dp_status === 'Violation' || item.pt_status === 'Violation') {
        modalStatus = 'Violation';
    } else if (item.dp_status === 'Expire' || item.pt_status === 'Expire') {
        modalStatus = 'Expire';
    } else if (item.dp_status === 'Rejected' || item.pt_status === 'Rejected') {
        modalStatus = 'Rejected';
    } else {
        modalStatus = item.dp_status || item.pt_status || 'Pending';
    }

    const statusSel = document.getElementById('edit-prog-status');
    if (statusSel) statusSel.value = modalStatus;

    const pubTimeInput = document.getElementById('edit-prog-publish-time');
    if (pubTimeInput) pubTimeInput.value = item.pt_time || item.dp_time || '';

    if (document.getElementById('edit-prog-dp-status')) document.getElementById('edit-prog-dp-status').value = item.dp_status || '';
    if (document.getElementById('edit-prog-dp-time')) document.getElementById('edit-prog-dp-time').value = item.dp_time || '';
    if (document.getElementById('edit-prog-pt-status')) document.getElementById('edit-prog-pt-status').value = item.pt_status || '';
    if (document.getElementById('edit-prog-pt-time')) document.getElementById('edit-prog-pt-time').value = item.pt_time || '';
    document.getElementById('edit-prog-publisher').value = item.publisher_id || '';
    document.getElementById('edit-prog-rating').value = item.rating || '';
    document.getElementById('edit-prog-remarks').value = item.remarks || '';

    const linkInput = document.getElementById('edit-prog-link');
    if (linkInput) linkInput.value = item.link || '';
    updateEditProgLinkButton(item.link || '', 'edit-prog-link-btn');

    const ptLinkInput = document.getElementById('edit-prog-pt-link');
    if (ptLinkInput) ptLinkInput.value = item.pt_link || '';
    updateEditProgLinkButton(item.pt_link || '', 'edit-prog-pt-link-btn');

    openModal('edit-dispatch-modal');
}

function updateEditProgLinkButton(val, btnId = 'edit-prog-link-btn') {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    const clean = (val || '').trim();
    if (clean) {
        btn.href = clean;
        btn.style.display = 'inline-flex';
    } else {
        btn.style.display = 'none';
    }
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
        link: document.getElementById('edit-prog-link')?.value.trim(),
        pt_link: document.getElementById('edit-prog-pt-link')?.value.trim(),
        status: document.getElementById('edit-prog-status')?.value || '',
        publish_time: document.getElementById('edit-prog-publish-time')?.value.trim() || '',
        moved_to_pt: document.getElementById('edit-prog-moved-to-pt')?.checked ? 1 : 0,
        dp_status: document.getElementById('edit-prog-dp-status')?.value || '',
        dp_time: document.getElementById('edit-prog-dp-time')?.value.trim() || '',
        pt_status: document.getElementById('edit-prog-pt-status')?.value || '',
        pt_time: document.getElementById('edit-prog-pt-time')?.value.trim() || '',
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

    const confirmed = await showConfirmDialog({
        title: "Delete Dispatch Record",
        message: "Are you sure you want to permanently delete this dispatch record?",
        detail: "This entry will be permanently removed from Programming Handover.",
        confirmText: "Delete Record",
        cancelText: "Cancel",
        type: "danger",
        icon: "🗑️"
    });
    if (!confirmed) return;

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
        "ID", "Date", "Title", "Path_WhatsApp", "Channel",
        "Sender", "Dispatch_Time", "DP_Link", "PT_Link", "Status", "Publish_Time",
        "Publisher", "Remarks", "Rating"
    ];

    const rows = ProgState.dispatches.map(d => {
        const isMovedToPt = (d.moved_to_pt == 1 || d.dp_status === 'Move to PT' || d.pt_status === 'Move to PT');
        let status = 'Pending';
        if (d.pt_status === 'Published' || d.dp_status === 'Published on PT' || d.dp_status === 'Published') {
            status = 'Published';
        } else if (isMovedToPt) {
            status = 'Move to PT';
        } else if (d.dp_status === 'In Progress' || d.pt_status === 'In Progress') {
            status = 'In Progress';
        } else {
            status = d.dp_status || d.pt_status || 'Pending';
        }
        const pubTime = d.pt_time || d.dp_time || '';

        return [
            d.id,
            `"${(d.dispatch_date || '').replace(/"/g, '""')}"`,
            `"${(d.title || '').replace(/"/g, '""')}"`,
            `"${(d.path_whatsapp || '').replace(/"/g, '""')}"`,
            `"${(d.channel || '').replace(/"/g, '""')}"`,
            `"${(d.sender_name || '').replace(/"/g, '""')}"`,
            `"${(d.dispatch_time || '').replace(/"/g, '""')}"`,
            `"${(d.link || '').replace(/"/g, '""')}"`,
            `"${(d.pt_link || '').replace(/"/g, '""')}"`,
            `"${status}"`,
            `"${pubTime.replace(/"/g, '""')}"`,
            `"${(d.publisher_name || '').replace(/"/g, '""')}"`,
            `"${(d.remarks || '').replace(/"/g, '""')}"`,
            `"${(d.rating || '').replace(/"/g, '""')}"`
        ];
    });

    const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + [headers.join(","), ...rows.map(r => r.join(","))].join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `Programming_Digital_Handover_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast("📊 CSV file exported successfully!", "success");
}

// 🖨️ Print Dispatch Matrix
function printProgrammingReport() {
    window.print();
}
