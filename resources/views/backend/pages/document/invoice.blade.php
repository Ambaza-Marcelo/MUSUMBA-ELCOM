<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8">
    @include('backend.pages.document.partials._document_styles')
</head>
<body style="font-family: 'DejaVu Sans', sans-serif;">
    @php
        $date = $date ?? $invoice->invoice_date ?? null;
        $documentTitle = 'FACTURE';
        $documentNumber = $invoice->invoice_number ?? '';
        $documentDate = $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y H:i') : '';
        $customerName = optional($invoice->client)->customer_name ?? $invoice->customer_name ?? '';
        $customerAddress = optional($invoice->client)->customer_address ?? '';
        $customerPhone = optional($invoice->client)->telephone ?? '';
        $customerNif = optional($invoice->client)->customer_TIN ?? '';
        $customerSubjectToVat = (optional($invoice->client)->vat_customer_payer && !in_array(optional($invoice->client)->vat_customer_payer, ['0', 'non', 'no', 'false'], true)) ? __('messages.Oui') : __('messages.Non');
        $details = $invoice->details ?? collect();
        $totalAmount = $details->sum('item_total_amount');
        $grandTotal = $totalAmount;
        $hasVat = ((string) ($invoice->vat_taxpayer ?? '0')) === '1';
        $fmtBif = fn ($amount) => number_format((float) $amount, 0, ',', ' ');
        $wrapLabel = function ($text, int $width = 42): string {
            $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
            if ($text === '') {
                return 'N/D';
            }
            return wordwrap($text, $width, "\n", true);
        };
    @endphp
    @include('backend.pages.document.partials._document_header')

    @include('backend.pages.document.partials._document_customer_date')

    <table class="doc-table {{ $hasVat ? 'doc-vat' : '' }}">
        @if($hasVat)
        <colgroup>
            <col style="width:3%">
            <col style="width:40%">
            <col style="width:5%">
            <col style="width:9%">
            <col style="width:10%">
            <col style="width:4%">
            <col style="width:9%">
            <col style="width:10%">
        </colgroup>
        @else
        <colgroup>
            <col style="width:4%">
            <col style="width:58%">
            <col style="width:6%">
            <col style="width:16%">
            <col style="width:16%">
        </colgroup>
        @endif
        <thead>
            <tr>
                <th class="col-num">N°</th>
                <th class="col-designation">@lang('messages.Désignation')</th>
                <th class="text-right col-qty">@lang('messages.Quantité')</th>
                @if($hasVat)
                <th class="text-right col-amount">@lang('messages.PU HTVA')</th>
                <th class="text-right col-amount">@lang('messages.PT HTVA')</th>
                <th class="text-right col-amount">Tx TVA</th>
                <th class="text-right col-amount">@lang('messages.TVA')</th>
                <th class="text-right col-amount">@lang('messages.TVAC')</th>
                @else
                <th class="text-right col-amount">@lang('messages.Prix unitaire')</th>
                <th class="text-right col-amount">@lang('messages.Montant')</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($details as $d)
            <tr>
                <td class="col-num">{{ $loop->iteration }}</td>
                <td class="col-designation">
                    <span class="designation-text">{!! nl2br(e($wrapLabel($d->item_designation ?? (optional($d->item)->name ?? '')))) !!}</span>
                    @if(!empty($d->description))
                        <span class="designation-text" style="font-size:8px; color:#444; margin-top:2px;">{!! nl2br(e($wrapLabel($d->description, 50))) !!}</span>
                    @endif
                </td>
                <td class="text-right col-qty">{{ number_format($d->item_quantity ?? 0, 0, ',', ' ') }}</td>
                @if($hasVat)
                @php
                    $qty = (float) ($d->item_quantity ?? 0);
                    $ptNvat = (float) ($d->item_price_nvat ?? 0);
                    if ($ptNvat <= 0 && $qty > 0) {
                        $ptNvat = ((float) ($d->item_price ?? 0)) * $qty;
                    }
                    $puHt = $qty > 0 ? $ptNvat / $qty : (float) ($d->item_price ?? 0);
                    $tva = (float) ($d->vat ?? 0);
                    $ptWvat = (float) ($d->item_price_wvat ?? $d->item_total_amount ?? ($ptNvat + $tva));
                @endphp
                <td class="text-right col-amount">{{ $fmtBif($puHt) }}</td>
                <td class="text-right col-amount">{{ $fmtBif($ptNvat) }}</td>
                <td class="text-right col-amount">{{ (float) (optional($d->item)->vat_rate ?? optional($d->item)->vat ?? 0) }}%</td>
                <td class="text-right col-amount">{{ $fmtBif($tva) }}</td>
                <td class="text-right col-amount">{{ $fmtBif($ptWvat) }}</td>
                @else
                <td class="text-right col-amount">{{ $fmtBif($d->item_price ?? 0) }}</td>
                <td class="text-right col-amount">{{ $fmtBif($d->item_total_amount ?? 0) }}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;">
                @if($hasVat)
                <td colspan="4" class="text-right">@lang('messages.TOTAL')</td>
                <td class="text-right col-amount">{{ $fmtBif($details->sum(function($d){ $q = (float)($d->item_quantity ?? 0); $ht = (float)($d->item_price_nvat ?? 0); if ($ht > 0) { return $ht; } return ((float)($d->item_price ?? 0)) * $q; })) }}</td>
                <td></td>
                <td class="text-right col-amount">{{ $fmtBif($details->sum('vat')) }}</td>
                <td class="text-right col-amount">{{ $fmtBif($totalAmount) }}</td>
                @else
                <td colspan="4" class="text-right">@lang('messages.TOTAL')</td>
                <td class="text-right col-amount">{{ $fmtBif($totalAmount) }}</td>
                @endif
            </tr>
        </tfoot>
    </table>
    <p class="doc-meta" style="margin-top:10px;"><strong>@lang('messages.Montant en lettres :')</strong> {{ \App\Helpers\NumberToWordsFrench::convertSafe((int) round($totalAmount ?? 0)) }} francs burundais</p>
    @if(!empty($invoice->invoice_signature))
    <p class="doc-meta"><strong>@lang('messages.Signature:')</strong> {{ $invoice->invoice_signature }}@if($invoice->invoice_signature_date) &nbsp;|&nbsp; <strong>@lang('messages.Date de Signature:')</strong> {{ \Carbon\Carbon::parse($invoice->invoice_signature_date)->format('d/m/Y H:i') }}@endif</p>
    @endif
    @if($invoice->description)
    <p class="doc-meta" style="margin-top:6px;"><strong>@lang('messages.Description:')</strong> {!! nl2br(e($wrapLabel($invoice->description, 80))) !!}</p>
    @endif

    @include('backend.pages.document.partials._document_totals_signature')
    @include('backend.pages.document.partials._document_footer_bank')
</body>
</html>
