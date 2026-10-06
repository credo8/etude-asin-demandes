<?php

namespace App\Enums;

enum StatutDemande: string
{
    case Deposee = 'deposee';
    case EnCours = 'en_cours';
    case Validee = 'validee';
    case Rejetee = 'rejetee';

    public function transitionsAutorisees(): array
    {
        return match ($this) {
            self::Deposee => [self::EnCours],
            self::EnCours => [self::Validee, self::Rejetee],
            self::Validee, self::Rejetee => [],
        };
    }

    public function peutPasserA(self $cible): bool
    {
        return in_array($cible, $this->transitionsAutorisees(), true);
    }

    public function estFinal(): bool
    {
        return $this->transitionsAutorisees() === [];
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Deposee => 'déposée',
            self::EnCours => 'en cours de traitement',
            self::Validee => 'validée',
            self::Rejetee => 'rejetée',
        };
    }

    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
