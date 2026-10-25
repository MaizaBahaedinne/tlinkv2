<li class="scope-tree-node">
    @php
        $description = $scopeDescriptions[$scopeSlug] ?? '';
        if ($description === '') {
            $fallback = __('site.scopes.'.$scopeSlug.'.text');
            $description = $fallback !== 'site.scopes.'.$scopeSlug.'.text' ? $fallback : '';
        }
    @endphp
    <div class="scope-tree-card">
        <p class="scope-tree-title">{{ $scopes[$scopeSlug] ?? $scopeSlug }}</p>
        @if($description !== '')
            <p class="scope-tree-desc">{{ $description }}</p>
        @endif
        <p class="scope-tree-sub">{{ $scopeSlug }}</p>
    </div>

    @if(!empty($childrenByParent[$scopeSlug]))
        <ul class="scope-tree-children">
            @foreach($childrenByParent[$scopeSlug] as $childSlug)
                @include('site.partials.scope-tree-node', [
                    'scopeSlug' => $childSlug,
                    'scopes' => $scopes,
                    'scopeDescriptions' => $scopeDescriptions,
                    'childrenByParent' => $childrenByParent,
                ])
            @endforeach
        </ul>
    @endif
</li>
