<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrJournalConge extends Model
{
    //
    protected $fillable = [
        'date_heure_debut',
        'date_heure_fin',
        'nbre_jours_conge_pris',
        'nbre_heures_conge_pris',
        'etat',
        'code',
        'valide_par',
        'confirme_par',
        'approuve_par',
        'auteur',
        'employe_id',
        'type_conge_id',
        'company_id',
    ];

    public function employe(){
        return $this->belongsTo('App\Models\HrEmploye');
    }

    public function typeConge(){
        return $this->belongsTo('App\Models\HrTypeConge');
    }

    public function company(){
        return $this->belongsTo('App\Models\HrCompany');
    }
}
