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
     * @return array{year: int, date_debut: string, date_fin: string}
     */
    public static function resolveFiscalYear(?string $fiscalYear): array
    {
        $year = (int) ($fiscalYear ?: Carbon::now()->format('Y'));
        if ($year < 1900 || $year > 9999) {
            $year = (int) Carbon::now()->format('Y');
        }

        return [
            'year' => $year,
            'date_debut' => Carbon::create($year, 1, 1)->startOfDay()->format('Y-m-d'),
            'date_fin' => Carbon::create($year, 12, 31)->endOfDay()->format('Y-m-d H:i:s'),
        ];
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
    public static function buildFilterLabel(?array $parentAccountIds, ?array $accountIds = null): string
    {
        $parentAccountIds = self::normalizeIds($parentAccountIds);
        $accountIds = self::normalizeIds($accountIds);
        $parts = [];

        if (!empty($parentAccountIds)) {
            $parents = AccountParentAccount::whereIn('id', $parentAccountIds)
                ->orderBy('account_number')
                ->get();

            foreach ($parents as $parent) {
                $childCount = AccountAccount::where('acc_parent_id', $parent->id)->count();
                $parts[] = trim($parent->account_number . ' ' . $parent->account_name)
                    . ' (' . $childCount . ' ' . __('messages.ledger_child_accounts') . ')';
            }
        }

        if (!empty($accountIds)) {
            $accounts = AccountAccount::whereIn('id', $accountIds)->orderBy('account_number')->get();
            foreach ($accounts as $account) {
                $parts[] = trim($account->account_number . ' ' . $account->account_name);
            }
        }

        if (empty($parts)) {
            return __('messages.ledger_all_accounts');
        }

        return implode(' ; ', $parts);
    }

    /**
     * @param  array<int|string>|null  $parentAccountIds
     * @param  array<int|string>|null  $accountIds
     * @return array{
     *     accountsData: \Illuminate\Support\Collection,
     *     grandTotals: array<string, float>,
     *     reportRow: array<string, float>,
     *     fiscalYear: int,
     *     periodStartFormatted: string,
     *     periodEndFormatted: string,
     *     filterLabel: string
     * }
     */
    public static function build(?array $parentAccountIds = null, ?string $fiscalYear = null, ?array $accountIds = null): array
    {
        $period = self::resolveFiscalYear($fiscalYear);
        $filteredAccountIds = self::resolveFilteredAccountIds($parentAccountIds, $accountIds);

        $query = AccountJournalEntryDetail::with('account')
            ->whereBetween('date', [$period['date_debut'], $period['date_fin']]);

        if ($filteredAccountIds !== null) {
            if (empty($filteredAccountIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('account_id', $filteredAccountIds);
            }
        }

        $entries = $query->get();

        $accountsData = $entries
            ->groupBy('account_id')
            ->map(function (Collection $rows) {
                $firstEntry = $rows->first();
                $account = optional($firstEntry)->account;

                $accountNumber = trim(optional($account)->account_number ?? '');
                $accountName = trim(optional($account)->account_name ?? '');

                if ($accountNumber === '') {
                    $accountNumber = __('messages.account_undefined');
                }

                $totalDebit = $rows->sum(fn ($entry) => (float) ($entry->debit ?? 0));
                $totalCredit = $rows->sum(fn ($entry) => (float) ($entry->credit ?? 0));
                $netBalance = $totalDebit - $totalCredit;

                return [
                    'account_number' => $accountNumber,
                    'account_name' => $accountName,
                    'movement_debit' => $totalDebit,
                    'movement_credit' => $totalCredit,
                    'balance_debit' => $netBalance >= 0 ? $netBalance : 0.0,
                    'balance_credit' => $netBalance < 0 ? abs($netBalance) : 0.0,
                ];
            })
            ->filter(function (array $row) {
                return ($row['movement_debit'] ?? 0) != 0.0 || ($row['movement_credit'] ?? 0) != 0.0;
            })
            ->sortBy(fn (array $row) => sprintf('%s|%s', $row['account_number'], $row['account_name']))
            ->values();

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
            'periodStartFormatted' => Carbon::create($period['year'], 1, 1)->format('d/m/y'),
            'periodEndFormatted' => Carbon::create($period['year'], 12, 31)->format('d/m/y'),
            'filterLabel' => self::buildFilterLabel($parentAccountIds, $accountIds),
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
