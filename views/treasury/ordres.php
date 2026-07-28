<?php $pageTitle = 'Ordres de paiement - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>

<?php
if (!function_exists('treasuryOrderNextAction')) {
    function treasuryOrderNextAction(array $ordre): array {
        $url = APP_URL . '/treasury/ordre-paiement/' . (int)$ordre['request_id'];
        $actions = [
            'emis' => [
                'label' => 'Paiement à exécuter',
                'owner' => 'Trésorerie',
                'button' => 'Exécuter',
                'icon' => 'bi-cash-coin',
                'tone' => 'warning',
            ],
            'execute' => [
                'label' => 'Paiement exécuté',
                'owner' => !empty($ordre['executed_by_nom']) ? $ordre['executed_by_nom'] : 'Cycle clôturé',
                'button' => 'Consulter',
                'icon' => 'bi-check2-circle',
                'tone' => 'success',
            ],
            'annule' => [
                'label' => 'Ordre annulé',
                'owner' => 'Cycle clôturé',
                'button' => 'Consulter',
                'icon' => 'bi-slash-circle',
                'tone' => 'secondary',
            ],
        ];
        $action = $actions[$ordre['status']] ?? [
            'label' => 'Suivi requis',
            'owner' => 'Trésorerie',
            'button' => 'Consulter',
            'icon' => 'bi-arrow-right-circle',
            'tone' => 'secondary',
        ];
        if (
            $ordre['status'] === 'emis'
            && !empty($ordre['issued_by'])
            && (int)$ordre['issued_by'] === (int)($_SESSION['user_id'] ?? 0)
        ) {
            $action['label'] = 'Exécution par un tiers requise';
            $action['owner'] = 'Autre agent de trésorerie habilité';
            $action['button'] = 'Suivre';
            $action['icon'] = 'bi-people';
        }
        $action['url'] = $url;
        return $action;
    }
}

if (!function_exists('treasuryOrderWaitingDays')) {
    function treasuryOrderWaitingDays(array $ordre): int {
        $anchor = $ordre['status'] === 'execute'
            ? ($ordre['executed_at'] ?? $ordre['issued_at'] ?? $ordre['created_at'])
            : ($ordre['cancelled_at'] ?? $ordre['issued_at'] ?? $ordre['created_at']);
        $timestamp = strtotime((string)$anchor);
        return $timestamp ? max(0, (int)floor((time() - $timestamp) / 86400)) : 0;
    }
}
?>

