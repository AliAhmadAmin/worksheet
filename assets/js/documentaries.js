/**
 * Discover Pakistan UHD TV - Documentaries Portal Controller
 * Collaborative desk between Programming (Filing/Meta) & Digital (Social Media Distribution)
 */

(function () {
    let documentaries = [];
    let searchTimeout = null;
    let staffMembers = [];

    // Helper: Escape HTML
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Helper: Format Date
    function formatDateDisplay(dateStr) {
        if (!dateStr) return '—';
        try {
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                const d = new Date(parts[0], parts[1] - 1, parts[2]);
                const day = String(d.getDate()).padStart(2, '0');
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                return `${day}-${months[d.getMonth()]}-${String(d.getFullYear()).slice(-2)}`;
            }
            return dateStr;
        } catch (e) {
            return dateStr;
        }
    }

    // Badge styling for Type
    function getTypeBadge(type) {
        const t = (type || 'Full').trim();
        if (t === 'Full') {
            return `<span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #059669; font-weight: 800; padding: 4px 10px; border-radius: 6px; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(16, 185, 129, 0.3);">🟢 Full</span>`;
        }
        if (t === 'Half') {
            return `<span class="badge" style="background: rgba(2, 132, 199, 0.15); color: #0284c7; font-weight: 800; padding: 4px 10px; border-radius: 6px; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(2, 132, 199, 0.3);">🔵 Half</span>`;
        }
        return `<span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; font-weight: 800; padding: 4px 10px; border-radius: 6px; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(245, 158, 11, 0.3);">🟠 Short</span>`;
    }

    // Load documentaries from server
    async function loadDocumentaries() {
        const loadingEl = document.getElementById('doc-table-loading');
        const emptyEl = document.getElementById('doc-table-empty');
        const tableEl = document.getElementById('doc-main-table');
        const tbodyEl = document.getElementById('doc-table-tbody');

        if (loadingEl) loadingEl.style.display = 'block';
        if (emptyEl) emptyEl.style.display = 'none';

        const search = (document.getElementById('doc-search-input')?.value || '').trim();
        const typeFilter = document.getElementById('doc-filter-type')?.value || 'all';
        const statusFilter = document.getElementById('doc-filter-status')?.value || 'all';
        const producerFilter = document.getElementById('doc-filter-producer')?.value || 'all';

        // Clear button toggle
        const clearBtn = document.getElementById('doc-search-clear');
        if (clearBtn) {
            clearBtn.style.display = search.length > 0 ? 'inline-block' : 'none';
        }

        const params = new URLSearchParams({
            action: 'list',
            search: search,
            type: typeFilter,
            status: statusFilter,
            producer: producerFilter
        });

        try {
            const res = await fetch(`api/documentaries.php?${params.toString()}`);
            const data = await res.json();

            if (loadingEl) loadingEl.style.display = 'none';

            if (!data.success) {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Error loading documentaries.', 'error');
                }
                return;
            }

            documentaries = data.documentaries || [];

            // Update KPI Cards
            updateKpiStats(data.stats);

            // Populate Producers Filter
            populateProducerFilter(data.producers, producerFilter);

            // Render Table
            renderTable(documentaries);

        } catch (err) {
            if (loadingEl) loadingEl.style.display = 'none';
            console.error('Failed to fetch documentaries:', err);
            if (typeof showToast === 'function') {
                showToast('Failed to connect to server.', 'error');
            }
        }
    }
    window.loadDocumentaries = loadDocumentaries;

    // Update KPI summary cards
    function updateKpiStats(stats) {
        if (!stats) return;
        const totalEl = document.getElementById('stat-doc-total');
        const fullEl = document.getElementById('stat-doc-full');
        const halfEl = document.getElementById('stat-doc-half');
        const shortEl = document.getElementById('stat-doc-short');
        const pendingEl = document.getElementById('stat-doc-pending');
        const completeEl = document.getElementById('stat-doc-complete');

        if (totalEl) totalEl.textContent = stats.total_count ?? 0;
        if (fullEl) fullEl.textContent = stats.full_count ?? 0;
        if (halfEl) halfEl.textContent = stats.half_count ?? 0;
        if (shortEl) shortEl.textContent = stats.short_count ?? 0;
        if (pendingEl) pendingEl.textContent = stats.pending_digital_count ?? 0;
        if (completeEl) completeEl.textContent = stats.complete_digital_count ?? 0;
    }

    // Populate producer filter dropdown
    function populateProducerFilter(producers, currentVal) {
        const select = document.getElementById('doc-filter-producer');
        if (!select || !producers) return;

        const currentSelection = currentVal || select.value || 'all';
        let html = '<option value="all">👤 All Producers</option>';
        producers.forEach(p => {
            if (p) {
                const selected = (p === currentSelection) ? 'selected' : '';
                html += `<option value="${escapeHtml(p)}" ${selected}>${escapeHtml(p)}</option>`;
            }
        });
        select.innerHTML = html;
    }

    // Render interactive table
    function renderTable(docs) {
        const tbody = document.getElementById('doc-table-tbody');
        const table = document.getElementById('doc-main-table');
        const emptyEl = document.getElementById('doc-table-empty');
        const countEl = document.getElementById('doc-showing-count');

        if (countEl) countEl.textContent = docs.length;

        if (!tbody) return;

        if (!docs || docs.length === 0) {
            tbody.innerHTML = '';
            if (table) table.style.display = 'none';
            if (emptyEl) emptyEl.style.display = 'block';
            return;
        }

        if (table) table.style.display = 'table';
        if (emptyEl) emptyEl.style.display = 'none';

        let html = '';
        docs.forEach(doc => {
            const hasYt = Boolean(doc.youtube_url && doc.youtube_url.trim());
            const hasFb = Boolean(doc.facebook_url && doc.facebook_url.trim());

            // FB & YT Links single combined cell
            const linksCell = `
                <div style="display: flex; flex-direction: column; gap: 4px; min-width: 130px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        ${hasFb ? `
                            <a href="${escapeHtml(doc.facebook_url)}" target="_blank" rel="noopener noreferrer" 
                               style="background: rgba(24, 119, 242, 0.12); color: #1877f2; border: 1px solid rgba(24, 119, 242, 0.3); border-radius: 5px; padding: 2px 7px; font-size: 11px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" 
                               title="${escapeHtml(doc.facebook_url)}">
                                <span>📘</span> FB Link
                            </a>
                        ` : `
                            <button type="button" 
                                    onclick="openDigitalLinksModal(${doc.id}, '${escapeHtml(doc.title).replace(/'/g, "\\'")}', '${escapeHtml(doc.youtube_url || '').replace(/'/g, "\\'")}', '')" 
                                    style="background: rgba(24, 119, 242, 0.05); color: #1877f2; border: 1px dashed rgba(24, 119, 242, 0.35); border-radius: 5px; padding: 2px 7px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                <span>+</span> Add FB
                            </button>
                        `}
                        <button type="button" onclick="openDigitalLinksModal(${doc.id}, '${escapeHtml(doc.title).replace(/'/g, "\\'")}', '${escapeHtml(doc.youtube_url || '').replace(/'/g, "\\'")}', '${escapeHtml(doc.facebook_url || '').replace(/'/g, "\\'")}')" style="background: transparent; border: none; cursor: pointer; font-size: 12px; color: var(--text-muted); padding: 1px 3px;" title="Update Links">✏️</button>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                        ${hasYt ? `
                            <a href="${escapeHtml(doc.youtube_url)}" target="_blank" rel="noopener noreferrer" 
                               style="background: rgba(239, 68, 68, 0.12); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 5px; padding: 2px 7px; font-size: 11px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" 
                               title="${escapeHtml(doc.youtube_url)}">
                                <span>▶️</span> YT Link
                            </a>
                        ` : `
                            <button type="button" 
                                    onclick="openDigitalLinksModal(${doc.id}, '${escapeHtml(doc.title).replace(/'/g, "\\'")}', '', '${escapeHtml(doc.facebook_url || '').replace(/'/g, "\\'")}')" 
                                    style="background: rgba(239, 68, 68, 0.05); color: #dc2626; border: 1px dashed rgba(239, 68, 68, 0.35); border-radius: 5px; padding: 2px 7px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                <span>+</span> Add YT
                            </button>
                        `}
                    </div>
                </div>
            `;

            // Network path clean preview with copy button
            const rawTitle = doc.title || '';
            const isPath = rawTitle.includes('\\') || rawTitle.includes('/');
            const displayTitle = escapeHtml(rawTitle);

            html += `
                <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s ease;" onmouseover="this.style.background='var(--bg-hover, rgba(0,0,0,0.02))'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 10px 12px; text-align: center; font-weight: 800; color: var(--text-muted);">
                        ${escapeHtml(doc.sr_no || doc.id)}
                    </td>
                    <td style="padding: 10px 12px; font-family: ${isPath ? 'monospace' : 'inherit'}; font-size: 12px; color: var(--text-main); word-break: break-all;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 6px;">
                            <span>${displayTitle}</span>
                            <button type="button" onclick="copyFilePath('${escapeHtml(rawTitle).replace(/'/g, "\\'")}')" style="background: transparent; border: none; cursor: pointer; font-size: 13px; color: var(--text-muted); padding: 2px 4px; flex-shrink: 0;" title="Copy Network Path / Title">📋</button>
                        </div>
                    </td>
                    <td style="padding: 10px 12px; color: var(--text-main); font-weight: 600;">
                        ${escapeHtml(doc.topic_guest || '—')}
                    </td>
                    <td style="padding: 10px 12px; text-align: center;">
                        ${getTypeBadge(doc.doc_type)}
                    </td>
                    <td style="padding: 10px 12px; white-space: nowrap; font-size: 12px;">
                        <div style="font-weight: 700; color: var(--text-main); line-height: 1.2;">${escapeHtml(doc.doc_time || '—')}</div>
                        <div style="color: var(--text-muted); font-size: 11px; margin-top: 3px; line-height: 1.2;">${formatDateDisplay(doc.doc_date)}</div>
                    </td>
                    <td style="padding: 10px 12px; white-space: nowrap; color: var(--text-main); font-weight: 600;">
                        ${escapeHtml(doc.city || 'Lahore')}
                    </td>
                    <td style="padding: 10px 12px; color: var(--text-main); font-weight: 700;">
                        ${escapeHtml(doc.producer_name || '—')}
                    </td>
                    <td style="padding: 8px 12px;">
                        ${linksCell}
                    </td>
                    <td style="padding: 10px 12px; text-align: right; white-space: nowrap;">
                        <button type="button" class="btn-sm" onclick="editDocumentary(${doc.id})" style="background: rgba(2, 132, 199, 0.1); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.25); border-radius: 6px; padding: 3px 8px; font-size: 11.5px; cursor: pointer; font-weight: 700; margin-right: 4px;" title="Full Edit">
                            ✏️
                        </button>
                        <button type="button" class="btn-sm" onclick="deleteDocumentary(${doc.id}, '${escapeHtml(rawTitle).replace(/'/g, "\\'")}')" style="background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 6px; padding: 3px 8px; font-size: 11.5px; cursor: pointer; font-weight: 700;" title="Delete Record">
                            🗑️
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    }

    // Filter Trigger Helpers
    function filterByType(type) {
        const select = document.getElementById('doc-filter-type');
        if (select) select.value = type;
        loadDocumentaries();
    }
    window.filterByType = filterByType;

    function filterByStatus(status) {
        const select = document.getElementById('doc-filter-status');
        if (select) select.value = status;
        loadDocumentaries();
    }
    window.filterByStatus = filterByStatus;

    function onDocSearchInput() {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadDocumentaries();
        }, 300);
    }
    window.onDocSearchInput = onDocSearchInput;

    function clearDocSearch() {
        const input = document.getElementById('doc-search-input');
        if (input) input.value = '';
        loadDocumentaries();
    }
    window.clearDocSearch = clearDocSearch;

    // Clipboard copy helper
    async function copyFilePath(path) {
        if (!path) return;
        try {
            await navigator.clipboard.writeText(path);
            if (typeof showToast === 'function') {
                showToast('Path copied to clipboard! 📋', 'success');
            }
        } catch (e) {
            // Fallback for non-HTTPS / restricted contexts
            const el = document.createElement('textarea');
            el.value = path;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
            if (typeof showToast === 'function') {
                showToast('Path copied to clipboard! 📋', 'success');
            }
        }
    }
    window.copyFilePath = copyFilePath;

    // Load staff/producers list for filing modal
    async function loadStaffList() {
        try {
            const res = await fetch('api/employees.php?action=list');
            const data = await res.json();
            if (data.success && data.employees) {
                staffMembers = data.employees;
                const select = document.getElementById('doc-form-producer-select');
                if (select) {
                    let html = '<option value="">-- Select from Staff List --</option>';
                    staffMembers.forEach(emp => {
                        html += `<option value="${emp.id}" data-name="${escapeHtml(emp.name)}">${escapeHtml(emp.name)} (${escapeHtml(emp.department_name || emp.role || 'Staff')})</option>`;
                    });
                    select.innerHTML = html;
                }
            }
        } catch (e) {
            console.error('Failed to load staff list:', e);
        }
    }

    function onProducerSelectChange() {
        const select = document.getElementById('doc-form-producer-select');
        const textInput = document.getElementById('doc-form-producer-text');
        if (!select || !textInput) return;

        const opt = select.options[select.selectedIndex];
        if (opt && opt.value) {
            textInput.value = opt.getAttribute('data-name') || opt.text.split('(')[0].trim();
        }
    }
    window.onProducerSelectChange = onProducerSelectChange;

    // Modal: Open for File / Edit
    function openDocumentaryModal(docData = null) {
        const modal = document.getElementById('modal-doc-form');
        const titleEl = document.getElementById('doc-modal-title');
        const formId = document.getElementById('doc-form-id');
        const formSr = document.getElementById('doc-form-sr');
        const formType = document.getElementById('doc-form-type');
        const formTitle = document.getElementById('doc-form-title');
        const formTopic = document.getElementById('doc-form-topic');
        const formDate = document.getElementById('doc-form-date');
        const formTime = document.getElementById('doc-form-time');
        const formCity = document.getElementById('doc-form-city');
        const formProducerSelect = document.getElementById('doc-form-producer-select');
        const formProducerText = document.getElementById('doc-form-producer-text');
        const formYt = document.getElementById('doc-form-yt');
        const formFb = document.getElementById('doc-form-fb');

        if (!modal) return;

        if (docData) {
            if (titleEl) titleEl.textContent = 'Edit Documentary Details';
            if (formId) formId.value = docData.id || '';
            if (formSr) formSr.value = docData.sr_no || '';
            if (formType) formType.value = docData.doc_type || 'Full';
            if (formTitle) formTitle.value = docData.title || '';
            if (formTopic) formTopic.value = docData.topic_guest || '';
            if (formDate) formDate.value = docData.doc_date || new Date().toISOString().substring(0, 10);
            if (formTime) formTime.value = docData.doc_time || '';
            if (formCity) formCity.value = docData.city || 'Lahore';
            if (formProducerText) formProducerText.value = docData.producer_name || '';
            if (formYt) formYt.value = docData.youtube_url || '';
            if (formFb) formFb.value = docData.facebook_url || '';

            if (formProducerSelect && docData.producer_id) {
                formProducerSelect.value = docData.producer_id;
            } else if (formProducerSelect) {
                formProducerSelect.value = '';
            }
        } else {
            if (titleEl) titleEl.textContent = 'File New Documentary';
            if (formId) formId.value = '';
            if (formSr) formSr.value = '';
            if (formType) formType.value = 'Full';
            if (formTitle) formTitle.value = '';
            if (formTopic) formTopic.value = '';
            if (formDate) formDate.value = new Date().toISOString().substring(0, 10);
            if (formTime) formTime.value = '';
            if (formCity) formCity.value = 'Lahore';
            if (formProducerSelect) formProducerSelect.value = '';
            if (formProducerText) formProducerText.value = '';
            if (formYt) formYt.value = '';
            if (formFb) formFb.value = '';
        }

        modal.classList.add('active');
        if (formTitle) setTimeout(() => formTitle.focus(), 150);
    }
    window.openDocumentaryModal = openDocumentaryModal;

    function closeDocumentaryModal() {
        const modal = document.getElementById('modal-doc-form');
        if (modal) modal.classList.remove('active');
    }
    window.closeDocumentaryModal = closeDocumentaryModal;

    // Edit handler fetching latest record data
    async function editDocumentary(id) {
        try {
            const res = await fetch(`api/documentaries.php?action=get&id=${id}`);
            const data = await res.json();
            if (data.success && data.documentary) {
                openDocumentaryModal(data.documentary);
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Could not load documentary data.', 'error');
                }
            }
        } catch (e) {
            console.error('Error fetching documentary:', e);
            if (typeof showToast === 'function') {
                showToast('Failed to fetch documentary details.', 'error');
            }
        }
    }
    window.editDocumentary = editDocumentary;

    // Submit File / Edit Form
    async function submitDocumentaryForm(event) {
        if (event) event.preventDefault();

        const btnSave = document.getElementById('btn-save-doc');
        const origText = btnSave ? btnSave.innerHTML : '';
        if (btnSave) {
            btnSave.disabled = true;
            btnSave.innerHTML = '<span>⏳</span> Saving...';
        }

        const id = document.getElementById('doc-form-id')?.value || '';
        const srNo = document.getElementById('doc-form-sr')?.value || '';
        const docType = document.getElementById('doc-form-type')?.value || 'Full';
        const title = document.getElementById('doc-form-title')?.value || '';
        const topicGuest = document.getElementById('doc-form-topic')?.value || '';
        const docDate = document.getElementById('doc-form-date')?.value || '';
        const docTime = document.getElementById('doc-form-time')?.value || '';
        const city = document.getElementById('doc-form-city')?.value || 'Lahore';
        const producerSelect = document.getElementById('doc-form-producer-select');
        const producerText = document.getElementById('doc-form-producer-text')?.value || '';
        const youtubeUrl = document.getElementById('doc-form-yt')?.value || '';
        const facebookUrl = document.getElementById('doc-form-fb')?.value || '';

        const producerId = producerSelect?.value ? parseInt(producerSelect.value, 10) : null;

        const payload = {
            action: 'save',
            id: id ? parseInt(id, 10) : null,
            sr_no: srNo,
            doc_type: docType,
            title: title,
            topic_guest: topicGuest,
            doc_date: docDate,
            doc_time: docTime,
            city: city,
            producer_id: producerId,
            producer_name: producerText,
            youtube_url: youtubeUrl,
            facebook_url: facebookUrl
        };

        try {
            const res = await fetch('api/documentaries.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (btnSave) {
                btnSave.disabled = false;
                btnSave.innerHTML = origText;
            }

            if (data.success) {
                closeDocumentaryModal();
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Documentary saved successfully! ✅', 'success');
                }
                await loadDocumentaries();
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Failed to save documentary.', 'error');
                }
            }
        } catch (e) {
            if (btnSave) {
                btnSave.disabled = false;
                btnSave.innerHTML = origText;
            }
            console.error('Error saving documentary:', e);
            if (typeof showToast === 'function') {
                showToast('Failed to connect to server.', 'error');
            }
        }
    }
    window.submitDocumentaryForm = submitDocumentaryForm;

    // Modal: Quick Digital Links
    function openDigitalLinksModal(id, title, yt, fb) {
        const modal = document.getElementById('modal-doc-quick-links');
        const idInput = document.getElementById('quick-links-doc-id');
        const titleEl = document.getElementById('quick-links-doc-title');
        const ytInput = document.getElementById('quick-links-yt');
        const fbInput = document.getElementById('quick-links-fb');

        if (!modal) return;

        if (idInput) idInput.value = id;
        if (titleEl) titleEl.textContent = title || 'Documentary Links';
        if (ytInput) ytInput.value = yt || '';
        if (fbInput) fbInput.value = fb || '';

        modal.classList.add('active');
        if (ytInput) setTimeout(() => ytInput.focus(), 150);
    }
    window.openDigitalLinksModal = openDigitalLinksModal;

    function closeDigitalLinksModal() {
        const modal = document.getElementById('modal-doc-quick-links');
        if (modal) modal.classList.remove('active');
    }
    window.closeDigitalLinksModal = closeDigitalLinksModal;

    // Submit Quick Digital Links
    async function submitDigitalLinksForm(event) {
        if (event) event.preventDefault();

        const btnSave = document.getElementById('btn-save-quick-links');
        const origText = btnSave ? btnSave.innerHTML : '';
        if (btnSave) {
            btnSave.disabled = true;
            btnSave.innerHTML = '<span>⏳</span> Updating...';
        }

        const id = document.getElementById('quick-links-doc-id')?.value || 0;
        const yt = document.getElementById('quick-links-yt')?.value || '';
        const fb = document.getElementById('quick-links-fb')?.value || '';

        const payload = {
            action: 'update_links',
            id: parseInt(id, 10),
            youtube_url: yt,
            facebook_url: fb
        };

        try {
            const res = await fetch('api/documentaries.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (btnSave) {
                btnSave.disabled = false;
                btnSave.innerHTML = origText;
            }

            if (data.success) {
                closeDigitalLinksModal();
                if (typeof showToast === 'function') {
                    showToast('Broadcast links updated successfully! 🚀', 'success');
                }
                await loadDocumentaries();
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Failed to update links.', 'error');
                }
            }
        } catch (e) {
            if (btnSave) {
                btnSave.disabled = false;
                btnSave.innerHTML = origText;
            }
            console.error('Error updating links:', e);
            if (typeof showToast === 'function') {
                showToast('Failed to connect to server.', 'error');
            }
        }
    }
    window.submitDigitalLinksForm = submitDigitalLinksForm;

    // Delete documentary
    async function deleteDocumentary(id, title) {
        if (!confirm(`Are you sure you want to delete this documentary?\n"${title}"`)) {
            return;
        }

        try {
            const res = await fetch('api/documentaries.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: parseInt(id, 10) })
            });
            const data = await res.json();

            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast('Documentary deleted successfully.', 'success');
                }
                await loadDocumentaries();
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Failed to delete documentary.', 'error');
                }
            }
        } catch (e) {
            console.error('Error deleting documentary:', e);
            if (typeof showToast === 'function') {
                showToast('Server connection failed.', 'error');
            }
        }
    }
    window.deleteDocumentary = deleteDocumentary;

    // Clear All Dummy Records
    async function clearAllDocumentaries() {
        if (!confirm('Are you sure you want to remove all dummy records? This will clear the table so you can start with fresh entries.')) {
            return;
        }

        try {
            const res = await fetch('api/documentaries.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_all' })
            });
            const data = await res.json();

            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast('All dummy records removed successfully! 🧹', 'success');
                }
                await loadDocumentaries();
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Failed to clear records.', 'error');
                }
            }
        } catch (e) {
            console.error('Error clearing documentaries:', e);
            if (typeof showToast === 'function') {
                showToast('Server connection failed.', 'error');
            }
        }
    }
    window.clearAllDocumentaries = clearAllDocumentaries;

    // CSV Export
    function exportDocumentariesCsv() {
        const search = (document.getElementById('doc-search-input')?.value || '').trim();
        const typeFilter = document.getElementById('doc-filter-type')?.value || 'all';
        const statusFilter = document.getElementById('doc-filter-status')?.value || 'all';
        const producerFilter = document.getElementById('doc-filter-producer')?.value || 'all';

        const params = new URLSearchParams({
            action: 'export_csv',
            search: search,
            type: typeFilter,
            status: statusFilter,
            producer: producerFilter
        });

        window.location.href = `api/documentaries.php?${params.toString()}`;
    }
    window.exportDocumentariesCsv = exportDocumentariesCsv;

    // Initialization on DOM Ready
    document.addEventListener('DOMContentLoaded', () => {
        loadStaffList();
        loadDocumentaries();
    });

})();
