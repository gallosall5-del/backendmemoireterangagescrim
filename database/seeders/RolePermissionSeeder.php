<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'personnels.view', 'personnels.create', 'personnels.update', 'personnels.delete',
            'infractions.view', 'infractions.create', 'infractions.update', 'infractions.delete',
            'accidents.view', 'accidents.create', 'accidents.update', 'accidents.delete',
            'victimes.view', 'victimes.create', 'victimes.update', 'victimes.delete',
            'services-remuneres.view', 'services-remuneres.create', 'services-remuneres.update', 'services-remuneres.delete',
            'amendes.view', 'amendes.create', 'amendes.update', 'amendes.delete',
            'immigrations.view', 'immigrations.create', 'immigrations.update', 'immigrations.delete',
            'parametrage.view', 'parametrage.create', 'parametrage.update', 'parametrage.delete',
            'dashboard.view',
            'export.pdf', 'export.csv', 'import.data',
            'audit.view',
            'notifications.send',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Admin : toutes les permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions(Permission::all());

        // Gestionnaire : toutes les permissions sauf création admin/gestionnaire
        $gestionnaire = Role::firstOrCreate(['name' => 'gestionnaire', 'guard_name' => 'api']);
        $gestionnaire->syncPermissions([
            'users.view', 'users.create', 'users.update', 'users.delete',
            'personnels.view', 'personnels.create', 'personnels.update', 'personnels.delete',
            'infractions.view', 'infractions.create', 'infractions.update', 'infractions.delete',
            'accidents.view', 'accidents.create', 'accidents.update', 'accidents.delete',
            'victimes.view', 'victimes.create', 'victimes.update', 'victimes.delete',
            'services-remuneres.view', 'services-remuneres.create', 'services-remuneres.update', 'services-remuneres.delete',
            'amendes.view', 'amendes.create', 'amendes.update', 'amendes.delete',
            'immigrations.view', 'immigrations.create', 'immigrations.update', 'immigrations.delete',
            'parametrage.view', 'parametrage.create', 'parametrage.update', 'parametrage.delete',
            'dashboard.view',
            'export.pdf', 'export.csv', 'import.data',
            'audit.view',
            'notifications.send',
        ]);

        // Agent : saisie terrain uniquement (mobile)
        $agent = Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'api']);
        $agent->syncPermissions([
            'personnels.view',
            'infractions.view', 'infractions.create', 'infractions.update',
            'accidents.view', 'accidents.create', 'accidents.update',
            'victimes.view', 'victimes.create', 'victimes.update',
            'amendes.view', 'amendes.create',
            'immigrations.view', 'immigrations.create',
            'dashboard.view',
            'parametrage.view',
        ]);
    }
}
