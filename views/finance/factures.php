<?php $pageTitle = 'Factures — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-receipt me-2 text-primary"></i>Factures</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/finance/overview">Financial Tracking</a></li>
                        <li class="breadcrumb-item active">Factures</li>
                    </ol>
                </nav>
            </div>
            <a href="<?= APP_URL ?>/finance/facture-ajouter" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nouvelle facture
            </a>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="card section-card">
        <div class="card-header bg-transparent border-0 fw-semibold">
            <i class="bi bi-files me-2"></i><?= (int)$pagination['total'] ?> facture(s)
        </div>
        <form data-register-filters method="GET" action="<?= APP_URL ?>/finance/factures">
                <input type="hidden" name="per_page" value="<?= (int)$parPage ?>">
                <div class="row g-2 align-items-center">
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="type_facture">
                            <option value="">Type</option>
                            <option value="client" <?= $filtres['type_facture'] === 'client' ? 'selected' : '' ?>>Client</option>
                            <option value="fournisseur" <?= $filtres['type_facture'] === 'fournisseur' ? 'selected' : '' ?>>Fournisseur</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="statut">
                            <option value="">Statut</option>
                            <?php foreach (['brouillon','validee','partiellement_payee','payee','en_retard','annulee'] as $s): ?>
                                <option value="<?= $s ?>" <?= $filtres['statut'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
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
                        <input type="number" class="form-control form-control-sm" name="annee" value="<?= (int)$filtres['annee'] ?>">
                    </div>
                    <div class="col-md-1">
                        <select class="form-select form-select-sm" name="mois">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= (int)$filtres['mois'] === $m ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" name="recherche" placeholder="Numéro, partenaire, objet..." value="<?= e($filtres['recherche']) ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-search"></i></button>
                        <a href="<?= APP_URL ?>/finance/factures" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                    </div>
                </div>
            </form>

        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" data-register-table data-register-mode="server" data-register-total="<?= (int)$pagination['total'] ?>" data-register-page-size="<?= (int)$parPage ?>">
                    <thead class="table-dark">
                        <tr>
                            <th>Numéro</th>
                            <th>Type</th>
                            <th>Partenaire</th>
                            <th>Objet</th>
                            <th>Facture</th>
                            <th>Échéance</th>
                            <th>Total</th>
                            <th>Payé</th>
                            <th>Reste</th>
                            <th>Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($factures as $f): ?>
                            <?php $reste = (float)$f['montant_total'] - (float)$f['montant_paye']; ?>
                            <tr data-register-state="<?= e($f['statut']) ?>" data-register-complete="<?= $f['statut'] === 'payee' ? 'true' : 'false' ?>">
                                <td class="fw-semibold"><?= e($f['numero_facture']) ?></td>
                                <td><span class="badge bg-<?= $f['type_facture'] === 'client' ? 'primary' : 'warning text-dark' ?>"><?= e($f['type_facture']) ?></span></td>
                                <td><?= e($f['partenaire']) ?></td>
                                <td><?= e($f['objet']) ?></td>
                                <td><?= formatDate($f['date_facture']) ?></td>
                                <td><?= formatDate($f['date_echeance']) ?></td>
                                <td><?= formatMontant((float)$f['montant_total'], $f['devise']) ?></td>
                                <td><?= formatMontant((float)$f['montant_paye'], $f['devise']) ?></td>
                                <td class="<?= $reste > 0 ? 'text-danger fw-semibold' : 'text-success' ?>"><?= formatMontant($reste, $f['devise']) ?></td>
                                <td data-register-state="<?= e($f['statut']) ?>"><span class="badge bg-secondary"><?= e($f['statut']) ?></span></td>
                                <td class="text-center">
                                    <a href="<?= APP_URL ?>/finance/facture-voir/<?= (int)$f['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer d-flex justify-content-end" data-register-pagination>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                            <li class="page-item <?= $p == $pagination['page_courante'] ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filtres, ['page' => $p, 'per_page' => $parPage])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
