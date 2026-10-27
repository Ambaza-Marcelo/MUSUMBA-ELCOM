<footer>
    <div class="footer-area plant-footer">
        <div class="brand-hazard brand-hazard--thin" aria-hidden="true"></div>
        <p>
            © {{ date('Y') }} <strong>@lang('messages.musumba_elcom')</strong>.
            <span class="plant-footer-designer">@lang('messages.footer_system_by') <strong>EGOTEK</strong></span>
        </p>
    </div>
</footer>
<style>
    .plant-footer {
        padding-top: 0;
    }
    .plant-footer .brand-hazard {
        margin: 0 0 0.85rem;
    }
    .plant-footer p {
        margin: 0;
        font-size: 0.82rem;
        font-family: 'Montserrat', sans-serif;
        color: #6f7180;
    }
    .plant-footer strong {
        color: #1e1d24;
    }
    .plant-footer-designer {
        display: inline-block;
        margin-left: 0.35rem;
        color: #a7a9ad;
    }
    .plant-footer-designer strong {
        color: #eba91b;
        font-weight: 700;
        letter-spacing: 0.04em;
    }
</style>
