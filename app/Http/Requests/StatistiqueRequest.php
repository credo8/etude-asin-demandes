<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatistiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_npi' => ['nullable', 'regex:/^[0-9]{10}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_npi.regex' => 'Le NPI doit comporter exactement 10 chiffres.',
        ];
    }
}
