<!-- header area start -->
<div class="header-area">
    <div class="row align-items-center">
        <div class="col-md-6 col-sm-8 clearfix">
            <div class="header-left-tools pull-left">
                <button type="button" class="nav-btn" aria-label="@lang('messages.dashboard_toggle_menu')" title="@lang('messages.dashboard_toggle_menu')">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <img
                    src="{{ asset('img/logo.jpeg') }}"
                    alt="@lang('messages.musumba_elcom')"
                    class="header-brand-logo"
                >
                <h2 class="header-plant-title">
                    @lang('messages.musumba_elcom')
                    <em>· @lang('messages.dashboard_plant_brand_tag')</em>
                </h2>
            </div>
        </div>
        <div class="col-md-6 col-sm-4 clearfix">
            <ul class="notification-area pull-right">
                <li id="full-view"><i class="ti-fullscreen"></i></li>
                <li id="full-view-exit"><i class="ti-zoom-out"></i></li>
                <li>
                    <select class="form-control changeLang">
                        <option value="fr" {{ session()->get('locale') == 'fr' ? 'selected' : '' }}>@lang('messages.french')</option>
                        <option value="en" {{ session()->get('locale') == 'en' ? 'selected' : '' }}>@lang('messages.english')</option>
                        <option value="ki" {{ session()->get('locale') == 'ki' ? 'selected' : '' }}>@lang('messages.kirundi')</option>
                    </select>
                </li>
                <li class="dropdown">
                    <i class="ti-bell dropdown-toggle" data-toggle="dropdown">
                        <span>1</span>
                    </i>
                    <div class="dropdown-menu bell-notify-box notify-box">
                        <span class="notify-title">@lang('messages.you_have_notifications', ['count' => 1])</span>
                        <div class="nofity-list">
                            <a href="#" class="notify-item">
                                <div class="notify-thumb"><i class="ti-key btn-danger"></i></div>
                                <div class="notify-text">
                                    <p>@lang('messages.permissions_updated')</p>
                                    <span>@lang('messages.now')</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
<script type="text/javascript">
    (function () {
        var url = "{{ route('changeLang') }}";
        $(".changeLang").change(function () {
            window.location.href = url + "?lang=" + $(this).val();
        });

        function syncBackdrop() {
            var collapsed = $(".page-container").hasClass("sbar_collapsed");
            var narrow = window.innerWidth <= 1364;
            var $backdrop = $("#sidebarBackdrop");
            if (narrow && !collapsed) {
                $backdrop.addClass("is-visible").attr("aria-hidden", "false");
            } else {
                $backdrop.removeClass("is-visible").attr("aria-hidden", "true");
            }
        }

        $(document).on("click", ".nav-btn", function () {
            setTimeout(syncBackdrop, 10);
        });

        $("#sidebarBackdrop").on("click", function () {
            $(".page-container").addClass("sbar_collapsed");
            syncBackdrop();
        });

        $(window).on("resize", syncBackdrop);
        $(function () { syncBackdrop(); });
    })();
</script>
<!-- header area end -->
