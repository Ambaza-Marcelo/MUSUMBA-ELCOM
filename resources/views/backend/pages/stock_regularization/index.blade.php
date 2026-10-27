@extends('backend.layouts.master')

@section('title')
Rectification des comptes d'ajustement - @lang('messages.admin_panel')
@endsection

@section('admin-content')

<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">Régularisation des sorties</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><span>Régularisation</span></li>
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
                    <ul class="nav nav-tabs mb-3">
                        @foreach ($allowed as $key => $label)
                            <li class="nav-item">
                                <a class="nav-link {{ $key === $moduleKey ? 'active' : '' }}" href="{{ route('admin.stockout-regularization.index', ['module' => $key]) }}">{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>

                    @php
                        $adjustmentAccount = $adjustmentAccountId ? \App\Models\Accounting\AccountAccount::find($adjustmentAccountId) : null;
                    @endphp
                    <h4 class="header-title">
                        {{ $module['label'] }} : sorties imputées sur le compte d'ajustement
                        @if ($adjustmentAccount)
                            ({{ $adjustmentAccount->account_number }} - {{ $adjustmentAccount->account_name }})
                        @endif
                    </h4>
                    <p class="text-muted mb-3">
                        La rectification modifie directement les lignes de journal erronées : le compte d'ajustement
                        est remplacé par le compte de la fiche de chaque article sorti (au prorata des valeurs CUMP de la sortie).
                        Montants, dates et n° de document d'origine sont conservés ; les soldes du grand livre sont mis à jour
                        et chaque substitution est tracée. Les sorties de type <strong>SAJ</strong> (ajustement réel)
                        ne sont rectifiées que par forçage ; les lignes d'une période clôturée sont refusées.
                    </p>

                    @include('backend.layouts.partials.messages')

                    @if (!$adjustmentAccountId)
                        <div class="alert alert-warning">Le compte d'ajustement n'est pas configuré dans les paramètres comptables.</div>
                    @elseif ($candidates->isEmpty())
                        <div class="alert alert-success">Aucune sortie ne reste imputée sur le compte d'ajustement.</div>
                    @else
                        <form action="{{ route('admin.stockout-regularization.all', $moduleKey) }}" method="POST" class="mb-3"
                              onsubmit="return confirm('Rectifier toutes les sorties détectées automatiquement (hors SAJ) ?');">
                            @csrf
                            <button type="submit" class="btn btn-primary">Rectifier tout (hors SAJ)</button>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="bg-light">
                                    <tr>
                                        <th>N° sortie</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th class="text-right">Montant sur ajustement</th>
                                        <th>Nouveau compte (fiche article)</th>
                                        <th style="width: 120px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($candidates as $candidate)
                                        <tr>
                                            <td><a href="{{ route($module['show_route'], $candidate['stockout_no']) }}">{{ $candidate['stockout_no'] }}</a></td>
                                            <td>{{ $candidate['date'] ? \Carbon\Carbon::parse($candidate['date'])->format('d/m/Y') : '' }}</td>
                                            <td>
                                                {{ $candidate['type'] }}
                                                @if ($candidate['requires_force'])
                                                    <span class="badge badge-warning">forçage requis</span>
                                                @endif
                                            </td>
                                            <td>{{ $candidate['description'] }}</td>
                                            <td class="text-right">
                                                {{ number_format($candidate['remaining'], 2, ',', ' ') }}
                                                <div class="small text-muted">{{ $candidate['line_count'] }} ligne(s)</div>
                                            </td>
                                            <td>
                                                @if ($candidate['error'])
                                                    <span class="text-danger">{{ $candidate['error'] }}</span>
                                                @else
                                                    @foreach ($candidate['allocation'] as $line)
                                                        <div>{{ $line['article'] }} → {{ $line['account'] }} : <strong>{{ number_format($line['amount'], 2, ',', ' ') }}</strong></div>
                                                    @endforeach
                                                @endif
                                            </td>
                                            <td>
                                                @if (!$candidate['error'])
                                                    <form action="{{ route('admin.stockout-regularization.rectify', [$moduleKey, $candidate['stockout_no']]) }}" method="POST"
                                                          onsubmit="return confirm('{{ $candidate['requires_force'] ? 'Forcer la rectification de cette sortie SAJ ?' : 'Rectifier cette sortie ?' }}');">
                                                        @csrf
                                                        @if ($candidate['requires_force'])
                                                            <input type="hidden" name="force" value="1">
                                                            <button type="submit" class="btn btn-warning btn-sm">Forcer</button>
                                                        @else
                                                            <button type="submit" class="btn btn-success btn-sm">Rectifier</button>
                                                        @endif
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
