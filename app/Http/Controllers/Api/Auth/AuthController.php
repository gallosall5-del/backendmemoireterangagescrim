<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use OpenApi\Attributes as OA;

/**
 * Contrôleur d'authentification JWT.
 */
class AuthController extends ApiController
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['login']);
    }

    #[OA\Post(
        path: "/api/auth/login",
        summary: "Connexion utilisateur",
        description: "Authentifie l'utilisateur et retourne un token JWT",
        tags: ["Authentification"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "password"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email", example: "admin@gescrim.sn"),
                new OA\Property(property: "password", type: "string", format: "password", example: "password123")
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Connexion réussie",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean", example: true),
                new OA\Property(property: "message", type: "string", example: "Connexion réussie."),
                new OA\Property(property: "data", type: "object",
                    properties: [
                        new OA\Property(property: "access_token", type: "string"),
                        new OA\Property(property: "token_type", type: "string", example: "bearer"),
                        new OA\Property(property: "expires_in", type: "integer", example: 3600),
                        new OA\Property(property: "user", type: "object")
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Identifiants incorrects")]
    #[OA\Response(response: 422, description: "Erreur de validation")]
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ], [
            'email.required' => 'L\'email est obligatoire.',
            'email.email' => 'L\'email doit être valide.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Erreur de validation', 422, $validator->errors());
        }

        $credentials = $request->only('email', 'password');
        $ip          = $request->ip();
        $email       = $credentials['email'];

        // Vérifier le verrouillage (5 échecs en 15 minutes)
        $recentFailures = DB::table('login_attempts')
            ->where('email', $email)
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subMinutes(15))
            ->count();

        if ($recentFailures >= 5) {
            AuditLog::create([
                'user_id'    => null,
                'action'     => 'login_blocked',
                'model_type' => User::class,
                'model_id'   => null,
                'new_values' => ['email' => $email, 'failures' => $recentFailures],
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
            ]);
            return $this->errorResponse(
                'Compte temporairement verrouillé suite à trop de tentatives. Réessayez dans 15 minutes.',
                429
            );
        }

        // Vérifier que l'utilisateur est actif
        $user = User::where('email', $email)->first();
        if ($user && !$user->is_active) {
            return $this->errorResponse('Votre compte est désactivé. Contactez l\'administrateur.', 403);
        }

        if (!$token = JWTAuth::attempt($credentials)) {
            // Enregistrer l'échec
            DB::table('login_attempts')->insert([
                'email'        => $email,
                'ip_address'   => $ip,
                'success'      => false,
                'attempted_at' => now(),
            ]);

            AuditLog::create([
                'user_id'    => null,
                'action'     => 'login_failed',
                'model_type' => User::class,
                'model_id'   => null,
                'new_values' => ['email' => $email, 'failures' => $recentFailures + 1],
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
            ]);

            $remaining = 4 - $recentFailures;
            $msg = $remaining > 0
                ? "Identifiants incorrects. {$remaining} tentative(s) restante(s) avant verrouillage."
                : 'Identifiants incorrects. Compte verrouillé pour 15 minutes.';

            return $this->errorResponse($msg, 401);
        }

        // Succès — enregistrer et effacer les échecs précédents
        DB::table('login_attempts')->insert([
            'email'        => $email,
            'ip_address'   => $ip,
            'success'      => true,
            'attempted_at' => now(),
        ]);

        // Mettre à jour la dernière connexion
        $user = auth()->user();
        $user->update(['last_login_at' => now()]);

        // Journaliser la connexion réussie
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'model_type' => User::class,
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->respondWithToken($token);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Authentification"},
     *     summary="Déconnexion",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Déconnexion réussie", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=401, description="Non authentifié", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $user = auth()->user();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'logout',
            'model_type' => User::class,
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        auth()->logout();

        return $this->successResponse(null, 'Déconnexion réussie.');
    }

    /**
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     tags={"Authentification"},
     *     summary="Rafraîchir le token JWT",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Token rafraîchi", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=401, description="Token invalide", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     tags={"Authentification"},
     *     summary="Profil de l'utilisateur connecté",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Profil retourné", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=401, description="Non authentifié", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();
        $user->load(['service', 'roles', 'personnel']);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'telephone' => $user->telephone,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at,
            'service' => $user->service,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'personnel' => $user->personnel,
        ]);
    }

    /**
     * Changer le mot de passe
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Le mot de passe actuel est obligatoire.',
            'new_password.required' => 'Le nouveau mot de passe est obligatoire.',
            'new_password.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'new_password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Erreur de validation', 422, $validator->errors());
        }

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Le mot de passe actuel est incorrect.', 400);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'password_changed',
            'model_type' => User::class,
            'model_id'   => $user->id,
            'new_values' => ['message' => 'Mot de passe modifié'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse(null, 'Mot de passe modifié avec succès.');
    }

    /**
     * Formater la réponse avec le token JWT
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        $user = auth()->user();

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60, // En secondes
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
        ], 'Connexion réussie.');
    }
}
