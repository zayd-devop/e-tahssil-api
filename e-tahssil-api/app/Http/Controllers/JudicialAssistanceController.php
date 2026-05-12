<?php

namespace App\Http\Controllers;

use App\Models\JudicialAssistance;
use App\Imports\JudicialAssistanceImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class JudicialAssistanceController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('year');
        $query = JudicialAssistance::query();

        $query->when($year, function ($q) use ($year) {
            return $q->where(function ($subQuery) use ($year) {
                $subQuery->where('file_year', $year)
                         ->orWhere('collectionFileNumber', 'LIKE', $year . '/%')
                         ->orWhere('collectionFileNumber', 'LIKE', '%' . $year . '%');
            });
        });

        // Limite à 1500 pour une vitesse maximale côté Frontend
        $debts = $query->orderBy('id', 'desc')->take(1500)->get();

        return response()->json($debts);
    }

    public function import(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            Excel::import(new JudicialAssistanceImport, $request->file('file'));
            return response()->json(['message' => 'تم استيراد الملف بنجاح'], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur Importation Excel : ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors de l\'importation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
