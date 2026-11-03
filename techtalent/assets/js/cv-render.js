/**
 * TechTalent CV Studio renderer.
 * Builds the CV as small "blocks" and flows them onto fixed A4 pages, so preview and
 * print paginate identically. Headings are kept with their first entry.
 * All user text is inserted with textContent (never innerHTML).
 */
window.CVRender = (function () {
    'use strict';

    var HEADINGS = {
        summary: 'Professional Summary',
        experience: 'Work Experience',
        education: 'Education',
        skills: 'Skills',
        projects: 'Projects',
        certifications: 'Certifications',
        languages: 'Languages'
    };

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined && text !== null && text !== '') n.textContent = text;
        return n;
    }

    function lines(text) {
        return String(text || '').split(/\n+/)
            .map(function (l) { return l.replace(/^\s*[•\-\*▪●◦‣·]\s*/, '').trim(); })
            .filter(Boolean);
    }

    function range(a, b) {
        return [a, b].filter(function (x) { return x && String(x).trim(); }).join(' – ');
    }

    function entryTop(title, sub, date) {
        var top = el('div', 'cv-entry-top');
        var left = el('div');
        if (title) left.appendChild(el('div', 'cv-entry-title', title));
        if (sub) left.appendChild(el('div', 'cv-entry-sub', sub));
        top.appendChild(left);
        if (date) top.appendChild(el('div', 'cv-entry-date', date));
        return top;
    }

    /** Returns an array of blocks; each block is a DOM node that is never split across pages. */
    function entryBlocks(top, paragraphs, bulletGroups) {
        var head = el('div', 'cv-entry');
        head.appendChild(top);
        paragraphs.forEach(function (p) { if (p) head.appendChild(el('div', 'cv-entry-text', p)); });
        var blocks = [head];
        bulletGroups.forEach(function (g) {
            if (!g.items.length) return;
            var target = blocks[blocks.length - 1];
            if (g.label) target.appendChild(el('div', 'cv-label', g.label));
            // Keep the first bullet with the entry heading; later bullets may move to the next page.
            var ul = el('ul', 'cv-bullets');
            ul.appendChild(el('li', null, g.items[0]));
            target.appendChild(ul);
            g.items.slice(1).forEach(function (item) {
                var cont = el('ul', 'cv-bullets cont');
                cont.appendChild(el('li', null, item));
                var wrap = el('div', 'cv-entry-cont');
                wrap.appendChild(cont);
                blocks.push(wrap);
            });
        });
        blocks[blocks.length - 1].classList.add('cv-entry-end');
        return blocks;
    }

    function sectionBlocks(key, d) {
        var p = d.personal || {};
        var out = [];
        if (key === 'summary') {
            String(p.summary || '').split(/\n\s*\n/).map(function (s) { return s.trim(); }).filter(Boolean)
                .forEach(function (para) { var w = el('div', 'cv-summary'); w.appendChild(el('p', null, para)); out.push(w); });
        } else if (key === 'experience') {
            (d.experience || []).forEach(function (e) {
                var sub = [e.company, e.location].filter(Boolean).join(', ');
                out = out.concat(entryBlocks(entryTop(e.position || e.company, e.position ? sub : e.location, range(e.start, e.end)), [],
                    [{ label: '', items: lines(e.responsibilities) }, { label: lines(e.achievements).length ? 'Key achievements' : '', items: lines(e.achievements) }]));
            });
        } else if (key === 'education') {
            (d.education || []).forEach(function (e) {
                var q = [e.qualification, e.field].filter(Boolean).join(' in ');
                out = out.concat(entryBlocks(entryTop(q || e.institution, q ? e.institution : '', range(e.start, e.end)), [e.description], []));
            });
        } else if (key === 'skills') {
            var s = d.skills || {};
            [['Technical', s.technical || []], ['Soft skills', s.soft || []]].forEach(function (pair) {
                if (!pair[1].length) return;
                var row = el('div', 'cv-skill-row');
                row.appendChild(el('strong', null, pair[0] + ': '));
                row.appendChild(document.createTextNode(pair[1].map(function (x) {
                    return x.level ? x.name + ' (' + x.level + ')' : x.name;
                }).join(', ')));
                out.push(row);
            });
        } else if (key === 'projects') {
            (d.projects || []).forEach(function (e) {
                var sub = [e.role, e.link].filter(Boolean).join(' | ');
                var desc = lines(e.description);
                out = out.concat(entryBlocks(entryTop(e.name, sub, range(e.start, e.end)), desc.length === 1 ? desc : [],
                    [{ label: '', items: desc.length > 1 ? desc : [] }]));
            });
        } else if (key === 'certifications') {
            (d.certifications || []).forEach(function (e) {
                var b = el('div', 'cv-entry cv-entry-end');
                b.appendChild(entryTop(e.name, [e.issuer, e.link].filter(Boolean).join(' | '), e.date));
                out.push(b);
            });
        } else if (key === 'languages') {
            var langs = (d.languages || []).map(function (l) { return l.level ? l.name + ' (' + l.level + ')' : l.name; }).filter(Boolean);
            if (langs.length) out.push(el('p', 'cv-inline-list', langs.join(', ')));
        }
        if (!out.length) return [];
        // Heading travels with the first block so it is never orphaned at a page bottom.
        var keep = el('section', 'cv-keep');
        keep.appendChild(el('h2', 'cv-h', HEADINGS[key]));
        keep.appendChild(out[0]);
        return [keep].concat(out.slice(1));
    }

    function headerBlock(d) {
        var p = d.personal || {};
        var h = el('header', 'cv-head');
        h.appendChild(el('h1', 'cv-name', p.full_name || 'Your Name'));
        if (p.title) h.appendChild(el('div', 'cv-title', p.title));
        var c = el('div', 'cv-contact');
        [p.location, p.phone, p.email, p.linkedin, p.github, p.portfolio].forEach(function (v) {
            if (v) c.appendChild(el('span', null, v));
        });
        if (c.childNodes.length) h.appendChild(c);
        return h;
    }

    function blocks(d) {
        var all = [headerBlock(d)];
        var hidden = d.hidden || {};
        (d.order || Object.keys(HEADINGS)).forEach(function (key) {
            if (hidden[key]) return;
            all = all.concat(sectionBlocks(key, d));
        });
        return all;
    }

    function newPage(container) {
        var page = el('div', 'cv-page');
        var body = el('div', 'cv-page-body');
        page.appendChild(body);
        container.appendChild(page);
        return body;
    }

    /**
     * Render CV data into container as one or more A4 pages.
     * @param {HTMLElement} container
     * @param {Object} data     CV content (see includes/cv-functions.php)
     * @param {Object} opts     {template: 'classic'|'modern'|'minimal', accent: '#rrggbb'}
     * @returns {number} page count
     */
    function render(container, data, opts) {
        opts = opts || {};
        container.innerHTML = '';
        container.className = 'cv-pages tpl-' + (opts.template || 'classic');
        container.style.setProperty('--cv-accent', /^#[0-9a-f]{6}$/i.test(opts.accent || '') ? opts.accent : '#0f2137');
        var body = newPage(container);
        blocks(data || {}).forEach(function (block) {
            body.appendChild(block);
            if (body.scrollHeight > body.clientHeight + 1 && body.childNodes.length > 1) {
                body.removeChild(block);
                body = newPage(container);
                body.appendChild(block);
            }
        });
        var pages = container.querySelectorAll('.cv-page');
        if (pages.length > 1) {
            pages.forEach(function (pg, i) { pg.appendChild(el('div', 'cv-page-no', 'Page ' + (i + 1) + ' of ' + pages.length)); });
        }
        return pages.length;
    }

    /** Scale rendered pages to fit a wrapper width (preview only; print ignores the transform). */
    function fit(scaleEl, wrapper, zoom) {
        var page = scaleEl.querySelector('.cv-page');
        if (!page) return;
        var natural = page.offsetWidth;
        var avail = wrapper.clientWidth - 32;
        var s = zoom || Math.min(1, avail / natural);
        scaleEl.style.transform = 'none';
        scaleEl.style.width = natural + 'px';
        var h = scaleEl.offsetHeight;
        scaleEl.style.transform = 'scale(' + s + ')';
        // Shrink the layout box to the visual size so the wrapper scrolls correctly.
        scaleEl.style.marginBottom = (-h * (1 - s)) + 'px';
        scaleEl.style.marginRight = (-natural * (1 - s)) + 'px';
    }

    return { render: render, fit: fit, HEADINGS: HEADINGS };
})();
