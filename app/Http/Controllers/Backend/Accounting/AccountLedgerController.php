<?php

namespace App\Http\Controllers\Backend\Accounting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Exports\Accounting\BalanceExport;
use App\Support\BalanceReportBuilder;
use PDF;
use Excel;

class AccountLedgerController extends Controller
{
    public $user;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::guard('admin')->user();
            return $next($request);
        });
    }

    private function normalizeRequestIds($value): array
    {
        if (!is_array($value)) {
            $value = $value ? [$value] : [];
        }

        $normalized = [];
        foreach ($value as $id) {
            if ($id !== null && $id !== '') {
                $normalized[] = (int) $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array{parentAccountIds: array<int, int>, accountIds: array<int, int>, fiscalYear: ?string, dateDebut: ?string, dateFin: ?string, mode: string, consolidateByParent: bool}
     */
    private function resolveBalanceFilters(Request $request): array
    {
        $mode = (string) $request->input('ledger_account_mode', 'all');
        $parentAccountIds = $this->normalizeRequestIds($request->input('parent_account_ids', []));
        $accountIds = $this->normalizeRequestIds($request->input('account_ids', []));
        $consolidateByParent = false;

        if ($mode === 'all') {
            $parentAccountIds = [];
            $accountIds = [];
        } elseif ($mode === 'parent') {
            $accountIds = [];
            $consolidateByParent = true;
        } elseif ($mode === 'parent_children') {
            // Parents sélectionnés → détail de tous leurs comptes enfants
            $accountIds = [];
            $consolidateByParent = false;
        } elseif ($mode === 'child') {
            $parentAccountIds = [];
        }

        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');
        $fiscalYear = $request->input('fiscal_year');

        return [
            'parentAccountIds' => $parentAccountIds,
            'accountIds' => $accountIds,
            'fiscalYear' => $fiscalYear !== null && $fiscalYear !== '' ? (string) $fiscalYear : null,
            'dateDebut' => $dateDebut !== null && $dateDebut !== '' ? (string) $dateDebut : null,
            'dateFin' => $dateFin !== null && $dateFin !== '' ? (string) $dateFin : null,
            'mode' => $mode,
            'consolidateByParent' => $consolidateByParent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBalanceReport(Request $request): array
    {
        $filters = $this->resolveBalanceFilters($request);

        return BalanceReportBuilder::build(
            $filters['parentAccountIds'],
            $filters['fiscalYear'],
            $filters['accountIds'],
            $filters['dateDebut'],
            $filters['dateFin'],
            $filters['mode'] === 'parent'
                ? true
                : ($filters['mode'] === 'parent_children' ? false : null)
        );
    }

    /**
     * Affiche la balance (écran) avec filtres période / comptes.
     */
    public function balance(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view any balance !');
        }

        $filters = $this->resolveBalanceFilters($request);
        $report = $this->buildBalanceReport($request);

        $accountsData = $report['accountsData'];
        $grandTotals = $report['grandTotals'];
        $reportRow = $report['reportRow'];
        $fiscalYear = $report['fiscalYear'];
        $periodStartFormatted = $report['periodStartFormatted'];
        $periodEndFormatted = $report['periodEndFormatted'];
        $filterLabel = $report['filterLabel'];
        $dateDebut = $report['dateDebut'] ?? null;
        $dateFin = $report['dateFin'] ?? null;
        $ledgerAccountMode = $filters['mode'];
        $selectedParentAccountIds = $filters['parentAccountIds'];
        $selectedAccountIds = $filters['accountIds'];

        $reportGeneratedAt = now()->format('d/m/Y H:i');
        $companyName = config('app.name', 'Entreprise');

        return view('backend.pages.accounting.balance.index', compact(
            'accountsData',
            'grandTotals',
            'reportRow',
            'reportGeneratedAt',
            'companyName',
            'fiscalYear',
            'periodStartFormatted',
            'periodEndFormatted',
            'filterLabel',
            'dateDebut',
            'dateFin',
            'ledgerAccountMode',
            'selectedParentAccountIds',
            'selectedAccountIds'
        ));
    }

    /**
     * Export balance to PDF
     */
    public function exportPDF(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export balance !');
        }

        $report = $this->buildBalanceReport($request);
        $accountsData = $report['accountsData'];
        $grandTotals = $report['grandTotals'];
        $reportRow = $report['reportRow'];
        $fiscalYear = $report['fiscalYear'];
        $periodStartFormatted = $report['periodStartFormatted'];
        $periodEndFormatted = $report['periodEndFormatted'];
        $filterLabel = $report['filterLabel'];

        $reportGeneratedAt = now()->format('d/m/Y \à H:i:s');
        $companyName = config('app.name', 'Entreprise');
        $setting = DB::table('settings')->orderBy('created_at', 'desc')->first();
        $reportScope = BalanceReportBuilder::reportScopeLabel($filterLabel);

        $pdf = PDF::setOptions([
            'defaultFont' => 'dejavu sans',
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ])->loadView('backend.pages.accounting.document.balance', compact(
            'accountsData',
            'grandTotals',
            'reportRow',
            'reportGeneratedAt',
            'companyName',
            'fiscalYear',
            'periodStartFormatted',
            'periodEndFormatted',
            'filterLabel',
            'reportScope',
            'setting'
        ))->setPaper('a4', 'portrait');

        $fileName = 'BALANCE_COMPTES_' . $fiscalYear . '_' . now()->format('YmdHis') . '.pdf';

        return $pdf->download($fileName);
    }

    /**
     * Export balance to Excel
     */
    public function exportExcel(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export balance !');
        }

        $filters = $this->resolveBalanceFilters($request);
        $report = $this->buildBalanceReport($request);
        $fiscalYear = $report['fiscalYear'];
        $fileName = 'BALANCE_COMPTES_' . $fiscalYear . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new BalanceExport(
                $filters['parentAccountIds'],
                (string) $fiscalYear,
                $filters['accountIds'],
                $filters['dateDebut'],
                $filters['dateFin'],
                $filters['mode'] === 'parent'
                    ? true
                    : ($filters['mode'] === 'parent_children' ? false : null)
            ),
            $fileName
        );
    }
}
