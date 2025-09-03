<?php if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Forbidden');
} ?>
<div class="container mt-3">
    <h3>Membership Form Builder</h3>
    <p class="text-muted">Configure labels, required fields, and options. No JSON needed.</p>

    <div class="d-flex gap-2 mb-3">
        <button class="btn btn-sm btn-outline-secondary" id="mfReload">Reload</button>
        <button class="btn btn-sm btn-primary" id="mfSave">Save</button>
        <a class="btn btn-sm btn-outline-primary" href="../membership_form.php" target="_blank">Open Membership Form</a>
    </div>

    <div id="mfBuilder" class="accordion">
        <!-- Sections will be injected here by JS -->
    </div>

    <script>
        let schema = null;

        function el(tag, attrs = {}, children = []) {
            const e = document.createElement(tag);
            Object.entries(attrs).forEach(([k, v]) => {
                if (k === 'class') e.className = v;
                else if (k === 'html') e.innerHTML = v;
                else e.setAttribute(k, v);
            });
            (Array.isArray(children) ? children : [children]).forEach(c => {
                if (c instanceof Node) e.appendChild(c);
                else if (c !== null && c !== undefined) e.appendChild(document.createTextNode(c));
            });
            return e;
        }

        function inputGroup(labelText, inputEl) {
            const wrap = el('div', {
                class: 'mb-2'
            });
            const lbl = el('label', {
                class: 'form-label small'
            }, labelText);
            wrap.appendChild(lbl);
            wrap.appendChild(inputEl);
            return wrap;
        }

        function buildTextField(name, cfg) {
            const col = el('div', {
                class: 'col-md-6'
            });
            const label = el('input', {
                class: 'form-control form-control-sm',
                value: cfg.label || '',
                'data-name': name,
                'data-prop': 'label'
            });
            const req = el('input', {
                type: 'checkbox',
                class: 'form-check-input',
                'data-name': name,
                'data-prop': 'required'
            });
            req.checked = !!cfg.required;
            col.appendChild(inputGroup('Label', label));
            const cbWrap = el('div', {
                class: 'form-check mb-2'
            }, [req, el('label', {
                class: 'form-check-label ms-2 small'
            }, 'Required')]);
            col.appendChild(cbWrap);
            return col;
        }

        function buildOptionsEditor(groupCfg) {
            const container = el('div');
            const list = el('div', {
                class: 'd-flex flex-column gap-1 mb-2'
            });

            function addRow(val = '') {
                const row = el('div', {
                    class: 'd-flex gap-2 align-items-center'
                });
                const inp = el('input', {
                    class: 'form-control form-control-sm',
                    value: val
                });
                const rm = el('button', {
                    type: 'button',
                    class: 'btn btn-sm btn-outline-danger'
                }, 'Remove');
                rm.addEventListener('click', () => row.remove());
                row.appendChild(inp);
                row.appendChild(rm);
                list.appendChild(row);
            }
            (groupCfg.options || []).forEach(addRow);
            const addBtn = el('button', {
                type: 'button',
                class: 'btn btn-sm btn-outline-secondary'
            }, 'Add option');
            addBtn.addEventListener('click', () => addRow(''));
            const others = el('input', {
                type: 'checkbox',
                class: 'form-check-input'
            });
            others.checked = !!groupCfg.includeOthers;
            const othersWrap = el('div', {
                class: 'form-check mt-2'
            }, [others, el('label', {
                class: 'form-check-label ms-2 small'
            }, 'Include "Others (Specify)"')]);
            container.appendChild(list);
            container.appendChild(addBtn);
            container.appendChild(othersWrap);
            container.buildValue = () => ({
                options: Array.from(list.querySelectorAll('input.form-control')).map(i => i.value).filter(Boolean),
                includeOthers: others.checked
            });
            return container;
        }

        function buildSection(title, fields) {
            const idx = document.querySelectorAll('#mfBuilder .accordion-item').length + 1;
            const body = el('div', {
                class: 'row g-3'
            });
            fields.forEach(f => body.appendChild(f));
            const item = el('div', {
                class: 'accordion-item'
            });
            const header = el('h2', {
                class: 'accordion-header'
            });
            const btn = el('button', {
                class: 'accordion-button collapsed',
                type: 'button',
                'data-bs-toggle': 'collapse',
                'data-bs-target': `#mfSec${idx}`
            }, title);
            header.appendChild(btn);
            const collapse = el('div', {
                id: `mfSec${idx}`,
                class: 'accordion-collapse collapse'
            });
            const inner = el('div', {
                class: 'accordion-body'
            });
            inner.appendChild(body);
            collapse.appendChild(inner);
            item.appendChild(header);
            item.appendChild(collapse);
            return item;
        }

        function renderBuilder() {
            const root = document.getElementById('mfBuilder');
            root.innerHTML = '';
            if (!schema) return;
            // Section 1
            const s1 = schema.section1?.fields || {};
            const s1Fields = Object.entries(s1).map(([name, cfg]) => buildTextField(name, cfg));
            root.appendChild(buildSection(schema.section1?.title || '1. Personal Information', s1Fields));
            // Section 2
            const s2 = schema.section2?.fields || {};
            const s2Fields = Object.entries(s2).map(([name, cfg]) => buildTextField(name, cfg));
            root.appendChild(buildSection(schema.section2?.title || '2. Employment Record with DOH', s2Fields));
            // Section 3
            const s3 = schema.section3?.fields || {};
            const s3Fields = Object.entries(s3).map(([name, cfg]) => buildTextField(name, cfg));
            root.appendChild(buildSection(schema.section3?.title || '3. Highest Educational Background', s3Fields));
            // Section 4 - radio group
            const s4 = schema.section4?.group || {};
            const s4Opt = buildOptionsEditor(s4);
            root.appendChild(buildSection(schema.section4?.title || '4. Current Engagement', [inputGroup('Options', s4Opt)]));
            // Section 5 - two groups
            const s5 = schema.section5?.groups || [];
            const s5Wrap = el('div');
            s5.forEach((g, idx) => {
                const ge = buildOptionsEditor(g);
                const titled = inputGroup((g.label || (idx === 0 ? 'Key Expertise' : 'Indicate specific field:')), ge);
                s5Wrap.appendChild(titled);
                ge.dataset.groupIndex = idx;
            });
            root.appendChild(buildSection(schema.section5?.title || '5. Key Expertise', [s5Wrap]));
            // Section 6
            const s6 = schema.section6?.fields || {};
            const s6Fields = Object.entries(s6).map(([name, cfg]) => buildTextField(name, cfg));
            root.appendChild(buildSection(schema.section6?.title || '6. Other Skills', s6Fields));
            // Section 7
            const s7 = schema.section7?.group || {};
            const s7Opt = buildOptionsEditor(s7);
            root.appendChild(buildSection(schema.section7?.title || '7. Committees', [inputGroup('Options', s7Opt)]));
        }

        function collectBuilder() {
            // Update schema object based on UI
            ['section1', 'section2', 'section3', 'section6'].forEach(sec => {
                const fields = schema[sec]?.fields || {};
                Object.keys(fields).forEach(name => {
                    const labelEl = document.querySelector(`[data-name="${name}"][data-prop="label"]`);
                    const reqEl = document.querySelector(`[data-name="${name}"][data-prop="required"]`);
                    if (labelEl) fields[name].label = labelEl.value;
                    if (reqEl) fields[name].required = !!reqEl.checked;
                });
            });
            // Section 4
            const s4 = schema.section4?.group;
            if (s4) {
                const opt = document.querySelector('#mfBuilder .accordion-item:nth-child(4) .accordion-body .form-label + div');
                if (opt && opt.buildValue) {
                    const v = opt.buildValue();
                    s4.options = v.options;
                    s4.includeOthers = v.includeOthers;
                }
            }
            // Section 5 (two groups)
            if (Array.isArray(schema.section5?.groups)) {
                const s5wrap = document.querySelector('#mfBuilder .accordion-item:nth-child(5) .accordion-body');
                const editors = s5wrap ? s5wrap.querySelectorAll('div .form-label + div') : [];
                editors.forEach((ed, idx) => {
                    if (ed.buildValue) {
                        const v = ed.buildValue();
                        schema.section5.groups[idx].options = v.options;
                        schema.section5.groups[idx].includeOthers = v.includeOthers;
                    }
                });
            }
            // Section 7
            const s7 = schema.section7?.group;
            if (s7) {
                const opt = document.querySelector('#mfBuilder .accordion-item:nth-child(7) .accordion-body .form-label + div');
                if (opt && opt.buildValue) {
                    const v = opt.buildValue();
                    s7.options = v.options;
                    s7.includeOthers = v.includeOthers;
                }
            }
            return schema;
        }

        async function loadSchema() {
            const res = await fetch('../backend/routes/settings_api.php?action=get_membership_form_schema');
            const j = await res.json();
            if (!j.status) {
                alert(j.message || 'Failed to load');
                return;
            }
            schema = j.schema;
            renderBuilder();
        }
        async function saveSchema() {
            const updated = collectBuilder();
            const fd = new FormData();
            fd.append('schema', JSON.stringify(updated));
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

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('mfReload').addEventListener('click', loadSchema);
            document.getElementById('mfSave').addEventListener('click', saveSchema);
            loadSchema();
        });
    </script>
</div>