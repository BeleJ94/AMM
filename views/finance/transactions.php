<?php $pageTitle = 'Transactions financières — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-cash-stack me-2 text-success"></i>Transactions financières</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/finance/overview">Financial Tracking</a></li>
                        <li class="breadcrumb-item active">Transactions</li>
                    </ol>
                </nav>
            </div>
            <a href="<?= APP_URL ?>/finance/ajouter" class="btn btn-success">
                <i class="bi bi-plus-lg me-1"></i>Nouveau flux
            </a>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="GET" action="<?= APP_URL ?>/finance/transactions">
                <div class="row g-2 align-items-center">
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="type_flux">
                            <option value="">Type flux</option>
                            <option value="revenu" <?= $filtres['type_flux'] === 'revenu' ? 'selected' : '' ?>>Revenu</option>
                            <option value="depense" <?= $filtres['type_flux'] === 'depense' ? 'selected' : '' ?>>Dépense</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="statut">
                            <option value="">Statut</option>
                            <option value="brouillon" <?= $filtres['statut'] === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                            <option value="valide" <?= $filtres['statut'] === 'valide' ? 'selected' : '' ?>>Validé</option>
                            <option value="annule" <?= $filtres['statut'] === 'annule' ? 'selected' : '' ?>>Annulé</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <select class="form-select form-select-sm" name="devise">
                            <option value="">Devise</option>
                            <option value="USD" <?= $filtres['devise'] === 'USD' ? 'selected' : '' ?>>USD</option>
                            <option value="CDF" <?= $filtres['devise'] === 'CDF' ? 'selected' : '' ?>>CDF</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <input type="number" class="form-control form-control-sm" min="2020" max="2100" name="annee" value="<?= (int)$filtres['annee'] ?>">
                    </div>
                    <div class="col-md-1">
                        <select class="form-select form-select-sm" name="mois">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= (int)$filtres['mois'] === $m ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" name="recherche" placeholder="Réf, partenaire, catégorie..." value="<?= e($filtres['recherche']) ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                        <a href="<?= APP_URL ?>/finance/transactions" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card section-card">
        <div class="card-header bg-transparent border-0 fw-semibold">
            <i class="bi bi-files me-2"></i><?= (int)$pagination['total'] ?> transaction(s)
        </div>
        <div class="card-body p-0">
            <?php if (empty($transactions)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-cash-stack fs-3 d-block mb-2"></i>Aucune transaction trouvée
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Réf</th>
                            <th>Date</th>
                            <th>Flux</th>
                            <th>Catégorie</th>
                            <th>Compte</th>
                            <th>Montant</th>
                            <th>USD base</th>
                            <th>Statut</th>
                            <th>Créé par</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($t['reference_unique']) ?></td>
                                <td><?= formatDate($t['date_operation']) ?></td>
                                <td><span class="badge bg-<?= $t['type_flux'] === 'revenu' ? 'success' : 'danger' ?>"><?= e($t['type_flux']) ?></span></td>
                                <td><?= e($t['categorie_nom']) ?></td>
                                <td><?= e($t['compte_nom']) ?></td>
                                <td><?= formatMontant((float)$t['montant'], $t['devise']) ?></td>
                                <td><?= formatMontant((float)$t['montant_usd'], 'USD') ?></td>
                                <td>
                                    <?php
                                    $badge = 'secondary';
                                    if ($t['statut'] === 'valide') $badge = 'success';
                                    if ($t['statut'] === 'brouillon') $badge = 'warning text-dark';
                                    if ($t['statut'] === 'annule') $badge = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= e($t['statut']) ?></span>
                                </td>
                                <td><?= e($t['created_by_nom'] ?? '—') ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <?php if ($t['statut'] === 'brouillon'): ?>
                                            <form method="POST" action="<?= APP_URL ?>/finance/valider/<?= (int)$t['id'] ?>" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Valider">
                                                    <i class="bi bi-check2-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($t['statut'] !== 'annule'): ?>
                                            <form method="POST" action="<?= APP_URL ?>/finance/annuler/<?= (int)$t['id'] ?>" class="d-inline"
                                                  onsubmit="return confirm('Annuler cette transaction ?')">
                                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                                <input type="hidden" name="motif" value="Annulation via interface transactions.">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Annuler">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer d-flex justify-content-end">
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                            <li class="page-item <?= $p == $pagination['page_courante'] ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filtres, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
