<?php $pageTitle = 'Dashboard Réception — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <div class="dashboard-hero">
    <div class="d-flex justify-content-between align-items-center mb-0">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-inbox me-2 text-primary"></i>Tableau de bord Réception</h2>
            <p class="text-muted mb-0">Bienvenue, <?= e($_SESSION['user_nom']) ?></p>
        </div>
        <a href="<?= APP_URL ?>/courrier/ajouter" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nouveau courrier
        </a>
    </div>
    </div>

    <?php afficherFlash(); ?>

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="reception_today">
                <div class="kpi-value text-primary"><?= $statsCourrier['aujourd_hui'] ?></div>
                <small class="kpi-label">Reçus aujourd'hui</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="reception_unassigned">
                <div class="kpi-value text-warning"><?= $statsCourrier['non_assignes'] ?></div>
                <small class="kpi-label">Non assignés</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="reception_urgent">
                <div class="kpi-value text-danger"><?= $statsCourrier['urgents'] ?></div>
                <small class="kpi-label">Urgents actifs</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="reception_total">
                <div class="kpi-value text-info"><?= $statsCourrier['total'] ?></div>
                <small class="kpi-label">Total courriers</small>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Courriers du jour -->
        <div class="col-md-6">
            <div class="card section-card h-100">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-calendar-day me-2 text-primary"></i>Courriers d'aujourd'hui
                </div>
                <div class="card-body p-0">
                    <?php if (empty($courriersDuJour)): ?>
                        <div class="text-center p-4 text-muted">Aucun courrier reçu aujourd'hui.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($courriersDuJour as $c): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="<?= APP_URL ?>/courrier/voir/<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
                                            <?= e($c['numero_unique']) ?>
                                        </a>
                                        <small class="d-block text-muted"><?= e(substr($c['objet'], 0, 60)) ?>...</small>
                                    </div>
                                    <span class="badge bg-<?= badgeUrgence($c['urgence']) ?>"><?= e($c['urgence']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="<?= APP_URL ?>/courrier/liste" class="btn btn-sm btn-outline-primary">Tous les courriers</a>
                </div>
            </div>
        </div>

        <!-- Non assignés -->
        <div class="col-md-6">
            <div class="card section-card h-100">
                <div class="card-header bg-warning bg-opacity-10 border-0 fw-semibold">
                    <i class="bi bi-person-slash me-2 text-warning"></i>Non assignés
                </div>
                <div class="card-body p-0">
                    <?php if (empty($nonAssignes)): ?>
                        <div class="text-center p-4 text-muted">
                            <i class="bi bi-check-all fs-3 text-success"></i>
                            <p>Tous les courriers ont été assignés.</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($nonAssignes as $c): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <a href="<?= APP_URL ?>/courrier/assigner/<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
                                                <?= e($c['numero_unique']) ?>
                                            </a>
                                            <small class="d-block text-muted"><?= e($c['expediteur']) ?></small>
                                        </div>
                                        <a href="<?= APP_URL ?>/courrier/assigner/<?= $c['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-person-plus"></i> Assigner
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
window.dashboardDetailData = Object.assign({}, window.dashboardDetailData || {}, {
    reception_today: {
        title: "Reception - Courriers recus aujourd'hui",
        columns: ['Numero', 'Reference', 'Expediteur', 'Objet', 'Urgence', 'Statut', 'Date reception'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['numero_unique'] ?? '',
                $c['reference_document'] ?? '-',
                $c['expediteur'] ?? '-',
                $c['objet'] ?? '-',
                $c['urgence'] ?? '-',
                $c['statut'] ?? '-',
                isset($c['date_reception']) ? formatDate($c['date_reception']) : '-',
            ];
        }, $courriersDuJour), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/courrier/voir/' . (int)($c['id'] ?? 0);
        }, $courriersDuJour), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'reception_courriers_du_jour'
    },
    reception_unassigned: {
        title: 'Reception - Courriers non assignes',
        columns: ['Numero', 'Reference', 'Expediteur', 'Objet', 'Urgence', 'Date reception'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['numero_unique'] ?? '',
                $c['reference_document'] ?? '-',
                $c['expediteur'] ?? '-',
                $c['objet'] ?? '-',
                $c['urgence'] ?? '-',
                isset($c['date_reception']) ? formatDate($c['date_reception']) : '-',
            ];
        }, $nonAssignesFull), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/courrier/assigner/' . (int)($c['id'] ?? 0);
        }, $nonAssignesFull), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'reception_non_assignes'
    },
    reception_urgent: {
        title: 'Reception - Courriers urgents actifs',
        columns: ['Numero', 'Reference', 'Service', 'Objet', 'Deadline', 'Statut'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['numero_unique'] ?? '',
                $c['reference_document'] ?? '-',
                $c['service_destinataire'] ?? '-',
                $c['objet'] ?? '-',
                isset($c['deadline']) && $c['deadline'] ? formatDate($c['deadline']) : '-',
                $c['statut'] ?? '-',
            ];
        }, $urgentsFull), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/courrier/voir/' . (int)($c['id'] ?? 0);
        }, $urgentsFull), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'reception_urgents'
    },
    reception_total: {
        title: 'Reception - Courriers recents',
        columns: ['Numero', 'Reference', 'Expediteur', 'Service', 'Urgence', 'Statut', 'Date reception'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['numero_unique'] ?? '',
                $c['reference_document'] ?? '-',
                $c['expediteur'] ?? '-',
                $c['service_destinataire'] ?? '-',
                $c['urgence'] ?? '-',
                $c['statut'] ?? '-',
                isset($c['date_reception']) ? formatDate($c['date_reception']) : '-',
            ];
        }, $courriersRecents), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/courrier/voir/' . (int)($c['id'] ?? 0);
        }, $courriersRecents), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'reception_courriers_recents'
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
