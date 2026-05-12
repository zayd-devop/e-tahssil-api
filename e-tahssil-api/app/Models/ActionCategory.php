<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActionCategory extends Model {
    protected $fillable = ['section_id', 'name'];
    public function actions() {
        return $this->hasMany(Action::class);
    }
}
