<?php $pageTitle = 'Dashboard Direction — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="dashboard-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
            <div>
                <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1"><li class="breadcrumb-item active">Accueil</li><li class="breadcrumb-item active">Vue Direction</li></ol></nav>
                <h2>Bonjour, <?= e($_SESSION['user_nom'] ?? '') ?></h2>
                <p class="text-muted small mb-0 mt-1">Voici les priorités opérationnelles et les indicateurs clés de l’entreprise.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="btn btn-light border disabled"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y') ?></span>
                <a href="<?= APP_URL ?>/courrier/ajouter" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouveau courrier</a>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3"><div class="card erp-kpi erp-kpi-primary kpi-clickable" data-dashboard-detail="direction_total_courriers"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Courriers enregistrés</div><span class="erp-kpi-icon"><i class="bi bi-envelope-paper"></i></span></div><div class="erp-kpi-value"><?= (int)$statsCourrier['total'] ?></div><div class="erp-kpi-meta"><?= (int)$statsCourrier['recus'] ?> reçu(s) à traiter</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card erp-kpi erp-kpi-danger kpi-clickable" data-dashboard-detail="direction_overdue"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Courriers en retard</div><span class="erp-kpi-icon"><i class="bi bi-clock-history"></i></span></div><div class="erp-kpi-value text-danger"><?= (int)$statsCourrier['overdue'] ?></div><div class="erp-kpi-meta">Nécessitent une action immédiate</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card erp-kpi erp-kpi-success kpi-clickable" data-dashboard-detail="direction_contrats_actifs"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Contrats actifs</div><span class="erp-kpi-icon"><i class="bi bi-file-earmark-check"></i></span></div><div class="erp-kpi-value text-success"><?= (int)$statsContrat['actifs'] ?></div><div class="erp-kpi-meta"><?= count($expirant30j) ?> échéance(s) sous 30 jours</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card erp-kpi erp-kpi-warning kpi-clickable" data-dashboard-detail="direction_urgents"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Priorités critiques</div><span class="erp-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span></div><div class="erp-kpi-value text-warning"><?= (int)$statsCourrier['urgents'] ?></div><div class="erp-kpi-meta"><?= (int)($statsDocument['expires'] ?? 0) ?> document(s) expiré(s)</div></div></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-4"><div class="card section-card h-100"><div class="card-header"><span class="erp-portlet-title"><i class="bi bi-pie-chart text-primary"></i>Répartition des courriers</span><a class="erp-portlet-link" href="<?= APP_URL ?>/courrier/liste">Voir tous</a></div><div class="card-body d-flex justify-content-center align-items-center"><canvas id="graphiqueStatuts" style="max-height:255px"></canvas></div></div></div>
        <div class="col-xl-5"><div class="card section-card h-100"><div class="card-header"><span class="erp-portlet-title"><i class="bi bi-activity text-primary"></i>Activité récente</span><a class="erp-portlet-link" href="<?= APP_URL ?>/courrier/liste">Historique complet</a></div><div class="card-body p-0"><div class="table-responsive"><table data-register-table data-register-filter-columns='{"2":"Service","3":"Statut"}' class="table table-sm table-hover"><thead><tr><th>Référence</th><th>Objet</th><th>Service</th><th>Statut</th></tr></thead><tbody><?php foreach(array_slice($courriersRecents,0,7) as $c): ?><tr data-register-state="<?= e(strtolower($c['statut'])) ?>" data-register-complete="<?= in_array($c['statut'], ['Termine', 'Clos', 'Archive'], true) ? 'true' : 'false' ?>"><td><a class="fw-semibold text-decoration-none" href="<?= APP_URL ?>/courrier/voir/<?= (int)$c['id'] ?>"><?= e($c['numero_unique']) ?></a></td><td class="text-truncate" style="max-width:190px"><?= e($c['objet']) ?></td><td><?= e($c['service_destinataire'] ?: '—') ?></td><td data-register-state="<?= e(strtolower($c['statut'])) ?>"><span class="badge bg-<?= badgeStatut($c['statut']) ?>"><?= e($c['statut']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
        <div class="col-xl-3"><div class="card section-card h-100"><div class="card-header"><span class="erp-portlet-title"><i class="bi bi-bell text-primary"></i>Rappels</span></div><div>
            <a class="erp-reminder-item" href="<?= APP_URL ?>/courrier/liste?statut=Overdue">Courriers en retard<span class="erp-reminder-count bg-danger-subtle text-danger"><?= (int)$statsCourrier['overdue'] ?></span></a>
            <a class="erp-reminder-item" href="<?= APP_URL ?>/courrier/liste?urgence=haute">Courriers urgents<span class="erp-reminder-count bg-warning-subtle text-warning-emphasis"><?= (int)$statsCourrier['urgents'] ?></span></a>
            <a class="erp-reminder-item" href="<?= APP_URL ?>/contrats/alerte">Contrats à échéance<span class="erp-reminder-count"><?= count($expirant30j) ?></span></a>
            <a class="erp-reminder-item" href="<?= APP_URL ?>/documents/liste">Documents expirés<span class="erp-reminder-count"><?= (int)($statsDocument['expires'] ?? 0) ?></span></a>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8"><div class="card section-card h-100"><div class="card-header"><span class="erp-portlet-title"><i class="bi bi-calendar2-check text-primary"></i>Échéances contractuelles</span><a class="erp-portlet-link" href="<?= APP_URL ?>/contrats/alerte">Toutes les alertes</a></div><div class="card-body p-0">
            <div class="table-responsive"><table data-register-table class="table table-sm table-hover"><thead><tr><th>Partenaire</th><th>Objet</th><th>Valeur</th><th>Échéance</th><th></th></tr></thead><tbody><?php foreach($expirant30j as $c): $jours=(int)ceil((strtotime($c['date_fin'])-time())/86400); ?><tr><td class="fw-semibold"><?= e($c['partenaire']) ?></td><td><?= e($c['objet']) ?></td><td><?= formatMontant((float)$c['valeur'],$c['devise']) ?></td><td><span class="badge bg-<?= $jours<=7?'danger':($jours<=15?'warning text-dark':'info') ?>"><?= formatDate($c['date_fin']) ?> · J-<?= $jours ?></span></td><td class="text-end"><a href="<?= APP_URL ?>/contrats/voir/<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-right"></i></a></td></tr><?php endforeach; ?></tbody></table></div>>
        </div></div></div>
        <div class="col-xl-4"><div class="card section-card h-100"><div class="card-header"><span class="erp-portlet-title"><i class="bi bi-lightning-charge text-primary"></i>Actions rapides</span></div><div class="card-body"><div class="row g-2">
            <div class="col-6"><a class="erp-quick-action" href="<?= APP_URL ?>/courrier/ajouter"><i class="bi bi-envelope-plus"></i>Nouveau courrier</a></div>
            <div class="col-6"><a class="erp-quick-action" href="<?= APP_URL ?>/projets/ajouter"><i class="bi bi-diagram-3"></i>Nouveau projet</a></div>
            <div class="col-6"><a class="erp-quick-action" href="<?= APP_URL ?>/contrats/ajouter"><i class="bi bi-file-earmark-plus"></i>Nouveau contrat</a></div>
            <div class="col-6"><a class="erp-quick-action" href="<?= APP_URL ?>/documents/upload"><i class="bi bi-cloud-arrow-up"></i>Ajouter un document</a></div>
            <div class="col-6"><a class="erp-quick-action" href="<?= APP_URL ?>/treasury/dashboard"><i class="bi bi-bank"></i>Trésorerie</a></div>
            <div class="col-6"><a class="erp-quick-action" href="<?= APP_URL ?>/dashboard/direction"><i class="bi bi-bar-chart-line"></i>Rapports Direction</a></div>
        </div></div></div></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('graphiqueStatuts');
    if (!ctx || typeof Chart === 'undefined') {
        return;
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Reçus', 'En cours', 'En retard', 'Terminés/Clos'],
            datasets: [{
                data: [
                    <?= $statsCourrier['recus'] ?>,
                    <?= $statsCourrier['en_cours'] ?>,
                    <?= $statsCourrier['overdue'] ?>,
                    <?= $statsCourrier['total'] - $statsCourrier['recus'] - $statsCourrier['en_cours'] - $statsCourrier['overdue'] ?>
                ],
                backgroundColor: ['#2b7de9','#e6a817','#cf3542','#16834b'],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 16, font: { size: 11 } } }
            }
        }
    });
});
</script>

