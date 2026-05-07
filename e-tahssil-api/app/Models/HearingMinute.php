<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HearingMinute extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_number', 'plaintiff_lawyer', 'defendant_lawyer',
        'subject', 'judge', 'result', 'next_date'
    ];

    // On dit à Laravel d'inclure automatiquement ce champ "virtuel" lors des requêtes JSON
    protected $appends = ['result_color'];

    // Accessor pour générer les couleurs Tailwind côté Backend !
    public function getResultColorAttribute()
    {
        $result = $this->result ?? '';

        if (str_contains($result, 'تأجيل') || str_contains($result, 'تأخير')) {
            return 'text-orange-600 bg-orange-50 border-orange-200';
        }
        if (str_contains($result, 'حجز') || str_contains($result, 'مداولة')) {
            return 'text-purple-600 bg-purple-50 border-purple-200';
        }
        if (str_contains($result, 'حكم') || str_contains($result, 'جاهز')) {
            return 'text-green-600 bg-green-50 border-green-200';
        }
        if (str_contains($result, 'شطب')) {
            return 'text-red-600 bg-red-50 border-red-200';
        }

        return 'text-gray-600 bg-gray-50 border-gray-200'; // Couleur par défaut
    }
}
