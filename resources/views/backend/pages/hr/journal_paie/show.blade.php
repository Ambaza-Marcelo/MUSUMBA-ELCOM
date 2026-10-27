@extends('backend.layouts.master')

@section('title')
@lang('Details Journal de paie') - @lang('messages.admin_panel')
@endsection

@section('styles')
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.jqueryui.min.css">
    <style>
        .jp-toolbar .btn { margin-left: 6px; }
        #dataTable th, #dataTable td { white-space: nowrap; font-size: 12px; }
        .jp-meta { font-size: 13px; margin-bottom: 10px; }
        .jp-meta strong { margin-right: 4px; }
    </style>
@endsection

@section('admin-content')

@php
    $fmt = function ($n) {
        return number_format((float) $n, 0, ',', ' ');
    };
@endphp

<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left">
                    Journal de paie {{ $data->code ?? '' }}
                </h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.dashboard')</a></li>
                    <li><a href="{{ route('admin.hr-journal-paies.index') }}">@lang('messages.list')</a></li>
                    <li><span>{{ $data->code ?? '' }}</span></li>
                </ul>
            </div>
        </div>
        <div class="col-sm-6 clearfix">
            @include('backend.layouts.partials.logout')
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="main-content-inner">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div>
                            <h4 class="header-title mb-2">
                                {{ $data->title ?? 'Journal de paie' }}
                            </h4>
                            <div class="jp-meta">
                                <div><strong>Code :</strong> {{ $data->code ?? '' }}</div>
                                <div>
                                    <strong>Periode :</strong>
                                    DU {{ \Carbon\Carbon::parse($data->date_debut)->format('d/m/Y') }}
                                    AU {{ \Carbon\Carbon::parse($data->date_fin)->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>
                        <div class="jp-toolbar mb-2">
                            <a href="{{ route('admin.hr-journal-paies.export-to-excel', ['code' => $data->code, 'company_id' => $company_id]) }}"
                               class="btn btn-success btn-sm text-white"
                               title="Exporter en Excel">
                                <i class="fa fa-file-excel-o"></i> Excel
                            </a>
                            <a href="{{ route('admin.hr-journal-paies.export-to-pdf', ['code' => $data->code, 'company_id' => $company_id]) }}"
                               class="btn btn-info btn-sm text-white"
                               target="_blank"
                               title="Exporter en PDF">
                                <i class="fa fa-file-pdf-o"></i> PDF
                            </a>
                            <a href="{{ route('admin.hr-journal-paies.index') }}" class="btn btn-secondary btn-sm">
                                Retour
                            </a>
                        </div>
                    </div>

                    <div class="clearfix"></div>
                    <div class="data-tables table-responsive">
                        @include('backend.layouts.partials.messages')
                        <table id="dataTable" class="text-center table table-bordered">
                            <thead class="bg-light text-capitalize">
                                <tr>
                                    <th>N°</th>
                                    <th>NOM et PRENOM</th>
                                    <th>JT</th>
                                    <th>SAL, BASE</th>
                                    <th>All fam</th>
                                    <th>Ind Log</th>
                                    <th>Ind Dépl</th>
                                    <th>Heure suppl</th>
                                    <th>AUT_IND</th>
                                    <th>Prim /Fonx°</th>
                                    <th>REM_BRUT</th>
                                    <th>INSS_PENS</th>
                                    <th>INSS_RSQ</th>
                                    <th>Santé QPP</th>
                                    <th>CHARGE_EMPL</th>
                                    <th>INSS_TRAV</th>
                                    <th>Santé_TRAV</th>
                                    <th>IRE Retenu</th>
                                    <th>AVANCE</th>
                                    <th>Soins méd</th>
                                    <th>Aut retenues</th>
                                    <th>TOT_RETENU</th>
                                    <th>NET A PAYER</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                               @foreach ($journal_paies as $jp)
                               @php
                                    $paie = \App\Support\PayrollCalculator::fromPaiement($jp);
                               @endphp
                               <tr>
                                    <td>{{ $loop->index + 1 }}</td>
                                    <td>{{ strtoupper(optional($jp->employe)->firstname.' '.optional($jp->employe)->lastname) }}</td>
                                    <td>{{ $fmt($jp->nbre_jours_ouvrables) }}</td>
                                    <td>{{ $fmt($paie['salaire_base']) }}</td>
                                    <td>{{ $fmt($paie['allocation_familiale']) }}</td>
                                    <td>{{ $fmt($paie['indemnite_logement']) }}</td>
                                    <td>{{ $fmt($paie['indemnite_deplacement']) }}</td>
                                    <td>{{ $fmt($paie['heures_suppl']) }}</td>
                                    <td>{{ $fmt($paie['autres_indemnites']) }}</td>
                                    <td>{{ $fmt($paie['prime_fonction']) }}</td>
                                    <td><strong>{{ $fmt($paie['rem_brut']) }}</strong></td>
                                    <td>{{ $fmt($paie['inss_pens']) }}</td>
                                    <td>{{ $fmt($paie['inss_risque']) }}</td>
                                    <td>{{ $fmt($paie['sante_qpp']) }}</td>
                                    <td>{{ $fmt($paie['charge_employeur']) }}</td>
                                    <td>{{ $fmt($paie['inss_trav']) }}</td>
                                    <td>{{ $fmt($paie['sante_trav']) }}</td>
                                    <td>{{ $fmt($paie['ire']) }}</td>
                                    <td>{{ $fmt($paie['avance']) }}</td>
                                    <td>{{ $fmt($paie['soins_medicaux']) }}</td>
                                    <td>{{ $fmt($paie['autres_retenues']) }}</td>
                                    <td><strong>{{ $fmt($paie['tot_retenu']) }}</strong></td>
                                    <td><strong>{{ $fmt($paie['net']) }}</strong></td>
                                    <td>
                                        @if($jp->id)
                                            <a class="btn btn-info btn-sm text-white"
                                               href="{{ route('admin.hr-fiche-paie.print', $jp->id) }}"
                                               target="_blank"
                                               title="Bulletin de paie">
                                                <i class="fa fa-print"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                               @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
     <script src="https://cdn.datatables.net/1.10.19/js/jquery.dataTables.js"></script>
     <script src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
     <script src="https://cdn.datatables.net/1.10.18/js/dataTables.bootstrap4.min.js"></script>
     <script src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js"></script>
     <script src="https://cdn.datatables.net/responsive/2.2.3/js/responsive.bootstrap.min.js"></script>
     <script>
        if ($('#dataTable').length) {
            $('#dataTable').DataTable({
                responsive: true,
                scrollX: true,
                pageLength: 25
            });
        }
     </script>
@endsection
