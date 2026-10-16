<?php // Shared register behaviour for cash movements and bank statement lines. ?>
<script>
(function () {
    var table = document.getElementById(<?= json_encode($movementRegisterId) ?>);
    if (!table) return;
    var details = Object.create(null);
    // DataTables owns the child rows: preserve their server-rendered content before initialisation.
    table.querySelectorAll('tbody > tr.movement-detail-row').forEach(function (detail) {
        details[detail.id] = detail.firstElementChild.innerHTML;
        detail.remove();
    });
    table.querySelectorAll('.movement-toggle').forEach(function (button) {
        button.dataset.registerDetailKey = button.getAttribute('data-bs-target').slice(1);
        button.removeAttribute('data-bs-toggle');
        button.removeAttribute('data-bs-target');
    });
    $(function () {
        if (!$.fn.dataTable || !$.fn.dataTable.isDataTable(table)) return;
        var api = $(table).DataTable();
        var controls = document.querySelector('[data-register-movement="' + table.id + '"]');
        var start = controls ? controls.querySelector('[data-filter-start]') : null;
        var end = controls ? controls.querySelector('[data-filter-end]') : null;
        $.fn.dataTable.ext.search.push(function (settings, values, index) {
            if (settings.nTable !== table) return true;
            var row = api.row(index).node();
            var date = row ? row.dataset.date || '' : '';
            return (!start || !start.value || date >= start.value) && (!end || !end.value || date <= end.value);
        });
        if (controls) {
            controls.addEventListener('submit', function (event) { event.preventDefault(); api.draw(); });
            controls.addEventListener('change', function () { api.draw(); });
            controls.addEventListener('reset', function () { setTimeout(function () { api.draw(); }, 0); });
        }
        $(table).on('click', '.movement-toggle', function () {
            var row = api.row(this.closest('tr'));
            var expanded = !row.child.isShown();
            if (expanded) {
                row.child(details[this.dataset.registerDetailKey] || '', 'movement-register-detail').show();
                row.child().attr('data-export-skip', '1');
            } else row.child.hide();
            this.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            var icon = this.querySelector('i');
            if (icon) icon.className = expanded ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        });
        api.draw();
    });
})();
</script>
