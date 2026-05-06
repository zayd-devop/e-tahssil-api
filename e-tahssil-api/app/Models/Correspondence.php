<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Correspondence extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'registration_number',
        'sender_from',
        'recipient_to',
        'recipient_supervisors',
        'subject',
        'attachments_count',
        'notes',
        'signer_name',
        'signer_role',
    ];

    protected $casts = [
        'recipient_supervisors' => 'array', // Transforme automatiquement le JSON en tableau PHP
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
