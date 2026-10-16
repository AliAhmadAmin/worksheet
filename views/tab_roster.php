<?php
/**
 * Tab: Duty Roster Management View
 * Monthly pre-assigned shift timings and rotational weekly offs
 * Discover Pakistan UHD TV Network
 */
$userRoleRoster = strtolower($authUser['role'] ?? '');
$isHodOrAdminRoster = in_array($userRoleRoster, ['super_admin', 'admin', 'hod', 'hr']) || !empty($authUser['can_manage_roster']);
$currentMonth = date('Y-m');
?>
<section id="tab-roster" class="tab-section">
    <!-- Top Action Card / Controls -->
    <div class="worksheet-card mb-4 no-print" style="margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 26px;">📅</span>
                    <div>
                        <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: var(--text-main);">Department Duty Roster</h2>
                        <p style="margin: 2px 0 0; font-size: 13px; color: var(--text-muted);">
                            Pre-assigned monthly duty hours, shift timings, and rotational weekly off schedules.
                        </p>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <!-- Month Picker -->
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label for="roster-month-picker" style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Month:</label>
                    <input type="month" id="roster-month-picker" class="input-control" value="<?= $currentMonth ?>" style="font-weight: 700; padding: 7px 12px; width: 155px;" onchange="loadDutyRoster()">
                </div>

                <!-- Department Selector -->
                <div style="display: flex; align-items: center; gap: 6px;">
                    <label for="roster-dept-select" style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Dept:</label>
                    <select id="roster-dept-select" class="input-control" style="font-weight: 600; padding: 7px 12px; min-width: 150px;" onchange="loadDutyRoster()">
                        <option value="2" selected>Digital Media</option>
                        <option value="1">News Room</option>
                        <option value="3">Programming</option>
                        <option value="4">HR / Operations</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <?php if ($isHodOrAdminRoster): ?>
                <button type="button" class="btn btn-outline" onclick="copyPreviousMonthRoster()" title="Roll forward previous month's schedule to selected month">
                    📋 Copy Last Month
                </button>
                <button type="button" class="btn btn-success" onclick="saveDutyRoster()" id="btn-save-roster" style="font-weight: 700;">
                    💾 Save Roster
                </button>
                <?php endif; ?>

                <button type="button" class="btn btn-primary" onclick="printOfficialDutyRoster()" style="font-weight: 700;">
                    🖨️ Print Official Roster
                </button>
            </div>
        </div>


        <!-- Quick Summary Stats -->
        <div id="roster-quick-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-top: 14px;">
            <div style="background: var(--bg-card-elevated); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Total Staff</div>
                <div id="stat-total-staff" style="font-size: 20px; font-weight: 800; color: var(--text-main);">0</div>
            </div>
            <div style="background: var(--bg-card-elevated); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Morning / Day</div>
                <div id="stat-morning-shifts" style="font-size: 20px; font-weight: 800; color: #38bdf8;">0</div>
            </div>
            <div style="background: var(--bg-card-elevated); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Afternoon / Evening</div>
                <div id="stat-afternoon-shifts" style="font-size: 20px; font-weight: 800; color: #f59e0b;">0</div>
            </div>
            <div style="background: var(--bg-card-elevated); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Night / Late Shift</div>
                <div id="stat-night-shifts" style="font-size: 20px; font-weight: 800; color: #8b5cf6;">0</div>
            </div>
            <div style="background: var(--bg-card-elevated); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color);">
                <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Configured Offs</div>
                <div id="stat-configured-offs" style="font-size: 20px; font-weight: 800; color: #10b981;">0</div>
            </div>
        </div>
    </div>

    <!-- Roster Grid / Printable Document Container -->
    <div id="roster-document-container" class="worksheet-card" style="padding: 24px; position: relative;">
        <!-- Printable Header (Visible on print and on-screen header) -->
        <div id="roster-printable-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 2px solid var(--border-color); padding-bottom: 14px;">
            <div class="roster-header-logo" style="flex: 0 0 180px; text-align: left;">
                <img src="assets/img/logo.svg" alt="Discover Pakistan UHD TV" class="roster-brand-logo" style="height: 48px; width: auto; max-width: 180px; object-fit: contain; display: block;">
            </div>
            <div class="roster-header-center" style="flex: 1; text-align: center; padding: 0 10px;">
                <h2 id="roster-document-title" style="font-size: 18px; font-weight: 800; color: #10b981; margin: 0; text-transform: uppercase; letter-spacing: 0.8px;">
                    DIGITAL DUTY ROSTER (<?= strtoupper(date('M Y')) ?>)
                </h2>
                <p id="roster-meta-subtitle" style="font-size: 12px; color: var(--text-muted); margin: 4px 0 0; font-weight: 600;">
                    Official Monthly Shift Timings & Weekly Off Allocations • 0 Team Members
                </p>
            </div>
            <div class="roster-header-spacer" style="flex: 0 0 180px;">
                <!-- Equal width spacer to keep title centered -->
            </div>
        </div>

        <!-- Dynamic Content rendered by JS -->
        <div id="roster-tables-container">
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <span style="font-size: 24px;">⏳</span>
                <p>Loading duty roster...</p>
            </div>
        </div>

        <!-- Official Signatures Block (For physical printout & HOD sign-off) -->
        <div id="roster-signature-block" style="margin-top: 45px; padding-top: 30px; border-top: 1px dashed var(--border-color); display: flex; justify-content: space-between; align-items: flex-end; padding-left: 40px; padding-right: 40px;">
            <div style="text-align: center; min-width: 220px;">
                <div style="border-bottom: 1.5px solid var(--text-main); width: 100%; margin-bottom: 8px;"></div>
                <div style="font-weight: 800; font-size: 13px; color: var(--text-main);">HOD DIGITAL MEDIA</div>
                <div style="font-size: 11.5px; color: var(--text-muted);">Discover Pakistan UHD TV</div>
            </div>

            <div style="text-align: center; min-width: 220px;">
                <div style="border-bottom: 1.5px solid var(--text-main); width: 100%; margin-bottom: 8px;"></div>
                <div style="font-weight: 800; font-size: 13px; color: var(--text-main);">HEAD OF HR</div>
                <div style="font-size: 11.5px; color: var(--text-muted);">Discover Pakistan UHD TV</div>
            </div>
        </div>
    </div>
</section>
