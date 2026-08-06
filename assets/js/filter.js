/**
 * Smart Product Filter - Main JS
 * مدیریت یکپارچه sort، filter، pagination و URL
 */
jQuery(function ($) {
    'use strict';

    // ========== تابع فرمت قیمت ==========
    function formatPrice(num) {
        return parseInt(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function parsePrice(str) {
        return parseInt(str.toString().replace(/,/g, '').replace(/[۰-۹]/g, d => d.charCodeAt(0) - 1776)) || 0;
    }

    // ========== State ==========
    const state = {
        orderby:   'date',
        paged:     1,
        min_price: parseInt(spfData.min_price),
        max_price: parseInt(spfData.max_price),
        filters:   {},
        toggles:   { instock: false, onsale: false, featured: false },
    };

    // init فیلترها از URL
    initFromUrl();

    // ========== Price Slider ==========
    if ($('#spf-price-slider').length) {
        $('#spf-price-slider').slider({
            range:  true,
            min:    parseInt(spfData.min_price),
            max:    parseInt(spfData.max_price),
            values: [state.min_price, state.max_price],
            slide: function (e, ui) {
                $('#spf-min-price').val(formatPrice(ui.values[0]));
                $('#spf-max-price').val(formatPrice(ui.values[1]));
                state.min_price = ui.values[0];
                state.max_price = ui.values[1];
            },
            stop: function () {
                if (isDesktop()) triggerFilter();
            },
        });

        $('#spf-min-price').val(formatPrice(state.min_price));
        $('#spf-max-price').val(formatPrice(state.max_price));
    }

    // تایپ توی input قیمت
    let priceTimer;
    $(document).on('input', '#spf-min-price, #spf-max-price', function () {
        const min = parsePrice($('#spf-min-price').val()) || parseInt(spfData.min_price);
        const max = parsePrice($('#spf-max-price').val()) || parseInt(spfData.max_price);
        state.min_price = min;
        state.max_price = max;
        if ($('#spf-price-slider').length) {
            $('#spf-price-slider').slider('values', [min, max]);
        }
        if (isDesktop()) {
            clearTimeout(priceTimer);
            priceTimer = setTimeout(triggerFilter, 700);
        }
    });

    // ========== Checkboxes (سایدبار و مودال) ==========
    $(document).on('change', '#spf-sidebar .spf-checkbox, #spf-modal-body .spf-checkbox', function () {
        const tax     = $(this).data('tax');
        const val     = decodeURIComponent($(this).val());
        const checked = $(this).is(':checked');

        if (!state.filters[tax]) state.filters[tax] = [];

        if (checked) {
            if (!state.filters[tax].includes(val)) {
                state.filters[tax].push(val);
            }
        } else {
            state.filters[tax] = state.filters[tax].filter(v => v !== val);
        }

        // sync هر دو طرف
        syncSidebar();
        syncModal();
        updateActiveCount();

        if (isDesktop()) triggerFilter();
    });

    // ========== toggle های سریع (موجود / تخفیف‌دار / ویژه) ==========
    $(document).on('change', '#spf-sidebar .spf-toggle-input, #spf-modal-body .spf-toggle-input', function () {
        const key     = $(this).data('toggle');
        const checked = $(this).is(':checked');

        state.toggles[key] = checked;
        syncToggles();
        state.paged = 1;

        if (isDesktop()) triggerFilter();
    });

    // ========== Sort Buttons ==========
    $(document).on('click', '.spf-sort-btn', function () {
        $('.spf-sort-btn').removeClass('active');
        $(this).addClass('active');
        state.orderby = $(this).data('orderby');
        state.paged   = 1;
        triggerFilter();
    });

    // ========== دکمه اعمال فیلتر (موبایل) ==========
    $(document).on('click', '.spf-btn-apply-modal', function () {
        state.paged = 1;
        triggerFilter();
        $('#spf-filter-modal').modal('hide');
    });

    // ========== ریست فیلترها ==========
    $(document).on('click', '#spf-reset-all, .spf-btn-reset-modal', function () {
        state.filters   = {};
        state.toggles   = { instock: false, onsale: false, featured: false };
        state.min_price = parseInt(spfData.min_price);
        state.max_price = parseInt(spfData.max_price);
        state.paged     = 1;
        state.orderby   = 'date';

        $('#spf-sidebar .spf-checkbox').prop('checked', false);
        syncToggles();
        $('#spf-sidebar .spf-color-option').removeClass('selected');
        $('#spf-sidebar .spf-color-check').remove();
        $('.spf-sort-btn').removeClass('active');
        $('.spf-sort-btn[data-orderby="date"]').addClass('active');
        if ($('#spf-price-slider').length) {
            $('#spf-price-slider').slider('values', [state.min_price, state.max_price]);
        }
        $('#spf-min-price').val(formatPrice(state.min_price));
        $('#spf-max-price').val(formatPrice(state.max_price));

        updateActiveCount();
        syncModal();

        const cleanUrl = spfData.page_url.split('?')[0].replace(/\/page\/\d+\/?/, '/');
        window.history.pushState({}, '', cleanUrl);

        loadProducts();
        $('#spf-filter-modal').modal('hide');
    });

    // ========== باز/بسته شدن بخش‌های فیلتر ==========
    $(document).on('click', '.spf-filter-title', function () {
        const $section = $(this).closest('.spf-filter-section');
        $section.toggleClass('spf-collapsed');
        $(this).find('.spf-arrow').toggleClass('open');
        $(this).next('.spf-filter-options').slideToggle(200);
    });

    // ========== Pagination ==========
    $(document).on('click', '#spf-pagination .woocommerce-pagination a', function (e) {
        e.preventDefault();
        const href  = $(this).attr('href');
        const match = href.match(/page\/(\d+)/);
        state.paged = match ? parseInt(match[1]) : 1;
        triggerFilter();
    });

    // ========== رنگ: تغییر ظاهر ==========
    $(document).on('change', '#spf-sidebar .spf-color-option input, #spf-modal-body .spf-color-option input', function () {
        const $label = $(this).closest('.spf-color-option');
        $label.toggleClass('selected', $(this).is(':checked'));

        if ($(this).is(':checked')) {
            $label.find('.spf-color-swatch-wrap').append('<svg class="spf-color-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>');
        } else {
            $label.find('.spf-color-check').remove();
        }
    });

    // ========== جستجو در ترم‌ها ==========
    $(document).on('input', '.spf-term-search', function () {
        const query = $(this).val().toLowerCase();
        const $list = $(this).closest('.spf-filter-options').find('.spf-terms-list .spf-option');
        $list.each(function () {
            const name = $(this).find('.spf-option-name').text().toLowerCase();
            $(this).toggle(name.includes(query));
        });
    });

    // ========== توابع اصلی ==========

    function triggerFilter() {
        state.paged = state.paged || 1;
        updateUrl();
        loadProducts();
    }

    function loadProducts() {
        const $container = $('#spf-products-container');
        const $loader    = $('#spf-loader');

        $container.css({ opacity: 0.4, 'pointer-events': 'none' });
        $loader.show();
        scrollToProducts();

        $.ajax({
            url:  spfData.ajax_url,
            type: 'POST',
            data: buildRequestData(),
            success: function (res) {
                if (res.success) {
                    $container.html(res.data.products);
                    $('#spf-pagination').html(res.data.pagination);
                    updateResultCount(res.data.found);
                }
            },
            error: function () {
                console.error('SPF: خطا در دریافت محصولات');
            },
            complete: function () {
                $container.css({ opacity: 1, 'pointer-events': 'auto' });
                $loader.hide();
            },
        });
    }

    function buildRequestData() {
        const data = {
            action:    'spf_filter',
            nonce:     spfData.nonce,
            orderby:   state.orderby,
            paged:     state.paged,
            min_price: state.min_price,
            max_price: state.max_price,
            page_url:  spfData.page_url,
        };

        Object.keys(state.filters).forEach(tax => {
            if (state.filters[tax] && state.filters[tax].length > 0) {
                data['filter_' + tax] = state.filters[tax];
            }
        });

        Object.keys(state.toggles).forEach(key => {
            if (state.toggles[key]) data[key] = 1;
        });

        if (spfData.current_tax && spfData.current_term_id) {
            data.current_tax     = spfData.current_tax;
            data.current_term_id = spfData.current_term_id;
        }

        return data;
    }

    function updateUrl() {
        const params = new URLSearchParams();

        if (state.orderby && state.orderby !== 'date') {
            params.set('orderby', state.orderby);
        }

        Object.keys(state.filters).forEach(tax => {
            if (state.filters[tax] && state.filters[tax].length > 0) {
                params.set('filter_' + tax, state.filters[tax].join(','));
            }
        });

        if (state.min_price !== parseInt(spfData.min_price)) {
            params.set('min_price', state.min_price);
        }
        if (state.max_price !== parseInt(spfData.max_price)) {
            params.set('max_price', state.max_price);
        }

        Object.keys(state.toggles).forEach(key => {
            if (state.toggles[key]) params.set(key, 1);
        });

        let baseUrl = spfData.page_url;
        if (state.paged > 1) {
            baseUrl = baseUrl + 'page/' + state.paged + '/';
        }

        const queryString = params.toString();
        const newUrl = queryString ? baseUrl + '?' + queryString : baseUrl;
        window.history.pushState({ spfState: state }, '', newUrl);
    }

    function initFromUrl() {
        const params = new URLSearchParams(window.location.search);

        if (params.get('orderby')) {
            state.orderby = params.get('orderby');
            $('.spf-sort-btn').removeClass('active');
            $(`.spf-sort-btn[data-orderby="${state.orderby}"]`).addClass('active');
        }

        if (params.get('min_price')) state.min_price = parseInt(params.get('min_price'));
        if (params.get('max_price')) state.max_price = parseInt(params.get('max_price'));

        params.forEach((value, key) => {
            if (key.startsWith('filter_')) {
                const tax = key.replace('filter_', '');
                state.filters[tax] = value.split(',').map(decodeURIComponent);
                state.filters[tax].forEach(slug => {
                    $(`#spf-sidebar .spf-checkbox[data-tax="${tax}"][value="${slug}"]`).prop('checked', true);
                });
            }
            if (Object.prototype.hasOwnProperty.call(state.toggles, key)) {
                state.toggles[key] = value === '1';
            }
        });

        syncToggles();
        updateActiveCount();
    }

    function updateActiveCount() {
        let total = 0;
        Object.values(state.filters).forEach(arr => { total += arr.length; });
        const $badge = $('.spf-active-count');
        if (total > 0) {
            $badge.text(total).show();
        } else {
            $badge.hide();
        }
    }

    function syncModal() {
        const $modalBody = $('#spf-modal-body');
        if (!$modalBody.length) return;

        $modalBody.html($('#spf-sidebar').html());

        Object.keys(state.filters).forEach(tax => {
            (state.filters[tax] || []).forEach(val => {
$modalBody.find(`.spf-checkbox[data-tax="${tax}"]`).filter(function() {
    return decodeURIComponent($(this).val()) === val || $(this).val() === val;
}).prop('checked', true);
                $modalBody.find(`.spf-color-option input[data-tax="${tax}"][value="${val}"]`)
                    .closest('.spf-color-option').addClass('selected');
            });
        });

        syncToggles();

        $modalBody.find('#spf-min-price').val(formatPrice(state.min_price));
        $modalBody.find('#spf-max-price').val(formatPrice(state.max_price));
        // init slider توی مودال
        const $modalSlider = $modalBody.find('#spf-price-slider');
        if ($modalSlider.length) {
            $modalSlider.slider({
                range:  true,
                min:    parseInt(spfData.min_price),
                max:    parseInt(spfData.max_price),
                values: [state.min_price, state.max_price],
                slide: function (e, ui) {
                    $modalBody.find('#spf-min-price').val(formatPrice(ui.values[0]));
                    $modalBody.find('#spf-max-price').val(formatPrice(ui.values[1]));
                    state.min_price = ui.values[0];
                    state.max_price = ui.values[1];
                },
                stop: function () {
                    // موبایل: فقط state رو آپدیت کن، اعمال با دکمه
                },
            });
        }
    }

function syncSidebar() {
    $('#spf-sidebar .spf-checkbox').prop('checked', false);
    $('#spf-sidebar .spf-color-option').removeClass('selected');
    $('#spf-sidebar .spf-color-check').remove();
    
    Object.keys(state.filters).forEach(tax => {
        (state.filters[tax] || []).forEach(val => {
            // هم encode شده هم decode شده رو چک کن
            const $cb = $(`#spf-sidebar .spf-checkbox[data-tax="${tax}"]`).filter(function() {
                return decodeURIComponent($(this).val()) === val || $(this).val() === val;
            });
            $cb.prop('checked', true);
            
            // رنگ
            const $colorLabel = $cb.closest('.spf-color-option');
            if ($colorLabel.length) {
                $colorLabel.addClass('selected');
                $colorLabel.find('.spf-color-swatch-wrap').append('<svg class="spf-color-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>');
            }
        });
    });
}

    function scrollToProducts() {
        const $target = $('#spf-sort-bar');
        if (!$target.length) return;

        const targetTop = $target.offset().top - 80;
        // اگه از قبل بالاتر از این نقطه هستیم، لازم نیست اسکرول کنیم
        if ($(window).scrollTop() > targetTop) {
            $('html, body').animate({ scrollTop: targetTop }, 300);
        }
    }

    function updateResultCount(newValue) {
        const $el = $('#spf-result-count-num');
        if (!$el.length) return;

        const from = parseInt($el.attr('data-count')) || 0;
        const to   = parseInt(newValue) || 0;
        $el.attr('data-count', to);

        if (from === to) {
            $el.text(to);
            return;
        }

        const duration  = 500;
        const startTime = performance.now();

        function step(now) {
            const progress = Math.min((now - startTime) / duration, 1);
            const value = Math.round(from + (to - from) * progress);
            $el.text(value);
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                $el.text(to);
            }
        }
        requestAnimationFrame(step);
    }

    function syncToggles() {
        $('#spf-sidebar .spf-toggle-input, #spf-modal-body .spf-toggle-input').each(function () {
            const key     = $(this).data('toggle');
            const checked = !!state.toggles[key];
            $(this).prop('checked', checked);
            $(this).closest('.spf-toggle-item').toggleClass('spf-toggle-active', checked);
        });
    }

    document.addEventListener('show.bs.modal', function (e) {
        if (e.target.id === 'spf-filter-modal') syncModal();
    });

    function isDesktop() {
        return window.innerWidth >= 768;
    }

    window.addEventListener('popstate', function (e) {
        if (e.state && e.state.spfState) {
            Object.assign(state, e.state.spfState);
            loadProducts();
        }
    });
});