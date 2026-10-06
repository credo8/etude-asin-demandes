<?php

namespace App\Enums;

enum TypeActe: string
{
    case ActeNaissance = 'acte_naissance';
    case CasierJudiciaire = 'casier_judiciaire';
    case CertificatResidence = 'certificat_residence';

    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
