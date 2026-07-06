@extends('backend.layouts.master')

@section('title')
@lang('messages.stockin_edit') - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .autocomplete-wrapper {
        position: relative;
    }
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
    .autocomplete-list div:hover {
        background-color: #e9e9e9;
    }
    .autocomplete-list div:last-child {
        border-bottom: none;
    }
    .autocomplete-active {
        background-color: #4CAF50 !important;
        color: #ffffff;
    }
</style>
@endsection

@section('admin-content')
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.stockin_edit')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.goods-stockins.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.stockin_edit')</span></li>
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
                    <h4 class="header-title">@lang('messages.Modifier Entrée Produits Finis')</h4>
                    @include('backend.layouts.partials.messages')
                    
                    <form action="{{ route('admin.goods-stockins.update', $stockin->stockin_no) }}" method="POST">
                        @csrf
                        @method('PUT')
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                             <label for="date">@lang('messages.date')</label>
                                <input type="date" class="form-control" id="date" name="date" value="{{ \Carbon\Carbon::parse($stockin->date)->format('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="handingover">@lang('messages.Remettant')</label>
                                <input type="text" class="form-control" id="handingover" name="handingover" value="{{ $stockin->handingover }}" placeholder="@lang('messages.enter_handingover_stock')" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="origin">@lang('messages.Origin')</label>
                                <input type="text" class="form-control" id="origin" name="origin" value="{{ $stockin->origin }}" placeholder="@lang('messages.enter_origin')" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="receptionist">@lang('messages.receptionist')</label>
                                    <input type="text" class="form-control" id="receptionist" name="receptionist" value="{{ $stockin->receptionist }}" placeholder="@lang('messages.enter_receptionist_stock')" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="item_movement_type">@lang('messages.Type Entrée')</label>
                                    <select class="form-control" name="item_movement_type" id="item_movement_type" required>
                                        <option value="EN" {{ $stockin->item_movement_type == 'EN' ? 'selected' : '' }}>@lang('messages.movement_en_normal')</option>
                                        <option value="ER" {{ $stockin->item_movement_type == 'ER' ? 'selected' : '' }}>@lang('messages.movement_er_return')</option>
                                        @if (Auth::guard('admin')->user()->can('goods_stockin.edit'))
                                        <option value="EAJ" {{ $stockin->item_movement_type == 'EAJ' ? 'selected' : '' }}>@lang('messages.movement_eaj_adjustment')</option>
                                        @endif
                                        <option value="EAU" {{ $stockin->item_movement_type == 'EAU' ? 'selected' : '' }}>@lang('messages.movement_eau_other')</option>
                                </select>
                                </div>
                            </div>
                    </div>
                    <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="goods_store_id">@lang('messages.Magasin marchandises')<strong style="color: red;">*</strong></label>
                                    <select class="form-control" name="goods_store_id" id="goods_store_id" required>
                                        @foreach($goods_stores as $store)
                                        <option value="{{ $store->id }}" {{ $stockin->goods_store_id == $store->id ? 'selected' : '' }}>{{ $store->code }} - {{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="item_purchase_or_sale_currency">@lang('messages.Devise')</label>
                                    <input type="text" class="form-control" id="item_purchase_or_sale_currency" name="item_purchase_or_sale_currency" placeholder="BIF" value="{{ $stockin->item_purchase_or_sale_currency ?? 'BIF' }}">
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
                            @foreach($stockinDetails as $index => $detail)
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
                                <td><input type="number" name="cost_price[]" placeholder="Enter cost price" class="form-control" value="{{ $detail->cost_price }}" step="any" min="0" required /></td>     
                                <td>@if($index == 0)<button type="button" name="add" id="add" class="btn btn-success">@lang('messages.addmore')</button>@else<button type="button" class="btn btn-danger remove-tr">@lang('messages.delete')</button>@endif</td>  
                            </tr>
                            @endforeach
                        </table> 
                        <div class="col-lg-12">
                            <label for="description"> @lang('messages.description')</label>
                            <textarea class="form-control" name="description" id="description" placeholder="@lang('messages.enter_description')" required>{{ $stockin->description }}</textarea>
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
    <script type="text/javascript">
    
    // Items data for autocomplete
    var items = [
        @foreach($items as $item)
        {
            id: {{ $item->id }},
            name: {!! json_encode($item->name . '/' . number_format($item->cump ?? 0,0,',',' ') . '/' . ($item->code ?? '')) !!},
            cump: {{ $item->cump ?? 0 }}
        }@if(!$loop->last),@endif
        @endforeach
    ];

    // Function to initialize item autocomplete for a specific row
    function initializeItemAutocomplete(wrapper) {
        var input = wrapper.find('.item_search');
        var hidden = wrapper.find('.item_id');
        var list = wrapper.find('.item_search-list');
        var selectedIndex = -1;

        // Remove any existing event handlers
        input.off('input.item keydown.item');

        input.on('input.item', function() {
            var searchText = $(this).val().toLowerCase();
            list.empty();
            selectedIndex = -1;

            if (searchText === '') {
                list.hide();
                hidden.val('');
                return;
            }

            var filtered = items.filter(function(item) {
                return item.name.toLowerCase().includes(searchText);
            });

            if (filtered.length > 0) {
                filtered.forEach(function(item) {
                    var div = $('<div></div>').text(item.name).attr('data-id', item.id).attr('data-name', item.name).attr('data-cump', item.cump);
                    div.on('click', function() {
                        input.val($(this).attr('data-name'));
                        hidden.val($(this).attr('data-id'));
                        // Auto-fill cost_price with CUMP value
                        var row = input.closest('tr');
                        var costPriceInput = row.find('input[name="cost_price[]"]');
                        costPriceInput.val($(this).attr('data-cump'));
                        list.hide();
                    });
                    list.append(div);
                });
                list.show();
            } else {
                list.hide();
            }
        });

        // Keyboard navigation
        input.on('keydown.item', function(e) {
            var items_list = list.find('div');
            
            if (e.keyCode === 40) { // Down arrow
                e.preventDefault();
                selectedIndex = (selectedIndex < items_list.length - 1) ? selectedIndex + 1 : selectedIndex;
                items_list.removeClass('autocomplete-active');
                if (selectedIndex >= 0) {
                    $(items_list[selectedIndex]).addClass('autocomplete-active');
                }
            } else if (e.keyCode === 38) { // Up arrow
                e.preventDefault();
                selectedIndex = (selectedIndex > 0) ? selectedIndex - 1 : -1;
                items_list.removeClass('autocomplete-active');
                if (selectedIndex >= 0) {
                    $(items_list[selectedIndex]).addClass('autocomplete-active');
                }
            } else if (e.keyCode === 13) { // Enter
                e.preventDefault();
                if (selectedIndex >= 0 && items_list.length > 0) {
                    var selectedItem = $(items_list[selectedIndex]);
                    var itemId = selectedItem.attr('data-id');
                    var itemName = selectedItem.attr('data-name');
                    var itemCump = selectedItem.attr('data-cump');
                    
                    input.val(itemName);
                    hidden.val(itemId);
                    // Auto-fill cost_price with CUMP value
                    var row = input.closest('tr');
                    var costPriceInput = row.find('input[name="cost_price[]"]');
                    costPriceInput.val(itemCump);
                    list.hide();
                }
            } else if (e.keyCode === 27) { // Escape
                list.hide();
            }
        });
    }
    
    // Hide autocomplete lists when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.autocomplete-wrapper').length) {
            $('.autocomplete-list').hide();
        }
    });

    // Initialize autocomplete for existing item rows
    $(document).ready(function() {
        $('.autocomplete-wrapper').each(function() {
            if ($(this).find('.item_search').length) {
                initializeItemAutocomplete($(this));
            }
        });
    });
    
    var i = 0;
       
    $("#add").click(function(){
   
        ++i;

         var markup = "<tr>"+
                      "<td>"+
                         "<div class='autocomplete-wrapper'>"+
                            "<input type='text' class='form-control item_search' placeholder='Tapez pour rechercher un article...' autocomplete='off' required>"+
                            "<input type='hidden' name='item_id[]' class='item_id'>"+
                            "<div class='autocomplete-list item_search-list'></div>"+
                         "</div>"+
                        "</td>"+
                        "<td>"+
                          "<input type='number' name='quantity[]' step='any' placeholder='Enter Quantity' min='0' class='form-control' required />"+
                        "</td>"+
                        "<td>"+
                        "<input type='number' step='any' name='cost_price[]' placeholder='Enter cost price' class='form-control' min='0' required />"+
                        "</td>"+
                        "<td>"+
                          "<button type='button' class='btn btn-danger remove-tr'>@lang('messages.delete')</button>"+
                        "</td>"+
                    "</tr>";
   
        var newRow = $(markup);
        $("#dynamicTable").append(newRow);
        
        // Initialize autocomplete for the new row
        initializeItemAutocomplete(newRow.find('.autocomplete-wrapper'));
    });
   
    $(document).on('click', '.remove-tr', function(){  
         $(this).parents('tr').remove();
    }); 

</script>
@endsection

