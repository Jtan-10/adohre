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
                    <div class="mb-3">
                        <label for="projectDescription" class="form-label">Description</label>
                        <textarea id="projectDescription" name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="projectDate" class="form-label">Date</label>
                            <input type="date" id="projectDate" name="date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="projectLocation" class="form-label">Location</label>
                            <input type="text" id="projectLocation" name="location" class="form-control">
                        </div>
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

        modal.addEventListener('show.bs.modal', (e) => {
            const trigger = e.relatedTarget;
            if (trigger && trigger.getAttribute('data-mode') === 'create') {
                form.reset();
                document.getElementById('projectId').value = '';
                modalTitle.textContent = 'Add Project';
            }
        });

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
                                <div class="text-muted mb-1">${ p.date ? new Date(p.date).toLocaleDateString() : '' } ${ p.location ? ' • ' + p.location : '' }</div>
                                <p class="mb-2">${p.description || ''}</p>
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
                    document.getElementById('projectDescription').value = p.description || '';
                    document.getElementById('projectDate').value = p.date || '';
                    document.getElementById('projectLocation').value = p.location || '';
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
</script>