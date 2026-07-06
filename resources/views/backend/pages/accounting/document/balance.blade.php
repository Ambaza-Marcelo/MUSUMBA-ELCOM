<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style type="text/css">
        @page {
            margin: 10mm 8mm 14mm 8mm;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8px;
            color: #000;
            margin: 0;
            padding: 0;
        }

        table {
            border-collapse: collapse;
        }

        .doc-header-wrap {
            margin-bottom: 8px;
        }

        .top-header {
            width: 100%;
            margin-bottom: 6px;
        }

        .top-header td {
            border: none;
            vertical-align: top;
            padding: 0;
        }

        .doc-ref {
            font-size: 9px;
        }

        .filter-label {
            font-size: 8px;
            margin-top: 2px;
        }

        .period-box {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 8px;
            line-height: 1.35;
            width: 44%;
        }

        .period-box p {
            margin: 0 0 2px;
        }

        .title-block {
            text-align: center;
            margin: 8px 0 4px;
        }

        .title-block h1 {
            margin: 0;
            font-size: 13px;
            font-weight: bold;
        }

        .title-block h2 {
            margin: 3px 0 0;
            font-size: 10px;
            font-weight: normal;
        }

        .print-date {
            text-align: center;
            font-size: 8px;
            margin: 6px 0 10px;
        }

        .balance-table,
        .carry-forward-table {
            width: 100%;
            table-layout: fixed;
        }

        .balance-table th,
        .balance-table td {
            border: 1px solid #000;
            padding: 2px 4px;
            font-size: 8px;
            vertical-align: middle;
        }

        .balance-table thead th {
            background-color: #eef2f7;
            font-weight: bold;
            text-align: center;
        }

        .col-number {
            width: 11%;
        }

        .col-name {
            width: 33%;
        }

        .col-amount {
            width: 14%;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .account-name {
            text-transform: uppercase;
        }

        .summary-label {
            font-weight: bold;
        }

        .report-row td {
            background-color: #f8f9fb;
        }

        .carry-forward-block {
            width: 100%;
            page-break-inside: avoid;
            margin-top: 0;
        }

        .carry-forward-table {
            width: 100%;
            table-layout: fixed;
        }

        .carry-forward-table td {
            border: 1px solid #000;
            padding: 2px 4px;
            font-size: 8px;
            vertical-align: middle;
            font-weight: bold;
            background-color: #eef2f7;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        .account-row {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    @php
        $formatAmount = [\App\Support\BalanceReportBuilder::class, 'formatAmount'];
        $scopeLabel = $reportScope ?? \App\Support\BalanceReportBuilder::reportScopeLabel($filterLabel);
        $balanceQrPayload = __('messages.accounts_balance_title') . ' - ' . $fiscalYear . ' - ' . (optional($setting)->name ?? $companyName);
    @endphp

    <div class="doc-header-wrap">
        <table style="width:100%; border:none; border-bottom:1px solid #333; margin-bottom:8px;">
            <tr>
                <td style="border:none; width:18%; vertical-align:top;">
                    @php $publicLogoPath = public_path('img/logo.jpeg'); @endphp
                    @if(file_exists($publicLogoPath))
                        <img src="{{ asset('img/logo.jpeg') }}" alt="" style="max-width:100px; max-height:55px; object-fit:contain;">
                    @endif
                </td>
                <td style="border:none; width:42%; vertical-align:top;">
                    <strong style="font-size:10px;">{{ optional($setting)->name ?? optional($setting)->company_name ?? $companyName }}</strong><br>
                    <span style="font-size:8px;">
                        @if(!empty($setting) && !empty($setting->nif))
                            NIF: {{ $setting->nif }}
                            @if(!empty($setting->rc)) &nbsp;|&nbsp; RC: {{ $setting->rc }} @endif
                            <br>
                        @endif
                        @if(!empty($setting) && (!empty($setting->zone) || !empty($setting->commune)))
                            {{ $setting->zone ?? '' }} {{ !empty($setting->commune) ? '- '.$setting->commune : '' }}<br>
                        @endif
                        @if(!empty($setting) && !empty($setting->rue))
                            {{ $setting->rue }}<br>
                        @endif
                        @if(!empty($setting) && !empty($setting->telephone1))
                            Tél: {{ $setting->telephone1 }}@if(!empty($setting->telephone2)) / {{ $setting->telephone2 }} @endif<br>
                        @endif
                        @if(!empty($setting) && !empty($setting->email))
                            Email: {{ $setting->email }}<br>
                        @endif
                    </span>
                </td>
                <td style="border:none; width:40%; text-align:right; vertical-align:top;">
                    @if(class_exists('SimpleSoftwareIO\\QrCode\\Facades\\QrCode'))
                        <img src="data:image/png;base64,{!! base64_encode(QrCode::size(50)->generate($balanceQrPayload)) !!}" alt="">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="top-header">
        <tr>
            <td style="width: 50%;" class="doc-ref">
                {{ __('messages.Accounting') }}<br>
                <span class="filter-label">{{ $filterLabel }}</span>
            </td>
            <td style="width: 50%;" align="right">
                <div class="period-box">
                    <p>{{ __('messages.accounts_balance_period_from') }} {{ $periodStartFormatted }} {{ __('messages.ledger_period_to') }} {{ $periodEndFormatted }}</p>
                    <p>{{ __('messages.accounts_balance_account_keeping') }}</p>
                    <p>{{ __('messages.accounts_balance_page') }} : 1</p>
                </div>
            </td>
        </tr>
    </table>

    <div class="title-block">
        <h1>{{ __('messages.accounts_balance_title') }}</h1>
        <h2>{{ $scopeLabel }}</h2>
    </div>

    <div class="print-date">
        {{ __('messages.accounts_balance_print_date') }} {{ $reportGeneratedAt }}
    </div>

    {{-- Tableau principal : en-têtes + Report (haut page 1) + lignes comptes --}}
    <table class="balance-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-number">{{ __('messages.accounts_balance_account_number') }}</th>
                <th rowspan="2" class="col-name">{{ __('messages.accounts_balance_account_name') }}</th>
                <th colspan="2">{{ __('messages.accounts_balance_movements') }}</th>
                <th colspan="2">{{ __('messages.accounts_balance_balances') }}</th>
            </tr>
            <tr>
                <th class="col-amount">{{ __('messages.accounts_balance_debit') }}</th>
                <th class="col-amount">{{ __('messages.accounts_balance_credit') }}</th>
                <th class="col-amount">{{ __('messages.accounts_balance_debit') }}</th>
                <th class="col-amount">{{ __('messages.accounts_balance_credit') }}</th>
            </tr>
        </thead>
        <tbody>
            @if(\App\Support\BalanceReportBuilder::hasAmounts($reportRow))
                <tr class="report-row">
                    <td></td>
                    <td class="summary-label">{{ __('messages.accounts_balance_report') }}</td>
                    <td class="amount">{{ $formatAmount($reportRow['movement_debit']) }}</td>
                    <td class="amount">{{ $formatAmount($reportRow['movement_credit']) }}</td>
                    <td class="amount">{{ $formatAmount($reportRow['balance_debit']) }}</td>
                    <td class="amount">{{ $formatAmount($reportRow['balance_credit']) }}</td>
                </tr>
            @endif
            @forelse ($accountsData as $row)
                <tr class="account-row">
                    <td>{{ $row['account_number'] }}</td>
                    <td class="account-name">{{ mb_strtoupper($row['account_name'] ?: __('messages.accounts_balance_undefined_account')) }}</td>
                    <td class="amount">{{ $formatAmount($row['movement_debit']) }}</td>
                    <td class="amount">{{ $formatAmount($row['movement_credit']) }}</td>
                    <td class="amount">{{ $formatAmount($row['balance_debit']) }}</td>
                    <td class="amount">{{ $formatAmount($row['balance_credit']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center;">{{ __('messages.no_data_found') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- A reporter : bloc séparé en bas du document (jamais en haut de page) --}}
    <div class="carry-forward-block">
        <table class="carry-forward-table">
            <tr>
                <td class="col-number"></td>
                <td class="col-name summary-label">{{ __('messages.accounts_balance_carry_forward') }}</td>
                <td class="col-amount amount">{{ $formatAmount($grandTotals['movement_debit']) }}</td>
                <td class="col-amount amount">{{ $formatAmount($grandTotals['movement_credit']) }}</td>
                <td class="col-amount amount">{{ $formatAmount($grandTotals['balance_debit']) }}</td>
                <td class="col-amount amount">{{ $formatAmount($grandTotals['balance_credit']) }}</td>
            </tr>
        </table>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('dejavu sans', 'normal');
            $pdf->page_text(500, 820, {!! json_encode(__('messages.accounts_balance_page') . ' : {PAGE_NUM}') !!}, $font, 8, [0, 0, 0]);
        }
    </script>
</body>
</html>
