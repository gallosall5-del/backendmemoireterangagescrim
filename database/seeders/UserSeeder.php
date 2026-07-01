<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder pour les utilisateurs de test.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin national
        $admin = User::firstOrCreate(['email' => 'admin@gescrim.sn'], [
            'name'                    => 'Admin Principal',
            'password'                => Hash::make('password123'),
            'telephone'               => '+221 77 000 00 01',
            'is_active'               => true,
            'is_2fa_enabled'          => true,
            'two_factor_confirmed_at' => now(),
            'read_scope_type'         => 'national',
            'read_scope_id'           => null,
            'write_scope_type'        => 'national',
            'write_scope_id'          => null,
        ]);
        $admin->syncRoles(['admin']);

        // Gestionnaire régional Dakar (portée région 1)
        $gestionnaireRegion = User::firstOrCreate(['email' => 'gestionnaire.dakar@gescrim.sn'], [
            'name'                    => 'Gestionnaire Région Dakar',
            'password'                => Hash::make('password123'),
            'telephone'               => '+221 77 000 00 02',
            'is_active'               => true,
            'is_2fa_enabled'          => true,
            'two_factor_confirmed_at' => now(),
            'read_scope_type'         => 'region',
            'read_scope_id'           => 1,
            'write_scope_type'        => 'region',
            'write_scope_id'          => 1,
        ]);
        $gestionnaireRegion->syncRoles(['gestionnaire']);

        // Gestionnaire commissariat (portée service 1)
        $gestionnaire = User::firstOrCreate(['email' => 'gestionnaire@gescrim.sn'], [
            'name'                    => 'Gestionnaire Commissariat Central',
            'password'                => Hash::make('password123'),
            'telephone'               => '+221 77 000 00 03',
            'service_id'              => 1,
            'is_active'               => true,
            'is_2fa_enabled'          => true,
            'two_factor_confirmed_at' => now(),
            'read_scope_type'         => 'service',
            'read_scope_id'           => 1,
            'write_scope_type'        => 'service',
            'write_scope_id'          => 1,
        ]);
        $gestionnaire->syncRoles(['gestionnaire']);

        // Agent terrain (portée service 1)
        $agent = User::firstOrCreate(['email' => 'agent@gescrim.sn'], [
            'name'                    => 'Agent Terrain',
            'password'                => Hash::make('password123'),
            'telephone'               => '+221 77 000 00 04',
            'service_id'              => 1,
            'is_active'               => true,
            'is_2fa_enabled'          => true,
            'two_factor_confirmed_at' => now(),
            'read_scope_type'         => 'service',
            'read_scope_id'           => 1,
            'write_scope_type'        => 'service',
            'write_scope_id'          => 1,
        ]);
        $agent->syncRoles(['agent']);

        // Agent Mbour (portée service 2)
        $agent2 = User::firstOrCreate(['email' => 'agent.mbour@gescrim.sn'], [
            'name'                    => 'Agent Mbour',
            'password'                => Hash::make('password123'),
            'telephone'               => '+221 77 000 00 05',
            'service_id'              => 2,
            'is_active'               => true,
            'is_2fa_enabled'          => true,
            'two_factor_confirmed_at' => now(),
            'read_scope_type'         => 'service',
            'read_scope_id'           => 2,
            'write_scope_type'        => 'service',
            'write_scope_id'          => 2,
        ]);
        $agent2->syncRoles(['agent']);
    }
}
