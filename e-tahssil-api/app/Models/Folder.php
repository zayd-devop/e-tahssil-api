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
    'debtor_address',
    'debt_amount',
    'user_id',
    'document_type', // 👈 À AJOUTER ICI
];

    // Relation avec l'utilisateur (le greffier)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
