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
     * @return array<string, mixed>
     */
    private function buildBalanceReport(Request $request): array
    {
        $fiscalYear = $request->input('fiscal_year');
        $mode = (string) $request->input('ledger_account_mode', 'all');

        $parentAccountIds = $this->normalizeRequestIds($request->input('parent_account_ids', []));
        $accountIds = $this->normalizeRequestIds($request->input('account_ids', []));

        if ($mode === 'all') {
            $parentAccountIds = [];
            $accountIds = [];
        }

        return BalanceReportBuilder::build($parentAccountIds, $fiscalYear, $accountIds);
    }

    /**
     * Display a listing of the resource.
     */
    public function balance(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view any balance !');
        }

        // Liste toujours affichée : année fiscale en cours, tous les comptes avec mouvements.
        $report = BalanceReportBuilder::build(null, null);

        $accountsData = $report['accountsData'];
        $grandTotals = $report['grandTotals'];
        $reportRow = $report['reportRow'];
        $fiscalYear = $report['fiscalYear'];
        $periodStartFormatted = $report['periodStartFormatted'];
        $periodEndFormatted = $report['periodEndFormatted'];
        $filterLabel = $report['filterLabel'];

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
            'filterLabel'
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

        $report = $this->buildBalanceReport($request);
        $fiscalYear = $report['fiscalYear'];
        $parentAccountIds = $this->normalizeRequestIds($request->input('parent_account_ids', []));
        $accountIds = $this->normalizeRequestIds($request->input('account_ids', []));
        if ((string) $request->input('ledger_account_mode', 'all') === 'all') {
            $parentAccountIds = [];
            $accountIds = [];
        }

        $filterSlug = str_replace([' ', ';', '(', ')'], ['_', '-', '', ''], $report['filterLabel']);

        return Excel::download(
            new BalanceExport($parentAccountIds, (string) $fiscalYear, $accountIds),
            'BALANCE_COMPTES_' . $filterSlug . '_' . $fiscalYear . '.xlsx'
        );
    }
}
