<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HearingMinute;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;

class HearingMinuteController extends Controller {

    // 1. Affichage du tableau (Max 1000 dossiers pour que ça reste ultra rapide)
    public function index(Request $request) {
        try {
            $data = HearingMinute::where('user_id', $request->user()->id)
                // On garde un numéro de dossier valide
                ->where('file_number', '!=', '-')
                ->where('file_number', '!=', '')

                // On garde la condition sur le contenu non null
                ->whereNotNull('decision_content')
                ->where('decision_content', '!=', '')
                ->where('decision_content', '!=', '-')

                // On s'assure que la date n'est pas vide pour éviter les erreurs de tri
                ->where('judgment_date', '!=', '-')
                ->where('judgment_date', '!=', '')

                // 🔥 LE TRI CHRONOLOGIQUE : Transforme "dd/mm/yyyy" en date pour trier du plus récent au plus ancien
                ->orderByRaw("STR_TO_DATE(judgment_date, '%d/%m/%Y') DESC")

                ->take(1000)
                ->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
    // 2. Importation du fichier "السجل العام"
public function importExcel(Request $request) {
        // 1. On donne un peu plus de temps à PHP au cas où le fichier est gigantesque (5 minutes)
        set_time_limit(300);

        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();

            $map = [
                'file_number'   => -1,
                'plaintiff'     => -1,
                'defendant'     => -1,
                'subject'       => -1,
                'judge'         => -1,
                'judgment_date' => -1,
                'judgment_num'  => -1,
                'ordinal'       => -1,
                'content'       => -1
            ];

            $headerFound = false;
            $parsedData = []; // 👈 On va stocker toutes les lignes ici

            foreach ($rows as $rowIndex => $cells) {
                $cells = array_map(fn($v) => trim((string) $v), $cells);

                if (!$headerFound) {
                    foreach ($cells as $idx => $txt) {
                        $txt = trim($txt);
                        if (empty($txt)) continue;

                        if ($map['file_number'] === -1 && mb_strpos($txt, 'الرقم الكامل للملف') !== false) $map['file_number'] = $idx;
                        if ($map['plaintiff'] === -1 && mb_strpos($txt, 'المدعي') !== false) $map['plaintiff'] = $idx;
                        if ($map['defendant'] === -1 && mb_strpos($txt, 'المدعى عليه') !== false) $map['defendant'] = $idx;
                        if ($map['subject'] === -1 && mb_strpos($txt, 'موضوع الدعوى') !== false) $map['subject'] = $idx;
                        if ($map['judge'] === -1 && (mb_strpos($txt, 'القاضي') !== false || mb_strpos($txt, 'المستشار') !== false)) $map['judge'] = $idx;
                        if ($map['judgment_date'] === -1 && mb_strpos($txt, 'تاريخ الحكم/القرار') !== false) $map['judgment_date'] = $idx;
                        if ($map['judgment_num'] === -1 && mb_strpos($txt, 'رقم الحكم/القرار') !== false) $map['judgment_num'] = $idx;
                        if ($map['content'] === -1 && mb_strpos($txt, 'مضمون الحكم/القرار') !== false) $map['content'] = $idx;
                        if ($map['ordinal'] === -1 && mb_strpos($txt, 'الرقم الترتيبي') !== false) $map['ordinal'] = $idx;
                    }
                    if ($map['file_number'] !== -1) $headerFound = true;
                    continue;
                }

                if (empty($cells[$map['file_number']])) continue;

                $fileNumber = preg_replace('/\s+/', '', $cells[$map['file_number']]);

                $rawDate = ($map['judgment_date'] !== -1 && !empty($cells[$map['judgment_date']])) ? $cells[$map['judgment_date']] : '-';
                if ($rawDate !== '-' && is_numeric($rawDate)) {
                    $rawDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate)->format('d/m/Y');
                }

                // 🔥 Au lieu de sauvegarder en BDD tout de suite, on l'ajoute dans notre tableau en mémoire
                $parsedData[] = [
                    'file_number'      => $fileNumber,
                    'user_id'          => $request->user()->id,
                    'plaintiff'        => $map['plaintiff'] !== -1 ? mb_substr($cells[$map['plaintiff']] ?? '-', 0, 250) : '-',
                    'defendant'        => $map['defendant'] !== -1 ? mb_substr($cells[$map['defendant']] ?? '-', 0, 250) : '-',
                    'subject'          => $map['subject'] !== -1 ? ($cells[$map['subject']] ?? '-') : '-',
                    'judge'            => $map['judge'] !== -1 ? ($cells[$map['judge']] ?? '-') : '-',
                    'judgment_date'    => $rawDate,
                    'judgment_number'  => $map['judgment_num'] !== -1 ? ($cells[$map['judgment_num']] ?? '-') : '-',
                    'ordinal_number'   => $map['ordinal'] !== -1 ? ($cells[$map['ordinal']] ?? '-') : '-',
                    'decision_content' => $map['content'] !== -1 ? ($cells[$map['content']] ?? '-') : '-',
                    'judgment_type'    => 'حكم قطعي',
                    'result_color'     => 'bg-blue-100 text-blue-700',
                    'created_at'       => now(),
                    'updated_at'       => now()
                ];
            }

            // 🚀 LE TURBO : Sauvegarde en masse !
            if (!empty($parsedData)) {
                // On divise le gros colis en paquets de 500 pour ne pas saturer MySQL
                foreach (array_chunk($parsedData, 500) as $chunk) {
                    \App\Models\HearingMinute::upsert(
                        $chunk,
                        ['file_number'], // La colonne qui détermine si le dossier existe déjà
                        ['plaintiff', 'defendant', 'subject', 'judge', 'judgment_date', 'judgment_number', 'ordinal_number', 'decision_content', 'updated_at'] // Les colonnes à mettre à jour si ça existe
                    );
                }
            }

            $data = \App\Models\HearingMinute::where('user_id', $request->user()->id)->orderBy('id', 'desc')->take(1000)->get();
            return response()->json(['success' => true, 'message' => 'تم الاستيراد بنجاح', 'data' => $data]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'خطأ: ' . $e->getMessage()], 500);
        }
    }

    // 3. Impression individuelle (Word)
    public function printSingle(Request $request, $id)
    {
        $minute = HearingMinute::where('user_id', $request->user()->id)->findOrFail($id);

        try {
            $templatePath = storage_path('app/templates/PVAudience.docx');
            $templateProcessor = new TemplateProcessor($templatePath);

            $templateProcessor->cloneBlock('pv_block', 1, true, false);

            $templateProcessor->setValue('judgment_date', $minute->judgment_date);
            $templateProcessor->setValue('judge', $minute->judge);
            $templateProcessor->setValue('file_num', $minute->file_number);
            $templateProcessor->setValue('decision_content', $minute->decision_content);
            $templateProcessor->setValue('plaintiff', $minute->plaintiff ?? '-');
            $templateProcessor->setValue('defendant', $minute->defendant ?? '-');

            $fileName = "PV_" . str_replace('/', '-', $minute->file_number) . ".docx";
            $tempFile = tempnam(sys_get_temp_dir(), 'PHPWord');
            $templateProcessor->saveAs($tempFile);

            return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Erreur lors de la génération du document'], 500);
        }
    }

    // 4. Impression groupée (Publipostage Word)
    public function printMerged(Request $request)
    {
        $ids = $request->input('ids');
        if (empty($ids)) return response()->json(['message' => 'لم يتم اختيار أي ملف'], 400);

        $minutes = HearingMinute::where('user_id', $request->user()->id)->whereIn('id', $ids)->get();

        $templatePath = storage_path('app/templates/PVAudience.docx');
        $templateProcessor = new TemplateProcessor($templatePath);

        $templateProcessor->cloneBlock('pv_block', count($minutes), true, true);

        foreach ($minutes as $index => $minute) {
            $i = $index + 1;

            $templateProcessor->setValue("judgment_date#$i", $minute->judgment_date);
            $templateProcessor->setValue("judge#$i", $minute->judge);
            $templateProcessor->setValue("file_num#$i", $minute->file_number);
            $templateProcessor->setValue("decision_content#$i", $minute->decision_content);
            $templateProcessor->setValue("plaintiff#$i", $minute->plaintiff ?? '-');
            $templateProcessor->setValue("defendant#$i", $minute->defendant ?? '-');
        }

        $fileName = "Publipostage_PV_" . date('Y-m-d') . ".docx";
        $tempFile = tempnam(sys_get_temp_dir(), 'PHPWord');
        $templateProcessor->saveAs($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}
