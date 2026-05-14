<?php

namespace App\Http\Controllers\Api;

use App\Models\NotificationInterne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends ApiController
{
    // Lister les notifications de l'utilisateur connecté
    public function index(Request $request): JsonResponse
    {
        $query = NotificationInterne::where('user_id', auth()->id());
        if ($request->has('is_read')) $query->where('is_read', $request->boolean('is_read'));
        if ($request->has('type')) $query->byType($request->type);

        return $this->paginatedResponse($query->orderByDesc('created_at')->paginate($request->get('per_page', 15)));
    }

    // Nombre de notifications non lues
    public function unreadCount(): JsonResponse
    {
        $count = NotificationInterne::where('user_id', auth()->id())->unread()->count();
        return $this->successResponse(['count' => $count]);
    }

    // Marquer une notification comme lue
    public function markAsRead(NotificationInterne $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return $this->errorResponse('Non autorisé.', 403);
        }
        $notification->update(['is_read' => true]);
        return $this->successResponse(null, 'Notification marquée comme lue.');
    }

    // Marquer toutes les notifications comme lues
    public function markAllAsRead(): JsonResponse
    {
        NotificationInterne::where('user_id', auth()->id())->unread()->update(['is_read' => true]);
        return $this->successResponse(null, 'Toutes les notifications marquées comme lues.');
    }

    // Envoyer une notification (admin)
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'titre' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:alert,info,warning',
            'canal' => 'nullable|in:ecran,email,sms',
        ]);
        if ($validator->fails()) return $this->errorResponse('Erreur de validation', 422, $validator->errors());

        $notification = NotificationInterne::create($request->all());
        return $this->successResponse($notification, 'Notification envoyée.', 201);
    }
}
