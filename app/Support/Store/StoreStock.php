<?php

namespace App\Support\Store;

use App\Models\StoreStockMovement;
use App\Models\StoreVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Stock : toute variation passe par ici pour laisser une trace dans le journal et ne jamais descendre sous zéro. */
final class StoreStock
{
    public static function adjust(
        StoreVariant $variant,
        int $delta,
        string $reason,
        ?User $user = null,
        ?string $note = null,
        ?int $orderId = null,
        ?int $returnId = null,
    ): StoreStockMovement {
        return DB::transaction(function () use ($variant, $delta, $reason, $user, $note, $orderId, $returnId): StoreStockMovement {
            $locked = StoreVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $balance = $locked->stock + $delta;

            if ($balance < 0) {
                $locked->loadMissing(['product', 'color', 'size']);

                throw ValidationException::withMessages(['stock' => 'Stock insuffisant pour '.$locked->product?->name.' ('.$locked->label().') : il en reste '.$locked->stock.'.']);
            }

            $locked->update(['stock' => $balance]);
            $variant->stock = $balance;

            return StoreStockMovement::query()->create([
                'variant_id' => $locked->id,
                'delta' => $delta,
                'balance' => $balance,
                'reason' => $reason,
                'order_id' => $orderId,
                'return_id' => $returnId,
                'user_id' => $user?->id,
                'note' => $note !== null ? Str::limit($note, 250, '') : null,
            ]);
        });
    }
}
