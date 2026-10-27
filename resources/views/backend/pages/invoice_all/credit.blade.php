
@extends('backend.layouts.master')

@section('title')
@lang('messages.factures') - @lang('messages.admin_panel')
@endsection

@section('styles')
    <!-- Start datatable css -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.jqueryui.min.css">
    @include('backend.layouts.partials.ajax_autocomplete')
    <style>
        .credit-client-modal .ajax-ac-results--anchored {
            z-index: 1065;
            border-radius: 6px;
            overflow: hidden;
        }
        .credit-client-modal .ajax-ac-item {
            padding: 10px 12px;
        }
        .credit-client-suggestion-name {
            font-weight: 600;
            color: #1f2d3d;
        }
        .credit-client-suggestion-meta {
            display: block;
            font-size: 12px;
            color: #6c757d;
            margin-top: 2px;
        }
    </style>
@endsection


@section('admin-content')

<!-- page title area start -->
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.factures')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><span>@lang('messages.list')</span></li>
                </ul>
            </div>
        </div>
        <div class="col-sm-6 clearfix">
            @include('backend.layouts.partials.logout')
        </div>
    </div>
</div>
<!-- page title area end -->

<div class="main-content-inner">
    <div class="row">
        <!-- data table start -->
        <div class="col-12 mt-5">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title float-left">Factures a credit</h4>
                    <div class="float-right mb-3">
                        <button type="button" class="btn btn-info" data-toggle="modal" data-target="#creditFilterModal">Filtres</button>
                        <button type="button" class="btn btn-info" data-toggle="modal" data-target="#recoveryExportModal">Rapport recouvrement</button>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#clientGlobalModal">Facture globale</button>
                    </div>
                    <div class="clearfix"></div>
                    <div class="clearfix"></div>
                    <div class="data-tables">
                        @include('backend.layouts.partials.messages')
                        <table id="dataTable" class="text-center">
                            <thead class="bg-light text-capitalize">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="10%">@lang('messages.Numéro Facture')</th>
                                    <th width="10%">@lang('messages.date')</th>
                                    <th width="9%">@lang('messages.Total BIF')</th>
                                    <th width="9%">@lang('messages.Équiv. USD')</th>
                                    <th width="20%">@lang('messages.Signature')</th>
                                    <th width="20%">@lang('messages.Customer')</th>
                                    <th width="10%">@lang('messages.Bon de Livraison')</th>
                                    <th width="12%">Statut crédit</th>
                                    <th width="10%">@lang('messages.created_by')</th>
                                    <th width="15%">@lang('messages.Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($factures as $facture)
                               <tr>
                                    <td>{{ $loop->index+1 }}</td>
                                    <td><a href="{{ route('admin.invoice.show', $facture->invoice_number) }}">{{ $facture->invoice_number }}</a></td>
                                    <td>{{ $facture->invoice_date ? \Carbon\Carbon::parse($facture->invoice_date)->format('d/m/Y') : 'N/A' }}</td>
                                    @php
                                        $__creditTotalBif = (float) ($facture->montant_total_credit ?? 0);
                                        if ($__creditTotalBif <= 0) {
                                            $__creditTotalBif = $facture->details->sum('item_total_amount');
                                        }
                                        $__creditFxDate = $facture->invoice_date ? \Carbon\Carbon::parse($facture->invoice_date)->format('Y-m-d') : null;
                                        $__creditFx = $__creditFxDate ? \App\Models\ExchangeRate::bifPerUsdForDate($__creditFxDate) : null;
                                        $__creditBpu = $__creditFx['bif_per_usd'] ?? null;
                                        $__creditUsd = ($__creditBpu && (float) $__creditBpu > 0) ? $__creditTotalBif / (float) $__creditBpu : null;
                                    @endphp
                                    <td>{{ number_format($__creditTotalBif, 0, ',', ' ') }}</td>
                                    <td>
                                        @if($__creditUsd !== null)
                                            {{ number_format($__creditUsd, 2, ',', ' ') }} USD
                                        @else
                                            <span class="text-muted" title="{{ \App\Models\ExchangeRate::missingBifUsdRateMessage() }}">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $facture->invoice_signature ?? 'N/A' }}</td>
                                    <td>{{ $facture->client ? $facture->client->customer_name : ($facture->customer_name ?? 'N/A') }}</td>
                                    <td>
                                        @if($facture->document_no)
                                            <a href="{{ route('admin.delivery-note.show', $facture->document_no) }}">{{ $facture->document_no }}</a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        @if($facture->etat_recouvrement == '1')
                                            <span class="badge badge-info">Paiement partiel</span><br>
                                            <small>Reste: {{ number_format((float) ($facture->reste_credit ?? 0), 0, ',', ' ') }} BIF</small>
                                        @elseif($facture->etat_recouvrement == '2')
                                            <span class="badge badge-success">Paiement total</span>
                                        @else
                                            <span class="badge badge-warning">Encours</span><br>
                                            <small>Reste: {{ number_format((float) ($facture->reste_credit ?? $__creditTotalBif), 0, ',', ' ') }} BIF</small>
                                        @endif
                                    </td>
                                    <td>{{ $facture->auteur ?? 'N/A' }}</td>
                                    <td>
                                        @if (Auth::guard('admin')->user()->can('recouvrement.create'))
                                            @if($facture->etat_recouvrement == 1 || $facture->etat_recouvrement == 0 || $facture->etat_recouvrement === null || (float) ($facture->reste_credit ?? $__creditTotalBif) > 0)
                                                <a href="{{ route('admin.credits.payments.create', $facture->invoice_number) }}" class="btn btn-info btn-sm">Payer Credit</a>
                                            @endif
                                        @endif                                       
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- data table end -->
        
    </div>
