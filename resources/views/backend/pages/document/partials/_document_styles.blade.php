{{-- Styles communs pour les documents PDF - police Unicode pour texte lisible (français) --}}
<style>
    @page { margin: 8mm 5mm; }
    * { font-family: "DejaVu Sans", sans-serif !important; }
    html, body, table, th, td, p, span, div, strong, h1, h2, h3 { font-family: "DejaVu Sans", sans-serif !important; }
    body { font-size: 10px; margin: 0; }
    table.doc-table { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: fixed; }
    table.doc-table, table.doc-table th, table.doc-table td { border: 1px solid #333; }
    table.doc-table th { background: #f0f0f0; padding: 4px 3px; text-align: left; font-size: 8.5px; line-height: 1.2; vertical-align: middle; }
    table.doc-table td { padding: 4px 3px; font-size: 9px; line-height: 1.25; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
    table.doc-table .col-num { text-align: center; width: 3%; padding: 4px 1px; }
    table.doc-table .col-designation {
        white-space: normal !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
        max-width: 0;
        line-height: 1.35;
        text-align: left;
        font-size: 9px;
    }
    table.doc-table .col-designation .designation-text {
        display: block;
        white-space: normal !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
        line-height: 1.35;
        font-size: 9px;
    }
    table.doc-table th.col-designation { white-space: normal !important; font-size: 8.5px; }
    table.doc-table .col-qty { text-align: right; white-space: nowrap; padding: 4px 2px; font-size: 9px; }
    table.doc-table .col-amount {
        text-align: right;
        white-space: nowrap;
        font-size: 8.5px;
        line-height: 1.2;
        padding: 4px 2px;
    }
    table.doc-table.doc-vat th { font-size: 7.5px; padding: 3px 2px; }
    table.doc-table.doc-vat td { font-size: 9px; padding: 3px 2px; }
    table.doc-table.doc-vat .col-amount { font-size: 8.5px; padding: 3px 2px; }
    table.doc-table.doc-vat .col-designation,
    table.doc-table.doc-vat .col-designation .designation-text { font-size: 9px; }
    table.doc-table.doc-vat th.col-amount { font-size: 7px; line-height: 1.1; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .doc-title { text-align: center; text-decoration: underline; font-size: 12px; margin: 8px 0; }
    .doc-meta { margin: 6px 0; font-size: 9px; line-height: 1.4; white-space: normal; word-wrap: break-word; overflow-wrap: break-word; }
    .doc-customer-block { white-space: normal; word-wrap: break-word; overflow-wrap: break-word; line-height: 1.35; font-size: 9px; }
    .doc-customer-block strong { display: inline; }
    .doc-header-company { font-size: 10px !important; }
    .doc-header-meta { font-size: 8px !important; line-height: 1.25; }
    .doc-header-logo { max-width: 125px; max-height: 90px; width: 125px; height: 90px; object-fit: contain; }
    .signature-block { margin-top: 20px; font-size: 9px; }
    .signature-line { margin-top: 20px; border-bottom: 1px solid #333; width: 180px; display: inline-block; }
</style>
