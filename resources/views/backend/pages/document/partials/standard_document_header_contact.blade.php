{{-- En-tête contact société pour documents RH (PDF) --}}
@include('backend.pages.document.partials._document_header', [
    'setting' => $setting ?? null,
    'documentTitle' => $documentTitle ?? null,
    'documentNumber' => $documentNumber ?? null,
    'documentDate' => $documentDate ?? ($date ?? null),
])
