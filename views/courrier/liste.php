<?php $pageTitle = 'Liste des courriers — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <!-- En-tête -->
    <div class="page-hero">
    <div class="d-flex justify-content-between align-items-center mb-0">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-envelope me-2 text-primary"></i>Courriers</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard">Accueil</a></li>
                    <li class="breadcrumb-item active">Courriers</li>
                </ol>
            </nav>
        </div>
        <?php if (in_array($_SESSION['user_role'], ['reception', 'admin', 'chef_service'])): ?>
            <a href="<?= APP_URL ?>/courrier/ajouter" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nouveau courrier
            </a>
        <?php endif; ?>
    </div>
    </div>

    <?php afficherFlash(); ?>

    <!-- =========================================================
         FILTRES DE RECHERCHE
         ========================================================= -->
    <div class="card filter-card mb-4">
        <div class="card-header bg-transparent fw-semibold border-0">
            <i class="bi bi-funnel me-2"></i>Filtres
        </div>
        <div class="card-body">
            <form method="GET" action="<?= APP_URL ?>/courrier/liste">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" name="recherche"
                               placeholder="Numéro, référence, expéditeur, objet..."
                               value="<?= e($filtres['recherche']) ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="statut">
                            <option value="">Tous les statuts</option>
                            <?php foreach (['Recu','Assigne','Accuse','En_cours','Termine','Clos','Archive','Overdue'] as $s): ?>
                                <option value="<?= $s ?>" <?= $filtres['statut'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="urgence">
                            <option value="">Toutes urgences</option>
                            <?php foreach (['basse','moyenne','haute'] as $u): ?>
                                <option value="<?= $u ?>" <?= $filtres['urgence'] === $u ? 'selected' : '' ?>><?= ucfirst($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" name="date_debut"
                               value="<?= e($filtres['date_debut']) ?>" title="Date début">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" name="date_fin"
                               value="<?= e($filtres['date_fin']) ?>" title="Date fin">
                    </div>
                    <div class="col-md-1">
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                            <a href="<?= APP_URL ?>/courrier/liste" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================
         TABLEAU DES COURRIERS
         ========================================================= -->
    <div class="card section-card">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi bi-list-ul me-2"></i><?= $pagination['total'] ?> courrier(s) trouvé(s)
            </span>
            <a href="<?= APP_URL ?>/courrier/liste?export=csv&<?= http_build_query(array_filter($filtres)) ?>"
               class="btn btn-sm btn-outline-success">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($courriers)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>Aucun courrier trouvé
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tableCourriers" class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Numéro</th>
                            <th>Référence</th>
                            <th>Expéditeur</th>
                            <th>Objet</th>
                            <th>Urgence</th>
                            <th>Statut</th>
                            <th>Réception</th>
                            <th>Deadline</th>
                            <th>Service</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courriers as $c): ?>
                            <tr>
                                <td>
                                    <a href="<?= APP_URL ?>/courrier/voir/<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
                                        <?= e($c['numero_unique']) ?>
                                    </a>
                                </td>
                                <td><?= e($c['reference_document'] ?? '—') ?></td>
                                <td><?= e($c['expediteur']) ?></td>
                                <td class="text-truncate" style="max-width: 220px;" title="<?= e($c['objet']) ?>">
                                    <?= e($c['objet']) ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= badgeUrgence($c['urgence']) ?>">
                                        <?= ucfirst(e($c['urgence'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= badgeStatut($c['statut']) ?>">
                                        <?= e($c['statut']) ?>
                                    </span>
                                </td>
                                <td><?= formatDate($c['date_reception']) ?></td>
                                <td>
                                    <?php if (!empty($c['deadline'])): ?>
                                        <?php
                                        $jours = (int)ceil((strtotime($c['deadline']) - time()) / 86400);
                                        $cls   = $jours < 0 ? 'text-danger fw-bold' : ($jours <= 3 ? 'text-warning fw-semibold' : '');
                                        ?>
                                        <span class="<?= $cls ?>"><?= formatDate($c['deadline']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($c['service_destinataire'] ?? '—') ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= APP_URL ?>/courrier/voir/<?= $c['id'] ?>" class="btn btn-outline-primary" title="Voir">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (in_array($_SESSION['user_role'], ['reception','admin','chef_service'])): ?>
                                            <a href="<?= APP_URL ?>/courrier/assigner/<?= $c['id'] ?>" class="btn btn-outline-warning" title="Assigner">
                                                <i class="bi bi-person-plus"></i>
                                            </a>
                                            <a href="<?= APP_URL ?>/courrier/modifier/<?= $c['id'] ?>" class="btn btn-outline-secondary" title="Modifier">
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
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Page <?= $pagination['page_courante'] ?> / <?= $pagination['total_pages'] ?>
                    (<?= $pagination['total'] ?> résultats)
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                            <li class="page-item <?= $p == $pagination['page_courante'] ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filtres, ['page' => $p])) ?>">
                                    <?= $p ?>
                                </a>
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
    if ($('#tableCourriers').length) {
        $('#tableCourriers').DataTable({
            paging: false,
            info: false,
            searching: false,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json'
            },
            columnDefs: [
                { orderable: false, targets: 9 }
            ]
        });
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
