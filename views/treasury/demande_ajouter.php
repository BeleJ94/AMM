<?php $pageTitle = 'Nouvelle demande de fonds - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="page-hero">
                <div class="d-flex align-items-center mb-0">
                    <a href="<?= APP_URL ?>/treasury/demandes" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h2 class="fw-bold mb-1"><i class="bi bi-card-checklist me-2 text-primary"></i>Nouvelle demande de fonds</h2>
                        <div class="text-muted small">Le justificatif est obligatoire avant soumission.</div>
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
                    <form id="demandeFondsForm" method="POST" action="<?= APP_URL ?>/treasury/demande-ajouter" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                        <input type="hidden" name="soumettre" id="submitRequestField" value="">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="request_date" value="<?= e($_POST['request_date'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Beneficiaire <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="beneficiary" value="<?= e($_POST['beneficiary'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Urgence <span class="text-danger">*</span></label>
                                <select class="form-select" name="urgency" id="urgencyField" required>
                                    <?php $urgencyValue = $_POST['urgency'] ?? 'moyenne'; ?>
                                    <option value="basse" <?= $urgencyValue === 'basse' ? 'selected' : '' ?>>Basse</option>
                                    <option value="moyenne" <?= $urgencyValue === 'moyenne' ? 'selected' : '' ?>>Moyenne</option>
                                    <option value="haute" <?= $urgencyValue === 'haute' ? 'selected' : '' ?>>Haute</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Objet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="objet" value="<?= e($_POST['objet'] ?? '') ?>" placeholder="Ex: Achat fournitures, frais terrain, paiement fournisseur..." required>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Contexte, details, reference facture ou besoin..."><?= e($_POST['description'] ?? '') ?></textarea>
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
                                <label class="form-label fw-semibold">Categorie <span class="text-danger">*</span></label>
                                <select class="form-select" name="category_id" id="categoryField" required>
                                    <option value="">-- Selectionner --</option>
                                    <?php foreach ($categories as $categorie): ?>
                                        <option value="<?= (int)$categorie['id'] ?>" <?= (int)($_POST['category_id'] ?? 0) === (int)$categorie['id'] ? 'selected' : '' ?>>
                                            <?= e($categorie['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Compte souhaite</label>
                                <select class="form-select" name="preferred_account_id">
                                    <option value="">-- Optionnel --</option>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= (int)$compte['id'] ?>" <?= (int)($_POST['preferred_account_id'] ?? 0) === (int)$compte['id'] ? 'selected' : '' ?>>
                                            <?= e($compte['nom']) ?> (<?= e($compte['devise']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Justificatif <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="justification_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                                <small class="text-muted">Facture, bon d'achat, note de besoin ou autre piece justificative.</small>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/treasury/demandes" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-outline-primary" data-request-action="draft">
                                <i class="bi bi-save me-1"></i>Enregistrer brouillon
                            </button>
                            <button type="submit" class="btn btn-primary" data-request-action="submit">
                                <i class="bi bi-send me-1"></i>Enregistrer et soumettre
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDemandeModal" tabindex="-1" aria-labelledby="confirmDemandeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width:42px;height:42px">
                        <i class="bi bi-shield-check fs-5"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="confirmDemandeModalLabel">Confirmer la demande de fonds</h5>
                        <div class="text-muted small" id="confirmRequestSubtitle">Vérifiez les informations avant l’enregistrement.</div>
                    </div>
                </div>
                <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="rounded-3 border bg-light-subtle p-3">
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="text-muted small">Bénéficiaire</span><strong class="small text-end" id="confirmBeneficiary">—</strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="text-muted small">Objet</span><strong class="small text-end" id="confirmObject">—</strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="text-muted small">Montant</span><strong class="text-primary text-end" id="confirmRequestAmount">—</strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="text-muted small">Catégorie</span><strong class="small text-end" id="confirmCategory">—</strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="text-muted small">Urgence</span><strong class="small text-end" id="confirmUrgency">—</strong></div>
                </div>
                <div class="alert border-0 small mt-3 mb-0" id="confirmRequestNotice"></div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-outline-secondary" id="cancelRequestConfirmation" data-bs-dismiss="modal">Revenir au formulaire</button>
                <button type="button" class="btn btn-primary px-4" id="confirmRequestButton"><i class="bi bi-check2-circle me-1"></i>Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    var form = document.getElementById('demandeFondsForm');
    var currencyField = document.getElementById('currencyField');
    var exchangeRateField = document.getElementById('exchangeRateField');
    var categoryField = document.getElementById('categoryField');
    var urgencyField = document.getElementById('urgencyField');
    var submitRequestField = document.getElementById('submitRequestField');
    var modalElement = document.getElementById('confirmDemandeModal');
    var confirmModal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    var confirmButton = document.getElementById('confirmRequestButton');
    var cancelButton = document.getElementById('cancelRequestConfirmation');
    var requestedAction = 'draft';
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
        form.addEventListener('click', function (event) {
            var actionButton = event.target.closest('[data-request-action]');
            if (actionButton) requestedAction = actionButton.getAttribute('data-request-action');
        });

        form.addEventListener('submit', function (event) {
            if (isSubmitting) return;
            event.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var beneficiary = form.querySelector('[name="beneficiary"]');
            var objectField = form.querySelector('[name="objet"]');
            var amount = form.querySelector('[name="amount"]');
            document.getElementById('confirmBeneficiary').textContent = beneficiary ? beneficiary.value.trim() : '—';
            document.getElementById('confirmObject').textContent = objectField ? objectField.value.trim() : '—';
            document.getElementById('confirmRequestAmount').textContent =
                (amount ? Number(amount.value).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0,00')
                + ' ' + (currencyField ? currencyField.value : '');
            document.getElementById('confirmCategory').textContent =
                categoryField && categoryField.selectedIndex >= 0 ? categoryField.options[categoryField.selectedIndex].text.trim() : '—';
            document.getElementById('confirmUrgency').textContent =
                urgencyField && urgencyField.selectedIndex >= 0 ? urgencyField.options[urgencyField.selectedIndex].text.trim() : '—';

            var isSubmission = requestedAction === 'submit';
            document.getElementById('confirmRequestSubtitle').textContent = isSubmission
                ? 'La demande entrera immédiatement dans le circuit de validation.'
                : 'La demande restera modifiable au statut brouillon.';
            var notice = document.getElementById('confirmRequestNotice');
            notice.className = 'alert border-0 small mt-3 mb-0 ' + (isSubmission ? 'alert-warning' : 'alert-info');
            notice.innerHTML = isSubmission
                ? '<i class="bi bi-send me-2"></i>Après confirmation, la demande sera transmise au Comptable senior.'
                : '<i class="bi bi-info-circle me-2"></i>Le brouillon pourra être vérifié avant sa soumission.';
            confirmButton.className = 'btn px-4 ' + (isSubmission ? 'btn-primary' : 'btn-outline-primary');
            confirmButton.innerHTML = '<i class="bi ' + (isSubmission ? 'bi-send' : 'bi-save') + ' me-1"></i>'
                + (isSubmission ? 'Confirmer et soumettre' : 'Enregistrer le brouillon');

            if (confirmModal) confirmModal.show();
        });
    }

    if (confirmButton && form) {
        confirmButton.addEventListener('click', function () {
            isSubmitting = true;
            submitRequestField.value = requestedAction === 'submit' ? '1' : '';
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement…';
            if (confirmModal) confirmModal.hide();
            form.submit();
        });
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', function () {
            Swal.fire({
                icon: 'info',
                title: 'Enregistrement suspendu',
                text: 'Vous pouvez vérifier ou corriger la demande avant de continuer.',
                confirmButtonText: 'Continuer',
                confirmButtonColor: '#1769e0',
                customClass: { popup: 'rounded-3' }
            });
        });
    }

    <?php if (!empty($erreurs)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Demande refusée',
        html: <?= json_encode(implode('<br>', array_map(static fn($message) => e($message), $erreurs)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        confirmButtonText: 'Corriger les informations',
        confirmButtonColor: '#1769e0',
        customClass: { popup: 'rounded-3' }
    });
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
