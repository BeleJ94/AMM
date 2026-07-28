<?php $pageTitle = 'Dashboard Juridique — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <div class="dashboard-hero">
    <div class="d-flex justify-content-between align-items-center mb-0">
        <h2 class="fw-bold mb-0"><i class="bi bi-shield-check me-2 text-info"></i>Tableau de bord Juridique</h2>
    </div>
    </div>

    <?php afficherFlash(); ?>

    <!-- KPI -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="legal_contrats_expirant">
                <div class="kpi-value text-warning"><?= count($contratsExpirant) ?></div>
                <small class="kpi-label">Contrats expirant (30j)</small>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="legal_courriers_juridiques">
                <div class="kpi-value text-primary"><?= count($courriersJuridiques) ?></div>
                <small class="kpi-label">Courriers juridiques</small>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="legal_documents_expires">
                <div class="kpi-value text-danger"><?= $statsDocument['expires'] ?></div>
                <small class="kpi-label">Documents expirés</small>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Contrats expirant bientôt -->
        <div class="col-md-6">
            <div class="card section-card">
                <div class="card-header bg-warning bg-opacity-10 border-0 fw-semibold">
                    <i class="bi bi-calendar-x me-2 text-warning"></i>Contrats à surveiller
                </div>
                <div class="card-body p-0">
                    <?php if (empty($contratsExpirant)): ?>
                        <div class="text-center p-4 text-muted">Aucun contrat expirant dans les 30 jours.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($contratsExpirant as $c): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <a href="<?= APP_URL ?>/contrats/voir/<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
                                                <?= e($c['partenaire']) ?>
                                            </a>
                                            <small class="d-block text-muted"><?= e($c['objet']) ?></small>
                                        </div>
                                        <span class="badge bg-danger align-self-center">
                                            <?= formatDate($c['date_fin']) ?>
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Courriers juridiques en cours -->
        <div class="col-md-6">
            <div class="card section-card">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-envelope-open me-2 text-primary"></i>Courriers service juridique
                </div>
                <div class="card-body p-0">
                    <?php if (empty($courriersJuridiques)): ?>
                        <div class="text-center p-4 text-muted">Aucun courrier en attente.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($courriersJuridiques as $c): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <a href="<?= APP_URL ?>/courrier/voir/<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
                                                <?= e($c['numero_unique']) ?>
                                            </a>
                                            <small class="d-block text-muted"><?= e(substr($c['objet'], 0, 55)) ?></small>
                                        </div>
                                        <span class="badge bg-<?= badgeStatut($c['statut']) ?> align-self-center">
                                            <?= e($c['statut']) ?>
                                        </span>
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
    legal_contrats_expirant: {
        title: 'Juridique - Contrats expirant sous 30 jours',
        columns: ['Partenaire', 'Objet', 'Date fin', 'Statut'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['partenaire'] ?? '-',
                $c['objet'] ?? '-',
                isset($c['date_fin']) ? formatDate($c['date_fin']) : '-',
                $c['statut'] ?? '-',
            ];
        }, $contratsExpirant), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/contrats/voir/' . (int)($c['id'] ?? 0);
        }, $contratsExpirant), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'legal_contrats_expirant'
    },
    legal_courriers_juridiques: {
        title: 'Juridique - Courriers du service',
        columns: ['Numero', 'Reference', 'Expediteur', 'Objet', 'Statut', 'Urgence'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['numero_unique'] ?? '-',
                $c['reference_document'] ?? '-',
                $c['expediteur'] ?? '-',
                $c['objet'] ?? '-',
                $c['statut'] ?? '-',
                $c['urgence'] ?? '-',
            ];
        }, $courriersJuridiquesFull), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/courrier/voir/' . (int)($c['id'] ?? 0);
        }, $courriersJuridiquesFull), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'legal_courriers_service'
    },
    legal_documents_expires: {
        title: 'Juridique - Documents expires',
        columns: ['Titre', 'Type', 'Service', 'Date expiration'],
        rows: <?= json_encode(array_map(function($d) {
            return [
                $d['titre'] ?? '-',
                $d['type_doc'] ?? '-',
                $d['service_proprietaire'] ?? '-',
                isset($d['expire_le']) ? formatDate($d['expire_le']) : '-',
            ];
        }, $documentsExpires), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($d) {
            return APP_URL . '/documents/voir/' . (int)($d['id'] ?? 0);
        }, $documentsExpires), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'legal_documents_expires'
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
