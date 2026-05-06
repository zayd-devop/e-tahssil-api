<?php

namespace App\Http\Controllers;

use App\Models\Correspondence;
use App\Models\Folder;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    // public function generateDispatchDocument(Request $request)
    // {
    //     // 1. Validation des données reçues depuis React
    //     $validated = $request->validate([
    //         'registration_number'   => 'nullable|string',
    //         'sender_from'           => 'required|string',
    //         'recipient_to'          => 'required|string',
    //         'recipient_supervisors' => 'nullable|array', // On accepte un tableau
    //         'recipient_supervisors.*'=> 'string',
    //         'subject'               => 'required|string',
    //         'attachments_count'     => 'nullable|numeric',
    //         'notes'                 => 'nullable|string',
    //         'signer_name'           => 'required|string',
    //         'signer_role'           => 'required|string',
    //     ]);

    //     // 2. Chemin vers ton fichier Template Word
    //     // Assure-toi d'avoir mis le fichier "dispatch_template.docx" dans storage/app/templates/
    //     $templatePath = storage_path('app/templates/dispatch_template.docx');

    //     if (!file_exists($templatePath)) {
    //         return response()->json(['message' => 'Le fichier template est introuvable sur le serveur.'], 404);
    //     }

    //     // 3. Initialisation de PHPWord TemplateProcessor
    //     $templateProcessor = new TemplateProcessor($templatePath);

    //     // 4. Remplacement des variables simples
    //     $templateProcessor->setValue('issue_date', date('d-m-Y'));
    //     $templateProcessor->setValue('registration_number', $validated['registration_number'] ?? '');
    //     $templateProcessor->setValue('sender_from', $validated['sender_from']);
    //     $templateProcessor->setValue('recipient_to', $validated['recipient_to']);
    //     $templateProcessor->setValue('attachments_count', $validated['attachments_count'] ?? 0);
    //     $templateProcessor->setValue('signer_name', $validated['signer_name']);
    //     $templateProcessor->setValue('signer_role', $validated['signer_role']);

    //     // 5. Traitement des "Superviseurs" (Tableau -> Texte multiligne Word)
    //     $supervisorsText = '';
    //     if (!empty($validated['recipient_supervisors'])) {
    //         foreach ($validated['recipient_supervisors'] as $supervisor) {
    //             // <w:br/> est la balise Word pour faire un retour à la ligne
    //             $supervisorsText .= "تحت اشراف السيد : " . $supervisor . "<w:br/>";
    //         }
    //     }
    //     $templateProcessor->setValue('recipient_supervisors', $supervisorsText);

    //     // 6. Traitement des champs multilignes (Sujet et Notes)
    //     // On remplace les \n (JavaScript/PHP) par <w:br/> (Word)
    //     $formattedSubject = str_replace("\n", '<w:br/>', $validated['subject']);
    //     $templateProcessor->setValue('subject', $formattedSubject);

    //     $formattedNotes = str_replace("\n", '<w:br/>', $validated['notes'] ?? '');
    //     $templateProcessor->setValue('notes', $formattedNotes);

    //     // 7. Création du dossier temporaire de manière native (plus robuste sous Windows)
    //     $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');

    //     if (!file_exists($tempDir)) {
    //         mkdir($tempDir, 0775, true);
    //     }

    //     // 8. Sauvegarde du fichier généré avec des séparateurs propres
    //     $fileName = 'dispatch_' . time() . '.docx';
    //     $tempPath = $tempDir . DIRECTORY_SEPARATOR . $fileName;

    //     $templateProcessor->saveAs($tempPath);

    //     // 9. Retourner le fichier en téléchargement et le supprimer du serveur après l'envoi
    //     return response()->download($tempPath)->deleteFileAfterSend(true);

    // }
    public function generateDispatchDocument(Request $request)
    {
        // 1. Validation des données du formulaire Frontend
        $validated = $request->validate([
            'registration_number'   => 'nullable|string',
            'sender_from'           => 'required|string',
            'recipient_to'          => 'required|string',
            'recipient_supervisors' => 'nullable|array',
            'recipient_supervisors.*'=> 'string',
            'subject'               => 'required|string',
            'attachments_count'     => 'nullable|numeric',
            'notes'                 => 'nullable|string',
        ]);

        // 2. Déduction du signataire (utilisateur connecté)
        $user = $request->user();
        $signerName = $user->name ?? 'الاسم غير متوفر';
        $signerRole = 'كاتب الضبط';

        // Assure-toi que la relation s'appelle bien "clerk" dans ton modèle User
        if ($user->clerk) {
            $signerName = trim($user->clerk->prenom . ' ' . $user->clerk->nom);
            if ($user->clerk->type_responsabilite) {
                $signerRole = $user->clerk->type_responsabilite;
            }
        } elseif ($user->role === 'admin') {
            $signerRole = 'رئيس الوحدة';
        }

        // 3. Charger le Template Word
        $templatePath = storage_path('app/templates/dispatch_template.docx');

        if (!file_exists($templatePath)) {
            return response()->json(['message' => 'Le fichier template est introuvable sur le serveur.'], 404);
        }

        $templateProcessor = new TemplateProcessor($templatePath);

        // 4. Remplir le Word
        $templateProcessor->setValue('issue_date', date('d-m-Y'));
        $templateProcessor->setValue('registration_number', $validated['registration_number'] ?? '');
        $templateProcessor->setValue('sender_from', $validated['sender_from']);
        $templateProcessor->setValue('recipient_to', $validated['recipient_to']);
        $templateProcessor->setValue('attachments_count', $validated['attachments_count'] ?? 0);
        $templateProcessor->setValue('signer_name', $signerName);
        $templateProcessor->setValue('signer_role', $signerRole);

        // Superviseurs multilignes
        $supervisorsText = '';
        if (!empty($validated['recipient_supervisors'])) {
            foreach ($validated['recipient_supervisors'] as $supervisor) {
                $supervisorsText .= "تحت اشراف السيد : " . $supervisor . "<w:br/>";
            }
        }
        $templateProcessor->setValue('recipient_supervisors', $supervisorsText);

        // Champs textes avec retours à la ligne
        $formattedSubject = str_replace("\n", '<w:br/>', $validated['subject']);
        $templateProcessor->setValue('subject', $formattedSubject);

        $formattedNotes = str_replace("\n", '<w:br/>', $validated['notes'] ?? '');
        $templateProcessor->setValue('notes', $formattedNotes);

        // 5. Sauvegarder dans la base de données (Historique / Archive)
        Correspondence::create([
            'user_id'               => $user->id,
            'registration_number'   => $validated['registration_number'] ?? null,
            'sender_from'           => $validated['sender_from'],
            'recipient_to'          => $validated['recipient_to'],
            'recipient_supervisors' => $validated['recipient_supervisors'] ?? [],
            'subject'               => $validated['subject'],
            'attachments_count'     => $validated['attachments_count'] ?? 0,
            'notes'                 => $validated['notes'] ?? null,
            'signer_name'           => $signerName,
            'signer_role'           => $signerRole,
        ]);

        // 6. Sauvegarder le fichier Word et l'envoyer
        $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $fileName = 'dispatch_' . time() . '.docx';
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $fileName;

        $templateProcessor->saveAs($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend(true);
    }

    // --- 2. RÉCUPÉRER L'ARCHIVE ---
    public function getArchive(Request $request)
    {
        $archive = Correspondence::where('user_id', $request->user()->id)
                    ->orderBy('created_at', 'desc')
                    ->get();

        $archive->transform(function ($item) {
            $item->date_envoi = $item->created_at->format('Y/m/d');
            return $item;
        });

        return response()->json($archive);
    }

    // --- 3. COMPTER LES LETTRES (Pour la TopBar) ---
    public function getUserLettersCount(Request $request)
    {
        $count = Correspondence::where('user_id', $request->user()->id)->count();
        return response()->json(['count' => $count]);
    }

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
            'user_id' => $request->user()->id,
            'document_type' => $request->activeDoc,
        ]);

        // 3. Vérifier que le modèle Word existe
        $templatePath = storage_path('app/templates/' . $request->activeDoc . '.docx');

        if (!file_exists($templatePath)) {
            return response()->json(['error' => "Le modèle Word pour {$request->activeDoc} est introuvable sur le serveur."], 404);
        }

        // 4. Ouvrir le modèle avec PHPWord
        $templateProcessor = new TemplateProcessor($templatePath);

        $user = $request->user();
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
