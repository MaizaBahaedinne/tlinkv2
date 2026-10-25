@php
    $scopeHierarchy = $scopeHierarchy ?? [];
    $scopeDescriptions = $scopeDescriptions ?? [];
    $scopeLogos = $scopeLogos ?? [];
    $childrenByParent = [];
    $roots = [];

    foreach ($scopes as $slug => $label) {
        $parent = $scopeHierarchy[$slug] ?? null;

        if ($parent && isset($scopes[$parent])) {
            $childrenByParent[$parent][] = $slug;
        } else {
            $roots[] = $slug;
        }
    }

    usort($roots, fn ($a, $b) => strcmp((string) $scopes[$a], (string) $scopes[$b]));

    foreach ($childrenByParent as $parent => $children) {
        usort($children, fn ($a, $b) => strcmp((string) $scopes[$a], (string) $scopes[$b]));
        $childrenByParent[$parent] = $children;
    }
@endphp

<div class="site-card scope-tree-wrap mt-6 p-6">
    <ul class="scope-tree">
        @foreach($roots as $root)
            @include('site.partials.scope-tree-node', [
                'scopeSlug' => $root,
                'scopes' => $scopes,
                'scopeDescriptions' => $scopeDescriptions,
                'scopeLogos' => $scopeLogos,
                'childrenByParent' => $childrenByParent,
            ])
        @endforeach
    </ul>
</div>
