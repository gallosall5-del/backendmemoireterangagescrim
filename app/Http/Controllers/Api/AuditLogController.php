<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur pour la consultation des logs d'audit.
 */
class AuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $me          = auth()->user();
        $currentRole = $me->getRoleNames()->first() ?? '';

        $query = AuditLog::with('user');

        // Restreindre les logs selon la portée territoriale
        if ($currentRole !== 'administrateur') {
            $visibleUserIds = match ($currentRole) {
                'gestionnaire' => User::whereHas('roles', fn($q) => $q->whereIn('name', ['gestionnaire', 'agent']))
                                ->where(function ($q) use ($me) {
                                    $q->whereHas('service', function ($sq) use ($me) {
                                        $sq->whereHas('commune.departement', fn($dq) => $dq->where('region_id', $me->read_scope_id));
                                    })->orWhere(function ($sq) use ($me) {
                                        $sq->where('read_scope_type', 'region')->where('read_scope_id', $me->read_scope_id);
                                    });
                                })->pluck('id'),
                default => collect([$me->id]),
            };

            $query->whereIn('user_id', $visibleUserIds);
        }

        if ($request->has('user_id')) $query->byUser($request->user_id);
        if ($request->has('action')) $query->byAction($request->action);
        if ($request->has('model_type')) $query->byModel($request->model_type);
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        return $this->paginatedResponse(
            $query->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 50), 100))
        );
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('user');
        return $this->successResponse($auditLog);
    }
}
