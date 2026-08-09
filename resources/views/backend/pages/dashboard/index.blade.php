
@extends('backend.layouts.master')

@section('title')
@lang('messages.dashboard') - @lang('messages.admin_panel')
@endsection

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --plant-steel: #1c2636;
        --plant-slate: #2a3648;
        --plant-concrete: #8b919a;
        --plant-dust: #d4d0c8;
        --plant-sand: #ebe7df;
        --plant-amber: #e0891a;
        --plant-amber-hot: #f5a623;
        --plant-teal: #1a6b63;
        --plant-teal-bright: #24968a;
        --plant-danger: #c45c4a;
        --plant-ok: #3d8b6e;
        --plant-ink: #1a2332;
        --plant-muted: #5c6675;
        --plant-card: #ffffff;
        --plant-line: rgba(28, 38, 54, 0.08);
        --plant-glow: rgba(224, 137, 26, 0.28);
    }

    .plant-dash {
        font-family: 'IBM Plex Sans', sans-serif;
        color: var(--plant-ink);
        padding-bottom: 2.5rem;
    }

    .plant-dash h1, .plant-dash h2, .plant-dash h3, .plant-dash h4, .plant-dash h5,
    .plant-dash .plant-display {
        font-family: 'Barlow Condensed', sans-serif;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    /* Hero — usine / béton */
    .plant-hero {
        position: relative;
        overflow: hidden;
        border-radius: 4px;
        margin-bottom: 1.5rem;
        background:
            linear-gradient(115deg, rgba(28, 38, 54, 0.94) 0%, rgba(42, 54, 72, 0.88) 48%, rgba(26, 107, 99, 0.75) 100%),
            repeating-linear-gradient(
                -12deg,
                transparent,
                transparent 18px,
                rgba(255, 255, 255, 0.03) 18px,
                rgba(255, 255, 255, 0.03) 19px
            ),
            radial-gradient(ellipse at 80% 20%, rgba(224, 137, 26, 0.35), transparent 50%);
        color: #fff;
        padding: 1.75rem 1.75rem 1.5rem;
        box-shadow: 0 18px 40px rgba(28, 38, 54, 0.22);
        animation: plantHeroIn 0.7s ease-out both;
    }

    .plant-hero::before {
        content: '';
        position: absolute;
        right: -40px;
        bottom: -60px;
        width: 220px;
        height: 320px;
        background: linear-gradient(180deg, rgba(212, 208, 200, 0.18), rgba(139, 145, 154, 0.08));
        border-radius: 8px 8px 40% 40%;
        transform: rotate(18deg);
        pointer-events: none;
    }

    .plant-hero::after {
        content: '';
        position: absolute;
        right: 90px;
        bottom: -40px;
        width: 140px;
        height: 260px;
        background: linear-gradient(180deg, rgba(235, 231, 223, 0.22), rgba(139, 145, 154, 0.06));
        border-radius: 6px 6px 35% 35%;
        transform: rotate(18deg);
        pointer-events: none;
        opacity: 0.85;
    }

    .plant-hero-inner {
        position: relative;
        z-index: 1;
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1.25rem;
    }

    .plant-hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--plant-amber-hot);
        margin-bottom: 0.45rem;
    }

    .plant-hero-logo {
        width: 72px;
        height: 72px;
        object-fit: contain;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.96);
        border: 2px solid rgba(245, 166, 35, 0.55);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
        padding: 4px;
        margin-bottom: 0.85rem;
    }

    .plant-hero-kicker .pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--plant-amber-hot);
        box-shadow: 0 0 0 0 var(--plant-glow);
        animation: plantPulse 1.8s ease-out infinite;
    }

    .plant-hero h1 {
        font-size: clamp(1.7rem, 3.2vw, 2.45rem);
        font-weight: 700;
        margin: 0 0 0.35rem;
        line-height: 1.05;
        color: #fff;
    }

    .plant-hero p {
        margin: 0;
        max-width: 34rem;
        color: rgba(235, 231, 223, 0.88);
        font-size: 0.95rem;
        line-height: 1.45;
        text-transform: none;
        font-family: 'IBM Plex Sans', sans-serif;
        letter-spacing: 0;
    }

    .plant-year-form {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 4px;
        padding: 0.55rem 0.85rem;
        backdrop-filter: blur(6px);
    }

    .plant-year-form label {
        margin: 0;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--plant-dust);
        white-space: nowrap;
    }

    .plant-year-form select {
        background: transparent;
        border: none;
        color: #fff;
        font-weight: 600;
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 1.15rem;
        min-width: 5rem;
        outline: none;
        cursor: pointer;
    }

    .plant-year-form select option {
        color: var(--plant-ink);
    }

    /* KPI strip */
    .plant-kpi-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.9rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 991px) {
        .plant-kpi-row { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 575px) {
        .plant-kpi-row { grid-template-columns: 1fr; }
    }

    .plant-kpi {
        position: relative;
        background: var(--plant-card);
        border-radius: 4px;
        padding: 1.1rem 1.15rem 1rem;
        border: 1px solid var(--plant-line);
        box-shadow: 0 8px 24px rgba(28, 38, 54, 0.06);
        overflow: hidden;
        animation: plantRise 0.55s ease-out both;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .plant-kpi:nth-child(1) { animation-delay: 0.05s; }
    .plant-kpi:nth-child(2) { animation-delay: 0.12s; }
    .plant-kpi:nth-child(3) { animation-delay: 0.19s; }
    .plant-kpi:nth-child(4) { animation-delay: 0.26s; }

    .plant-kpi:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 28px rgba(28, 38, 54, 0.1);
    }

    .plant-kpi::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--plant-amber);
    }

    .plant-kpi.teal::before { background: var(--plant-teal); }
    .plant-kpi.steel::before { background: var(--plant-steel); }
    .plant-kpi.ok::before { background: var(--plant-ok); }

    .plant-kpi-label {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--plant-muted);
        margin-bottom: 0.35rem;
    }

    .plant-kpi-value {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        color: var(--plant-steel);
        margin: 0;
    }

    .plant-kpi-hint {
        margin: 0.4rem 0 0;
        font-size: 0.78rem;
        color: var(--plant-concrete);
    }

    .plant-kpi-icon {
        position: absolute;
        right: 0.9rem;
        top: 0.9rem;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        background: var(--plant-sand);
        color: var(--plant-steel);
        font-size: 1rem;
        opacity: 0.9;
    }

    /* Section panels */
    .plant-panel {
        background: var(--plant-card);
        border-radius: 4px;
        border: 1px solid var(--plant-line);
        box-shadow: 0 10px 28px rgba(28, 38, 54, 0.06);
        margin-bottom: 1.25rem;
        overflow: hidden;
        animation: plantRise 0.6s ease-out both;
    }

    .plant-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.95rem 1.25rem;
        background: linear-gradient(90deg, var(--plant-steel) 0%, var(--plant-slate) 70%, #1a6b63 140%);
        color: #fff;
    }

    .plant-panel-head.amber {
        background: linear-gradient(90deg, var(--plant-steel) 0%, #3d3428 55%, #8a5a12 130%);
    }

    .plant-panel-head.teal {
        background: linear-gradient(90deg, var(--plant-steel) 0%, #1e3d45 50%, var(--plant-teal) 130%);
    }

    .plant-panel-head h5 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.55rem;
    }

    .plant-panel-head h5 i {
        color: var(--plant-amber-hot);
    }

    .plant-panel-body {
        padding: 1.25rem;
        background:
            linear-gradient(180deg, #faf9f7 0%, #ffffff 40%);
    }

    /* Metric cards inside profitability */
    .plant-metric {
        height: 100%;
        border-radius: 4px;
        border: 1px solid var(--plant-line);
        background: #fff;
        padding: 1.15rem 1.2rem;
        position: relative;
        overflow: hidden;
    }

    .plant-metric::after {
        content: '';
        position: absolute;
        right: -20px;
        top: -20px;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(26, 107, 99, 0.06);
    }

    .plant-metric h5 {
        font-size: 1.05rem;
        color: var(--plant-teal);
        margin-bottom: 0.85rem;
    }

    .plant-metric .muted {
        margin: 0;
        font-size: 0.78rem;
        color: var(--plant-muted);
        text-transform: none;
        font-family: 'IBM Plex Sans', sans-serif;
        letter-spacing: 0;
        font-weight: 500;
    }

    .plant-metric .amount {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 1.55rem;
        font-weight: 700;
        margin: 0.15rem 0 0.65rem;
        color: var(--plant-steel);
        letter-spacing: 0.01em;
    }

    .plant-metric .amount.danger { color: var(--plant-danger); }
    .plant-metric .amount.amber { color: var(--plant-amber); }
    .plant-metric .amount.teal { color: var(--plant-teal); }

    .plant-metric hr {
        border-top-color: var(--plant-line);
        margin: 0.75rem 0;
    }

    .plant-badge {
        display: inline-block;
        padding: 0.2rem 0.55rem;
        border-radius: 3px;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 0.4rem;
    }

    .plant-badge.cash {
        background: rgba(61, 139, 110, 0.12);
        color: var(--plant-ok);
    }

    .plant-badge.credit {
        background: rgba(224, 137, 26, 0.15);
        color: #a65f0a;
    }

    /* Process flow */
    .plant-flow {
        display: flex;
        align-items: stretch;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .plant-step {
        flex: 1;
        min-width: 110px;
        text-align: center;
        text-decoration: none !important;
        color: var(--plant-steel);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0.35rem;
        transition: transform 0.2s ease, color 0.2s ease;
    }

    .plant-step:hover {
        transform: translateY(-4px);
        color: var(--plant-teal);
        text-decoration: none !important;
    }

    .plant-step:hover .plant-step-icon {
        border-color: var(--plant-amber);
        background: linear-gradient(145deg, #fff 0%, var(--plant-sand) 100%);
        box-shadow: 0 8px 20px var(--plant-glow);
        color: var(--plant-amber);
    }

    .plant-step-icon {
        position: relative;
        width: 68px;
        height: 68px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        border: 2px solid var(--plant-teal);
        background: linear-gradient(145deg, #ffffff 0%, #f3f1ec 100%);
        font-size: 1.45rem;
        color: var(--plant-teal);
        margin-bottom: 0.55rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease, background 0.2s ease;
    }

    .plant-step-badge {
        position: absolute;
        top: -7px;
        right: -7px;
        min-width: 22px;
        height: 22px;
        line-height: 16px;
        padding: 2px 6px;
        border-radius: 11px;
        font-size: 11px;
        font-weight: 700;
        background: var(--plant-danger) !important;
        border: 2px solid #fff;
    }

    .plant-step-badge.warn {
        background: var(--plant-amber) !important;
        color: #1c2636 !important;
    }

    .plant-step-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--plant-ink);
        line-height: 1.3;
        font-family: 'IBM Plex Sans', sans-serif;
        text-transform: none;
        letter-spacing: 0;
    }

    .plant-arrow {
        color: var(--plant-concrete);
        font-size: 1.2rem;
        align-self: center;
        flex-shrink: 0;
        padding-bottom: 1.6rem;
    }

    .plant-note {
        background: linear-gradient(90deg, rgba(26, 107, 99, 0.08), rgba(224, 137, 26, 0.06));
        border-left: 3px solid var(--plant-teal);
        padding: 0.7rem 1rem;
        margin-bottom: 1rem;
        font-size: 0.82rem;
        color: var(--plant-muted);
        border-radius: 0 4px 4px 0;
    }

    .plant-source-links {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px dashed rgba(28, 38, 54, 0.12);
    }

    .plant-source-link {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.85rem;
        border-radius: 3px;
        background: var(--plant-sand);
        color: var(--plant-steel);
        text-decoration: none !important;
        font-size: 0.78rem;
        font-weight: 600;
        border: 1px solid transparent;
        transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    }

    .plant-source-link:hover {
        background: #fff;
        border-color: var(--plant-teal);
        color: var(--plant-teal);
    }

    .plant-source-badge {
        min-width: 20px;
        height: 20px;
        line-height: 16px;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        background: var(--plant-amber) !important;
        color: #1c2636 !important;
    }

    .page-title-area {
        background: transparent !important;
        margin-bottom: 0.5rem;
    }

    @keyframes plantHeroIn {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes plantRise {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes plantPulse {
        0% { box-shadow: 0 0 0 0 rgba(245, 166, 35, 0.55); }
        70% { box-shadow: 0 0 0 10px rgba(245, 166, 35, 0); }
        100% { box-shadow: 0 0 0 0 rgba(245, 166, 35, 0); }
    }

    @media (max-width: 767px) {
        .plant-arrow { display: none; }
        .plant-hero { padding: 1.25rem; }
    }
</style>
@endsection


@section('admin-content')
<div class="plant-dash">
<div class="page-title-area">
    <div class="row align-items-center">
        <div class="col-sm-6">
            <div class="breadcrumbs-area clearfix">
                <h4 class="page-title pull-left plant-display">@lang('messages.dashboard')</h4>
                <ul class="breadcrumbs pull-left">
                    <li><a href="{{ route('admin.dashboard') }}">@lang('messages.home')</a></li>
                    <li><span>@lang('messages.dashboard')</span></li>
                </ul>
            </div>
        </div>
        <div class="col-sm-6 clearfix">
            @include('backend.layouts.partials.logout')
        </div>
    </div>
</div>

@if (Auth::guard('admin')->user()->can('raw_material_order_client.view') || Auth::guard('admin')->user()->can('material_big_store.view') ||
Auth::guard('admin')->user()->can('raw_material_big_store.view') || Auth::guard('admin')->user()->can('invoice.view') ||
Auth::guard('admin')->user()->can('material_purchase.view') || Auth::guard('admin')->user()->can('material_supplier_order.view') || Auth::guard('admin')->user()->can('material_reception.view') ||
Auth::guard('admin')->user()->can('raw_material_purchase.view') || Auth::guard('admin')->user()->can('raw_material_supplier_order.view') || Auth::guard('admin')->user()->can('raw_material_reception.view') ||
Auth::guard('admin')->user()->can('account_cash_ouflow_request.view') || Auth::guard('admin')->user()->can('account_cash_ouflow.view') || Auth::guard('admin')->user()->can('fund_transfer.view') ||
Auth::guard('admin')->user()->can('production_plan.view') || Auth::guard('admin')->user()->can('raw_material_requisition.view') || Auth::guard('admin')->user()->can('raw_material_transfer.view') || Auth::guard('admin')->user()->can('production_order.view') ||
Auth::guard('admin')->user()->can('proforma_invoice.view') || Auth::guard('admin')->user()->can('sales_order.view') || Auth::guard('admin')->user()->can('delivery_note.view'))
<div class="main-content-inner">
    <div class="row">
        <div class="col-lg-12">

            {{-- Hero usine --}}
            <div class="plant-hero">
                <div class="plant-hero-inner">
                    <div>
                        <img
                            src="{{ asset('img/logo.jpeg') }}"
                            alt="@lang('messages.musumba_elcom')"
                            class="plant-hero-logo"
                        >
                        <div class="plant-hero-kicker">
                            <span class="pulse-dot"></span>
                            @lang('messages.dashboard_plant_live')
                        </div>
                        <h1>@lang('messages.dashboard_plant_title')</h1>
                        <p>@lang('messages.dashboard_plant_subtitle')</p>
                    </div>
                    <form method="GET" action="{{ url()->current() }}" class="plant-year-form">
                        <label for="exercise">@lang('messages.fiscal_year')</label>
                        <select name="exercise" id="exercise" onchange="this.form.submit()">
                            @foreach($exercises as $exercise)
                                <option value="{{ $exercise }}" {{ $exercise == $selectedExercise ? 'selected' : '' }}>{{ $exercise }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>

            {{-- KPIs production --}}
            @if (Auth::guard('admin')->user()->can('production_plan.view') || Auth::guard('admin')->user()->can('production_order.view'))
            <div class="plant-kpi-row">
                <div class="plant-kpi">
                    <span class="plant-kpi-icon"><i class="fa fa-calendar"></i></span>
                    <div class="plant-kpi-label">@lang('messages.dashboard_kpi_plans_year')</div>
                    <p class="plant-kpi-value">{{ number_format($productionKpis['plans_year'] ?? 0, 0, ',', ' ') }}</p>
                    <p class="plant-kpi-hint">{{ $selectedExercise }}</p>
                </div>
                <div class="plant-kpi teal">
                    <span class="plant-kpi-icon"><i class="fa fa-industry"></i></span>
                    <div class="plant-kpi-label">@lang('messages.dashboard_kpi_orders_year')</div>
                    <p class="plant-kpi-value">{{ number_format($productionKpis['orders_year'] ?? 0, 0, ',', ' ') }}</p>
                    <p class="plant-kpi-hint">@lang('messages.dashboard_kpi_orders_year_hint')</p>
                </div>
                <div class="plant-kpi ok">
                    <span class="plant-kpi-icon"><i class="fa fa-check-circle"></i></span>
                    <div class="plant-kpi-label">@lang('messages.dashboard_kpi_orders_approved')</div>
                    <p class="plant-kpi-value">{{ number_format($productionKpis['orders_approved_year'] ?? 0, 0, ',', ' ') }}</p>
                    <p class="plant-kpi-hint">@lang('messages.dashboard_kpi_orders_approved_hint')</p>
                </div>
                <div class="plant-kpi steel">
                    <span class="plant-kpi-icon"><i class="fa fa-cogs"></i></span>
                    <div class="plant-kpi-label">@lang('messages.dashboard_kpi_pipeline')</div>
                    <p class="plant-kpi-value">{{ number_format($productionKpis['pipeline_pending'] ?? 0, 0, ',', ' ') }}</p>
                    <p class="plant-kpi-hint">@lang('messages.dashboard_kpi_pipeline_hint')</p>
                </div>
            </div>
            @endif

            {{-- Rentabilité --}}
            @if (Auth::guard('admin')->user()->can('raw_material_purchase.view') || Auth::guard('admin')->user()->can('material_purchase.view'))
            <div class="plant-panel" style="animation-delay: 0.1s;">
                <div class="plant-panel-head amber">
                    <h5><i class="fa fa-line-chart"></i> @lang('messages.profitability_dashboard')</h5>
                    <span style="font-size:0.75rem;opacity:0.85;letter-spacing:0.08em;text-transform:uppercase;">{{ $selectedExercise }}</span>
                </div>
                <div class="plant-panel-body">
                    <div class="row">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="plant-metric">
                                <h5>@lang('messages.company_profitability')</h5>
                                <p class="muted">@lang('messages.products_accounts_7')</p>
                                <p class="amount teal">{{ number_format($products,0,',',' ') }}</p>
                                <p class="muted">@lang('messages.charges_accounts_6')</p>
                                <p class="amount danger">{{ number_format($charges,0,',',' ') }}</p>
                                <hr>
                                <p class="muted font-weight-bold">@lang('messages.profit')</p>
                                <p class="amount amber" style="font-size:1.85rem;margin-bottom:0;">{{ number_format($profit,0,',',' ') }}</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="plant-metric">
                                <h5>@lang('messages.sales')</h5>
                                <span class="plant-badge cash">@lang('messages.cash')</span>
                                <p class="muted">@lang('messages.invoice_count')</p>
                                <p class="amount">{{ number_format($cashSales['count'],0,',',' ') }}</p>
                                <p class="muted">@lang('messages.total_amount')</p>
                                <p class="amount teal">{{ number_format($cashSales['sum'],0,',',' ') }}</p>
                                <hr>
                                <span class="plant-badge credit">@lang('messages.credit')</span>
                                <p class="muted">@lang('messages.invoice_count')</p>
                                <p class="amount">{{ number_format($creditSales['count'],0,',',' ') }}</p>
                                <p class="muted">@lang('messages.total_amount')</p>
                                <p class="amount amber" style="margin-bottom:0;">{{ number_format($creditSales['sum'],0,',',' ') }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="plant-metric">
                                <h5>@lang('messages.purchases_by_category')</h5>
                                <p class="muted font-weight-bold" style="color:var(--plant-teal);">@lang('messages.raw_materials')</p>
                                <p class="muted">@lang('messages.paid'): {{ number_format($rawMaterialPurchases['paid_count'],0,',',' ') }} ({{ number_format($rawMaterialPurchases['paid_sum'],0,',',' ') }})</p>
                                <p class="muted mb-2">@lang('messages.unpaid'): {{ number_format($rawMaterialPurchases['unpaid_count'],0,',',' ') }} ({{ number_format($rawMaterialPurchases['unpaid_sum'],0,',',' ') }})</p>
                                <hr>
                                <p class="muted font-weight-bold" style="color:var(--plant-teal);">@lang('messages.materials')</p>
                                <p class="muted">@lang('messages.paid'): {{ number_format($materialPurchases['paid_count'],0,',',' ') }} ({{ number_format($materialPurchases['paid_sum'],0,',',' ') }})</p>
                                <p class="muted" style="margin-bottom:0;">@lang('messages.unpaid'): {{ number_format($materialPurchases['unpaid_count'],0,',',' ') }} ({{ number_format($materialPurchases['unpaid_sum'],0,',',' ') }})</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Production process — cœur de l'usine --}}
            @if (Auth::guard('admin')->user()->can('production_plan.view') || Auth::guard('admin')->user()->can('raw_material_requisition.view') || Auth::guard('admin')->user()->can('raw_material_transfer.view') || Auth::guard('admin')->user()->can('production_order.view'))
            <div class="plant-panel" style="animation-delay: 0.15s;">
                <div class="plant-panel-head">
                    <h5><i class="fa fa-industry"></i> @lang('messages.dashboard_production_process_title')</h5>
                </div>
                <div class="plant-panel-body">
                    <div class="plant-flow">
                        <a href="{{ route('admin.production-plan.index') }}" class="plant-step" title="@lang('messages.Plan de production')">
                            <span class="plant-step-icon">
                                <i class="fa fa-calendar-check-o"></i>
                                @if(($productionProcess['production_plans'] ?? 0) > 0)
                                <span class="badge plant-step-badge" title="@lang('messages.dashboard_unapproved_pending')">{{ $productionProcess['production_plans'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.Plan de production')</span>
                        </a>
                        <i class="fa fa-long-arrow-right plant-arrow"></i>
                        <a href="{{ route('admin.raw-material-requisitions.index') }}" class="plant-step" title="@lang('messages.Requisition')">
                            <span class="plant-step-icon">
                                <i class="fa fa-clipboard"></i>
                                @if(($productionProcess['requisitions'] ?? 0) > 0)
                                <span class="badge plant-step-badge" title="@lang('messages.dashboard_unapproved_pending')">{{ $productionProcess['requisitions'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.Requisition')</span>
                        </a>
                        <i class="fa fa-long-arrow-right plant-arrow"></i>
                        <a href="{{ route('admin.raw-material-transfers.index') }}" class="plant-step" title="@lang('messages.Transfer')">
                            <span class="plant-step-icon">
                                <i class="fa fa-exchange"></i>
                                @if(($productionProcess['transfers'] ?? 0) > 0)
                                <span class="badge plant-step-badge" title="@lang('messages.dashboard_unapproved_pending')">{{ $productionProcess['transfers'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.Transfer')</span>
                        </a>
                        <i class="fa fa-long-arrow-right plant-arrow"></i>
                        <a href="{{ route('admin.production-order.index') }}" class="plant-step" title="@lang('messages.Production Order')">
                            <span class="plant-step-icon">
                                <i class="fa fa-wrench"></i>
                                @if(($productionProcess['production_orders'] ?? 0) > 0)
                                <span class="badge plant-step-badge" title="@lang('messages.dashboard_unapproved_pending')">{{ $productionProcess['production_orders'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.Production Order')</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif

            {{-- Approvisionnements --}}
            @if (Auth::guard('admin')->user()->can('material_purchase.view') || Auth::guard('admin')->user()->can('material_supplier_order.view') || Auth::guard('admin')->user()->can('material_reception.view') || Auth::guard('admin')->user()->can('raw_material_purchase.view') || Auth::guard('admin')->user()->can('raw_material_supplier_order.view') || Auth::guard('admin')->user()->can('raw_material_reception.view'))
            <div class="row">
                @if (Auth::guard('admin')->user()->can('material_purchase.view') || Auth::guard('admin')->user()->can('material_supplier_order.view') || Auth::guard('admin')->user()->can('material_reception.view'))
                <div class="col-lg-6">
                    <div class="plant-panel" style="animation-delay: 0.2s;">
                        <div class="plant-panel-head teal">
                            <h5><i class="fa fa-wrench"></i> @lang('messages.dashboard_procurement_supplies')</h5>
                        </div>
                        <div class="plant-panel-body">
                            <div class="plant-flow">
                                <a href="{{ route('admin.material-purchases.index') }}" class="plant-step">
                                    <span class="plant-step-icon">
                                        <i class="fa fa-file-text-o"></i>
                                        @if($materialProcurement['purchase_requests'] > 0)
                                        <span class="badge plant-step-badge">{{ $materialProcurement['purchase_requests'] }}</span>
                                        @endif
                                    </span>
                                    <span class="plant-step-label">@lang('messages.dashboard_step_purchase_request')</span>
                                </a>
                                <i class="fa fa-long-arrow-right plant-arrow"></i>
                                <a href="{{ route('admin.material-supplier-orders.index') }}" class="plant-step">
                                    <span class="plant-step-icon">
                                        <i class="fa fa-shopping-cart"></i>
                                        @if($materialProcurement['purchase_orders'] > 0)
                                        <span class="badge plant-step-badge">{{ $materialProcurement['purchase_orders'] }}</span>
                                        @endif
                                    </span>
                                    <span class="plant-step-label">@lang('messages.dashboard_step_purchase_order')</span>
                                </a>
                                <i class="fa fa-long-arrow-right plant-arrow"></i>
                                <a href="{{ route('admin.material-receptions.index') }}" class="plant-step">
                                    <span class="plant-step-icon">
                                        <i class="fa fa-truck"></i>
                                        @if($materialProcurement['purchase_receipts'] > 0)
                                        <span class="badge plant-step-badge">{{ $materialProcurement['purchase_receipts'] }}</span>
                                        @endif
                                    </span>
                                    <span class="plant-step-label">@lang('messages.dashboard_step_purchase_receipt')</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                @if (Auth::guard('admin')->user()->can('raw_material_purchase.view') || Auth::guard('admin')->user()->can('raw_material_supplier_order.view') || Auth::guard('admin')->user()->can('raw_material_reception.view'))
                <div class="col-lg-6">
                    <div class="plant-panel" style="animation-delay: 0.25s;">
                        <div class="plant-panel-head amber">
                            <h5><i class="fa fa-cubes"></i> @lang('messages.dashboard_procurement_raw_materials')</h5>
                        </div>
                        <div class="plant-panel-body">
                            <div class="plant-flow">
                                <a href="{{ route('admin.raw-material-purchases.index') }}" class="plant-step">
                                    <span class="plant-step-icon">
                                        <i class="fa fa-clipboard"></i>
                                        @if($rawMaterialProcurement['purchase_requests'] > 0)
                                        <span class="badge plant-step-badge">{{ $rawMaterialProcurement['purchase_requests'] }}</span>
                                        @endif
                                    </span>
                                    <span class="plant-step-label">@lang('messages.dashboard_step_purchase_request')</span>
                                </a>
                                <i class="fa fa-long-arrow-right plant-arrow"></i>
                                <a href="{{ route('admin.raw-material-supplier-orders.index') }}" class="plant-step">
                                    <span class="plant-step-icon">
                                        <i class="fa fa-shopping-basket"></i>
                                        @if($rawMaterialProcurement['purchase_orders'] > 0)
                                        <span class="badge plant-step-badge">{{ $rawMaterialProcurement['purchase_orders'] }}</span>
                                        @endif
                                    </span>
                                    <span class="plant-step-label">@lang('messages.dashboard_step_purchase_order')</span>
                                </a>
                                <i class="fa fa-long-arrow-right plant-arrow"></i>
                                <a href="{{ route('admin.raw-material-receptions.index') }}" class="plant-step">
                                    <span class="plant-step-icon">
                                        <i class="fa fa-download"></i>
                                        @if($rawMaterialProcurement['purchase_receipts'] > 0)
                                        <span class="badge plant-step-badge">{{ $rawMaterialProcurement['purchase_receipts'] }}</span>
                                        @endif
                                    </span>
                                    <span class="plant-step-label">@lang('messages.dashboard_step_purchase_receipt')</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif

            {{-- Paiements --}}
            @if (Auth::guard('admin')->user()->can('account_cash_ouflow_request.view') || Auth::guard('admin')->user()->can('account_cash_ouflow.view') || Auth::guard('admin')->user()->can('fund_transfer.view'))
            <div class="plant-panel" style="animation-delay: 0.3s;">
                <div class="plant-panel-head teal">
                    <h5><i class="fa fa-money"></i> @lang('messages.dashboard_payment_process_title')</h5>
                </div>
                <div class="plant-panel-body">
                    <div class="plant-note">
                        <i class="fa fa-info-circle mr-1"></i>
                        @lang('messages.dashboard_payment_link_help')
                    </div>
                    <div class="plant-flow">
                        <a href="{{ route('admin.cash-out-flow-request.index') }}" class="plant-step">
                            <span class="plant-step-icon">
                                <i class="fa fa-file-text"></i>
                                @if($paymentProcess['cash_outflow_requests'] > 0)
                                <span class="badge plant-step-badge">{{ $paymentProcess['cash_outflow_requests'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.dashboard_payment_step_cash_out_request')</span>
                        </a>
                        <i class="fa fa-long-arrow-right plant-arrow"></i>
                        <a href="{{ route('admin.cash-out-flow.index') }}" class="plant-step">
                            <span class="plant-step-icon">
                                <i class="fa fa-credit-card"></i>
                                @if($paymentProcess['cash_outflows'] > 0)
                                <span class="badge plant-step-badge">{{ $paymentProcess['cash_outflows'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.dashboard_payment_step_cash_out')</span>
                        </a>
                        <i class="fa fa-long-arrow-right plant-arrow"></i>
                        <a href="{{ route('admin.accounting-return-fund.index') }}" class="plant-step">
                            <span class="plant-step-icon">
                                <i class="fa fa-reply"></i>
                                @if($paymentProcess['return_funds'] > 0)
                                <span class="badge plant-step-badge">{{ $paymentProcess['return_funds'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.dashboard_payment_step_return_fund')</span>
                        </a>
                    </div>
                    <div class="plant-source-links">
                        @can('material_supplier_order.view')
                        <a href="{{ route('admin.material-supplier-orders.index') }}" class="plant-source-link">
                            <i class="fa fa-shopping-cart"></i>
                            @lang('messages.dashboard_payment_cash_via_order_supplies')
                            @if($awaitingPayment['material_orders_cash'] > 0)
                            <span class="badge plant-source-badge">{{ $awaitingPayment['material_orders_cash'] }}</span>
                            @endif
                        </a>
                        @endcan
                        @can('raw_material_supplier_order.view')
                        <a href="{{ route('admin.raw-material-supplier-orders.index') }}" class="plant-source-link">
                            <i class="fa fa-shopping-basket"></i>
                            @lang('messages.dashboard_payment_cash_via_order_raw_materials')
                            @if($awaitingPayment['raw_material_orders_cash'] > 0)
                            <span class="badge plant-source-badge">{{ $awaitingPayment['raw_material_orders_cash'] }}</span>
                            @endif
                        </a>
                        @endcan
                        @can('material_reception.view')
                        <a href="{{ route('admin.material-receptions.index') }}" class="plant-source-link">
                            <i class="fa fa-truck"></i>
                            @lang('messages.dashboard_payment_credit_via_reception_supplies')
                            @if($awaitingPayment['material_receptions_credit'] > 0)
                            <span class="badge plant-source-badge">{{ $awaitingPayment['material_receptions_credit'] }}</span>
                            @endif
                        </a>
                        @endcan
                        @can('raw_material_reception.view')
                        <a href="{{ route('admin.raw-material-receptions.index') }}" class="plant-source-link">
                            <i class="fa fa-download"></i>
                            @lang('messages.dashboard_payment_credit_via_reception_raw_materials')
                            @if($awaitingPayment['raw_material_receptions_credit'] > 0)
                            <span class="badge plant-source-badge">{{ $awaitingPayment['raw_material_receptions_credit'] }}</span>
                            @endif
                        </a>
                        @endcan
                    </div>
                </div>
            </div>
            @endif

            {{-- Ventes --}}
            @if (Auth::guard('admin')->user()->can('proforma_invoice.view') || Auth::guard('admin')->user()->can('sales_order.view') || Auth::guard('admin')->user()->can('invoice.view') || Auth::guard('admin')->user()->can('delivery_note.view'))
            <div class="plant-panel" style="animation-delay: 0.35s;">
                <div class="plant-panel-head amber">
                    <h5><i class="fa fa-shopping-bag"></i> @lang('messages.dashboard_sales_process_title')</h5>
                </div>
                <div class="plant-panel-body">
                    <div class="plant-flow">
                        <a href="{{ route('admin.proforma-invoice.index') }}" class="plant-step">
                            <span class="plant-step-icon">
                                <i class="fa fa-file-text-o"></i>
                                @if($salesProcess['proforma_invoices'] > 0)
                                <span class="badge plant-step-badge">{{ $salesProcess['proforma_invoices'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.Proforma Invoice')</span>
                        </a>
                        <i class="fa fa-long-arrow-right plant-arrow"></i>
                        <a href="{{ route('admin.sales-order.index') }}" class="plant-step">
                            <span class="plant-step-icon">
                                <i class="fa fa-shopping-cart"></i>
                                @if($salesProcess['sales_orders'] > 0)
                                <span class="badge plant-step-badge">{{ $salesProcess['sales_orders'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.Bons de commande')</span>
                        </a>
                        <i class="fa fa-long-arrow-right plant-arrow"></i>
                        <a href="{{ route('admin.invoice.index') }}" class="plant-step">
                            <span class="plant-step-icon">
                                <i class="fa fa-file-text"></i>
                                @if($salesProcess['invoices_in_progress'] > 0)
                                <span class="badge plant-step-badge warn" title="@lang('messages.dashboard_invoice_in_progress')">{{ $salesProcess['invoices_in_progress'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.Invoices')</span>
                        </a>
                        <i class="fa fa-long-arrow-right plant-arrow"></i>
                        <a href="{{ route('admin.delivery-note.index') }}" class="plant-step">
                            <span class="plant-step-icon">
                                <i class="fa fa-truck"></i>
                                @if($salesProcess['delivery_notes'] > 0)
                                <span class="badge plant-step-badge">{{ $salesProcess['delivery_notes'] }}</span>
                                @endif
                            </span>
                            <span class="plant-step-label">@lang('messages.Delivery Note')</span>
                        </a>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endif
</div>
@endsection
