<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active', 'latitude', 'longitude'];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    /** Centre approximatif de chaque gouvernorat (slug => [latitude, longitude]), utilisé par la carte publique. */
    public const COORDINATES = [
        'tunis' => [36.8065, 10.1815],
        'ariana' => [36.8625, 10.1956],
        'ben-arous' => [36.7531, 10.2189],
        'manouba' => [36.8101, 10.0863],
        'nabeul' => [36.4561, 10.7376],
        'zaghouan' => [36.4029, 10.1429],
        'bizerte' => [37.2744, 9.8739],
        'beja' => [36.7256, 9.1817],
        'jendouba' => [36.5011, 8.7803],
        'le-kef' => [36.1675, 8.7049],
        'siliana' => [36.0850, 9.3708],
        'sousse' => [35.8256, 10.6369],
        'monastir' => [35.7643, 10.8113],
        'mahdia' => [35.5047, 11.0622],
        'sfax' => [34.7406, 10.7603],
        'kairouan' => [35.6781, 10.0963],
        'kasserine' => [35.1676, 8.8365],
        'sidi-bouzid' => [35.0382, 9.4849],
        'gabes' => [33.8815, 10.0982],
        'medenine' => [33.3549, 10.5055],
        'tataouine' => [32.9297, 10.4518],
        'gafsa' => [34.4250, 8.7842],
        'tozeur' => [33.9197, 8.1335],
        'kebili' => [33.7050, 8.9690],
    ];

    protected static function booted(): void
    {
        // Tout nouveau gouvernorat connu reçoit ses coordonnées (installateur d'organigramme, import, seeder...).
        static::creating(function (Governorate $governorate): void {
            if ($governorate->latitude === null && isset(self::COORDINATES[$governorate->slug])) {
                [$governorate->latitude, $governorate->longitude] = self::COORDINATES[$governorate->slug];
            }
        });
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(OrgNode::class)->where('kind', 'club');
    }
}
