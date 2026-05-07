<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HearingMinute extends Model {
    use HasFactory;

    protected $fillable = [
        'file_number', 'judgment_type', 'judgment_date', 'judgment_number',
        'ordinal_number', 'decision_content', 'judge', 'subject',
        'result_color', 'user_id'
    ];

    public function user() {
        return $this->belongsTo(User::class,'user_id');
    }
}
