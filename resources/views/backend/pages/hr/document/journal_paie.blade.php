<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8">
    <style>
        @page { margin: 8mm 6mm; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #000000;
            background: #ffffff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #000000;
            padding: 3px 2px;
            text-align: left;
            vertical-align: middle;
            font-size: 7.5px;
            color: #000000;
            background: #ffffff;
        }
        th {
            font-weight: bold;
            background: #f0f0f0;
            text-align: center;
        }
        .no-border td { border: none; padding: 2px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            text-decoration: underline;
            margin: 6px 0 8px 0;
        }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
@php
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
    $fmt = function ($n) {
        return number_format((float) $n, 0, '.', ' ');
    };

    $totalBrut = 0;
    $totalInssEmp = 0;
    $totalInssEmplr = 0;
    $totalAssEmp = 0;
    $totalAssEmplr = 0;
    $totalIre = 0;
    $totalPret = 0;
    $totalSoins = 0;
    $totalAutre = 0;
    $totalRetenue = 0;
    $totalNet = 0;
    $totalBase = 0;
@endphp

<table class="no-border">
    <tr>
        <td style="width:18%; vertical-align:top;">
            @if($logoSrc)
                <img src="{{ $logoSrc }}" width="110" height="70" alt="MUSUMBA ELCOM">
            @endif
        </td>
        <td style="width:52%; vertical-align:top; font-size:9px;">
            <strong style="font-size:11px;">{{ $companyName }}</strong><br>
            @if(!empty($setting->nif)) NIF: {{ $setting->nif }} @endif
            @if(!empty($setting->rc)) | RC: {{ $setting->rc }} @endif
            <br>
            @if(!empty($setting->telephone1)) Tel: {{ $setting->telephone1 }} @endif
        </td>
        <td style="width:30%; vertical-align:top; text-align:right; font-size:9px;">
            <strong>JOURNAL DE PAIE</strong><br>
            Code: {{ $journal_paie->code ?? '' }}<br>
            DU {{ \Carbon\Carbon::parse($journal_paie->date_debut)->format('d/m/Y') }}<br>
            AU {{ \Carbon\Carbon::parse($journal_paie->date_fin)->format('d/m/Y') }}
        </td>
    </tr>
</table>

<div class="title">
    {{ strtoupper($journal_paie->title ?? 'JOURNAL DE PAIE') }}
</div>

