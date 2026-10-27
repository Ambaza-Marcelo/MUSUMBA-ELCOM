<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm 10mm; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #000000;
            background: #ffffff;
        }
        :root {
            --brand-yellow: #eba91b;
            --brand-black: #000000;
            --brand-gray: #a7a9ad;
            --brand-soft: #f5f5f5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }
        th, td {
            border: 1px solid #000000;
            padding: 5px 4px;
            text-align: left;
            vertical-align: middle;
            font-size: 9px;
            color: #000000;
            background: #ffffff;
        }
        th {
            font-weight: bold;
            background: var(--brand-soft);
        }
        .no-border td { border: none; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin: 8px 0 10px 0;
            background: var(--brand-yellow);
            color: var(--brand-black);
            border: 1px solid var(--brand-black);
            padding: 7px 8px;
        }
        .bold { font-weight: bold; }
        .brand-head th {
            background: var(--brand-yellow);
            color: var(--brand-black);
        }
        .brand-total td,
        .brand-total th {
            background: #fff6df;
            color: var(--brand-black);
        }
    </style>
</head>
<body>
@php
    $employe = $data->employe ?? null;
    $companyName = $setting->name ?? ($setting->company_name ?? 'MUSUMBA ELCOM');
    $logoPath = public_path('img/logo.jpeg');
    if (!file_exists($logoPath)) {
        $logoPath = public_path('img/logo.png');
    }
    $logoSrc = null;
    if (file_exists($logoPath)) {
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';
        $logoSrc = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($logoPath));
    }

    $paie = \App\Support\PayrollCalculator::fromPaiement($data);
    $salaire_brut = $paie['rem_brut'];
    $base_imposable = $paie['base_ire'];
    $taux_ire = $paie['taux_ire'];
    $somme_impot = $paie['ire'];
    $total_deductions = $paie['tot_retenu'];
    $net_a_payer = $paie['net'];

    $anciennete = '';
    if ($employe && !empty($employe->date_debut)) {
        $jours = \Carbon\Carbon::parse($employe->date_debut)->diffInDays(\Carbon\Carbon::now());
        if ($jours < 30) {
            $anciennete = number_format($jours, 0, '.', '') . ' jour(s)';
        } elseif ($jours < 365) {
            $anciennete = number_format($jours / 30, 0, '.', '') . ' mois';
        } else {
            $anciennete = number_format($jours / 365.25, 0, '.', '') . ' an(s)';
        }
    }

    $personnes_a_charge = 0;
    if ($employe) {
        $children = (int) ($employe->children_number ?? 0);
        $personnes_a_charge = ((string) $employe->statut_matrimonial === '1') ? ($children + 1) : $children;
    }

    $fmt = function ($n) {
        return number_format((float) $n, 0, '.', ' ');
    };
@endphp

<table class="no-border">
    <tr>
        <td style="width:22%; vertical-align:top;">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" width="125" height="85" alt="MUSUMBA ELCOM">
            @endif
        </td>
        <td style="width:48%; vertical-align:top; font-size:9px;">
            <strong style="font-size:11px;">{{ $companyName }}</strong><br>
            @if(!empty($setting->nif)) NIF: {{ $setting->nif }} @endif
            @if(!empty($setting->rc)) | RC: {{ $setting->rc }} @endif
            <br>
            @if(!empty($setting->telephone1)) Tel: {{ $setting->telephone1 }} @endif
            @if(!empty($setting->email)) | {{ $setting->email }} @endif
        </td>
        <td style="width:30%; vertical-align:top; text-align:right; font-size:10px;">
            <strong>Periode : {{ \Carbon\Carbon::parse($data->date_fin)->format('m/Y') }}</strong>
        </td>
    </tr>
</table>

<div class="title">
    BULLETIN DE PAIE DU {{ \Carbon\Carbon::parse($data->date_debut)->format('d/m/Y') }}
    AU {{ \Carbon\Carbon::parse($data->date_fin)->format('d/m/Y') }}
</div>

<table>
    <tr>
        <th style="width:22%;">NOM ET PRENOM</th>
        <td style="width:28%;">{{ $employe ? strtoupper($employe->firstname.' '.$employe->lastname) : '' }}</td>
        <th style="width:22%;">MATRICULE</th>
        <td style="width:28%;">{{ $employe->matricule_no ?? '' }}</td>
    </tr>
    <tr>
        <th>NUMERO DE COMPTE</th>
        <td>{{ $employe->numero_compte ?? '' }}</td>
        <th>CNI</th>
        <td>{{ $employe->cni ?? '' }}</td>
    </tr>
    <tr>
        <th>FONCTION</th>
        <td>{{ optional(optional($employe)->fonction)->name }}</td>
        <th>ANCIENNETE</th>
        <td>{{ $anciennete }}</td>
    </tr>
    <tr>
        <th>GENRE</th>
        <td>{{ ($employe && (string)$employe->gender === '1') ? 'MASCULIN' : 'FEMININ' }}</td>
        <th>Personnes a charge</th>
        <td>{{ $personnes_a_charge }}</td>
    </tr>
    <tr>
        <th>DATE D'EMBAUCHE</th>
        <td>{{ ($employe && $employe->date_debut) ? \Carbon\Carbon::parse($employe->date_debut)->format('d/m/Y') : '' }}</td>
        <th>DATE DE FIN</th>
        <td></td>
    </tr>
