
@extends('backend.layouts.master')

@section('title')
@lang('messages.Creation du client') - @lang('messages.admin_panel')
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
                <h4 class="page-title pull-left">@lang('messages.Creation du client')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.clients.index') }}">@lang('messages.list')</a></li>
                    <li><span>@lang('messages.Creation du client')</span></li>
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
                    <h4 class="header-title">@lang('messages.Creation du Client')</h4>
                    @include('backend.layouts.partials.messages')

                    <form action="{{ route('admin.clients.store') }}" method="POST" id="client_form">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tp_type">@lang('messages.Type de contribuable')</label>
                                    <select name="tp_type" id="tp_type" class="form-control" required>
                                        <option value="">@lang('messages.merci de choisir')</option>
                                        <option value="1">@lang('messages.Personne Physique')</option>
                                        <option value="2">@lang('messages.société locale')</option>
                                        <option value="3">@lang('messages.société étrangère')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6" id="customer_tin_group">
                                <div class="form-group">
                                    <label for="customer_TIN">@lang('messages.NIF du Client')</label>
                                    <input type="text" class="form-control" id="customer_TIN" name="customer_TIN" placeholder="Entrer le NIF du Client">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6" id="customer_name_group">
                                <div class="form-group">
                                    <label for="customer_name">@lang('messages.Nom du Client')<strong style="color: red;" id="customer_name_required">*</strong></label>
                                    <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Entrer le nom du Client" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="telephone">@lang('messages.Telephone du Client')</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" placeholder="@lang('messages.enter_phone')">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_address">@lang('messages.Adresse du Client')</label>
                                    <input type="text" class="form-control" id="customer_address" name="customer_address" placeholder="Entrer l'adresse du Client">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="mail">@lang('messages.E-mail du Client')</label>
                                    <input type="text" class="form-control" id="mail" name="mail" placeholder="@lang('messages.enter_email')">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="vat_customer_payer">@lang('messages.Client est assujetti a la TVA?')</label>
                                <div class="form-group">
                                    <label class="text">Non Assujetti
                                        <input type="checkbox" name="vat_customer_payer" value="0" checked="checked" class="form-control">
                                    </label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    <label class="text">Assujetti
                                        <input type="checkbox" name="vat_customer_payer" value="1" class="form-control">
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="account_search">@lang('messages.account')<strong style="color: red;">*</strong></label>
                                    <div class="ajax-ac-wrap">
                                        <input type="text" class="form-control" id="account_search" placeholder="@lang('messages.search_account_placeholder')" autocomplete="off">
                                        <input type="hidden" name="account_id" id="account_id" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" onclick="this.style.visibility='hidden';" ondblclick="this.style.visibility='hidden';" class="btn btn-primary mt-4 pr-4 pl-4">@lang('messages.save')</button>
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
    group_(document.getElementsByName('vat_customer_payer'), function(item) {
        item.onclick = function(e) {
            group_(document.getElementsByName('vat_customer_payer'), function(it) { it.checked = false; });
            e.target.checked = true;
        };
    });

    function syncClientFieldsByTpType() {
        var tp = $('#tp_type').val();
        var nif = $('#customer_TIN')[0];
        var nifGroup = $('#customer_tin_group');
        var customerName = $('#customer_name')[0];
        var customerNameRequired = $('#customer_name_required');
        if (tp === '2') {
            nif.setAttribute('required', 'required');
            nifGroup.show();
            customerName.removeAttribute('required');
            customerName.setAttribute('readonly', 'readonly');
            customerNameRequired.hide();
        } else {
            nif.removeAttribute('required');
            nifGroup.hide();
            customerName.setAttribute('required', 'required');
            customerName.removeAttribute('readonly');
            customerNameRequired.show();
        }
    }
    $('#tp_type').on('change', syncClientFieldsByTpType);
    syncClientFieldsByTpType();

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

    $('#client_form').on('submit', function(e) {
        if (!$('#account_id').val()) {
            e.preventDefault();
            alert({!! json_encode(__('messages.Please select an account from the suggestions list.')) !!});
            return false;
        }
    });
});
</script>
@endsection
