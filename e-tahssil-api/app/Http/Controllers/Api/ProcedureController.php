<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProcedureController extends Controller
{
    public function index() {
       return Procedure::all()->map(function ($p) {
        return [
            'id' => $p->id,
            'fileNumber' => $p->fileNumber, // Modifié ici
            'parties' => $p->parties ?? [],
            'role' => $p->role,
            'address' => $p->address,
            'decision' => $p->decision,
            'judgmentNumber' => $p->judgmentNumber, // Modifié ici
        ];
         });
    }
    public function update(Request $request, $id)
{
    $procedure = Procedure::findOrFail($id);

    $procedure->update([
        'fileNumber' => $request->fileNumber, // Modifié ici
        'parties' => $request->parties,
        'role' => $request->role,
        'address' => $request->address,
        'decision' => $request->decision,
        'judgmentNumber' => $request->judgmentNumber, // Modifié ici
    ]);

    return response()->json(['message' => 'Modifié avec succès']);
}

//    public function import(Request $request)
//     {
//         try {
//             $request->validate([
//                 'file' => 'required|mimes:xlsx,xls,csv'
//             ]);

//             $data = Excel::toArray(new class {}, $request->file('file'));

//             if (empty($data) || empty($data[0])) {
//                 return response()->json(['error' => 'الملف فارغ أو لا يمكن قراءته'], 400);
//             }

//             $rows = $data[0];
//             $headers = $rows[0]; // Les en-têtes bruts

//             // Variables pour stocker les index
//             $idxFile = false; $idxAddress = false; $idxDecision = false;
//             $idxJudgment = false; $idxParty = false; $idxRole = false; $idxPronouncement = false;

//             // RECHERCHE INTELLIGENTE : On cherche juste une partie du mot pour ignorer les espaces/BOM invisibles
//             foreach ($headers as $index => $header) {
//                 $headerStr = (string) $header; // Forcer en chaîne de caractères

//                 if (str_contains($headerStr, 'الملف')) $idxFile = $index;
//                 if (str_contains($headerStr, 'الحكم')) $idxJudgment = $index;
//                 if (str_contains($headerStr, 'العنوان')) $idxAddress = $index;
//                 if (str_contains($headerStr, 'المقرر')) $idxDecision = $index;
//                 if (str_contains($headerStr, 'الصفة')) $idxRole = $index;
//                 if (str_contains($headerStr, 'الطرف')) $idxParty = $index;
//             }

//             // Sauvegarde dans la base de données
//             for ($i = 1; $i < count($rows); $i++) {
//                 $row = $rows[$i];

//                 // On vérifie que la ligne n'est pas complètement vide
//                 if (array_filter($row)) {
//                     Procedure::create([
//                         // Utilise exactement les noms de ta base de données (fileNumber, judgmentNumber)
//                         'fileNumber' => $idxFile !== false ? $row[$idxFile] : null,
//                         'judgmentNumber' => $idxJudgment !== false ? $row[$idxJudgment] : null,
//                         'address' => $idxAddress !== false ? $row[$idxAddress] : null,
//                         'decision' => $idxDecision !== false ? $row[$idxDecision] : null,
//                         'role' => $idxRole !== false ? $row[$idxRole] : null,
//                         'parties' => $idxParty !== false && $row[$idxParty] ? explode("\n", str_replace([',', '-'], "\n", $row[$idxParty])) : [],
//                     ]);
//                 }
//             }

//             return response()->json(['message' => 'تم استيراد البيانات بنجاح']);

//         } catch (\Exception $e) {
//             return response()->json([
//                 'error' => 'حدث خطأ في الخادم',
//                 'details' => $e->getMessage()
//             ], 500);
//         }
//     }
public function import(Request $request)
{
    try {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);

        // Lecture sécurisée du fichier Excel
        $data = Excel::toArray(new class {}, $request->file('file'));

        if (empty($data) || empty($data[0])) {
            return response()->json(['error' => 'الملف فارغ (Le fichier est vide)'], 400);
        }

        $rows = $data[0];
        $headers = $rows[0]; // La ligne 0 contient les titres des colonnes

        // 1. RECHERCHE INTELLIGENTE DES COLONNES (Pour éviter le décalage)
        $idxFile = -1; $idxParty = -1; $idxRole = -1; $idxAddress = -1;
        $idxDecision = -1; $idxJudgment = -1;

        foreach ($headers as $index => $header) {
            $headerStr = trim((string) $header);
            if (str_contains($headerStr, 'الملف')) $idxFile = $index;
            if (str_contains($headerStr, 'الطرف')) $idxParty = $index;
            if (str_contains($headerStr, 'الصفة')) $idxRole = $index;
            if (str_contains($headerStr, 'العنوان')) $idxAddress = $index;
            if (str_contains($headerStr, 'المقرر')) $idxDecision = $index;
            if (str_contains($headerStr, 'الحكم')) $idxJudgment = $index;
        }

        // Si on ne trouve pas la colonne "Numéro de dossier", on arrête
        if ($idxFile === -1) {
            return response()->json(['error' => 'لم يتم العثور على عمود "رقم الملف" (Colonne Numéro de dossier introuvable)'], 400);
        }

        $groupedData = [];
        $currentFileNumber = null;

        // 2. PARCOURS ET FUSION DES LIGNES (Gestion du Merge Excel)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Si la ligne est complètement vide, on l'ignore
            if (empty(array_filter($row))) continue;

            // Extraction sécurisée des valeurs en utilisant les index trouvés
            $valFile = $idxFile !== -1 ? trim((string)($row[$idxFile] ?? '')) : '';
            $valParty = $idxParty !== -1 ? trim((string)($row[$idxParty] ?? '')) : '';
            $valRole = $idxRole !== -1 ? trim((string)($row[$idxRole] ?? '')) : '';
            $valAddress = $idxAddress !== -1 ? trim((string)($row[$idxAddress] ?? '')) : '';
            $valDecision = $idxDecision !== -1 ? trim((string)($row[$idxDecision] ?? '')) : '';
            $valJudgment = $idxJudgment !== -1 ? trim((string)($row[$idxJudgment] ?? '')) : '';

            // Si on trouve un nouveau numéro de fichier, on initialise son groupe
            if ($valFile !== '') {
                $currentFileNumber = $valFile;

                if (!isset($groupedData[$currentFileNumber])) {
                    $groupedData[$currentFileNumber] = [
                        'fileNumber'      => $currentFileNumber,
                        'parties'         => [],
                        'roles'           => [],
                        'addresses'       => [], // CORRIGÉ : Avec un "s"
                        'decisions'       => [], // CORRIGÉ : Avec un "s"
                        'judgmentNumbers' => [],
                    ];
                }
            }

            // Si aucun numéro n'a été trouvé (ex: 1ère ligne vide), on passe
            if (!$currentFileNumber) continue;

            // Ajout des valeurs dans les tableaux du dossier en cours
            if ($valParty !== '') $groupedData[$currentFileNumber]['parties'][] = $valParty;
            if ($valRole !== '') $groupedData[$currentFileNumber]['roles'][] = $valRole;
            if ($valAddress !== '') $groupedData[$currentFileNumber]['addresses'][] = $valAddress;
            if ($valDecision !== '') $groupedData[$currentFileNumber]['decisions'][] = $valDecision;
            if ($valJudgment !== '') $groupedData[$currentFileNumber]['judgmentNumbers'][] = $valJudgment;
        }

        // 3. INSERTION DANS LA BASE DE DONNÉES
        foreach ($groupedData as $group) {

            // On enlève les doublons (array_unique) et les valeurs vides (array_filter)
            $parties = array_values(array_unique(array_filter($group['parties'])));
            $role = implode(' / ', array_unique(array_filter($group['roles'])));
            $address = implode("\n", array_unique(array_filter($group['addresses'])));
            $decision = implode("\n", array_unique(array_filter($group['decisions'])));
            $judgmentNumber = implode(' - ', array_unique(array_filter($group['judgmentNumbers'])));

            Procedure::create([
                'fileNumber'     => $group['fileNumber'],
                'parties'        => $parties, // C'est un tableau, Laravel le transforme en JSON grâce au "cast" dans le modèle
                'role'           => $role,
                'address'        => $address,
                'decision'       => $decision,
                'judgmentNumber' => $judgmentNumber,
            ]);
        }

        return response()->json(['message' => 'تم استيراد البيانات بنجاح (Import réussi)']);

    } catch (\Exception $e) {
        return response()->json([
            'error'   => 'حدث خطأ أثناء الاستيراد (Erreur lors de l\'importation)',
            'details' => $e->getMessage(),
            'line'    => $e->getLine()
        ], 500);
    }
}
    public function print($id) {
        $procedure = Procedure::findOrFail($id);
        return response()->json($procedure);
    }
}
