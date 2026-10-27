<?php

namespace App\Console\Commands;

use App\Support\StockInventoryResync;
use Illuminate\Console\Command;

class ResyncStockInventory extends Command
{
    protected $signature = 'stock:resync-inventory
                            {--store= : Code magasin (optionnel)}
                            {--type=fg : fg|fgsm|goods|all}';

    protected $description = 'Recalcule les quantités des fiches stock à partir des rapports de mouvements';

    public function handle(): int
    {
        $type = strtolower((string) $this->option('type') ?: 'fg');
        $store = $this->option('store');
        $store = $store !== null && $store !== '' ? (string) $store : null;

        $runs = [];
        if ($type === 'all') {
            $runs = ['fg', 'fgsm', 'goods'];
        } else {
            $runs = [$type];
        }

        foreach ($runs as $run) {
            if ($run === 'fg') {
                $result = StockInventoryResync::resyncFinishedGoodsStore($store);
                $label = 'Produits finis';
            } elseif ($run === 'fgsm') {
                $result = StockInventoryResync::resyncFinishedGoodsSmStore($store);
                $label = 'Petit stock PF';
            } elseif ($run === 'goods') {
                $result = StockInventoryResync::resyncGoodsStore($store);
                $label = 'Marchandises';
            } else {
                $this->error('Type inconnu: ' . $run);
                return 1;
            }

            $this->info(sprintf(
                '%s — mis à jour: %d | ignorés: %d%s',
                $label,
                $result['updated'],
                $result['skipped'],
                $store ? (' | magasin: ' . $store) : ''
            ));

            foreach (array_slice($result['details'], 0, 30) as $row) {
                if ((float) $row['old_quantity'] === (float) $row['new_quantity']) {
                    continue;
                }
                $this->line(sprintf(
                    '  %s / entity %s : %s → %s',
                    $row['code_store'],
                    $row['entity_id'],
                    $row['old_quantity'],
                    $row['new_quantity']
                ));
            }
        }

        return 0;
    }
}
