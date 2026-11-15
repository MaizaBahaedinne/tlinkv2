<?php

use App\Models\MarketingAccount;
use App\Models\OrgNode;
use App\Support\DigitalManagers;
use App\Support\EntityPages;
use App\Support\ModuleCatalog;
use App\Support\ModulePermissions;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Demandes d'accès et validation : les postes qui gèrent déjà les événements peuvent demander l'accès aux pages de
     * leur entité ; le poste « Digital manager » valide. Les liens Facebook / Instagram déjà saisis sur les fiches des
     * clubs et dans les réglages du site deviennent des pages du coffre.
     */
    public function up(): void
    {
        ModuleCatalog::sync();

        OrgNode::query()
            ->where('kind', 'position')
            ->whereNotNull('module_permissions')
            ->each(function (OrgNode $node): void {
                $matrix = (array) $node->module_permissions;

                if (! in_array('event.manage', ModulePermissions::derivePermissions($matrix), true) || isset($matrix['marketing.requests'])) {
                    return;
                }

                $matrix['marketing.requests'] = ['read', 'create'];
                $node->update(['module_permissions' => $matrix]);
            });

        DigitalManagers::ensurePosition();

        foreach (['facebook' => 'facebook_url', 'instagram' => 'instagram_url'] as $platform => $column) {
            OrgNode::query()->where('kind', 'club')->whereNotNull($column)->get(['id', 'title', $column])->each(function (OrgNode $club) use ($platform, $column): void {
                $url = $club->{$column};

                if (! EntityPages::valid($url) || MarketingAccount::query()->where('club_id', $club->id)->where('platform', $platform)->exists()) {
                    return;
                }

                MarketingAccount::query()->create(['club_id' => $club->id, 'platform' => $platform, 'name' => MarketingAccount::PLATFORMS[$platform].' — '.$club->title, 'profile_url' => $url, 'two_factor' => 'none']);
            });

            $url = (new SiteSettings())->raw()[$column] ?? null;

            if (is_string($url) && EntityPages::valid($url) && ! MarketingAccount::query()->whereNull('club_id')->where('scope', 'foundation')->where('platform', $platform)->exists()) {
                MarketingAccount::query()->create(['scope' => 'foundation', 'platform' => $platform, 'name' => MarketingAccount::PLATFORMS[$platform].' — Fondation Tunivisions', 'profile_url' => $url, 'two_factor' => 'none']);
            }
        }
    }

    public function down(): void
    {
        // Les droits accordés et les pages restent en place.
    }
};
