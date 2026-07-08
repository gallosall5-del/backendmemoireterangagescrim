<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class HealthController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'timestamp' => now()->toISOString(), 'build' => '92b0209']);
    }

    public function testMail(Request $request): JsonResponse
    {
        $to = $request->query('to', 'sallgallo125@gmail.com');
        $mailer = config('mail.default');
        $host   = config('mail.mailers.smtp.host');
        $port   = config('mail.mailers.smtp.port');
        $scheme = config('mail.mailers.smtp.scheme');
        $user   = config('mail.mailers.smtp.username');

        try {
            Mail::raw('Test GESCRIM mail from Railway — ' . now()->toISOString(), function ($m) use ($to) {
                $m->to($to)->subject('[GESCRIM] Test mail Railway');
            });
            return response()->json([
                'status'  => 'sent',
                'to'      => $to,
                'mailer'  => $mailer,
                'host'    => $host,
                'port'    => $port,
                'scheme'  => $scheme,
                'user'    => $user,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'mailer'  => $mailer,
                'host'    => $host,
                'port'    => $port,
                'scheme'  => $scheme,
                'user'    => $user,
            ], 500);
        }
    }
}
