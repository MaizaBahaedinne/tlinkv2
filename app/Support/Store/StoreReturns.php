<?php

namespace App\Support\Store;

use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreReturn;
use App\Models\StoreReturnItem;
use App\Models\StoreVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Retours : demande du membre (articles et quantités), décision, réception (remise en stock) puis clôture
 * (remboursement ou échange). Un pack se retourne en entier : ses articles suivent la ligne du pack.
 */
final class StoreReturns
{
    /**
     * Lignes retournables d'une commande, avec la quantité encore disponible au retour.
     *
     * @return array<int, array{item: StoreOrderItem, remaining: int}>
     */
    public static function returnable(StoreOrder $order): array
    {
        $pending = StoreReturnItem::query()
            ->whereHas('returnRequest', fn ($query) => $query->where('order_id', $order->id)->whereIn('status', ['requested', 'approved']))
            ->selectRaw('order_item_id, sum(quantity) as total')->groupBy('order_item_id')->pluck('total', 'order_item_id');

        $rows = [];

        foreach ($order->items()->where('kind', '!=', 'pack_item')->orderBy('id')->get() as $item) {
            $remaining = $item->quantity - $item->returned_quantity - (int) ($pending[$item->id] ?? 0);

            if ($remaining > 0) {
                $rows[] = ['item' => $item, 'remaining' => $remaining];
            }
        }

        return $rows;
    }

    /** Un retour se demande pour une commande livrée, dans le délai fixé par les réglages. */
    public static function canRequest(StoreOrder $order): bool
    {
        if ($order->status !== 'delivered' || $order->delivered_at === null) {
            return false;
        }

        if ($order->delivered_at->copy()->addDays(StoreSettings::returnDays())->endOfDay()->isPast()) {
            return false;
        }

        return self::returnable($order) !== [];
    }

    /**
     * @param array<int|string, mixed> $quantities id de ligne de commande => quantité à retourner
     */
    public static function request(User $user, StoreOrder $order, array $quantities, string $reason, ?string $details, string $resolution): StoreReturn
    {
        if ((int) $order->user_id !== (int) $user->id || ! self::canRequest($order)) {
            throw ValidationException::withMessages(['items' => 'Cette commande ne peut plus faire l’objet d’un retour.']);
        }

        $chosen = [];

        foreach (self::returnable($order) as $row) {
            $quantity = (int) ($quantities[$row['item']->id] ?? 0);

            if ($quantity > $row['remaining']) {
                throw ValidationException::withMessages(['items' => 'Quantité trop élevée pour '.$row['item']->name.' : '.$row['remaining'].' maximum.']);
            }

            if ($quantity > 0) {
                $chosen[$row['item']->id] = $quantity;
            }
        }

        if ($chosen === []) {
            throw ValidationException::withMessages(['items' => 'Indiquez au moins un article à retourner.']);
        }

        return DB::transaction(function () use ($user, $order, $chosen, $reason, $details, $resolution): StoreReturn {
            $return = StoreReturn::query()->create([
                'reference' => self::reference(),
                'order_id' => $order->id,
                'user_id' => $user->id,
                'status' => 'requested',
                'reason' => $reason,
                'details' => $details,
                'resolution' => $resolution,
            ]);

            foreach ($chosen as $itemId => $quantity) {
                StoreReturnItem::query()->create(['return_id' => $return->id, 'order_item_id' => $itemId, 'quantity' => $quantity]);
            }

            return $return;
        });
    }

    public static function approve(StoreReturn $return, ?string $note): bool
    {
        return self::decide($return, 'approved', $note, 'Retour accepté', 'Votre demande de retour '.$return->reference.' est acceptée. Renvoyez les articles à l’équipe.');
    }

    public static function reject(StoreReturn $return, ?string $note): bool
    {
        return self::decide($return, 'rejected', $note, 'Retour refusé', 'Votre demande de retour '.$return->reference.' est refusée.'.($note ? ' Motif : '.$note : ''));
    }

