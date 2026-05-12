<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionCard;
use Illuminate\Http\Request;

class ProductionCardController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'employeeName' => 'required|string',
            'section' => 'required|string',
        ]);

        $card = ProductionCard::create([
            'user_id' => $request->user()->id, // Récupère l'ID via le token
            'employee_name' => $request->employeeName,
            'section' => $request->section,
            'registre' => $request->registre,
            'selected_actions' => $request->selectedActions,
            'dossiers_notifies' => $request->dossiersNotifies,
            'dossiers_executes' => $request->dossiersExecutes,
            'montant_recouvre' => $request->montantRecouvre,
            'pv_positif' => $request->pvPositif,
            'pv_positif_count' => $request->pvPositifCount,
            'pv_negatif' => $request->pvNegatif,
            'pv_negatif_count' => $request->pvNegatifCount,
            'contrainte' => $request->contrainte,
            'dossiers_annulation' => $request->dossiersAnnulation,
            'dossiers_iskatat' => $request->dossiersIskatat,
            'montant_delegations' => $request->montantDelegations,
            'contre_personnes' => $request->contrePersonnes,
            'montant_personnes' => $request->montantPersonnes,
            'contre_societes' => $request->contreSocietes,
            'montant_societes' => $request->montantSocietes,
        ]);

        return response()->json([
            'message' => 'تم حفظ البطاقة بنجاح',
            'data' => $card
        ], 201);
    }
}
