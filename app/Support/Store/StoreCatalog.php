<?php

namespace App\Support\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class StoreCatalog
{
    /**
     * Slug unique pour un modèle ayant une colonne `slug`.
     *
     * @param class-string<Model> $model
     */
    public static function uniqueSlug(string $model, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'article';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
