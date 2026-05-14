<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Commune;
use App\Models\Service;

/**
 * Seeder pour les services DSP dans les principales communes.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        // Types de services avec leurs descriptions
        $types = [
            'CC' => 'Commissariat Central',
            'CA' => 'Commissariat d\'Arrondissement',
            'PP' => 'Poste de Police',
            'CU' => 'Commissariat Urbain',
            'CS' => 'Commissariat Spécial',
        ];

        // Créer des services dans les principales communes
        $communesPrincipales = Commune::whereIn('nom', [
            'Dakar-Plateau', 'Médina', 'Grand-Dakar', 'Parcelles Assainies',
            'Guédiawaye', 'Pikine Nord', 'Rufisque Nord',
            'Thiès Nord', 'Mbour', 'Saint-Louis', 'Kaolack',
            'Ziguinchor', 'Tambacounda', 'Louga', 'Kolda',
        ])->get();

        foreach ($communesPrincipales as $commune) {
            // Un CC pour chaque commune principale
            Service::create([
                'nom' => "Commissariat Central de {$commune->nom}",
                'type' => 'CC',
                'commune_id' => $commune->id,
                'telephone' => '+221 33 ' . rand(800, 899) . ' ' . rand(10, 99) . ' ' . rand(10, 99),
            ]);

            // Un CA et un PP pour les grandes villes
            if (in_array($commune->nom, ['Dakar-Plateau', 'Médina', 'Grand-Dakar', 'Thiès Nord', 'Saint-Louis', 'Kaolack'])) {
                Service::create([
                    'nom' => "Commissariat d'Arrondissement de {$commune->nom}",
                    'type' => 'CA',
                    'commune_id' => $commune->id,
                ]);

                Service::create([
                    'nom' => "Poste de Police de {$commune->nom}",
                    'type' => 'PP',
                    'commune_id' => $commune->id,
                ]);
            }
        }
    }
}
