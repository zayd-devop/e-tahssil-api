<?php

namespace App\Http\Controllers;

use App\Imports\OutstandingDebtImport;
use App\Models\OutstandingDebt;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;



class OutstandingDebtController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // 1. On récupère le paramètre 'year' envoyé par React
        $year = $request->query('year');

        // 2. On construit la requête avec Eloquent
        $query = OutstandingDebt::query();

        // 3. Si une année est spécifiée et n'est pas vide, on ajoute le filtre WHERE
        // On utilise 'when' pour garder un code propre et fluide
        $query->when($year, function ($q) use ($year) {
            return $q->where('file_year', $year);
        });

        // 4. On trie par ID décroissant pour voir les derniers ajouts en premier
        $debts = $query->orderBy('id', 'desc')->get();

        // 5. On renvoie la réponse en JSON
        return response()->json($debts);
    }

    // Tu pourras remplir la méthode store() plus tard quand tu feras ton bouton "Ajouter"
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'collectionFileNumber' => 'required|string|unique:outstanding_debts',
            'fullName' => 'required|string',
            // Ajoute tes autres règles de validation ici plus tard...
        ]);

        $debt = OutstandingDebt::create($validatedData);

        return response()->json($debt, 201);
    }

   public function import(Request $request)
    {
        // 1. 🚀 ASTUCE DE PRO : On donne un temps infini et une mémoire illimitée à PHP juste pour cet import
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        // 2. On valide la présence du fichier
        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            // 3. On lance l'importation avec la classe qu'on a créée
            Excel::import(new OutstandingDebtImport, $request->file('file'));

            return response()->json(['message' => 'تم استيراد الملف بنجاح'], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur Importation Excel : ' . $e->getMessage());
            // Si une autre erreur survient, on la renvoie proprement pour que React puisse la lire
            return response()->json([
                'message' => 'Erreur lors de l\'importation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
