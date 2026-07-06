<script type="text/javascript">
(function () {
    var itemsSearchUrl = {!! json_encode(route('admin.production-plan.items-search')) !!};

    var itemDetails = [
        @foreach($itemDetails as $itemDetail)
        {
            item_code: {!! json_encode($itemDetail->code) !!},
            r_material_id: {{ $itemDetail->r_material_id ?? 0 }},
            raw_material_name: {!! json_encode($itemDetail->rawMaterial ? $itemDetail->rawMaterial->name . ' (' . ($itemDetail->rawMaterial->rawMaterialMeasurement ? $itemDetail->rawMaterial->rawMaterialMeasurement->purchase_unit : 'N/A') . ')' : 'N/A') !!},
            quantity: {{ $itemDetail->quantity ?? 0 }},
            unit: {!! json_encode($itemDetail->rawMaterial && $itemDetail->rawMaterial->rawMaterialMeasurement ? $itemDetail->rawMaterial->rawMaterialMeasurement->purchase_unit : '') !!},
            raw_material_cump: {{ $itemDetail->rawMaterial ? ($itemDetail->rawMaterial->cump ?? 0) : 0 }}
        }@if(!$loop->last),@endif
        @endforeach
    ];

    var rawMaterialItems = [
        @foreach($raw_materials as $raw_material)
        {
            id: {{ $raw_material->id }},
            text: {!! json_encode($raw_material->name . ' (' . ($raw_material->rawMaterialMeasurement ? $raw_material->rawMaterialMeasurement->purchase_unit : 'N/A') . ')') !!},
            unit: {!! json_encode($raw_material->rawMaterialMeasurement ? $raw_material->rawMaterialMeasurement->purchase_unit : '') !!},
            cump: {{ $raw_material->cump ?? 0 }}
        }@if(!$loop->last),@endif
        @endforeach
    ];

    function escAttr(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function initItemAutocomplete($wrapper) {
        var $input = $wrapper.find('.item_search').first();
        var $hidden = $wrapper.find('.item_id').first();
        var $codeHidden = $wrapper.find('.item_code').first();

        if (!$input.length || $input.data('ppItemAc')) {
            return;
        }
        $input.data('ppItemAc', true);

        if (typeof attachAjaxAutocomplete !== 'function') {
            return;
        }

        attachAjaxAutocomplete({
            $input: $input,
            $hidden: $hidden,
            url: itemsSearchUrl,
            minChars: 1,
            extraParams: { limit: 25 },
            filterResults: function (results) {
                return (results || []).map(function (item) {
                    return {
                        id: item.id,
                        text: item.label || item.text || '',
                        code: item.code || '',
                        cump: item.cump || 0
                    };
                });
            },
            renderItem: function (item) {
                return '<div><strong>' + escAttr(item.text) + '</strong></div>';
            },
            onSelect: function (item) {
                $codeHidden.val(item.code || '');
                var $row = $wrapper.closest('tr');
                var $qtyInput = $row.find('input[name="item_quantity[]"]');
                var qty = parseFloat($qtyInput.val() || 0);
                if (!$qtyInput.val() || isNaN(qty) || qty <= 0) {
                    $qtyInput.val(1);
                }
                recalculateAllRawMaterials();
                calculateTotalCost();
            }
        });

        $input.on('input.ppItemClear', function () {
            if (!String($(this).val() || '').trim()) {
                $hidden.val('');
                $codeHidden.val('');
                recalculateAllRawMaterials();
            }
        });
    }

    function initRawMaterialAutocomplete($wrapper) {
        var $input = $wrapper.find('.raw_material_search').first();
        var $hidden = $wrapper.find('.r_material_id').first();

        if (!$input.length || $input.data('ppRmAc')) {
            return;
        }
        $input.data('ppRmAc', true);

        if (typeof attachLocalAutocomplete !== 'function') {
            return;
        }

        attachLocalAutocomplete({
            $input: $input,
            $hidden: $hidden,
            items: rawMaterialItems,
            minChars: 1,
            renderItem: function (item) {
                return '<div><strong>' + escAttr(item.text) + '</strong></div>';
            },
            onSelect: function (item) {
                var $row = $wrapper.closest('tr');
                $row.find('input[name="unit[]"]').val(item.unit || '');
                var $costInput = $row.find('input[name="material_cost[]"]');
                if (!$costInput.val() || $costInput.val() === '0') {
                    $costInput.val(item.cump || 0);
                }
                calculateRowTotal($row, 'material');
            }
        });
    }

    function initAllAutocompletes($root) {
        ($root || $(document)).find('.autocomplete-wrapper').each(function () {
            var $wrapper = $(this);
            if ($wrapper.find('.item_search').length) {
                initItemAutocomplete($wrapper);
            }
            if ($wrapper.find('.raw_material_search').length) {
                initRawMaterialAutocomplete($wrapper);
            }
        });
    }

    function recalculateAllRawMaterials() {
        var materialsMap = {};

        $('#finishedGoodsTable tr').each(function () {
            if ($(this).find('th').length > 0) {
                return;
            }

            var itemId = $(this).find('.item_id').val();
            var itemCode = $(this).find('.item_code').val();
            var itemQuantity = parseFloat($(this).find('input[name="item_quantity[]"]').val() || 0);

            if (itemId && itemCode) {
                var details = itemDetails.filter(function (detail) {
                    var detailCode = String(detail.item_code || '').trim();
                    var searchCode = String(itemCode || '').trim();
                    return detailCode.toLowerCase() === searchCode.toLowerCase();
                });

                details.forEach(function (detail) {
                    if (detail.r_material_id && detail.r_material_id > 0) {
                        var rMaterialId = parseInt(detail.r_material_id, 10);
                        var requiredQuantity = itemQuantity * (parseFloat(detail.quantity) || 0);

                        if (materialsMap[rMaterialId]) {
                            materialsMap[rMaterialId].quantity += requiredQuantity;
                        } else {
                            materialsMap[rMaterialId] = {
                                quantity: requiredQuantity,
                                raw_material_name: detail.raw_material_name || '',
                                unit: detail.unit || '',
                                raw_material_cump: detail.raw_material_cump || 0
                            };
                        }
                    }
                });
            }
        });

        $('#rawMaterialsTable tbody tr').remove();

        Object.keys(materialsMap).forEach(function (rMaterialId) {
            var material = materialsMap[rMaterialId];
            var markup = '<tr>' +
                '<td>' +
                    '<div class="autocomplete-wrapper">' +
                        '<input type="text" class="form-control raw_material_search" value="' + escAttr(material.raw_material_name) + '" placeholder="@lang('messages.type_to_search_raw_material')" autocomplete="off" required>' +
                        '<input type="hidden" name="r_material_id[]" class="r_material_id" value="' + escAttr(rMaterialId) + '">' +
                    '</div>' +
                '</td>' +
                '<td><input type="number" name="material_quantity[]" value="' + escAttr(material.quantity.toFixed(2)) + '" class="form-control" min="0" step="any" required></td>' +
                '<td><input type="text" name="unit[]" value="' + escAttr(material.unit || '') + '" class="form-control"></td>' +
                '<td><input type="number" name="material_cost[]" value="' + escAttr(material.raw_material_cump) + '" class="form-control" min="0" step="any" required></td>' +
                '<td><button type="button" class="btn btn-danger btn-sm remove-tr">@lang('messages.Delete')</button></td>' +
            '</tr>';
            var $newRow = $(markup);
            $('#rawMaterialsTable tbody').append($newRow);
            initRawMaterialAutocomplete($newRow.find('.autocomplete-wrapper'));
        });

        calculateTotalCost();
    }

    function calculateRowTotal(row, type) {
        if (type === 'item') {
            recalculateAllRawMaterials();
        }
        calculateTotalCost();
    }

    function calculateTotalCost() {
        var laborCost = parseFloat($('#estimated_labor_cost').val() || 0);
        var overheadCost = parseFloat($('#estimated_overhead_cost').val() || 0);
        var materialCost = 0;

        $('input[name="material_quantity[]"]').each(function () {
            var qty = parseFloat($(this).val() || 0);
            var cost = parseFloat($(this).closest('tr').find('input[name="material_cost[]"]').val() || 0);
            materialCost += qty * cost;
        });

        var total = laborCost + materialCost + overheadCost;
        $('#total_estimated_cost').val(total.toFixed(2));

        var totalFinishedGoodsQuantity = 0;
        $('#finishedGoodsTable tr').each(function () {
            if ($(this).find('th').length > 0) {
                return;
            }
            var itemId = $(this).find('.item_id').val();
            if (itemId) {
                totalFinishedGoodsQuantity += parseFloat($(this).find('input[name="item_quantity[]"]').val() || 0);
            }
        });

        if (totalFinishedGoodsQuantity > 0 && total > 0) {
            $('#finishedGoodsTable tr').each(function () {
                if ($(this).find('th').length > 0) {
                    return;
                }
                var itemId = $(this).find('.item_id').val();
                if (itemId) {
                    var qty = parseFloat($(this).find('input[name="item_quantity[]"]').val() || 0);
                    if (qty > 0) {
                        $(this).find('input[name="cost_price[]"]').val(total.toFixed(2));
                    }
                }
            });
        }
    }

    var finishedGoodsCount = 0;
    $(document).on('click', '.add-finished-btn', function () {
        finishedGoodsCount += 1;
        var markup = '<tr>' +
            '<td>' +
                '<div class="autocomplete-wrapper">' +
                    '<input type="text" class="form-control item_search" placeholder="@lang('messages.search_finished_product')" autocomplete="off" required>' +
                    '<input type="hidden" name="item_id[]" class="item_id">' +
                    '<input type="hidden" class="item_code" value="">' +
                '</div>' +
            '</td>' +
            '<td><input type="number" name="item_quantity[]" placeholder="@lang('messages.quantity_placeholder')" class="form-control" min="0" step="any" required></td>' +
            '<td><input type="number" name="cost_price[]" placeholder="@lang('messages.cost_price_placeholder')" class="form-control" min="0" step="any"></td>' +
            '<td><button type="button" class="btn btn-danger remove-tr">@lang('messages.Delete')</button></td>' +
        '</tr>';
        var $newRow = $(markup);
        $('#finishedGoodsTable').append($newRow);
        initItemAutocomplete($newRow.find('.autocomplete-wrapper'));
    });

    $('#add_raw_material').on('click', function () {
        var markup = '<tr>' +
            '<td>' +
                '<div class="autocomplete-wrapper">' +
                    '<input type="text" class="form-control raw_material_search" placeholder="@lang('messages.type_to_search_raw_material')" autocomplete="off" required>' +
                    '<input type="hidden" name="r_material_id[]" class="r_material_id">' +
                '</div>' +
            '</td>' +
            '<td><input type="number" name="material_quantity[]" placeholder="@lang('messages.quantity_placeholder')" class="form-control" min="0" step="any" required></td>' +
            '<td><input type="text" name="unit[]" placeholder="@lang('messages.Unit')" class="form-control"></td>' +
            '<td><input type="number" name="material_cost[]" placeholder="@lang('messages.Cost')" class="form-control" min="0" step="any" required></td>' +
            '<td><button type="button" class="btn btn-danger btn-sm remove-tr">@lang('messages.Delete')</button></td>' +
        '</tr>';
        var $newRow = $(markup);
        $('#rawMaterialsTable tbody').append($newRow);
        initRawMaterialAutocomplete($newRow.find('.autocomplete-wrapper'));
    });

    $(document).on('click', '.remove-tr', function () {
        var $row = $(this).closest('tr');
        if ($row.closest('#finishedGoodsTable').length) {
            $row.remove();
            recalculateAllRawMaterials();
        } else {
            $row.remove();
        }
        calculateTotalCost();
    });

    $(document).on('input', '#estimated_labor_cost, #estimated_overhead_cost', calculateTotalCost);
    $(document).on('input', 'input[name="material_quantity[]"], input[name="material_cost[]"]', function () {
        calculateRowTotal($(this).closest('tr'), 'material');
    });
    $(document).on('input', 'input[name="item_quantity[]"]', function () {
        recalculateAllRawMaterials();
        calculateRowTotal($(this).closest('tr'), 'item');
    });
    $(document).on('input', 'input[name="cost_price[]"]', function () {
        calculateRowTotal($(this).closest('tr'), 'item');
    });

    $('#production_plan_form').on('submit', function (e) {
        if (!confirm({!! json_encode($productionPlanConfirmMessage) !!})) {
            e.preventDefault();
            return false;
        }
    });

    $(document).ready(function () {
        initAllAutocompletes();
        @if(!empty($recalculateOnLoad))
        recalculateAllRawMaterials();
        @endif
    });
})();
</script>
