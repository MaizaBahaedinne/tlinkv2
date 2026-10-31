<?php

namespace App\Support\Store;

use App\Models\AppNotification;
use App\Models\StoreDeliveryMethod;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StorePaymentMethod;
use App\Models\StoreVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Commandes : création depuis le panier (avec décrément du stock), avancement, paiement et annulation. */
final class StoreOrders
{
    /**
     * @param array{delivery_method_id: int, payment_method_id: int, ship_name: string, ship_phone: string, governorate_id?: int|null, city?: string|null, address?: string|null, note?: string|null} $data
     */
    public static function place(User $user, array $data): StoreOrder
    {
        $delivery = StoreDeliveryMethod::query()->where('is_active', true)->find($data['delivery_method_id'] ?? 0);
        $payment = StorePaymentMethod::query()->where('is_active', true)->find($data['payment_method_id'] ?? 0);

        if ($delivery === null) {
            throw ValidationException::withMessages(['delivery_method_id' => 'Choisissez un mode de livraison disponible.']);
        }

        if ($payment === null || ! $payment->allowsDelivery($delivery->kind)) {
            throw ValidationException::withMessages(['payment_method_id' => 'Choisissez un mode de paiement disponible pour cette livraison.']);
        }

        if (! $delivery->isPickup() && (empty($data['governorate_id']) || empty($data['address']))) {
            throw ValidationException::withMessages(['address' => 'Indiquez le gouvernorat et l’adresse de livraison.']);
        }

        return DB::transaction(function () use ($user, $data, $delivery, $payment): StoreOrder {
            $cart = StoreCart::summary($user);

            if ($cart['lines'] === []) {
                throw ValidationException::withMessages(['cart' => 'Votre panier est vide.']);
            }

            if (! $cart['ok']) {
                throw ValidationException::withMessages(['cart' => 'Certains articles ne sont plus disponibles en quantité suffisante : ajustez votre panier.']);
            }

            $governorateId = $delivery->isPickup() ? null : (int) $data['governorate_id'];
            $fee = $delivery->feeFor($cart['subtotal'], $governorateId);

            $order = StoreOrder::query()->create([
                'reference' => self::reference(),
                'user_id' => $user->id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'delivery_method_id' => $delivery->id,
                'delivery_name' => $delivery->name,
                'delivery_kind' => $delivery->kind,
                'payment_method_id' => $payment->id,
                'payment_key' => $payment->key,
                'payment_name' => $payment->name,
                'ship_name' => $data['ship_name'],
                'ship_phone' => $data['ship_phone'],
                'ship_governorate_id' => $governorateId,
                'ship_city' => $delivery->isPickup() ? null : ($data['city'] ?? null),
                'ship_address' => $delivery->isPickup() ? null : ($data['address'] ?? null),
                'subtotal' => $cart['subtotal'],
                'delivery_fee' => $fee,
                'total' => round($cart['subtotal'] + $fee, 2),
                'note' => $data['note'] ?? null,
            ]);

            foreach ($cart['lines'] as $line) {
                $parent = StoreOrderItem::query()->create([
                    'order_id' => $order->id,
                    'kind' => $line['kind'],
                    'variant_id' => $line['variant']?->id,
                    'pack_id' => $line['pack']?->id,
                    'name' => $line['name'],
                    'variant_label' => $line['kind'] === 'product' ? $line['detail'] : null,
                    'sku' => $line['variant']?->sku,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['total'],
                ]);

                foreach ($line['components'] as $component) {
                    /** @var StoreVariant $variant */
                    $variant = $component['variant'];

                    StoreOrderItem::query()->create([
                        'order_id' => $order->id,
                        'parent_id' => $parent->id,
                        'kind' => 'pack_item',
                        'variant_id' => $variant->id,
                        'name' => $variant->product->name,
                        'variant_label' => $variant->label(),
                        'sku' => $variant->sku,
                        'unit_price' => 0,
                        'quantity' => $component['quantity'] * $line['quantity'],
                        'line_total' => 0,
                    ]);
                }
            }

            ksort($cart['needs']);

            foreach ($cart['needs'] as $variantId => $units) {
                StoreStock::adjust(StoreVariant::query()->findOrFail($variantId), -$units, 'sale', $user, 'Commande '.$order->reference, $order->id);
            }

            StoreCart::clear($user);

            self::notify($order, 'store.order_placed', 'Commande reçue', 'Votre commande '.$order->reference.' ('.$order->totalLabel().') est enregistrée. Nous vous informerons de son avancement.');

            return $order;
        });
    }

