<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport de recouvrement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        .header { margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; margin-bottom: 6px; }
        .meta { font-size: 11px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 6px; }
        th { background: #f3f3f3; text-align: left; }
        .right { text-align: right; }
        .totals { margin-top: 18px; width: 45%; margin-left: auto; }
        .totals td { border: 1px solid #ccc; padding: 6px; }
        .totals .label { font-weight: bold; background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Rapport de recouvrement</div>
        <div class="meta">
            Periode :
            {{ $periodStart ? \Carbon\Carbon::parse($periodStart)->format('d/m/Y') : 'N/A' }}
            au
            {{ $periodEnd ? \Carbon\Carbon::parse($periodEnd)->format('d/m/Y') : 'N/A' }}
        </div>
        @if($setting)
            <div class="meta">{{ $setting->company_name ?? $setting->name_company ?? '' }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Numero facture</th>
                <th>Date</th>
                <th>Client</th>
                <th>Montant credit</th>
                <th>Montant recouvre</th>
                <th>Reste a payer</th>
                <th>Statut</th>
                <th>Charge de recouvrement</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row['invoice_number'] }}</td>
                    <td>{{ $row['invoice_date'] }}</td>
                    <td>{{ $row['client_name'] }}</td>
                    <td class="right">{{ number_format($row['total'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($row['paid'], 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($row['remaining'], 0, ',', ' ') }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['collector'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Total credit</td>
            <td class="right">{{ number_format($totalCredit, 0, ',', ' ') }} BIF</td>
        </tr>
        <tr>
            <td class="label">Total recouvre</td>
            <td class="right">{{ number_format($totalPaid, 0, ',', ' ') }} BIF</td>
        </tr>
        <tr>
            <td class="label">Reste a payer</td>
            <td class="right">{{ number_format($totalRemaining, 0, ',', ' ') }} BIF</td>
        </tr>
    </table>
</body>
</html>
