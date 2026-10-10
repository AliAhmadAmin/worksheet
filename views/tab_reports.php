        <!-- TAB 3: MATRIX REPORTS & ANALYTICS -->
        <section id="tab-reports" class="tab-section">
            <!-- Filter Bar -->
            <div class="report-header-banner">
                <div class="card-title-group">
                    <h3>📊 Production & Content Analytics Matrix</h3>
                    <p>Cross-tabulation of departments, content types, and team member outputs.</p>
                </div>

                <div class="controls-group">
                    <label style="font-size: 12.5px; color: var(--text-muted);">From:</label>
                    <input type="date" id="report-start-date" class="input-control" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
                    <label style="font-size: 12.5px; color: var(--text-muted);">To:</label>
                    <input type="date" id="report-end-date" class="input-control" value="<?= date('Y-m-d') ?>">
                    <button type="button" class="btn btn-primary" onclick="loadReports()">
                        🔄 Filter
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportReportCSV()">
                        📥 Export CSV
                    </button>
                </div>
            </div>

            <!-- Matrix 1: Department vs Content Type -->
            <div class="matrix-card">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 14px;">
                    <div>
                        <h4 style="margin: 0; font-size: 16px; font-weight: 800;">Department Content Matrix</h4>
                        <span style="font-size: 11.5px; color: var(--text-muted);">Tracking desks (rows) × Content types (columns)</span>
                    </div>
                    <?php if (in_array($authUser['role'] ?? '', ['admin', 'super_admin', 'hod'])): ?>
                    <button type="button" class="btn btn-outline" style="padding: 5px 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;" onclick="openTrackingOptionsModal()">
                        ⚙️ Customize Matrix Options
                    </button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="matrix-table">
                        <thead id="matrix-dept-thead">
                            <!-- Dynamic columns -->
                        </thead>
                        <tbody id="matrix-dept-tbody">
                            <!-- Dynamic rows & totals -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Matrix 2: Team & Employee Performance Breakdown -->
            <div class="matrix-card">
                <h4 style="margin-bottom: 14px; font-size: 16px; font-weight: 800;">Team & Employee Performance Breakdown</h4>
                <div class="table-responsive">
                    <table class="matrix-table">
                        <thead id="matrix-employee-thead">
                            <!-- Dynamic columns -->
                        </thead>
                        <tbody id="matrix-employee-tbody">
                            <!-- Dynamic rows -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
