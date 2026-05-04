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
        $folder = Folder::updateOrCreate(
            ['dossier_num' => $request->dossierNum], // On cherche par numéro de dossier
            [
                'debtor_name' => $request->debtorName,
                'debtor_cin' => $request->debtorCIN ?? null,
                'debt_amount' => $request->debtAmount ?? 0,
                'debtor_address' => $request->debtorAddress ?? null,
                'user_id' => Auth::id(),
            ]
        );

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
        // 👇 NOUVELLE LOGIQUE POUR LE TABLEAU DES COMPTES BANCAIRES (ATD) 👇
        // ====================================================================
        if ($request->filled('debtor_bank_accounts')) {
            $comptesBruts = $request->debtor_bank_accounts;

            // On découpe le texte à chaque saut de ligne (Entrée)
            $lignes = explode("\n", str_replace("\r", "", $comptesBruts));

            // On prépare le tableau au format exigé par PHPWord
            $comptesPourWord = [];
            foreach ($lignes as $ligne) {
                $compte = trim($ligne); // Enlève les espaces inutiles
                if (!empty($compte)) {
                    $comptesPourWord[] = ['account_number' => $compte];
                }
            }

            // On injecte dans le tableau Word
            if (count($comptesPourWord) > 0) {
                // S'il y a des comptes, on clone la ligne
                $templateProcessor->cloneRowAndSetValues('account_number', $comptesPourWord);
            } else {
                // S'il est vide mais que le champ était là, on met un tiret
                $templateProcessor->cloneRowAndSetValues('account_number', [['account_number' => '---']]);
            }
        }
        // ====================================================================
        // 👆 FIN DE LA NOUVELLE LOGIQUE 👆
        // ====================================================================
        // ====================================================================
        // 👇 LOGIQUE POUR LA LISTE DES DOCUMENTS (حق الاطلاع) 👇
        // ====================================================================
        if ($request->filled('requested_info')) {
            $lignes = explode("\n", str_replace("\r", "", $request->requested_info));
            $infosPourWord = [];

            // On nettoie chaque ligne tapée par l'utilisateur
            foreach ($lignes as $ligne) {
                $texte = trim($ligne);
                if (!empty($texte)) {
                    $infosPourWord[] = ['requested_info' => $texte];
                }
            }

            // On clone le bloc Word autant de fois qu'il y a de lignes
            if (count($infosPourWord) > 0) {
                $templateProcessor->cloneBlock('block_req', 0, true, false, $infosPourWord);
            } else {
                // Si l'utilisateur n'a rien saisi, on met un tiret vide
                $templateProcessor->cloneBlock('block_req', 0, true, false, [['requested_info' => '---']]);
            }

            // On supprime la variable de la requête pour ne pas que la boucle générale (Etape 5)
            // essaie de la remplacer à nouveau
            $request->request->remove('requested_info');
        }
        // ====================================================================
        // 5. Injecter TOUTES les variables envoyées par React

        $dataToInject = $request->all();

        // Petit formatage juste pour que la date du وصل soit belle (JJ/MM/AAAA)
        if (!empty($dataToInject['receipt_date'])) {
            $dataToInject['receipt_date'] = date('d/m/Y', strtotime($dataToInject['receipt_date']));
        }

        // On boucle sur nos données pour les injecter dans Word
        foreach ($dataToInject as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $templateProcessor->setValue($key, $value);
            }
            if (!empty($dataToInject['receipt_date'])) {
            $dataToInject['receipt_date'] = date('d/m/Y', strtotime($dataToInject['receipt_date']));
        }

        // 👇 Ajoute cette ligne pour la nouvelle date de notification
        if (!empty($dataToInject['notification_date'])) {
            $dataToInject['notification_date'] = date('d/m/Y', strtotime($dataToInject['notification_date']));
        }
        }

        // Maintenant on boucle sur nos données modifiées pour les injecter dans Word
        foreach ($dataToInject as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $templateProcessor->setValue($key, $value);
            }
        }

        // 6. Préparer le fichier de sortie temporaire
        $safeDossierNum = str_replace('/', '-', $request->dossierNum); // Transforme 125/2026 en 125-2026
        $fileName = 'document_' . $safeDossierNum . '_' . time() . '.docx';

        // On s'assure que le dossier temp existe
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        $tempPath = storage_path('app/temp/' . $fileName);

        // Sauvegarder le nouveau fichier rempli
        $templateProcessor->saveAs($tempPath);

        // 7. Renvoyer le fichier au navigateur pour téléchargement et le supprimer du serveur
        return response()->download($tempPath, $request->activeDoc . '.docx')->deleteFileAfterSend(true);
    }


    // Récupérer l'historique des dossiers
    public function getFolders()
    {
        // On récupère tous les dossiers, du plus récent au plus ancien
        $folders = Folder::orderBy('created_at', 'desc')->paginate(10);

        // On les renvoie au format JSON
        return response()->json($folders);
    }
}