<table>
    <thead>
        <tr>
            <th style="width:3%;">N°</th>
            <th style="width:14%;">NOM et PRENOM</th>
            <th style="width:4%;">JT</th>
            <th style="width:6%;">SAL, BASE</th>
            <th style="width:5%;">All fam</th>
            <th style="width:6%;">Ind Log</th>
            <th style="width:6%;">Ind Dépl</th>
            <th style="width:5%;">Heure suppl</th>
            <th style="width:5%;">AUT_IND</th>
            <th style="width:6%;">Prim /Fonx°</th>
            <th style="width:7%;">REM_BRUT</th>
            <th style="width:6%;">INSS_PENS</th>
            <th style="width:5%;">INSS_RSQ</th>
            <th style="width:5%;">Santé QPP</th>
            <th style="width:7%;">CHARGE_EMPL</th>
            <th style="width:6%;">INSS_TRAV</th>
            <th style="width:5%;">Santé_TRAV</th>
            <th style="width:6%;">IRE Retenu</th>
            <th style="width:5%;">AVANCE</th>
            <th style="width:5%;">Soins méd</th>
            <th style="width:6%;">Aut retenues</th>
            <th style="width:6%;">TOT_RETENU</th>
            <th style="width:7%;">NET A PAYER</th>
        </tr>
    </thead>
    <tbody>
        @foreach($datas as $row)
            @php
                $paie = \App\Support\PayrollCalculator::fromPaiement($row);
                $totalBase += $paie['salaire_base'];
                $totalBrut += $paie['rem_brut'];
                $totalInssEmp += $paie['inss_trav'];
                $totalInssEmplr += $paie['inss_pens'];
                $totalAssEmp += $paie['sante_trav'];
                $totalAssEmplr += $paie['sante_qpp'];
                $totalIre += $paie['ire'];
                $totalPret += $paie['avance'];
                $totalSoins += $paie['soins_medicaux'];
                $totalAutre += $paie['autres_retenues'];
                $totalRetenue += $paie['tot_retenu'];
                $totalNet += $paie['net'];
            @endphp
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td>{{ strtoupper(optional($row->employe)->firstname.' '.optional($row->employe)->lastname) }}</td>
                <td class="text-center">{{ $fmt($row->nbre_jours_ouvrables) }}</td>
                <td class="text-right">{{ $fmt($paie['salaire_base']) }}</td>
                <td class="text-right">{{ $fmt($paie['allocation_familiale']) }}</td>
                <td class="text-right">{{ $fmt($paie['indemnite_logement']) }}</td>
                <td class="text-right">{{ $fmt($paie['indemnite_deplacement']) }}</td>
                <td class="text-right">{{ $fmt($paie['heures_suppl']) }}</td>
                <td class="text-right">{{ $fmt($paie['autres_indemnites']) }}</td>
                <td class="text-right">{{ $fmt($paie['prime_fonction']) }}</td>
                <td class="text-right bold">{{ $fmt($paie['rem_brut']) }}</td>
                <td class="text-right">{{ $fmt($paie['inss_pens']) }}</td>
                <td class="text-right">{{ $fmt($paie['inss_risque']) }}</td>
                <td class="text-right">{{ $fmt($paie['sante_qpp']) }}</td>
                <td class="text-right">{{ $fmt($paie['charge_employeur']) }}</td>
                <td class="text-right">{{ $fmt($paie['inss_trav']) }}</td>
                <td class="text-right">{{ $fmt($paie['sante_trav']) }}</td>
                <td class="text-right">{{ $fmt($paie['ire']) }}</td>
                <td class="text-right">{{ $fmt($paie['avance']) }}</td>
                <td class="text-right">{{ $fmt($paie['soins_medicaux']) }}</td>
                <td class="text-right">{{ $fmt($paie['autres_retenues']) }}</td>
                <td class="text-right bold">{{ $fmt($paie['tot_retenu']) }}</td>
                <td class="text-right bold">{{ $fmt($paie['net']) }}</td>
            </tr>
        @endforeach
        <tr class="bold">
            <td colspan="2" class="text-center">TOTAL</td>
            <td></td>
            <td class="text-right">{{ $fmt($totalBase) }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td class="text-right">{{ $fmt(0) }}</td>
            <td class="text-right">{{ $fmt(0) }}</td>
            <td></td>
            <td class="text-right">{{ $fmt($totalBrut) }}</td>
            <td class="text-right">{{ $fmt($totalInssEmplr) }}</td>
            <td class="text-right">{{ $fmt(2400 * count($datas)) }}</td>
            <td class="text-right">{{ $fmt($totalAssEmplr) }}</td>
            <td class="text-right">{{ $fmt($totalBrut + $totalInssEmplr + $totalAssEmplr + (2400 * count($datas))) }}</td>
            <td class="text-right">{{ $fmt($totalInssEmp) }}</td>
            <td class="text-right">{{ $fmt($totalAssEmp) }}</td>
            <td class="text-right">{{ $fmt($totalIre) }}</td>
            <td class="text-right">{{ $fmt($totalPret) }}</td>
            <td class="text-right">{{ $fmt($totalSoins) }}</td>
            <td class="text-right">{{ $fmt($totalAutre) }}</td>
            <td class="text-right">{{ $fmt($totalRetenue) }}</td>
            <td class="text-right">{{ $fmt($totalNet) }}</td>
        </tr>
    </tbody>
</table>

<br>
<table class="no-border">
    <tr>
        <td style="width:50%; vertical-align:top;">
            Chef Comptable et signature<br><br><br>
            ____________________________
        </td>
        <td style="width:50%; vertical-align:top; text-align:right;">
            Direction et signature<br><br><br>
            ____________________________
        </td>
    </tr>
</table>
</body>
</html>
