<?php

namespace App\Http\Requests;

use App\Enums\StatutDemande;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDemandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'numero_npi' => ['required', 'regex:/^[0-9]{10}$/'],
            'statut' => ['nullable', Rule::enum(StatutDemande::class)],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_npi.required' => "Le NPI de l'usager est obligatoire pour consulter ses demandes.",
            'numero_npi.regex' => 'Le NPI doit comporter exactement 10 chiffres.',
            'statut.enum' => 'Le statut doit être l\'un de : deposee, en_cours, validee, rejetee.',
            'per_page.integer' => 'Le paramètre per_page doit être un entier.',
            'per_page.min' => 'Le paramètre per_page doit être supérieur ou égal à 1.',
            'page.integer' => 'Le paramètre page doit être un entier.',
            'page.min' => 'Le paramètre page doit être supérieur ou égal à 1.',
        ];
    }
}
