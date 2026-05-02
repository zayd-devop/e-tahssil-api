<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    protected $fillable = [
        'fileNumber', 'parties', 'role', 'address', 'decision', 'judgmentNumber'
    ];

    protected $casts = [
        'parties' => 'array',
    ];
}
