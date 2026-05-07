<?php

namespace App\Imports;

use App\Models\HearingMinute;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class HearingMinutesImport implements ToModel, WithStartRow
{
    public function startRow(): int
    {
        return 2; // On ignore la ligne des entêtes
    }

    public function model(array $row)
    {
        // On vérifie l'index 6 car c'est lui qui contient maintenant le numéro de dossier (à l'extrême droite)
        if (!isset($row[6]) || empty($row[6])) {
            return null;
        }

        return new HearingMinute([
            // Inversion totale des index pour correspondre au sens de lecture GAUCHE -> DROITE
            'file_number'      => $row[6], // Colonne A (à droite)
            'plaintiff_lawyer' => $row[5], // Colonne B
            'defendant_lawyer' => $row[4], // Colonne C
            'subject'          => $row[3], // Colonne D
            'judge'            => $row[2], // Colonne E
            'result'           => $row[1], // Colonne F
            'next_date'        => $row[0], // Colonne G (à gauche) -> devient l'index 0
        ]);
    }
}
