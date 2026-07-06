@include('backend.layouts.partials.ajax_autocomplete')
<script>
(function () {
    if (window.StockoutAutocomplete) {
        return;
    }

    window.StockoutAutocomplete = {
        initGoodsLine: function ($wrapper) {
            attachAjaxAutocomplete({
                $input: $wrapper.find('.item_search'),
                $hidden: $wrapper.find('.item_id'),
                url: @json(route('admin.autocomplete.items')),
                minChars: 1,
                onSelect: function (item) {
                    $wrapper.closest('tr').find('input[name="cost_price[]"]').val(item.cump ?? 0);
                }
            });
        },

        initMaterialLine: function ($wrapper, materials) {
            var items = (materials || []).map(function (m) {
                return { id: m.id, text: m.name };
            });
            attachLocalAutocomplete({
                $input: $wrapper.find('.material_search'),
                $hidden: $wrapper.find('.material_id'),
                items: items
            });
        },

        initRawMaterialLine: function ($wrapper, foods) {
            var items = (foods || []).map(function (m) {
                return { id: m.id, text: m.name };
            });
            attachLocalAutocomplete({
                $input: $wrapper.find('.food_search'),
                $hidden: $wrapper.find('.r_material_id'),
                items: items
            });
        },

        initFinishedGoodsLine: function ($wrapper) {
            attachAjaxAutocomplete({
                $input: $wrapper.find('.item_search'),
                $hidden: $wrapper.find('.item_id'),
                url: @json(route('admin.finished-goods-stockouts.items-search')),
                minChars: 1,
                extraParams: function () {
                    var storeType = $('#store_type').val();
                    var storeId = storeType === 'SM'
                        ? $('#finished_g_sm_store_id').val()
                        : $('#finished_g_store_id').val();
                    return { store_type: storeType, store_id: storeId, limit: 20 };
                },
                validateBeforeSearch: function () {
                    var storeType = $('#store_type').val();
                    var storeId = storeType === 'SM'
                        ? $('#finished_g_sm_store_id').val()
                        : $('#finished_g_store_id').val();
                    return !!(storeType && storeId);
                },
                filterResults: function (results) {
                    return (results || []).map(function (r) {
                        return {
                            id: r.id,
                            text: r.label || r.text || '',
                            cump: r.cump
                        };
                    });
                },
                onSelect: function (item) {
                    $wrapper.closest('tr').find('input[name="cost_price[]"]').val(item.cump ?? 0);
                }
            });
        },

        initExistingRows: function (inputSelector, initFn) {
            $('.autocomplete-wrapper').each(function () {
                if ($(this).find(inputSelector).length) {
                    initFn($(this));
                }
            });
        }
    };
})();
</script>
