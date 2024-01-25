<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Archivo extends Model
{
    use HasFactory;

    protected $table = 'archivos';

    protected $fillable = [
        'nombre',
        'tipo_de_archivo',
        'tamano_de_archivo',
        'path',

        'asignable_id',
        'asignable_type'
    ];

    public function asignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function path(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Storage::url($value),
        );
    }

}