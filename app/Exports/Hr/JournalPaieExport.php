<?php

namespace App\Exports\Hr;

use Carbon\Carbon;
use App\Models\HrPaiement;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class JournalPaieExport implements FromCollection, WithMapping, WithHeadings
{
    protected $code;
    protected $companyId;

    public function __construct($code = null, $companyId = null)
    {
        $this->code = $code ?: request()->input('code');
        $this->companyId = $companyId ?: request()->input('company_id');
    }

    public function collection()
    {
        $query = HrPaiement::with(['employe.fonction', 'employe.grade'])
            ->where('code', $this->code)
            ->where('employe_id', '!=', '');

        if (!empty($this->companyId)) {
            $query->where('company_id', $this->companyId);
        }

        return $query->orderBy('id')->get();
    }

    public function map($data): array
    {
        $excel = \App\Support\PayrollCalculator::fromPaiement($data);
        $employe = $data->employe;

        return [
            $data->id,
            trim(($employe->firstname ?? '').' '.($employe->lastname ?? '')),
            (float)$data->nbre_jours_ouvrables,
            $excel['salaire_base'],
            $excel['allocation_familiale'],
            $excel['indemnite_logement'],
            $excel['indemnite_deplacement'],
            $excel['heures_suppl'],
            $excel['autres_indemnites'],
            $excel['prime_fonction'],
            $excel['rem_brut'],
            $excel['inss_pens'],
            $excel['inss_risque'],
            $excel['sante_qpp'],
            $excel['charge_employeur'],
            $excel['inss_trav'],
            $excel['sante_trav'],
            $excel['ire'],
            $excel['avance'],
            $excel['soins_medicaux'],
            $excel['autres_retenues'],
            $excel['tot_retenu'],
            $excel['net'],
        ];
    }

    public function headings(): array
    {
        return [
            'N°',
            'NOM et PRENOM',
            'JT',
            'SAL, BASE',
            'All fam',
            'Ind Log',
            'Ind Dépl',
            'Heure suppl',
            'AUT_IND',
            'Prim /Fonx°',
            'REM_BRUT',
            'INSS_PENS',
            'INSS_RSQ',
            'Santé QPP',
            'CHARGE_EMPL',
            'INSS_TRAV',
            'Santé_TRAV',
            'IRE Retenu',
            'AVANCE',
            'Soins méd',
            'Aut retenues',
            'TOT_RETENU',
            'NET A PAYER',
        ];
    }

    protected function formatAnciennete($dateEmbauche, $referenceDate = null): string
    {
        if (!$dateEmbauche) {
            return '';
        }

        $start = Carbon::parse($dateEmbauche)->startOfDay();
        $end = $referenceDate
            ? Carbon::parse($referenceDate)->startOfDay()
            : Carbon::now()->startOfDay();

        if ($end->lt($start)) {
            return '';
        }

        $years = $start->diffInYears($end);
        $months = $start->copy()->addYears($years)->diffInMonths($end);

        $parts = [];
        if ($years > 0) {
            $parts[] = $years.' an'.($years > 1 ? 's' : '');
        }
        if ($months > 0 || $years === 0) {
            $parts[] = $months.' mois';
        }

        return implode(' ', $parts);
    }
}
