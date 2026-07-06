{{-- En-tête document: informations de la société (format type devis / facture) --}}
@php
    $setting = $setting ?? null;
    $companyName = $setting ? ($setting->name ?? $setting->company_name ?? 'MUSUMBA ELCOM') : 'MUSUMBA ELCOM';
    $publicLogoPath = public_path('img/logo.jpeg');

    if (!empty($documentDate)) {
        $docDate = $documentDate;
    } elseif (!empty($date)) {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            $docDate = \Carbon\Carbon::parse($date)->format('d/m/Y H:i');
        } elseif (is_string($date) && preg_match('/^\d{2}\/\d{2}\/\d{4}/', $date)) {
            $docDate = $date;
        } else {
            try {
                $docDate = \Carbon\Carbon::parse($date)->format('d/m/Y H:i');
            } catch (\Exception $e) {
                $docDate = (string) $date;
            }
        }
    } else {
        $docDate = now()->format('d/m/Y H:i');
    }
@endphp
<table style="width:100%; border:none; border-bottom:1px solid #333; margin-bottom:12px;">
    <tr>
        <td style="border:none; width:20%; vertical-align:top;">
            @if(file_exists($publicLogoPath))
                <img src="{{ asset('img/logo.jpeg') }}" alt="MUSUMBA ELCOM" class="doc-header-logo" style="max-width:125px; max-height:90px; width:125px; height:90px; object-fit:contain;">
            @endif
        </td>
        <td style="border:none; width:40%; vertical-align:top;">
            <strong class="doc-header-company" style="font-size:10px;">{{ $companyName }}</strong><br>
            <span class="doc-header-meta" style="font-size:8px;">
                @if($setting && !empty($setting->nif))
                    NIF: {{ $setting->nif }}
                    @if(!empty($setting->rc)) &nbsp;|&nbsp; RC: {{ $setting->rc }} @endif
                    @if(!empty($setting->tp_fiscal_center)) &nbsp;|&nbsp; C.FISCAL: {{ $setting->tp_fiscal_center }} @endif
                    <br>
                @endif
                @if($setting && isset($setting->vat_taxpayer))
                    Assujetti à la TVA: {{ $setting->vat_taxpayer ? __('messages.Oui') : __('messages.Non') }}<br>
                @endif
                @if($setting && (!empty($setting->zone) || !empty($setting->commune)))
                    {{ $setting->zone ?? '' }} {{ $setting->commune ? '- '.$setting->commune : '' }}<br>
                @endif
                @if($setting && !empty($setting->province))
                    {{ $setting->province }}<br>
                @endif
                @if($setting && !empty($setting->rue))
                    {{ $setting->rue }}<br>
                @endif
                @if($setting && !empty($setting->quartier))
                    {{ $setting->quartier }}<br>
                @endif
                @if($setting && !empty($setting->postal_number))
                    B.P. {{ $setting->postal_number }}<br>
                @endif
                @if($setting && !empty($setting->telephone1))
                    Tél: {{ $setting->telephone1 }}@if(!empty($setting->telephone2)) / {{ $setting->telephone2 }}@endif<br>
                @endif
                @if($setting && !empty($setting->email))
                    Email: {{ $setting->email }}<br>
                @endif
            </span>
        </td>
        <td style="border:none; width:40%; text-align:right; vertical-align:top;">
            @if(!$publicLogoPath || !file_exists($publicLogoPath))
                @if($setting && !empty($setting->logo))
                @php
                    $logoPath = storage_path('public/logo/'.$setting->logo);
                @endphp
                @if(file_exists($logoPath))
                    <img src="{{ $logoPath }}" width="125" height="90" alt="" style="max-width:125px; max-height:90px; height:90px; object-fit:contain;"><br>
                @endif
                @endif
            @endif
            @if(!empty($documentTitle))
                <strong style="font-size:13px; text-decoration:underline;">{{ $documentTitle }}</strong><br>
            @endif
            @if(!empty($documentNumber))
                <span style="font-size:11px;">{{ $documentNumber }}</span><br>
            @endif
            <span style="font-size:10px;">{{ $docDate }}</span>
        </td>
    </tr>
</table>
