<!doctype html>
<html>
<head>
    <link rel="stylesheet" href="{{ asset('backend/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/font-awesome.min.css') }}">
    <meta charset="utf-8">
    <style type="text/css">
        tr,th,td{
             border: 1px solid black;
             text-align: center;
             width: auto;
        }
        .brand-head th{
            background-color: #eba91b !important;
            color: #000 !important;
        }
        .brand-total td{
            background-color: #fff6df !important;
            color: #000 !important;
            font-weight: 700;
        }
        .brand-title{
            background-color: #eba91b !important;
            color: #000 !important;
        }

    </style>
</head>
<body>
<div class="container">
    <div>
        <div>
            <div>
                @php
                    $setting = $setting ?? \DB::table('settings')->orderBy('created_at', 'desc')->first();
                @endphp
                @include('backend.pages.document.partials.standard_document_header_contact', ['setting' => $setting])
                <div>
                                        <div style="float: right; border: 1px solid #000;background-color: #fff6df;width: 242px;padding: 10px;">
                        <small>
                           &nbsp;&nbsp; @if($data->employe_id) {!! QrCode::size(50)->backgroundColor(255,255,255)->generate('MATRICULE'.$data->employe->matricule_no.', Designed by AMBAZA' ) !!} @endif
                        </small>
                    </div>


