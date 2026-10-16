<?php $pageTitle = 'Financial Tracking — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="dashboard-hero mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Financial Tracking</h2>
                <p class="text-muted mb-0">Pilotage des revenus, dépenses et budgets mensuels.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/finance/ajouter" class="btn btn-success">
                    <i class="bi bi-plus-lg me-1"></i>Nouveau flux
                </a>
                <a href="<?= APP_URL ?>/finance/budgets?annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?>" class="btn btn-outline-primary">
                    <i class="bi bi-piggy-bank me-1"></i>Budgets
                </a>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="GET" action="<?= APP_URL ?>/finance/overview" class="row g-2">
                <div class="col-md-2">
                    <input type="number" min="2020" max="2100" class="form-control form-control-sm" name="annee" value="<?= (int)$annee ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="mois">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $mois === $m ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-funnel me-1"></i>Appliquer</button>
                </div>
                <div class="col-md-6 text-md-end text-muted small d-flex align-items-center justify-content-md-end">
                    Période analysée: <?= sprintf('%02d', $mois) ?>/<?= (int)$annee ?>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-success"><?= formatMontant((float)$stats['revenus_usd'], 'USD') ?></div>
                <small class="kpi-label">Revenus validés</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-danger"><?= formatMontant((float)$stats['depenses_usd'], 'USD') ?></div>
                <small class="kpi-label">Dépenses validées</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value <?= ((float)$stats['solde_usd'] >= 0) ? 'text-primary' : 'text-danger' ?>">
                    <?= formatMontant((float)$stats['solde_usd'], 'USD') ?>
                </div>
                <small class="kpi-label">Solde net</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-info"><?= number_format((float)$stats['execution_depense_pct'], 1, ',', ' ') ?>%</div>
                <small class="kpi-label">Exécution budget dépenses</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-primary"><?= formatMontant((float)$statsFactures['encours_client_usd'], 'USD') ?></div>
                <small class="kpi-label">Encours clients</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-warning"><?= formatMontant((float)$statsFactures['encours_fournisseur_usd'], 'USD') ?></div>
                <small class="kpi-label">Encours fournisseurs</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3 border-start border-danger border-3">
                <div class="kpi-value text-danger"><?= (int)$statsFactures['retard_count'] ?></div>
                <small class="kpi-label">Factures en retard</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-secondary"><?= (int)$statsFactures['factures_ouvertes'] ?></div>
                <small class="kpi-label">Factures ouvertes</small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card section-card">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-list-check me-2"></i>Transactions récentes
                </div>
                <div class="table-responsive">
                    <table data-register-table data-register-filter-columns='{"2":"Flux","3":"Catégorie","6":"Statut"}' class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Réf</th>
                                <th>Date</th>
                                <th>Flux</th>
                                <th>Catégorie</th>
                                <th>Montant</th>
                                <th>Compte</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactionsRecentes)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">Aucune transaction enregistrée.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($transactionsRecentes as $t): ?>
                                <tr data-register-state="<?= e($t['statut']) ?>" data-register-complete="false">
                                    <td class="fw-semibold"><?= e($t['reference_unique']) ?></td>
                                    <td><?= formatDate($t['date_operation']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $t['type_flux'] === 'revenu' ? 'success' : 'danger' ?>">
                                            <?= e($t['type_flux']) ?>
                                        </span>
                                    </td>
                                    <td><?= e($t['categorie_nom']) ?></td>
                                    <td><?= formatMontant((float)$t['montant'], $t['devise']) ?></td>
                                    <td><?= e($t['compte_nom']) ?></td>
                                    <td data-register-state="<?= e($t['statut']) ?>">
                                        <?php
                                        $badge = 'secondary';
                                        if ($t['statut'] === 'valide') $badge = 'success';
                                        if ($t['statut'] === 'brouillon') $badge = 'warning text-dark';
                                        if ($t['statut'] === 'annule') $badge = 'danger';
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= e($t['statut']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-end">
                    <a href="<?= APP_URL ?>/finance/transactions?annee=<?= (int)$annee ?>&mois=<?= (int)$mois ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card section-card h-100">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-pie-chart me-2"></i>Budget du mois
                </div>
                <div class="card-body">
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Budget dépenses (USD)</span>
                        <strong><?= formatMontant((float)$stats['budget_depense_usd'], 'USD') ?></strong>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted">Réalisé dépenses (USD)</span>
                        <strong><?= formatMontant((float)$stats['realise_depense_usd'], 'USD') ?></strong>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <?php $pct = min(100, max(0, (float)$stats['execution_depense_pct'])); ?>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar <?= $pct > 90 ? 'bg-danger' : 'bg-info' ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                        </div>
                        <small class="text-muted">Exécution: <?= number_format((float)$stats['execution_depense_pct'], 1, ',', ' ') ?>%</small>
                    </div>

                    <h6 class="fw-semibold">Lignes budgétaires</h6>
                    <ul class="list-group list-group-flush">
                        <?php if (empty($budgetsMois)): ?>
                            <li class="list-group-item px-0 text-muted">Aucun budget saisi.</li>
                        <?php endif; ?>
                        <?php foreach ($budgetsMois as $b): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between">
                                <span><?= e($b['categorie_nom']) ?></span>
                                <strong><?= formatMontant((float)$b['montant_prevu'], $b['devise']) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
