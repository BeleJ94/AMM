<?php $pageTitle = 'Contrats — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <div class="page-hero">
    <div class="d-flex justify-content-between align-items-center mb-0">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Contrats</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard">Accueil</a></li>
                    <li class="breadcrumb-item active">Contrats</li>
                </ol>
            </nav>
        </div>
        <?php if (in_array($_SESSION['user_role'], ['direction','admin','chef_service'])): ?>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/contrats/alerte" class="btn btn-warning">
                    <i class="bi bi-bell me-1"></i>Alertes
                </a>
                <a href="<?= APP_URL ?>/contrats/ajouter" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Nouveau contrat
                </a>
            </div>
        <?php endif; ?>
    </div>
    </div>

    <?php afficherFlash(); ?>

    <!-- Filtres -->
    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="GET" action="<?= APP_URL ?>/contrats/liste">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" name="recherche"
                               placeholder="Partenaire, objet..."
                               value="<?= e($filtres['recherche']) ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="statut">
                            <option value="">Tous les statuts</option>
                            <?php foreach (['actif','expire','resilie','renouvele'] as $s): ?>
                                <option value="<?= $s ?>" <?= $filtres['statut'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="devise">
                            <option value="">Devise</option>
                            <option value="USD" <?= $filtres['devise'] === 'USD' ? 'selected' : '' ?>>USD</option>
                            <option value="CDF" <?= $filtres['devise'] === 'CDF' ? 'selected' : '' ?>>CDF</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                            <a href="<?= APP_URL ?>/contrats/liste" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau -->
    <div class="card section-card">
        <div class="card-header bg-transparent border-0 fw-semibold">
            <i class="bi bi-files me-2"></i><?= $pagination['total'] ?> contrat(s)
        </div>
        <div class="card-body p-0">
            <?php if (empty($contrats)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-x fs-3 d-block mb-2"></i>Aucun contrat trouvé
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tableContrats" class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Partenaire</th>
                            <th>Objet</th>
                            <th>Valeur</th>
                            <th>Devise</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Statut</th>
                            <th>Reconduction</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contrats as $c): ?>
                            <?php
                            $jours = !empty($c['date_fin']) ? (int)ceil((strtotime($c['date_fin']) - time()) / 86400) : null;
                            $ligneCls = '';
                            if ($jours !== null && $jours <= 7 && $c['statut'] === 'actif')  $ligneCls = 'table-danger';
                            elseif ($jours !== null && $jours <= 30 && $c['statut'] === 'actif') $ligneCls = 'table-warning';
                            ?>
                            <tr class="<?= $ligneCls ?>">
                                <td class="fw-semibold"><?= e($c['partenaire']) ?></td>
                                <td class="text-truncate" style="max-width:200px;" title="<?= e($c['objet']) ?>"><?= e($c['objet']) ?></td>
                                <td><?= number_format($c['valeur'], 2, ',', ' ') ?></td>
                                <td><?= e($c['devise']) ?></td>
                                <td><?= formatDate($c['date_debut']) ?></td>
                                <td>
                                    <?= formatDate($c['date_fin']) ?>
                                    <?php if ($jours !== null && $c['statut'] === 'actif' && $jours <= 30): ?>
                                        <span class="badge bg-<?= $jours <= 7 ? 'danger' : 'warning text-dark' ?> ms-1">
                                            <?= $jours < 0 ? 'Expiré' : "J-$jours" ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= badgeStatutContrat($c['statut']) ?>"><?= e($c['statut']) ?></span></td>
                                <td><?= $c['tacite_reconduction'] ? '<span class="badge bg-info">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= APP_URL ?>/contrats/voir/<?= $c['id'] ?>" class="btn btn-outline-primary" title="Voir">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (in_array($_SESSION['user_role'], ['direction','admin','chef_service'])): ?>
                                            <a href="<?= APP_URL ?>/contrats/modifier/<?= $c['id'] ?>" class="btn btn-outline-secondary" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
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

        <!-- Pagination -->
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

<script>
$(document).ready(function() {
    if ($('#tableContrats').length) {
        $('#tableContrats').DataTable({
            paging: false, info: false, searching: false,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json' },
            columnDefs: [{ orderable: false, targets: 8 }]
        });
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
