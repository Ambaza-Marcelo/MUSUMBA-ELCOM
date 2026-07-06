{{-- Partial: bouton "Modifier le fournisseur" + modal. Affiche le bouton seulement si @can('supplier.edit').
    Variables attendues:
    - $updateSupplierRoute: route('admin.xxx.update-supplier', $identifier)
    - $currentSupplierId: id du fournisseur actuel (pour pré-sélection)
    - $suppliers: collection Supplier (liste déroulante)
    - $modalId: (optionnel) id du modal, défaut 'updateSupplierModal'
    - $entityLabel: (optionnel) libellé pour le titre du modal, ex. "cette demande d'achat"
--}}
@php $modalId = $modalId ?? 'updateSupplierModal'; $entityLabel = $entityLabel ?? 'cet enregistrement'; @endphp
@if(Auth::guard('admin')->check() && Auth::guard('admin')->user()->can('supplier.edit'))
<button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#{{ $modalId }}" title="@lang('Modifier le fournisseur')">
    <i class="fa fa-edit"></i> @lang('Modifier le fournisseur')
</button>
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="{{ $updateSupplierRoute }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}Label">@lang('Modifier le fournisseur')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">@lang('Choisissez le nouveau fournisseur pour') {{ $entityLabel }}.</p>
                    <div class="form-group">
                        <label for="supplier_id_{{ $modalId }}">@lang('messages.supplier') <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplier_id_{{ $modalId }}" class="form-control" required>
                            <option value="">-- @lang('Choisir un fournisseur') --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ (old('supplier_id', $currentSupplierId) == $supplier->id) ? 'selected' : '' }}>{{ $supplier->supplier_name ?? $supplier->name ?? $supplier->id }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <span class="text-danger small">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">@lang('messages.cancel')</button>
                    <button type="submit" class="btn btn-primary">@lang('messages.update')</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
