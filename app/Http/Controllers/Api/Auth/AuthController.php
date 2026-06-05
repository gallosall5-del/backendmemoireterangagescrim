<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\RecaptchaService;
use App\Services\TwoFactorService;
use App\Services\DeviceSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use OpenApi\Attributes as OA;

class AuthController extends ApiController
{
    public function __construct(
        private RecaptchaService    $recaptcha,
        private TwoFactorService    $twoFactor,
        private DeviceSessionService $deviceSession,
    ) {
        $this->middleware('auth:api')->except(['login', 'verify2fa']);
    }

    // ──────────────────────────────────────────────────────────────
    // LOGIN — Étape 1 : identifiants + reCAPTCHA
    // ──────────────────────────────────────────────────────────────

    #[OA\Post(
        path: "/api/auth/login",
        summary: "Connexion utilisateur (étape 1)",
        tags: ["Authentification"]
    )]
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'            => 'required|email',
            'password'         => 'required|string|min:6',
            'recaptcha_token'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Erreur de validation', 422, $validator->errors());
        }

        $ip    = $request->ip() ?? '0.0.0.0';
        $email = $request->email;

        // ── reCAPTCHA ──
        $captchaToken = $request->input('recaptcha_token', '');
        $captchaResult = $this->recaptcha->verify($captchaToken, $ip, 'login');

        if (!$captchaResult['valid']) {
            AuditLog::create([
                'user_id'    => null,
                'action'     => 'captcha_failed',
                'model_type' => User::class,
                'model_id'   => null,
                'new_values' => [
                    'email' => $email,
                    'score' => $captchaResult['score'] ?? 0,
                ],
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
            ]);
            return $this->errorResponse('Vérification anti-bot échouée. Réessayez.', 422);
        }

        // ── Verrouillage (5 échecs en 15 min) ──
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

        // ── Compte actif ──
        $user = User::where('email', $email)->first();
        if ($user && !$user->is_active) {
            return $this->errorResponse('Votre compte est désactivé. Contactez l\'administrateur.', 403);
        }

        // ── Vérification credentials ──
        if (!$token = JWTAuth::attempt($request->only('email', 'password'))) {
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
                'new_values' => [
                    'email'         => $email,
                    'failures'      => $recentFailures + 1,
                    'captcha_score' => $captchaResult['score'] ?? null,
                ],
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
            ]);

            $remaining = 4 - $recentFailures;
            $msg = $remaining > 0
                ? "Identifiants incorrects. {$remaining} tentative(s) restante(s) avant verrouillage."
                : 'Identifiants incorrects. Compte verrouillé pour 15 minutes.';

            return $this->errorResponse($msg, 401);
        }

        $user = JWTAuth::setToken($token)->toUser();

        // ── 2FA activé : retourner un ticket temporaire, pas de JWT complet ──
        $deviceId = $request->header('X-Device-Id')
            ?? $this->deviceSession->generateDeviceId($request);
        $deviceInfo = $this->deviceSession->verify($user, $request, $deviceId);

        $require2fa = $user->is_2fa_enabled
            || (!$deviceInfo['trusted'] && $user->is_2fa_enabled);

        if ($require2fa) {
            // Invalider le token immédiatement — on ne le donne pas encore
            try { JWTAuth::invalidate($token); } catch (\Throwable) {}

            // Envoyer le code OTP par email
            $this->twoFactor->sendOtp($user);

            // Stocker un ticket de 5 minutes en cache
            $ticket = Str::random(64);
            Cache::put("2fa_ticket:{$ticket}", [
                'user_id'       => $user->id,
                'device_id'     => $deviceId,
                'ip'            => $ip,
                'captcha_score' => $captchaResult['score'] ?? null,
            ], now()->addMinutes(5));

            return $this->successResponse([
                'requires_2fa'      => true,
                'two_factor_ticket' => $ticket,
                'email_hint'        => $this->maskEmail($user->email),
            ], 'Un code de vérification a été envoyé à votre adresse email.');
        }

        // ── Pas de 2FA : finaliser la connexion ──
        return $this->finalizeLogin($user, $token, $request, $deviceId, $captchaResult['score'] ?? null);
    }

    // ──────────────────────────────────────────────────────────────
    // VERIFY 2FA — Étape 2 : vérifier le code OTP
    // ──────────────────────────────────────────────────────────────

    public function verify2fa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ticket' => 'required|string',
            'code'   => 'required|string|size:6|regex:/^\d{6}$/',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Données invalides.', 422, $validator->errors());
        }

        $ticket    = $request->input('ticket');
        $cacheKey  = "2fa_ticket:{$ticket}";
        $ticketData = Cache::get($cacheKey);

        if (!$ticketData) {
            return $this->errorResponse('Ticket expiré ou invalide. Recommencez la connexion.', 401);
        }

        $user = User::find($ticketData['user_id']);
        if (!$user) {
            return $this->errorResponse('Utilisateur introuvable.', 404);
        }

        // Compteur d'échecs OTP (5 max en 10 min)
        $otpFailKey = "2fa_fails:{$user->id}";
        $otpFails   = (int) Cache::get($otpFailKey, 0);

        if ($otpFails >= 5) {
            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => '2fa_blocked',
                'model_type' => User::class,
                'model_id'   => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return $this->errorResponse('Trop de tentatives OTP. Reconnectez-vous.', 429);
        }

        if (!$this->twoFactor->verify($user, $request->input('code'))) {
            Cache::put($otpFailKey, $otpFails + 1, now()->addMinutes(10));

            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => '2fa_failed',
                'model_type' => User::class,
                'model_id'   => $user->id,
                'new_values' => ['attempts' => $otpFails + 1],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->errorResponse('Code OTP incorrect.', 401);
        }

        // OTP valide : consommer le ticket et le compteur d'échecs
        Cache::forget($cacheKey);
        Cache::forget($otpFailKey);

        // Générer le vrai JWT
        $token = JWTAuth::fromUser($user);

        return $this->finalizeLogin(
            $user,
            $token,
            $request,
            $ticketData['device_id'],
            $ticketData['captcha_score'] ?? null,
            true
        );
    }

    // ──────────────────────────────────────────────────────────────
    // LOGOUT
    // ──────────────────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        $user = auth()->user();

        $this->deviceSession->revokeAll($user);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'logout',
            'model_type' => User::class,
            'model_id'   => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        auth()->logout();

        return $this->successResponse(null, 'Déconnexion réussie.')
            ->withoutCookie('jwt_token');
    }

    // ──────────────────────────────────────────────────────────────
    // REFRESH
    // ──────────────────────────────────────────────────────────────

    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth()->refresh());
    }

    // ──────────────────────────────────────────────────────────────
    // ME
    // ──────────────────────────────────────────────────────────────

    public function me(): JsonResponse
    {
        $user = auth()->user();
        $user->load(['service', 'roles', 'personnel']);

        return $this->successResponse([
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'telephone'        => $user->telephone,
            'is_active'        => $user->is_active,
            'is_2fa_enabled'   => $user->is_2fa_enabled,
            'last_login_at'    => $user->last_login_at,
            'service_id'       => $user->service_id,
            'service'          => $user->service,
            'read_scope_type'  => $user->read_scope_type?->value,
            'read_scope_id'    => $user->read_scope_id,
            'write_scope_type' => $user->write_scope_type?->value,
            'write_scope_id'   => $user->write_scope_id,
            'roles'            => $user->getRoleNames(),
            'permissions'      => $user->getAllPermissions()->pluck('name'),
            'personnel'        => $user->personnel,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // CHANGER MOT DE PASSE
    // ──────────────────────────────────────────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
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

    // ──────────────────────────────────────────────────────────────
    // 2FA — Activation / Désactivation
    // ──────────────────────────────────────────────────────────────

    /**
     * POST /api/auth/2fa/setup
     * Envoie un code OTP par email pour confirmer l'activation du 2FA.
     */
    public function setup2fa(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->is_2fa_enabled) {
            return $this->errorResponse('La double authentification est déjà activée.', 409);
        }

        $this->twoFactor->sendOtp($user);

        return $this->successResponse([
            'email_hint' => $this->maskEmail($user->email),
        ], 'Un code de vérification a été envoyé à votre adresse email.');
    }

    /**
     * POST /api/auth/2fa/enable
     * Confirme le code reçu par email et active le 2FA.
     */
    public function enable2fa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6|regex:/^\d{6}$/',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Code invalide.', 422, $validator->errors());
        }

        $user = auth()->user();

        if (!$this->twoFactor->verify($user, $request->input('code'))) {
            return $this->errorResponse('Code incorrect ou expiré.', 401);
        }

        $this->twoFactor->enable($user);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => '2fa_enabled',
            'model_type' => User::class,
            'model_id'   => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse(null, 'Double authentification activée avec succès.');
    }

    /** POST /api/auth/2fa/disable — désactive 2FA après vérification du mot de passe */
    public function disable2fa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Mot de passe requis.', 422);
        }

        $user = auth()->user();

        if (!Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Mot de passe incorrect.', 401);
        }

        // Admins ne peuvent pas désactiver 2FA si c'est obligatoire
        if ($user->hasRole(['super_admin', 'admin'])) {
            return $this->errorResponse(
                'Les administrateurs ne peuvent pas désactiver la double authentification.',
                403
            );
        }

        $this->twoFactor->disable($user);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => '2fa_disabled',
            'model_type' => User::class,
            'model_id'   => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->successResponse(null, 'Double authentification désactivée.');
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PRIVÉS
    // ──────────────────────────────────────────────────────────────

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email);
        $masked = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3));
        return $masked . '@' . $domain;
    }

    private function finalizeLogin(
        User    $user,
        string  $token,
        Request $request,
        string  $deviceId,
        ?float  $captchaScore = null,
        bool    $via2fa = false
    ): JsonResponse {
        $ip = $request->ip() ?? '0.0.0.0';

        // Enregistrer la session device
        $this->deviceSession->register($user, $request, $deviceId);

        // Journaliser le login
        DB::table('login_attempts')->insert([
            'email'        => $user->email,
            'ip_address'   => $ip,
            'success'      => true,
            'attempted_at' => now(),
        ]);

        $user->update(['last_login_at' => now()]);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => $via2fa ? '2fa_success' : 'login',
            'model_type' => User::class,
            'model_id'   => $user->id,
            'new_values' => array_filter([
                'captcha_score' => $captchaScore,
                'device_id'     => $deviceId,
                'via_2fa'       => $via2fa ?: null,
            ]),
            'ip_address' => $ip,
            'user_agent' => $request->userAgent(),
        ]);

        return $this->respondWithToken($token, $user, $deviceId);
    }

    protected function respondWithToken(string $token, ?User $userModel = null, ?string $deviceId = null): JsonResponse
    {
        $user   = $userModel ?? auth()->user();
        $ttlSec = config('jwt.ttl') * 60;

        $user->load('service');

        $response = $this->successResponse([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $ttlSec,
            'device_id'    => $deviceId,
            'user' => [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'service_id'       => $user->service_id,
                'service'          => $user->service,
                'read_scope_type'  => $user->read_scope_type?->value,
                'read_scope_id'    => $user->read_scope_id,
                'write_scope_type' => $user->write_scope_type?->value,
                'write_scope_id'   => $user->write_scope_id,
                'is_2fa_enabled'   => $user->is_2fa_enabled,
                'roles'            => $user->getRoleNames(),
            ],
        ], 'Connexion réussie.');

        $response->cookie(
            'jwt_token',
            $token,
            config('jwt.ttl'),
            '/',
            null,
            config('app.env') === 'production',
            true,   // HttpOnly
            false,
            'Lax'
        );

        if ($deviceId) {
            $response->cookie(
                'device_id',
                $deviceId,
                60 * 24 * 365, // 1 an
                '/',
                null,
                config('app.env') === 'production',
                false, // lisible par JS (pas sensible)
                false,
                'Lax'
            );
        }

        return $response;
    }
}
