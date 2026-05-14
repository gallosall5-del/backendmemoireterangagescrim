<?php

namespace App\Http\Controllers\Api;

use App\Models\Victime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Contrôleur CRUD pour les victimes et impliqués.
 */
class VictimeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Victime::with(['infraction', 'accident']);

        if ($request->has('infraction_id')) {
            $query->where('infraction_id', $request->infraction_id);
        }
        if ($request->has('accident_id')) {
            $query->where('accident_id', $request->accident_id);
        }
        if ($request->has('nationalite')) {
            $query->where('nationalite', $request->nationalite);
        }

        $victimes = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($victimes);
    }

    public function show(Victime $victime): JsonResponse
    {
        $victime->load(['infraction.typeInfraction', 'accident']);
        return $this->successResponse($victime);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'no_cin_passeport' => 'nullable|string|max:50',
            'sexe' => 'nullable|in:M,F',
            'age' => 'nullable|integer|min:0|max:150',
            'nationalite' => 'nullable|in:Sénégalaise,Étrangère',
            'infraction_id' => 'nullable|exists:infractions,id',
            'accident_id' => 'nullable|exists:accidents,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Erreur de validation', 422, $validator->errors());
        }

        // Au moins un lien (infraction ou accident) doit être fourni
        if (!$request->infraction_id && !$request->accident_id) {
            return $this->errorResponse('La victime doit être liée à une infraction ou un accident.', 422);
        }

        $victime = Victime::create($request->all());

        return $this->successResponse($victime, 'Victime enregistrée avec succès.', 201);
    }

    public function update(Request $request, Victime $victime): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'no_cin_passeport' => 'nullable|string|max:50',
            'sexe' => 'nullable|in:M,F',
            'age' => 'nullable|integer|min:0|max:150',
            'nationalite' => 'nullable|in:Sénégalaise,Étrangère',
            'infraction_id' => 'nullable|exists:infractions,id',
            'accident_id' => 'nullable|exists:accidents,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Erreur de validation', 422, $validator->errors());
        }

        $victime->update($request->all());

        return $this->successResponse($victime, 'Victime mise à jour avec succès.');
    }

    public function destroy(Victime $victime): JsonResponse
    {
        $victime->delete();
        return $this->successResponse(null, 'Victime supprimée avec succès.');
    }
}
