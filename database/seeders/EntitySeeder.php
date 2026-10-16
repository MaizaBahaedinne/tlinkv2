<?php

namespace Database\Seeders;

use App\Models\Entity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EntitySeeder extends Seeder
{
    public function run(): void
    {
        $entities = [
            ['name' => 'University', 'category' => 'education'],
            ['name' => 'High School', 'category' => 'education'],
            ['name' => 'We Are Carthage', 'category' => 'territorial'],
            ['name' => 'We Are Campers', 'category' => 'community'],
        ];

        foreach ($entities as $entity) {
            Entity::query()->updateOrCreate(
                ['slug' => Str::slug($entity['name'])],
                [
                    'name' => $entity['name'],
                    'category' => $entity['category'],
                    'is_active' => true,
                ]
            );
        }
    }
}
