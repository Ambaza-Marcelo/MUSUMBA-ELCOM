<?php

namespace App\Services\Stock;

use App\Models\Accounting\AccountAccount;
use App\Models\Accounting\AccountJournalEntryDetail;
use App\Models\Accounting\AccountLedger;
use App\Models\Accounting\AccountSetting;
use App\Models\Material;
use App\Services\Accounting\PeriodLockService;
use App\Services\Material\MaterialFlowOrchestrator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * Rectification des sorties de stock dont le débit a été imputé sur un compte
 * d'ajustement global (m_saj_account_id pour les matériels, f_saj_account_id sinon).
 *
 * Les lignes de journal erronées sont modifiées en place : le compte d'ajustement
 * est remplacé par le compte de la fiche de chaque article sorti. Montant, date et
 * n° de document sont conservés ; si plusieurs comptes sont concernés, la ligne est
 * scindée en lignes de même date et même document dont la somme égale le montant d'origine.
 * Les soldes AccountLedger sont mis à jour et chaque substitution est tracée
 * dans account_journal_rectifications.
 *
 * Les sorties de type SAJ (ajustement réel) ne sont rectifiées que sur forçage.
 * Les lignes situées dans une période verrouillée ou un exercice clôturé sont refusées.
 * Une nouvelle catégorie se déclare dans modules().
 */
class StockoutRegularizationService
{
    const ADJUSTMENT_TYPE = 'SAJ';

    const PERMISSION = 'account_journal_entry.edit';

    /** @var MaterialFlowOrchestrator */
    protected $orchestrator;

    /** @var PeriodLockService */
    protected $periodLock;

