
@extends('backend.layouts.master')

@section('title')
{{ __('messages.edit_production_plan') }} {{ $plan->prodution_plan_number }} - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .form-check-label {
        text-transform: capitalize;
    }
    .autocomplete-wrapper {
        position: relative;
    }
    #finishedGoodsTable,
    #rawMaterialsTable,
    #finishedGoodsTable td,
    #rawMaterialsTable td,
    .card-body {
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
                <h4 class="page-title pull-left">{{ __('messages.edit_production_plan') }} {{ $plan->prodution_plan_number }}</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.production-plan.index') }}">@lang('messages.list')</a></li>
                    <li><a href="{{ route('admin.production-plan.show', $plan->prodution_plan_number) }}">{{ $plan->prodution_plan_number }}</a></li>
                    <li><span>@lang('messages.Modifier')</span></li>
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
                    <h4 class="header-title">{{ __('messages.edit_production_plan') }}: {{ $plan->prodution_plan_number }}</h4>
                    @include('backend.layouts.partials.messages')
                    
                    <form action="{{ route('admin.production-plan.update', $plan->prodution_plan_number) }}" method="post" id="production_plan_form">
                        @csrf
                        @method('PUT')
                        
                        <div class="section-title">
                            <i class="fa fa-info-circle"></i> @lang('messages.production_plan_details')
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="production_plan_date">@lang('messages.Date du plan') <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="production_plan_date" name="production_plan_date" value="{{ old('production_plan_date', \Carbon\Carbon::parse($plan->production_plan_date)->format('Y-m-d')) }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="planned_start_time">@lang('messages.Planned Start Time')</label>
                                    <input type="datetime-local" class="form-control" id="planned_start_time" name="planned_start_time" value="{{ old('planned_start_time', $plannedStartDefault) }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="planned_finish_time">@lang('messages.Planned Finish Time')</label>
                                    <input type="datetime-local" class="form-control" id="planned_finish_time" name="planned_finish_time" value="{{ old('planned_finish_time', $plannedFinishDefault) }}">
                                </div>
                            </div>
                        </div>

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
                            @foreach($finishedGoods as $good)
                            <tr>  
                                <td>
                                    <div class="autocomplete-wrapper">
                                        <input type="text" class="form-control item_search" value="{{ $good->item ? $good->item->code . ' - ' . $good->item->name : 'N/A' }}" placeholder="@lang('messages.type_to_search_item')" autocomplete="off" required>
                                        <input type="hidden" name="item_id[]" class="item_id" value="{{ $good->item_id }}">
                                        <input type="hidden" class="item_code" value="{{ $good->item ? $good->item->code : '' }}">
                                    </div>
                                </td>  
                                <td><input type="number" name="item_quantity[]" value="{{ $good->item_quantity }}" placeholder="@lang('messages.quantity_placeholder')" class="form-control" min="0" step="any" required /></td>
                                <td><input type="number" name="cost_price[]" value="{{ $good->cost_price }}" placeholder="@lang('messages.cost_price_placeholder')" class="form-control" min="0" step="any" /></td>
                                <td>
                                    @if($loop->first)
                                    <button type="button" class="btn btn-success add-finished-btn">@lang('messages.Add More')</button>
                                    @else
                                    <button type="button" class="btn btn-danger remove-tr">@lang('messages.Delete')</button>
                                    @endif
                                </td>     
                            </tr>
                            @endforeach
                            @if($finishedGoods->isEmpty())
                            <tr>  
                                <td>
                                    <div class="autocomplete-wrapper">
                                        <input type="text" class="form-control item_search" placeholder="@lang('messages.type_to_search_item')" autocomplete="off" required>
                                        <input type="hidden" name="item_id[]" class="item_id">
                                        <input type="hidden" class="item_code" value="">
                                    </div>
                                </td>  
                                <td><input type="number" name="item_quantity[]" placeholder="@lang('messages.quantity_placeholder')" class="form-control" min="0" step="any" required /></td>
                                <td><input type="number" name="cost_price[]" placeholder="@lang('messages.cost_price_placeholder')" class="form-control" min="0" step="any" /></td>
                                <td><button type="button" class="btn btn-success add-finished-btn">@lang('messages.Add More')</button></td>     
                            </tr>
                            @endif
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
                                @foreach($rawMaterials as $material)
                                <tr>  
                                    <td>
                                        <div class="autocomplete-wrapper">
                                            <input type="text" class="form-control raw_material_search" value="{{ $material->rawMaterial ? $material->rawMaterial->name . ' (' . ($material->rawMaterial->rawMaterialMeasurement ? $material->rawMaterial->rawMaterialMeasurement->purchase_unit : '') . ')' : 'N/A' }}" placeholder="@lang('messages.type_to_search_raw_material')" autocomplete="off" required>
                                            <input type="hidden" name="r_material_id[]" class="r_material_id" value="{{ $material->r_material_id }}">
                                        </div>
                                    </td>  
                                    <td><input type="number" name="material_quantity[]" value="{{ $material->material_quantity }}" placeholder="@lang('messages.quantity_placeholder')" class="form-control" min="0" step="any" required /></td>
                                    <td><input type="text" name="unit[]" value="{{ $material->rawMaterial && $material->rawMaterial->rawMaterialMeasurement ? $material->rawMaterial->rawMaterialMeasurement->purchase_unit : '' }}" placeholder="@lang('messages.unit_placeholder')" class="form-control" readonly /></td>
                                    <td><input type="number" name="material_cost[]" value="{{ $material->material_cost }}" placeholder="@lang('messages.cost_placeholder')" class="form-control" min="0" step="any" required /></td>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-tr">@lang('messages.Delete')</button></td>     
                                </tr>
                                @endforeach
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
                                    <input type="number" class="form-control" id="estimated_labor_cost" name="estimated_labor_cost" value="{{ $plan->estimated_labor_cost }}" min="0" step="any">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="estimated_overhead_cost">@lang('messages.Estimated Overhead Cost')</label>
                                    <input type="number" class="form-control" id="estimated_overhead_cost" name="estimated_overhead_cost" value="{{ $plan->estimated_overhead_cost }}" min="0" step="any">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('messages.Total Estimated Cost')</label>
                                    <input type="text" class="form-control" id="total_estimated_cost" readonly value="{{ $plan->total_estimated_cost }}">
                                    <small class="form-text text-muted">@lang('messages.(Calculated automatically)')</small>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label for="description">@lang('messages.Description') <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" id="description" rows="3" required>{{ $plan->description }}</textarea>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary" id="save">@lang('messages.update')</button>
                            <a href="{{ route('admin.production-plan.index') }}" class="btn btn-secondary">@lang('messages.cancel')</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@php
    $productionPlanConfirmMessage = __('messages.save_production_plan_changes_confirm');
    $recalculateOnLoad = true;
@endphp
@include('backend.pages.production_plan.partials.autocomplete_scripts')
@endsection

