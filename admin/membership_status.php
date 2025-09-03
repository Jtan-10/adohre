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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">Membership Status</h3>
                <button id="refreshBtn" class="btn btn-outline-secondary">Refresh</button>
            </div>
            <div class="card">
                <div class="card-body">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= $cspNonce ?>"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" nonce="<?= $cspNonce ?>" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js" nonce="<?= $cspNonce ?>"></script>
    <script nonce="<?= $cspNonce ?>">
        const api = async (url, opts = {}) => (await fetch(url, opts)).json();
        const head = document.getElementById('gridHead');
        const body = document.getElementById('gridBody');

        function renderHead(years) {
            const fixed = [
                'Name',
                'Year of Membership',
                'Age upon Membership',
                'Membership Status',
                'Membership Certification',
                'Membership Fee',
                'Annual Dues'
            ];
            head.innerHTML = '<tr>' + fixed.map(h => `<th>${h}</th>`).join('') + '<th>Actions</th></tr>';
        }

        function renderBody(years, members) {
            // If DataTable is already initialized, clear and destroy it before rebuilding rows
            if (window.jQuery && $.fn && $.fn.DataTable) {
                const id = '#gridTable';
                if ($.fn.DataTable.isDataTable(id)) {
                    const dt = $(id).DataTable();
                    dt.clear();
                    dt.destroy();
                }
            }

            body.innerHTML = members.map(m => {
                const name = `${m.last_name}, ${m.first_name}`;
                const cert = m.certification || 'Regular';
                const status = m.membership_status || 'inactive';
                const year = m.year_of_membership || '';
                const age = m.age_upon_membership || '';
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
                    <td>${name}</td>
                    <td><input type="number" class="form-control form-control-sm" data-field="year_of_membership" value="${year}"></td>
                    <td><input type="number" class="form-control form-control-sm" data-field="age_upon_membership" value="${age}"></td>
                    <td>
                        <select class="form-select form-select-sm" data-field="membership_status">
                            <option value="active" ${status==='active'?'selected':''}>Active</option>
                            <option value="inactive" ${status!=='active'?'selected':''}>Inactive</option>
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm" data-field="certification">
                            <option value="Regular" ${cert==='Regular'?'selected':''}>Regular</option>
                            <option value="Honorary" ${cert==='Honorary'?'selected':''}>Honorary</option>
                        </select>
                    </td>
                    <td>
                        <div class="small">Fee Payment: <span class="badge bg-secondary" data-field="badge_fee">—</span></div>
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
                        </div>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Action</button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" data-action="save" data-user="${m.user_id}">Save</a></li>
                                <li><a class="dropdown-item" href="#" data-action="notice_fee" data-user="${m.user_id}">Send Membership Fee Notice (₱300)</a></li>
                                <li><a class="dropdown-item" href="#" data-action="notice_dues" data-user="${m.user_id}">Send Annual Due Notice (₱200)</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            `;
            }).join('');
            // Initialize or reinitialize DataTable like in admin users
            if (window.jQuery && $.fn && $.fn.DataTable) {
                const id = '#gridTable';
                $(id).DataTable({
                    pageLength: 10,
                    order: [],
                    autoWidth: false,
                    destroy: true
                });
            }
        }

        async function loadGrid() {
            const j = await api('../backend/routes/membership_status.php?action=grid');
            if (!j.status) {
                body.innerHTML = '<tr><td colspan="3">Failed to load</td></tr>';
                return;
            }
            renderHead(j.years);
            renderBody(j.years, j.members);
        }

        document.getElementById('refreshBtn').addEventListener('click', loadGrid);

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
            fdProfile.append('certification', get('certification')?.value || 'Regular');
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

        // Send notices
        body.addEventListener('click', async (e) => {
            const btnFee = e.target.closest('[data-action="notice_fee"]');
            const btnDues = e.target.closest('[data-action="notice_dues"]');
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
                alert('Membership fee notice sent (₱300)');
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
                alert('Annual dues notice sent (₱200)');
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

        async function updateBadges(tr) {
            const userId = parseInt(tr.getAttribute('data-user-id'), 10);
            const year = tr.querySelector('[data-field="dues_year"]').value;
            const r = await fetchState(userId, year);
            if (!r.status) return;
            const feeBadge = tr.querySelector('[data-field="badge_fee"]');
            const duesBadge = tr.querySelector('[data-field="badge_dues"]');
            const paint = (el, val) => {
                if (!el) return;
                el.classList.remove('bg-secondary', 'bg-warning', 'bg-success', 'bg-danger', 'bg-info');
                let cls = 'bg-secondary';
                if (val === 'New') cls = 'bg-info';
                else if (val === 'Pending') cls = 'bg-warning';
                else if (val === 'Completed') cls = 'bg-success';
                else if (val === 'Canceled') cls = 'bg-danger';
                el.classList.add(cls);
                el.textContent = val || '—';
            };
            paint(feeBadge, r.state.membership_fee);
            paint(duesBadge, r.state.annual_dues);
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