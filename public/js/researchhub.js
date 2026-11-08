/*
 * ResearchHub — small progressive enhancements for the server-rendered pages.
 * Every page works without this script; it adds live progress, inline editing helpers and
 * the citation popover. No build step: served as-is from public/js.
 */
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const csrf = () => $('meta[name="csrf-token"]')?.content || '';
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const store = {
        get(k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } },
        set(k, v) { try { window.sessionStorage.setItem(k, v); } catch (e) { /* storage unavailable */ } },
    };
    const getJson = (url) => fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : Promise.reject(r)));

    /* ------------------------------------------------------------------ dirty-form tracking */
    let dirty = false;
    document.addEventListener('input', (e) => { if (e.target.closest('form')) dirty = true; });
    document.addEventListener('submit', () => { dirty = false; });

    /* ------------------------------------------------------------------ project progress polling */
    function initProgress() {
        const el = $('[data-poll-url]');
        if (!el) return;
        const url = el.dataset.pollUrl;
        let fingerprint = el.dataset.pollFingerprint || null;
        let lastStatus = null;
        let timer = null;

        const schedule = (ms) => { clearTimeout(timer); timer = setTimeout(tick, ms); };

        function notify(data) {
            if (document.body.dataset.push !== '1' || !('Notification' in window) || Notification.permission !== 'granted') return;
            try { new Notification('ResearchHub: ' + data.label, { body: data.message || data.title || '', tag: 'researchhub-' + url }); } catch (e) { /* ignore */ }
        }

        function banner() {
            if ($('#rh-update-banner')) return;
            const div = document.createElement('div');
            div.id = 'rh-update-banner';
            div.className = 'fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 bg-primary text-on-primary rounded-lg shadow-lg font-label-md text-label-md';
            div.innerHTML = '<span class="material-symbols-outlined text-[20px]">sync</span><span>This project has new updates.</span>' +
                '<button class="underline" type="button">Reload</button>';
            div.querySelector('button').addEventListener('click', () => window.location.reload());
            document.body.appendChild(div);
        }

        function tick() {
            if (document.hidden) { schedule(5000); return; }
            getJson(url).then((data) => {
                if (lastStatus && lastStatus !== data.status && data.needs_user) notify(data);
                lastStatus = data.status;

                if (fingerprint === null) {
                    fingerprint = data.fingerprint;
                } else if (data.fingerprint !== fingerprint) {
                    fingerprint = data.fingerprint;
                    const typing = document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
                    if (dirty || typing) banner(); else { window.location.reload(); return; }
                }
                schedule(data.running ? 3000 : 20000);
            }).catch(() => schedule(30000));
        }

        schedule(2000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) schedule(300); });
    }

    /* ------------------------------------------------------------------ title assistant polling */
    function initTitlePoll() {
        const el = $('[data-title-poll]');
        if (!el) return;
        const shownStalled = el.dataset.stalled === '1';
        const poll = () => getJson(el.dataset.titlePoll)
            .then((d) => {
                if (d.status !== 'pending' || (d.stalled && !shownStalled)) { window.location.reload(); return; }
                setTimeout(poll, d.stalled ? 15000 : 2500); // keep checking quietly in case the worker starts
            })
            .catch(() => setTimeout(poll, 8000));
        setTimeout(poll, 2000);
    }

    function initAutoRefresh() {
        const el = $('[data-auto-refresh]');
        if (el && !$('[data-poll-url]')) setTimeout(() => { if (!dirty) window.location.reload(); }, parseInt(el.dataset.autoRefresh, 10) || 5000);
    }

    /* ------------------------------------------------------------------ toggles */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-toggle]');
        if (!btn) return;
        const target = $(btn.dataset.toggle);
        if (target) {
            target.classList.toggle('hidden');
            if (!target.classList.contains('hidden')) {
                const field = target.querySelector('input:not([type=hidden]), textarea, select');
                if (field) field.focus({ preventScroll: true });
            }
        }
        if (btn.dataset.hide) $$(btn.dataset.hide).forEach((el) => el.classList.add('hidden'));
        if (btn.hasAttribute('data-hide-self')) btn.classList.add('hidden');
    });

    /* ------------------------------------------------------------------ repeatable rows */
    let rowCounter = 1000;

    function renumber(container) {
        if (!container) return;
        const counts = {};
        $$(':scope > [data-row]', container).forEach((row) => {
            if (row.classList.contains('rh-deleted')) return;
            $$('[data-number]', row).forEach((n) => {
                const prefix = n.dataset.number;
                counts[prefix] = (counts[prefix] || 0) + 1;
                n.textContent = prefix + counts[prefix];
            });
        });
    }

    document.addEventListener('click', (e) => {
        const add = e.target.closest('[data-add-row]');
        if (add) {
            const container = $(add.dataset.addRow);
            const tpl = add.dataset.template ? document.getElementById(add.dataset.template) : null;
            if (!container || !tpl) return;
            const index = String(rowCounter++);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = tpl.innerHTML.replace(/__INDEX__/g, index).trim();
            const isTable = container.tagName === 'TBODY';
            const nodes = isTable ? (() => { const t = document.createElement('tbody'); t.innerHTML = tpl.innerHTML.replace(/__INDEX__/g, index).trim(); return Array.from(t.children); })() : Array.from(wrapper.children);
            nodes.forEach((node) => {
                if (add.dataset.construct) $$('[data-construct-field]', node).forEach((f) => { f.value = add.dataset.construct; });
                container.appendChild(node);
            });
            renumber(container);
            const first = nodes[0]?.querySelector('input:not([type=hidden]), textarea');
            if (first) first.focus();
            dirty = true;
            return;
        }

        const remove = e.target.closest('[data-remove-row]');
        if (remove) {
            const row = remove.closest('[data-row]');
            const container = row?.parentElement;
            row?.remove();
            renumber(container);
            dirty = true;
        }
    });

    // Hidden "delete" checkboxes on existing rows: mark the row instead of removing it.
    document.addEventListener('change', (e) => {
        if (!e.target.matches('[data-delete-row]')) return;
        const row = e.target.closest('[data-row]');
        if (!row) return;
        const deleted = e.target.checked;
        row.classList.toggle('rh-deleted', deleted);
        row.classList.toggle('opacity-40', deleted);
        $$('input:not([type=hidden]):not([data-delete-row]), textarea, select', row).forEach((f) => {
            if (deleted && f.required) { f.dataset.wasRequired = '1'; f.required = false; }
            if (!deleted && f.dataset.wasRequired) f.required = true;
        });
        const icon = e.target.parentElement.querySelector('.material-symbols-outlined');
        if (icon) icon.textContent = deleted ? 'undo' : 'delete';
        e.target.parentElement.title = deleted ? 'Undo remove' : 'Remove';
        renumber(row.parentElement);
    });

    /* ------------------------------------------------------------------ instrument scale types */
    document.addEventListener('change', (e) => {
        if (!e.target.matches('[data-scale-select]')) return;
        const row = e.target.closest('[data-row]') || e.target.parentElement;
        $$('[data-scale-show]', row).forEach((el) => el.classList.toggle('hidden', el.dataset.scaleShow !== e.target.value));
    });

    /* ------------------------------------------------------------------ analysis plan: fields per test */
    document.addEventListener('change', (e) => {
        if (!e.target.matches('[data-test-select]')) return;
        const form = e.target.closest('[data-plan-form]');
        let map = {};
        try { map = JSON.parse(form?.dataset.fields || '{}'); } catch (err) { map = {}; }
        const wanted = map[e.target.value] || [];
        const block = e.target.closest('[data-analysis]');
        $$('[data-field]', block).forEach((el) => el.classList.toggle('hidden', !wanted.includes(el.dataset.field)));
    });

    /* ------------------------------------------------------------------ uploads */
    async function uploadEach(zone, files, label) {
        const maxKb = parseInt(zone.dataset.maxKb, 10);
        const limit = maxKb >= 1024 ? Math.round(maxKb / 1024) + ' MB' : maxKb + ' KB';
        const hidden = Array.from(zone.querySelectorAll('input[type=hidden]')).filter((i) => i.name !== '_token');
        const problems = [];
        let added = 0;

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (label) label.textContent = 'Uploading ' + (i + 1) + ' of ' + files.length + '…';
            if (file.size > maxKb * 1024) {
                problems.push(file.name + ' was not uploaded: it is larger than ' + limit + '.');
                continue;
            }
            const body = new FormData();
            hidden.forEach((h) => body.append(h.name, h.value));
            body.append('papers[]', file);
            try {
                const response = await fetch(zone.action, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body });
                const data = await response.json().catch(() => ({}));
                added += data.added || 0;
                (data.rejected || []).forEach((m) => problems.push(m));
                if (!response.ok && !(data.rejected || []).length) {
                    problems.push(file.name + ': ' + (data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'the upload failed.')));
                }
            } catch (err) {
                problems.push(file.name + ': the upload was interrupted. Please try again.');
            }
        }

        try { sessionStorage.setItem('rh-upload-report', JSON.stringify({ added, problems })); } catch (err) { /* report is a convenience */ }
        window.location.reload();
    }

    /** After a batch upload, show what was added and what was not. */
    function showUploadReport() {
        let report = null;
        try { report = JSON.parse(sessionStorage.getItem('rh-upload-report') || 'null'); sessionStorage.removeItem('rh-upload-report'); } catch (err) { return; }
        const zone = $('[data-dropzone]');
        if (!report || !zone) return;
        const box = document.createElement('div');
        const failed = report.problems.length > 0;
        box.className = 'mb-4 p-4 border rounded-lg font-body-sm text-body-sm ' + (failed ? 'bg-error-container/50 border-error-container text-on-error-container' : 'bg-tertiary-fixed/40 border-tertiary-fixed text-on-tertiary-fixed-variant');
        box.innerHTML = '<p class="font-label-md text-label-md">' + report.added + (report.added === 1 ? ' file' : ' files') + ' uploaded' + (failed ? ', ' + report.problems.length + ' not uploaded' : '') + '.</p>'
            + (failed ? '<ul class="list-disc pl-5 mt-1">' + report.problems.map((p) => '<li>' + escapeHtml(p) + '</li>').join('') + '</ul>' : '');
        zone.parentNode.insertBefore(box, zone);
    }

    function initDropzones() {
        showUploadReport();
        $$('[data-dropzone]').forEach((zone) => {
            const input = zone.querySelector('input[type=file]');
            if (!input) return;
            const submit = () => {
                if (!input.files || !input.files.length) return;
                zone.classList.add('opacity-60', 'pointer-events-none');
                const label = zone.querySelector('.font-headline-sm');
                // Paper uploads go one file per request, so a big batch never exceeds the server's request limit.
                if (zone.dataset.maxKb && window.fetch && window.FormData) {
                    uploadEach(zone, Array.from(input.files), label);
                    return;
                }
                if (label) label.textContent = 'Uploading ' + input.files.length + (input.files.length === 1 ? ' file…' : ' files…');
                zone.requestSubmit ? zone.requestSubmit() : zone.submit();
            };
            input.addEventListener('change', submit);
            ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => {
                e.preventDefault();
                zone.classList.add('border-primary', 'bg-surface-container-low');
            }));
            ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => {
                e.preventDefault();
                zone.classList.remove('border-primary', 'bg-surface-container-low');
            }));
            zone.addEventListener('drop', (e) => {
                if (!e.dataTransfer?.files?.length) return;
                try {
                    const dt = new DataTransfer();
                    Array.from(e.dataTransfer.files).forEach((f) => { if (!input.multiple && dt.files.length) return; dt.items.add(f); });
                    input.files = dt.files;
                } catch (err) {
                    input.files = e.dataTransfer.files;
                }
                submit();
            });
        });
    }

    document.addEventListener('change', (e) => {
        if (!e.target.matches('[data-auto-submit]')) return;
        const form = e.target.form;
        if (form && e.target.value) form.requestSubmit ? form.requestSubmit() : form.submit();
    });

    /* ------------------------------------------------------------------ busy buttons */
    document.addEventListener('submit', (e) => {
        const btn = e.submitter;
        if (!btn || !btn.dataset.busyText || e.defaultPrevented) return;
        setTimeout(() => {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span> ' + escapeHtml(btn.dataset.busyText);
        }, 0);
    });

    /* ------------------------------------------------------------------ character counters */
    function initCounters() {
        $$('[data-counter]').forEach((field) => {
            const out = document.getElementById(field.dataset.counter);
            if (!out) return;
            const update = () => { out.textContent = field.value.length.toLocaleString('en-US'); };
            field.addEventListener('input', update);
            update();
        });
        $$('[data-char-count]').forEach((out) => {
            const field = $('[name="' + out.dataset.charCount + '"]');
            if (!field) return;
            const max = parseInt(field.getAttribute('maxlength'), 10) || 4000;
            const update = () => { out.textContent = field.value.length.toLocaleString('en-US') + ' / ' + max.toLocaleString('en-US') + ' characters'; };
            field.addEventListener('input', update);
        });
    }

    /* ------------------------------------------------------------------ dismissible notices */
    function initDismissible() {
        $$('[data-dismissible]').forEach((el) => {
            const key = 'rh-dismissed-' + el.dataset.dismissible;
            if (store.get(key)) { el.remove(); return; }
            el.querySelector('[data-dismiss]')?.addEventListener('click', () => { store.set(key, '1'); el.remove(); });
        });
    }

    /* ------------------------------------------------------------------ title assistant tags */
    function initTags() {
        const list = $('[data-tag-list]');
        const input = $('[data-tag-input]');
        const tpl = $('[data-tag-template]');
        if (!list || !input || !tpl) return;
        const empty = $('[data-tag-empty]', list);

        const existing = () => $$('input[name="keywords[]"]', list).map((i) => i.value.toLowerCase());
        const add = () => {
            const words = input.value.split(/[,;\n]+/).map((w) => w.trim().slice(0, 60)).filter(Boolean);
            words.forEach((word) => {
                if (existing().includes(word.toLowerCase()) || existing().length >= 12) return;
                const holder = document.createElement('div');
                holder.innerHTML = tpl.innerHTML.replace(/__KEYWORD__/g, escapeHtml(word)).trim();
                const chip = holder.firstElementChild;
                list.insertBefore(chip, empty || null);
            });
            input.value = '';
            if (empty) empty.classList.toggle('hidden', existing().length > 0);
            input.focus();
        };
        $('[data-tag-add]')?.addEventListener('click', add);
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } });
    }

    document.addEventListener('change', (e) => {
        if (!e.target.matches('[data-title-choice]')) return;
        const active = $('[data-title-active]');
        if (active) {
            const t = e.target.dataset.titleChoice;
            active.textContent = '"' + (t.length > 60 ? t.slice(0, 60) + '...' : t) + '"';
        }
    });

    /* ------------------------------------------------------------------ browser notifications opt-in */
    document.addEventListener('change', (e) => {
        if (!e.target.matches('[data-push-toggle]') || !e.target.checked) return;
        if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
    });

    /* ------------------------------------------------------------------ citation popover */
    function initCitations() {
        const pop = $('#cite-pop');
        if (!pop) return;
        const body = $('#cite-body', pop);
        const heading = $('#cite-heading', pop);
        const icon = $('#cite-icon', pop);
        let current = null;

        const close = () => { pop.classList.add('hidden'); current = null; };

        function place(btn) {
            const r = btn.getBoundingClientRect();
            const w = pop.offsetWidth || 380;
            let left = Math.min(Math.max(16, r.left), window.innerWidth - w - 16);
            let top = r.bottom + 8;
            const h = pop.offsetHeight || 240;
            if (top + h > window.innerHeight - 16) top = Math.max(16, r.top - h - 8);
            pop.style.left = left + 'px';
            pop.style.top = top + 'px';
        }

        function render(data) {
            const sources = data.sources || [];
            if (!sources.length) {
                body.innerHTML = '<p class="font-body-sm text-body-sm text-on-surface-variant">This citation does not match any uploaded paper.</p>';
                icon.textContent = 'report';
                icon.className = 'material-symbols-outlined text-error text-[20px]';
                heading.textContent = 'Unmatched citation';
                return;
            }
            const anyVerified = sources.some((s) => s.passages.some((p) => p.verified));
            icon.textContent = anyVerified ? 'verified' : 'info';
            icon.className = 'material-symbols-outlined text-[20px] ' + (anyVerified ? 'text-on-tertiary-container' : 'text-secondary');
            heading.textContent = sources.length > 1 ? sources.length + ' sources' : 'Source passage';

            body.innerHTML = sources.map((s) => {
                const passages = s.passages.filter((p) => p.text).map((p) => {
                    const href = s.pdf_url + (p.page ? '#page=' + encodeURIComponent(p.page) : '');
                    return '<blockquote class="border-l-2 border-secondary pl-3 font-body-sm text-body-sm text-on-surface italic">“' + escapeHtml(p.text) + '”</blockquote>' +
                        '<div class="flex items-center justify-between mt-1 text-[12px] text-on-surface-variant">' +
                        '<span class="flex items-center gap-1">' + (p.verified ? '<span class="material-symbols-outlined text-[14px] text-on-tertiary-container">check_circle</span>Verified by the citations API' : 'Added by you') + (p.location ? ' · ' + escapeHtml(p.location) : '') + '</span>' +
                        '<a class="text-secondary hover:underline" href="' + escapeHtml(href) + '" rel="noopener" target="_blank">Open PDF</a></div>';
                }).join('<div class="h-3"></div>');
                const none = '<p class="font-body-sm text-body-sm text-on-surface-variant">No quoted passage was stored for this citation. ' +
                    '<a class="text-secondary hover:underline" href="' + escapeHtml(s.pdf_url) + '" rel="noopener" target="_blank">Open the PDF</a> to check it.</p>';
                return '<div class="space-y-2">' +
                    '<p class="font-label-md text-label-md text-primary">' + escapeHtml(s.in_text) + '</p>' +
                    '<p class="text-[12px] text-on-surface-variant leading-snug">' + escapeHtml(s.reference) + '</p>' +
                    (passages || none) + '</div>';
            }).join('<hr class="border-outline-variant my-3"/>');
        }

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('button.cite');
            if (btn) {
                e.preventDefault();
                if (current === btn) { close(); return; }
                current = btn;
                body.innerHTML = '<p class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant"><span class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>Loading source…</p>';
                pop.classList.remove('hidden');
                place(btn);
                const url = pop.dataset.url.replace('__SECTION__', encodeURIComponent(btn.dataset.section)) + '?keys=' + encodeURIComponent(btn.dataset.keys);
                getJson(url).then((data) => { if (current === btn) { render(data); place(btn); } })
                    .catch(() => { body.innerHTML = '<p class="font-body-sm text-body-sm text-error">The source could not be loaded.</p>'; });
                return;
            }
            if (e.target.closest('[data-cite-close]') || (!pop.contains(e.target) && current)) close();
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
        window.addEventListener('scroll', () => { if (current) place(current); }, { passive: true });
    }

    /* ------------------------------------------------------------------ boot */
    function boot() {
        initProgress();
        initTitlePoll();
        initAutoRefresh();
        initDropzones();
        initCounters();
        initDismissible();
        initTags();
        initCitations();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();

    window.ResearchHub = { csrf };
})();
