<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    private string $secretKey;
    private float  $threshold;

    public function __construct()
    {
        $this->secretKey = config('services.recaptcha.secret_key', '');
        $this->threshold = (float) config('services.recaptcha.threshold', 0.5);
    }

    /**
     * Verify a reCAPTCHA v3 token.
     *
     * Returns:
     *   valid   => bool   — whether to allow the request
     *   score   => float  — 0.0 (bot) to 1.0 (human)
     *   action  => string — action name echoed by Google
     */
    public function verify(?string $token, ?string $ip = null, ?string $expectedAction = null): array
    {
        // Token vide ou pas de clé configurée → laisser passer
        if (empty($this->secretKey) || empty($token)) {
            return ['valid' => true, 'score' => 1.0, 'action' => $expectedAction ?? 'login'];
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://www.google.com/recaptcha/api/siteverify', array_filter([
                    'secret'   => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));

            $data = $response->json();

            if (empty($data['success'])) {
                return [
                    'valid'  => false,
                    'score'  => 0.0,
                    'action' => '',
                    'errors' => $data['error-codes'] ?? [],
                ];
            }

            $score  = (float) ($data['score'] ?? 0.0);
            $action = $data['action'] ?? '';

            // Action mismatch is a bot signal
            $valid = $score >= $this->threshold
                && (empty($expectedAction) || $action === $expectedAction);

            return ['valid' => $valid, 'score' => $score, 'action' => $action];

        } catch (\Throwable $e) {
            // Network failure → log but don't block the user
            Log::warning('reCAPTCHA API unreachable: ' . $e->getMessage());
            return ['valid' => true, 'score' => 0.5, 'action' => $expectedAction ?? '', 'network_error' => true];
        }
    }
}
