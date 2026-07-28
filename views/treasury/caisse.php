<?php
$pageTitle = 'Livre de caisse - ' . APP_NAME;
$cashSwal = $_SESSION['cash_swal'] ?? null;
unset($_SESSION['cash_swal']);
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>

<?php
if (!function_exists('treasuryCashStatusBadge')) {
    function treasuryCashStatusBadge(string $status): string {
        switch ($status) {
            case 'ouverte':
                return 'success';
            case 'cloturee':
                return 'warning text-dark';
            case 'controlee':
                return 'primary';
            case 'verrouillee':
                return 'dark';
            default:
                return 'secondary';
        }
    }
}

$compteActuel = null;
foreach ($comptes as $compte) {
    if ((int)$compte['id'] === (int)$compteSelectionne) {
        $compteActuel = $compte;
        break;
    }
}

$sessionStatus = $sessionCourante['status'] ?? 'non_ouverte';
$journeeCloturee = in_array($sessionStatus, ['cloturee','controlee','verrouillee'], true);
$peutCloturer = !empty($sessionCourante) && (!$journeeCloturee || $peutModifierCloturee);
$alertePlafond = (float)($stats['solde_caisse_usd'] ?? 0) > (float)$plafondCaisseUsd;
$soldeCourantMouvements = (float)($soldeInitialMouvements ?? 0);
$mouvementsAvecSolde = [];
foreach ($mouvements as $mouvement) {
    $montant = (float)$mouvement['amount'];
    $soldeCourantMouvements += $mouvement['sens'] === 'entree' ? $montant : -$montant;
    $mouvement['running_balance'] = $soldeCourantMouvements;
    $mouvementsAvecSolde[] = $mouvement;
}
$deviseMouvements = $sessionCourante['currency'] ?? ($compteActuel['devise'] ?? 'USD');
$cashIn = (float)($sessionCourante['cash_in'] ?? 0);
$cashOut = (float)($sessionCourante['cash_out'] ?? 0);
$cashNet = $cashIn - $cashOut;
$variance = $sessionCourante && $sessionCourante['variance_amount'] !== null ? (float)$sessionCourante['variance_amount'] : null;
$cashRatio = $plafondCaisseUsd > 0 ? min(100, ((float)($stats['solde_caisse_usd'] ?? 0) / $plafondCaisseUsd) * 100) : 0;
$livrePdfUrl = APP_URL . '/treasury/caisse-pdf?cash_account_id=' . (int)$compteSelectionne
    . '&date_debut=' . rawurlencode((string)$filtres['date_debut'])
    . '&date_fin=' . rawurlencode((string)$filtres['date_fin']);
$livreExcelUrl = APP_URL . '/treasury/caisse-excel?cash_account_id=' . (int)$compteSelectionne
    . '&date_debut=' . rawurlencode((string)$filtres['date_debut'])
    . '&date_fin=' . rawurlencode((string)$filtres['date_fin']);
?>

