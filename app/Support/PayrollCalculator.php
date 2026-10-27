<?php

namespace App\Support;

/**
 * Calcul du journal de paie, aligné sur le journal Excel :
 * logement 60 %, déplacement 15 %, INSS 4 % / 6 % plafonné à 450 000,
 * risque employeur 2 400, IRE par tranches.
 * L'assurance maladie est obligatoire pour chaque salarié, sans exception :
 * 4 % et 6 % d'une base de 400 000, soit 16 000 (employé) et 24 000 (employeur).
 */
class PayrollCalculator
{
    public const PLAFOND_INSS = 450000;
    public const BASE_ASSURANCE_MALADIE = 400000;
    /** À partir de ce brut, le journal applique déjà le plafond INSS (18 000 / 27 000). */
    public const SEUIL_PLAFOND_INSS = 400000;
    public const TAUX_INSS_EMPLOYE = 0.04;
    public const TAUX_INSS_EMPLOYEUR = 0.06;
    public const INSS_RISQUE = 2400;
    public const TAUX_LOGEMENT = 0.60;
    public const TAUX_DEPLACEMENT = 0.15;

    public static function fromPaiement($data, ?float $avance = null): array
    {
        return self::calculate(
            (float) ($data->somme_salaire_base ?? 0),
            (float) ($data->allocation_familiale ?? 0),
            (float) ($data->prime_fonction ?? 0),
            $avance !== null ? $avance : (float) ($data->retenue_pret ?? 0),
            (float) ($data->soins_medicaux ?? 0),
            (float) ($data->autre_retenue ?? 0)
        );
    }

    public static function calculate(
        float $salaireBase,
        float $allocationFamiliale = 0,
        float $primeFonction = 0,
        float $avance = 0,
        float $soinsMedicaux = 0,
        float $autresRetenues = 0,
        float $heuresSuppl = 0,
        float $autresIndemnites = 0
    ): array {
        $santeTrav = self::BASE_ASSURANCE_MALADIE * self::TAUX_INSS_EMPLOYE;
        $santeQpp = self::BASE_ASSURANCE_MALADIE * self::TAUX_INSS_EMPLOYEUR;
        $logement = $salaireBase * self::TAUX_LOGEMENT;
        $deplacement = $salaireBase * self::TAUX_DEPLACEMENT;
        $remBrut = $salaireBase
            + $allocationFamiliale
            + $logement
            + $deplacement
            + $heuresSuppl
            + $autresIndemnites
            + $primeFonction;

        $assietteInss = ($remBrut >= self::SEUIL_PLAFOND_INSS)
            ? self::PLAFOND_INSS
            : $remBrut;
        $inssTrav = $assietteInss * self::TAUX_INSS_EMPLOYE;
        $inssPens = $assietteInss * self::TAUX_INSS_EMPLOYEUR;
        $inssRisque = self::INSS_RISQUE;

        $baseIre = $remBrut - $logement - $deplacement - $inssTrav - $santeTrav;
        if ($baseIre <= 150000) {
            $ire = 0.0;
            $tauxIre = '0%';
        } elseif ($baseIre <= 300000) {
            $ire = ($baseIre - 150000) * 0.20;
            $tauxIre = '20%';
        } else {
            $ire = (150000 * 0.20) + (($baseIre - 300000) * 0.30);
            $tauxIre = '30%';
        }

        $totRetenu = $inssTrav + $santeTrav + $ire + $avance + $soinsMedicaux + $autresRetenues;
        $net = $remBrut - $totRetenu;
        $chargeEmployeur = $remBrut + $inssPens + $inssRisque + $santeQpp;

        return [
            'salaire_base' => $salaireBase,
            'allocation_familiale' => $allocationFamiliale,
            'indemnite_logement' => $logement,
            'indemnite_deplacement' => $deplacement,
            'heures_suppl' => $heuresSuppl,
            'autres_indemnites' => $autresIndemnites,
            'prime_fonction' => $primeFonction,
            'rem_brut' => $remBrut,
            'inss_pens' => $inssPens,
            'inss_risque' => $inssRisque,
            'sante_qpp' => $santeQpp,
            'charge_employeur' => $chargeEmployeur,
            'inss_trav' => $inssTrav,
            'sante_trav' => $santeTrav,
            'base_ire' => $baseIre,
            'taux_ire' => $tauxIre,
            'ire' => $ire,
            'avance' => $avance,
            'soins_medicaux' => $soinsMedicaux,
            'autres_retenues' => $autresRetenues,
            'tot_retenu' => $totRetenu,
            'net' => $net,
        ];
    }
}
