<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * @deprecated Utiliser ProgressiveStockReportBuilder::apply()
 * Conservé pour compatibilité des exports existants.
 */
class StockReportInventoryNormalizer
{
    public static function isInventoryType($type): bool
    {
        return strtoupper(trim((string) $type)) === 'INVENTAIRE';
    }

    /**
     * @param  object|array  $row
     * @return object|array
     */
    public static function normalizeRow($row)
    {
        return ProgressiveStockReportBuilder::apply(collect([$row]), [
            'display_direction' => 'asc',
        ])->first();
    }

    public static function normalizeCollection($rows, array $options = []): Collection
    {
        return ProgressiveStockReportBuilder::apply($rows, array_merge([
            'display_direction' => 'desc',
        ], $options));
    }
}
