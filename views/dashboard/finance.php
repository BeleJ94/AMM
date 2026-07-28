<?php $pageTitle = 'Dashboard Finance — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <div class="dashboard-hero">
    <div class="d-flex justify-content-between align-items-center mb-0">
        <h2 class="fw-bold mb-0"><i class="bi bi-currency-dollar me-2 text-success"></i>Tableau de bord Finance</h2>
        <a href="<?= APP_URL ?>/contrats/ajouter" class="btn btn-success">
            <i class="bi bi-plus-lg me-1"></i>Nouveau contrat
        </a>
    </div>
    </div>

    <?php afficherFlash(); ?>

    <!-- KPI Financiers -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="finance_total_usd">
                <div class="kpi-value text-success" style="font-size:1.25rem;"><?= formatMontant($statsContrat['total_usd'], 'USD') ?></div>
                <small class="kpi-label">Total contrats (USD)</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="finance_paye_usd">
                <div class="kpi-value text-info" style="font-size:1.25rem;"><?= formatMontant($statsContrat['paye_usd'], 'USD') ?></div>
                <small class="kpi-label">Total payé (USD)</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-clickable text-center py-3" data-dashboard-detail="finance_actifs">
                <div class="kpi-value text-primary"><?= $statsContrat['actifs'] ?></div>
                <small class="kpi-label">Contrats actifs</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi-card kpi-clickable text-center py-3 border-start border-danger border-3" data-dashboard-detail="finance_expirant_30j">
                <div class="kpi-value text-danger"><?= $statsContrat['expirant_30j'] ?></div>
                <small class="kpi-label">Expirant (30j)</small>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Contrats actifs -->
        <div class="col-lg-8">
            <div class="card section-card">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-file-earmark-check me-2 text-success"></i>Contrats actifs
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Partenaire</th>
                                <th>Objet</th>
                                <th>Valeur</th>
                                <th>Fin</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contratsActifs as $c): ?>
                                <tr>
                                    <td><a href="<?= APP_URL ?>/contrats/voir/<?= $c['id'] ?>"><?= e($c['partenaire']) ?></a></td>
                                    <td class="text-truncate" style="max-width:180px;"><?= e($c['objet']) ?></td>
                                    <td><?= formatMontant($c['valeur'], $c['devise']) ?></td>
                                    <td><?= formatDate($c['date_fin']) ?></td>
                                    <td><span class="badge bg-<?= badgeStatutContrat($c['statut']) ?>"><?= e($c['statut']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-end">
                    <a href="<?= APP_URL ?>/contrats/liste" class="btn btn-sm btn-outline-primary">Voir tous</a>
                </div>
            </div>
        </div>

        <!-- Alertes expirant 7j -->
        <div class="col-lg-4">
            <div class="card section-card h-100">
                <div class="card-header bg-danger bg-opacity-10 border-0 fw-semibold text-danger">
                    <i class="bi bi-alarm me-2"></i>Expirant dans 7 jours
                </div>
                <div class="card-body p-0">
                    <?php if (empty($expirant7j)): ?>
                        <div class="text-center p-4 text-muted">Aucun contrat urgent.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($expirant7j as $c): ?>
                                <li class="list-group-item">
                                    <div class="fw-semibold"><?= e($c['partenaire']) ?></div>
                                    <small class="text-danger">Expire le <?= formatDate($c['date_fin']) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="<?= APP_URL ?>/contrats/alerte" class="btn btn-sm btn-outline-danger w-100">Toutes les alertes</a>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
window.dashboardDetailData = Object.assign({}, window.dashboardDetailData || {}, {
    finance_total_usd: {
        title: 'Finance - Contrats actifs valorises (USD/CDF)',
        columns: ['Partenaire', 'Objet', 'Valeur', 'Devise', 'Date fin', 'Statut'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['partenaire'] ?? '-',
                $c['objet'] ?? '-',
                number_format((float)($c['valeur'] ?? 0), 2, ',', ' '),
                $c['devise'] ?? '-',
                isset($c['date_fin']) ? formatDate($c['date_fin']) : '-',
                $c['statut'] ?? '-',
            ];
        }, $contratsActifsFull), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/contrats/voir/' . (int)($c['id'] ?? 0);
        }, $contratsActifsFull), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'finance_contrats_valorises'
    },
    finance_paye_usd: {
        title: 'Finance - Base contrats pour suivi paiement',
        columns: ['Partenaire', 'Objet', 'Valeur contrat', 'Devise', 'Date fin'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['partenaire'] ?? '-',
                $c['objet'] ?? '-',
                number_format((float)($c['valeur'] ?? 0), 2, ',', ' '),
                $c['devise'] ?? '-',
                isset($c['date_fin']) ? formatDate($c['date_fin']) : '-',
            ];
        }, $contratsActifsFull), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/contrats/voir/' . (int)($c['id'] ?? 0);
        }, $contratsActifsFull), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'finance_base_paiement'
    },
    finance_actifs: {
        title: 'Finance - Contrats actifs',
        columns: ['Partenaire', 'Objet', 'Valeur', 'Fin', 'Statut'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['partenaire'] ?? '-',
                $c['objet'] ?? '-',
                formatMontant((float)($c['valeur'] ?? 0), $c['devise'] ?? 'USD'),
                isset($c['date_fin']) ? formatDate($c['date_fin']) : '-',
                $c['statut'] ?? '-',
            ];
        }, $contratsActifsFull), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/contrats/voir/' . (int)($c['id'] ?? 0);
        }, $contratsActifsFull), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'finance_contrats_actifs'
    },
    finance_expirant_30j: {
        title: 'Finance - Contrats expirant sous 30 jours',
        columns: ['Partenaire', 'Objet', 'Valeur', 'Date fin', 'Jours restants'],
        rows: <?= json_encode(array_map(function($c) {
            $days = isset($c['date_fin']) ? (int)ceil((strtotime($c['date_fin']) - time()) / 86400) : 0;
            return [
                $c['partenaire'] ?? '-',
                $c['objet'] ?? '-',
                formatMontant((float)($c['valeur'] ?? 0), $c['devise'] ?? 'USD'),
                isset($c['date_fin']) ? formatDate($c['date_fin']) : '-',
                'J-' . $days,
            ];
        }, $expirant30j), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/contrats/voir/' . (int)($c['id'] ?? 0);
        }, $expirant30j), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'finance_expirant_30j'
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
