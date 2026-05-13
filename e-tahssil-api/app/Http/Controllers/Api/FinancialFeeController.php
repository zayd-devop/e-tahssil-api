<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialFee;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpWord\TemplateProcessor;

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
        $fees = $query->orderBy('id', 'asc')->get();

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
                if (str_contains($h, 'رقم القضية')) $idxCase = $index;
                if (str_contains($h, 'تاريخ الحكم')) $idxJudgementDate = $index;
                if (str_contains($h, 'رقم الحكم')) $idxJudgementNumber = $index;


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
                    'case_number' => $idxCase !== -1 ? trim((string)$row[$idxCase]) : null,
                    'judgement_date' => $idxJudgementDate !== -1 ? $row[$idxJudgementDate] : null,
                    'judgement_number' => $idxJudgementNumber !== -1 ? trim((string)$row[$idxJudgementNumber]) : null,

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
    public function downloadIndar($id)
    {
        try {
            $fee = FinancialFee::findOrFail($id);

            // 1. Charger le template
            $templatePath = storage_path('app/templates/template_indar.docx');
            $templateProcessor = new TemplateProcessor($templatePath);

            // 2. Remplacer les variables dans le Word
            $templateProcessor->setValue('registry_number', $fee->registry_number ?? '......');
            $templateProcessor->setValue('debtor_name', $fee->debtor_name);
            $templateProcessor->setValue('debtor_address', $fee->debtor_address ?? '................');
            $templateProcessor->setValue('execution_order_number', $fee->execution_order_number ?? '......');
            $templateProcessor->setValue('execution_order_date', $fee->execution_order_date ?? '......');
            $templateProcessor->setValue('case_number', $fee->case_number ?? '......');
            $templateProcessor->setValue('judgement_date', $fee->judgement_date ?? '......');
            $templateProcessor->setValue('judgement_number', $fee->judgement_number ?? '......');
            $templateProcessor->setValue('judicial_fees', number_format($fee->judicial_fees, 2));
            $templateProcessor->setValue('pleading_rights', number_format($fee->pleading_rights, 2));
            $templateProcessor->setValue('total_amount', number_format($fee->total_amount, 2));
            $templateProcessor->setValue('current_date', date('Y-m-d'));

            // Récupérer les infos du signataire (utilisateur connecté)
            $user = auth()->user();
            $signerName = ($user->prenom . ' ' . $user->nom) ?: '................';
            $templateProcessor->setValue('signer_name', $signerName);
            $templateProcessor->setValue('signer_role', $user->type_responsabilite ?? 'كاتب الضبط');

            // 3. Sauvegarder dans un fichier temporaire
            $fileName = "Indar_" . str_replace(' ', '_', $fee->debtor_name) . ".docx";
            $tempPath = storage_path('app/public/' . $fileName);
            $templateProcessor->saveAs($tempPath);

            // 4. Retourner le fichier au navigateur
            return response()->download($tempPath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function bulkDownloadIndar(Request $request)
{
    try {
        $request->validate(['ids' => 'required|array']);

        $fees = FinancialFee::whereIn('id', $request->ids)->get();

        if ($fees->isEmpty()) {
            return response()->json(['error' => 'لا توجد بيانات'], 404);
        }

        // 1. تحميل القالب
        $templatePath = storage_path('app/templates/template_indar.docx');
        $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

        // 2. استنساخ الكتلة (RECORD) بعدد الملفات المحددة
        // المعاملات: (اسم الكتلة, عدد النسخ, استبدال الكتلة الأصلية, إضافة فهرس للمتغيرات)
        $templateProcessor->cloneBlock('RECORD', $fees->count(), true, true);

        // جلب معلومات الموظف
        $user = auth()->user();
        $signerName = ($user->prenom . ' ' . $user->nom) ?: '................';
        $signerRole = $user->type_responsabilite ?? 'كاتب الضبط';

        // 3. ملء البيانات لكل نسخة (PHPWord سيضيف #1, #2 لكل متغير تلقائياً)
        $i = 1;
        foreach ($fees as $fee) {
            $templateProcessor->setValue('registry_number#' . $i, $fee->registry_number ?? '......');
            $templateProcessor->setValue('debtor_name#' . $i, $fee->debtor_name);
            $templateProcessor->setValue('debtor_address#' . $i, $fee->debtor_address ?? '................');
            $templateProcessor->setValue('execution_order_number#' . $i, $fee->execution_order_number ?? '......');
            $templateProcessor->setValue('execution_order_date#' . $i, $fee->execution_order_date ?? '......');
            $templateProcessor->setValue('case_number#' . $i, $fee->case_number ?? '......');
            $templateProcessor->setValue('judgement_date#' . $i, $fee->judgement_date ?? '......');
            $templateProcessor->setValue('judgement_number#' . $i, $fee->judgement_number ?? '......');
            $templateProcessor->setValue('judicial_fees#' . $i, number_format($fee->judicial_fees, 2));
            $templateProcessor->setValue('pleading_rights#' . $i, number_format($fee->pleading_rights, 2));
            $templateProcessor->setValue('total_amount#' . $i, number_format($fee->total_amount, 2));
            $templateProcessor->setValue('current_date#' . $i, date('Y-m-d'));

            $templateProcessor->setValue('signer_name#' . $i, $signerName);
            $templateProcessor->setValue('signer_role#' . $i, $signerRole);

            $i++;
        }

        // 4. الحفظ والإرسال
        $fileName = "Indarat_Groupes_" . date('Y-m-d') . ".docx";
        $tempPath = storage_path('app/public/' . $fileName);
        $templateProcessor->saveAs($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend(true);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


}
