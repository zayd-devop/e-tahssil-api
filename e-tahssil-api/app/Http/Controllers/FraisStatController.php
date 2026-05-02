<?php

namespace App\Http\Controllers;

use App\Models\FraisStat;
use Illuminate\Http\Request;

class FraisStatController extends Controller
{
    public function index(Request $request)
    {
        // On récupère l'année demandée par React (par défaut 2026)
        $year = $request->query('year', '2026');

        // On cherche toutes les statistiques pour cette année
        $stats = FraisStat::where('year', $year)
                          ->orderBy('month', 'asc') // On trie par mois (01, 02, etc.)
                          ->get();

        return response()->json($stats);
    }
    public function store(Request $request)
    {
        // 1. Validation des données envoyées par React
        $validatedData = $request->validate([
            'month' => 'required|string|size:2',
            'year' => 'required|string|size:4',

            'extraits_dossiers' => 'nullable|integer|min:0',
            'extraits_montant' => 'nullable|numeric|min:0',

            'frais_dossiers' => 'nullable|integer|min:0',
            'frais_montant' => 'nullable|numeric|min:0',

            'assist_dossiers' => 'nullable|integer|min:0',
            'assist_montant' => 'nullable|numeric|min:0',

            'injonc_dossiers' => 'nullable|integer|min:0',
            'injonc_montant' => 'nullable|numeric|min:0',

            'titres_dossiers' => 'nullable|integer|min:0',
            'titres_montant' => 'nullable|numeric|min:0',
        ]);

        // Pour éviter les valeurs nulles, on les convertit en 0 si l'utilisateur n'a rien saisi
        $dataToSave = array_map(function($value) {
            return $value === null ? 0 : $value;
        }, $validatedData);

        // 2. Vérifier si les statistiques pour ce mois et cette année existent déjà
        $existingStat = FraisStat::where('month', $request->month)
                                 ->where('year', $request->year)
                                 ->first();

        if ($existingStat) {
            // Si ça existe déjà, on met à jour (Update)
            $existingStat->update($dataToSave);
            $message = 'تم تحديث الإحصائيات بنجاح'; // Mis à jour avec succès
        } else {
            // Sinon, on crée une nouvelle entrée (Create)
            FraisStat::create($dataToSave);
            $message = 'تم حفظ الإحصائيات بنجاح'; // Sauvegardé avec succès
        }

        // 3. Renvoyer une réponse de succès à React
        return response()->json([
            'status' => 'success',
            'message' => $message
        ], 200);
    }
}