    public function __construct(MaterialFlowOrchestrator $orchestrator, PeriodLockService $periodLock)
    {
        $this->orchestrator = $orchestrator;
        $this->periodLock = $periodLock;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function modules(): array
    {
        return [
            'material' => [
                'label' => 'Matériels',
                'permission' => self::PERMISSION,
                'adjustment_setting' => 'm_saj_account_id',
                'stockout' => \App\Models\MaterialStockout::class,
                'detail' => \App\Models\MaterialStockoutDetail::class,
                'article' => Material::class,
                'article_key' => 'material_id',
                'detail_value' => 'total_purchase_value',
                'reports' => [
                    \App\Models\MaterialBigReport::class,
                    \App\Models\MaterialSmallReport::class,
                    \App\Models\MaterialExtraBigReport::class,
                ],
                'show_route' => 'admin.material-stockouts.show',
            ],
            'raw_material' => [
                'label' => 'Matières premières',
                'permission' => self::PERMISSION,
                'adjustment_setting' => 'f_saj_account_id',
                'stockout' => \App\Models\RawMaterialStockout::class,
                'detail' => \App\Models\RawMaterialStockoutDetail::class,
                'article' => \App\Models\RawMaterial::class,
                'article_key' => 'r_material_id',
                'detail_value' => 'total_purchase_value',
                'reports' => [
                    \App\Models\RawMaterialBigReport::class,
                    \App\Models\RawMaterialSmallReport::class,
                    \App\Models\RawMaterialExtraBigReport::class,
                ],
                'show_route' => 'admin.raw-material-stockouts.show',
            ],
            'goods' => [
                'label' => 'Marchandises',
                'permission' => self::PERMISSION,
                'adjustment_setting' => 'f_saj_account_id',
                'stockout' => \App\Models\GoodsStockout::class,
                'detail' => \App\Models\GoodsStockoutDetail::class,
                'article' => \App\Models\Item::class,
                'article_key' => 'item_id',
                'detail_value' => 'total_cost_value',
                'reports' => [\App\Models\GoodsStoreReport::class],
                'show_route' => 'admin.goods-stockouts.show',
            ],
            'finished_goods' => [
                'label' => 'Produits finis',
                'permission' => self::PERMISSION,
                'adjustment_setting' => 'f_saj_account_id',
                'stockout' => \App\Models\FinishedGoodsStockout::class,
                'detail' => \App\Models\FinishedGoodsStockoutDetail::class,
                'article' => \App\Models\Item::class,
                'article_key' => 'item_id',
                'detail_value' => 'total_cost_value',
                'reports' => [\App\Models\FinishedGoodReport::class],
                'show_route' => 'admin.finished-goods-stockouts.show',
            ],
        ];
    }

    public function module(string $key): array
    {
        $modules = $this->modules();
        if (!isset($modules[$key])) {
            throw new InvalidArgumentException('Catégorie de sortie inconnue : ' . $key);
        }

        return $modules[$key] + ['key' => $key];
    }

    public function adjustmentAccountId(string $moduleKey): ?int
    {
        $setting = $this->module($moduleKey)['adjustment_setting'];
        $id = (int) AccountSetting::where('active', 1)->value($setting);

        return $id > 0 ? $id : null;
    }

    /**
     * Sorties approuvées de la catégorie ayant encore un montant sur le compte d'ajustement.
     *
     * @return Collection|array[]
     */
    public function candidates(string $moduleKey): Collection
    {
        $module = $this->module($moduleKey);
        $adjustmentAccountId = $this->adjustmentAccountId($moduleKey);
        if (!$adjustmentAccountId) {
            return collect();
        }

        $stockouts = $module['stockout']::query()
            ->where('status', 4)
            ->get()
            ->keyBy('stockout_no');

        if ($stockouts->isEmpty()) {
            return collect();
        }

        $debited = AccountJournalEntryDetail::query()
            ->where('account_id', $adjustmentAccountId)
            ->whereIn('document_no', $stockouts->keys()->all())
            ->where('debit', '>', 0)
            ->groupBy('document_no')
            ->select('document_no', DB::raw('SUM(debit) as total_debit'))
            ->pluck('total_debit', 'document_no');

        return $debited->map(function ($totalDebit, $stockoutNo) use ($stockouts, $moduleKey) {
            $remaining = $this->remainingAmount($moduleKey, $stockoutNo);
            if ($remaining <= 0) {
                return null;
            }

            $stockout = $stockouts->get($stockoutNo);
            $type = strtoupper((string) ($stockout->item_movement_type ?? ''));
            $allocation = null;
            $error = null;

            try {
                $allocation = $this->allocate($moduleKey, $stockoutNo, $remaining);
                foreach ($this->erroneousLines($moduleKey, $stockoutNo) as $line) {
                    $this->periodLock->assertDateWritable($line->date);
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }

            return [
                'stockout_no' => $stockoutNo,
                'date' => $stockout->date,
                'type' => $type !== '' ? $type : '—',
                'description' => $stockout->description,
                'line_count' => $this->erroneousLines($moduleKey, $stockoutNo)->count(),
                'remaining' => $remaining,
                'requires_force' => $type === self::ADJUSTMENT_TYPE,
                'allocation' => $allocation,
                'error' => $error,
            ];
        })->filter()->values();
    }

    /**
     * Débit encore imputé sur le compte d'ajustement pour cette sortie.
     */
    public function remainingAmount(string $moduleKey, string $stockoutNo): float
    {
        return round((float) $this->erroneousLines($moduleKey, $stockoutNo)->sum('debit'), 2);
    }

    /**
     * Lignes de journal de la sortie débitant le compte d'ajustement.
     *
     * @return Collection|AccountJournalEntryDetail[]
     */
    public function erroneousLines(string $moduleKey, string $stockoutNo, bool $lock = false): Collection
    {
        $adjustmentAccountId = $this->adjustmentAccountId($moduleKey);
        if (!$adjustmentAccountId) {
            return collect();
        }

        $query = AccountJournalEntryDetail::query()
            ->where('account_id', $adjustmentAccountId)
            ->where('document_no', $stockoutNo)
            ->where('debit', '>', 0)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Compte unique de la fiche de l'élément consommé.
     */
    public function articleAccountId($article): int
    {
        if ($article instanceof Material) {
            return $this->orchestrator->resolveStockoutDebitAccountId($article);
        }

        $accountId = (int) ($article->default_account_id ?? 0);
        if ($accountId <= 0) {
            throw new RuntimeException(
                'Compte comptable manquant sur la fiche de « ' . ($article->name ?? ('#' . $article->id)) . ' ».'
            );
        }

        return $accountId;
    }

    /**
     * Ventilation du montant par compte de fiche, au prorata des valeurs de sortie (CUMP)
     * enregistrées dans les rapports de stock du document.
     *
     * @return array<int, array{article_id:int, article:string, account_id:int, account:string, amount:float}>
     */
    public function allocate(string $moduleKey, string $stockoutNo, float $amount): array
    {
        $module = $this->module($moduleKey);
        $weights = $this->articleWeights($module, $stockoutNo);
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            throw new RuntimeException('Impossible de ventiler ' . $stockoutNo . ' : aucune valeur de sortie par article.');
        }

        $articles = $module['article']::whereIn('id', array_keys($weights))->get()->keyBy('id');
        $ids = array_keys($weights);
        $lastId = end($ids);
        $allocated = 0.0;
        $lines = [];

        foreach ($weights as $articleId => $weight) {
            $article = $articles->get($articleId);
            if (!$article) {
                throw new RuntimeException('Article #' . $articleId . ' introuvable pour la sortie ' . $stockoutNo . '.');
            }

            $accountId = $this->articleAccountId($article);

            $lineAmount = $articleId === $lastId
                ? round($amount - $allocated, 2)
                : round($amount * $weight / $totalWeight, 2);
            $allocated += $lineAmount;

            if ($lineAmount <= 0) {
                continue;
            }

            $account = AccountAccount::find($accountId);
            $lines[] = [
                'article_id' => (int) $articleId,
                'article' => $article->name,
                'account_id' => $accountId,
                'account' => $account ? ($account->account_number . ' - ' . $account->account_name) : ('#' . $accountId),
                'amount' => $lineAmount,
            ];
        }

        return $lines;
    }

    /**
     * Rectifie en place les lignes de journal de la sortie qui débitent le compte d'ajustement.
     *
     * @return array{amount:float, lines:array}
     * @throws RuntimeException|\App\Exceptions\AccountingPeriodLockedException
     */
    public function rectify(string $moduleKey, string $stockoutNo, bool $force = false, ?string $rectifiedBy = null): array
    {
        $module = $this->module($moduleKey);
        $adjustmentAccountId = $this->adjustmentAccountId($moduleKey);
        if (!$adjustmentAccountId) {
            throw new RuntimeException('Le compte d\'ajustement n\'est pas configuré dans les paramètres comptables.');
        }

        $stockout = $module['stockout']::where('stockout_no', $stockoutNo)->first();
        if (!$stockout || (int) $stockout->status !== 4) {
            throw new RuntimeException('La sortie ' . $stockoutNo . ' est introuvable ou non approuvée.');
        }

        if (!$force && strtoupper((string) $stockout->item_movement_type) === self::ADJUSTMENT_TYPE) {
            throw new RuntimeException(
                'La sortie ' . $stockoutNo . ' est une sortie d\'ajustement (SAJ) : utilisez le forçage pour la rectifier.'
            );
        }

        return DB::transaction(function () use ($moduleKey, $stockoutNo, $adjustmentAccountId, $rectifiedBy) {
            $journalLines = $this->erroneousLines($moduleKey, $stockoutNo, true);
            if ($journalLines->isEmpty()) {
                throw new RuntimeException('La sortie ' . $stockoutNo . ' n\'a plus de ligne imputée sur le compte d\'ajustement.');
            }

            $hasAudit = Schema::hasTable('account_journal_rectifications');
            $now = Carbon::now();
            $total = 0.0;
            $result = [];

            foreach ($journalLines as $journalLine) {
                $this->periodLock->assertDateWritable($journalLine->date);

                $lineAmount = round((float) $journalLine->debit, 2);
                $allocation = $this->allocate($moduleKey, $stockoutNo, $lineAmount);

                foreach ($allocation as $share) {
                    if ((int) $share['account_id'] === (int) $adjustmentAccountId) {
                        throw new RuntimeException(
                            'La fiche de « ' . $share['article'] . ' » pointe elle-même sur le compte d\'ajustement : rectification impossible.'
                        );
                    }
                }

                $this->updateLedgerTotals($adjustmentAccountId, 'debit', -$lineAmount);

                foreach ($allocation as $index => $share) {
                    $state = $this->updateLedgerTotals($share['account_id'], 'debit', $share['amount']);

                    if ($index === 0) {
                        $journalLine->account_id = $share['account_id'];
                        $journalLine->debit = $share['amount'];
                        $journalLine->balance = $state['balance'];
                        $journalLine->save();
                        $targetId = $journalLine->id;
                    } else {
                        $copy = collect($journalLine->getAttributes())->except('id')->all();
                        $copy['account_id'] = $share['account_id'];
                        $copy['debit'] = $share['amount'];
                        $copy['balance'] = $state['balance'];
                        AccountJournalEntryDetail::insert([$copy]);
                        $targetId = (int) AccountJournalEntryDetail::where('document_no', $stockoutNo)
                            ->where('account_id', $share['account_id'])
                            ->orderByDesc('id')
                            ->value('id');
                    }

                    if ($hasAudit) {
                        DB::table('account_journal_rectifications')->insert([
                            'journal_entry_detail_id' => $targetId,
                            'source_journal_entry_detail_id' => $journalLine->id,
                            'module' => $moduleKey,
                            'document_no' => $stockoutNo,
                            'old_account_id' => $adjustmentAccountId,
                            'new_account_id' => $share['account_id'],
                            'amount' => $share['amount'],
                            'reason' => 'Substitution du compte d\'ajustement par le compte de la fiche : ' . $share['article'],
                            'rectified_by' => $rectifiedBy,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $result[] = $share;
                }

                $total += $lineAmount;
            }

            return [
                'amount' => round($total, 2),
                'lines' => $result,
            ];
        });
    }

    /**
     * @return array<int, float> article_id => valeur de sortie
     */
    protected function articleWeights(array $module, string $stockoutNo): array
    {
        $key = $module['article_key'];
        $weights = [];

        foreach ($module['reports'] as $reportClass) {
            $rows = $reportClass::query()
                ->where('document_no', $stockoutNo)
                ->whereNotNull($key)
                ->where('value_stockout', '>', 0)
                ->groupBy($key)
                ->select($key, DB::raw('SUM(value_stockout) as value'))
                ->get();

            foreach ($rows as $row) {
                $id = (int) $row->{$key};
                $weights[$id] = ($weights[$id] ?? 0) + (float) $row->value;
            }
        }

        if (array_sum($weights) <= 0) {
            $weights = [];
            $details = $module['detail']::where('stockout_no', $stockoutNo)->get();
            foreach ($details as $detail) {
                $id = (int) ($detail->{$key} ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $weights[$id] = ($weights[$id] ?? 0) + (float) ($detail->{$module['detail_value']} ?? 0);
            }
        }

        ksort($weights);

        return array_filter($weights, function ($value) {
            return $value > 0;
        });
    }

    protected function updateLedgerTotals(int $accountId, string $type, float $amount): array
    {
        $ledger = AccountLedger::firstOrNew(['account_id' => $accountId]);

        $debit = (float) ($ledger->debit ?? 0);
        $credit = (float) ($ledger->credit ?? 0);

        if ($type === 'debit') {
            $debit += $amount;
        } else {
            $credit += $amount;
        }

        $ledger->debit = $debit;
        $ledger->credit = $credit;
        $ledger->balance = $debit - $credit;
        $ledger->save();

        return ['debit' => $debit, 'credit' => $credit, 'balance' => $ledger->balance];
    }
}
