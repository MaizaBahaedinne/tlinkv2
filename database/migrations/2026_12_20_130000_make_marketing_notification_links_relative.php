<?php

use App\Models\AppNotification;
use App\Support\DigitalManagers;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Les notifications de demandes d'accès déjà envoyées portaient un lien absolu, que la liste n'affichait pas comme cliquable. */
    public function up(): void
    {
        AppNotification::query()->where('type', 'marketing_access')->get()->each(function (AppNotification $notification): void {
            $url = $notification->data['url'] ?? null;

            if (is_string($url) && ! str_starts_with($url, '/')) {
                $notification->forceFill(['data' => ['url' => DigitalManagers::relative($url)] + (array) $notification->data])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Sans objet.
    }
};
