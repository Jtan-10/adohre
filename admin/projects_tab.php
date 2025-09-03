<div class="form-section" id="manageProjectsSection">
    <div class="d-flex justify-content-between align-items-center">
        <h3 class="m-0">Manage Projects</h3>
        <button type="button" id="openAddProjectBtn" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#projectModal" data-mode="create">
            <i class="bi bi-plus-lg"></i> Add Project
        </button>
    </div>
    <hr>

    <div id="projectsList"></div>
</div>

<!-- Project Modal -->
<div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="projectModalLabel">Add Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="projectForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="projectTitle" class="form-label">Title</label>
                        <input type="text" id="projectTitle" name="title" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="projectPartner" class="form-label">Partner</label>
                            <select id="projectPartner" name="partner" class="form-select">
                                <option value="">Select partner</option>
                                <option value="National Government Agencies (NGA)">National Government Agencies (NGA)</option>
                                <option value="Local Government Units (LGU)">Local Government Units (LGU)</option>
                                <option value="Private Sector / Businesses">Private Sector / Businesses</option>
                                <option value="Civil Society Organizations (CSOs) / NGOs">Civil Society Organizations (CSOs) / NGOs</option>
                                <option value="Academe / Research Institutions">Academe / Research Institutions</option>
                                <option value="Development Partners / International Organizations">Development Partners / International Organizations</option>
                                <option value="Community / People’s Organizations (POs)">Community / People’s Organizations (POs)</option>
                                <option value="Other">Other (specify)</option>
                            </select>
                            <input type="text" id="projectPartnerOther" class="form-control mt-2 d-none" placeholder="Specify partner">
                        </div>
                        <div class="col-md-6">
                            <label for="projectDate" class="form-label">Start/Reference Month</label>
                            <input type="month" id="projectDate" name="date" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="projectStatus" class="form-label">Status</label>
                            <select id="projectStatus" name="status" class="form-select">
                                <option value="scheduling">Scheduling</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="finished">Finished</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="projectEndDate" class="form-label">End Month (optional)</label>
                            <input type="month" id="projectEndDate" name="end_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="projectDescription" class="form-label">Description</label>
                        <textarea id="projectDescription" name="description" class="form-control" rows="4" placeholder="Short description of the project"></textarea>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="projectImage" class="form-label">Image</label>
                        <input type="file" id="projectImage" name="image" class="form-control" accept="image/*">
                    </div>
                    <input type="hidden" id="projectId" name="id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveProjectBtn" class="btn btn-success">Save</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const list = document.getElementById('projectsList');
        const modal = document.getElementById('projectModal');
        const modalTitle = document.getElementById('projectModalLabel');
        const form = document.getElementById('projectForm');
        const saveBtn = document.getElementById('saveProjectBtn');
        const partnerSelect = document.getElementById('projectPartner');
        const partnerOther = document.getElementById('projectPartnerOther');

        modal.addEventListener('show.bs.modal', (e) => {
            const trigger = e.relatedTarget;
            if (trigger && trigger.getAttribute('data-mode') === 'create') {
                form.reset();
                document.getElementById('projectId').value = '';
                modalTitle.textContent = 'Add Project';
                // Reset partner other field
                if (partnerOther) {
                    partnerOther.classList.add('d-none');
                    partnerOther.value = '';
                }
            }
        });

        // Toggle display of Other partner input
        if (partnerSelect) {
            partnerSelect.addEventListener('change', () => {
                if (partnerSelect.value === 'Other') {
                    partnerOther.classList.remove('d-none');
                    partnerOther.focus();
                } else {
                    partnerOther.classList.add('d-none');
                    partnerOther.value = '';
                }
            });
        }

        function fetchProjects() {
            fetch('../backend/routes/content_manager.php?action=fetch_projects')
                .then(r => r.json())
                .then(data => {
                    if (!data.status) {
                        list.innerHTML = '<p class="text-danger">Failed to load projects.</p>';
                        return;
                    }
                    if (!Array.isArray(data.projects) || data.projects.length === 0) {
                        list.innerHTML = '<p class="text-muted">No projects found.</p>';
                        return;
                    }
                    const html = data.projects.map(p => `
                    <div class="content-item">
                        <div class="row g-2 w-100">
                            <div class="col-md-3">
                                <img src="${ p.image ? '../backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(p.image) : '../assets/default-image.jpg' }" class="img-fluid" alt="Project image">
                            </div>
                            <div class="col-md-9">
                                <h5 class="mb-1">${p.title || ''}</h5>
                                <div class="text-muted mb-1">
                                    ${ p.date ? new Date(p.date).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) : '' }
                                    ${ p.status ? ' • ' + p.status.charAt(0).toUpperCase()+p.status.slice(1) : '' }
                                    ${ p.end_date ? ' • End: ' + new Date(p.end_date).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) : '' }
                                </div>
                                ${ p.partner ? `<div class="text-muted mb-2"><i class=\"fas fa-handshake me-1\"></i>${p.partner}</div>` : '' }
                                ${ p.description ? `<div class="mb-2 small">${p.description.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>` : '' }
                                <div>
                                    <button class="btn btn-primary btn-sm edit-project" data-id="${p.project_id}">Edit</button>
                                    <button class="btn btn-danger btn-sm delete-project ms-2" data-id="${p.project_id}">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
                    list.innerHTML = html;

                    list.querySelectorAll('.edit-project').forEach(btn => btn.addEventListener('click', () => editProject(btn.getAttribute('data-id'))));
                    list.querySelectorAll('.delete-project').forEach(btn => btn.addEventListener('click', () => deleteProject(btn.getAttribute('data-id'))));
                })
                .catch(() => list.innerHTML = '<p class="text-danger">Failed to load projects.</p>');
        }

        function editProject(id) {
            fetch(`../backend/routes/content_manager.php?action=get_project&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.status) {
                        alert('Failed to load project');
                        return;
                    }
                    const p = data.project;
                    document.getElementById('projectId').value = p.project_id;
                    document.getElementById('projectTitle').value = p.title || '';
                    // Handle partner select and Other
                    const partnerVal = p.partner || '';
                    const optionVals = Array.from(partnerSelect.options).map(o => o.value);
                    if (partnerVal && optionVals.includes(partnerVal)) {
                        partnerSelect.value = partnerVal;
                        partnerOther.classList.add('d-none');
                        partnerOther.value = '';
                    } else if (partnerVal) {
                        partnerSelect.value = 'Other';
                        partnerOther.classList.remove('d-none');
                        partnerOther.value = partnerVal;
                    } else {
                        partnerSelect.value = '';
                        partnerOther.classList.add('d-none');
                        partnerOther.value = '';
                    }
                    document.getElementById('projectDescription').value = p.description || '';
                    document.getElementById('projectDate').value = p.date ? p.date.slice(0, 7) : '';
                    document.getElementById('projectStatus').value = p.status || 'scheduling';
                    document.getElementById('projectEndDate').value = p.end_date ? p.end_date.slice(0, 7) : '';
                    modalTitle.textContent = 'Edit Project';
                    new bootstrap.Modal(modal).show();
                });
        }

        function deleteProject(id) {
            if (!confirm('Delete this project?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_project');
            fd.append('id', id);
            const csrf = form.querySelector('input[name="csrf_token"]').value;
            if (csrf) fd.append('csrf_token', csrf);
            fetch('../backend/routes/content_manager.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status) fetchProjects();
                    else alert('Error: ' + data.message);
                })
                .catch(() => alert('Failed to delete project.'));
        }

        saveBtn.addEventListener('click', () => {
            const fd = new FormData(form);
            // Normalize month inputs to YYYY-MM-01 for DB DATE fields, or remove if empty
            const startMonth = document.getElementById('projectDate').value; // YYYY-MM
            const endMonth = document.getElementById('projectEndDate').value; // YYYY-MM
            if (startMonth) {
                fd.set('date', `${startMonth}-01`);
            } else {
                fd.delete('date');
            }
            if (endMonth) {
                fd.set('end_date', `${endMonth}-01`);
            } else {
                fd.delete('end_date');
            }
            // Map Partner 'Other' to provided text
            if (partnerSelect && partnerSelect.value === 'Other') {
                const otherVal = (partnerOther.value || '').trim();
                fd.set('partner', otherVal || 'Other');
            }
            const id = document.getElementById('projectId').value;
            fd.append('action', id ? 'update_project' : 'add_project');
            const csrf = form.querySelector('input[name="csrf_token"]').value;
            if (csrf && !fd.get('csrf_token')) fd.append('csrf_token', csrf);
            fetch('../backend/routes/content_manager.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status) {
                        bootstrap.Modal.getInstance(modal)?.hide();
                        form.reset();
                        document.getElementById('projectId').value = '';
                        fetchProjects();
                    } else {
                        alert('Error: ' + (data.message || 'Save failed'));
                    }
                })
                .catch(() => alert('Failed to save project.'));
        });

        fetchProjects();
    });

    // End date is optional for all statuses; no toggle needed.
</script>