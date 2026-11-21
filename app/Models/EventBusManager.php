<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Responsable d'un bus : présence, communication ou les deux. */
class EventBusManager extends Model
{
    public const DUTIES = [
        'both' => 'Présence et communication',
        'attendance' => 'Présence',
        'communication' => 'Communication',
    ];

    protected $fillable = ['bus_id', 'user_id', 'duty'];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(EventBus::class, 'bus_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dutyLabel(): string
    {
        return self::DUTIES[$this->duty] ?? self::DUTIES['both'];
    }
}