    /** Les articles sont revenus : les quantités retournées sont comptées et, au choix, remises en stock. */
    public static function receive(StoreReturn $return, bool $restock, ?User $actor): bool
    {
        if ($return->status !== 'approved') {
            return false;
        }

        DB::transaction(function () use ($return, $restock, $actor): void {
            foreach ($return->items()->with('orderItem')->get() as $line) {
                $item = $line->orderItem;
                $item->increment('returned_quantity', $line->quantity);

                // Un pack revient entier : chaque article du pack revient dans la même proportion.
                $targets = $item->kind === 'pack'
                    ? $item->children()->get()->map(fn (StoreOrderItem $child): array => [$child, intdiv($child->quantity, max(1, $item->quantity)) * $line->quantity])->all()
                    : [[$item, $line->quantity]];

                foreach ($targets as [$target, $quantity]) {
                    if ($target->isChild()) {
                        $target->increment('returned_quantity', $quantity);
                    }

                    if ($restock && $target->variant_id !== null && ($variant = StoreVariant::query()->find($target->variant_id)) !== null) {
                        StoreStock::adjust($variant, $quantity, 'return', $actor, 'Retour '.$return->reference, $return->order_id, $return->id);
                    }
                }
            }

            $return->update(['status' => 'received', 'received_at' => now(), 'restocked' => $restock]);
        });

        self::notifyMember($return, 'Retour reçu', 'Nous avons bien reçu les articles de votre retour '.$return->reference.'.');

        return true;
    }

    /** Clôture : le remboursement (plafonné à ce qui a été payé) est enregistré sur la commande. */
    public static function complete(StoreReturn $return, ?float $refundAmount, ?string $note): bool
    {
        if ($return->status !== 'received') {
            return false;
        }

        DB::transaction(function () use ($return, $refundAmount, $note): void {
            $order = StoreOrder::query()->whereKey($return->order_id)->lockForUpdate()->firstOrFail();
            $amount = 0.0;

            if ($return->resolution === 'refund' && in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
                $suggested = (float) $return->items()->with('orderItem')->get()->sum(fn (StoreReturnItem $line): float => $line->quantity * (float) $line->orderItem->unit_price);
                $remaining = (float) $order->total - (float) $order->refunded_amount;
                $amount = round(max(0, min($refundAmount ?? $suggested, $remaining)), 2);

                if ($amount > 0) {
                    $refunded = (float) $order->refunded_amount + $amount;
                    $order->update([
                        'refunded_amount' => $refunded,
                        'payment_status' => $refunded >= (float) $order->total ? 'refunded' : 'partially_refunded',
                    ]);
                }
            }

            $return->update([
                'status' => 'completed',
                'completed_at' => now(),
                'refund_amount' => $amount,
                'admin_note' => $note ?? $return->admin_note,
            ]);
        });

        self::notifyMember($return, 'Retour clôturé', $return->resolution === 'refund'
            ? 'Votre retour '.$return->reference.' est clôturé.'.((float) $return->refresh()->refund_amount > 0 ? ' Montant remboursé : '.StoreSettings::money((float) $return->refund_amount).'.' : '')
            : 'Votre retour '.$return->reference.' est clôturé : l’équipe vous contacte pour l’échange.');

        return true;
    }

    private static function decide(StoreReturn $return, string $status, ?string $note, string $title, string $body): bool
    {
        if ($return->status !== 'requested') {
            return false;
        }

        $return->update(['status' => $status, 'decided_at' => now(), 'admin_note' => $note]);
        self::notifyMember($return, $title, $body);

        return true;
    }

    private static function notifyMember(StoreReturn $return, string $title, string $body): void
    {
        if ($return->user_id === null) {
            return;
        }

        \App\Models\AppNotification::query()->create([
            'user_id' => $return->user_id,
            'type' => 'store.return',
            'title' => $title,
            'body' => $body,
            'data' => ['return_id' => $return->id, 'order_id' => $return->order_id],
        ]);
    }

    private static function reference(): string
    {
        do {
            $reference = 'TR-'.strtoupper(Str::random(8));
        } while (StoreReturn::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
