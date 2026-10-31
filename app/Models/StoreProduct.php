<?php

namespace App\Models;

use App\Support\Store\StoreSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class StoreProduct extends Model
{
    protected $fillable = ['category_id', 'type_id', 'name', 'slug', 'summary', 'description', 'material', 'composition', 'weight', 'fit', 'care', 'details', 'size_guide', 'image_path', 'price', 'is_active', 'is_featured'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean', 'is_featured' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(StoreType::class, 'type_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(StoreVariant::class, 'product_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function images(): HasMany
    {
        return $this->hasMany(StoreProductImage::class, 'product_id')->orderBy('sort_order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(StoreReview::class, 'product_id');
    }

    /**
     * Photos de la fiche : la photo principale d'abord, puis la galerie.
     *
     * @return \Illuminate\Support\Collection<int, array{url: string, color_id: int|null}>
     */
    public function gallery(): \Illuminate\Support\Collection
    {
        $photos = $this->images->map(fn (StoreProductImage $image): array => ['url' => $image->url(), 'color_id' => $image->color_id]);

        if ($cover = $this->imageUrl()) {
            $photos->prepend(['url' => $cover, 'color_id' => null]);
        }

        return $photos->values();
    }

    /** Moyenne et nombre des avis publiés (relation `reviews` chargée ou non). */
    public function ratingSummary(): array
    {
        $reviews = $this->reviews->where('status', StoreReview::PUBLISHED);
        $count = $reviews->count();

        return [
            'count' => $count,
            'average' => $count > 0 ? round((float) $reviews->avg('rating'), 1) : 0.0,
            'distribution' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $star): array => [$star => $reviews->where('rating', $star)->count()])->all(),
        ];
    }

    /**
     * Guide des tailles saisi en texte : une ligne par taille, colonnes séparées par « | » ; la première ligne porte les titres.
     *
     * @return array{head: array<int, string>, rows: array<int, array<int, string>>}|null
     */
    public function sizeGuideTable(): ?array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', (string) $this->size_guide))
            ->map(fn (string $line): array => array_map('trim', explode('|', $line)))
            ->filter(fn (array $cells): bool => implode('', $cells) !== '')
            ->values();

        if ($lines->count() < 2) {
            return null;
        }

        return ['head' => $lines->first(), 'rows' => $lines->slice(1)->values()->all()];
    }

    /** @return array<int, string> Une consigne d'entretien par ligne. */
    public function careLines(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->care))->map(fn (string $line): string => trim($line))->filter()->values()->all();
    }

    /** Stock vendable : variantes actives uniquement. */
    public function availableStock(): int
    {
        return (int) $this->variants->where('is_active', true)->sum('stock');
    }

    /** Prix le plus bas parmi les variantes actives (le prix de base à défaut). */
    public function lowestPrice(): float
    {
        $prices = $this->variants->where('is_active', true)->map(fn (StoreVariant $variant): float => $variant->unitPrice());

        return (float) ($prices->min() ?? $this->price);
    }

    public function hasVariedPrices(): bool
    {
        return $this->variants->where('is_active', true)->map(fn (StoreVariant $variant): float => $variant->unitPrice())->unique()->count() > 1;
    }

    public function priceLabel(): string
    {
        return ($this->hasVariedPrices() ? 'À partir de ' : '').StoreSettings::money($this->lowestPrice());
    }
}
