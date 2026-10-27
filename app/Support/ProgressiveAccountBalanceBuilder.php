<?php

namespace App\Support;

/**
 * Solde progressif comptable (débit − crédit) ligne à ligne,
 * après tri chronologique strict.
 */
class ProgressiveAccountBalanceBuilder
{
    /**
     * @param  \Illuminate\Support\Collection|iterable  $entries
     * @param  array{
     *     date_column?: string,
     *     created_column?: string,
     *     debit_column?: string,
     *     credit_column?: string,
     *     group_by?: string|null
     * }  $options
     * @return \Illuminate\Support\Collection  lignes enrichies : running_balance, balance_debit, balance_credit
     */
    public static function apply($entries, array $options = [])
    {
        $collection = $entries instanceof \Illuminate\Support\Collection
            ? $entries
            : collect($entries);

        if ($collection->isEmpty()) {
            return $collection;
        }

        $dateColumn = $options['date_column'] ?? 'date';
        $createdColumn = $options['created_column'] ?? 'created_at';
        $debitColumn = $options['debit_column'] ?? 'debit';
        $creditColumn = $options['credit_column'] ?? 'credit';
        $groupBy = $options['group_by'] ?? 'account_id';

        $groups = $groupBy
            ? $collection->groupBy(function ($row) use ($groupBy) {
                return (string) ChronologicalProgression::attr($row, $groupBy, '');
            })
            : collect(['all' => $collection]);

        $result = collect();

        foreach ($groups as $groupRows) {
            $sorted = ChronologicalProgression::sort($groupRows, 'asc', $dateColumn, $createdColumn);
            $running = 0.0;

            foreach ($sorted as $entry) {
                $debit = ChronologicalProgression::toFloat(ChronologicalProgression::attr($entry, $debitColumn, 0));
                $credit = ChronologicalProgression::toFloat(ChronologicalProgression::attr($entry, $creditColumn, 0));
                $running += ($debit - $credit);

                $entry = ChronologicalProgression::setAttr($entry, $debitColumn, $debit);
                $entry = ChronologicalProgression::setAttr($entry, $creditColumn, $credit);
                $entry = ChronologicalProgression::setAttr($entry, 'running_balance', $running);
                $entry = ChronologicalProgression::setAttr($entry, 'balance_debit', $running > 0 ? $running : 0.0);
                $entry = ChronologicalProgression::setAttr($entry, 'balance_credit', $running < 0 ? abs($running) : 0.0);

                $result->push($entry);
            }
        }

        return $result->values();
    }
}
