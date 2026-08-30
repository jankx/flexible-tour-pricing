(function () {
    'use strict';

    var config = window.jankxTourPricing;
    if (!config || !config.restUrl) {
        return;
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString('vi-VN') + '₫';
    }

    function fetchJson(url) {
        return fetch(url, {
            headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            return response.json();
        });
    }

    function priceUrl(tourId, date, qtyMap) {
        var url = config.restUrl + '/tour/' + tourId + '/price?date=' + encodeURIComponent(date);
        if (qtyMap) {
            Object.keys(qtyMap).forEach(function (group) {
                url += '&groups[' + encodeURIComponent(group) + ']=' + qtyMap[group];
            });
        }
        return url;
    }

    var labelMap = {};
    fetchJson(config.restUrl + '/groups').then(function (res) {
        if (res && res.groups) {
            res.groups.forEach(function (group) {
                labelMap[group.id] = group.label;
            });
        }
    });

    // ------------------------------------------------------------------
    // E-commerce add-to-cart: refresh the total when date / group qty change
    // ------------------------------------------------------------------
    function bindAddToCart() {
        var select = document.querySelector('.jankx-add-to-cart-form .jankx-tour-pricing-departure__select');
        if (!select) {
            return;
        }

        var form = select.closest('form');
        var totalEl = form.querySelector('.jankx-tour-pricing-total');
        var submitBtn = form.querySelector('button[type="submit"]');
        var qtyInputs = form.querySelectorAll('input[name^="group_qty["]');

        function readQty() {
            var map = {};
            qtyInputs.forEach(function (input) {
                var match = input.name.match(/^group_qty\[([^\]]+)\]/);
                if (match) {
                    map[match[1]] = parseInt(input.value, 10) || 0;
                }
            });
            return map;
        }

        function refresh() {
            var date = select.value;
            var qty = readQty();
            var total = Object.keys(qty).reduce(function (sum, g) { return sum + qty[g]; }, 0);

            if (!date) {
                totalEl.textContent = config.i18n.selectDate || 'Chọn ngày khởi hành';
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                return;
            }
            if (total <= 0) {
                totalEl.textContent = '—';
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                totalEl.textContent = config.i18n.loading || '...';
            }

            var tourId = select.getAttribute('data-tour-id');
            fetchJson(priceUrl(tourId, date, qty)).then(function (res) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                if (res && res.success) {
                    totalEl.textContent = fmt(res.subtotal);
                } else {
                    totalEl.textContent = '—';
                }
            }).catch(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                totalEl.textContent = '—';
            });
        }

        select.addEventListener('change', refresh);
        qtyInputs.forEach(function (input) {
            input.addEventListener('input', refresh);
            input.addEventListener('change', refresh);
        });

        refresh();
    }

    // ------------------------------------------------------------------
    // Booking form (quote request): show the per-date price breakdown and
    // attach it to the request so the admin sees the quoted prices.
    // ------------------------------------------------------------------
    function bindBookingForm() {
        var dateInput = document.querySelector('.jankx-travel-booking-form input[name="departure_date"]');
        if (!dateInput) {
            return;
        }

        var form = dateInput.closest('form');
        var tourIdInput = form.querySelector('input[name="tour_id"]');
        var tourId = tourIdInput ? tourIdInput.value : '0';

        function renderBreakdown(res) {
            var existing = form.querySelector('.jankx-tour-pricing-booking-price');
            if (existing) {
                existing.remove();
            }
            var hidden = form.querySelector('input[name="booking_price_json"]');
            if (hidden) {
                hidden.remove();
            }

            if (!(res && res.success)) {
                return;
            }

            var box = document.createElement('div');
            box.className = 'jankx-tour-pricing-booking-price';

            var listItems = Object.keys(res.prices).map(function (g) {
                return '<li>' + (labelMap[g] || g) + ': <strong>' + fmt(res.prices[g]) + '</strong></li>';
            }).join('');

            box.innerHTML = '<span class="jankx-tour-pricing-booking-price__title">'
                + (config.i18n.priceForDate || 'Giá cho ngày này')
                + '</span><ul>' + listItems + '</ul>';

            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                form.insertBefore(box, submitBtn);
            } else {
                form.appendChild(box);
            }

            var hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'booking_price_json';
            hiddenInput.value = JSON.stringify({
                date: res.date,
                prices: res.prices,
                base_price: res.base_price
            });
            form.appendChild(hiddenInput);
        }

        dateInput.addEventListener('change', function () {
            var date = dateInput.value;
            if (!date) {
                renderBreakdown(null);
                return;
            }
            fetchJson(priceUrl(tourId, date, null)).then(renderBreakdown);
        });
    }

    bindAddToCart();
    bindBookingForm();
})();