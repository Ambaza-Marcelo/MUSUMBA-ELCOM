@php
    $hasMaterials = $hasMaterials ?? false;
    $hasGoodsItems = $hasGoodsItems ?? ($hasItems ?? false);
    $hasFinishedGoodsItems = $hasFinishedGoodsItems ?? false;
@endphp

@if($hasMaterials || $hasGoodsItems || $hasFinishedGoodsItems)
<div class="col-md-8">
    <div class="alert alert-info mb-0 py-2">
        <small>@lang('messages.reception_stock_auto_info')</small>
    </div>
</div>
@endif
