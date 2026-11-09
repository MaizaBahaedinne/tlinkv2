<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Bannière publicitaire (image ou GIF) affichée dans le fil T.Link, sous la deuxième publication.
 */
class Advert extends Model
{
    protected $fillable = ['title', 'image_path', 'link_url', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    /** Lien uniquement en http(s) (évite les schémas dangereux type javascript:). */
    public function safeUrl(): ?string
    {
        return $this->link_url && preg_match('#^https?://#i', $this->link_url) === 1 ? $this->link_url : null;
    }

    /** Bannière à afficher : une des publicités actives, tirée au hasard à chaque chargement du fil. */
    public static function forFeed(): ?array
    {
        $advert = static::query()->where('is_active', true)->inRandomOrder()->first();

        return $advert === null ? null : [
            'id' => $advert->id,
            'title' => $advert->title,
            'image_url' => $advert->imageUrl(),
            'url' => $advert->safeUrl(),
        ];
    }
}
