<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrNoteInterne extends Model
{
    //
    protected $fillable = [
        'date',
        'title',
        'message',
        'signe_par',
        'confirme_par',
        'departement_id',
    ];

    public function departement(){
        return $this->belongsTo('App\Models\HrDepartement');
    }
}
