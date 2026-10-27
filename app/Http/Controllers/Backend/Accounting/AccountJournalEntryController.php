<?php

namespace App\Http\Controllers\Backend\Accounting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Accounting\AccountAccount;
use App\Models\Accounting\AccountParentAccount;
use App\Models\Accounting\AccountJournalEntry;
use App\Models\Accounting\AccountJournalEntryDetail;
use App\Models\Accounting\AccountLedger;
use App\Models\Accounting\Currency;
use App\Models\Accounting\AccountCostCenter;
use App\Http\Controllers\Backend\Concerns\GuardsAccountingPeriod;
use Validator;
use PDF;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Accounting\LedgerExport;
use App\Support\LedgerReportBuilder;

class AccountJournalEntryController extends Controller
{
    use GuardsAccountingPeriod;
    //
    public $user;

    /**
     * Parse optional piece / document filter (comma, semicolon or line breaks).
     *
     * @return array<int, string>
     */
    private static function parsePieceDocumentFilterTokens(?string $input): array
    {
        if ($input === null || trim($input) === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,;]+/u', $input, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($parts as $part) {
            $t = trim($part);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::guard('admin')->user();
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * - By default shows the 100 most recent records
     * - When a period (date_debut & date_fin) is provided, shows all data in that range
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.view')) {
            abort(403, 'Sorry !! You are Unauthorized to view any journal entry !');
        }

        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');

        $query = AccountJournalEntryDetail::with('account');

        if ($dateDebut && $dateFin) {
            $query->whereBetween('date', [$dateDebut, $dateFin]);
        } else {
            $query->orderBy('date', 'desc')->orderBy('created_at', 'desc')->orderBy('id', 'desc')->limit(100);
        }

        $datas = $query->get();
        $report = LedgerReportBuilder::buildRows($datas);

