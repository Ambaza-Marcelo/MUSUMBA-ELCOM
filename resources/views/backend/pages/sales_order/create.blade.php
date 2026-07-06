@extends('backend.layouts.master')

@section('title')
{{ $proforma_number ? __('messages.create_so_from_proforma') : __('messages.new_sales_order') }} - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .autocomplete { position: relative; display: inline-block; width: 100%; }
    .autocomplete-items { position: absolute; border: 1px solid #d4d4d4; z-index: 99; top: 100%; left: 0; right: 0; max-height: 200px; overflow-y: auto; background: white; }
    .autocomplete-items div { padding: 10px; cursor: pointer; border-bottom: 1px solid #d4d4d4; }
    .autocomplete-items div:hover { background-color: #e9e9e9; }
    .autocomplete-active { background-color: DodgerBlue !important; color: #fff; }
    .readonly-field { background-color: #f5f5f5; cursor: not-allowed; }
</style>
@endsection

@section('admin-content')
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">{{ $proforma_number ? __('messages.create_sales_order') : __('messages.new_sales_order') }}</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.sales-order.index') }}">@lang('messages.Bons de commande')</a></li>
                    <li><span>{{ $proforma_number ? __('messages.from_proforma') . ' ' . $proforma_number : __('messages.new') }}</span></li>
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
                    <h4 class="header-title">{{ $proforma_number ? __('messages.create_so_from_proforma_header') . ' ' . $proforma_number : __('messages.new_sales_order') }}</h4>
                    @include('backend.layouts.partials.messages')
                    <form action="{{ route('admin.sales-order.store') }}" method="post" id="dynamic_form" enctype="multipart/form-data">
                        @csrf
                        @if($proforma_number)
                            <input type="hidden" name="proforma_number" value="{{ $proforma_number }}">
                            <input type="hidden" name="document_no" value="{{ $proforma_number }}">
                        @endif
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="client_id">@lang('messages.Client')</label>
                                    @if($proforma_invoice && $proforma_invoice->client)
                                        <input type="text" class="form-control readonly-field" value="{{ $proforma_invoice->client->customer_name }}/{{ $proforma_invoice->client->telephone }}" readonly />
                                        <input type="hidden" name="client_id" value="{{ $proforma_invoice->client_id }}" />
                                    @else
                                        <div class="autocomplete">
                                            <input type="text" id="client_name" name="client_name" class="form-control" placeholder="@lang('messages.choose_client')" autocomplete="off" required
                                                value="{{ ($defaultClient ?? null) ? $defaultClient->customer_name . '/' . $defaultClient->telephone : '' }}" />
                                            <input type="hidden" name="client_id" id="client_id" value="{{ ($defaultClient ?? null) ? $defaultClient->id : '' }}" required />
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sales_order_date">@lang('messages.date')</label>
                                    <input type="date" class="form-control" id="sales_order_date" name="sales_order_date"
                                           value="{{ old('sales_order_date', now()->format('Y-m-d')) }}">
                                </div>
                            </div>
                        </div>
                        <table class="table table-bordered" id="dynamicTable">
                            <tr>
                                <th>@lang('messages.item')</th>
                                <th>@lang('messages.quantity')</th>
                                <th>@lang('messages.Prix Unitaire')</th>
                                <th>@lang('messages.Total')</th>
                                @if(!$datas->isNotEmpty())<th>@lang('messages.Action')</th>@endif
                            </tr>
                            @if($datas->isNotEmpty())
                                @php $grand_total = 0; @endphp
                                @foreach($datas as $index => $detail)
                                @php
                                    $item_name = $detail->item ? $detail->item->name : ($detail->item_designation ?? 'N/A');
                                    $grand_total += $detail->item_total_amount;
                                @endphp
                                <tr>
                                    <td>
                                        <input type="text" class="form-control readonly-field" value="{{ $item_name }}/{{ number_format($detail->item_price ?? 0, 0, ',', ' ') }}" readonly />
                                        <input type="hidden" name="item_id[]" value="{{ $detail->item_id }}" />
                                    </td>
                                    <td><input type="number" name="quantity[]" class="form-control" value="{{ $detail->item_quantity }}" required /></td>
                                    <td><input type="text" class="form-control readonly-field" value="{{ number_format($detail->item_price ?? 0, 0, ',', ' ') }} BIF" readonly /><input type="hidden" name="item_price[]" value="{{ $detail->item_price ?? 0 }}" /></td>
                                    <td><input type="text" class="form-control readonly-field" value="{{ number_format($detail->item_total_amount ?? 0, 0, ',', ' ') }} BIF" readonly /></td>
                                </tr>
                                @endforeach
                            @else
                            <tr>
                                <td>
                                    <div class="autocomplete">
                                        <input type="text" name="item_name[]" id="item_name_0" placeholder="@lang('messages.search_item_placeholder')" class="form-control" autocomplete="off" required />
                                        <input type="hidden" name="item_id[]" id="item_id_0" />
                                    </div>
                                </td>
                                <td><input type="number" name="quantity[]" id="quantity_0" class="form-control quantity-input" required /></td>
                                <td><input type="text" id="unit_price_0" class="form-control unit-price" readonly /></td>
                                <td><input type="text" id="total_price_0" class="form-control total-price" readonly /></td>
                                <td><button type="button" name="add" id="add" class="btn btn-success">@lang('messages.addmore')</button></td>
                            </tr>
                            @endif
                        </table>
                        @if($datas->isNotEmpty())
                        <div class="row mt-3">
                            <div class="col-md-4 offset-md-8">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">@lang('messages.Total Général')</h5>
                                        <h3 id="grand_total" class="text-primary">{{ number_format($datas->sum('item_total_amount'), 0, ',', ' ') }} BIF</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="row mt-3">
                            <div class="col-md-4 offset-md-8">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">@lang('messages.Total Général')</h5>
                                        <h3 id="grand_total" class="text-primary">@lang('messages.0 BIF')</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        <div class="col-lg-12 mt-2 form-group">
                            <label for="document">@lang('messages.Document (PDF / Image)')</label>
                            <input type="file" class="form-control document-upload-with-preview" name="document" id="document" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">@lang('messages.PDF, JPG, JPEG, PNG')</small>
                            @include('backend.layouts.partials.document_upload_preview')
                        </div>
                        <div class="col-lg-12 mt-2">
                            <label for="description">@lang('messages.description')</label>
                            <textarea class="form-control" name="description" id="description" placeholder="Description">{{ $proforma_invoice->description ?? '' }}</textarea>
                        </div>
                        <div style="margin-top: 15px; margin-left: 15px;">
                            <button type="submit" class="btn btn-primary" id="save">@lang('messages.save')</button>
                            <a href="{{ $proforma_number ? route('admin.proforma-invoice.index') : route('admin.sales-order.index') }}" class="btn btn-secondary">@lang('messages.cancel')</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@if(!$datas->isNotEmpty())
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script>
var i = 0;
// Build items array in JavaScript
var items = [
@foreach($items as $item)
    {
        id: {{ $item->id }},
        name: {{ json_encode($item->name) }},
        price: {{ $item->selling_price ?? 0 }},
        display: {{ json_encode($item->name . '/' . number_format($item->selling_price ?? 0, 0, ',', ' ')) }}
    },
@endforeach
];

// Build clients array in JavaScript
var clients = [
@foreach($clients as $c)
    {
        id: {{ $c->id }},
        name: {{ json_encode($c->customer_name) }},
        display: {{ json_encode($c->customer_name . '/' . ($c->telephone ?? '')) }}
    },
@endforeach
];
function autocomplete(inp, arr, hiddenInput) {
    inp.addEventListener("input", function() {
        var val = this.value; var a = document.createElement("DIV"); a.setAttribute("class", "autocomplete-items"); a.setAttribute("id", this.id + "list");
        this.parentNode.appendChild(a);
        for (var i = 0; i < arr.length; i++) {
            if (arr[i].name.toLowerCase().substr(0, val.length) !== val.toLowerCase()) continue;
            var b = document.createElement("DIV"); b.innerHTML = arr[i].display; b.addEventListener("click", function() {
                inp.value = this.innerHTML; var sel = arr.find(function(x) { return x.display === inp.value; }); if (sel) hiddenInput.value = sel.id;
                if (sel && sel.price !== undefined) { var rowIndex = inp.id.split('_')[2]; if (rowIndex !== undefined) { document.getElementById('unit_price_' + rowIndex).value = sel.price.toLocaleString() + ' BIF'; } }
                a.remove();
            }); a.appendChild(b);
        }
    });
    document.addEventListener("click", function(e) { if (e.target !== inp) { var x = document.getElementById(inp.id + "list"); if (x) x.remove(); } });
}
autocomplete(document.getElementById("client_name"), clients, document.getElementById("client_id"));
autocomplete(document.getElementById("item_name_0"), items, document.getElementById("item_id_0"));
$("#add").click(function(){
    ++i;
    var row = '<tr><td><div class="autocomplete"><input type="text" name="item_name[]" id="item_name_'+i+'" class="form-control" placeholder="Rechercher..." required /><input type="hidden" name="item_id[]" id="item_id_'+i+'" /></div></td><td><input type="number" name="quantity[]" id="quantity_'+i+'" class="form-control quantity-input" required /></td><td><input type="text" id="unit_price_'+i+'" class="form-control unit-price" readonly /></td><td><input type="text" id="total_price_'+i+'" class="form-control total-price" readonly /></td><td><button type="button" class="btn btn-danger remove-tr">@lang('messages.Supprimer')</button></td></tr>';
    $("#dynamicTable").append(row);
    setTimeout(function(){ autocomplete(document.getElementById("item_name_"+i), items, document.getElementById("item_id_"+i)); }, 100);
});
$(document).on("click", ".remove-tr", function(){ $(this).closest("tr").remove(); });
$(document).on("input", ".quantity-input", function(){ var idx = this.id.split("_")[1]; var q = parseFloat(this.value)||0; var pid = $("#item_id_"+idx).val(); var it = items.find(function(x){ return x.id == pid; }); var p = it ? it.price : 0; $("#total_price_"+idx).val((q*p).toLocaleString()+" BIF"); });
</script>
@endif
@endsection
