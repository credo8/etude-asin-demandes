<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemandeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_npi' => (string) $this->numero_npi,
            'type_acte' => $this->type_acte->value,
            'nombre_copies' => $this->nombre_copies,
            'statut' => $this->statut->value,
            'motif_rejet' => $this->motif_rejet,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
