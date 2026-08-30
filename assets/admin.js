(function () {
    'use strict';

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