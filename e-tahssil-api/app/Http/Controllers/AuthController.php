<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Méthode de connexion (Login)
    public function login(Request $request)
    {
        // 1. Validation des données
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // 2. Recherche de l'utilisateur uniquement par son email
        $user = User::where('email', $request->email)->first();

        // 3. Vérification des identifiants
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects.'
            ], 401);
        }

        // 4. Création du Token (Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        // 5. Réponse
        return response()->json([
            'message' => 'Connexion réussie',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user // Retourne l'utilisateur simple sans relations admin/clerk
        ]);
    }

    // Méthode de déconnexion (Logout)
    public function logout(Request $request)
    {
        // Supprime le token actuel
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }

    // Récupérer l'utilisateur authentifié
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
