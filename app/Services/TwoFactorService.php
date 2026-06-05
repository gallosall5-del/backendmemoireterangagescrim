<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class TwoFactorService
{
    private int $expiresInMinutes = 5;
    private int $codeLength       = 6;

    /**
     * Générer un code OTP à 6 chiffres, le stocker en cache et l'envoyer par email.
     */
    public function sendOtp(User $user): void
    {
        $code = $this->generateCode();

        // Stocker en cache (clé unique par user)
        Cache::put(
            $this->cacheKey($user->id),
            bcrypt($code), // stocké hashé
            now()->addMinutes($this->expiresInMinutes)
        );

        Mail::to($user->email)->send(new OtpMail($code, $user->name, $this->expiresInMinutes));
    }

    /**
     * Vérifier le code OTP saisi par l'utilisateur.
     */
    public function verify(User $user, string $code): bool
    {
        $hashed = Cache::get($this->cacheKey($user->id));

        if (!$hashed) {
            return false; // expiré ou jamais envoyé
        }

        if (!password_verify($code, $hashed)) {
            return false;
        }

        // Consommer le code (usage unique)
        Cache::forget($this->cacheKey($user->id));
        return true;
    }

    /**
     * Activer le 2FA pour un utilisateur (flag en base uniquement).
     * L'email est déjà vérifié — pas besoin de secret TOTP.
     */
    public function enable(User $user): void
    {
        $user->update([
            'is_2fa_enabled'          => true,
            'two_factor_secret'       => null,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Désactiver le 2FA.
     */
    public function disable(User $user): void
    {
        $user->update([
            'is_2fa_enabled'          => false,
            'two_factor_secret'       => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), $this->codeLength, '0', STR_PAD_LEFT);
    }

    private function cacheKey(int $userId): string
    {
        return "otp_code:{$userId}";
    }
}
