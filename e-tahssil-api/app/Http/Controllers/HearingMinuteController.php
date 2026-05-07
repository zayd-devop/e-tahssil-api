<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HearingMinute;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\TemplateProcessor;


class HearingMinuteController extends Controller {
    public function printMerged(Request $request)
{
    $ids = $request->input('ids');
    if (empty($ids)) return response()->json(['message' => 'لم يتم اختيار أي ملف'], 400);

    $minutes = HearingMinute::where('user_id', $request->user()->id)->whereIn('id', $ids)->get();

    $templatePath = storage_path('app/templates/PVAudience.docx');
    $templateProcessor = new TemplateProcessor($templatePath);

    // 1. On clone le bloc entier autant de fois qu'il y a de dossiers
    $templateProcessor->cloneBlock('pv_block', count($minutes), true, true);

    // 2. On remplit les variables pour chaque bloc cloné
    foreach ($minutes as $index => $minute) {
        $i = $index + 1; // L'index pour TemplateProcessor commence à 1

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

public function printSingle(Request $request, $id)
{
    // 1. Récupérer le dossier spécifique
    $minute = HearingMinute::where('user_id', $request->user()->id)->findOrFail($id);

    try {
        // 2. Charger le template
        $templatePath = storage_path('app/templates/PVAudience.docx');
        $templateProcessor = new TemplateProcessor($templatePath);

        // --- LA CORRECTION EST ICI ---
        // On demande à PHPWord de cloner le bloc une seule fois.
        // Le dernier paramètre "false" est crucial : il dit à PHPWord de NE PAS ajouter de numéros (#1) aux variables.
        $templateProcessor->cloneBlock('pv_block', 1, true, false);
        // ------------------------------

        // 3. Remplir les balises normalement
        $templateProcessor->setValue('judgment_date', $minute->judgment_date);
        $templateProcessor->setValue('judge', $minute->judge);
        $templateProcessor->setValue('file_num', $minute->file_number);
        $templateProcessor->setValue('decision_content', $minute->decision_content);
        $templateProcessor->setValue('plaintiff', $minute->plaintiff ?? '-');
        $templateProcessor->setValue('defendant', $minute->defendant ?? '-');

        // 4. Générer le fichier
        $fileName = "PV_" . str_replace('/', '-', $minute->file_number) . ".docx";
        $tempFile = tempnam(sys_get_temp_dir(), 'PHPWord');
        $templateProcessor->saveAs($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);

    } catch (\Exception $e) {
        return response()->json(['message' => 'Erreur lors de la génération du document'], 500);
    }
}

  public function index(Request $request) {
    // 1. On calcule la date exacte d'il y a 4 semaines avec Carbon (ex: "2026-04-09")
    $fourWeeksAgo = \Carbon\Carbon::now()->subWeeks(4)->format('Y-m-d');

    // 2. On récupère les jugements filtrés
    $data = \App\Models\HearingMinute::where('user_id', $request->user()->id)
        // Filtre de propreté (on ignore les lignes fantômes)
        ->whereNotNull('judgment_type')
        ->where('judgment_type', '!=', '-')
        ->where('judgment_type', '!=', '')
        ->where('judgment_date', '!=', '-') // On ignore les dates vides pour éviter les erreurs SQL

        // 🔥 FILTRE DES 4 SEMAINES : On transforme le texte "dd/mm/yyyy" en vraie date SQL pour comparer
        ->whereRaw("STR_TO_DATE(judgment_date, '%d/%m/%Y') >= ?", [$fourWeeksAgo])

        ->orderBy('id', 'desc')
        ->get();

    // 3. Liaison avec le السجل العام pour injecter les noms (المدعي et المدعى عليه)
    foreach ($data as $item) {
        // Nettoyage extrême : enlève TOUS les espaces invisibles du numéro de dossier
        $cleanFileNumber = preg_replace('/\s+/', '', $item->file_number);

        // Recherche dans le dictionnaire
        $reference = \App\Models\CaseReference::whereRaw("REPLACE(file_number, ' ', '') = ?", [$cleanFileNumber])
                                              ->where('user_id', $request->user()->id)
                                              ->first();

        // Assignation des noms
        $item->plaintiff = $reference ? $reference->plaintiff : '-';
        $item->defendant = $reference ? $reference->defendant : '-';
    }

    return response()->json(['success' => true, 'data' => $data]);
}

    public function importExcel(Request $request) {
    $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $map = [
            'file_number' => -1, 'type' => -1, 'date' => -1, 'num' => -1,
            'ordinal' => -1, 'content' => -1, 'judge' => -1, 'subject' => -1
        ];
        $parsedData = [];
        $headerFound = false;

        foreach ($rows as $rowIndex => $cells) {
            $cells = array_map(fn($v) => trim($v ?? ''), $cells);

            if (!$headerFound) {
                foreach ($cells as $idx => $txt) {
                    if (mb_strpos($txt, 'الرقم الكامل للملف') !== false) $map['file_number'] = $idx;
                    if (mb_strpos($txt, 'نوع الحكم') !== false) $map['type'] = $idx;
                    if (mb_strpos($txt, 'تاريخ الحكم') !== false) $map['date'] = $idx;
                    if (mb_strpos($txt, 'رقم الحكم') !== false) $map['num'] = $idx;
                    if (mb_strpos($txt, 'الترتيبي') !== false) $map['ordinal'] = $idx;
                    if (mb_strpos($txt, 'مضمون') !== false) $map['content'] = $idx;
                    if (mb_strpos($txt, 'القاضي') !== false) $map['judge'] = $idx;
                    if (mb_strpos($txt, 'الموضوع') !== false) $map['subject'] = $idx;
                }
                if ($map['file_number'] !== -1) $headerFound = true;
                continue;
            }

            if (empty($cells[$map['file_number']])) continue;

            $fileNumber = $cells[$map['file_number']] ?? '-';

            // 🔍 RECHERCHE DES NOMS DANS LE RÉPERTOIRE (السجل العام)
            $reference = \App\Models\CaseReference::where('file_number', $fileNumber)
                            ->where('user_id', $request->user()->id)
                            ->first();

            $parsedData[] = [
                'file_number'     => $fileNumber,
                'judgment_type'   => $map['type'] !== -1 ? ($cells[$map['type']] ?? '-') : '-',
                'judgment_date'   => $map['date'] !== -1 ? ($cells[$map['date']] ?? '-') : '-',
                'judgment_number' => $map['num'] !== -1 ? ($cells[$map['num']] ?? '-') : '-',
                'ordinal_number'  => $map['ordinal'] !== -1 ? ($cells[$map['ordinal']] ?? '-') : '-',
                'decision_content'=> $map['content'] !== -1 ? ($cells[$map['content']] ?? '-') : '-',
                'judge'           => $map['judge'] !== -1 ? ($cells[$map['judge']] ?? '-') : '-',
                'subject'         => $map['subject'] !== -1 ? ($cells[$map['subject']] ?? '-') : '-',
                'result_color'    => $this->getColor($cells[$map['type']] ?? ''),

                // 👇 LIAISON DES NOUVEAUX CHAMPS 👇
                'plaintiff'       => $reference ? $reference->plaintiff : '-',
                'defendant'       => $reference ? $reference->defendant : '-',
                // 👆 ---------------------------- 👆

                'user_id'         => $request->user()->id,
                'created_at'      => now(),
                'updated_at'      => now()
            ];
        }

        \App\Models\HearingMinute::insert($parsedData);
        return response()->json(['success' => true, 'data' => \App\Models\HearingMinute::where('user_id', $request->user()->id)->orderBy('id', 'desc')->get()]);

    } catch (\Exception $e) {
        return response()->json(['message' => $e->getMessage()], 500);
    }
}

    private function getColor($type) {
        if (mb_strpos($type, 'نهائي') !== false) return 'bg-emerald-100 text-emerald-700';
        if (mb_strpos($type, 'تمهيدي') !== false) return 'bg-amber-100 text-amber-700';
        return 'bg-blue-100 text-blue-700';
    }


}
