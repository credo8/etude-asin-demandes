<?php

namespace App\Http\Requests;

use App\Enums\TypeActe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDemandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_npi' => ['required', 'regex:/^[0-9]{10}$/'],
            'type_acte' => ['required', Rule::enum(TypeActe::class)],
            'nombre_copies' => ['bail', 'required', 'integer', 'between:1,5'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_npi.required' => 'Le NPI est obligatoire.',
            'numero_npi.regex' => 'Le NPI doit comporter exactement 10 chiffres.',
            'type_acte.required' => "Le type d'acte est obligatoire.",
            'type_acte.enum' => "Le type d'acte doit être l'un de : acte_naissance, casier_judiciaire, certificat_residence.",
            'nombre_copies.required' => 'Le nombre de copies est obligatoire.',
            'nombre_copies.integer' => 'Le nombre de copies doit être un entier.',
            'nombre_copies.between' => 'Le nombre de copies doit être compris entre 1 et 5.',
        ];
    }
}
