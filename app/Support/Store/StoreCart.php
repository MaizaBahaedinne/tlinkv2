<?php

namespace App\Support\Store;

use App\Models\StoreCartItem;
use App\Models\StorePack;
use App\Models\StoreVariant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Panier d'un membre : lignes (article ou pack), contrôle de stock et total. */
final class StoreCart
{
    public const MAX_QUANTITY = 50;

    public static function count(User $user): int
    {
        return (int) StoreCartItem::query()->where('user_id', $user->id)->sum('quantity');
    }

    /**
     * Lignes détaillées du panier.
     *
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float, ok: bool, needs: array<int, int>}
     */
    public static function summary(User $user): array
    {
        $items = StoreCartItem::query()->where('user_id', $user->id)->orderBy('id')->get();

        $variantIds = $items->pluck('variant_id')->merge($items->flatMap(fn (StoreCartItem $item) => array_values((array) $item->selection)))->filter()->unique()->values();
        $variants = StoreVariant::query()->with(['product', 'color', 'size'])->whereIn('id', $variantIds)->get()->keyBy('id');
        $packs = StorePack::query()->with('items.product')->whereIn('id', $items->pluck('pack_id')->filter())->get()->keyBy('id');

        $lines = [];
        $totalNeeds = [];

        foreach ($items as $item) {
            $line = $item->pack_id !== null
                ? self::packLine($item, $packs->get($item->pack_id), $variants)
                : self::productLine($item, $variants->get($item->variant_id));

            foreach ($line['needs'] as $variantId => $units) {
                $totalNeeds[$variantId] = ($totalNeeds[$variantId] ?? 0) + $units;
            }

            $lines[] = $line;
        }

        // Stock cumulé : deux lignes qui puisent dans la même variante ne doivent pas dépasser ce qui reste.
        foreach ($lines as &$line) {
            foreach ($line['needs'] as $variantId => $units) {
                $stock = $variants->get($variantId)?->stock ?? 0;

                if ($totalNeeds[$variantId] > $stock && ! in_array('Stock insuffisant', $line['issues'], true)) {
                    $line['issues'][] = 'Stock insuffisant';
                }
            }

            $line['ok'] = $line['issues'] === [];
        }
        unset($line);

        return [
            'lines' => $lines,
            'subtotal' => round(array_sum(array_column($lines, 'total')), 2),
            'ok' => $lines !== [] && ! in_array(false, array_column($lines, 'ok'), true),
            'needs' => $totalNeeds,
        ];
    }

    public static function addVariant(User $user, StoreVariant $variant, int $quantity): void
    {
        $variant->loadMissing('product');

        if (! $variant->is_active || ! $variant->product?->is_active) {
            throw ValidationException::withMessages(['variant_id' => 'Cet article n’est plus disponible.']);
        }

        $item = StoreCartItem::query()->where('user_id', $user->id)->where('variant_id', $variant->id)->whereNull('pack_id')->first();
        $total = min(self::MAX_QUANTITY, ($item?->quantity ?? 0) + $quantity);

        if ($total > $variant->stock) {
            throw ValidationException::withMessages(['quantity' => $variant->stock > 0 ? 'Stock insuffisant : il en reste '.$variant->stock.'.' : 'Cette variante est épuisée.']);
        }

        $item === null
            ? StoreCartItem::query()->create(['user_id' => $user->id, 'variant_id' => $variant->id, 'quantity' => $total])
            : $item->update(['quantity' => $total]);
    }

    /**
     * @param array<int|string, mixed> $choices variante choisie par ligne du pack (id de ligne => id de variante)
     */
    public static function addPack(User $user, StorePack $pack, int $quantity, array $choices): void
    {
        $pack->loadMissing('items.product', 'items.variant');

        if (! $pack->is_active || $pack->items->isEmpty()) {
            throw ValidationException::withMessages(['pack' => 'Ce pack n’est plus disponible.']);
        }

        $selection = [];

        foreach ($pack->items as $packItem) {
            $variantId = $packItem->variant_id ?? (int) ($choices[$packItem->id] ?? 0);
            $variant = StoreVariant::query()->where('product_id', $packItem->product_id)->where('is_active', true)->find($variantId);

            if ($variant === null || ! $packItem->product?->is_active) {
                throw ValidationException::withMessages(['choices' => 'Choisissez la couleur et la taille de chaque article du pack ('.$packItem->product?->name.').']);
            }

            $selection[(string) $packItem->id] = $variant->id;
        }

        ksort($selection);

        $item = StoreCartItem::query()->where('user_id', $user->id)->where('pack_id', $pack->id)->get()
            ->first(function (StoreCartItem $existing) use ($selection): bool {
                $stored = (array) $existing->selection;
                ksort($stored);

                return $stored == $selection;
            });

        $total = min(self::MAX_QUANTITY, ($item?->quantity ?? 0) + $quantity);

        if ($issue = self::stockIssue($pack, $selection, $total)) {
            throw ValidationException::withMessages(['quantity' => $issue]);
        }

        $item === null
            ? StoreCartItem::query()->create(['user_id' => $user->id, 'pack_id' => $pack->id, 'selection' => $selection, 'quantity' => $total])
            : $item->update(['quantity' => $total]);
    }

