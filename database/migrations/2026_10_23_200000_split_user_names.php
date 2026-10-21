<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separe le prenom et le nom de famille des utilisateurs.
 * La colonne `name` est conservee : c'est le nom complet affiche partout (« Prenom Nom »).
 *
 * Les comptes existants sont decoupes au premier espace (premier mot = prenom, reste = nom).
 * Ce decoupage est approximatif pour les noms composes : il se corrige depuis l'administration,
 * et la reexecution de `legacy:import-users` rectifie les comptes importes depuis les champs prenom / nom d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 120)->nullable()->after('name');
            $table->string('last_name', 120)->nullable()->after('first_name');
        });

        DB::table('users')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                $parts = preg_split('/\s+/u', trim((string) $user->name), 2, PREG_SPLIT_NO_EMPTY) ?: [];

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $parts[0] ?? null,
                    'last_name' => $parts[1] ?? null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
