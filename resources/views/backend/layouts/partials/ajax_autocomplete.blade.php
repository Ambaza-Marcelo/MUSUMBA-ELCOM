<style>
    .ajax-ac-wrap { position: relative; }
    .ajax-ac-results,
    .autocomplete-list,
    .autocomplete-items {
        position: fixed;
        z-index: 10050;
        background: #fff;
        border: 1px solid #ced4da;
        max-height: 240px;
        overflow-y: auto;
        box-shadow: 0 6px 14px rgba(0,0,0,.12);
        display: none;
        box-sizing: border-box;
    }
    .ajax-ac-results { border-top: none; }
    .autocomplete-list,
    .autocomplete-items {
        border: 1px solid #d4d4d4;
    }
    .ajax-ac-item,
    .autocomplete-list div,
    .autocomplete-items div {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        line-height: 1.25;
    }
    .ajax-ac-item:last-child,
    .autocomplete-list div:last-child,
    .autocomplete-items div:last-child { border-bottom: none; }
    .ajax-ac-item:hover,
    .ajax-ac-item.is-active,
    .autocomplete-list div:hover,
    .autocomplete-items div:hover { background: #f8f9fa; }
    .ajax-ac-muted { color: #6c757d; }
    .autocomplete-active {
        background-color: #4CAF50 !important;
        color: #ffffff;
    }
    .autocomplete-wrapper,
    .autocomplete {
        position: relative;
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
                $list.css({
                    position: 'fixed',
                    zIndex: 10050,
                    top: rect.bottom + 'px',
                    left: rect.left + 'px',
                    width: rect.width + 'px',
                    boxSizing: 'border-box'
                });
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

        function bindDropdownPositioning($input, $results, ns, $dropdownParent){
            function positionResults(){
                if (!$results.is(':visible')) return;
                if ($dropdownParent && $dropdownParent.length) {
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
            return positionResults;
        }

        window.attachAjaxAutocomplete = function(opts){
            var $input = opts.$input;
            var $hidden = opts.$hidden || null;
            var $dropdownParent = opts.$dropdownParent || null;
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
            if (!$results.length){
                $results = $('<div class="ajax-ac-results" role="listbox"></div>');
                $wrap.append($results);
            }
            if ($dropdownParent && $dropdownParent.length) {
                $results.addClass('ajax-ac-results--anchored').appendTo($dropdownParent);
            }

            var activeIndex = -1;
            var lastResults = [];
            var ns = '.ajaxAc' + ($input.attr('id') || Math.random().toString(36).slice(2));
            var $interactionRoot = ($dropdownParent && $dropdownParent.length) ? $dropdownParent : $wrap;
            var positionResults = bindDropdownPositioning($input, $results, ns, $dropdownParent);

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
            $(document).off('click' + ns);
            $(window).off('scroll' + ns + ' resize' + ns);

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

            $interactionRoot.on('mousedown' + ns, '.ajax-ac-item[data-idx]', function(e){
                e.preventDefault();
                var idx = parseInt($(this).data('idx'), 10);
                if (!isNaN(idx) && lastResults[idx]){
                    choose(lastResults[idx]);
                }
            });

            $input.on('blur' + ns, function(){
                setTimeout(close, 200);
            });

            $(document).on('click' + ns, function(ev){
                if (!$.contains($interactionRoot[0], ev.target)) close();
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

            if (!$input || !$input.length) return;

            var $wrap = $input.closest('.ajax-ac-wrap');
            if (!$wrap.length){
                $input.wrap('<div class="ajax-ac-wrap"></div>');
                $wrap = $input.closest('.ajax-ac-wrap');
            }
            var $results = $wrap.find('.ajax-ac-results');
            if (!$results.length){
                $results = $('<div class="ajax-ac-results" role="listbox"></div>');
                $wrap.append($results);
            }

            var activeIndex = -1;
            var lastResults = [];
            var ns = '.ajaxLocal' + ($input.attr('id') || Math.random().toString(36).slice(2));
            var positionResults = bindDropdownPositioning($input, $results, ns);

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
            $(document).off('click' + ns);
            $(window).off('scroll' + ns + ' resize' + ns);

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

            $wrap.on('mousedown' + ns, '.ajax-ac-item[data-idx]', function(e){
                e.preventDefault();
                var idx = parseInt($(this).data('idx'), 10);
                if (!isNaN(idx) && lastResults[idx]){
                    choose(lastResults[idx]);
                }
            });

            $input.on('blur' + ns, function(){
                setTimeout(close, 200);
            });

            $(document).on('click' + ns, function(ev){
                if (!$.contains($wrap[0], ev.target)) close();
            });
        };

        $(function(){
            AcDropdown.init();
        });
    })();
</script>
