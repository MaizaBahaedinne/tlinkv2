<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Abonné à la newsletter du site public. Un abonné qui se désinscrit reste en base (pour ne pas être réinscrit à son insu
 * par un import) mais n'apparaît plus parmi les abonnés actifs.
 */
class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'locale', 'unsubscribe_token', 'ip_address', 'subscribed_at', 'unsubscribed_at'];

    protected $hidden = ['unsubscribe_token'];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $subscriber): void {
            if (blank($subscriber->unsubscribe_token)) {
                $subscriber->unsubscribe_token = Str::random(48);
            }
        });
    }

    /**
     * @param Builder<NewsletterSubscriber> $query
     * @return Builder<NewsletterSubscriber>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }
}
