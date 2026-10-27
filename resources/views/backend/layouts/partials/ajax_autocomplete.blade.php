<style>
    .ajax-ac-wrap { position: relative; }
    .ajax-ac-results,
    .autocomplete-list,
    .autocomplete-items {
        position: fixed;
        z-index: 20050;
        background: #fff;
        border: 1px solid #ced4da;
        max-height: min(280px, 45vh);
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        box-shadow: 0 8px 20px rgba(0,0,0,.16);
        display: none;
        box-sizing: border-box;
        border-radius: 4px;
    }
    .ajax-ac-results.ajax-ac-results--body {
        border-top: 1px solid #ced4da;
    }
    .ajax-ac-results { border-top: none; }
    .autocomplete-list,
    .autocomplete-items {
        border: 1px solid #d4d4d4;
    }
    .ajax-ac-item,
    .autocomplete-list div,
    .autocomplete-items div {
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        line-height: 1.35;
        word-break: break-word;
        font-size: 0.95rem;
    }
    .ajax-ac-item:last-child,
    .autocomplete-list div:last-child,
    .autocomplete-items div:last-child { border-bottom: none; }
    .ajax-ac-item:hover,
    .ajax-ac-item.is-active,
    .autocomplete-list div:hover,
    .autocomplete-items div:hover { background: #f1f3f5; }
    .ajax-ac-muted { color: #6c757d; }
    .autocomplete-active {
        background-color: #4CAF50 !important;
        color: #ffffff;
    }
    .autocomplete-wrapper,
    .autocomplete {
        position: relative;
    }
    @media (max-width: 576px) {
        .ajax-ac-results,
        .autocomplete-list,
        .autocomplete-items {
            max-height: min(220px, 40vh);
            font-size: 0.9rem;
        }
        .ajax-ac-item,
        .autocomplete-list div,
        .autocomplete-items div {
            padding: 12px 14px;
        }
    }
</style>

<script>
    (function(){
        if (typeof jQuery === 'undefined') return;

        function esc(s){
            return (s != null ? s : '').toString()
                .replace(/&/g,'&amp;')
                .replace(/</g,'&lt;')
                .replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;')
                .replace(/'/g,'&#039;');
        }

        function debounce(fn, delay){
            var t;
            return function(){
                var ctx = this, args = arguments;
                clearTimeout(t);
                t = setTimeout(function(){ fn.apply(ctx, args); }, delay);
            };
        }

        window.AcDropdown = {
            findInputForList: function($list){
                if (!$list || !$list.length) return $();
                var $input = $list.siblings('input[type="text"], input:not([type])').filter(':visible').first();
                if (!$input.length) {
                    $input = $list.closest('.autocomplete-wrapper, .autocomplete, .ajax-ac-wrap')
                        .find('input[type="text"], input:not([type])').filter(':visible').first();
                }
                return $input;
            },
            position: function($input, $list){
                if (!$input || !$input.length || !$list || !$list.length) return;
                var rect = $input[0].getBoundingClientRect();
                var vw = window.innerWidth || document.documentElement.clientWidth;
                var vh = window.innerHeight || document.documentElement.clientHeight;
                var gap = 2;
                var width = Math.max(rect.width, 180);
                var left = rect.left;
                if (left + width > vw - 8) left = Math.max(8, vw - width - 8);
                if (left < 8) left = 8;
                if (width > vw - 16) width = vw - 16;

                var spaceBelow = vh - rect.bottom - 8;
                var spaceAbove = rect.top - 8;
                var maxH = Math.min(280, Math.floor(vh * 0.45));
                var openUp = spaceBelow < 140 && spaceAbove > spaceBelow;
                var avail = openUp ? spaceAbove : spaceBelow;
                var height = Math.max(120, Math.min(maxH, avail));

                var css = {
                    position: 'fixed',
                    zIndex: 20050,
                    left: left + 'px',
                    width: width + 'px',
                    maxHeight: height + 'px',
                    boxSizing: 'border-box',
                    right: 'auto'
                };
                if (openUp) {
                    css.top = 'auto';
                    css.bottom = (vh - rect.top + gap) + 'px';
                } else {
                    css.bottom = 'auto';
                    css.top = (rect.bottom + gap) + 'px';
                }
                $list.css(css);
            },
            positionForList: function($list){
                if ($list.hasClass('ajax-ac-results--anchored')) return;
                var $input = AcDropdown.findInputForList($list);
                if ($input.length) AcDropdown.position($input, $list);
            },
            repositionVisible: function(){
                $('.ajax-ac-results:visible, .autocomplete-list:visible, .autocomplete-items:visible').each(function(){
                    AcDropdown.positionForList($(this));
                });
            },
            init: function(){
                if (window.__acDropdownInit) return;
                window.__acDropdownInit = true;

                var listObserver = new MutationObserver(function(mutations){
                    mutations.forEach(function(m){
                        if (m.type === 'attributes') {
                            var $el = $(m.target);
                            if ($el.is(':visible')) AcDropdown.positionForList($el);
                        } else if (m.type === 'childList') {
                            var $el = $(m.target);
                            if ($el.is('.autocomplete-list, .autocomplete-items, .ajax-ac-results') && $el.is(':visible')) {
                                AcDropdown.positionForList($el);
                            }
                        }
                    });
                });

                function observeList(el){
                    listObserver.observe(el, {
                        attributes: true,
                        attributeFilter: ['style', 'class'],
                        childList: true
                    });
                }

                function scanLists(root){
                    $(root).find('.autocomplete-list, .autocomplete-items, .ajax-ac-results').addBack('.autocomplete-list, .autocomplete-items, .ajax-ac-results').each(function(){
                        if (!this.__acDropdownObserved) {
                            this.__acDropdownObserved = true;
                            observeList(this);
                        }
                    });
                }

                scanLists(document.body);

                var domObserver = new MutationObserver(function(mutations){
                    mutations.forEach(function(m){
                        for (var i = 0; i < m.addedNodes.length; i++) {
                            var node = m.addedNodes[i];
                            if (node.nodeType !== 1) continue;
                            scanLists(node);
                        }
                    });
                });
                domObserver.observe(document.body, { childList: true, subtree: true });

                $(window).on('scroll.acDropdown resize.acDropdown', function(){
                    AcDropdown.repositionVisible();
                });

                $(document).on('input.acDropdown focus.acDropdown', '.autocomplete-wrapper input, .autocomplete input', function(){
                    var $input = $(this);
                    setTimeout(function(){
                        var listId = $input.attr('id') + 'autocomplete-list';
                        var $byId = $('#' + listId);
                        if ($byId.length && $byId.is(':visible')) {
                            AcDropdown.position($input, $byId);
                        }
                        $input.closest('.autocomplete-wrapper, .autocomplete')
                            .find('.autocomplete-list:visible, .autocomplete-items:visible')
                            .each(function(){ AcDropdown.positionForList($(this)); });
                    }, 0);
                });
            }
        };

        function bindDropdownPositioning($input, $results, ns, $dropdownParent, appendToBody){
            function positionResults(){
                if (!$results.is(':visible')) return;
                if ($dropdownParent && $dropdownParent.length && !appendToBody) {
                    $results.css({
                        position: 'absolute',
                        top: '100%',
                        left: 0,
                        right: 0,
                        width: '100%',
                        marginTop: '2px',
                        zIndex: 1060
                    });
                    return;
                }
                AcDropdown.position($input, $results);
            }
            $(window).on('scroll' + ns + ' resize' + ns, positionResults);
            var $modal = $input.closest('.modal');
            if ($modal.length) {
                $modal.on('scroll' + ns, positionResults);
            }
            return positionResults;
        }

        window.attachAjaxAutocomplete = function(opts){
            var $input = opts.$input;
            var $hidden = opts.$hidden || null;
            var $dropdownParent = opts.$dropdownParent || null;
            var appendToBody = opts.appendToBody;
            if (appendToBody == null) {
                appendToBody = $input.closest('.modal').length > 0;
            }
            var url = opts.url;
            var minChars = (opts.minChars != null) ? opts.minChars : 1;
            var extraParams = opts.extraParams || null;
            var filterResults = opts.filterResults || null;
            var validateBeforeSearch = opts.validateBeforeSearch || null;
            var onSelect = opts.onSelect || function(){};
            var renderItem = opts.renderItem || function(item){
                return '<div><strong>' + esc(item.text || '') + '</strong></div>';
            };

            if (!$input || !$input.length) return;

            var $wrap = $input.closest('.ajax-ac-wrap');
            if (!$wrap.length){
                $input.wrap('<div class="ajax-ac-wrap"></div>');
                $wrap = $input.closest('.ajax-ac-wrap');
            }
            var $results = $wrap.find('.ajax-ac-results');
            if (!$results.length && appendToBody) {
                $results = $('body > .ajax-ac-results[data-for="' + ($input.attr('id') || '') + '"]');
            }
            if (!$results.length){
                $results = $('<div class="ajax-ac-results" role="listbox"></div>');
                if (appendToBody) {
                    $results
                        .addClass('ajax-ac-results--body')
                        .attr('data-for', $input.attr('id') || '')
                        .appendTo(document.body);
                } else {
                    $wrap.append($results);
                }
            } else if (appendToBody && !$results.parent().is('body')) {
                $results.addClass('ajax-ac-results--body').appendTo(document.body);
            }
            if ($dropdownParent && $dropdownParent.length && !appendToBody) {
                $results.addClass('ajax-ac-results--anchored').appendTo($dropdownParent);
            }

            var activeIndex = -1;
            var lastResults = [];
            var ns = '.ajaxAc' + ($input.attr('id') || Math.random().toString(36).slice(2));
            var $interactionRoot = ($dropdownParent && $dropdownParent.length && !appendToBody) ? $dropdownParent : $wrap;
            var positionResults = bindDropdownPositioning($input, $results, ns, $dropdownParent, appendToBody);

            function close(){
                $results.hide().empty();
                activeIndex = -1;
                lastResults = [];
            }

            function openWithHtml(html){
                $results.html(html).show();
                positionResults();
            }

            function setActive(idx){
                activeIndex = idx;
                $results.find('.ajax-ac-item').removeClass('is-active');
                if (activeIndex >= 0){
                    var $it = $results.find('.ajax-ac-item').eq(activeIndex);
                    $it.addClass('is-active');
                    var top = $it.position().top;
                    var h = $it.outerHeight();
                    var boxH = $results.innerHeight();
                    if (top < 0) $results.scrollTop($results.scrollTop() + top);
                    else if (top + h > boxH) $results.scrollTop($results.scrollTop() + (top + h - boxH));
                }
            }

            function choose(item){
                $input.val(item.text || item.name || '');
                if ($hidden && $hidden.length){
                    $hidden.val(item.id != null ? item.id : '');
                }
                onSelect(item);
                close();
            }

            var doSearch = debounce(function(){
                if (typeof validateBeforeSearch === 'function' && validateBeforeSearch() === false) {
                    close();
                    return;
                }

                var q = ($input.val() || '').trim();
                if ($hidden && $hidden.length) $hidden.val('');
                if (q.length < minChars){ close(); return; }

                var params = { q: q };
                if (extraParams) {
                    var add = (typeof extraParams === 'function') ? (extraParams() || {}) : (extraParams || {});
                    Object.keys(add).forEach(function(k){ params[k] = add[k]; });
                }

                openWithHtml('<div class="ajax-ac-item ajax-ac-muted">Recherche...</div>');

                $.get(url, params, function(data){
                    var results = (data && data.results) ? data.results : [];
                    if (typeof filterResults === 'function') {
                        results = filterResults(results) || [];
                    }
                    lastResults = results;
                    activeIndex = -1;
                    if (!results.length){
                        openWithHtml('<div class="ajax-ac-item ajax-ac-muted">Aucun résultat</div>');
                        return;
                    }
                    var html = '';
                    results.forEach(function(item, idx){
                        html += '<div class="ajax-ac-item" data-idx="'+idx+'">' + renderItem(item) + '</div>';
                    });
                    openWithHtml(html);
                }).fail(function(){
                    openWithHtml('<div class="ajax-ac-item ajax-ac-muted">Erreur de recherche</div>');
                });
            }, 200);

            $input.off('input' + ns + ' focus' + ns + ' keydown' + ns + ' blur' + ns);
            $interactionRoot.off('mousedown' + ns);
            $results.off('mousedown' + ns);
            $(document).off('click' + ns);
            $(window).off('scroll' + ns + ' resize' + ns);
            $input.closest('.modal').off('scroll' + ns);

            $input.on('input' + ns, doSearch);
            $input.on('focus' + ns, function(){
                var q = ($input.val() || '').trim();
                if (q.length >= minChars) doSearch();
            });

            $input.on('keydown' + ns, function(e){
                if (!$results.is(':visible')) return;
                var count = $results.find('.ajax-ac-item[data-idx]').length;
                if (!count) return;

                if (e.key === 'ArrowDown'){
                    e.preventDefault();
                    setActive(activeIndex + 1 >= count ? 0 : activeIndex + 1);
                } else if (e.key === 'ArrowUp'){
                    e.preventDefault();
                    setActive(activeIndex - 1 < 0 ? count - 1 : activeIndex - 1);
                } else if (e.key === 'Enter'){
                    if (activeIndex >= 0 && lastResults[activeIndex]){
                        e.preventDefault();
                        choose(lastResults[activeIndex]);
                    }
                } else if (e.key === 'Escape'){
                    close();
                }
            });

            function onItemMouseDown(e){
                e.preventDefault();
                var idx = parseInt($(this).data('idx'), 10);
                if (!isNaN(idx) && lastResults[idx]){
                    choose(lastResults[idx]);
                }
            }
            $interactionRoot.on('mousedown' + ns, '.ajax-ac-item[data-idx]', onItemMouseDown);
            $results.on('mousedown' + ns, '.ajax-ac-item[data-idx]', onItemMouseDown);

            $input.on('blur' + ns, function(){
                setTimeout(close, 200);
            });

            $(document).on('click' + ns, function(ev){
                var t = ev.target;
                if ($.contains($interactionRoot[0], t)) return;
                if ($results[0] && $.contains($results[0], t)) return;
                if ($input[0] === t || $.contains($wrap[0], t)) return;
                close();
            });
        };

        window.attachLocalAutocomplete = function(opts){
            var $input = opts.$input;
            var $hidden = opts.$hidden || null;
            var items = opts.items || [];
            var minChars = typeof opts.minChars === 'number' ? opts.minChars : 1;
            var onSelect = opts.onSelect || function(){};
            var renderItem = opts.renderItem || function(item){
                return '<div><strong>' + esc(item.text || '') + '</strong></div>';
            };
            var appendToBody = opts.appendToBody;
            if (appendToBody == null) {
                appendToBody = $input.closest('.modal').length > 0;
            }

            if (!$input || !$input.length) return;

            var $wrap = $input.closest('.ajax-ac-wrap');
            if (!$wrap.length){
                $input.wrap('<div class="ajax-ac-wrap"></div>');
                $wrap = $input.closest('.ajax-ac-wrap');
            }
            var $results = $wrap.find('.ajax-ac-results');
            if (!$results.length && appendToBody) {
                $results = $('body > .ajax-ac-results[data-for="' + ($input.attr('id') || '') + '"]');
            }
            if (!$results.length){
                $results = $('<div class="ajax-ac-results" role="listbox"></div>');
                if (appendToBody) {
                    $results
                        .addClass('ajax-ac-results--body')
                        .attr('data-for', $input.attr('id') || '')
                        .appendTo(document.body);
                } else {
                    $wrap.append($results);
                }
            } else if (appendToBody && !$results.parent().is('body')) {
                $results.addClass('ajax-ac-results--body').appendTo(document.body);
            }

            var activeIndex = -1;
            var lastResults = [];
            var ns = '.ajaxLocal' + ($input.attr('id') || Math.random().toString(36).slice(2));
            var positionResults = bindDropdownPositioning($input, $results, ns, null, appendToBody);

            function close(){
                $results.hide().empty();
                activeIndex = -1;
                lastResults = [];
            }

            function openWithHtml(html){
                $results.html(html).show();
                positionResults();
            }

            function setActive(idx){
                activeIndex = idx;
                $results.find('.ajax-ac-item').removeClass('is-active');
                if (activeIndex >= 0){
                    $results.find('.ajax-ac-item').eq(activeIndex).addClass('is-active');
                }
            }

            function choose(item){
                $input.val(item.text || '');
                if ($hidden && $hidden.length){
                    $hidden.val(item.id != null ? item.id : (item.code != null ? item.code : ''));
                }
                onSelect(item);
                close();
            }

            function syncHiddenWithQuery(q){
                if (!$hidden || !$hidden.length) return;
                q = (q || '').toLowerCase().trim();
                if (!q) {
                    $hidden.val('');
                    return;
                }
                var exact = items.filter(function(item){
                    return (item.text || '').toLowerCase() === q;
                });
                if (exact.length === 1) {
                    $hidden.val(exact[0].id || exact[0].code || '');
                    return;
                }
                var partial = items.filter(function(item){
                    var hay = ((item.text || '') + ' ' + (item.code || '') + ' ' + (item.name || '')).toLowerCase();
                    return hay.indexOf(q) !== -1;
                });
                if (partial.length === 1) {
                    $hidden.val(partial[0].id || partial[0].code || '');
                } else {
                    $hidden.val('');
                }
            }

            function filterItems(q){
                q = (q || '').toLowerCase().trim();
                if (q.length < minChars){
                    close();
                    if (!q) syncHiddenWithQuery('');
                    return;
                }

                syncHiddenWithQuery(q);

                lastResults = items.filter(function(item){
                    var hay = ((item.text || '') + ' ' + (item.code || '') + ' ' + (item.name || '')).toLowerCase();
                    return hay.indexOf(q) !== -1;
                }).slice(0, 30);

                activeIndex = -1;
                if (!lastResults.length){
                    openWithHtml('<div class="ajax-ac-item ajax-ac-muted">Aucun résultat</div>');
                    return;
                }

                var html = '';
                lastResults.forEach(function(item, idx){
                    html += '<div class="ajax-ac-item" data-idx="'+idx+'">' + renderItem(item) + '</div>';
                });
                openWithHtml(html);
            }

            var debouncedFilter = debounce(function(){
                filterItems($input.val());
            }, 120);

            $input.off('input' + ns + ' focus' + ns + ' keydown' + ns + ' blur' + ns);
            $wrap.off('mousedown' + ns);
            $results.off('mousedown' + ns);
            $(document).off('click' + ns);
            $(window).off('scroll' + ns + ' resize' + ns);
            $input.closest('.modal').off('scroll' + ns);

            $input.on('input' + ns, debouncedFilter);
            $input.on('focus' + ns, function(){
                var val = ($input.val() || '').trim();
                if (val.length >= minChars) filterItems(val);
            });

            $input.on('keydown' + ns, function(e){
                if (!$results.is(':visible')) return;
                var count = $results.find('.ajax-ac-item[data-idx]').length;
                if (!count) return;

                if (e.key === 'ArrowDown'){
                    e.preventDefault();
                    setActive(activeIndex + 1 >= count ? 0 : activeIndex + 1);
                } else if (e.key === 'ArrowUp'){
                    e.preventDefault();
                    setActive(activeIndex - 1 < 0 ? count - 1 : activeIndex - 1);
                } else if (e.key === 'Enter'){
                    if (activeIndex >= 0 && lastResults[activeIndex]){
                        e.preventDefault();
                        choose(lastResults[activeIndex]);
                    }
                } else if (e.key === 'Escape'){
                    close();
                }
            });

            function onLocalItemMouseDown(e){
                e.preventDefault();
                var idx = parseInt($(this).data('idx'), 10);
                if (!isNaN(idx) && lastResults[idx]){
                    choose(lastResults[idx]);
                }
            }
            $wrap.on('mousedown' + ns, '.ajax-ac-item[data-idx]', onLocalItemMouseDown);
            $results.on('mousedown' + ns, '.ajax-ac-item[data-idx]', onLocalItemMouseDown);

            $input.on('blur' + ns, function(){
                setTimeout(close, 200);
            });

            $(document).on('click' + ns, function(ev){
                var t = ev.target;
                if ($.contains($wrap[0], t)) return;
                if ($results[0] && $.contains($results[0], t)) return;
                close();
            });
        };

        $(function(){
            AcDropdown.init();
        });
    })();
</script>