<style>
.treasury-compact-table {
    font-size: .82rem;
}
.treasury-compact-table > :not(caption) > * > * {
    padding: .45rem .55rem;
    vertical-align: middle;
}
.movement-detail-row td {
    background: #f8fbff;
    border-top: 0;
}
.movement-detail-row.collapse.show,
.movement-detail-row.collapsing {
    display: table-row;
}
.movement-toggle {
    width: 30px;
    height: 30px;
    padding: 0;
}
.cash-workspace-bar { background: #fff; border: 1px solid var(--erp-border); border-radius: 8px; box-shadow: var(--erp-shadow); }
.cash-context-label { color: #718096; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
.cash-context-value { color: #17283e; font-size: .82rem; font-weight: 800; }
.cash-status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: .69rem; font-weight: 800; }
.cash-status-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.cash-kpi .erp-kpi-value { font-size: 1.2rem; }
.cash-balance-card { color: #fff; border-color: #123f70; background: linear-gradient(135deg,#0b2847,#155a99); }
.cash-balance-card .erp-kpi-label,.cash-balance-card .erp-kpi-meta { color: #b8cee4; }
.cash-balance-card .erp-kpi-value { color: #fff; }
.cash-workflow { display: flex; align-items: flex-start; }
.cash-workflow-step { position: relative; flex: 1; color: #8290a0; text-align: center; font-size: .63rem; font-weight: 700; }
.cash-workflow-step:not(:last-child)::after { content:''; position:absolute; top:11px; left:58%; right:-42%; height:2px; background:#dde5ee; }
.cash-workflow-marker { position:relative; z-index:1; width:24px; height:24px; margin:0 auto 6px; display:flex; align-items:center; justify-content:center; border:2px solid #d4dde7; border-radius:50%; background:#fff; font-size:.65rem; }
.cash-workflow-step.done { color:#16834b; }
.cash-workflow-step.done .cash-workflow-marker { color:#fff; border-color:#16834b; background:#16834b; }
.cash-workflow-step.done:not(:last-child)::after { background:#8dd0ad; }
.cash-workflow-step.current { color:#1769e0; }
.cash-workflow-step.current .cash-workflow-marker { color:#1769e0; border-color:#1769e0; background:#e9f2ff; box-shadow:0 0 0 4px rgba(23,105,224,.08); }
.cash-action-panel { position: sticky; top: 82px; }
.cash-control-list { margin:0; }
.cash-control-list div { display:flex; justify-content:space-between; gap:16px; padding:9px 0; border-bottom:1px solid #edf1f5; font-size:.74rem; }
.cash-control-list dt { color:#718096; font-weight:600; }
.cash-control-list dd { margin:0; color:#273a50; font-weight:700; text-align:right; }
.cash-section-help { color:#75869a; font-size:.68rem; font-weight:400; }
.cash-close-launch { border:1px solid #f0d59c; background:#fffaf0; }
.cash-close-launch-icon { width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; color:#9a6810; background:#ffefc8; }
.cash-close-check { display:flex; align-items:flex-start; gap:9px; padding:9px 0; border-bottom:1px solid #edf1f5; font-size:.72rem; }
.cash-close-check:last-child { border-bottom:0; }
.cash-close-check i { margin-top:1px; color:#16834b; }
.cash-close-summary { border:1px solid #dce5ef; border-radius:8px; background:#f8fbff; }
.cash-close-summary-label { color:#718096; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.035em; }
.cash-close-summary-value { margin-top:4px; color:#172b43; font-size:1rem; font-weight:800; }
.cash-variance-box { border:1px solid #dce5ef; border-radius:8px; background:#f8fafc; }
.cash-variance-box.has-variance { border-color:#edbcc2; background:#fff5f6; }
.cash-variance-value { color:#16834b; font-size:1.12rem; font-weight:850; }
.cash-variance-box.has-variance .cash-variance-value { color:#b12634; }
@media print {
    .sidebar, .navbar, .btn, .filter-card, .no-print, footer { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    .page-hero { padding: 0 0 1rem 0 !important; background: transparent !important; }
    body { background: #fff !important; }
}
</style>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-0">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/dashboard">Trésorerie</a></li>
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/comptes">Comptes</a></li>
                        <li class="breadcrumb-item active">Livre de caisse</li>
                    </ol>
                </nav>
                <h2>Livre de caisse</h2>
                <p class="text-muted small mb-0 mt-1">Suivez les espèces, rapprochez le solde physique et sécurisez la clôture quotidienne.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">
                <button type="submit" form="formRecalculerCaisse" class="btn btn-outline-secondary" <?= $compteSelectionne<=0?'disabled':'' ?>><i class="bi bi-arrow-repeat me-1"></i>Actualiser</button>
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalApercuExcelCfo" <?= $compteSelectionne<=0?'disabled':'' ?>><i class="bi bi-file-earmark-excel me-1"></i>Excel CFO</button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalApercuLivreCaisse" <?= $compteSelectionne<=0?'disabled':'' ?>><i class="bi bi-file-earmark-pdf me-1"></i>Prévisualiser le livre</button>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <?php if ($alertePlafond): ?>
        <div class="alert alert-danger d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>
                Solde caisse consolide: <strong><?= formatMontant((float)$stats['solde_caisse_usd'], 'USD') ?></strong>.
                Plafond autorise: <strong><?= formatMontant((float)$plafondCaisseUsd, 'USD') ?></strong>.
            </div>
        </div>
    <?php endif; ?>

    <div class="cash-workspace-bar mb-3 no-print">
        <div class="p-3">
            <form method="GET" action="<?= APP_URL ?>/treasury/caisse">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-4 col-md-5">
                        <label class="form-label">Compte de caisse</label>
                        <select class="form-select" name="cash_account_id">
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?= (int)$compte['id'] ?>" <?= (int)$compteSelectionne === (int)$compte['id'] ? 'selected' : '' ?>>
                                    <?= e($compte['nom']) ?> (<?= e($compte['devise']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label">Journée</label>
                        <input type="date" class="form-control" name="date_fin" id="cashDateField" value="<?= e($dateSelectionnee) ?>">
                        <input type="hidden" name="date_debut" id="cashDateStartField" value="<?= e($dateSelectionnee) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label">Statut historique</label>
                        <select class="form-select" name="status">
                            <option value="">Tous</option>
                            <?php foreach (['ouverte','cloturee','controlee','verrouillee'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($filtres['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Afficher
                        </button>
                        <?php if ($compteSelectionne > 0): ?>
                            <?php if (!$sessionCourante): ?><button type="submit" form="formOuvrirCaisse" class="btn btn-success"><i class="bi bi-unlock me-1"></i>Ouvrir la journée</button><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php if ($compteSelectionne > 0): ?>
                <form id="formOuvrirCaisse" method="POST" action="<?= APP_URL ?>/treasury/caisse" class="d-none">
                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                    <input type="hidden" name="action" value="ouvrir">
                    <input type="hidden" name="cash_account_id" value="<?= (int)$compteSelectionne ?>">
                    <input type="hidden" name="session_date" value="<?= e($dateSelectionnee) ?>">
                </form>
                <form id="formRecalculerCaisse" method="POST" action="<?= APP_URL ?>/treasury/caisse" class="d-none">
                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                    <input type="hidden" name="action" value="recalculer">
                    <input type="hidden" name="cash_account_id" value="<?= (int)$compteSelectionne ?>">
                    <input type="hidden" name="session_date" value="<?= e($dateSelectionnee) ?>">
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($comptes)): ?>
        <div class="alert alert-warning">Aucun compte de type caisse n’est configure.</div>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3"><div class="card erp-kpi cash-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Solde d’ouverture</div><span class="erp-kpi-icon bg-light text-primary"><i class="bi bi-door-open"></i></span></div><div class="erp-kpi-value"><?= formatMontant((float)($sessionCourante['opening_balance']??0),$deviseMouvements) ?></div><div class="erp-kpi-meta"><?= e($compteActuel['nom']??'Compte non sélectionné') ?></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card erp-kpi erp-kpi-success cash-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Entrées du jour</div><span class="erp-kpi-icon"><i class="bi bi-arrow-down-left"></i></span></div><div class="erp-kpi-value text-success"><?= formatMontant($cashIn,$deviseMouvements) ?></div><div class="erp-kpi-meta">Encaissements espèces validés</div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card erp-kpi erp-kpi-danger cash-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Sorties du jour</div><span class="erp-kpi-icon"><i class="bi bi-arrow-up-right"></i></span></div><div class="erp-kpi-value text-danger"><?= formatMontant($cashOut,$deviseMouvements) ?></div><div class="erp-kpi-meta">Flux net : <?= formatMontant($cashNet,$deviseMouvements) ?></div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card erp-kpi cash-kpi cash-balance-card"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Solde théorique</div><span class="erp-kpi-icon bg-white bg-opacity-10 text-white"><i class="bi bi-calculator"></i></span></div><div class="erp-kpi-value"><?= formatMontant((float)($sessionCourante['theoretical_balance']??0),$deviseMouvements) ?></div><div class="erp-kpi-meta">Solde attendu en fin de journée</div></div></div></div>
        </div>

        <div class="card section-card mb-3 no-print"><div class="card-body py-3"><div class="row align-items-center g-3"><div class="col-lg-4"><div class="cash-context-label">Journée sélectionnée</div><div class="d-flex align-items-center gap-2 mt-1"><div class="cash-context-value"><?= formatDate($dateSelectionnee) ?> · <?= e($compteActuel['nom']??'—') ?></div><span class="cash-status-pill bg-<?= treasuryUiStatusBadge($sessionStatus) ?> bg-opacity-10 text-<?= treasuryUiStatusBadge($sessionStatus) ?>"><span class="cash-status-dot"></span><?= e(treasuryUiLabel($sessionStatus)) ?></span></div></div><div class="col-lg-8"><div class="cash-workflow">
            <?php $workflowStates=['ouverte'=>1,'cloturee'=>2,'controlee'=>3,'verrouillee'=>4]; $currentStep=$workflowStates[$sessionStatus]??0; foreach([1=>'Ouverture',2=>'Clôture',3=>'Contrôle',4=>'Verrouillage'] as $step=>$label): ?><div class="cash-workflow-step <?= $currentStep>$step?'done':($currentStep===$step?'current':'') ?>"><span class="cash-workflow-marker"><?= $currentStep>$step?'<i class="bi bi-check"></i>':$step ?></span><?= e($label) ?></div><?php endforeach; ?>
        </div></div></div></div></div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card section-card mb-4">
                    <div class="card-header bg-transparent border-0 fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><i class="bi bi-journal-text me-2"></i>Mouvements du <?= formatDate($filtres['date_debut']) ?> au <?= formatDate($filtres['date_fin']) ?></span>
                        <span class="d-flex align-items-center gap-2">
                            <?php if ($sessionCourante): ?>
                                <span class="badge bg-<?= treasuryUiStatusBadge($sessionCourante['status']) ?>"><?= e(treasuryUiLabel($sessionCourante['status'])) ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">non_ouverte</span>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-success btn-sm no-print" data-bs-toggle="modal" data-bs-target="#modalApercuExcelCfo">
                                <i class="bi bi-file-earmark-excel me-1"></i>Excel CFO
                            </button>
                        </span>
                    </div>
                    <div class="card-body border-bottom no-print">
                        <div class="row g-2 align-items-end" data-movement-controls="cashMovementsTable">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Date début</label>
                                <input type="date" class="form-control form-control-sm" data-filter-start value="<?= e($filtres['date_debut']) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Date fin</label>
                                <input type="date" class="form-control form-control-sm" data-filter-end value="<?= e($filtres['date_fin']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Tri</label>
                                <select class="form-select form-select-sm" data-sort-order>
                                    <option value="asc">Croissant</option>
                                    <option value="desc">Décroissant</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold">Affichage</label>
                                <select class="form-select form-select-sm" data-display-mode>
                                    <option value="continuous">Continu</option>
                                    <option value="paginated">Pagination</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex gap-1">
                                <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-reset-filters>
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($mouvementsAvecSolde)): ?>
                            <?php treasuryUiEmptyState('bi-inboxes', 'Aucun mouvement espèces', 'Les encaissements espèces et paiements caisse de cette période apparaîtront ici.'); ?>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 treasury-compact-table" id="cashMovementsTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="no-print" data-export-skip="1"></th>
                                            <th>Date</th>
                                            <th>Référence</th>
                                            <th>Type</th>
                                            <th class="text-end">Entree</th>
                                            <th class="text-end">Sortie</th>
                                            <th class="text-end">Solde</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($mouvementsAvecSolde as $index => $mouvement): ?>
                                            <?php
                                            $entree = $mouvement['sens'] === 'entree' ? (float)$mouvement['amount'] : 0.0;
                                            $sortie = $mouvement['sens'] === 'sortie' ? (float)$mouvement['amount'] : 0.0;
                                            $detailId = 'cashMovementDetail' . (int)$index;
                                            ?>
                                            <tr class="movement-master-row"
                                                data-date="<?= e($mouvement['operation_date']) ?>"
                                                data-sort="<?= e($mouvement['operation_date'] . ' ' . ($mouvement['created_at'] ?? '00:00:00') . ' ' . sprintf('%08d', (int)$mouvement['id'])) ?>">
                                                <td class="no-print" data-export-skip="1">
                                                    <button class="btn btn-outline-secondary btn-sm movement-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($detailId) ?>" aria-expanded="false" title="Voir les détails">
                                                        <i class="bi bi-chevron-down"></i>
                                                    </button>
                                                </td>
                                                <td><?= formatDate($mouvement['operation_date']) ?></td>
                                                <td class="fw-semibold"><?= e($mouvement['reference_operation']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $mouvement['sens'] === 'entree' ? 'success' : 'danger' ?>">
                                                        <?= e(treasuryUiLabel($mouvement['type_operation'])) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end text-success" data-export-value="<?= e(number_format($entree, 2, '.', '')) ?>">
                                                    <?= $entree > 0 ? formatMontant($entree, $mouvement['currency']) : '—' ?>
                                                </td>
                                                <td class="text-end text-danger" data-export-value="<?= e(number_format($sortie, 2, '.', '')) ?>">
                                                    <?= $sortie > 0 ? formatMontant($sortie, $mouvement['currency']) : '—' ?>
                                                </td>
                                                <td class="text-end fw-semibold" data-export-value="<?= e(number_format((float)$mouvement['running_balance'], 2, '.', '')) ?>">
                                                    <?= formatMontant((float)$mouvement['running_balance'], $mouvement['currency']) ?>
                                                </td>
                                            </tr>
                                            <tr class="movement-detail-row collapse" id="<?= e($detailId) ?>" data-detail-for="<?= e($mouvement['operation_date']) ?>" data-export-skip="1">
                                                <td colspan="7">
                                                    <div class="row g-2 small">
                                                        <div class="col-md-4"><span class="text-muted">Partenaire:</span> <strong><?= e($mouvement['partenaire'] ?? '—') ?></strong></div>
                                                        <div class="col-md-5"><span class="text-muted">Libellé:</span> <?= e($mouvement['libelle'] ?? '—') ?></div>
                                                        <div class="col-md-3"><span class="text-muted">Montant USD:</span> <?= formatMontant((float)$mouvement['amount_usd'], 'USD') ?></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-2 border-top no-print" data-pagination-for="cashMovementsTable">
                                <div class="small text-muted" data-page-info></div>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" data-page-prev><i class="bi bi-chevron-left"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" data-page-next><i class="bi bi-chevron-right"></i></button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card section-card">
                    <div class="card-header bg-transparent border-0 fw-semibold">
                        <i class="bi bi-clock-history me-2"></i>Historique des journees
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($sessions)): ?>
                            <?php treasuryUiEmptyState('bi-safe2', 'Aucune session de caisse trouvée', 'Ajustez les filtres ou ouvrez une journée de caisse.'); ?>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Compte</th>
                                            <th>Ouverture</th>
                                            <th>Entrees</th>
                                            <th>Sorties</th>
                                            <th>Theorique</th>
                                            <th>Ecart</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                            <tr>
                                                <td><?= formatDate($session['session_date']) ?></td>
                                                <td><?= e($session['cash_account_nom']) ?></td>
                                                <td><?= formatMontant((float)$session['opening_balance'], $session['currency']) ?></td>
                                                <td class="text-success"><?= formatMontant((float)$session['cash_in'], $session['currency']) ?></td>
                                                <td class="text-danger"><?= formatMontant((float)$session['cash_out'], $session['currency']) ?></td>
                                                <td><?= formatMontant((float)$session['theoretical_balance'], $session['currency']) ?></td>
                                                <td class="<?= (float)($session['variance_amount'] ?? 0) == 0.0 ? '' : 'text-danger fw-semibold' ?>">
                                                    <?= $session['variance_amount'] !== null ? formatMontant((float)$session['variance_amount'], $session['currency']) : '—' ?>
                                                </td>
                                                <td><span class="badge bg-<?= treasuryUiStatusBadge($session['status']) ?>"><?= e(treasuryUiLabel($session['status'])) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="cash-action-panel">
                <div class="card section-card mb-3">
                    <div class="card-header">
                        <span class="erp-portlet-title"><i class="bi bi-clipboard-check text-primary"></i>Contrôle de la journée</span>
                    </div>
                    <div class="card-body">
                        <dl class="cash-control-list"><div><dt>Compte</dt><dd><?= e($compteActuel['nom']??'—') ?></dd></div><div><dt>Date</dt><dd><?= formatDate($dateSelectionnee) ?></dd></div><div><dt>Montant physique</dt><dd><?= $sessionCourante&&$sessionCourante['physical_balance']!==null?formatMontant((float)$sessionCourante['physical_balance'],$deviseMouvements):'À compter' ?></dd></div><div><dt>Écart constaté</dt><dd class="<?= $variance!==null&&abs($variance)>.001?'text-danger':'text-success' ?>"><?= $variance!==null?formatMontant($variance,$deviseMouvements):'—' ?></dd></div><div><dt>Clôturé par</dt><dd><?= e($sessionCourante['closed_by_nom']??'—') ?></dd></div><div><dt>Contrôlé par</dt><dd><?= e($sessionCourante['checked_by_nom']??'—') ?></dd></div></dl>

                        <?php if (!empty($sessionCourante['close_comment'])): ?>
                            <div class="alert alert-light border small mb-2">
                                <strong>Commentaire cloture:</strong><br><?= nl2br(e($sessionCourante['close_comment'])) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($sessionCourante['control_comment'])): ?>
                            <div class="alert alert-light border small">
                                <strong>Commentaire controle:</strong><br><?= nl2br(e($sessionCourante['control_comment'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($sessionCourante && $peutCloturer): ?>
                    <div class="card cash-close-launch mb-3 no-print">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="cash-close-launch-icon"><i class="bi bi-lock"></i></span>
                                <div><div class="fw-bold"><?= $journeeCloturee ? 'Corriger la clôture' : 'Clôturer la journée' ?></div><div class="text-muted small mt-1"><?= $journeeCloturee ? 'Mettez à jour le comptage sous habilitation renforcée.' : 'Finalisez le comptage physique et transmettez la journée au contrôle.' ?></div></div>
                            </div>
                            <div class="cash-close-check"><i class="bi bi-check2-circle"></i><span>Écritures recalculées · solde théorique <strong><?= formatMontant((float)$sessionCourante['theoretical_balance'],$deviseMouvements) ?></strong></span></div>
                            <div class="cash-close-check"><i class="bi bi-check2-circle"></i><span><?= count($mouvementsAvecSolde) ?> mouvement(s) inclus dans la journée sélectionnée</span></div>
                            <button type="button" class="btn btn-warning w-100 mt-3" data-bs-toggle="modal" data-bs-target="#modalClotureCaisse">
                                <i class="bi bi-clipboard-check me-1"></i><?= $journeeCloturee ? 'Examiner et corriger' : 'Préparer la clôture' ?>
                            </button>
                        </div>
                    </div>
                <?php elseif ($sessionCourante && $journeeCloturee && !$peutModifierCloturee): ?>
                    <div class="alert alert-secondary no-print">
                        Journee cloturee. Modification reservee aux profils admin/direction.
                    </div>
                <?php endif; ?>

                <?php if ($sessionCourante && $sessionCourante['status'] === 'cloturee' && $peutControlerCaisse): ?>
                    <div class="card form-card no-print"><div class="card-header"><span class="erp-portlet-title"><i class="bi bi-shield-check text-success"></i>Contrôle comptable</span></div>
                        <div class="card-body">
                            <p class="text-muted small">Examinez le rapprochement et formalisez le visa comptable de la journée.</p>
                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#modalControleCaisse">
                                <i class="bi bi-shield-check me-1"></i>Préparer le contrôle
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($sessionCourante && $peutCloturer): ?>
<div class="modal fade" id="modalClotureCaisse" tabindex="-1" aria-labelledby="modalClotureCaisseLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= APP_URL ?>/treasury/caisse-cloturer" id="cashClosureForm">
                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                <input type="hidden" name="action" value="cloturer">
                <input type="hidden" name="session_id" value="<?= (int)$sessionCourante['id'] ?>">
                <input type="hidden" name="return_to" value="compte">
                <div class="modal-header px-4 py-3">
                    <div>
                        <div class="text-warning-emphasis small fw-bold text-uppercase mb-1">Contrôle de fin de journée</div>
                        <h5 class="modal-title fw-bold mb-1" id="modalClotureCaisseLabel"><?= $journeeCloturee ? 'Correction de la clôture' : 'Clôture de la caisse' ?></h5>
                        <div class="text-muted small"><?= e($compteActuel['nom']??'Compte de caisse') ?> · <?= formatDate($dateSelectionnee) ?> · <?= e($deviseMouvements) ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="alert alert-light border d-flex align-items-start gap-2 small mb-4">
                        <i class="bi bi-info-circle text-primary mt-1"></i>
                        <div>La clôture fige le comptage physique et place la journée dans le circuit de contrôle comptable. Vérifiez les montants avant de confirmer.</div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><div class="cash-close-summary p-3 h-100"><div class="cash-close-summary-label">Solde d’ouverture</div><div class="cash-close-summary-value"><?= formatMontant((float)$sessionCourante['opening_balance'],$deviseMouvements) ?></div></div></div>
                        <div class="col-md-4"><div class="cash-close-summary p-3 h-100"><div class="cash-close-summary-label">Flux net</div><div class="cash-close-summary-value <?= $cashNet>=0?'text-success':'text-danger' ?>"><?= formatMontant($cashNet,$deviseMouvements) ?></div></div></div>
                        <div class="col-md-4"><div class="cash-close-summary p-3 h-100"><div class="cash-close-summary-label">Solde théorique</div><div class="cash-close-summary-value text-primary" id="cashClosureTheoreticalDisplay"><?= formatMontant((float)$sessionCourante['theoretical_balance'],$deviseMouvements) ?></div></div></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="cashPhysicalBalance" class="form-label fw-semibold">Montant physique réellement compté</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" name="physical_balance" id="cashPhysicalBalance" class="form-control form-control-lg" value="<?= e((string)($sessionCourante['physical_balance'] ?? $sessionCourante['theoretical_balance'])) ?>" data-theoretical="<?= e((string)(float)$sessionCourante['theoretical_balance']) ?>" required>
                                <span class="input-group-text fw-semibold"><?= e($deviseMouvements) ?></span>
                            </div>
                            <div class="form-text">Saisissez le total issu du comptage des billets et pièces.</div>
                        </div>
                        <div class="col-md-5">
                            <div class="cash-variance-box p-3 h-100" id="cashVarianceBox">
                                <div class="cash-close-summary-label">Écart de caisse</div>
                                <div class="cash-variance-value mt-2" id="cashVarianceValue">0,00 <?= e($deviseMouvements) ?></div>
                                <div class="text-muted small mt-1" id="cashVarianceHelp">Comptage conforme au solde théorique.</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="cashClosureComment" class="form-label fw-semibold">Observation de clôture <span class="text-muted fw-normal">(obligatoire en cas d’écart)</span></label>
                            <textarea name="commentaire" id="cashClosureComment" class="form-control" rows="3" placeholder="Précisez le comptage, l’origine d’un écart ou toute information utile au contrôleur."><?= e($sessionCourante['close_comment'] ?? '') ?></textarea>
                            <div class="invalid-feedback">Une justification est obligatoire lorsqu’un écart est constaté.</div>
                        </div>
                    </div>
                    <div class="form-check border rounded-3 p-3 ps-5 mt-4 bg-light">
                        <input class="form-check-input" type="checkbox" value="1" name="confirm_closure" id="cashClosureConfirmation" required>
                        <label class="form-check-label small" for="cashClosureConfirmation">Je confirme avoir effectué le comptage physique et vérifié les écritures de la journée.</label>
                    </div>
                </div>
                <div class="modal-footer px-4 py-3">
                    <span class="text-muted small me-auto"><i class="bi bi-shield-check me-1"></i>Action journalisée avec votre identité.</span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning px-4" id="cashClosureSubmit"><i class="bi bi-lock me-1"></i><?= $journeeCloturee ? 'Enregistrer la correction' : 'Clôturer la journée' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($sessionCourante && $sessionCourante['status'] === 'cloturee' && $peutControlerCaisse): ?>
<div class="modal fade" id="modalControleCaisse" tabindex="-1" aria-labelledby="modalControleCaisseLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="<?= APP_URL ?>/treasury/caisse-cloturer" id="cashControlForm">
                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                <input type="hidden" name="action" value="controler">
                <input type="hidden" name="session_id" value="<?= (int)$sessionCourante['id'] ?>">
                <input type="hidden" name="return_to" value="compte">
                <div class="modal-header px-4 py-3">
                    <div><div class="small fw-bold text-success text-uppercase mb-1">Visa comptable</div><h5 class="modal-title fw-bold mb-1" id="modalControleCaisseLabel">Valider le contrôle</h5><div class="small text-muted"><?= e($compteActuel['nom'] ?? 'Compte de caisse') ?> · <?= formatDate($dateSelectionnee) ?></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="cash-close-summary p-3 mb-3">
                        <div class="d-flex justify-content-between gap-3 mb-2"><span class="text-muted small">Solde théorique</span><strong><?= formatMontant((float)$sessionCourante['theoretical_balance'], $deviseMouvements) ?></strong></div>
                        <div class="d-flex justify-content-between gap-3"><span class="text-muted small">Écart constaté</span><strong class="<?= abs((float)($sessionCourante['variance_amount'] ?? 0)) > .001 ? 'text-danger' : 'text-success' ?>"><?= formatMontant((float)($sessionCourante['variance_amount'] ?? 0), $deviseMouvements) ?></strong></div>
                    </div>
                    <label for="cashControlComment" class="form-label fw-semibold">Observation du contrôleur</label>
                    <textarea name="commentaire" id="cashControlComment" class="form-control" rows="3" placeholder="Conclusion du contrôle ou réserve éventuelle"></textarea>
                    <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-info-circle text-primary me-1"></i>Votre validation sera horodatée et intégrée à la piste d’audit.</div>
                </div>
                <div class="modal-footer px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Revenir</button>
                    <button type="submit" class="btn btn-success px-4"><i class="bi bi-check2-circle me-1"></i>Valider le contrôle</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalApercuExcelCfo" tabindex="-1" aria-labelledby="modalApercuExcelCfoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 px-4 pt-4 pb-2">
                <div class="d-flex align-items-center gap-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width:46px;height:46px"><i class="bi bi-file-earmark-spreadsheet fs-4"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="modalApercuExcelCfoLabel">Classeur de pilotage CFO</h5>
                        <div class="text-muted small"><?= e($compteActuel['nom'] ?? 'Compte de caisse') ?> · <?= formatDate($filtres['date_debut']) ?><?= $filtres['date_fin'] !== $filtres['date_debut'] ? ' au ' . formatDate($filtres['date_fin']) : '' ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body px-4 py-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><div class="border rounded-3 p-3 h-100"><div class="text-muted small mb-1">Entrées</div><div class="fw-bold text-success fs-5"><?= formatMontant($cashIn, $deviseMouvements) ?></div></div></div>
                    <div class="col-md-4"><div class="border rounded-3 p-3 h-100"><div class="text-muted small mb-1">Sorties</div><div class="fw-bold text-danger fs-5"><?= formatMontant($cashOut, $deviseMouvements) ?></div></div></div>
                    <div class="col-md-4"><div class="border rounded-3 p-3 h-100 bg-primary-subtle"><div class="text-muted small mb-1">Solde théorique</div><div class="fw-bold text-primary fs-5"><?= formatMontant((float)($sessionCourante['theoretical_balance'] ?? $soldeCourantMouvements), $deviseMouvements) ?></div></div></div>
                </div>
                <div class="small text-uppercase fw-bold text-muted mb-2">Contenu du classeur</div>
                <div class="list-group list-group-flush border rounded-3">
                    <div class="list-group-item d-flex align-items-center gap-3 py-3"><i class="bi bi-speedometer2 text-primary fs-5"></i><div><strong>Synthèse CFO</strong><div class="small text-muted">KPIs, cadre de gouvernance et graphique des flux.</div></div></div>
                    <div class="list-group-item d-flex align-items-center gap-3 py-3"><i class="bi bi-journal-text text-success fs-5"></i><div><strong>Journal de caisse</strong><div class="small text-muted">Mouvements filtrables avec solde progressif et formules auditables.</div></div></div>
                    <div class="list-group-item d-flex align-items-center gap-3 py-3"><i class="bi bi-shield-check text-warning fs-5"></i><div><strong>Contrôles</strong><div class="small text-muted">Équation de caisse, écart physique, plafond et visas de contrôle.</div></div></div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <span class="text-muted small me-auto">Format Excel natif `.xlsx` avec filtres, formules et graphique.</span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <a class="btn btn-success px-4" href="<?= e($livreExcelUrl) ?>"><i class="bi bi-download me-1"></i>Télécharger le classeur</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalApercuLivreCaisse" tabindex="-1" aria-labelledby="modalApercuLivreCaisseLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:min(96vw,1500px)">
        <div class="modal-content border-0 shadow-lg" style="height:92vh">
            <div class="modal-header px-4">
                <div>
                    <h5 class="modal-title fw-bold" id="modalApercuLivreCaisseLabel"><i class="bi bi-journal-richtext text-primary me-2"></i>Livre de caisse officiel</h5>
                    <div class="text-muted small"><?= e($compteActuel['nom'] ?? 'Compte de caisse') ?> · <?= formatDate($filtres['date_debut']) ?><?= $filtres['date_fin'] !== $filtres['date_debut'] ? ' au ' . formatDate($filtres['date_fin']) : '' ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body position-relative p-0 bg-light">
                <div id="cashBookPdfLoader" class="position-absolute top-50 start-50 translate-middle text-center">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Préparation du livre de caisse…</div>
                </div>
                <iframe id="cashBookPdfFrame" title="Prévisualisation du livre de caisse" class="w-100 h-100 border-0" data-src="<?= e($livrePdfUrl) ?>#toolbar=1&navpanes=0&view=FitH"></iframe>
            </div>
            <div class="modal-footer px-4">
                <span class="text-muted small me-auto"><i class="bi bi-shield-check me-1"></i>Document officiel généré depuis les écritures validées.</span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                <a class="btn btn-primary" href="<?= e($livrePdfUrl) ?>&amp;download=1"><i class="bi bi-download me-1"></i>Télécharger le PDF</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    var dateField = document.getElementById('cashDateField');
    var startField = document.getElementById('cashDateStartField');
    var actionDateFields = document.querySelectorAll('input[name="session_date"]');
    var previewModal = document.getElementById('modalApercuLivreCaisse');
    var previewFrame = document.getElementById('cashBookPdfFrame');
    var previewLoader = document.getElementById('cashBookPdfLoader');
    var closureForm = document.getElementById('cashClosureForm');
    var physicalField = document.getElementById('cashPhysicalBalance');
    var varianceBox = document.getElementById('cashVarianceBox');
    var varianceValue = document.getElementById('cashVarianceValue');
    var varianceHelp = document.getElementById('cashVarianceHelp');
    var closureComment = document.getElementById('cashClosureComment');
    var controlForm = document.getElementById('cashControlForm');
    var closureCurrency = <?= json_encode((string)$deviseMouvements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    var closureApproved = false;
    var controlApproved = false;

    function syncCashDate() {
        if (!dateField) return;
        if (startField) startField.value = dateField.value;
        actionDateFields.forEach(function (field) {
            field.value = dateField.value;
        });
    }

    if (dateField) {
        dateField.addEventListener('change', syncCashDate);
        syncCashDate();
    }

    if (previewModal && previewFrame) {
        previewModal.addEventListener('shown.bs.modal', function () {
            if (!previewFrame.getAttribute('src')) {
                previewFrame.setAttribute('src', previewFrame.getAttribute('data-src'));
            }
        });
        previewFrame.addEventListener('load', function () {
            if (previewLoader) previewLoader.classList.add('d-none');
        });
    }

    function updateClosureVariance() {
        if (!physicalField) return 0;

        var theoretical = parseFloat(physicalField.getAttribute('data-theoretical')) || 0;
        var physical = parseFloat(physicalField.value);
        var variance = Number.isFinite(physical) ? physical - theoretical : 0;
        var hasVariance = Number.isFinite(physical) && Math.abs(variance) > 0.001;
        var formattedVariance = new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(variance);

        if (varianceValue) varianceValue.textContent = formattedVariance + ' ' + closureCurrency;
        if (varianceBox) varianceBox.classList.toggle('has-variance', hasVariance);
        if (varianceHelp) {
            varianceHelp.textContent = hasVariance
                ? (variance > 0 ? 'Excédent constaté : une justification est requise.' : 'Manquant constaté : une justification est requise.')
                : 'Comptage conforme au solde théorique.';
        }
        if (closureComment) {
            closureComment.required = hasVariance;
            if (!hasVariance) closureComment.classList.remove('is-invalid');
        }
        return variance;
    }

    if (physicalField) {
        physicalField.addEventListener('input', updateClosureVariance);
        updateClosureVariance();
    }

    if (closureComment) {
        closureComment.addEventListener('input', function () {
            if (closureComment.value.trim()) closureComment.classList.remove('is-invalid');
        });
    }

    if (closureForm) {
        closureForm.addEventListener('submit', function (event) {
            if (closureApproved) return;
            event.preventDefault();
            var variance = updateClosureVariance();
            if (Math.abs(variance) > 0.001 && closureComment && !closureComment.value.trim()) {
                event.stopPropagation();
                closureComment.classList.add('is-invalid');
                closureComment.focus();
                return;
            }
            if (!closureForm.checkValidity()) {
                closureForm.reportValidity();
                return;
            }
            Swal.fire({
                icon: 'warning',
                title: 'Confirmer la clôture',
                text: 'Le comptage sera figé et la journée transmise au contrôle comptable.',
                showCancelButton: true,
                confirmButtonText: 'Oui, clôturer',
                cancelButtonText: 'Vérifier encore',
                confirmButtonColor: '#b7791f',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                customClass: {popup: 'rounded-3'}
            }).then(function (result) {
                if (!result.isConfirmed) return;
                closureApproved = true;
                closureForm.submit();
            });
        });
    }

    if (controlForm) {
        controlForm.addEventListener('submit', function (event) {
            if (controlApproved) return;
            event.preventDefault();
            Swal.fire({
                icon: 'question',
                title: 'Confirmer le visa comptable',
                text: 'Cette validation confirme que la journée et son rapprochement ont été examinés.',
                showCancelButton: true,
                confirmButtonText: 'Valider le contrôle',
                cancelButtonText: 'Revenir',
                confirmButtonColor: '#16834b',
                cancelButtonColor: '#64748b',
                reverseButtons: true,
                customClass: {popup: 'rounded-3'}
            }).then(function (result) {
                if (!result.isConfirmed) return;
                controlApproved = true;
                controlForm.submit();
            });
        });
    }

    <?php if (!empty($cashSwal)): ?>
    Swal.fire({
        icon: <?= json_encode($cashSwal['icon'] ?? 'success') ?>,
        title: <?= json_encode($cashSwal['title'] ?? 'Opération terminée', JSON_UNESCAPED_UNICODE) ?>,
        text: <?= json_encode($cashSwal['text'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        confirmButtonText: 'Continuer',
        confirmButtonColor: '#1769e0',
        timer: <?= ($cashSwal['icon'] ?? '') === 'success' ? '4300' : 'null' ?>,
        timerProgressBar: true,
        customClass: {popup: 'rounded-3'}
    });
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
