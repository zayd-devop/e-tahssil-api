<?php

namespace App\Http\Controllers;

use App\Models\Correspondence;
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
}
