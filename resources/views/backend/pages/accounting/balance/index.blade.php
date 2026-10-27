
@extends('backend.layouts.master')

@section('title')
    @lang('messages.accounts_balance_title') - @lang('messages.admin_panel')
@endsection

@section('styles')
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.jqueryui.min.css">
    <style>
        #exportBalanceModal .modal-content,
        #exportBalanceModal .modal-body {
            overflow: visible;
        }

        .balance-parent-picker {
            position: relative;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 6px 8px;
            background: #fff;
            min-height: 42px;
        }

        .balance-parent-picker .ajax-ac-results--anchored {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            width: 100%;
            margin-top: 2px;
            z-index: 1060;
            box-sizing: border-box;
        }

        .balance-parent-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 6px;
        }

        .balance-parent-tag {
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

        .balance-parent-tag-remove {
            border: none;
            background: transparent;
            color: #6c757d;
            margin-left: 6px;
            padding: 0;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
        }

        .balance-parent-tag-remove:hover {
            color: #dc3545;
        }

        #parent_account_search {
            border: none;
            box-shadow: none;
            padding-left: 0;
            padding-right: 0;
            height: 32px;
        }

        .balance-account-filter .btn {
            flex: 1 1 auto;
            white-space: nowrap;
        }

        #parent_account_search:focus {
            outline: none;
            box-shadow: none;
        }

        .balance-account-filter .btn {
            flex: 1 1 0;
            font-size: 12px;
            white-space: normal;
        }

        .balance-account-filter .btn.active {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: #fff;
        }

        .balance-account-tag.is-parent {
            background: #eef7ee;
            border-color: #c8e6c8;
            color: #1b4d1b;
        }

        .balance-meta {
            border: 1px solid #e1e5eb;
            border-radius: 6px;
            padding: 1.25rem 1.5rem;
            background-color: #f9fbfd;
            margin-bottom: 1.5rem;
        }

        .balance-meta h5 {
            font-size: 1.125rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: .35rem;
        }

        .balance-meta .meta-line {
            font-size: .875rem;
            margin-bottom: .2rem;
            color: #4d5969;
        }

        .balance-table thead th {
            background-color: #eef2f7;
            text-transform: uppercase;
            font-size: .75rem;
            letter-spacing: .05em;
            color: #2f3a4c;
            border-bottom: 2px solid #d9dde2;
            vertical-align: middle;
        }

        .balance-table tbody td {
            font-size: .85rem;
            padding: .6rem .75rem;
        }

        .balance-table tbody tr:nth-child(even):not(.summary-row) {
            background-color: #fcfdff;
        }

        .balance-table tbody tr.summary-row td {
            background-color: #eef2f7;
            font-weight: 600;
        }

        .balance-table .text-right {
            text-align: right;
        }
    </style>
@endsection

@section('admin-content')

<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.accounts_balance_title')</h4>
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

