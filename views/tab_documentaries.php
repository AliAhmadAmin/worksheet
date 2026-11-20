<?php
/**
 * Discover Pakistan UHD TV - Documentaries View
 * Programming Desk Submissions & Digital Social Video Distribution
 */
?>
<section id="tab-documentaries" class="tab-section active" style="padding: 16px 20px;">
    <div class="worksheet-card" style="background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); overflow: hidden;">
        
        <!-- Header & Action Ribbon -->
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; background: linear-gradient(135deg, rgba(2, 132, 199, 0.04), rgba(16, 185, 129, 0.04));">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(2, 132, 199, 0.12); border: 1px solid rgba(2, 132, 199, 0.25); display: flex; align-items: center; justify-content: center; font-size: 22px;">
                    📽️
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                        Documentaries Hub
                        <span style="font-size: 11px; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #059669; padding: 2px 8px; border-radius: 20px;">Programming & Digital Desk</span>
                    </h2>
                    <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--text-muted);">
                        Filed by Programming Department • Social Video Distribution by Digital Department
                    </p>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn btn-outline" onclick="clearAllDocumentaries()" style="font-size: 12px; font-weight: 700; padding: 7px 12px; border-radius: 8px; color: #dc2626; border-color: rgba(239, 68, 68, 0.35); display: inline-flex; align-items: center; gap: 6px;" title="Clear all dummy records">
                    <span>🗑️</span> Clear Dummy Data
                </button>
                <button type="button" class="btn btn-outline" onclick="exportDocumentariesCsv()" style="font-size: 12px; font-weight: 700; padding: 7px 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                    <span>📥</span> Export CSV
                </button>
                <button type="button" class="btn btn-primary" onclick="openDocumentaryModal()" style="font-size: 12.5px; font-weight: 800; padding: 7px 16px; border-radius: 8px; background: #0284c7; border-color: #0284c7; color: #fff; display: inline-flex; align-items: center; gap: 6px;">
                    <span>+</span> File Documentary
                </button>
            </div>
        </div>

        <!-- KPI Summary Cards Ribbon -->
        <div style="padding: 14px 24px; background: var(--bg-card-elevated, #f8fafc); border-bottom: 1px solid var(--border-color); display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px;">
            <div style="background: var(--bg-card, #fff); border: 1px solid var(--border-color); border-radius: 10px; padding: 10px 14px; cursor: pointer;" onclick="filterByType('all')" title="Show All Documentaries">
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Total Docs</div>
                <div id="stat-doc-total" style="font-size: 20px; font-weight: 800; color: var(--text-main); margin-top: 2px;">0</div>
            </div>

            <div style="background: var(--bg-card, #fff); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 10px; padding: 10px 14px; cursor: pointer;" onclick="filterByType('Full')" title="Filter Full Documentaries">
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #059669;">🟢 Full Docs</div>
                <div id="stat-doc-full" style="font-size: 20px; font-weight: 800; color: #059669; margin-top: 2px;">0</div>
            </div>

            <div style="background: var(--bg-card, #fff); border: 1px solid rgba(2, 132, 199, 0.3); border-radius: 10px; padding: 10px 14px; cursor: pointer;" onclick="filterByType('Half')" title="Filter Half Documentaries">
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #0284c7;">🔵 Half Docs</div>
                <div id="stat-doc-half" style="font-size: 20px; font-weight: 800; color: #0284c7; margin-top: 2px;">0</div>
            </div>

            <div style="background: var(--bg-card, #fff); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 10px; padding: 10px 14px; cursor: pointer;" onclick="filterByType('Short')" title="Filter Short Documentaries">
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #d97706;">🟠 Short Docs</div>
                <div id="stat-doc-short" style="font-size: 20px; font-weight: 800; color: #d97706; margin-top: 2px;">0</div>
            </div>

            <div style="background: var(--bg-card, #fff); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 10px 14px; cursor: pointer;" onclick="filterByStatus('pending_digital')" title="Filter Documentaries missing YouTube or Facebook links">
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #dc2626;">⏳ Pending Digital</div>
                <div id="stat-doc-pending" style="font-size: 20px; font-weight: 800; color: #dc2626; margin-top: 2px;">0</div>
            </div>

            <div style="background: var(--bg-card, #fff); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 10px; padding: 10px 14px; cursor: pointer;" onclick="filterByStatus('completed_digital')" title="Filter fully published documentaries">
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #10b981;">✅ Fully Published</div>
                <div id="stat-doc-complete" style="font-size: 20px; font-weight: 800; color: #10b981; margin-top: 2px;">0</div>
            </div>
        </div>

        <!-- Filter & Search Controls Bar -->
        <div style="padding: 12px 24px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <!-- Search Input -->
                <div style="position: relative; display: inline-flex; align-items: center;">
                    <input type="text" id="doc-search-input" class="input-control" placeholder="🔍 Search title, topic, guest, producer..." style="padding: 6px 28px 6px 10px; font-size: 12.5px; width: 260px; border-radius: 8px;" oninput="onDocSearchInput()">
                    <button type="button" id="doc-search-clear" onclick="clearDocSearch()" style="display: none; position: absolute; right: 8px; background: transparent; border: none; font-size: 12px; cursor: pointer; color: var(--text-muted);">✕</button>
                </div>

                <!-- Type Selector -->
                <select id="doc-filter-type" class="input-control" onchange="loadDocumentaries()" style="padding: 6px 10px; font-size: 12.5px; border-radius: 8px; font-weight: 600;">
                    <option value="all">📂 All Types (Full / Half / Short)</option>
                    <option value="Full">🟢 Full Documentaries</option>
                    <option value="Half">🔵 Half Documentaries</option>
                    <option value="Short">🟠 Short Documentaries</option>
                </select>

                <!-- Status Selector -->
                <select id="doc-filter-status" class="input-control" onchange="loadDocumentaries()" style="padding: 6px 10px; font-size: 12.5px; border-radius: 8px; font-weight: 600;">
                    <option value="all">🌐 All Status</option>
                    <option value="pending_digital">⏳ Pending Digital Links</option>
                    <option value="completed_digital">✅ YouTube & Facebook Complete</option>
                    <option value="any_published">🔗 Any Link Published</option>
                </select>

                <!-- Producer Filter Dropdown -->
                <select id="doc-filter-producer" class="input-control" onchange="loadDocumentaries()" style="padding: 6px 10px; font-size: 12.5px; border-radius: 8px;">
                    <option value="all">👤 All Producers</option>
                </select>
            </div>

            <!-- Quick Reload Button -->
            <div>
                <button type="button" class="btn btn-outline" onclick="loadDocumentaries()" style="padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 8px;">
                    🔄 Refresh
                </button>
            </div>
        </div>

        <!-- Table Container -->
        <div style="padding: 0; overflow-x: auto;">
            <div id="doc-table-loading" style="display: none; text-align: center; padding: 40px; color: var(--text-muted); font-size: 13px;">
                <div class="spinner" style="margin: 0 auto 10px;"></div>
                Loading documentaries...
            </div>

            <div id="doc-table-empty" style="display: none; text-align: center; padding: 50px 20px; color: var(--text-muted);">
                <div style="font-size: 36px; margin-bottom: 8px;">📽️</div>
                <div style="font-size: 15px; font-weight: 700; color: var(--text-main);">No Documentaries Found</div>
                <p style="font-size: 12.5px; margin: 4px 0 14px 0;">No documentary records match the current filter criteria.</p>
                <button type="button" class="btn btn-primary" onclick="openDocumentaryModal()" style="font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 6px;">+ File New Documentary</button>
            </div>

            <table id="doc-main-table" class="interactive-table" style="width: 100%; border-collapse: collapse; margin-bottom: 0; font-size: 12.5px;">
                <thead style="background: #0f172a; color: #fff;">
                    <tr>
                        <th style="padding: 10px 12px; width: 45px; text-align: center; font-weight: 700;">Sr #</th>
                        <th style="padding: 10px 12px; width: 23%; font-weight: 700;">Title / Path</th>
                        <th style="padding: 10px 12px; width: 19%; font-weight: 700;">Topic & Guest</th>
                        <th style="padding: 10px 12px; width: 85px; text-align: center; font-weight: 700;">Type</th>
                        <th style="padding: 10px 12px; width: 105px; font-weight: 700;">Time & Date</th>
                        <th style="padding: 10px 12px; width: 80px; font-weight: 700;">City</th>
                        <th style="padding: 10px 12px; width: 130px; font-weight: 700;">Producer</th>
                        <th style="padding: 10px 12px; width: 155px; font-weight: 700;">FB & YT Links</th>
                        <th style="padding: 10px 12px; width: 80px; text-align: right; font-weight: 700;">Action</th>
                    </tr>
                </thead>
                <tbody id="doc-table-tbody">
                    <!-- Populated dynamically via documentaries.js -->
                </tbody>
            </table>
        </div>

        <!-- Table Footer / Count Indicator -->
        <div style="padding: 12px 24px; background: var(--bg-card-elevated, #f8fafc); border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 12px; color: var(--text-muted);">
            <div>
                Showing <strong id="doc-showing-count" style="color: var(--text-main);">0</strong> documentary record(s)
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span>🟢 Full: <b>1-Hour Documentary</b></span>
                <span>🔵 Half: <b>30-Min Documentary</b></span>
                <span>🟠 Short: <b>Short / Feature Clip</b></span>
            </div>
        </div>

    </div>
