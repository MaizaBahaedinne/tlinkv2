<?php

use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Google Analytics et Google Tag Manager sont deux outils distincts : chacun a désormais son propre réglage. */
    private const ANALYTICS_ID = 'G-WX9RVDBH4P';

    private const TAG_MANAGER_ID = 'GTM-P3PW7PKX';

    public function up(): void
    {
        $row = DB::table('settings')->where('group', SiteSettings::GROUP)->where('key', SiteSettings::KEY)->first();
        $content = $row && $row->value ? json_decode((string) $row->value, true) : [];
        $content = is_array($content) ? $content : [];

        // L'ancien champ unique est réparti selon son format ; une valeur déjà saisie dans un nouveau champ est respectée.
        $legacy = strtoupper(trim((string) ($content['google_tag_id'] ?? '')));
        unset($content['google_tag_id']);

        $analytics = strtoupper(trim((string) ($content['google_analytics_id'] ?? '')));
        $tagManager = strtoupper(trim((string) ($content['google_tag_manager_id'] ?? '')));

        if ($legacy !== '') {
            str_starts_with($legacy, 'GTM-') ? $tagManager = $tagManager ?: $legacy : $analytics = $analytics ?: $legacy;
        }

        $content['google_analytics_id'] = $analytics !== '' ? $analytics : self::ANALYTICS_ID;
        $content['google_tag_manager_id'] = $tagManager !== '' ? $tagManager : self::TAG_MANAGER_ID;
        $json = json_encode($content, JSON_UNESCAPED_UNICODE);

        if ($row) {
            DB::table('settings')->where('id', $row->id)->update(['value' => $json, 'updated_at' => now()]);
        } else {
            DB::table('settings')->insert(['group' => SiteSettings::GROUP, 'key' => SiteSettings::KEY, 'value' => $json, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Réglages modifiables en admin : rien à défaire.
    }
};
