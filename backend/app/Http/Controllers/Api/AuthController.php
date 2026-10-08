<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->where('name', 'admin')->delete();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'token' => $user->createToken('admin')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->tokens()->where('name', 'admin')->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->admin($request));
    }

    private function admin(Request $request): array
    {
        return [
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ];
    }
}
