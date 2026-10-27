<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8">
    @include('backend.pages.document.partials._document_styles')
</head>
<body style="font-family: 'DejaVu Sans', sans-serif;">
    @include('backend.pages.document.partials._document_header')

    <h3 class="doc-title">LISTE DES MATÉRIELS / ARTICLES</h3>
    <div class="doc-meta">
        <strong>Date d'édition :</strong> {{ now()->format('d/m/Y H:i') }}
        &nbsp;|&nbsp; <strong>Nombre d'articles :</strong> {{ $materials->count() }}
    </div>

    <table class="doc-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Article</th>
                <th>Code</th>
                <th>Code magasin</th>
                <th class="text-right">Quantité</th>
                <th>Unité</th>
                <th class="text-right">PA</th>
                <th>Nature</th>
                <th>Compte charge</th>
            </tr>
        </thead>
        <tbody>
            @foreach($materials as $material)
                @php
                    $nature = [];
                    if ($material->is_stockable ?? true) {
                        $nature[] = 'Stockable';
                    } else {
                        $nature[] = 'Non stockable';
                    }
                    if ($material->is_amortizable) {
                        $nature[] = 'Amortissable';
                    }
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $material->name }}</td>
                    <td>{{ $material->code }}</td>
                    <td>{{ $material->code_store ?: '—' }}</td>
                    <td class="text-right">{{ number_format((float) $material->quantity, 2, ',', ' ') }}</td>
                    <td>{{ $material->materialMeasurement ? $material->materialMeasurement->purchase_unit : '—' }}</td>
                    <td class="text-right">{{ number_format((float) $material->purchase_price, 2, ',', ' ') }}</td>
                    <td>{{ implode(' / ', $nature) }}</td>
                    <td>
                        @if($material->account)
                            {{ $material->account->account_number }} - {{ $material->account->account_name }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
