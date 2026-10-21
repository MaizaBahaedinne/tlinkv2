<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use App\Support\LegacyClubImporter;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyClubImportTest extends TestCase
{
    use RefreshDatabase;

    private function dump(): string
    {
        $sql = <<<'SQL'
INSERT INTO `tbl_club` (`clubID`, `name`, `birthday`, `city`, `facebook`, `email`, `is_Actif`, `charte`, `SenJun`) VALUES
(-1, 'Foundation', '2016-10-24', '', 'tunivisionsfoundation@gmail.com', 'https://www.facebook.com/Tunivisionsfoundation/', '1', 0, 1),
(0, 'University', '2016-10-24', 'Tunis', 'tunivisionsfoundation@gmail.com', 'https://www.facebook.com/Tunivisionsfoundation/', '1', 1, 2),
(1, 'High School', '2019-03-20', 'Tunis', 'x@gmail.com', 'https://www.facebook.com/x/', '1', 1, 2),
(2, 'Alumni', '2020-02-18', 'Tunis', 'x@gmail.com', 'https://www.facebook.com/x/', '1', 1, 2),
(143, 'ESPRIT TIC', '2016-12-16', 'Ariana', 'esprit@esprit.tn', 'https://www.facebook.com/ClubTunivisionsEsprit/', '1', 1, 3),
(144, 'ESPRIT GCEM', '2017-11-27', 'Ariana', 'gcem@esprit.tn', 'https://www.facebook.com/gcem/', '1', 1, 3),
(149, 'ISIMS', '0000-00-00', 'Sfax', 'club@gmail.com', '', '1', 0, 3),
(159, 'FSB', '2019-02-13', 'Bizert', 'tunivision2019@gmail.com', 'https://www.facebook.com/fsb/', '0', 1, 3),
(173, 'CSFMT H.LIF', '2020-11-15', 'Tunis', 'csf@gmail.com', 'https://www.facebook.com/csf', '1', 1, 3),
(299, 'Lycée Cité Salem	', '0000-00-00', '	Ben AROUS	', 'ctj@gmail.com', 'https://www.facebook.com/lcsb/', '1', 1, 4),
(332, 'Lycée le pôle d\'enseignement ', '2020-11-11', 'Nabeul', 'pen@gmail.com', 'https://www.facebook.com/pen', '1', 0, 4),
(336, 'ISET Kelibia ', '2020-02-26', 'Kelibia', 'kl@gmail.com', 'https://www.facebook.com/kl', '1', 0, 3),
(999, 'Sans ville', '0000-00-00', '', '', '', '1', 0, 3);

-- Structure de la table suivante
INSERT INTO `tbl_autre` (`id`) VALUES
(1, 'ne doit pas etre lu', '2020-01-01', 'Tunis', 'a', 'b', '1', 0, 3);
SQL;

        $path = tempnam(sys_get_temp_dir(), 'club');
        file_put_contents($path, $sql);

        return $path;
    }

    private function clubs()
    {
        return OrgNode::query()->whereNotNull('legacy_id');
    }

    public function test_import_maps_clubs_and_is_idempotent(): void
    {
        $this->seed(GovernorateSeeder::class);
        $path = $this->dump();

        $stats = app(LegacyClubImporter::class)->import($path);

        $this->assertSame(4, $stats['branches']);
        $this->assertSame(9, $stats['clubs']);
        $this->assertSame(0, $stats['skipped']);
        $this->assertSame(9, $this->clubs()->count());
        $this->assertSame(['Sans ville ()'], $stats['no_governorate']);

        // Le champ email/facebook est classe selon son contenu, meme si inverse.
        $esprit = OrgNode::query()->where('legacy_id', 143)->firstOrFail();
        $this->assertSame('esprit@esprit.tn', $esprit->email);
        $this->assertSame('https://www.facebook.com/ClubTunivisionsEsprit/', $esprit->facebook_url);
        $this->assertSame('2016-12-16', $esprit->founded_on->toDateString());
        $this->assertTrue($esprit->charter_signed);
        $this->assertSame('active', $esprit->status);
        $this->assertSame('university', $esprit->scope);
        $this->assertSame('ariana', $esprit->governorate->slug);

        $this->assertNull(OrgNode::query()->where('legacy_id', 149)->value('founded_on'));
        $this->assertSame('inactive', OrgNode::query()->where('legacy_id', 159)->value('status'));
        $this->assertSame('bizerte', OrgNode::query()->where('legacy_id', 159)->firstOrFail()->governorate->slug);
        $this->assertSame('nabeul', OrgNode::query()->where('legacy_id', 336)->firstOrFail()->governorate->slug);

        // Classement par SenJun : « CSFMT H.LIF » est universitaire, les lycees sont High School.
        $this->assertSame('university', OrgNode::query()->where('legacy_id', 173)->firstOrFail()->scope);
        $lycee = OrgNode::query()->where('legacy_id', 299)->firstOrFail();
        $this->assertSame('Lycée Cité Salem', $lycee->title);
        $this->assertSame('high-school', $lycee->scope);
        $this->assertSame('ben-arous', $lycee->governorate->slug);
        $this->assertSame("Lycée le pôle d'enseignement", OrgNode::query()->where('legacy_id', 332)->value('title'));

        // Etablissements : ESPRIT TIC + ESPRIT GCEM partagent ESPRIT.
        $this->assertSame('ESPRIT', $esprit->institution_name);
        $this->assertSame('ESPRIT', OrgNode::query()->where('legacy_id', 144)->value('institution_name'));
        $this->assertSame('university', $esprit->institution_type);
        $this->assertSame('high_school', $lycee->institution_type);
        $this->assertSame('Lycée Cité Salem', $lycee->institution_name);
        $this->assertSame(0, $this->clubs()->whereNull('institution_name')->count());

        // Les clubs sont ranges dans l'organigramme : branche du perimetre puis groupe du gouvernorat.
        $this->assertSame('university-clubs:ariana', $esprit->parent->system_key);
        $this->assertSame('university-clubs', $esprit->parent->parent->system_key);
        $this->assertSame('highschool-clubs', $lycee->parent->parent->system_key);

        $again = app(LegacyClubImporter::class)->import($path);
        $this->assertSame(0, $again['created']);
        $this->assertSame(9, $again['updated']);
        $this->assertSame(9, $this->clubs()->count());

        unlink($path);
    }
}
