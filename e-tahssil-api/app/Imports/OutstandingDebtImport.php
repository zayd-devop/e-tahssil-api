<?php

namespace App\Imports;

use App\Models\OutstandingDebt;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithUpserts; // 👈 Import obligatoire pour gérer les doublons

class OutstandingDebtImport implements ToModel, WithStartRow, WithUpserts
{
    /**
     * Le vrai tableau de données commence à la ligne 10.
     */
    public function startRow(): int
    {
        return 10;
    }

    /**
     * C'est ici qu'on dit à Laravel : "Si tu vois ce numéro de dossier
     * exister déjà dans la base, mets-le à jour au lieu de planter".
     */
    public function uniqueBy()
    {
        return 'collectionFileNumber';
    }

    /**
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // On ignore les lignes où le numéro de dossier est vide
        if (empty($row[1])) {
            return null;
        }

        return new OutstandingDebt([
            'collectionFileNumber' => $row[1],
            'judgmentNumber'       => $row[2],
            'judgmentDate'         => $this->transformDate($row[3]),
            'fullName'             => $row[4],
            'assumptionsNumber'    => $row[5],
            'assumptionsDate'      => $this->transformDate($row[6]),

            // On nettoie l'argent pour éviter les erreurs de type DECIMAL
            'fines'                => $this->transformDecimal($row[7]),
            'monetaryConvictions'  => $this->transformDecimal($row[8]),
            'expenses'             => $this->transformDecimal($row[9]),

            'lastProcedure'        => $row[10],
            'procedureDate'        => $this->transformDate($row[11]),
            'notes'                => $row[12] ?? null,
        ]);
    }

    /**
     * Fonction magique pour nettoyer l'argent (DECIMAL)
     */
    private function transformDecimal($value)
    {
        // 1. On enlève les espaces autour
        $cleanValue = trim($value);

        // 2. Si c'est vide, un tiret, ou si ce n'est pas un nombre, on retourne 0
        if ($cleanValue === '' || $cleanValue === '-' || !is_numeric($cleanValue)) {
            return 0;
        }

        // 3. Sinon, on retourne le vrai nombre
        return (float) $cleanValue;
    }

    /**
     * Fonction magique pour gérer les dates Excel bizarres et le texte libre
     */
    private function transformDate($value)
    {
        if (empty($value)) {
            return null;
        }

        // Si c'est un numéro (format date caché d'Excel, ex: 41258)
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return (string) $value; // En cas de problème de conversion, on le garde en texte
            }
        }

        // Si c'est du texte libre (ex: "11/242" ou "02/2010"), on le garde tel quel
        return (string) $value;
    }
}
