<?php $pageTitle = 'Ordre de paiement - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>
<?php
$ordreSwal = $_SESSION['ordre_swal'] ?? null;
unset($_SESSION['ordre_swal']);
$hasOrder = !empty($ordre);
$ordreUi = $ordreUi ?? [
    'peut_emettre' => false,
    'emission_blocages' => [],
    'peut_executer' => false,
    'execution_blocages' => [],
];
$statusOrdre = $hasOrder ? (string)$ordre['status'] : 'a_emettre';
$isCashPayment = $hasOrder && (
    ($ordre['payment_method'] ?? '') === 'especes'
    || ($ordre['account_type'] ?? '') === 'caisse'
);
$externalProofRequired = $hasOrder && !$isCashPayment;
$isOrderIssuer = $hasOrder
    && (int)($ordre['issued_by'] ?? 0) === (int)($_SESSION['user_id'] ?? 0)
    && (int)($_SESSION['user_id'] ?? 0) > 0;
$montant = $hasOrder ? (float)$ordre['amount'] : (float)$demande['amount'];
$devise = $hasOrder ? (string)$ordre['currency'] : (string)$demande['currency'];
$workflowSteps = [
    'validee_direction' => ['label' => 'Demande validée', 'icon' => 'bi-shield-check'],
    'emis' => ['label' => 'Ordre émis', 'icon' => 'bi-file-earmark-check'],
    'execute' => ['label' => 'Paiement exécuté', 'icon' => 'bi-cash-coin'],
    'payee' => ['label' => 'Demande payée', 'icon' => 'bi-check2-circle'],
];
$workflowDone = [
    'validee_direction' => in_array($demande['status'], ['validee_direction','ordre_emis','payee'], true),
    'emis' => $hasOrder && in_array($ordre['status'], ['emis','execute'], true),
    'execute' => $hasOrder && $ordre['status'] === 'execute',
    'payee' => $demande['status'] === 'payee',
];
?>

