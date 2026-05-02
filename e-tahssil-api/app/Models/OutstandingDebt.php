<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutstandingDebt extends Model
{
    use HasFactory;

    // Autorise l'insertion en masse pour ces colonnes
    protected $fillable = [
        'collectionFileNumber',
        'fullName',
        'judgmentNumber',
        'judgmentDate',
        'assumptionsNumber',
        'assumptionsDate',
        'fines',
        'monetaryConvictions',
        'expenses',
        'lastProcedure',
        'procedureDate',
        'notes',
        'file_year',
    ];
}
