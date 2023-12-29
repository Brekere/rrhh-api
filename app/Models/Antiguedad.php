<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Antiguedad extends Model
{
    use HasFactory;

    protected $table = 'antiguedades';

    protected $fillable = [
        'añosCumplidos',
        'diasCorresopondientes',
        'regimen'
    ];

    public function empleado(){
        return $this->hasMany(Empleado::class,'antiguedad_id');
    }
}