</section>

<!-- ================= MODAL: FILE / EDIT DOCUMENTARY ================= -->
<div id="modal-doc-form" class="modal-overlay" style="z-index: 10060;">
    <div class="modal-card" style="max-width: 620px; border-radius: 18px; padding: 0; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.35);">
        
        <div style="background: linear-gradient(135deg, rgba(2, 132, 199, 0.12), rgba(16, 185, 129, 0.08)); padding: 16px 22px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 20px;">📽️</span>
                <h3 id="doc-modal-title" style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-main);">File New Documentary</h3>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeDocumentaryModal()" style="font-size: 16px; padding: 4px 8px; line-height: 1;">✕</button>
        </div>

        <form id="doc-form" onsubmit="submitDocumentaryForm(event)" style="padding: 20px 22px; max-height: 75vh; overflow-y: auto;">
            <input type="hidden" id="doc-form-id" value="">
            <input type="hidden" id="doc-form-sr" value="">

            <div style="margin-bottom: 14px;">
                <label class="form-label" style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">
                    <span>Documentary Type <span style="color:#ef4444;">*</span></span>
                    <span style="font-weight: 600; color: #059669; font-size: 11px; background: rgba(16, 185, 129, 0.1); padding: 1px 7px; border-radius: 4px;">⚡ Sr # Auto-Generated</span>
                </label>
                <select id="doc-form-type" class="input-control" style="width: 100%; font-size: 12.5px; padding: 8px 10px; border-radius: 8px; font-weight: 700;" required>
                    <option value="Full">🟢 Full Documentary (1-Hour)</option>
                    <option value="Half">🔵 Half Documentary (30-Min)</option>
                    <option value="Short">🟠 Short Documentary (Feature / Clip)</option>
                </select>
            </div>

            <!-- Title / File Path -->
            <div style="margin-bottom: 14px;">
                <label class="form-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">Title / Network File Path <span style="color:#ef4444;">*</span></label>
                <input type="text" id="doc-form-title" class="input-control" placeholder="e.g. \\10.0.0.4\transmission\Transmission\Documentary\1-Full Documentry\..." style="width: 100%; font-size: 12.5px; padding: 8px 10px; border-radius: 8px; font-family: monospace;" required>
            </div>

            <!-- Topic & Guest -->
            <div style="margin-bottom: 14px;">
                <label class="form-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">Topic & Guest Details</label>
                <input type="text" id="doc-form-topic" class="input-control" placeholder="e.g. Secret Cave Exploration Documentary (Guest: ...)" style="width: 100%; font-size: 12.5px; padding: 8px 10px; border-radius: 8px;">
            </div>

            <!-- Date, Time & City -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                <div>
                    <label class="form-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" id="doc-form-date" class="input-control" style="width: 100%; font-size: 12px; padding: 7px 8px; border-radius: 8px;" required>
                </div>
                <div>
                    <label class="form-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">Time (Transmission)</label>
                    <input type="text" id="doc-form-time" class="input-control" placeholder="e.g. 03:35 PM" style="width: 100%; font-size: 12px; padding: 7px 8px; border-radius: 8px;">
                </div>
                <div>
                    <label class="form-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">City</label>
                    <input type="text" id="doc-form-city" class="input-control" placeholder="e.g. Lahore" value="Lahore" style="width: 100%; font-size: 12px; padding: 7px 8px; border-radius: 8px;">
                </div>
            </div>

            <!-- Producer -->
            <div style="margin-bottom: 16px;">
                <label class="form-label" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">Producer</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <select id="doc-form-producer-select" class="input-control" style="font-size: 12px; padding: 7px 8px; border-radius: 8px;" onchange="onProducerSelectChange()">
                        <option value="">-- Select from Staff List --</option>
                    </select>
                    <input type="text" id="doc-form-producer-text" class="input-control" placeholder="Or type producer name..." style="font-size: 12px; padding: 7px 8px; border-radius: 8px;">
                </div>
            </div>

            <!-- Social Media Distribution Links (Digital Desk) -->
            <div style="background: rgba(2, 132, 199, 0.05); border: 1px solid rgba(2, 132, 199, 0.2); border-radius: 12px; padding: 12px 14px; margin-bottom: 12px;">
                <div style="font-size: 11.5px; font-weight: 800; color: #0284c7; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <span>📱</span> Digital Social Media Links
                </div>
                <div style="margin-bottom: 8px;">
                    <label style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 2px; color: var(--text-muted);">YouTube URL</label>
                    <input type="url" id="doc-form-yt" class="input-control" placeholder="https://youtu.be/... or https://www.youtube.com/watch?v=..." style="width: 100%; font-size: 12px; padding: 6px 8px; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 2px; color: var(--text-muted);">Facebook URL</label>
                    <input type="url" id="doc-form-fb" class="input-control" placeholder="https://www.facebook.com/share/... or post link" style="width: 100%; font-size: 12px; padding: 6px 8px; border-radius: 6px;">
                </div>
            </div>

            <div style="background: var(--bg-card-elevated, #f8fafc); padding: 12px 18px; border-top: 1px solid var(--border-color); margin: 10px -22px -20px -22px; display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-outline" onclick="closeDocumentaryModal()" style="min-width: 80px; padding: 6px 14px; font-size: 12px; font-weight: 600; border-radius: 6px;">
                    Cancel
                </button>
                <button type="submit" id="btn-save-doc" class="btn btn-primary" style="min-width: 120px; padding: 6px 16px; font-size: 12px; font-weight: 700; border-radius: 6px; background: #0284c7; border-color: #0284c7; color: #fff;">
                    <span>💾</span> Save Documentary
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL: QUICK DIGITAL LINKS UPDATE ================= -->
<div id="modal-doc-quick-links" class="modal-overlay" style="z-index: 10065;">
    <div class="modal-card" style="max-width: 500px; border-radius: 16px; padding: 0; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.35);">
        
        <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(59, 130, 246, 0.1)); padding: 14px 18px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">🔗</span>
                <div>
                    <h3 style="margin: 0; font-size: 14.5px; font-weight: 800; color: var(--text-main);">Update Digital Broadcast Links</h3>
                    <div id="quick-links-doc-title" style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px; max-width: 380px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Documentary Title</div>
                </div>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeDigitalLinksModal()" style="font-size: 16px; padding: 4px 8px; line-height: 1;">✕</button>
        </div>

        <form id="quick-links-form" onsubmit="submitDigitalLinksForm(event)" style="padding: 18px 20px;">
            <input type="hidden" id="quick-links-doc-id" value="">

            <div style="margin-bottom: 14px;">
                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: #ef4444;">
                    <span>▶️</span> YouTube Video Link
                </label>
                <input type="url" id="quick-links-yt" class="input-control" placeholder="https://youtu.be/... or https://youtube.com/watch?v=..." style="width: 100%; font-size: 12.5px; padding: 7px 10px; border-radius: 8px;">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: #1877f2;">
                    <span>📘</span> Facebook Video / Post Link
                </label>
                <input type="url" id="quick-links-fb" class="input-control" placeholder="https://www.facebook.com/share/... or post URL" style="width: 100%; font-size: 12.5px; padding: 7px 10px; border-radius: 8px;">
            </div>

            <div style="background: var(--bg-card-elevated, #f8fafc); padding: 10px 18px; border-top: 1px solid var(--border-color); margin: 10px -20px -18px -20px; display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
                <button type="button" class="btn btn-outline" onclick="closeDigitalLinksModal()" style="min-width: 75px; padding: 6px 12px; font-size: 12px; font-weight: 600; border-radius: 6px;">
                    Cancel
                </button>
                <button type="submit" id="btn-save-quick-links" class="btn btn-primary" style="min-width: 110px; padding: 6px 14px; font-size: 12px; font-weight: 700; border-radius: 6px; background: #0284c7; border-color: #0284c7; color: #fff;">
                    <span>💾</span> Save Links
                </button>
            </div>
        </form>
    </div>
</div>
