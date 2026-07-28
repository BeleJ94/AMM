<?php $pageTitle = 'Demande ' . e($demande['request_number']) . ' - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>
<?php
$demandeSwal = $_SESSION['demande_swal'] ?? null;
unset($_SESSION['demande_swal']);

if (!function_exists('treasuryDecisionBadge')) {
    function treasuryDecisionBadge(string $decision): string {
        return $decision === 'valide' ? 'success' : 'danger';
    }
}

$validatedLevels = [];
foreach ($validations as $validation) {
    if (($validation['decision'] ?? '') === 'valide') $validatedLevels[] = $validation['approval_level'];
}
$niveauxValidationAutorises = $niveauxValidationAutorises ?? [];
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$isOwnRequest = $currentUserId > 0
    && ($currentUserId === (int)($demande['created_by'] ?? 0) || $currentUserId === (int)($demande['submitted_by'] ?? 0));
$canSubmit = $demande['status'] === 'brouillon';
$canComptable = $demande['status'] === 'soumise' && !in_array('comptable', $validatedLevels, true)
    && !empty($niveauxValidationAutorises['comptable']) && !$isOwnRequest;
$canDirectionFin = $demande['status'] === 'validee_comptable' && !in_array('direction_financiere', $validatedLevels, true)
    && !empty($niveauxValidationAutorises['direction_financiere']) && !$isOwnRequest;
$canDirectionGen = $demande['status'] === 'validee_comptable' && !in_array('direction_generale', $validatedLevels, true)
    && !empty($niveauxValidationAutorises['direction_generale']) && !$isOwnRequest;
$canValidate = $canComptable || $canDirectionFin || $canDirectionGen;
$canOrder = $demande['status'] === 'validee_direction' && !empty($peutEmettreOrdre);
$peutAnnulerDemande = $peutAnnulerDemande ?? false;
$canCancelRequest = $peutAnnulerDemande
    && in_array($demande['status'], ['brouillon','soumise','validee_comptable','validee_direction','rejetee'], true);
$canCancelOrder = $peutAnnulerDemande && !empty($ordre) && ($ordre['status'] ?? '') === 'emis';

$workflowSteps = [
    'brouillon' => ['icon' => 'bi-pencil-square', 'label' => 'Préparation'],
    'soumise' => ['icon' => 'bi-send', 'label' => 'Soumission'],
    'validee_comptable' => ['icon' => 'bi-shield-check', 'label' => 'Contrôle'],
    'validee_direction' => ['icon' => 'bi-check2-circle', 'label' => 'Approbation'],
    'ordre_emis' => ['icon' => 'bi-file-earmark-check', 'label' => 'Ordonnancement'],
    'payee' => ['icon' => 'bi-cash-coin', 'label' => 'Paiement'],
];
$workflowOrder = array_keys($workflowSteps);
$currentWorkflowIndex = array_search($demande['status'], $workflowOrder, true);
$isStopped = in_array($demande['status'], ['rejetee', 'annulee'], true);
$directionFinanciereValidee = in_array('direction_financiere', $validatedLevels, true);
$directionGeneraleValidee = in_array('direction_generale', $validatedLevels, true);
$directionNextAction = ['Double approbation de la Direction attendue', 'Les validations de la Direction financière et de la Direction générale sont requises.', 'bi-building-check'];
if ($directionGeneraleValidee && !$directionFinanciereValidee) {
    $directionNextAction = ['Validation de la Direction financière attendue', 'La Direction générale a déjà approuvé cette demande. Le visa de la Direction financière reste requis.', 'bi-building-check'];
} elseif ($directionFinanciereValidee && !$directionGeneraleValidee) {
    $directionNextAction = ['Validation de la Direction générale attendue', 'La Direction financière a déjà approuvé cette demande. Le visa de la Direction générale reste requis.', 'bi-building-check'];
}
$nextAction = [
    'brouillon' => ['Soumettre la demande', 'Elle sera transmise au Comptable senior pour contrôle.', 'bi-send'],
    'soumise' => ['Contrôle comptable attendu', 'La demande est en attente de la décision du Comptable senior.', 'bi-hourglass-split'],
    'validee_comptable' => $directionNextAction,
    'validee_direction' => ['Émettre l’ordre de paiement', 'La demande est approuvée et prête pour l’ordonnancement.', 'bi-file-earmark-check'],
    'ordre_emis' => ['Exécuter le paiement', 'Un ordre de paiement a été émis et reste à exécuter.', 'bi-bank'],
    'payee' => ['Cycle terminé', 'Le paiement a été exécuté et la demande est clôturée.', 'bi-check-circle'],
    'rejetee' => ['Demande rejetée', 'Consultez l’historique pour connaître la décision et son motif.', 'bi-x-octagon'],
    'annulee' => ['Demande annulée', 'Aucune action financière supplémentaire n’est attendue.', 'bi-slash-circle'],
][$demande['status']] ?? ['Suivi en cours', 'Consultez le statut et l’historique de la demande.', 'bi-info-circle'];
$urgencyClass = ['basse' => 'success', 'moyenne' => 'warning', 'haute' => 'danger'][$demande['urgency']] ?? 'secondary';
?>

