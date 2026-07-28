<?php $pageTitle = 'Nouvelle facture — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="page-hero">
                <div class="d-flex align-items-center mb-0">
                    <a href="<?= APP_URL ?>/finance/factures" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i></a>
                    <h2 class="fw-bold mb-0"><i class="bi bi-file-earmark-plus me-2 text-primary"></i>Nouvelle facture</h2>
                </div>
            </div>

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
                    <form method="POST" action="<?= APP_URL ?>/finance/facture-ajouter" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Type facture *</label>
                                <select class="form-select" name="type_facture" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="client" <?= ($_POST['type_facture'] ?? '') === 'client' ? 'selected' : '' ?>>Client</option>
                                    <option value="fournisseur" <?= ($_POST['type_facture'] ?? '') === 'fournisseur' ? 'selected' : '' ?>>Fournisseur</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Partenaire *</label>
                                <input type="text" class="form-control" name="partenaire" value="<?= e($_POST['partenaire'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Objet *</label>
                                <input type="text" class="form-control" name="objet" value="<?= e($_POST['objet'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date facture *</label>
                                <input type="date" class="form-control" name="date_facture" value="<?= e($_POST['date_facture'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date échéance *</label>
                                <input type="date" class="form-control" name="date_echeance" value="<?= e($_POST['date_echeance'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Montant total *</label>
                                <input type="number" min="0" step="0.01" class="form-control" name="montant_total" value="<?= e($_POST['montant_total'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Devise *</label>
                                <select class="form-select" name="devise" required>
                                    <option value="USD" <?= ($_POST['devise'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="CDF" <?= ($_POST['devise'] ?? '') === 'CDF' ? 'selected' : '' ?>>CDF</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" name="description" rows="3"><?= e($_POST['description'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Pièce jointe</label>
                                <input type="file" class="form-control" name="piece_jointe" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/finance/factures" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Créer facture</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