</div>
<div class="modal fade" id="creditFilterModal" tabindex="-1" role="dialog" aria-labelledby="creditFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog credit-client-modal" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.credits.receivables.index') }}" method="GET">
                <div class="modal-header">
                    <h5 class="modal-title" id="creditFilterModalLabel">Filtrer les factures a credit</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="filter_date_debut">Date debut</label>
                        <input type="date" class="form-control" id="filter_date_debut" name="date_debut" value="{{ request('date_debut') }}">
                    </div>
                    <div class="form-group">
                        <label for="filter_date_fin">Date fin</label>
                        <input type="date" class="form-control" id="filter_date_fin" name="date_fin" value="{{ request('date_fin') }}">
                    </div>
                    <div class="form-group">
                        <label for="filter_client_id">@lang('messages.Nom du Client')</label>
                        @php
                            $selectedFilterClient = $clients->firstWhere('id', request('client_id'));
                            $selectedFilterClientText = $selectedFilterClient ? trim(($selectedFilterClient->customer_name ?? '') . '/' . ($selectedFilterClient->telephone ?? '')) : '';
                        @endphp
                        <div class="ajax-ac-wrap">
                            <input type="text" class="form-control" id="filter_client_search" value="{{ $selectedFilterClientText }}" placeholder="Rechercher client par nom, telephone ou NIF">
                            <input type="hidden" name="client_id" id="filter_client_id" value="{{ request('client_id') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('admin.credits.receivables.index') }}" class="btn btn-secondary">Reinitialiser</a>
                    <button type="submit" class="btn btn-info">Filtrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="recoveryExportModal" tabindex="-1" role="dialog" aria-labelledby="recoveryExportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="GET">
                <div class="modal-header">
                    <h5 class="modal-title" id="recoveryExportModalLabel">Exporter le rapport de recouvrement</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="recovery_date_debut">Date debut</label>
                        <input type="date" class="form-control" id="recovery_date_debut" name="date_debut" value="{{ request('date_debut') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="recovery_date_fin">Date fin</label>
                        <input type="date" class="form-control" id="recovery_date_fin" name="date_fin" value="{{ request('date_fin') }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" formaction="{{ route('admin.credits.reports.recovery.pdf') }}" class="btn btn-info">Exporter PDF</button>
                    <button type="submit" formaction="{{ route('admin.credits.reports.recovery.excel') }}" class="btn btn-success">Exporter Excel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="clientGlobalModal" tabindex="-1" role="dialog" aria-labelledby="clientGlobalModalLabel" aria-hidden="true">
    <div class="modal-dialog credit-client-modal" role="document">
        <div class="modal-content">
            <form method="GET">
                <div class="modal-header">
                    <h5 class="modal-title" id="clientGlobalModalLabel">Exporter la facture globale client</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="global_date_debut">Date debut</label>
                        <input type="date" class="form-control" id="global_date_debut" name="date_debut" value="{{ request('date_debut') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="global_date_fin">Date fin</label>
                        <input type="date" class="form-control" id="global_date_fin" name="date_fin" value="{{ request('date_fin') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="global_client_id">Client</label>
                        @php
                            $selectedGlobalClient = $clients->firstWhere('id', request('client_id'));
                            $selectedGlobalClientText = $selectedGlobalClient ? trim(($selectedGlobalClient->customer_name ?? '') . '/' . ($selectedGlobalClient->telephone ?? '')) : '';
                        @endphp
                        <div class="ajax-ac-wrap">
                            <input type="text" class="form-control" id="global_client_search" value="{{ $selectedGlobalClientText }}" placeholder="Rechercher client par nom, telephone ou NIF" required>
                            <input type="hidden" name="client_id" id="global_client_id" value="{{ request('client_id') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" formaction="{{ route('admin.credits.reports.client-global.pdf') }}" class="btn btn-primary">Exporter PDF</button>
                    <button type="submit" formaction="{{ route('admin.credits.reports.client-global.excel') }}" class="btn btn-info">Exporter Excel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


