<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Region;
use App\Models\Commune;
use App\Models\Service;

/**
 * Crée des utilisateurs de test pour chaque région et commune,
 * avec les 4 rôles (super_admin, admin, superviseur, agent).
 *
 * Règles de scope par rôle :
 *  - super_admin : read=national, write=national  (un seul, partagé)
 *  - admin       : read=national, write=national  (un par région)
 *  - superviseur : read=region,   write=region    (un par région)
 *  - agent       : read=service,  write=service   (un par service)
 *
 * Convention email : {role}{slug}@gescrim.sn
 *   ex. agentdakar@gescrim.sn  superviseurmb@gescrim.sn
 */
class TestUsersSeeder extends Seeder
{
    /** Transforme un nom en slug court pour l'email (sans accents, sans espaces, max 8 chars). */
    private function slug(string $name): string
    {
        $map = [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e',
            'À' => 'a', 'Â' => 'a',
            'Î' => 'i', 'Ô' => 'o',
            'Ù' => 'u', 'Û' => 'u',
            'Ç' => 'c',
        ];
        $normalized = strtr($name, $map);
        // Garder seulement les lettres et chiffres, tout en minuscules
        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($normalized));
        return substr($slug, 0, 8);
    }

    /** Crée un utilisateur (ignore si l'email existe déjà). */
    private function makeUser(array $data, string $role): void
    {
        if (User::where('email', $data['email'])->exists()) {
            return;
        }

        $user = User::create(array_merge($data, [
            'password'                => Hash::make('passer123'),
            'is_active'               => true,
            'is_2fa_enabled'          => true,
            'two_factor_confirmed_at' => now(),
        ]));

        $user->assignRole($role);
    }

    public function run(): void
    {
        // ── 1. Super admin national (unique) ─────────────────────────────────
        $this->makeUser([
            'name'             => 'Super Admin National',
            'email'            => 'superadmin@gescrim.sn',
            'telephone'        => '+221 77 100 00 00',
            'read_scope_type'  => 'national',
            'read_scope_id'    => null,
            'write_scope_type' => 'national',
            'write_scope_id'   => null,
        ], 'super_admin');

        // ── 2. Un admin, un superviseur et des agents par région ─────────────
        $regions = Region::with([
            'departements.communes.services',
        ])->get();

        foreach ($regions as $region) {
            $slug = $this->slug($region->nom);

            // Admin (portée nationale, rattaché à la région par convention d'email)
            $this->makeUser([
                'name'             => "Admin {$region->nom}",
                'email'            => "admin{$slug}@gescrim.sn",
                'telephone'        => '+221 77 200 00 00',
                'read_scope_type'  => 'national',
                'read_scope_id'    => null,
                'write_scope_type' => 'national',
                'write_scope_id'   => null,
            ], 'admin');

            // Superviseur (portée région)
            $this->makeUser([
                'name'             => "Superviseur {$region->nom}",
                'email'            => "superviseur{$slug}@gescrim.sn",
                'telephone'        => '+221 77 300 00 00',
                'read_scope_type'  => 'region',
                'read_scope_id'    => $region->id,
                'write_scope_type' => 'region',
                'write_scope_id'   => $region->id,
            ], 'superviseur');

            // Agents (un par service dans la région)
            foreach ($region->departements as $dept) {
                foreach ($dept->communes as $commune) {
                    foreach ($commune->services as $service) {
                        $serviceSlug = $this->slug($commune->nom);
                        // Suffixe numérique si plusieurs services dans la même commune
                        $email = "agent{$serviceSlug}{$service->id}@gescrim.sn";

                        $this->makeUser([
                            'name'             => "Agent {$commune->nom} ({$service->nom})",
                            'email'            => $email,
                            'telephone'        => '+221 77 400 00 00',
                            'service_id'       => $service->id,
                            'read_scope_type'  => 'service',
                            'read_scope_id'    => $service->id,
                            'write_scope_type' => 'service',
                            'write_scope_id'   => $service->id,
                        ], 'agent');
                    }
                }
            }
        }

        // ── 3. Résumé ─────────────────────────────────────────────────────────
        $total = User::count();
        $this->command->info("TestUsersSeeder terminé — {$total} utilisateurs au total.");
        $this->command->table(
            ['Rôle', 'Nombre'],
            [
                ['super_admin',  User::role('super_admin')->count()],
                ['admin',        User::role('admin')->count()],
                ['superviseur',  User::role('superviseur')->count()],
                ['agent',        User::role('agent')->count()],
            ]
        );
    }
}
