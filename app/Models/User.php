<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'first_name', 'last_name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * `name` est le nom complet (« Prénom Nom »), garde cohérent avec le prénom et le nom :
     * - prénom ou nom modifié (et `name` laissé tel quel) : le nom complet est recomposé ;
     * - seul `name` modifié : il est redécoupé en prénom et nom.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $splitChanged = $user->isDirty(['first_name', 'last_name']);

            if ($splitChanged && ! $user->isDirty('name')) {
                $full = trim(preg_replace('/\s+/u', ' ', trim((string) $user->first_name).' '.trim((string) $user->last_name)));

                if ($full !== '') {
                    $user->name = $full;
                }
            } elseif (! $splitChanged && $user->isDirty('name')) {
                [$user->first_name, $user->last_name] = self::splitName((string) $user->name);
            }
        });
    }

    /**
     * Découpe un nom complet au premier espace : premier mot = prénom, reste = nom de famille.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName), 2, PREG_SPLIT_NO_EMPTY) ?: [];

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function orgPerson(): HasOne
    {
        return $this->hasOne(OrgPerson::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