<style>
.order-view-tabs { display:flex; gap:6px; flex-wrap:wrap; }
.order-view-btn { min-height:32px; padding:5px 10px; border:1px solid #d9e3ee; border-radius:6px; color:#53657a; background:#fff; font-size:.7rem; font-weight:700; }
.order-view-btn:hover,.order-view-btn.active { color:#1769e0; border-color:#a9c8ef; background:#edf5ff; }
.order-number { color:#1769e0; font-weight:800; }
.order-beneficiary { display:flex; align-items:center; gap:8px; }
.order-avatar { width:29px; height:29px; flex:0 0 29px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; color:#155bb7; background:#e8f1ff; font-size:.68rem; font-weight:800; }
.order-object { max-width:260px; color:#68798c; font-size:.7rem; }
.order-account { color:#2e4157; font-weight:700; }
.order-next-action { min-width:270px; padding-left:11px; border-left:3px solid #cbd5e1; }
.order-next-action.tone-warning { border-left-color:#d99a18; }
.order-next-action.tone-success { border-left-color:#198754; }
.order-action-label { color:#1d3046; font-size:.78rem; font-weight:800; }
.order-action-owner { color:#718096; font-size:.68rem; }
.order-action-age { display:inline-flex; align-items:center; gap:4px; margin-top:4px; color:#718096; font-size:.65rem; }
.order-action-age.is-late { color:#b12634; font-weight:700; }
.order-action-btn { min-width:92px; white-space:nowrap; }
.order-table-card .dataTables_wrapper > .row { margin:0; padding:11px 14px; align-items:center; border-bottom:1px solid #edf1f5; }
.order-table-card .dataTables_wrapper > .row:last-child { border-top:1px solid #edf1f5; border-bottom:0; }
.order-table-card .dataTables_filter { text-align:right; }
.order-table-card .dataTables_filter input { min-width:230px; margin-left:8px; border:1px solid #cfd9e5; border-radius:6px; padding:6px 9px; }
.order-table-card .dataTables_length select { border:1px solid #cfd9e5; border-radius:6px; padding:5px 24px 5px 8px; }
.order-table-card .dataTables_info { color:#718096; font-size:.7rem; }
.order-table-card .pagination { margin:0; }
.order-kpi-link { color:inherit; text-decoration:none; cursor:pointer; }
.order-kpi-link .erp-kpi { transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease; }
.order-kpi-link:hover .erp-kpi { transform:translateY(-2px); border-color:#a9c8ef; box-shadow:0 10px 24px rgba(17,55,93,.1); }
.order-kpi-link.is-active .erp-kpi { border-color:#6ba6e8; box-shadow:0 0 0 2px rgba(23,105,224,.08); }
.order-kpi-link:focus { outline:0; }
.order-kpi-link:focus .erp-kpi { border-color:#6ba6e8; box-shadow:0 0 0 3px rgba(23,105,224,.12); }
.order-kpi-link .erp-kpi-meta::after { content:' · Voir les détails'; color:#1769e0; font-weight:700; }
.order-kpi-summary { border:1px solid #dce5ef; border-radius:8px; background:#f8fbff; }
.order-kpi-summary-value { color:#132c49; font-size:1.25rem; font-weight:800; }
.order-kpi-table { font-size:.76rem; }
.order-kpi-table th { color:#fff; background:#173e67; font-size:.65rem; text-transform:uppercase; white-space:nowrap; }
.order-kpi-table td { vertical-align:middle; }
@media(max-width:767.98px){.order-table-card .dataTables_filter{text-align:left;margin-top:8px}.order-table-card .dataTables_filter input{min-width:170px}.order-view-tabs{overflow-x:auto;flex-wrap:nowrap;padding-bottom:4px}.order-view-btn{white-space:nowrap}}
@media(max-width:575.98px){.order-next-action{min-width:230px;align-items:flex-start!important;flex-direction:column}.order-action-btn{width:100%}}
</style>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-0">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/dashboard">Trésorerie</a></li>
                        <li class="breadcrumb-item active">Ordres de paiement</li>
                    </ol>
                </nav>
                <h2>Ordres de paiement</h2>
                <p class="text-muted small mb-0 mt-1">Suivez les engagements autorisés jusqu’à l’exécution effective du paiement.</p>
            </div>
            <a href="<?= APP_URL ?>/treasury/demandes?status=validee_direction" class="btn btn-primary align-self-start align-self-lg-center">
                <i class="bi bi-file-earmark-plus me-1"></i>Émettre depuis une demande
            </a>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="order-kpi-link" role="button" tabindex="0" data-kpi="total">
                <div class="card erp-kpi erp-kpi-primary h-100"><div class="card-body">
                    <div class="d-flex justify-content-between"><div class="erp-kpi-label">Tous les ordres</div><span class="erp-kpi-icon"><i class="bi bi-files"></i></span></div>
                    <div class="erp-kpi-value"><?= (int)$orderStats['total'] ?></div><div class="erp-kpi-meta">Volume enregistré</div>
                </div></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="order-kpi-link" role="button" tabindex="0" data-kpi="emis">
                <div class="card erp-kpi erp-kpi-warning h-100"><div class="card-body">
                    <div class="d-flex justify-content-between"><div class="erp-kpi-label">À exécuter</div><span class="erp-kpi-icon"><i class="bi bi-hourglass-split"></i></span></div>
                    <div class="erp-kpi-value text-warning"><?= (int)$orderStats['emis'] ?></div><div class="erp-kpi-meta"><?= formatMontant((float)$orderStats['montant_a_executer'], 'USD') ?> engagés</div>
                </div></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="order-kpi-link" role="button" tabindex="0" data-kpi="execute">
                <div class="card erp-kpi erp-kpi-success h-100"><div class="card-body">
                    <div class="d-flex justify-content-between"><div class="erp-kpi-label">Exécutés</div><span class="erp-kpi-icon"><i class="bi bi-check2-circle"></i></span></div>
                    <div class="erp-kpi-value text-success"><?= (int)$orderStats['execute'] ?></div><div class="erp-kpi-meta">Paiements finalisés</div>
                </div></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="order-kpi-link" role="button" tabindex="0" data-kpi="annule">
                <div class="card erp-kpi h-100"><div class="card-body">
                    <div class="d-flex justify-content-between"><div class="erp-kpi-label">Annulés</div><span class="erp-kpi-icon bg-light text-secondary"><i class="bi bi-slash-circle"></i></span></div>
                    <div class="erp-kpi-value text-secondary"><?= (int)$orderStats['annule'] ?></div><div class="erp-kpi-meta">Cycles clôturés sans paiement</div>
                </div></div>
            </div>
        </div>
    </div>

    <div class="card filter-card mb-3">
        <div class="card-body">
            <form method="GET" action="<?= APP_URL ?>/treasury/ordres">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Statut</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">Tous les statuts</option>
                            <?php foreach (['emis','execute','annule'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($filtres['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(treasuryUiLabel($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Recherche</label>
                        <input type="text" class="form-control form-control-sm" name="recherche" value="<?= e($filtres['recherche'] ?? '') ?>" placeholder="Ordre, demande, bénéficiaire ou objet">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Filtrer</button>
                        <a href="<?= APP_URL ?>/treasury/ordres" class="btn btn-outline-secondary btn-sm" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card section-card order-table-card">
        <div class="card-header bg-transparent border-0 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
                <div class="fw-semibold"><i class="bi bi-list-check me-2"></i>Registre des ordres</div>
                <div class="text-muted small"><?= count($ordres) ?> ordre(s) dans la sélection</div>
            </div>
            <div class="order-view-tabs" id="orderQuickViews">
                <button type="button" class="order-view-btn active" data-status="">Vue complète</button>
                <button type="button" class="order-view-btn" data-status="status-emis">À exécuter</button>
                <button type="button" class="order-view-btn" data-status="status-execute">Exécutés</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exporterCSV('tableOrdresPaiement', 'registre_ordres_paiement')"><i class="bi bi-download me-1"></i>CSV</button>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($ordres)): ?>
                <?php treasuryUiEmptyState('bi-file-earmark-check', 'Aucun ordre de paiement trouvé', 'Ajustez les filtres ou ouvrez une demande validée pour émettre un ordre.', '<a href="' . APP_URL . '/treasury/demandes?status=validee_direction" class="btn btn-sm btn-primary"><i class="bi bi-arrow-right me-1"></i>Voir les demandes prêtes</a>'); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100" id="tableOrdresPaiement">
                        <thead class="table-dark">
                            <tr>
                                <th>Ordre</th>
                                <th>Émission</th>
                                <th>Demande</th>
                                <th>Bénéficiaire / objet</th>
                                <th>Règlement</th>
                                <th class="text-end">Montant</th>
                                <th>Prochaine action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ordres as $ordre): ?>
                                <?php
                                $nextAction = treasuryOrderNextAction($ordre);
                                $waitingDays = treasuryOrderWaitingDays($ordre);
                                $isPending = $ordre['status'] === 'emis';
                                $isLate = $isPending && $waitingDays >= 2;
                                $paymentMethod = (($ordre['account_type'] ?? '') === 'caisse') ? 'especes' : $ordre['payment_method'];
                                ?>
                                <tr>
                                    <td data-order="<?= (int)$ordre['id'] ?>">
                                        <a href="<?= e($nextAction['url']) ?>" class="text-decoration-none"><span class="order-number"><?= e($ordre['order_number']) ?></span></a>
                                        <div class="text-muted small mt-1">Par <?= e($ordre['issued_by_nom'] ?? '—') ?></div>
                                    </td>
                                    <td data-order="<?= e($ordre['order_date']) ?>" class="text-nowrap"><?= formatDate($ordre['order_date']) ?></td>
                                    <td>
                                        <a href="<?= APP_URL ?>/treasury/demande-voir/<?= (int)$ordre['request_id'] ?>" class="fw-semibold text-decoration-none"><?= e($ordre['request_number']) ?></a>
                                    </td>
                                    <td>
                                        <div class="order-beneficiary"><span class="order-avatar"><?= e(strtoupper(substr((string)$ordre['beneficiary'], 0, 1))) ?></span><span class="fw-semibold"><?= e($ordre['beneficiary']) ?></span></div>
                                        <div class="order-object text-truncate mt-1" title="<?= e($ordre['objet']) ?>"><?= e($ordre['objet']) ?></div>
                                    </td>
                                    <td>
                                        <div class="order-account"><?= e($ordre['account_nom'] ?? '—') ?></div>
                                        <span class="badge bg-light text-secondary border mt-1"><?= e(treasuryUiLabel($paymentMethod)) ?></span>
                                    </td>
                                    <td class="fw-semibold text-end text-nowrap" data-order="<?= e((string)(float)$ordre['amount_usd']) ?>"><?= formatMontant((float)$ordre['amount'], $ordre['currency']) ?></td>
                                    <td data-search="<?= e('status-' . $ordre['status'] . ' ' . treasuryUiLabel($ordre['status']) . ' ' . $nextAction['label'] . ' ' . $nextAction['owner']) ?>">
                                        <div class="d-flex align-items-center justify-content-between gap-3 order-next-action tone-<?= e($nextAction['tone']) ?>">
                                            <div>
                                                <div class="order-action-label"><i class="bi <?= e($nextAction['icon']) ?> me-1 text-<?= e($nextAction['tone']) ?>"></i><?= e($nextAction['label']) ?></div>
                                                <div class="order-action-owner"><?= e($nextAction['owner']) ?> · <span class="badge bg-light text-secondary border"><?= e(treasuryUiLabel($ordre['status'])) ?></span></div>
                                                <?php if ($isPending): ?>
                                                    <div class="order-action-age <?= $isLate ? 'is-late' : '' ?>"><i class="bi bi-clock"></i><?= $waitingDays === 0 ? 'Émis aujourd’hui' : 'En attente depuis ' . $waitingDays . ' jour' . ($waitingDays > 1 ? 's' : '') ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?= e($nextAction['url']) ?>" class="btn btn-outline-<?= e($nextAction['tone']) ?> btn-sm order-action-btn"><?= e($nextAction['button']) ?><i class="bi bi-arrow-right ms-1"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="orderKpiDetailModal" tabindex="-1" aria-labelledby="orderKpiDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:min(96vw,1450px)">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header px-4 py-3">
                <div>
                    <div class="text-primary small fw-bold text-uppercase mb-1">Analyse des ordres de paiement</div>
                    <h5 class="modal-title fw-bold mb-1" id="orderKpiDetailModalLabel">Détail du KPI</h5>
                    <div class="text-muted small" id="orderKpiDetailSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="order-kpi-summary p-3"><div class="text-muted small mb-1">Nombre d’ordres</div><div class="order-kpi-summary-value" id="orderKpiCount">0</div></div></div>
                    <div class="col-md-4"><div class="order-kpi-summary p-3"><div class="text-muted small mb-1">Engagement total</div><div class="order-kpi-summary-value text-primary" id="orderKpiTotal">0,00 USD</div></div></div>
                    <div class="col-md-4"><div class="order-kpi-summary p-3"><div class="text-muted small mb-1">Montant moyen</div><div class="order-kpi-summary-value" id="orderKpiAverage">0,00 USD</div></div></div>
                </div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-hover mb-0 order-kpi-table">
                        <thead><tr><th>Ordre</th><th>Date</th><th>Demande</th><th>Bénéficiaire</th><th>Objet</th><th>Compte / mode</th><th class="text-end">Montant</th><th>Statut</th><th>Action</th><th></th></tr></thead>
                        <tbody id="orderKpiDetailBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer px-4">
                <span class="text-muted small me-auto">Les exports reprennent l’intégralité des ordres de cet indicateur.</span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                <a class="btn btn-outline-danger" id="orderKpiPdfButton" href="#" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>PDF CFO</a>
                <a class="btn btn-success" id="orderKpiExcelButton" href="#"><i class="bi bi-file-earmark-excel me-1"></i>Excel CFO</a>
            </div>
        </div>
    </div>
</div>

<script>
var orderKpiData = <?= json_encode($orderKpiDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function escapeOrderKpiHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function openOrderKpi(metric) {
    var data = orderKpiData[metric];
    if (!data) return;
    document.getElementById('orderKpiDetailModalLabel').textContent = data.title;
    document.getElementById('orderKpiDetailSubtitle').textContent = data.subtitle;
    document.getElementById('orderKpiCount').textContent = Number(data.count || 0).toLocaleString('fr-FR');
    document.getElementById('orderKpiTotal').textContent = Number(data.total_usd || 0).toLocaleString('fr-FR', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' USD';
    document.getElementById('orderKpiAverage').textContent = Number(data.average_usd || 0).toLocaleString('fr-FR', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' USD';
    var body = document.getElementById('orderKpiDetailBody');
    if (!data.rows.length) {
        body.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-5"><i class="bi bi-inboxes fs-3 d-block mb-2"></i>Aucun ordre dans cet indicateur.</td></tr>';
    } else {
        body.innerHTML = data.rows.map(function(row) {
            var statusClass = row.status === 'Exécuté' ? 'success' : (row.status === 'Émis' ? 'warning text-dark' : 'secondary');
            return '<tr>'
                + '<td><strong class="text-primary">' + escapeOrderKpiHtml(row.number) + '</strong></td>'
                + '<td class="text-nowrap">' + escapeOrderKpiHtml(row.date) + '</td>'
                + '<td>' + escapeOrderKpiHtml(row.request) + '</td>'
                + '<td>' + escapeOrderKpiHtml(row.beneficiary) + '</td>'
                + '<td style="max-width:250px">' + escapeOrderKpiHtml(row.object) + '</td>'
                + '<td><strong>' + escapeOrderKpiHtml(row.account) + '</strong><div class="text-muted small">' + escapeOrderKpiHtml(row.method) + '</div></td>'
                + '<td class="text-end fw-semibold text-nowrap">' + escapeOrderKpiHtml(row.amount) + '</td>'
                + '<td><span class="badge bg-' + statusClass + '">' + escapeOrderKpiHtml(row.status) + '</span></td>'
                + '<td class="text-nowrap">' + escapeOrderKpiHtml(row.action) + '</td>'
                + '<td><a class="btn btn-outline-primary btn-sm" href="' + escapeOrderKpiHtml(row.url) + '" title="Ouvrir"><i class="bi bi-eye"></i></a></td>'
                + '</tr>';
        }).join('');
    }
    document.getElementById('orderKpiPdfButton').href = APP_URL + '/treasury/ordres-kpi-pdf?metric=' + encodeURIComponent(metric);
    document.getElementById('orderKpiExcelButton').href = APP_URL + '/treasury/ordres-kpi-excel?metric=' + encodeURIComponent(metric);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('orderKpiDetailModal')).show();
}

document.querySelectorAll('.order-kpi-link').forEach(function(card) {
    card.addEventListener('click', function() { openOrderKpi(card.getAttribute('data-kpi')); });
    card.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openOrderKpi(card.getAttribute('data-kpi'));
        }
    });
});

$(function(){
    var tableEl = $('#tableOrdresPaiement');
    if (!tableEl.length || !$.fn.DataTable) return;
    var table = tableEl.DataTable({
        pageLength: 25,
        lengthMenu: [[10,25,50,100,-1],[10,25,50,100,'Toutes']],
        order: [[1,'desc'],[0,'desc']],
        stateSave: true,
        autoWidth: false,
        columnDefs: [{orderable:false,targets:6},{className:'text-nowrap',targets:[0,1,2,5]}],
        language: {url:'//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json',search:'Rechercher dans le tableau :',lengthMenu:'Afficher _MENU_ lignes'}
    });
    $('#orderQuickViews .order-view-btn').on('click', function(){
        $('#orderQuickViews .order-view-btn').removeClass('active');
        $(this).addClass('active');
        table.column(6).search($(this).data('status') || '').draw();
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