    public static function setQuantity(StoreCartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $item->delete();

            return;
        }

        $quantity = min($quantity, self::MAX_QUANTITY);

        $issue = $item->pack_id !== null
            ? self::stockIssue($item->pack()->with('items.product')->firstOrFail(), (array) $item->selection, $quantity)
            : self::variantIssue($item->variant, $quantity);

        if ($issue !== null) {
            throw ValidationException::withMessages(['quantity' => $issue]);
        }

        $item->update(['quantity' => $quantity]);
    }

    public static function clear(User $user): void
    {
        StoreCartItem::query()->where('user_id', $user->id)->delete();
    }

    private static function variantIssue(?StoreVariant $variant, int $quantity): ?string
    {
        if ($variant === null || ! $variant->is_active) {
            return 'Cet article n’est plus disponible.';
        }

        return $quantity > $variant->stock ? 'Stock insuffisant : il en reste '.$variant->stock.'.' : null;
    }

    /**
     * @param array<int|string, int> $selection
     */
    private static function stockIssue(StorePack $pack, array $selection, int $quantity): ?string
    {
        $needs = [];

        foreach ($pack->items as $packItem) {
            $variantId = (int) ($selection[$packItem->id] ?? 0);
            $needs[$variantId] = ($needs[$variantId] ?? 0) + $packItem->quantity * $quantity;
        }

        foreach (StoreVariant::query()->with('product')->whereIn('id', array_keys($needs))->get() as $variant) {
            if (! $variant->is_active) {
                return 'Un article du pack n’est plus disponible.';
            }

            if ($needs[$variant->id] > $variant->stock) {
                return 'Stock insuffisant pour '.$variant->product?->name.' : il en reste '.$variant->stock.'.';
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function productLine(StoreCartItem $item, ?StoreVariant $variant): array
    {
        $issues = [];
        $available = $variant !== null && $variant->is_active && $variant->product?->is_active;

        if (! $available) {
            $issues[] = 'Article indisponible';
        } elseif ($variant->stock < $item->quantity) {
            $issues[] = 'Stock insuffisant';
        }

        $unit = $variant?->unitPrice() ?? 0.0;

        return [
            'id' => $item->id,
            'kind' => 'product',
            'name' => $variant?->product?->name ?? 'Article supprimé',
            'detail' => $variant?->label(),
            'image' => $variant?->product?->imageUrl(),
            'url' => $variant?->product ? route('store.products.show', $variant->product->slug) : null,
            'unit_price' => $unit,
            'quantity' => $item->quantity,
            'total' => round($unit * $item->quantity, 2),
            'needs' => $available ? [$variant->id => $item->quantity] : [],
            'components' => [],
            'variant' => $variant,
            'pack' => null,
            'issues' => $issues,
            'ok' => $issues === [],
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, StoreVariant> $variants
     * @return array<string, mixed>
     */
    private static function packLine(StoreCartItem $item, ?StorePack $pack, $variants): array
    {
        $issues = [];
        $needs = [];
        $components = [];

        if ($pack === null || ! $pack->is_active) {
            $issues[] = 'Pack indisponible';
        } else {
            foreach ($pack->items as $packItem) {
                $variant = $variants->get((int) (((array) $item->selection)[$packItem->id] ?? 0));

                if ($variant === null || ! $variant->is_active || ! $variant->product?->is_active) {
                    $issues[] = 'Un article du pack n’est plus disponible';

                    continue;
                }

                $needs[$variant->id] = ($needs[$variant->id] ?? 0) + $packItem->quantity * $item->quantity;
                $components[] = ['variant' => $variant, 'quantity' => $packItem->quantity];
            }

            foreach ($needs as $variantId => $units) {
                if (($variants->get($variantId)?->stock ?? 0) < $units) {
                    $issues[] = 'Stock insuffisant';
                    break;
                }
            }
        }

        $unit = (float) ($pack?->price ?? 0);

        return [
            'id' => $item->id,
            'kind' => 'pack',
            'name' => $pack?->name ?? 'Pack supprimé',
            'detail' => collect($components)->map(fn (array $c): string => $c['quantity'].'× '.$c['variant']->product->name.' ('.$c['variant']->label().')')->implode(' · '),
            'image' => $pack?->imageUrl(),
            'url' => $pack ? route('store.packs.show', $pack->slug) : null,
            'unit_price' => $unit,
            'quantity' => $item->quantity,
            'total' => round($unit * $item->quantity, 2),
            'needs' => $needs,
            'components' => $components,
            'variant' => null,
            'pack' => $pack,
            'issues' => array_values(array_unique($issues)),
            'ok' => $issues === [],
        ];
    }
}
