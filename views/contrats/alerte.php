<?php $pageTitle = 'Alertes Échéances — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <div class="page-hero">
    <div class="d-flex justify-content-between align-items-center mb-0">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Alertes – Échéances des contrats</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard">Accueil</a></li>
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/contrats/liste">Contrats</a></li>
                    <li class="breadcrumb-item active">Alertes</li>
                </ol>
            </nav>
        </div>
        <a href="<?= APP_URL ?>/contrats/liste" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Retour
        </a>
    </div>
    </div>

    <!-- Résumé des alertes -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card kpi-card border-start border-danger border-4 text-center py-3">
                <div class="h3 fw-bold text-danger"><?= count($expirant7j) ?></div>
                <small class="text-muted">Expirant dans 7 jours</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card kpi-card border-start border-warning border-4 text-center py-3">
                <div class="h3 fw-bold text-warning"><?= count($expirant15j) ?></div>
                <small class="text-muted">Expirant dans 15 jours</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card kpi-card border-start border-info border-4 text-center py-3">
                <div class="h3 fw-bold text-info"><?= count($expirant30j) ?></div>
                <small class="text-muted">Expirant dans 30 jours</small>
            </div>
        </div>
    </div>

    <!-- Contrats J-7 -->
    <?php if (!empty($expirant7j)): ?>
    <div class="card section-card mb-4 border-top border-danger border-3">
        <div class="card-header bg-danger text-white fw-semibold">
            <i class="bi bi-alarm me-2"></i>URGENT : Expirent dans moins de 7 jours
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Partenaire</th><th>Objet</th><th>Valeur</th><th>Expire le</th><th>Reconduction</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($expirant7j as $c): ?>
                        <?php $jours = (int)ceil((strtotime($c['date_fin']) - time()) / 86400); ?>
                        <tr class="table-danger">
                            <td class="fw-semibold"><?= e($c['partenaire']) ?></td>
                            <td><?= e($c['objet']) ?></td>
                            <td><?= formatMontant($c['valeur'], $c['devise']) ?></td>
                            <td>
                                <?= formatDate($c['date_fin']) ?>
                                <span class="badge bg-danger ms-1">J-<?= $jours ?></span>
                            </td>
                            <td><?= $c['tacite_reconduction'] ? '<span class="badge bg-info">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                            <td>
                                <a href="<?= APP_URL ?>/contrats/voir/<?= $c['id'] ?>" class="btn btn-sm btn-danger">
                                    <i class="bi bi-eye me-1"></i>Voir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contrats J-15 -->
    <?php
    // Afficher uniquement ceux entre J-8 et J-15 (pas ceux déjà dans J-7)
    $ids7j = array_column($expirant7j, 'id');
    $entre8et15 = array_filter($expirant15j, fn($c) => !in_array($c['id'], $ids7j));
    if (!empty($entre8et15)):
    ?>
    <div class="card section-card mb-4 border-top border-warning border-3">
        <div class="card-header bg-warning fw-semibold">
            <i class="bi bi-exclamation-circle me-2"></i>Attention : Expirent dans 8–15 jours
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Partenaire</th><th>Objet</th><th>Valeur</th><th>Expire le</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entre8et15 as $c): ?>
                        <?php $jours = (int)ceil((strtotime($c['date_fin']) - time()) / 86400); ?>
                        <tr class="table-warning">
                            <td class="fw-semibold"><?= e($c['partenaire']) ?></td>
                            <td><?= e($c['objet']) ?></td>
                            <td><?= formatMontant($c['valeur'], $c['devise']) ?></td>
                            <td><?= formatDate($c['date_fin']) ?> <span class="badge bg-warning text-dark">J-<?= $jours ?></span></td>
                            <td>
                                <a href="<?= APP_URL ?>/contrats/voir/<?= $c['id'] ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-eye me-1"></i>Voir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contrats J-30 -->
    <?php
    $ids15j = array_column($expirant15j, 'id');
    $entre16et30 = array_filter($expirant30j, fn($c) => !in_array($c['id'], $ids15j));
    if (!empty($entre16et30)):
    ?>
    <div class="card section-card border-top border-info border-3">
        <div class="card-header bg-info bg-opacity-10 fw-semibold border-0">
            <i class="bi bi-info-circle me-2 text-info"></i>À surveiller : Expirent dans 16–30 jours
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Partenaire</th><th>Objet</th><th>Valeur</th><th>Expire le</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entre16et30 as $c): ?>
                        <?php $jours = (int)ceil((strtotime($c['date_fin']) - time()) / 86400); ?>
                        <tr>
                            <td class="fw-semibold"><?= e($c['partenaire']) ?></td>
                            <td><?= e($c['objet']) ?></td>
                            <td><?= formatMontant($c['valeur'], $c['devise']) ?></td>
                            <td><?= formatDate($c['date_fin']) ?> <span class="badge bg-info text-dark">J-<?= $jours ?></span></td>
                            <td>
                                <a href="<?= APP_URL ?>/contrats/voir/<?= $c['id'] ?>" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-eye me-1"></i>Voir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($expirant30j)): ?>
        <div class="card section-card">
            <div class="card-body text-center p-5 text-muted">
                <i class="bi bi-shield-check fs-1 text-success d-block mb-3"></i>
                <h5>Aucune alerte d'échéance</h5>
                <p>Tous les contrats sont à jour pour les 30 prochains jours.</p>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
