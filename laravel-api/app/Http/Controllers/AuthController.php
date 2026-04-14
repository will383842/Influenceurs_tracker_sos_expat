<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        // Revoke previous tokens to avoid accumulation
        $user->tokens()->delete();

        $token = $user->createToken('mc-session')->plainTextToken;

        $user->update(['last_login_at' => now()]);

        ActivityLog::create([
            'user_id'    => $user->id,
            'action'     => 'login',
            'created_at' => now(),
        ]);

        return response()->json([
            'token' => $token,
            'user'  => $user->only('id', 'name', 'email', 'role', 'contact_types', 'territories'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->tokens()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request)
    {
        return response()->json(
            $request->user()->only('id', 'name', 'email', 'role', 'contact_types', 'territories')
        );
    }
}
