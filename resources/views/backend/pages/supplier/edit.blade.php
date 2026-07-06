
@extends('backend.layouts.master')

@section('title')
@lang('messages.Modifier du fournisseur') - @lang('messages.admin_panel')
@endsection

@section('styles')
<style>
    .form-check-label {
        text-transform: capitalize;
    }
</style>
@endsection


@section('admin-content')

<!-- page title area start -->
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">@lang('messages.Modifier du fournisseur')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.suppliers.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.Modifier du fournisseur')</span></li>
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
                    <h4 class="header-title">@lang('messages.Modifier du Fournisseur')</h4>
                    @include('backend.layouts.partials.messages')

                    <form action="{{ route('admin.suppliers.update', $supplier->id) }}" method="POST" id="supplier_form">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tp_type">@lang('messages.Type de contribuable')</label>
                                    <select name="tp_type" id="tp_type" class="form-control" required>
                                        <option value="">@lang('messages.merci de choisir')</option>
                                        <option value="1" {{ old('tp_type', $supplier->tp_type) == '1' ? 'selected' : '' }}>Personne Physique</option>
                                        <option value="2" {{ old('tp_type', $supplier->tp_type) == '2' ? 'selected' : '' }}>société locale</option>
                                        <option value="3" {{ old('tp_type', $supplier->tp_type) == '3' ? 'selected' : '' }}>société étrangère</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6" id="supplier_tin_group">
                                <div class="form-group">
                                    <label for="supplier_TIN">@lang('messages.NIF du Fournisseur')</label>
                                    <input type="text" class="form-control" id="supplier_TIN" name="supplier_TIN" placeholder="Entrer le NIF du Fournisseur" value="{{ old('supplier_TIN', $supplier->supplier_TIN) }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6" id="supplier_name_group">
                                <div class="form-group">
                                    <label for="supplier_name">@lang('messages.Nom du Fournisseur')<strong style="color: red;" id="supplier_name_required">*</strong></label>
                                    <input type="text" class="form-control" id="supplier_name" name="supplier_name" placeholder="Entrer le nom du Fournisseur" value="{{ old('supplier_name', $supplier->supplier_name) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="telephone">@lang('messages.Telephone du Fournisseur')</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" value="{{ old('telephone', $supplier->telephone) }}" placeholder="@lang('messages.enter_phone')">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supplier_address">@lang('messages.Adresse du Fournisseur')</label>
                                    <input type="text" class="form-control" id="supplier_address" name="supplier_address" value="{{ old('supplier_address', $supplier->supplier_address) }}" placeholder="Entrer l'adresse du Fournisseur">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mail">@lang('messages.E-mail du Fournisseur')</label>
                                    <input type="text" class="form-control" id="mail" name="mail" value="{{ old('mail', $supplier->mail) }}" placeholder="@lang('messages.enter_email')">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="vat_supplier_payer">@lang('messages.Fournisseur est assujetti a la TVA?')</label>
                                <div class="form-group">
                                    <label class="text">Non Assujetti
                                        <input type="checkbox" name="vat_supplier_payer" value="0" @if($supplier->vat_supplier_payer == '0') checked="checked" @endif class="form-control">
                                    </label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    <label class="text">Assujetti
                                        <input type="checkbox" name="vat_supplier_payer" value="1" @if($supplier->vat_supplier_payer == '1') checked="checked" @endif class="form-control">
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                @php
                                    $selectedAccount = $accounts->firstWhere('id', old('account_id', $supplier->account_id));
                                    $selectedAccountLabel = $selectedAccount ? $selectedAccount->account_number . ' - ' . $selectedAccount->account_name : '';
                                @endphp
                                <div class="form-group">
                                    <label for="account_search">@lang('messages.account')<strong style="color: red;">*</strong></label>
                                    <div class="ajax-ac-wrap">
                                        <input type="text" class="form-control" id="account_search" placeholder="@lang('messages.search_account_placeholder')" autocomplete="off" value="{{ old('account_search', $selectedAccountLabel) }}">
                                        <input type="hidden" name="account_id" id="account_id" required value="{{ old('account_id', $supplier->account_id) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" onclick="this.style.visibility='hidden';" ondblclick="this.style.visibility='hidden';" class="btn btn-primary mt-4 pr-4 pl-4">@lang('messages.update')</button>
                    </form>
                </div>
            </div>
        </div>
        <!-- data table end -->
    </div>
</div>
@endsection

@section('scripts')
<script type="text/javascript">
$(function() {
    var group_ = function(el, callback) {
        el.forEach(function(checkbox) { callback(checkbox); });
    };
    group_(document.getElementsByName('vat_supplier_payer'), function(item) {
        item.onclick = function(e) {
            group_(document.getElementsByName('vat_supplier_payer'), function(it) { it.checked = false; });
            e.target.checked = true;
        };
    });

    function syncSupplierFieldsByTpType() {
        var tp = $('#tp_type').val();
        var nif = $('#supplier_TIN')[0];
        var nifGroup = $('#supplier_tin_group');
        var supplierName = $('#supplier_name')[0];
        var supplierNameRequired = $('#supplier_name_required');
        if (tp === '2') {
            nif.setAttribute('required', 'required');
            nifGroup.show();
            supplierName.removeAttribute('required');
            supplierName.setAttribute('readonly', 'readonly');
            supplierNameRequired.hide();
        } else {
            nif.removeAttribute('required');
            nifGroup.hide();
            supplierName.setAttribute('required', 'required');
            supplierName.removeAttribute('readonly');
            supplierNameRequired.show();
        }
    }
    $('#tp_type').on('change', syncSupplierFieldsByTpType);
    syncSupplierFieldsByTpType();

    function escHtml(s) {
        return (s != null ? s : '').toString()
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    attachAjaxAutocomplete({
        $input: $('#account_search'),
        $hidden: $('#account_id'),
        url: '{{ route("admin.accounting-accounts.search") }}',
        minChars: 1,
        renderItem: function(item) {
            return '<div><strong>' + escHtml(item.text || '') + '</strong></div>';
        }
    });

    $('#supplier_form').on('submit', function(e) {
        if (!$('#account_id').val()) {
            e.preventDefault();
            alert({!! json_encode(__('messages.Please select an account from the suggestions list.')) !!});
            return false;
        }
    });
});
</script>
@endsection