<style>
.request-page-shell { max-width:1440px; margin:0 auto; }
.request-exec-summary { border:1px solid #e1e7ee; border-radius:.65rem; background:#fff; }
.request-eyebrow { color:#718096; font-size:.65rem; font-weight:800; letter-spacing:.09em; text-transform:uppercase; }
.request-number { color:#17283e; font-size:1.15rem; font-weight:800; letter-spacing:-.015em; }
.request-status { display:inline-flex; align-items:center; gap:.35rem; padding:.34rem .62rem; border:1px solid #dce4ec; border-radius:999px; color:#41566d; background:#f8fafc; font-size:.7rem; font-weight:750; }
.request-amount { color:#17283e; font-size:1.45rem; font-weight:800; letter-spacing:-.025em; }
.request-kpi { min-height:100%; padding:.9rem 0; border:0; background:transparent; }
.request-kpi-label,.request-field-label { color:#718096; font-size:.66rem; font-weight:800; letter-spacing:.055em; text-transform:uppercase; }
.request-kpi-value { margin-top:.35rem; color:#17283e; font-size:.96rem; font-weight:750; }
.request-meta-grid > div + div { border-left:1px solid #edf1f5; }
.request-next { border-top:1px solid #e7ecf1; background:#fafbfd; }
.request-next.stopped { border-color:#f0d7da; background:#fffafa; }
.request-detail-row { padding:.9rem 0; border-bottom:1px solid #edf1f5; }
.request-detail-row:last-child { border-bottom:0; }
.request-detail-value { color:#263b52; font-weight:650; }
.request-timeline { position:relative; padding-left:1.7rem; }
.request-timeline::before { content:""; position:absolute; left:.42rem; top:.4rem; bottom:.4rem; width:2px; background:#dfe7ef; }
.request-timeline-item { position:relative; padding:0 0 1.35rem .65rem; }
.request-timeline-item:last-child { padding-bottom:0; }
.request-timeline-dot { position:absolute; left:-1.7rem; top:.15rem; width:.9rem; height:.9rem; border:3px solid #fff; border-radius:50%; box-shadow:0 0 0 1px #cbd6e2; background:#1769e0; }
.request-action-card { position:sticky; top:88px; }
.request-section-card { border:1px solid #e4e9ef; box-shadow:none!important; }
.request-header-actions .btn { min-height:36px; }
.request-modal .modal-content { overflow:hidden; border:0; border-radius:.9rem; box-shadow:0 24px 70px rgba(12,35,61,.2); }
.request-modal .modal-header { padding:1.2rem 1.35rem; border-bottom:1px solid #e7edf3; background:#f8fafc; }
.request-modal-icon { display:grid; place-items:center; width:42px; height:42px; border-radius:.7rem; font-size:1.1rem; }
.request-modal-summary { padding:.9rem 1rem; border:1px solid #e1e8f0; border-radius:.7rem; background:#f8fafc; }
@media(max-width:991.98px){.request-action-card{position:static}}
@media(max-width:767.98px){.request-meta-grid > div + div{border-left:0}.request-meta-grid > div{border-bottom:1px solid #edf1f5}.request-meta-grid > div:last-child{border-bottom:0}}
</style>

<?php if (!empty($demandeSwal)): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: <?= json_encode($demandeSwal['icon'] ?? 'success') ?>,
        title: <?= json_encode($demandeSwal['title'] ?? 'Opération terminée', JSON_UNESCAPED_UNICODE) ?>,
        text: <?= json_encode($demandeSwal['text'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        confirmButtonText: 'Continuer', confirmButtonColor: '#1769e0',
        timer: 4300, timerProgressBar: true, customClass: {popup:'rounded-3'}
    });
});
</script>
<?php endif; ?>

<div class="container-fluid request-page-shell">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= APP_URL ?>/treasury/demandes" class="btn btn-outline-secondary btn-sm" aria-label="Retour aux demandes"><i class="bi bi-arrow-left"></i></a>
            <div><h1 class="h6 fw-bold mb-0">Demande de fonds</h1><div class="small text-muted mt-1"><?= e($demande['request_number']) ?></div></div>
        </div>
        <div class="d-flex flex-wrap gap-2 request-header-actions">
            <?php if ($canSubmit): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSoumettre"><i class="bi bi-send me-1"></i>Soumettre</button><?php endif; ?>
            <?php if ($canOrder): ?><a href="<?= APP_URL ?>/treasury/ordre-paiement/<?= (int)$demande['id'] ?>" class="btn btn-dark"><i class="bi bi-file-earmark-check me-1"></i>Créer l’ordre</a><?php endif; ?>
            <?php if ($canCancelRequest): ?><button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAnnulerDemande"><i class="bi bi-x-circle me-1"></i>Annuler</button><?php endif; ?>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <section class="request-exec-summary mb-4">
        <div class="p-3 p-md-4 border-bottom">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="request-number"><?= e($demande['request_number']) ?></span>
                        <span class="request-status"><i class="bi bi-circle-fill small text-<?= $isStopped ? 'danger' : 'primary' ?>"></i><?= e(treasuryUiLabel($demande['status'])) ?></span>
                    </div>
                    <div class="fw-semibold"><?= e($demande['objet']) ?></div>
                    <div class="small text-muted mt-1"><?= e($demande['beneficiary']) ?> · <?= formatDate($demande['request_date']) ?></div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="request-field-label">Montant demandé</div>
                    <div class="request-amount mt-1"><?= formatMontant((float)$demande['amount'], $demande['currency']) ?></div>
                    <div class="small text-muted"><?= formatMontant((float)$demande['amount_usd'], 'USD') ?> en base USD</div>
                </div>
            </div>
        </div>
        <div class="row g-0 request-meta-grid px-3 px-md-4">
            <div class="col-md-4 pe-md-4"><div class="request-kpi"><div class="request-kpi-label">Catégorie</div><div class="request-kpi-value"><?= e($demande['category_nom'] ?? 'Non renseignée') ?></div></div></div>
            <div class="col-md-4 px-md-4"><div class="request-kpi"><div class="request-kpi-label">Urgence</div><div class="request-kpi-value"><span class="badge bg-<?= $urgencyClass ?>"><?= e(treasuryUiLabel($demande['urgency'])) ?></span></div></div></div>
            <div class="col-md-4 ps-md-4"><div class="request-kpi"><div class="request-kpi-label">Compte souhaité</div><div class="request-kpi-value"><?= e($demande['preferred_account_nom'] ?? 'À déterminer') ?></div></div></div>
        </div>
        <div class="request-next <?= $isStopped ? 'stopped' : '' ?> px-3 px-md-4 py-3 d-flex align-items-start gap-3">
            <i class="bi <?= e($nextAction[2]) ?> text-<?= $isStopped ? 'danger' : 'secondary' ?>"></i>
            <div class="flex-grow-1"><div class="small fw-bold"><?= e($nextAction[0]) ?></div><div class="small text-muted"><?= e($nextAction[1]) ?></div></div>
        </div>
    </section>

    <?php if ($isOwnRequest && in_array($demande['status'], ['soumise','validee_comptable'], true)): ?>
        <div class="small text-muted mb-4"><i class="bi bi-shield-lock me-1"></i>Séparation des tâches active : le créateur ou soumetteur ne peut pas approuver sa propre demande.</div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="<?= $canValidate ? 'col-lg-8' : 'col-12' ?>">
            <div class="card section-card request-section-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4"><h3 class="h6 fw-bold mb-0">Informations de la demande</h3></div>
                <div class="card-body px-4">
                    <div class="request-detail-row"><div class="request-field-label">Objet</div><div class="request-detail-value mt-1"><?= e($demande['objet']) ?></div></div>
                    <div class="request-detail-row"><div class="request-field-label">Description / justification</div><div class="request-detail-value mt-1"><?= !empty($demande['description']) ? nl2br(e($demande['description'])) : '<span class="text-muted fw-normal">Aucune précision complémentaire.</span>' ?></div></div>
                    <div class="row">
                        <div class="col-md-6"><div class="request-detail-row"><div class="request-field-label">Créée par</div><div class="request-detail-value mt-1"><?= e($demande['created_by_nom'] ?? '—') ?></div></div></div>
                        <div class="col-md-6"><div class="request-detail-row"><div class="request-field-label">Soumise par</div><div class="request-detail-value mt-1"><?= e($demande['submitted_by_nom'] ?? 'Non soumise') ?></div></div></div>
                    </div>
                    <?php if ($demande['status'] === 'annulee'): ?><div class="alert alert-danger mt-3 mb-0"><strong>Motif d’annulation :</strong><br><?= nl2br(e($demande['cancellation_reason'] ?? '—')) ?></div><?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <?php if (!empty($demande['justification_file'])): ?><a href="<?= UPLOAD_URL . e($demande['justification_file']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-paperclip me-1"></i>Consulter le justificatif</a><?php endif; ?>
                        <?php if (!empty($ordre)): ?><a href="<?= APP_URL ?>/treasury/ordre-paiement/<?= (int)$demande['id'] ?>" class="btn btn-outline-dark"><i class="bi bi-file-earmark-check me-1"></i>Voir l’ordre de paiement</a><?php endif; ?>
                        <?php if ($canCancelOrder): ?><button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAnnulerOrdre"><i class="bi bi-x-circle me-1"></i>Annuler l’ordre</button><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card section-card request-section-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4"><h3 class="h6 fw-bold mb-0">Historique des décisions</h3></div>
                <div class="card-body px-4">
                    <?php if (empty($validations)): ?>
                        <?php treasuryUiEmptyState('bi-clock-history', 'Aucune décision enregistrée', 'Le journal sera alimenté à chaque étape de validation.'); ?>
                    <?php else: ?>
                        <div class="request-timeline">
                            <?php foreach ($validations as $validation): ?>
                                <div class="request-timeline-item">
                                    <span class="request-timeline-dot bg-<?= treasuryDecisionBadge($validation['decision']) ?>"></span>
                                    <div class="d-flex flex-wrap justify-content-between gap-2"><strong><?= e(treasuryUiLabel($validation['approval_level'])) ?></strong><small class="text-muted"><?= !empty($validation['approved_at']) ? e(date('d/m/Y à H:i', strtotime($validation['approved_at']))) : '—' ?></small></div>
                                    <div class="small mt-1"><span class="badge bg-<?= treasuryDecisionBadge($validation['decision']) ?>"><?= e(treasuryUiLabel($validation['decision'])) ?></span> par <?= e($validation['approved_by_nom'] ?? '—') ?></div>
                                    <?php if (!empty($validation['approval_comment'])): ?><div class="small text-muted mt-2"><?= e($validation['approval_comment']) ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($canValidate): ?>
        <div class="col-lg-4">
            <div class="request-action-card">
                    <div class="card section-card request-section-card">
                        <div class="card-header bg-transparent border-0 pt-4 px-4"><h3 class="h6 fw-bold mb-1">Décision requise</h3><div class="small text-muted">Vérifiez les informations avant de statuer.</div></div>
                        <div class="card-body px-4">
                            <form method="POST" action="<?= APP_URL ?>/treasury/demande-valider/<?= (int)$demande['id'] ?>" id="validationForm">
                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                <div class="mb-3"><label class="form-label fw-semibold">Niveau de décision</label><select class="form-select" name="approval_level" id="approvalLevel" required>
                                    <?php if ($canComptable): ?><option value="comptable">Comptable senior</option><?php endif; ?>
                                    <?php if ($canDirectionFin): ?><option value="direction_financiere">Direction financière</option><?php endif; ?>
                                    <?php if ($canDirectionGen): ?><option value="direction_generale">Direction générale</option><?php endif; ?>
                                </select></div>
                                <div class="mb-3"><label class="form-label fw-semibold">Décision</label><select class="form-select" name="decision" id="decisionField" required><option value="valide">Approuver</option><option value="rejete">Rejeter</option></select></div>
                                <div class="mb-3"><label class="form-label fw-semibold">Commentaire</label><textarea class="form-control" name="commentaire" id="commentaireField" rows="3" placeholder="Ajoutez une observation utile à la traçabilité"></textarea><div class="form-text" id="commentHelp">Facultatif pour une approbation.</div></div>
                                <button type="submit" class="btn btn-primary w-100" id="decisionButton"><i class="bi bi-shield-check me-1"></i>Revoir et confirmer</button>
                            </form>
                        </div>
                    </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canSubmit): ?>
<div class="modal fade request-modal" id="modalSoumettre" tabindex="-1" aria-labelledby="modalSoumettreLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="<?= APP_URL ?>/treasury/demande-voir/<?= (int)$demande['id'] ?>" class="modal-content js-confirm-form"><div class="modal-header"><div class="d-flex gap-3 align-items-center"><span class="request-modal-icon bg-primary-subtle text-primary"><i class="bi bi-send"></i></span><div><h5 class="modal-title fw-bold mb-1" id="modalSoumettreLabel">Soumettre la demande</h5><div class="small text-muted">Transmission au circuit d’approbation</div></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="action" value="soumettre"><div class="request-modal-summary"><div class="d-flex justify-content-between gap-3 mb-2"><span class="text-muted small">Référence</span><strong><?= e($demande['request_number']) ?></strong></div><div class="d-flex justify-content-between gap-3"><span class="text-muted small">Montant</span><strong class="text-primary"><?= formatMontant((float)$demande['amount'], $demande['currency']) ?></strong></div></div><p class="small text-muted mt-3 mb-0">Après confirmation, la demande ne sera plus un brouillon et sera transmise au Comptable senior.</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Retour</button><button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Confirmer la soumission</button></div></form></div></div>
<?php endif; ?>

<?php if ($canValidate): ?>
<div class="modal fade request-modal" id="modalDecision" tabindex="-1" aria-labelledby="modalDecisionLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div class="d-flex gap-3 align-items-center"><span class="request-modal-icon" id="decisionModalIcon"><i class="bi bi-shield-check"></i></span><div><h5 class="modal-title fw-bold mb-1" id="modalDecisionLabel">Confirmer la décision</h5><div class="small text-muted">Cette action sera inscrite au journal d’audit.</div></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><div class="request-modal-summary"><div class="d-flex justify-content-between gap-3 mb-2"><span class="text-muted small">Demande</span><strong><?= e($demande['request_number']) ?></strong></div><div class="d-flex justify-content-between gap-3 mb-2"><span class="text-muted small">Niveau</span><strong id="decisionLevelSummary">—</strong></div><div class="d-flex justify-content-between gap-3"><span class="text-muted small">Décision</span><strong id="decisionSummary">—</strong></div></div><div class="small text-muted mt-3" id="decisionCommentSummary"></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Revenir</button><button type="button" class="btn" id="confirmDecisionButton">Confirmer</button></div></div></div></div>
<?php endif; ?>

<?php if ($canCancelRequest): ?>
<div class="modal fade request-modal" id="modalAnnulerDemande" tabindex="-1" aria-labelledby="modalAnnulerDemandeLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="<?= APP_URL ?>/treasury/demande-annuler/<?= (int)$demande['id'] ?>" class="modal-content js-confirm-form"><div class="modal-header"><div class="d-flex gap-3 align-items-center"><span class="request-modal-icon bg-danger-subtle text-danger"><i class="bi bi-x-octagon"></i></span><div><h5 class="modal-title fw-bold mb-1" id="modalAnnulerDemandeLabel">Annuler la demande</h5><div class="small text-muted">Action sensible avec traçabilité obligatoire</div></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><div class="request-modal-summary mb-3"><div class="d-flex justify-content-between gap-3"><span class="text-muted small">Dossier concerné</span><strong><?= e($demande['request_number']) ?></strong></div></div><label class="form-label fw-semibold">Motif d’annulation <span class="text-danger">*</span></label><textarea name="motif" class="form-control" rows="4" required minlength="3" placeholder="Expliquez précisément la raison de l’annulation"></textarea><div class="form-text">Ce motif figurera dans la traçabilité de la demande.</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Conserver la demande</button><button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Confirmer l’annulation</button></div></form></div></div>
<?php endif; ?>

<?php if ($canCancelOrder): ?>
<div class="modal fade request-modal" id="modalAnnulerOrdre" tabindex="-1" aria-labelledby="modalAnnulerOrdreLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="<?= APP_URL ?>/treasury/ordre-annuler/<?= (int)$ordre['id'] ?>" class="modal-content js-confirm-form"><div class="modal-header"><div class="d-flex gap-3 align-items-center"><span class="request-modal-icon bg-danger-subtle text-danger"><i class="bi bi-file-earmark-x"></i></span><div><h5 class="modal-title fw-bold mb-1" id="modalAnnulerOrdreLabel">Annuler l’ordre de paiement</h5><div class="small text-muted">L’ordre émis ne pourra plus être exécuté.</div></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="retour_demande" value="1"><label class="form-label fw-semibold">Motif d’annulation <span class="text-danger">*</span></label><textarea name="motif" class="form-control" rows="4" required minlength="3" placeholder="Indiquez le motif de cette décision"></textarea></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Retour</button><button type="submit" class="btn btn-danger">Confirmer l’annulation</button></div></form></div></div>
<?php endif; ?>

<script>
(function () {
    var validationForm = document.getElementById('validationForm');
    var decisionField = document.getElementById('decisionField');
    var commentField = document.getElementById('commentaireField');
    var commentHelp = document.getElementById('commentHelp');
    var modalEl = document.getElementById('modalDecision');
    var modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    var confirmed = false;

    function refreshDecision() {
        if (!decisionField || !commentField) return;
        var rejected = decisionField.value === 'rejete';
        commentField.required = rejected;
        commentHelp.textContent = rejected ? 'Le motif est obligatoire pour justifier un rejet.' : 'Facultatif pour une approbation.';
    }
    if (decisionField) {
        decisionField.addEventListener('change', refreshDecision);
        refreshDecision();
    }
    if (validationForm) {
        validationForm.addEventListener('submit', function (event) {
            if (confirmed) return;
            event.preventDefault();
            refreshDecision();
            if (!validationForm.checkValidity()) {
                validationForm.reportValidity();
                return;
            }
            var rejected = decisionField.value === 'rejete';
            var level = document.getElementById('approvalLevel');
            document.getElementById('decisionLevelSummary').textContent = level.options[level.selectedIndex].text;
            document.getElementById('decisionSummary').textContent = rejected ? 'Rejeter la demande' : 'Approuver la demande';
            document.getElementById('decisionSummary').className = rejected ? 'text-danger' : 'text-success';
            document.getElementById('decisionCommentSummary').textContent = commentField.value.trim() ? 'Commentaire : ' + commentField.value.trim() : 'Aucun commentaire complémentaire.';
            var icon = document.getElementById('decisionModalIcon');
            icon.className = 'request-modal-icon ' + (rejected ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success');
            icon.innerHTML = '<i class="bi ' + (rejected ? 'bi-x-octagon' : 'bi-check2-circle') + '"></i>';
            document.getElementById('modalDecisionLabel').textContent = rejected ? 'Confirmer le rejet' : 'Confirmer l’approbation';
            var button = document.getElementById('confirmDecisionButton');
            button.className = 'btn ' + (rejected ? 'btn-danger' : 'btn-success');
            button.textContent = rejected ? 'Confirmer le rejet' : 'Confirmer l’approbation';
            modal.show();
        });
        document.getElementById('confirmDecisionButton').addEventListener('click', function () {
            confirmed = true;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement…';
            modal.hide();
            validationForm.submit();
        });
    }
    document.querySelectorAll('.js-confirm-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement…';
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
