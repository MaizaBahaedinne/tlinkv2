<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
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

        $entity = Entity::query()->where('slug', 'university')->first();

        Post::query()->updateOrCreate(
            ['content' => 'Bienvenue sur T.Link, le reseau de Fondation Tunivisions.'],
            [
                'user_id' => $user->id,
                'entity_id' => $entity?->id,
                'visibility' => 'authenticated',
                'type' => 'announcement',
                'published_at' => now(),
            ]
        );
    }
}
