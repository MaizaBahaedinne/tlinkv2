// T.Link Messenger : liste des conversations, fil de messages et envoi, mis à jour en quasi temps réel
// par interrogation adaptative (plus fréquente quand l'onglet est visible et actif). Aucun HTML n'est
// injecté depuis le serveur : tout le contenu passe par textContent.

(function () {
    var root = document.getElementById('messenger');
    if (!root) { return; }

    var cfg = root.dataset;
    var activeId = cfg.active ? parseInt(cfg.active, 10) : null;
    var me = parseInt(cfg.me, 10);
    var timeFmt = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' });
    var dayFmt = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
    var shortDayFmt = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit' });
    var baseTitle = document.title;

    function $(id) { return document.getElementById(id); }

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

    // Pastille « Profil vérifié » à placer après un nom.
    function verifiedBadge() {
        var node = el('i', 'fa-solid fa-circle-check shrink-0 text-sky-500');
        node.title = 'Profil vérifié';
        node.setAttribute('role', 'img');
        node.setAttribute('aria-label', 'Profil vérifié');
        return node;
    }

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

    function request(url, options) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.csrf }, options.headers || {});
        return fetch(url, options).then(function (response) {
            if (response.status === 401 || response.status === 419) { window.location.reload(); }
            return response.json().catch(function () { return {}; }).then(function (data) { return { ok: response.ok, status: response.status, data: data }; });
        });
    }

    function submitForm(action, method, fields) {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = action;
        form.hidden = true;
        var all = Object.assign({ _token: cfg.csrf }, method && method !== 'POST' ? { _method: method } : {}, fields || {});
        Object.keys(all).forEach(function (name) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = all[name];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    // ───────────── Activité de l'utilisateur et cadence d'interrogation ─────────────

    var lastActivity = Date.now();
    ['keydown', 'pointerdown', 'touchstart', 'scroll'].forEach(function (name) {
        window.addEventListener(name, function () { lastActivity = Date.now(); }, { passive: true });
    });

    function interval(fast, idle, hidden) {
        if (document.hidden) { return hidden; }
        return Date.now() - lastActivity < 60000 ? fast : idle;
    }

    // ───────────── Liste des conversations ─────────────

    var listBox = $('mg-list');
    var listEmpty = $('mg-list-empty');
    var listTimer = null;
    var listFails = 0;

    function relativeTime(iso) {
        if (!iso) { return ''; }
        var date = new Date(iso);
        var now = new Date();
        if (date.toDateString() === now.toDateString()) { return timeFmt.format(date); }
        return shortDayFmt.format(date);
    }

    function setBadge(count) {
        if (typeof window.tlinkMessengerBadge === 'function') { window.tlinkMessengerBadge(count); }
        document.title = (count > 0 ? '(' + count + ') ' : '') + baseTitle;
    }

    function renderList(conversations) {
        listBox.textContent = '';
        listEmpty.hidden = conversations.length > 0;

        conversations.forEach(function (conversation) {
            var item = el('li');
            var link = el('a', 'flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50 ' + (conversation.id === activeId ? 'bg-rose-50/60' : ''));
            link.href = withId(cfg.urlShow, conversation.id);
            if (conversation.id === activeId) { link.setAttribute('aria-current', 'page'); }

            link.appendChild(avatar(conversation.avatar, conversation.title, 'h-11 w-11', conversation.type === 'group'));

            var body = el('span', 'min-w-0 flex-1');
            var top = el('span', 'flex items-baseline justify-between gap-2');
            var nameLine = el('span', 'flex min-w-0 items-center gap-1');
            nameLine.appendChild(el('span', 'truncate text-sm ' + (conversation.unread > 0 ? 'font-bold text-slate-900' : 'font-semibold text-slate-800'), conversation.title));
            if (conversation.verified) { nameLine.appendChild(verifiedBadge()); }
            top.appendChild(nameLine);
            top.appendChild(el('span', 'shrink-0 text-[11px] text-slate-400', relativeTime(conversation.last_at)));
            body.appendChild(top);
            body.appendChild(el('span', 'block truncate text-xs ' + (conversation.unread > 0 ? 'font-semibold text-slate-700' : 'text-slate-500'), conversation.preview));
            link.appendChild(body);

            if (conversation.unread > 0) {
                link.appendChild(el('span', 'inline-flex min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-tlink-blue px-1.5 text-[11px] font-bold leading-5 text-white', conversation.unread > 99 ? '99+' : String(conversation.unread)));
            }

            item.appendChild(link);
            listBox.appendChild(item);
        });
    }

    function loadList() {
        clearTimeout(listTimer);
        request(cfg.urlList).then(function (result) {
            if (result.ok) {
                listFails = 0;
                renderList(result.data.conversations || []);
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

    loadList();

    // ───────────── Nouvelle conversation ─────────────

    var dialog = $('mg-dialog');
    var search = $('mg-search');
    var results = $('mg-results');
    var chips = $('mg-chips');
    var groupForm = $('mg-group-form');
    var dialogError = $('mg-dialog-error');
    var selected = {};
    var searchTimer = null;
    var searchAbort = null;

    function mode() { return groupForm.querySelector('input[name="mode"]:checked').value; }

    function syncMode() {
        var group = mode() === 'group';
        $('mg-group-title').hidden = !group;
        $('mg-group-actions').hidden = !group;
        chips.hidden = !group;
    }

    function renderChips() {
        chips.textContent = '';
        groupForm.querySelectorAll('input[name="members[]"]').forEach(function (input) { input.remove(); });

        Object.keys(selected).forEach(function (id) {
            var chip = el('li', 'inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-tlink-blue');
            chip.appendChild(el('span', null, selected[id]));
            var remove = el('button', 'text-tlink-blue/70 hover:text-tlink-blue');
            remove.type = 'button';
            remove.setAttribute('aria-label', 'Retirer ' + selected[id]);
            remove.appendChild(icon('fa-solid fa-xmark'));
            remove.addEventListener('click', function () { delete selected[id]; renderChips(); });
            chip.appendChild(remove);
            chips.appendChild(chip);

            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'members[]';
            hidden.value = id;
            groupForm.appendChild(hidden);
        });
    }

    function renderResults(members) {
        results.textContent = '';
        results.hidden = false;

        if (!members.length) {
            results.appendChild(el('li', 'px-3 py-3 text-sm text-slate-500', 'Aucun membre trouvé.'));
            return;
        }

        members.forEach(function (member) {
            var row = el('li');
            var button = el('button', 'flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50');
            button.type = 'button';
            button.appendChild(avatar(member.avatar, member.name, 'h-9 w-9', false));
            button.appendChild(el('span', 'truncate font-semibold text-slate-800', member.name));
            if (member.verified) { button.appendChild(verifiedBadge()); }
            button.addEventListener('click', function () {
                if (mode() === 'direct') {
                    submitForm(withId(cfg.urlDirect, member.id), 'POST');
                } else {
                    selected[member.id] = member.name;
                    renderChips();
                    search.value = '';
                    results.hidden = true;
                    search.focus();
                }
            });
            row.appendChild(button);
            results.appendChild(row);
        });
    }

    function runSearch() {
        var term = search.value.trim();
        if (term.length < 2) { results.hidden = true; return; }

        if (searchAbort) { searchAbort.abort(); }
        searchAbort = new AbortController();

        request(cfg.urlMembers + '?q=' + encodeURIComponent(term), { signal: searchAbort.signal }).then(function (result) {
            if (result.ok && search.value.trim() === term) { renderResults(result.data.members || []); }
        }).catch(function () { /* recherche annulée */ });
    }

    $('mg-new').addEventListener('click', function () {
        selected = {};
        renderChips();
        search.value = '';
        results.hidden = true;
        dialogError.hidden = true;
        syncMode();
        dialog.showModal();
        search.focus();
    });
    $('mg-dialog-close').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (event) { if (event.target === dialog) { dialog.close(); } });
    search.addEventListener('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(runSearch, 220); });
    groupForm.querySelectorAll('input[name="mode"]').forEach(function (radio) { radio.addEventListener('change', syncMode); });
    groupForm.addEventListener('submit', function (event) {
        if (mode() !== 'group') { event.preventDefault(); return; }
        if (!$('mg-title').value.trim() || !Object.keys(selected).length) {
            event.preventDefault();
            dialogError.textContent = 'Donnez un nom au groupe et choisissez au moins un membre.';
            dialogError.hidden = false;
        }
    });

    // ───────────── Conversation ouverte ─────────────

    if (!activeId) { return; }

    var box = $('mg-messages');
    var input = $('mg-input');
    var composer = $('mg-composer');
    var sendButton = $('mg-send');
    var errorBox = $('mg-error');
    var jump = $('mg-jump');
    var typingBox = $('mg-typing');
    var fileInput = $('mg-file');

    var messages = [];
    var seen = {};
    var reads = {};
    var meta = null;
    var hasMore = false;
    var serverTime = null;
    var stick = true;
    var typingNames = [];
    var threadTimer = null;
    var threadFails = 0;
    var loadingOlder = false;
    var lastTypingSent = 0;
    var readSent = 0;

    function lastId() { return messages.length ? messages[messages.length - 1].id : 0; }

    function dayLabel(date) {
        var today = new Date();
        var yesterday = new Date(today.getTime() - 86400000);
        if (date.toDateString() === today.toDateString()) { return 'Aujourd’hui'; }
        if (date.toDateString() === yesterday.toDateString()) { return 'Hier'; }
        var label = dayFmt.format(date);
        return label.charAt(0).toUpperCase() + label.slice(1);
    }

    function receipt(message) {
        if (!message.mine || message.deleted || !meta) { return ''; }
        var ids = Object.keys(reads);
        if (!ids.length) { return 'Envoyé'; }
        var readers = ids.filter(function (id) { return reads[id] >= message.id; }).length;
        if (meta.type === 'direct') { return readers > 0 ? 'Vu' : 'Envoyé'; }
        return readers > 0 ? 'Vu par ' + readers : 'Envoyé';
    }

    function messageNode(message, previous) {
        var showAuthor = meta && meta.type === 'group' && !message.mine && (!previous || previous.user_id !== message.user_id || previous.deleted);
        var row = el('div', 'mb-1 flex items-end gap-2 ' + (message.mine ? 'justify-end' : 'justify-start'));
        row.dataset.id = message.id;

        if (!message.mine && meta && meta.type === 'group') {
            row.appendChild(showAuthor ? avatar(message.avatar, message.author, 'h-7 w-7', false) : el('span', 'w-7 shrink-0'));
        }

        var column = el('div', 'flex max-w-[80%] flex-col ' + (message.mine ? 'items-end' : 'items-start'));
        if (showAuthor) {
            var who = el('span', 'mb-0.5 inline-flex items-center gap-1 px-1 text-[11px] font-semibold text-slate-500', message.author);
            if (message.verified) { who.appendChild(verifiedBadge()); }
            column.appendChild(who);
        }

        var bubble = el('div', 'rounded-2xl px-3.5 py-2 text-sm leading-relaxed ' + (message.deleted
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
                var img = el('img', 'max-h-64 max-w-full rounded-xl object-cover');
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

        row.appendChild(column);
        return row;
    }

    function render(options) {
        options = options || {};
        var previousHeight = box.scrollHeight;
        var previousTop = box.scrollTop;
        box.textContent = '';

        if (hasMore) {
            var older = el('button', 'mx-auto mb-3 block rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-tlink-blue shadow-sm ring-1 ring-slate-200 hover:bg-slate-50', 'Messages précédents');
            older.type = 'button';
            older.addEventListener('click', loadOlder);
            box.appendChild(older);
        }

        if (!messages.length) {
            box.appendChild(el('p', 'py-10 text-center text-sm text-slate-400', 'Aucun message. Dites bonjour !'));
        }

        var day = null;
        messages.forEach(function (message, index) {
            var date = new Date(message.created_at);
            if (date.toDateString() !== day) {
                day = date.toDateString();
                box.appendChild(el('p', 'my-3 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-400', dayLabel(date)));
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

    function renderHeader() {
        if (!meta) { return; }
        $('mg-head-title').textContent = meta.title;
        if (meta.verified) {
            var headBadge = verifiedBadge();
            headBadge.classList.add('ml-1.5');
            $('mg-head-title').appendChild(headBadge);
        }

        var headAvatar = $('mg-head-avatar');
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

        $('mg-head-sub').textContent = typingNames.length
            ? typingNames.join(', ') + (typingNames.length > 1 ? ' écrivent…' : ' écrit…')
            : (meta.type === 'group' ? meta.members.length + ' membres : ' + meta.members.join(', ') : '');
        typingBox.textContent = typingNames.length ? typingNames.join(', ') + (typingNames.length > 1 ? ' écrivent…' : ' écrit…') : '';

        var restricted = !!meta.restriction;
        $('mg-restriction').hidden = !restricted;
        $('mg-restriction').textContent = meta.restriction || '';
        composer.hidden = restricted;

        var menu = $('mg-menu-items');
        menu.textContent = '';
        function menuItem(label, iconClass, onClick, danger) {
            var button = el('button', 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50 ' + (danger ? 'text-rose-700' : ''));
            button.type = 'button';
            button.appendChild(icon(iconClass + ' w-4 text-center'));
            button.appendChild(el('span', null, label));
            button.addEventListener('click', onClick);
            menu.appendChild(button);
        }

        if (meta.type === 'direct' && meta.other_id) {
            menuItem('Voir le profil', 'fa-regular fa-id-card', function () { window.location.href = withId(cfg.urlProfile, meta.other_id); });
            if (meta.blocked_by_me) {
                menuItem('Débloquer ce membre', 'fa-solid fa-lock-open', function () { submitForm(withId(cfg.urlBlock, meta.other_id), 'DELETE'); });
            } else {
                menuItem('Bloquer ce membre', 'fa-solid fa-ban', function () {
                    if (window.confirm('Bloquer ce membre ? Il ne pourra plus vous écrire.')) { submitForm(withId(cfg.urlBlock, meta.other_id), 'POST'); }
                }, true);
            }
        } else if (meta.type === 'group') {
            menuItem('Quitter le groupe', 'fa-solid fa-right-from-bracket', function () {
                if (window.confirm('Quitter ce groupe ?')) { submitForm(cfg.urlLeave, 'POST'); }
            }, true);
        }
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
        var id = lastId();
        if (!id || id <= readSent || document.hidden) { return; }
        readSent = id;
        request(cfg.urlRead, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ up_to: id }) })
            .then(function (result) { if (result.ok) { setBadge(result.data.unread || 0); refreshSoon(); } });
    }

    function apply(data, initial) {
        var changed = false;

        if (data.conversation) { meta = data.conversation; changed = true; }
        serverTime = data.server_time || serverTime;

        var added = addMessages(data.messages || []);
        if (added.length) { changed = true; }

        (data.deleted_ids || []).forEach(function (id) {
            messages.forEach(function (message) {
                if (message.id === id && !message.deleted) { message.deleted = true; message.body = null; message.image = null; changed = true; }
            });
        });

        var newReads = data.reads || {};
        if (JSON.stringify(newReads) !== JSON.stringify(reads)) { reads = newReads; changed = true; }

        var newTyping = data.typing || [];
        if (newTyping.join('|') !== typingNames.join('|')) { typingNames = newTyping; renderHeader(); }

        if (data.conversation) { renderHeader(); }

        if (changed) {
            var incoming = added.some(function (message) { return !message.mine; });
            render({ toBottom: initial || added.some(function (message) { return message.mine; }) });
            if (incoming) {
                lastActivity = Date.now();
                if (!stick) { jump.hidden = false; }
                markRead();
                refreshSoon();
            }
        }
        if (initial) { markRead(); }
    }

    function poll() {
        clearTimeout(threadTimer);
        var query = '?after=' + lastId() + (serverTime ? '&since=' + serverTime : '');

        request(cfg.urlMessages + query).then(function (result) {
            if (result.ok) {
                threadFails = 0;
                apply(result.data, false);
            } else if (result.status === 403 || result.status === 404) {
                window.location.href = cfg.urlList.replace(/conversations$/, '');
                return;
            } else {
                threadFails++;
            }
            schedule();
        }).catch(function () { threadFails++; schedule(); });
    }

    function schedule() {
        clearTimeout(threadTimer);
        var base = interval(2000, 5000, 20000);
        threadTimer = setTimeout(poll, Math.min(30000, base * Math.pow(2, Math.min(threadFails, 4))));
    }

    function loadOlder() {
        if (loadingOlder || !messages.length) { return; }
        loadingOlder = true;
        request(cfg.urlMessages + '?before=' + messages[0].id).then(function (result) {
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
        stick = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
        if (stick) { jump.hidden = true; }
    }, { passive: true });
    jump.addEventListener('click', function () { box.scrollTop = box.scrollHeight; jump.hidden = true; stick = true; });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { poll(); markRead(); loadList(); }
    });
    window.addEventListener('focus', markRead);

    // ───────────── Envoi ─────────────

    function showError(text) {
        errorBox.textContent = text || '';
        errorBox.hidden = !text;
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 144) + 'px';
    }

    function clearFile() {
        fileInput.value = '';
        $('mg-file-chip').hidden = true;
    }

    $('mg-attach').addEventListener('click', function () { fileInput.click(); });
    $('mg-file-clear').addEventListener('click', clearFile);
    fileInput.addEventListener('change', function () {
        var file = fileInput.files[0];
        if (!file) { return clearFile(); }
        if (file.size > 5 * 1024 * 1024) { clearFile(); return showError('Image trop lourde (5 Mo maximum).'); }
        showError('');
        $('mg-file-name').textContent = file.name;
        $('mg-file-chip').hidden = false;
    });

    input.addEventListener('input', function () {
        autoGrow();
        var now = Date.now();
        if (input.value.trim() !== '' && now - lastTypingSent > 3000) {
            lastTypingSent = now;
            request(cfg.urlTyping, { method: 'POST' }).catch(function () { /* indicateur facultatif */ });
        }
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            composer.requestSubmit();
        }
    });

    composer.addEventListener('submit', function (event) {
        event.preventDefault();
        var body = input.value.trim();
        var file = fileInput.files[0];
        if (!body && !file) { return; }

        var data = new FormData();
        if (body) { data.append('body', body); }
        if (file) { data.append('image', file); }

        sendButton.disabled = true;
        showError('');

        request(cfg.urlSend, { method: 'POST', body: data }).then(function (result) {
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

    // ───────────── Démarrage ─────────────

    request(cfg.urlMessages).then(function (result) {
        if (!result.ok) {
            window.location.href = cfg.urlList.replace(/conversations$/, '');
            return;
        }
        hasMore = !!result.data.has_more;
        apply(result.data, true);
        if (!messages.length) { render({ toBottom: true }); }
        schedule();
        input.focus({ preventScroll: true });
    });
})();
