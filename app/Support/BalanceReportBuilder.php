<?php

namespace App\Support;

use App\Models\Accounting\AccountAccount;
use App\Models\Accounting\AccountJournalEntryDetail;
use App\Models\Accounting\AccountParentAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BalanceReportBuilder
{
    /**
     * @return array{year: int, date_debut: string, date_fin: string, period_start_formatted: string, period_end_formatted: string}
     */
    public static function resolveFiscalYear(?string $fiscalYear): array
    {
        $year = (int) ($fiscalYear ?: Carbon::now()->format('Y'));
        if ($year < 1900 || $year > 9999) {
            $year = (int) Carbon::now()->format('Y');
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();

        return [
            'year' => $year,
            'date_debut' => $start->format('Y-m-d H:i:s'),
            'date_fin' => $end->format('Y-m-d H:i:s'),
            'period_start_formatted' => $start->format('d/m/y'),
            'period_end_formatted' => $end->format('d/m/y'),
        ];
    }

    /**
     * Résout la période de tirage : dates personnalisées prioritaires, sinon année fiscale.
     *
     * @return array{year: int, date_debut: string, date_fin: string, period_start_formatted: string, period_end_formatted: string}
     */
    public static function resolvePeriod(?string $fiscalYear = null, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut !== null ? trim($dateDebut) : '';
        $dateFin = $dateFin !== null ? trim($dateFin) : '';

        if ($dateDebut !== '' && $dateFin !== '') {
            try {
                $start = Carbon::parse($dateDebut)->startOfDay();
                $end = Carbon::parse($dateFin)->endOfDay();
            } catch (\Exception $e) {
                return self::resolveFiscalYear($fiscalYear);
            }

            if ($end->lt($start)) {
                $tmp = $start->copy();
                $start = $end->copy()->startOfDay();
                $end = $tmp->copy()->endOfDay();
            }

            return [
                'year' => (int) $start->format('Y'),
                'date_debut' => $start->format('Y-m-d H:i:s'),
                'date_fin' => $end->format('Y-m-d H:i:s'),
                'period_start_formatted' => $start->format('d/m/y'),
                'period_end_formatted' => $end->format('d/m/y'),
            ];
        }

        return self::resolveFiscalYear($fiscalYear);
    }

    /**
     * @param  array<int|string>|null  $parentAccountIds
     * @param  array<int|string>|null  $accountIds
     * @return array<int, int>|null  null = tous les comptes
     */
    public static function resolveFilteredAccountIds(?array $parentAccountIds, ?array $accountIds): ?array
    {
        $parentAccountIds = self::normalizeIds($parentAccountIds);
        $accountIds = self::normalizeIds($accountIds);

        if (empty($parentAccountIds) && empty($accountIds)) {
            return null;
        }

        $ids = $accountIds;
        if (!empty($parentAccountIds)) {
            $childIds = AccountAccount::whereIn('acc_parent_id', $parentAccountIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = array_merge($ids, $childIds);
        }

        $ids = array_values(array_unique($ids));

        return empty($ids) ? [] : $ids;
    }

    /**
     * @param  array<int|string>|null  $parentAccountIds
     * @return array<int, int>|null  null = tous les comptes
     * @deprecated Use resolveFilteredAccountIds()
     */
    public static function resolveChildAccountIds(?array $parentAccountIds): ?array
    {
        return self::resolveFilteredAccountIds($parentAccountIds, null);
    }

    /**
     * @param  array<int|string>|null  $parentAccountIds
     * @param  array<int|string>|null  $accountIds
     */
    public static function buildFilterLabel(?array $parentAccountIds, ?array $accountIds = null, bool $consolidatedByParent = false): string
    {
        $parentAccountIds = self::normalizeIds($parentAccountIds);
        $accountIds = self::normalizeIds($accountIds);
        $parts = [];

        if (!empty($parentAccountIds)) {
            $parts = array_merge(
                $parts,
                AccountParentAccount::whereIn('id', $parentAccountIds)
                    ->orderBy('account_number')
                    ->pluck('account_number')
                    ->map(fn ($number) => trim((string) $number))
                    ->filter()
                    ->values()
                    ->all()
            );
        }

        if (!empty($accountIds)) {
            $parts = array_merge(
                $parts,
                AccountAccount::whereIn('id', $accountIds)
                    ->orderBy('account_number')
                    ->pluck('account_number')
                    ->map(fn ($number) => trim((string) $number))
                    ->filter()
                    ->values()
                    ->all()
            );
        }

        if (empty($parts)) {
            return __('messages.ledger_all_accounts');
        }

        $joined = implode(', ', $parts);

        // Parents sélectionnés mais détail enfants (pas consolidé)
        if (!empty($parentAccountIds) && empty($accountIds) && !$consolidatedByParent) {
            return __('messages.ledger_children_of_parents_label') . ': ' . $joined;
        }

        return $joined;
    }

    /**
     * Agrège débit/crédit depuis AccountJournalEntryDetail sur la période,
     * filtrés par compte(s) parent(s) et/ou enfant(s).
     *
     * Mode parent consolidé (parent_account_ids, sans account_ids, consolidate=true) :
     * une ligne consolidée par compte parent.
     *
     * Mode enfants des parents (parent_account_ids, consolidate=false) :
     * une ligne par compte enfant rattaché aux parents choisis.
     *
     * @param  array<int|string>|null  $parentAccountIds
     * @param  array<int|string>|null  $accountIds
     * @return array{
     *     accountsData: \Illuminate\Support\Collection,
     *     grandTotals: array<string, float>,
     *     reportRow: array<string, float>,
     *     fiscalYear: int,
     *     periodStartFormatted: string,
     *     periodEndFormatted: string,
     *     filterLabel: string,
     *     dateDebut: string,
     *     dateFin: string,
     *     consolidatedByParent: bool
     * }
     */
    public static function build(
        ?array $parentAccountIds = null,
        ?string $fiscalYear = null,
        ?array $accountIds = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?bool $consolidateByParent = null
    ): array {
        $period = self::resolvePeriod($fiscalYear, $dateDebut, $dateFin);
        $parentAccountIds = self::normalizeIds($parentAccountIds);
        $accountIds = self::normalizeIds($accountIds);
        $explicitConsolidate = $consolidateByParent;

        if ($consolidateByParent === null) {
            $consolidateByParent = !empty($parentAccountIds) && empty($accountIds);
        } else {
            $consolidateByParent = (bool) $consolidateByParent
                && !empty($parentAccountIds)
                && empty($accountIds);
        }

        // Mode "enfants des parents" sans parent choisi → aucune ligne (pas toute la balance)
        if ($explicitConsolidate === false && empty($parentAccountIds) && empty($accountIds)) {
            $filteredAccountIds = [];
        } else {
            $filteredAccountIds = self::resolveFilteredAccountIds($parentAccountIds, $accountIds);
        }

        $query = AccountJournalEntryDetail::with(['account.accParent'])
            ->whereBetween('date', [$period['date_debut'], $period['date_fin']]);

        if ($filteredAccountIds !== null) {
            if (empty($filteredAccountIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('account_id', $filteredAccountIds);
            }
        }

        $entries = $query->get();

        if ($consolidateByParent) {
            $accountsData = self::buildConsolidatedParentRows($entries, $parentAccountIds);
        } else {
            $accountsData = self::buildChildAccountRows($entries);
        }

        $grandTotals = [
            'movement_debit' => (float) $accountsData->sum('movement_debit'),
            'movement_credit' => (float) $accountsData->sum('movement_credit'),
            'balance_debit' => (float) $accountsData->sum('balance_debit'),
            'balance_credit' => (float) $accountsData->sum('balance_credit'),
        ];

        $reportRow = [
            'movement_debit' => 0.0,
            'movement_credit' => 0.0,
            'balance_debit' => 0.0,
            'balance_credit' => 0.0,
        ];

        return [
            'accountsData' => $accountsData,
            'grandTotals' => $grandTotals,
            'reportRow' => $reportRow,
            'fiscalYear' => $period['year'],
            'periodStartFormatted' => $period['period_start_formatted'],
            'periodEndFormatted' => $period['period_end_formatted'],
            'filterLabel' => self::buildFilterLabel($parentAccountIds, $accountIds, $consolidateByParent),
            'dateDebut' => Carbon::parse($period['date_debut'])->format('Y-m-d'),
            'dateFin' => Carbon::parse($period['date_fin'])->format('Y-m-d'),
            'consolidatedByParent' => $consolidateByParent,
        ];
    }

    /**
     * Une ligne par compte enfant (détail).
     */
    private static function buildChildAccountRows(Collection $entries): Collection
    {
        return $entries
            ->groupBy('account_id')
            ->map(function (Collection $rows) {
                $account = optional($rows->first())->account;

                $accountNumber = trim(optional($account)->account_number ?? '');
                $accountName = trim(optional($account)->account_name ?? '');

                if ($accountNumber === '') {
                    $accountNumber = __('messages.account_undefined');
                }

                return self::makeBalanceRow($accountNumber, $accountName, $rows);
            })
            ->filter(fn (array $row) => self::hasAmounts($row))
            ->sortBy(fn (array $row) => sprintf('%s|%s', $row['account_number'], $row['account_name']))
            ->values();
    }

    /**
     * Une ligne consolidée par compte parent (somme de tous les enfants).
     *
     * @param  array<int, int>  $parentAccountIds
     */
    private static function buildConsolidatedParentRows(Collection $entries, array $parentAccountIds): Collection
    {
        $parents = AccountParentAccount::whereIn('id', $parentAccountIds)
            ->orderBy('account_number')
            ->get()
            ->keyBy('id');

        $grouped = $entries->groupBy(function ($entry) {
            return (int) (optional($entry->account)->acc_parent_id ?? 0);
        });

        $rows = collect();

        foreach ($parentAccountIds as $parentId) {
            $parent = $parents->get($parentId);
            if (!$parent) {
                continue;
            }

            $parentEntries = $grouped->get($parentId, collect());
            $row = self::makeBalanceRow(
                trim((string) $parent->account_number),
                trim((string) $parent->account_name),
                $parentEntries
            );

            if (self::hasAmounts($row)) {
                $rows->push($row);
            }
        }

        return $rows
            ->sortBy(fn (array $row) => sprintf('%s|%s', $row['account_number'], $row['account_name']))
            ->values();
    }

    private static function makeBalanceRow(string $accountNumber, string $accountName, Collection $rows): array
    {
        $totalDebit = $rows->sum(function ($entry) {
            return (float) ($entry->debit ?? 0);
        });
        $totalCredit = $rows->sum(function ($entry) {
            return (float) ($entry->credit ?? 0);
        });
        $netBalance = $totalDebit - $totalCredit;

        return [
            'account_number' => $accountNumber !== '' ? $accountNumber : __('messages.account_undefined'),
            'account_name' => $accountName,
            'movement_debit' => $totalDebit,
            'movement_credit' => $totalCredit,
            'balance_debit' => $netBalance >= 0 ? $netBalance : 0.0,
            'balance_credit' => $netBalance < 0 ? abs($netBalance) : 0.0,
        ];
    }

    public static function formatAmount(float $value): string
    {
        if ($value == 0.0) {
            return '';
        }

        return number_format($value, 0, ',', '.');
    }

    /**
     * @param  array<string, float|int|string|null>  $row
     */
    public static function hasAmounts(array $row): bool
    {
        foreach (['movement_debit', 'movement_credit', 'balance_debit', 'balance_credit'] as $key) {
            if ((float) ($row[$key] ?? 0) != 0.0) {
                return true;
            }
        }

        return false;
    }

    public static function reportScopeLabel(string $filterLabel): string
    {
        if ($filterLabel === __('messages.ledger_all_accounts')) {
            return __('messages.accounts_balance_complete');
        }

        return $filterLabel;
    }

    /**
     * @param  array<int|string>|null  $ids
     * @return array<int, int>
     */
    private static function normalizeIds(?array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            if ($id !== null && $id !== '') {
                $normalized[] = (int) $id;
            }
        }

        return array_values(array_unique($normalized));
    }
}
