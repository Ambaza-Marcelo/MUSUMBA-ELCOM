
@extends('backend.layouts.master')

@section('title')
@lang('messages.stockin_create') - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .form-check-label {
        text-transform: capitalize;
    }
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
                <h4 class="page-title pull-left">@lang('messages.stockin_create')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.raw-material-stockins.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.stockin_create')</span></li>
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
                    <h4 class="header-title">@lang('messages.Nouveau Entré')</h4>
                    @include('backend.layouts.partials.messages')
                    
                    <form action="{{ route('admin.raw-material-stockins.store') }}" method="POST">
                        @csrf
                    <div class="row">
                        <div class="col-md-4" id="dynamicDiv">
                            <div class="form-group">
                             <label for="date">@lang('messages.date')</label>
                                <input type="date" class="form-control" id="date" name="date" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="handingover">@lang('messages.Remettant')</label>
                                <input type="text" class="form-control" id="handingover" name="handingover" placeholder="@lang('messages.enter_handingover_stock')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="origin">@lang('messages.Origin')</label>
                                <input type="text" class="form-control" id="origin" name="origin" placeholder="@lang('messages.enter_origin')">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="receptionist">@lang('messages.receptionist')</label>
                                    <input type="text" class="form-control" id="receptionist" name="receptionist" placeholder="@lang('messages.enter_receptionist_stock')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="item_movement_type">@lang('messages.Type Entrée')</label>
                                    <select class="form-control" name="item_movement_type" id="item_movement_type" required>
                                    <option disabled="disabled" selected="selected">@lang('messages.Merci de choisir')</option>
                                        <option value="EN">@lang('messages.Entrée Normale')</option>
                                        <option value="ER">@lang('messages.Entrée Retour Marchandises')</option>
                                        @if (Auth::guard('admin')->user()->can('raw_material_stockin.edit'))
                                        <option value="EAJ">@lang('messages.Entrée Ajustement')</option>
                                        @endif
                                        <option value="EAU">@lang('messages.Entrée Autre')</option>
                                </select>
                                </div>
                            </div>
                    </div>
                    <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="store_type">@lang('messages.Store Type')<strong style="color: red;">*</strong></label>
                                    <select class="form-control" name="store_type" id="store_type">
                                        <option disabled="disabled" selected="selected">@lang('messages.Merci de choisir')</option>
                                        @if (Auth::guard('admin')->user()->can('raw_material_extra_big_inventory.view'))
                                        <option value="0" class="form-control">@lang('messages.Food Extra Big Store')</option>
                                        @endif
                                        @if (Auth::guard('admin')->user()->can('raw_material_big_inventory.view'))
                                        <option value="1" class="form-control">@lang('messages.Stock intermediaire des matieres premieres')</option>
                                        @endif
                                        @if (Auth::guard('admin')->user()->can('raw_material_small_inventory.view'))
                                        <option value="2" class="form-control">@lang('messages.Petit Stock des matières premières')</option>
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6" id="dynamic_store">
                                
                            </div>
                    </div>
                         <table class="table table-bordered" id="dynamicTable">  
                            <tr>
                                <th>@lang('messages.item')</th>
                                <th>@lang('messages.quantity')</th>
                                <th>@lang('messages.purchase_price')</th>
                                <th>@lang('messages.Action')</th>
                            </tr>
                            <tr>  
                                <td>
                                    <div class="autocomplete-wrapper">
                                        <input type="text" class="form-control food_search" placeholder="@lang('messages.type_to_search_item')" autocomplete="off" required>
                                        <input type="hidden" name="r_material_id[]" class="r_material_id">
                                        <div class="autocomplete-list food_search-list"></div>
                                    </div>
                                </td>  
                                <td><input type="number" name="quantity[]" placeholder="@lang('messages.enter_quantity')" class="form-control"  step="any" min="0"/></td>  
                                <td><input type="number" name="purchase_price[]" placeholder="@lang('messages.enter_purchase_price')" class="form-control"  step="any" min="0"/></td>     
                                <td><button type="button" name="add" id="add" class="btn btn-success">@lang('messages.addmore')</button></td>  
                            </tr>  
                        </table> 
                        <div class="col-lg-12">
                            <label for="description"> @lang('messages.description')</label>
                            <textarea class="form-control" name="description" id="description" placeholder="@lang('messages.enter_description')">
                                MOI {{ Auth::guard('admin')->user()->name }},J'ACCUSE L'ENTREE DE CES ARTICLES
                            </textarea>
                        </div>
                        <div style="margin-top: 15px;margin-left: 15px;">
                            <button type="submit" onclick="this.style.visibility='hidden';" ondblclick="this.style.visibility='hidden';" class="btn btn-primary">@lang('messages.save')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
