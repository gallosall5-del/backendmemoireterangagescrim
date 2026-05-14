<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur pour la consultation des logs d'audit.
 */
class AuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        if ($request->has('user_id')) $query->byUser($request->user_id);
        if ($request->has('action')) $query->byAction($request->action);
        if ($request->has('model_type')) $query->byModel($request->model_type);
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        return $this->paginatedResponse(
            $query->orderByDesc('created_at')->paginate($request->get('per_page', 50))
        );
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $auditLog->load('user');
        return $this->successResponse($auditLog);
    }
}
