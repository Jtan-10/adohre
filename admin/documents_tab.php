<?php if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Forbidden');
} ?>
<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Documents</h3>
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#documentModal" onclick="openDocModal()">
            <i class="bi bi-plus-lg"></i> Add Document
        </button>
    </div>

    <div id="documentsList" class="row g-3"></div>

    <!-- Add/Edit Document Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentModalLabel">Add Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="documentForm">
                        <input type="hidden" name="id" id="doc_id" />
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="doc_name" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <input type="text" class="form-control" name="type" id="doc_type" placeholder="Policy, Form, Report" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">PDF File</label>
                            <input type="file" class="form-control" name="file" id="doc_file" accept="application/pdf" />
                            <div class="form-text">Max 20MB. Uploading a new file will replace the existing one.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="saveDocument()">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    async function fetchDocuments() {
        const res = await fetch('../backend/routes/content_manager.php?action=fetch_documents');
        const data = await res.json();
        if (!data.status) return;
        const list = document.getElementById('documentsList');
        list.innerHTML = '';
        data.documents.forEach(doc => {
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4';
            col.innerHTML = `
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h6 class="card-title">${escapeHTML(doc.name)}</h6>
            <p class="mb-1"><strong>Type:</strong> ${escapeHTML(doc.type || '-')}</p>
            <p class="text-muted mb-2"><small>Uploaded: ${new Date(doc.uploaded_at).toLocaleString()}</small></p>
            <div class="mt-auto d-flex gap-2">
              <a class="btn btn-outline-primary btn-sm" target="_blank" href="../pdf_proxy.php?url=${encodeURIComponent(doc.file_url)}">Preview</a>
              <button class="btn btn-outline-secondary btn-sm" onclick='editDocument(${JSON.stringify(doc)})'>Edit</button>
              <button class="btn btn-outline-danger btn-sm" onclick="deleteDocument(${doc.document_id})">Delete</button>
            </div>
          </div>
        </div>`;
            list.appendChild(col);
        });
    }

    function escapeHTML(str) {
        return (str || '').replace(/[&<>"]/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;'
        } [c]));
    }

    function openDocModal() {
        document.getElementById('documentModalLabel').textContent = 'Add Document';
        document.getElementById('doc_id').value = '';
        document.getElementById('doc_name').value = '';
        document.getElementById('doc_type').value = '';
        document.getElementById('doc_file').value = '';
    }

    function editDocument(doc) {
        const m = new bootstrap.Modal(document.getElementById('documentModal'));
        document.getElementById('documentModalLabel').textContent = 'Edit Document';
        document.getElementById('doc_id').value = doc.document_id;
        document.getElementById('doc_name').value = doc.name;
        document.getElementById('doc_type').value = doc.type || '';
        document.getElementById('doc_file').value = '';
        m.show();
    }

    async function saveDocument() {
        const id = document.getElementById('doc_id').value;
        const form = document.getElementById('documentForm');
        const formData = new FormData(form);
        formData.append('action', id ? 'update_document' : 'add_document');
        const res = await fetch('../backend/routes/content_manager.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.status) {
            bootstrap.Modal.getInstance(document.getElementById('documentModal')).hide();
            await fetchDocuments();
        } else {
            alert(data.message || 'Failed to save');
        }
    }

    async function deleteDocument(id) {
        if (!confirm('Delete this document?')) return;
        const fd = new FormData();
        fd.append('action', 'delete_document');
        fd.append('id', id);
        const res = await fetch('../backend/routes/content_manager.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.status) {
            await fetchDocuments();
        } else {
            alert(data.message || 'Failed to delete');
        }
    }

    document.addEventListener('DOMContentLoaded', fetchDocuments);
</script>