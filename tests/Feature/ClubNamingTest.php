<?php

namespace Tests\Feature;

use App\Models\OrgNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubNamingTest extends TestCase
{
    use RefreshDatabase;

    private function club(string $title, string $category): OrgNode
    {
        return OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'club', 'category' => $category, 'title' => $title, 'status' => 'active', 'sort_order' => 0]);
    }

    public function test_university_and_highschool_clubs_start_with_club_tunivisions(): void
    {
        $this->assertSame('Club Tunivisions ESPRIT', $this->club('ESPRIT', 'university_club')->title);
        $this->assertSame('Club Tunivisions Lycée Pilote', $this->club('Lycée Pilote', 'highschool_club')->title);
    }

    public function test_the_prefix_is_never_doubled_and_replaces_a_plain_club_prefix(): void
    {
        $this->assertSame('Club Tunivisions ENIT', $this->club('Club Tunivisions ENIT', 'university_club')->title);
        $this->assertSame('Club Tunivisions Sup\'Com', $this->club('Club Sup\'Com', 'university_club')->title);
        $this->assertSame('Club Tunivisions ISET', $this->club('club tunivisions - ISET', 'university_club')->title);
    }

    public function test_renaming_a_club_keeps_the_prefix(): void
    {
        $club = $this->club('ESPRIT', 'university_club');
        $club->update(['title' => 'ESPRIT TIC']);

        $this->assertSame('Club Tunivisions ESPRIT TIC', $club->fresh()->title);
    }

    public function test_other_blocks_are_not_renamed(): void
    {
        $this->assertSame('We Are Carthage', $this->club('We Are Carthage', 'regional_club')->title);
        $this->assertSame('Département Média', OrgNode::query()->create(['relation_type' => 'hierarchy', 'kind' => 'club_department', 'category' => 'university_club', 'title' => 'Département Média', 'sort_order' => 0])->title);
    }
}
