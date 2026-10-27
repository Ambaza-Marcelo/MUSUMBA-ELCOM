
@extends('backend.layouts.master')

@section('title')
@lang('messages.stockout_create') - @lang('messages.admin_panel')
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
                <h4 class="page-title pull-left">@lang('messages.stockout_create')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.material-stockouts.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.stockout_create')</span></li>
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
                    <h4 class="header-title">@lang('messages.Nouvelle Sortie')</h4>
                    @include('backend.layouts.partials.messages')
                    
                    <form action="{{ route('admin.material-stockouts.storeFromSmall') }}" method="POST">
                        @csrf
                        <input type="hidden" name="store_type" value="sm">
                    <div class="row">
                        <div class="col-md-6" id="dynamicDiv">
                            <div class="form-group">
                             <label for="date">@lang('messages.date')</label>
                                <input type="date" class="form-control" id="date" name="date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="asker">@lang('messages.Demandeur')</label>
                                <input type="text" class="form-control" id="asker" name="asker" placeholder="Entrer le demandeur">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="origin_sm_store_id">@lang('messages.Stock Origine')<strong style="color: red;">*</strong></label>
                                    <select class="form-control" name="origin_sm_store_id" id="origin_sm_store_id" required>
                                        <option disabled="disabled" selected="selected">@lang('messages.Merci de choisir')</option>
                                        @foreach($material_origin_stores as $material_origin_store)
                                        <option value="{{ $material_origin_store->id }}" class="form-control">{{ $material_origin_store->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                            <div class="form-group">
                                <label for="destination">@lang('messages.Destination')</label>
                                <input type="text" class="form-control" id="destination" name="destination" placeholder="Entrer destination">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="item_movement_type">@lang('messages.Type de Sortie')</label>
                                    <select class="form-control" name="item_movement_type" id="item_movement_type" required>
                                    <option disabled="disabled">@lang('messages.Merci de choisir')</option>
                                        @if (Auth::guard('admin')->user()->can('material_stockout.reject'))
                                        <option value="SP">@lang('messages.Sortie Perte')</option>
                                        <option value="SV">@lang('messages.Sortie Vol')</option>
                                        <option value="SD">@lang('messages.Sortie Désuétude')</option>
                                        <option value="SC">@lang('messages.Sortie Casse')</option>
                                        <option value="SAJ">@lang('messages.Sortie Ajustement')</option>
                                        @endif
                                        <option value="SAU" selected="selected">@lang('messages.Sortie Autre')</option>
                                </select>
                                </div>
                            </div>
                    </div>
                         <table class="table table-bordered" id="dynamicTable">  
                            <tr>
                                <th>@lang('messages.item')</th>
                                <th>@lang('messages.quantity')</th>
                                <th>@lang('messages.Action')</th>
                            </tr>
                            <tr>  
                                <td>
                                    <div class="autocomplete-wrapper">
                                        <input type="text" class="form-control material_search" placeholder="@lang('messages.type_to_search_item')" autocomplete="off" required>
                                        <input type="hidden" name="material_id[]" class="material_id">
                                        <div class="autocomplete-list material_search-list"></div>
                                    </div>
                                </td>  
                                <td><input type="number" name="quantity[]" placeholder="@lang('messages.enter_quantity')" class="form-control" step="any" /></td>     
                                <td><button type="button" name="add" id="add" class="btn btn-success">@lang('messages.addmore')</button></td>  
                            </tr>  
                        </table> 
                        <div class="col-lg-12">
                            <label for="description"> @lang('messages.description')</label>
                            <textarea class="form-control" name="description" id="description" placeholder="@lang('messages.enter_description')">
                                SORTIE DES ARTICLES
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
@include('backend.layouts.partials.stockout_autocomplete_helpers')
    <script type="text/javascript">
    
    var materials = [
        @foreach($materials as $material)
        {
            id: {{ $material->id }},
            name: {!! json_encode($material->name . '/' . number_format($material->cump,0,',',' ') . '/' . $material->materialMeasurement->purchase_unit) !!}
        }@if(!$loop->last),@endif
        @endforeach
    ];

    function initializeMaterialAutocomplete(wrapper) {
        StockoutAutocomplete.initMaterialLine(wrapper, materials);
    }

    $(document).ready(function() {
        StockoutAutocomplete.initExistingRows('.material_search', initializeMaterialAutocomplete);
    });
    
    var i = 0;
       
    $("#add").click(function(){
   
        ++i;

         var markup = "<tr>"+
                      "<td>"+
                         "<div class='autocomplete-wrapper'>"+
                            "<input type='text' class='form-control material_search' placeholder='@lang('messages.type_to_search_item')' autocomplete='off' required>"+
                            "<input type='hidden' name='material_id[]' class='material_id'>"+
                         "</div>"+
                        "</td>"+
                        "<td>"+
                          "<input type='number' name='quantity[]' placeholder='@lang('messages.enter_quantity')' class='form-control' step='any' />"+
                        "</td>"+
                        "<td>"+
                          "<button type='button' class='btn btn-danger remove-tr'>@lang('messages.delete')</button>"+
                        "</td>"+
                    "</tr>";
   
        var newRow = $(markup);
        $("#dynamicTable").append(newRow);
        
        initializeMaterialAutocomplete(newRow.find('.autocomplete-wrapper'));
    });
   
    $(document).on('click', '.remove-tr', function(){  
         $(this).parents('tr').remove();
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
