(function () {
    'use strict';
    var form = document.getElementById('demandeFondsForm');
    if (!form) return;
    var rows = document.getElementById('requestItems');
    var currency = document.getElementById('currencyField');
    var rate = document.getElementById('exchangeRateField');
    var amount = document.getElementById('amountField');
    var file = document.getElementById('justificationFile');
    var modalEl = document.getElementById('confirmDemandeModal');
    var action = 'draft';
    var nextIndex = rows.children.length;
    function money(number, unit) { return Number(number || 0).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + (unit ? ' ' + unit : ''); }
    function set(id, value) { var el = document.getElementById(id); if (el) el.textContent = value; }
    function total() {
        var cents = 0;
        rows.querySelectorAll('.tr-item').forEach(function (row, index) {
            var quantity = Math.round((Number(row.querySelector('[data-item="quantity"]').value) || 0) * 1000) / 1000;
            var price = Math.round((Number(row.querySelector('[data-item="unit_price"]').value) || 0) * 100) / 100;
            var lineCents = Math.round((quantity * price + Number.EPSILON) * 100);
            cents += lineCents;
            row.querySelector('.tr-line-number').textContent = index + 1;
            row.querySelector('.tr-line-total').textContent = money(lineCents / 100);
            row.querySelectorAll('[data-item]').forEach(function (field) {
                var label = field.dataset.item === 'description' ? 'Bien ou service' : (field.dataset.item === 'quantity' ? 'Quantité' : 'Prix unitaire estimé');
                field.setAttribute('aria-label', label + ', ligne ' + (index + 1));
            });
            var remove = row.querySelector('.tr-remove-item');
            remove.disabled = rows.children.length === 1;
            remove.setAttribute('aria-label', 'Supprimer la ligne ' + (index + 1));
        });
        var value = cents / 100;
        amount.value = value.toFixed(2);
        var isCDF = currency.value === 'CDF';
        var exchange = Number(rate.value);
        rate.disabled = !isCDF;
        rate.required = isCDF;
        document.getElementById('exchangeRateGroup').hidden = !isCDF;
        set('requestTableTotal', money(value, currency.value));
        set('requestSummaryAmount', money(value, currency.value));
        set('requestSummaryEquivalent', isCDF ? (exchange > 0 ? 'Équivalent : ' + money(value / exchange, 'USD') : 'Renseignez le taux pour calculer la contre-valeur USD.') : 'Montant de référence en USD');
        set('requestSummaryRate', isCDF && exchange > 0 ? '1 USD = ' + money(exchange, 'CDF') : '');
        var departmentField = document.getElementById('departmentField');
        set('requestSummaryDepartment', departmentField.value ? departmentField.options[departmentField.selectedIndex].text : '—');
        set('requestSummaryBeneficiary', document.getElementById('beneficiaryField').value.trim() || '—');
        set('requestSummaryItemCount', String(rows.children.length));
        document.getElementById('addRequestItem').disabled = rows.children.length >= 100;
        rows.querySelector('[data-item="unit_price"]').setCustomValidity(value > 0 ? '' : 'Le montant total de la demande doit être supérieur à zéro.');
    }
    form.addEventListener('input', total);
    form.addEventListener('change', total);
    currency.addEventListener('change', total);
    document.getElementById('addRequestItem').addEventListener('click', function () {
        if (rows.children.length >= 100) return;
        var row = rows.querySelector('.tr-item').cloneNode(true);
        row.querySelectorAll('[data-item]').forEach(function (field) { field.name = 'items[' + nextIndex + '][' + field.dataset.item + ']'; field.value = field.dataset.item === 'quantity' ? '1' : ''; field.setCustomValidity(''); });
        nextIndex++;
        rows.appendChild(row);
        total();
        row.querySelector('[data-item="description"]').focus();
    });
    rows.addEventListener('click', function (event) { var remove = event.target.closest('.tr-remove-item'); if (remove && rows.children.length > 1) { remove.closest('.tr-item').remove(); total(); } });
    function fileFeedback() {
        var selected = file.files && file.files[0];
        var invalid = selected && selected.size > 10 * 1024 * 1024;
        file.setCustomValidity(invalid ? 'La pièce jointe dépasse la taille maximale de 10 Mo.' : '');
        set('requestFileFeedback', selected ? selected.name + ' · ' + (selected.size / 1024 / 1024).toLocaleString('fr-FR', {maximumFractionDigits: 2}) + ' Mo' + (invalid ? ' — Fichier trop volumineux.' : '') : '');
        document.getElementById('requestFileFeedback').classList.toggle('text-danger', !!invalid);
        document.getElementById('removeRequestFile').classList.toggle('d-none', !selected);
    }
    file.addEventListener('change', fileFeedback);
    document.getElementById('removeRequestFile').addEventListener('click', function () { file.value = ''; fileFeedback(); file.focus(); });
    form.addEventListener('click', function (event) { var button = event.target.closest('[data-request-action]'); if (button) action = button.dataset.requestAction; });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        total();
        if (!form.reportValidity()) return;
        if (event.submitter && event.submitter.dataset.requestAction) action = event.submitter.dataset.requestAction;
        set('confirmRequestAmount', document.getElementById('requestSummaryAmount').textContent);
        set('confirmRequestEquivalent', document.getElementById('requestSummaryEquivalent').textContent);
        var departmentField = document.getElementById('departmentField');
        set('confirmDepartment', departmentField.value ? departmentField.options[departmentField.selectedIndex].text : '—');
        set('confirmBeneficiary', document.getElementById('beneficiaryField').value.trim());
        set('confirmObject', document.getElementById('objectField').value.trim());
        set('confirmRequestNotice', action === 'submit' ? 'La demande sera transmise au Trésorier A pour contrôle, puis au Comptable senior. Les décisions seront consultables dans le suivi.' : 'La demande sera enregistrée en brouillon. Vous pourrez la consulter et la soumettre ultérieurement.');
        set('confirmRequestButton', action === 'submit' ? 'Confirmer et soumettre' : 'Enregistrer le brouillon');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    document.getElementById('confirmRequestButton').addEventListener('click', function () {
        if (!form.reportValidity()) return;
        document.getElementById('submitRequestField').value = action === 'submit' ? '1' : '';
        this.disabled = true;
        this.textContent = 'Enregistrement…';
        form.querySelectorAll('[data-request-action]').forEach(function (button) { button.disabled = true; });
        HTMLFormElement.prototype.submit.call(form);
    });
    total();
})();
