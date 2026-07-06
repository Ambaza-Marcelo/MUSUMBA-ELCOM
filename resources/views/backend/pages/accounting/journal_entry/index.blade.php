
@extends('backend.layouts.master')

@section('title')
@lang('messages.Journal Entry') - @lang('messages.admin_panel')
@endsection

@section('styles')
    <!-- Start datatable css -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.jqueryui.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/rowgroup/1.1.1/css/rowGroup.dataTables.min.css">
    <style>
        #printLedgerModal .modal-content,
        #printLedgerModal .modal-body {
            overflow: visible;
        }

        .ledger-account-picker {
            position: relative;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 6px 8px;
            background: #fff;
            min-height: 42px;
        }

        .ledger-account-picker .ajax-ac-results--anchored {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            width: 100%;
            margin-top: 2px;
            z-index: 1060;
            box-sizing: border-box;
        }

        .ledger-account-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 6px;
        }

        .ledger-account-tag {
            display: inline-flex;
            align-items: center;
            background: #e9f1fb;
            color: #1b3a68;
            border: 1px solid #c8d5eb;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            line-height: 1.3;
            max-width: 100%;
        }

        .ledger-account-tag.is-parent {
            background: #eef7ee;
            border-color: #c8e6c8;
            color: #1b4d1b;
        }

        .ledger-account-tag-remove {
            border: none;
            background: transparent;
            color: #6c757d;
            margin-left: 6px;
            padding: 0;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
        }

        .ledger-account-tag-remove:hover {
            color: #dc3545;
        }

        #ledger_account_search {
            border: none;
            box-shadow: none;
            padding-left: 0;
            padding-right: 0;
            height: 32px;
        }

        #ledger_account_search:focus {
            outline: none;
            box-shadow: none;
        }

        .ledger-account-filter .btn {
            flex: 1 1 0;
            font-size: 12px;
            white-space: normal;
        }

        .ledger-account-filter .btn.active {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: #fff;
        }

        .ledger-meta {
            border: 1px solid #e1e5eb;
            border-radius: 6px;
            padding: 1rem 1.25rem;
            background-color: #f9fbfd;
            margin-bottom: 1.5rem;
        }

        .ledger-meta h5 {
            font-size: 1.125rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: .25rem;
        }

        .ledger-meta .meta-line {
            font-size: .875rem;
            margin-bottom: .15rem;
            color: #4d5969;
        }

        .ledger-table thead th {
            background-color: #f0f3f7;
            text-transform: uppercase;
            font-size: .75rem;
            letter-spacing: .05em;
            color: #2f3a4c;
            border-bottom: 2px solid #d9dde2;
            vertical-align: middle;
        }

        .ledger-table thead th[rowspan="2"] {
            vertical-align: middle;
        }

        .ledger-table thead th[colspan] {
            text-align: center;
            font-weight: 600;
        }

        .ledger-table tbody td {
            font-size: .85rem;
            padding: .6rem .75rem;
        }

        .ledger-table tbody tr.group-header td {
            background-color: #e9f1fb;
            font-weight: 600;
            border-top: 2px solid #c8d5eb;
            color: #1b3a68;
            padding: .65rem .75rem;
        }

        .ledger-table tbody tr.group-header td .group-title {
            font-size: .9rem;
        }

        .ledger-table tbody tr.dtrg-start td {
            background-color: #e9f1fb;
            font-weight: 600;
            border-top: 2px solid #c8d5eb;
            color: #1b3a68;
            padding: .65rem .75rem;
        }

        .ledger-table tbody tr.dtrg-start td .group-title {
            font-size: .9rem;
        }

        .ledger-table tbody tr.dtrg-start td .group-sums {
            font-size: .75rem;
            letter-spacing: .02em;
        }

        .ledger-table tbody tr:nth-child(even):not(.dtrg-start):not(.dtrg-end) {
            background-color: #fcfdff;
        }

        .ledger-table tfoot td {
            background-color: #f0f3f7;
            font-weight: 600;
            font-size: .85rem;
            border-top: 2px solid #d9dde2;
        }

        .ledger-table .text-right {
            text-align: right;
        }

        .d-none {
            display: none;
        }
    </style>
@endsection


@section('admin-content')

