<?php

namespace App\Models;

use App\Enums\StatutDemande;
use App\Enums\TypeActe;
use Illuminate\Database\Eloquent\Model;

class Demande extends Model
{
    protected $fillable = ['numero_npi', 'type_acte', 'nombre_copies', 'statut', 'motif_rejet'];

    protected function casts(): array
    {
        return [
            'type_acte' => TypeActe::class,
            'statut' => StatutDemande::class,
            'nombre_copies' => 'integer',
        ];
    }
}
