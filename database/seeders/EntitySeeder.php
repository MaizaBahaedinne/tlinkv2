<?php

namespace Database\Seeders;

use App\Models\Entity;
use Illuminate\Database\Seeder;

class EntitySeeder extends Seeder
{
    public function run(): void
    {
        $entities = [
            ['name' => 'Fondation', 'slug' => 'foundation', 'category' => 'foundation', 'description' => 'Branche nationale de gouvernance et de coordination.'],
            ['name' => 'University', 'slug' => 'university', 'category' => 'education', 'description' => 'Branche universitaire et ses clubs.'],
            ['name' => 'High School', 'slug' => 'high-school', 'category' => 'education', 'description' => 'Branche secondaire et ses clubs.'],
            ['name' => 'Alumni', 'slug' => 'alumni', 'category' => 'alumni', 'description' => 'Branche des anciens membres et du réseau alumni.'],
        ];

        foreach ($entities as $entity) {
            Entity::query()->updateOrCreate(
                ['slug' => $entity['slug']],
                [
                    'name' => $entity['name'],
                    'category' => $entity['category'],
                    'description' => $entity['description'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