<style>
.order-summary {
    background: linear-gradient(135deg, #f8fafc 0%, #eef4ff 100%);
    border: 1px solid rgba(13, 110, 253, .12);
    border-radius: 10px;
}
.order-amount {
    font-size: clamp(1.45rem, 2.4vw, 2.15rem);
    line-height: 1.05;
}
.order-meta-tile {
    min-height: 86px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #fff;
}
.order-workflow {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .75rem;
}
.order-workflow-step {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #fff;
    padding: .85rem;
    min-height: 96px;
}
.order-workflow-step.is-done {
    border-color: rgba(25, 135, 84, .35);
    background: #f1fbf6;
}
.order-workflow-step.is-current {
    border-color: rgba(13, 110, 253, .35);
    background: #f3f7ff;
}
.order-print-title {
    display: none;
}
.order-official-document { display: none; }
.order-confirm-modal .modal-content {
    overflow: hidden;
    border: 0;
    border-radius: .9rem;
    box-shadow: 0 24px 70px rgba(12, 35, 61, .2);
}
.order-confirm-modal .modal-header {
    padding: 1.2rem 1.35rem;
    border-bottom: 1px solid #e7edf3;
    background: #f8fafc;
}
.order-confirm-icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    border-radius: .7rem;
    font-size: 1.1rem;
}
.order-confirm-summary {
    padding: .9rem 1rem;
    border: 1px solid #e1e8f0;
    border-radius: .7rem;
    background: #f8fafc;
}
.order-balance-panel {
    display: none;
    border: 1px solid #dfe6ee;
    border-radius: .7rem;
    background: #fafbfd;
}
.order-balance-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.order-balance-item { padding: .9rem 1rem; }
.order-balance-item + .order-balance-item { border-left: 1px solid #e5ebf1; }
.order-balance-label { color: #718096; font-size: .65rem; font-weight: 750; text-transform: uppercase; letter-spacing: .04em; }
.order-balance-value { margin-top: .25rem; color: #20364e; font-size: .9rem; font-weight: 800; }
.order-balance-status { padding: .7rem 1rem; border-top: 1px solid #e5ebf1; font-size: .72rem; }
@media (max-width:575.98px) {
    .order-balance-grid { grid-template-columns: 1fr; }
    .order-balance-item + .order-balance-item { border-left: 0; border-top: 1px solid #e5ebf1; }
}
@media (max-width: 767.98px) {
    .order-workflow {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .order-action-bar .btn {
        width: 100%;
    }
}
@media print {
    @page { size: A4 portrait; margin: 11mm; }
    * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }
    .app-topbar, .app-sidebar, .app-sidebar-offcanvas, .app-footer,
    .page-hero, .order-summary, .order-workflow, .row.g-4,
    .no-print, .breadcrumb, .alert, .form-card, .order-print-title {
        display: none !important;
    }
    html, body { width: 210mm; min-height: 297mm; background: #fff !important; }
    .app-layout { display: block !important; margin: 0 !important; }
    .app-main { min-height: auto !important; padding: 0 !important; }
    .container-fluid { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
    .order-official-document {
        position: relative;
        display: block !important;
        min-height: 270mm;
        padding: 14mm 13mm 11mm;
        overflow: hidden;
        color: #17283e;
        border: 1px solid #cfd9e5;
        font-family: Arial, sans-serif;
        font-size: 9pt;
        background: #fff;
    }
    .op-stripe { position:absolute; inset:0 0 auto; height:6px; background:linear-gradient(90deg,#0b2847 0 34%,#1769e0 34% 78%,#1a9a5b 78%); }
    .op-header { display:flex; justify-content:space-between; align-items:flex-start; gap:24px; padding-top:3mm; }
    .op-logo { display:block; width:61mm; height:auto; }
    .op-brand-meta { margin-top:5px; color:#65758a; font-size:7.5pt; line-height:1.45; }
    .op-doc-meta { text-align:right; }
    .op-doc-type { color:#1769e0; font-size:7.5pt; font-weight:800; letter-spacing:1.2px; text-transform:uppercase; }
    .op-doc-title { margin:4px 0 2px; color:#10243b; font-size:18pt; font-weight:800; letter-spacing:-.4px; }
    .op-doc-number { color:#4f6277; font-size:9pt; font-weight:700; }
    .op-status { display:inline-block; margin-top:7px; padding:4px 9px; border:1px solid #bdd4ec; color:#12558f; background:#edf6ff; font-size:7pt; font-weight:800; text-transform:uppercase; }
    .op-rule { height:1px; margin:13px 0; background:#dfe6ef; }
    .op-section-title { margin:0 0 7px; color:#64758a; font-size:7pt; font-weight:800; letter-spacing:1px; text-transform:uppercase; }
    .op-amount-panel { display:grid; grid-template-columns:1.35fr 1fr 1fr; margin-bottom:14px; overflow:hidden; border:1px solid #cbd9e9; border-radius:7px; background:#f8fbff; }
    .op-amount-main { padding:15px; color:#fff; background:#0b3c69; }
    .op-panel-cell { padding:15px; border-right:1px solid #dfe6ef; }
    .op-panel-cell:last-child { border-right:0; }
    .op-label { margin-bottom:5px; color:#718096; font-size:6.5pt; font-weight:800; letter-spacing:.45px; text-transform:uppercase; }
    .op-amount-main .op-label { color:#b9d1e8; }
    .op-value { color:#253950; font-size:9pt; font-weight:700; line-height:1.35; }
    .op-amount-main .op-value { color:#fff; font-size:17pt; font-weight:800; white-space:nowrap; }
    .op-info-table { width:100%; margin-bottom:14px; border-collapse:collapse; table-layout:fixed; }
    .op-info-table td { padding:10px 11px; border:1px solid #dfe6ef; vertical-align:top; }
    .op-object { margin-bottom:14px; padding:11px 13px; border-left:3px solid #1769e0; background:#f7f9fc; line-height:1.5; }
    .op-instructions { min-height:42px; margin-bottom:14px; padding:10px 12px; border:1px solid #e1e7ee; border-radius:5px; color:#44566c; background:#fafbfd; line-height:1.5; }
    .op-trace { width:100%; border-collapse:collapse; }
    .op-trace th,.op-trace td { padding:7px 0; border-bottom:1px solid #edf1f5; text-align:left; }
    .op-trace th { width:34%; color:#718096; font-size:6.5pt; font-weight:800; letter-spacing:.4px; text-transform:uppercase; }
    .op-trace td { color:#253950; font-weight:650; }
    .op-signatures { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; margin-top:26mm; page-break-inside:avoid; }
    .op-signature { padding-top:7px; border-top:1px solid #7d8da0; color:#586b80; font-size:7.5pt; text-align:center; }
    .op-footer { position:absolute; right:13mm; bottom:8mm; left:13mm; display:flex; justify-content:space-between; padding-top:7px; border-top:1px solid #dfe6ef; color:#8290a0; font-size:6.5pt; }
}
</style>

<?php if (!empty($ordreSwal) || !empty($erreurs)): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: <?= json_encode(!empty($ordreSwal) ? ($ordreSwal['icon'] ?? 'success') : 'error') ?>,
        title: <?= json_encode(!empty($ordreSwal) ? ($ordreSwal['title'] ?? 'Opération terminée') : 'Action impossible', JSON_UNESCAPED_UNICODE) ?>,
        html: <?= json_encode(!empty($ordreSwal)
            ? e($ordreSwal['text'] ?? '')
            : implode('<br>', array_map('e', $erreurs)), JSON_UNESCAPED_UNICODE) ?>,
        confirmButtonText: 'Continuer',
        confirmButtonColor: '#1769e0',
        timer: <?= !empty($ordreSwal) && ($ordreSwal['icon'] ?? '') === 'success' ? '4300' : 'null' ?>,
        timerProgressBar: true,
        customClass: {popup: 'rounded-3'}
    });
});
</script>
<?php endif; ?>

<div class="container-fluid">
    <div class="page-hero no-print">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-0">
            <div class="d-flex align-items-center">
                <a href="<?= APP_URL ?>/treasury/demande-voir/<?= (int)$demande['id'] ?>" class="btn btn-outline-secondary btn-sm me-3" title="Retour demande">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="fw-bold mb-1">
                        <i class="bi bi-file-earmark-check me-2 text-primary"></i>
                        <?= $hasOrder ? e($ordre['order_number']) : 'Émettre un ordre' ?>
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/dashboard">Trésorerie</a></li>
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/demandes">Demandes</a></li>
                            <li class="breadcrumb-item active">Ordre de paiement</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-self-stretch align-self-lg-center order-action-bar">
                <?php if ($hasOrder): ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Imprimer / PDF
                    </button>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/treasury/demande-voir/<?= (int)$demande['id'] ?>" class="btn btn-outline-primary">
                    <i class="bi bi-card-checklist me-1"></i>Demande source
                </a>
                <a href="<?= APP_URL ?>/treasury/ordres" class="btn btn-primary">
                    <i class="bi bi-list-check me-1"></i>Tous les ordres
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($ordreSwal)): ?>
        <?php afficherFlash(); ?>
    <?php else: ?>
        <?php
        // Le SweetAlert porte déjà le résultat de l'action : on efface le flash
        // équivalent pour éviter deux confirmations concurrentes.
        unset($_SESSION['flash_succes'], $_SESSION['flash_erreur'], $_SESSION['flash_info'], $_SESSION['flash_avertissement']);
        ?>
    <?php endif; ?>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger no-print">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Action impossible</div>
            <ul class="mb-0">
                <?php foreach ($erreurs as $erreur): ?>
                    <li><?= e($erreur) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($hasOrder): ?>
        <article class="order-official-document" aria-label="Ordre de paiement officiel">
            <div class="op-stripe"></div>
            <header class="op-header">
                <div>
                    <img src="<?= APP_URL ?>/assets/images/logo/logo_amm.jpg" alt="AMM" class="op-logo">
                    <div class="op-brand-meta">Vente et fourniture d’énergie électrique<br>Lubumbashi, République Démocratique du Congo</div>
                </div>
                <div class="op-doc-meta">
                    <div class="op-doc-type">Trésorerie · Décaissement autorisé</div>
                    <div class="op-doc-title">ORDRE DE PAIEMENT</div>
                    <div class="op-doc-number"><?= e($ordre['order_number']) ?></div>
                    <span class="op-status"><?= e(treasuryUiLabel($ordre['status'])) ?></span>
                </div>
            </header>

            <div class="op-rule"></div>
            <div class="op-section-title">Instruction de paiement</div>
            <div class="op-amount-panel">
                <div class="op-amount-main">
                    <div class="op-label">Montant ordonnancé</div>
                    <div class="op-value"><?= formatMontant((float)$ordre['amount'], $ordre['currency']) ?></div>
                </div>
                <div class="op-panel-cell">
                    <div class="op-label">Bénéficiaire</div>
                    <div class="op-value"><?= e($ordre['beneficiary']) ?></div>
                </div>
                <div class="op-panel-cell">
                    <div class="op-label">Date de l’ordre</div>
                    <div class="op-value"><?= formatDate($ordre['order_date']) ?></div>
                </div>
            </div>

            <div class="op-section-title">Coordonnées du règlement</div>
            <table class="op-info-table">
                <tr>
                    <td><div class="op-label">Mode de paiement</div><div class="op-value"><?= e(treasuryUiLabel($ordre['payment_method'])) ?></div></td>
                    <td><div class="op-label">Compte à débiter</div><div class="op-value"><?= e($ordre['account_nom'] ?? '—') ?></div></td>
                    <td><div class="op-label">Référence de paiement</div><div class="op-value"><?= e($ordre['payment_reference'] ?: '—') ?></div></td>
                </tr>
            </table>

            <div class="op-section-title">Objet et fondement</div>
            <div class="op-object">
                <strong><?= e($demande['objet']) ?></strong><br>
                <span style="color:#65758a">Demande source <?= e($demande['request_number']) ?> · <?= e($demande['category_nom'] ?? 'Catégorie non renseignée') ?></span>
            </div>

            <div class="op-section-title">Instructions au trésorier</div>
            <div class="op-instructions"><?= !empty($ordre['instructions']) ? nl2br(e($ordre['instructions'])) : 'Exécuter le paiement conformément aux coordonnées et contrôles repris sur le présent ordre.' ?></div>

            <div class="op-section-title">Traçabilité et contrôle interne</div>
            <table class="op-trace">
                <tr><th>Ordre émis par</th><td><?= e($ordre['issued_by_nom'] ?? '—') ?></td></tr>
                <tr><th>Date d’émission</th><td><?= !empty($ordre['issued_at']) ? e(date('d/m/Y à H:i', strtotime($ordre['issued_at']))) : '—' ?></td></tr>
                <tr><th>Statut de la demande</th><td><?= e(treasuryUiLabel($demande['status'])) ?></td></tr>
                <?php if (($ordre['status'] ?? '') === 'execute'): ?>
                    <tr><th>Exécuté par</th><td><?= e($ordre['executed_by_nom'] ?? '—') ?></td></tr>
                    <tr><th>Date du paiement</th><td><?= !empty($ordre['payment_date']) ? formatDate($ordre['payment_date']) : '—' ?></td></tr>
                    <tr><th>Transaction financière</th><td><?= !empty($ordre['financial_transaction_id']) ? '#' . (int)$ordre['financial_transaction_id'] : '—' ?></td></tr>
                <?php endif; ?>
            </table>

            <div class="op-signatures">
                <div class="op-signature">Établi par · Direction financière</div>
                <div class="op-signature">Exécuté par · Trésorerie</div>
                <div class="op-signature">Acquit · Bénéficiaire</div>
            </div>

            <footer class="op-footer">
                <span>AMM-ERP · Ordre de paiement officiel · Document de contrôle interne</span>
                <span>Imprimé le <?= date('d/m/Y à H:i') ?> par <?= e($_SESSION['user_nom'] ?? 'Utilisateur autorisé') ?></span>
            </footer>
        </article>
    <?php endif; ?>

    <div class="order-print-title">
        <h3 class="fw-bold mb-1">ORDRE DE PAIEMENT</h3>
        <div><?= e(APP_NAME) ?></div>
    </div>

    <div class="order-summary p-4 mb-4 printable-order">
        <div class="row g-3 align-items-center">
            <div class="col-lg-5">
                <div class="text-muted small text-uppercase fw-semibold mb-1">Montant à payer</div>
                <div class="order-amount fw-bold"><?= formatMontant($montant, $devise) ?></div>
                <div class="mt-2">
                    <span class="badge bg-<?= treasuryUiStatusBadge($hasOrder ? $ordre['status'] : $demande['status']) ?>">
                        <?= e($hasOrder ? treasuryUiLabel($ordre['status']) : 'En attente émission') ?>
                    </span>
                    <span class="badge bg-<?= treasuryUiStatusBadge($demande['status']) ?> ms-1">
                        Demande <?= e(treasuryUiLabel($demande['status'])) ?>
                    </span>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="order-meta-tile p-3">
                            <div class="text-muted small">Bénéficiaire</div>
                            <div class="fw-semibold text-truncate" title="<?= e($demande['beneficiary']) ?>"><?= e($demande['beneficiary']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="order-meta-tile p-3">
                            <div class="text-muted small">Demande</div>
                            <div class="fw-semibold"><?= e($demande['request_number']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="order-meta-tile p-3">
                            <div class="text-muted small">Compte</div>
                            <div class="fw-semibold text-truncate"><?= e($ordre['account_nom'] ?? $demande['preferred_account_nom'] ?? 'À sélectionner') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="order-workflow mb-4 no-print">
        <?php foreach ($workflowSteps as $step => $meta): ?>
            <?php
            $done = !empty($workflowDone[$step]);
            $current = (!$done && (
                ($step === 'validee_direction' && !$workflowDone['validee_direction'])
                || ($step === 'emis' && $workflowDone['validee_direction'] && !$workflowDone['emis'])
                || ($step === 'execute' && $workflowDone['emis'] && !$workflowDone['execute'])
                || ($step === 'payee' && $workflowDone['execute'] && !$workflowDone['payee'])
            ));
            ?>
            <div class="order-workflow-step <?= $done ? 'is-done' : '' ?> <?= $current ? 'is-current' : '' ?>">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center <?= $done ? 'bg-success text-white' : ($current ? 'bg-primary text-white' : 'bg-light text-muted') ?>" style="width: 34px; height: 34px;">
                        <i class="bi <?= e($done ? 'bi-check2' : $meta['icon']) ?>"></i>
                    </span>
                    <div class="fw-semibold small"><?= e($meta['label']) ?></div>
                </div>
                <div class="small text-muted"><?= $done ? 'Terminé' : ($current ? 'Étape active' : 'En attente') ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card section-card mb-4">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-card-checklist me-2"></i>Demande source
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Numéro</dt>
                        <dd class="col-7 fw-semibold"><?= e($demande['request_number']) ?></dd>
                        <dt class="col-5 text-muted">Bénéficiaire</dt>
                        <dd class="col-7 fw-semibold"><?= e($demande['beneficiary']) ?></dd>
                        <dt class="col-5 text-muted">Objet</dt>
                        <dd class="col-7"><?= e($demande['objet']) ?></dd>
                        <dt class="col-5 text-muted">Montant</dt>
                        <dd class="col-7 fw-semibold"><?= formatMontant((float)$demande['amount'], $demande['currency']) ?></dd>
                        <dt class="col-5 text-muted">Statut</dt>
                        <dd class="col-7">
                            <span class="badge bg-<?= treasuryUiStatusBadge($demande['status']) ?>"><?= e(treasuryUiLabel($demande['status'])) ?></span>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card section-card order-controls no-print">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-signpost-split me-2"></i><?= $hasOrder ? 'Étape suivante' : 'Disponibilité de l’émission' ?>
                </div>
                <div class="card-body">
                    <?php if (!$hasOrder): ?>
                        <?php if ($ordreUi['peut_emettre']): ?>
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check2-circle me-1"></i>Cette demande est prête pour émission.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <div class="fw-semibold mb-1"><i class="bi bi-lock me-1"></i>Émission bloquée</div>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($ordreUi['emission_blocages'] as $blocage): ?>
                                        <li><?= e($blocage) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php elseif (($ordre['status'] ?? '') === 'execute'): ?>
                        <div class="small text-success">
                            <i class="bi bi-check2-circle me-1"></i>
                            Paiement exécuté. Le cycle de cet ordre est terminé.
                        </div>
                    <?php elseif ($ordreUi['peut_executer']): ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check2-circle me-1"></i>Le paiement peut être exécuté par votre profil.
                        </div>
                    <?php elseif (($ordre['status'] ?? '') === 'emis' && $isOrderIssuer): ?>
                        <div class="small">
                            <div class="fw-semibold text-body mb-1"><i class="bi bi-arrow-right-circle me-1 text-primary"></i>Prochaine étape : exécution du paiement</div>
                            <div class="text-muted">L’ordre a été transmis. Conformément à la séparation des tâches, le paiement doit être exécuté par un autre utilisateur habilité.</div>
                        </div>
                    <?php elseif (($ordre['status'] ?? '') === 'emis'): ?>
                        <div class="small">
                            <div class="fw-semibold text-body mb-1"><i class="bi bi-clock me-1 text-primary"></i>En attente d’exécution</div>
                            <div class="text-muted">Un utilisateur disposant du profil de paiement doit maintenant prendre en charge cet ordre.</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary mb-0">
                            <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Exécution non disponible</div>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($ordreUi['execution_blocages'] as $blocage): ?>
                                    <li><?= e($blocage) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <?php if (!$hasOrder): ?>
                <div class="card form-card">
                    <div class="card-header bg-transparent border-0 fw-semibold">
                        <i class="bi bi-pencil-square me-2"></i>Émettre l’ordre de paiement
                    </div>
                    <div class="card-body">
                        <?php if (!$ordreUi['peut_emettre']): ?>
                            <?php treasuryUiEmptyState('bi-shield-lock', 'Ordre non émissible', 'Consultez les contrôles métier à gauche pour connaître la prochaine action.'); ?>
                        <?php else: ?>
                            <form method="POST" action="<?= APP_URL ?>/treasury/ordre-paiement/<?= (int)$demande['id'] ?>" id="issueOrderForm">
                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Date ordre</label>
                                        <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Mode paiement</label>
                                        <select name="payment_method" class="form-select" id="issuePaymentMethod" required>
                                            <option value="">Sélectionner</option>
                                            <option value="especes">Espèces</option>
                                            <option value="banque">Banque</option>
                                            <option value="mobile_money">Mobile money</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Compte</label>
                                        <select name="account_id" class="form-select" id="issueAccount" required>
                                            <option value="">Sélectionner</option>
                                            <?php foreach ($comptes as $compte): ?>
                                                <option value="<?= (int)$compte['id'] ?>"
                                                        data-currency="<?= e($compte['devise']) ?>"
                                                        data-account-type="<?= e($compte['type_compte']) ?>"
                                                        data-balance="<?= e((string)(float)$compte['solde_disponible']) ?>"
                                                        data-required="<?= e((string)(float)$compte['montant_requis']) ?>"
                                                        data-after="<?= e((string)(float)$compte['solde_apres']) ?>"
                                                        data-covered="<?= !empty($compte['couverture_suffisante']) ? '1' : '0' ?>"
                                                        <?= (int)($demande['preferred_account_id'] ?? 0) === (int)$compte['id'] ? 'selected' : '' ?>>
                                                    <?= e($compte['nom']) ?> · <?= formatMontant((float)$compte['solde_disponible'], $compte['devise']) ?> disponible
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Référence paiement</label>
                                        <input type="text" name="payment_reference" class="form-control" placeholder="Chèque, virement, retrait caisse...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Instructions au trésorier</label>
                                        <input type="text" name="instructions" class="form-control" placeholder="Instruction courte et exploitable">
                                    </div>
                                </div>
                                <div class="order-balance-panel mt-3" id="issueBalancePanel">
                                    <div class="order-balance-grid">
                                        <div class="order-balance-item"><div class="order-balance-label">Solde disponible</div><div class="order-balance-value" id="issueBalanceAvailable">—</div></div>
                                        <div class="order-balance-item"><div class="order-balance-label">Montant à couvrir</div><div class="order-balance-value" id="issueBalanceRequired">—</div></div>
                                        <div class="order-balance-item"><div class="order-balance-label">Solde projeté</div><div class="order-balance-value" id="issueBalanceAfter">—</div></div>
                                    </div>
                                    <div class="order-balance-status" id="issueBalanceStatus"></div>
                                </div>
                                <div class="alert alert-info mt-4 mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    L’émission verrouille cette demande au statut ordre émis. Le paiement sera exécuté séparément par un profil autorisé.
                                </div>
                                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                                    <a href="<?= APP_URL ?>/treasury/demande-voir/<?= (int)$demande['id'] ?>" class="btn btn-outline-secondary">Annuler</a>
                                    <button type="submit" class="btn btn-primary" id="issueOrderSubmitButton">
                                        <i class="bi bi-file-earmark-check me-1"></i>Émettre l’ordre
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card section-card mb-4 printable-order">
                    <div class="card-header bg-transparent border-0 fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><i class="bi bi-info-circle me-2"></i>Détails de l’ordre</span>
                        <span class="badge bg-<?= treasuryUiStatusBadge($ordre['status']) ?>"><?= e(treasuryUiLabel($ordre['status'])) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">Numéro ordre</div>
                                <div class="fw-semibold"><?= e($ordre['order_number']) ?></div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small">Date ordre</div>
                                <div class="fw-semibold"><?= formatDate($ordre['order_date']) ?></div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small">Mode</div>
                                <div class="fw-semibold"><?= e($isCashPayment ? 'Espèces' : treasuryUiLabel($ordre['payment_method'])) ?></div>
                                <?php if ($isCashPayment && ($ordre['payment_method'] ?? '') !== 'especes'): ?><div class="small text-muted">Déduit du compte de caisse sélectionné</div><?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Bénéficiaire</div>
                                <div class="fw-semibold"><?= e($ordre['beneficiary']) ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Compte de paiement</div>
                                <div class="fw-semibold"><?= e($ordre['account_nom'] ?? '—') ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Montant</div>
                                <div class="h5 fw-bold mb-0"><?= formatMontant((float)$ordre['amount'], $ordre['currency']) ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Référence ordre</div>
                                <div class="fw-semibold"><?= e($ordre['payment_reference'] ?: '—') ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Émis par</div>
                                <div class="fw-semibold"><?= e($ordre['issued_by_nom'] ?? '—') ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small">Date émission</div>
                                <div class="fw-semibold"><?= !empty($ordre['issued_at']) ? formatDate($ordre['issued_at']) : '—' ?></div>
                            </div>
                            <?php if (!empty($ordre['instructions'])): ?>
                                <div class="col-12">
                                    <div class="text-muted small">Instructions</div>
                                    <div><?= nl2br(e($ordre['instructions'])) ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="col-12 d-none d-print-block mt-4">
                                <div class="row g-5 pt-5">
                                    <div class="col-4"><div class="border-top pt-2 small text-muted">Direction financière</div></div>
                                    <div class="col-4"><div class="border-top pt-2 small text-muted">Trésorerie</div></div>
                                    <div class="col-4"><div class="border-top pt-2 small text-muted">Bénéficiaire</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($ordre['status'] === 'emis'): ?>
                    <div class="card form-card no-print">
                        <div class="card-header bg-transparent border-0 fw-semibold">
                            <i class="bi bi-cash-coin me-2 text-success"></i>Exécuter le paiement
                        </div>
                        <div class="card-body">
                            <?php if (!$ordreUi['peut_executer']): ?>
                                <div class="p-2 p-md-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="order-confirm-icon bg-primary-subtle text-primary">
                                            <i class="bi bi-person-check"></i>
                                        </span>
                                        <div>
                                            <h3 class="h6 fw-bold mb-1">Paiement à prendre en charge par un autre utilisateur</h3>
                                            <?php if ($isOrderIssuer): ?>
                                                <p class="small text-muted mb-3">
                                                    Vous avez émis cet ordre. Pour sécuriser le décaissement, vous ne pouvez pas également exécuter son paiement.
                                                </p>
                                            <?php else: ?>
                                                <p class="small text-muted mb-3">
                                                    Votre profil actuel n’est pas habilité à exécuter ce paiement.
                                                </p>
                                            <?php endif; ?>
                                            <div class="border rounded p-3 bg-light">
                                                <div class="small fw-semibold mb-2">Comment poursuivre ?</div>
                                                <ol class="small text-muted mb-0 ps-3">
                                                    <li class="mb-1">Un autre utilisateur habilité ouvre cet ordre depuis la liste des ordres.</li>
                                                    <li>Il renseigne la référence, joint la preuve externe si nécessaire, puis exécute le paiement.</li>
                                                </ol>
                                            </div>
                                            <div class="small text-muted mt-3">
                                                <i class="bi bi-shield-check me-1"></i>
                                                Profils habilités : Trésorier, administrateur ou utilisateur autorisé du service Trésorerie. L’exécutant doit être différent de l’émetteur.
                                            </div>
                                            <a href="<?= APP_URL ?>/treasury/ordres" class="btn btn-outline-primary btn-sm mt-3">
                                                <i class="bi bi-list-check me-1"></i>Retour à la liste des ordres
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <form method="POST" action="<?= APP_URL ?>/treasury/paiement-executer/<?= (int)$ordre['id'] ?>" enctype="multipart/form-data" id="executePaymentForm">
                                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Cette action créera une dépense validée, marquera la demande comme payée et empêchera un second paiement.
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Date paiement</label>
                                            <input type="date" name="payment_date" id="executePaymentDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Référence</label>
                                            <input type="text" name="reference_payment" class="form-control" placeholder="<?= $externalProofRequired ? 'Référence banque ou opérateur' : 'Générée automatiquement par AMM-ERP' ?>" <?= $externalProofRequired ? 'required' : '' ?>>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold"><?= $externalProofRequired ? 'Preuve externe' : 'Acquit signé' ?></label>
                                            <input type="file" name="proof_file" id="executeProofFile" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" <?= $externalProofRequired ? 'required' : '' ?>>
                                            <div class="form-text"><?= $externalProofRequired ? 'Obligatoire : avis de débit, confirmation bancaire ou reçu opérateur.' : 'Facultatif à l’exécution. Le bon de décaissement sera généré automatiquement.' ?></div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">Notes</label>
                                            <textarea name="notes" class="form-control" rows="2" placeholder="Observations utiles pour l’audit"></textarea>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check2-circle me-1"></i>Exécuter le paiement
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($ordre['status'] === 'execute'): ?>
                    <div class="card section-card">
                        <div class="card-header bg-transparent border-0 fw-semibold">
                            <i class="bi bi-check2-circle me-2 text-success"></i>Paiement exécuté
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="text-muted small">Date paiement</div>
                                    <div class="fw-semibold"><?= !empty($ordre['payment_date']) ? formatDate($ordre['payment_date']) : '—' ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Exécuté par</div>
                                    <div class="fw-semibold"><?= e($ordre['executed_by_nom'] ?? '—') ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Transaction financière</div>
                                    <div class="fw-semibold"><?= !empty($ordre['financial_transaction_id']) ? '#' . (int)$ordre['financial_transaction_id'] : '—' ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Référence paiement</div>
                                    <div class="fw-semibold"><?= e($ordre['executed_reference_payment'] ?: '—') ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Preuve externe / acquit</div>
                                    <?php if (!empty($ordre['payment_proof_file'])): ?>
                                        <a href="<?= UPLOAD_URL . e($ordre['payment_proof_file']) ?>" target="_blank" class="btn btn-outline-primary btn-sm no-print">
                                            <i class="bi bi-paperclip me-1"></i>Voir la preuve
                                        </a>
                                        <span class="d-none d-print-inline"><?= e($ordre['payment_proof_file']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <div class="border rounded p-3 bg-light d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                        <div>
                                            <div class="fw-semibold"><i class="bi bi-file-earmark-check me-1 text-success"></i><?= $isCashPayment ? 'Bon de décaissement généré' : 'Bordereau interne généré' ?></div>
                                            <div class="small text-muted">Document officiel AMM-ERP lié à ce paiement et à sa transaction financière.</div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="<?= APP_URL ?>/treasury/paiement-bordereau/<?= (int)$ordre['id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye me-1"></i>Aperçu</a>
                                            <a href="<?= APP_URL ?>/treasury/paiement-bordereau/<?= (int)$ordre['id'] ?>?download=1" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i>Télécharger</a>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($ordre['payment_notes'])): ?>
                                    <div class="col-12">
                                        <div class="text-muted small">Notes</div>
                                        <div><?= nl2br(e($ordre['payment_notes'])) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary no-print">
                        <i class="bi bi-info-circle me-1"></i>Cet ordre n’est plus payable.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$hasOrder && !empty($ordreUi['peut_emettre'])): ?>
<div class="modal fade order-confirm-modal" id="confirmIssueOrderModal" tabindex="-1" aria-labelledby="confirmIssueOrderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="order-confirm-icon bg-primary-subtle text-primary"><i class="bi bi-file-earmark-check"></i></span>
                    <div><h5 class="modal-title fw-bold mb-1" id="confirmIssueOrderLabel">Confirmer l’émission</h5><div class="small text-muted">Vérifiez les paramètres de paiement.</div></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-4">
                <div class="order-confirm-summary">
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Bénéficiaire</span><strong class="small text-end"><?= e($demande['beneficiary']) ?></strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Montant</span><strong class="text-primary text-end"><?= formatMontant((float)$demande['amount'], $demande['currency']) ?></strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Mode</span><strong class="small text-end" id="issueMethodSummary">—</strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Compte</span><strong class="small text-end" id="issueAccountSummary">—</strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Solde disponible</span><strong class="small text-end" id="issueModalBalance">—</strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Solde après émission</span><strong class="small text-end" id="issueModalAfter">—</strong></div>
                </div>
                <p class="small text-muted mt-3 mb-0">L’émission fera passer la demande au statut « Ordre émis ». Le paiement restera une opération distincte.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Revenir</button>
                <button type="button" class="btn btn-primary" id="confirmIssueOrderButton"><i class="bi bi-file-earmark-check me-1"></i>Émettre l’ordre</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($hasOrder && $ordre['status'] === 'emis' && !empty($ordreUi['peut_executer'])): ?>
<div class="modal fade order-confirm-modal" id="confirmExecutePaymentModal" tabindex="-1" aria-labelledby="confirmExecutePaymentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="order-confirm-icon bg-success-subtle text-success"><i class="bi bi-cash-coin"></i></span>
                    <div><h5 class="modal-title fw-bold mb-1" id="confirmExecutePaymentLabel">Confirmer le paiement</h5><div class="small text-muted">Dernière vérification avant comptabilisation.</div></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-4">
                <div class="order-confirm-summary">
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Ordre</span><strong class="small text-end"><?= e($ordre['order_number']) ?></strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Bénéficiaire</span><strong class="small text-end"><?= e($ordre['beneficiary']) ?></strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Montant</span><strong class="text-success text-end"><?= formatMontant((float)$ordre['amount'], $ordre['currency']) ?></strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Date</span><strong class="small text-end" id="executeDateSummary">—</strong></div>
                    <div class="d-flex justify-content-between gap-3 py-1"><span class="small text-muted">Preuve jointe</span><strong class="small text-end" id="executeProofSummary">—</strong></div>
                </div>
                <div class="alert alert-warning border-0 small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Cette action créera une dépense validée, clôturera la demande et empêchera tout second paiement.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Revenir</button>
                <button type="button" class="btn btn-success" id="confirmExecutePaymentButton"><i class="bi bi-check2-circle me-1"></i>Confirmer le paiement</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    function formatAccountAmount(value, currency) {
        return Number(value || 0).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + currency;
    }
    var issueAccount = document.getElementById('issueAccount');
    var issuePaymentMethod = document.getElementById('issuePaymentMethod');
    var issueSubmitButton = document.getElementById('issueOrderSubmitButton');
    function refreshIssueBalance() {
        if (!issueAccount) return;
        var option = issueAccount.options[issueAccount.selectedIndex];
        var panel = document.getElementById('issueBalancePanel');
        if (!option || !option.value) {
            if (panel) panel.style.display = 'none';
            if (issueSubmitButton) issueSubmitButton.disabled = false;
            issueAccount.setCustomValidity('');
            return;
        }
        var currency = option.dataset.currency || '';
        var expectedTypes = {especes: 'caisse', banque: 'banque', mobile_money: 'mobile_money'};
        var expectedType = issuePaymentMethod ? expectedTypes[issuePaymentMethod.value] : null;
        var compatible = !expectedType || option.dataset.accountType === expectedType;
        var covered = option.dataset.covered === '1' && compatible;
        panel.style.display = 'block';
        document.getElementById('issueBalanceAvailable').textContent = formatAccountAmount(option.dataset.balance, currency);
        document.getElementById('issueBalanceRequired').textContent = formatAccountAmount(option.dataset.required, currency);
        var after = document.getElementById('issueBalanceAfter');
        after.textContent = formatAccountAmount(option.dataset.after, currency);
        after.className = 'order-balance-value ' + (covered ? 'text-success' : 'text-danger');
        var status = document.getElementById('issueBalanceStatus');
        status.className = 'order-balance-status ' + (covered ? 'text-success' : 'text-danger');
        var message = covered
            ? 'Fonds disponibles pour couvrir cet ordre.'
            : (!compatible
                ? 'Ce compte n’est pas compatible avec le mode de paiement sélectionné.'
                : 'Solde insuffisant : sélectionnez un autre compte approvisionné.');
        status.innerHTML = '<i class="bi ' + (covered ? 'bi-check2-circle' : 'bi-exclamation-circle') + ' me-1"></i>' + message;
        issueAccount.setCustomValidity(covered ? '' : message);
        if (issueSubmitButton) issueSubmitButton.disabled = !covered;
    }
    if (issueAccount) {
        issueAccount.addEventListener('change', refreshIssueBalance);
        refreshIssueBalance();
    }
    if (issuePaymentMethod) issuePaymentMethod.addEventListener('change', refreshIssueBalance);

    function bindConfirmation(formId, modalId, confirmId, prepare) {
        var form = document.getElementById(formId);
        var modalElement = document.getElementById(modalId);
        var confirmButton = document.getElementById(confirmId);
        if (!form || !modalElement || !confirmButton) return;
        var modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        var confirmed = false;
        form.addEventListener('submit', function (event) {
            if (confirmed) return;
            event.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            if (prepare) prepare();
            modal.show();
        });
        confirmButton.addEventListener('click', function () {
            confirmed = true;
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement…';
            modal.hide();
            form.submit();
        });
    }

    bindConfirmation('issueOrderForm', 'confirmIssueOrderModal', 'confirmIssueOrderButton', function () {
        var method = document.getElementById('issuePaymentMethod');
        var account = document.getElementById('issueAccount');
        document.getElementById('issueMethodSummary').textContent = method.options[method.selectedIndex].text;
        document.getElementById('issueAccountSummary').textContent = account.options[account.selectedIndex].text;
        var option = account.options[account.selectedIndex];
        var currency = option.dataset.currency || '';
        document.getElementById('issueModalBalance').textContent = formatAccountAmount(option.dataset.balance, currency);
        var modalAfter = document.getElementById('issueModalAfter');
        modalAfter.textContent = formatAccountAmount(option.dataset.after, currency);
        modalAfter.className = 'small text-end ' + (option.dataset.covered === '1' ? 'text-success' : 'text-danger');
    });

    bindConfirmation('executePaymentForm', 'confirmExecutePaymentModal', 'confirmExecutePaymentButton', function () {
        var date = document.getElementById('executePaymentDate').value;
        var proof = document.getElementById('executeProofFile');
        document.getElementById('executeDateSummary').textContent = date ? date.split('-').reverse().join('/') : '—';
        document.getElementById('executeProofSummary').textContent = proof.files.length
            ? proof.files[0].name
            : <?= json_encode($externalProofRequired ? '—' : 'Bon généré par AMM-ERP', JSON_UNESCAPED_UNICODE) ?>;
    });
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