<script>
window.dashboardDetailData = Object.assign({}, window.dashboardDetailData || {}, {
    direction_total_courriers: {
        title: 'Direction - Courriers recents',
        columns: ['Numero', 'Reference', 'Expediteur', 'Objet', 'Service', 'Urgence', 'Statut'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['numero_unique'] ?? '',
                $c['reference_document'] ?? '-',
                $c['expediteur'] ?? '-',
                $c['objet'] ?? '-',
                $c['service_destinataire'] ?? '-',
                $c['urgence'] ?? '-',
                $c['statut'] ?? '-',
            ];
        }, $courriersRecents), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/courrier/voir/' . (int)($c['id'] ?? 0);
        }, $courriersRecents), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'direction_courriers_recents'
    },
    direction_overdue: {
        title: 'Direction - Courriers overdue',
        columns: ['Numero', 'Reference', 'Objet', 'Service', 'Deadline', 'Urgence'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['numero_unique'] ?? '',
                $c['reference_document'] ?? '-',
                $c['objet'] ?? '-',
                $c['service_destinataire'] ?? '-',
                isset($c['deadline']) && $c['deadline'] ? formatDate($c['deadline']) : '-',
                $c['urgence'] ?? '-',
            ];
        }, $overdueFull), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/courrier/voir/' . (int)($c['id'] ?? 0);
        }, $overdueFull), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'direction_overdue'
    },
    direction_contrats_actifs: {
        title: 'Direction - Contrats actifs',
        columns: ['Partenaire', 'Objet', 'Valeur', 'Date fin', 'Statut'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['partenaire'] ?? '-',
                $c['objet'] ?? '-',
                formatMontant((float)($c['valeur'] ?? 0), $c['devise'] ?? 'USD'),
                isset($c['date_fin']) ? formatDate($c['date_fin']) : '-',
                $c['statut'] ?? '-',
            ];
        }, $contratsActifs), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/contrats/voir/' . (int)($c['id'] ?? 0);
        }, $contratsActifs), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'direction_contrats_actifs'
    },
    direction_urgents: {
        title: 'Direction - Courriers urgents',
        columns: ['Numero', 'Reference', 'Expediteur', 'Objet', 'Deadline', 'Statut'],
        rows: <?= json_encode(array_map(function($c) {
            return [
                $c['numero_unique'] ?? '',
                $c['reference_document'] ?? '-',
                $c['expediteur'] ?? '-',
                $c['objet'] ?? '-',
                isset($c['deadline']) && $c['deadline'] ? formatDate($c['deadline']) : '-',
                $c['statut'] ?? '-',
            ];
        }, $urgentsFull), JSON_UNESCAPED_UNICODE) ?>,
        links: <?= json_encode(array_map(function($c) {
            return APP_URL . '/courrier/voir/' . (int)($c['id'] ?? 0);
        }, $urgentsFull), JSON_UNESCAPED_UNICODE) ?>,
        exportName: 'direction_urgents'
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
