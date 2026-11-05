<?php

use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Mesure Google (gtag.js) de la Fondation : une seule balise Google par page, elle remplace le conteneur Tag Manager posé par défaut. */
    private const TAG_ID = 'G-WX9RVDBH4P';

    private const PREVIOUS_DEFAULT = 'GTM-P3PW7PKX';

    public function up(): void
    {
        $row = DB::table('settings')->where('group', SiteSettings::GROUP)->where('key', SiteSettings::KEY)->first();
        $content = $row && $row->value ? json_decode((string) $row->value, true) : [];
        $content = is_array($content) ? $content : [];
        $current = strtoupper(trim((string) ($content['google_tag_id'] ?? '')));

        // Une valeur choisie par l'administrateur est respectée ; seul le défaut précédent est remplacé.
        if ($current !== '' && $current !== self::PREVIOUS_DEFAULT) {
            return;
        }

        $content['google_tag_id'] = self::TAG_ID;
        $json = json_encode($content, JSON_UNESCAPED_UNICODE);

        if ($row) {
            DB::table('settings')->where('id', $row->id)->update(['value' => $json, 'updated_at' => now()]);
        } else {
            DB::table('settings')->insert(['group' => SiteSettings::GROUP, 'key' => SiteSettings::KEY, 'value' => $json, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Réglage modifiable en admin : rien à défaire.
    }
};
