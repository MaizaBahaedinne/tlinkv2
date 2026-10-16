<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GovernorateSeeder extends Seeder
{
    public function run(): void
    {
        $governorates = [
            'Tunis',
            'Ariana',
            'Ben Arous',
            'Manouba',
            'Nabeul',
            'Zaghouan',
            'Bizerte',
            'Beja',
            'Jendouba',
            'Le Kef',
            'Siliana',
            'Sousse',
            'Monastir',
            'Mahdia',
            'Sfax',
            'Kairouan',
            'Kasserine',
            'Sidi Bouzid',
            'Gabes',
            'Medenine',
            'Tataouine',
            'Gafsa',
            'Tozeur',
            'Kebili',
        ];

        foreach ($governorates as $name) {
            Governorate::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }
    }
}
