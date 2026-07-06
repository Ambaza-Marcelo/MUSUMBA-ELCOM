<?php

namespace App\Support;

class StockInventoryReportBuilder
{
    /**
     * Inventaire = remplacement du stock théorique par le stock physique.
     * L'entrée inventaire (quantité comptée) doit être égale au stock final.
     */
    public static function movementPayload(
        float $theoreticalQuantity,
        float $physicalQuantity,
        float $cump,
        ?float $valueInitial = null
    ): array {
        $theoreticalQuantity = (float) $theoreticalQuantity;
        $physicalQuantity = (float) $physicalQuantity;
        $cump = (float) $cump;
        $valueInitial = $valueInitial ?? ($theoreticalQuantity * $cump);
        $valueFinal = $physicalQuantity * $cump;
        $delta = $physicalQuantity - $theoreticalQuantity;

        return [
            'quantity_stock_initial' => $theoreticalQuantity,
            'value_stock_initial' => $valueInitial,
            'quantity_stockin' => $delta > 0 ? $delta : 0,
            'value_stockin' => $delta > 0 ? $delta * $cump : 0,
            'stock_total' => $physicalQuantity,
            'quantity_stockout' => $delta < 0 ? abs($delta) : 0,
            'value_stockout' => $delta < 0 ? abs($delta) * $cump : 0,
            'quantity_stock_final' => $physicalQuantity,
            'value_stock_final' => $valueFinal,
            'cump' => $cump,
        ];
    }
}
