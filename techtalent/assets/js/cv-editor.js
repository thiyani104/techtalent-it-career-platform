/**
 * TechTalent CV Studio editor (used by cv-editor.php and the import review screen).
 * Reads its configuration from <script id="cvConfig" type="application/json">.
 */
(function () {
    'use strict';

    var cfg = JSON.parse(document.getElementById('cvConfig').textContent);
    var state = cfg.data;
    var meta = { title: cfg.title, template: cfg.template, accent: cfg.accent };
    var dirty = false;
    var saving = false;
    var autosaveTimer = null;
    var renderTimer = null;
    var openSections = { personal: true };

    var LEVELS = ['', 'Beginner', 'Intermediate', 'Advanced', 'Expert'];
    var SECTIONS = {
        experience: { label: 'Work Experience', icon: 'bi-briefcase', add: 'Add experience', fields: [
            ['position', 'Position / job title'], ['company', 'Company'], ['location', 'Location'],
            ['start', 'Start date', 'text', 'e.g. Jun 2023'], ['end', 'End date', 'text', 'e.g. Dec 2024 or Present'],
            ['responsibilities', 'Responsibilities (one per line)', 'textarea'], ['achievements', 'Achievements (one per line)', 'textarea']] },
        education: { label: 'Education', icon: 'bi-mortarboard', add: 'Add education', fields: [
            ['qualification', 'Qualification', 'text', 'e.g. BSc (Hons)'], ['field', 'Field of study', 'text', 'e.g. Information Technology'],
            ['institution', 'Institution'], ['start', 'Start date', 'text', 'e.g. 2019'], ['end', 'End date', 'text', 'e.g. 2023'],
            ['description', 'Details (GPA, honours, modules)', 'textarea']] },
        projects: { label: 'Projects', icon: 'bi-kanban', add: 'Add project', fields: [
            ['name', 'Project name'], ['role', 'Your role'], ['link', 'Link', 'text', 'github.com/you/project'],
            ['start', 'Start date'], ['end', 'End date'], ['description', 'Description (one point per line)', 'textarea']] },
        certifications: { label: 'Certifications', icon: 'bi-award', add: 'Add certification', fields: [
            ['name', 'Certification'], ['issuer', 'Issuer'], ['date', 'Date', 'text', 'e.g. Mar 2024'], ['link', 'Credential link']] },
        languages: { label: 'Languages', icon: 'bi-translate', add: 'Add language', fields: [
            ['name', 'Language'], ['level', 'Level', 'text', 'e.g. Fluent, Native']] }
    };
    var PERSONAL = [
        ['full_name', 'Full name', 'col-md-6'], ['title', 'Professional title', 'col-md-6'],
        ['email', 'Email', 'col-md-6', 'email'], ['phone', 'Phone', 'col-md-6'],
        ['location', 'Location / address', 'col-md-12'], ['linkedin', 'LinkedIn', 'col-md-4'],
        ['github', 'GitHub', 'col-md-4'], ['portfolio', 'Portfolio / website', 'col-md-4']
    ];
    var OPTIONAL = ['projects', 'certifications', 'languages'];

    var $ = function (id) { return document.getElementById(id); };
    var form = $('cvForm');

    /* ---------------- small DOM helper (text only, never innerHTML) ---------------- */
    function h(tag, attrs, children) {
        var n = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'class') n.className = attrs[k];
            else if (k === 'text') n.textContent = attrs[k];
            else if (k === 'value') n.value = attrs[k];
            else if (k === 'checked') n.checked = !!attrs[k];
            else if (k.indexOf('data-') === 0 || ['type', 'placeholder', 'rows', 'id', 'for', 'list', 'title', 'aria-label', 'maxlength', 'role'].indexOf(k) >= 0) n.setAttribute(k, attrs[k]);
        });
        (children || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
        return n;
    }
    function icon(name) { return h('i', { class: 'bi ' + name }); }
    function btn(cls, title, iconName, data) {
        var b = h('button', Object.assign({ type: 'button', class: 'btn btn-sm ' + cls, title: title, 'aria-label': title }, data), [icon(iconName)]);
        return b;
    }

    function getPath(path) {
        return path.split('.').reduce(function (o, k) { return o == null ? undefined : o[k]; }, state);
    }
    function setPath(path, value) {
        var keys = path.split('.');
        var o = state;
        for (var i = 0; i < keys.length - 1; i++) o = o[keys[i]];
        o[keys[keys.length - 1]] = value;
    }

    /* ---------------- form construction ---------------- */
    function field(label, path, type, placeholder, colCls) {
        var id = 'f_' + path.replace(/\./g, '_');
        var input;
        if (type === 'textarea') {
            input = h('textarea', { class: 'form-control form-control-sm', id: id, rows: 3, 'data-path': path, placeholder: placeholder || '' });
        } else {
            input = h('input', { class: 'form-control form-control-sm', id: id, type: type || 'text', 'data-path': path, placeholder: placeholder || '' });
        }
        input.value = getPath(path) || '';
        return h('div', { class: colCls || 'col-md-6' }, [h('label', { class: 'form-label small fw-semibold mb-1', for: id, text: label }), input]);
    }

    function card(key, title, iconName, body, tools) {
        var open = !!openSections[key];
        var header = h('div', { class: 'card-header d-flex align-items-center justify-content-between gap-2 py-2' }, [
            h('button', { type: 'button', class: 'btn btn-link text-decoration-none text-dark fw-semibold p-0 text-start flex-grow-1', 'data-act': 'toggle', 'data-sec': key },
                [icon(iconName + ' me-2 text-primary'), title, h('i', { class: 'bi ms-2 small text-muted ' + (open ? 'bi-chevron-up' : 'bi-chevron-down') })]),
            tools || null
        ]);
        var bodyWrap = h('div', { class: 'card-body' + (open ? '' : ' d-none') }, [body]);
        return h('div', { class: 'card cv-section-card mb-3 border-0 shadow-sm', 'data-card': key }, [header, bodyWrap]);
    }

    function sectionTools(key) {
        var wrap = h('div', { class: 'd-flex align-items-center gap-1' });
        if (OPTIONAL.indexOf(key) >= 0) {
            var sw = h('div', { class: 'form-check form-switch mb-0 me-1', title: 'Show this section in the CV' }, [
                h('input', { class: 'form-check-input', type: 'checkbox', id: 'show_' + key, 'data-hide': key, checked: !state.hidden[key] }),
                h('label', { class: 'form-check-label small', for: 'show_' + key, text: 'Show' })
            ]);
            wrap.appendChild(sw);
        }
        if (key !== 'personal') {
            wrap.appendChild(btn('btn-light', 'Move section up', 'bi-arrow-up', { 'data-act': 'move-section', 'data-sec': key, 'data-dir': '-1' }));
            wrap.appendChild(btn('btn-light', 'Move section down', 'bi-arrow-down', { 'data-act': 'move-section', 'data-sec': key, 'data-dir': '1' }));
        }
        return wrap;
    }

    function buildPersonal() {
        var row = h('div', { class: 'row g-2' });
        PERSONAL.forEach(function (f) { row.appendChild(field(f[1], 'personal.' + f[0], f[3], '', f[2])); });
        return card('personal', 'Personal Details', 'bi-person-vcard', row);
    }

    function buildSummary() {
        var body = h('div', {}, [field('Professional summary (2–4 sentences)', 'personal.summary', 'textarea', 'Who you are, your strongest skills, and the role you are targeting.', 'col-12')]);
        body.querySelector('textarea').rows = 5;
        return card('summary', 'Professional Summary', 'bi-card-text', body, sectionTools('summary'));
    }

    function buildEntries(key) {
        var def = SECTIONS[key];
        var list = state[key];
        var body = h('div', {});
        if (!list.length) body.appendChild(h('p', { class: 'text-muted small mb-2', text: 'No entries yet.' }));
        list.forEach(function (entry, i) {
            var tools = h('div', { class: 'cv-entry-tools' }, [
                btn('btn-light', 'Move up', 'bi-arrow-up', { 'data-act': 'move-entry', 'data-sec': key, 'data-idx': i, 'data-dir': '-1' }),
                btn('btn-light', 'Move down', 'bi-arrow-down', { 'data-act': 'move-entry', 'data-sec': key, 'data-idx': i, 'data-dir': '1' }),
                btn('btn-outline-danger', 'Remove entry', 'bi-trash', { 'data-act': 'remove-entry', 'data-sec': key, 'data-idx': i })
            ]);
            var title = entry[def.fields[0][0]] || entry[def.fields[1][0]] || ('Entry ' + (i + 1));
            var row = h('div', { class: 'row g-2' });
            def.fields.forEach(function (f) {
                row.appendChild(field(f[1], key + '.' + i + '.' + f[0], f[2], f[3], f[2] === 'textarea' ? 'col-12' : 'col-md-6'));
            });
            body.appendChild(h('div', { class: 'cv-entry-card' }, [
                h('div', { class: 'd-flex justify-content-between align-items-center mb-2' }, [h('span', { class: 'small fw-semibold text-truncate', text: (i + 1) + '. ' + title }), tools]),
                row
            ]));
        });
        body.appendChild(h('button', { type: 'button', class: 'btn btn-sm btn-outline-primary', 'data-act': 'add-entry', 'data-sec': key }, [icon('bi-plus-lg me-1'), def.add]));
        var count = list.length ? ' (' + list.length + ')' : '';
        return card(key, def.label + count, def.icon, body, sectionTools(key));
    }

    function buildSkills() {
        var body = h('div', {});
        [['technical', 'Technical skills'], ['soft', 'Soft skills']].forEach(function (pair) {
            var type = pair[0];
            body.appendChild(h('div', { class: 'small fw-semibold mb-1 mt-1', text: pair[1] }));
            state.skills[type].forEach(function (s, i) {
                var sel = h('select', { class: 'form-select form-select-sm', style: '', 'data-path': 'skills.' + type + '.' + i + '.level', 'aria-label': 'Proficiency' });
                LEVELS.forEach(function (l) { sel.appendChild(h('option', { value: l, text: l || 'Level (optional)' })); });
                sel.value = s.level || '';
                sel.style.maxWidth = '170px';
                var name = h('input', { class: 'form-control form-control-sm', type: 'text', 'data-path': 'skills.' + type + '.' + i + '.name', list: 'cvSkillList', 'aria-label': 'Skill name' });
                name.value = s.name;
                body.appendChild(h('div', { class: 'cv-skill-item' }, [name, sel,
                    btn('btn-light', 'Move up', 'bi-arrow-up', { 'data-act': 'move-skill', 'data-type': type, 'data-idx': i, 'data-dir': '-1' }),
                    btn('btn-outline-danger', 'Remove skill', 'bi-x-lg', { 'data-act': 'remove-skill', 'data-type': type, 'data-idx': i })]));
            });
            var add = h('input', { class: 'form-control form-control-sm', type: 'text', list: 'cvSkillList', placeholder: type === 'technical' ? 'Add a skill, e.g. PHP' : 'Add a soft skill, e.g. Teamwork', id: 'addSkill_' + type });
            body.appendChild(h('div', { class: 'input-group input-group-sm mb-3' }, [add,
                h('button', { type: 'button', class: 'btn btn-outline-primary', 'data-act': 'add-skill', 'data-type': type }, [icon('bi-plus-lg')])]));
        });
        var n = state.skills.technical.length + state.skills.soft.length;
        return card('skills', 'Skills' + (n ? ' (' + n + ')' : ''), 'bi-tools', body, sectionTools('skills'));
    }

    function buildForm() {
        var scroll = form.scrollTop;
        form.innerHTML = '';
        form.appendChild(buildPersonal());
        state.order.forEach(function (key) {
            if (key === 'summary') form.appendChild(buildSummary());
            else if (key === 'skills') form.appendChild(buildSkills());
            else form.appendChild(buildEntries(key));
        });
        form.scrollTop = scroll;
    }

    /* ---------------- preview ---------------- */
    function renderPreview() {
        var pages = CVRender.render($('cvPages'), state, meta);
        CVRender.fit($('cvScale'), $('cvWrap'), parseFloat($('cvZoom') ? $('cvZoom').value : 0) || 0);
        if ($('cvPageCount')) $('cvPageCount').textContent = pages + (pages === 1 ? ' page' : ' pages');
    }
    function schedulePreview() {
        clearTimeout(renderTimer);
        renderTimer = setTimeout(renderPreview, 150);
    }

    /* ---------------- state changes ---------------- */
    function changed(structural) {
        dirty = true;
        setStatus('Unsaved changes', 'text-warning');
        if (structural) buildForm();
        schedulePreview();
        if (cfg.mode === 'edit') {
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(function () { save(false); }, 4000);
        }
    }

    function blank(key) {
        var o = {};
        SECTIONS[key].fields.forEach(function (f) { o[f[0]] = ''; });
        return o;
    }

    function move(arr, i, dir) {
        var j = i + dir;
        if (j < 0 || j >= arr.length) return false;
        var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
        return true;
    }

    form.addEventListener('input', function (e) {
        var path = e.target.getAttribute('data-path');
        if (!path) return;
        setPath(path, e.target.value);
        changed(false);
    });

    form.addEventListener('change', function (e) {
        var hide = e.target.getAttribute('data-hide');
        if (hide) { state.hidden[hide] = !e.target.checked; changed(false); }
    });

    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.id && e.target.id.indexOf('addSkill_') === 0) {
            e.preventDefault();
            addSkill(e.target.id.replace('addSkill_', ''));
        }
    });

    function addSkill(type) {
        var input = $('addSkill_' + type);
        var name = (input.value || '').trim();
        if (!name) return;
        var exists = state.skills.technical.concat(state.skills.soft).some(function (s) { return s.name.toLowerCase() === name.toLowerCase(); });
        if (!exists) state.skills[type].push({ name: name, level: '' });
        changed(true);
        var again = $('addSkill_' + type);
        if (again) again.focus();
    }

    form.addEventListener('click', function (e) {
        var b = e.target.closest('[data-act]');
        if (!b) return;
        var act = b.getAttribute('data-act');
        var sec = b.getAttribute('data-sec');
        var idx = parseInt(b.getAttribute('data-idx'), 10);
        var dir = parseInt(b.getAttribute('data-dir'), 10);
        var type = b.getAttribute('data-type');
        if (act === 'toggle') {
            openSections[sec] = !openSections[sec];
            buildForm();
            return;
        }
        if (act === 'add-entry') {
            state[sec].push(blank(sec));
            openSections[sec] = true;
            if (OPTIONAL.indexOf(sec) >= 0) state.hidden[sec] = false;
            changed(true);
        } else if (act === 'remove-entry') {
            if (!confirm('Remove this entry?')) return;
            state[sec].splice(idx, 1);
            changed(true);
        } else if (act === 'move-entry') {
            if (move(state[sec], idx, dir)) changed(true);
        } else if (act === 'add-skill') {
            addSkill(type);
        } else if (act === 'remove-skill') {
            state.skills[type].splice(idx, 1);
            changed(true);
        } else if (act === 'move-skill') {
            if (move(state.skills[type], idx, dir)) changed(true);
        } else if (act === 'move-section') {
            if (move(state.order, state.order.indexOf(sec), dir)) changed(true);
        }
    });

    /* ---------------- toolbar ---------------- */
    document.querySelectorAll('.cv-tpl-btn').forEach(function (b) {
        b.classList.toggle('active', b.getAttribute('data-tpl') === meta.template);
        b.addEventListener('click', function () {
            meta.template = b.getAttribute('data-tpl');
            document.querySelectorAll('.cv-tpl-btn').forEach(function (x) { x.classList.toggle('active', x === b); });
            changed(false);
        });
    });
    if ($('cvAccent')) {
        $('cvAccent').value = meta.accent;
        $('cvAccent').addEventListener('input', function () { meta.accent = this.value; changed(false); });
    }
    if ($('cvTitle')) {
        $('cvTitle').value = meta.title;
        $('cvTitle').addEventListener('input', function () { meta.title = this.value; changed(false); });
    }
    if ($('cvZoom')) $('cvZoom').addEventListener('change', renderPreview);
    window.addEventListener('resize', function () { schedulePreview(); });

    /* ---------------- server calls ---------------- */
    function api(payload) {
        payload.csrf = cfg.csrf;
        return fetch(cfg.api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: 'Unexpected server response (' + r.status + ').' }; });
        }).catch(function () { return { ok: false, error: 'Network error. Check your connection and try again.' }; });
    }

    function setStatus(text, cls) {
        var s = $('saveStatus');
        if (!s) return;
        s.className = 'small ' + (cls || 'text-muted');
        s.textContent = text;
    }

    function validTitle() {
        if (!meta.title || !meta.title.trim()) {
            setStatus('Please give this CV a name.', 'text-danger');
            if ($('cvTitle')) $('cvTitle').focus();
            return false;
        }
        return true;
    }

    function save(manual) {
        clearTimeout(autosaveTimer);
        if (saving || !validTitle()) return Promise.resolve(false);
        if (cfg.mode === 'import') return createFromImport();
        if (!dirty && !manual) return Promise.resolve(true);
        saving = true;
        setStatus('Saving…', 'text-muted');
        var wasDirty = dirty;
        dirty = false;
        return api({ action: 'save', cv_id: cfg.cvId, title: meta.title, template: meta.template, accent: meta.accent, content: state })
            .then(function (res) {
                saving = false;
                if (res.ok) {
                    setStatus('All changes saved · ' + res.saved_at, 'text-success');
                    return true;
                }
                dirty = dirty || wasDirty;
                setStatus('Not saved: ' + (res.error || 'unknown error'), 'text-danger');
                return false;
            });
    }

    function createFromImport() {
        saving = true;
        setStatus('Creating CV…', 'text-muted');
        return api({ action: 'create_import', token: cfg.importToken, title: meta.title, template: meta.template, accent: meta.accent, content: state })
            .then(function (res) {
                saving = false;
                if (res.ok) {
                    dirty = false;
                    window.location.href = res.redirect;
                    return true;
                }
                setStatus('Not saved: ' + (res.error || 'unknown error'), 'text-danger');
                return false;
            });
    }

    if ($('saveBtn')) $('saveBtn').addEventListener('click', function () { save(true); });
    if ($('printBtn')) $('printBtn').addEventListener('click', function () {
        if (cfg.mode !== 'edit') { window.print(); return; }
        // Open the window synchronously (avoids popup blockers), then point it at the print view once saved.
        var w = window.open('', '_blank');
        save(true).then(function (ok) {
            if (!ok) { if (w) w.close(); return; }
            var url = 'cv-print.php?id=' + encodeURIComponent(cfg.cvId) + '&autoprint=1';
            if (w) w.location.href = url; else window.location.href = url;
        });
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(true); }
    });

    /* ---------------- ATS checker ---------------- */
    function runAts() {
        var out = $('atsResults');
        out.innerHTML = '';
        out.appendChild(h('div', { class: 'text-muted small' }, [h('span', { class: 'spinner-border spinner-border-sm me-2' }), 'Checking…']));
        api({ action: 'ats', template: meta.template, content: state }).then(function (res) {
            out.innerHTML = '';
            if (!res.ok) { out.appendChild(h('div', { class: 'alert alert-danger small', text: res.error || 'Check failed.' })); return; }
            var s = res.result.summary;
            var cls = s.score >= 80 ? 'success' : (s.score >= 60 ? 'warning' : 'danger');
            out.appendChild(h('div', { class: 'd-flex align-items-center gap-3 mb-3' }, [
                h('div', { class: 'display-6 fw-bold text-' + cls, text: s.score + '%' }),
                h('div', { class: 'small' }, [
                    h('div', { class: 'fw-semibold', text: 'Readiness score (heuristic)' }),
                    h('div', { class: 'text-muted', text: s.pass + ' passed · ' + s.warn + ' warnings · ' + s.fail + ' issues' })
                ])
            ]));
            var order = { fail: 0, warn: 1, pass: 2 };
            var groups = {};
            res.result.checks.forEach(function (c) { (groups[c.group] = groups[c.group] || []).push(c); });
            Object.keys(groups).forEach(function (g) {
                out.appendChild(h('h6', { class: 'fw-bold mt-3 mb-2', text: g }));
                groups[g].sort(function (a, b) { return order[a.status] - order[b.status]; }).forEach(function (c) {
                    var ic = c.status === 'pass' ? 'bi-check-circle-fill' : (c.status === 'warn' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill');
                    var item = h('div', { class: 'cv-check-item shadow-sm ' + c.status }, [
                        h('div', { class: 'small fw-semibold' }, [icon(ic + ' me-2 cv-status-' + c.status), c.label, c.detail ? h('span', { class: 'text-muted fw-normal', text: ' — ' + c.detail }) : null])
                    ]);
                    if (c.fix && c.status !== 'pass') item.appendChild(h('div', { class: 'small text-muted mt-1 ms-4' }, [h('strong', { text: 'Fix: ' }), c.fix]));
                    out.appendChild(item);
                });
            });
        });
    }
    if ($('atsRun')) $('atsRun').addEventListener('click', runAts);

    /* ---------------- Skill gap ---------------- */
    function skillList(title, items, matched) {
        var wrap = h('div', { class: 'mb-3' }, [h('div', { class: 'small fw-semibold mb-1', text: title + ' (' + items.length + ')' })]);
        if (!items.length) { wrap.appendChild(h('div', { class: 'small text-muted', text: 'None' })); return wrap; }
        var box = h('div', { class: 'd-flex flex-wrap gap-1' });
        items.forEach(function (it) {
            var badge = h('span', { class: 'badge rounded-pill ' + (matched ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-danger-subtle text-danger-emphasis border border-danger-subtle'), text: it.skill });
            if (it.source) badge.title = 'Found in: ' + it.source;
            box.appendChild(badge);
        });
        wrap.appendChild(box);
        return wrap;
    }

    function runGap() {
        var out = $('gapResults');
        var jobId = $('gapJob').value;
        out.innerHTML = '';
        if (!jobId) { out.appendChild(h('div', { class: 'alert alert-info small', text: 'Choose a vacancy to compare against.' })); return; }
        out.appendChild(h('div', { class: 'text-muted small' }, [h('span', { class: 'spinner-border spinner-border-sm me-2' }), 'Comparing…']));
        api({ action: 'gap', job_id: jobId, content: state }).then(function (res) {
            out.innerHTML = '';
            if (!res.ok) { out.appendChild(h('div', { class: 'alert alert-danger small', text: res.error || 'Comparison failed.' })); return; }
            var r = res.result;
            out.appendChild(h('div', { class: 'fw-semibold mb-1', text: res.job.job_title + ' — ' + res.job.company_name }));
            if (r.percent === null) {
                out.appendChild(h('div', { class: 'alert alert-warning small', text: 'This vacancy does not list any required skills, so a match percentage cannot be calculated.' }));
            } else {
                var cls = r.percent >= 75 ? 'bg-success' : (r.percent >= 50 ? 'bg-warning' : 'bg-danger');
                var bar = h('div', { class: 'progress-bar ' + cls, role: 'progressbar' });
                bar.style.width = r.percent + '%';
                out.appendChild(h('div', { class: 'd-flex align-items-center gap-2 mb-1' }, [h('div', { class: 'fs-4 fw-bold', text: r.percent + '%' }), h('div', { class: 'small text-muted', text: 'required-skill match' })]));
                out.appendChild(h('div', { class: 'progress mb-2' }, [bar]));
                out.appendChild(h('div', { class: 'small text-muted mb-3', text: 'Match % = matched required skills (' + r.matched_required + ') ÷ total required skills (' + r.total_required + ') × 100' }));
            }
            var req = r.required;
            out.appendChild(skillList('Matched required skills', req.filter(function (x) { return x.matched; }), true));
            out.appendChild(skillList('Missing required skills', req.filter(function (x) { return !x.matched; }), false));
            if (r.optional.length) {
                out.appendChild(skillList('Optional skills you have', r.optional.filter(function (x) { return x.matched; }), true));
                out.appendChild(skillList('Optional skills missing', r.optional.filter(function (x) { return !x.matched; }), false));
            }
            out.appendChild(h('div', { class: 'small text-muted border-top pt-2' }, [
                h('strong', { text: 'How matching works: ' }),
                'skills are compared case-insensitively using the platform skill list and approved aliases (e.g. "JS" = JavaScript, "k8s" = Kubernetes). ' +
                'A skill counts as matched if it is in your Skills section or mentioned in your experience/projects (hover a badge to see where). ' +
                'Combined requirements such as "HTML/CSS" need every part. This score is informational only and is not a hiring decision.'
            ]));
        });
    }
    if ($('gapRun')) $('gapRun').addEventListener('click', runGap);

    /* ---------------- init ---------------- */
    buildForm();
    renderPreview();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(renderPreview);
    if (cfg.mode === 'import') { dirty = true; setStatus('Review the imported details, then save.', 'text-warning'); }
    if (cfg.preselectJob && $('gapJob')) {
        $('gapJob').value = String(cfg.preselectJob);
        // Bootstrap's bundle loads in the footer, after this script.
        window.addEventListener('DOMContentLoaded', function () {
            var tab = document.querySelector('[data-bs-target="#tabGap"]');
            if (tab && window.bootstrap) bootstrap.Tab.getOrCreateInstance(tab).show();
            runGap();
        });
    }
})();
