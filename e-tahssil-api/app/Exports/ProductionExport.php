<?php

namespace App\Exports;

use App\Models\ProductionCard;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductionExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    protected $year;
    protected $month;

    public function __construct($year, $month = null)
    {
        $this->year = $year;
        $this->month = $month;
    }

    public function collection()
    {
        $query = ProductionCard::query()->whereYear('created_at', $this->year);

        if ($this->month && $this->month !== 'ALL') {
            $query->whereMonth('created_at', $this->month);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    // 1. Définition des en-têtes (sans "السجل")
    public function headings(): array
    {
        return [
            'اسم الموظف',
            'الشعبة',
            'تاريخ الإنجاز',
            'المهام المختارة',
            'الملفات المبلغة',
            'الملفات المنفذة',
            'المبالغ المستخلصة (د.م)',
            'محاضر إيجابية',
            'محاضر سلبية',
            'طلبات الإكراه',
            'عدد الإلغاءات',
            'عدد الإسقاطات',
            'عدد الإستردادات'
        ];
    }

    // 2. Mappage des données avec remplacement des valeurs vides
    public function map($row): array
    {
        $actions = '';
        if (is_array($row->selected_actions)) {
            $actions = implode(' - ', $row->selected_actions);
        } elseif (is_string($row->selected_actions)) {
            $decoded = json_decode($row->selected_actions, true);
            $actions = is_array($decoded) ? implode(' - ', $decoded) : '';
        }

        // On prépare la ligne (sans la colonne registre)
        $mappedRow = [
            $row->employee_name,
            $row->section,
            $row->created_at ? $row->created_at->format('Y-m-d') : null,
            $actions,
            $row->dossiers_notifies,
            $row->dossiers_executes,
            $row->montant_recouvre,
            $row->pv_positif_count,
            $row->pv_negatif_count,
            $row->contrainte,
            $row->dossiers_annulation,
            $row->dossiers_iskatat,
            $row->montant_delegations,
        ];

        // Remplacer uniquement les valeurs strictement nulles ou vides par "-" (pour garder les zéros intacts)
        return array_map(function ($value) {
            if ($value === null || $value === '') {
                return '-';
            }
            return $value;
        }, $mappedRow);
    }

    // 3. Application du design et du sens de lecture (RTL)
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Forcer de droite à gauche
                $sheet->setRightToLeft(true);

                // Style de l'en-tête (Ligne 1) - Fond bleu foncé, texte blanc
                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FFFFFFFF'],
                        'size' => 12,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF003366'],
                    ],
                ]);

                // Style global (Toutes les cellules) - Centrage et Bordures
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF888888'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                // Ajuster spécifiquement la hauteur des lignes
                for ($row = 1; $row <= $highestRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }
            },
        ];
    }
}
