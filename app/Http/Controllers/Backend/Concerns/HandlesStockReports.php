<?php

namespace App\Http\Controllers\Backend\Concerns;

use App\Support\ChronologicalProgression;
use App\Support\ProgressiveStockReportBuilder;
use App\Support\StockReportFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HandlesStockReports
{
    protected function stockReportFilterConfig(): array
    {
        return [
            'date_column' => 'date',
            'item_column' => null,
            'store_column' => 'code_store',
            'default_limit' => 100,
        ];
    }

    protected function stockReportFilterConfigWithStores($stores): array
    {
        return array_merge($this->stockReportFilterConfig(), [
            'registered_store_codes' => $stores->pluck('code')->filter()->values()->all(),
        ]);
    }

    protected function stockReportViewData(Request $request, $stores): array
    {
        $config = $this->stockReportFilterConfigWithStores($stores);

        return [
            'stores' => $stores,
            'articles' => StockReportFilter::selectedArticlesForView($request, $config),
        ];
    }

    /**
     * Tri d'insertion (created_at → id).
     *
     * @deprecated Prefer orderStockReportChronologically()
     */
    protected function orderStockReportById(Builder $query, string $direction = 'desc'): Builder
    {
        return $this->orderStockReportChronologically($query, $direction);
    }

    protected function orderStockReportChronologically(Builder $query, string $direction = 'desc'): Builder
    {
        return ChronologicalProgression::applyInsertionQueryOrder($query, $direction);
    }

    /**
     * Recalcule le stock progressif selon l'ordre d'insertion, puis ordonne pour l'affichage.
     *
     * @param  \Illuminate\Support\Collection|iterable  $rows
     * @return \Illuminate\Support\Collection
     */
    protected function normalizeStockReportRows($rows, string $displayDirection = 'desc')
    {
        $config = $this->stockReportFilterConfig();

        return ProgressiveStockReportBuilder::apply($rows, [
            'entity_column' => $config['item_column'] ?? null,
            'store_column' => $config['store_column'] ?? 'code_store',
            'display_direction' => $displayDirection,
        ]);
    }

    protected function validateStockReportDates(Request $request, string $redirectRoute)
    {
        if (!StockReportFilter::requiresDateRange($request)) {
            return redirect()->route($redirectRoute)
                ->with('error', __('messages.export_select_date_range'));
        }

        return null;
    }

    protected function mergeExportRequest(Request $request): Request
    {
        $request->merge(['export_mode' => true]);

        return $request;
    }
}