</table>

<table>
    <thead>
        <tr class="brand-head">
            <th style="width:28%;">RUBRIQUES</th>
            <th style="width:18%;" class="text-right">BASE</th>
            <th style="width:18%;" class="text-center">TAUX (EMPLOYE)</th>
            <th style="width:18%;" class="text-right">MONTANT EMPLOYE</th>
            <th style="width:18%;" class="text-right">MONTANT EMPLOYEUR</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>SALAIRE DE BASE</td>
            <td class="text-right">{{ $fmt($paie['salaire_base']) }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>ALL. FAMILIALE</td>
            <td></td>
            <td></td>
            <td class="text-right">{{ $fmt($paie['allocation_familiale']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td>IND. DE DEPLACEMENT</td>
            <td class="text-right">{{ $fmt($paie['salaire_base']) }}</td>
            <td class="text-center">15%</td>
            <td class="text-right">{{ $fmt($paie['indemnite_deplacement']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td>IND. DE LOGEMENT</td>
            <td class="text-right">{{ $fmt($paie['salaire_base']) }}</td>
            <td class="text-center">60%</td>
            <td class="text-right">{{ $fmt($paie['indemnite_logement']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td>PRIME DE FONCTION</td>
            <td></td>
            <td></td>
            <td class="text-right">{{ $fmt($paie['prime_fonction']) }}</td>
            <td></td>
        </tr>
        <tr class="bold brand-total">
            <td>REM_BRUT</td>
            <td class="text-right">{{ $fmt($paie['salaire_base']) }}</td>
            <td></td>
            <td class="text-right">{{ $fmt($salaire_brut) }}</td>
            <td></td>
        </tr>
        <tr>
            <td>INSS_TRAV / INSS_PENS</td>
            <td class="text-right">{{ $salaire_brut < 450000 ? $fmt($salaire_brut) : '450 000' }}</td>
            <td class="text-center">4%</td>
            <td class="text-right">{{ $fmt($paie['inss_trav']) }}</td>
            <td class="text-right">{{ $fmt($paie['inss_pens']) }}</td>
        </tr>
        <tr>
            <td>ASSURANCE MALADIE</td>
            <td class="text-right">400 000</td>
            <td class="text-center">4%</td>
            <td class="text-right">{{ $fmt($paie['sante_trav']) }}</td>
            <td class="text-right">{{ $fmt($paie['sante_qpp']) }}</td>
        </tr>
        <tr>
            <td>SOINS MEDICAUX</td>
            <td></td>
            <td></td>
            <td class="text-right">{{ $fmt($paie['soins_medicaux']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td>IRE Retenu</td>
            <td class="text-right">{{ $fmt($base_imposable) }}</td>
            <td class="text-center">{{ $taux_ire }}</td>
            <td class="text-right">{{ $fmt($somme_impot) }}</td>
            <td></td>
        </tr>
        <tr>
            <td>AVANCE</td>
            <td class="text-right">{{ $fmt($salaire_brut) }}</td>
            <td></td>
            <td class="text-right">{{ $fmt($paie['avance']) }}</td>
            <td></td>
        </tr>
        <tr>
            <td>AUT RETENUES</td>
            <td class="text-right">{{ $fmt($salaire_brut) }}</td>
            <td></td>
            <td class="text-right">{{ $fmt($paie['autres_retenues']) }}</td>
            <td></td>
        </tr>
        <tr class="bold brand-total">
            <td>TOT_RETENU</td>
            <td class="text-right">{{ $fmt($salaire_brut) }}</td>
            <td></td>
            <td class="text-right">{{ $fmt($total_deductions) }}</td>
            <td></td>
        </tr>
        <tr class="bold brand-total">
            <td>NET A PAYER</td>
            <td class="text-right">{{ $fmt($salaire_brut) }}</td>
            <td></td>
            <td class="text-right">{{ $fmt($net_a_payer) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr class="brand-head">
            <th style="width:20%;"></th>
            <th style="width:27%;" class="text-right">REM_BRUT</th>
            <th style="width:27%;" class="text-right">TOT_RETENU</th>
            <th style="width:26%;" class="text-right">NET A PAYER</th>
        </tr>
    </thead>
    <tbody>
        <tr class="bold brand-total">
            <td>MENSUEL</td>
            <td class="text-right">{{ $fmt($salaire_brut) }}</td>
            <td class="text-right">{{ $fmt($total_deductions) }}</td>
            <td class="text-right">{{ $fmt($net_a_payer) }}</td>
        </tr>
    </tbody>
</table>

<table>
    <thead>
        <tr>
            <th style="width:50%;">CHEF DU PERSONNEL ET SIGNATURE</th>
            <th style="width:50%;">EMPLOYE ET SIGNATURE</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="height:50px; vertical-align:top;"></td>
            <td style="height:50px; vertical-align:top;">
                {{ $employe ? strtoupper($employe->firstname.' '.$employe->lastname) : '' }} .........................
            </td>
        </tr>
    </tbody>
</table>
</body>
</html>