    /** Avance la commande vers un statut plus loin dans le parcours. */
    public static function advance(StoreOrder $order, string $status): bool
    {
        if (! in_array($status, $order->nextStatuses(), true)) {
            return false;
        }

        $changes = ['status' => $status];

        if ($status === 'shipped') {
            $changes['shipped_at'] = now();
        }

        if ($status === 'delivered') {
            $changes['delivered_at'] = now();
            $changes['shipped_at'] = $order->shipped_at ?? now();

            // Paiement à la livraison ou au retrait : l'argent est encaissé à la remise.
            if ($order->payment_status === 'unpaid' && in_array($order->payment_key, ['cod', 'cash_pickup'], true)) {
                $changes['payment_status'] = 'paid';
                $changes['paid_at'] = now();
            }
        }

        $order->update($changes);

        $messages = [
            'confirmed' => 'Votre commande '.$order->reference.' est confirmée.',
            'preparing' => 'Votre commande '.$order->reference.' est en cours de préparation.',
            'shipped' => $order->isPickup() ? 'Votre commande '.$order->reference.' est prête : vous pouvez la retirer.' : 'Votre commande '.$order->reference.' a été expédiée.',
            'delivered' => 'Votre commande '.$order->reference.' est livrée. Merci !',
        ];

        self::notify($order, 'store.order_'.$status, 'Commande '.mb_strtolower(StoreOrder::STATUSES[$status]), $messages[$status] ?? '');

        return true;
    }

    public static function markPaid(StoreOrder $order): bool
    {
        if ($order->payment_status !== 'unpaid' || $order->status === 'cancelled') {
            return false;
        }

        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);

        self::notify($order, 'store.order_paid', 'Paiement reçu', 'Le paiement de votre commande '.$order->reference.' est confirmé.');

        return true;
    }

    /** Annule la commande avant son expédition : le stock est remis en vente et un paiement reçu passe en « remboursé ». */
    public static function cancel(StoreOrder $order, ?User $actor = null): bool
    {
        if (! $order->canBeCancelled()) {
            return false;
        }

        DB::transaction(function () use ($order, $actor): void {
            $items = $order->items()->whereNotNull('variant_id')->where('kind', '!=', 'pack')->get();

            foreach ($items->sortBy('variant_id') as $item) {
                $variant = StoreVariant::query()->find($item->variant_id);

                if ($variant !== null) {
                    StoreStock::adjust($variant, $item->quantity, 'cancel', $actor, 'Annulation '.$order->reference, $order->id);
                }
            }

            $changes = ['status' => 'cancelled', 'cancelled_at' => now()];

            if (in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
                $changes['payment_status'] = 'refunded';
                $changes['refunded_amount'] = $order->total;
            }

            $order->update($changes);
        });

        self::notify($order, 'store.order_cancelled', 'Commande annulée', 'Votre commande '.$order->reference.' a été annulée.');

        return true;
    }

    public static function notify(StoreOrder $order, string $type, string $title, string $body): void
    {
        if ($order->user_id === null) {
            return;
        }

        AppNotification::query()->create([
            'user_id' => $order->user_id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => ['order_id' => $order->id],
        ]);
    }

    private static function reference(): string
    {
        do {
            $reference = 'TS-'.strtoupper(Str::random(8));
        } while (StoreOrder::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
