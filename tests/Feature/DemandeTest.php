<?php

namespace Tests\Feature;

use App\Enums\StatutDemande;
use App\Enums\TypeActe;
use App\Models\Demande;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemandeTest extends TestCase
{
    use RefreshDatabase;

    private const NPI = '0123456789';

    private function demande(array $attributs = []): Demande
    {
        return Demande::forceCreate(array_merge([
            'numero_npi' => self::NPI,
            'type_acte' => TypeActe::ActeNaissance->value,
            'nombre_copies' => 1,
            'statut' => StatutDemande::Deposee->value,
        ], $attributs));
    }

    private function corps(array $remplacements = []): array
    {
        return array_merge([
            'numero_npi' => self::NPI,
            'type_acte' => 'acte_naissance',
            'nombre_copies' => 2,
        ], $remplacements);
    }

    private function changer(Demande $demande, array $corps)
    {
        return $this->patchJson("/api/demandes/{$demande->id}/statut", $corps);
    }

    public function test_une_demande_valide_est_deposee_avec_le_statut_deposee(): void
    {
        $this->postJson('/api/demandes', $this->corps())
            ->assertCreated()
            ->assertJsonPath('data.numero_npi', self::NPI)
            ->assertJsonPath('data.statut', 'deposee')
            ->assertJsonPath('data.nombre_copies', 2)
            ->assertJsonStructure(['data' => ['id', 'numero_npi', 'type_acte', 'nombre_copies', 'statut', 'motif_rejet', 'created_at', 'updated_at']]);

        $this->assertDatabaseCount('demandes', 1);
    }

    public function test_le_client_ne_peut_pas_imposer_le_statut(): void
    {
        $this->postJson('/api/demandes', $this->corps(['statut' => 'validee']))
            ->assertCreated()
            ->assertJsonPath('data.statut', 'deposee');
    }

    #[DataProvider('depotsInvalides')]
    public function test_un_depot_invalide_est_refuse_avec_un_message_clair(array $remplacements, string $champ, string $message): void
    {
        $this->postJson('/api/demandes', $this->corps($remplacements))
            ->assertStatus(422)
            ->assertJsonPath("errors.{$champ}.0", $message);

        $this->assertDatabaseCount('demandes', 0);
    }

    public static function depotsInvalides(): array
    {
        $npi = 'Le NPI doit comporter exactement 10 chiffres.';
        $type = "Le type d'acte doit être l'un de : acte_naissance, casier_judiciaire, certificat_residence.";
        $entier = 'Le nombre de copies doit être un entier.';
        $intervalle = 'Le nombre de copies doit être compris entre 1 et 5.';

        return [
            'npi trop court' => [['numero_npi' => '123456789'], 'numero_npi', $npi],
            'npi trop long' => [['numero_npi' => '12345678901'], 'numero_npi', $npi],
            'npi avec lettres' => [['numero_npi' => '01234abcde'], 'numero_npi', $npi],
            'type inconnu' => [['type_acte' => 'passeport'], 'type_acte', $type],
            'zéro copie' => [['nombre_copies' => 0], 'nombre_copies', $intervalle],
            'six copies' => [['nombre_copies' => 6], 'nombre_copies', $intervalle],
            'copies décimales' => [['nombre_copies' => 2.5], 'nombre_copies', $entier],
            'copies texte' => [['nombre_copies' => 'abc'], 'nombre_copies', $entier],
        ];
    }

    public function test_les_champs_obligatoires_sont_signales(): void
    {
        $this->postJson('/api/demandes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['numero_npi', 'type_acte', 'nombre_copies']);
    }

    public function test_les_erreurs_sont_en_json_meme_sans_en_tete_accept(): void
    {
        $this->post('/api/demandes', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_la_liste_ne_contient_que_les_demandes_de_l_usager_du_plus_recent_au_plus_ancien(): void
    {
        $ancienne = $this->demande(['created_at' => '2026-01-01 10:00:00']);
        $recente = $this->demande(['created_at' => '2026-01-03 10:00:00']);
        $egale = $this->demande(['created_at' => '2026-01-03 10:00:00']);
        $this->demande(['numero_npi' => '9999999999']);

        $reponse = $this->getJson('/api/demandes?numero_npi='.self::NPI)->assertOk();

        $this->assertSame(
            [$egale->id, $recente->id, $ancienne->id],
            collect($reponse->json('data'))->pluck('id')->all(),
        );
    }

    public function test_le_filtre_par_statut_est_facultatif(): void
    {
        $this->demande();
        $this->demande(['statut' => StatutDemande::EnCours->value]);

        $this->getJson('/api/demandes?numero_npi='.self::NPI)->assertJsonCount(2, 'data');

        $this->getJson('/api/demandes?numero_npi='.self::NPI.'&statut=en_cours')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.statut', 'en_cours');
    }

    #[DataProvider('recherchesInvalides')]
    public function test_une_recherche_invalide_retourne_422(string $requete): void
    {
        $this->getJson('/api/demandes'.$requete)->assertStatus(422);
    }

    public static function recherchesInvalides(): array
    {
        return [
            'npi absent' => [''],
            'npi invalide' => ['?numero_npi=123'],
            'statut inconnu' => ['?numero_npi=0123456789&statut=termine'],
            'page invalide' => ['?numero_npi=0123456789&page=0'],
        ];
    }

    public function test_la_pagination_est_limitee_a_vingt_demandes_par_page(): void
    {
        foreach (range(1, 25) as $ignore) {
            $this->demande();
        }

        $premiere = $this->getJson('/api/demandes?numero_npi='.self::NPI)->assertOk();

        $this->assertCount(20, $premiere->json('data'));
        $this->assertSame(25, $premiere->json('meta.total'));

        $this->getJson('/api/demandes?numero_npi='.self::NPI.'&page=2')->assertJsonCount(5, 'data');
        $this->getJson('/api/demandes?numero_npi='.self::NPI.'&per_page=100')->assertJsonCount(20, 'data');
    }

    public function test_le_detail_d_une_demande_est_retourne(): void
    {
        $demande = $this->demande();

        $this->getJson('/api/demandes/'.$demande->id)
            ->assertOk()
            ->assertJsonPath('data.id', $demande->id);
    }

    public function test_une_demande_inexistante_retourne_404_avec_un_message(): void
    {
        $this->getJson('/api/demandes/99999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Demande introuvable.');

        $this->getJson('/api/demandes/abc')->assertNotFound();
    }

    public function test_le_cycle_de_vie_complet_mene_a_la_validation(): void
    {
        $demande = $this->demande();

        $this->changer($demande, ['statut' => 'en_cours'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'en_cours');

        $this->changer($demande, ['statut' => 'validee'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'validee');
    }

    public function test_un_rejet_motive_est_enregistre(): void
    {
        $demande = $this->demande(['statut' => 'en_cours']);

        $this->changer($demande, ['statut' => 'rejetee', 'motif_rejet' => 'Pièces justificatives illisibles.'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'rejetee')
            ->assertJsonPath('data.motif_rejet', 'Pièces justificatives illisibles.');
    }

    public function test_un_rejet_sans_motif_est_refuse(): void
    {
        $demande = $this->demande(['statut' => 'en_cours']);

        $this->changer($demande, ['statut' => 'rejetee'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motif_rejet');

        $this->assertSame(StatutDemande::EnCours, $demande->fresh()->statut);
    }

    public function test_un_motif_sans_rejet_est_refuse(): void
    {
        $demande = $this->demande(['statut' => 'en_cours']);

        $this->changer($demande, ['statut' => 'validee', 'motif_rejet' => 'Motif inattendu.'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motif_rejet');
    }

    #[DataProvider('transitionsInterdites')]
    public function test_une_transition_interdite_retourne_409(string $depart, string $cible): void
    {
        $demande = $this->demande(['statut' => $depart]);

        $this->changer($demande, [
            'statut' => $cible,
            'motif_rejet' => $cible === 'rejetee' ? 'Motif valable.' : null,
        ])
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertSame($depart, $demande->fresh()->statut->value);
    }

    public static function transitionsInterdites(): array
    {
        return [
            'saut déposée vers validée' => ['deposee', 'validee'],
            'saut déposée vers rejetée' => ['deposee', 'rejetee'],
            'déposée vers déposée' => ['deposee', 'deposee'],
            'en cours vers en cours' => ['en_cours', 'en_cours'],
            'retour en arrière' => ['en_cours', 'deposee'],
            'validée vers en cours' => ['validee', 'en_cours'],
            'validée vers rejetée' => ['validee', 'rejetee'],
            'validée vers validée' => ['validee', 'validee'],
            'rejetée vers en cours' => ['rejetee', 'en_cours'],
            'rejetée vers validée' => ['rejetee', 'validee'],
        ];
    }

    public function test_un_statut_inconnu_retourne_422(): void
    {
        $demande = $this->demande();

        $this->changer($demande, ['statut' => 'termine'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('statut');
    }

    public function test_changer_le_statut_d_une_demande_inexistante_retourne_404(): void
    {
        $this->patchJson('/api/demandes/99999/statut', ['statut' => 'en_cours'])->assertNotFound();
    }

    public function test_les_statistiques_listent_tous_les_statuts_meme_a_zero(): void
    {
        $this->demande();
        $this->demande();
        $this->demande(['statut' => 'validee']);

        $this->getJson('/api/demandes/statistiques')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'total' => 3,
                    'par_statut' => ['deposee' => 2, 'en_cours' => 0, 'validee' => 1, 'rejetee' => 0],
                ],
            ]);
    }

    public function test_les_statistiques_peuvent_cibler_un_usager(): void
    {
        $this->demande();
        $this->demande(['numero_npi' => '9999999999']);

        $this->getJson('/api/demandes/statistiques?numero_npi='.self::NPI)
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_un_npi_invalide_dans_les_statistiques_retourne_422(): void
    {
        $this->getJson('/api/demandes/statistiques?numero_npi=12')->assertStatus(422);
    }
}
