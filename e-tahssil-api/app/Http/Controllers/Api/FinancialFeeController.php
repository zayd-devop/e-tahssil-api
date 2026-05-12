<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialFee;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class FinancialFeeController extends Controller
{
    public function index(Request $request, $type)
    {
        $year = $request->query('year');
        $query = FinancialFee::where('type', $type);

        $query->when($year, function ($q) use ($year) {
            return $q->where(function ($subQuery) use ($year) {
                // البحث إما في تاريخ التنفيذ أو بداخل رقم السجل (مثل: 2022/14)
                $subQuery->whereYear('execution_order_date', $year)
                         ->orWhere('registry_number', 'LIKE', $year . '/%')
                         ->orWhere('registry_number', 'LIKE', '%' . $year . '%');
            });
        });

        // تم إضافة limit 1500 لتسريع العرض
        $fees = $query->orderBy('id', 'desc')->take(1500)->get();

        return response()->json($fees);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:complementary,legal_aid',
            'debtor_name' => 'required|string',
        ]);

        $fee = FinancialFee::create($request->all());
        return response()->json(['message' => 'تم الحفظ بنجاح', 'data' => $fee]);
    }

    public function update(Request $request, $id)
    {
        $fee = FinancialFee::findOrFail($id);
        $fee->update($request->all());
        return response()->json(['message' => 'تم التعديل بنجاح', 'data' => $fee]);
    }

    public function destroy($id)
    {
        FinancialFee::findOrFail($id)->delete();
        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    // دالة الحذف المجمع
    public function bulkDestroy(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        FinancialFee::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'تم الحذف المجمع بنجاح']);
    }

    // دالة الاستيراد من Excel
    // دالة الاستيراد من Excel
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls,csv',
                'type' => 'required|in:complementary,legal_aid'
            ]);

            $data = Excel::toArray(new class {}, $request->file('file'));

            if (empty($data) || empty($data[0])) {
                return response()->json(['error' => 'الملف فارغ'], 400);
            }

            $rows = $data[0];
            $headers = $rows[0];

            // البحث عن الأعمدة
            $idxReg = -1; $idxDate = -1; $idxOrder = -1; $idxName = -1;
            $idxFees = -1; $idxPlead = -1; $idxAddress = -1;

            foreach ($headers as $index => $header) {
                $h = trim((string) $header);

                if (str_contains($h, 'رقم سجل')) $idxReg = $index;
                if (str_contains($h, 'تاريخ الامر')) $idxDate = $index;
                if (str_contains($h, 'رقم الامر')) $idxOrder = $index;

                // 🔥 LE CORRECTIF EST ICI : On cherche le nom, mais on s'assure que ce n'est PAS l'adresse
                if ((str_contains($h, 'الاسم') || str_contains($h, 'الإسم') || str_contains($h, 'المدين')) && !str_contains($h, 'عنوان')) {
                    $idxName = $index;
                }

                if (str_contains($h, 'الرسوم القضائية')) $idxFees = $index;
                if (str_contains($h, 'حقوق المرافعة')) $idxPlead = $index;
                if (str_contains($h, 'عنوان')) $idxAddress = $index;
            }

            if ($idxName === -1) {
                return response()->json(['error' => 'عمود الإسم الكامل للمدين مفقود'], 400);
            }

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row))) continue;

                // On récupère uniquement le nom pour faire la vérification
                $debtorName = $idxName !== -1 ? trim((string)$row[$idxName]) : '';
                $upperName = strtoupper($debtorName);

                // 🔥 LE VRAI CORRECTIF EST ICI 🔥
                // On vérifie SEULEMENT la colonne du nom.
                // Si le nom est "المجموع العام" ou une formule Excel, on ignore la ligne !
                if (str_contains($debtorName, 'المجموع') || str_starts_with($upperName, 'SUM') || str_starts_with($upperName, '=')) {
                    continue;
                }

                // Sécurité supplémentaire : on ignore la ligne si le nom, le numéro de registre et l'ordre sont vides (ligne fantôme)
                if (empty($debtorName) && empty($row[$idxReg]) && empty($row[$idxOrder])) {
                    continue;
                }

                $dateVal = $idxDate !== -1 ? $row[$idxDate] : null;
                if (is_numeric($dateVal)) {
                    $dateVal = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateVal)->format('Y-m-d');
                }

                FinancialFee::create([
                    'type' => $request->type,
                    'registry_number' => $idxReg !== -1 ? trim((string)$row[$idxReg]) : null,
                    'execution_order_date' => $dateVal,
                    'execution_order_number' => $idxOrder !== -1 ? trim((string)$row[$idxOrder]) : null,
                    'debtor_name' => $debtorName ?: 'بدون اسم',
                    'judicial_fees' => $idxFees !== -1 ? (float)$row[$idxFees] : 0,
                    'pleading_rights' => $idxPlead !== -1 ? (float)$row[$idxPlead] : 0,
                    'debtor_address' => $idxAddress !== -1 ? trim((string)$row[$idxAddress]) : null,
                ]);
            }

            return response()->json(['message' => 'تم استيراد البيانات بنجاح']);

        } catch (\Exception $e) {
            return response()->json(['error' => 'حدث خطأ أثناء الاستيراد', 'details' => $e->getMessage()], 500);
        }
    }
}