@section('scripts')
     <!-- Start datatable js -->
     <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.js"></script>
     <script src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
     <script src="https://cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
     <script src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js"></script>
     <script src="https://cdn.datatables.net/responsive/2.2.3/js/responsive.bootstrap.min.js"></script>
     
     <script>
        function escHtml(s) {
            return (s != null ? s : '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

         /*================================
        datatable active
        ==================================*/
        if ($('#dataTable').length) {
            $('#dataTable').DataTable({
                responsive: true,
                pageLength: 100,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
            });
        }

        attachAjaxAutocomplete({
            $input: $('#filter_client_search'),
            $hidden: $('#filter_client_id'),
            $dropdownParent: $('#creditFilterModal .credit-client-modal'),
            url: '{{ route("admin.autocomplete.clients") }}',
            minChars: 1,
            renderItem: function(item) {
                var name = escHtml(item.name || item.text || '');
                var tel = escHtml(item.telephone || 'N/A');
                var tin = escHtml(item.tin || 'N/A');
                return '<div><span class="credit-client-suggestion-name">' + name + '</span><span class="credit-client-suggestion-meta">Tel: ' + tel + ' | NIF: ' + tin + '</span></div>';
            },
            onSelect: function(item) {
                $('#filter_client_search').val(item.text || '');
            }
        });

        attachAjaxAutocomplete({
            $input: $('#global_client_search'),
            $hidden: $('#global_client_id'),
            $dropdownParent: $('#clientGlobalModal .credit-client-modal'),
            url: '{{ route("admin.autocomplete.clients") }}',
            minChars: 1,
            renderItem: function(item) {
                var name = escHtml(item.name || item.text || '');
                var tel = escHtml(item.telephone || 'N/A');
                var tin = escHtml(item.tin || 'N/A');
                return '<div><span class="credit-client-suggestion-name">' + name + '</span><span class="credit-client-suggestion-meta">Tel: ' + tel + ' | NIF: ' + tin + '</span></div>';
            },
            onSelect: function(item) {
                $('#global_client_search').val(item.text || '');
            }
        });

        $('#filter_client_search').on('input', function() {
            if (!$(this).val().trim()) {
                $('#filter_client_id').val('');
            }
        });

        $('#global_client_search').on('input', function() {
            if (!$(this).val().trim()) {
                $('#global_client_id').val('');
            }
        });

        $('#clientGlobalModal form').on('submit', function(e) {
            if (!$('#global_client_id').val()) {
                e.preventDefault();
                alert('Veuillez selectionner un client dans la liste de recherche.');
                return false;
            }
        });

     </script>
@endsection