/*
 * Organigramme de la Fondation We Are Carthage — application (rendu SVG, interactions, édition).
 * Dépend de OrgLayout (orgchart-layout.js) et OrgExport (orgchart-export.js).
 * Tout le contenu dynamique est inséré via textContent / setAttribute (jamais innerHTML).
 */
(function () {
    'use strict';

    var cfg = JSON.parse(document.getElementById('oc-config').textContent);
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var NS = 'http://www.w3.org/2000/svg';
    var L = window.OrgLayout;
    var X = window.OrgExport;

    var FONT = "Manrope, 'Segoe UI', Helvetica, Arial, sans-serif";
    var TITLE_FONT = '700 13px ' + FONT;
    var SUB_FONT = '500 11.5px ' + FONT;
    var TITLE = 'Organigramme de la Fondation We Are Carthage';

    var COLORS = {
        presidency: '#6d3fd0',
        direction: '#0f2e5f',
        national_office: '#0b7fab',
        project: '#0f7a58',
        regional_club: '#c2570c',
        university_club: '#1c5bcb',
        highschool_club: '#b4237d'
    };
    var LINK = { hierarchy: '#5b6b8c', attachment: '#8d99b0', validate: '#d98a1f' };

    var FIELD_LABELS = {
        title: 'Intitulé', subtitle: 'Sous-titre', description: 'Description', category: 'Catégorie',
        relation_type: 'Nature du lien', parent_id: 'Rattachement', validation_status: 'Statut du lien',
        validation_note: 'Note de validation', children_layout: 'Disposition', is_collapsed: 'Repli par défaut',
        governorate_id: 'Gouvernorat', kind: 'Type', scope: 'Périmètre', email: 'E-mail', facebook_url: 'Facebook'
    };

    var S = {
        chart: null, nodes: [], byId: {}, kids: {}, people: {},
        canEdit: !!cfg.canEdit, editMode: false,
        selectedId: null,
        collapse: loadJson('oc.collapse.v1', {}),
        dirty: {},
        view: { x: 40, y: 24, k: 1 },
        lay: null,
        gNode: {}, pLink: [],
        term: '', matchIds: null, onlyValidate: false,
        gesture: null, pointers: {}, pinch: null, dropTarget: null
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

    function loadJson(key, fallback) {
        try { var v = JSON.parse(localStorage.getItem(key)); return v && typeof v === 'object' ? v : fallback; } catch (e) { return fallback; }
    }

    function saveCollapse() {
        try { localStorage.setItem('oc.collapse.v1', JSON.stringify(S.collapse)); } catch (e) { /* stockage indisponible */ }
    }

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

    function norm(s) {
        return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    function initials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        return ((parts[0] || '?')[0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
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
        if (Object.keys(ellipsisCache).length > 4000) { ellipsisCache = {}; }
        ellipsisCache[key] = out;
        return out;
    }

    function toast(message, kind, sticky) {
        var box = $('#oc-toasts');
        var t = h('div', { class: 'oc-toast ' + (kind || 'info'), role: kind === 'error' ? 'alert' : 'status', text: message });
        box.appendChild(t);
        var remove = function () { if (t.parentNode) { t.parentNode.removeChild(t); } };
        if (!sticky) { setTimeout(remove, kind === 'error' ? 7000 : 3800); }
        return remove;
    }

    function busy(message) { return toast(message, 'info', true); }

    function formatError(err) {
        if (err && err.errors) {
            var first = Object.keys(err.errors)[0];
            if (first) { return [].concat(err.errors[first])[0]; }
        }
        if (err && err.status === 419) { return 'Session expirée : rechargez la page puis réessayez.'; }
        if (err && err.status === 403) { return err.message || 'Action réservée aux administrateurs.'; }
        if (err && err.message) { return err.message; }
        return 'Une erreur est survenue. Réessayez.';
    }

    function api(method, path, body, isForm) {
        var opts = {
            method: method, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (body && isForm) { opts.body = body; }
        else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }

        return fetch(cfg.base + path, opts).then(function (r) {
            return r.json().catch(function () { return null; }).then(function (data) {
                if (!r.ok) { throw { status: r.status, message: data && data.message, errors: data && data.errors }; }
                return data;
            });
        });
    }

    /* ------------------------------------------------------------------ Données */

    function setChart(chart) {
        S.chart = chart;
        S.canEdit = !!chart.can_edit;
        S.nodes = chart.nodes;
        S.byId = {};
        S.kids = {};
        S.people = {};
        chart.people.forEach(function (p) { S.people[p.id] = p; });
        chart.nodes.forEach(function (n) { S.byId[n.id] = n; });
        chart.nodes.slice().sort(function (a, b) { return (a.sort_order - b.sort_order) || (a.id - b.id); }).forEach(function (n) {
            if (n.parent_id != null) { (S.kids[n.parent_id] = S.kids[n.parent_id] || []).push(n); }
        });
        Object.keys(S.dirty).forEach(function (id) {
            var n = S.byId[id];
            if (!n || (S.dirty[id].dx === n.dx && S.dirty[id].dy === n.dy)) { delete S.dirty[id]; }
        });
        if (S.selectedId != null && !S.byId[S.selectedId]) { S.selectedId = null; }
        descCache = {};
    }

    var descCache = {};

    function descendantIds(id) {
        if (descCache[id]) { return descCache[id]; }
        var out = [];
        (S.kids[id] || []).forEach(function (c) { out.push(c.id); out.push.apply(out, descendantIds(c.id)); });
        descCache[id] = out;
        return out;
    }

    function ancestorIds(id) {
        var out = [];
        var n = S.byId[id];
        var guard = 0;
        while (n && n.parent_id != null && guard++ < 100) { out.unshift(n.parent_id); n = S.byId[n.parent_id]; }
        return out;
    }

    function dfs() {
        var out = [];
        (function walk(n, depth) {
            out.push({ node: n, depth: depth });
            (S.kids[n.id] || []).forEach(function (c) { walk(c, depth + 1); });
        })(rootNode(), 0);
        return out;
    }

    function rootNode() {
        return S.nodes.filter(function (n) { return n.parent_id == null; })[0] || S.nodes[0];
    }

    function isCollapsed(n) {
        return Object.prototype.hasOwnProperty.call(S.collapse, n.id) ? !!S.collapse[n.id] : !!n.is_collapsed;
    }

    function peopleOf(n) {
        return (n.person_ids || []).map(function (id) { return S.people[id]; }).filter(Boolean);
    }

    function offsetOf(n) {
        return S.dirty[n.id] || { dx: n.dx || 0, dy: n.dy || 0 };
    }

    /* ------------------------------------------------------------------ Rendu */

    function layout() {
        S.lay = L.compute(S.nodes, { isCollapsed: isCollapsed, offsetOf: offsetOf });
    }

    function render() {
        layout();
        layerLinks.textContent = '';
        layerNodes.textContent = '';
        S.gNode = {};
        S.pLink = [];

        S.lay.links.forEach(function (link) {
            var child = S.byId[link.to];
            var tv = link.type !== 'attachment' && child.validation_status === 'to_validate';
            var path = sv('path', {
                'class': 'oc-link', fill: 'none',
                stroke: link.type === 'attachment' ? LINK.attachment : (tv ? LINK.validate : LINK.hierarchy),
                'stroke-width': 1.7,
                'stroke-dasharray': link.type === 'attachment' ? '7 5' : (tv ? '4 4' : null),
                'stroke-linejoin': 'round'
            });
            layerLinks.appendChild(path);
            S.pLink.push({ link: link, el: path });
        });
        updateLinks();

        S.nodes.forEach(function (n) {
            var p = S.lay.pos[n.id];
            if (p) { var g = drawNode(n, p); S.gNode[n.id] = g; layerNodes.appendChild(g); }
        });

        applyStates();
        renderSide();
        updateSaveState();
    }

    function updateLinks() {
        S.pLink.forEach(function (item) {
            var p = S.lay.pos[item.link.from];
            var c = S.lay.pos[item.link.to];
            if (p && c) { item.el.setAttribute('d', L.linkPath(item.link, p, c)); }
        });
    }

    function reposition() {
        layout();
        Object.keys(S.gNode).forEach(function (id) {
            var p = S.lay.pos[id];
            if (p) { S.gNode[id].setAttribute('transform', 'translate(' + p.x + ' ' + p.y + ')'); }
        });
        updateLinks();
    }

    function subtitleLine(n, people) {
        if (people.length) {
            return people[0].full_name + (people.length > 1 ? ' +' + (people.length - 1) : '');
        }
        var count = descendantIds(n.id).length;
        if (n.kind === 'club') {
            var place = n.institution_name && n.institution_name !== n.title ? n.institution_name : (n.governorate_name || 'Club');
            return place + (count ? ' · ' + count + ' postes' : '');
        }
        if (n.kind === 'unit' || n.kind === 'department' || n.kind === 'club_department') {
            return (n.kind === 'unit' ? '' : 'Département · ') + count + ' bloc' + (count > 1 ? 's' : '');
        }
        return null;
    }

    function drawNode(n, p) {
        var color = COLORS[n.category] || COLORS.direction;
        var tv = n.validation_status === 'to_validate';
        var people = peopleOf(n);
        var compact = p.inList;
        var hasKids = (S.kids[n.id] || []).length > 0;
        var collapsed = isCollapsed(n);
        var w = p.w;
        var hh = p.h;

        var g = sv('g', {
            'class': 'oc-node', 'data-id': n.id, transform: 'translate(' + p.x + ' ' + p.y + ')',
            tabindex: 0, role: 'button', 'aria-label': n.title + (people.length ? ' — ' + people[0].full_name : ' — poste vacant')
        });
        g.appendChild(sv('title', {}, tooltip(n, people)));

        g.appendChild(sv('rect', {
            'class': 'oc-card', width: w, height: hh, rx: 10, fill: '#ffffff',
            stroke: tv ? LINK.validate : '#cfd8e8', 'stroke-width': tv ? 1.8 : 1.2, 'stroke-dasharray': tv ? '5 3' : null
        }));
        g.appendChild(sv('path', { d: 'M10 0A10 10 0 0 0 0 10V' + (hh - 10) + 'A10 10 0 0 0 10 ' + hh + 'Z', fill: color }));

        var d = hh - (compact ? 20 : 22);
        var ax = 20;
        var ay = (hh - d) / 2;
        var cx = ax + d / 2;
        var cy = ay + d / 2;

        var isPosition = n.kind === 'position';

        if (!isPosition) {
            // Unités, départements et clubs : pas d'avatar, seulement le libellé.
        } else if (people[0] && people[0].photo_url) {
            g.appendChild(sv('circle', { cx: cx, cy: cy, r: d / 2 + 1, fill: '#ffffff', stroke: color, 'stroke-width': 1.5 }));
            g.appendChild(sv('image', {
                href: people[0].photo_url, x: ax, y: ay, width: d, height: d,
                preserveAspectRatio: 'xMidYMid slice', 'clip-path': 'url(#oc-clip-c)'
            }));
        } else if (people[0]) {
            g.appendChild(sv('circle', { cx: cx, cy: cy, r: d / 2, fill: color, 'fill-opacity': 0.14, stroke: color, 'stroke-width': 1.2 }));
            g.appendChild(sv('text', {
                x: cx, y: cy + 4.5, 'text-anchor': 'middle', 'font-family': FONT, 'font-size': compact ? 11 : 13, 'font-weight': 800, fill: color
            }, initials(people[0].full_name)));
        } else {
            g.appendChild(sv('circle', { cx: cx, cy: cy, r: d / 2, fill: '#f3f5fa', stroke: '#c3cde0', 'stroke-width': 1.2, 'stroke-dasharray': '3 3' }));
            g.appendChild(sv('circle', { cx: cx, cy: cy - d * 0.12, r: d * 0.14, fill: '#b7c2d8' }));
            g.appendChild(sv('path', {
                d: 'M' + (cx - d * 0.25) + ' ' + (cy + d * 0.3) + 'A' + (d * 0.25) + ' ' + (d * 0.22) + ' 0 0 1 ' + (cx + d * 0.25) + ' ' + (cy + d * 0.3) + 'Z',
                fill: '#b7c2d8'
            }));
        }

        var tx = isPosition ? ax + d + 10 : 20;
        var maxW = w - tx - (hasKids && compact ? 34 : 12);
        var vacant = !people.length && n.kind === 'position';
        var line2 = subtitleLine(n, people) || (vacant ? 'Poste vacant' : null);
        var titleLines = compact ? [ellipsize(n.title, maxW, TITLE_FONT)] : wrapLines(n.title, maxW, TITLE_FONT, 2);
        var two = titleLines.length === 2;
        var yTitle = compact ? hh / 2 - 3 : (two ? (line2 ? hh / 2 - 15 : hh / 2 - 4) : hh / 2 - 5);
        var yLine2 = compact ? hh / 2 + 13 : (two ? hh / 2 + 18 : hh / 2 + 14);

        titleLines.forEach(function (text, i) {
            g.appendChild(sv('text', {
                x: tx, y: (line2 || two) ? yTitle + i * 14 : hh / 2 + 4.5, 'font-family': FONT, 'font-size': 13, 'font-weight': 700, fill: '#0a2147'
            }, text));
        });
        if (line2) {
            g.appendChild(sv('text', {
                x: tx, y: yLine2, 'font-family': FONT, 'font-size': 11.5, 'font-weight': 500,
                fill: people.length ? '#34415c' : '#8a96ac', 'font-style': vacant ? 'italic' : 'normal'
            }, ellipsize(line2, maxW, SUB_FONT)));
        }

        if (tv) {
            var badge = sv('g', { 'class': 'oc-badge-validate', transform: 'translate(' + (w - 2) + ' 2)' });
            badge.appendChild(sv('circle', { r: 9, fill: '#fff3dd', stroke: LINK.validate, 'stroke-width': 1.4 }));
            badge.appendChild(sv('text', { y: 4, 'text-anchor': 'middle', 'font-family': FONT, 'font-size': 11, 'font-weight': 800, fill: '#a85a08' }, '?'));
            g.appendChild(badge);
        }

        if (hasKids) {
            var hidden = descendantIds(n.id).length;
            var tg = sv('g', {
                'class': 'oc-toggle' + (collapsed ? '' : ' oc-toggle-open'), 'data-toggle': n.id,
                transform: compact ? 'translate(' + (w - 18) + ' ' + hh / 2 + ')' : 'translate(' + w / 2 + ' ' + hh + ')',
                role: 'button', 'aria-label': collapsed ? 'Déplier' : 'Replier'
            });
            var label = collapsed ? '+' + hidden : '−';
            var tw = collapsed ? Math.max(24, 12 + label.length * 7) : 22;
            tg.appendChild(sv('rect', { x: -tw / 2, y: -10, width: tw, height: 20, rx: 10, fill: collapsed ? color : '#ffffff', stroke: color, 'stroke-width': 1.5 }));
            tg.appendChild(sv('text', {
                y: 4, 'text-anchor': 'middle', 'font-family': FONT, 'font-size': 11, 'font-weight': 800, fill: collapsed ? '#ffffff' : color
            }, label));
            g.appendChild(tg);
        }

        return g;
    }

    function tooltip(n, people) {
        var lines = [n.title];
        if (n.subtitle) { lines.push(n.subtitle); }
        lines.push(people.length ? people.map(function (p) { return p.full_name; }).join(', ') : (n.kind === 'position' ? 'Poste vacant' : ''));
        if (n.validation_status === 'to_validate') { lines.push('Relation à valider'); }
        return lines.filter(Boolean).join('\n');
    }

    function applyStates() {
        var dimming = S.matchIds !== null || S.onlyValidate;
        Object.keys(S.gNode).forEach(function (id) {
            var n = S.byId[id];
            var g = S.gNode[id];
            var match = (S.matchIds === null || S.matchIds[id]) && (!S.onlyValidate || n.validation_status === 'to_validate');
            g.classList.toggle('is-selected', String(S.selectedId) === String(id));
            g.classList.toggle('is-match', dimming && match);
            g.classList.toggle('is-dim', dimming && !match);
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

    function fitTop() {
        var s = stageSize();
        var b = S.lay.bounds;
        var bw = b.maxX - b.minX;
        var k = Math.max(0.4, Math.min((s.w - 40) / bw, 1));
        S.view.k = k;
        S.view.x = (s.w - bw * k) / 2 - b.minX * k;
        S.view.y = 28 - b.minY * k;
        applyView();
    }

    function centerOn(id, minK) {
        var p = S.lay.pos[id];
        if (!p) { return; }
        var s = stageSize();
        S.view.k = Math.max(S.view.k, minK || 0.7);
        S.view.x = s.w / 2 - (p.x + p.w / 2) * S.view.k;
        S.view.y = s.h / 3 - (p.y + p.h / 2) * S.view.k;
        applyView();
    }

    function toWorld(clientX, clientY) {
        var r = svgRoot.getBoundingClientRect();
        return { x: (clientX - r.left - S.view.x) / S.view.k, y: (clientY - r.top - S.view.y) / S.view.k };
    }

    /* ------------------------------------------------------------------ Interactions souris / tactile */

    function nodeIdFrom(target) {
        var g = target.closest ? target.closest('.oc-node') : null;
        return g ? Number(g.getAttribute('data-id')) : null;
    }

    svgRoot.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse' && e.button !== 0) { return; }
        if (e.target.closest && e.target.closest('.oc-toggle')) { return; }

        S.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
        closeMenus();

        if (Object.keys(S.pointers).length === 2) {
            var pts = Object.keys(S.pointers).map(function (k) { return S.pointers[k]; });
            S.pinch = { d: Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y), k: S.view.k };
            S.gesture = null;
            return;
        }

        try { svgRoot.setPointerCapture(e.pointerId); } catch (err) { /* ignoré */ }

        var id = nodeIdFrom(e.target);
        var base = { startX: e.clientX, startY: e.clientY, viewX: S.view.x, viewY: S.view.y, moved: false, id: id };

        if (id != null && S.editMode && S.canEdit) {
            var off = offsetOf(S.byId[id]);
            S.gesture = Object.assign(base, { type: 'drag', ox: off.dx, oy: off.dy });
        } else {
            S.gesture = Object.assign(base, { type: id != null ? 'tap' : 'pan' });
        }
    });

    svgRoot.addEventListener('pointermove', function (e) {
        if (S.pointers[e.pointerId]) { S.pointers[e.pointerId] = { x: e.clientX, y: e.clientY }; }

        if (S.pinch && Object.keys(S.pointers).length === 2) {
            var pts = Object.keys(S.pointers).map(function (k) { return S.pointers[k]; });
            var d = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
            var r = svgRoot.getBoundingClientRect();
            var targetK = S.pinch.k * (d / S.pinch.d);
            zoomAt(targetK / S.view.k, (pts[0].x + pts[1].x) / 2 - r.left, (pts[0].y + pts[1].y) / 2 - r.top);
            return;
        }

        var gs = S.gesture;
        if (!gs) { return; }
        var dx = e.clientX - gs.startX;
        var dy = e.clientY - gs.startY;

        if (!gs.moved && Math.hypot(dx, dy) > 5) {
            gs.moved = true;
            if (gs.type === 'tap') { gs.type = 'pan'; }
            if (gs.type === 'drag') { svgRoot.classList.add('is-dragging'); }
            else { svgRoot.classList.add('is-panning'); }
        }
        if (!gs.moved) { return; }

        if (gs.type === 'pan') {
            S.view.x = gs.viewX + dx;
            S.view.y = gs.viewY + dy;
            applyView();
        } else if (gs.type === 'drag') {
            S.dirty[gs.id] = { dx: Math.round(gs.ox + dx / S.view.k), dy: Math.round(gs.oy + dy / S.view.k) };
            reposition();
            updateDropTarget(e);
            updateSaveState();
        }
    });

    function endPointer(e) {
        delete S.pointers[e.pointerId];
        if (Object.keys(S.pointers).length < 2) { S.pinch = null; }
        try { svgRoot.releasePointerCapture(e.pointerId); } catch (err) { /* ignoré */ }

        var gs = S.gesture;
        svgRoot.classList.remove('is-dragging', 'is-panning');
        if (!gs || e.type === 'pointercancel') { S.gesture = null; clearDrop(); return; }
        S.gesture = null;

        if (gs.type === 'tap' || (gs.type === 'drag' && !gs.moved)) {
            if (gs.id != null) { select(gs.id); }
        } else if (gs.type === 'drag' && gs.moved) {
            var target = S.dropTarget;
            clearDrop();
            if (e.shiftKey && target != null) { reparentByDrag(gs.id, target, gs); }
        } else if (gs.type === 'pan' && !gs.moved) {
            select(null);
            closeDrawers();
        }
    }

    svgRoot.addEventListener('pointerup', endPointer);
    svgRoot.addEventListener('pointercancel', endPointer);

    svgRoot.addEventListener('click', function (e) {
        var tg = e.target.closest ? e.target.closest('.oc-toggle') : null;
        if (tg) { e.stopPropagation(); toggleCollapse(Number(tg.getAttribute('data-toggle'))); }
    });

    svgRoot.addEventListener('dblclick', function (e) {
        // La capture du pointeur redirige l'événement vers le SVG : on retrouve le bloc par ses coordonnées.
        var hit = document.elementFromPoint(e.clientX, e.clientY);
        var id = hit ? nodeIdFrom(hit) : null;
        if (id != null && S.canEdit && !(hit.closest && hit.closest('.oc-toggle'))) { openNodeDialog({ mode: 'edit', node: S.byId[id] }); }
    });

    svgRoot.addEventListener('wheel', function (e) {
        e.preventDefault();
        var r = svgRoot.getBoundingClientRect();
        var speed = e.ctrlKey ? 0.01 : 0.0016;
        zoomAt(Math.exp(-e.deltaY * speed), e.clientX - r.left, e.clientY - r.top);
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

    function updateDropTarget(e) {
        clearDrop();
        if (!e.shiftKey || !S.gesture) { return; }
        var w = toWorld(e.clientX, e.clientY);
        var blocked = {};
        blocked[S.gesture.id] = true;
        descendantIds(S.gesture.id).forEach(function (id) { blocked[id] = true; });
        var found = null;
        Object.keys(S.lay.pos).forEach(function (id) {
            var p = S.lay.pos[id];
            if (!blocked[id] && S.byId[id].relation_type !== 'attachment' && w.x >= p.x && w.x <= p.x + p.w && w.y >= p.y && w.y <= p.y + p.h) { found = Number(id); }
        });
        if (found != null) {
            S.dropTarget = found;
            S.gNode[found].classList.add('is-drop');
        }
    }

    function clearDrop() {
        if (S.dropTarget != null && S.gNode[S.dropTarget]) { S.gNode[S.dropTarget].classList.remove('is-drop'); }
        S.dropTarget = null;
    }

    function toggleCollapse(id) {
        var n = S.byId[id];
        S.collapse[id] = !isCollapsed(n);
        saveCollapse();
        render();
    }

    function setAllCollapsed(collapsed) {
        var depthOf = {};
        dfs().forEach(function (item) { depthOf[item.node.id] = item.depth; });
        S.nodes.forEach(function (n) {
            if ((S.kids[n.id] || []).length) { S.collapse[n.id] = collapsed ? depthOf[n.id] >= 2 : false; }
        });
        saveCollapse();
        render();
        fitTop();
    }

    /* ------------------------------------------------------------------ Sélection et panneau */

    function select(id) {
        S.selectedId = id;
        applyStates();
        renderPanel();
        renderSide();
        if (id != null && window.matchMedia('(max-width: 900px)').matches) { app.classList.add('oc-open-panel'); app.classList.remove('oc-open-side'); }
    }

    function focusNode(id) {
        ancestorIds(id).forEach(function (a) { S.collapse[a] = false; });
        saveCollapse();
        S.selectedId = id;
        render();
        centerOn(id, 0.8);
        renderPanel();
        var g = S.gNode[id];
        if (g) { g.classList.add('is-flash'); setTimeout(function () { g.classList.remove('is-flash'); }, 1800); }
        closeDrawers();
        if (window.matchMedia('(max-width: 900px)').matches) { app.classList.add('oc-open-panel'); }
    }

    function chip(text, color, cls) {
        return h('span', { class: 'oc-chip ' + (cls || ''), style: color ? '--chip:' + color : null, text: text });
    }

    function avatar(person, size, color) {
        var s = 'width:' + size + 'px;height:' + size + 'px;';
        if (person && person.photo_url) { return h('img', { class: 'oc-avatar', src: person.photo_url, alt: '', style: s, loading: 'lazy' }); }
        return h('span', { class: 'oc-avatar oc-avatar-initials', style: s + 'font-size:' + Math.round(size * 0.38) + 'px;--chip:' + (color || '#0f2e5f'), text: person ? initials(person.full_name) : '?' });
    }

    function section(title, body, actions) {
        return h('section', { class: 'oc-sec' }, h('div', { class: 'oc-sec-head' }, h('h3', { text: title }), actions || null), body);
    }

    // Recherche dans la liste des utilisateurs de la plateforme (nom ou e-mail) puis affectation au bloc.
    function userPicker(n) {
        var input = h('input', { type: 'search', class: 'oc-user-input', placeholder: 'Affecter un utilisateur (nom ou e-mail)…', autocomplete: 'off', 'aria-label': 'Rechercher un utilisateur à affecter' });
        var results = h('div', { class: 'oc-user-results', role: 'listbox', hidden: true });
        var timer = null;
        var seq = 0;

        function show(users, message) {
            results.textContent = '';
            results.hidden = false;
            if (message) { results.appendChild(h('p', { class: 'oc-muted', text: message })); }
            users.forEach(function (u) {
                var assigned = (n.person_ids || []).some(function (pid) { var p = S.people[pid]; return p && p.user_id === u.id; });
                results.appendChild(h('button', {
                    type: 'button', class: 'oc-user-item', role: 'option', disabled: assigned,
                    onclick: function () { pick(u); }
                }, h('strong', { text: u.name }), h('small', { class: 'oc-muted', text: [u.email, u.phone, assigned ? 'déjà affecté' : (u.already_listed ? 'déjà dans l\'organigramme' : null)].filter(Boolean).join(' · ') })));
            });
        }

        function pick(u) {
            input.disabled = true;
            api('POST', '/blocs/' + n.id + '/utilisateurs', { user_id: u.id }).then(function (res) {
                applyResponse(res, u.name + ' affecté(e) à « ' + n.title + ' ».');
            }).catch(function (err) { input.disabled = false; toast(formatError(err), 'error'); });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var term = input.value.trim();
            if (term.length < 2) { results.hidden = true; return; }
            timer = setTimeout(function () {
                var mine = ++seq;
                api('GET', '/utilisateurs?q=' + encodeURIComponent(term)).then(function (res) {
                    if (mine !== seq) { return; }
                    show(res.users, res.users.length ? null : 'Aucun utilisateur actif ne correspond.');
                }).catch(function (err) { show([], formatError(err)); });
            }, 250);
        });
        input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { results.hidden = true; } });

        return h('div', { class: 'oc-user-picker' }, input, results);
    }

    function btn(label, onclick, cls) {
        return h('button', { type: 'button', class: 'oc-btn ' + (cls || ''), onclick: onclick, text: label });
    }

    // Perimetre, club (statut, etablissement, contact) et effectifs rattaches au bloc.
    function platformLinks(n) {
        var rows = [];

        function row(label, text, url, extra) {
            if (!text) { return; }
            rows.push(h('div', { class: 'oc-link-row' },
                h('span', { class: 'oc-muted', text: label }),
                url ? h('a', { href: url, text: text, rel: 'noopener' }) : h('strong', { text: text }),
                extra ? h('small', { class: 'oc-muted', text: extra }) : null
            ));
        }

        function plural(count, word) { return count + ' ' + word + (count > 1 ? 's' : ''); }

        var meta = S.chart.meta || {};
        var isClub = n.kind === 'club';

        row('Périmètre', n.scope_name, null, n.scope_inherited ? 'hérité du bloc parent' : (n.members_scope != null ? plural(n.members_scope, 'adhérent') : null));
        if (isClub) {
            row('Statut', (meta.club_statuses || {})[n.status] || n.status, null, n.members_club != null ? plural(n.members_club, 'adhérent') : null);
            row('Établissement', n.institution_name, null, (meta.institution_types || {})[n.institution_type] || null);
            row('E-mail', n.email, n.email ? 'mailto:' + n.email : null);
            row('Facebook', n.facebook_url, /^https?:\/\//i.test(n.facebook_url || '') ? n.facebook_url : null);
            row('Fondé le', n.founded_on);
            row('Charte', n.charter_signed ? 'signée' : null);
        }
        row('Gouvernorat', n.governorate_name);

        return rows.length ? h('div', { class: 'oc-links' }, rows) : null;
    }

    function renderPanel() {
        var body = $('#oc-panel-body');
        body.textContent = '';
        var n = S.selectedId != null ? S.byId[S.selectedId] : null;

        if (!n) {
            body.appendChild(h('div', { class: 'oc-empty' },
                h('p', { class: 'oc-empty-title', text: 'Aucun bloc sélectionné' }),
                h('p', { text: 'Cliquez sur un poste, une unité ou un club pour afficher sa fiche : personne, fonction, téléphone et e-mail professionnels, rattachement.' }),
                S.canEdit ? h('p', { text: 'Activez « Modifier » pour déplacer les blocs à la souris. Utilisez « Ajouter » pour créer des postes, des clubs ou des personnes.' }) : h('p', { class: 'oc-note', text: 'Vous consultez l\'organigramme en lecture seule.' })
            ));
            return;
        }

        var color = COLORS[n.category] || COLORS.direction;
        var people = peopleOf(n);
        var kidsCount = (S.kids[n.id] || []).length;
        var allCount = descendantIds(n.id).length;

        body.appendChild(h('div', { class: 'oc-fiche-head', style: '--chip:' + color },
            chip(S.chart.meta.categories[n.category] || n.category, color, 'is-solid'),
            h('h3', { class: 'oc-fiche-title', text: n.title }),
            n.subtitle ? h('p', { class: 'oc-fiche-sub', text: n.subtitle }) : null,
            h('div', { class: 'oc-badges' },
                chip(S.chart.meta.kinds[n.kind] || n.kind),
                n.kind === 'position' ? chip(S.chart.meta.capacities[n.capacity] || n.capacity, null, n.capacity === 'multiple' ? 'is-ok' : '') : null,
                n.kind === 'position' && n.access_level && n.access_level !== 'none' ? chip('Accès : ' + (S.chart.meta.access_levels[n.access_level] || n.access_level).split(' (')[0], '#6d3fd0') : null,
                n.relation_type === 'attachment' ? chip('Rattachement', '#6d3fd0') : chip('Lien hiérarchique'),
                n.validation_status === 'to_validate' ? chip('À valider', '#a85a08', 'is-warn') : chip('Confirmé', '#0f7a58', 'is-ok')
            ),
            n.validation_note ? h('p', { class: 'oc-note oc-note-warn', text: n.validation_note }) : null
        ));

        // Chemin hiérarchique
        var trail = ancestorIds(n.id);
        if (trail.length) {
            var crumbs = h('div', { class: 'oc-trail' });
            trail.forEach(function (aid, i) {
                if (i) { crumbs.appendChild(h('span', { class: 'oc-trail-sep', text: '›' })); }
                crumbs.appendChild(h('button', { type: 'button', class: 'oc-trail-link', text: S.byId[aid].title, onclick: function () { focusNode(aid); } }));
            });
            body.appendChild(section(n.relation_type === 'attachment' ? 'Rattaché à' : 'Rattachement hiérarchique', crumbs));
        }

        // Liens avec la structure de la plateforme
        var links = platformLinks(n);
        if (links) { body.appendChild(section('Liens avec la plateforme', links)); }

        // Personnes
        var list = h('div', { class: 'oc-people' });
        var isPos = n.kind === 'position';
        var full = n.capacity !== 'multiple' && people.length >= 1;
        if (!people.length) { list.appendChild(h('p', { class: 'oc-muted', text: isPos ? 'Poste vacant : aucune personne affectée.' : 'Les personnes sont affectées aux postes : ajoutez un poste dans ce bloc.' })); }
        people.forEach(function (p) {
            var row = h('div', { class: 'oc-person' },
                avatar(p, 46, color),
                h('div', { class: 'oc-person-info' },
                    h('strong', { text: p.full_name }),
                    p.job_title ? h('span', { class: 'oc-muted', text: p.job_title }) : null,
                    p.phone ? h('a', { href: 'tel:' + p.phone.replace(/[^0-9+]/g, ''), text: p.phone }) : null,
                    p.email ? h('a', { href: 'mailto:' + p.email, text: p.email }) : null,
                    !p.phone && !p.email ? h('span', { class: 'oc-muted', text: 'Coordonnées non communiquées' }) : null
                ),
                S.canEdit ? h('div', { class: 'oc-person-actions' },
                    h('button', { type: 'button', class: 'oc-icon-btn', title: 'Modifier la personne', 'aria-label': 'Modifier la personne', text: '✎', onclick: function () { openPersonDialog({ person: p }); } }),
                    h('button', { type: 'button', class: 'oc-icon-btn', title: 'Retirer de ce poste', 'aria-label': 'Retirer de ce poste', text: '⊖', onclick: function () { unassign(n, p.id); } })
                ) : null
            );
            list.appendChild(row);
        });

        if (S.canEdit && isPos && full) {
            list.appendChild(h('p', { class: 'oc-note', text: 'Poste à titulaire unique : retirez la personne en place pour en affecter une autre, ou passez le poste en « Plusieurs personnes ».' }));
        } else if (S.canEdit && isPos) {
            list.appendChild(userPicker(n));
            var free = S.chart.people.filter(function (p) { return (n.person_ids || []).indexOf(p.id) === -1; });
            var select = h('select', { 'aria-label': 'Affecter une personne déjà dans l\'organigramme' }, h('option', { value: '', text: free.length ? 'Ou une personne déjà dans l\'organigramme…' : 'Aucune autre personne dans l\'organigramme' }));
            free.forEach(function (p) { select.appendChild(h('option', { value: p.id, text: p.full_name + (p.job_title ? ' — ' + p.job_title : '') })); });
            select.disabled = !free.length;
            select.addEventListener('change', function () { if (select.value) { assign(n, Number(select.value)); } });
            list.appendChild(h('div', { class: 'oc-assign' }, select));
        }
        body.appendChild(section('Personnes', list));

        if (n.description) { body.appendChild(section('Description', h('p', { class: 'oc-desc', text: n.description }))); }

        if (kidsCount || allCount) {
            var kidsList = h('ul', { class: 'oc-kids' });
            (S.kids[n.id] || []).slice(0, 12).forEach(function (c) {
                var cp = peopleOf(c)[0];
                kidsList.appendChild(h('li', {}, h('button', { type: 'button', class: 'oc-trail-link', onclick: function () { focusNode(c.id); }, text: c.title + (cp ? ' — ' + cp.full_name : '') + (c.relation_type === 'attachment' ? ' (rattaché)' : '') })));
            });
            if (kidsCount > 12) { kidsList.appendChild(h('li', { class: 'oc-muted', text: '… et ' + (kidsCount - 12) + ' autres' })); }
            body.appendChild(section('Sous-blocs (' + kidsCount + ' directs, ' + allCount + ' au total)', kidsList));
        }

        // Actions
        var actions = h('div', { class: 'oc-actions' });
        actions.appendChild(btn('Centrer', function () { centerOn(n.id, 0.8); }));
        if (kidsCount) { actions.appendChild(btn(isCollapsed(n) ? 'Déplier' : 'Replier', function () { toggleCollapse(n.id); renderPanel(); })); }
        if (S.canEdit) {
            actions.appendChild(btn('Modifier le bloc', function () { openNodeDialog({ mode: 'edit', node: n }); }, 'oc-btn-primary'));
            actions.appendChild(btn('Ajouter un sous-poste', function () { openNodeDialog({ mode: 'create', parentId: n.id }); }));
            if (n.parent_id != null) {
                actions.appendChild(btn('Monter', function () { reorder(n, 'up'); }, 'oc-btn-sm'));
                actions.appendChild(btn('Descendre', function () { reorder(n, 'down'); }, 'oc-btn-sm'));
            }
            if (n.kind === 'club' && !kidsCount) { actions.appendChild(btn('Appliquer l\'équipe type', function () { applyTemplate(n); })); }
            var off = offsetOf(n);
            if (off.dx || off.dy) { actions.appendChild(btn('Position automatique', function () { S.dirty[n.id] = { dx: 0, dy: 0 }; reposition(); updateSaveState(); toast('Position rétablie : pensez à enregistrer.', 'info'); })); }
            actions.appendChild(btn('Historique du bloc', function () { openHistory(n.id); }));
            if (n.parent_id != null) { actions.appendChild(btn('Supprimer', function () { deleteNode(n); }, 'oc-btn-danger')); }
        }
        body.appendChild(section('Actions', actions));
    }

    function renderStats() {
        var dl = $('#oc-stats');
        dl.textContent = '';
        var positions = S.nodes.filter(function (n) { return n.kind === 'position'; });
        var vacant = positions.filter(function (n) { return !(n.person_ids || []).length; }).length;
        var rows = [
            ['Blocs', S.nodes.length],
            ['Clubs', S.nodes.filter(function (n) { return n.kind === 'club'; }).length],
            ['Départements', S.nodes.filter(function (n) { return n.kind === 'department' || n.kind === 'club_department'; }).length],
            ['Postes pourvus', positions.length - vacant],
            ['Postes vacants', vacant],
            ['Personnes', S.chart.people.length],
            ['Relations à valider', S.nodes.filter(function (n) { return n.validation_status === 'to_validate'; }).length]
        ];
        rows.forEach(function (r) { dl.appendChild(h('div', {}, h('dt', { text: r[0] }), h('dd', { text: String(r[1]) }))); });
    }

    function renderSide() {
        var nav = $('#oc-branches');
        nav.textContent = '';
        dfs().forEach(function (item) {
            var n = item.node;
            if (n.kind !== 'unit' && item.depth > 2) { return; }
            var color = COLORS[n.category] || COLORS.direction;
            var tv = descendantIds(n.id).concat([n.id]).filter(function (id) { return S.byId[id].validation_status === 'to_validate'; }).length;
            nav.appendChild(h('button', {
                type: 'button', class: 'oc-branch' + (String(S.selectedId) === String(n.id) ? ' is-active' : ''),
                style: 'padding-left:' + (10 + Math.min(item.depth, 4) * 12) + 'px;--chip:' + color,
                onclick: function () { focusNode(n.id); }
            },
                h('i', { class: 'oc-dot', style: 'background:' + color }),
                h('span', { class: 'oc-branch-name', text: n.title }),
                h('span', { class: 'oc-count', title: descendantIds(n.id).length + ' sous-blocs', text: String(descendantIds(n.id).length) }),
                tv ? h('span', { class: 'oc-count is-warn', title: tv + ' relation(s) à valider', text: '?' + tv }) : null
            ));
        });
        renderStats();
    }

    /* ------------------------------------------------------------------ Recherche */

    var searchTimer = null;

    $('#oc-search').addEventListener('input', function (e) {
        clearTimeout(searchTimer);
        var value = e.target.value;
        searchTimer = setTimeout(function () { runSearch(value); }, 120);
    });

    $('#oc-filter-validate').addEventListener('change', function (e) {
        S.onlyValidate = e.target.checked;
        applyStates();
    });

    function runSearch(value) {
        var term = norm(value).trim();
        var box = $('#oc-results');
        box.textContent = '';
        S.term = term;

        if (term.length < 2) {
            S.matchIds = null;
            box.hidden = true;
            applyStates();
            return;
        }

        var words = term.split(/\s+/);
        var matches = [];
        S.nodes.forEach(function (n) {
            var people = peopleOf(n);
            var hay = norm([n.title, n.subtitle, n.governorate_name, n.scope_name, n.institution_name, n.description].concat(people.map(function (p) { return p.full_name + ' ' + (p.job_title || ''); })).join(' '));
            if (words.every(function (w) { return hay.indexOf(w) !== -1; })) { matches.push(n); }
        });

        S.matchIds = {};
        matches.forEach(function (n) { S.matchIds[n.id] = true; });
        applyStates();

        box.hidden = false;
        box.appendChild(h('p', { class: 'oc-results-title', text: matches.length ? matches.length + ' résultat' + (matches.length > 1 ? 's' : '') : 'Aucun résultat' }));
        matches.slice(0, 40).forEach(function (n) {
            var p = peopleOf(n)[0];
            box.appendChild(h('button', { type: 'button', class: 'oc-result', onclick: function () { focusNode(n.id); } },
                h('i', { class: 'oc-dot', style: 'background:' + (COLORS[n.category] || COLORS.direction) }),
                h('span', {}, h('strong', { text: n.title }), h('small', { text: [n.subtitle, p ? p.full_name : null].filter(Boolean).join(' · ') }))
            ));
        });
        if (matches.length > 40) { box.appendChild(h('p', { class: 'oc-muted', text: 'Affinez la recherche pour voir les autres résultats.' })); }
    }

    /* ------------------------------------------------------------------ Légende */

    function buildLegend() {
        var body = $('#oc-legend-body');
        body.textContent = '';
        Object.keys(S.chart.meta.categories).forEach(function (key) {
            body.appendChild(h('div', { class: 'oc-legend-row' }, h('i', { class: 'oc-swatch', style: 'background:' + COLORS[key] }), h('span', { text: S.chart.meta.categories[key] })));
        });
        lineLegend().forEach(function (item) {
            var line = sv('svg', { width: 22, height: 10, viewBox: '0 0 22 10', 'aria-hidden': 'true' });
            line.appendChild(sv('line', { x1: 0, y1: 5, x2: 22, y2: 5, stroke: item.color, 'stroke-width': 2, 'stroke-dasharray': item.dash }));
            body.appendChild(h('div', { class: 'oc-legend-row' }, line, h('span', { text: item.label })));
        });
        body.appendChild(h('div', { class: 'oc-legend-row' }, h('i', { class: 'oc-swatch is-vacant' }), h('span', { text: 'Poste vacant (aucun nom saisi)' })));
    }

    function lineLegend() {
        return [
            { label: 'Lien hiérarchique confirmé', color: LINK.hierarchy, dash: null },
            { label: 'Rattachement sans lien hiérarchique', color: LINK.attachment, dash: '7 5' },
            { label: 'Relation à valider (« ? »)', color: LINK.validate, dash: '4 4' }
        ];
    }

    /* ------------------------------------------------------------------ Dialogues */

    function openDialog(dlg) { if (!dlg.open) { dlg.showModal(); } }

    $$('dialog').forEach(function (dlg) {
        dlg.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('[data-close]')) { dlg.close(); } });
    });

    function showError(id, err) {
        var box = $(id);
        box.textContent = formatError(err);
        box.hidden = false;
    }

    function clearError(id) { var box = $(id); box.textContent = ''; box.hidden = true; }

    function fillSelect(select, options, value, emptyLabel) {
        select.textContent = '';
        if (emptyLabel != null) { select.appendChild(h('option', { value: '', text: emptyLabel })); }
        options.forEach(function (o) { select.appendChild(h('option', { value: o.value, text: o.label })); });
        select.value = value == null ? '' : String(value);
    }

    function mapOptions(obj) { return Object.keys(obj).map(function (k) { return { value: k, label: obj[k] }; }); }

    function askConfirm(o) {
        return new Promise(function (resolve) {
            var dlg = $('#dlg-confirm');
            $('#cf-title').textContent = o.title;
            $('#cf-body').textContent = o.body;
            $('#cf-ok').textContent = o.okLabel || 'Confirmer';
            $('#cf-ok').className = 'oc-btn ' + (o.primary ? 'oc-btn-primary' : 'oc-btn-danger');
            var wrap = $('#cf-opt-wrap');
            wrap.hidden = !o.optLabel;
            $('#cf-opt').checked = false;
            $('#cf-opt-label').textContent = o.optLabel || '';
            dlg.returnValue = '';
            var done = function () {
                dlg.removeEventListener('close', done);
                resolve({ ok: dlg.returnValue === 'ok', opt: $('#cf-opt').checked });
            };
            dlg.addEventListener('close', done);
            openDialog(dlg);
        });
    }

    /* ----- Bloc */

    var nodeCtx = null;

    // Un bloc est dans un club s'il est ce club ou s'il en descend.
    function inClub(id) {
        if (id == null || !S.byId[id]) { return false; }
        return S.byId[id].kind === 'club' || ancestorIds(id).some(function (aid) { return S.byId[aid].kind === 'club'; });
    }

    // Types proposés selon l'emplacement : département de club dans un club, département de bureau national ailleurs.
    function refreshKindOptions(selected, parentId, isClub) {
        var kinds = S.chart.meta.kinds;
        var club = inClub(parentId);
        var allowed = ['position', 'unit', club ? 'club_department' : 'department'];
        if (selected && allowed.indexOf(selected) === -1 && selected !== 'club') { allowed.push(selected); }
        var options = isClub ? [{ value: 'club', label: kinds.club }] : allowed.map(function (k) { return { value: k, label: kinds[k] }; });
        fillSelect($('#n-kind'), options, isClub ? 'club' : selected);
        $('#n-kind').disabled = !!isClub;
        syncCapacityField();
    }

    function syncCapacityField() {
        $('#n-capacity-wrap').hidden = $('#n-kind').value !== 'position';
        $('#n-access-wrap').hidden = $('#n-kind').value !== 'position';
    }

    $('#n-kind').addEventListener('change', syncCapacityField);

    function openNodeDialog(o) {
        nodeCtx = o;
        var meta = S.chart.meta;
        var n = o.node || null;
        var isEdit = o.mode === 'edit';
        var isClub = n && n.kind === 'club';
        var isRoot = n && n.parent_id == null;
        var parent = o.parentId != null ? S.byId[o.parentId] : (n ? S.byId[n.parent_id] : null);

        $('#dn-title').textContent = isEdit ? 'Modifier « ' + n.title + ' »' : 'Nouveau poste, unité ou département';
        $('#n-title').value = n ? n.title : '';
        $('#n-subtitle').value = n ? n.subtitle || '' : (parent ? parent.title : '');
        $('#n-desc').value = n ? (n.description || '') : '';
        $('#n-vnote').value = n ? (n.validation_note || '') : '';
        $('#n-collapsed').checked = n ? !!n.is_collapsed : false;

        fillSelect($('#n-capacity'), mapOptions(meta.capacities), n ? n.capacity : 'single');
        fillSelect($('#n-access'), mapOptions(meta.access_levels), n ? (n.access_level || 'none') : 'none');
        refreshKindOptions(n && !isClub ? n.kind : 'position', parent ? parent.id : null, isClub);
        fillSelect($('#n-category'), mapOptions(meta.categories), n ? n.category : (parent ? parent.category : 'direction'));
        fillSelect($('#n-relation'), mapOptions(meta.relations), n ? n.relation_type : 'hierarchy');
        fillSelect($('#n-validation'), mapOptions(meta.validations), n ? n.validation_status : 'confirmed');
        fillSelect($('#n-layout'), mapOptions(meta.layouts), n ? n.children_layout : 'horizontal');
        fillSelect($('#n-gov'), meta.governorates.map(function (g) { return { value: g.id, label: g.name }; }), n ? n.governorate_id : '', '— Aucun —');
        $('#n-gov-wrap').hidden = !isClub;
        $('#n-structure').hidden = !S.canEdit;
        fillSelect($('#n-scope'), mapOptions(meta.scopes || {}), n ? n.scope : '', isClub ? '\u2014 Aucun \u2014' : '\u2014 H\u00e9rit\u00e9 du bloc parent \u2014');
        $('#n-club-fields').hidden = !isClub || !S.canEdit;
        if (isClub) {
            fillSelect($('#n-status'), mapOptions(meta.club_statuses || {}), n.status || 'active');
            fillSelect($('#n-inst-type'), mapOptions(meta.institution_types || {}), n.institution_type || '', '\u2014 Aucun \u2014');
            $('#n-email').value = n.email || '';
            $('#n-facebook').value = n.facebook_url || '';
            $('#n-founded').value = n.founded_on || '';
            $('#n-charter').checked = !!n.charter_signed;
            $('#n-inst-name').value = n.institution_name || '';
        }

        var blocked = {};
        if (n) { blocked[n.id] = true; descendantIds(n.id).forEach(function (id) { blocked[id] = true; }); }
        var parents = dfs().filter(function (item) { return !blocked[item.node.id] && item.node.relation_type !== 'attachment'; })
            .map(function (item) { return { value: item.node.id, label: new Array(item.depth + 1).join('· ') + item.node.title }; });

        if (isRoot) {
            fillSelect($('#n-parent'), [], '', '— Sommet de l\'organigramme —');
            $('#n-parent').disabled = true;
            $('#n-relation').disabled = true;
        } else {
            fillSelect($('#n-parent'), parents, n ? n.parent_id : (o.parentId != null ? o.parentId : ''));
            $('#n-parent').disabled = false;
            $('#n-relation').disabled = false;
        }

        clearError('#n-error');
        openDialog($('#dlg-node'));
        $('#n-title').focus();
    }

    function syncWithPlatform() {
        api('POST', '/synchroniser', {}).then(function (res) {
            var s = res.sync || {};
            var changed = (s.linked || 0) + (s.updated || 0) + (s.departments || 0);
            applyResponse(res, changed ? changed + ' bloc' + (changed > 1 ? 's' : '') + ' mis \u00e0 jour.' : 'L\u2019organigramme est d\u00e9j\u00e0 align\u00e9.');
        }).catch(function (err) { toast(formatError(err), 'error'); });
    }

    $('#n-parent').addEventListener('change', function () {
        var parent = S.byId[$('#n-parent').value];
        if (parent && nodeCtx && nodeCtx.mode === 'create') { $('#n-category').value = parent.category; }
        if (nodeCtx && !(nodeCtx.node && nodeCtx.node.kind === 'club')) { refreshKindOptions($('#n-kind').value, parent ? parent.id : null, false); }
    });

    $('#f-node').addEventListener('submit', function (e) {
        e.preventDefault();
        if (!nodeCtx) { return; }
        var n = nodeCtx.node;
        var isClub = n && n.kind === 'club';
        var isRoot = n && n.parent_id == null;
        var body = {
            title: $('#n-title').value.trim(),
            subtitle: $('#n-subtitle').value.trim() || null,
            description: $('#n-desc').value.trim() || null,
            category: $('#n-category').value,
            relation_type: isRoot ? 'hierarchy' : $('#n-relation').value,
            parent_id: isRoot ? null : Number($('#n-parent').value) || null,
            validation_status: $('#n-validation').value,
            validation_note: $('#n-vnote').value.trim() || null,
            children_layout: $('#n-layout').value,
            is_collapsed: $('#n-collapsed').checked ? 1 : 0,
            governorate_id: isClub ? (Number($('#n-gov').value) || null) : (n ? n.governorate_id : null),
            scope: $('#n-scope').value || null
        };
        if (isClub) {
            body.status = $('#n-status').value;
            body.email = $('#n-email').value.trim() || null;
            body.facebook_url = $('#n-facebook').value.trim() || null;
            body.founded_on = $('#n-founded').value || null;
            body.charter_signed = $('#n-charter').checked ? 1 : 0;
            body.institution_name = $('#n-inst-name').value.trim() || null;
            body.institution_type = $('#n-inst-type').value || null;
        }
        if (!isClub) {
            body.kind = $('#n-kind').value;
            body.capacity = body.kind === 'position' ? $('#n-capacity').value : 'single';
            body.access_level = body.kind === 'position' ? $('#n-access').value : 'none';
        }
        if (!body.title) { showError('#n-error', { message: 'L\'intitulé est obligatoire.' }); return; }

        var request = nodeCtx.mode === 'edit' ? api('PATCH', '/blocs/' + n.id, body) : api('POST', '/blocs', body);
        request.then(function (res) {
            if (nodeCtx.mode === 'edit') { delete S.collapse[n.id]; saveCollapse(); }
            $('#dlg-node').close();
            applyResponse(res, nodeCtx.mode === 'edit' ? 'Bloc mis à jour.' : 'Bloc créé.');
        }).catch(function (err) { showError('#n-error', err); });
    });

    /* ----- Club */

    function openClubDialog(type) {
        var meta = S.chart.meta;
        $('#c-type').value = type || 'regional';
        $('#c-name').value = '';
        fillSelect($('#c-gov'), meta.governorates.map(function (g) { return { value: g.id, label: g.name }; }), '', '— Choisir —');
        $('#c-template').checked = true;
        syncClubForm();
        clearError('#c-error');
        openDialog($('#dlg-club'));
        $('#c-name').focus();
    }

    function syncClubForm() {
        var regional = $('#c-type').value === 'regional';
        $('#c-gov-wrap').querySelector('label').textContent = regional ? 'Gouvernorat *' : 'Gouvernorat (facultatif)';
        $('#c-name').placeholder = regional ? 'We Are …' : ($('#c-type').value === 'university' ? 'We Are + nom de l\'établissement' : 'We Are + nom du lycée');
    }

    $('#c-type').addEventListener('change', syncClubForm);
    $('#c-gov').addEventListener('change', function () {
        var select = $('#c-gov');
        if ($('#c-type').value === 'regional' && select.value && !$('#c-name').value.trim()) {
            $('#c-name').value = 'We Are ' + select.options[select.selectedIndex].text;
        }
    });

    $('#f-club').addEventListener('submit', function (e) {
        e.preventDefault();
        var body = {
            name: $('#c-name').value.trim(),
            type: $('#c-type').value,
            governorate_id: Number($('#c-gov').value) || null,
            apply_template: $('#c-template').checked ? 1 : 0
        };
        if (!body.name) { showError('#c-error', { message: 'Le nom du club est obligatoire.' }); return; }
        api('POST', '/clubs', body).then(function (res) {
            $('#dlg-club').close();
            applyResponse(res, 'Club créé.');
            if (res.selected_id) { focusNode(res.selected_id); }
        }).catch(function (err) { showError('#c-error', err); });
    });

    /* ----- Personne */

    var personCtx = null;

    function setPreview(person, url) {
        var box = $('#p-preview');
        box.textContent = '';
        var src = url || (person && person.photo_url);
        if (src) { box.appendChild(h('img', { src: src, alt: '' })); }
        else { box.textContent = person ? initials(person.full_name) : '?'; }
    }

    function openPersonDialog(o) {
        personCtx = o;
        var p = o.person || null;
        $('#dp-title').textContent = p ? 'Modifier ' + p.full_name : 'Nouvelle personne';
        $('#p-name').value = p ? p.full_name : '';
        $('#p-job').value = p ? (p.job_title || '') : '';
        $('#p-phone').value = p ? (p.phone || '') : '';
        $('#p-email').value = p ? (p.email || '') : '';
        $('#p-notes').value = p ? (p.notes || '') : '';
        $('#p-visible').checked = p ? !!p.contact_visible : true;
        $('#p-photo').value = '';
        $('#p-remove').checked = false;
        $('#p-remove-wrap').hidden = !(p && p.photo_url) || !!(p && p.from_account);
        $('#p-delete').hidden = !p;
        $('#p-delete').textContent = p && p.from_account ? 'Retirer de l\'organigramme' : 'Supprimer la personne';

        // Personne issue d'un compte : identité et coordonnées se modifient dans le compte, pas ici.
        var fromAccount = !!(p && p.from_account);
        ['#p-name', '#p-phone', '#p-email', '#p-photo'].forEach(function (sel) { $(sel).disabled = fromAccount; });
        $('#p-account').hidden = !fromAccount;
        if (fromAccount) {
            var link = $('#p-account-link');
            link.hidden = !p.user_url;
            if (p.user_url) { link.href = p.user_url; }
        }
        setPreview(p, null);
        clearError('#p-error');
        openDialog($('#dlg-person'));
        $('#p-name').focus();
    }

    $('#p-photo').addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (file) { setPreview(null, URL.createObjectURL(file)); }
    });

    $('#f-person').addEventListener('submit', function (e) {
        e.preventDefault();
        var p = personCtx.person;
        var data = new FormData();
        data.append('full_name', $('#p-name').value.trim());
        data.append('job_title', $('#p-job').value.trim());
        data.append('phone', $('#p-phone').value.trim());
        data.append('email', $('#p-email').value.trim());
        data.append('notes', $('#p-notes').value.trim());
        data.append('contact_visible', $('#p-visible').checked ? '1' : '0');
        data.append('remove_photo', $('#p-remove').checked ? '1' : '0');
        var file = $('#p-photo').files[0];
        if (file) { data.append('photo', file); }
        if (!$('#p-name').value.trim()) { showError('#p-error', { message: 'Le nom est obligatoire.' }); return; }

        var request;
        if (p) {
            data.append('_method', 'PATCH');
            if (S.selectedId != null) { data.append('node_id', S.selectedId); }
            request = api('POST', '/personnes/' + p.id, data, true);
        } else {
            if (personCtx.assignTo != null) { data.append('assign_to_node', personCtx.assignTo); }
            request = api('POST', '/personnes', data, true);
        }

        request.then(function (res) {
            $('#dlg-person').close();
            applyResponse(res, p ? 'Personne mise à jour.' : 'Personne ajoutée.');
        }).catch(function (err) { showError('#p-error', err); });
    });

    $('#p-delete').addEventListener('click', function () {
        var p = personCtx && personCtx.person;
        if (!p) { return; }
        askConfirm({ title: p.from_account ? 'Retirer de l\'organigramme' : 'Supprimer la personne', body: p.from_account ? 'Retirer « ' + p.full_name + ' » de tous ses postes ? Son compte n\'est pas supprimé.' : 'Supprimer « ' + p.full_name + ' » et la retirer de tous ses postes ? Les postes deviendront vacants.', okLabel: p.from_account ? 'Retirer' : 'Supprimer' }).then(function (r) {
            if (!r.ok) { return; }
            api('DELETE', '/personnes/' + p.id).then(function (res) {
                $('#dlg-person').close();
                applyResponse(res, 'Personne supprimée.');
            }).catch(function (err) { showError('#p-error', err); });
        });
    });

    /* ----- Import */

    function openImport() {
        $('#f-import').reset();
        clearError('#i-error');
        openDialog($('#dlg-import'));
    }

    $('#f-import').addEventListener('submit', function (e) {
        e.preventDefault();
        var file = $('#i-file').files[0];
        if (!file) { showError('#i-error', { message: 'Choisissez un fichier JSON.' }); return; }
        if (!$('#i-confirm').checked) { showError('#i-error', { message: 'Confirmez le remplacement complet de l\'organigramme.' }); return; }
        var data = new FormData();
        data.append('file', file);
        data.append('confirm', '1');
        api('POST', '/import', data, true).then(function (res) {
            $('#dlg-import').close();
            S.dirty = {};
            S.collapse = {};
            saveCollapse();
            applyResponse(res, 'Import terminé : ' + (res.imported ? res.imported.nodes + ' blocs, ' + res.imported.people + ' personnes.' : 'organigramme remplacé.'));
            fitTop();
        }).catch(function (err) { showError('#i-error', err); });
    });

    /* ----- Historique */

    function describeLog(log) {
        var m = log.metadata || {};
        var text = m.title || m.name || '';
        if (m.changes && typeof m.changes === 'object') {
            var parts = Object.keys(m.changes).slice(0, 4).map(function (k) {
                var c = m.changes[k] || {};
                return (FIELD_LABELS[k] || k) + ' : ' + fmtVal(k, c.from) + ' → ' + fmtVal(k, c.to);
            });
            text += (text && parts.length ? ' — ' : '') + parts.join(' ; ');
        }
        if (m.blocks) { text = m.blocks + ' bloc(s)'; }
        return text;
    }

    function fmtVal(key, v) {
        if (v == null || v === '') { return '∅'; }
        if (key === 'parent_id' && S.byId[v]) { return S.byId[v].title; }
        if (key === 'is_collapsed') { return v ? 'oui' : 'non'; }
        var s = String(v);
        return s.length > 60 ? s.slice(0, 57) + '…' : s;
    }

    function openHistory(nodeId) {
        var list = $('#dh-list');
        list.textContent = '';
        list.appendChild(h('li', { class: 'oc-muted', text: 'Chargement…' }));
        $('#dh-title').textContent = nodeId ? 'Historique de « ' + S.byId[nodeId].title + ' »' : 'Historique des modifications';
        openDialog($('#dlg-history'));
        api('GET', '/historique' + (nodeId ? '?node_id=' + nodeId : '')).then(function (res) {
            list.textContent = '';
            if (!res.data.length) { list.appendChild(h('li', { class: 'oc-muted', text: 'Aucune modification enregistrée.' })); return; }
            res.data.forEach(function (log) {
                list.appendChild(h('li', {},
                    h('div', { class: 'oc-history-top' }, h('strong', { text: log.label }), h('time', { text: new Date(log.at).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }) })),
                    h('div', { class: 'oc-muted', text: log.actor + (describeLog(log) ? ' · ' + describeLog(log) : '') })
                ));
            });
        }).catch(function (err) { list.textContent = ''; list.appendChild(h('li', { class: 'oc-form-error', text: formatError(err) })); });
    }

    /* ------------------------------------------------------------------ Mutations */

    function applyResponse(res, message) {
        setChart(res.chart);
        if (res.selected_id != null) { S.selectedId = res.selected_id; }
        render();
        renderPanel();
        if (S.term) { runSearch($('#oc-search').value); }
        if (message) { toast(message, 'ok'); }
    }

    function assign(n, personId) {
        var ids = (n.person_ids || []).concat([personId]);
        api('PUT', '/blocs/' + n.id + '/affectations', { person_ids: ids }).then(function (res) { applyResponse(res, 'Personne affectée.'); }).catch(function (err) { toast(formatError(err), 'error'); });
    }

    function unassign(n, personId) {
        var ids = (n.person_ids || []).filter(function (id) { return id !== personId; });
        api('PUT', '/blocs/' + n.id + '/affectations', { person_ids: ids }).then(function (res) { applyResponse(res, 'Personne retirée du poste.'); }).catch(function (err) { toast(formatError(err), 'error'); });
    }

    function reorder(n, direction) {
        api('POST', '/blocs/' + n.id + '/ordre', { direction: direction }).then(function (res) { applyResponse(res, null); }).catch(function (err) { toast(formatError(err), 'error'); });
    }

    function applyTemplate(n) {
        api('POST', '/blocs/' + n.id + '/modele').then(function (res) { applyResponse(res, 'Équipe type créée.'); }).catch(function (err) { toast(formatError(err), 'error'); });
    }

    function deleteNode(n) {
        var all = descendantIds(n.id).length;
        askConfirm({
            title: 'Supprimer « ' + n.title + ' »',
            body: all ? 'Ce bloc contient ' + all + ' sous-bloc(s). La suppression est définitive (elle reste tracée dans l\'historique).' : 'Supprimer ce bloc ? Cette action est définitive (elle reste tracée dans l\'historique).',
            optLabel: all ? 'Supprimer aussi les ' + all + ' sous-blocs' : null,
            okLabel: 'Supprimer'
        }).then(function (r) {
            if (!r.ok) { return; }
            if (all && !r.opt) { toast('Cochez la case pour supprimer aussi les sous-blocs, ou déplacez-les d\'abord.', 'error'); return; }
            api('DELETE', '/blocs/' + n.id, all ? { cascade: true } : null).then(function (res) {
                S.selectedId = null;
                delete S.dirty[n.id];
                applyResponse(res, 'Bloc supprimé.');
            }).catch(function (err) { toast(formatError(err), 'error'); });
        });
    }

    function nodeBody(n, over) {
        return Object.assign({
            title: n.title, subtitle: n.subtitle, description: n.description, category: n.category,
            relation_type: n.relation_type, parent_id: n.parent_id, validation_status: n.validation_status,
            validation_note: n.validation_note, children_layout: n.children_layout, is_collapsed: n.is_collapsed ? 1 : 0,
            governorate_id: n.governorate_id, scope: n.scope
        }, n.kind === 'club' ? {} : { kind: n.kind }, over || {});
    }

    function reparentByDrag(id, targetId, gs) {
        var n = S.byId[id];
        var t = S.byId[targetId];
        // Le déplacement visuel est annulé : le rattachement repositionne le bloc automatiquement.
        if (gs.ox === n.dx && gs.oy === n.dy) { delete S.dirty[id]; } else { S.dirty[id] = { dx: gs.ox, dy: gs.oy }; }
        reposition();
        updateSaveState();

        askConfirm({
            title: 'Changer le rattachement',
            body: 'Rattacher « ' + n.title + ' » (et ses sous-blocs) sous « ' + t.title + ' » ?',
            okLabel: 'Rattacher',
            primary: true
        }).then(function (r) {
            if (!r.ok) { return; }
            api('PATCH', '/blocs/' + n.id, nodeBody(n, { parent_id: t.id })).then(function (res) {
                var needsReset = res.chart.nodes.some(function (x) { return x.id === n.id && (x.dx || x.dy); });
                var next = needsReset ? api('POST', '/disposition', { positions: [{ id: n.id, dx: 0, dy: 0 }] }) : Promise.resolve(res);
                return next;
            }).then(function (res) {
                delete S.dirty[n.id];
                S.collapse[t.id] = false;
                saveCollapse();
                applyResponse(res, 'Rattachement modifié.');
            }).catch(function (err) { toast(formatError(err), 'error'); });
        });
    }

    function dirtyCount() { return Object.keys(S.dirty).length; }

    function updateSaveState() {
        var count = dirtyCount();
        var save = $('[data-act="save"]');
        if (save) { save.disabled = count === 0; }
        var badge = $('#oc-dirty');
        badge.hidden = count === 0;
        badge.textContent = String(count);
    }

    function savePositions() {
        var positions = Object.keys(S.dirty).map(function (id) { return { id: Number(id), dx: S.dirty[id].dx, dy: S.dirty[id].dy }; });
        if (!positions.length) { return; }
        api('POST', '/disposition', { positions: positions }).then(function (res) {
            S.dirty = {};
            applyResponse(res, 'Positions enregistrées.');
        }).catch(function (err) { toast(formatError(err), 'error'); });
    }

    function setEditMode(on) {
        S.editMode = on && S.canEdit;
        app.setAttribute('data-edit-mode', S.editMode ? 'on' : 'off');
        var b = $('[data-act="toggle-edit"]');
        b.setAttribute('aria-pressed', S.editMode ? 'true' : 'false');
        b.textContent = S.editMode ? 'Terminer' : 'Modifier';
        var hint = $('#oc-hint');
        hint.hidden = !S.editMode;
        hint.textContent = !S.editMode ? '' : (window.matchMedia('(max-width: 900px)').matches
            ? 'Mode édition : glissez un bloc pour le déplacer, puis Enregistrer.'
            : 'Mode édition : glissez un bloc pour le déplacer (son sous-arbre le suit) · Maj + dépôt sur un autre bloc = changer de rattachement · double-clic = modifier · pensez à « Enregistrer ».');
    }

    /* ------------------------------------------------------------------ Exports */

    function exportContext() {
        var total = S.nodes.length;
        var shown = Object.keys(S.lay.pos).length;
        return {
            viewport: viewport, bounds: S.lay.bounds, title: TITLE,
            subtitle: 'Exporté le ' + new Date().toLocaleDateString('fr-FR', { dateStyle: 'long' }) + ' · ' + shown + ' bloc' + (shown > 1 ? 's' : '') + ' affiché' + (shown > 1 ? 's' : '') + ' sur ' + total,
            legend: Object.keys(S.chart.meta.categories).map(function (k) { return { label: S.chart.meta.categories[k], color: COLORS[k] }; }),
            lineLegend: lineLegend()
        };
    }

    function stamp() { return new Date().toISOString().slice(0, 10); }

    function runExport(kind) {
        var done = busy('Préparation de l\'export…');
        var win = null;
        if (kind === 'print') {
            win = window.open('', '_blank');
            if (win) { win.document.write('<p style="font:16px sans-serif;padding:24px">Préparation du PDF pour impression…</p>'); }
        }

        X.buildSvg(exportContext()).then(function (built) {
            if (kind === 'svg') { X.download(new Blob([X.serialize(built.svg)], { type: 'image/svg+xml' }), 'organigramme-' + stamp() + '.svg'); return null; }
            if (kind === 'png') { return X.toPng(built, 2).then(function (blob) { X.download(blob, 'organigramme-' + stamp() + '.png'); }); }

            var mode = kind === 'pdf-fit' ? 'fit' : 'pages';
            return X.toPdf(built, { mode: mode, title: TITLE, footerNote: new Date().toLocaleDateString('fr-FR') }).then(function (res) {
                if (kind === 'print') {
                    var url = URL.createObjectURL(res.blob);
                    if (win) { win.location.href = url; toast('PDF ouvert : lancez l\'impression depuis le lecteur (' + res.pages + ' page' + (res.pages > 1 ? 's' : '') + ' A3).', 'ok'); return; }
                }
                X.download(res.blob, 'organigramme-' + stamp() + '.pdf');
                toast('PDF généré (' + res.pages + ' page' + (res.pages > 1 ? 's' : '') + ' A3).', 'ok');
            });
        }).catch(function (err) {
            if (win) { win.close(); }
            toast(err && err.message ? err.message : 'Export impossible.', 'error');
        }).then(done, done);
    }

    /* ------------------------------------------------------------------ Barre d'outils, menus, tiroirs */

    function closeMenus() {
        $$('.oc-menu-list').forEach(function (m) { m.hidden = true; });
        $$('[data-menu]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    }

    function closeDrawers() { app.classList.remove('oc-open-side', 'oc-open-panel'); }

    document.addEventListener('click', function (e) {
        var menuBtn = e.target.closest('[data-menu]');
        if (menuBtn) {
            var list = document.getElementById(menuBtn.getAttribute('data-menu'));
            var open = list.hidden;
            closeMenus();
            list.hidden = !open;
            menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }
        if (!e.target.closest('.oc-menu-list')) { closeMenus(); }

        var actEl = e.target.closest('[data-act]');
        if (!actEl || actEl.disabled) { return; }
        closeMenus();
        act(actEl.getAttribute('data-act'));
    });

    function act(name) {
        switch (name) {
            case 'add-node': openNodeDialog({ mode: 'create', parentId: S.selectedId != null ? S.selectedId : rootNode().id }); break;
            case 'add-club': openClubDialog('regional'); break;
            case 'add-person': openPersonDialog({ assignTo: S.selectedId }); break;
            case 'sync': syncWithPlatform(); break;
            case 'toggle-edit': setEditMode(!S.editMode); break;
            case 'save': savePositions(); break;
            case 'export-png': runExport('png'); break;
            case 'export-svg': runExport('svg'); break;
            case 'export-pdf-fit': runExport('pdf-fit'); break;
            case 'export-pdf-pages': runExport('pdf-pages'); break;
            case 'print': runExport('print'); break;
            case 'export-csv': window.location.href = cfg.base + '/export.csv'; break;
            case 'export-json': window.location.href = cfg.base + '/export.json'; break;
            case 'import': openImport(); break;
            case 'reset-view': S.collapse = {}; saveCollapse(); render(); fitTop(); break;
            case 'expand-all': setAllCollapsed(false); break;
            case 'collapse-all': setAllCollapsed(true); break;
            case 'history': openHistory(null); break;
            case 'zoom-in': zoomCenter(1.2); break;
            case 'zoom-out': zoomCenter(1 / 1.2); break;
            case 'zoom-reset': S.view.k = 1; applyView(); centerOn(S.selectedId != null ? S.selectedId : rootNode().id, 1); break;
            case 'fit': fit(); break;
            case 'toggle-legend':
                var box = $('#oc-legend');
                box.classList.toggle('is-collapsed');
                $('.oc-legend-toggle').setAttribute('aria-expanded', box.classList.contains('is-collapsed') ? 'false' : 'true');
                break;
            case 'toggle-side': app.classList.toggle('oc-open-side'); app.classList.remove('oc-open-panel'); break;
            case 'toggle-panel': app.classList.toggle('oc-open-panel'); app.classList.remove('oc-open-side'); break;
            case 'close-panel': closeDrawers(); break;
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeMenus(); }
    });

    window.addEventListener('beforeunload', function (e) {
        if (dirtyCount()) { e.preventDefault(); e.returnValue = ''; }
    });

    /* ------------------------------------------------------------------ Démarrage */

    function boot() {
        $$('[data-needs="editor"]').forEach(function (el) { el.hidden = !S.canEdit; });

        if (window.matchMedia('(max-width: 900px)').matches || window.innerHeight < 860) {
            $('#oc-legend').classList.add('is-collapsed');
            $('.oc-legend-toggle').setAttribute('aria-expanded', 'false');
        }

        api('GET', '/donnees').then(function (chart) {
            setChart(chart);
            $$('[data-needs="editor"]').forEach(function (el) { el.hidden = !S.canEdit; });
            buildLegend();
            render();
            renderPanel();
            fitTop();
            $('#oc-loading').hidden = true;

            // Lien direct depuis l'administration : ?bloc=<id>
            var wanted = Number(new URLSearchParams(window.location.search).get('bloc'));
            if (wanted && S.byId[wanted]) { focusNode(wanted); }
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function () { ellipsisCache = {}; render(); });
            }
        }).catch(function (err) {
            $('#oc-loading').textContent = 'Impossible de charger l\'organigramme. ' + formatError(err);
            $('#oc-loading').classList.add('is-error');
        });
    }

    window.OrgChartApp = { state: S, render: render, focusNode: focusNode, fit: fit, select: select };

    boot();
})();
