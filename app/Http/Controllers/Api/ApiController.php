<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApiController extends Controller
{
    /**
     * Issue a plain-text API token for the authenticated user.
     * POST /api/auth/token  { email, password, device_name }
     */
    public function token(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        // Revoke any previous token with the same device name, then create a new one
        $user->tokens()->where('name', $request->device_name)->delete();
        $token = $user->createToken($request->device_name);

        return response()->json([
            'token_type' => 'Bearer',
            'token'      => $token->plainTextToken,
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Token révoqué.']);
    }
}
