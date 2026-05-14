<?php

namespace App\Http\Controllers\Api;

use App\Models\AmendePieceSaisie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AmendePieceSaisieController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = AmendePieceSaisie::with(['service', 'user']);
        if ($request->has('type')) $query->byType($request->type);
        if ($request->has('service_id')) $query->byService($request->service_id);
        if ($request->has('date_from') && $request->has('date_to')) $query->byDateRange($request->date_from, $request->date_to);

        return $this->paginatedResponse($query->orderByDesc('date')->paginate($request->get('per_page', 15)));
    }

    public function show(AmendePieceSaisie $amendePieceSaisie): JsonResponse
    {
        return $this->successResponse($amendePieceSaisie->load(['service', 'user']));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:Amende,Pièce saisie',
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date',
            'montant' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        if ($validator->fails()) return $this->errorResponse('Erreur de validation', 422, $validator->errors());

        $data = $request->all();
        $data['user_id'] = auth()->id();
        $amende = AmendePieceSaisie::create($data);
        return $this->successResponse($amende->load('service'), 'Enregistré avec succès.', 201);
    }

    public function update(Request $request, AmendePieceSaisie $amendePieceSaisie): JsonResponse
    {
        $amendePieceSaisie->update($request->all());
        return $this->successResponse($amendePieceSaisie->load('service'), 'Mis à jour.');
    }

    public function destroy(AmendePieceSaisie $amendePieceSaisie): JsonResponse
    {
        $amendePieceSaisie->delete();
        return $this->successResponse(null, 'Supprimé.');
    }
}
