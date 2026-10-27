@extends('backend.layouts.master')

@section('title')
@lang('messages.stockout_edit') - @lang('messages.admin_panel')
@endsection

@section('styles')
@endsection

@section('admin-content')
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.stockout_edit')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.goods-stockouts.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.stockout_edit')</span></li>
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
                    <h4 class="header-title">@lang('messages.Modifier Sortie Marchandises')</h4>
                    @include('backend.layouts.partials.messages')
                    
                    <form action="{{ route('admin.goods-stockouts.update', $stockout->stockout_no) }}" method="POST">
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
                                <input type="text" class="form-control" id="asker" name="asker" value="{{ $stockout->asker }}" placeholder="Enter asker" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="destination">@lang('messages.Destination')</label>
                                <input type="text" class="form-control" id="destination" name="destination" value="{{ $stockout->destination }}" placeholder="Enter destination" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="requisition_no">@lang('messages.N° Réquisition')</label>
                                    <input type="text" class="form-control" id="requisition_no" name="requisition_no" value="{{ $stockout->requisition_no }}" placeholder="Enter requisition number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="item_movement_type">@lang('messages.Type Sortie')</label>
                                    <select class="form-control" name="item_movement_type" id="item_movement_type" required>
                                        <option value="SP" {{ $stockout->item_movement_type == 'SP' ? 'selected' : '' }}>@lang('messages.movement_sp_loss')</option>
                                        <option value="SV" {{ $stockout->item_movement_type == 'SV' ? 'selected' : '' }}>@lang('messages.movement_sv_theft')</option>
                                        <option value="SC" {{ $stockout->item_movement_type == 'SC' ? 'selected' : '' }}>@lang('messages.movement_sc_breakage')</option>
                                        @if (Auth::guard('admin')->user()->can('goods_stockout.edit'))
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
                                    <label for="goods_store_id">@lang('messages.Magasin marchandises d\'Origine')<strong style="color: red;">*</strong></label>
                                    <select class="form-control" name="goods_store_id" id="goods_store_id" required>
                                        @foreach($goods_stores as $store)
                                        <option value="{{ $store->id }}" {{ $stockout->goods_store_id == $store->id ? 'selected' : '' }}>{{ $store->code }} - {{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="item_purchase_or_sale_currency">@lang('messages.Devise')</label>
                                    <input type="text" class="form-control" id="item_purchase_or_sale_currency" name="item_purchase_or_sale_currency" placeholder="BIF" value="{{ $stockout->item_purchase_or_sale_currency ?? 'BIF' }}">
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
                                            if($item) {
                                                $itemName = $item->name . '/' . number_format($item->cump ?? 0,0,',',' ') . '/' . ($item->code ?? '');
                                            } else {
                                                $itemName = '';
                                            }
                                        @endphp
                                        <input type="text" class="form-control item_search" placeholder="@lang('messages.type_to_search_item')" autocomplete="off" value="{{ $itemName }}" required>
                                        <input type="hidden" name="item_id[]" class="item_id" value="{{ $detail->item_id }}">
                                        <div class="autocomplete-list item_search-list"></div>
                                    </div>
                                </td>  
                                <td><input type="number" name="quantity[]" placeholder="@lang('messages.enter_quantity')" class="form-control" value="{{ $detail->quantity }}" step="any" min="0" required /></td>  
                                <td><input type="number" name="cost_price[]" placeholder="Enter cost price" class="form-control" value="{{ $detail->cost_price }}" step="any" min="0" readonly /></td>     
                                <td>@if($index == 0)<button type="button" name="add" id="add" class="btn btn-success">@lang('messages.addmore')</button>@else<button type="button" class="btn btn-danger remove-tr">@lang('messages.delete')</button>@endif</td>  
                            </tr>
                            @endforeach
                        </table> 
                        <div class="col-lg-12">
                            <label for="description"> @lang('messages.description')</label>
                            <textarea class="form-control" name="description" id="description" placeholder="@lang('messages.enter_description')" required>{{ $stockout->description }}</textarea>
                        </div>
                        <div style="margin-top: 15px;margin-left: 15px;">
                            <button type="submit" onclick="this.style.visibility='hidden';" ondblclick="this.style.visibility='hidden';" class="btn btn-primary">@lang('messages.update')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
@include('backend.layouts.partials.stockout_autocomplete_helpers')
    <script type="text/javascript">
    function initItemAutocomplete($wrapper) {
        StockoutAutocomplete.initGoodsLine($wrapper);
    }

    $(document).ready(function() {
        StockoutAutocomplete.initExistingRows('.item_search', initItemAutocomplete);
    });
    
    var i = 0;
       
    $("#add").click(function(){
   
        ++i;

         var markup = "<tr>"+
                      "<td>"+
                         "<div class='autocomplete-wrapper'>"+
                            "<input type='text' class='form-control item_search' placeholder='@lang('messages.type_to_search_item')' autocomplete='off' required>"+
                            "<input type='hidden' name='item_id[]' class='item_id'>"+
                         "</div>"+
                        "</td>"+
                        "<td>"+
                          "<input type='number' name='quantity[]' step='any' placeholder='@lang('messages.enter_quantity')' min='0' class='form-control' required />"+
                        "</td>"+
                        "<td>"+
                        "<input type='number' step='any' name='cost_price[]' placeholder='@lang('messages.enter_cost_price')' class='form-control' min='0' readonly />"+
                        "</td>"+
                        "<td>"+
                          "<button type='button' class='btn btn-danger remove-tr'>@lang('messages.delete')</button>"+
                        "</td>"+
                    "</tr>";
   
        var newRow = $(markup);
        $("#dynamicTable").append(newRow);
        
        initItemAutocomplete(newRow.find('.autocomplete-wrapper'));
    });
   
    $(document).on('click', '.remove-tr', function(){  
         $(this).parents('tr').remove();
    }); 

</script>
@endsection

