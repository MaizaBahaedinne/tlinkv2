<?php

namespace App\Support\Academy;

use App\Models\AcademyCourse;
use App\Models\AcademyOrder;
use App\Models\AcademyPlan;
use App\Models\AcademySubscription;
use App\Models\AppNotification;
use App\Models\User;
use App\Support\Academy\Payments\PaymentGateways;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Commandes Academy : création, validation du paiement (accès accordé), annulation, remboursement. */
final class AcademyOrders
{
    /**
     * Commande en attente pour un cours ou un abonnement (celle déjà ouverte est réutilisée).
     */
    public static function open(User $user, AcademyCourse|AcademyPlan $item): AcademyOrder
    {
        $isCourse = $item instanceof AcademyCourse;
        $column = $isCourse ? 'course_id' : 'plan_id';

        $existing = AcademyOrder::query()->where('user_id', $user->id)->where($column, $item->id)->where('status', 'pending')->first();

        if ($existing !== null) {
            return $existing;
        }

        return AcademyOrder::query()->create([
            'reference' => self::reference(),
            'user_id' => $user->id,
            $column => $item->id,
            'label' => $isCourse ? $item->title : 'Abonnement '.AcademySettings::NAME.' · '.$item->name,
            'amount' => $item->price,
            'currency' => 'TND',
            'status' => 'pending',
            'provider' => PaymentGateways::active()->key(),
        ]);
    }

    /**
     * Confirme le paiement et accorde l'accès. Sans effet si la commande est déjà payée.
     */
    public static function markPaid(AcademyOrder $order, ?string $providerReference = null): bool
    {
        return DB::transaction(function () use ($order, $providerReference): bool {
            $order = AcademyOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->status, ['pending', 'cancelled'], true)) {
                return false;
            }

            $order->update(['status' => 'paid', 'paid_at' => now(), 'provider_reference' => $providerReference ?? $order->provider_reference]);

            $user = User::query()->findOrFail($order->user_id);

            if ($order->course_id !== null && ($course = AcademyCourse::query()->find($order->course_id)) !== null) {
                AcademyAccess::enroll($user, $course, 'purchase');
            }

            if ($order->plan_id !== null && ($plan = AcademyPlan::query()->find($order->plan_id)) !== null) {
                $latest = AcademySubscription::query()->where('user_id', $user->id)->where('ends_at', '>', now())->max('ends_at');
                $start = $latest !== null ? \Illuminate\Support\Carbon::parse($latest) : now();

                AcademySubscription::query()->create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'order_id' => $order->id,
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->addDays($plan->duration_days),
                ]);
            }

            AppNotification::query()->create([
                'user_id' => $user->id,
                'type' => 'academy.order_paid',
                'title' => 'Paiement confirmé',
                'body' => 'Votre commande '.$order->reference.' ('.$order->label.') est confirmée : votre accès est ouvert.',
                'data' => ['order_id' => $order->id],
            ]);

            return true;
        });
    }

    /** Annule une commande en attente. */
    public static function cancel(AcademyOrder $order): bool
    {
        if ($order->status !== 'pending') {
            return false;
        }

        $order->update(['status' => 'cancelled']);

        return true;
    }

    /** Rembourse une commande payée et retire l'accès qu'elle avait ouvert. */
    public static function refund(AcademyOrder $order): bool
    {
        if ($order->status !== 'paid') {
            return false;
        }

        DB::transaction(function () use ($order): void {
            $order->update(['status' => 'refunded']);

            if ($order->course_id !== null) {
                // Un cours déjà terminé garde son certificat.
                \App\Models\AcademyEnrollment::query()
                    ->where('user_id', $order->user_id)->where('course_id', $order->course_id)
                    ->where('source', 'purchase')->whereNull('completed_at')->delete();
            }

            if ($order->plan_id !== null) {
                AcademySubscription::query()->where('order_id', $order->id)->where('ends_at', '>', now())->update(['ends_at' => now()]);
            }
        });

        return true;
    }

    private static function reference(): string
    {
        do {
            $reference = 'TA-'.strtoupper(Str::random(8));
        } while (AcademyOrder::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
