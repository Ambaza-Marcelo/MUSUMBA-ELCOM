<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrGroupeIndemnite extends Model
{
    //
    protected $fillable = [
        'designation',
        'classe_inferieure',
        'classe_superieure',
    ];

    public function indemnite(){
        return $this->hasMany('App\Models\HrIndemnite','groupe_indemnite_id');
    }
}
