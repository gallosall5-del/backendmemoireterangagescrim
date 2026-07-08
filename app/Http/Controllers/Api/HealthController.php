<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class HealthController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'timestamp' => now()->toISOString(), 'build' => 'a9714c9-fix']);
    }

    public function testMail(Request $request): JsonResponse
    {
        $to = $request->query('to', 'sallgallo125@gmail.com');
        $mailer = config('mail.default');
        $host   = config('mail.mailers.smtp.host');
        $port   = config('mail.mailers.smtp.port');
        $scheme = config('mail.mailers.smtp.scheme');
        $user   = config('mail.mailers.smtp.username');

        // Test connectivité réseau brute
        $socketTest = 'not tested';
        $fp = @fsockopen($host, (int) $port, $errno, $errstr, 10);
        if ($fp) {
            $socketTest = 'connected';
            fclose($fp);
        } else {
            $socketTest = "failed: $errstr ($errno)";
        }

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
                'socket'  => $socketTest,
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
                'socket'  => $socketTest,
            ], 500);
        }
    }
}
