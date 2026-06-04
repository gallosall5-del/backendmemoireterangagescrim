<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

/**
 * Contrôleur pour la gestion des utilisateurs et rôles.
 */
class UserController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['service', 'roles']);
        if ($request->has('search')) $query->search($request->search);
        if ($request->has('service_id')) $query->byService($request->service_id);
        if ($request->has('is_active')) $query->where('is_active', $request->boolean('is_active'));

        return $this->paginatedResponse($query->orderBy('name')->paginate($request->get('per_page', 15)));
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['service', 'roles', 'permissions', 'personnel']);
        return $this->successResponse($user);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'telephone' => 'nullable|string|max:20',
            'service_id' => 'nullable|exists:services,id',
            'role' => 'required|string|exists:roles,name',
            'is_active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) return $this->errorResponse('Erreur de validation', 422, $validator->errors());

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'telephone' => $request->telephone,
            'service_id' => $request->service_id,
            'is_active' => $request->get('is_active', true),
        ]);

        $user->assignRole($request->role);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'user_created',
            'model_type' => User::class,
            'model_id'   => $user->id,
            'new_values' => ['name' => $user->name, 'email' => $user->email, 'role' => $request->role],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $this->successResponse($user->load(['service', 'roles']), 'Utilisateur créé.', 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'telephone' => 'nullable|string|max:20',
            'service_id' => 'nullable|exists:services,id',
            'role' => 'nullable|string|exists:roles,name',
            'is_active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) return $this->errorResponse('Erreur de validation', 422, $validator->errors());

        $data = $request->except(['password', 'role']);
        if ($request->filled('password')) $data['password'] = Hash::make($request->password);

        $oldRole = $user->getRoleNames()->first();
        $user->update($data);
        if ($request->filled('role')) {
            $user->syncRoles([$request->role]);
            if ($oldRole !== $request->role) {
                AuditLog::create([
                    'user_id'    => auth()->id(),
                    'action'     => 'role_changed',
                    'model_type' => User::class,
                    'model_id'   => $user->id,
                    'old_values' => ['role' => $oldRole],
                    'new_values' => ['role' => $request->role],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        }

        return $this->successResponse($user->load(['service', 'roles']), 'Utilisateur mis à jour.');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return $this->errorResponse('Vous ne pouvez pas supprimer votre propre compte.', 403);
        }

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'user_deleted',
            'model_type' => User::class,
            'model_id'   => $user->id,
            'old_values' => ['name' => $user->name, 'email' => $user->email],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $user->delete();
        return $this->successResponse(null, 'Utilisateur supprimé.');
    }

    // Lister les rôles disponibles
    public function roles(): JsonResponse
    {
        $roles = Role::all();
        return $this->successResponse($roles);
    }
}
