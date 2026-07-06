<?php

namespace App\Http\Controllers\Backend\Concerns;

use Illuminate\Support\Facades\DB;

trait GuardsStockApproval
{
    /**
     * @param  object|null  $document  Header model with a status field
     */
    protected function stockDocumentAlreadyApproved($document): bool
    {
        return $document && (int) ($document->status ?? 0) === 4;
    }

    /**
     * Roll back an open transaction and flash an error when status is already approved.
     */
    protected function abortIfStockDocumentAlreadyApproved($document, ?string $message = null): bool
    {
        if (!$this->stockDocumentAlreadyApproved($document)) {
            return false;
        }

        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        session()->flash(
            'error',
            $message ?? __('messages.stock_document_already_approved')
        );

        return true;
    }

    /**
     * @param  object|null  $header  Inventory header with a status field
     */
    protected function abortIfInventoryAlreadyValidated($header, int $validatedStatus = 2, ?string $message = null): bool
    {
        if (!$header || (int) ($header->status ?? 0) !== $validatedStatus) {
            return false;
        }

        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        session()->flash(
            'error',
            $message ?? __('messages.inventory_already_validated')
        );

        return true;
    }
}
