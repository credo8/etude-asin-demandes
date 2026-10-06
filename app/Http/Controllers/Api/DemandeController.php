<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatutDemande;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexDemandeRequest;
use App\Http\Requests\StatistiqueRequest;
use App\Http\Requests\StoreDemandeRequest;
use App\Http\Requests\UpdateStatutRequest;
use App\Http\Resources\DemandeResource;
use App\Models\Demande;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class DemandeController extends Controller
{
    private const PAR_PAGE_MAX = 20;

    public function store(StoreDemandeRequest $request): JsonResponse
    {
        $demande = Demande::create([
            ...$request->validated(),
            'statut' => StatutDemande::Deposee,
        ]);

        return DemandeResource::make($demande)->response()->setStatusCode(201);
    }

    public function index(IndexDemandeRequest $request): AnonymousResourceCollection
    {
        $parPage = min((int) ($request->validated('per_page') ?? self::PAR_PAGE_MAX), self::PAR_PAGE_MAX);

        $demandes = Demande::query()
            ->where('numero_npi', $request->validated('numero_npi'))
            ->when($request->validated('statut'), fn ($requete, string $statut) => $requete->where('statut', $statut))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($parPage)
            ->withQueryString();

        return DemandeResource::collection($demandes);
    }

    public function show(Demande $demande): DemandeResource
    {
        return DemandeResource::make($demande);
    }

    public function updateStatut(UpdateStatutRequest $request, Demande $demande): JsonResponse|DemandeResource
    {
        $actuel = $demande->statut;
        $cible = StatutDemande::from($request->validated('statut'));

        if (! $actuel->peutPasserA($cible)) {
            return $this->refus($actuel, $cible);
        }

        $modifiees = Demande::whereKey($demande->id)
            ->where('statut', $actuel->value)
            ->update([
                'statut' => $cible->value,
                'motif_rejet' => $cible === StatutDemande::Rejetee ? $request->validated('motif_rejet') : null,
            ]);

        if ($modifiees === 0) {
            return $this->refus($demande->fresh()->statut, $cible);
        }

        return DemandeResource::make($demande->refresh());
    }

    public function statistiques(StatistiqueRequest $request): JsonResponse
    {
        $comptes = DB::table('demandes')
            ->when($request->validated('numero_npi'), fn ($requete, string $npi) => $requete->where('numero_npi', $npi))
            ->selectRaw('statut, COUNT(*) AS total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        $parStatut = collect(StatutDemande::cases())
            ->mapWithKeys(fn (StatutDemande $statut) => [$statut->value => (int) ($comptes[$statut->value] ?? 0)]);

        return response()->json([
            'data' => [
                'total' => $parStatut->sum(),
                'par_statut' => $parStatut,
            ],
        ]);
    }

    private function refus(StatutDemande $actuel, StatutDemande $cible): JsonResponse
    {
        if ($actuel->estFinal()) {
            $message = "Cette demande est {$actuel->libelle()} : son statut ne peut plus changer.";
        } else {
            $autorises = implode(' ou ', array_map(
                fn (StatutDemande $statut) => "« {$statut->libelle()} »",
                $actuel->transitionsAutorisees(),
            ));
            $message = "Transition interdite : une demande {$actuel->libelle()} ne peut pas passer à « {$cible->libelle()} ». Statut autorisé : {$autorises}.";
        }

        return response()->json(['message' => $message], 409);
    }
}
