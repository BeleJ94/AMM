<?php $pageTitle = 'Nouvel encaissement - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="page-hero">
                <div class="d-flex align-items-center mb-0">
                    <a href="<?= APP_URL ?>/treasury/encaissements" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h2 class="fw-bold mb-1"><i class="bi bi-receipt-cutoff me-2 text-success"></i>Nouvel encaissement</h2>
                        <div class="text-muted small">Le numero de recu sera genere automatiquement apres enregistrement.</div>
                    </div>
                </div>
            </div>

            <?php afficherFlash(); ?>

            <?php if (!empty($erreurs)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($erreurs as $erreur): ?>
                            <li><?= e($erreur) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card form-card">
                <div class="card-body p-4">
                    <form id="encaissementForm" method="POST" action="<?= APP_URL ?>/treasury/encaissement-ajouter" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_receipt" value="<?= e($_POST['date_receipt'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Payeur <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="payer_name" value="<?= e($_POST['payer_name'] ?? '') ?>" placeholder="Client, agent, partenaire..." required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Mode de paiement <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_method" id="paymentMethodField" required>
                                    <option value="">-- Selectionner --</option>
                                    <option value="especes" <?= ($_POST['payment_method'] ?? '') === 'especes' ? 'selected' : '' ?>>Especes</option>
                                    <option value="banque" <?= ($_POST['payment_method'] ?? '') === 'banque' ? 'selected' : '' ?>>Banque</option>
                                    <option value="mobile_money" <?= ($_POST['payment_method'] ?? '') === 'mobile_money' ? 'selected' : '' ?>>Mobile money</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Objet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="objet" value="<?= e($_POST['objet'] ?? '') ?>" placeholder="Motif de l'encaissement" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Montant <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="amount" min="0" step="0.01" value="<?= e($_POST['amount'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Devise <span class="text-danger">*</span></label>
                                <select class="form-select" name="currency" id="currencyField" required>
                                    <option value="USD" <?= ($_POST['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="CDF" <?= ($_POST['currency'] ?? '') === 'CDF' ? 'selected' : '' ?>>CDF</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Taux CDF/USD</label>
                                <input type="number" class="form-control" name="exchange_rate" id="exchangeRateField" min="0" step="0.0001" value="<?= e($_POST['exchange_rate'] ?? '') ?>" placeholder="Ex: 2850">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Compte <span class="text-danger">*</span></label>
                                <select class="form-select" name="account_id" id="accountField" required>
                                    <option value="">-- Selectionner --</option>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= (int)$compte['id'] ?>" data-type="<?= e($compte['type_compte'] ?? '') ?>" <?= (int)($_POST['account_id'] ?? 0) === (int)$compte['id'] ? 'selected' : '' ?>>
                                            <?= e($compte['nom']) ?> (<?= e($compte['devise']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Reference paiement</label>
                                <input type="text" class="form-control" name="reference_payment" value="<?= e($_POST['reference_payment'] ?? '') ?>" placeholder="Bordereau, transaction mobile money...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Preuve</label>
                                <input type="file" class="form-control" name="proof_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <small class="text-muted">Optionnel: bordereau, capture, facture, justificatif.</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Précision utile pour la trésorerie..."><?= e($_POST['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/treasury/encaissements" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Enregistrer en brouillon</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmEncaissementModal" tabindex="-1" aria-labelledby="confirmEncaissementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width:42px;height:42px">
                        <i class="bi bi-shield-check fs-5"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="confirmEncaissementModalLabel">Confirmer l’encaissement</h5>
                        <div class="text-muted small">Vérifiez les informations avant l’enregistrement.</div>
                    </div>
                </div>
                <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="rounded-3 border bg-light-subtle p-3">
                    <div class="d-flex justify-content-between gap-3 py-1">
                        <span class="text-muted small">Payeur</span>
                        <strong class="small text-end" id="confirmPayer">—</strong>
                    </div>
                    <div class="d-flex justify-content-between gap-3 py-1">
                        <span class="text-muted small">Montant</span>
                        <strong class="text-success text-end" id="confirmAmount">—</strong>
                    </div>
                    <div class="d-flex justify-content-between gap-3 py-1">
                        <span class="text-muted small">Mode de paiement</span>
                        <strong class="small text-end" id="confirmMethod">—</strong>
                    </div>
                    <div class="d-flex justify-content-between gap-3 py-1">
                        <span class="text-muted small">Compte crédité</span>
                        <strong class="small text-end" id="confirmAccount">—</strong>
                    </div>
                </div>
                <div class="alert alert-warning border-0 small mt-3 mb-0">
                    <i class="bi bi-info-circle me-2"></i>Le reçu sera d’abord enregistré au statut <strong>brouillon</strong>.
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-outline-secondary" id="rejectEncaissementButton" data-bs-dismiss="modal">Revenir au formulaire</button>
                <button type="button" class="btn btn-success px-4" id="confirmEncaissementButton">
                    <i class="bi bi-check2-circle me-1"></i>Confirmer l’enregistrement
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    var form = document.getElementById('encaissementForm');
    var currencyField = document.getElementById('currencyField');
    var exchangeRateField = document.getElementById('exchangeRateField');
    var paymentMethodField = document.getElementById('paymentMethodField');
    var accountField = document.getElementById('accountField');
    var modalElement = document.getElementById('confirmEncaissementModal');
    var confirmModal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    var confirmButton = document.getElementById('confirmEncaissementButton');
    var rejectButton = document.getElementById('rejectEncaissementButton');
    var isSubmitting = false;

    function toggleExchangeRate() {
        if (!currencyField || !exchangeRateField) return;
        var isCDF = currencyField.value === 'CDF';
        exchangeRateField.required = isCDF;
        exchangeRateField.disabled = !isCDF;
        if (!isCDF) exchangeRateField.value = '';
    }

    if (currencyField) {
        currencyField.addEventListener('change', toggleExchangeRate);
        toggleExchangeRate();
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (isSubmitting) return;
            event.preventDefault();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var payer = form.querySelector('[name="payer_name"]');
            var amount = form.querySelector('[name="amount"]');
            document.getElementById('confirmPayer').textContent = payer ? payer.value.trim() : '—';
            document.getElementById('confirmAmount').textContent =
                (amount ? Number(amount.value).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0,00')
                + ' ' + (currencyField ? currencyField.value : '');
            document.getElementById('confirmMethod').textContent =
                paymentMethodField && paymentMethodField.selectedIndex >= 0
                    ? paymentMethodField.options[paymentMethodField.selectedIndex].text : '—';
            document.getElementById('confirmAccount').textContent =
                accountField && accountField.selectedIndex >= 0
                    ? accountField.options[accountField.selectedIndex].text.trim() : '—';

            if (confirmModal) confirmModal.show();
        });
    }

    if (confirmButton && form) {
        confirmButton.addEventListener('click', function () {
            isSubmitting = true;
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement…';
            if (confirmModal) confirmModal.hide();
            form.submit();
        });
    }

    if (rejectButton) {
        rejectButton.addEventListener('click', function () {
            Swal.fire({
                icon: 'info',
                title: 'Enregistrement suspendu',
                text: 'Vous pouvez vérifier ou corriger les informations avant de soumettre à nouveau.',
                confirmButtonText: 'Continuer',
                confirmButtonColor: '#1769e0',
                customClass: { popup: 'rounded-3' }
            });
        });
    }

    <?php if (!empty($erreurs)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Enregistrement refusé',
        html: <?= json_encode(implode('<br>', array_map(static fn($message) => e($message), $erreurs)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        confirmButtonText: 'Corriger les informations',
        confirmButtonColor: '#1769e0',
        customClass: { popup: 'rounded-3' }
    });
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
