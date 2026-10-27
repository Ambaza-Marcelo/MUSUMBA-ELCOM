@extends('backend.layouts.master')

@section('title')
@lang('messages.stockout_edit') - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .autocomplete-wrapper { position: relative; }
    .autocomplete-list {
        position: absolute;
        border: 1px solid #d4d4d4;
        border-top: none;
        z-index: 99;
        max-height: 200px;
        overflow-y: auto;
        background-color: #fff;
        width: 100%;
        display: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-top: -1px;
    }
    .autocomplete-list div {
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #d4d4d4;
    }
    .autocomplete-list div:hover { background-color: #e9e9e9; }
    .autocomplete-list div:last-child { border-bottom: none; }
    .autocomplete-active { background-color: #4CAF50 !important; color: #ffffff; }
</style>
@endsection

@section('admin-content')
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.stockout_edit')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.finished-goods-stockouts.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.stockout_edit')</span></li>
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
                    <h4 class="header-title">@lang('messages.Modifier Sortie Produits Finis')</h4>
                    @include('backend.layouts.partials.messages')

                    <form action="{{ route('admin.finished-goods-stockouts.update', $stockout->stockout_no) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="date">@lang('messages.date')</label>
                                    <input type="date" class="form-control" id="date" name="date" value="{{ \Carbon\Carbon::parse($stockout->date)->format('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="asker">@lang('messages.Demandeur')</label>
                                    <input type="text" class="form-control" id="asker" name="asker" value="{{ $stockout->asker }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="destination">@lang('messages.Destination')</label>
                                    <input type="text" class="form-control" id="destination" name="destination" value="{{ $stockout->destination }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="requisition_no">@lang('messages.N° Réquisition')</label>
                                    <input type="text" class="form-control" id="requisition_no" name="requisition_no" value="{{ $stockout->requisition_no }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="item_movement_type">@lang('messages.Type Sortie')</label>
                                    <select class="form-control" name="item_movement_type" id="item_movement_type" required>
                                        <option value="SP" {{ $stockout->item_movement_type == 'SP' ? 'selected' : '' }}>@lang('messages.movement_sp_loss')</option>
                                        <option value="SV" {{ $stockout->item_movement_type == 'SV' ? 'selected' : '' }}>@lang('messages.movement_sv_theft')</option>
                                        <option value="SC" {{ $stockout->item_movement_type == 'SC' ? 'selected' : '' }}>@lang('messages.movement_sc_breakage')</option>
                                        @if (Auth::guard('admin')->user()->can('finished_goods_stockout.edit'))
                                        <option value="SAJ" {{ $stockout->item_movement_type == 'SAJ' ? 'selected' : '' }}>@lang('messages.movement_saj_adjustment')</option>
                                        @endif
                                        <option value="SAU" {{ in_array($stockout->item_movement_type, ['SAU', 'SN', '', null], true) ? 'selected' : '' }}>@lang('messages.movement_sau_other')</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    @php $storeType = $stockout->store_type ?: 'MAIN'; @endphp
                                    <label for="store_type">@lang('messages.Type de Stock Produits Finis')<strong style="color: red;">*</strong></label>
                                    <select class="form-control" name="store_type" id="store_type" required>
                                        <option value="MAIN" {{ $storeType === 'MAIN' ? 'selected' : '' }}>@lang('messages.Stock Principal')</option>
                                        <option value="SM" {{ $storeType === 'SM' ? 'selected' : '' }}>@lang('messages.Stock SM')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="finished_g_store_id">@lang('messages.Magasin d\'Origine')<strong style="color: red;">*</strong></label>
                                    <select class="form-control" name="finished_g_store_id" id="finished_g_store_id" required>
                                        @foreach($finished_goods_stores as $store)
                                            <option value="{{ $store->id }}" {{ $stockout->finished_g_store_id == $store->id ? 'selected' : '' }}>{{ $store->code }} - {{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="sm_store_row" style="display:none;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="finished_g_sm_store_id">@lang('messages.Magasin SM d\'Origine')<strong style="color: red;">*</strong></label>
                                    <select class="form-control" name="finished_g_sm_store_id" id="finished_g_sm_store_id">
                                        @foreach($finished_goods_sm_stores as $store)
                                            <option value="{{ $store->id }}" {{ $stockout->finished_g_sm_store_id == $store->id ? 'selected' : '' }}>{{ $store->code }} - {{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="item_purchase_or_sale_currency">@lang('messages.Devise')</label>
                                    <input type="text" class="form-control" id="item_purchase_or_sale_currency" name="item_purchase_or_sale_currency" value="{{ $stockout->item_purchase_or_sale_currency ?? 'BIF' }}">
                                </div>
                            </div>
                        </div>

                        <table class="table table-bordered" id="dynamicTable">
                            <tr>
                                <th>@lang('messages.item')</th>
                                <th>@lang('messages.quantity')</th>
                                <th>@lang('messages.Prix de Coût')</th>
                                <th>@lang('messages.Action')</th>
                            </tr>
                            @foreach($stockoutDetails as $index => $detail)
                            <tr>
                                <td>
                                    <div class="autocomplete-wrapper">
                                        @php
                                            $item = $detail->item;
                                            $itemName = $item ? ($item->name . '/' . number_format($item->cump ?? 0,0,',',' ') . '/' . ($item->code ?? '')) : '';
                                        @endphp
                                        <input type="text" class="form-control item_search" placeholder="@lang('messages.type_to_search_item')" autocomplete="off" value="{{ $itemName }}" required>
                                        <input type="hidden" name="item_id[]" class="item_id" value="{{ $detail->item_id }}">
                                        <div class="autocomplete-list item_search-list"></div>
                                    </div>
                                </td>
                                <td><input type="number" name="quantity[]" class="form-control" value="{{ $detail->quantity }}" step="any" min="0" required /></td>
                                <td><input type="number" name="cost_price[]" class="form-control" value="{{ $detail->cost_price }}" step="any" min="0" readonly /></td>
                                <td>
                                    @if($index == 0)
                                        <button type="button" name="add" id="add" class="btn btn-success">@lang('messages.addmore')</button>
                                    @else
                                        <button type="button" class="btn btn-danger remove-tr">@lang('messages.delete')</button>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </table>

                        <div class="col-lg-12">
                            <label for="description">@lang('messages.description')</label>
                            <textarea class="form-control" name="description" id="description" required>{{ $stockout->description }}</textarea>
                        </div>

                        <div style="margin-top: 15px;margin-left: 15px;">
                            <button type="submit" onclick="this.style.visibility='hidden';" ondblclick="this.style.visibility='hidden';" class="btn btn-primary">@lang('messages.update')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
@include('backend.layouts.partials.stockout_autocomplete_helpers')
<script type="text/javascript">
    function initializeItemAutocomplete(wrapper) {
        StockoutAutocomplete.initFinishedGoodsLine(wrapper);
    }

    $(document).ready(function() {
        function syncStoreTypeUI() {
            var type = $('#store_type').val();
            if (type === 'SM') {
                $('#sm_store_row').show();
                $('#finished_g_sm_store_id').prop('required', true);
                $('#finished_g_store_id').prop('required', false);
            } else {
                $('#sm_store_row').hide();
                $('#finished_g_sm_store_id').prop('required', false);
                $('#finished_g_store_id').prop('required', true);
            }
        }

        $('#store_type').on('change', syncStoreTypeUI);
        syncStoreTypeUI();

        $('#finished_g_store_id, #finished_g_sm_store_id, #store_type').on('change', function () {
            $('#dynamicTable .item_id').val('');
            $('#dynamicTable .item_search').val('');
            $('#dynamicTable input[name="cost_price[]"]').val('');
        });

        StockoutAutocomplete.initExistingRows('.item_search', initializeItemAutocomplete);
    });

    $("#add").click(function(){
        var markup = "<tr>"+
            "<td>"+
                "<div class='autocomplete-wrapper'>"+
                    "<input type='text' class='form-control item_search' placeholder='@lang('messages.type_to_search_item')' autocomplete='off' required>"+
                    "<input type='hidden' name='item_id[]' class='item_id'>"+
                "</div>"+
            "</td>"+
            "<td><input type='number' name='quantity[]' step='any' placeholder='@lang('messages.enter_quantity')' min='0' class='form-control' required /></td>"+
            "<td><input type='number' step='any' name='cost_price[]' placeholder='@lang('messages.enter_cost_price')' class='form-control' min='0' readonly /></td>"+
            "<td><button type='button' class='btn btn-danger remove-tr'>@lang('messages.delete')</button></td>"+
        "</tr>";

        var newRow = $(markup);
        $("#dynamicTable").append(newRow);
        initializeItemAutocomplete(newRow.find('.autocomplete-wrapper'));
    });

    $(document).on('click', '.remove-tr', function(){
        $(this).parents('tr').remove();
    });
</script>
@endsection

