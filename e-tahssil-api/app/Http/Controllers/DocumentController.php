<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Folder;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function generate(Request $request)
    {
        // 1. Validation basique (s'assurer qu'on reçoit bien un dossier et un type de document)
        $request->validate([
            'dossierNum' => 'required|string',
            'debtorName' => 'required|string',
            'activeDoc'  => 'required|string', // ex: 'pv_carence', 'mortgage'
        ]);

        // 2. Sauvegarder ou mettre à jour le dossier dans la base de données
        $folder = Folder::create([
            'dossier_num' => $request->dossierNum,
            'debtor_name' => $request->debtorName,
            'debtor_cin' => $request->debtorCIN ?? null,
            'debt_amount' => $request->debtAmount ?? 0,
            'debtor_address' => $request->debtorAddress ?? null,
            'user_id' => Auth::id(),
            'document_type' => $request->activeDoc,
        ]);

        // 3. Vérifier que le modèle Word existe
        $templatePath = storage_path('app/templates/' . $request->activeDoc . '.docx');

        if (!file_exists($templatePath)) {
            return response()->json(['error' => "Le modèle Word pour {$request->activeDoc} est introuvable sur le serveur."], 404);
        }

        // 4. Ouvrir le modèle avec PHPWord
        $templateProcessor = new TemplateProcessor($templatePath);

        $user = Auth::user();
        if ($user->clerk) {
            $nomAafficher = $user->clerk->nom . ' ' . $user->clerk->prenom;
        } elseif ($user->admin) {
            $nomAafficher = $user->admin->nom . ' ' . $user->admin->prenom;
        } else {
            $nomAafficher = $user->name ?? 'مستخدم غير معروف';
        }
        $templateProcessor->setValue('user_name', $nomAafficher);
        $templateProcessor->setValue('current_date', date('Y/m/d'));

        // ====================================================================
        // 👇 LOGIQUE POUR LE TABLEAU DES COMPTES BANCAIRES (ATD) 👇
        // ====================================================================
        if ($request->filled('debtor_bank_accounts')) {
            $comptesBruts = $request->debtor_bank_accounts;
            $lignes = explode("\n", str_replace("\r", "", $comptesBruts));
            $comptesPourWord = [];

            foreach ($lignes as $ligne) {
                $compte = trim($ligne);
                if (!empty($compte)) {
                    $comptesPourWord[] = ['account_number' => $compte];
                }
            }

            if (count($comptesPourWord) > 0) {
                $templateProcessor->cloneRowAndSetValues('account_number', $comptesPourWord);
            } else {
                $templateProcessor->cloneRowAndSetValues('account_number', [['account_number' => '---']]);
            }
        }

        // ====================================================================
        // 👇 LOGIQUE POUR LA LISTE DES DOCUMENTS (حق الاطلاع) 👇
        // ====================================================================
        if ($request->filled('requested_info')) {
            $lignes = explode("\n", str_replace("\r", "", $request->requested_info));
            $infosPourWord = [];

            foreach ($lignes as $ligne) {
                $texte = trim($ligne);
                if (!empty($texte)) {
                    $infosPourWord[] = ['requested_info' => $texte];
                }
            }

            if (count($infosPourWord) > 0) {
                $templateProcessor->cloneBlock('block_req', 0, true, false, $infosPourWord);
            } else {
                $templateProcessor->cloneBlock('block_req', 0, true, false, [['requested_info' => '---']]);
            }

            $request->request->remove('requested_info');
        }

        // ====================================================================
        // 5. Injecter TOUTES les variables (et gérer les vides dynamiquement)
        // ====================================================================
        $dataToInject = $request->all();

        // On formate TOUTES les dates ici avant d'injecter
        if (!empty($dataToInject['receipt_date'])) {
            $dataToInject['receipt_date'] = date('d/m/Y', strtotime($dataToInject['receipt_date']));
        }
        if (!empty($dataToInject['notification_date'])) {
            $dataToInject['notification_date'] = date('d/m/Y', strtotime($dataToInject['notification_date']));
        }
        if (!empty($dataToInject['old_declaration_date'])) {
            $dataToInject['old_declaration_date'] = date('d/m/Y', strtotime($dataToInject['old_declaration_date']));
        }
        if (!empty($dataToInject['extract_date'])) {
            $dataToInject['extract_date'] = date('d/m/Y', strtotime($dataToInject['extract_date']));
        }

        // L'ASTUCE MAGIQUE : On demande à PHPWord de trouver tous les tags restants dans le document !
        $variablesInWord = $templateProcessor->getVariables();

        foreach ($variablesInWord as $variable) {
            // Est-ce que la donnée existe dans ce qu'a envoyé React ET n'est pas vide ?
            if (isset($dataToInject[$variable]) && trim((string)$dataToInject[$variable]) !== '') {
                $templateProcessor->setValue($variable, $dataToInject[$variable]);
            } else {
                // Si React ne l'a pas envoyé (champ non touché) ou s'il est vide -> pointillés !
                $templateProcessor->setValue($variable, '.........................');
            }
        }
        // ====================================================================
        // 6. Préparer le fichier de sortie temporaire
        // ====================================================================
        $safeDossierNum = str_replace('/', '-', $request->dossierNum);
        $fileName = 'document_' . $safeDossierNum . '_' . time() . '.docx';

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        $tempPath = storage_path('app/temp/' . $fileName);

        $templateProcessor->saveAs($tempPath);

        // 7. Renvoyer le fichier au navigateur pour téléchargement
        return response()->download($tempPath, $request->activeDoc . '.docx')->deleteFileAfterSend(true);
    }

    public function getFolders()
    {
        $folders = Folder::orderBy('created_at', 'desc')->paginate(10);
        return response()->json($folders);
    }
}
