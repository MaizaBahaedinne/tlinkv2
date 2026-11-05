<?php

use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Balise Google fournie par la Fondation : posée une seule fois, modifiable ensuite dans Admin > Site public. */
    private const TAG_ID = 'G-WX9RVDBH4P';

    public function up(): void
    {
        $row = DB::table('settings')->where('group', SiteSettings::GROUP)->where('key', SiteSettings::KEY)->first();
        $content = $row && $row->value ? json_decode((string) $row->value, true) : [];
        $content = is_array($content) ? $content : [];

        if (($content['google_tag_id'] ?? '') !== '') {
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
