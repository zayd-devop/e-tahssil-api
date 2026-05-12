<?php

namespace App\Http\Controllers;

use App\Imports\SupplementaryFeeImport;
use App\Models\SupplementaryFee;
use Illuminate\Http\Request;
// use App\Imports\SupplementaryFeeImport; // À créer de la même manière que l'autre si tu veux l'import Excel
use Maatwebsite\Excel\Facades\Excel;

class SupplementaryFeeController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('year');

        // Remplace 'OutstandingDebt::query()' par 'SupplementaryFee::query()' dans l'autre contrôleur
        $query = SupplementaryFee::query();

        $query->when($year, function ($q) use ($year) {
            // 🔥 LE CORRECTIF EST ICI :
            // On cherche dans l'année de la feuille Excel OU on extrait l'année directement depuis le numéro de dossier !
            return $q->where(function ($subQuery) use ($year) {
                $subQuery->where('file_year', $year)
                         ->orWhere('collectionFileNumber', 'LIKE', $year . '/%')
                         ->orWhere('collectionFileNumber', 'LIKE', '%' . $year . '%');
            });
        });
        $debts =$query->orderBy('id', 'desc')->take(1500)->get();


        return response()->json($debts);
    }

    // Garde la même logique pour import() et store() que ton autre contrôleur...
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'collectionFileNumber' => 'required|string|unique:supplementary_fees',
            'fullName' => 'required|string',
            // Ajoute tes autres règles de validation ici plus tard...
        ]);

        $debt = SupplementaryFee::create($validatedData);

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
            Excel::import(new SupplementaryFeeImport, $request->file('file'));

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
