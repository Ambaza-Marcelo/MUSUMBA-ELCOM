<?php

namespace App\Support;

use App\Models\FinishedGoodReport;
use App\Models\FinishedGoodsSmStoreDetail;
use App\Models\FinishedGoodsSmStoreReport;
use App\Models\FinishedGoodsStoreDetail;
use App\Models\GoodsStoreDetail;
use App\Models\GoodsStoreReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recalcule quantity (et valeur) des fiches stock à partir des mouvements de rapport.
 */
class StockInventoryResync
{
    /**
     * @return array{updated:int, skipped:int, details:array<int, array<string, mixed>>}
     */
    public static function resyncFinishedGoodsStore(?string $codeStore = null): array
    {
        return self::resyncGeneric([
            'report_model' => FinishedGoodReport::class,
            'detail_model' => FinishedGoodsStoreDetail::class,
            'entity_column' => 'item_id',
            'store_column' => 'code_store',
            'detail_store_column' => 'code_store',
            'code_store' => $codeStore,
            'value_field' => 'total_cump_value',
        ]);
    }

    /**
     * @return array{updated:int, skipped:int, details:array<int, array<string, mixed>>}
     */
    public static function resyncFinishedGoodsSmStore(?string $codeStore = null): array
    {
        return self::resyncGeneric([
            'report_model' => FinishedGoodsSmStoreReport::class,
            'detail_model' => FinishedGoodsSmStoreDetail::class,
            'entity_column' => 'item_id',
            'store_column' => 'code_store',
            'detail_store_column' => 'code_store',
            'code_store' => $codeStore,
            'value_field' => 'total_cump_value',
            // SM movements may historically live in FinishedGoodReport — merge both.
            'extra_report_models' => [FinishedGoodReport::class],
        ]);
    }

    /**
     * @return array{updated:int, skipped:int, details:array<int, array<string, mixed>>}
     */
    public static function resyncGoodsStore(?string $codeStore = null): array
    {
        return self::resyncGeneric([
            'report_model' => GoodsStoreReport::class,
            'detail_model' => GoodsStoreDetail::class,
            'entity_column' => 'item_id',
            'store_column' => 'code_store',
            'detail_store_column' => 'code_store',
            'code_store' => $codeStore,
            'value_field' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{updated:int, skipped:int, details:array<int, array<string, mixed>>}
     */
    protected static function resyncGeneric(array $config): array
    {
        $reportModel = $config['report_model'];
        $detailModel = $config['detail_model'];
        $entityColumn = $config['entity_column'];
        $storeColumn = $config['store_column'];
        $detailStoreColumn = $config['detail_store_column'];
        $codeStore = isset($config['code_store']) ? trim((string) $config['code_store']) : '';
        $valueField = $config['value_field'] ?? null;
        $extraReportModels = $config['extra_report_models'] ?? [];

        $hasValueField = false;
        if ($valueField) {
            $sample = new $detailModel;
            $hasValueField = \Illuminate\Support\Facades\Schema::hasColumn($sample->getTable(), $valueField);
        }

        $query = $reportModel::query()
            ->whereNotNull($entityColumn)
            ->where($entityColumn, '!=', '');

        if ($codeStore !== '') {
            $query->where($storeColumn, $codeStore);
        }

        $rows = $query->get();

        foreach ($extraReportModels as $extraModel) {
            if (!class_exists($extraModel) || $extraModel === $reportModel) {
                continue;
            }
            $extraQuery = $extraModel::query()
                ->whereNotNull($entityColumn)
                ->where($entityColumn, '!=', '');
            if ($codeStore !== '') {
                $extraQuery->where($storeColumn, $codeStore);
            }
            $rows = $rows->concat($extraQuery->get());
        }

        if ($rows->isEmpty()) {
            return ['updated' => 0, 'skipped' => 0, 'details' => []];
        }

        $recalculated = ProgressiveStockReportBuilder::apply($rows, [
            'entity_column' => $entityColumn,
            'store_column' => $storeColumn,
            'display_direction' => 'asc',
        ]);

        /** @var Collection $lastByKey */
        $lastByKey = $recalculated
            ->groupBy(function ($row) use ($storeColumn, $entityColumn) {
                return ChronologicalProgression::attr($row, $storeColumn, '')
                    . '|'
                    . ChronologicalProgression::attr($row, $entityColumn, '');
            })
            ->map(function (Collection $group) {
                return ChronologicalProgression::sortByInsertion($group, 'asc')->last();
            });

        $updated = 0;
        $skipped = 0;
        $details = [];

        DB::transaction(function () use (
            $lastByKey,
            $detailModel,
            $detailStoreColumn,
            $entityColumn,
            $valueField,
            $hasValueField,
            &$updated,
            &$skipped,
            &$details
        ) {
            foreach ($lastByKey as $key => $lastRow) {
                [$store, $entityId] = array_pad(explode('|', (string) $key, 2), 2, null);
                if ($store === null || $store === '' || $entityId === null || $entityId === '') {
                    $skipped++;
                    continue;
                }

                $detail = $detailModel::query()
                    ->where($detailStoreColumn, $store)
                    ->where($entityColumn, $entityId)
                    ->first();

                if (!$detail) {
                    $skipped++;
                    continue;
                }

                $finalQty = ChronologicalProgression::toFloat(
                    ChronologicalProgression::attr($lastRow, 'quantity_stock_final', 0)
                );
                $finalCump = ChronologicalProgression::toFloat(
                    ChronologicalProgression::attr($lastRow, 'cump', $detail->cump ?? 0)
                );
                $oldQty = (float) ($detail->quantity ?? 0);

                $detail->quantity = $finalQty;
                if ($finalCump > 0) {
                    $detail->cump = $finalCump;
                }
                if ($hasValueField && $valueField) {
                    $detail->{$valueField} = $finalQty * (float) ($detail->cump ?? 0);
                }
                $detail->save();

                $updated++;
                $details[] = [
                    'code_store' => $store,
                    'entity_id' => $entityId,
                    'old_quantity' => $oldQty,
                    'new_quantity' => $finalQty,
                ];
            }
        });

        return compact('updated', 'skipped', 'details');
    }
}
