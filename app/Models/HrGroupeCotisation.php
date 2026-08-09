<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrGroupeCotisation extends Model
{
    //
    protected $fillable = [
        'designation',
        'classe_inferieure',
        'classe_superieure',
    ];

    public function cotisation(){
        return $this->hasMany('App\Models\HrCotisation','groupe_cotisation_id');
    }
}
