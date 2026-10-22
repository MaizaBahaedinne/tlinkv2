/*
 * Organigramme — exports côté navigateur : SVG, PNG, PDF multi-pages (JPEG + écriture PDF manuelle).
 * Aucune dépendance externe.
 */
(function (root) {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var XLINK = 'http://www.w3.org/1999/xlink';
    var FONT = "Manrope, 'Segoe UI', Helvetica, Arial, sans-serif";

    function el(tag, attrs, text) {
        var node = document.createElementNS(NS, tag);
        Object.keys(attrs || {}).forEach(function (k) { node.setAttribute(k, attrs[k]); });
        if (text != null) { node.textContent = text; }
        return node;
    }

    function blobToDataUrl(blob) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () { resolve(reader.result); };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    /** Remplace les photos par des data URI pour que l'image exportée soit autonome. */
    function inlineImages(svg) {
        var images = Array.prototype.slice.call(svg.querySelectorAll('image'));

        return Promise.all(images.map(function (img) {
            var url = img.getAttribute('href') || img.getAttributeNS(XLINK, 'href');

            if (!url || url.indexOf('data:') === 0) { return null; }

            return fetch(url, { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) { throw new Error('photo'); } return r.blob(); })
                .then(blobToDataUrl)
                .then(function (data) {
                    img.setAttribute('href', data);
                    img.setAttributeNS(XLINK, 'xlink:href', data);
                })
                .catch(function () { img.parentNode && img.parentNode.removeChild(img); });
        }));
    }

    /**
     * Construit un SVG autonome : titre, diagramme complet, légende.
     * @param {{viewport: Element, bounds: object, title: string, subtitle: string, legend: Array, lineLegend: Array}} o
     * @returns {Promise<{svg: SVGElement, w: number, h: number}>}
     */
    function buildSvg(o) {
        var margin = 40;
        var headerH = 76;
        var contentW = o.bounds.maxX - o.bounds.minX;
        var contentH = o.bounds.maxY - o.bounds.minY;

        var legendCols = 4;
        var legendItems = o.legend.length + o.lineLegend.length;
        var legendRows = Math.ceil(legendItems / legendCols);
        var legendH = 34 + legendRows * 24;
        var legendW = legendCols * 250;

        var w = Math.ceil(Math.max(contentW, legendW, 760) + margin * 2);
        var h = Math.ceil(headerH + contentH + legendH + margin * 2 + 24);

        var svg = el('svg', {
            xmlns: NS, 'xmlns:xlink': XLINK, version: '1.1',
            viewBox: '0 0 ' + w + ' ' + h, width: w, height: h
        });

        var defs = el('defs');
        var clip = el('clipPath', { id: 'oc-clip-c', clipPathUnits: 'objectBoundingBox' });
        clip.appendChild(el('circle', { cx: '.5', cy: '.5', r: '.5' }));
        defs.appendChild(clip);
        svg.appendChild(defs);

        svg.appendChild(el('rect', { x: 0, y: 0, width: w, height: h, fill: '#ffffff' }));
        svg.appendChild(el('rect', { x: 0, y: 0, width: w, height: 8, fill: '#7d0020' }));

        svg.appendChild(el('text', { x: margin, y: margin + 14, 'font-family': FONT, 'font-size': 26, 'font-weight': 800, fill: '#4a0a1c' }, o.title));
        svg.appendChild(el('text', { x: margin, y: margin + 38, 'font-family': FONT, 'font-size': 13, fill: '#806c71' }, o.subtitle));

        var offsetX = margin + (w - margin * 2 - contentW) / 2 - o.bounds.minX;
        var offsetY = margin + headerH - o.bounds.minY;
        var content = el('g', { transform: 'translate(' + offsetX + ' ' + offsetY + ')' });

        Array.prototype.slice.call(o.viewport.children).forEach(function (layer) {
            var clone = layer.cloneNode(true);
            Array.prototype.slice.call(clone.querySelectorAll('.oc-toggle-open, .oc-ui-only')).forEach(function (n) { n.parentNode.removeChild(n); });
            Array.prototype.slice.call(clone.querySelectorAll('[tabindex]')).forEach(function (n) { n.removeAttribute('tabindex'); });
            content.appendChild(clone);
        });
        svg.appendChild(content);

        // Légende
        var ly = margin + headerH + contentH + 36;
        svg.appendChild(el('text', { x: margin, y: ly, 'font-family': FONT, 'font-size': 13, 'font-weight': 800, fill: '#4a0a1c' }, 'Légende'));
        var col = 0;
        var row = 0;
        o.legend.forEach(function (item) {
            var x = margin + col * 250;
            var y = ly + 16 + row * 24;
            svg.appendChild(el('rect', { x: x, y: y, width: 16, height: 16, rx: 4, fill: '#fff', stroke: item.color, 'stroke-width': 2 }));
            svg.appendChild(el('rect', { x: x, y: y, width: 5, height: 16, rx: 2, fill: item.color }));
            svg.appendChild(el('text', { x: x + 24, y: y + 12, 'font-family': FONT, 'font-size': 12, fill: '#1f1217' }, item.label));
            col++;
            if (col === legendCols) { col = 0; row++; }
        });
        o.lineLegend.forEach(function (item) {
            var x = margin + col * 250;
            var y = ly + 16 + row * 24;
            var attrs = { x1: x, y1: y + 8, x2: x + 18, y2: y + 8, stroke: item.color, 'stroke-width': 2 };
            if (item.dash) { attrs['stroke-dasharray'] = item.dash; }
            svg.appendChild(el('line', attrs));
            svg.appendChild(el('text', { x: x + 26, y: y + 12, 'font-family': FONT, 'font-size': 12, fill: '#1f1217' }, item.label));
            col++;
            if (col === legendCols) { col = 0; row++; }
        });

        return inlineImages(svg).then(function () { return { svg: svg, w: w, h: h }; });
    }

    /** Sérialise le SVG en fixant sa taille d'affichage (pixels) sans toucher au viewBox. */
    function serialize(svg, pxW, pxH) {
        if (pxW) { svg.setAttribute('width', Math.round(pxW)); }
        if (pxH) { svg.setAttribute('height', Math.round(pxH)); }

        return new XMLSerializer().serializeToString(svg);
    }

    function loadImage(svgString) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = function () { reject(new Error('Impossible de rasteriser l\'organigramme.')); };
            img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgString);
        });
    }

    function canvasToBlob(canvas, type, quality) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) { blob ? resolve(blob) : reject(new Error('Export impossible : image trop grande.')); }, type, quality);
        });
    }

    /** Échelle maximale respectant les limites des navigateurs (côté et surface du canvas). */
    function safeScale(w, h, wanted) {
        var bySide = 16000 / Math.max(w, h);
        var byArea = Math.sqrt(64e6 / (w * h));

        return Math.max(0.1, Math.min(wanted, bySide, byArea));
    }

    function toPng(built, wantedScale) {
        var s = safeScale(built.w, built.h, wantedScale || 2);
        var pw = Math.round(built.w * s);
        var ph = Math.round(built.h * s);

        return loadImage(serialize(built.svg, pw, ph)).then(function (img) {
            var canvas = document.createElement('canvas');
            canvas.width = pw;
            canvas.height = ph;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, pw, ph);
            ctx.drawImage(img, 0, 0, pw, ph);

            return canvasToBlob(canvas, 'image/png');
        });
    }

    /* ------------------------------------------------------------------ PDF */

    var PAGE = { w: 1190.55, h: 841.89, margin: 28, footer: 18 }; // A3 paysage, en points
    var MAX_PAGES = 18;

    function latin1(text) {
        var out = new Uint8Array(text.length);
        for (var i = 0; i < text.length; i++) {
            var code = text.charCodeAt(i);
            out[i] = code < 256 ? code : 63;
        }
        return out;
    }

    function pdfText(text) {
        return String(text).replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)').replace(/[\r\n]+/g, ' ');
    }

    function num(n) { return (Math.round(n * 100) / 100).toString(); }

    /**
     * Assemble un PDF : une image JPEG (DCTDecode) par page + pied de page.
     * @param {Array<{w:number,h:number,x:number,y:number,dw:number,dh:number,jpeg:Uint8Array,iw:number,ih:number,footer:string}>} pages
     * @param {{title:string}} meta
     * @returns {Uint8Array}
     */
    function buildPdf(pages, meta) {
        var parts = [];
        var length = 0;
        var offsets = [];

        function push(chunk) {
            var bytes = typeof chunk === 'string' ? latin1(chunk) : chunk;
            parts.push(bytes);
            length += bytes.length;
        }

        function begin(id) { offsets[id] = length; push(id + ' 0 obj\n'); }

        push('%PDF-1.4\n%\u00e2\u00e3\u00cf\u00d3\n');

        var kids = pages.map(function (_, i) { return (5 + i * 3) + ' 0 R'; }).join(' ');

        begin(1); push('<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');
        begin(2); push('<< /Type /Pages /Count ' + pages.length + ' /Kids [' + kids + '] >>\nendobj\n');
        begin(3); push('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n');
        begin(4); push('<< /Title (' + pdfText(meta.title) + ') /Producer (T.Link) /Creator (T.Link Organigramme) >>\nendobj\n');

        pages.forEach(function (p, i) {
            var pageId = 5 + i * 3;
            var contentId = pageId + 1;
            var imageId = pageId + 2;

            begin(pageId);
            push('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + num(p.w) + ' ' + num(p.h) + '] /Resources << /XObject << /Im0 ' + imageId + ' 0 R >> /Font << /F1 3 0 R >> >> /Contents ' + contentId + ' 0 R >>\nendobj\n');

            var stream = 'q ' + num(p.dw) + ' 0 0 ' + num(p.dh) + ' ' + num(p.x) + ' ' + num(p.y) + ' cm /Im0 Do Q\n'
                + 'BT /F1 8 Tf 0.38 0.44 0.54 rg ' + num(PAGE.margin) + ' 12 Td (' + pdfText(p.footer) + ') Tj ET\n';
            var streamBytes = latin1(stream);

            begin(contentId);
            push('<< /Length ' + streamBytes.length + ' >>\nstream\n');
            push(streamBytes);
            push('\nendstream\nendobj\n');

            begin(imageId);
            push('<< /Type /XObject /Subtype /Image /Width ' + p.iw + ' /Height ' + p.ih + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + p.jpeg.length + ' >>\nstream\n');
            push(p.jpeg);
            push('\nendstream\nendobj\n');
        });

        var count = 5 + pages.length * 3;
        var xref = length;
        var table = 'xref\n0 ' + count + '\n0000000000 65535 f \n';

        for (var id = 1; id < count; id++) {
            table += ('0000000000' + offsets[id]).slice(-10) + ' 00000 n \n';
        }

        push(table);
        push('trailer\n<< /Size ' + count + ' /Root 1 0 R /Info 4 0 R >>\nstartxref\n' + xref + '\n%%EOF\n');

        var out = new Uint8Array(length);
        var cursor = 0;
        parts.forEach(function (part) { out.set(part, cursor); cursor += part.length; });

        return out;
    }

    /**
     * @param {{svg: SVGElement, w: number, h: number}} built
     * @param {{mode: 'fit'|'pages', title: string, footerNote: string}} options
     */
    function toPdf(built, options) {
        // Orientation A3 choisie selon la forme du diagramme (haut = portrait, large = paysage).
        var portrait = built.h > built.w * 1.1;
        var pageW = portrait ? PAGE.h : PAGE.w;
        var pageH = portrait ? PAGE.w : PAGE.h;
        var uw = pageW - PAGE.margin * 2;
        var uh = pageH - PAGE.margin * 2 - PAGE.footer;
        var s;
        var cols = 1;
        var rows = 1;

        if (options.mode === 'fit') {
            s = Math.min(uw / built.w, uh / built.h);
        } else {
            s = 0.8;
            for (;;) {
                cols = Math.ceil((built.w * s) / uw - 1e-6);
                rows = Math.ceil((built.h * s) / uh - 1e-6);
                if (cols * rows <= MAX_PAGES || s <= 0.25) { break; }
                s *= 0.92;
            }
        }

        var R = 2; // pixels par point
        var pxPerUnit = s * R;
        var imgW = Math.round(built.w * pxPerUnit);
        var imgH = Math.round(built.h * pxPerUnit);

        return loadImage(serialize(built.svg, imgW, imgH)).then(function (img) {
            var pages = [];
            var total = cols * rows;
            var chain = Promise.resolve();

            for (var r = 0; r < rows; r++) {
                for (var c = 0; c < cols; c++) {
                    (function (r, c) {
                        chain = chain.then(function () {
                            var tileW = Math.min(uw, built.w * s - c * uw);
                            var tileH = Math.min(uh, built.h * s - r * uh);
                            var cw = Math.max(1, Math.round(tileW * R));
                            var ch = Math.max(1, Math.round(tileH * R));
                            var canvas = document.createElement('canvas');
                            canvas.width = cw;
                            canvas.height = ch;
                            var ctx = canvas.getContext('2d');
                            ctx.fillStyle = '#ffffff';
                            ctx.fillRect(0, 0, cw, ch);
                            ctx.drawImage(img, -c * uw * R, -r * uh * R, imgW, imgH);

                            return canvasToBlob(canvas, 'image/jpeg', 0.92)
                                .then(function (blob) { return blob.arrayBuffer(); })
                                .then(function (buffer) {
                                    var index = r * cols + c + 1;
                                    pages.push({
                                        w: pageW, h: pageH,
                                        x: PAGE.margin, y: pageH - PAGE.margin - tileH,
                                        dw: tileW, dh: tileH,
                                        jpeg: new Uint8Array(buffer), iw: cw, ih: ch,
                                        footer: options.title + ' - ' + options.footerNote + ' - page ' + index + '/' + total
                                            + (total > 1 ? ' (ligne ' + (r + 1) + ', colonne ' + (c + 1) + ')' : '')
                                    });
                                });
                        });
                    })(r, c);
                }
            }

            return chain.then(function () {
                return new Blob([buildPdf(pages, { title: options.title })], { type: 'application/pdf' });
            });
        }).then(function (blob) { return { blob: blob, pages: cols * rows }; });
    }

    function download(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 30000);
    }

    root.OrgExport = {
        buildSvg: buildSvg,
        serialize: serialize,
        toPng: toPng,
        toPdf: toPdf,
        buildPdf: buildPdf,
        download: download,
        PAGE: PAGE
    };

    if (typeof module !== 'undefined' && module.exports) { module.exports = root.OrgExport; }
})(typeof window !== 'undefined' ? window : globalThis);
