/**
 * jankx/tour-service-info – Frontend interaction
 *
 * Wires up the date chips, group-qty steppers, live price fetch,
 * "Thêm vào giỏ hàng" and "Đặt ngay" buttons.
 *
 * Relies on jankxTourPricing.restUrl injected by TourPricingExtension.
 */
(function () {
    'use strict';

    var config = window.jankxTourPricing;
    if (!config || !config.restUrl) { return; }

    /* ── helpers ────────────────────────────────────────────── */
    function fmt(n) {
        return Number(n || 0).toLocaleString('vi-VN') + ' ₫';
    }

    function fetchJson(url) {
        return fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); });
    }

    function priceUrl(tourId, date, qtyMap) {
        var url = config.restUrl + '/tour/' + tourId + '/price?date=' + encodeURIComponent(date);
        if (qtyMap) {
            Object.keys(qtyMap).forEach(function (g) {
                url += '&groups[' + encodeURIComponent(g) + ']=' + qtyMap[g];
            });
        }
        return url;
    }

    /* ── label map (group id → human label) ─────────────────── */
    var labelMap  = {};
    var pricesMap = {}; // { groupId: pricePerPerson } for selected date
    fetchJson(config.restUrl + '/groups').then(function (res) {
        if (res && res.groups) {
            res.groups.forEach(function (g) { labelMap[g.id] = g.label; });
        }
    });

    /* ── initialise each block on the page ──────────────────── */
    function initBlock(block) {
        var tourId     = block.dataset.tourId;
        if (!tourId) { return; }

        var chips      = block.querySelectorAll('.jtsi-date-chip[data-date]');
        var qtyRows    = block.querySelectorAll('.jtsi-qty-row');
        var totalEl    = block.querySelector('.jtsi-footer__total');
        var hiddenDate = block.querySelector('.jtsi-hidden-date');
        var addBtn     = block.querySelector('.jtsi-btn--add-cart');
        var bookBtn    = block.querySelector('.jtsi-btn--book-now');

        var selectedDate = '';

        /* ── read qty map from inputs ───────────────────────── */
        function readQty() {
            var map = {};
            qtyRows.forEach(function (row) {
                var g   = row.dataset.group;
                var inp = row.querySelector('.jtsi-qty-input');
                if (g && inp) {
                    map[g] = Math.max(0, parseInt(inp.value, 10) || 0);
                }
            });
            return map;
        }

        /* ── update per-row price display ───────────────────── */
        function refreshRowPrices() {
            qtyRows.forEach(function (row) {
                var g        = row.dataset.group;
                var priceEl  = row.querySelector('[data-group-price]');
                var inp      = row.querySelector('.jtsi-qty-input');
                if (!priceEl) { return; }
                var unitPrice = pricesMap[g] || 0;
                if (unitPrice > 0) {
                    priceEl.textContent = fmt(unitPrice);
                } else {
                    priceEl.textContent = '—';
                }
            });
        }

        /* ── fetch price and update total ───────────────────── */
        function refreshTotal() {
            if (!selectedDate) {
                totalEl.textContent = '0 ₫';
                totalEl.classList.remove('is-nonzero');
                if (addBtn) { addBtn.disabled = true; }
                if (bookBtn) { bookBtn.disabled = true; }
                return;
            }

            var qty   = readQty();
            var total = Object.values(qty).reduce(function (s, v) { return s + v; }, 0);

            if (total <= 0) {
                totalEl.textContent = '0 ₫';
                totalEl.classList.remove('is-nonzero');
                if (addBtn) { addBtn.disabled = true; }
                if (bookBtn) { bookBtn.disabled = true; }
                return;
            }

            if (addBtn) { addBtn.disabled = true; }
            if (bookBtn) { bookBtn.disabled = true; }
            totalEl.textContent = '...';

            fetchJson(priceUrl(tourId, selectedDate, qty)).then(function (res) {
                if (res && res.success) {
                    // Cache unit prices per group for row display
                    if (res.prices) {
                        Object.assign(pricesMap, res.prices);
                        refreshRowPrices();
                    }
                    totalEl.textContent = fmt(res.subtotal);
                    totalEl.classList.toggle('is-nonzero', res.subtotal > 0);
                    if (addBtn) { addBtn.disabled = false; }
                    if (bookBtn) { bookBtn.disabled = false; }
                } else {
                    totalEl.textContent = '—';
                    totalEl.classList.remove('is-nonzero');
                }
            }).catch(function () {
                totalEl.textContent = '—';
                totalEl.classList.remove('is-nonzero');
                if (addBtn) { addBtn.disabled = false; }
                if (bookBtn) { bookBtn.disabled = false; }
            });
        }

        /* ── fetch unit prices for a date (no qty) ──────────── */
        function fetchDatePrices(date) {
            fetchJson(priceUrl(tourId, date, null)).then(function (res) {
                if (res && res.prices) {
                    pricesMap = res.prices;
                    refreshRowPrices();
                }
            });
        }

        /* ── date chip click ────────────────────────────────── */
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                chips.forEach(function (c) {
                    c.classList.remove('is-selected');
                    c.setAttribute('aria-pressed', 'false');
                });
                chip.classList.add('is-selected');
                chip.setAttribute('aria-pressed', 'true');
                selectedDate = chip.dataset.date;
                if (hiddenDate) { hiddenDate.value = selectedDate; }
                fetchDatePrices(selectedDate);
                refreshTotal();
            });
        });

        /* ── stepper buttons ────────────────────────────────── */
        qtyRows.forEach(function (row) {
            var inp  = row.querySelector('.jtsi-qty-input');
            var decB = row.querySelector('[data-action="dec"]');
            var incB = row.querySelector('[data-action="inc"]');
            if (!inp) { return; }

            if (decB) {
                decB.addEventListener('click', function () {
                    var v = parseInt(inp.value, 10) || 0;
                    if (v > parseInt(inp.min || 0, 10)) {
                        inp.value = v - 1;
                        refreshTotal();
                    }
                });
            }
            if (incB) {
                incB.addEventListener('click', function () {
                    var v = parseInt(inp.value, 10) || 0;
                    inp.value = v + 1;
                    refreshTotal();
                });
            }
            inp.addEventListener('input', refreshTotal);
            inp.addEventListener('change', refreshTotal);
        });

        /* ── "Thêm vào giỏ hàng" ───────────────────────────── */
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.addEventListener('click', function () {
                if (!selectedDate) { return; }
                var qty  = readQty();
                var form = buildCartForm(tourId, selectedDate, qty, 'add');
                document.body.appendChild(form);
                form.submit();
            });
        }

        /* ── "Đặt ngay" ────────────────────────────────────── */
        if (bookBtn) {
            bookBtn.disabled = true;
            bookBtn.addEventListener('click', function () {
                if (!selectedDate) { return; }
                var qty  = readQty();
                var form = buildCartForm(tourId, selectedDate, qty, 'book');
                document.body.appendChild(form);
                form.submit();
            });
        }
    }

    /* ── build a hidden form to submit to WC / custom cart ──── */
    function buildCartForm(tourId, date, qtyMap, action) {
        var form = document.createElement('form');
        form.method = 'post';
        form.style.display = 'none';

        // Action URL: reuse current page – WooCommerce / custom handler picks it up
        form.action = window.location.href;

        function addField(name, value) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = name;
            inp.value = value;
            form.appendChild(inp);
        }

        addField('add-to-cart',    tourId);
        addField('product_id',     tourId);
        addField('departure_date', date);
        addField('jankx_action',   action === 'book' ? 'book_now' : 'add_to_cart');

        Object.keys(qtyMap).forEach(function (g) {
            addField('group_qty[' + g + ']', qtyMap[g]);
        });

        // WP nonce if available
        if (config.nonce) {
            addField('jankx_nonce', config.nonce);
        }

        return form;
    }

    /* ── boot ───────────────────────────────────────────────── */
    function boot() {
        document.querySelectorAll('.jtsi-block').forEach(initBlock);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
