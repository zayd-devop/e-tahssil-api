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
    public function index()
    {
        // Récupère toutes les dettes et les renvoie au format JSON pour React
        $debts = OutstandingDebt::all();

        return response()->json($debts, 200);
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
    // On vérifie qu'un fichier a bien été envoyé
    $request->validate([
        'file' => 'required|mimes:xlsx,xls,csv|max:10240', // Max 10MB
    ]);

    try {
        // La magie opère ici : Laravel lit l'Excel et l'insère en base
        Excel::import(new OutstandingDebtImport, $request->file('file'));

        return response()->json(['message' => 'Importation réussie'], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Erreur lors de l\'importation', 'error' => $e->getMessage()], 500);
    }
}
}
