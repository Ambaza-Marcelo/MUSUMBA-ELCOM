<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrIndemnite extends Model
{
    //
    protected $fillable = [
        'code',
        'type_indemnite_id',
        'groupe_indemnite_id',
        'contrat_id',
        'pourcentage_sb',
        'somme_indemnite',
        'created_by',
        'auteur',
    ];

    public function typeIndemnite(){
        return $this->belongsTo('App\Models\HrTypeIndemnite');
    }

    public function groupeIndemnite(){
        return $this->belongsTo('App\Models\HrGroupeIndemnite');
    }

    public function paiement(){
        return $this->hasMany('App\Models\HrPaiement','indemnite_id');
    }

    public function report(){
        return $this->hasMany('App\Models\HrReport','indemnite_id');
    }
}
