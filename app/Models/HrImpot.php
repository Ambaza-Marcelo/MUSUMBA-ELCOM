<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrImpot extends Model
{
    //
    protected $fillable = [
        'groupe_impot_id',
        'pourcentage_impot',
        'created_by',
    ];

    public function groupeImpot(){
        return $this->belongsTo('App\Models\HrGroupeImpot');
    }
}
