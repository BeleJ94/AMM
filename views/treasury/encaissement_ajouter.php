<?php
$pageTitle = 'Nouvel encaissement - ' . APP_NAME;
$valeur = static fn(string $cle, string $defaut = '') => e($_POST[$cle] ?? $defaut);
require_once __DIR__ . '/../layouts/header.php';
?>
<style>
.receipt-workspace{max-width:1320px;margin:auto}.receipt-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}.receipt-heading h1{font-size:1.65rem;color:#142b45;font-weight:800;letter-spacing:-.035em}.receipt-heading p{color:#687b90;margin:0}.receipt-section{background:#fff;border:1px solid #dce4ed;border-radius:12px;padding:24px;margin-bottom:20px}.receipt-section h2{font-size:1rem;color:#19344f;font-weight:750;margin:0 0 5px}.receipt-section-help{color:#728196;font-size:.8rem;margin-bottom:22px}.receipt-workspace .form-label{font-size:.82rem}.receipt-workspace .form-control,.receipt-workspace .form-select{min-height:44px;font-size:.9rem}.receipt-workspace .receipt-amount{font-size:1.7rem;font-weight:750;min-height:58px;font-variant-numeric:tabular-nums}.receipt-summary{position:sticky;top:90px;border:1px solid #dce4ed;border-radius:12px;overflow:hidden;background:#fff}.receipt-summary-top{background:#142e49;color:#fff;padding:24px}.receipt-summary-top .receipt-caption{color:#c1d0df;font-size:.78rem}.receipt-summary-amount{font-size:1.9rem;font-weight:800;overflow-wrap:anywhere;font-variant-numeric:tabular-nums;margin-top:10px}.receipt-summary-body{padding:24px}.receipt-summary dl{margin:0}.receipt-summary dt{font-size:.76rem;color:#758499;font-weight:500}.receipt-summary dd{font-size:.9rem;color:#253b53;margin:4px 0 18px;overflow-wrap:anywhere}.receipt-upload{background:#f8fafc;border:1px dashed #aabbd0;border-radius:10px;padding:20px}.receipt-upload.dragging{background:#eaf3ff;border-color:#1769e0}.receipt-actions{background:#fff;border:1px solid #dce4ed;border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px}.receipt-steps{font-size:.8rem;color:#697d91;display:flex;gap:10px;flex-wrap:wrap}.receipt-steps strong{color:#19344f}.receipt-summary-usd{font-size:1.25rem;font-weight:750;color:#148052;font-variant-numeric:tabular-nums}@media(max-width:991px){.receipt-summary{position:static;margin-bottom:20px}.receipt-heading{align-items:flex-start}.receipt-section{padding:20px}}@media(max-width:575px){.receipt-heading{flex-direction:column}.receipt-actions{align-items:stretch;flex-direction:column}.receipt-actions .btn{flex:1}.receipt-heading h1{font-size:1.4rem}}
</style>
<div class="container-fluid receipt-workspace">
    <nav aria-label="Fil d’Ariane"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/dashboard">Trésorerie</a></li><li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/encaissements">Encaissements</a></li><li class="breadcrumb-item active" aria-current="page">Nouveau</li></ol></nav>
    <header class="receipt-heading"><div><h1>Nouvel encaissement</h1><p>Enregistrez une entrée de fonds et préparez sa validation.</p></div><span class="badge bg-light text-secondary border">Nouvelle opération</span></header>
    <?php afficherFlash(); ?>
    <?php if (!empty($erreurs)): ?><div class="alert alert-danger" role="alert"><strong>L’enregistrement nécessite des corrections.</strong><ul class="mb-0 mt-2"><?php foreach ($erreurs as $erreur): ?><li><?= e($erreur) ?></li><?php endforeach; ?></ul><?php if (!empty($_FILES['proof_file']['name'])): ?><p class="mb-0 mt-2">Veuillez sélectionner à nouveau votre justificatif.</p><?php endif; ?></div><?php endif; ?>
    <form id="encaissementForm" method="POST" action="<?= APP_URL ?>/treasury/encaissement-ajouter" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
    <div class="row g-4"><div class="col-lg-8">
        <section class="receipt-section" aria-labelledby="receiptIdentityTitle"><h2 id="receiptIdentityTitle">Informations de l’opération</h2><p class="receipt-section-help">Identifiez le payeur et le motif de l’encaissement. Les champs marqués * sont obligatoires.</p><div class="row g-3">
            <div class="col-md-8"><label for="payerField" class="form-label">Payeur *</label><input id="payerField" class="form-control" name="payer_name" value="<?= $valeur('payer_name') ?>" placeholder="Nom du client, de l’agent ou du partenaire" required></div>
            <div class="col-md-4"><label for="receiptDate" class="form-label">Date d’encaissement *</label><input id="receiptDate" type="date" class="form-control" name="date_receipt" value="<?= $valeur('date_receipt', date('Y-m-d')) ?>" required></div>
            <div class="col-12"><label for="receiptObject" class="form-label">Objet *</label><input id="receiptObject" class="form-control" name="objet" value="<?= $valeur('objet') ?>" placeholder="Ex. Règlement de la facture…" required></div>
        </div></section>
        <section class="receipt-section" aria-labelledby="receiptAmountTitle"><h2 id="receiptAmountTitle">Montant et affectation</h2><p class="receipt-section-help">Précisez le montant reçu et le compte à créditer.</p><div class="row g-3">
            <div class="col-sm-8"><label for="amountField" class="form-label">Montant encaissé *</label><input id="amountField" type="number" class="form-control receipt-amount" name="amount" min="0.01" step="0.01" value="<?= $valeur('amount') ?>" placeholder="0,00" required></div>
            <div class="col-sm-4"><label for="currencyField" class="form-label">Devise *</label><select class="form-select" name="currency" id="currencyField" required><?php foreach (['USD','CDF'] as $devise): ?><option value="<?= $devise ?>" <?= ($_POST['currency'] ?? 'USD') === $devise ? 'selected' : '' ?>><?= $devise ?></option><?php endforeach; ?></select></div>
            <div class="col-12" id="exchangeRateGroup"><label for="exchangeRateField" class="form-label">Taux de conversion *</label><div class="input-group"><span class="input-group-text">1 USD =</span><input type="number" class="form-control" name="exchange_rate" id="exchangeRateField" min="0.0001" step="0.0001" value="<?= $valeur('exchange_rate') ?>" placeholder="Ex. 2850" aria-describedby="exchangeRateHelp"><span class="input-group-text">CDF</span></div><small id="exchangeRateHelp" class="text-muted">Saisissez le nombre de CDF pour 1 USD.</small></div>
            <div class="col-md-5"><label for="paymentMethodField" class="form-label">Mode de paiement *</label><select class="form-select" name="payment_method" id="paymentMethodField" required><option value="">Sélectionner un mode</option><?php foreach (['especes'=>'Espèces','banque'=>'Banque','mobile_money'=>'Mobile money'] as $code=>$nom): ?><option value="<?= $code ?>" <?= ($_POST['payment_method'] ?? '') === $code ? 'selected' : '' ?>><?= $nom ?></option><?php endforeach; ?></select></div>
            <div class="col-md-7"><label for="accountField" class="form-label">Compte crédité *</label><select class="form-select" name="account_id" id="accountField" aria-describedby="accountHelp accountWarning" required><option value="">Sélectionner un compte</option><?php foreach ($comptes as $compte): ?><option value="<?= (int)$compte['id'] ?>" data-type="<?= e($compte['type_compte']) ?>" data-currency="<?= e($compte['devise']) ?>" <?= (int)($_POST['account_id'] ?? 0) === (int)$compte['id'] ? 'selected' : '' ?>><?= e($compte['nom']) ?> (<?= e($compte['devise']) ?>)</option><?php endforeach; ?></select><small id="accountHelp" class="text-muted">Les comptes sont proposés selon le mode de paiement.</small></div>
            <div class="col-12"><div id="accountWarning" class="alert alert-warning small mb-0" role="status" hidden></div></div>
        </div></section>
        <section class="receipt-section" aria-labelledby="receiptProofTitle"><h2 id="receiptProofTitle">Justificatifs</h2><p class="receipt-section-help">Ajoutez les références utiles au contrôle. Ces informations sont facultatives.</p><div class="row g-3">
            <div class="col-12"><label for="referenceField" class="form-label">Référence du paiement</label><input id="referenceField" class="form-control" name="reference_payment" value="<?= $valeur('reference_payment') ?>" placeholder="N° de bordereau ou référence de transaction"></div>
            <div class="col-12"><div class="receipt-upload" id="proofDropZone"><label for="proofField" class="form-label fw-semibold"><i class="bi bi-paperclip me-1"></i>Pièce justificative</label><p class="text-muted small mb-2">Déposez un fichier ici ou sélectionnez-le sur votre appareil.</p><input id="proofField" type="file" class="form-control" name="proof_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" aria-describedby="proofHelp proofSelection"><div id="proofHelp" class="text-muted small mt-2">PDF, images, Word ou Excel · <?= (int)(MAX_FILE_SIZE / 1024 / 1024) ?> Mo maximum.</div><div id="proofSelection" class="small mt-2" aria-live="polite"></div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="removeProof" hidden>Retirer le fichier</button></div></div>
            <div class="col-12"><label for="notesField" class="form-label">Notes complémentaires</label><textarea id="notesField" class="form-control" name="notes" rows="3" placeholder="Informations utiles au contrôle de l’opération"><?= $valeur('notes') ?></textarea></div>
        </div></section>
    </div><aside class="col-lg-4" aria-label="Synthèse financière"><div class="receipt-summary"><div class="receipt-summary-top"><div class="receipt-caption">MONTANT ENCAISSÉ</div><div id="summaryAmount" class="receipt-summary-amount">—</div><div class="receipt-caption mt-2">Conservé dans sa devise d’origine</div></div><div class="receipt-summary-body"><div role="status" aria-live="polite" aria-atomic="true"><div class="text-muted small mb-1">Contre-valeur USD</div><div id="receiptUsdPreview" class="receipt-summary-usd">—</div><div id="receiptConversionDetail" class="text-muted small mt-2"></div></div><hr class="my-4"><dl><dt>Payeur</dt><dd id="summaryPayer">—</dd><dt>Mode de paiement</dt><dd id="summaryMethod">—</dd><dt>Compte crédité</dt><dd id="summaryAccount">—</dd></dl><div class="border-top pt-3 small text-muted"><strong class="text-dark d-block mb-1">Enregistrement en brouillon</strong>Le numéro de reçu sera généré à l’enregistrement. Une validation sera ensuite nécessaire.</div></div></div></aside></div>
    <div class="receipt-actions mb-4"><div class="receipt-steps"><strong>1. Saisie</strong><span>→</span><span>2. Brouillon</span><span>→</span><span>3. Validation</span></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= APP_URL ?>/treasury/encaissements">Annuler</a><button type="submit" class="btn btn-primary px-4">Vérifier et enregistrer</button></div></div>
    </form>
</div>
<div class="modal fade" id="confirmEncaissementModal" tabindex="-1" aria-labelledby="confirmEncaissementModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow"><div class="modal-header"><h2 class="modal-title fs-5" id="confirmEncaissementModalLabel">Vérifier l’encaissement</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div><div class="modal-body p-4"><dl class="row g-2 mb-3"><?php foreach (['Payer'=>'Payeur','Amount'=>'Montant reçu','Rate'=>'Taux appliqué','Usd'=>'Contre-valeur USD','Method'=>'Mode de paiement','Account'=>'Compte crédité'] as $id=>$nom): ?><dt class="col-5 text-muted fw-normal small"><?= $nom ?></dt><dd class="col-7 text-end fw-semibold small" id="confirm<?= $id ?>">—</dd><?php endforeach; ?></dl><p id="confirmCurrencyWarning" class="alert alert-warning small" hidden></p><p class="bg-light border rounded p-3 small mb-0">L’encaissement sera enregistré en <strong>brouillon</strong>, puis devra être validé.</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Modifier</button><button type="button" id="confirmEncaissementButton" class="btn btn-primary">Enregistrer le brouillon</button></div></div></div></div>
<script>
(function () {
    const form = document.getElementById('encaissementForm');
    const amount = document.getElementById('amountField');
    const currency = document.getElementById('currencyField');
    const rate = document.getElementById('exchangeRateField');
    const method = document.getElementById('paymentMethodField');
    const account = document.getElementById('accountField');
    const proof = document.getElementById('proofField');
    const warning = document.getElementById('accountWarning');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmEncaissementModal'));
    const confirmButton = document.getElementById('confirmEncaissementButton');
    const text = (id, value) => { document.getElementById(id).textContent = value; };
    const selectedText = field => field.value ? field.options[field.selectedIndex].text.trim() : '—';
    const money = (value, code) => Number(value).toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ' + code;
    let submitting = false;
    function updateSummary() {
        const isCDF = currency.value === 'CDF';
        const validAmount = amount.value !== '' && Number.isFinite(Number(amount.value)) && Number(amount.value) > 0;
        const validRate = Number.isFinite(Number(rate.value)) && Number(rate.value) > 0;
        rate.required = isCDF;
        rate.disabled = !isCDF;
        document.getElementById('exchangeRateGroup').hidden = !isCDF;
        rate.setCustomValidity(isCDF && !validRate ? 'Saisissez le nombre de CDF pour 1 USD (taux positif).' : '');
        text('summaryAmount', validAmount ? money(amount.value, currency.value) : '—');
        text('receiptUsdPreview', validAmount && (!isCDF || validRate) ? money(isCDF ? Number(amount.value) / Number(rate.value) : amount.value, 'USD') : '—');
        text('receiptConversionDetail', isCDF ? (validRate ? '1 USD = ' + Number(rate.value).toLocaleString('fr-FR', {maximumFractionDigits:4}) + ' CDF' : 'Renseignez le taux pour obtenir la contre-valeur.') : 'Devise de base · aucune conversion nécessaire.');
        text('summaryPayer', document.getElementById('payerField').value.trim() || '—');
        text('summaryMethod', selectedText(method));
        text('summaryAccount', selectedText(account));
        const option = account.selectedOptions[0];
        const mismatch = account.value && option.dataset.currency !== currency.value;
        warning.hidden = !mismatch;
        warning.textContent = mismatch ? 'Le compte sélectionné est en ' + option.dataset.currency + ', mais le montant est encaissé en ' + currency.value + '. Vérifiez l’affectation avant de continuer.' : '';
    }
    function filterAccounts() {
        const type = {especes:'caisse', banque:'banque', mobile_money:'mobile_money'}[method.value];
        Array.from(account.options).forEach(option => {
            const incompatible = !!option.value && !!type && option.dataset.type !== type;
            option.hidden = incompatible;
            option.disabled = incompatible;
        });
        if (account.selectedOptions[0] && account.selectedOptions[0].disabled) account.value = '';
        updateSummary();
    }
    function updateProof() {
        const file = proof.files[0];
        const oversized = file && file.size > <?= (int)MAX_FILE_SIZE ?>;
        proof.setCustomValidity(oversized ? 'Le fichier dépasse la taille maximale autorisée.' : '');
        text('proofSelection', file ? file.name + ' · ' + (file.size / 1024 / 1024).toLocaleString('fr-FR', {maximumFractionDigits:2}) + ' Mo' + (oversized ? ' — fichier trop volumineux' : '') : '');
        document.getElementById('removeProof').hidden = !file;
    }
    proof.addEventListener('change', updateProof);
    document.getElementById('removeProof').addEventListener('click', () => { proof.value = ''; updateProof(); });
    const dropZone = document.getElementById('proofDropZone');
    ['dragover','dragleave','drop'].forEach(eventName => dropZone.addEventListener(eventName, event => {
        event.preventDefault();
        dropZone.classList.toggle('dragging', eventName === 'dragover');
        if (eventName === 'drop' && event.dataTransfer.files.length) {
            const transfer = new DataTransfer(); transfer.items.add(event.dataTransfer.files[0]); proof.files = transfer.files; updateProof();
        }
    }));
    form.addEventListener('input', updateSummary);
    form.addEventListener('change', updateSummary);
    method.addEventListener('change', filterAccounts);
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (submitting) return;
        updateSummary(); updateProof();
        if (!form.reportValidity()) return;
        text('confirmPayer', document.getElementById('summaryPayer').textContent);
        text('confirmAmount', document.getElementById('summaryAmount').textContent);
        text('confirmUsd', document.getElementById('receiptUsdPreview').textContent);
        text('confirmRate', currency.value === 'CDF' ? document.getElementById('receiptConversionDetail').textContent : 'Sans conversion');
        text('confirmMethod', selectedText(method)); text('confirmAccount', selectedText(account));
        const confirmWarning = document.getElementById('confirmCurrencyWarning');
        confirmWarning.hidden = warning.hidden; confirmWarning.textContent = warning.textContent;
        modal.show();
    });
    confirmButton.addEventListener('click', () => {
        if (submitting) return;
        updateSummary(); updateProof();
        if (!form.reportValidity()) return;
        submitting = true; confirmButton.disabled = true; confirmButton.textContent = 'Enregistrement…';
        modal.hide(); HTMLFormElement.prototype.submit.call(form);
    });
    filterAccounts(); updateProof();
})();
</script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
