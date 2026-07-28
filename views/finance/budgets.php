<?php $pageTitle = 'Budgets financiers — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-piggy-bank me-2 text-primary"></i>Budgets mensuels</h2>
                <p class="text-muted mb-0">Prévision des dépenses et suivi d'exécution.</p>
            </div>
            <a href="<?= APP_URL ?>/finance/overview?annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Retour overview
            </a>
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

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card form-card">
                <div class="card-header fw-semibold bg-transparent border-0">Ajouter / Mettre à jour un budget</div>
                <div class="card-body">
                    <form method="POST" action="<?= APP_URL ?>/finance/budgets">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Année</label>
                                <input type="number" class="form-control" min="2020" max="2100" name="annee" value="<?= (int)$annee ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Mois</label>
                                <select class="form-select" name="mois" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $mois === $m ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Catégorie dépense</label>
                                <select class="form-select" name="categorie_id" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($categoriesDepense as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>"><?= e($c['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold">Montant prévu</label>
                                <input type="number" class="form-control" name="montant_prevu" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Devise</label>
                                <select class="form-select" name="devise">
                                    <option value="USD">USD</option>
                                    <option value="CDF">CDF</option>
                                </select>
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Enregistrer budget</button>
                    </form>
                </div>
            </div>

            <div class="card section-card mt-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Budget dépenses (USD)</span>
                        <strong><?= formatMontant((float)$stats['budget_depense_usd'], 'USD') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Réalisé dépenses (USD)</span>
                        <strong><?= formatMontant((float)$stats['realise_depense_usd'], 'USD') ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Exécution</span>
                        <strong><?= number_format((float)$stats['execution_depense_pct'], 1, ',', ' ') ?>%</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card section-card h-100">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    Budgets saisis - <?= sprintf('%02d', $mois) ?>/<?= (int)$annee ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Catégorie</th>
                                    <th>Type</th>
                                    <th>Montant prévu</th>
                                    <th>Devise</th>
                                    <th>Maj</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($budgetsMois)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Aucun budget saisi pour cette période.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($budgetsMois as $b): ?>
                                    <tr>
                                        <td><?= e($b['categorie_nom']) ?></td>
                                        <td><span class="badge bg-danger">dépense</span></td>
                                        <td><?= formatMontant((float)$b['montant_prevu'], $b['devise']) ?></td>
                                        <td><?= e($b['devise']) ?></td>
                                        <td><?= formatDate(substr((string)$b['updated_at'], 0, 10)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
