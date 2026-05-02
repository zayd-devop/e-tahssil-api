<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FraisStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'year',
        'extraits_dossiers', 'extraits_montant',
        'frais_dossiers', 'frais_montant',
        'assist_dossiers', 'assist_montant',
        'injonc_dossiers', 'injonc_montant',
        'titres_dossiers', 'titres_montant'
    ];
}
