<?php

namespace Tests\Unit;

use App\Enums\StatutDemande;
use PHPUnit\Framework\TestCase;

class StatutDemandeTest extends TestCase
{
    public function test_seules_les_transitions_du_cycle_de_vie_sont_autorisees(): void
    {
        $this->assertTrue(StatutDemande::Deposee->peutPasserA(StatutDemande::EnCours));
        $this->assertTrue(StatutDemande::EnCours->peutPasserA(StatutDemande::Validee));
        $this->assertTrue(StatutDemande::EnCours->peutPasserA(StatutDemande::Rejetee));

        $this->assertFalse(StatutDemande::Deposee->peutPasserA(StatutDemande::Validee));
        $this->assertFalse(StatutDemande::Deposee->peutPasserA(StatutDemande::Rejetee));
        $this->assertFalse(StatutDemande::EnCours->peutPasserA(StatutDemande::Deposee));
        $this->assertFalse(StatutDemande::EnCours->peutPasserA(StatutDemande::EnCours));
    }

    public function test_validee_et_rejetee_sont_des_statuts_finaux(): void
    {
        $this->assertTrue(StatutDemande::Validee->estFinal());
        $this->assertTrue(StatutDemande::Rejetee->estFinal());
        $this->assertFalse(StatutDemande::Deposee->estFinal());
        $this->assertFalse(StatutDemande::EnCours->estFinal());
    }
}
