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
        // Super Admin
        $superAdmin = User::firstOrCreate(['email' => 'admin@gescrim.sn'], [
            'name'             => 'Admin Principal',
            'password'         => Hash::make('password123'),
            'telephone'        => '+221 77 000 00 01',
            'is_active'        => true,
            'read_scope_type'  => 'national',
            'read_scope_id'    => null,
            'write_scope_type' => 'national',
            'write_scope_id'   => null,
        ]);
        $superAdmin->syncRoles(['super_admin']);

        // Admin régional Dakar (portée région 1 = Dakar uniquement)
        $admin = User::firstOrCreate(['email' => 'admin.dakar@gescrim.sn'], [
            'name'             => 'Administrateur Dakar',
            'password'         => Hash::make('password123'),
            'telephone'        => '+221 77 000 00 02',
            'is_active'        => true,
            'read_scope_type'  => 'region',
            'read_scope_id'    => 1,
            'write_scope_type' => 'region',
            'write_scope_id'   => 1,
        ]);
        $admin->syncRoles(['admin']);

        // Gestionnaire commissariat (portée service 1 uniquement)
        $gestionnaire = User::firstOrCreate(['email' => 'gestionnaire@gescrim.sn'], [
            'name'             => 'Gestionnaire Commissariat Central',
            'password'         => Hash::make('password123'),
            'telephone'        => '+221 77 000 00 03',
            'service_id'       => 1,
            'is_active'        => true,
            'read_scope_type'  => 'service',
            'read_scope_id'    => 1,
            'write_scope_type' => 'service',
            'write_scope_id'   => 1,
        ]);
        $gestionnaire->syncRoles(['gestionnaire']);

        // Superviseur Dakar (lecture région 1, pas d'écriture)
        $superviseur = User::firstOrCreate(['email' => 'superviseur@gescrim.sn'], [
            'name'             => 'Superviseur Dakar',
            'password'         => Hash::make('password123'),
            'telephone'        => '+221 77 000 00 04',
            'service_id'       => 1,
            'is_active'        => true,
            'read_scope_type'  => 'region',
            'read_scope_id'    => 1,
            'write_scope_type' => 'national',
            'write_scope_id'   => null,
        ]);
        $superviseur->syncRoles(['superviseur']);

        // Agent terrain (portée service 1)
        $agent = User::firstOrCreate(['email' => 'agent@gescrim.sn'], [
            'name'             => 'Agent Terrain',
            'password'         => Hash::make('password123'),
            'telephone'        => '+221 77 000 00 05',
            'service_id'       => 1,
            'is_active'        => true,
            'read_scope_type'  => 'service',
            'read_scope_id'    => 1,
            'write_scope_type' => 'service',
            'write_scope_id'   => 1,
        ]);
        $agent->syncRoles(['agent']);

        // Agent Mbour (portée service 2)
        $agent2 = User::firstOrCreate(['email' => 'agent.mbour@gescrim.sn'], [
            'name'             => 'Agent Mbour',
            'password'         => Hash::make('password123'),
            'telephone'        => '+221 77 000 00 06',
            'service_id'       => 2,
            'is_active'        => true,
            'read_scope_type'  => 'service',
            'read_scope_id'    => 2,
            'write_scope_type' => 'service',
            'write_scope_id'   => 2,
        ]);
        $agent2->syncRoles(['agent']);
    }
}
