<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    protected $fillable = [
        'fileNumber', 'parties', 'role', 'address', 'decision', 'judgmentNumber','user_id'
    ];

    protected $casts = [
        'parties' => 'array',
    ];
}
