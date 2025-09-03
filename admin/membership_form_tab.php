<?php if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Forbidden');
} ?>
<div class="container mt-3">
    <h3>Membership Form Builder</h3>
    <p class="text-muted">Edit the form schema in JSON. Fields map to the existing form sections. Keep names consistent with handler expectations.</p>
    <div class="mb-2">
        <button class="btn btn-sm btn-outline-secondary" onclick="loadSchema()">Reload</button>
        <button class="btn btn-sm btn-primary" onclick="saveSchema()">Save</button>
    </div>
    <textarea id="membershipSchema" class="form-control" rows="24" spellcheck="false" style="font-family: monospace;"></textarea>
    <small class="text-muted">Tip: Valid JSON required. Use double quotes for keys/strings.</small>

    <script>
        async function loadSchema() {
            const res = await fetch('../backend/routes/settings_api.php?action=get_membership_form_schema');
            const j = await res.json();
            if (!j.status) {
                alert(j.message || 'Failed to load');
                return;
            }
            document.getElementById('membershipSchema').value = JSON.stringify(j.schema, null, 2);
        }
        async function saveSchema() {
            let text = document.getElementById('membershipSchema').value;
            let parsed;
            try {
                parsed = JSON.parse(text);
            } catch (e) {
                alert('Invalid JSON: ' + e.message);
                return;
            }
            const fd = new FormData();
            fd.append('schema', JSON.stringify(parsed));
            const res = await fetch('../backend/routes/settings_api.php?action=update_membership_form_schema', {
                method: 'POST',
                body: fd
            });
            const j = await res.json();
            if (j.status) {
                alert('Saved');
            } else {
                alert(j.message || 'Save failed');
            }
        }
        document.addEventListener('DOMContentLoaded', loadSchema);
    </script>
</div>