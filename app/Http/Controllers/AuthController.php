<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Credenciales inválidas',
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->uuid ?? (string) $user->id,
                'name' => $user->nombre,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:app_users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'nombre' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->uuid ?? (string) $user->id,
                'name' => $user->nombre,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->uuid ?? (string) $user->id,
            'name' => $user->nombre,
            'email' => $user->email,
            'avatar' => $user->avatar,
        ]);
    }

    public function google(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $googleResponse = \Illuminate\Support\Facades\Http::get(
            'https://oauth2.googleapis.com/tokeninfo',
            ['id_token' => $request->id_token]
        );

        if (!$googleResponse->ok()) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        $googleUser = $googleResponse->json();

        $user = User::where('firebase_uid', $googleUser['sub'])->first();

        if (!$user) {
            $user = User::where('email', $googleUser['email'])->first();
            if ($user) {
                $user->update(['firebase_uid' => $googleUser['sub']]);
            } else {
                $user = User::create([
                    'firebase_uid' => $googleUser['sub'],
                    'nombre' => $googleUser['name'] ?? 'Usuario',
                    'email' => $googleUser['email'],
                    'avatar' => $googleUser['picture'] ?? null,
                ]);
            }
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->uuid ?? (string) $user->id,
                'name' => $user->nombre,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ],
        ]);
    }
}
