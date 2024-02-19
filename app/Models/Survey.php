<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Survey extends Model
{
    use HasFactory;

    const TYPE_TEXT = 'text';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_SELECT = 'select';
    const TYPE_RADIO = 'radio';
    const TYPE_CHECKBOX = 'checkbox';

    protected $fillable = [
        'evaluator_id',
        'image',
        'title',
        'slug',
        'status',
        'description',
        'expire_date'
    ];

    protected $appends = ['imagen'];

    public function imagen(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image ? Storage::disk('s3')->url($this->image) : null
        );
    }

    protected function defaultPathFolder(): Attribute
    {
        return Attribute::make(
            get: fn () => "surveys/id_" . $this->id . "/foto_de_encuesta",
        );
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function question()
    {
        return $this->hasMany(SurveyQuestion::class, 'survey_id');
    }

    public function answer()
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    public function evaluee()
    {
        return $this->belongsToMany(User::class, 'p_survey_evaluee', 'survey_id', 'evaluee_id')->withTimestamps();
    }
}