@include('backend.pages.document.partials.standard_document_header_spacer')
<div>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th colspan="4" class="brand-title">BULLETIN DE PAIE DU {{ \Carbon\Carbon::parse($data->date_debut)->format('d/m/Y') }} AU {{ \Carbon\Carbon::parse($data->date_fin)->format('d/m/Y') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>NOM ET PRENOM</th>
                                    <td>@if($data->employe_id){{ $data->employe->firstname }} {{ $data->employe->lastname }} @endif</td>
                                    <th>MATRICULE</th>
                                    <td>@if($data->employe_id){{ $data->employe->matricule_no }} @endif</td>
                                </tr>
                                <tr>
                                    <th>NUMERO DE COMPTE</th>
                                    <td>@if($data->employe_id){{ $data->employe->numero_compte }} @endif</td>
                                    <th>CNI</th>
                                    <td>@if($data->employe_id){{ $data->employe->cni }} @endif</td>
                                </tr>
                                <tr>
                                    <th>FONCTION</th>
                                    <td>@if($data->employe_id){{ $data->employe->fonction->name }} @endif</td>
                                    <th>ANCIENNETE</th>
                                    @php
                                        $time1 = strftime(strtotime($data->employe->date_debut));
                                        $time2 = strftime(strtotime(\Carbon\Carbon::now()));


                                        $diff = $time2 - $time1;
 
                                        $jours = ($diff / 3600)/24; 
                                    @endphp
                                    <td>@if($jours <= 30)<strong>{{ number_format($jours,0)}}</strong> jour(s) @elseif($jours >= 365.25)<strong>{{ number_format($jours/365.25,0)}} an(s)</strong> @else {{ number_format(($jours / 30),0) }} mois @endif</td>
                                </tr>
                                <tr>
                                    <th>GENRE</th>
                                    <td>@if($data->employe->gender === '1') MASCULIN @else FEMININ @endif</td>
                                    <th>Nbre de personnes a charges</th>
                                    <td>@if($data->employe->statut_matrimonial === '1') {{ $data->employe->children_number + 1 }} @elseif($data->employe->statut_matrimonial === '2') {{ $data->employe->children_number }} @elseif($data->employe->statut_matrimonial === '3') {{ $data->employe->children_number }} @else 0 @endif</td>
                                </tr>
                                <tr>
                                    <th>DATE D'EMBAUCHE</th>
                                    <td>@if($data->employe_id){{ \Carbon\Carbon::parse($data->employe->date_debut)->format('d/m/Y') }} @endif</td>
                                    <th>DATE DE FIN</th>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table><br>
                        <table class="table table-bordered">
                            <thead>
                                <tr class="brand-head">
                                    <th>RUBRIQUES</th>
                                    <th>BASE</th>
                                    <th>TAUX(PART EMPLOYE)</th>
                                    <th>MONTANT(PART EMPLOYE)</th>
                                    <th>MONTANT(PART EMPLOYEUR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    @php $paie = \App\Support\PayrollCalculator::fromPaiement($data); @endphp
                                    <td>SALAIRE DE BASE</td>
                                    <td>{{ number_format($paie['salaire_base'],0,',',' ') }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>ALLOCATIONS FAMILIALES</td>
                                    <td></td>
                                    <td></td>
                                    <td>{{ number_format($paie['allocation_familiale'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>INDEMNITE DE DEPLACEMENT</td>
                                    <td>{{ number_format($paie['salaire_base'],0,',',' ') }}</td>
                                    <td>15%</td>
                                    <td>{{ number_format($paie['indemnite_deplacement'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>INDEMNITE DE LOGEMENT</td>
                                    <td>{{ number_format($paie['salaire_base'],0,',',' ') }}</td>
                                    <td>60%</td>
                                    <td>{{ number_format($paie['indemnite_logement'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>PRIME DE FONCTION</td>
                                    <td></td>
                                    <td></td>
                                    <td>{{ number_format($paie['prime_fonction'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr class="brand-total">
                                    <td>REM_BRUT</td>
                                    <td>{{ number_format($paie['salaire_base'],0,',',' ') }}</td>
                                    <td></td>
                                    <td>{{ number_format($paie['rem_brut'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>INSS_TRAV / INSS_PENS</td>
                                    <td>@if($paie['rem_brut'] < 450000){{ number_format($paie['rem_brut'],0,',',' ') }} @else 450 000 @endif</td>
                                    <td>4%</td>
                                    <td>{{ number_format($paie['inss_trav'],0,',',' ') }}</td>
                                    <td>{{ number_format($paie['inss_pens'],0,',',' ') }}</td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>ASSURANCE MALADIE</td>
                                    <td>400 000</td>
                                    <td>4%</td>
                                    <td>{{ number_format($paie['sante_trav'],0,',',' ') }}</td>
                                    <td>{{ number_format($paie['sante_qpp'],0,',',' ') }}</td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>SOINS MEDICAUX</td>
                                    <td></td>
                                    <td></td>
                                    <td>{{ number_format($paie['soins_medicaux'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>IRE Retenu</td>
                                    <td>{{ number_format($paie['base_ire'],0,',',' ') }}</td>
                                    <td>{{ $paie['taux_ire'] }}</td>
                                    <td>{{ number_format($paie['ire'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>AVANCE</td>
                                    <td>{{ number_format($paie['rem_brut'],0,',',' ') }}</td>
                                    <td></td>
                                    <td>{{ number_format($paie['avance'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>AUT RETENUES</td>
                                    <td>{{ number_format($paie['rem_brut'],0,',',' ') }}</td>
                                    <td></td>
                                    <td>{{ number_format($paie['autres_retenues'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr class="brand-total">
                                    <td>TOT_RETENU</td>
                                    <td>{{ number_format($paie['rem_brut'],0,',',' ') }}</td>
                                    <td></td>
                                    <td>{{ number_format($paie['tot_retenu'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tbody>
                                <tr>
                                    <td>NET A PAYER</td>
                                    <td>{{ number_format($paie['rem_brut'],0,',',' ') }}</td>
                                    <td></td>
                                    <td>{{ number_format($paie['net'],0,',',' ') }}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                        <br>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>REM_BRUT</th>
                                    <th>TOT_RETENU</th>
                                    <th>NET A PAYER</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>MENSUEL</td>
                                    <td>{{ number_format($paie['rem_brut'],0,',',' ') }}</td>
                                    <td>{{ number_format($paie['tot_retenu'],0,',',' ') }}</td>
                                    <td>{{ number_format($paie['net'],0,',',' ') }}</td>
                                </tr>
                            </tbody>
                        </table><br>
                    </div>
                    <div style="float: right;">
                        <a href="{{ route('admin.hr-fiche-paie.print',$data->id) }}" target="_blank" rel="noopener">
                            <img src="{{ asset('img/ISSh.gif') }}" width="60" title="Telecharger le bulletin de paie PDF">
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

