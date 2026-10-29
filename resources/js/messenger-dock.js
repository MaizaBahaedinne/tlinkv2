// T.Link Messenger : dock de messagerie flottant (liste en bas à droite + fenêtres de discussion), disponible sur
// toutes les pages. Même API JSON que la page /messagerie ; temps réel par interrogation adaptative.
// Aucun HTML n'est injecté depuis le serveur : tout le contenu passe par textContent.

(function () {
    var root = document.getElementById('mg-dock');
    if (!root) { return; }

    var cfg = root.dataset;
    var me = parseInt(cfg.me, 10);
    var storeKey = 'tlink.dock.' + me;
    var timeFmt = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' });
    var shortDayFmt = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit' });
    var baseTitle = document.title;
    var desktop = window.matchMedia('(min-width: 768px)');

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined && text !== null) { node.textContent = text; }
        return node;
    }

    function icon(classes) {
        var node = document.createElement('i');
        node.className = classes;
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function withId(template, id) { return template.replace(/\/0(\/|$)/, '/' + id + '$1'); }

    function initials(name) {
        return (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) { return part.charAt(0).toUpperCase(); }).join('');
    }

    function avatar(url, name, sizeClass, group) {
        var wrap = el('span', 'grid shrink-0 place-items-center overflow-hidden rounded-full bg-rose-50 text-xs font-semibold text-tlink-blue ' + sizeClass);
        wrap.setAttribute('aria-hidden', 'true');
        if (url) {
            var img = el('img', 'h-full w-full object-cover');
            img.src = url;
            img.alt = '';
            img.loading = 'lazy';
            wrap.appendChild(img);
        } else if (group) {
            wrap.appendChild(icon('fa-solid fa-user-group'));
        } else {
            wrap.textContent = initials(name);
        }
        return wrap;
    }

    function iconButton(iconClass, label, onClick) {
        var button = el('button', 'grid h-8 w-8 shrink-0 place-items-center rounded-full text-slate-500 hover:bg-slate-100');
        button.type = 'button';
        button.title = label;
        button.setAttribute('aria-label', label);
        button.appendChild(icon(iconClass));
        button.addEventListener('click', function (event) { event.stopPropagation(); onClick(event); });
        return button;
    }

    function countBadge(count) {
        return el('span', 'inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-tlink-blue px-1.5 text-[11px] font-bold leading-5 text-white', count > 99 ? '99+' : String(count));
    }

    function request(url, options) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.csrf }, options.headers || {});
        return fetch(url, options).then(function (response) {
            if (response.status === 401 || response.status === 419) { window.location.reload(); }
            return response.json().catch(function () { return {}; }).then(function (data) { return { ok: response.ok, status: response.status, data: data }; });
        });
    }

    // ───────────── Activité de l'utilisateur et cadence d'interrogation ─────────────

    var lastActivity = Date.now();
    ['keydown', 'pointerdown', 'touchstart', 'scroll'].forEach(function (name) {
        window.addEventListener(name, function () { lastActivity = Date.now(); }, { passive: true });
    });

    function interval(fast, idle, hidden) {
        if (document.hidden) { return hidden; }
        if (!desktop.matches) { return Math.max(idle, 30000); }
        return Date.now() - lastActivity < 60000 ? fast : idle;
    }

    // ───────────── État persistant (liste ouverte, fenêtres ouvertes) ─────────────

    var listOpen = false;
    var wins = [];

    function maxWindows() {
        var width = window.innerWidth;
        return width >= 1500 ? 3 : (width >= 1150 ? 2 : 1);
    }

    function saveState() {
        try {
            localStorage.setItem(storeKey, JSON.stringify({ list: listOpen, wins: wins.map(function (win) { return { id: win.id, min: win.minimized }; }) }));
        } catch (error) { /* stockage indisponible */ }
    }

    function loadState() {
        try {
            var saved = JSON.parse(localStorage.getItem(storeKey));
            if (saved && Array.isArray(saved.wins)) { return saved; }
        } catch (error) { /* état ignoré */ }
        return { list: false, wins: [] };
    }

    // ───────────── Structure du dock ─────────────

    var winsBox = el('div', 'flex items-end gap-3');
    var panel = el('section', 'flex w-72 flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-soft');
    panel.setAttribute('aria-label', 'Messagerie');

    var bar = el('button', 'flex h-12 w-full items-center gap-2 px-3 text-left hover:bg-slate-50');
    bar.type = 'button';
    bar.setAttribute('aria-expanded', 'false');
    bar.appendChild(avatar(cfg.meAvatar, cfg.meName, 'h-8 w-8', false));
    bar.appendChild(el('span', 'min-w-0 flex-1 truncate font-display text-sm font-semibold text-slate-900', 'Messagerie'));
    var barBadge = el('span');
    bar.appendChild(barBadge);
    var chevron = icon('fa-solid fa-chevron-up text-xs text-slate-500');
    bar.appendChild(chevron);

    var body = el('div', 'flex h-[26rem] max-h-[calc(100dvh-6rem)] flex-col border-t border-slate-100');
    body.hidden = true;

    var searchWrap = el('div', 'relative border-b border-slate-100 p-2');
    var search = el('input', 'w-full rounded-xl border border-transparent bg-slate-100 py-1.5 pl-8 pr-2 text-sm outline-none focus:border-tlink-blue focus:bg-white');
    search.type = 'search';
    search.maxLength = 80;
    search.placeholder = 'Rechercher un membre ou une discussion…';
    search.setAttribute('aria-label', 'Rechercher un membre ou une discussion');
    var searchIcon = icon('fa-solid fa-magnifying-glass pointer-events-none absolute left-5 top-1/2 -translate-y-1/2 text-xs text-slate-400');
    searchWrap.appendChild(searchIcon);
    searchWrap.appendChild(search);

    var scroller = el('div', 'min-h-0 flex-1 overflow-y-auto');
    var listBox = el('ul', 'divide-y divide-slate-100');
    listBox.setAttribute('aria-label', 'Conversations');
    var listEmpty = el('p', 'p-5 text-center text-xs text-slate-500', 'Aucune conversation. Recherchez un membre pour lui écrire.');
    listEmpty.hidden = true;
    var membersTitle = el('p', 'border-y border-slate-100 bg-slate-50 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500', 'Membres');
    membersTitle.hidden = true;
    var membersBox = el('ul', 'divide-y divide-slate-100');
    scroller.appendChild(listBox);
    scroller.appendChild(listEmpty);
    scroller.appendChild(membersTitle);
    scroller.appendChild(membersBox);

    var footer = el('a', 'block border-t border-slate-100 px-3 py-2 text-center text-xs font-semibold text-tlink-blue hover:bg-slate-50', 'Ouvrir T.Link Messenger');
    footer.href = cfg.urlIndex;

    body.appendChild(searchWrap);
    body.appendChild(scroller);
    body.appendChild(footer);
    panel.appendChild(bar);
    panel.appendChild(body);
    root.appendChild(winsBox);
    root.appendChild(panel);

    function setListOpen(open, focusSearch) {
        listOpen = open;
        body.hidden = !open;
        bar.setAttribute('aria-expanded', open ? 'true' : 'false');
        chevron.className = (open ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-up') + ' text-xs text-slate-500';
        saveState();
        if (open) {
            loadList();
            if (focusSearch) { search.focus(); }
        }
    }

    bar.addEventListener('click', function () { setListOpen(!listOpen, true); });

    // L'icône de l'en-tête ouvre le dock sur ordinateur (le lien reste actif ailleurs et avec Ctrl/Cmd).
    var headerLink = document.getElementById('nav-messages');
    if (headerLink) {
        headerLink.addEventListener('click', function (event) {
            if (!desktop.matches || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
            event.preventDefault();
            setListOpen(!listOpen, true);
        });
    }

    // ───────────── Liste des conversations ─────────────

    var conversations = [];
    var unreadTotal = 0;
    var listTimer = null;
    var listFails = 0;

    function relativeTime(iso) {
        if (!iso) { return ''; }
        var date = new Date(iso);
        return date.toDateString() === new Date().toDateString() ? timeFmt.format(date) : shortDayFmt.format(date);
    }

    function setBadge(count) {
        unreadTotal = count;
        barBadge.textContent = '';
        if (count > 0) { barBadge.appendChild(countBadge(count)); }
        if (typeof window.tlinkMessengerBadge === 'function') { window.tlinkMessengerBadge(count); }
        document.title = (count > 0 ? '(' + count + ') ' : '') + baseTitle;
    }

    function renderList() {
        var term = search.value.trim().toLowerCase();
        var shown = conversations.filter(function (conversation) { return !term || conversation.title.toLowerCase().indexOf(term) !== -1; });

        listBox.textContent = '';
        listEmpty.hidden = shown.length > 0 || term !== '';

        shown.forEach(function (conversation) {
            var item = el('li');
            var button = el('button', 'flex w-full items-center gap-3 px-3 py-2.5 text-left transition hover:bg-slate-50');
            button.type = 'button';
            button.appendChild(avatar(conversation.avatar, conversation.title, 'h-10 w-10', conversation.type === 'group'));

            var text = el('span', 'min-w-0 flex-1');
            var top = el('span', 'flex items-baseline justify-between gap-2');
            top.appendChild(el('span', 'truncate text-sm ' + (conversation.unread > 0 ? 'font-bold text-slate-900' : 'font-semibold text-slate-800'), conversation.title));
            top.appendChild(el('span', 'shrink-0 text-[11px] text-slate-400', relativeTime(conversation.last_at)));
            text.appendChild(top);
            text.appendChild(el('span', 'block truncate text-xs ' + (conversation.unread > 0 ? 'font-semibold text-slate-700' : 'text-slate-500'), conversation.preview));
            button.appendChild(text);

            if (conversation.unread > 0) { button.appendChild(countBadge(conversation.unread)); }
            button.addEventListener('click', function () { openWindow(conversation.id, { focus: true }); });
            item.appendChild(button);
            listBox.appendChild(item);
        });

        wins.forEach(function (win) { win.setUnread(unreadFor(win.id)); });
    }

    function unreadFor(id) {
        for (var i = 0; i < conversations.length; i++) {
            if (conversations[i].id === id) { return conversations[i].unread; }
        }
        return 0;
    }

    function loadList() {
        clearTimeout(listTimer);
        request(cfg.urlList).then(function (result) {
            if (result.ok) {
                listFails = 0;
                conversations = result.data.conversations || [];
                renderList();
                setBadge(result.data.unread || 0);
            } else if (result.status === 404 || result.status === 403) {
                return;
            } else {
                listFails++;
            }
            listTimer = setTimeout(loadList, Math.min(60000, interval(6000, 15000, 40000) * Math.pow(2, Math.min(listFails, 3))));
        }).catch(function () {
            listFails++;
            listTimer = setTimeout(loadList, 30000);
        });
    }

    var refreshSoon = (function () {
        var timer = null;
        return function () { clearTimeout(timer); timer = setTimeout(loadList, 400); };
    })();

    // ───────────── Recherche de membres ─────────────

    var searchTimer = null;
    var searchAbort = null;

    function renderMembers(members) {
        membersBox.textContent = '';
        membersTitle.hidden = false;

        if (!members.length) {
            membersBox.appendChild(el('li', 'px-3 py-3 text-xs text-slate-500', 'Aucun membre trouvé.'));
            return;
        }

        members.forEach(function (member) {
            var item = el('li');
            var button = el('button', 'flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50');
            button.type = 'button';
            button.appendChild(avatar(member.avatar, member.name, 'h-9 w-9', false));
            button.appendChild(el('span', 'truncate font-semibold text-slate-800', member.name));
            button.addEventListener('click', function () {
                request(withId(cfg.urlDirect, member.id), { method: 'POST' }).then(function (result) {
                    if (result.ok && result.data.conversation) {
                        search.value = '';
                        membersBox.textContent = '';
                        membersTitle.hidden = true;
                        renderList();
                        openWindow(result.data.conversation, { focus: true });
                        refreshSoon();
                    } else {
                        membersBox.textContent = '';
                        membersBox.appendChild(el('li', 'px-3 py-3 text-xs font-semibold text-red-600', (result.data && result.data.message) || 'Conversation impossible.'));
                    }
                });
            });
            item.appendChild(button);
            membersBox.appendChild(item);
        });
    }

    search.addEventListener('input', function () {
        renderList();
        clearTimeout(searchTimer);
        var term = search.value.trim();
        if (term.length < 2) {
            membersBox.textContent = '';
            membersTitle.hidden = true;
            return;
        }
        searchTimer = setTimeout(function () {
            if (searchAbort) { searchAbort.abort(); }
            searchAbort = new AbortController();
            request(cfg.urlMembers + '?q=' + encodeURIComponent(term), { signal: searchAbort.signal }).then(function (result) {
                if (result.ok && search.value.trim() === term) { renderMembers(result.data.members || []); }
            }).catch(function () { /* recherche annulée */ });
        }, 250);
    });

    // ───────────── Fenêtres de discussion ─────────────

    function findWindow(id) {
        for (var i = 0; i < wins.length; i++) {
            if (wins[i].id === id) { return wins[i]; }
        }
        return null;
    }

    function openWindow(id, options) {
        options = options || {};
        var existing = findWindow(id);
        if (existing) {
            existing.expand();
            if (options.focus) { existing.focus(); }
            return existing;
        }

        while (wins.length >= maxWindows()) { wins[0].destroy(); }

        var win = createWindow(id, !!options.minimized);
        wins.push(win);
        winsBox.appendChild(win.node);
        saveState();
        if (options.focus) { win.focus(); }
        return win;
    }

    function createWindow(id, startMinimized) {
        var messages = [];
        var seen = {};
        var reads = {};
        var meta = null;
        var hasMore = false;
        var serverTime = null;
        var stick = true;
        var typingNames = [];
        var timer = null;
        var fails = 0;
        var loadingOlder = false;
        var lastTypingSent = 0;
        var readSent = 0;
        var unread = 0;
        var destroyed = false;

        var urls = {
            messages: withId(cfg.urlMessages, id),
            send: withId(cfg.urlSend, id),
            read: withId(cfg.urlRead, id),
            typing: withId(cfg.urlTyping, id),
            full: withId(cfg.urlShow, id)
        };

        var api = { id: id, minimized: startMinimized };

        // Structure
        var node = el('section', 'flex w-80 flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-soft');
        node.setAttribute('aria-label', 'Conversation');

        var head = el('div', 'flex h-12 shrink-0 cursor-pointer items-center gap-2 px-3 hover:bg-slate-50');
        var headAvatar = el('span', 'grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-full bg-rose-50 text-xs font-semibold text-tlink-blue');
        var headText = el('span', 'min-w-0 flex-1');
        var headTitle = el('span', 'block truncate text-sm font-semibold text-slate-900', 'Conversation');
        var headSub = el('span', 'block truncate text-[11px] text-slate-500');
        headSub.setAttribute('aria-live', 'polite');
        headText.appendChild(headTitle);
        headText.appendChild(headSub);
        var headBadge = el('span');
        var fullLink = el('a', 'grid h-8 w-8 shrink-0 place-items-center rounded-full text-slate-500 hover:bg-slate-100');
        fullLink.href = urls.full;
        fullLink.title = 'Ouvrir en plein écran';
        fullLink.setAttribute('aria-label', 'Ouvrir en plein écran');
        fullLink.appendChild(icon('fa-solid fa-up-right-and-down-left-from-center'));
        fullLink.addEventListener('click', function (event) { event.stopPropagation(); });
        var minButton = iconButton('fa-solid fa-minus', 'Réduire', function () { api.toggle(); });
        var closeButton = iconButton('fa-solid fa-xmark', 'Fermer', function () { api.destroy(); });
        [headAvatar, headText, headBadge, fullLink, minButton, closeButton].forEach(function (child) { head.appendChild(child); });
        head.addEventListener('click', function () { api.toggle(); });

        var panelBody = el('div', 'flex flex-col border-t border-slate-100');
        var box = el('div', 'h-80 overflow-y-auto bg-slate-50/60 px-3 py-3');
        box.setAttribute('role', 'log');
        box.setAttribute('aria-live', 'polite');
        var jump = el('button', 'mx-auto -mt-9 mb-2 block rounded-full bg-slate-900/90 px-3 py-1 text-[11px] font-semibold text-white shadow-soft', 'Nouveaux messages');
        jump.type = 'button';
        jump.hidden = true;
        var typingLine = el('div', 'h-4 px-3 text-[11px] italic text-slate-500');
        var restriction = el('p', 'border-t border-slate-100 bg-amber-50 px-3 py-2 text-center text-xs text-amber-900');
        var composer = el('form', 'border-t border-slate-100 p-2');
        composer.autocomplete = 'off';
        var errorLine = el('p', 'mb-1 text-[11px] font-semibold text-red-600');
        errorLine.setAttribute('role', 'alert');
        errorLine.hidden = true;
        var fileChip = el('p', 'mb-1 inline-flex items-center gap-2 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] text-slate-700');
        var fileName = el('span', 'truncate');
        var fileClear = el('button', 'text-slate-500 hover:text-slate-900');
        fileClear.type = 'button';
        fileClear.setAttribute('aria-label', 'Retirer l’image');
        fileClear.appendChild(icon('fa-solid fa-xmark'));
        fileChip.hidden = true;
        restriction.hidden = true;
        fileChip.appendChild(icon('fa-solid fa-image'));
        fileChip.appendChild(fileName);
        fileChip.appendChild(fileClear);
        var row = el('div', 'flex items-end gap-1.5');
        var fileInput = el('input', 'hidden');
        fileInput.type = 'file';
        fileInput.accept = 'image/png,image/jpeg,image/webp';
        var attach = el('button', 'grid h-9 w-9 shrink-0 place-items-center rounded-full text-slate-500 hover:bg-slate-100');
        attach.type = 'button';
        attach.setAttribute('aria-label', 'Joindre une image');
        attach.appendChild(icon('fa-regular fa-image'));
        var input = el('textarea', 'max-h-24 min-h-[2.25rem] flex-1 resize-none rounded-2xl border border-slate-200 bg-white px-3 py-1.5 text-sm outline-none focus:border-tlink-blue');
        input.rows = 1;
        input.maxLength = 4000;
        input.placeholder = 'Écrivez un message…';
        input.setAttribute('aria-label', 'Votre message');
        var sendButton = el('button', 'grid h-9 w-9 shrink-0 place-items-center rounded-full bg-tlink-blue text-white hover:opacity-90 disabled:opacity-50');
        sendButton.type = 'submit';
        sendButton.setAttribute('aria-label', 'Envoyer');
        sendButton.appendChild(icon('fa-solid fa-paper-plane text-xs'));
        [attach, input, sendButton, fileInput].forEach(function (child) { row.appendChild(child); });
        [errorLine, fileChip, row].forEach(function (child) { composer.appendChild(child); });
        [box, jump, typingLine, restriction, composer].forEach(function (child) { panelBody.appendChild(child); });
        node.appendChild(head);
        node.appendChild(panelBody);
        api.node = node;

        function lastId() { return messages.length ? messages[messages.length - 1].id : 0; }

        function showError(text) {
            errorLine.textContent = text || '';
            errorLine.hidden = !text;
        }

        function receipt(message) {
            if (!message.mine || message.deleted || !meta) { return ''; }
            var ids = Object.keys(reads);
            if (!ids.length) { return 'Envoyé'; }
            var readers = ids.filter(function (key) { return reads[key] >= message.id; }).length;
            if (meta.type === 'direct') { return readers > 0 ? 'Vu' : 'Envoyé'; }
            return readers > 0 ? 'Vu par ' + readers : 'Envoyé';
        }

        function messageNode(message, previous) {
            var group = meta && meta.type === 'group';
            var showAuthor = group && !message.mine && (!previous || previous.user_id !== message.user_id || previous.deleted);
            var line = el('div', 'mb-1 flex items-end gap-1.5 ' + (message.mine ? 'justify-end' : 'justify-start'));

            if (!message.mine && group) {
                line.appendChild(showAuthor ? avatar(message.avatar, message.author, 'h-6 w-6', false) : el('span', 'w-6 shrink-0'));
            }

            var column = el('div', 'flex max-w-[82%] flex-col ' + (message.mine ? 'items-end' : 'items-start'));
            if (showAuthor) { column.appendChild(el('span', 'mb-0.5 px-1 text-[10px] font-semibold text-slate-500', message.author)); }

            var bubble = el('div', 'rounded-2xl px-3 py-1.5 text-[13px] leading-relaxed ' + (message.deleted
                ? 'border border-dashed border-slate-300 italic text-slate-400'
                : (message.mine ? 'bg-tlink-blue text-white' : 'bg-white text-slate-800 shadow-sm ring-1 ring-slate-200')));

            if (message.deleted) {
                bubble.textContent = 'Message supprimé';
            } else {
                if (message.image) {
                    var link = el('a', 'mb-1 block');
                    link.href = message.image;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    var img = el('img', 'max-h-48 max-w-full rounded-xl object-cover');
                    img.src = message.image;
                    img.alt = message.image_name || 'Image jointe';
                    img.loading = 'lazy';
                    img.addEventListener('load', function () { if (stick) { box.scrollTop = box.scrollHeight; } });
                    link.appendChild(img);
                    bubble.appendChild(link);
                }
                if (message.body) { bubble.appendChild(el('p', 'whitespace-pre-wrap break-words', message.body)); }
            }
            column.appendChild(bubble);

            var foot = el('span', 'mt-0.5 flex items-center gap-2 px-1 text-[10px] text-slate-400');
            foot.appendChild(el('span', null, timeFmt.format(new Date(message.created_at))));
            var status = receipt(message);
            if (status) { foot.appendChild(el('span', status === 'Envoyé' ? '' : 'font-semibold text-tlink-blue', status)); }
            if (message.mine && !message.deleted) {
                var remove = el('button', 'hover:text-red-600');
                remove.type = 'button';
                remove.title = 'Supprimer ce message';
                remove.setAttribute('aria-label', 'Supprimer ce message');
                remove.appendChild(icon('fa-regular fa-trash-can'));
                remove.addEventListener('click', function () { removeMessage(message); });
                foot.appendChild(remove);
            }
            column.appendChild(foot);

            line.appendChild(column);
            return line;
        }

        function render(options) {
            options = options || {};
            var previousHeight = box.scrollHeight;
            var previousTop = box.scrollTop;
            box.textContent = '';

            if (hasMore) {
                var older = el('button', 'mx-auto mb-2 block rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-tlink-blue shadow-sm ring-1 ring-slate-200 hover:bg-slate-50', 'Messages précédents');
                older.type = 'button';
                older.addEventListener('click', loadOlder);
                box.appendChild(older);
            }

            if (!messages.length) {
                box.appendChild(el('p', 'py-8 text-center text-xs text-slate-400', 'Aucun message. Dites bonjour !'));
            }

            var day = null;
            messages.forEach(function (message, index) {
                var date = new Date(message.created_at);
                if (date.toDateString() !== day) {
                    day = date.toDateString();
                    var label = date.toDateString() === new Date().toDateString() ? 'Aujourd’hui' : shortDayFmt.format(date);
                    box.appendChild(el('p', 'my-2 text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400', label));
                }
                box.appendChild(messageNode(message, messages[index - 1]));
            });

            if (options.toBottom || stick) {
                box.scrollTop = box.scrollHeight;
            } else if (options.keepAnchor) {
                box.scrollTop = previousTop + (box.scrollHeight - previousHeight);
            } else {
                box.scrollTop = previousTop;
            }
        }

        function typingText() {
            return typingNames.length ? typingNames.join(', ') + (typingNames.length > 1 ? ' écrivent…' : ' écrit…') : '';
        }

        function renderHead() {
            if (!meta) { return; }
            headTitle.textContent = meta.title;
            headAvatar.textContent = '';
            if (meta.avatar) {
                var img = el('img', 'h-full w-full object-cover');
                img.src = meta.avatar;
                img.alt = '';
                headAvatar.appendChild(img);
            } else if (meta.type === 'group') {
                headAvatar.appendChild(icon('fa-solid fa-user-group'));
            } else {
                headAvatar.textContent = initials(meta.title);
            }
            headSub.textContent = typingText() || (meta.type === 'group' ? meta.members.length + ' membres' : '');
            typingLine.textContent = typingText();

            var restricted = !!meta.restriction;
            restriction.textContent = meta.restriction || '';
            restriction.hidden = !restricted;
            composer.hidden = restricted;
        }

        function renderUnread() {
            headBadge.textContent = '';
            if (api.minimized && unread > 0) { headBadge.appendChild(countBadge(unread)); }
        }

        function addMessages(incoming) {
            var added = [];
            incoming.forEach(function (message) {
                if (seen[message.id]) { return; }
                seen[message.id] = true;
                messages.push(message);
                added.push(message);
            });
            messages.sort(function (a, b) { return a.id - b.id; });
            return added;
        }

        function markRead() {
            var last = lastId();
            if (destroyed || api.minimized || !last || last <= readSent || document.hidden) { return; }
            readSent = last;
            request(urls.read, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ up_to: last }) })
                .then(function (result) { if (result.ok) { setBadge(result.data.unread || 0); refreshSoon(); } });
        }

        function apply(data, initial) {
            var changed = false;

            if (data.conversation) { meta = data.conversation; changed = true; }
            serverTime = data.server_time || serverTime;

            var added = addMessages(data.messages || []);
            if (added.length) { changed = true; }

            (data.deleted_ids || []).forEach(function (deletedId) {
                messages.forEach(function (message) {
                    if (message.id === deletedId && !message.deleted) { message.deleted = true; message.body = null; message.image = null; changed = true; }
                });
            });

            var newReads = data.reads || {};
            if (JSON.stringify(newReads) !== JSON.stringify(reads)) { reads = newReads; changed = true; }

            var newTyping = data.typing || [];
            if (newTyping.join('|') !== typingNames.join('|')) { typingNames = newTyping; renderHead(); }
            if (data.conversation) { renderHead(); }

            if (changed) {
                var incoming = added.some(function (message) { return !message.mine; });
                render({ toBottom: initial || added.some(function (message) { return message.mine; }) });
                if (incoming) {
                    if (!stick) { jump.hidden = false; }
                    markRead();
                    refreshSoon();
                }
            }
            if (initial) { markRead(); }
        }

        function poll() {
            clearTimeout(timer);
            if (destroyed) { return; }
            var query = '?after=' + lastId() + (serverTime ? '&since=' + serverTime : '');

            request(urls.messages + query).then(function (result) {
                if (destroyed) { return; }
                if (result.ok) {
                    fails = 0;
                    apply(result.data, false);
                } else if (result.status === 403 || result.status === 404) {
                    api.destroy();
                    return;
                } else {
                    fails++;
                }
                schedule();
            }).catch(function () { fails++; schedule(); });
        }

        function schedule() {
            clearTimeout(timer);
            if (destroyed) { return; }
            // Fenêtre réduite : on s'appuie sur la liste (compteur de non-lus) et on n'interroge que rarement.
            var base = api.minimized ? 30000 : interval(2000, 5000, 20000);
            timer = setTimeout(poll, Math.min(60000, base * Math.pow(2, Math.min(fails, 4))));
        }

        function loadOlder() {
            if (loadingOlder || !messages.length) { return; }
            loadingOlder = true;
            request(urls.messages + '?before=' + messages[0].id).then(function (result) {
                loadingOlder = false;
                if (!result.ok) { return; }
                hasMore = !!result.data.has_more;
                addMessages(result.data.messages || []);
                var wasStick = stick;
                stick = false;
                render({ keepAnchor: true });
                stick = wasStick;
            }).catch(function () { loadingOlder = false; });
        }

        function removeMessage(message) {
            if (!window.confirm('Supprimer ce message ?')) { return; }
            request(withId(cfg.urlDelete, message.id), { method: 'DELETE' }).then(function (result) {
                if (result.ok) {
                    message.deleted = true;
                    message.body = null;
                    message.image = null;
                    render();
                    refreshSoon();
                }
            });
        }

        box.addEventListener('scroll', function () {
            stick = box.scrollHeight - box.scrollTop - box.clientHeight < 60;
            if (stick) { jump.hidden = true; }
        }, { passive: true });
        jump.addEventListener('click', function () { box.scrollTop = box.scrollHeight; jump.hidden = true; stick = true; });

        // Envoi
        function autoGrow() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 96) + 'px';
        }

        function clearFile() {
            fileInput.value = '';
            fileChip.hidden = true;
        }

        attach.addEventListener('click', function () { fileInput.click(); });
        fileClear.addEventListener('click', clearFile);
        fileInput.addEventListener('change', function () {
            var file = fileInput.files[0];
            if (!file) { return clearFile(); }
            if (file.size > 5 * 1024 * 1024) { clearFile(); return showError('Image trop lourde (5 Mo maximum).'); }
            showError('');
            fileName.textContent = file.name;
            fileChip.hidden = false;
        });

        input.addEventListener('input', function () {
            autoGrow();
            var now = Date.now();
            if (input.value.trim() !== '' && now - lastTypingSent > 3000) {
                lastTypingSent = now;
                request(urls.typing, { method: 'POST' }).catch(function () { /* indicateur facultatif */ });
            }
        });
        input.addEventListener('focus', markRead);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                composer.requestSubmit();
            }
        });

        composer.addEventListener('submit', function (event) {
            event.preventDefault();
            var text = input.value.trim();
            var file = fileInput.files[0];
            if (!text && !file) { return; }

            var data = new FormData();
            if (text) { data.append('body', text); }
            if (file) { data.append('image', file); }

            sendButton.disabled = true;
            showError('');

            request(urls.send, { method: 'POST', body: data }).then(function (result) {
                sendButton.disabled = false;
                if (!result.ok) {
                    var errors = result.data && result.data.errors;
                    var first = errors ? (errors.body || errors.image || [])[0] : null;
                    showError(first || (result.data && result.data.message) || 'Envoi impossible, réessayez.');
                    return;
                }
                input.value = '';
                autoGrow();
                clearFile();
                addMessages([result.data.message]);
                stick = true;
                render({ toBottom: true });
                refreshSoon();
                poll();
                input.focus();
            }).catch(function () {
                sendButton.disabled = false;
                showError('Connexion indisponible, réessayez.');
            });
        });

        // Interface publique de la fenêtre
        function applyMinimized() {
            panelBody.hidden = api.minimized;
            minButton.title = api.minimized ? 'Agrandir' : 'Réduire';
            minButton.setAttribute('aria-label', minButton.title);
            renderUnread();
        }

        api.setUnread = function (count) {
            unread = count;
            renderUnread();
        };

        api.expand = function () {
            if (!api.minimized) { return; }
            api.minimized = false;
            applyMinimized();
            saveState();
            box.scrollTop = box.scrollHeight;
            poll();
            markRead();
        };

        api.toggle = function () {
            if (api.minimized) {
                api.expand();
                input.focus({ preventScroll: true });
            } else {
                api.minimized = true;
                applyMinimized();
                saveState();
                schedule();
            }
        };

        api.focus = function () { if (!api.minimized) { input.focus({ preventScroll: true }); } };

        api.destroy = function () {
            destroyed = true;
            clearTimeout(timer);
            node.remove();
            var index = wins.indexOf(api);
            if (index !== -1) { wins.splice(index, 1); }
            saveState();
        };

        document.addEventListener('visibilitychange', function () {
            if (destroyed) { return; }
            if (!document.hidden) { if (!api.minimized) { poll(); markRead(); } }
        });

        applyMinimized();
        renderHead();

        // Chargement initial
        request(urls.messages).then(function (result) {
            if (destroyed) { return; }
            if (!result.ok) { api.destroy(); return; }
            hasMore = !!result.data.has_more;
            apply(result.data, true);
            if (!messages.length) { render({ toBottom: true }); }
            api.setUnread(unreadFor(id));
            schedule();
        }).catch(function () { schedule(); });

        return api;
    }

    // ───────────── Démarrage ─────────────

    var saved = loadState();
    saved.wins.slice(-maxWindows()).forEach(function (entry) {
        if (entry && Number.isInteger(entry.id)) { openWindow(entry.id, { minimized: !!entry.min }); }
    });
    setListOpen(!!saved.list, false);
    if (!saved.list) { loadList(); }

    document.addEventListener('visibilitychange', function () { if (!document.hidden) { loadList(); } });
})();
