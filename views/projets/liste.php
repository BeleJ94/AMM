<?php $pageTitle = 'Projets — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-diagram-3 me-2 text-primary"></i>Module Projets</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard">Accueil</a></li>
                        <li class="breadcrumb-item active">Projets</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/projets/calendrier" class="btn btn-outline-primary">
                    <i class="bi bi-calendar3 me-1"></i>Calendrier
                </a>
                <a href="<?= APP_URL ?>/projets/ajouter" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Nouveau projet
                </a>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-primary"><?= (int)$stats['total_projets'] ?></div>
                <small class="kpi-label">Projets total</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-success"><?= (int)$stats['projets_en_cours'] ?></div>
                <small class="kpi-label">En cours</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-danger"><?= (int)$stats['projets_en_retard'] ?></div>
                <small class="kpi-label">En retard</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card kpi-card text-center py-3">
                <div class="kpi-value text-warning"><?= formatMontant((float)$stats['budget_total_usd'], 'USD') ?></div>
                <small class="kpi-label">Budget cumule (USD)</small>
            </div>
        </div>
    </div>

    <div class="card section-card">
        <div class="card-header bg-transparent border-0 fw-semibold">
            <i class="bi bi-kanban me-2"></i><?= (int)$pagination['total'] ?> projet(s)
        </div>
        <div class="card-body border-bottom py-2">
            <form data-register-filters method="GET" action="<?= APP_URL ?>/projets/liste">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" name="recherche" aria-label="Rechercher" placeholder="Titre, description..." value="<?= e($filtres['recherche']) ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="statut" aria-label="Statut">
                            <option value="">Tous les statuts</option>
                            <?php foreach (['non_demarre', 'en_cours', 'termine', 'en_retard', 'suspendu'] as $s): ?>
                                <option value="<?= $s ?>" <?= $filtres['statut'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" name="responsable_id" aria-label="Responsable">
                            <option value="">Tous les responsables</option>
                            <?php foreach ($responsables as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= (int)$filtres['responsable_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                                    <?= e($u['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex gap-1">
                            <button type="submit" title="Appliquer les filtres" aria-label="Appliquer les filtres" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                            <a href="<?= APP_URL ?>/projets/liste" title="Effacer les filtres" aria-label="Effacer les filtres" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body p-0">

                <div class="table-responsive">
                    <table id="tableProjets" class="table table-sm table-hover align-middle compact mb-0" data-register-table data-register-mode="server" data-register-total="<?= (int)$pagination['total'] ?>">
                        <thead class="table-dark">
                            <tr>
                                <th>Titre</th>
                                <th>Responsable</th>
                                <th>Budget</th>
                                <th>Debut</th>
                                <th>Fin</th>
                                <th>Statut</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($projets as $p): ?>
                            <?php
                            $jours = (int)ceil((strtotime($p['date_fin']) - time()) / 86400);
                            $badgeClass = 'secondary';
                            if ($p['statut'] === 'en_cours') $badgeClass = 'primary';
                            if ($p['statut'] === 'termine') $badgeClass = 'success';
                            if ($p['statut'] === 'en_retard') $badgeClass = 'danger';
                            if ($p['statut'] === 'suspendu') $badgeClass = 'warning text-dark';
                            ?>
                            <tr data-register-state="<?= e($p['statut']) ?>" data-register-complete="<?= $p['statut'] === 'termine' ? 'true' : 'false' ?>">
                                <td>
                                    <a href="<?= APP_URL ?>/projets/voir/<?= (int)$p['id'] ?>" class="fw-semibold text-decoration-none">
                                        <?= e($p['titre']) ?>
                                    </a>
                                    <div class="small text-muted text-truncate" style="max-width: 350px;"><?= e($p['description'] ?? '') ?></div>
                                </td>
                                <td><?= e($p['responsable_nom'] ?? 'Non defini') ?></td>
                                <td><?= formatMontant((float)$p['budget'], $p['devise']) ?></td>
                                <td><?= formatDate($p['date_debut']) ?></td>
                                <td>
                                    <?= formatDate($p['date_fin']) ?>
                                    <?php if ($jours <= 7 && $p['statut'] !== 'termine'): ?>
                                        <span class="badge bg-<?= $jours < 0 ? 'danger' : 'warning text-dark' ?> ms-1">
                                            <?= $jours < 0 ? 'Depasse' : 'J-' . $jours ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td data-register-state="<?= e($p['statut']) ?>"><span class="badge bg-<?= $badgeClass ?>"><?= e($p['statut']) ?></span></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= APP_URL ?>/projets/voir/<?= (int)$p['id'] ?>" class="btn btn-outline-primary" title="Voir">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= APP_URL ?>/projets/gantt/<?= (int)$p['id'] ?>" class="btn btn-outline-info" title="Planning Gantt">
                                            <i class="bi bi-bar-chart-steps"></i>
                                        </a>
                                        <a href="<?= APP_URL ?>/projets/modifier/<?= (int)$p['id'] ?>" class="btn btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
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
                            <li class="page-item <?= $p === $pagination['page_courante'] ? 'active' : '' ?>">
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
