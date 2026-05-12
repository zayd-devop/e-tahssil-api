<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'employee_name', 'section', 'registre', 'selected_actions',
        'dossiers_notifies', 'dossiers_executes', 'montant_recouvre',
        'pv_positif', 'pv_positif_count', 'pv_negatif', 'pv_negatif_count', 'contrainte',
        'dossiers_annulation', 'dossiers_iskatat', 'montant_delegations',
        'contre_personnes', 'montant_personnes', 'contre_societes', 'montant_societes'
    ];

    // Conversion automatique du JSON en Array PHP
    protected $casts = [
        'selected_actions' => 'array',
        'pv_positif' => 'boolean',
        'pv_negatif' => 'boolean',
        'contre_personnes' => 'boolean',
        'contre_societes' => 'boolean',
    ];
}
