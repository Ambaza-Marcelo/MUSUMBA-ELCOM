
@extends('backend.layouts.master')

@section('title')
@lang('messages.Validation de facture à cash') - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .form-check-label { text-transform: capitalize; }
</style>
@endsection


@section('admin-content')

<!-- page title area start -->
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.Validation de facture à cash')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.invoice.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.Validation de facture à cash')</span></li>
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
        <!-- data table start -->
        <div class="col-12 mt-5">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Validation de facture à cash : {{$facture->invoice_number}}</h4>
                    @include('backend.layouts.partials.messages')
                    
                    <form action="{{ route('admin.invoice.validate-as-cash', $facture->invoice_number) }}" method="POST" id="cash_validate_form">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-sm-4">
                                <label for="invoice_number">@lang('messages.Numero Facture')</label>
                                <input type="text" value="{{ $facture->invoice_number }}" name="invoice_number" class="form-control" readonly>
                            </div>
                            <div class="col-sm-4">
                                <label for="tp_name">@lang('messages.Nom et Prenom')</label>
                                <input type="text" value="{{ $setting->name ?? '' }}" name="tp_name" class="form-control" readonly>
                            </div>
                            <div class="col-sm-4">
                                <label for="tp_TIN">@lang('messages.NIF Contribuable')</label>
                                <input type="text" value="{{ $setting->nif ?? '' }}" name="tp_TIN" class="form-control" readonly>
                            </div>
                            
                        </div>
                        <div class="row">
                            <div class="col-sm-4">
                                <label for="tp_trade_number">@lang('messages.RC du Contribuable')</label>
                                <input type="text" value="{{ $setting->rc ?? '' }}" name="tp_trade_number" class="form-control" readonly>
                            </div>
                            <div class="col-sm-4">
                                <label for="tp_phone_number">@lang('messages.Tel. du Contribuable')</label>
                                <input type="text" value="{{ $setting->telephone1 ?? $setting->telephone2 ?? '' }}" name="tp_phone_number" class="form-control" readonly>
                            </div>
                            <div class="col-sm-4">
                                <label for="tp_address_province">@lang('messages.Province')</label>
                                <input type="text" value="{{ $setting->province ?? '' }}" name="tp_address_province" class="form-control" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="client_search">@lang('messages.Nom du Client')</label>
                                    <div class="ajax-ac-wrap">
                                        <input type="text"
                                               class="form-control"
                                               id="client_search"
                                               value="{{ $facture->client ? $facture->client->customer_name . '/' . $facture->client->telephone : '' }}"
                                               placeholder="@lang('messages.type_to_search_client')"
                                               autocomplete="off">
                                        <input type="hidden" name="client_id" id="client_id" value="{{ $facture->client_id }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="account_search">@lang('messages.Account')</label>
                                    <div class="ajax-ac-wrap">
                                        <input type="text"
                                               class="form-control"
                                               id="account_search"
                                               placeholder="@lang('messages.type_to_search_account')"
                                               autocomplete="off"
                                               required>
                                        <input type="hidden" name="account_id" id="account_id" value="">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                        <table class="table table-bordered" id="dynamicTable">
                            <tr>
                                <th>@lang('messages.Article')</th>
                                <th>@lang('messages.Quantite')</th>
                                <th>@lang('messages.Prix Unitaire')</th>
                                <th>@lang('messages.Prix HTVA') </th>
                                <th>@lang('messages.Montant TVA') </th>
                                <th>@lang('messages.P.V TVAC') </th>
                                <th>@lang('messages.P.V total') </th>
                            </tr>
                            @php
                                $items_data = [];
                                $grand_total = 0;
                            @endphp
                            @foreach($datas as $index => $detail)
                            @php
                                $item_name = $detail->item ? $detail->item->name : ($detail->item_designation ?? 'N/A');
                                $item_display = $item_name . '/' . number_format($detail->item_price, 0, ',', ' ');
                                $items_data[] = [
                                    'id' => $detail->item_id,
                                    'name' => $item_name,
                                    'price' => $detail->item_price,
                                    'display' => $item_display
                                ];
                                $grand_total += $detail->item_total_amount;
                            @endphp
                            <tr>  
                                <td>
                                    <div class="autocomplete">
                                        <input type="text" 
                                            id="item_name_{{ $index }}" 
                                            class="form-control readonly-field" 
                                            value="{{ $item_display }}" 
                                            readonly />
                                        <input type="hidden" name="item_id[]" value="{{ $detail->item_id }}" />
                                    </div>
                                </td>  
                                <td>
                                    <input type="number" 
                                        name="item_quantity[]" 
                                        id="quantity_{{ $index }}" 
                                        class="form-control readonly-field quantity-input" 
                                        value="{{ $detail->item_quantity }}" 
                                        readonly />
                                </td>
                                <td>
                                    <input type="text" 
                                        id="unit_price_{{ $index }}" 
                                        class="form-control readonly-field unit-price" 
                                        value="{{ number_format($detail->item_price, 0, ',', ' ') }} BIF" 
                                        readonly />
                                    <input type="hidden" name="item_price[]" value="{{ $detail->item_price }}" />
                                </td>
                                <td>
                                    <input type="text" step='any' min='0' name="item_price_nvat[]" value="{{ number_format($detail->item_price_nvat ?? 0, 2, '.', ' ') }}" class="form-control" readonly/>
                                </td>
                                <td>
                                    <input type="text" step='any' min='0' name="vat[]" value="{{ number_format($detail->vat ?? 0, 2, '.', ' ') }}" class="form-control" readonly/>
                                </td>
                                <td>
                                    <input type="text" step='any' min='0' name="item_price_wvat[]" value="{{ number_format($detail->item_price_wvat ?? 0, 2, '.', ' ') }}" class="form-control" readonly/>
                                </td>
                                <td>
                                    <input type="text" step='any' min='0' name="Item_total_amount[]" value="{{ number_format($detail->item_total_amount ?? 0, 2, '.', ' ') }}" class="form-control" readonly/>
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        
                        <div class="row mt-3">
                            <div class="col-md-8"></div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        @php
                                            $total_without_vat = 0;
                                            $total_vat = 0;
                                            $total_with_vat = 0;
                                            foreach($datas as $detail) {
                                                $total_without_vat += $detail->item_price_nvat ?? 0;
                                                $total_vat += $detail->vat ?? 0;
                                                $total_with_vat += $detail->item_total_amount ?? 0;
                                            }
                                        @endphp
                                        <table class="table table-bordered">
                                            <tr>
                                                <th>@lang('messages.Total HTVA')</th>
                                                <td class="text-right"><strong>{{ number_format($total_without_vat, 2, '.', ' ') }} BIF</strong></td>
                                            </tr>
                                            <tr>
                                                <th>@lang('messages.Total TVA')</th>
                                                <td class="text-right"><strong>{{ number_format($total_vat, 2, '.', ' ') }} BIF</strong></td>
                                            </tr>
                                            <tr>
                                                <th>@lang('messages.Total TTC')</th>
                                                <td class="text-right"><strong class="text-primary">{{ number_format($total_with_vat, 2, '.', ' ') }} BIF</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-12">
                            <label for="description">@lang('messages.description')</label>
                            <textarea class="form-control" name="description" id="description" placeholder="@lang('messages.enter_description')">{{ $facture->description ?? '' }}</textarea>
                        </div>
                        
                        <div style="margin-top: 15px;margin-left: 15px;">
                            <button type="submit" onclick="this.style.visibility='hidden';" ondblclick="this.style.visibility='hidden';" class="btn btn-primary mt-4 pr-4 pl-4" id="cash_submit_btn">@lang('messages.VALIDER A CASH')</button>
                            <a href="{{ route('admin.invoice.index') }}" class="btn btn-secondary mt-4 pr-4 pl-4">@lang('messages.cancel')</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script type="text/javascript">
    function escHtml(s) {
        return (s != null ? s : '').toString()
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    $(document).ready(function() {
        attachAjaxAutocomplete({
            $input: $('#client_search'),
            $hidden: $('#client_id'),
            url: '{{ route("admin.autocomplete.clients") }}',
            minChars: 1,
            renderItem: function(item) {
                var name = escHtml(item.name || item.text || '');
                var tel = item.telephone ? (' <span class="ajax-ac-muted">- ' + escHtml(item.telephone) + '</span>') : '';
                return '<div><strong>' + name + '</strong>' + tel + '</div>';
            },
            onSelect: function(item) {
                $('#client_search').val(item.text || '');
            }
        });

        attachAjaxAutocomplete({
            $input: $('#account_search'),
            $hidden: $('#account_id'),
            url: '{{ route("admin.accounting-accounts.search") }}',
            minChars: 1,
            extraParams: { account_types: '3,4,5,6,7' },
            renderItem: function(item) {
                return '<div><strong>' + escHtml(item.text || '') + '</strong></div>';
            },
            onSelect: function(item) {
                $('#account_search').val(item.text || '');
            }
        });

        $('#cash_validate_form').on('submit', function(e) {
            if (!$('#client_id').val()) {
                e.preventDefault();
                $('#cash_submit_btn').css('visibility', 'visible');
                alert({!! json_encode(__('messages.select_client_from_list')) !!});
                return false;
            }
            if (!$('#account_id').val()) {
                e.preventDefault();
                $('#cash_submit_btn').css('visibility', 'visible');
                alert({!! json_encode(__('messages.Please select an account from the suggestions list.')) !!});
                return false;
            }
        });
    });
</script>
@endsection
