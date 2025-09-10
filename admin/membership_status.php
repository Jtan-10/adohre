<?php
define('APP_INIT', true);
require_once 'admin_header.php';

// Restrict to admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Status - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.css" />
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <div id="content" class="content p-4" style="width: 100%;">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <h3 class="mb-0 me-auto">Membership Status</h3>
                <button id="refreshBtn" class="btn btn-outline-secondary ms-auto">Refresh</button>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="mb-2">
                        <button class="btn btn-sm btn-outline-primary" id="toggleFilters" type="button">Filters</button>
                        <button class="btn btn-sm btn-outline-secondary ms-1" id="clearFilters" type="button">Clear</button>
                        <div id="filterPanel" class="border rounded p-2 mt-2 d-none small" style="background:#f8f9fa;">
                            <div class="row g-3">
                                <div class="col-6 col-lg-2">
                                    <strong class="d-block mb-1">Status</strong>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="active" id="f_status_active" data-filter-group="statuses"><label class="form-check-label" for="f_status_active">Active</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="inactive" id="f_status_inactive" data-filter-group="statuses"><label class="form-check-label" for="f_status_inactive">Inactive</label></div>
                                </div>
                                <div class="col-6 col-lg-2">
                                    <strong class="d-block mb-1">Mortality</strong>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="alive" id="f_mort_alive" data-filter-group="mortality"><label class="form-check-label" for="f_mort_alive">Alive</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="deceased" id="f_mort_deceased" data-filter-group="mortality"><label class="form-check-label" for="f_mort_deceased">Deceased</label></div>
                                </div>
                                <div class="col-6 col-lg-2">
                                    <strong class="d-block mb-1">Certification</strong>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="regular" id="f_cert_regular" data-filter-group="certifications"><label class="form-check-label" for="f_cert_regular">Regular</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="honorary" id="f_cert_honorary" data-filter-group="certifications"><label class="form-check-label" for="f_cert_honorary">Honorary</label></div>
                                </div>
                                <div class="col-6 col-lg-2">
                                    <strong class="d-block mb-1">Lifetime</strong>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="yes" id="f_life_yes" data-filter-group="lifetime"><label class="form-check-label" for="f_life_yes">Yes</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="no" id="f_life_no" data-filter-group="lifetime"><label class="form-check-label" for="f_life_no">No</label></div>
                                </div>
                                <div class="col-6 col-lg-2">
                                    <strong class="d-block mb-1">Fee Status</strong>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="paid" id="f_fee_paid" data-filter-group="fee"><label class="form-check-label" for="f_fee_paid">Paid</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="pending" id="f_fee_pending" data-filter-group="fee"><label class="form-check-label" for="f_fee_pending">Pending</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="unpaid" id="f_fee_unpaid" data-filter-group="fee"><label class="form-check-label" for="f_fee_unpaid">Unpaid</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="canceled" id="f_fee_canceled" data-filter-group="fee"><label class="form-check-label" for="f_fee_canceled">Canceled</label></div>
                                </div>
                                <div class="col-6 col-lg-2">
                                    <strong class="d-block mb-1">Dues Status</strong>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="paid" id="f_dues_paid" data-filter-group="dues"><label class="form-check-label" for="f_dues_paid">Paid</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="unpaid" id="f_dues_unpaid" data-filter-group="dues"><label class="form-check-label" for="f_dues_unpaid">Unpaid</label></div>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" value="waived" id="f_dues_waived" data-filter-group="dues"><label class="form-check-label" for="f_dues_waived">Waived</label></div>
                                </div>
                                <div class="col-6 col-lg-2">
                                    <strong class="d-block mb-1">Year</strong>
                                    <div id="filterYears" style="max-height:150px;overflow:auto;" class="pe-1 small"></div>
                                </div>
                            </div>
                            <div class="mt-2 text-muted fst-italic">Filters are combined with AND logic and also with the text search.</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" id="gridTable">
                            <thead id="gridHead"></thead>
                            <tbody id="gridBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Update Annual Dues Modal -->
    <div class="modal fade" id="bulkDuesModal" tabindex="-1" aria-labelledby="bulkDuesModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkDuesModalLabel">Bulk Update Annual Dues</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Select years</label>
                        <div id="bulkYearsList" class="border rounded p-2" style="max-height: 240px; overflow: auto;"></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Set status</label>
                        <select class="form-select" id="bulkDuesStatus">
                            <option value="Paid">Paid</option>
                            <option value="Unpaid">Unpaid</option>
                            <option value="Waived">Waived</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="applyBulkDuesBtn">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= $cspNonce ?>"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" nonce="<?= $cspNonce ?>" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js" nonce="<?= $cspNonce ?>"></script>
    <script nonce="<?= $cspNonce ?>">
        const api = async (url, opts = {}) => (await fetch(url, opts)).json();
        const head = document.getElementById('gridHead');
        const body = document.getElementById('gridBody');
        let gridDT = null; // hold a single DataTable instance
        let globalSearchTerm = '';
        let YEARS = []; // dynamic years from backend
        let FEE = 300.00,
            DUES = 200.00; // dynamic amounts
        let bulkModal, currentBulkRow = null;
        const filterState = {
            statuses: new Set(),
            certifications: new Set(),
            lifetime: new Set(), // yes/no
            fee: new Set(), // paid/pending/unpaid/canceled
            dues: new Set(), // paid/unpaid/waived
            years: new Set(), // numeric years as string
            mortality: new Set() // alive/deceased
        };

        const filterPanel = () => document.getElementById('filterPanel');
        const filterYearsBox = () => document.getElementById('filterYears');

        document.getElementById('toggleFilters').addEventListener('click', () => {
            const p = filterPanel();
            if (!p) return;
            p.classList.toggle('d-none');
        });
        document.getElementById('clearFilters').addEventListener('click', () => {
            ['statuses', 'certifications', 'lifetime', 'fee', 'dues', 'years'].forEach(k => filterState[k].clear());
            if (filterPanel()) {
                filterPanel().querySelectorAll('input[type="checkbox"][data-filter-group]').forEach(cb => cb.checked = false);
            }
            if (gridDT) gridDT.draw();
        });

        (function setupFilterEvents() {
            const p = filterPanel();
            if (!p) return;
            p.addEventListener('change', (e) => {
                const cb = e.target.closest('input[type="checkbox"][data-filter-group]');
                if (!cb) return;
                const group = cb.getAttribute('data-filter-group');
                const val = cb.value.toLowerCase();
                const set = filterState[group];
                if (!set) return;
                if (cb.checked) set.add(val);
                else set.delete(val);
                if (gridDT) gridDT.draw();
            });
        })();

        function populateYearFilters(years) {
            const box = filterYearsBox();
            if (!box) return;
            box.innerHTML = years.map(y => `<div class="form-check mb-1"><input class="form-check-input" type="checkbox" value="${y}" id="f_year_${y}" data-filter-group="years"><label class="form-check-label" for="f_year_${y}">${y}</label></div>`).join('');
        }

        // Ensure unique members by user_id
        function dedupeMembers(members) {
            const seen = new Set();
            return members.filter(m => {
                const id = String(m.user_id);
                if (seen.has(id)) return false;
                seen.add(id);
                return true;
            });
        }

        function renderHead(years) {
            const fixed = [
                'Name',
                'Year of Membership',
                'Age upon Membership',
                'Membership Status',
                'Mortality Status',
                'Membership Certification',
                'Previous Office',
                'Lifetime Member',
                'Membership Fee',
                'Annual Dues'
            ];
            head.innerHTML = '<tr>' + fixed.map(h => `<th>${h}</th>`).join('') + '<th>Actions</th></tr>';
        }

        function renderBody(years, members) {
            // Destroy existing DataTable instance once, to avoid duplicated DOM management
            if (gridDT) {
                try {
                    gridDT.destroy();
                } catch {}
                gridDT = null;
            }
            // Rebuild rows
            body.innerHTML = members.map(m => {
                const name = `${m.last_name}, ${m.first_name}`;
                const nameSort = name.toLowerCase();
                const cert = m.certification || 'Regular';
                const status = m.membership_status || 'inactive';
                const mortality = m.mortality_status || 'Alive';
                const year = m.year_of_membership || '';
                const age = m.age_upon_membership || '';
                const prevOffice = m.previous_office || '';
                const lifetime = (String(m.is_lifetime) === '1');
                // membership_fee input removed; we will show payment status badge in the Membership Fee column
                const latestYear = years[years.length - 1];
                const dLatest = m.dues[String(latestYear)] || {
                    status: (latestYear === 2021 ? 'Waived' : 'Unpaid'),
                    amount: ''
                };
                const yearOptions = years.map(y => `<option value="${y}" ${y===latestYear?'selected':''}>${y}</option>`).join('');
                const duesEnc = encodeURIComponent(JSON.stringify(m.dues || {}));
                return `
                <tr data-user-id="${m.user_id}" data-dues-enc="${duesEnc}">
                    <td data-order="${nameSort}">${name}</td>
                    <td data-order="${year || ''}"><input type="number" class="form-control form-control-sm" data-field="year_of_membership" value="${year}"></td>
                    <td data-order="${age || ''}"><input type="number" class="form-control form-control-sm" data-field="age_upon_membership" value="${age}"></td>
                    <td data-order="${status.toLowerCase()}">
                        <select class="form-select form-select-sm" data-field="membership_status">
                            <option value="active" ${status==='active'?'selected':''}>Active</option>
                            <option value="inactive" ${status==='inactive'?'selected':''}>Inactive</option>
                        </select>
                    </td>
                    <td data-order="${mortality.toLowerCase()}">
                        <select class="form-select form-select-sm" data-field="mortality_status">
                            <option value="Alive" ${mortality==='Alive'?'selected':''}>Alive</option>
                            <option value="Deceased" ${mortality==='Deceased'?'selected':''}>Deceased</option>
                        </select>
                    </td>
                    <td data-order="${cert.toLowerCase()}">
                        <select class="form-select form-select-sm" data-field="certification">
                            <option value="Regular" ${cert==='Regular'?'selected':''}>Regular</option>
                            <option value="Honorary" ${cert==='Honorary'?'selected':''}>Honorary</option>
                        </select>
                    </td>
                    <td data-order="${(prevOffice||'').toLowerCase()}">
                        <input type="text" class="form-control form-control-sm" data-field="previous_office" value="${prevOffice.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\"/g,'&quot;')}">
                    </td>
                    <td data-order="${lifetime?1:0}">
                        <select class="form-select form-select-sm" data-field="is_lifetime">
                            <option value="1" ${lifetime?'selected':''}>Yes</option>
                            <option value="0" ${!lifetime?'selected':''}>No</option>
                        </select>
                    </td>
            <td>
                        <div class="d-flex flex-column gap-1">
                <div class="small">Fee Payment: <span class="badge bg-secondary" data-field="badge_fee">—</span></div>
                            <div class="small text-muted" data-field="fee_simple_text">Status: —</div>
                                <div class="d-flex gap-1 align-items-center">
                                    <select class="form-select form-select-sm" data-field="fee_manual_select" title="Set Membership Fee Status">
                                        <option value="">Set Fee...</option>
                                        <option value="Paid">Paid</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Unpaid">Unpaid</option>
                                        <option value="Canceled">Canceled</option>
                                    </select>
                                    <button class="btn btn-outline-success btn-sm" data-action="fee_manual_apply" title="Apply selected fee status">Apply</button>
                                </div>
                        </div>
                    </td>
                    <td>
                        <div class="row g-1 align-items-center">
                            <div class="col-auto">
                                <select class="form-select form-select-sm" data-field="dues_year">${yearOptions}</select>
                            </div>
                            <div class="col-auto">
                                <select class="form-select form-select-sm" data-field="dues_status">
                                    <option value="Paid" ${dLatest.status==='Paid'?'selected':''}>Paid</option>
                                    <option value="Unpaid" ${dLatest.status==='Unpaid'?'selected':''}>Unpaid</option>
                                    <option value="Waived" ${dLatest.status==='Waived'?'selected':''}>Waived</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <div class="small">Dues Payment: <span class="badge bg-secondary" data-field="badge_dues">—</span></div>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-outline-primary btn-sm" data-action="bulk_dues" title="Bulk update multiple years">Bulk Update</button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Action</button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" data-action="save" data-user="${m.user_id}">Save</a></li>
                                <li><a class="dropdown-item" href="#" data-action="notice_fee" data-user="${m.user_id}">Send Membership Fee Notice (₱${FEE.toFixed(0)})</a></li>
                                <li><a class="dropdown-item" href="#" data-action="notice_dues" data-user="${m.user_id}">Send Annual Due Notice (₱${DUES.toFixed(0)})</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            `;
            }).join('');

            // Safety: remove any duplicate DOM rows by data-user-id (in case of legacy DOM state)
            const seenIds = new Set();
            Array.from(body.querySelectorAll('tr')).forEach(tr => {
                const id = tr.getAttribute('data-user-id');
                if (seenIds.has(id)) tr.remove();
                else seenIds.add(id);
            });
            // Initialize DataTable once per render
            if (window.jQuery && $.fn && $.fn.DataTable) {
                // Register a single custom global filter (idempotent)
                if (!window.__msSearchFilterRegistered) {
                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                        if (!settings || !settings.nTable || settings.nTable.id !== 'gridTable') return true;
                        const term = globalSearchTerm;
                        try {
                            const api = new $.fn.dataTable.Api(settings);
                            const tr = api.row(dataIndex).node();
                            if (!tr) return true;
                            const pick = (sel) => {
                                const el = tr.querySelector(sel);
                                if (!el) return '';
                                if (el.tagName === 'SELECT') {
                                    const o = el.options[el.selectedIndex];
                                    return (o && o.textContent) ? o.textContent.trim() : '';
                                }
                                return (el.value || el.textContent || '').trim();
                            };
                            const membershipStatus = pick('[data-field="membership_status"]').toLowerCase();
                            const mortality = pick('[data-field="mortality_status"]').toLowerCase();
                            const lifetime = pick('[data-field="is_lifetime"]').toLowerCase();
                            const certification = pick('[data-field="certification"]').toLowerCase();
                            const yearVal = pick('[data-field="year_of_membership"]').toLowerCase();
                            const feeB = tr.querySelector('[data-field="badge_fee"]');
                            const duesB = tr.querySelector('[data-field="badge_dues"]');
                            const feeStatus = (feeB ? feeB.textContent.trim().toLowerCase() : '');
                            const duesStatusSimple = (duesB ? duesB.textContent.trim().toLowerCase() : '');
                            const duesSelectStatus = pick('[data-field="dues_status"]').toLowerCase(); // raw select (Paid/Unpaid/Waived)

                            // Apply checklist filters (AND logic) first
                            if (filterState.statuses.size && !filterState.statuses.has(membershipStatus)) return false;
                            if (filterState.mortality.size && !filterState.mortality.has(mortality)) return false;
                            if (filterState.certifications.size && !filterState.certifications.has(certification)) return false;
                            if (filterState.lifetime.size && !filterState.lifetime.has(lifetime)) return false;
                            if (filterState.fee.size && !filterState.fee.has(feeStatus)) return false;
                            // Dues: allow match against either raw select or simplified badge text
                            if (filterState.dues.size && !(
                                    filterState.dues.has(duesSelectStatus) || filterState.dues.has(duesStatusSimple)
                                )) return false;
                            if (filterState.years.size && !filterState.years.has(yearVal)) return false;

                            // If no text search term, row passes (filters already applied)
                            if (!term) return true;

                            // Special exact matches for status & lifetime via term
                            if (term === 'active') return membershipStatus === 'active';
                            if (term === 'inactive') return membershipStatus === 'inactive';
                            if (term === 'deceased') return mortality === 'deceased';
                            if (term === 'alive') return mortality === 'alive';
                            if (term === 'yes') return lifetime === 'yes';
                            if (term === 'no') return lifetime === 'no';
                            const fields = [
                                pick('td:first-child'),
                                pick('[data-field="year_of_membership"]'),
                                pick('[data-field="age_upon_membership"]'),
                                membershipStatus,
                                mortality,
                                pick('[data-field="certification"]'),
                                pick('[data-field="previous_office"]'),
                                lifetime,
                                pick('[data-field="dues_status"]'), // raw select value included
                                feeStatus,
                                duesStatusSimple,
                                duesSelectStatus
                            ];
                            // (feeStatus & duesStatus already added)
                            const haystack = fields.join(' ').toLowerCase();
                            return haystack.includes(term);
                        } catch (e) {
                            return true;
                        }
                    });
                    window.__msSearchFilterRegistered = true;
                }
                const id = '#gridTable';
                gridDT = $(id).DataTable({
                    pageLength: 10,
                    order: [],
                    autoWidth: false
                });
                // Attach to built-in search box (DataTables generates it after init)
                attachBuiltInSearch(gridDT);
                // Reapply search term after rebuild
                if (globalSearchTerm) {
                    gridDT.draw();
                }
            }
        }

        async function loadGrid() {
            const j = await api('../backend/routes/membership_status.php?action=grid');
            if (!j.status) {
                body.innerHTML = '<tr><td colspan="3">Failed to load</td></tr>';
                return;
            }
            YEARS = j.years || [];
            FEE = (typeof j.fee_amount !== 'undefined') ? parseFloat(j.fee_amount) : 300.00;
            DUES = (typeof j.annual_dues_amount !== 'undefined') ? parseFloat(j.annual_dues_amount) : 200.00;
            const uniqMembers = dedupeMembers(j.members || []);
            renderHead(j.years);
            renderBody(j.years, uniqMembers);
            populateYearFilters(j.years || []);
        }

        document.getElementById('refreshBtn').addEventListener('click', () => {
            loadGrid();
        });

        // Hook into DataTables native search input instead of custom bar
        function attachBuiltInSearch(dt) {
            const attempt = () => {
                try {
                    const container = dt.table().container();
                    const sel = 'div.dataTables_filter input, .dt-search input[type="search"]';
                    const input = container.querySelector(sel);
                    if (!input) return false;
                    if (input.__msBound) return true;
                    input.__msBound = true;
                    input.setAttribute('placeholder', 'Search any column...');
                    let timer = null;
                    input.addEventListener('input', () => {
                        clearTimeout(timer);
                        timer = setTimeout(() => {
                            globalSearchTerm = input.value.trim().toLowerCase();
                            dt.search(''); // clear native
                            dt.draw();
                        }, 200);
                    });
                    return true;
                } catch (e) {
                    return true;
                }
            };
            if (attempt()) return;
            const container = dt.table().container();
            const mo = new MutationObserver(() => {
                if (attempt()) mo.disconnect();
            });
            mo.observe(container, {
                childList: true,
                subtree: true
            });
            setTimeout(() => mo.disconnect(), 3000);
        }

        // When dues year changes, load status/amount for that year from the dues map
        body.addEventListener('change', (e) => {
            const sel = e.target.closest('[data-field="dues_year"]');
            if (!sel) return;
            const tr = sel.closest('tr');
            const enc = tr.getAttribute('data-dues-enc') || encodeURIComponent('{}');
            let duesMap = {};
            try {
                duesMap = JSON.parse(decodeURIComponent(enc));
            } catch {}
            const y = sel.value;
            const defStatus = (parseInt(y, 10) === 2021) ? 'Waived' : 'Unpaid';
            const d = duesMap[y] || {
                status: defStatus,
                amount: ''
            };
            const statusEl = tr.querySelector('[data-field="dues_status"]');
            if (statusEl) statusEl.value = d.status || defStatus;
        });

        // Keep data-order synced for status and certification selects
        body.addEventListener('change', (e) => {
            const st = e.target.closest('select[data-field="membership_status"]');
            const mortSel = e.target.closest('select[data-field="mortality_status"]');
            const certSel = e.target.closest('select[data-field="certification"]');
            if (!st && !certSel && !mortSel) return;
            const td = e.target.closest('td');
            if (td) td.setAttribute('data-order', (e.target.value || '').toLowerCase());
        });

        body.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-action="save"]');
            if (!btn) return;
            e.preventDefault();
            const userId = parseInt(btn.getAttribute('data-user'), 10);
            const tr = btn.closest('tr');
            // Collect profile fields
            const get = (sel) => tr.querySelector(`[data-field="${sel}"]`);
            const fdProfile = new FormData();
            fdProfile.append('action', 'save_profile');
            fdProfile.append('user_id', String(userId));
            fdProfile.append('year_of_membership', get('year_of_membership')?.value || '');
            fdProfile.append('age_upon_membership', get('age_upon_membership')?.value || '');
            fdProfile.append('membership_status', get('membership_status')?.value || 'inactive');
            fdProfile.append('mortality_status', get('mortality_status')?.value || 'Alive');
            fdProfile.append('certification', get('certification')?.value || 'Regular');
            fdProfile.append('previous_office', get('previous_office')?.value || '');
            fdProfile.append('is_lifetime', get('is_lifetime')?.value || '0');
            const r1 = await api('../backend/routes/membership_status.php', {
                method: 'POST',
                body: fdProfile
            });
            if (!r1.status) {
                alert(r1.message || 'Save profile failed');
                return;
            }

            // Collect dues for the selected year only
            const selYear = tr.querySelector('[data-field="dues_year"]').value;
            const selStatus = tr.querySelector('[data-field="dues_status"]').value;
            const dues = [{
                year: parseInt(selYear, 10),
                status: selStatus
            }];
            const fdDues = new FormData();
            fdDues.append('action', 'save_dues');
            fdDues.append('user_id', String(userId));
            fdDues.append('dues', JSON.stringify(dues));
            const r2 = await api('../backend/routes/membership_status.php', {
                method: 'POST',
                body: fdDues
            });
            if (!r2.status) {
                alert(r2.message || 'Save dues failed');
                return;
            }
            // Update cached dues map on the row for immediate consistency
            try {
                const enc = tr.getAttribute('data-dues-enc') || encodeURIComponent('{}');
                const map = JSON.parse(decodeURIComponent(enc));
                map[String(selYear)] = {
                    status: selStatus
                };
                tr.setAttribute('data-dues-enc', encodeURIComponent(JSON.stringify(map)));
            } catch {}
            alert('Saved');
        });

        // Keep sorting values in sync when inputs change (year/age/prevOffice)
        body.addEventListener('input', (e) => {
            const yearInp = e.target.closest('input[data-field="year_of_membership"]');
            const ageInp = e.target.closest('input[data-field="age_upon_membership"]');
            const prevOfficeInp = e.target.closest('input[data-field="previous_office"]');
            const inp = yearInp || ageInp || prevOfficeInp;
            if (!inp) return;
            const td = inp.closest('td');
            if (td) td.setAttribute('data-order', inp.value || '');
        });

        // Keep sorting values in sync when selects change (status/certification/lifetime)
        body.addEventListener('change', (e) => {
            const sel = e.target.closest('select[data-field="membership_status"], select[data-field="certification"], select[data-field="is_lifetime"]');
            if (!sel) return;
            const td = sel.closest('td');
            if (td) td.setAttribute('data-order', (sel.value || '').toLowerCase());
        });

        // Send notices
        body.addEventListener('click', async (e) => {
            const btnFee = e.target.closest('[data-action="notice_fee"]');
            const btnDues = e.target.closest('[data-action="notice_dues"]');
            const btnFeeApply = e.target.closest('[data-action="fee_manual_apply"]');
            if (!btnFee && !btnDues) return;
            e.preventDefault();
            const tr = e.target.closest('tr');
            const userId = parseInt(tr.getAttribute('data-user-id'), 10);
            if (btnFee) {
                const fd = new FormData();
                fd.append('action', 'send_notice');
                fd.append('type', 'membership_fee');
                fd.append('user_id', String(userId));
                const res = await api('../backend/routes/membership_status.php', {
                    method: 'POST',
                    body: fd
                });
                if (!res.status) {
                    alert(res.message || 'Failed sending fee notice');
                    return;
                }
                pollRowState(tr);
                alert(`Membership fee notice sent (₱${FEE.toFixed(0)})`);
            }
            if (btnDues) {
                const year = tr.querySelector('[data-field="dues_year"]').value;
                const fd = new FormData();
                fd.append('action', 'send_notice');
                fd.append('type', 'annual_dues');
                fd.append('user_id', String(userId));
                fd.append('year', String(year));
                const res = await api('../backend/routes/membership_status.php', {
                    method: 'POST',
                    body: fd
                });
                if (!res.status) {
                    alert(res.message || 'Failed sending dues notice');
                    return;
                }
                pollRowState(tr);
                alert(`Annual dues notice sent (₱${DUES.toFixed(0)})`);
            }
            if (btnFeeApply) {
                const select = tr.querySelector('[data-field="fee_manual_select"]');
                const val = select ? select.value : '';
                if (!val) {
                    alert('Select a fee status to apply');
                    return;
                }
                const fd = new FormData();
                fd.append('action', 'set_fee_status');
                fd.append('user_id', String(userId));
                fd.append('fee_status', val);
                const res = await api('../backend/routes/membership_status.php', {
                    method: 'POST',
                    body: fd
                });
                if (!res.status) {
                    alert(res.message || 'Failed to set fee status');
                    return;
                }
                // Refresh badges asap
                pollRowState(tr);
                alert('Membership fee status updated');
            }
        });

        // Polling helpers for live status
        async function fetchState(userId, year) {
            const url = new URL('../backend/routes/membership_status.php', window.location.href);
            url.searchParams.set('action', 'payment_state');
            url.searchParams.set('user_id', String(userId));
            if (year) url.searchParams.set('year', String(year));
            const res = await fetch(url.toString());
            return res.json();
        }

        function simplifyPaymentStatus(s) {
            if (!s) return 'Unpaid';
            if (s === 'Completed') return 'Paid';
            if (s === 'Canceled') return 'Canceled';
            // New/Pending -> Pending
            return 'Pending';
        }

        async function updateBadges(tr) {
            const userId = parseInt(tr.getAttribute('data-user-id'), 10);
            const year = tr.querySelector('[data-field="dues_year"]').value;
            const r = await fetchState(userId, year);
            if (!r.status) return;
            const feeBadge = tr.querySelector('[data-field="badge_fee"]');
            const duesBadge = tr.querySelector('[data-field="badge_dues"]');
            const feeSimple = tr.querySelector('[data-field="fee_simple_text"]');
            const paint = (el, val, mapSimple = false) => {
                if (!el) return;
                el.classList.remove('bg-secondary', 'bg-warning', 'bg-success', 'bg-danger', 'bg-info');
                let cls = 'bg-secondary';
                let text = val || '—';
                // Map to simplified display if needed
                const simple = simplifyPaymentStatus(val);
                if (simple === 'Pending') cls = 'bg-warning';
                else if (simple === 'Paid') cls = 'bg-success';
                else if (simple === 'Canceled') cls = 'bg-danger';
                else cls = 'bg-secondary';
                text = mapSimple ? simple : (val || '—');
                el.classList.add(cls);
                el.textContent = text;
            };
            paint(feeBadge, r.state.membership_fee, true); // show simplified on badge
            paint(duesBadge, r.state.annual_dues, true);
            if (feeSimple) feeSimple.textContent = 'Status: ' + simplifyPaymentStatus(r.state.membership_fee);
        }

        function pollRowState(tr) {
            updateBadges(tr);
            // Light polling for 60s
            let count = 0;
            const iv = setInterval(async () => {
                count++;
                await updateBadges(tr);
                if (count >= 12) clearInterval(iv); // every 5s * 12 = 60s
            }, 5000);
        }

        // Bulk Update: open modal and populate years
        body.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action="bulk_dues"]');
            if (!btn) return;
            e.preventDefault();
            currentBulkRow = btn.closest('tr');
            const list = document.getElementById('bulkYearsList');
            list.innerHTML = YEARS.map(y => {
                return `<div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${y}" id="year_${y}">
                    <label class="form-check-label" for="year_${y}">${y}</label>
                </div>`;
            }).join('');
            if (!bulkModal) bulkModal = new bootstrap.Modal(document.getElementById('bulkDuesModal'));
            bulkModal.show();
        });

        document.getElementById('applyBulkDuesBtn').addEventListener('click', async () => {
            if (!currentBulkRow) return;
            const userId = parseInt(currentBulkRow.getAttribute('data-user-id'), 10);
            const status = (document.getElementById('bulkDuesStatus').value) || 'Unpaid';
            const years = Array.from(document.querySelectorAll('#bulkYearsList input[type="checkbox"]:checked')).map(c => parseInt(c.value, 10));
            if (!years.length) {
                alert('Select at least one year.');
                return;
            }
            const duesPayload = years.map(y => ({
                year: y,
                status
            }));
            const fd = new FormData();
            fd.append('action', 'save_dues');
            fd.append('user_id', String(userId));
            fd.append('dues', JSON.stringify(duesPayload));
            const res = await api('../backend/routes/membership_status.php', {
                method: 'POST',
                body: fd
            });
            if (!res.status) {
                alert(res.message || 'Bulk update failed');
                return;
            }
            // Update cached dues map in the row
            try {
                const enc = currentBulkRow.getAttribute('data-dues-enc') || encodeURIComponent('{}');
                const map = JSON.parse(decodeURIComponent(enc));
                years.forEach(y => {
                    map[String(y)] = {
                        status
                    };
                });
                currentBulkRow.setAttribute('data-dues-enc', encodeURIComponent(JSON.stringify(map)));
            } catch {}
            // If currently selected year is among updated, reflect in select
            const selYearEl = currentBulkRow.querySelector('[data-field="dues_year"]');
            const selStatusEl = currentBulkRow.querySelector('[data-field="dues_status"]');
            if (selYearEl && selStatusEl && years.includes(parseInt(selYearEl.value, 10))) {
                selStatusEl.value = status;
            }
            bulkModal.hide();
            alert('Annual dues updated for selected years');
        });

        // Trigger initial badge state after grid loads
        const observer = new MutationObserver(() => {
            document.querySelectorAll('#gridBody tr').forEach(tr => pollRowState(tr));
        });
        observer.observe(body, {
            childList: true
        });

        loadGrid();
    </script>
</body>

</html>