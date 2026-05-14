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
        $superAdmin = User::create([
            'name' => 'Admin Principal',
            'email' => 'admin@gescrim.sn',
            'password' => Hash::make('password123'),
            'telephone' => '+221 77 000 00 01',
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        // Admin
        $admin = User::create([
            'name' => 'Administrateur DSP',
            'email' => 'admin.dsp@gescrim.sn',
            'password' => Hash::make('password123'),
            'telephone' => '+221 77 000 00 02',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        // Superviseur
        $superviseur = User::create([
            'name' => 'Superviseur Dakar',
            'email' => 'superviseur@gescrim.sn',
            'password' => Hash::make('password123'),
            'telephone' => '+221 77 000 00 03',
            'service_id' => 1, // Premier service créé
            'is_active' => true,
        ]);
        $superviseur->assignRole('superviseur');

        // Agent terrain
        $agent = User::create([
            'name' => 'Agent Terrain',
            'email' => 'agent@gescrim.sn',
            'password' => Hash::make('password123'),
            'telephone' => '+221 77 000 00 04',
            'service_id' => 1,
            'is_active' => true,
        ]);
        $agent->assignRole('agent');

        // Agent 2
        $agent2 = User::create([
            'name' => 'Agent Mbour',
            'email' => 'agent.mbour@gescrim.sn',
            'password' => Hash::make('password123'),
            'telephone' => '+221 77 000 00 05',
            'service_id' => 2,
            'is_active' => true,
        ]);
        $agent2->assignRole('agent');
    }
}
