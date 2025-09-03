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
                'Membership Fee'
            ];
            head.innerHTML = '<tr>' + fixed.map(h => `<th>${h}</th>`).join('') + years.map(y => `<th>${y} (Status)</th><th>${y} (Amount)</th>`).join('') + '<th>Actions</th></tr>';
        }

        function renderBody(years, members) {
            body.innerHTML = members.map(m => {
                const name = `${m.last_name}, ${m.first_name}`;
                const cert = m.certification || 'Regular';
                const status = m.membership_status || 'inactive';
                const year = m.year_of_membership || '';
                const age = m.age_upon_membership || '';
                const fee = m.membership_fee || '';
                const duesCells = years.map(y => {
                    const d = m.dues[String(y)] || {
                        status: y === 2021 ? 'Waived' : 'Unpaid',
                        amount: ''
                    };
                    return `
                    <td>
                        <select class="form-select form-select-sm" data-user="${m.user_id}" data-field="dues_status_${y}">
                            <option value="Paid" ${d.status==='Paid'?'selected':''}>Paid</option>
                            <option value="Unpaid" ${d.status==='Unpaid'?'selected':''}>Unpaid</option>
                            <option value="Waived" ${d.status==='Waived'?'selected':''}>Waived</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" step="0.01" class="form-control form-control-sm" data-user="${m.user_id}" data-field="dues_amount_${y}" value="${d.amount ?? ''}">
                    </td>
                `;
                }).join('');
                return `
                <tr data-user-id="${m.user_id}">
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
                    <td><input type="number" step="0.01" class="form-control form-control-sm" data-field="membership_fee" value="${fee}"></td>
                    ${duesCells}
                    <td>
                        <button class="btn btn-sm btn-success" data-action="save" data-user="${m.user_id}">Save</button>
                    </td>
                </tr>
            `;
            }).join('');
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

        body.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-action="save"]');
            if (!btn) return;
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
            fdProfile.append('membership_fee', get('membership_fee')?.value || '');
            const r1 = await api('../backend/routes/membership_status.php', {
                method: 'POST',
                body: fdProfile
            });
            if (!r1.status) {
                alert(r1.message || 'Save profile failed');
                return;
            }

            // Collect dues
            const tds = Array.from(tr.querySelectorAll('[data-user]'));
            const map = {};
            tds.forEach(el => {
                const attr = el.getAttribute('data-field');
                if (!attr) return;
                const parts = attr.split('_'); // dues_status_YYYY or dues_amount_YYYY
                const kind = parts[1]; // status or amount
                const year = parts[2];
                map[year] = map[year] || {
                    year: parseInt(year, 10),
                    status: 'Unpaid',
                    amount: ''
                };
                if (kind === 'status') map[year].status = el.value;
                if (kind === 'amount') map[year].amount = el.value;
            });
            const dues = Object.values(map);
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
            alert('Saved');
        });

        loadGrid();
    </script>
</body>

</html>