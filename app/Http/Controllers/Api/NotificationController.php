<?php

namespace App\Http\Controllers\Api;

use App\Models\NotificationInterne;
use App\Models\User;
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

        $paginated = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        // Normalise chaque notification pour que le frontend lise title/read_at
        $paginated->getCollection()->transform(function ($n) {
            $n->title = $n->titre;
            $n->read_at = $n->is_read ? ($n->updated_at?->toISOString()) : null;
            return $n;
        });

        return $this->paginatedResponse($paginated);
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

    // Envoyer une notification (admin) — user_id optionnel : si absent, diffusion globale
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'title'   => 'required|string|max:255',
            'message' => 'required|string',
            'type'    => 'nullable|in:alert,info,warning,success,error',
            'canal'   => 'nullable|in:ecran,email,sms',
        ]);
        if ($validator->fails()) return $this->errorResponse('Erreur de validation', 422, $validator->errors());

        $base = [
            'titre'   => $request->title,
            'message' => $request->message,
            'type'    => $request->type ?? 'info',
            'canal'   => $request->canal ?? 'ecran',
            'is_read' => false,
        ];

        if ($request->filled('user_id')) {
            $notification = NotificationInterne::create(array_merge($base, ['user_id' => $request->user_id]));
            return $this->successResponse($notification, 'Notification envoyée.', 201);
        }

        // Diffusion globale : créer une entrée par utilisateur actif
        $users = User::where('is_active', true)->pluck('id');
        foreach ($users as $userId) {
            NotificationInterne::create(array_merge($base, ['user_id' => $userId]));
        }
        return $this->successResponse(null, "Notification diffusée à {$users->count()} utilisateur(s).", 201);
    }
}
