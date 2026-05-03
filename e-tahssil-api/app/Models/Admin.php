<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    protected $fillable = [
        'nom',
        'prenom',
        'type_responsabilite',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
