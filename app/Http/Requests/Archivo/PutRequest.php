<?php

namespace App\Http\Requests\Archivo;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string'],
            'tipo_de_archivo' => ['required', 'string'],
            'tamano_de_archivo' => ['required', 'numeric'],
            'path' => ['required', 'string', Rule::unique('archivos')->ignore($this->route("Archivo")->id)],
            'asignable_id' => ['required', 'numeric'],
            'asignable_type' => ['required', 'string'],
        ];
    }
}
