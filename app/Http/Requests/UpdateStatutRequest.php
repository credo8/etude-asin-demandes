<?php

namespace App\Http\Requests;

use App\Enums\StatutDemande;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::enum(StatutDemande::class)],
            'motif_rejet' => [
                'nullable',
                'required_if:statut,rejetee',
                'prohibited_unless:statut,rejetee',
                'string',
                'min:3',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'statut.required' => 'Le nouveau statut est obligatoire.',
            'statut.enum' => 'Le statut doit être l\'un de : deposee, en_cours, validee, rejetee.',
            'motif_rejet.required_if' => 'Un rejet doit toujours être motivé : le champ motif_rejet est obligatoire.',
            'motif_rejet.prohibited_unless' => 'Le motif de rejet ne peut être fourni que pour le statut rejetee.',
            'motif_rejet.string' => 'Le motif de rejet doit être un texte.',
            'motif_rejet.min' => 'Le motif de rejet doit comporter au moins 3 caractères.',
            'motif_rejet.max' => 'Le motif de rejet doit comporter au plus 500 caractères.',
        ];
    }
}
