@extends('backend.auth.auth_master')

@section('auth_title')
@lang('messages.login') — @lang('messages.musumba_elcom')
@endsection

@section('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root {
        --login-steel: #1e1d24;
        --login-slate: #3f424c;
        --login-amber: #eba91b;
        --login-amber-hot: #f0b429;
        --login-teal: #eba91b;
        --login-teal-bright: #f0b429;
        --login-sand: #eeeff8;
        --login-dust: #d3d6e0;
        --login-muted: #6f7180;
        --login-ink: #1e1d24;
    }

    body {
        margin: 0;
        min-height: 100vh;
        font-family: 'Montserrat', sans-serif;
        background: var(--login-steel);
    }

    #preloader { background: var(--login-steel); }
    #preloader .loader { border-top-color: var(--login-amber); }

    .login-area,
    .login-area .container {
        max-width: none !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .login-shell {
        min-height: 100vh;
        display: grid;
        grid-template-columns: 1.15fr 1fr;
        background: var(--login-sand);
    }

    /* Left — factory brand plane */
    .login-hero {
        position: relative;
        overflow: hidden;
        color: #fff;
        padding: clamp(2rem, 5vw, 3.5rem);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        background:
            linear-gradient(125deg, #000000 0%, #1e1d24 48%, #3f424c 100%),
            repeating-linear-gradient(
                -45deg,
                transparent,
                transparent 14px,
                rgba(235, 169, 27, 0.08) 14px,
                rgba(235, 169, 27, 0.08) 28px
            ),
            radial-gradient(ellipse at 85% 15%, rgba(235, 169, 27, 0.45), transparent 52%);
        animation: loginHeroIn 0.75s ease-out both;
    }

    .login-hero::before,
    .login-hero::after {
        content: '';
        position: absolute;
        pointer-events: none;
        border-radius: 10px 10px 42% 42%;
        background: linear-gradient(180deg, rgba(235, 231, 223, 0.2), rgba(139, 145, 154, 0.06));
        transform: rotate(16deg);
    }

    .login-hero::before {
        right: -30px;
        bottom: -70px;
        width: 180px;
        height: 340px;
    }

    .login-hero::after {
        right: 95px;
        bottom: -50px;
        width: 120px;
        height: 280px;
        opacity: 0.75;
    }

    .login-hero .brand-hazard {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 2;
        height: 6px;
        margin: 0;
    }

    .login-hero-top,
    .login-hero-bottom {
        position: relative;
        z-index: 1;
    }

    .login-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--login-amber-hot);
        margin-bottom: 1.1rem;
    }

    .login-kicker .pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--login-amber-hot);
        box-shadow: 0 0 0 0 rgba(245, 166, 35, 0.5);
        animation: loginPulse 1.8s ease-out infinite;
    }

    .login-hero h1 {
        font-family: 'Montserrat', sans-serif;
        font-size: clamp(2.4rem, 5vw, 3.6rem);
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        line-height: 0.98;
        margin: 0 0 0.75rem;
        color: #fff;
    }

    .login-hero .tagline {
        max-width: 26rem;
        margin: 0;
        font-size: 1.02rem;
        line-height: 1.5;
        color: rgba(238, 239, 248, 0.92);
    }

    .login-hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 2rem;
    }

    .login-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.8rem;
        border-radius: 4px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--login-sand);
    }

    .login-chip i {
        color: var(--login-amber-hot);
    }

    .login-hero-foot {
        font-size: 0.78rem;
        color: rgba(212, 208, 200, 0.7);
        letter-spacing: 0.04em;
    }

    /* Right — form panel */
    .login-panel {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: clamp(1.5rem, 4vw, 3rem);
        background:
            linear-gradient(180deg, #ffffff 0%, var(--login-sand) 100%);
        position: relative;
        animation: loginPanelIn 0.7s ease-out 0.1s both;
    }

    .login-card {
        width: 100%;
        max-width: 420px;
        background: #fff;
        border-radius: 6px;
        border: 1px solid rgba(28, 38, 54, 0.08);
        box-shadow: 0 22px 48px rgba(28, 38, 54, 0.12);
        overflow: hidden;
    }

    .login-card-accent {
        height: 5px;
        background: repeating-linear-gradient(-45deg, #000 0 10px, #eba91b 10px 20px);
    }

    .login-card-body {
        padding: 2rem 1.85rem 1.75rem;
    }

    .login-brand-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        margin-bottom: 1.5rem;
    }

    .login-logo {
        width: 64px;
        height: 64px;
        flex-shrink: 0;
        object-fit: contain;
        border-radius: 8px;
        background: #fff;
        border: 1px solid rgba(28, 38, 54, 0.08);
        box-shadow: 0 8px 18px rgba(28, 38, 54, 0.1);
        padding: 4px;
    }

    .login-hero-logo {
        width: min(180px, 42vw);
        height: auto;
        display: block;
        margin-bottom: 1.25rem;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 14px 36px rgba(0, 0, 0, 0.28);
        padding: 0.55rem;
        animation: loginHeroIn 0.85s ease-out both;
    }

    .login-brand-text strong {
        display: block;
        font-family: 'Montserrat', sans-serif;
        font-size: 1.35rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--login-steel);
        line-height: 1.05;
    }

    .login-brand-text span {
        display: block;
        margin-top: 0.2rem;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--login-teal);
    }

    .login-card h2 {
        font-family: 'Montserrat', sans-serif;
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: var(--login-ink);
        margin: 0 0 0.35rem;
    }

    .login-card .intro {
        margin: 0 0 1.35rem;
        font-size: 0.9rem;
        color: var(--login-muted);
        line-height: 1.45;
    }

    .login-card .alert {
        border-radius: 4px;
        font-size: 0.85rem;
    }

    .login-field {
        margin-bottom: 1.15rem;
    }

    .login-field label {
        display: block;
        margin-bottom: 0.4rem;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--login-muted);
    }

    .login-input-wrap {
        position: relative;
    }

    .login-input-wrap i {
        position: absolute;
        right: 0.95rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--login-teal);
        font-size: 1rem;
        pointer-events: none;
    }

    .login-input-wrap input {
        width: 100%;
        height: 48px;
        padding: 0 2.5rem 0 0.95rem;
        border: 1.5px solid rgba(28, 38, 54, 0.14);
        border-radius: 4px;
        background: #fbfaf8;
        color: var(--login-ink);
        font-size: 0.95rem;
        font-family: 'Montserrat', sans-serif;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .login-input-wrap input:focus {
        border-color: var(--login-teal);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(235, 169, 27, 0.22);
    }

    .login-field .invalid-feedback {
        display: block;
        margin-top: 0.35rem;
        font-size: 0.8rem;
    }

    .login-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: 0.25rem 0 1.35rem;
    }

    .login-remember {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        margin: 0;
        font-size: 0.88rem;
        color: var(--login-muted);
        cursor: pointer;
        user-select: none;
    }

    .login-remember input {
        width: 16px;
        height: 16px;
        accent-color: var(--login-teal);
        cursor: pointer;
    }

    .login-submit {
        width: 100%;
        height: 50px;
        border: none;
        border-radius: 4px;
        background: #eba91b;
        color: #000;
        font-family: 'Montserrat', sans-serif;
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.55rem;
        box-shadow: 0 12px 24px rgba(235, 169, 27, 0.35);
        transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
    }

    .login-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 28px rgba(235, 169, 27, 0.4);
        filter: brightness(1.05);
        color: #000;
    }

    .login-submit:active {
        transform: translateY(0);
    }

    .login-footer {
        margin-top: 1.35rem;
        padding-top: 1rem;
        border-top: 1px dashed rgba(28, 38, 54, 0.12);
        text-align: center;
        font-size: 0.78rem;
        color: var(--login-muted);
    }

    .login-footer strong {
        color: var(--login-teal);
        font-weight: 600;
        letter-spacing: 0.04em;
    }

    /* Override legacy login theme leftovers */
    .login-box,
    .login-form-head,
    .login-form-body,
    .form-gp,
    .submit-btn-area {
        all: unset;
        display: block;
    }

    @keyframes loginHeroIn {
        from { opacity: 0; transform: translateX(-18px); }
        to { opacity: 1; transform: translateX(0); }
    }

    @keyframes loginPanelIn {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes loginPulse {
        0% { box-shadow: 0 0 0 0 rgba(245, 166, 35, 0.55); }
        70% { box-shadow: 0 0 0 10px rgba(245, 166, 35, 0); }
        100% { box-shadow: 0 0 0 0 rgba(245, 166, 35, 0); }
    }

    @media (max-width: 991px) {
        .login-shell {
            grid-template-columns: 1fr;
        }

        .login-hero {
            min-height: auto;
            padding: 1.75rem 1.5rem 1.5rem;
        }

        .login-hero h1 {
            font-size: 2.2rem;
        }

        .login-hero::before,
        .login-hero::after {
            opacity: 0.45;
            width: 100px;
            height: 180px;
        }

        .login-hero-meta {
            margin-top: 1.25rem;
        }

        .login-panel {
            padding: 1.25rem 1rem 2rem;
        }
    }

    @media (max-width: 480px) {
        .login-card-body {
            padding: 1.5rem 1.15rem 1.35rem;
        }

        .login-brand-text strong {
            font-size: 1.15rem;
        }
    }
</style>
@endsection

@section('auth-content')
<div class="login-area">
    <div class="container">
        <div class="login-shell">
            <aside class="login-hero" aria-label="@lang('messages.musumba_elcom')">
                <div class="brand-hazard" aria-hidden="true"></div>
                <div class="login-hero-top">
                    <img
                        src="{{ asset('img/logo.jpeg') }}"
                        alt="@lang('messages.musumba_elcom')"
                        class="login-hero-logo"
                    >
                    <div class="login-kicker">
                        <span class="pulse-dot"></span>
                        @lang('messages.dashboard_plant_live')
                    </div>
                    <h1>@lang('messages.musumba_elcom')</h1>
                    <p class="tagline">@lang('messages.dashboard_plant_subtitle')</p>
                    <div class="login-hero-meta">
                        <span class="login-chip"><i class="fa fa-industry"></i> @lang('messages.dashboard_plant_brand_tag')</span>
                        <span class="login-chip"><i class="fa fa-cogs"></i> @lang('messages.dashboard_production_process_title')</span>
                    </div>
                </div>
                <div class="login-hero-bottom">
                    <p class="login-hero-foot">@lang('messages.login_secure_access')</p>
                </div>
            </aside>

            <section class="login-panel">
                <div class="login-card">
                    <div class="login-card-accent"></div>
                    <div class="login-card-body">
                        <div class="login-brand-row">
                            <img
                                src="{{ asset('img/logo.jpeg') }}"
                                alt="@lang('messages.musumba_elcom')"
                                class="login-logo"
                            >
                            <div class="login-brand-text">
                                <strong>@lang('messages.musumba_elcom')</strong>
                                <span>@lang('messages.dashboard_plant_brand_tag')</span>
                            </div>
                        </div>

                        <h2>@lang('messages.login')</h2>
                        <p class="intro">@lang('messages.login_welcome')</p>

                        @include('backend.layouts.partials.messages')

                        <form method="POST" action="{{ route('admin.login.submit') }}" autocomplete="on">
                            @csrf

                            <div class="login-field">
                                <label for="exampleInputEmail1">@lang('messages.mail') @lang('messages.or') @lang('messages.username')</label>
                                <div class="login-input-wrap">
                                    <input
                                        type="text"
                                        id="exampleInputEmail1"
                                        name="email"
                                        value="{{ old('email') }}"
                                        required
                                        autofocus
                                        autocomplete="username"
                                    >
                                    <i class="ti-email"></i>
                                </div>
                                @error('email')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="login-field">
                                <label for="exampleInputPassword1">@lang('messages.password')</label>
                                <div class="login-input-wrap">
                                    <input
                                        type="password"
                                        id="exampleInputPassword1"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                    >
                                    <i class="ti-lock"></i>
                                </div>
                                @error('password')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="login-options">
                                <label class="login-remember" for="customControlAutosizing">
                                    <input type="checkbox" id="customControlAutosizing" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                    @lang('messages.rememberme')
                                </label>
                            </div>

                            <button type="submit" class="login-submit" id="form_submit">
                                @lang('messages.login')
                                <i class="ti-arrow-right"></i>
                            </button>
                        </form>

                        <div class="login-footer">
                            © {{ date('Y') }} @lang('messages.musumba_elcom')
                            · @lang('messages.footer_system_by') <strong>EGOTEK</strong>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script type="text/javascript">
    function preventBack() {
        window.history.forward();
    }
    setTimeout("preventBack()", 0);
    window.onunload = function () { null; };
</script>
@endsection
