<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    // Les champs que Laravel a le droit de remplir automatiquement
    protected $fillable = [
        'dossier_num',
        'debtor_name',
        'debtor_cin',
        'debt_amount',
        'debtor_address',
        'user_id'
    ];

    // Relation avec l'utilisateur (le greffier)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
