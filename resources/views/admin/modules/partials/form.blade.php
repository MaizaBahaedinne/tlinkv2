<div style="margin-top:10px;min-width:300px">
    <form method="post" action="{{ route('admin.modules.update', $module) }}" class="grid" style="gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
        @csrf
        @method('PUT')
        <div>
            <label class="text-sm" for="mn-{{ $module->id }}">Nom</label>
            <input id="mn-{{ $module->id }}" type="text" name="name" value="{{ $module->name }}" maxlength="120" required>
        </div>
        <div>
            <label class="text-sm" for="mo-{{ $module->id }}">Ordre</label>
            <input id="mo-{{ $module->id }}" type="number" name="sort_order" min="0" max="9999" value="{{ $module->sort_order }}">
        </div>
        @if(! $module->is_system)
            <div>
                <label class="text-sm" for="mp-{{ $module->id }}">Module parent</label>
                <select id="mp-{{ $module->id }}" name="parent_id">
                    <option value="">— Module principal —</option>
                    @foreach($modules->where('id', '!=', $module->id) as $parent)
                        <option value="{{ $parent->id }}" @selected($module->parent_id === $parent->id)>{{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div style="grid-column:1/-1">
            <label class="text-sm" for="md-{{ $module->id }}">Description</label>
            <input id="md-{{ $module->id }}" type="text" name="description" value="{{ $module->description }}" maxlength="500">
        </div>
        <div style="grid-column:1/-1;display:flex;flex-wrap:wrap;gap:14px;align-items:center">
            <span class="text-sm">Actions :</span>
            @foreach($actions as $code => $label)
                <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="actions[]" value="{{ $code }}" @checked(in_array($code, $module->supportedActions()))> <span class="text-sm">{{ $label }}</span></label>
            @endforeach
            <label style="display:flex;align-items:center;gap:6px;margin-left:auto"><input type="checkbox" name="is_active" value="1" @checked($module->is_active)> <span class="text-sm">Actif</span></label>
        </div>
        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px">
            <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
        </div>
    </form>
    @unless($module->is_system)
        <form method="post" action="{{ route('admin.modules.destroy', $module) }}" onsubmit="return confirm('Supprimer ce module ?')" style="margin-top:8px">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm"><x-admin.icon name="trash" :size="15" /> Supprimer</button>
        </form>
    @endunless
</div>
