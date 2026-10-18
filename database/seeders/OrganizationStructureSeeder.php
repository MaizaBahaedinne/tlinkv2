<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Department;
use App\Models\Entity;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class OrganizationStructureSeeder extends Seeder
{
    public function run(): void
    {
        $foundation = Entity::query()->where('slug', 'foundation')->first();
        $university = Entity::query()->where('slug', 'university')->first();
        $highSchool = Entity::query()->where('slug', 'high-school')->first();
        $alumni = Entity::query()->where('slug', 'alumni')->first();

        $structure = [
            [
                'entity' => $foundation,
                'department' => ['name' => 'Bureau national', 'slug' => 'bureau-national'],
                'institutions' => [],
                'clubs' => [],
            ],
            [
                'entity' => $university,
                'department' => ['name' => 'Bureau universitaire', 'slug' => 'bureau-universitaire'],
                'institutions' => [
                    ['name' => 'Université pilote', 'slug' => 'universite-pilote', 'institution_type' => 'university'],
                ],
                'clubs' => [
                    ['name' => 'Club Innovation', 'slug' => 'club-innovation'],
                    ['name' => 'Club Culture', 'slug' => 'club-culture'],
                ],
            ],
            [
                'entity' => $highSchool,
                'department' => ['name' => 'Bureau lycée', 'slug' => 'bureau-lycee'],
                'institutions' => [
                    ['name' => 'Lycée pilote', 'slug' => 'lycee-pilote', 'institution_type' => 'high_school'],
                ],
                'clubs' => [
                    ['name' => 'Club Leadership', 'slug' => 'club-leadership'],
                    ['name' => 'Club Volontariat', 'slug' => 'club-volontariat'],
                ],
            ],
            [
                'entity' => $alumni,
                'department' => ['name' => 'Bureau alumni', 'slug' => 'bureau-alumni'],
                'institutions' => [],
                'clubs' => [],
            ],
        ];

        foreach ($structure as $node) {
            if (! $node['entity']) {
                continue;
            }

            $department = Department::query()->updateOrCreate(
                [
                    'entity_id' => $node['entity']->id,
                    'slug' => $node['department']['slug'],
                ],
                [
                    'name' => $node['department']['name'],
                    'description' => $node['department']['name'].' de '.$node['entity']->name,
                ]
            );

            $createdInstitutions = [];

            foreach ($node['institutions'] as $institution) {
                $createdInstitutions[] = Institution::query()->updateOrCreate(
                    [
                        'entity_id' => $node['entity']->id,
                        'slug' => $institution['slug'],
                    ],
                    [
                        'name' => $institution['name'],
                        'institution_type' => $institution['institution_type'],
                        'description' => $institution['name'].' rattachée à '.$node['entity']->name,
                        'is_active' => true,
                    ]
                );
            }

            $defaultInstitutionId = $createdInstitutions[0]->id ?? null;

            foreach ($node['clubs'] as $club) {
                Club::query()->updateOrCreate(
                    [
                        'slug' => $club['slug'],
                    ],
                    [
                        'name' => $club['name'],
                        'entity_id' => $node['entity']->id,
                        'department_id' => $department->id,
                        'institution_id' => $defaultInstitutionId,
                        'status' => 'active',
                    ]
                );
            }
        }
    }
}
