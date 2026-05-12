<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'registry_number',
        'execution_order_date',
        'execution_order_number',
        'debtor_name',
        'judicial_fees',
        'pleading_rights',
        'total_amount',
        'debtor_address'
    ];

    // حساب المجموع تلقائياً عند الإضافة أو التعديل
    protected static function booted()
    {
        static::saving(function ($fee) {
            $fee->total_amount = $fee->judicial_fees + $fee->pleading_rights;
        });
    }
}
