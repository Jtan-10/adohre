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
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Select Member</label>
                            <select id="memberSelect" class="form-select">
                                <option value="">Loading...</option>
                            </select>
                        </div>
                        <div class="col-md-6 text-end">
                            <button id="refreshBtn" class="btn btn-outline-secondary">Refresh</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="memberForms" class="d-none">
                <div class="card mb-3">
                    <div class="card-header">Profile</div>
                    <div class="card-body">
                        <form id="profileForm" class="row g-3">
                            <input type="hidden" id="pf_user_id">
                            <div class="col-md-6">
                                <label class="form-label">Name</label>
                                <input type="text" id="pf_name" class="form-control" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Membership Status</label>
                                <select id="pf_membership_status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year of Membership</label>
                                <input type="number" id="pf_year" class="form-control" min="1900" max="2100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Age upon Membership</label>
                                <input type="number" id="pf_age" class="form-control" min="0" max="150">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Membership Certification</label>
                                <select id="pf_cert" class="form-select">
                                    <option value="Regular">Regular</option>
                                    <option value="Honorary">Honorary</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Membership Fee</label>
                                <input type="number" step="0.01" id="pf_fee" class="form-control">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">Save Profile</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Annual Dues</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle" id="duesTable">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <button id="saveDuesBtn" class="btn btn-success">Save Dues</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= $cspNonce ?>"></script>
    <script nonce="<?= $cspNonce ?>">
        const api = async (url, opts = {}) => {
            const res = await fetch(url, Object.assign({
                headers: {
                    'X-Requested-With': 'fetch'
                }
            }, opts));
            return res.json();
        };

        const memberSelect = document.getElementById('memberSelect');
        const memberForms = document.getElementById('memberForms');
        const profileForm = document.getElementById('profileForm');
        const duesTableBody = document.querySelector('#duesTable tbody');

        async function loadMembers() {
            memberSelect.innerHTML = '<option value="">Loading...</option>';
            const data = await api('../backend/routes/membership_status.php?action=list');
            if (!data.status) {
                memberSelect.innerHTML = '<option value="">Failed to load</option>';
                return;
            }
            memberSelect.innerHTML = '<option value="">Select member...</option>' + data.data.map(u =>
                `<option value="${u.user_id}">${u.last_name}, ${u.first_name}</option>`
            ).join('');
        }

        async function loadMember(user_id) {
            if (!user_id) {
                memberForms.classList.add('d-none');
                return;
            }
            const data = await api(`../backend/routes/membership_status.php?action=get_member&user_id=${user_id}`);
            if (!data.status) {
                alert(data.message || 'Load failed');
                return;
            }
            memberForms.classList.remove('d-none');
            // Fill profile
            document.getElementById('pf_user_id').value = data.profile.user_id;
            document.getElementById('pf_name').value = `${data.profile.last_name}, ${data.profile.first_name}`;
            document.getElementById('pf_membership_status').value = data.profile.membership_status || 'inactive';
            document.getElementById('pf_year').value = data.profile.year_of_membership || '';
            document.getElementById('pf_age').value = data.profile.age_upon_membership || '';
            document.getElementById('pf_cert').value = data.profile.certification || 'Regular';
            document.getElementById('pf_fee').value = data.profile.membership_fee || '';
            // Fill dues
            duesTableBody.innerHTML = data.dues.map(d => `
        <tr>
            <td>${d.year}</td>
            <td>
                <select class="form-select form-select-sm" data-year="${d.year}">
                    <option value="Paid" ${d.status==='Paid'?'selected':''}>Paid</option>
                    <option value="Unpaid" ${d.status==='Unpaid'?'selected':''}>Unpaid</option>
                    <option value="Waived" ${d.status==='Waived'?'selected':''}>Waived</option>
                </select>
            </td>
            <td><input type="number" step="0.01" class="form-control form-control-sm" data-amount-year="${d.year}" value="${d.amount ?? ''}"></td>
        </tr>
    `).join('');
        }

        memberSelect.addEventListener('change', (e) => loadMember(e.target.value));
        document.getElementById('refreshBtn').addEventListener('click', loadMembers);

        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const user_id = document.getElementById('pf_user_id').value;
            const fd = new FormData();
            fd.append('action', 'save_profile');
            fd.append('user_id', user_id);
            fd.append('membership_status', document.getElementById('pf_membership_status').value);
            fd.append('year_of_membership', document.getElementById('pf_year').value);
            fd.append('age_upon_membership', document.getElementById('pf_age').value);
            fd.append('certification', document.getElementById('pf_cert').value);
            fd.append('membership_fee', document.getElementById('pf_fee').value);
            const res = await api('../backend/routes/membership_status.php', {
                method: 'POST',
                body: fd
            });
            if (!res.status) return alert(res.message || 'Save failed');
            alert('Profile saved');
        });

        document.getElementById('saveDuesBtn').addEventListener('click', async () => {
            const user_id = document.getElementById('pf_user_id').value;
            const rows = Array.from(duesTableBody.querySelectorAll('tr'));
            const dues = rows.map(r => {
                const y = parseInt(r.children[0].textContent, 10);
                const s = r.querySelector('select').value;
                const a = r.querySelector('input').value;
                return {
                    year: y,
                    status: s,
                    amount: a
                };
            });
            const fd = new FormData();
            fd.append('action', 'save_dues');
            fd.append('user_id', user_id);
            fd.append('dues', JSON.stringify(dues));
            const res = await api('../backend/routes/membership_status.php', {
                method: 'POST',
                body: fd
            });
            if (!res.status) return alert(res.message || 'Save failed');
            alert('Dues saved');
        });

        // Initial loads
        loadMembers();
    </script>
</body>

</html>