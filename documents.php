<?php
require_once 'header.php';
// Enforce member-only access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Forbidden</title></head><body><div class="container py-5"><h1>403 Forbidden</h1><p>Documents are accessible to members only.</p></div></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-4">
        <h1 class="mb-4">Documents</h1>
        <div id="docsContainer" class="row g-3"></div>
    </div>

    <script>
        async function loadDocs() {
            const res = await fetch('backend/routes/content_manager.php?action=fetch_documents');
            const data = await res.json();
            const container = document.getElementById('docsContainer');
            container.innerHTML = '';
            if (!data.status) {
                container.innerHTML = '<div class="col-12 text-muted">No documents found.</div>';
                return;
            }
            data.documents.forEach(doc => {
                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4';
                col.innerHTML = `
          <div class="card h-100">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">${escapeHTML(doc.name)}</h5>
              <p class="mb-1"><strong>Type:</strong> ${escapeHTML(doc.type || '-')}</p>
              <p class="text-muted mb-3"><small>Uploaded: ${new Date(doc.uploaded_at).toLocaleString()}</small></p>
              <a class="btn btn-success mt-auto" target="_blank" href="pdf_proxy.php?url=${encodeURIComponent(doc.file_url)}">Preview</a>
            </div>
          </div>`;
                container.appendChild(col);
            });
        }

        function escapeHTML(str) {
            return (str || '').replace(/[&<>\"]/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '\"': '&quot;'
            } [c]));
        }
        document.addEventListener('DOMContentLoaded', loadDocs);
    </script>
</body>

</html>