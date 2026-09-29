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
                <h4 style="margin-bottom: 14px; font-size: 16px; font-weight: 800;">Department Content Matrix</h4>
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
