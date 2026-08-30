(function () {
    'use strict';

    // --------------------------------------------------------------
    // Calendar navigation: highlight a departure row for the clicked day
    // --------------------------------------------------------------
    var cal = document.querySelector('.jankx-tour-pricing-cal');
    if (cal) {
        var months = cal.querySelectorAll('.jankx-tour-pricing-cal__month');
        var monthKeys = Array.prototype.map.call(months, function (m) {
            return m.getAttribute('data-month');
        });
        var currentIndex = Math.max(0, monthKeys.indexOf(cal.getAttribute('data-active-month') || monthKeys[0]));
        var jump = cal.querySelector('.jankx-tour-pricing-cal__jump');

        function showMonth(index) {
            if (index < 0 || index >= months.length) {
                return;
            }
            currentIndex = index;
            months.forEach(function (m, i) {
                m.classList.toggle('is-active', i === currentIndex);
            });
            if (jump) {
                jump.value = monthKeys[currentIndex];
            }
        }

        if (jump) {
            jump.addEventListener('change', function () {
                var index = monthKeys.indexOf(jump.value);
                if (index >= 0) {
                    showMonth(index);
                }
            });
        }

        cal.addEventListener('click', function (event) {
            var prev = event.target.closest('.jankx-tour-pricing-cal__nav--prev');
            if (prev) {
                event.preventDefault();
                showMonth(currentIndex - 1);
                return;
            }
            var next = event.target.closest('.jankx-tour-pricing-cal__nav--next');
            if (next) {
                event.preventDefault();
                showMonth(currentIndex + 1);
                return;
            }

            var day = event.target.closest('.jankx-tour-pricing-cal__day.is-departure');
            if (!day) {
                return;
            }
            event.preventDefault();

            var date = day.getAttribute('data-date');
            var row = document.querySelector('tr[data-date="' + date + '"]');
            if (!row) {
                return;
            }

            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.classList.add('is-highlight');
            window.setTimeout(function () {
                row.classList.remove('is-highlight');
            }, 1500);
        });

        if (months.length) {
            showMonth(currentIndex);
        }
    }

    // --------------------------------------------------------------
    // "Xoá giá": clear the price inputs of a departure row
    // --------------------------------------------------------------
    document.addEventListener('click', function (event) {
        var reset = event.target.closest('.jankx-tour-pricing-calendar__reset');
        if (!reset) {
            return;
        }
        event.preventDefault();

        var row = reset.closest('tr[data-date]');
        if (!row) {
            return;
        }

        row.querySelectorAll('.jankx-tour-pricing-calendar__price').forEach(function (input) {
            input.value = '';
        });
    });
})();