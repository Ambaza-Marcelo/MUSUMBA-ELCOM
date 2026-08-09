<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrTypeAbsence extends Model
{
    protected $table = 'hr_type_absences';

    protected $fillable = [
        'type',
        'name',
        'created_by',
    ];
}
