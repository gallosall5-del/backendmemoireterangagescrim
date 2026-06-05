<?php

namespace App\Http\Controllers\Api;

use App\Models\Infraction;
use App\Models\Accident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     *     description="Envoie en une seule requête toutes les infractions et accidents saisis en mode hors-ligne.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="infractions", type="array", @OA\Items(ref="#/components/schemas/Infraction")),
     *             @OA\Property(property="accidents", type="array", @OA\Items(ref="#/components/schemas/Accident"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Synchronisation réussie",
     *         @OA\JsonContent(@OA\Property(property="infractions_synced", type="integer"),
     *                         @OA\Property(property="accidents_synced", type="integer"))
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function batch(Request $request): JsonResponse
    {
        $user = auth()->user();
        $scopeService = app(\App\Services\ScopeAccessService::class);

        $validator = Validator::make($request->all(), [
            'infractions' => 'nullable|array',
            'infractions.*.type_infraction_id' => 'required|exists:type_infractions,id',
            'infractions.*.service_id' => 'required|exists:services,id',
            'infractions.*.date' => 'required|date',
            'infractions.*.lieu' => 'required|string',
            'infractions.*.commune_id' => 'required|exists:communes,id',
            'infractions.*.issue' => 'required|in:Constatée,Déférée',
            'accidents' => 'nullable|array',
            'accidents.*.type' => 'required|in:matériel,corporel,mortel',
            'accidents.*.date' => 'required|date',
            'accidents.*.lieu' => 'required|string',
            'accidents.*.commune_id' => 'required|exists:communes,id',
            'accidents.*.service_id' => 'required|exists:services,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Erreur de validation', 422, $validator->errors());
        }

        $results = ['infractions_synced' => 0, 'accidents_synced' => 0, 'errors' => []];

        DB::beginTransaction();
        try {
            // Synchroniser les infractions
            if ($request->has('infractions')) {
                foreach ($request->infractions as $index => $data) {
                    if (isset($data['commune_id']) && !$scopeService->canAccessCommune($user, $data['commune_id'], 'write')) {
                        \App\Models\AuditLog::create([
                            'user_id' => $user->id,
                            'action' => 'sync_violation',
                            'model_type' => Infraction::class,
                            'new_values' => ['commune_id' => $data['commune_id']],
                            'ip_address' => $request->ip(),
                        ]);
                        throw new \Exception('Accès territorial refusé pour une infraction en commune ' . $data['commune_id']);
                    }
                    
                    $data['user_id'] = $user->id;
                    $data['sync_status'] = 'synced';
                    $data['annee'] = date('Y', strtotime($data['date']));
                    Infraction::create($data);
                    $results['infractions_synced']++;
                }
            }

            // Synchroniser les accidents
            if ($request->has('accidents')) {
                foreach ($request->accidents as $data) {
                    if (isset($data['commune_id']) && !$scopeService->canAccessCommune($user, $data['commune_id'], 'write')) {
                        \App\Models\AuditLog::create([
                            'user_id' => $user->id,
                            'action' => 'sync_violation',
                            'model_type' => Accident::class,
                            'new_values' => ['commune_id' => $data['commune_id']],
                            'ip_address' => $request->ip(),
                        ]);
                        throw new \Exception('Accès territorial refusé pour un accident en commune ' . $data['commune_id']);
                    }

                    $data['user_id'] = $user->id;
                    $data['sync_status'] = 'synced';
                    Accident::create($data);
                    $results['accidents_synced']++;
                }
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
     *     description="Retourne le nombre d'éléments en attente et synchonisés.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Statut de sync")
     * )
     */
    public function status(): JsonResponse
    {
        $data = [
            'pending_infractions' => Infraction::pending()->count(),
            'pending_accidents'   => Accident::pending()->count(),
            'synced_infractions'  => Infraction::where('sync_status', 'synced')->count(),
            'synced_accidents'    => Accident::where('sync_status', 'synced')->count(),
        ];
        return $this->successResponse($data);
    }
}
