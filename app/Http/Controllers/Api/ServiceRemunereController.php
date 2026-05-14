<?php

namespace App\Http\Controllers\Api;

use App\Models\ServiceRemunere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceRemunereController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = ServiceRemunere::with(['service', 'user']);
        if ($request->has('service_id')) $query->byService($request->service_id);
        if ($request->has('date_from') && $request->has('date_to')) $query->byDateRange($request->date_from, $request->date_to);

        return $this->paginatedResponse($query->orderByDesc('date')->paginate($request->get('per_page', 15)));
    }

    public function show(ServiceRemunere $serviceRemunere): JsonResponse
    {
        return $this->successResponse($serviceRemunere->load(['service', 'user']));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'libelle' => 'required|string|max:255',
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date',
            'montant' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        if ($validator->fails()) return $this->errorResponse('Erreur de validation', 422, $validator->errors());

        $data = $request->all();
        $data['user_id'] = auth()->id();
        $sr = ServiceRemunere::create($data);
        return $this->successResponse($sr->load('service'), 'Service rémunéré enregistré.', 201);
    }

    public function update(Request $request, ServiceRemunere $serviceRemunere): JsonResponse
    {
        $serviceRemunere->update($request->all());
        return $this->successResponse($serviceRemunere->load('service'), 'Mis à jour.');
    }

    public function destroy(ServiceRemunere $serviceRemunere): JsonResponse
    {
        $serviceRemunere->delete();
        return $this->successResponse(null, 'Supprimé.');
    }
}
