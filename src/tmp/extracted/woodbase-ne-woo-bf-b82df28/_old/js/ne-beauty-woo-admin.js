jQuery(function ($) {

    /* =========================
       DEBOUNCE helper
    ========================= */
    function nebfDebounce(fn, delay) {
        let timer = null;
        return function () {
            const context = this;
            const args = arguments;

            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    /* =========================
       SÖK-indikator
    ========================= */
    const $searchInput = $('#nebf-live-search');

    // Skapa indikator om den inte finns
    if (!$('#nebf-search-indicator').length) {
        $('<span id="nebf-search-indicator" style="margin-left:8px; display:none; font-style:italic;">Söker…</span>')
            .insertAfter($searchInput);
    }
    const $indicator = $('#nebf-search-indicator');

    /* =========================
       ACCORDION
    ========================= */
    $(document).on('click', 'tr.product-row', function (e) {
        // Ignorera klick på checkbox eller label
        if ($(e.target).is('input[type="checkbox"], label')) return;

        const $row = $(this);
        const accId = $row.data('accordion');
        const $accordion = $('#' + accId);
        if (!$accordion.length) return;

        // Stäng alla andra accordion-rader och ta bort 'is-open' från andra rader
        $('tr.accordion-row').not($accordion).hide();
        $('tr.product-row').not($row).removeClass('is-open');

        // Toggle den klickade raden
        $accordion.toggle();
        $row.toggleClass('is-open');
    });

    /* =========================
       VÄLJ ALLA / AVMARKERA ALLA
    ========================= */
    const $selectAllBtn = $('#nebf-select-all');

    $(document).on('click', '#nebf-select-all', function (e) {
        e.preventDefault();

        const $checkboxes = $('tr.product-row input[type="checkbox"]');

        // Om minst en inte är markerad → markera alla, annars avmarkera alla
        const allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;

        if (allChecked) {
            $checkboxes.prop('checked', false).trigger('change');
            $selectAllBtn.text('Välj alla');
        } else {
            $checkboxes.prop('checked', true).trigger('change');
            $selectAllBtn.text('Avmarkera alla');
        }
    });

    /* =========================
       LIVE-SÖK (3+ tecken)
    ========================= */
    const handleLiveSearch = nebfDebounce(function () {
        const value = $searchInput.val().toLowerCase();
        const doSearch = value.length >= 3;

        $('tr.product-row').each(function () {
            const $row = $(this);
            const text = $row.text().toLowerCase();
            const accId = $row.data('accordion');
            const match = text.indexOf(value) !== -1;

            if (!doSearch) {
                $row.show();
                return;
            }

            $row.toggle(match);

            // Stäng detaljer om huvudraden döljs
            if (!match && accId) {
                $('#' + accId).hide();
            }
        });

        $indicator.hide();
    }, 300);

    $(document).on('keyup', '#nebf-live-search', function () {
        if (this.value.length >= 3) {
            $indicator.show();
        } else {
            $indicator.hide();
        }
        handleLiveSearch();
    });

    /* =========================
       MARKERA VALD RAD
    ========================= */
    $(document).on('change', 'tr.product-row input[type="checkbox"]', function () {
        const $row = $(this).closest('tr.product-row');
        $row.toggleClass('is-selected', this.checked);

        // Uppdatera knapptext dynamiskt
        const $checkboxes = $('tr.product-row input[type="checkbox"]');
        const allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
        $selectAllBtn.text(allChecked ? 'Avmarkera alla' : 'Välj alla');
    });

    // Om sidan laddas med redan valda checkboxar
    $('tr.product-row input[type="checkbox"]:checked').each(function () {
        $(this).closest('tr.product-row').addClass('is-selected');
    });

});