        return view('backend.pages.accounting.journal_entry.index', [
            'datas' => $datas,
            'ledgerRows' => $report['ledgerRows'],
            'grandTotalDebit' => $report['grandTotalDebit'],
            'grandTotalCredit' => $report['grandTotalCredit'],
            'grandBalanceDebit' => $report['grandBalanceDebit'],
            'grandBalanceCredit' => $report['grandBalanceCredit'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any journal entry !');
        }

        $currencies = Currency::orderBy('currency_name','asc')->get();
        $cost_centers = AccountCostCenter::orderBy('name','asc')->get();
        $accounts = AccountAccount::orderBy('account_number', 'asc')->get();

        return view('backend.pages.accounting.journal_entry.create', compact('currencies','cost_centers','accounts'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.create')) {
            abort(403, 'Sorry !! You are Unauthorized to create any journal entry !');
        }

        $rules = array(
                'date'  => 'required',
                'libelle.*'  => 'required',
                'document_no.*'  => 'required',
                //'cost_center_id'  => 'required',
                //'currency_id'  => 'required',
                'account_id.*' => 'required',
                'debit.*' => 'required',
                'credit.*' => 'required',
            );

            $error = Validator::make($request->all(),$rules);

            if($error->fails()){
                return response()->json([
                    'error' => $error->errors()->all(),
                ]);
            }

            if ($this->abortIfAccountingPeriodLocked($request->date)) {
                return redirect()->back()->withInput();
            }

            try {DB::beginTransaction();

            $date = $request->date ? \Carbon\Carbon::parse($request->date) : \Carbon\Carbon::now();
            $libelle = $request->libelle;
            $document_no = $request->document_no;
            $currency_id = $request->currency_id;
            $created_by = $this->user->name;
            $active = 1;
            $account_id =$request->account_id;
            $debit = $request->debit;
            $credit = $request->credit;


            //create journal_entry
            $journal_entry = new AccountJournalEntry();
            $journal_entry->date = $date;
            $journal_entry->type = $request->type ?? 'NORMAL';
            //$journal_entry->currency_id = $currency_id;
            $journal_entry->active = $active;
            $journal_entry->save();
            $insert_data = [];
            $ledgerAdjustments = [];

            //insert details of journal_entry No.
            for( $count = 0; $count < count($account_id); $count++ ){

                $current_account_id = $account_id[$count];
                $current_debit = (float) ($debit[$count] ?? 0);
                $current_credit = (float) ($credit[$count] ?? 0);

                $data = array(
                    'date' => $date,
                    'libelle' => $libelle[$count],
                    'document_no' => $document_no[$count],
                    //'currency_id' => $currency_id,
                    'active' => $active,
                    'account_id' => $current_account_id,
                    'debit' => $current_debit,
                    'credit' => $current_credit,
                    'created_at' => \Carbon\Carbon::now()
                );
                $insert_data[] = $data;

                if (!isset($ledgerAdjustments[$current_account_id])) {
                    $ledgerAdjustments[$current_account_id] = [
                        'debit' => 0,
                        'credit' => 0,
                    ];
                }

                $ledgerAdjustments[$current_account_id]['debit'] += $current_debit;
                $ledgerAdjustments[$current_account_id]['credit'] += $current_credit;
            }

            AccountJournalEntryDetail::insert($insert_data);

            foreach ($ledgerAdjustments as $ledgerAccountId => $amounts) {
                $accountLedger = AccountLedger::firstOrNew(['account_id' => $ledgerAccountId]);

                $existingDebit = (float) ($accountLedger->debit ?? 0);
                $existingCredit = (float) ($accountLedger->credit ?? 0);

                $accountLedger->date = $date;
                $accountLedger->debit = $existingDebit + $amounts['debit'];
                $accountLedger->credit = $existingCredit + $amounts['credit'];
                $accountLedger->balance = $accountLedger->debit - $accountLedger->credit;
                $accountLedger->save();
            }

        DB::commit();
            session()->flash('success', 'cash out flow has been created !!');
            return redirect()->route('admin.journal-entry.index');
        } catch (\Exception $e) {
            // An error occured; cancel the transaction...

            DB::rollback();

            // and throw the error again.

            throw $e;
        }

    }

    /**
     * Get opening balance data for previous fiscal year
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getOpeningBalance(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.create')) {
            abort(403, 'Sorry !! You are Unauthorized to view opening balance !');
        }

        $currentYear = Carbon::now()->year;
        $previousYear = (string)($currentYear - 1);

        // Get all accounts with their opening balances from previous year
        $openingBalances = AccountLedger::where('fiscal_year', $previousYear)
            ->with('account')
            ->get()
            ->filter(function($ledger) {
                return $ledger->account !== null; // Filter out ledgers without accounts
            })
            ->groupBy('account_id')
            ->map(function ($ledgers) {
                // Get the latest ledger entry for each account (final balance)
                $latestLedger = $ledgers->sortByDesc('date')->first();
                if (!$latestLedger || !$latestLedger->account) {
                    return null;
                }
                return [
                    'account_id' => $latestLedger->account_id,
                    'debit' => (float)($latestLedger->debit ?? 0),
                    'credit' => (float)($latestLedger->credit ?? 0),
                    'balance' => (float)($latestLedger->balance ?? 0),
                    'account_number' => $latestLedger->account->account_number ?? '',
                    'account_name' => $latestLedger->account->account_name ?? '',
                ];
            })
            ->filter()
            ->values();

        // Get all accounts (in case no opening balance data exists), ordered by account_number
        $allAccounts = AccountAccount::select('id', 'account_number', 'account_name')->orderBy('account_number', 'asc')->get();

        // For OPENING type: exclude accounts whose account_number begins with 6 or 7
        $excludeOpening = function ($accountNumber) {
            $num = (string) ($accountNumber ?? '');
            if ($num === '') return true;
            $first = substr($num, 0, 1);
            return $first !== '6' && $first !== '7';
        };

        // If we have opening balances, use them; otherwise use all accounts with empty balances
        if ($openingBalances->count() > 0) {
            $accountsData = $openingBalances
                ->filter(function ($balance) use ($excludeOpening) {
                    return $excludeOpening($balance['account_number'] ?? '');
                })
                ->map(function ($balance) {
                    // Determine debit or credit based on balance
                    $debit = 0;
                    $credit = 0;
                    if ($balance['balance'] > 0) {
                        $debit = abs($balance['balance']);
                    } elseif ($balance['balance'] < 0) {
                        $credit = abs($balance['balance']);
                    }

                    return [
                        'account_id' => $balance['account_id'],
                        'account_number' => $balance['account_number'],
                        'account_name' => $balance['account_name'],
                        'account_display' => $balance['account_number'] . '-' . $balance['account_name'],
                        'debit' => $debit,
                        'credit' => $credit,
                    ];
                })
                ->sortBy('account_number')
                ->values();
        } else {
            // No opening balance data - return all accounts with empty values (excluding 6 and 7)
            $accountsData = $allAccounts
                ->filter(function ($account) use ($excludeOpening) {
                    return $excludeOpening($account->account_number ?? '');
                })
                ->map(function ($account) {
                    return [
                        'account_id' => $account->id,
                        'account_number' => $account->account_number,
                        'account_name' => $account->account_name,
                        'account_display' => $account->account_number . '-' . $account->account_name,
                        'debit' => 0,
                        'credit' => 0,
                    ];
                })
                ->values();
        }

        return response()->json([
            'success' => true,
            'data' => $accountsData,
            'previous_year' => $previousYear,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to edit any journal entry !');
        }

        // Get journal entry details by date (grouped entries)
        $journalEntryDetail = AccountJournalEntryDetail::findOrFail($id);
        $date = $journalEntryDetail->date;
        
        // Get all details for this date
        $details = AccountJournalEntryDetail::where('date', $date)
            ->with('account')
            ->get();
        
        // Get the journal entry if exists
        $journalEntry = AccountJournalEntry::where('date', $date)->first();
        
        $currencies = Currency::orderBy('currency_name','asc')->get();
        $cost_centers = AccountCostCenter::orderBy('name','asc')->get();
        $accounts = AccountAccount::orderBy('account_number', 'asc')->get();

        return view('backend.pages.accounting.journal_entry.edit', compact('details', 'journalEntry', 'currencies', 'cost_centers', 'accounts', 'date'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.edit')) {
            abort(403, 'Sorry !! You are Unauthorized to update any journal entry !');
        }

        $rules = array(
            'date'  => 'required',
            'libelle.*'  => 'required',
            'document_no.*'  => 'required',
            'account_id.*' => 'required',
            'debit.*' => 'required',
            'credit.*' => 'required',
        );

        $error = Validator::make($request->all(),$rules);

        if($error->fails()){
            return response()->json([
                'error' => $error->errors()->all(),
            ]);
        }

        if ($this->abortIfAccountingPeriodLocked($request->date)) {
            return redirect()->back()->withInput();
        }

        try {
            DB::beginTransaction();

            $journalEntryDetail = AccountJournalEntryDetail::findOrFail($id);
            $oldDate = $journalEntryDetail->date;
            
            $date = $request->date ? \Carbon\Carbon::parse($request->date) : \Carbon\Carbon::now();
            $libelle = $request->libelle;
            $document_no = $request->document_no;
            $account_id = $request->account_id;
            $debit = $request->debit;
            $credit = $request->credit;

            // Delete old details for this date
            $oldDetails = AccountJournalEntryDetail::where('date', $oldDate)->get();
            $oldLedgerAdjustments = [];
            
            foreach ($oldDetails as $oldDetail) {
                $oldAccountId = $oldDetail->account_id;
                $oldDebit = (float)($oldDetail->debit ?? 0);
                $oldCredit = (float)($oldDetail->credit ?? 0);
                
                if (!isset($oldLedgerAdjustments[$oldAccountId])) {
                    $oldLedgerAdjustments[$oldAccountId] = [
                        'debit' => 0,
                        'credit' => 0,
                    ];
                }
                
                $oldLedgerAdjustments[$oldAccountId]['debit'] += $oldDebit;
                $oldLedgerAdjustments[$oldAccountId]['credit'] += $oldCredit;
            }
            
            // Reverse old ledger adjustments
            foreach ($oldLedgerAdjustments as $ledgerAccountId => $amounts) {
                $accountLedger = AccountLedger::where('account_id', $ledgerAccountId)->first();
                if ($accountLedger) {
                    $existingDebit = (float) ($accountLedger->debit ?? 0);
                    $existingCredit = (float) ($accountLedger->credit ?? 0);
                    
                    $accountLedger->debit = max(0, $existingDebit - $amounts['debit']);
                    $accountLedger->credit = max(0, $existingCredit - $amounts['credit']);
                    $accountLedger->balance = $accountLedger->debit - $accountLedger->credit;
                    $accountLedger->save();
                }
            }
            
            // Delete old details
            AccountJournalEntryDetail::where('date', $oldDate)->delete();
            
            // Delete old journal entry if exists
            AccountJournalEntry::where('date', $oldDate)->delete();

            // Create new journal entry
            $journal_entry = new AccountJournalEntry();
            $journal_entry->date = $date;
            $journal_entry->type = $request->type ?? 'NORMAL';
            $journal_entry->active = 1;
            $journal_entry->save();
            
            $insert_data = [];
            $ledgerAdjustments = [];

            // Insert new details
            for($count = 0; $count < count($account_id); $count++){
                $current_account_id = $account_id[$count];
                $current_debit = (float) ($debit[$count] ?? 0);
                $current_credit = (float) ($credit[$count] ?? 0);

                $data = array(
                    'date' => $date,
                    'libelle' => $libelle[$count],
                    'document_no' => $document_no[$count],
                    'active' => 1,
                    'account_id' => $current_account_id,
                    'debit' => $current_debit,
                    'credit' => $current_credit,
                    'created_at' => \Carbon\Carbon::now()
                );
                $insert_data[] = $data;

                if (!isset($ledgerAdjustments[$current_account_id])) {
                    $ledgerAdjustments[$current_account_id] = [
                        'debit' => 0,
                        'credit' => 0,
                    ];
                }

                $ledgerAdjustments[$current_account_id]['debit'] += $current_debit;
                $ledgerAdjustments[$current_account_id]['credit'] += $current_credit;
            }

            AccountJournalEntryDetail::insert($insert_data);

            // Update ledger with new adjustments
            foreach ($ledgerAdjustments as $ledgerAccountId => $amounts) {
                $accountLedger = AccountLedger::firstOrNew(['account_id' => $ledgerAccountId]);

                $existingDebit = (float) ($accountLedger->debit ?? 0);
                $existingCredit = (float) ($accountLedger->credit ?? 0);

                $accountLedger->date = $date;
                $accountLedger->debit = $existingDebit + $amounts['debit'];
                $accountLedger->credit = $existingCredit + $amounts['credit'];
                $accountLedger->balance = $accountLedger->debit - $accountLedger->credit;
                $accountLedger->save();
            }

            DB::commit();
            session()->flash('success', 'Journal entry has been updated !!');
            return redirect()->route('admin.journal-entry.index');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Search parent and child accounts for ledger export autocomplete.
     */
    public function searchAccounts(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        $type = (string) $request->get('type', 'parent');
        $results = [];

        // parent_children = sélection de parents pour en exporter les enfants
        if ($type === 'parent' || $type === 'parent_children') {
            $parentQuery = AccountParentAccount::query();
            if ($term !== '') {
                $parentQuery->where(function ($q) use ($term) {
                    $q->where('account_name', 'LIKE', '%' . $term . '%')
                        ->orWhere('account_number', 'LIKE', '%' . $term . '%');
                });
            }

            foreach ($parentQuery->orderBy('account_number')->limit(50)->get() as $parent) {
                $childCount = AccountAccount::where('acc_parent_id', $parent->id)->count();
                $results[] = [
                    'id' => 'parent:' . $parent->id,
                    'type' => 'parent',
                    'real_id' => (int) $parent->id,
                    'text' => '[Parent] ' . $parent->account_number . ' - ' . $parent->account_name
                        . ' (' . $childCount . ' ' . __('messages.ledger_children_short') . ')',
                    'sort_key' => (string) $parent->account_number,
                ];
            }
        }

        if ($type === 'child') {
            $childQuery = AccountAccount::with('accParent');
            if ($term !== '') {
                $childQuery->where(function ($q) use ($term) {
                    $q->where('account_name', 'LIKE', '%' . $term . '%')
                        ->orWhere('account_number', 'LIKE', '%' . $term . '%');
                });
            }

            foreach ($childQuery->orderBy('account_number')->limit(50)->get() as $account) {
                $parent = $account->accParent;
                $parentSuffix = $parent
                    ? ' <- ' . trim($parent->account_number . ' - ' . $parent->account_name)
                    : '';

                $results[] = [
                    'id' => 'child:' . $account->id,
                    'type' => 'child',
                    'real_id' => (int) $account->id,
                    'text' => $account->account_number . ' - ' . $account->account_name . $parentSuffix,
                    'sort_key' => (string) $account->account_number,
                ];
            }
        }

        usort($results, fn (array $a, array $b) => strcmp($a['sort_key'], $b['sort_key']));

        return response()->json([
            'results' => array_map(static function (array $row) {
                return [
                    'id' => $row['id'],
                    'type' => $row['type'],
                    'real_id' => $row['real_id'],
                    'text' => $row['text'],
                ];
            }, $results),
        ]);
    }

    /**
     * Print general ledger for a specific period and account
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function print(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.view')) {
            abort(403, 'Sorry !! You are Unauthorized to print general ledger !');
        }
        $date_debut = $request->input('date_debut');
        $date_fin = $request->input('date_fin');
        $account_id = $request->input('account_id');
        $account_ids = $request->input('account_ids', []);
        $parent_account_ids = $request->input('parent_account_ids', []);

        if (!is_array($account_ids)) {
            $account_ids = $account_ids ? [$account_ids] : [];
        }
        if (!is_array($parent_account_ids)) {
            $parent_account_ids = $parent_account_ids ? [$parent_account_ids] : [];
        }
        if (empty($account_ids) && $account_id) {
            $account_ids = [$account_id];
        }

        $query = AccountJournalEntryDetail::with('account')
            ->whereBetween('date', [$date_debut, $date_fin]);

        LedgerReportBuilder::applyAccountFilter($query, $account_ids, $parent_account_ids);

        $pieceTokens = self::parsePieceDocumentFilterTokens($request->input('piece_document_filter'));
        if (!empty($pieceTokens)) {
            $query->wherePieceOrDocumentIn($pieceTokens);
        }

        $datas = $query->orderBy('date')->orderBy('created_at')->orderBy('id')->get();

        $sectionData = LedgerReportBuilder::buildAccountSections($datas);
        $pages = LedgerReportBuilder::buildPdfPages($sectionData['sections']);

        if (empty($pages)) {
            $pages = [[
                'blocks' => [],
                'carry_forward' => [
                    'debit' => 0,
                    'credit' => 0,
                    'balance_debit' => 0,
                    'balance_credit' => 0,
                ],
            ]];
        }

        $periodStartFormatted = $date_debut ? \Carbon\Carbon::parse($date_debut)->format('d/m/Y') : '--/--/----';
        $periodEndFormatted = $date_fin ? \Carbon\Carbon::parse($date_fin)->format('d/m/Y') : '--/--/----';

        $accountLabel = LedgerReportBuilder::buildFilterLabel($account_ids, $parent_account_ids);

        $setting = DB::table('settings')->orderBy('created_at','desc')->first();
        $printedAt = now()->format('d/m/Y H:i');

        $pdf = PDF::loadView('backend.pages.accounting.journal_entry.print', compact(
            'pages',
            'periodStartFormatted',
            'periodEndFormatted',
            'accountLabel',
            'setting',
            'printedAt'
        ))->setPaper('a4', 'portrait');

        return $pdf->download(self::ledgerFileName($accountLabel, $date_debut, $date_fin, 'pdf'));
    }

    /**
     * Export general ledger to Excel for a specific period and (optionally) selected accounts
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportToExcel(Request $request)
    {
        if (is_null($this->user) || !$this->user->can('account_journal_entry.view')) {
            abort(403, 'Sorry !! You are Unauthorized to export general ledger !');
        }

        $date_debut = $request->input('date_debut');
        $date_fin = $request->input('date_fin');
        $account_id = $request->input('account_id');
        $account_ids = $request->input('account_ids', []);
        $parent_account_ids = $request->input('parent_account_ids', []);

        if (!is_array($account_ids)) {
            $account_ids = $account_ids ? [$account_ids] : [];
        }
        if (!is_array($parent_account_ids)) {
            $parent_account_ids = $parent_account_ids ? [$parent_account_ids] : [];
        }
        if (empty($account_ids) && $account_id) {
            $account_ids = [$account_id];
        }

        $filterLabel = LedgerReportBuilder::buildFilterLabel($account_ids, $parent_account_ids);

        $normalizedAccountIds = !empty($account_ids) ? $account_ids : null;
        $normalizedParentAccountIds = !empty($parent_account_ids) ? $parent_account_ids : null;
        $pieceTokens = self::parsePieceDocumentFilterTokens($request->input('piece_document_filter'));

        return Excel::download(
            new LedgerExport($date_debut, $date_fin, $normalizedAccountIds, $pieceTokens, $normalizedParentAccountIds),
            self::ledgerFileName($filterLabel, $date_debut, $date_fin, 'xlsx')
        );
    }

    /**
     * Nom de fichier sûr pour le téléchargement : ASCII uniquement, sans "/" ni "\".
     */
    private static function ledgerFileName(?string $label, $dateDebut, $dateFin, string $extension): string
    {
        $period = ($dateDebut ? Carbon::parse($dateDebut)->format('d_m_Y') : 'debut')
            . '_' . ($dateFin ? Carbon::parse($dateFin)->format('d_m_Y') : 'fin');

        $safeLabel = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', \Illuminate\Support\Str::ascii((string) $label)), '_');
        $safeLabel = substr($safeLabel, 0, 120);

        return 'GENERAL_LEDGER_' . ($safeLabel !== '' ? $safeLabel . '_' : '') . $period . '.' . $extension;
    }

}
