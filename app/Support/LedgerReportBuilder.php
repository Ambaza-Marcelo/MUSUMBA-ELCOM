<?php

namespace App\Support;

use App\Models\Accounting\AccountAccount;
use App\Models\Accounting\AccountParentAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LedgerReportBuilder
{
    /** Capacité estimée d'une page PDF portrait (unités de hauteur). */
    public const PDF_PAGE_CAPACITY = 28;

    /**
     * @param  array<int|string>|null  $accountIds
     * @param  array<int|string>|null  $parentAccountIds
     * @return array<int, int>|null  null = tous les comptes
     */
    public static function resolveAccountIds(?array $accountIds, ?array $parentAccountIds): ?array
    {
        $accountIds = self::normalizeIds($accountIds);
        $parentAccountIds = self::normalizeIds($parentAccountIds);

        if (empty($accountIds) && empty($parentAccountIds)) {
            return null;
        }

        $ids = $accountIds;
        if (!empty($parentAccountIds)) {
            $childIds = AccountAccount::whereIn('acc_parent_id', $parentAccountIds)->pluck('id')->all();
            $ids = array_merge($ids, $childIds);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int|string>|null  $accountIds
     * @param  array<int|string>|null  $parentAccountIds
     */
    public static function applyAccountFilter(Builder $query, ?array $accountIds, ?array $parentAccountIds): void
    {
        $resolved = self::resolveAccountIds($accountIds, $parentAccountIds);

        if ($resolved === null) {
            return;
        }

        if (empty($resolved)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('account_id', $resolved);
    }

    /**
     * @param  array<int|string>|null  $accountIds
     * @param  array<int|string>|null  $parentAccountIds
     */
    public static function buildFilterLabel(?array $accountIds, ?array $parentAccountIds): string
    {
        $accountIds = self::normalizeIds($accountIds);
        $parentAccountIds = self::normalizeIds($parentAccountIds);
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
            if ($accounts->count() === 1) {
                $acc = $accounts->first();
                $parts[] = $acc->account_number . ' ' . $acc->account_name;
            } elseif ($accounts->count() > 1) {
                $parts[] = __('messages.ledger_selected_accounts') . ' (' . $accounts->count() . ')';
            }
        }

        if (empty($parts)) {
            return __('messages.ledger_all_accounts');
        }

        return implode(' ; ', $parts);
    }

    /**
     * Tri chronologique : date opération → created_at → id.
     * Casts les dates string et stabilise les égalités d'horodatage.
     */
    public static function sortByOperationDate(Collection $entries): Collection
    {
        return ChronologicalProgression::sort($entries, 'asc', 'date', 'created_at');
    }

    /**
     * Regroupe les écritures par compte (tri numéro de compte), N° pièce repart à 1 par compte.
     *
     * @return array{
     *     sections: array<int, array<string, mixed>>,
     *     grandTotalDebit: float,
     *     grandTotalCredit: float,
     *     grandBalanceDebit: float,
     *     grandBalanceCredit: float
     * }
     */
    public static function buildAccountSections(Collection $entries): array
    {
        $grouped = $entries->groupBy('account_id');

        $sortedGroups = $grouped->sortBy(function (Collection $group) {
            $first = $group->first();

            return (string) (optional($first->account)->account_number ?? 'ZZZZZZZZ');
        });

        $sections = [];
        $grandTotalDebit = 0;
        $grandTotalCredit = 0;

        foreach ($sortedGroups as $accountId => $groupEntries) {
            $sorted = self::sortByOperationDate($groupEntries);
            $first = $sorted->first();
            $accountNumber = (string) (optional($first->account)->account_number ?? '');
            $accountName = (string) (optional($first->account)->account_name ?? '');

            $rows = [];
            $runningBalance = 0.0;
            $accountDebit = 0.0;
            $accountCredit = 0.0;
            $cj = 1;

            foreach ($sorted as $entry) {
                $debit = ChronologicalProgression::toFloat($entry->debit ?? 0);
                $credit = ChronologicalProgression::toFloat($entry->credit ?? 0);
                $runningBalance += ($debit - $credit);
                $accountDebit += $debit;
                $accountCredit += $credit;
                $grandTotalDebit += $debit;
                $grandTotalCredit += $credit;

                $balanceDebit = $runningBalance > 0 ? $runningBalance : 0;
                $balanceCredit = $runningBalance < 0 ? abs($runningBalance) : 0;
                $operationDateObj = ChronologicalProgression::parseDateTime($entry->date ?? null);
                $createdAtObj = ChronologicalProgression::parseDateTime($entry->created_at ?? null);
                $operationDate = $operationDateObj ? $operationDateObj->format('d/m/Y') : '--/--/----';

                $rows[] = [
                    'operation_date' => $operationDate,
                    'created_at' => $createdAtObj
                        ? $createdAtObj->format('d/m/Y H:i')
                        : '--/--/----',
                    'cj' => $cj,
                    'document_no' => $entry->document_no ?? '',
                    'label' => $entry->libelle ?? $entry->description ?? '',
                    'let' => $entry->description ?? '',
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance_debit' => $balanceDebit,
                    'balance_credit' => $balanceCredit,
                ];
                $cj++;
            }

            $finalBalance = $runningBalance;

            $sections[] = [
                'account_id' => $accountId,
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'account_label' => trim($accountNumber . ' ' . $accountName),
                'rows' => $rows,
                'total_debit' => $accountDebit,
                'total_credit' => $accountCredit,
                'total_balance_debit' => $finalBalance > 0 ? $finalBalance : 0,
                'total_balance_credit' => $finalBalance < 0 ? abs($finalBalance) : 0,
            ];
        }

        $grandBalance = $grandTotalDebit - $grandTotalCredit;

        return [
            'sections' => $sections,
            'grandTotalDebit' => $grandTotalDebit,
            'grandTotalCredit' => $grandTotalCredit,
            'grandBalanceDebit' => $grandBalance > 0 ? $grandBalance : 0,
            'grandBalanceCredit' => $grandBalance < 0 ? abs($grandBalance) : 0,
        ];
    }

    /**
     * Liste plate pour Excel / écran (ordre par numéro de compte).
     *
     * @return array{
     *     ledgerRows: array<int, array<string, mixed>>,
     *     grandTotalDebit: float,
     *     grandTotalCredit: float,
     *     grandBalanceDebit: float,
     *     grandBalanceCredit: float
     * }
     */
    public static function buildRows(Collection $entries): array
    {
        $data = self::buildAccountSections($entries);
        $ledgerRows = [];

        foreach ($data['sections'] as $section) {
            foreach ($section['rows'] as $index => $row) {
                $ledgerRows[] = array_merge($row, [
                    'account_label' => $section['account_label'],
                    'account_id' => $section['account_id'],
                    'account_number' => $section['account_number'],
                    'date' => $row['operation_date'],
                    'piece' => $row['document_no'],
                    'is_first_of_account' => ($index === 0),
                ]);
            }
        }

        return [
            'ledgerRows' => $ledgerRows,
            'grandTotalDebit' => $data['grandTotalDebit'],
            'grandTotalCredit' => $data['grandTotalCredit'],
            'grandBalanceDebit' => $data['grandBalanceDebit'],
            'grandBalanceCredit' => $data['grandBalanceCredit'],
        ];
    }

    /**
     * Découpe le grand livre en pages PDF avec en-tête compte répété et « A reporter » par page.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array{blocks: array<int, array<string, mixed>>, carry_forward: array<string, float>}>
     */
    public static function buildPdfPages(array $sections, int $pageCapacity = self::PDF_PAGE_CAPACITY): array
    {
        $pages = [];
        $currentBlocks = [];
        $currentUsed = 0;
        $cumulativeDebit = 0.0;
        $cumulativeCredit = 0.0;

        $flushPage = function () use (&$pages, &$currentBlocks, &$currentUsed, &$cumulativeDebit, &$cumulativeCredit) {
            if (empty($currentBlocks)) {
                return;
            }

            $balance = $cumulativeDebit - $cumulativeCredit;
            $pages[] = [
                'blocks' => $currentBlocks,
                'carry_forward' => [
                    'debit' => $cumulativeDebit,
                    'credit' => $cumulativeCredit,
                    'balance_debit' => $balance > 0 ? $balance : 0,
                    'balance_credit' => $balance < 0 ? abs($balance) : 0,
                ],
            ];
            $currentBlocks = [];
            $currentUsed = 0;
        };

        $appendBlock = function (array $block, int $weight) use (&$currentBlocks, &$currentUsed, $pageCapacity, $flushPage) {
            $carryWeight = 1;
            if ($currentUsed > 0 && ($currentUsed + $weight + $carryWeight) > $pageCapacity) {
                $flushPage();
            }
            $currentBlocks[] = $block;
            $currentUsed += $weight;
        };

        foreach ($sections as $section) {
            $rowCount = count($section['rows']);
            $rowIndex = 0;
            $headerPending = true;
            $totalPending = $rowCount > 0;

            while ($headerPending || $rowIndex < $rowCount || $totalPending) {
                if ($headerPending) {
                    $headerWeight = mb_strlen(($section['account_name'] ?? '')) > 40 ? 2 : 1;
                    $appendBlock(['type' => 'account_header', 'section' => $section], $headerWeight);
                    $headerPending = false;
                }

                while ($rowIndex < $rowCount) {
                    $row = $section['rows'][$rowIndex];
                    $weight = self::estimateMovementWeight($row['label'] ?? '');
                    $remaining = $rowCount - $rowIndex;
                    $carryWeight = 1;

                    if ($currentUsed > 0
                        && ($currentUsed + $weight + ($remaining === 1 && $totalPending ? 1 : 0) + $carryWeight) > $pageCapacity
                    ) {
                        $flushPage();
                        $headerPending = true;
                        break;
                    }

                    $appendBlock(['type' => 'movement', 'row' => $row], $weight);
                    $cumulativeDebit += (float) $row['debit'];
                    $cumulativeCredit += (float) $row['credit'];
                    $rowIndex++;
                }

                if ($headerPending) {
                    continue;
                }

                if ($totalPending && $rowIndex >= $rowCount) {
                    $carryWeight = 1;
                    if ($currentUsed > 0 && ($currentUsed + 1 + $carryWeight) > $pageCapacity) {
                        $flushPage();
                        $headerPending = true;
                        continue;
                    }

                    $appendBlock(['type' => 'account_total', 'section' => $section], 1);
                    $totalPending = false;
                }
            }
        }

        $flushPage();

        return $pages;
    }

    private static function estimateMovementWeight(string $label): int
    {
        $length = mb_strlen($label);

        if ($length <= 45) {
            return 1;
        }

        if ($length <= 90) {
            return 2;
        }

        return 3;
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
