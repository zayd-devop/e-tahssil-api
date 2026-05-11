<?php

namespace App\Imports;

use App\Models\SupplementaryFee; // 🔥 On utilise le nouveau modèle
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;

class SupplementaryFeeImport implements ToModel, WithStartRow, WithUpserts, WithChunkReading, WithEvents
{
    private $currentSheetYear = '';

    public function startRow(): int { return 3; }
    public function uniqueBy() { return 'collectionFileNumber'; }
    public function chunkSize(): int { return 1000; }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                $sheetTitle = $event->getSheet()->getTitle();
                $this->currentSheetYear = preg_match('/\d{4}/', $sheetTitle, $matches) ? $matches[0] : $sheetTitle;
            }
        ];
    }

    private function cleanJudicialID($value) {
        if (empty($value)) return null;
        $cleaned = preg_replace('/[A-ZBSUM()=$]/i', '', (string)$value);
        return trim($cleaned, " \t\n\r\0\x0B=+");
    }

    private function hasArabic($str) {
        return preg_match('/\p{Arabic}/u', (string)$str);
    }

    public function model(array $row)
    {
        // 1. 🧠 DÉTECTION INFAILLIBLE : On cherche la colonne du Nom
        $isInversed = false;

        $nameInCol8 = $this->hasArabic($row[8] ?? ''); // Index 8 = Colonne I (Format Inversé)
        $nameInCol4 = $this->hasArabic($row[4] ?? ''); // Index 4 = Colonne E (Format Standard)

        if ($nameInCol8 && !$nameInCol4) {
            $isInversed = true;
        } elseif ($nameInCol4 && !$nameInCol8) {
            $isInversed = false;
        } else {
            // Sécurité extrême : si le nom est manquant, on regarde la colonne A
            $firstCol = trim((string)($row[0] ?? ''));
            // Dans le standard, A est le N° d'ordre (jamais vide). Donc si c'est vide, c'est Inversé.
            if (empty($firstCol) || !is_numeric($firstCol)) {
                $isInversed = true;
            } else {
                $isInversed = false;
            }
        }

        // 2. MAPPING DYNAMIQUE SÉCURISÉ
        if ($isInversed) {
            // FORMAT INVERSÉ (Notes à Gauche : A -> M)
            $idxNotes    = 0;  $idxProcDate = 1;  $idxLastProc = 2;  $idxExpenses = 3;
            $idxMonConv  = 4;  $idxFines    = 5;  $idxAssumpDate = 6; $idxAssumpNum = 7;
            $idxFullName = 8;  $idxJudgDate = 9;  $idxJudgNum  = 10; $idxFileNum  = 11;
        } else {
            // FORMAT STANDARD (Ordre à Gauche : A -> M)
            $idxFileNum  = 1;  $idxJudgNum  = 2;  $idxJudgDate = 3;  $idxFullName = 4;
            $idxAssumpNum = 5; $idxAssumpDate = 6; $idxFines    = 7;  $idxMonConv  = 8;
            $idxExpenses = 9;  $idxLastProc = 10; $idxProcDate = 11; $idxNotes    = 12;
        }

        $rawFileNum = $this->cleanJudicialID($row[$idxFileNum] ?? null);
        $processedFileNum = $this->transformCalculation($rawFileNum);

        // On ignore les lignes vides ou les totaux
        if (empty($processedFileNum) || str_contains((string)($row[$idxFullName] ?? ''), 'المجموع')) {
            return null;
        }

        // 🔥 On crée une instance du nouveau modèle SupplementaryFee
        return new SupplementaryFee([
            'collectionFileNumber' => (string)$processedFileNum,
            'judgmentNumber'       => $this->cleanJudicialID($row[$idxJudgNum] ?? null),
            'judgmentDate'         => $this->transformDate($row[$idxJudgDate] ?? null),
            'fullName'             => empty(trim((string)($row[$idxFullName] ?? ''))) ? 'غير معروف' : trim((string)($row[$idxFullName])),
            'assumptionsNumber'    => $this->cleanJudicialID($row[$idxAssumpNum] ?? null),
            'assumptionsDate'      => $this->transformDate($row[$idxAssumpDate] ?? null),
            'fines'                => $this->transformDecimal($row[$idxFines] ?? null),
            'monetaryConvictions'  => $this->transformDecimal($row[$idxMonConv] ?? null),
            'expenses'             => $this->transformDecimal($row[$idxExpenses] ?? null),
            'lastProcedure'        => $row[$idxLastProc] ?? null,
            'procedureDate'        => $this->transformDate($row[$idxProcDate] ?? null),
            'notes'                => $row[$idxNotes] ?? null,
            'file_year'            => $this->currentSheetYear,
        ]);
    }

    private function transformCalculation($value) {
        if (is_string($value) && str_contains($value, '+')) {
            $parts = explode('+', $value);
            return array_sum(array_map('floatval', $parts));
        }
        return $value;
    }

    private function transformDecimal($value) {
        if (empty($value) || $value === '-') return 0;
        $clean = preg_replace('/[^0-9.+]/', '', (string)$value);
        if (str_contains($clean, '+')) {
            return array_sum(array_map('floatval', explode('+', $clean)));
        }
        return is_numeric($clean) ? (float)$clean : 0;
    }

    private function transformDate($value) {
        if (empty($value) || $value === '-') return null;
        if (is_numeric($value)) {
            if ($value < 3000) return (string)$value;
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) { return (string)$value; }
        }
        return (string)$value;
    }
}
