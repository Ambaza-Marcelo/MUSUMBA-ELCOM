
@extends('backend.layouts.master')

@section('title')
@lang('messages.sortie de fonds') - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .form-check-label { text-transform: capitalize; }
    .autocomplete-wrapper { position: relative; }
    .autocomplete-list { position: absolute; border: 1px solid #d4d4d4; border-top: none; z-index: 99; max-height: 200px; overflow-y: auto; background: #fff; width: 100%; display: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: -1px; }
    .autocomplete-list div { padding: 10px; cursor: pointer; border-bottom: 1px solid #d4d4d4; }
    .autocomplete-list div:hover { background-color: #e9e9e9; }
    .autocomplete-list div:last-child { border-bottom: none; }
    .autocomplete-active { background-color: #4CAF50 !important; color: #fff; }
</style>
@endsection


@section('admin-content')
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.sortie de fonds')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.cash-out-flow.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.sortie de fonds')</span></li>
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
                    <h4 class="header-title">@lang('messages.sortie de fonds')</h4>
                    @include('backend.layouts.partials.messages')
                    
                    <form action="{{ route('admin.cash-out-flow.store') }}" method="post" id="dynamic_form">
                        @csrf
                    <input type="hidden" name="cash_outflow_request_no" value="{{ $cash_outflow_request_no }}">
                    <div class="row">
                        <div class="col-md-4" id="dynamicDiv">
                            <div class="form-group">
                                <label for="date">@lang('messages.date')</label>
                                <input type="datetime-local" class="form-control" id="date" name="date" value="{{ $data->date }}">
                            </div>
                        </div>
                        <div class="col-md-4" id="dynamicDiv">
                            <div class="form-group">
                                <label for="asker">@lang('messages.asker')</label>
                                <input type="text" class="form-control" id="asker" name="asker" value="{{ $data->asker }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="employe_id">@lang('messages.Employé')</label>
                                <select class="form-control" name="employe_id" id="employe_id" required>
                                 <option disabled="disabled" selected="selected">@lang('messages.Merci de choisir')</option>
                                @foreach($employes as $employe)
                                    <option value="{{$employe->id}}" {{ $data->employe_id == $employe->id ? 'selected' : '' }}>{{$employe->firstname}} {{$employe->lastname}}</option>
                                @endforeach
                             </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="cost_center_id">@lang('messages.Cost Center')</label>
                                <select class="form-control" name="cost_center_id" id="cost_center_id" required>
                                 <option disabled="disabled" selected="selected">@lang('messages.Merci de choisir')</option>
                                @foreach($cost_centers as $cost_center)
                                    <option value="{{$cost_center->id}}" {{ $data->cost_center_id == $cost_center->id ? 'selected' : '' }}>{{$cost_center->name}}</option>
                                @endforeach
                             </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="currency_id">@lang('messages.Currency')</label>
                                <select class="form-control" name="currency_id" id="currency_id" required>
                                 <option disabled="disabled" selected="selected">@lang('messages.Merci de choisir')</option>
                                @foreach($currencies as $currency)
                                    <option value="{{$currency->id}}" {{ $data->currency_id == $currency->id ? 'selected' : '' }}>{{$currency->currency_name}}</option>
                                @endforeach
                             </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="account_id">@lang('messages.Account')</label>
                                <div class="autocomplete-wrapper">
                                    <input type="text" id="account_search" class="form-control" placeholder="@lang('messages.type_to_search_account')" autocomplete="off">
                                    <input type="hidden" name="account_id" id="account_id" value="" required>
                                    <div class="autocomplete-list" id="account_search-list"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="vat_supplier_payer">@lang('messages.assujetti à la tva?')</label>
                                @php $requestVatPayer = optional($datas->first())->vat_supplier_payer; @endphp
                                <select class="form-control" id="vat_supplier_payer" disabled>
                                    <option value="0" {{ $requestVatPayer == 0 ? 'selected' : '' }}>@lang('messages.Non assujetti')</option>
                                    <option value="1" {{ $requestVatPayer == 1 ? 'selected' : '' }}>@lang('messages.Assujetti')</option>
                                </select>
                                <input type="hidden" name="vat_supplier_payer" value="{{ $requestVatPayer ?? 0 }}">
                            </div>
                        </div>
                        <div class="col-md-6" id="vat_rate">
                            
                        </div>
                    </div>

                         <table class="table table-bordered" id="dynamicTable">  
                            <tr class="">
                                <th>@lang('messages.item')</th>
                                <th>@lang('messages.quantity')</th>
                                <th>P.U.</th>
                                <th>HTVA</th>
                                <th>TVA</th>
                                <th>TTC</th>
                            </tr>
                            @foreach($datas as $data)
                            <tr class="">    
                                <td>
                                    {{ optional($data->expense)->name }}
                                    <input type="hidden" name="expense_id[]" value="{{ $data->expense_id }}">
                                </td>
                                <td>
                                    {{ number_format($data->quantity, 2, ',', ' ') }}
                                    <input type="hidden" name="quantity[]" value="{{ $data->quantity }}">
                                </td>
                                <td>{{ number_format($data->cost_price, 0, ',', ' ') }}</td>
                                <td>{{ number_format($data->cost_nvat, 0, ',', ' ') }}</td>
                                <td>{{ number_format($data->vat, 0, ',', ' ') }}</td>
                                <td>{{ number_format($data->cost_wvat, 0, ',', ' ') }}</td>
                            </tr>
                            @endforeach
                        </table>
                        <div class="col-lg-12">
                            <label for="description">@lang('messages.description')</label>
                            <textarea class="form-control" name="description" id="description" placeholder="@lang('messages.enter_description')">
                                SORTIE DE FONDS
                            </textarea>
                        </div>
                        <div style="margin-top: 15px;margin-left: 15px;">
                            <button type="submit" onclick="this.style.visibility='hidden';" ondblclick="this.style.visibility='hidden';" class="btn btn-primary" id="save">@lang('messages.save')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
</div>

<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
@include('backend.layouts.partials.ajax_autocomplete')
    <script type="text/javascript">
    $(document).ready(function(){
        attachAjaxAutocomplete({
            $input: $('#account_search'),
            $hidden: $('#account_id'),
            url: '{{ route("admin.accounting-accounts.search") }}',
            minChars: 1,
            renderItem: function(item){
                var t = (item.text || '').toString().replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                return '<div><strong>' + t + '</strong></div>';
            }
        });
    });

    //one checked box in checkbox group of invoice_currency

    var group_=(el,callback)=>{
        el.forEach((checkbox)=>{
        callback(checkbox)
        })
    }

    group_(document.getElementsByName('invoice_currency'),(item)=>{
    item.onclick=(e)=>{
    group_(document.getElementsByName('invoice_currency'),(item)=>{
    item.checked=false;
    })
    e.target.checked=true;

    }
    })

    $('#vat_supplier_payer').change(function () { 
    if ($(this).val() === '1'){

                var vat_rate = "<label for='vat_rate'>@lang('messages.merci de choisir')<strong style='color: red;'>*</strong></label>"+
                            "<select class='form-control' disabled>"+
                                "<option value='0' {{ $data->vat_rate == '0' ? 'selected' : '' }}>0%</option>"+
                                "<option value='10' {{ $data->vat_rate == '10' ? 'selected' : '' }}>10%</option>"+
                                "<option value='18' {{ $data->vat_rate == '18' ? 'selected' : '' }}>18%</option>";
        
        $("#vat_rate").append([vat_rate]);
    }

    })
    .trigger( "change" ); 

    function preventBack() {
        window.history.forward();
    }
    setTimeout("preventBack()", 0);
    window.onunload = function () {
        null
    };

</script>
@endsection