<div class="main-content-inner">
    <div class="row">
        <div class="col-12 mt-5">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
                        <h4 class="header-title mb-0">{{ __('messages.accounts_balance_title') }}</h4>
                        <div class="d-flex align-items-center flex-wrap">
                            <button type="button" class="btn btn-info btn-sm mr-1" data-toggle="modal" data-target="#exportBalanceModal">
                                <i class="fa fa-filter"></i> @lang('messages.accounts_balance_configure')
                            </button>
                            <button type="button" class="btn btn-primary btn-sm mr-1" data-toggle="modal" data-target="#exportBalanceModal" data-export-mode="pdf">
                                <i class="fa fa-file-pdf"></i> @lang('messages.export_pdf')
                            </button>
                            <button type="button" class="btn btn-success btn-sm mr-2" data-toggle="modal" data-target="#exportBalanceModal" data-export-mode="excel">
                                <i class="fa fa-file-excel"></i> @lang('messages.export_excel')
                            </button>
                            <span class="text-muted small">@lang('messages.accounts_balance_generated_at') {{ $reportGeneratedAt }}</span>
                        </div>
                    </div>

                    <div class="balance-meta">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>{{ __('messages.accounts_balance_title') }}</h5>
                                <p class="meta-line mb-1"><strong>{{ __('messages.accounts_balance_structure') }} :</strong> {{ $companyName }}</p>
                                <p class="meta-line mb-0"><strong>{{ __('messages.accounts_balance_report_type') }} :</strong> {{ __('messages.accounts_balance_report_type_value') }}</p>
                            </div>
                            <div class="col-md-6 text-md-right">
                                <p class="meta-line mb-1">
                                    <strong>{{ __('messages.accounts_balance_period_from') }}</strong>
                                    {{ $periodStartFormatted }}
                                    {{ __('messages.ledger_period_to') }}
                                    {{ $periodEndFormatted }}
                                </p>
                                <p class="meta-line mb-1"><strong>{{ __('messages.accounts_balance_fiscal_year') }} :</strong> {{ $fiscalYear }}</p>
                                <p class="meta-line mb-0"><strong>{{ __('messages.Compte') }} :</strong> {{ $filterLabel }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="data-tables">
                        @include('backend.layouts.partials.messages')
                        <table id="balanceTable" class="table table-bordered table-hover balance-table">
                            <thead>
                                <tr>
                                    <th rowspan="2">{{ __('messages.accounts_balance_account_number') }}</th>
                                    <th rowspan="2">{{ __('messages.accounts_balance_account_name') }}</th>
                                    <th colspan="2" class="text-center">{{ __('messages.accounts_balance_movements') }}</th>
                                    <th colspan="2" class="text-center">{{ __('messages.accounts_balance_balances') }}</th>
                                </tr>
                                <tr>
                                    <th>{{ __('messages.accounts_balance_debit') }}</th>
                                    <th>{{ __('messages.accounts_balance_credit') }}</th>
                                    <th>{{ __('messages.accounts_balance_debit') }}</th>
                                    <th>{{ __('messages.accounts_balance_credit') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(\App\Support\BalanceReportBuilder::hasAmounts($reportRow))
                                    <tr class="summary-row">
                                        <td colspan="2"><strong>{{ __('messages.accounts_balance_report') }}</strong></td>
                                        <td class="text-right">{{ number_format($reportRow['movement_debit'], 0, ',', ' ') }}</td>
                                        <td class="text-right">{{ number_format($reportRow['movement_credit'], 0, ',', ' ') }}</td>
                                        <td class="text-right">{{ number_format($reportRow['balance_debit'], 0, ',', ' ') }}</td>
                                        <td class="text-right">{{ number_format($reportRow['balance_credit'], 0, ',', ' ') }}</td>
                                    </tr>
                                @endif
                                @forelse ($accountsData as $row)
                                    <tr>
                                        <td>{{ $row['account_number'] }}</td>
                                        <td>{{ $row['account_name'] ?: __('messages.accounts_balance_undefined_account') }}</td>
                                        <td class="text-right movement-debit" data-raw="{{ $row['movement_debit'] }}">{{ number_format($row['movement_debit'], 0, ',', ' ') }}</td>
                                        <td class="text-right movement-credit" data-raw="{{ $row['movement_credit'] }}">{{ number_format($row['movement_credit'], 0, ',', ' ') }}</td>
                                        <td class="text-right balance-debit" data-raw="{{ $row['balance_debit'] }}">{{ number_format($row['balance_debit'], 0, ',', ' ') }}</td>
                                        <td class="text-right balance-credit" data-raw="{{ $row['balance_credit'] }}">{{ number_format($row['balance_credit'], 0, ',', ' ') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">@lang('messages.no_data_found')</td>
                                    </tr>
                                @endforelse
                                <tr class="summary-row">
                                    <td colspan="2"><strong>{{ __('messages.accounts_balance_carry_forward') }}</strong></td>
                                    <td class="text-right total-movement-debit"><strong>{{ number_format($grandTotals['movement_debit'], 0, ',', ' ') }}</strong></td>
                                    <td class="text-right total-movement-credit"><strong>{{ number_format($grandTotals['movement_credit'], 0, ',', ' ') }}</strong></td>
                                    <td class="text-right total-balance-debit"><strong>{{ number_format($grandTotals['balance_debit'], 0, ',', ' ') }}</strong></td>
                                    <td class="text-right total-balance-credit"><strong>{{ number_format($grandTotals['balance_credit'], 0, ',', ' ') }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exportBalanceModal" tabindex="-1" role="dialog" aria-labelledby="exportBalanceModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportBalanceModalLabel">@lang('messages.accounts_balance_export_modal_title')</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="exportBalanceForm" method="GET" action="{{ route('admin.accounts-balance.index') }}">
                <input type="hidden" name="ledger_account_mode" id="ledger_account_mode" value="{{ $ledgerAccountMode ?? 'all' }}">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_fiscal_year">@lang('messages.accounts_balance_fiscal_year')</label>
                        <input type="number"
                               class="form-control"
                               id="modal_fiscal_year"
                               name="fiscal_year"
                               min="2000"
                               max="9999"
                               value="{{ $fiscalYear }}">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="modal_date_debut">@lang('messages.Date début') <strong class="text-danger">*</strong></label>
                            <input type="date"
                                   class="form-control"
                                   id="modal_date_debut"
                                   name="date_debut"
                                   value="{{ $dateDebut ?? ($fiscalYear . '-01-01') }}"
                                   required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="modal_date_fin">@lang('messages.Date de fin') <strong class="text-danger">*</strong></label>
                            <input type="date"
                                   class="form-control"
                                   id="modal_date_fin"
                                   name="date_fin"
                                   value="{{ $dateFin ?? ($fiscalYear . '-12-31') }}"
                                   required>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label for="parent_account_search">@lang('messages.ledger_accounts_unified_label')</label>
                        <div class="btn-group btn-group-sm d-flex flex-wrap mb-2 balance-account-filter" role="group">
                            <button type="button"
                                    class="btn btn-outline-secondary balance-account-filter-btn {{ ($ledgerAccountMode ?? 'all') === 'all' ? 'active' : '' }}"
                                    data-mode="all"
                                    id="balance_account_filter_all">
                                @lang('messages.ledger_accounts_filter_all')
                            </button>
                            <button type="button"
                                    class="btn btn-outline-secondary balance-account-filter-btn {{ ($ledgerAccountMode ?? '') === 'parent' ? 'active' : '' }}"
                                    data-mode="parent"
                                    id="balance_account_filter_parent">
                                @lang('messages.ledger_accounts_filter_parents')
                            </button>
                            <button type="button"
                                    class="btn btn-outline-secondary balance-account-filter-btn {{ ($ledgerAccountMode ?? '') === 'parent_children' ? 'active' : '' }}"
                                    data-mode="parent_children"
                                    id="balance_account_filter_parent_children">
                                @lang('messages.ledger_accounts_filter_parent_children')
                            </button>
                            <button type="button"
                                    class="btn btn-outline-secondary balance-account-filter-btn {{ ($ledgerAccountMode ?? '') === 'child' ? 'active' : '' }}"
                                    data-mode="child"
                                    id="balance_account_filter_child">
                                @lang('messages.ledger_accounts_filter_children')
                            </button>
                        </div>
                        <small id="balance_account_mode_help" class="form-text text-muted mb-2 d-none"></small>
                        <div id="balance_account_picker_wrap" class="{{ ($ledgerAccountMode ?? 'all') === 'all' ? 'd-none' : '' }}">
                            <div class="balance-parent-picker">
                                <div id="parent_accounts_tags" class="balance-parent-tags"></div>
                                <div class="ajax-ac-wrap">
                                    <input type="text"
                                           class="form-control"
                                           id="parent_account_search"
                                           autocomplete="off"
                                           placeholder="@lang('messages.ledger_accounts_parent_search_placeholder')">
                                </div>
                            </div>
                        </div>
                        <div id="parent_account_ids_container"></div>
                        <div id="account_ids_container"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" id="showBalanceBtn" class="btn btn-info">
                        <i class="fa fa-eye"></i> @lang('messages.accounts_balance_show')
                    </button>
                    <button type="button" id="exportBalancePdfBtn" class="btn btn-primary">
                        <i class="fa fa-file-pdf"></i> @lang('messages.export_pdf')
                    </button>
                    <button type="button" id="exportBalanceExcelBtn" class="btn btn-success">
                        <i class="fa fa-file-excel"></i> @lang('messages.export_excel')
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.js"></script>
    <script src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.3/js/responsive.bootstrap.min.js"></script>

    <script>
        (function () {
            var accountSearchUrl = @json(route('admin.journal-entry.search-accounts'));
            var parentPlaceholder = @json(__('messages.ledger_accounts_parent_search_placeholder'));
            var childPlaceholder = @json(__('messages.ledger_accounts_child_search_placeholder'));
            var parentChildrenHelp = @json(__('messages.ledger_accounts_parent_children_help'));
            var selectedBalanceAccounts = {};
            var balanceAccountMode = 'all';

            function isParentPickerMode(mode) {
                return mode === 'parent' || mode === 'parent_children';
            }

            function escHtml(text) {
                return String(text || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function clearAllBalanceSelections() {
                selectedBalanceAccounts = {};
                $('#parent_accounts_tags').empty();
                $('#parent_account_ids_container').empty();
                $('#account_ids_container').empty();
            }

            function addBalanceAccount(item) {
                var key = String(item.id);
                if (!key || selectedBalanceAccounts[key]) {
                    return;
                }

                if (isParentPickerMode(balanceAccountMode) && item.type !== 'parent') {
                    return;
                }
                if (balanceAccountMode === 'child' && item.type !== 'child') {
                    return;
                }

                selectedBalanceAccounts[key] = item;

                var tagClass = item.type === 'parent'
                    ? 'balance-parent-tag balance-account-tag is-parent'
                    : 'balance-parent-tag balance-account-tag';

                var $tag = $('<span class="' + tagClass + '" data-account-key="' + escHtml(key) + '">' +
                    '<span class="balance-parent-tag-label">' + escHtml(item.text || '') + '</span>' +
                    '<button type="button" class="balance-parent-tag-remove" aria-label="Remove">&times;</button>' +
                    '</span>');

                $('#parent_accounts_tags').append($tag);

                if (item.type === 'parent') {
                    $('#parent_account_ids_container').append(
                        $('<input type="hidden" name="parent_account_ids[]">')
                            .val(item.real_id)
                            .attr('data-account-key', key)
                    );
                } else {
                    $('#account_ids_container').append(
                        $('<input type="hidden" name="account_ids[]">')
                            .val(item.real_id)
                            .attr('data-account-key', key)
                    );
                }
            }

            function removeBalanceAccount(key) {
                key = String(key);
                delete selectedBalanceAccounts[key];
                $('#parent_accounts_tags .balance-account-tag[data-account-key="' + key + '"]').remove();
                $('#parent_account_ids_container input[data-account-key="' + key + '"]').remove();
                $('#account_ids_container input[data-account-key="' + key + '"]').remove();
            }

            function setupBalanceAutocomplete() {
                if (typeof attachAjaxAutocomplete !== 'function') {
                    return;
                }

                attachAjaxAutocomplete({
                    $input: $('#parent_account_search'),
                    $dropdownParent: $('.balance-parent-picker'),
                    url: accountSearchUrl,
                    minChars: 0,
                    appendToBody: true,
                    extraParams: function () {
                        return { type: balanceAccountMode };
                    },
                    filterResults: function (results) {
                        return (results || []).filter(function (item) {
                            if (selectedBalanceAccounts[String(item.id)]) {
                                return false;
                            }
                            if (isParentPickerMode(balanceAccountMode)) {
                                return item.type === 'parent';
                            }
                            if (balanceAccountMode === 'child') {
                                return item.type === 'child';
                            }
                            return false;
                        });
                    },
                    renderItem: function (item) {
                        return '<div><strong>' + escHtml(item.text || '') + '</strong></div>';
                    },
                    onSelect: function (item) {
                        addBalanceAccount(item);
                        setTimeout(function () {
                            $('#parent_account_search').val('').focus();
                        }, 0);
                    }
                });
            }

            function setBalanceAccountMode(mode) {
                balanceAccountMode = mode;
                $('#ledger_account_mode').val(mode);

                $('.balance-account-filter-btn').removeClass('active');
                $('.balance-account-filter-btn[data-mode="' + mode + '"]').addClass('active');

                clearAllBalanceSelections();
                $('#parent_account_search').val('');

                if (mode === 'all') {
                    $('#balance_account_picker_wrap').addClass('d-none');
                    $('#balance_account_mode_help').addClass('d-none').text('');
                    return;
                }

                $('#balance_account_picker_wrap').removeClass('d-none');
                $('#parent_account_search').attr(
                    'placeholder',
                    isParentPickerMode(mode) ? parentPlaceholder : childPlaceholder
                );

                if (mode === 'parent_children') {
                    $('#balance_account_mode_help').removeClass('d-none').text(parentChildrenHelp);
                } else {
                    $('#balance_account_mode_help').addClass('d-none').text('');
                }

                setupBalanceAutocomplete();

                setTimeout(function () {
                    $('#parent_account_search').trigger('focus').trigger('input');
                }, 120);
            }

            $(function () {
                var initialMode = @json($ledgerAccountMode ?? 'all');
                setBalanceAccountMode(initialMode);

                $('#modal_fiscal_year').on('change', function () {
                    var year = parseInt($(this).val(), 10);
                    if (!year || year < 2000) {
                        return;
                    }
                    var $start = $('#modal_date_debut');
                    var $end = $('#modal_date_fin');
                    if (!$start.val() || String($start.val()).substr(0, 4) !== String(year)) {
                        $start.val(year + '-01-01');
                    }
                    if (!$end.val() || String($end.val()).substr(0, 4) !== String(year)) {
                        $end.val(year + '-12-31');
                    }
                });

                $('.balance-account-filter-btn').on('click', function () {
                    setBalanceAccountMode(String($(this).data('mode') || 'all'));
                });

                $('#parent_accounts_tags').on('click', '.balance-parent-tag-remove', function () {
                    removeBalanceAccount($(this).closest('.balance-account-tag').data('account-key'));
                });

                $('#exportBalanceForm').on('keydown', function (e) {
                    if (e.key === 'Enter' && e.target && e.target.id === 'parent_account_search') {
                        e.preventDefault();
                    }
                });

                $('#exportBalanceModal').on('shown.bs.modal', function () {
                    if (balanceAccountMode !== 'all') {
                        setTimeout(function () {
                            $('#parent_account_search').trigger('focus').trigger('input');
                        }, 150);
                    }
                });

                $('#exportBalancePdfBtn').on('click', function () {
                    var $form = $('#exportBalanceForm');
                    var originalAction = $form.attr('action');
                    var originalTarget = $form.attr('target');
                    $form.attr('action', @json(route('admin.accounts-balance.export-pdf')));
                    $form.attr('target', '_blank');
                    $form.trigger('submit');
                    $form.attr('action', originalAction);
                    if (originalTarget) {
                        $form.attr('target', originalTarget);
                    } else {
                        $form.removeAttr('target');
                    }
                });

                $('#exportBalanceExcelBtn').on('click', function () {
                    var $form = $('#exportBalanceForm');
                    var originalAction = $form.attr('action');
                    var originalTarget = $form.attr('target');
                    $form.attr('action', @json(route('admin.accounts-balance.export-excel')));
                    $form.attr('target', '_blank');
                    $form.trigger('submit');
                    $form.attr('action', originalAction);
                    if (originalTarget) {
                        $form.attr('target', originalTarget);
                    } else {
                        $form.removeAttr('target');
                    }
                });
            });
        })();
    </script>

    <script>
        $(document).ready(function () {
            if (!$('#balanceTable').length) {
                return;
            }

            function formatNumber(num) {
                return (parseFloat(num) || 0).toLocaleString('fr-FR');
            }

            function parseNumber(value) {
                if (typeof value === 'number') return value;
                var cleaned = String(value || '').replace(/<[^>]*>/g, '').replace(/\u00A0/g, '').replace(/\s+/g, '').replace(',', '.');
                var parsed = parseFloat(cleaned);
                return isNaN(parsed) ? 0 : parsed;
            }

            function calculateColumnTotal(api, columnIndex) {
                return api.column(columnIndex, { search: 'applied' }).data().reduce(function (total, value) {
                    return total + parseNumber(value);
                }, 0);
            }

            function updateFooterTotals(api) {
                $('.total-movement-debit').html('<strong>' + formatNumber(calculateColumnTotal(api, 2)) + '</strong>');
                $('.total-movement-credit').html('<strong>' + formatNumber(calculateColumnTotal(api, 3)) + '</strong>');
                $('.total-balance-debit').html('<strong>' + formatNumber(calculateColumnTotal(api, 4)) + '</strong>');
                $('.total-balance-credit').html('<strong>' + formatNumber(calculateColumnTotal(api, 5)) + '</strong>');
            }

            try {
                var table = $('#balanceTable').DataTable({
                    responsive: true,
                    ordering: true,
                    order: [[0, 'asc']],
                    pageLength: 25,
                    columnDefs: [
                        { targets: [2, 3, 4, 5], className: 'text-right' }
                    ],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.10.19/i18n/{{ app()->getLocale() === 'fr' ? 'French' : 'English' }}.json'
                    }
                });

                table.on('draw', function () {
                    updateFooterTotals(table);
                });
                updateFooterTotals(table);
            } catch (error) {
                console.warn('Balance table DataTable init failed:', error);
            }
        });
    </script>
@endsection
