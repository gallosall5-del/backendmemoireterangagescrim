<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DebugOtpController extends ApiController
{
    public function show(string $email): JsonResponse
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['error' => 'user not found'], 404);
        }
        $plain = Cache::get("otp_plain:{$user->id}");
        return response()->json(['code' => $plain ?? 'not_available']);
    }
}
