<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Action extends Model {
    protected $fillable = ['action_category_id', 'name'];
}
