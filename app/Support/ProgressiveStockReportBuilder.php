<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Recalcule stock initial / entrée / sortie / final en chaîne progressive
 * par (magasin + article), selon l'ordre d'insertion réel (created_at → id).
 */
class ProgressiveStockReportBuilder
{
    /**
     * @param  Collection|iterable  $rows
     * @param  array{
     *     entity_column?: string|null,
     *     store_column?: string,
     *     display_direction?: string
     * }  $options
     */
    public static function apply($rows, array $options = []): Collection
    {
        $collection = $rows instanceof Collection ? $rows : collect($rows);
        if ($collection->isEmpty()) {
            return $collection;
        }

        $storeColumn = $options['store_column'] ?? 'code_store';
        $displayDirection = strtolower($options['display_direction'] ?? 'desc');
        $entityColumn = $options['entity_column'] ?? self::detectEntityColumn($collection->first());

        $grouped = $collection->groupBy(function ($row) use ($storeColumn, $entityColumn) {
            $store = (string) ChronologicalProgression::attr($row, $storeColumn, '');
            $entity = (string) ChronologicalProgression::attr($row, $entityColumn, '');

            return $store . '|' . $entity;
        });

        $recalculated = collect();

        foreach ($grouped as $groupRows) {
            $sorted = ChronologicalProgression::sortByInsertion($groupRows, 'asc');
            $runningQty = null;
            $runningValue = null;

            foreach ($sorted as $row) {
                $cump = ChronologicalProgression::toFloat(ChronologicalProgression::attr($row, 'cump', 0));
                $type = strtoupper(trim((string) ChronologicalProgression::attr($row, 'type_transaction', '')));
                $isInventory = $type === 'INVENTAIRE';

                $stockin = ChronologicalProgression::toFloat(ChronologicalProgression::attr($row, 'quantity_stockin', 0));
                $stockout = ChronologicalProgression::toFloat(ChronologicalProgression::attr($row, 'quantity_stockout', 0));
                $storedInitial = ChronologicalProgression::toFloat(ChronologicalProgression::attr($row, 'quantity_stock_initial', 0));
                $storedFinal = ChronologicalProgression::toFloat(ChronologicalProgression::attr($row, 'quantity_stock_final', 0));

                if ($runningQty === null) {
                    $runningQty = $storedInitial;
                    $runningValue = ChronologicalProgression::toFloat(
                        ChronologicalProgression::attr($row, 'value_stock_initial', $runningQty * $cump)
                    );
                }

                $initialQty = $runningQty;
                $initialValue = $runningValue !== null ? $runningValue : ($initialQty * $cump);

                if ($isInventory) {
                    $physical = $storedFinal > 0
                        ? $storedFinal
                        : ($initialQty + $stockin - $stockout);
                    $delta = $physical - $initialQty;
                    $stockin = $delta > 0 ? $delta : 0.0;
                    $stockout = $delta < 0 ? abs($delta) : 0.0;
                    $finalQty = $physical;
                } else {
                    $finalQty = $initialQty + $stockin - $stockout;
                }

                $valueStockin = ChronologicalProgression::toFloat(
                    ChronologicalProgression::attr($row, 'value_stockin', $stockin * $cump)
                );
                $valueStockout = ChronologicalProgression::toFloat(
                    ChronologicalProgression::attr($row, 'value_stockout', $stockout * $cump)
                );

                if ($isInventory) {
                    $valueStockin = $stockin * $cump;
                    $valueStockout = $stockout * $cump;
                }

                $finalValue = $initialValue + $valueStockin - $valueStockout;
                if ($isInventory) {
                    $finalValue = $finalQty * $cump;
                }

                $row = ChronologicalProgression::setAttr($row, 'quantity_stock_initial', $initialQty);
                $row = ChronologicalProgression::setAttr($row, 'value_stock_initial', $initialValue);
                $row = ChronologicalProgression::setAttr($row, 'quantity_stockin', $stockin);
                $row = ChronologicalProgression::setAttr($row, 'value_stockin', $valueStockin);
                $row = ChronologicalProgression::setAttr($row, 'quantity_stockout', $stockout);
                $row = ChronologicalProgression::setAttr($row, 'value_stockout', $valueStockout);
                $row = ChronologicalProgression::setAttr($row, 'quantity_stock_final', $finalQty);
                $row = ChronologicalProgression::setAttr($row, 'value_stock_final', $finalValue);
                $row = ChronologicalProgression::setAttr($row, 'stock_total', $finalQty);
                $row = ChronologicalProgression::setAttr($row, 'cump', $cump);

                $runningQty = $finalQty;
                $runningValue = $finalValue;
                $recalculated->push($row);
            }
        }

        return ChronologicalProgression::sortByInsertion($recalculated, $displayDirection);
    }

    /**
     * @param  object|array|null  $row
     */
    public static function detectEntityColumn($row): string
    {
        foreach (['item_id', 'material_id', 'r_material_id'] as $candidate) {
            $value = ChronologicalProgression::attr($row, $candidate);
            if ($value !== null && $value !== '') {
                return $candidate;
            }
        }

        return 'item_id';
    }
}
