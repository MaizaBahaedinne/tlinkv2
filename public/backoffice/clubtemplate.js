/*
 * Modèles de clubs — organigramme interactif d'un modèle d'équipe (University, High School, We Are Carthage).
 * Même moteur de disposition et même habillage que /organigramme (OrgLayout, orgchart.css).
 * Tout le contenu dynamique est inséré via textContent / setAttribute (jamais innerHTML).
 */
(function () {
    'use strict';

    var cfg = JSON.parse(document.getElementById('ct-config').textContent);
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var NS = 'http://www.w3.org/2000/svg';
    var L = window.OrgLayout;

    var FONT = "Manrope, 'Segoe UI', Helvetica, Arial, sans-serif";
    var TITLE_FONT = '700 13px ' + FONT;
    var SUB_FONT = '500 11.5px ' + FONT;

    var COLORS = { regional_club: '#c2570c', university_club: '#1c5bcb', highschool_club: '#b4237d' };
    var COLOR = COLORS[cfg.chart.category] || '#7d0020';
    var LINK = '#80676d';
    var ROOT_ID = -1;

    var S = {
        chart: null, nodes: [], byId: {}, byKey: {}, kids: {},
        canEdit: false, selectedKey: null, rootSelected: false,
        view: { x: 40, y: 24, k: 1 }, lay: null, gNode: {}, pLink: [],
        gesture: null, pointers: {}, pinch: null
    };

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    var app = $('#oc-app');
    var svgRoot = $('#oc-svg');
    var viewport = $('#oc-viewport');
    var layerLinks = $('#oc-links');
    var layerNodes = $('#oc-nodes');
    var stage = $('#oc-stage');

    /* ------------------------------------------------------------------ Utilitaires */

    function h(tag, props) {
        var e = document.createElement(tag);
        Object.keys(props || {}).forEach(function (k) {
            var v = props[k];
            if (v == null || v === false) { return; }
            if (k === 'class') { e.className = v; }
            else if (k === 'text') { e.textContent = v; }
            else if (k === 'style') { e.style.cssText = v; }
            else if (k.indexOf('on') === 0) { e.addEventListener(k.slice(2), v); }
            else { e.setAttribute(k, v === true ? '' : v); }
        });
        for (var i = 2; i < arguments.length; i++) { append(e, arguments[i]); }
        return e;
    }

    function append(parent, child) {
        if (child == null || child === false) { return; }
        if (Array.isArray(child)) { child.forEach(function (c) { append(parent, c); }); return; }
        parent.appendChild(child.nodeType ? child : document.createTextNode(String(child)));
    }

    function sv(tag, attrs, text) {
        var e = document.createElementNS(NS, tag);
        Object.keys(attrs || {}).forEach(function (k) { if (attrs[k] != null) { e.setAttribute(k, attrs[k]); } });
        if (text != null) { e.textContent = text; }
        return e;
    }

    var measureCtx = document.createElement('canvas').getContext('2d');
    var ellipsisCache = {};

    function wrapLines(text, maxW, font, max) {
        measureCtx.font = font;
        var words = String(text || '').split(/\s+/).filter(Boolean);
        var lines = [];
        var cur = '';
        for (var i = 0; i < words.length; i++) {
            var test = cur ? cur + ' ' + words[i] : words[i];
            if (!cur || measureCtx.measureText(test).width <= maxW) { cur = test; continue; }
            lines.push(cur);
            cur = words[i];
            if (lines.length === max - 1) { cur = words.slice(i).join(' '); break; }
        }
        if (cur) { lines.push(cur); }
        return lines.map(function (l) { return ellipsize(l, maxW, font); });
    }

    function ellipsize(text, maxW, font) {
        text = String(text || '');
        var key = font + '|' + maxW + '|' + text;
        if (ellipsisCache[key] != null) { return ellipsisCache[key]; }
        measureCtx.font = font;
        var out = text;
        if (measureCtx.measureText(text).width > maxW) {
            var lo = 0;
            var hi = text.length;
            while (lo < hi) {
                var mid = Math.ceil((lo + hi) / 2);
                if (measureCtx.measureText(text.slice(0, mid).trimEnd() + '…').width <= maxW) { lo = mid; } else { hi = mid - 1; }
            }
            out = text.slice(0, lo).trimEnd() + '…';
        }
        ellipsisCache[key] = out;
        return out;
    }

    function toast(message, kind) {
        var box = $('#oc-toasts');
        var t = h('div', { class: 'oc-toast ' + (kind || 'info'), role: kind === 'error' ? 'alert' : 'status', text: message });
        box.appendChild(t);
        setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, kind === 'error' ? 7000 : 3800);
    }

    function formatError(err) {
        if (err && err.errors) {
            var first = Object.keys(err.errors)[0];
            if (first) { return [].concat(err.errors[first])[0]; }
        }
        if (err && err.status === 419) { return 'Session expirée : rechargez la page puis réessayez.'; }
        if (err && err.status === 403) { return err.message || 'Action réservée aux administrateurs.'; }
        return (err && err.message) || 'Une erreur est survenue. Réessayez.';
    }

    function api(method, path, body) {
        var opts = {
            method: method, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }

        return fetch(cfg.base + path, opts).then(function (r) {
            return r.json().catch(function () { return null; }).then(function (data) {
                if (!r.ok) { throw { status: r.status, message: data && data.message, errors: data && data.errors }; }
                return data;
            });
        });
    }

    function shortLevel(level) {
        return String((S.chart.levels || {})[level] || level).split(' (')[0];
    }

    /* ------------------------------------------------------------------ Données */

    function setChart(chart) {
        S.chart = chart;
        S.canEdit = !!chart.can_edit;
        S.byId = {};
        S.byKey = {};
        S.kids = {};

        var root = {
            id: ROOT_ID, key: null, parent_id: null, relation_type: 'hierarchy', children_layout: 'horizontal', sort_order: 0,
            title: 'Club ' + chart.label, kind: 'club', clubs: chart.club_count
        };
        S.nodes = [root];
        S.byId[ROOT_ID] = root;

        chart.blocks.forEach(function (b) {
            var node = Object.assign({}, b, {
                parent_id: b.parent_id == null ? ROOT_ID : b.parent_id,
                relation_type: 'hierarchy',
                children_layout: b.kind === 'club_department' ? 'vertical' : 'horizontal',
                sort_order: b.sort_order + 1
            });
            S.nodes.push(node);
            S.byId[node.id] = node;
            S.byKey[node.key] = node;
        });

        S.nodes.forEach(function (n) {
            if (n.parent_id != null) { (S.kids[n.parent_id] = S.kids[n.parent_id] || []).push(n); }
        });

        if (S.selectedKey && !S.byKey[S.selectedKey]) { S.selectedKey = null; }
    }

    function descendantKeys(id) {
        var out = [];
        (S.kids[id] || []).forEach(function (c) { out.push(c.key); out = out.concat(descendantKeys(c.id)); });
        return out;
    }

    function ancestors(n) {
        var trail = [];
        var cur = S.byId[n.parent_id];
        while (cur && trail.length < 40) { trail.unshift(cur); cur = S.byId[cur.parent_id]; }
        return trail;
    }

    function selected() {
        return S.selectedKey ? S.byKey[S.selectedKey] : (S.rootSelected ? S.byId[ROOT_ID] : null);
    }

    /* ------------------------------------------------------------------ Rendu */

    function layout() {
        S.lay = L.compute(S.nodes, { isCollapsed: function () { return false; }, offsetOf: function () { return { dx: 0, dy: 0 }; } });
    }

    function render() {
        layout();
        layerLinks.textContent = '';
        layerNodes.textContent = '';
        S.gNode = {};
        S.pLink = [];

        S.lay.links.forEach(function (link) {
            var path = sv('path', { 'class': 'oc-link', fill: 'none', stroke: LINK, 'stroke-width': 1.7, 'stroke-linejoin': 'round' });
            layerLinks.appendChild(path);
            S.pLink.push({ link: link, el: path });
        });

        S.pLink.forEach(function (item) {
            var p = S.lay.pos[item.link.from];
            var c = S.lay.pos[item.link.to];
            if (p && c) { item.el.setAttribute('d', L.linkPath(item.link, p, c)); }
        });

        S.nodes.forEach(function (n) {
            var p = S.lay.pos[n.id];
            if (p) { var g = drawNode(n, p); S.gNode[n.id] = g; layerNodes.appendChild(g); }
        });

        applyStates();
        renderSide();
    }

    function moduleCount(n) { return Object.keys(n.modules || {}).length; }

    function rightsSuffix(n) {
        var count = moduleCount(n);
        return count ? ' · ' + count + ' module' + (count > 1 ? 's' : '') : '';
    }

    function subtitleLine(n) {
        if (n.id === ROOT_ID) { return n.clubs + ' club' + (n.clubs > 1 ? 's' : '') + ' suivent ce modèle'; }
        if (n.kind === 'position') {
            return (n.capacity === 'multiple' ? 'Plusieurs titulaires' : 'Un titulaire') + rightsSuffix(n);
        }
        var count = descendantKeys(n.id).length;
        return 'Département · ' + count + ' poste' + (count > 1 ? 's' : '');
    }

    function drawNode(n, p) {
        var compact = p.inList;
        var w = p.w;
        var hh = p.h;
        var isRoot = n.id === ROOT_ID;
        var isDept = n.kind === 'club_department';

        var g = sv('g', {
            'class': 'oc-node', 'data-id': n.id, transform: 'translate(' + p.x + ' ' + p.y + ')',
            tabindex: 0, role: 'button', 'aria-label': n.title
        });
        g.appendChild(sv('title', {}, n.title + '\n' + subtitleLine(n) + (n.clubs && !isRoot ? '\nPrésent dans ' + n.clubs + ' club(s)' : '')));

        g.appendChild(sv('rect', {
            'class': 'oc-card', width: w, height: hh, rx: 10, fill: isRoot ? '#fff7f2' : (isDept ? '#fbf7f8' : '#ffffff'),
            stroke: '#e2d5d8', 'stroke-width': 1.2
        }));
        g.appendChild(sv('path', { d: 'M10 0A10 10 0 0 0 0 10V' + (hh - 10) + 'A10 10 0 0 0 10 ' + hh + 'Z', fill: COLOR, 'fill-opacity': isDept ? 0.55 : 1 }));

        var tx = 20;
        var maxW = w - tx - 12;
        var line2 = subtitleLine(n);
        var titleLines = compact ? [ellipsize(n.title, maxW, TITLE_FONT)] : wrapLines(n.title, maxW, TITLE_FONT, 2);
        var two = titleLines.length === 2;
        var yTitle = compact ? hh / 2 - 3 : (two ? hh / 2 - 15 : hh / 2 - 5);
        var yLine2 = compact ? hh / 2 + 13 : (two ? hh / 2 + 18 : hh / 2 + 14);

        titleLines.forEach(function (text, i) {
            g.appendChild(sv('text', { x: tx, y: yTitle + i * 14, 'font-family': FONT, 'font-size': 13, 'font-weight': 700, fill: '#4a0a1c' }, text));
        });
        g.appendChild(sv('text', { x: tx, y: yLine2, 'font-family': FONT, 'font-size': 11.5, 'font-weight': 500, fill: '#523e43' }, ellipsize(line2, maxW, SUB_FONT)));

        return g;
    }

    function applyStates() {
        Object.keys(S.gNode).forEach(function (id) {
            var n = S.byId[id];
            var on = (n.id === ROOT_ID && S.rootSelected) || (n.key && n.key === S.selectedKey);
            S.gNode[id].classList.toggle('is-selected', !!on);
        });
    }

    function renderSide() {
        var dl = $('#oc-stats');
        dl.textContent = '';
        var blocks = S.chart.blocks;
        var rows = [
            ['Clubs concernés', S.chart.club_count],
            ['Départements', blocks.filter(function (b) { return b.kind === 'club_department'; }).length],
            ['Postes', blocks.filter(function (b) { return b.kind === 'position'; }).length],
            ['Postes à plusieurs titulaires', blocks.filter(function (b) { return b.kind === 'position' && b.capacity === 'multiple'; }).length]
        ];
        rows.forEach(function (r) { dl.appendChild(h('div', {}, h('dt', { text: r[0] }), h('dd', { text: String(r[1]) }))); });

        var box = $('#ct-levels');
        box.textContent = '';
        Object.keys(S.chart.levels).forEach(function (level) {
            var perms = S.chart.level_permissions[level] || [];
            var chips = h('div', { class: 'oc-badges' });
            if (!perms.length) { chips.appendChild(h('span', { class: 'oc-muted', text: 'Aucun droit d’administration.' })); }
            perms.forEach(function (p) { chips.appendChild(h('span', { class: 'oc-chip', text: p })); });
            box.appendChild(h('div', {}, h('strong', { text: shortLevel(level) }), chips));
        });
    }

    /* ------------------------------------------------------------------ Vue : pan / zoom */

    function applyView() {
        viewport.setAttribute('transform', 'translate(' + S.view.x + ' ' + S.view.y + ') scale(' + S.view.k + ')');
        $('#oc-zoom-label').textContent = Math.round(S.view.k * 100) + ' %';
    }

    function stageSize() {
        var r = stage.getBoundingClientRect();
        return { w: r.width, h: r.height };
    }

    function zoomAt(factor, cx, cy) {
        var k = Math.min(3, Math.max(0.08, S.view.k * factor));
        var real = k / S.view.k;
        S.view.x = cx - (cx - S.view.x) * real;
        S.view.y = cy - (cy - S.view.y) * real;
        S.view.k = k;
        applyView();
    }

    function zoomCenter(factor) {
        var s = stageSize();
        zoomAt(factor, s.w / 2, s.h / 2);
    }

    function fit() {
        var s = stageSize();
        var b = S.lay.bounds;
        var bw = b.maxX - b.minX;
        var bh = b.maxY - b.minY;
        var k = Math.max(0.08, Math.min((s.w - 60) / bw, (s.h - 60) / bh, 1.2));
        S.view.k = k;
        S.view.x = (s.w - bw * k) / 2 - b.minX * k;
        S.view.y = (s.h - bh * k) / 2 - b.minY * k;
        applyView();
    }

    function centerOn(id) {
        var p = S.lay.pos[id];
        if (!p) { return; }
        var s = stageSize();
        S.view.x = s.w / 2 - (p.x + p.w / 2) * S.view.k;
        S.view.y = s.h / 3 - (p.y + p.h / 2) * S.view.k;
        applyView();
    }

    /* ------------------------------------------------------------------ Interactions souris / tactile */

    function nodeIdFrom(target) {
        var g = target && target.closest ? target.closest('.oc-node') : null;
        return g ? Number(g.getAttribute('data-id')) : null;
    }

    svgRoot.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse' && e.button !== 0) { return; }
        S.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };

        if (Object.keys(S.pointers).length === 2) {
            var pts = Object.keys(S.pointers).map(function (k) { return S.pointers[k]; });
            S.pinch = { d: Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y), k: S.view.k };
            S.gesture = null;
            return;
        }

        try { svgRoot.setPointerCapture(e.pointerId); } catch (err) { /* ignoré */ }

        var id = nodeIdFrom(e.target);
        var reparent = id != null && id !== ROOT_ID && e.shiftKey && S.canEdit;
        S.gesture = {
            startX: e.clientX, startY: e.clientY, viewX: S.view.x, viewY: S.view.y, moved: false, id: id,
            type: reparent ? 'reparent' : (id != null ? 'tap' : 'pan')
        };
    });

    svgRoot.addEventListener('pointermove', function (e) {
        if (S.pointers[e.pointerId]) { S.pointers[e.pointerId] = { x: e.clientX, y: e.clientY }; }

        if (S.pinch && Object.keys(S.pointers).length === 2) {
            var pts = Object.keys(S.pointers).map(function (k) { return S.pointers[k]; });
            var d = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
            var r = svgRoot.getBoundingClientRect();
            zoomAt((S.pinch.k * (d / S.pinch.d)) / S.view.k, (pts[0].x + pts[1].x) / 2 - r.left, (pts[0].y + pts[1].y) / 2 - r.top);
            return;
        }

        var gs = S.gesture;
        if (!gs) { return; }
        var dx = e.clientX - gs.startX;
        var dy = e.clientY - gs.startY;

        if (!gs.moved && Math.hypot(dx, dy) > 5) {
            gs.moved = true;
            if (gs.type === 'tap') { gs.type = 'pan'; }
            svgRoot.classList.add(gs.type === 'reparent' ? 'is-dragging' : 'is-panning');
        }

        if (gs.moved && gs.type === 'pan') {
            S.view.x = gs.viewX + dx;
            S.view.y = gs.viewY + dy;
            applyView();
        } else if (gs.moved && gs.type === 'reparent') {
            markDrop(nodeIdFrom(document.elementFromPoint(e.clientX, e.clientY)), gs.id);
        }
    });

    function markDrop(targetId, movingId) {
        $$('.oc-node.is-drop').forEach(function (g) { g.classList.remove('is-drop'); });
        var ok = targetId != null && canDropOn(movingId, targetId);
        if (ok && S.gNode[targetId]) { S.gNode[targetId].classList.add('is-drop'); }
        return ok;
    }

    function canDropOn(movingId, targetId) {
        var moving = S.byId[movingId];
        if (!moving || targetId === movingId || targetId === moving.parent_id) { return false; }
        if (targetId === ROOT_ID) { return true; }
        return descendantKeys(movingId).indexOf(S.byId[targetId].key) === -1;
    }

    function endPointer(e) {
        delete S.pointers[e.pointerId];
        if (Object.keys(S.pointers).length < 2) { S.pinch = null; }
        try { svgRoot.releasePointerCapture(e.pointerId); } catch (err) { /* ignoré */ }

        var gs = S.gesture;
        svgRoot.classList.remove('is-dragging', 'is-panning');
        $$('.oc-node.is-drop').forEach(function (g) { g.classList.remove('is-drop'); });
        S.gesture = null;
        if (!gs || e.type === 'pointercancel') { return; }

        if (gs.type === 'tap' || (gs.type === 'reparent' && !gs.moved)) {
            if (gs.id != null) { select(gs.id); }
        } else if (gs.type === 'reparent' && gs.moved) {
            var target = nodeIdFrom(document.elementFromPoint(e.clientX, e.clientY));
            if (target != null && canDropOn(gs.id, target)) { reparent(gs.id, target); }
        } else if (gs.type === 'pan' && !gs.moved) {
            select(null);
            closeDrawers();
        }
    }

    svgRoot.addEventListener('pointerup', endPointer);
    svgRoot.addEventListener('pointercancel', endPointer);

    svgRoot.addEventListener('wheel', function (e) {
        e.preventDefault();
        var r = svgRoot.getBoundingClientRect();
        zoomAt(Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.0016)), e.clientX - r.left, e.clientY - r.top);
    }, { passive: false });

    svgRoot.addEventListener('keydown', function (e) {
        var g = e.target.closest ? e.target.closest('.oc-node') : null;
        if (g && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); select(Number(g.getAttribute('data-id'))); return; }
        if (e.key === '+' || e.key === '=') { zoomCenter(1.2); }
        else if (e.key === '-') { zoomCenter(1 / 1.2); }
        else if (e.key === '0') { fit(); }
        else if (e.key === 'ArrowLeft') { S.view.x += 60; applyView(); }
        else if (e.key === 'ArrowRight') { S.view.x -= 60; applyView(); }
        else if (e.key === 'ArrowUp') { S.view.y += 60; applyView(); }
        else if (e.key === 'ArrowDown') { S.view.y -= 60; applyView(); }
        else if (e.key === 'Escape') { select(null); }
    });

    function select(id) {
        S.rootSelected = id === ROOT_ID;
        S.selectedKey = id != null && id !== ROOT_ID ? S.byId[id].key : null;
        applyStates();
        renderPanel();
        if (id != null && window.matchMedia('(max-width: 900px)').matches) { app.classList.add('oc-open-panel'); app.classList.remove('oc-open-side'); }
    }

    function closeDrawers() { app.classList.remove('oc-open-side', 'oc-open-panel'); }

    /* ------------------------------------------------------------------ Panneau */

    function section(title, body) {
        return h('section', { class: 'oc-sec' }, h('div', { class: 'oc-sec-head' }, h('h3', { text: title })), body);
    }

    function btn(label, onclick, cls) {
        return h('button', { type: 'button', class: 'oc-btn ' + (cls || ''), onclick: onclick, text: label });
    }

    function field(label, control) {
        return h('div', { class: 'oc-field' }, h('label', { text: label }), control);
    }

    function selectOf(options, value, disabled) {
        var s = h('select', { disabled: disabled });
        options.forEach(function (o) { s.appendChild(h('option', { value: o.value, text: o.label })); });
        s.value = value == null ? '' : String(value);
        return s;
    }

    /* Matrice C R U D par module, comme sur la fiche d'un poste. */
    function buildMatrix(n) {
        var actions = Object.keys(S.chart.actions);
        var boxes = {};
        var current = n.modules || {};
        var table = h('table', { class: 'ct-matrix' });
        var head = h('tr', {}, h('th', { text: 'Module' }));

        function setCell(key, action, on) {
            var cell = boxes[key] && boxes[key][action];
            if (cell) { cell.checked = on; }
        }

        function fill(preset) {
            Object.keys(boxes).forEach(function (key) {
                actions.forEach(function (a) { setCell(key, a, ((preset[key] || []).indexOf(a) !== -1)); });
            });
        }

        actions.forEach(function (a) {
            var th = h('th', { class: 'ct-matrix-col' });
            th.appendChild(h('button', {
                type: 'button', class: 'oc-btn oc-btn-sm', disabled: !S.canEdit, title: 'Tout cocher / décocher : ' + S.chart.actions[a], text: S.chart.action_letters[a],
                onclick: function () {
                    var keys = Object.keys(boxes).filter(function (k) { return boxes[k][a]; });
                    var all = keys.every(function (k) { return boxes[k][a].checked; });
                    keys.forEach(function (k) { setCell(k, a, !all); if (!all && a !== 'read') { setCell(k, 'read', true); } });
                    if (all && a === 'read') { keys.forEach(function (k) { actions.forEach(function (x) { setCell(k, x, false); }); }); }
                }
            }));
            head.appendChild(th);
        });
        table.appendChild(h('thead', {}, head));

        var tbody = h('tbody');
        var group = null;

        S.chart.module_catalog.forEach(function (m) {
            if (m.group !== group) {
                group = m.group;
                tbody.appendChild(h('tr', { class: 'ct-matrix-group' }, h('td', { colspan: actions.length + 1, text: group })));
            }

            boxes[m.key] = {};
            var row = h('tr', {}, h('td', { class: 'ct-matrix-name', text: m.name }));

            actions.forEach(function (a) {
                var td = h('td', { class: 'ct-matrix-cell' });

                if (m.actions.indexOf(a) === -1) {
                    td.textContent = '—';
                } else {
                    var box = h('input', { type: 'checkbox', disabled: !S.canEdit, 'aria-label': S.chart.actions[a] + ' — ' + m.name });
                    box.checked = (current[m.key] || []).indexOf(a) !== -1;
                    box.addEventListener('change', function () {
                        // « Consulter » accompagne toute autre action ; le retirer retire les autres.
                        if (box.checked && a !== 'read') { setCell(m.key, 'read', true); }
                        if (!box.checked && a === 'read') { actions.forEach(function (x) { setCell(m.key, x, false); }); }
                    });
                    boxes[m.key][a] = box;
                    td.appendChild(box);
                }

                row.appendChild(td);
            });

            tbody.appendChild(row);
        });
        table.appendChild(tbody);

        var presets = h('div', { class: 'oc-actions' });
        if (S.canEdit) {
            presets.appendChild(h('span', { class: 'oc-muted', text: 'Préréglage :' }));
            presets.appendChild(btn('Aucun droit', function () { fill({}); }, 'oc-btn-sm'));
            Object.keys(S.chart.presets).forEach(function (level) {
                if (level === 'none') { return; }
                presets.appendChild(btn(shortLevel(level), function () { fill(S.chart.presets[level]); }, 'oc-btn-sm'));
            });
        }

        var el = h('div', {}, presets, h('div', { class: 'ct-matrix-wrap' }, table),
            h('p', { class: 'oc-note', text: 'Ces droits s’exercent sur le périmètre du club, pour chaque titulaire du poste. « Consulter » est ajouté dès qu’une autre action est accordée.' }));

        return {
            el: el,
            read: function () {
                var out = {};
                Object.keys(boxes).forEach(function (key) {
                    var picked = actions.filter(function (a) { return boxes[key][a] && boxes[key][a].checked; });
                    if (picked.length) { out[key] = picked; }
                });
                return out;
            }
        };
    }

    function renderPanel() {
        var body = $('#oc-panel-body');
        body.textContent = '';
        var n = selected();

        if (!n) {
            body.appendChild(h('div', { class: 'oc-empty' },
                h('p', { class: 'oc-empty-title', text: 'Aucun bloc sélectionné' }),
                h('p', { text: 'Cliquez sur un département ou un poste pour régler son intitulé, son rattachement, le nombre de titulaires et son niveau d’accès.' }),
                S.canEdit ? h('p', { text: 'Chaque changement s’applique à tous les clubs de ce type. Maj + glisser un bloc sur un autre pour le rattacher.' }) : h('p', { class: 'oc-note', text: 'Vous consultez le modèle en lecture seule.' })
            ));
            return;
        }

        if (n.id === ROOT_ID) {
            body.appendChild(h('div', { class: 'oc-fiche-head', style: '--chip:' + COLOR },
                h('span', { class: 'oc-chip is-solid', style: '--chip:' + COLOR, text: S.chart.label }),
                h('h3', { class: 'oc-fiche-title', text: n.title }),
                h('p', { class: 'oc-fiche-sub', text: n.clubs + ' club(s) suivent ce modèle.' })
            ));
            var rootActions = h('div', { class: 'oc-actions' });
            if (S.canEdit) { rootActions.appendChild(btn('Ajouter un bloc sous le club', function () { openAdd(null); }, 'oc-btn-primary')); }
            rootActions.appendChild(btn('Centrer', function () { centerOn(n.id); }));
            body.appendChild(section('Actions', rootActions));
            return;
        }

        var isPos = n.kind === 'position';
        body.appendChild(h('div', { class: 'oc-fiche-head', style: '--chip:' + COLOR },
            h('span', { class: 'oc-chip is-solid', style: '--chip:' + COLOR, text: S.chart.label }),
            h('h3', { class: 'oc-fiche-title', text: n.title }),
            h('div', { class: 'oc-badges' },
                h('span', { class: 'oc-chip', text: S.chart.kinds[n.kind] || n.kind }),
                isPos ? h('span', { class: 'oc-chip' + (n.capacity === 'multiple' ? ' is-ok' : ''), text: n.capacity === 'multiple' ? 'Plusieurs personnes' : 'Un seul titulaire' }) : null,
                isPos && moduleCount(n) ? h('span', { class: 'oc-chip', style: '--chip:#6d3fd0', text: 'Droits : ' + moduleCount(n) + ' module' + (moduleCount(n) > 1 ? 's' : '') }) : null
            )
        ));

        var trail = ancestors(n);
        var crumbs = h('div', { class: 'oc-trail' });
        trail.forEach(function (a, i) {
            if (i) { crumbs.appendChild(h('span', { class: 'oc-trail-sep', text: '›' })); }
            crumbs.appendChild(h('button', { type: 'button', class: 'oc-trail-link', text: a.title, onclick: function () { select(a.id); centerOn(a.id); } }));
        });
        body.appendChild(section('Rattachement', crumbs));

        body.appendChild(section('Présence dans les clubs', h('p', { class: 'oc-desc', text: n.clubs + ' club(s) sur ' + S.chart.club_count + ' ont ce bloc.' })));

        var title = h('input', { value: n.title, maxlength: 120, disabled: !S.canEdit, 'aria-label': 'Intitulé' });
        var blocked = {};
        blocked[n.key] = true;
        descendantKeys(n.id).forEach(function (k) { blocked[k] = true; });
        var parentOptions = [{ value: '', label: 'Le club' }].concat(S.chart.blocks.filter(function (b) { return !blocked[b.key]; }).map(function (b) { return { value: b.key, label: b.title }; }));
        var parent = selectOf(parentOptions, n.parent_key || '', !S.canEdit);
        var capacity = selectOf([{ value: 'single', label: 'Un seul titulaire' }, { value: 'multiple', label: 'Plusieurs personnes' }], n.capacity, !S.canEdit);
        var matrix = isPos ? buildMatrix(n) : null;

        var form = h('form', { class: 'oc-form', novalidate: true },
            field('Intitulé', title),
            field('Rattaché à', parent),
            isPos ? field('Titulaires du poste', capacity) : null,
            isPos ? h('div', { class: 'oc-field' }, h('label', { text: 'Habilitations par module' }), matrix.el) : null,
            S.canEdit ? h('div', { class: 'oc-actions' }, h('button', { type: 'submit', class: 'oc-btn oc-btn-primary', text: 'Enregistrer pour tous les clubs' })) : null
        );
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var payload = { title: title.value.trim(), parent_key: parent.value === '' ? null : parent.value };
            if (isPos) { payload.capacity = capacity.value; payload.modules = matrix.read(); }
            mutate('PATCH', '/blocs/' + encodeURIComponent(n.key), payload);
        });
        body.appendChild(section('Réglages', form));

        var kidsList = S.kids[n.id] || [];
        if (kidsList.length) {
            var list = h('ul', { class: 'oc-kids' });
            kidsList.forEach(function (c) {
                list.appendChild(h('li', {}, h('button', { type: 'button', class: 'oc-trail-link', text: c.title, onclick: function () { select(c.id); centerOn(c.id); } })));
            });
            body.appendChild(section('Sous-blocs (' + kidsList.length + ')', list));
        }

        var actions = h('div', { class: 'oc-actions' });
        actions.appendChild(btn('Centrer', function () { centerOn(n.id); }));
        if (S.canEdit) {
            actions.appendChild(btn('Ajouter un sous-bloc', function () { openAdd(n); }));
            actions.appendChild(btn('Monter', function () { mutate('POST', '/blocs/' + encodeURIComponent(n.key) + '/deplacer', { direction: 'up' }); }, 'oc-btn-sm'));
            actions.appendChild(btn('Descendre', function () { mutate('POST', '/blocs/' + encodeURIComponent(n.key) + '/deplacer', { direction: 'down' }); }, 'oc-btn-sm'));
            actions.appendChild(btn('Retirer du modèle', function () { remove(n); }, 'oc-btn-danger'));
        }
        body.appendChild(section('Actions', actions));
    }

    /* ------------------------------------------------------------------ Actions */

    function mutate(method, path, body, after) {
        return api(method, path, body).then(function (res) {
            setChart(res.chart);
            render();
            renderPanel();
            toast(res.message, 'success');
            if (after) { after(res); }
        }).catch(function (err) { toast(formatError(err), 'error'); });
    }

    function reparent(id, targetId) {
        var n = S.byId[id];
        var target = S.byId[targetId];
        askConfirm({
            title: 'Rattacher le bloc',
            body: '« ' + n.title + ' » sera rattaché à « ' + target.title + ' » dans tous les clubs ' + S.chart.label + '.',
            okLabel: 'Rattacher'
        }).then(function (ok) {
            if (ok) { mutate('PATCH', '/blocs/' + encodeURIComponent(n.key), { parent_key: targetId === ROOT_ID ? null : target.key }); }
        });
    }

    function remove(n) {
        askConfirm({
            title: 'Retirer du modèle',
            body: '« ' + n.title + ' » sera retiré du modèle. Dans les clubs, le bloc est supprimé s’il est vide ; s’il a des titulaires, il est conservé sans rôle.',
            okLabel: 'Retirer'
        }).then(function (ok) {
            if (ok) { mutate('DELETE', '/blocs/' + encodeURIComponent(n.key), null, function () { select(null); }); }
        });
    }

    function askConfirm(o) {
        return new Promise(function (resolve) {
            var dlg = $('#dlg-confirm');
            $('#cf-title').textContent = o.title;
            $('#cf-body').textContent = o.body;
            $('#cf-ok').textContent = o.okLabel || 'Confirmer';
            dlg.returnValue = '';
            var done = function () { dlg.removeEventListener('close', done); resolve(dlg.returnValue === 'ok'); };
            dlg.addEventListener('close', done);
            if (!dlg.open) { dlg.showModal(); }
        });
    }

    /* ----- Ajout d'un bloc */

    var addParent = null;

    function syncAddFields() {
        var isPos = $('#a-kind').value === 'position';
        $('#a-capacity-wrap').hidden = !isPos;
        $('#a-access-wrap').hidden = !isPos;
    }

    function openAdd(parentNode) {
        addParent = parentNode && parentNode.id !== ROOT_ID ? parentNode : null;
        $('#a-where').textContent = addParent ? 'Sous « ' + addParent.title + ' ».' : 'Directement sous le club.';

        var kind = $('#a-kind');
        kind.textContent = '';
        Object.keys(S.chart.kinds).forEach(function (k) { kind.appendChild(h('option', { value: k, text: S.chart.kinds[k] })); });
        kind.value = 'position';

        var access = $('#a-access');
        access.textContent = '';
        Object.keys(S.chart.levels).forEach(function (k) { access.appendChild(h('option', { value: k, text: S.chart.levels[k] })); });
        access.value = 'none';

        $('#a-title').value = '';
        $('#a-capacity').value = 'single';
        $('#a-error').hidden = true;
        syncAddFields();
        var dlg = $('#dlg-add');
        if (!dlg.open) { dlg.showModal(); }
        $('#a-title').focus();
    }

    $('#a-kind').addEventListener('change', syncAddFields);

    $('#f-add').addEventListener('submit', function (e) {
        e.preventDefault();
        var isPos = $('#a-kind').value === 'position';
        var payload = { title: $('#a-title').value.trim(), kind: $('#a-kind').value, parent_key: addParent ? addParent.key : null };
        if (isPos) { payload.capacity = $('#a-capacity').value; payload.access_level = $('#a-access').value; }

        api('POST', '/blocs', payload).then(function (res) {
            $('#dlg-add').close();
            setChart(res.chart);
            S.selectedKey = res.created || null;
            S.rootSelected = false;
            render();
            renderPanel();
            if (res.created && S.byKey[res.created]) { centerOn(S.byKey[res.created].id); }
            toast(res.message, 'success');
        }).catch(function (err) {
            var box = $('#a-error');
            box.textContent = formatError(err);
            box.hidden = false;
        });
    });

    $$('dialog').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('[data-close]')) { dlg.close(); } });
    });

    /* ------------------------------------------------------------------ Barre d'outils */

    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-act]');
        if (!el || el.disabled) { return; }

        switch (el.getAttribute('data-act')) {
            case 'add': openAdd(selected()); break;
            case 'apply': mutate('POST', '/appliquer', null); break;
            case 'reset-view': fit(); break;
            case 'zoom-in': zoomCenter(1.2); break;
            case 'zoom-out': zoomCenter(1 / 1.2); break;
            case 'zoom-reset': S.view.k = 1; applyView(); centerOn(ROOT_ID); break;
            case 'fit': fit(); break;
            case 'toggle-side': app.classList.toggle('oc-open-side'); app.classList.remove('oc-open-panel'); break;
            case 'toggle-panel': app.classList.toggle('oc-open-panel'); app.classList.remove('oc-open-side'); break;
            case 'close-panel': closeDrawers(); break;
        }
    });

    /* ------------------------------------------------------------------ Démarrage */

    setChart(cfg.chart);
    render();
    renderPanel();
    fit();
    $('#oc-loading').hidden = true;

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () { ellipsisCache = {}; render(); });
    }
    window.addEventListener('resize', function () { fit(); });
})();
