<?php $pageTitle = 'Demandes de fonds - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>

<?php
if (!function_exists('treasuryRequestStatusBadge')) {
    function treasuryRequestStatusBadge(string $status): string {
        switch ($status) {
            case 'brouillon':
                return 'secondary';
            case 'soumise':
            case 'a_controler':
            case 'en_suspens':
                return 'warning text-dark';
            case 'validee_comptable':
                return 'info text-dark';
            case 'validee_direction':
                return 'primary';
            case 'ordre_emis':
                return 'dark';
            case 'payee':
                return 'success';
            case 'rejetee':
            case 'annulee':
                return 'danger';
            default:
                return 'secondary';
        }
    }
}

if (!function_exists('treasuryUrgencyBadge')) {
    function treasuryUrgencyBadge(string $urgency): string {
        switch ($urgency) {
            case 'haute':
                return 'danger';
            case 'moyenne':
                return 'warning text-dark';
            default:
                return 'secondary';
        }
    }
}

if (!function_exists('treasuryRequestNextAction')) {
    function treasuryRequestNextAction(array $demande): array {
        $id = (int)$demande['id'];
        $detailUrl = APP_URL . '/treasury/demande-voir/' . $id;
        $directionLabel = 'Approbation Direction requise';
        $directionOwner = 'Direction financière et générale';
        if (!empty($demande['direction_generale_validee']) && empty($demande['direction_financiere_validee'])) {
            $directionLabel = 'Validation Direction financière requise';
            $directionOwner = 'Direction financière · DG déjà validée';
        } elseif (!empty($demande['direction_financiere_validee']) && empty($demande['direction_generale_validee'])) {
            $directionLabel = 'Validation Direction générale requise';
            $directionOwner = 'Direction générale · DF déjà validée';
        }
        $actions = [
            'brouillon' => [
                'label' => 'À compléter et soumettre', 'owner' => 'Demandeur',
                'button' => 'Continuer', 'icon' => 'bi-pencil-square', 'tone' => 'secondary', 'url' => $detailUrl,
            ],
            'a_controler' => [
                'label' => 'Contrôle du dossier requis', 'owner' => 'Trésorier A',
                'button' => 'Examiner', 'icon' => 'bi-search', 'tone' => 'primary', 'url' => $detailUrl,
            ],
            'en_suspens' => [
                'label' => 'En attente de reprise', 'owner' => treasuryUiLabel($demande['suspended_level'] ?? 'comptable'),
                'button' => 'Consulter', 'icon' => 'bi-pause-circle', 'tone' => 'warning', 'url' => $detailUrl,
            ],
            'soumise' => [
                'label' => 'Contrôle comptable requis', 'owner' => 'Comptable senior',
                'button' => 'Examiner', 'icon' => 'bi-shield-check', 'tone' => 'warning', 'url' => $detailUrl,
            ],
            'validee_comptable' => [
                'label' => $directionLabel, 'owner' => $directionOwner,
                'button' => 'Examiner', 'icon' => 'bi-building-check', 'tone' => 'primary', 'url' => $detailUrl,
            ],
            'validee_direction' => [
                'label' => 'Ordre de paiement à émettre', 'owner' => 'Direction financière',
                'button' => 'Créer l’OP', 'icon' => 'bi-file-earmark-check', 'tone' => 'primary',
                'url' => APP_URL . '/treasury/ordre-paiement/' . $id,
            ],
            'ordre_emis' => [
                'label' => 'Paiement à exécuter', 'owner' => 'Trésorerie',
                'button' => 'Ouvrir l’OP', 'icon' => 'bi-cash-coin', 'tone' => 'warning',
                'url' => APP_URL . '/treasury/ordre-paiement/' . $id,
            ],
            'payee' => [
                'label' => 'Paiement terminé', 'owner' => 'Cycle clôturé',
                'button' => 'Consulter', 'icon' => 'bi-check2-circle', 'tone' => 'success', 'url' => $detailUrl,
            ],
            'rejetee' => [
                'label' => 'Correction requise', 'owner' => 'Demandeur',
                'button' => 'Voir le motif', 'icon' => 'bi-exclamation-circle', 'tone' => 'danger', 'url' => $detailUrl,
            ],
            'annulee' => [
                'label' => 'Demande annulée', 'owner' => 'Cycle clôturé',
                'button' => 'Consulter', 'icon' => 'bi-slash-circle', 'tone' => 'secondary', 'url' => $detailUrl,
            ],
        ];
        return $actions[$demande['status']] ?? [
            'label' => 'Suivi requis', 'owner' => 'Trésorerie',
            'button' => 'Consulter', 'icon' => 'bi-arrow-right-circle', 'tone' => 'secondary', 'url' => $detailUrl,
        ];
    }
}

