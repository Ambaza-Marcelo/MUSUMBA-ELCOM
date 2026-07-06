
@extends('backend.layouts.master')

@section('title')
@lang('messages.create production order') - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .form-check-label {
        text-transform: capitalize;
    }
    .autocomplete-wrapper {
        position: relative;
    }
    #finishedGoodsTable td,
    #rawMaterialsTable td {
        overflow: visible;
        position: relative;
    }
    .section-title {
        background: #f8f9fa;
        padding: 10px 15px;
        margin: 20px 0 15px 0;
        border-left: 4px solid #007bff;
        font-weight: 600;
        color: #333;
    }
</style>
@endsection

@section('admin-content')
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.create production order')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.production-order.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.create production order')</span></li>
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
                    <h4 class="header-title">@lang('messages.Create Production Order')</h4>
                    @include('backend.layouts.partials.messages')
                    
                    <form action="{{ route('admin.production-order.store') }}" method="post" id="production_order_form">
                        @csrf
                        
                        <!-- Order Details Section -->
                        <div class="section-title">
                            <i class="fa fa-info-circle"></i> Order Details
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="production_date">@lang('messages.Production Date') <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="production_date" name="production_date" value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="finished_goods_store_type">@lang('messages.Stock Produits Finis (Destination)') <span class="text-danger">*</span></label>
                                    <select class="form-control" name="finished_goods_store_type" id="finished_goods_store_type" required>
                                        <option value="MAIN" selected>@lang('messages.Stock Principal')</option>
                                        <option value="SM">@lang('messages.Stock SM')</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6" id="fg_main_store_col">
                                <div class="form-group">
                                    <label for="finished_g_store_id">@lang('messages.Magasin Produits Finis (Principal)') <span class="text-danger">*</span></label>
                                    <select class="form-control" name="finished_g_store_id" id="finished_g_store_id">
                                        <option disabled selected>@lang('messages.Merci de choisir')</option>
                                        @foreach($finished_goods_stores as $store)
                                            <option value="{{ $store->id }}">{{ $store->code }} - {{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6" id="fg_sm_store_col" style="display:none;">
                                <div class="form-group">
                                    <label for="finished_g_sm_store_id">@lang('messages.Magasin Produits Finis (SM)') <span class="text-danger">*</span></label>
                                    <select class="form-control" name="finished_g_sm_store_id" id="finished_g_sm_store_id">
                                        <option disabled selected>@lang('messages.Merci de choisir')</option>
                                        @foreach($finished_goods_sm_stores as $store)
                                            <option value="{{ $store->id }}">{{ $store->code }} - {{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <!-- Scheduling Section -->
                        <div class="section-title">
                            <i class="fa fa-calendar"></i> Scheduling
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="planned_start_time">@lang('messages.Planned Start Time')</label>
                                    <input type="datetime-local" class="form-control" id="planned_start_time" name="planned_start_time" value="{{ $plannedStartDefault ?? now()->format('Y-m-d\TH:i') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="planned_finish_time">@lang('messages.Planned Finish Time')</label>
                                    <input type="datetime-local" class="form-control" id="planned_finish_time" name="planned_finish_time" value="{{ $plannedFinishDefault ?? now()->addDay()->format('Y-m-d\TH:i') }}">
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="start_date" id="start_date" value="{{ date('Y-m-d') }}">
                        <input type="hidden" name="end_date" id="end_date" value="{{ date('Y-m-d', strtotime('+1 day')) }}">

                        <!-- Finished Goods Section -->
                        <div class="section-title">
                            <i class="fa fa-box"></i> Product & Quantity (Finished Goods)
                        </div>
                        <table class="table table-bordered" id="finishedGoodsTable">  
                            <tr>
                                <th width="40%">@lang('messages.Item Code / Name') <span class="text-danger">*</span></th>
                                <th width="20%">@lang('messages.Quantity') <span class="text-danger">*</span></th>
                                <th width="20%">@lang('messages.Cost Price')</th>
                                <th width="20%">@lang('messages.Action')</th>
                            </tr>
                            <tr>  
                                <td>
                                    <div class="autocomplete-wrapper">
                                        <input type="text" class="form-control item_search" placeholder="@lang('messages.type_to_search_item')" autocomplete="off" required>
                                        <input type="hidden" name="item_id[]" class="item_id">
                                        <input type="hidden" class="item_code" value="">
                                        <div class="autocomplete-list item_search-list"></div>
                                    </div>
                                </td>  
                                <td><input type="number" name="item_quantity[]" placeholder="@lang('messages.quantity_placeholder')" class="form-control" min="0" step="any" required /></td>
                                <td><input type="number" name="cost_price[]" placeholder="@lang('messages.cost_price_placeholder')" class="form-control" min="0" step="any" /></td>
                                <td><button type="button" name="add_finished" id="add_finished" class="btn btn-success">@lang('messages.Add More')</button></td>     
                            </tr>
                        </table>

                        <!-- Raw Materials Section -->
                        <div class="section-title">
                            <i class="fa fa-flask"></i> Materials (Raw Materials)
                            <small class="text-muted ml-2">(Auto-loaded from ItemDetail when finished goods are selected)</small>
                        </div>
                        <table class="table table-bordered" id="rawMaterialsTable">  
                            <thead>
                                <tr>
                                    <th width="25%">@lang('messages.Raw Material') <span class="text-danger">*</span></th>
                                    <th width="15%">@lang('messages.Quantity') <span class="text-danger">*</span></th>
                                    <th width="10%">@lang('messages.Unit')</th>
                                    <th width="15%">@lang('messages.Cost') <span class="text-danger">*</span></th>
                                    <th width="5%">@lang('messages.Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Rows will be added automatically when finished goods are selected -->
                            </tbody>
                        </table>
                        <div style="margin-top: 10px;">
                            <button type="button" name="add_raw_material" id="add_raw_material" class="btn btn-success">
                                <i class="fa fa-plus"></i> Add More Raw Material
                            </button>
                        </div>

                        <!-- Costing Section -->
                        <div class="section-title">
                            <i class="fa fa-calculator"></i> Costing
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="estimated_labor_cost">@lang('messages.Estimated Labor Cost')</label>
                                    <input type="number" class="form-control" id="estimated_labor_cost" name="estimated_labor_cost" value="0" min="0" step="any">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="estimated_overhead_cost">@lang('messages.Estimated Overhead Cost')</label>
                                    <input type="number" class="form-control" id="estimated_overhead_cost" name="estimated_overhead_cost" value="0" min="0" step="any">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('messages.Total Estimated Cost')</label>
                                    <input type="text" class="form-control" id="total_estimated_cost" readonly value="0">
                                    <small class="form-text text-muted">@lang('messages.(Calculated automatically)')</small>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label for="description">@lang('messages.Description') <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" id="description" rows="3" required>@lang('messages.PRODUCTION ORDER')</textarea>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary" id="save">@lang('messages.save')</button>
                            <a href="{{ route('admin.production-order.index') }}" class="btn btn-secondary">@lang('messages.cancel')</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script type="text/javascript">
    var itemsSearchUrl = {!! json_encode(route('admin.production-order.items-search')) !!};
    var itemsSearchTimer = null;

    function getSelectedStoreParams() {
        var storeType = $('#finished_goods_store_type').val();
        var storeId = storeType === 'SM' ? $('#finished_g_sm_store_id').val() : $('#finished_g_store_id').val();
        return { storeType: storeType, storeId: storeId };
    }

    function fetchItemsForStore(query, callback) {
        var params = getSelectedStoreParams();
        if (!params.storeType || !params.storeId) {
            callback([]);
            return;
        }
        $.get(itemsSearchUrl, {
            q: query || '',
            store_type: params.storeType,
            store_id: params.storeId,
            limit: 20
        }).done(function (data) {
            callback((data && data.results) ? data.results : []);
        }).fail(function () {
            callback([]);
        });
    }

    // ItemDetails data - raw materials for each item
    var itemDetails = [
        @foreach($itemDetails as $itemDetail)
        {
            item_code: {!! json_encode($itemDetail->code) !!},
            r_material_id: {{ $itemDetail->r_material_id ?? 0 }},
            raw_material_name: {!! json_encode($itemDetail->rawMaterial ? $itemDetail->rawMaterial->name . ' (' . ($itemDetail->rawMaterial->rawMaterialMeasurement ? $itemDetail->rawMaterial->rawMaterialMeasurement->purchase_unit : 'N/A') . ')' : 'N/A') !!},
            quantity: {{ $itemDetail->quantity ?? 0 }},
            unit: {!! json_encode($itemDetail->rawMaterial && $itemDetail->rawMaterial->rawMaterialMeasurement ? $itemDetail->rawMaterial->rawMaterialMeasurement->purchase_unit : '') !!},
            raw_material_cump: {{ $itemDetail->rawMaterial ? ($itemDetail->rawMaterial->cump ?? 0) : 0 }}
        }@if(!$loop->last),@endif
        @endforeach
    ];

    // Raw materials data for autocomplete
    var rawMaterials = [
        @foreach($raw_materials as $raw_material)
        {
            id: {{ $raw_material->id }},
            name: {!! json_encode($raw_material->name . ' (' . ($raw_material->rawMaterialMeasurement ? $raw_material->rawMaterialMeasurement->purchase_unit : 'N/A') . ')') !!},
            unit: {!! json_encode($raw_material->rawMaterialMeasurement ? $raw_material->rawMaterialMeasurement->purchase_unit : '') !!},
            cump: {{ $raw_material->cump ?? 0 }}
        }@if(!$loop->last),@endif
        @endforeach
    ];

    // Function to initialize item autocomplete
    function initializeItemAutocomplete(wrapper) {
        var input = wrapper.find('.item_search');
        var hidden = wrapper.find('.item_id');
        var codeHidden = wrapper.find('.item_code');
        var list = wrapper.find('.item_search-list');
        var selectedIndex = -1;

        input.off('input.item keydown.item');

        input.on('input.item', function() {
            var searchText = $(this).val().toLowerCase();
            list.empty();
            selectedIndex = -1;

            if (searchText === '') {
                list.hide();
                hidden.val('');
                codeHidden.val('');
                return;
            }

            clearTimeout(itemsSearchTimer);
            itemsSearchTimer = setTimeout(function () {
                fetchItemsForStore(searchText, function (matches) {
                    list.empty();
                    selectedIndex = -1;
                    if (!matches || matches.length === 0) {
                        list.hide();
                        return;
                    }
                    matches.forEach(function(item, index) {
                        var div = $('<div></div>')
                            .text(item.label)
                            .attr('data-id', item.id)
                            .attr('data-code', item.code || '');
                        if (index === 0) {
                            div.addClass('autocomplete-active');
                            selectedIndex = 0;
                        }
                        div.on('click', function() {
                            input.val(item.label);
                            hidden.val(item.id);
                            codeHidden.val(item.code || '');
                            recalculateAllRawMaterials();
                            list.hide();
                        });
                        list.append(div);
                    });
                    list.show();
                if (window.AcDropdown) AcDropdown.positionForList(list);
                });
            }, 200);
        });

        input.on('keydown.item', function(e) {
            var items = list.find('div');
            if (e.keyCode === 40) { // Down arrow
                e.preventDefault();
                selectedIndex = (selectedIndex < items.length - 1) ? selectedIndex + 1 : selectedIndex;
                items.removeClass('autocomplete-active').eq(selectedIndex).addClass('autocomplete-active');
            } else if (e.keyCode === 38) { // Up arrow
                e.preventDefault();
                selectedIndex = (selectedIndex > 0) ? selectedIndex - 1 : 0;
                items.removeClass('autocomplete-active').eq(selectedIndex).addClass('autocomplete-active');
            } else if (e.keyCode === 13) { // Enter
                e.preventDefault();
                if (selectedIndex >= 0) {
                    items.eq(selectedIndex).click();
                }
            }
        });
    }

    // Function to initialize raw material autocomplete
    function initializeRawMaterialAutocomplete(wrapper) {
        var input = wrapper.find('.raw_material_search');
        var hidden = wrapper.find('.r_material_id');
        var list = wrapper.find('.raw_material_search-list');
        var selectedIndex = -1;

        input.off('input.raw_material keydown.raw_material');

        input.on('input.raw_material', function() {
            var searchText = $(this).val().toLowerCase();
            list.empty();
            selectedIndex = -1;

            if (searchText === '') {
                list.hide();
                return;
            }

            var matches = rawMaterials.filter(function(rawMaterial) {
                return rawMaterial.name.toLowerCase().includes(searchText);
            });

            if (matches.length > 0) {
                matches.forEach(function(rawMaterial, index) {
                    var div = $('<div></div>').text(rawMaterial.name);
                    if (index === 0) {
                        div.addClass('autocomplete-active');
                        selectedIndex = 0;
                    }
                    div.on('click', function() {
                        input.val(rawMaterial.name);
                        hidden.val(rawMaterial.id);
                        // Auto-fill unit and cost from cump
                        var row = wrapper.closest('tr');
                        row.find('input[name="unit[]"]').val(rawMaterial.unit || '');
                        var costInput = row.find('input[name="material_cost[]"]');
                        if (!costInput.val() || costInput.val() == '0') {
                            costInput.val(rawMaterial.cump || 0);
                        }
                        // Calculate total cost for this row
                        calculateRowTotal(row, 'material');
                        list.hide();
                    });
                    list.append(div);
                });
                list.show();
                if (window.AcDropdown) AcDropdown.positionForList(list);
            } else {
                list.hide();
            }
        });

        input.on('keydown.raw_material', function(e) {
            var items = list.find('div');
            if (e.keyCode === 40) {
                e.preventDefault();
                selectedIndex = (selectedIndex < items.length - 1) ? selectedIndex + 1 : selectedIndex;
                items.removeClass('autocomplete-active').eq(selectedIndex).addClass('autocomplete-active');
            } else if (e.keyCode === 38) {
                e.preventDefault();
                selectedIndex = (selectedIndex > 0) ? selectedIndex - 1 : 0;
                items.removeClass('autocomplete-active').eq(selectedIndex).addClass('autocomplete-active');
            } else if (e.keyCode === 13) {
                e.preventDefault();
                if (selectedIndex >= 0) {
                    items.eq(selectedIndex).click();
                }
            }
        });
    }

    // Hide autocomplete lists when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.autocomplete-wrapper').length) {
            $('.autocomplete-list').hide();
        }
    });

    // Initialize autocomplete for existing rows
    $(document).ready(function() {
        function syncProductionDateFields() {
            var startVal = $('#planned_start_time').val();
            var finishVal = $('#planned_finish_time').val();
            if (startVal) {
                $('#start_date').val(startVal.substring(0, 10));
            }
            if (finishVal) {
                $('#end_date').val(finishVal.substring(0, 10));
            }
        }
        syncProductionDateFields();
        $('#planned_start_time, #planned_finish_time').on('change', syncProductionDateFields);

        function syncFinishedGoodsStoreUI() {
            var type = $('#finished_goods_store_type').val();
            if (type === 'SM') {
                $('#fg_sm_store_col').show();
                $('#fg_main_store_col').hide();
                $('#finished_g_sm_store_id').prop('required', true);
                $('#finished_g_store_id').prop('required', false);
            } else {
                $('#fg_sm_store_col').hide();
                $('#fg_main_store_col').show();
                $('#finished_g_sm_store_id').prop('required', false);
                $('#finished_g_store_id').prop('required', true);
            }
        }

        $('#finished_goods_store_type').on('change', syncFinishedGoodsStoreUI);
        syncFinishedGoodsStoreUI();

        $('.autocomplete-wrapper').each(function() {
            if ($(this).find('.item_search').length) {
                initializeItemAutocomplete($(this));
            }
            if ($(this).find('.raw_material_search').length) {
                initializeRawMaterialAutocomplete($(this));
            }
        });
    });

    // Add finished goods row
    var finishedGoodsCount = 0;
    $("#add_finished").click(function(){
        ++finishedGoodsCount;
        var markup = "<tr>"+
                     "<td>"+
                        "<div class='autocomplete-wrapper'>"+
                           "<input type='text' class='form-control item_search' placeholder='Type to search item...' autocomplete='off' required>"+
                           "<input type='hidden' name='item_id[]' class='item_id'>"+
                           "<input type='hidden' class='item_code' value=''>"+
                           "<div class='autocomplete-list item_search-list'></div>"+
                        "</div>"+
                       "</td>"+
                       "<td>"+
                         "<input type='number' name='item_quantity[]' placeholder='Quantity' class='form-control' min='0' step='any' required/>"+
                       "</td>"+
                       "<td>"+
                         "<input type='number' name='cost_price[]' placeholder='Cost Price' class='form-control' min='0' step='any'/>"+
                       "</td>"+
                       "<td>"+
                         "<button type='button' class='btn btn-danger remove-tr'>@lang('messages.Delete')</button>"+
                       "</td>"+
                   "</tr>";
        var newRow = $(markup);
        $("#finishedGoodsTable").append(newRow);
        initializeItemAutocomplete(newRow.find('.autocomplete-wrapper'));
    });

    // Note: Raw materials rows are added automatically from ItemDetail when finished goods are selected
    // No manual "Add More" button for raw materials

    // Remove row
    $(document).on('click', '.remove-tr', function(){  
        $(this).parents('tr').remove();
        calculateTotalCost();
    });

    // Recalculate all raw materials based on all selected finished goods
    function recalculateAllRawMaterials() {
        // First, collect all required materials from all finished goods
        var materialsMap = {}; // r_material_id -> {quantity, raw_material_name, unit, raw_material_cump}
        
        // Loop through all finished goods rows
        $('#finishedGoodsTable tr').each(function() {
            // Skip header row
            if ($(this).find('th').length > 0) {
                return;
            }
            
            var itemId = $(this).find('.item_id').val();
            var itemCode = $(this).find('.item_code').val();
            var itemQuantity = parseFloat($(this).find('input[name="item_quantity[]"]').val() || 0);
            
            // Include selected items even when quantity is still 0/empty:
            // raw material rows will show with 0 quantities and update later when qty changes.
            if (itemId) {
                if (itemCode) {
                    // Find all ItemDetails for this item code
                    var details = itemDetails.filter(function(detail) {
                        var detailCode = String(detail.item_code || '').trim();
                        var searchCode = String(itemCode || '').trim();
                        return detailCode.toLowerCase() === searchCode.toLowerCase();
                    });
                    
                    // Process each raw material for this item
                    details.forEach(function(detail) {
                        if (detail.r_material_id && detail.r_material_id > 0) {
                            var rMaterialId = parseInt(detail.r_material_id);
                            var requiredQuantity = itemQuantity * (parseFloat(detail.quantity) || 0);
                            
                            if (materialsMap[rMaterialId]) {
                                // Add to existing quantity if same raw material is used by multiple items
                                materialsMap[rMaterialId].quantity += requiredQuantity;
                            } else {
                                // Create new entry
                                materialsMap[rMaterialId] = {
                                    quantity: requiredQuantity,
                                    raw_material_name: detail.raw_material_name || '',
                                    unit: detail.unit || '',
                                    raw_material_cump: detail.raw_material_cump || 0
                                };
                            }
                        }
                    });
                }
            }
        });
        
        // Clear existing raw materials rows (except header)
        $('#rawMaterialsTable tbody tr').each(function() {
            if ($(this).find('th').length === 0) {
                $(this).remove();
            }
        });
        
        // Add all materials to the table
        for (var rMaterialId in materialsMap) {
            if (materialsMap.hasOwnProperty(rMaterialId)) {
                var material = materialsMap[rMaterialId];
                var markup = "<tr>"+
                             "<td>"+
                                "<div class='autocomplete-wrapper'>"+
                                   "<input type='text' class='form-control raw_material_search' value='" + material.raw_material_name + "' placeholder='Type to search raw material...' autocomplete='off' required>"+
                                   "<input type='hidden' name='r_material_id[]' class='r_material_id' value='" + rMaterialId + "'>"+
                                   "<div class='autocomplete-list raw_material_search-list'></div>"+
                                "</div>"+
                               "</td>"+
                               "<td>"+
                                 "<input type='number' name='material_quantity[]' value='" + material.quantity.toFixed(2) + "' placeholder='Quantity' class='form-control' min='0' step='any' required/>"+
                               "</td>"+
                               "<td>"+
                                 "<input type='text' name='unit[]' value='" + (material.unit || '') + "' placeholder='Unit' class='form-control'/>"+
                               "</td>"+
                               "<td>"+
                                 "<input type='number' name='material_cost[]' value='" + material.raw_material_cump + "' placeholder='Cost' class='form-control' min='0' step='any' required/>"+
                               "</td>"+
                               "<td>"+
                                 "<button type='button' class='btn btn-danger remove-tr'>@lang('messages.Delete')</button>"+
                               "</td>"+
                           "</tr>";
                var newRow = $(markup);
                $("#rawMaterialsTable tbody").append(newRow);
                initializeRawMaterialAutocomplete(newRow.find('.autocomplete-wrapper'));
            }
        }
        
        calculateTotalCost();
    }

    // Load raw materials from ItemDetail when item is selected (legacy function, now calls recalculateAllRawMaterials)
    function loadRawMaterialsFromItem(itemCode, itemRow) {
        // Recalculate all raw materials based on all finished goods
        recalculateAllRawMaterials();
    }

    // Calculate total cost for a specific row
    function calculateRowTotal(row, type) {
        if (type === 'item') {
            var qty = parseFloat(row.find('input[name="item_quantity[]"]').val() || 0);
            var cost = parseFloat(row.find('input[name="cost_price[]"]').val() || 0);
            // Recalculate all raw materials when item quantity changes
            recalculateAllRawMaterials();
        } else if (type === 'material') {
            var qty = parseFloat(row.find('input[name="material_quantity[]"]').val() || 0);
            var cost = parseFloat(row.find('input[name="material_cost[]"]').val() || 0);
            // You can add a total column if needed
        }
        calculateTotalCost();
    }

    // Calculate total cost and update finished goods cost_price
    function calculateTotalCost() {
        var laborCost = parseFloat($('#estimated_labor_cost').val() || 0);
        var overheadCost = parseFloat($('#estimated_overhead_cost').val() || 0);
        var materialCost = 0;
        
        $('input[name="material_quantity[]"]').each(function() {
            var qty = parseFloat($(this).val() || 0);
            var cost = parseFloat($(this).closest('tr').find('input[name="material_cost[]"]').val() || 0);
            materialCost += qty * cost;
        });
        
        var total = laborCost + materialCost + overheadCost;
        $('#total_estimated_cost').val(total.toFixed(2));
        
        // Calculate total quantity of finished goods
        var totalFinishedGoodsQuantity = 0;
        $('#finishedGoodsTable tr').each(function() {
            if ($(this).find('th').length > 0) {
                return; // Skip header
            }
            var itemId = $(this).find('.item_id').val();
            if (itemId) {
                var qty = parseFloat($(this).find('input[name="item_quantity[]"]').val() || 0);
                totalFinishedGoodsQuantity += qty;
            }
        });
        
        // Distribute total cost to finished goods proportionally
        if (totalFinishedGoodsQuantity > 0 && total > 0) {
            var costPerUnit = total / totalFinishedGoodsQuantity;
            
            $('#finishedGoodsTable tr').each(function() {
                if ($(this).find('th').length > 0) {
                    return; // Skip header
                }
                var itemId = $(this).find('.item_id').val();
                if (itemId) {
                    var qty = parseFloat($(this).find('input[name="item_quantity[]"]').val() || 0);
                    if (qty > 0) {
                        // Set cost_price = Total Estimated Cost (same for all finished goods)
                        // Or distribute proportionally: cost_price = (total / totalQuantity) * itemQuantity
                        // Based on user requirement: cost_price should equal Total Estimated Cost
                        var costInput = $(this).find('input[name="cost_price[]"]');
                        costInput.val(total.toFixed(2));
                    }
                }
            });
        }
    }

    // Calculate totals when inputs change
    $(document).on('input', '#estimated_labor_cost, #estimated_overhead_cost', function() {
        calculateTotalCost();
    });

    $(document).on('input', 'input[name="material_quantity[]"], input[name="material_cost[]"]', function() {
        var row = $(this).closest('tr');
        calculateRowTotal(row, 'material');
    });

    $(document).on('input', 'input[name="item_quantity[]"]', function() {
        var row = $(this).closest('tr');
        // Recalculate all raw materials when quantity changes
        recalculateAllRawMaterials();
        calculateRowTotal(row, 'item');
    });

    // When store selection changes: clear selected items and rebuild raw materials
    $('#finished_goods_store_type, #finished_g_store_id, #finished_g_sm_store_id').on('change', function () {
        $('#finishedGoodsTable .item_id').val('');
        $('#finishedGoodsTable .item_code').val('');
        $('#finishedGoodsTable .item_search').val('');
        recalculateAllRawMaterials();
    });

    $(document).on('input', 'input[name="cost_price[]"]', function() {
        var row = $(this).closest('tr');
        calculateRowTotal(row, 'item');
    });

    // Form submission confirmation
    $('#production_order_form').on('submit', function(e) {
        if (!confirm({{ json_encode(__('messages.confirm_create_production_order')) }})) {
            e.preventDefault();
            return false;
        }
    });
</script>
@endsection

