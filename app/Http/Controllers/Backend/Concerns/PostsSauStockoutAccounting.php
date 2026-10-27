<?php

namespace App\Http\Controllers\Backend\Concerns;

use App\Models\Accounting\AccountJournalEntryDetail;
use App\Models\Material;
use App\Services\Material\MaterialFlowOrchestrator;
use Carbon\Carbon;
use RuntimeException;

/**
 * Flux SAU (Sortie Autre), type par défaut de toute sortie directe de stock :
 *  - Débit  : compte par défaut de la fiche article (Material / RawMaterial / Item)
 *  - Crédit : compte stock du magasin, valorisé au CUMP
 *
 * Les flux SN (Sortie Normale, réservée à la facturation / bon de livraison) et
 * ST (Sortie Transfert, générée par le transfert entre stocks) ne sont ni acceptés
 * ni comptabilisés par les contrôleurs de sortie directe.
 *
 * Le contrôleur hôte doit exposer updateLedgerTotals($accountId, $type, $amount).
 */
trait PostsSauStockoutAccounting
{
    protected function defaultStockoutMovementType(): string
    {
        return 'SAU';
    }

    /**
     * Type de sortie normalisé : SAU si non renseigné.
     */
    protected function resolveStockoutMovementType($type): string
    {
        $type = strtoupper(trim((string) $type));

        return $type !== '' ? $type : $this->defaultStockoutMovementType();
    }

    protected function isSauMovement($type): bool
    {
        return $this->resolveStockoutMovementType($type) === 'SAU';
    }

    /**
     * Types interdits en sortie directe : SN (facturation) et ST (transfert entre stocks).
     */
    protected function isExcludedStockoutMovement($type): bool
    {
        return in_array(strtoupper(trim((string) $type)), ['SN', 'ST'], true);
    }

    protected function excludedStockoutMovementMessage($type): string
    {
        if (strtoupper(trim((string) $type)) === 'ST') {
            return 'La sortie transfert (ST) est enregistrée automatiquement lors du transfert entre deux stocks. '
                . 'Modifiez le type de sortie de ce document.';
        }

        return 'La sortie normale (SN) est réservée à la facturation : elle est enregistrée '
            . 'à la validation du bon de livraison de la facture. Modifiez le type de sortie de ce document.';
    }

    /**
     * Compte à débiter pour une sortie SAU, lu sur la fiche de l'article.
     *
     * @throws RuntimeException si la fiche n'a pas de compte par défaut
     */
    protected function sauDebitAccountId($article): int
    {
        if (!$article) {
            throw new RuntimeException('Article introuvable sur une ligne de sortie : la sortie SAU est bloquée.');
        }

        if ($article instanceof Material) {
            return app(MaterialFlowOrchestrator::class)->resolveStockoutDebitAccountId($article);
        }

        $accountId = (int) ($article->default_account_id ?? 0);
        if ($accountId <= 0) {
            throw new RuntimeException(
                'Compte comptable par défaut manquant sur la fiche de l\'article « '
                . ($article->name ?? ('#' . $article->id)) . ' ». '
                . 'Renseignez-le avant de valider une sortie SAU.'
            );
        }

        return $accountId;
    }

    /**
     * Vérifie que chaque article dispose de son compte SAU. Retourne le message d'erreur ou null.
     */
    protected function sauMissingAccountMessage(iterable $articles): ?string
    {
        foreach ($articles as $article) {
            try {
                $this->sauDebitAccountId($article);
            } catch (RuntimeException $e) {
                return $e->getMessage();
            }
        }

        return null;
    }

    /**
     * Écritures SAU : crédit du compte stock (total) + débit du compte de chaque article.
     *
     * @param  array<int, array{article: mixed, amount: float}>  $lines  valeurs au CUMP
     * @throws RuntimeException si un compte article manque (la transaction appelante doit être annulée)
     */
    protected function postSauStockoutEntries(int $stockAccountId, array $lines, $date, string $documentNo, ?string $description): float
    {
        $debits = [];
        $labels = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }

            $accountId = $this->sauDebitAccountId($line['article'] ?? null);
            $debits[$accountId] = ($debits[$accountId] ?? 0) + $amount;
            $labels[$accountId][] = $line['article']->name ?? '';
            $total += $amount;
        }

        $total = round($total, 2);
        if ($total <= 0) {
            return 0.0;
        }

        $now = Carbon::now();
        $description = (string) ($description ?? '');

        $stockLedgerState = $this->updateLedgerTotals($stockAccountId, 'credit', $total);
        $entries = [[
            'date' => $date,
            'document_no' => $documentNo,
            'description' => $description,
            'account_id' => $stockAccountId,
            'libelle' => $description,
            'credit' => $total,
            'balance' => $stockLedgerState['balance'],
            'created_at' => $now,
        ]];

        foreach ($debits as $accountId => $amount) {
            $amount = round($amount, 2);
            $ledgerState = $this->updateLedgerTotals($accountId, 'debit', $amount);
            $articleNames = implode(', ', array_unique(array_filter($labels[$accountId])));

            $entries[] = [
                'date' => $date,
                'document_no' => $documentNo,
                'description' => $description,
                'account_id' => $accountId,
                'libelle' => trim($description . ($articleNames !== '' ? ' - ' . $articleNames : '')),
                'debit' => $amount,
                'balance' => $ledgerState['balance'],
                'created_at' => $now,
            ];
        }

        foreach ($entries as $entry) {
            AccountJournalEntryDetail::insert([$entry]);
        }

        return $total;
    }
}