if (!function_exists('treasuryRequestWaitingDays')) {
    function treasuryRequestWaitingDays(array $demande): int {
        $anchor = $demande['last_action_at'] ?? $demande['submitted_at'] ?? $demande['created_at'] ?? $demande['request_date'] ?? date('Y-m-d');
        $timestamp = strtotime((string)$anchor);
        return $timestamp ? max(0, (int)floor((time() - $timestamp) / 86400)) : 0;
    }
}

$requestExportQuery = http_build_query(array_merge(['metric' => 'registre'], array_filter($filtres ?? [], static function ($value) {
    return $value !== '';
})));
$accesDepartement = $accesDepartement ?? false;
$peutCreerDemande = $peutCreerDemande ?? false;
?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/treasury-requests.css">

<style>
.request-view-tabs { display:flex; gap:6px; flex-wrap:wrap; }
.request-view-btn { min-height:32px; padding:5px 10px; border:1px solid #d9e3ee; border-radius:6px; color:#53657a; background:#fff; font-size:.7rem; font-weight:700; }
.request-view-btn:hover,.request-view-btn.active { color:#1769e0; border-color:#a9c8ef; background:#edf5ff; }
.request-owner { display:flex; align-items:center; gap:8px; }
.request-owner-avatar { width:29px; height:29px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; color:#155bb7; background:#e8f1ff; font-size:.68rem; font-weight:800; }
.request-number { color:#1769e0; font-weight:800; }
.request-object { max-width:250px; color:#34465c; }
.request-next-action { min-width:270px; padding-left:11px; border-left:3px solid #cbd5e1; }
.request-next-action.tone-warning { border-left-color:#d99a18; }
.request-next-action.tone-primary { border-left-color:#1769e0; }
.request-next-action.tone-success { border-left-color:#198754; }
.request-next-action.tone-danger { border-left-color:#cf3542; }
.request-action-label { color:#1d3046; font-size:.78rem; font-weight:800; }
.request-action-owner { color:#718096; font-size:.68rem; }
.request-action-age { display:inline-flex; align-items:center; gap:4px; margin-top:4px; color:#718096; font-size:.65rem; }
.request-action-age.is-late { color:#b12634; font-weight:700; }
.request-action-btn { min-width:94px; white-space:nowrap; }
.request-table-card .dataTables_wrapper > .row { margin:0; padding:11px 14px; align-items:center; border-bottom:1px solid #edf1f5; }
.request-table-card .dataTables_wrapper > .row:last-child { border-top:1px solid #edf1f5; border-bottom:0; }
.request-table-card .dataTables_filter { text-align:right; }
.request-table-card .dataTables_filter input { min-width:230px; margin-left:8px; border:1px solid #cfd9e5; border-radius:6px; padding:6px 9px; }
.request-table-card .dataTables_length select { border:1px solid #cfd9e5; border-radius:6px; padding:5px 24px 5px 8px; }
.request-table-card .dataTables_info { color:#718096; font-size:.7rem; }
.request-table-card .pagination { margin:0; }
.request-kpi-card { cursor:pointer; transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease; }
.request-kpi-card:hover,.request-kpi-card:focus { transform:translateY(-2px); border-color:#a9c8ef; box-shadow:0 10px 24px rgba(17,55,93,.1); outline:0; }
.request-kpi-card .erp-kpi-meta::after { content:' · Voir les détails'; color:#1769e0; font-weight:700; }
.kpi-detail-summary { border:1px solid #dce5ef; border-radius:8px; background:#f8fbff; }
.kpi-detail-summary-value { color:#132c49; font-size:1.25rem; font-weight:800; }
.kpi-detail-table { font-size:.76rem; }
.kpi-detail-table th { color:#fff; background:#173e67; font-size:.65rem; text-transform:uppercase; letter-spacing:.025em; white-space:nowrap; }
.kpi-detail-table td { vertical-align:middle; }
@media(max-width:767.98px){.request-table-card .dataTables_filter{text-align:left;margin-top:8px}.request-table-card .dataTables_filter input{min-width:170px}.request-view-tabs{overflow-x:auto;flex-wrap:nowrap;padding-bottom:4px}.request-view-btn{white-space:nowrap}}
@media(max-width:575.98px){.request-next-action{min-width:230px;align-items:flex-start!important;flex-direction:column}.request-action-btn{width:100%}}
</style>

<div class="container-fluid tr-page">
    <div class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-0">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><?php if (!$accesDepartement): ?><a href="<?= APP_URL ?>/treasury/dashboard">Trésorerie</a><?php else: ?>Mon département<?php endif; ?></li>
                        <li class="breadcrumb-item active">Demandes</li>
                    </ol>
                </nav>
                <h2>Demandes de fonds</h2>
                <p class="text-muted small mb-0 mt-1"><?= $accesDepartement ? 'Créez vos demandes et suivez les décisions de la trésorerie et de la direction.' : 'Pilotez les besoins des départements, les contrôles et les validations.' ?></p>
            </div>
            <?php if ($peutCreerDemande): ?><a href="<?= APP_URL ?>/treasury/demande-ajouter" class="btn btn-primary align-self-start align-self-lg-center">
                <i class="bi bi-plus-lg me-1"></i>Nouvelle demande
            </a><?php endif; ?>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="row g-3 mb-3">
        <?php foreach ([
            'total'=>['Toutes les demandes','Volume enregistré','bi-card-list','primary'],
            'a_controler'=>['À contrôler','Trésorier A','bi-search','primary'],
            'soumise'=>['À valider','Comptable senior','bi-shield-check','warning'],
            'validee_comptable'=>['Circuit Direction','Visas de direction','bi-building-check','primary'],
            'en_suspens'=>['En suspens','Reprises et rappels','bi-pause-circle','warning'],
            'validee_direction'=>['Prêtes pour ordre','Demandes approuvées','bi-file-earmark-check','success'],
        ] as $metric => $kpi): ?><div class="col-6 col-md-4 col-xl-2"><div class="card erp-kpi erp-kpi-<?= $kpi[3] ?> request-kpi-card h-100" role="button" tabindex="0" data-kpi="<?= $metric ?>" aria-label="Consulter <?= e($kpi[0]) ?>"><div class="card-body"><div class="d-flex justify-content-between gap-2"><div class="erp-kpi-label"><?= e($kpi[0]) ?></div><span class="erp-kpi-icon"><i class="bi <?= $kpi[2] ?>"></i></span></div><div class="erp-kpi-value"><?= (int)($requestStats[$metric] ?? 0) ?></div><div class="erp-kpi-meta"><?= e($kpi[1]) ?></div></div></div></div><?php endforeach; ?>
    </div>

    <div class="card filter-card mb-3">
        <div class="card-body">
            <form method="GET" action="<?= APP_URL ?>/treasury/demandes">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Début</label>
                        <input type="date" class="form-control form-control-sm" name="date_debut" value="<?= e($filtres['date_debut'] ?? '') ?>" title="Date debut">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Fin</label>
                        <input type="date" class="form-control form-control-sm" name="date_fin" value="<?= e($filtres['date_fin'] ?? '') ?>" title="Date fin">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Statut</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">Statut</option>
                            <?php foreach (['brouillon','a_controler','soumise','validee_comptable','validee_direction','en_suspens','rejetee','ordre_emis','payee','annulee'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($filtres['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(treasuryUiLabel($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Urgence</label>
                        <select class="form-select form-select-sm" name="urgency">
                            <option value="">Urgence</option>
                            <option value="basse" <?= ($filtres['urgency'] ?? '') === 'basse' ? 'selected' : '' ?>>Basse</option>
                            <option value="moyenne" <?= ($filtres['urgency'] ?? '') === 'moyenne' ? 'selected' : '' ?>>Moyenne</option>
                            <option value="haute" <?= ($filtres['urgency'] ?? '') === 'haute' ? 'selected' : '' ?>>Haute</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Recherche</label>
                        <input type="text" class="form-control form-control-sm" name="recherche" value="<?= e($filtres['recherche'] ?? '') ?>" placeholder="Numero, beneficiaire, objet...">
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100" title="Filtrer"><i class="bi bi-search"></i></button>
                        <a href="<?= APP_URL ?>/treasury/demandes" class="btn btn-outline-secondary btn-sm" title="Reinitialiser"><i class="bi bi-x"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card section-card request-table-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><span class="erp-portlet-title"><i class="bi bi-list-check text-primary"></i>Registre des demandes</span><div class="small text-muted mt-1"><?= count($demandes) ?> résultat(s) selon les filtres métier</div></div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="request-view-tabs" id="requestQuickViews"><button type="button" class="request-view-btn active" data-status="">Toutes</button><button type="button" class="request-view-btn" data-status="À contrôler">À contrôler</button><button type="button" class="request-view-btn" data-status="Soumise">À valider</button><button type="button" class="request-view-btn" data-status="En suspens">En suspens</button><button type="button" class="request-view-btn" data-status="Validée comptable">Direction</button><button type="button" class="request-view-btn" data-status="Validée direction">Prêtes pour ordre</button><button type="button" class="request-view-btn" data-status="Payée">Payées</button></div>
                <?php if (!$accesDepartement): ?>
                <a href="<?= APP_URL ?>/treasury/demandes-kpi-pdf?<?= e($requestExportQuery . '&download=1') ?>" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf me-1"></i>PDF CFO
                </a>
                <a href="<?= APP_URL ?>/treasury/demandes-kpi-excel?<?= e($requestExportQuery) ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-file-earmark-excel me-1"></i>Excel CFO
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($demandes)): ?>
                <?php treasuryUiEmptyState('bi-card-checklist', 'Aucune demande trouvée', 'Ajustez les filtres ou créez une nouvelle demande de fonds.', $peutCreerDemande ? '<a href="' . APP_URL . '/treasury/demande-ajouter" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouvelle demande</a>' : ''); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100" id="tableDemandesFonds">
                        <thead class="table-dark">
                            <tr>
                                <th>Numéro</th>
                                <th>Date</th>
                                <th>Bénéficiaire</th>
                                <th>Objet</th>
                                <th>Categorie</th>
                                <th>Montant</th>
                                <th>Urgence</th>
                                <th>Prochaine action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($demandes as $demande): ?>
                                <?php
                                $nextAction = treasuryRequestNextAction($demande);
                                if ($accesDepartement) { $nextAction['url'] = APP_URL . '/treasury/demande-voir/' . (int)$demande['id']; $nextAction['button'] = 'Suivre'; }
                                $waitingDays = treasuryRequestWaitingDays($demande);
                                $isClosed = in_array($demande['status'], ['payee', 'annulee'], true);
                                $isLate = !$isClosed && $waitingDays >= 3;
                                ?>
                                <tr>
                                    <td data-order="<?= (int)$demande['id'] ?>">
                                        <a href="<?= APP_URL ?>/treasury/demande-voir/<?= (int)$demande['id'] ?>" class="fw-semibold text-decoration-none">
                                            <span class="request-number"><?= e($demande['request_number']) ?></span>
                                        </a>
                                    </td>
                                    <td data-order="<?= e($demande['request_date']) ?>"><?= formatDate($demande['request_date']) ?></td>
                                    <td><div class="request-owner"><span class="request-owner-avatar"><?= e(mb_strtoupper(mb_substr((string)$demande['beneficiary'],0,1))) ?></span><span><?= e($demande['beneficiary']) ?><?php if (!empty($demande['department'])): ?><span class="d-block small text-muted mt-1"><?= e($demande['department']) ?></span><?php endif; ?></span></div></td>
                                    <td><div class="request-object text-truncate" title="<?= e($demande['objet']) ?>"><?= e($demande['objet']) ?></div></td>
                                    <td><?= e($demande['category_nom'] ?? '—') ?></td>
                                    <td class="fw-semibold text-end" data-order="<?= e((string)(float)$demande['amount_usd']) ?>"><?= formatMontant((float)$demande['amount'], $demande['currency']) ?><?php if (isset($demande['approved_amount']) && (float)$demande['approved_amount'] < (float)$demande['amount']): ?><span class="d-block small text-success mt-1">Autorisé : <?= formatMontant((float)$demande['approved_amount'], $demande['currency']) ?></span><?php endif; ?></td>
                                    <td><span class="badge bg-<?= treasuryUrgencyBadge($demande['urgency']) ?>"><?= e(treasuryUiLabel($demande['urgency'])) ?></span></td>
                                    <td data-search="<?= e(treasuryUiLabel($demande['status']) . ' ' . $nextAction['label'] . ' ' . $nextAction['owner']) ?>">
                                        <div class="d-flex align-items-center justify-content-between gap-3 request-next-action tone-<?= e($nextAction['tone']) ?>">
                                            <div>
                                                <div class="request-action-label"><i class="bi <?= e($nextAction['icon']) ?> me-1 text-<?= e($nextAction['tone']) ?>"></i><?= e($nextAction['label']) ?></div>
                                                <div class="request-action-owner"><?= e($nextAction['owner']) ?> · <span class="badge bg-light text-secondary border"><?= e(treasuryUiLabel($demande['status'])) ?></span></div>
                                                <?php if ($demande['status'] === 'en_suspens' && !empty($demande['reminder_date'])): ?><div class="request-action-age <?= $demande['reminder_date'] <= date('Y-m-d') ? 'is-late' : '' ?>"><i class="bi bi-bell"></i>Rappel le <?= formatDate($demande['reminder_date']) ?></div><?php endif; ?>
                                                <?php if (!$isClosed): ?>
                                                    <div class="request-action-age <?= $isLate ? 'is-late' : '' ?>"><i class="bi bi-clock"></i><?= $waitingDays === 0 ? 'Dernière action aujourd’hui' : 'Sans action depuis ' . $waitingDays . ' jour' . ($waitingDays > 1 ? 's' : '') ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?= e($nextAction['url']) ?>" class="btn btn-outline-<?= e($nextAction['tone']) ?> btn-sm request-action-btn">
                                                <?= e($nextAction['button']) ?><i class="bi bi-arrow-right ms-1"></i>
                                            </a>
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

<div class="modal fade" id="requestKpiDetailModal" tabindex="-1" aria-labelledby="requestKpiDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width:min(96vw,1450px)">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header px-4 py-3">
                <div>
                    <div class="text-primary small fw-bold text-uppercase mb-1">Analyse des demandes de fonds</div>
                    <h5 class="modal-title fw-bold mb-1" id="requestKpiDetailModalLabel">Détail du KPI</h5>
                    <div class="text-muted small" id="requestKpiDetailSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="kpi-detail-summary p-3"><div class="text-muted small mb-1">Nombre de demandes</div><div class="kpi-detail-summary-value" id="requestKpiCount">0</div></div></div>
                    <div class="col-md-4"><div class="kpi-detail-summary p-3"><div class="text-muted small mb-1">Engagement total</div><div class="kpi-detail-summary-value text-primary" id="requestKpiTotal">0,00 USD</div></div></div>
                    <div class="col-md-4"><div class="kpi-detail-summary p-3"><div class="text-muted small mb-1">Montant moyen</div><div class="kpi-detail-summary-value" id="requestKpiAverage">0,00 USD</div></div></div>
                </div>
                <div class="table-responsive border rounded-3">
                    <table class="table table-hover mb-0 kpi-detail-table">
                        <thead><tr><th>Numéro</th><th>Date</th><th>Bénéficiaire</th><th>Objet</th><th>Catégorie</th><th class="text-end">Montant</th><th>Urgence</th><th>Statut</th><th></th></tr></thead>
                        <tbody id="requestKpiDetailBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer px-4">
                <span class="text-muted small me-auto"><?= $accesDepartement ? 'Ces demandes sont limitées à votre périmètre de consultation.' : 'Les exports reprennent l’intégralité des lignes de cet indicateur.' ?></span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                <?php if (!$accesDepartement): ?><a class="btn btn-outline-danger" id="requestKpiPdfButton" href="#" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>PDF CFO</a>
                <a class="btn btn-success" id="requestKpiExcelButton" href="#"><i class="bi bi-file-earmark-excel me-1"></i>Excel CFO</a><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
var requestKpiData = <?= json_encode($requestKpiDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function escapeRequestKpiHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function openRequestKpi(metric) {
    var data = requestKpiData[metric];
    if (!data) return;
    document.getElementById('requestKpiDetailModalLabel').textContent = data.title;
    document.getElementById('requestKpiDetailSubtitle').textContent = data.subtitle;
    document.getElementById('requestKpiCount').textContent = Number(data.count || 0).toLocaleString('fr-FR');
    document.getElementById('requestKpiTotal').textContent = Number(data.total_usd || 0).toLocaleString('fr-FR', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' USD';
    document.getElementById('requestKpiAverage').textContent = (data.count ? Number(data.total_usd || 0) / Number(data.count) : 0).toLocaleString('fr-FR', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' USD';

    var body = document.getElementById('requestKpiDetailBody');
    if (!data.rows.length) {
        body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-inboxes fs-3 d-block mb-2"></i>Aucune demande dans cet indicateur.</td></tr>';
    } else {
        body.innerHTML = data.rows.map(function(row) {
            var urgencyClass = row.urgency === 'Haute' ? 'danger' : (row.urgency === 'Moyenne' ? 'warning text-dark' : 'secondary');
            return '<tr>'
                + '<td><strong class="text-primary">' + escapeRequestKpiHtml(row.number) + '</strong></td>'
                + '<td class="text-nowrap">' + escapeRequestKpiHtml(row.date) + '</td>'
                + '<td>' + escapeRequestKpiHtml(row.beneficiary) + '</td>'
                + '<td style="max-width:260px">' + escapeRequestKpiHtml(row.object) + '</td>'
                + '<td>' + escapeRequestKpiHtml(row.category) + '</td>'
                + '<td class="text-end fw-semibold text-nowrap">' + escapeRequestKpiHtml(row.amount) + '</td>'
                + '<td><span class="badge bg-' + urgencyClass + '">' + escapeRequestKpiHtml(row.urgency) + '</span></td>'
                + '<td><span class="badge bg-light text-dark border">' + escapeRequestKpiHtml(row.status) + '</span></td>'
                + '<td><a class="btn btn-outline-primary btn-sm" href="' + escapeRequestKpiHtml(row.url) + '" title="Ouvrir"><i class="bi bi-eye"></i></a></td>'
                + '</tr>';
        }).join('');
    }
    var pdfButton = document.getElementById('requestKpiPdfButton');
    var excelButton = document.getElementById('requestKpiExcelButton');
    if (pdfButton) pdfButton.href = APP_URL + '/treasury/demandes-kpi-pdf?metric=' + encodeURIComponent(metric);
    if (excelButton) excelButton.href = APP_URL + '/treasury/demandes-kpi-excel?metric=' + encodeURIComponent(metric);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('requestKpiDetailModal')).show();
}

document.querySelectorAll('.request-kpi-card').forEach(function(card) {
    card.addEventListener('click', function() { openRequestKpi(card.getAttribute('data-kpi')); });
    card.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openRequestKpi(card.getAttribute('data-kpi'));
        }
    });
});

$(function(){
    var tableEl=$('#tableDemandesFonds');
    if(!tableEl.length||!$.fn.DataTable)return;
    var table=tableEl.DataTable({
        pageLength:25,
        lengthMenu:[[10,25,50,100,-1],[10,25,50,100,'Toutes']],
        order:[[1,'desc'],[0,'desc']],
        stateSave:true,
        autoWidth:false,
        columnDefs:[{orderable:false,targets:7},{className:'text-nowrap',targets:[0,1,5,6]}],
        language:{url:'//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json',search:'Rechercher dans le tableau :',lengthMenu:'Afficher _MENU_ lignes'}
    });
    $('#requestQuickViews .request-view-btn').on('click',function(){
        $('#requestQuickViews .request-view-btn').removeClass('active');
        $(this).addClass('active');
        table.column(7).search($(this).data('status')||'').draw();
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
