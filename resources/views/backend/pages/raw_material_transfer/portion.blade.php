@extends('backend.layouts.master')

@section('title')
@lang('messages.transfer') - Portion - @lang('messages.admin_panel')
@endsection

@section('admin-content')
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">Portion</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.raw-material-transfers.index') }}">@lang('messages.list')</a></li>
                    <li><span>Portion</span></li>
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
                    <h4 class="header-title">Portion — {{ $transfer_no }}</h4>
                    @include('backend.layouts.partials.messages')

                    <div class="alert alert-info">
                        Fonctionnalité à venir. La portion n'est pas encore disponible.
                    </div>

                    <table class="table table-bordered">
                        <tr>
                            <th>@lang('messages.item')</th>
                            <th>@lang('messages.quantity')</th>
                            <th>@lang('messages.unit_price')</th>
                            <th>@lang('messages.Transfering Quantity')</th>
                        </tr>
                        @foreach($datas as $row)
                        <tr>
                            <td>
                                {{ optional($row->rawMaterial)->name }}/{{ optional(optional($row->rawMaterial)->rawMaterialMeasurement)->purchase_unit ?? 'N/A' }}
                            </td>
                            <td>{{ $row->quantity_requisitioned }}</td>
                            <td>{{ $row->price }}</td>
                            <td>{{ $row->quantity_transfered }}</td>
                        </tr>
                        @endforeach
                    </table>

                    <a href="{{ route('admin.raw-material-transfers.index') }}" class="btn btn-secondary">
                        @lang('messages.back')
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
