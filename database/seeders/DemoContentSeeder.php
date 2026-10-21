<?php

namespace Database\Seeders;

use App\Models\OrgAssignment;
use App\Models\OrgNode;
use App\Models\OrgPerson;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Support\OrgChart\OrgChartInstaller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'admin.local@tlink.test'],
            [
                'name' => 'Admin Local T.Link',
                'password' => Hash::make('Password123!'),
            ]
        );

        Profile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'headline' => 'National Bureau Administrator',
                'bio' => 'Compte local de demonstration pour T.Link.',
            ]
        );

        Post::query()->updateOrCreate(
            ['content' => 'Bienvenue sur T.Link, le reseau de Fondation Tunivisions.'],
            [
                'user_id' => $user->id,
                'scope' => 'foundation',
                'visibility' => 'authenticated',
                'type' => 'announcement',
                'published_at' => now(),
            ]
        );

        // Les droits viennent du poste : le compte de démo occupe le poste d'administrateur de la plateforme.
        $installer = app(OrgChartInstaller::class);
        $installer->isInstalled() ? $installer->restructure() : $installer->install();

        $position = OrgNode::query()->where('system_key', 'national-administrators')->first();

        if ($position) {
            $person = OrgPerson::query()->firstOrCreate(['user_id' => $user->id], ['full_name' => $user->name, 'contact_visible' => true]);
            OrgAssignment::query()->firstOrCreate(['node_id' => $position->id, 'person_id' => $person->id]);
        }
    }
}