</div>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script type="text/javascript">
    
    // Foods data for autocomplete
    var foods = [
        @foreach($raw_materials as $raw_material)
        {
            id: {{ $raw_material->id }},
            name: {!! json_encode($raw_material->name . '/' . number_format($raw_material->cump,0,',',' ') . '/' . $raw_material->rawMaterialMeasurement->purchase_unit) !!},
            cump: {{ $raw_material->cump ?? 0 }}
        }@if(!$loop->last),@endif
        @endforeach
    ];

    // Function to initialize food autocomplete for a specific row
    function initializeFoodAutocomplete(wrapper) {
        var input = wrapper.find('.food_search');
        var hidden = wrapper.find('.r_material_id');
        var list = wrapper.find('.food_search-list');
        var selectedIndex = -1;

        // Remove any existing event handlers
        input.off('input.food keydown.food');

        input.on('input.food', function() {
            var searchText = $(this).val().toLowerCase();
            list.empty();
            selectedIndex = -1;

            if (searchText === '') {
                list.hide();
                hidden.val('');
                return;
            }

            var filtered = foods.filter(function(item) {
                return item.name.toLowerCase().includes(searchText);
            });

            if (filtered.length > 0) {
                filtered.forEach(function(item) {
                    var div = $('<div></div>').text(item.name).attr('data-id', item.id).attr('data-name', item.name).attr('data-cump', item.cump);
                    div.on('click', function() {
                        input.val($(this).attr('data-name'));
                        hidden.val($(this).attr('data-id'));
                        // Auto-fill purchase_price with CUMP value
                        var row = input.closest('tr');
                        var purchasePriceInput = row.find('input[name="purchase_price[]"]');
                        purchasePriceInput.val($(this).attr('data-cump'));
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
        input.on('keydown.food', function(e) {
            var items = list.find('div');
            
            if (e.keyCode === 40) { // Down arrow
                e.preventDefault();
                selectedIndex = (selectedIndex < items.length - 1) ? selectedIndex + 1 : selectedIndex;
                items.removeClass('autocomplete-active');
                if (selectedIndex >= 0) {
                    $(items[selectedIndex]).addClass('autocomplete-active');
                }
            } else if (e.keyCode === 38) { // Up arrow
                e.preventDefault();
                selectedIndex = (selectedIndex > 0) ? selectedIndex - 1 : -1;
                items.removeClass('autocomplete-active');
                if (selectedIndex >= 0) {
                    $(items[selectedIndex]).addClass('autocomplete-active');
                }
            } else if (e.keyCode === 13) { // Enter
                e.preventDefault();
                if (selectedIndex >= 0 && items.length > 0) {
                    var selectedItem = $(items[selectedIndex]);
                    var foodId = selectedItem.attr('data-id');
                    var foodName = selectedItem.attr('data-name');
                    var foodCump = selectedItem.attr('data-cump');
                    
                    input.val(foodName);
                    hidden.val(foodId);
                    // Auto-fill purchase_price with CUMP value
                    var row = input.closest('tr');
                    var purchasePriceInput = row.find('input[name="purchase_price[]"]');
                    purchasePriceInput.val(foodCump);
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

    // Initialize autocomplete for existing food rows
    $(document).ready(function() {
        $('.autocomplete-wrapper').each(function() {
            if ($(this).find('.food_search').length) {
                initializeFoodAutocomplete($(this));
            }
        });
    });
    
    var i = 0;
       
    $("#add").click(function(){
   
        ++i;

         var markup = "<tr>"+
                      "<td>"+
                         "<div class='autocomplete-wrapper'>"+
                            "<input type='text' class='form-control food_search' placeholder='Tapez pour rechercher un article...' autocomplete='off' required>"+
                            "<input type='hidden' name='r_material_id[]' class='r_material_id'>"+
                            "<div class='autocomplete-list food_search-list'></div>"+
                         "</div>"+
                        "</td>"+
                        "<td>"+
                          "<input type='number' name='quantity[]' step='any' placeholder='Enter Quantity' min='0' class='form-control' />"+
                        "</td>"+
                        "<td>"+
                        "<input type='number' step='any' name='purchase_price[]' placeholder='Enter purchase price' class='form-control' min='0' />"+
                        "</td>"+
                        "<td>"+
                          "<button type='button' class='btn btn-danger remove-tr'>@lang('messages.delete')</button>"+
                        "</td>"+
                    "</tr>";
   
        var newRow = $(markup);
        $("#dynamicTable").append(newRow);
        
        // Initialize autocomplete for the new row
        initializeFoodAutocomplete(newRow.find('.autocomplete-wrapper'));
    });
   
    $(document).on('click', '.remove-tr', function(){  
         $(this).parents('tr').remove();
    }); 

    $('#store_type').change(function () { 
        // Clear previous store selection
        $("#dynamic_store").empty();
        
    if ($(this).val() === '0'){
        var extra_big_store = "<div class='form-group'>"+
                            "<label for='dest_extra_store_id'>@lang('messages.Food Extra Big Store')<strong style='color: red;'>*</strong></label>"+
                                "<select name='dest_extra_store_id' id='dest_extra_store_id' class='form-control' required>"+
                                "<option selected disabled>@lang('messages.merci de choisir')</option>"+
                                "@foreach($raw_material_extra_big_stores as $raw_material_big_store)"+
                                    "<option value='{{$raw_material_big_store->id}}'>{{ $raw_material_big_store->code}}&nbsp;{{ $raw_material_big_store->name}}</option>"+
                                    "@endforeach"+
                                "</select>"+
                        "</div>";
        
        $("#dynamic_store").append(extra_big_store);
    }
    if ($(this).val() === '1'){
        var big_store = "<div class='form-group'>"+
                            "<label for='dest_bg_store_id'>@lang('messages.Stock intermediaire des matieres premieres')<strong style='color: red;'>*</strong></label>"+
                                "<select name='dest_bg_store_id' id='dest_bg_store_id' class='form-control' required>"+
                                "<option selected disabled>@lang('messages.merci de choisir')</option>"+
                                "@foreach($raw_material_big_stores as $raw_material_big_store)"+
                                    "<option value='{{$raw_material_big_store->id}}'>{{ $raw_material_big_store->code}}&nbsp;{{ $raw_material_big_store->name}}</option>"+
                                    "@endforeach"+
                                "</select>"+
                        "</div>";
        
        $("#dynamic_store").append(big_store);
    }
    if ($(this).val() === '2'){
        var small_store = "<div class='form-group'>"+
                            "<label for='dest_sm_store_id'>@lang('messages.Petit Stock des matières premières')<strong style='color: red;'>*</strong></label>"+
                                "<select name='dest_sm_store_id' id='dest_sm_store_id' class='form-control' required>"+
                                "<option selected disabled>@lang('messages.merci de choisir')</option>"+
                                "@foreach($raw_material_small_stores as $raw_material_small_store)"+
                                "<option value='{{$raw_material_small_store->id}}'>{{ $raw_material_small_store->code }}&nbsp;{{ $raw_material_small_store->name}}</option>"+
                                    "@endforeach"+
                                "</select>"+
                        "</div>";
        
        $("#dynamic_store").append(small_store);
    }
    });

    function preventBack() {
        window.history.forward();
    }
    setTimeout("preventBack()", 0);
    window.onunload = function () {
        null
    };

</script>
@endsection
