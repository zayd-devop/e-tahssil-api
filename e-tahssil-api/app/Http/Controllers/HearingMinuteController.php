<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HearingMinute;
use App\Imports\HearingMinutesImport;
use Maatwebsite\Excel\Facades\Excel;

class HearingMinuteController extends Controller
{
    /**
     * Importation du fichier Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            Excel::import(new HearingMinutesImport(), $request->file('file'));

            // 👇 NOUVEAU FILTRE ROBUSTE POUR L'IMPORT 👇
            $updatedData = HearingMinute::whereNotNull('result')
                ->whereRaw("TRIM(result) != ?", [''])
                ->whereRaw("TRIM(result) != ?", ['?'])
                ->whereRaw("TRIM(result) != ?", ['؟'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'message' => 'تم استيراد البيانات بنجاح',
                'data' => $updatedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء الاستيراد: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupération initiale des données
     */
    public function index()
    {
        // 👇 LE MÊME FILTRE ICI 👇
        $data = HearingMinute::whereNotNull('result')
            ->whereRaw("TRIM(result) != ?", [''])
            ->whereRaw("TRIM(result) != ?", ['?'])
            ->whereRaw("TRIM(result) != ?", ['؟'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $data]);
    }
}
