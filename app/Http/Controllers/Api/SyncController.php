<?php

namespace App\Http\Controllers\Api;

use App\Models\Infraction;
use App\Models\Accident;
use App\Models\AmendePieceSaisie;
use App\Models\ImmigrationClandestine;
use App\Models\ServiceRemunere;
use App\Models\Victime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * Contrôleur pour la synchronisation offline.
 * Gère l'envoi par lot des données saisies hors ligne.
 */
class SyncController extends ApiController
{
    /**
     * @OA\Post(
     *     path="/api/sync/batch",
     *     tags={"Synchronisation"},
     *     summary="Synchroniser les données offline par lot",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Synchronisation réussie")
     * )
     */
    public function batch(Request $request): JsonResponse
    {
        $user = auth()->user();
        $scopeService = app(\App\Services\ScopeAccessService::class);

        $results = [
            'synced_infractions'      => [],
            'synced_accidents'        => [],
            'synced_amendes'          => [],
            'synced_immigrations'     => [],
            'synced_services_remuneres' => [],
            'errors'                  => [],
        ];

        DB::beginTransaction();
        try {
            // ── Infractions ──
            $infractionFields = ['type_infraction_id', 'service_id', 'annee', 'date', 'heure', 'lieu', 'commune_id', 'issue', 'type_drogue', 'unite', 'quantite', 'latitude', 'longitude', 'description', 'local_id', 'montant_amende', 'plaque_vehicule'];
            foreach ($request->input('infractions', []) as $data) {
                $data = Arr::only($data, array_merge($infractionFields, ['victimes']));
                if (isset($data['commune_id']) && !$scopeService->canAccessCommune($user, $data['commune_id'], 'write')) {
                    \App\Models\AuditLog::create([
                        'user_id'    => $user->id,
                        'action'     => 'sync_violation',
                        'model_type' => Infraction::class,
                        'new_values' => ['commune_id' => $data['commune_id']],
                        'ip_address' => $request->ip(),
                    ]);
                    $results['errors'][] = 'Accès territorial refusé (infraction commune ' . $data['commune_id'] . ')';
                    continue;
                }
                $localId = $data['local_id'] ?? null;
                $data['user_id']     = $user->id;
                $data['sync_status'] = 'synced';
                $data['annee']       = date('Y', strtotime($data['date'] ?? now()));
                unset($data['victimes']);
                $record = Infraction::updateOrCreate(
                    ['local_id' => $localId],
                    $data
                );
                $results['synced_infractions'][] = ['local_id' => $localId, 'id' => $record->id];

                // Victimes liées : isolation via savepoint pour éviter la race condition delete/insert
                DB::statement('SAVEPOINT sp_victimes_inf_' . $record->id);
                Victime::where('infraction_id', $record->id)->delete();
                foreach ($data['victimes'] ?? [] as $v) {
                    $v['infraction_id'] = $record->id;
                    unset($v['accident_id'], $v['local_id'], $v['parent_local_id'], $v['parent_type'], $v['sync_status']);
                    Victime::create($v);
                }
                DB::statement('RELEASE SAVEPOINT sp_victimes_inf_' . $record->id);
            }

            // ── Accidents ──
            $accidentFields = ['type', 'date', 'heure', 'lieu', 'commune_id', 'service_id', 'moyen', 'cause_probable', 'latitude', 'longitude', 'description', 'local_id'];
            foreach ($request->input('accidents', []) as $data) {
                $data = Arr::only($data, array_merge($accidentFields, ['victimes']));
                if (isset($data['commune_id']) && !$scopeService->canAccessCommune($user, $data['commune_id'], 'write')) {
                    \App\Models\AuditLog::create([
                        'user_id'    => $user->id,
                        'action'     => 'sync_violation',
                        'model_type' => Accident::class,
                        'new_values' => ['commune_id' => $data['commune_id']],
                        'ip_address' => $request->ip(),
                    ]);
                    $results['errors'][] = 'Accès territorial refusé (accident commune ' . $data['commune_id'] . ')';
                    continue;
                }
                $localId = $data['local_id'] ?? null;
                $data['user_id']     = $user->id;
                $data['sync_status'] = 'synced';
                unset($data['victimes']);
                $record = Accident::updateOrCreate(
                    ['local_id' => $localId],
                    $data
                );
                $results['synced_accidents'][] = ['local_id' => $localId, 'id' => $record->id];

                // Victimes liées : isolation via savepoint pour éviter la race condition delete/insert
                DB::statement('SAVEPOINT sp_victimes_acc_' . $record->id);
                Victime::where('accident_id', $record->id)->delete();
                foreach ($data['victimes'] ?? [] as $v) {
                    $v['accident_id'] = $record->id;
                    unset($v['infraction_id'], $v['local_id'], $v['parent_local_id'], $v['parent_type'], $v['sync_status']);
                    Victime::create($v);
                }
                DB::statement('RELEASE SAVEPOINT sp_victimes_acc_' . $record->id);
            }

            // ── Amendes ──
            $amendeFields = ['date', 'lieu', 'commune_id', 'service_id', 'montant', 'description', 'plaque_immatriculation', 'local_id'];
            foreach ($request->input('amendes', []) as $data) {
                $data = Arr::only($data, $amendeFields);
                if (isset($data['commune_id']) && !$scopeService->canAccessCommune($user, $data['commune_id'], 'write')) {
                    \App\Models\AuditLog::create([
                        'user_id'    => $user->id,
                        'action'     => 'sync_violation',
                        'model_type' => AmendePieceSaisie::class,
                        'new_values' => ['commune_id' => $data['commune_id']],
                        'ip_address' => $request->ip(),
                    ]);
                    $results['errors'][] = 'Accès territorial refusé (amende commune ' . $data['commune_id'] . ')';
                    continue;
                }
                $localId = $data['local_id'] ?? null;
                $data['user_id'] = $user->id;
                $record = AmendePieceSaisie::updateOrCreate(
                    ['local_id' => $localId],
                    $data
                );
                $results['synced_amendes'][] = ['local_id' => $localId, 'id' => $record->id];
            }

            // ── Immigrations ──
            $immigrationFields = ['date', 'lieu', 'commune_id', 'service_id', 'nombre_personnes', 'nombre_hommes', 'nombre_femmes', 'nombre_mineurs', 'observations', 'latitude', 'longitude', 'local_id'];
            foreach ($request->input('immigrations', []) as $data) {
                $data = Arr::only($data, $immigrationFields);
                if (isset($data['commune_id']) && !$scopeService->canAccessCommune($user, $data['commune_id'], 'write')) {
                    \App\Models\AuditLog::create([
                        'user_id'    => $user->id,
                        'action'     => 'sync_violation',
                        'model_type' => ImmigrationClandestine::class,
                        'new_values' => ['commune_id' => $data['commune_id']],
                        'ip_address' => $request->ip(),
                    ]);
                    $results['errors'][] = 'Accès territorial refusé (immigration commune ' . $data['commune_id'] . ')';
                    continue;
                }
                $localId = $data['local_id'] ?? null;
                $data['user_id'] = $user->id;
                $record = ImmigrationClandestine::updateOrCreate(
                    ['local_id' => $localId],
                    $data
                );
                $results['synced_immigrations'][] = ['local_id' => $localId, 'id' => $record->id];
            }

            // ── Services rémunérés ──
            $serviceRemFields = ['date', 'libelle', 'montant', 'service_id', 'commune_id', 'description', 'local_id'];
            foreach ($request->input('services_remuneres', []) as $data) {
                $data = Arr::only($data, $serviceRemFields);
                if (isset($data['commune_id']) && !$scopeService->canAccessCommune($user, $data['commune_id'], 'write')) {
                    \App\Models\AuditLog::create([
                        'user_id'    => $user->id,
                        'action'     => 'sync_violation',
                        'model_type' => ServiceRemunere::class,
                        'new_values' => ['commune_id' => $data['commune_id']],
                        'ip_address' => $request->ip(),
                    ]);
                    $results['errors'][] = 'Accès territorial refusé (service rémunéré commune ' . $data['commune_id'] . ')';
                    continue;
                }
                $localId = $data['local_id'] ?? null;
                $data['user_id'] = $user->id;
                $record = ServiceRemunere::updateOrCreate(
                    ['local_id' => $localId],
                    $data
                );
                $results['synced_services_remuneres'][] = ['local_id' => $localId, 'id' => $record->id];
            }

            DB::commit();
            return $this->successResponse($results, 'Synchronisation réussie.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Erreur de synchronisation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sync/status",
     *     tags={"Synchronisation"},
     *     summary="État de synchronisation",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Statut de sync")
     * )
     */
    public function status(): JsonResponse
    {
        $data = [
            'pending_infractions'  => Infraction::pending()->count(),
            'pending_accidents'    => Accident::pending()->count(),
            'synced_infractions'   => Infraction::where('sync_status', 'synced')->count(),
            'synced_accidents'     => Accident::where('sync_status', 'synced')->count(),
        ];
        return $this->successResponse($data);
    }
}
