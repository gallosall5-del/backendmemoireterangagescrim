<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Seeder pour les rôles et permissions Spatie.
 * Définit les 4 rôles principaux et leurs permissions.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Réinitialiser le cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Créer les permissions
        $permissions = [
            // Utilisateurs
            'users.view', 'users.create', 'users.update', 'users.delete',
            // Personnel
            'personnels.view', 'personnels.create', 'personnels.update', 'personnels.delete',
            // Infractions
            'infractions.view', 'infractions.create', 'infractions.update', 'infractions.delete',
            // Accidents
            'accidents.view', 'accidents.create', 'accidents.update', 'accidents.delete',
            // Victimes
            'victimes.view', 'victimes.create', 'victimes.update', 'victimes.delete',
            // Services rémunérés
            'services-remuneres.view', 'services-remuneres.create', 'services-remuneres.update', 'services-remuneres.delete',
            // Amendes
            'amendes.view', 'amendes.create', 'amendes.update', 'amendes.delete',
            // Immigrations
            'immigrations.view', 'immigrations.create', 'immigrations.update', 'immigrations.delete',
            // Paramétrage
            'parametrage.view', 'parametrage.create', 'parametrage.update', 'parametrage.delete',
            // Dashboard
            'dashboard.view',
            // Export
            'export.pdf', 'export.csv', 'import.data',
            // Audit
            'audit.view',
            // Notifications
            'notifications.send',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }

        // Créer les rôles et assigner les permissions
        $superAdmin = Role::create(['name' => 'super_admin', 'guard_name' => 'api']);
        $superAdmin->givePermissionTo(Permission::all());

        $admin = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $admin->givePermissionTo(Permission::all());

        $superviseur = Role::create(['name' => 'superviseur', 'guard_name' => 'api']);
        $superviseur->givePermissionTo([
            'personnels.view', 'infractions.view', 'accidents.view',
            'victimes.view', 'services-remuneres.view', 'amendes.view',
            'immigrations.view', 'parametrage.view', 'dashboard.view',
            'export.pdf', 'export.csv', 'audit.view', 'users.view',
        ]);

        $agent = Role::create(['name' => 'agent', 'guard_name' => 'api']);
        $agent->givePermissionTo([
            'infractions.view', 'infractions.create', 'infractions.update',
            'accidents.view', 'accidents.create', 'accidents.update',
            'victimes.view', 'victimes.create', 'victimes.update',
            'services-remuneres.view', 'services-remuneres.create',
            'amendes.view', 'amendes.create',
            'immigrations.view', 'immigrations.create',
            'dashboard.view',
        ]);
    }
}
