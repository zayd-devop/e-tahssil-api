<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Folder;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;

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
        // On utilise updateOrCreate pour mettre à jour si le dossier existe déjà, ou le créer sinon
        $folder = Folder::updateOrCreate(
            ['dossier_num' => $request->dossierNum], // On cherche par numéro de dossier
            [
                'debtor_name' => $request->debtorName,
                'debtor_cin' => $request->debtorCIN ?? null,
                'debt_amount' => $request->debtAmount ?? 0,
                'debtor_address' => $request->debtorAddress ?? null,
                'user_id' => 1, // Temporaire (on mettra l'ID de l'utilisateur connecté plus tard)
            ]
        );

        // 3. Vérifier que le modèle Word existe
        // Les modèles doivent être placés dans le dossier: storage/app/templates/
        $templatePath = storage_path('app/templates/' . $request->activeDoc . '.docx');

        if (!file_exists($templatePath)) {
            return response()->json(['error' => "Le modèle Word pour {$request->activeDoc} est introuvable sur le serveur."], 404);
        }

        // 4. Ouvrir le modèle avec PHPWord
        $templateProcessor = new TemplateProcessor($templatePath);

        // 👇 Ajout spécial pour la signature et la date
        // Plus tard, on utilisera : auth()->user()->name
        $templateProcessor->setValue('user_name', 'عدنان شقور');
        $templateProcessor->setValue('user_id', '1234'); // L'ID de l'employé
        $templateProcessor->setValue('current_date', date('Y/m/d')); // La date du jour automatique

        // 5. Injecter TOUTES les variables envoyées par React
        // On boucle sur tout ce que React a envoyé (Données générales + spécifiques)
        foreach ($request->all() as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                // Remplace {ma_cle} par sa valeur dans le Word
                $templateProcessor->setValue($key, $value);
            }
        }

        // 6. Préparer le fichier de sortie temporaire
        $fileName = 'document_' . $folder->dossier_num . '_' . time() . '.docx';
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
}
