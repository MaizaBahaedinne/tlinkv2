{{-- Sélecteur de membre avec recherche en base (autocomplétion). Variables : $url (JSON ?q=), $inputName (défaut user_id). --}}
@php($pickerId = 'picker-'.\Illuminate\Support\Str::random(6))
<div class="field span-2" data-member-picker data-search-url="{{ $url }}" style="position:relative">
    <label for="{{ $pickerId }}">Membre <span class="req">*</span></label>
    <input type="hidden" name="{{ $inputName ?? 'user_id' }}" data-picker-id>
    <input type="search" id="{{ $pickerId }}" autocomplete="off" placeholder="Rechercher par nom ou e-mail…" data-picker-input>
    <p class="hint" data-picker-chosen>Tapez au moins 2 lettres puis choisissez un membre dans la liste.</p>
    <ul role="listbox" hidden data-picker-results style="position:absolute;z-index:20;left:0;right:0;top:100%;margin:2px 0 0;padding:4px;list-style:none;background:#fff;border:1px solid #d9dee7;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);max-height:260px;overflow:auto"></ul>
</div>

@once
    @push('scripts')
    <script>
        document.querySelectorAll('[data-member-picker]').forEach(function (box) {
            var input = box.querySelector('[data-picker-input]');
            var list = box.querySelector('[data-picker-results]');
            var hidden = box.querySelector('[data-picker-id]');
            var chosen = box.querySelector('[data-picker-chosen]');
            var timer = null;

            function close() { list.hidden = true; list.innerHTML = ''; }

            function render(members) {
                list.innerHTML = '';
                if (!members.length) {
                    var empty = document.createElement('li');
                    empty.textContent = 'Aucun membre trouvé.';
                    empty.style.padding = '8px';
                    list.appendChild(empty);
                }
                members.forEach(function (member) {
                    var item = document.createElement('li');
                    item.setAttribute('role', 'option');
                    item.style.cssText = 'padding:8px;cursor:pointer;border-radius:8px';
                    item.textContent = member.name + ' — ' + member.email;
                    item.addEventListener('mouseenter', function () { item.style.background = '#f1f5f9'; });
                    item.addEventListener('mouseleave', function () { item.style.background = ''; });
                    item.addEventListener('click', function () {
                        hidden.value = member.id;
                        input.value = member.name;
                        chosen.textContent = 'Sélectionné : ' + member.name + ' (' + member.email + ')';
                        close();
                    });
                    list.appendChild(item);
                });
                list.hidden = false;
            }

            input.addEventListener('input', function () {
                hidden.value = '';
                clearTimeout(timer);
                var term = input.value.trim();
                if (term.length < 2) { close(); return; }
                timer = setTimeout(function () {
                    fetch(box.dataset.searchUrl + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (response) { return response.ok ? response.json() : { members: [] }; })
                        .then(function (data) { render(data.members || []); })
                        .catch(close);
                }, 250);
            });

            document.addEventListener('click', function (event) {
                if (event.target !== input && !list.contains(event.target)) { close(); }
            });
        });
    </script>
    @endpush
@endonce
