<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style type="text/css">
        @page {
            margin: 12mm 10mm 14mm 10mm;
        }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .ledger-page {
            page-break-after: always;
        }
        .ledger-page:last-child {
            page-break-after: auto;
        }
        .company-block {
            font-size: 7.5px;
            line-height: 1.35;
            margin-bottom: 6px;
        }
        .doc-title {
            text-align: center;
            margin: 4px 0 2px;
        }
        .doc-title h2 {
            margin: 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-title h3 {
            margin: 2px 0 0;
            font-size: 10px;
            font-weight: normal;
        }
        .meta-line {
            font-size: 7.5px;
            margin: 0 0 6px;
        }
        table.ledger-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.ledger-grid th,
        table.ledger-grid td {
            border: 1px solid #000;
            padding: 2px 3px;
            vertical-align: top;
        }
        table.ledger-grid thead th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            font-size: 7px;
            padding: 3px 2px;
        }
        .col-date { width: 9%; }
        .col-piece { width: 5%; }
        .col-doc { width: 10%; }
        .col-label { width: 34%; }
        .col-amount { width: 10.5%; }
        .col-label-cell {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.25;
        }
        .col-amount-cell {
            white-space: nowrap;
            text-align: right;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        tr.account-header td {
            font-weight: bold;
            background-color: #fff;
            padding: 3px;
        }
        tr.account-total td {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        tr.carry-forward td {
            font-weight: bold;
            background-color: #f0f0f0;
            border-top: 2px solid #000;
        }
        .account-tag {
            font-size: 7px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
@foreach ($pages as $pageIndex => $page)
    <div class="ledger-page">
        <div class="company-block">
            @if($setting)
                {{ $setting->commune }}-{{ $setting->zone }}, {{ $setting->rue }}<br>
                @if(!empty($setting->nif) || !empty($setting->rc))
                    @if(!empty($setting->nif))NIF : {{ $setting->nif }}@endif
                    @if(!empty($setting->rc))-RC : {{ $setting->rc }}@endif
                    <br>
                @endif
                {{ $setting->telephone1 }}-{{ $setting->telephone2 }}<br>
                {{ $setting->email }}
            @endif
        </div>

        <div class="doc-title">
            <h2>@lang('messages.GRAND LIVRE DES COMPTES')</h2>
            <h3>{{ config('app.name', 'EGOTEK') }}</h3>
        </div>

        <p class="meta-line">
            <strong>@lang('messages.Période :')</strong>
            {{ $periodStartFormatted }} @lang('messages.ledger_period_to') {{ $periodEndFormatted }}
            &nbsp;&nbsp;
            <strong>@lang('messages.Compte :')</strong> {{ $accountLabel }}
            &nbsp;&nbsp;
            <strong>@lang('messages.ledger_page')</strong> {{ $pageIndex + 1 }}
        </p>
        <p class="meta-line">
            <strong>@lang('messages.Date de tirage :')</strong> {{ $printedAt }}
        </p>

        <table class="ledger-grid">
            <thead>
                <tr>
                    <th rowspan="2" class="col-date">@lang('messages.date')</th>
                    <th rowspan="2" class="col-piece">@lang('messages.N° pièce')</th>
                    <th rowspan="2" class="col-doc">@lang('messages.N° Document')</th>
                    <th rowspan="2" class="col-label">@lang('messages.Libellé écriture')</th>
                    <th colspan="2" class="text-center">@lang('messages.MOUVEMENTS')</th>
                    <th colspan="2" class="text-center">@lang('messages.SOLDES')</th>
                </tr>
                <tr>
                    <th class="col-amount">@lang('messages.DÉBIT')</th>
                    <th class="col-amount">@lang('messages.CRÉDIT')</th>
                    <th class="col-amount">@lang('messages.DÉBIT')</th>
                    <th class="col-amount">@lang('messages.CRÉDIT')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($page['blocks'] as $block)
                    @if ($block['type'] === 'account_header')
                        @php $section = $block['section']; @endphp
                        <tr class="account-header">
                            <td colspan="8">
                                <div class="account-tag">@lang('messages.ledger_account_header')</div>
                                <div>{{ $section['account_number'] }} {{ $section['account_name'] }}</div>
                            </td>
                        </tr>
                    @elseif ($block['type'] === 'movement')
                        @php $row = $block['row']; @endphp
                        <tr>
                            <td class="text-center">{{ $row['operation_date'] }}</td>
                            <td class="text-center">{{ $row['cj'] }}</td>
                            <td class="text-center col-label-cell">{{ $row['document_no'] }}</td>
                            <td class="col-label-cell">{{ $row['label'] }}</td>
                            <td class="col-amount-cell">{{ number_format($row['debit'], 0, ',', ' ') }}</td>
                            <td class="col-amount-cell">{{ number_format($row['credit'], 0, ',', ' ') }}</td>
                            <td class="col-amount-cell">{{ number_format($row['balance_debit'], 0, ',', ' ') }}</td>
                            <td class="col-amount-cell">{{ number_format($row['balance_credit'], 0, ',', ' ') }}</td>
                        </tr>
                    @elseif ($block['type'] === 'account_total')
                        @php $section = $block['section']; @endphp
                        <tr class="account-total">
                            <td colspan="4">@lang('messages.ledger_account_total') {{ $section['account_number'] }}</td>
                            <td class="col-amount-cell">{{ number_format($section['total_debit'], 0, ',', ' ') }}</td>
                            <td class="col-amount-cell">{{ number_format($section['total_credit'], 0, ',', ' ') }}</td>
                            <td class="col-amount-cell">{{ number_format($section['total_balance_debit'], 0, ',', ' ') }}</td>
                            <td class="col-amount-cell">{{ number_format($section['total_balance_credit'], 0, ',', ' ') }}</td>
                        </tr>
                    @endif
                @endforeach

                <tr class="carry-forward">
                    <td colspan="4" class="text-right"><strong>@lang('messages.A reporter :')</strong></td>
                    <td class="col-amount-cell"><strong>{{ number_format($page['carry_forward']['debit'], 0, ',', ' ') }}</strong></td>
                    <td class="col-amount-cell"><strong>{{ number_format($page['carry_forward']['credit'], 0, ',', ' ') }}</strong></td>
                    <td class="col-amount-cell"><strong>{{ number_format($page['carry_forward']['balance_debit'], 0, ',', ' ') }}</strong></td>
                    <td class="col-amount-cell"><strong>{{ number_format($page['carry_forward']['balance_credit'], 0, ',', ' ') }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
