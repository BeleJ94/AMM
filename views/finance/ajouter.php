<?php $pageTitle = 'Nouveau flux financier — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="page-hero">
                <div class="d-flex align-items-center mb-0">
                    <a href="<?= APP_URL ?>/finance/transactions" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i></a>
                    <h2 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-success"></i>Nouveau flux financier</h2>
                </div>
            </div>

            <?php afficherFlash(); ?>

            <?php if (!empty($erreurs)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($erreurs as $e): ?>
                            <li><?= e($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card form-card">
                <div class="card-body p-4">
                    <form method="POST" action="<?= APP_URL ?>/finance/ajouter" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date opération <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_operation" value="<?= e($_POST['date_operation'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Type flux <span class="text-danger">*</span></label>
                                <select class="form-select" name="type_flux" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="revenu" <?= ($_POST['type_flux'] ?? '') === 'revenu' ? 'selected' : '' ?>>Revenu</option>
                                    <option value="depense" <?= ($_POST['type_flux'] ?? '') === 'depense' ? 'selected' : '' ?>>Dépense</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Catégorie <span class="text-danger">*</span></label>
                                <select class="form-select" name="categorie_id" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>" data-type="<?= e($c['type_flux']) ?>" <?= ((int)($_POST['categorie_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                            <?= e(ucfirst($c['type_flux'])) ?> - <?= e($c['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Compte <span class="text-danger">*</span></label>
                                <select class="form-select" name="compte_id" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($comptes as $a): ?>
                                        <option value="<?= (int)$a['id'] ?>" <?= ((int)($_POST['compte_id'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>>
                                            <?= e($a['nom']) ?> (<?= e($a['devise']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Montant <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="montant" min="0" step="0.01" value="<?= e($_POST['montant'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Devise <span class="text-danger">*</span></label>
                                <select class="form-select" name="devise" id="deviseField" required>
                                    <option value="USD" <?= ($_POST['devise'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="CDF" <?= ($_POST['devise'] ?? '') === 'CDF' ? 'selected' : '' ?>>CDF</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Taux change (CDF/USD)</label>
                                <input type="number" class="form-control" name="taux_change" id="tauxChangeField" min="0" step="0.0001" value="<?= e($_POST['taux_change'] ?? '') ?>" placeholder="Ex: 2850">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Partenaire</label>
                                <input type="text" class="form-control" name="partenaire" value="<?= e($_POST['partenaire'] ?? '') ?>" placeholder="Client, fournisseur, institution...">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Description opérationnelle du flux..."><?= e($_POST['description'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Pièce jointe</label>
                                <input type="file" class="form-control" name="piece_jointe" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <small class="text-muted">Optionnel: facture, justificatif, bordereau.</small>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/finance/transactions" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Créer en brouillon</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var deviseField = document.getElementById('deviseField');
    var tauxField = document.getElementById('tauxChangeField');

    function toggleTaux() {
        var isCDF = deviseField && deviseField.value === 'CDF';
        if (!tauxField) return;
        tauxField.required = isCDF;
        tauxField.disabled = !isCDF;
        if (!isCDF) {
            tauxField.value = '';
        }
    }

    if (deviseField) {
        deviseField.addEventListener('change', toggleTaux);
        toggleTaux();
    }
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