<!-- page title area start -->
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.Journal Entry')</h4>
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
                    <h4 class="header-title float-left">@lang('messages.Grand livre des comptes')</h4>
                    <p class="float-right mb-2">
                        @if (Auth::guard('admin')->user()->can('account_journal_entry.create'))
                            <a class="btn btn-primary text-white" href="{{ route('admin.journal-entry.create') }}">@lang('messages.new')</a>
                        @endif
                        <button type="button" class="btn btn-success text-white" data-toggle="modal" data-target="#printLedgerModal">
                            <i class="fa fa-print"></i> @lang('messages.ledger_print_pdf')
                        </button>
                        <button type="button" class="btn btn-outline-success text-success" data-toggle="modal" data-target="#printLedgerModal">
                            <i class="fa fa-file-excel"></i> @lang('messages.export_excel')
                        </button>
                    </p>
                    <div class="clearfix"></div>
                    
                    {{-- Filtres de période pour limiter / étendre les données affichées --}}
                    <form method="GET" action="{{ route('admin.journal-entry.index') }}" class="mb-3">
                        <div class="form-row align-items-end">
                            <div class="col-md-3">
                                <label for="date_debut">@lang('messages.Date début')</label>
                                <input type="date"
                                       class="form-control"
                                       id="date_debut"
                                       name="date_debut"
                                       value="{{ request('date_debut') }}">
                            </div>
                            <div class="col-md-3">
                                <label for="date_fin">@lang('messages.Date fin')</label>
                                <input type="date"
                                       class="form-control"
                                       id="date_fin"
                                       name="date_fin"
                                       value="{{ request('date_fin') }}">
                            </div>
                            <div class="col-md-3 d-flex mt-3 mt-md-0">
                                <button type="submit" class="btn btn-primary mr-2">
                                    @lang('messages.filter')
                                </button>
                                <a href="{{ route('admin.journal-entry.index') }}" class="btn btn-secondary">
                                    @lang('messages.Réinitialiser')
                                </a>
                            </div>
                        </div>
                        <small class="form-text text-muted mt-1">
                            @lang('messages.journal_default_limit_notice')
                        </small>
                    </form>
                    @php
                        $periodStart = $datas->whereNotNull('date')->min('date');
                        $periodEnd = $datas->whereNotNull('date')->max('date');
                        $periodStartFormatted = $periodStart ? \Carbon\Carbon::parse($periodStart)->format('d/m/Y') : '--/--/----';
                        $periodEndFormatted = $periodEnd ? \Carbon\Carbon::parse($periodEnd)->format('d/m/Y') : '--/--/----';
                    @endphp

                    <div class="ledger-meta">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>@lang('messages.Grand livre des comptes')</h5>
                                <p class="meta-line mb-0">{{ config('app.name', 'Entreprise') }}</p>
                            </div>
                            <div class="col-md-6 text-md-right">
                                <p class="meta-line mb-1"><strong>@lang('messages.Période :')</strong> {{ $periodStartFormatted }} @lang('messages.ledger_period_to') {{ $periodEndFormatted }}</p>
                                <p class="meta-line mb-1"><strong>@lang('messages.Date de tirage :')</strong> {{ now()->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="data-tables">
                        @include('backend.layouts.partials.messages')
                        <table id="ledgerTable" class="table table-bordered table-hover ledger-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="d-none">@lang('messages.Compte')</th>
                                    <th rowspan="2">@lang('messages.ledger_creation_date')</th>
                                    <th rowspan="2">@lang('messages.ledger_operation_date')</th>
                                    <th rowspan="2">@lang('messages.N° pièce')</th>
                                    <th rowspan="2">@lang('messages.N° Document')</th>
                                    <th rowspan="2">@lang('messages.Libellé écriture')</th>
                                    <th rowspan="2">@lang('messages.Let')</th>
                                    <th colspan="2" class="text-center">@lang('messages.MOUVEMENTS')</th>
                                    <th colspan="2" class="text-center">@lang('messages.SOLDES')</th>
                                </tr>
                                <tr>
                                    <th>@lang('messages.DÉBIT')</th>
                                    <th>@lang('messages.CRÉDIT')</th>
                                    <th>@lang('messages.DÉBIT')</th>
                                    <th>@lang('messages.CRÉDIT')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ledgerRows as $row)
                                    <tr class="ledger-data-row">
                                        <td class="d-none">{{ $row['account_label'] }}</td>
                                        <td>{{ $row['created_at'] }}</td>
                                        <td>{{ $row['operation_date'] }}</td>
                                        <td class="text-center">{{ $row['cj'] }}</td>
                                        <td>{{ $row['document_no'] }}</td>
                                        <td>{{ $row['label'] }}</td>
                                        <td>{{ $row['let'] ?? '' }}</td>
                                        <td class="debit-amount text-right" data-raw="{{ $row['debit'] }}">{{ number_format($row['debit'], 0, ',', ' ') }}</td>
                                        <td class="credit-amount text-right" data-raw="{{ $row['credit'] }}">{{ number_format($row['credit'], 0, ',', ' ') }}</td>
                                        <td class="balance-debit-amount text-right" data-raw="{{ $row['balance_debit'] }}">{{ number_format($row['balance_debit'], 0, ',', ' ') }}</td>
                                        <td class="balance-credit-amount text-right" data-raw="{{ $row['balance_credit'] }}">{{ number_format($row['balance_credit'], 0, ',', ' ') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="summary-row" style="background-color: #f8f9fa; font-weight: bold;">
                                    <td class="d-none"></td>
                                    <td colspan="6" class="text-right"><strong>@lang('messages.A reporter :')</strong></td>
                                    <td class="total-debit text-right"><strong>{{ number_format($grandTotalDebit, 0, ',', ' ') }}</strong></td>
                                    <td class="total-credit text-right"><strong>{{ number_format($grandTotalCredit, 0, ',', ' ') }}</strong></td>
                                    <td class="text-right"><strong>{{ number_format($grandBalanceDebit, 0, ',', ' ') }}</strong></td>
                                    <td class="text-right"><strong>{{ number_format($grandBalanceCredit, 0, ',', ' ') }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- data table end -->
        
    </div>
</div>

<!-- Print Ledger Modal -->
<div class="modal fade" id="printLedgerModal" tabindex="-1" role="dialog" aria-labelledby="printLedgerModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printLedgerModalLabel">@lang('messages.Imprimer le Grand Livre')</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="printLedgerForm" action="{{ route('admin.journal-entry.print') }}" method="GET" target="_blank">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="print_date_debut">@lang('messages.Date de début') <strong style="color: red;">*</strong></label>
                        <input type="date" class="form-control" id="print_date_debut" name="date_debut" required>
                    </div>
                    <div class="form-group">
                        <label for="print_date_fin">@lang('messages.Date de fin') <strong style="color: red;">*</strong></label>
                        <input type="date" class="form-control" id="print_date_fin" name="date_fin" required>
                    </div>
                    <div class="form-group mb-0">
                        <label for="ledger_account_search">@lang('messages.ledger_accounts_unified_label')</label>
                        <div class="btn-group btn-group-sm d-flex mb-2 ledger-account-filter" role="group" aria-label="@lang('messages.ledger_accounts_unified_label')">
                            <button type="button"
                                    class="btn btn-outline-secondary ledger-account-filter-btn active"
                                    data-mode="all"
                                    id="ledger_account_filter_all">
                                @lang('messages.ledger_accounts_filter_all')
                            </button>
                            <button type="button"
                                    class="btn btn-outline-secondary ledger-account-filter-btn"
                                    data-mode="parent"
                                    id="ledger_account_filter_parent">
                                @lang('messages.ledger_accounts_filter_parents')
                            </button>
                            <button type="button"
                                    class="btn btn-outline-secondary ledger-account-filter-btn"
                                    data-mode="child"
                                    id="ledger_account_filter_child">
                                @lang('messages.ledger_accounts_filter_children')
                            </button>
                        </div>
                        <div id="ledger_account_picker_wrap" class="d-none">
                            <div class="ledger-account-picker">
                                <div id="ledger_accounts_tags" class="ledger-account-tags"></div>
                                <div class="ajax-ac-wrap">
                                    <input type="text"
                                           class="form-control"
                                           id="ledger_account_search"
                                           autocomplete="off"
                                           placeholder="@lang('messages.ledger_accounts_parent_search_placeholder')">
                                </div>
                            </div>
                        </div>
                        <div id="ledger_parent_account_ids_container"></div>
                        <div id="ledger_account_ids_container"></div>
                        <small class="form-text text-muted">@lang('messages.ledger_accounts_unified_help')</small>
                    </div>
                    <div class="form-group">
                        <label for="piece_document_filter">@lang('messages.N° pièce / N° document (optionnel)')</label>
                        <textarea class="form-control" id="piece_document_filter" name="piece_document_filter" rows="3" placeholder="@lang('messages.ledger_piece_document_placeholder')"></textarea>
                        <small class="form-text text-muted">@lang('messages.ledger_piece_document_filter_help')</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">@lang('messages.Fermer')</button>
                    <button type="submit" id="printPdfBtn" class="btn btn-success">
                        <i class="fa fa-print"></i> @lang('messages.ledger_print_pdf_submit')
                    </button>
                    <button type="button" id="exportExcelBtn" class="btn btn-outline-success">
                        <i class="fa fa-file-excel"></i> @lang('messages.export_excel')
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


@section('scripts')
     <!-- Start datatable js -->
     <script src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
     <script src="https://cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
     <script src="https://cdn.datatables.net/rowgroup/1.1.1/js/dataTables.rowGroup.min.js"></script>

     <script>
        (function () {
            var accountSearchUrl = @json(route('admin.journal-entry.search-accounts'));
            var parentPlaceholder = @json(__('messages.ledger_accounts_parent_search_placeholder'));
            var childPlaceholder = @json(__('messages.ledger_accounts_child_search_placeholder'));
            var selectedLedgerAccounts = {};
            var ledgerAccountMode = 'all';

            function escHtml(text) {
                return String(text || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function clearAllLedgerSelections() {
                selectedLedgerAccounts = {};
                $('#ledger_accounts_tags').empty();
                $('#ledger_parent_account_ids_container').empty();
                $('#ledger_account_ids_container').empty();
            }

            function addLedgerAccount(item) {
                var key = String(item.id);
                if (!key || selectedLedgerAccounts[key]) {
                    return;
                }

                if (ledgerAccountMode === 'parent' && item.type !== 'parent') {
                    return;
                }
                if (ledgerAccountMode === 'child' && item.type !== 'child') {
                    return;
                }

                selectedLedgerAccounts[key] = item;

                var tagClass = item.type === 'parent' ? 'ledger-account-tag is-parent' : 'ledger-account-tag';
                var $tag = $('<span class="' + tagClass + '" data-account-key="' + escHtml(key) + '">' +
                    '<span class="ledger-account-tag-label">' + escHtml(item.text || '') + '</span>' +
                    '<button type="button" class="ledger-account-tag-remove" aria-label="Remove">&times;</button>' +
                    '</span>');

                $('#ledger_accounts_tags').append($tag);

                if (item.type === 'parent') {
                    $('#ledger_parent_account_ids_container').append(
                        $('<input type="hidden" name="parent_account_ids[]">')
                            .val(item.real_id)
                            .attr('data-account-key', key)
                    );
                } else {
                    $('#ledger_account_ids_container').append(
                        $('<input type="hidden" name="account_ids[]">')
                            .val(item.real_id)
                            .attr('data-account-key', key)
                    );
                }
            }

            function removeLedgerAccount(key) {
                key = String(key);
                delete selectedLedgerAccounts[key];
                $('#ledger_accounts_tags .ledger-account-tag[data-account-key="' + key + '"]').remove();
                $('#ledger_parent_account_ids_container input[data-account-key="' + key + '"]').remove();
                $('#ledger_account_ids_container input[data-account-key="' + key + '"]').remove();
            }

            function setupLedgerAutocomplete() {
                if (typeof attachAjaxAutocomplete !== 'function') {
                    return;
                }

                attachAjaxAutocomplete({
                    $input: $('#ledger_account_search'),
                    $dropdownParent: $('.ledger-account-picker'),
                    url: accountSearchUrl,
                    minChars: 0,
                    extraParams: function () {
                        return { type: ledgerAccountMode };
                    },
                    filterResults: function (results) {
                        return (results || []).filter(function (item) {
                            if (selectedLedgerAccounts[String(item.id)]) {
                                return false;
                            }
                            if (ledgerAccountMode === 'parent') {
                                return item.type === 'parent';
                            }
                            if (ledgerAccountMode === 'child') {
                                return item.type === 'child';
                            }
                            return false;
                        });
                    },
                    renderItem: function (item) {
                        return '<div><strong>' + escHtml(item.text || '') + '</strong></div>';
                    },
                    onSelect: function (item) {
                        addLedgerAccount(item);
                        setTimeout(function () {
                            $('#ledger_account_search').val('').focus();
                        }, 0);
                    }
                });
            }

            function setLedgerAccountMode(mode) {
                ledgerAccountMode = mode;

                $('.ledger-account-filter-btn').removeClass('active');
                $('.ledger-account-filter-btn[data-mode="' + mode + '"]').addClass('active');

                clearAllLedgerSelections();
                $('#ledger_account_search').val('');

                if (mode === 'all') {
                    $('#ledger_account_picker_wrap').addClass('d-none');
                    return;
                }

                $('#ledger_account_picker_wrap').removeClass('d-none');
                $('#ledger_account_search').attr(
                    'placeholder',
                    mode === 'parent' ? parentPlaceholder : childPlaceholder
                );

                setupLedgerAutocomplete();

                setTimeout(function () {
                    $('#ledger_account_search').trigger('focus').trigger('input');
                }, 120);
            }

            $(function () {
                setLedgerAccountMode('all');

                $('.ledger-account-filter-btn').on('click', function () {
                    setLedgerAccountMode(String($(this).data('mode') || 'all'));
                });

                $('#ledger_accounts_tags').on('click', '.ledger-account-tag-remove', function () {
                    removeLedgerAccount($(this).closest('.ledger-account-tag').data('account-key'));
                });

                $('#printLedgerForm').on('keydown', function (e) {
                    if (e.key === 'Enter' && e.target && e.target.id === 'ledger_account_search') {
                        e.preventDefault();
                    }
                });

                $('#printLedgerModal').on('shown.bs.modal', function () {
                    if (ledgerAccountMode !== 'all') {
                        setTimeout(function () {
                            $('#ledger_account_search').trigger('focus').trigger('input');
                        }, 150);
                    }
                });
            });
        })();
     </script>

     <script>
        $(document).ready(function () {
            if (!$('#ledgerTable').length) {
                return;
            }

            function formatNumber(num) {
                return (parseFloat(num) || 0).toLocaleString('fr-FR');
            }

            function updateFooterTotals(api) {
                var totalDebit = 0;
                var totalCredit = 0;

                api.rows({ search: 'applied', filter: 'applied' }).every(function () {
                    var $row = $(this.node());
                    if (!$row.hasClass('ledger-data-row')) {
                        return;
                    }
                    totalDebit += parseFloat($row.find('.debit-amount').data('raw')) || 0;
                    totalCredit += parseFloat($row.find('.credit-amount').data('raw')) || 0;
                });

                var balance = totalDebit - totalCredit;
                var balanceDebit = balance > 0 ? balance : 0;
                var balanceCredit = balance < 0 ? Math.abs(balance) : 0;

                $('.total-debit').html('<strong>' + formatNumber(totalDebit) + '</strong>');
                $('.total-credit').html('<strong>' + formatNumber(totalCredit) + '</strong>');
                $('.summary-row td:nth-last-child(2)').html('<strong>' + formatNumber(balanceDebit) + '</strong>');
                $('.summary-row td:last-child').html('<strong>' + formatNumber(balanceCredit) + '</strong>');
            }

            try {
                var table = $('#ledgerTable').DataTable({
                    orderCellsTop: true,
                    ordering: false,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Tous']],
                    dom: '<"row align-items-center mb-2"<"col-sm-6"l><"col-sm-6"f>>rt<"row mt-2"<"col-sm-6"i><"col-sm-6"p>>',
                    columnDefs: [
                        { targets: 0, visible: false, searchable: true },
                        { targets: [7, 8, 9, 10], className: 'text-right' }
                    ],
                    rowGroup: {
                        dataSrc: 0,
                        startRender: function (rows, group) {
                            return $('<tr class="group-header dtrg-start"/>')
                                .append('<td colspan="11"><span class="group-title">' + group + '</span></td>');
                        }
                    },
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.10.19/i18n/{{ app()->getLocale() === 'fr' ? 'French' : 'English' }}.json'
                    }
                });

                table.on('draw', function () {
                    updateFooterTotals(table);
                });

                updateFooterTotals(table);
            } catch (error) {
                console.warn('Ledger DataTable init failed:', error);
            }

            $('#exportExcelBtn').on('click', function () {
                var $form = $('#printLedgerForm');
                var originalAction = $form.attr('action');

                $form.attr('action', @json(route('admin.journal-entry.export-excel')));
                $form.attr('target', '_blank');
                $form.trigger('submit');

                $form.attr('action', originalAction);
            });
        });

        function preventBack() {
            window.history.forward();
        }
        setTimeout('preventBack()', 0);
        window.onunload = function () {
            null;
        };
     </script>
@endsection