/*
 * Organigramme — moteur de disposition (pur, sans DOM).
 *
 * Entrée : liste de blocs { id, parent_id, relation_type, children_layout, sort_order }.
 * Sortie : positions absolues, liens et limites, prêts à être dessinés.
 *
 *  - disposition horizontale : arbre « tidy », enfants centrés sous leur parent ;
 *  - disposition verticale   : liste type arborescence de fichiers (indentée), héritée par les descendants ;
 *  - rattachements           : placés sur la même ligne que le bloc, à droite puis à gauche (liens pointillés) ;
 *  - décalages manuels (dx, dy) cumulés : un bloc déplacé emporte tout son sous-arbre.
 */
(function (root) {
    'use strict';

    var C = {
        W: 216, H: 68,          // bloc standard
        CW: 252, CH: 54,        // bloc compact (liste verticale)
        HGAP: 28,               // espace horizontal entre frères
        VGAP: 60,               // espace vertical parent → enfants
        VGAP_LIST: 10,          // espace vertical en mode liste
        INDENT: 34,             // retrait en mode liste
        AGAP: 16,               // espace entre rattachements empilés
        ROOT_GAP: 80
    };

    function sum(list, key) {
        var total = 0;
        for (var i = 0; i < list.length; i++) { total += list[i][key]; }
        return total;
    }

    function maxOf(list, key) {
        var max = 0;
        for (var i = 0; i < list.length; i++) { if (list[i][key] > max) { max = list[i][key]; } }
        return max;
    }

    /**
     * @param {Array} nodes
     * @param {{isCollapsed?: function, offsetOf?: function}} options
     */
    function compute(nodes, options) {
        options = options || {};
        var isCollapsed = options.isCollapsed || function () { return false; };
        var offsetOf = options.offsetOf || function (n) { return { dx: n.dx || 0, dy: n.dy || 0 }; };

        var byId = {};
        nodes.forEach(function (n) { byId[n.id] = n; });

        var sorted = nodes.slice().sort(function (a, b) {
            return (a.sort_order - b.sort_order) || (a.id - b.id);
        });

        var children = {}; // id -> { h: [], a: [] }
        var roots = [];

        sorted.forEach(function (n) {
            if (n.parent_id == null || !byId[n.parent_id]) { roots.push(n); return; }
            var entry = children[n.parent_id] || (children[n.parent_id] = { h: [], a: [] });
            (n.relation_type === 'attachment' ? entry.a : entry.h).push(n);
        });

        function measure(n, inList) {
            var w = inList ? C.CW : C.W;
            var h = inList ? C.CH : C.H;
            var vertical = n.children_layout === 'vertical' || inList;
            var entry = children[n.id] || { h: [], a: [] };
            var collapsed = isCollapsed(n);

            var hk = collapsed ? [] : entry.h.map(function (c) { return measure(c, vertical); });
            var ak = collapsed ? [] : entry.a.map(function (c) { return measure(c, false); });

            var right = [];
            var left = [];
            ak.forEach(function (a, i) { (i % 2 === 0 ? right : left).push(a); });

            var rightW = maxOf(right, 'w');
            var leftW = maxOf(left, 'w');
            var A = leftW ? leftW + C.HGAP : 0;
            var B = rightW ? rightW + C.HGAP : 0;

            function stackH(list) { return list.length ? sum(list, 'h') + (list.length - 1) * C.AGAP : 0; }
            var rowH = Math.max(h, stackH(left), stackH(right));

            var m = { n: n, w: 0, h: 0, nw: w, nh: h, vertical: vertical, inList: inList, bx: A, kids: [], atts: [] };

            // Rattachements : colonnes de part et d'autre du bloc.
            var yr = 0;
            right.forEach(function (a) { m.atts.push({ m: a, x: A + w + C.HGAP, y: yr }); yr += a.h + C.AGAP; });
            var yl = 0;
            left.forEach(function (a) { m.atts.push({ m: a, x: A - C.HGAP - a.w, y: yl }); yl += a.h + C.AGAP; });

            var totalW = A + w + B;
            var totalH = rowH;

            if (hk.length) {
                if (vertical) {
                    var y = rowH + C.VGAP_LIST;
                    var maxW = 0;
                    hk.forEach(function (c) {
                        m.kids.push({ m: c, x: A + C.INDENT, y: y });
                        y += c.h + C.VGAP_LIST;
                        if (c.w > maxW) { maxW = c.w; }
                    });
                    totalW = Math.max(totalW, A + C.INDENT + maxW);
                    totalH = y - C.VGAP_LIST;
                } else {
                    var K = sum(hk, 'w') + (hk.length - 1) * C.HGAP;
                    var kidsLeft = A + w / 2 - K / 2;
                    var x = kidsLeft;
                    hk.forEach(function (c) {
                        m.kids.push({ m: c, x: x, y: rowH + C.VGAP });
                        x += c.w + C.HGAP;
                    });
                    var shift = Math.min(0, kidsLeft);
                    totalW = Math.max(totalW, kidsLeft + K) - shift;
                    totalH = rowH + C.VGAP + maxOf(hk, 'h');
                    if (shift < 0) {
                        m.bx -= shift;
                        m.kids.forEach(function (k) { k.x -= shift; });
                        m.atts.forEach(function (k) { k.x -= shift; });
                    }
                }
            }

            m.w = totalW;
            m.h = totalH;

            return m;
        }

        var pos = {};
        var links = [];

        function place(m, ox, oy, accX, accY) {
            var n = m.n;
            var x = ox + m.bx;
            var y = oy;
            var own = offsetOf(n) || { dx: 0, dy: 0 };
            var ax = accX + (own.dx || 0);
            var ay = accY + (own.dy || 0);

            pos[n.id] = {
                id: n.id,
                x: x + ax, y: y + ay, w: m.nw, h: m.nh,
                lx: x, ly: y,
                vertical: m.vertical, inList: m.inList
            };

            m.kids.forEach(function (k) {
                links.push({ from: n.id, to: k.m.n.id, type: 'hierarchy' });
                place(k.m, ox + k.x, oy + k.y, ax, ay);
            });
            m.atts.forEach(function (k) {
                links.push({ from: n.id, to: k.m.n.id, type: 'attachment' });
                place(k.m, ox + k.x, oy + k.y, ax, ay);
            });
        }

        var cursor = 0;
        roots.forEach(function (r) {
            var m = measure(r, false);
            place(m, cursor, 0, 0, 0);
            cursor += m.w + C.ROOT_GAP;
        });

        var bounds = { minX: Infinity, minY: Infinity, maxX: -Infinity, maxY: -Infinity };
        Object.keys(pos).forEach(function (id) {
            var p = pos[id];
            bounds.minX = Math.min(bounds.minX, p.x);
            bounds.minY = Math.min(bounds.minY, p.y);
            bounds.maxX = Math.max(bounds.maxX, p.x + p.w);
            bounds.maxY = Math.max(bounds.maxY, p.y + p.h);
        });

        if (!isFinite(bounds.minX)) { bounds = { minX: 0, minY: 0, maxX: C.W, maxY: C.H }; }

        return { pos: pos, links: links, bounds: bounds, children: children };
    }

    /**
     * Tracé orthogonal d'un lien, à partir des positions finales (décalages inclus).
     * Retourne une chaîne `d` SVG.
     */
    function linkPath(link, p, c) {
        var x1, y1, x2, y2, mid;

        if (link.type === 'attachment') {
            var toRight = c.x + c.w / 2 >= p.x + p.w / 2;
            x1 = toRight ? p.x + p.w : p.x;
            y1 = p.y + p.h / 2;
            x2 = toRight ? c.x : c.x + c.w;
            y2 = c.y + c.h / 2;
            mid = (x1 + x2) / 2;

            return 'M' + x1 + ' ' + y1 + 'H' + mid + 'V' + y2 + 'H' + x2;
        }

        if (c.inList) {
            var tx = p.x + C.INDENT / 2 - 2;
            if (c.x > tx + 6 && c.y > p.y + p.h) {
                return 'M' + tx + ' ' + (p.y + p.h) + 'V' + (c.y + c.h / 2) + 'H' + c.x;
            }
        }

        x1 = p.x + p.w / 2;
        y1 = p.y + p.h;
        x2 = c.x + c.w / 2;
        y2 = c.y;
        mid = y2 - y1 >= C.VGAP / 2 + 8 ? y1 + C.VGAP / 2 : (y1 + y2) / 2;

        return 'M' + x1 + ' ' + y1 + 'V' + mid + 'H' + x2 + 'V' + y2;
    }

    root.OrgLayout = { C: C, compute: compute, linkPath: linkPath };

    if (typeof module !== 'undefined' && module.exports) { module.exports = root.OrgLayout; }
})(typeof window !== 'undefined' ? window : globalThis);
