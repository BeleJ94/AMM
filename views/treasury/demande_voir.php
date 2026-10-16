<?php $pageTitle = 'Demande ' . e($demande['request_number']) . ' - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>
<?php
$demandeSwal = $_SESSION['demande_swal'] ?? null;
unset($_SESSION['demande_swal']);

if (!function_exists('treasuryDecisionBadge')) {
    function treasuryDecisionBadge(string $decision): string {
        return ['valide' => 'success', 'en_suspens' => 'warning text-dark', 'repris' => 'info text-dark'][$decision] ?? 'danger';
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
$canSubmit = $demande['status'] === 'brouillon' && !empty($peutSoumettre);
$isSuspended = $demande['status'] === 'en_suspens';
$canResume = $isSuspended && !empty($niveauxValidationAutorises[$demande['suspended_level'] ?? '']) && !$isOwnRequest;
$canControl = $demande['status'] === 'a_controler' && !empty($peutControlerDemande);
$lignesDemande = $lignesDemande ?? [];
$approvedAmount = (float)($demande['approved_amount'] ?? $demande['amount']);
$isPartial = $approvedAmount < (float)$demande['amount'] - 0.00001;
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
    && in_array($demande['status'], ['brouillon','a_controler','soumise','validee_comptable','validee_direction','en_suspens','rejetee'], true);
$canCancelOrder = $peutAnnulerDemande && !empty($ordre) && ($ordre['status'] ?? '') === 'emis';

$workflowSteps = [
    'brouillon' => ['icon' => 'bi-pencil-square', 'label' => 'Préparation'],
    'a_controler' => ['icon' => 'bi-search', 'label' => 'Trésorier A'],
    'soumise' => ['icon' => 'bi-shield-check', 'label' => 'Comptable senior'],
    'validee_comptable' => ['icon' => 'bi-building-check', 'label' => 'Direction'],
    'validee_direction' => ['icon' => 'bi-check2-circle', 'label' => 'Approbation'],
    'ordre_emis' => ['icon' => 'bi-file-earmark-check', 'label' => 'Ordonnancement'],
    'payee' => ['icon' => 'bi-cash-coin', 'label' => 'Paiement'],
];
$workflowOrder = array_keys($workflowSteps);
$currentWorkflowIndex = array_search($isSuspended ? ($demande['previous_status'] ?? '') : $demande['status'], $workflowOrder, true);
$isStopped = in_array($demande['status'], ['rejetee', 'annulee'], true);
$directionFinanciereValidee = in_array('direction_financiere', $validatedLevels, true);
$directionGeneraleValidee = in_array('direction_generale', $validatedLevels, true);
$directionNextAction = ['Approbation de la Direction attendue', 'Les visas de direction prévus par le circuit sont en attente.', 'bi-building-check'];
if ($directionGeneraleValidee && !$directionFinanciereValidee) {
    $directionNextAction = ['Validation de la Direction financière attendue', 'La Direction générale a déjà approuvé cette demande. Le visa de la Direction financière reste requis.', 'bi-building-check'];
} elseif ($directionFinanciereValidee && !$directionGeneraleValidee) {
    $directionNextAction = ['Validation de la Direction générale attendue', 'La Direction financière a déjà approuvé cette demande. Le visa de la Direction générale reste requis.', 'bi-building-check'];
}
$nextAction = [
    'brouillon' => ['Soumettre la demande', 'Elle sera transmise au Trésorier A pour contrôle avant la validation comptable.', 'bi-send'],
    'a_controler' => ['Contrôle du Trésorier A attendu', 'Le Trésorier A vérifie le dossier et le transmet au Comptable senior.', 'bi-search'],
    'en_suspens' => ['Demande en suspens', 'Le traitement reprendra au niveau ' . treasuryUiLabel($demande['suspended_level'] ?? '') . (!empty($demande['reminder_date']) ? '. Rappel prévu le ' . formatDate($demande['reminder_date']) . '.' : '.'), 'bi-pause-circle'],
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
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/treasury-requests.css">

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

<div class="container-fluid request-page-shell tr-page">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            <a href="<?= APP_URL ?>/treasury/demandes" class="btn btn-outline-secondary btn-sm" aria-label="Retour aux demandes"><i class="bi bi-arrow-left"></i></a>
            <div><h1 class="h6 fw-bold mb-0">Demande de fonds</h1><div class="small text-muted mt-1"><?= e($demande['request_number']) ?></div></div>
        </div>
        <div class="d-flex flex-wrap gap-2 request-header-actions">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#requestPdfModal"><i class="bi bi-file-earmark-pdf me-1"></i>Aperçu de la fiche</button>
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
                    <div class="small text-muted mt-1"><?= e($demande['department'] ?? 'Département non renseigné') ?> · <?= e($demande['beneficiary']) ?> · <?= formatDate($demande['request_date']) ?></div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="request-field-label">Montant demandé</div>
                    <div class="request-amount mt-1"><?= formatMontant((float)$demande['amount'], $demande['currency']) ?></div>
                    <div class="small text-muted"><?= formatMontant((float)$demande['amount_usd'], 'USD') ?> en base USD</div>
                    <?php if ($demande['currency'] === 'CDF' && !empty($demande['exchange_rate'])): ?><div class="small text-muted mt-1">1 USD = <?= formatMontant((float)$demande['exchange_rate'], 'CDF') ?></div><?php endif; ?>
                    <?php if ($isPartial): ?><div class="tr-approved-box mt-3 text-start"><div class="request-field-label text-success">Validation partielle</div><strong><?= formatMontant($approvedAmount, $demande['currency']) ?> autorisés</strong><div class="small text-muted mt-1">Solde non autorisé : <?= formatMontant((float)$demande['amount'] - $approvedAmount, $demande['currency']) ?></div></div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="row g-0 request-meta-grid px-3 px-md-4">
            <div class="col-md-4 pe-md-4"><div class="request-kpi"><div class="request-kpi-label">Catégorie</div><div class="request-kpi-value"><?= e($demande['category_nom'] ?? 'Non renseignée') ?></div></div></div>
            <div class="col-md-4 px-md-4"><div class="request-kpi"><div class="request-kpi-label">Urgence</div><div class="request-kpi-value"><span class="badge bg-<?= $urgencyClass ?>"><?= e(treasuryUiLabel($demande['urgency'])) ?></span></div></div></div>
            <div class="col-md-4 ps-md-4"><div class="request-kpi"><div class="request-kpi-label">Compte souhaité</div><div class="request-kpi-value"><?= e($demande['preferred_account_nom'] ?? 'À déterminer') ?></div></div></div>
        </div>
        <div class="request-next <?= $isStopped ? 'stopped' : '' ?> <?= $isSuspended ? 'bg-warning-subtle' : '' ?> px-3 px-md-4 py-3 d-flex align-items-start gap-3">
            <i class="bi <?= e($nextAction[2]) ?> text-<?= $isStopped ? 'danger' : 'secondary' ?>"></i>
            <div class="flex-grow-1"><div class="small fw-bold"><?= e($nextAction[0]) ?></div><div class="small text-muted"><?= e($nextAction[1]) ?></div></div>
        </div>
    </section>
    <?php if (!$isStopped): ?><div class="tr-workflow mb-3" aria-label="Progression de la demande"><?php foreach (array_values($workflowSteps) as $stepIndex => $step): ?><div class="tr-workflow-step <?= $currentWorkflowIndex !== false && $stepIndex < $currentWorkflowIndex ? 'is-done' : ($stepIndex === $currentWorkflowIndex ? 'is-current' : '') ?>"><i class="bi <?= e($step['icon']) ?> me-1"></i><?= e($step['label']) ?></div><?php endforeach; ?></div><?php endif; ?>

    <?php if ($isOwnRequest && in_array($demande['status'], ['soumise','validee_comptable'], true)): ?>
        <div class="small text-muted mb-4"><i class="bi bi-shield-lock me-1"></i>Séparation des tâches active : le créateur ou soumetteur ne peut pas approuver sa propre demande.</div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="<?= ($canValidate || $canControl || $canResume) ? 'col-lg-8' : 'col-12' ?>">
            <section class="card request-section-card mb-4"><div class="card-header bg-transparent border-0 pt-4 px-4"><h2 class="h6 fw-bold mb-1">Détail des biens et services</h2><div class="small text-muted">Prix unitaires estimés · <?= e($demande['currency']) ?></div></div><div class="card-body px-0 pb-0"><div class="table-responsive"><table class="table tr-detail-table mb-0" id="tableBiensDemande" <?php if (!empty($lignesDemande)): ?>data-register-table<?php endif; ?> data-register-order='[]'><thead><tr><th>N°</th><th>Services / Biens</th><th class="text-end">Quantité</th><th class="text-end">Prix unitaire estimé</th><th class="text-end">Total</th></tr></thead><tbody><?php if (empty($lignesDemande)): ?><tr><td colspan="5" class="text-muted py-4 px-4">Cette demande historique ne comporte pas de lignes détaillées. Le montant et la justification sont conservés ci-dessous.</td></tr><?php endif; ?><?php foreach ($lignesDemande as $lineIndex => $line): ?><tr><td><?= $lineIndex + 1 ?></td><td><?= e($line['description']) ?></td><td class="text-end"><?= e(rtrim(rtrim(number_format((float)$line['quantity'], 4, '.', ' '), '0'), '.')) ?></td><td class="text-end text-nowrap"><?= formatMontant((float)$line['unit_price'], $demande['currency']) ?></td><td class="text-end fw-semibold text-nowrap"><?= formatMontant((float)($line['line_total'] ?? ((float)$line['quantity'] * (float)$line['unit_price'])), $demande['currency']) ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><td colspan="4" class="text-end fw-bold">Total demandé</td><td class="text-end fw-bold text-nowrap"><?= formatMontant((float)$demande['amount'], $demande['currency']) ?></td></tr></tfoot></table></div></div></section>
            <div class="card section-card request-section-card mb-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4"><h3 class="h6 fw-bold mb-0">Informations de la demande</h3></div>
                <div class="card-body px-4">
                    <div class="request-detail-row"><div class="request-field-label">Objet</div><div class="request-detail-value mt-1"><?= e($demande['objet']) ?></div></div>
                    <div class="request-detail-row"><div class="request-field-label">Justification du besoin</div><div class="request-detail-value mt-1"><?= !empty($demande['description']) ? nl2br(e($demande['description'])) : '<span class="text-muted fw-normal">Aucune précision complémentaire.</span>' ?></div></div>
                    <div class="row"><div class="col-md-6"><div class="request-detail-row"><div class="request-field-label">Département demandeur</div><div class="request-detail-value mt-1"><?= e($demande['department'] ?? '—') ?></div></div></div><div class="col-md-6"><div class="request-detail-row"><div class="request-field-label">Nom du demandeur</div><div class="request-detail-value mt-1"><?= e($demande['requester_name'] ?? $demande['created_by_nom'] ?? '—') ?></div></div></div></div>
                    <div class="row">
                        <div class="col-md-6"><div class="request-detail-row"><div class="request-field-label">Créée par</div><div class="request-detail-value mt-1"><?= e($demande['created_by_nom'] ?? '—') ?></div></div></div>
                        <div class="col-md-6"><div class="request-detail-row"><div class="request-field-label">Soumise par</div><div class="request-detail-value mt-1"><?= e($demande['submitted_by_nom'] ?? 'Non soumise') ?></div></div></div>
                    </div>
                    <?php if ($demande['status'] === 'annulee'): ?><div class="alert alert-danger mt-3 mb-0"><strong>Motif d’annulation :</strong><br><?= nl2br(e($demande['cancellation_reason'] ?? '—')) ?></div><?php endif; ?>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <?php if (!empty($demande['justification_file'])): ?><a href="<?= UPLOAD_URL . e($demande['justification_file']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-paperclip me-1"></i>Consulter le justificatif</a><?php endif; ?>
                        <?php if (!empty($ordre) && empty($accesDepartement)): ?><a href="<?= APP_URL ?>/treasury/ordre-paiement/<?= (int)$demande['id'] ?>" class="btn btn-outline-dark"><i class="bi bi-file-earmark-check me-1"></i>Voir l’ordre de paiement</a><?php endif; ?>
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
                                    <?php if (isset($validation['approved_amount'])): ?><div class="small text-success mt-1">Montant autorisé : <strong><?= formatMontant((float)$validation['approved_amount'], $demande['currency']) ?></strong><?= (float)$validation['approved_amount'] < (float)$demande['amount'] ? ' · Validation partielle' : ' · Validation totale' ?></div><?php endif; ?>
                                    <?php if (!empty($validation['reminder_date'])): ?><div class="small text-warning-emphasis mt-1"><i class="bi bi-bell me-1"></i>Rappel prévu le <?= formatDate($validation['reminder_date']) ?></div><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($canValidate || $canControl || $canResume): ?>
        <div class="col-lg-4">
            <div class="request-action-card">
                    <?php if ($canControl): ?><section class="card request-section-card mb-3"><div class="card-body p-4"><div class="request-eyebrow mb-2">Trésorier A</div><h2 class="h6 fw-bold">Contrôler et transmettre</h2><p class="tr-choice-note">Vérifiez les lignes, la justification et le document joint. La transmission ouvre la validation du Comptable senior.</p><form method="POST" action="<?= APP_URL ?>/treasury/demande-voir/<?= (int)$demande['id'] ?>" class="js-confirm-form"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="action" value="controler"><label for="controlComment" class="form-label">Observation du contrôle <span class="text-muted fw-normal">(facultatif)</span></label><textarea id="controlComment" name="commentaire" class="form-control mb-3" rows="3" placeholder="Éléments vérifiés, précision utile au comptable…"></textarea><button class="btn btn-primary w-100" type="submit"><i class="bi bi-send-check me-1"></i>Transmettre au Comptable senior</button></form></div></section><?php endif; ?>
                    <?php if ($canResume): ?><section class="card request-section-card mb-3"><div class="card-body p-4"><div class="request-eyebrow mb-2">Reprise du traitement</div><h2 class="h6 fw-bold">Lever la mise en suspens</h2><p class="tr-choice-note">La demande reprendra au même niveau : <?= e(treasuryUiLabel($demande['suspended_level'])) ?>. Vous pourrez ensuite enregistrer votre décision.</p><form method="POST" action="<?= APP_URL ?>/treasury/demande-valider/<?= (int)$demande['id'] ?>" class="js-confirm-form"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="approval_level" value="<?= e($demande['suspended_level']) ?>"><input type="hidden" name="decision" value="reprendre"><label for="resumeComment" class="form-label">Observation de reprise <span class="text-muted fw-normal">(facultatif)</span></label><textarea id="resumeComment" name="commentaire" class="form-control mb-3" rows="3" placeholder="Le document ou la précision attendue a été reçu…"></textarea><button class="btn btn-primary w-100" type="submit"><i class="bi bi-play-circle me-1"></i>Reprendre l’examen</button></form></div></section><?php endif; ?>
                    <?php if ($canValidate): ?><div class="card section-card request-section-card">
                        <div class="card-header bg-transparent border-0 pt-4 px-4"><h3 class="h6 fw-bold mb-1">Décision requise</h3><div class="small text-muted">Vérifiez les informations avant de statuer.</div></div>
                        <div class="card-body px-4">
                            <form method="POST" action="<?= APP_URL ?>/treasury/demande-valider/<?= (int)$demande['id'] ?>" id="validationForm">
                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                <div class="mb-3"><label class="form-label fw-semibold">Niveau de décision</label><select class="form-select" name="approval_level" id="approvalLevel" required>
                                    <?php if ($canComptable): ?><option value="comptable">Comptable senior</option><?php endif; ?>
                                    <?php if ($canDirectionFin): ?><option value="direction_financiere">Direction financière</option><?php endif; ?>
                                    <?php if ($canDirectionGen): ?><option value="direction_generale">Direction générale</option><?php endif; ?>
                                </select></div>
                                <div class="mb-3"><label for="decisionField" class="form-label fw-semibold">Décision</label><select class="form-select" name="decision" id="decisionField" required><option value="valide">Validé</option><option value="en_suspens">En suspens</option><option value="annule">Annulé</option></select></div>
                                <div id="approvalAmountGroup" class="mb-3"><label for="approvedAmountField" class="form-label fw-semibold">Montant autorisé (<?= e($demande['currency']) ?>)</label><input id="approvedAmountField" type="number" class="form-control" name="approved_amount" min="0.01" max="<?= e(number_format($approvedAmount, 2, '.', '')) ?>" step="0.01" value="<?= e(number_format($approvedAmount, 2, '.', '')) ?>" required><div id="partialApprovalLabel" class="form-text">Validation totale du montant disponible.</div><div class="tr-partial-notice mt-2">Une validation partielle réduit le montant payable. Le solde non autorisé ne sera pas payé par cette demande ; une nouvelle demande sera nécessaire pour le réexaminer.</div></div>
                                <div id="reminderDateGroup" class="mb-3" hidden><label for="reminderDateField" class="form-label fw-semibold">Date de rappel <span class="text-danger">*</span></label><input id="reminderDateField" type="date" class="form-control" name="reminder_date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"><div class="form-text">La demande reste visible dans « En suspens » et un rappel sera adressé à ce niveau de décision.</div></div>
                                <div class="mb-3"><label class="form-label fw-semibold">Commentaire</label><textarea class="form-control" name="commentaire" id="commentaireField" rows="3" placeholder="Ajoutez une observation utile à la traçabilité"></textarea><div class="form-text" id="commentHelp">Facultatif pour une approbation.</div></div>
                                <button type="submit" class="btn btn-primary w-100" id="decisionButton"><i class="bi bi-shield-check me-1"></i>Revoir et confirmer</button>
                            </form>
                        </div>
                    </div><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canSubmit): ?>
<div class="modal fade request-modal" id="modalSoumettre" tabindex="-1" aria-labelledby="modalSoumettreLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="<?= APP_URL ?>/treasury/demande-voir/<?= (int)$demande['id'] ?>" class="modal-content js-confirm-form"><div class="modal-header"><div class="d-flex gap-3 align-items-center"><span class="request-modal-icon bg-primary-subtle text-primary"><i class="bi bi-send"></i></span><div><h5 class="modal-title fw-bold mb-1" id="modalSoumettreLabel">Soumettre la demande</h5><div class="small text-muted">Transmission au circuit d’approbation</div></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="action" value="soumettre"><div class="request-modal-summary"><div class="d-flex justify-content-between gap-3 mb-2"><span class="text-muted small">Référence</span><strong><?= e($demande['request_number']) ?></strong></div><div class="d-flex justify-content-between gap-3"><span class="text-muted small">Montant</span><strong class="text-primary"><?= formatMontant((float)$demande['amount'], $demande['currency']) ?></strong></div></div><p class="small text-muted mt-3 mb-0">Après confirmation, la demande sera transmise au Trésorier A pour contrôle, puis au Comptable senior.</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Retour</button><button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Confirmer la soumission</button></div></form></div></div>
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
    var approvedAmountField = document.getElementById('approvedAmountField');
    var reminderDateField = document.getElementById('reminderDateField');
    var amountLimit = <?= json_encode($approvedAmount) ?>;
    var requestCurrency = <?= json_encode($demande['currency']) ?>;
    function money(value) { return Number(value).toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ' + requestCurrency; }

    function refreshDecision() {
        if (!decisionField || !commentField) return;
        var valid = decisionField.value === 'valide';
        var paused = decisionField.value === 'en_suspens';
        var partial = valid && Number(approvedAmountField.value) < amountLimit;
        document.getElementById('approvalAmountGroup').hidden = !valid;
        approvedAmountField.disabled = !valid;
        approvedAmountField.required = valid;
        document.getElementById('reminderDateGroup').hidden = !paused;
        reminderDateField.disabled = !paused;
        reminderDateField.required = paused;
        commentField.required = !valid || partial;
        commentHelp.textContent = paused ? 'Indiquez le motif et les éléments attendus avant la reprise.' : (!valid ? 'Un motif est obligatoire pour annuler la demande.' : (partial ? 'Justifiez la réduction du montant. Le solde ne sera pas payable sur cette demande.' : 'Facultatif pour une validation totale.'));
        document.getElementById('partialApprovalLabel').textContent = partial ? 'Validation partielle · ' + money(amountLimit - Number(approvedAmountField.value)) + ' non autorisés à cette étape.' : 'Validation totale du montant disponible.';
    }
    if (decisionField) {
        decisionField.addEventListener('change', refreshDecision);
        approvedAmountField.addEventListener('input', refreshDecision);
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
            var cancelled = decisionField.value === 'annule';
            var paused = decisionField.value === 'en_suspens';
            var partial = !cancelled && !paused && Number(approvedAmountField.value) < amountLimit;
            var tone = cancelled ? 'danger' : (paused ? 'warning' : 'success');
            var decisionLabel = cancelled ? 'Annuler la demande' : (paused ? 'Mettre en suspens · rappel le ' + reminderDateField.value.split('-').reverse().join('/') : (partial ? 'Validation partielle · ' : 'Validation totale · ') + money(approvedAmountField.value));
            var level = document.getElementById('approvalLevel');
            document.getElementById('decisionLevelSummary').textContent = level.options[level.selectedIndex].text;
            document.getElementById('decisionSummary').textContent = decisionLabel;
            document.getElementById('decisionSummary').className = 'text-' + tone;
            document.getElementById('decisionCommentSummary').textContent = commentField.value.trim() ? 'Commentaire : ' + commentField.value.trim() : 'Aucun commentaire complémentaire.';
            var icon = document.getElementById('decisionModalIcon');
            icon.className = 'request-modal-icon bg-' + tone + '-subtle text-' + tone;
            icon.innerHTML = '<i class="bi ' + (cancelled ? 'bi-x-octagon' : (paused ? 'bi-pause-circle' : 'bi-check2-circle')) + '"></i>';
            document.getElementById('modalDecisionLabel').textContent = cancelled ? 'Confirmer l’annulation' : (paused ? 'Confirmer la mise en suspens' : 'Confirmer la validation');
            var button = document.getElementById('confirmDecisionButton');
            button.className = 'btn btn-' + tone;
            button.textContent = 'Confirmer la décision';
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

<div class="modal fade" id="requestPdfModal" tabindex="-1" aria-labelledby="requestPdfModalTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:95vw"><div class="modal-content tr-modal"><div class="modal-header"><div><div class="tr-eyebrow mb-1">Document de la demande</div><h2 class="modal-title h5" id="requestPdfModalTitle"><?= e($demande['request_number']) ?> · Fiche d’achat / fonds</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div><div class="modal-body p-0"><iframe class="tr-pdf-frame" id="requestPdfFrame" data-src="<?= APP_URL ?>/treasury/demande-pdf/<?= (int)$demande['id'] ?>" title="Aperçu de la fiche de demande de fonds"></iframe></div><div class="modal-footer"><span class="small text-muted me-auto">Si l’aperçu ne s’affiche pas, ouvrez le document dans un autre onglet.</span><a href="<?= APP_URL ?>/treasury/demande-pdf/<?= (int)$demande['id'] ?>?download=1" class="btn btn-primary"><i class="bi bi-download me-1"></i>Télécharger le PDF</a><a href="<?= APP_URL ?>/treasury/demande-pdf/<?= (int)$demande['id'] ?>" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Pleine taille</a><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button></div></div></div></div>
<script>document.getElementById('requestPdfModal').addEventListener('show.bs.modal', function () { var frame = document.getElementById('requestPdfFrame'); if (!frame.getAttribute('src')) frame.src = frame.dataset.src; });</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
