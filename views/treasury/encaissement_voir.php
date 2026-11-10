<?php $pageTitle = 'Recu ' . e($encaissement['receipt_number']) . ' - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>

<?php
$encaissementSwal = $_SESSION['encaissement_swal'] ?? null;
unset($_SESSION['encaissement_swal']);

$versements = $versements ?? [];
$etapes = $etapes ?? [];
$droits = $droits ?? [];
$comptes = $comptes ?? [];
$statut = (string)$encaissement['status'];
$estRecouvre = in_array($statut, ['recouvre', 'valide'], true);
$estAnnule = $statut === 'annule';
// Classe visuelle : recouvré = vert, annulé = rouge, toute étape en cours = ambre.
$statusClass = $estRecouvre ? 'valide' : ($estAnnule ? 'annule' : 'brouillon');
$statusLabel = ['brouillon' => 'Constaté', 'constate' => 'Constaté', 'liquide' => 'Liquidé', 'ordonnance' => 'Ordonnancé', 'partiellement_recouvre' => 'Partiellement recouvré', 'recouvre' => 'Recouvré', 'valide' => 'Recouvré', 'annule' => 'Annulé'][$statut] ?? treasuryUiLabel($statut);
$methodLabel = !empty($encaissement['payment_method']) ? treasuryUiLabel((string)$encaissement['payment_method']) : 'Déterminé au recouvrement';
$reste = max(0, (float)$encaissement['amount'] - (float)$encaissement['amount_collected']);
$circuit = [
    'constatation' => ['Constatation', 'bi-journal-plus', $encaissement['created_by_nom'] ?? null, $encaissement['created_at'] ?? null],
    'liquidation' => ['Liquidation', 'bi-calculator', $encaissement['liquidated_by_nom'] ?? null, $encaissement['liquidated_at'] ?? null],
    'ordonnancement' => ['Ordonnancement', 'bi-file-earmark-text', $encaissement['ordered_by_nom'] ?? null, $encaissement['ordered_at'] ?? null],
    'recouvrement' => ['Recouvrement', 'bi-cash-coin', null, $encaissement['recovered_at'] ?? null],
];
$rang = ['brouillon' => 1, 'constate' => 1, 'liquide' => 2, 'ordonnance' => 3, 'partiellement_recouvre' => 3, 'recouvre' => 4, 'valide' => 4, 'annule' => 0][$statut] ?? 0;
$aUneAction = !empty($droits['liquider']) || !empty($droits['ordonnancer']) || !empty($droits['recouvrer']) || !empty($droits['renvoyer']);
?>

<style>
.receipt-document {
    position: relative;
    overflow: hidden;
    color: #17283e;
    background: #fff;
}
.receipt-document::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 7px;
    background: linear-gradient(90deg, #0b2847 0 34%, #1769e0 34% 78%, #1a9a5b 78%);
}
.receipt-brand { min-width: 0; }
.receipt-brand-logo { display: block; width: 288px; max-width: 100%; height: auto; }
.receipt-company-meta { margin-top: 7px; color: #65758a; font-size: .7rem; line-height: 1.5; }
.receipt-doc-type { color: #1769e0; font-size: .68rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
.receipt-number { color: #10243b; font-size: 1.38rem; font-weight: 800; letter-spacing: -.025em; }
.receipt-status { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 999px; font-size: .66rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
.receipt-status-valide { color: #126b3d; background: #e7f7ee; border: 1px solid #bce7cf; }
.receipt-status-brouillon { color: #8a5b00; background: #fff4d9; border: 1px solid #efd795; }
.receipt-status-annule { color: #a31f2b; background: #fdebed; border: 1px solid #f4bfc5; }
.receipt-rule { height: 1px; margin: 22px 0; background: #dfe6ef; }
.receipt-section-title { margin-bottom: 10px; color: #64758a; font-size: .63rem; font-weight: 800; letter-spacing: .105em; text-transform: uppercase; }
.receipt-info-grid { display: grid; grid-template-columns: 1.6fr 1fr 1fr; border: 1px solid #dfe6ef; border-radius: 8px; overflow: hidden; }
.receipt-info-cell { min-height: 74px; padding: 13px 15px; border-right: 1px solid #dfe6ef; }
.receipt-info-cell:last-child { border-right: 0; }
.receipt-field-label { margin-bottom: 5px; color: #718096; font-size: .63rem; font-weight: 700; text-transform: uppercase; letter-spacing: .055em; }
.receipt-field-value { color: #17283e; font-size: .82rem; font-weight: 700; }
.receipt-object { padding: 14px 15px; border-left: 3px solid #1769e0; background: #f7f9fc; }
.receipt-amount-panel { display: grid; grid-template-columns: 1.35fr 1fr 1fr; overflow: hidden; border: 1px solid #cbd9e9; border-radius: 9px; background: #f8fbff; }
.receipt-amount-main { padding: 18px; color: #fff; background: linear-gradient(135deg, #0b2847, #155590); }
.receipt-amount-main .receipt-field-label { color: #adc7e3; }
.receipt-amount-value { margin-top: 5px; font-size: 1.7rem; font-weight: 800; letter-spacing: -.04em; white-space: nowrap; }
.receipt-amount-side { padding: 18px; border-right: 1px solid #dfe6ef; }
.receipt-amount-side:last-child { border-right: 0; }
.receipt-reference-table { width: 100%; border-collapse: collapse; font-size: .75rem; }
.receipt-reference-table th, .receipt-reference-table td { padding: 9px 0; border-bottom: 1px solid #edf1f5; }
.receipt-reference-table th { width: 34%; color: #718096; font-size: .64rem; font-weight: 700; letter-spacing: .045em; text-transform: uppercase; }
.receipt-reference-table td { color: #253950; font-weight: 600; }
.receipt-notes { padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 7px; color: #44566c; background: #fafbfd; font-size: .73rem; }
.receipt-signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; margin-top: 58px; }
.receipt-signature { padding-top: 8px; border-top: 1px solid #8898aa; color: #5e6f82; font-size: .67rem; text-align: center; }
.receipt-document-footer { display: flex; justify-content: space-between; margin-top: 42px; padding-top: 10px; border-top: 1px solid #dfe6ef; color: #8290a0; font-size: .58rem; }
.receipt-flow { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
.receipt-flow-step { padding:12px 14px; border:1px solid #dce4ed; border-radius:10px; background:#fff; color:#6b7c90; font-size:.78rem; }
.receipt-flow-step strong { display:block; color:#1d3046; font-size:.85rem; }
.receipt-flow-step.is-done { border-color:#bce7cf; background:#f1fbf5; }
.receipt-flow-step.is-done strong { color:#126b3d; }
.receipt-flow-step.is-current { border-color:#1769e0; box-shadow:0 0 0 3px rgba(23,105,224,.12); }
.receipt-action-panel { border:1px solid #dce4ed; border-radius:12px; background:#fff; }
.receipt-steps-log { list-style:none; margin:0; padding:0; }
.receipt-steps-log li { padding:9px 0; border-bottom:1px solid #edf1f5; font-size:.8rem; }
.receipt-steps-log li:last-child { border-bottom:0; }
@media (max-width: 767.98px) { .receipt-flow { grid-template-columns:1fr 1fr; } }
.receipt-watermark { position: absolute; top: 47%; left: 50%; z-index: 0; transform: translate(-50%,-50%) rotate(-25deg); color: rgba(207,53,66,.075); font-size: 5rem; font-weight: 900; letter-spacing: .12em; pointer-events: none; }
.receipt-document > *:not(.receipt-watermark) { position: relative; z-index: 1; }

@media (max-width: 767.98px) {
    .receipt-header { flex-direction: column; }
    .receipt-header-meta { width: 100%; text-align: left !important; }
}

@media print {
    @page { size: A4 portrait; margin: 12mm; }
    * { print-color-adjust: exact !important; -webkit-print-color-adjust: exact !important; }
    .app-topbar,
    .app-sidebar,
    .app-sidebar-offcanvas,
    .app-footer,
    .no-print {
        display: none !important;
    }
    .app-layout {
        margin-top: 0 !important;
        display: block !important;
    }
    .app-main {
        padding: 0 !important;
        min-height: auto !important;
    }
    .receipt-print-card {
        width: 100% !important;
        max-width: none !important;
        border: 0 !important;
        box-shadow: none !important;
    }
    .receipt-print-card .card-body { padding: 0 !important; }
    .receipt-document { min-height: 267mm; padding: 14mm 13mm 10mm !important; border: 1px solid #cfd9e5 !important; border-radius: 0 !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .receipt-document::before { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .receipt-amount-main, .receipt-status, .receipt-object { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .container-fluid, .row, .col-lg-9 { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
    .receipt-signatures { margin-top: 68px; }
    a[href]::after { content: none !important; }
    html, body {
        width: 210mm;
        min-height: 297mm;
        background: #fff !important;
    }
}

@media (max-width: 767.98px) {
    .receipt-info-grid, .receipt-amount-panel { grid-template-columns: 1fr; }
    .receipt-info-cell, .receipt-amount-side { border-right: 0; border-bottom: 1px solid #dfe6ef; }
    .receipt-signatures { gap: 24px; }
}
</style>

<?php if (!empty($encaissementSwal)): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: <?= json_encode($encaissementSwal['icon'] ?? 'success') ?>,
        title: <?= json_encode($encaissementSwal['title'] ?? 'Opération réussie', JSON_UNESCAPED_UNICODE) ?>,
        text: <?= json_encode($encaissementSwal['text'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        confirmButtonText: 'Voir le reçu',
        confirmButtonColor: '#1769e0',
        timer: 4200,
        timerProgressBar: true,
        customClass: { popup: 'rounded-3' }
    });
});
</script>
<?php endif; ?>

<div class="container-fluid">
    <div class="page-hero no-print">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-0">
            <div class="d-flex align-items-center">
                <a href="<?= APP_URL ?>/treasury/encaissements" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i></a>
                <div>
                    <h2 class="fw-bold mb-1"><i class="bi bi-receipt me-2 text-success"></i><?= e($encaissement['receipt_number']) ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/dashboard">Trésorerie</a></li>
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/encaissements">Encaissements</a></li>
                            <li class="breadcrumb-item active">Recu</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="ouvrirApercuRecu()">
                    <i class="bi bi-printer me-1"></i>Imprimer
                </button>
                <a class="btn btn-primary" href="<?= APP_URL ?>/treasury/encaissement-pdf/<?= (int)$encaissement['id'] ?>?download=1">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Télécharger en PDF
                </a>
                <?php if (!empty($encaissement['title_number']) && (float)$encaissement['amount_collected'] > 0): ?>
                    <a class="btn btn-outline-primary" href="<?= APP_URL ?>/treasury/encaissement-pdf/<?= (int)$encaissement['id'] ?>?doc=titre&amp;download=1"><i class="bi bi-file-earmark-text me-1"></i>Titre de recette</a>
                <?php endif; ?>
                <?php if (!empty($droits['annuler'])): ?>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAnnulerEncaissement">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <?php if (!$estAnnule): ?>
    <div class="receipt-flow mb-3 no-print" aria-label="Circuit de l’encaissement">
        <?php $i = 0; foreach ($circuit as $code => [$libelle, $icone, $par, $le]): $i++; ?>
            <div class="receipt-flow-step <?= $i <= $rang ? 'is-done' : ($i === $rang + 1 ? 'is-current' : '') ?>">
                <strong><i class="bi <?= $icone ?> me-1"></i><?= $i ?>. <?= e($libelle) ?></strong>
                <?php if ($i <= $rang): ?>
                    <?= $par ? e($par) : '' ?><?= $le ? ($par ? ' · ' : '') . e(date('d/m/Y', strtotime($le))) : '' ?>
                <?php elseif ($code === 'recouvrement' && $statut === 'partiellement_recouvre'): ?>
                    Partiel · reste <?= formatMontant($reste, $encaissement['currency']) ?>
                <?php elseif ($i === $rang + 1): ?>
                    En attente
                <?php else: ?>
                    À venir
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($encaissement['is_cash_sale'])): ?><div class="alert alert-info small no-print py-2"><i class="bi bi-cash-stack me-1"></i>Encaissement comptant : les 4 étapes ont été enregistrées en une seule opération. Il est à contrôler a posteriori.</div><?php endif; ?>
    <?php if (!empty($droits['separation'])): ?><div class="alert alert-secondary small no-print py-2"><i class="bi bi-shield-lock me-1"></i>Séparation des tâches : <?= e($droits['separation']) ?></div><?php endif; ?>

    <?php if ($aUneAction): ?>
    <section class="receipt-action-panel p-3 p-md-4 mb-4 no-print" aria-label="Action attendue">
        <?php if (!empty($droits['liquider'])): ?>
            <h2 class="h6 fw-bold mb-1">Étape 2 · Liquider la créance</h2>
            <p class="small text-muted">Arrêtez le montant définitivement dû. Un écart avec le montant constaté (<?= formatMontant((float)($encaissement['estimated_amount'] ?? $encaissement['amount']), $encaissement['currency']) ?>) doit être motivé.</p>
            <form method="POST" action="<?= APP_URL ?>/treasury/encaissement-voir/<?= (int)$encaissement['id'] ?>" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="action" value="liquider">
                <div class="col-md-3"><label class="form-label small fw-semibold" for="liqAmount">Montant liquidé (<?= e($encaissement['currency']) ?>) *</label><input id="liqAmount" type="number" name="amount" class="form-control" min="0.01" step="0.01" value="<?= e((string)(float)$encaissement['amount']) ?>" required></div>
                <div class="col-md-6"><label class="form-label small fw-semibold" for="liqComment">Observation <span class="text-muted fw-normal">(obligatoire en cas d’écart)</span></label><input id="liqComment" name="commentaire" class="form-control" maxlength="1000"></div>
                <div class="col-md-3 d-grid"><button class="btn btn-primary" onclick="return confirm('Liquider cette créance ?')"><i class="bi bi-calculator me-1"></i>Liquider</button></div>
            </form>
        <?php endif; ?>
        <?php if (!empty($droits['ordonnancer'])): ?>
            <h2 class="h6 fw-bold mb-1">Étape 3 · Ordonnancer — émettre le titre de recette</h2>
            <p class="small text-muted">Montant liquidé : <strong><?= formatMontant((float)$encaissement['amount'], $encaissement['currency']) ?></strong><?= !empty($encaissement['liquidation_comment']) ? ' · ' . e($encaissement['liquidation_comment']) : '' ?>. Un titre de recette numéroté sera émis.</p>
            <form method="POST" action="<?= APP_URL ?>/treasury/encaissement-voir/<?= (int)$encaissement['id'] ?>" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="action" value="ordonnancer">
                <div class="col-md-3"><label class="form-label small fw-semibold" for="ordDue">Échéance de paiement</label><input id="ordDue" type="date" name="due_date" class="form-control" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>"></div>
                <div class="col-md-6"><label class="form-label small fw-semibold" for="ordComment">Observation</label><input id="ordComment" name="commentaire" class="form-control" maxlength="1000"></div>
                <div class="col-md-3 d-grid"><button class="btn btn-primary" onclick="return confirm('Émettre le titre de recette ?')"><i class="bi bi-file-earmark-text me-1"></i>Ordonnancer</button></div>
            </form>
        <?php endif; ?>
        <?php if (!empty($droits['recouvrer'])): ?>
            <h2 class="h6 fw-bold mb-1">Étape 4 · Enregistrer un recouvrement</h2>
            <p class="small text-muted">Titre <?= e($encaissement['title_number'] ?? '') ?> · reste à recouvrer : <strong><?= formatMontant($reste, $encaissement['currency']) ?></strong>. Un versement partiel est possible ; l’écriture est passée sur le compte crédité.</p>
            <form method="POST" action="<?= APP_URL ?>/treasury/encaissement-voir/<?= (int)$encaissement['id'] ?>" enctype="multipart/form-data" class="row g-2 align-items-end" id="recoveryForm">
                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="action" value="recouvrer">
                <div class="col-md-2"><label class="form-label small fw-semibold" for="recAmount">Montant (<?= e($encaissement['currency']) ?>) *</label><input id="recAmount" type="number" name="amount" class="form-control" min="0.01" max="<?= e((string)$reste) ?>" step="0.01" value="<?= e((string)$reste) ?>" required></div>
                <div class="col-md-2"><label class="form-label small fw-semibold" for="recDate">Date *</label><input id="recDate" type="date" name="collection_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-md-2"><label class="form-label small fw-semibold" for="recMethod">Mode *</label><select id="recMethod" name="payment_method" class="form-select" required><option value="">Choisir</option><?php foreach (['especes' => 'Espèces', 'banque' => 'Banque', 'mobile_money' => 'Mobile money'] as $code => $nom): ?><option value="<?= $code ?>"><?= $nom ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label small fw-semibold" for="recAccount">Compte crédité *</label><select id="recAccount" name="account_id" class="form-select" required><option value="">Choisir un compte</option><?php foreach ($comptes as $compte): if (($compte['devise'] ?? '') !== $encaissement['currency'] || empty($compte['actif'])) continue; ?><option value="<?= (int)$compte['id'] ?>" data-type="<?= e($compte['type_compte']) ?>"><?= e($compte['nom']) ?> (<?= e($compte['devise']) ?>)</option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label small fw-semibold" for="recRef">Référence</label><input id="recRef" name="reference_payment" class="form-control" maxlength="120" placeholder="Bordereau, transaction…"></div>
                <div class="col-md-6"><label class="form-label small fw-semibold" for="recProof">Justificatif</label><input id="recProof" type="file" name="proof_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></div>
                <div class="col-md-3 offset-md-3 d-grid"><button class="btn btn-success" onclick="return confirm('Enregistrer ce recouvrement et comptabiliser la recette ?')"><i class="bi bi-cash-coin me-1"></i>Recouvrer</button></div>
            </form>
            <script>
            (function(){var m=document.getElementById('recMethod'),a=document.getElementById('recAccount');if(!m||!a)return;m.addEventListener('change',function(){var t={especes:'caisse',banque:'banque',mobile_money:'mobile_money'}[m.value];Array.from(a.options).forEach(function(o){if(!o.value)return;var off=!!t&&o.dataset.type!==t;o.hidden=off;o.disabled=off;});if(a.selectedOptions[0]&&a.selectedOptions[0].disabled)a.value='';});})();
            </script>
        <?php endif; ?>
        <?php if (!empty($droits['renvoyer'])): ?>
            <div class="border-top mt-3 pt-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#renvoiForm"><i class="bi bi-arrow-counterclockwise me-1"></i>Renvoyer à l’étape précédente</button>
                <form id="renvoiForm" class="collapse mt-2" method="POST" action="<?= APP_URL ?>/treasury/encaissement-voir/<?= (int)$encaissement['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="action" value="renvoyer">
                    <div class="input-group"><input name="commentaire" class="form-control" placeholder="Motif du renvoi (obligatoire)" required maxlength="1000"><button class="btn btn-outline-danger">Renvoyer</button></div>
                </form>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card section-card receipt-print-card">
                <div class="card-body p-3 p-md-4">
                    <article class="receipt-document p-4 p-md-5" aria-label="Reçu officiel d'encaissement">
                    <?php if (!$estRecouvre): ?><div class="receipt-watermark"><?= e(mb_strtoupper($statusLabel)) ?></div><?php endif; ?>

                    <header class="receipt-header d-flex justify-content-between align-items-start gap-4 pt-2">
                        <div class="receipt-brand">
                            <img src="<?= APP_URL ?>/assets/images/logo/logo_amm.jpg" alt="AMM" class="receipt-brand-logo">
                            <div class="receipt-company-meta">Vente et fourniture d’énergie électrique<br>Lubumbashi, République Démocratique du Congo</div>
                        </div>
                        <div class="receipt-header-meta text-end flex-shrink-0"><div class="receipt-doc-type"><?= (float)$encaissement['amount_collected'] > 0 ? 'Reçu d’encaissement' : (!empty($encaissement['title_number']) ? 'Titre de recette' : 'Fiche de créance') ?></div><div class="receipt-number"><?= e($encaissement['receipt_number']) ?></div><?php if (!empty($encaissement['title_number'])): ?><div class="small text-muted mb-1">Titre <?= e($encaissement['title_number']) ?></div><?php endif; ?><span class="receipt-status receipt-status-<?= e($statusClass) ?>"><i class="bi bi-<?= $estRecouvre?'check-circle-fill':($estAnnule?'x-circle-fill':'clock-fill') ?>"></i><?= e($statusLabel) ?></span></div>
                    </header>

                    <div class="receipt-rule"></div>
                    <div class="receipt-section-title">Informations de l’encaissement</div>
                    <div class="receipt-info-grid mb-4">
                        <div class="receipt-info-cell"><div class="receipt-field-label">Débiteur</div><div class="receipt-field-value fs-6"><?= e($encaissement['payer_name']) ?></div></div>
                        <div class="receipt-info-cell"><div class="receipt-field-label">Date de constatation</div><div class="receipt-field-value"><?= formatDate($encaissement['date_receipt']) ?></div></div>
                        <div class="receipt-info-cell"><div class="receipt-field-label">Mode de paiement</div><div class="receipt-field-value"><?= e($methodLabel) ?></div></div>
                    </div>

                    <div class="receipt-section-title">Objet du paiement</div>
                    <div class="receipt-object mb-4"><div class="receipt-field-value"><?= e($encaissement['objet']) ?></div></div>

                    <div class="receipt-section-title">Montants</div>
                    <div class="receipt-amount-panel mb-4">
                        <div class="receipt-amount-main"><div class="receipt-field-label"><?= in_array($statut, ['constate','brouillon'], true) ? 'Montant constaté' : 'Montant liquidé' ?></div><div class="receipt-amount-value"><?= formatMontant((float)$encaissement['amount'], $encaissement['currency']) ?></div><div class="small mt-1" style="color:#adc7e3"><?= formatMontant((float)$encaissement['amount_usd'], 'USD') ?></div></div>
                        <div class="receipt-amount-side"><div class="receipt-field-label">Recouvré</div><div class="receipt-field-value fs-6 text-success"><?= formatMontant((float)$encaissement['amount_collected'], $encaissement['currency']) ?></div><small class="text-muted">Reste : <?= formatMontant($estAnnule ? 0 : $reste, $encaissement['currency']) ?></small></div>
                        <div class="receipt-amount-side"><div class="receipt-field-label">Compte crédité</div><div class="receipt-field-value"><?= e($encaissement['account_nom'] ?? '—') ?></div><small class="text-muted"><?= e(ucfirst(str_replace('_',' ',(string)($encaissement['type_compte'] ?? '')))) ?></small></div>
                    </div>

                    <div class="receipt-section-title">Traçabilité et références</div>
                    <table class="receipt-reference-table mb-4">
                        <tr>
                            <th>Référence paiement</th>
                            <td><?= e($encaissement['reference_payment'] ?: '—') ?></td>
                        </tr>
                        <tr>
                            <th>Taux CDF/USD</th>
                            <td><?= !empty($encaissement['exchange_rate']) ? e($encaissement['exchange_rate']) : '—' ?></td>
                        </tr>
                        <tr>
                            <th>Enregistré par</th>
                            <td><?= e($encaissement['created_by_nom'] ?? '—') ?></td>
                        </tr>
                        <?php if (!empty($encaissement['category_nom'])): ?><tr><th>Catégorie de recette</th><td><?= e($encaissement['category_nom']) ?></td></tr><?php endif; ?>
                        <tr><th>Liquidé par</th><td><?= e($encaissement['liquidated_by_nom'] ?? '—') ?><?php if (!empty($encaissement['liquidated_at'])): ?><span class="text-muted"> · <?= e(date('d/m/Y à H:i', strtotime($encaissement['liquidated_at']))) ?></span><?php endif; ?><?php if (!empty($encaissement['liquidation_comment'])): ?><div class="small text-muted"><?= e($encaissement['liquidation_comment']) ?></div><?php endif; ?></td></tr>
                        <tr><th>Ordonnancé par</th><td><?= e($encaissement['ordered_by_nom'] ?? '—') ?><?php if (!empty($encaissement['ordered_at'])): ?><span class="text-muted"> · <?= e(date('d/m/Y à H:i', strtotime($encaissement['ordered_at']))) ?></span><?php endif; ?></td></tr>
                        <?php if (!empty($encaissement['due_date'])): ?><tr><th>Échéance</th><td><?= formatDate($encaissement['due_date']) ?><?php if (!$estRecouvre && !$estAnnule && $encaissement['due_date'] < date('Y-m-d')): ?> <span class="badge bg-danger ms-1">Échue</span><?php endif; ?></td></tr><?php endif; ?>
                        <?php if (!empty($encaissement['financial_transaction_id'])): ?>
                            <tr>
                                <th>Écriture financière</th>
                                <td><a href="<?= APP_URL ?>/finance/transactions?transaction_id=<?= (int)$encaissement['financial_transaction_id'] ?>&amp;annee=0&amp;mois=0">Transaction #<?= (int)$encaissement['financial_transaction_id'] ?> · Consulter</a></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($encaissement['status'] === 'annule'): ?>
                            <tr>
                                <th>Annulé par</th>
                                <td>
                                    <?= e($encaissement['cancelled_by_nom'] ?? '—') ?>
                                    <?php if (!empty($encaissement['cancelled_at'])): ?>
                                        <span class="text-muted small">le <?= e(date('d/m/Y H:i', strtotime($encaissement['cancelled_at']))) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Motif d’annulation</th>
                                <td><?= nl2br(e($encaissement['cancellation_reason'] ?? '—')) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <?php if (!empty($versements)): ?>
                    <div class="receipt-section-title">Versements</div>
                    <table class="receipt-reference-table mb-4">
                        <?php foreach ($versements as $v): ?>
                        <tr>
                            <th><?= e($v['collection_number']) ?></th>
                            <td><?= formatDate($v['collection_date']) ?> · <?= formatMontant((float)$v['amount'], $v['currency']) ?> · <?= e(treasuryUiLabel((string)$v['payment_method'])) ?> · <?= e($v['account_nom'] ?? '—') ?><?= !empty($v['reference_payment']) ? ' · réf. ' . e($v['reference_payment']) : '' ?>
                                <?php if ($v['status'] === 'annule'): ?><span class="badge bg-danger ms-1">Annulé</span><?php endif; ?>
                                <?php if (!empty($v['financial_transaction_id'])): ?><a class="no-print small ms-1" href="<?= APP_URL ?>/finance/transactions?transaction_id=<?= (int)$v['financial_transaction_id'] ?>&amp;annee=0&amp;mois=0">Écriture #<?= (int)$v['financial_transaction_id'] ?></a><?php endif; ?>
                                <?php if (!empty($v['proof_file'])): ?><a class="no-print small ms-1" href="<?= UPLOAD_URL . e($v['proof_file']) ?>" target="_blank" rel="noopener">Justificatif</a><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>

                    <?php if (!empty($encaissement['notes'])): ?><div class="receipt-section-title">Observations</div><div class="receipt-notes mb-4"><?= nl2br(e($encaissement['notes'])) ?></div><?php endif; ?>

                    <?php if (!empty($encaissement['proof_file'])): ?>
                        <div class="no-print mb-4">
                            <a href="<?= UPLOAD_URL . e($encaissement['proof_file']) ?>" target="_blank" class="btn btn-outline-primary">
                                <i class="bi bi-paperclip me-1"></i>Voir la preuve
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="receipt-signatures"><div class="receipt-signature">Signature du payeur</div><div class="receipt-signature">Trésorier · Signature et cachet AMM</div></div>
                    <footer class="receipt-document-footer"><span>Document généré par AMM-ERP · <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></span><span>Imprimé le <?= date('d/m/Y à H:i') ?> · Page 1/1</span></footer>
                    </article>
                </div>
            </div>

            <?php if (!empty($etapes)): ?>
            <section class="card section-card mt-3 no-print"><div class="card-body p-4">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-clock-history me-1"></i>Journal des étapes</h2>
                <ul class="receipt-steps-log">
                    <?php foreach ($etapes as $etape): ?>
                    <li><div class="d-flex justify-content-between gap-2 flex-wrap"><strong><?= e(treasuryUiLabel($etape['step'])) ?></strong><span class="text-muted small"><?= e(date('d/m/Y à H:i', strtotime($etape['created_at']))) ?> · <?= e($etape['user_nom'] ?? '—') ?></span></div>
                        <?php if ($etape['amount'] !== null): ?><div class="small"><?= formatMontant((float)$etape['amount'], $encaissement['currency']) ?></div><?php endif; ?>
                        <?php if (!empty($etape['comment'])): ?><div class="small text-muted"><?= e($etape['comment']) ?></div><?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div></section>
            <?php endif; ?>

            <div class="no-print mt-3">
                <div class="alert alert-info d-flex align-items-start gap-2 py-2 mb-2">
                    <i class="bi bi-info-circle-fill mt-1"></i>
                    <div class="small"><strong>Pour obtenir le PDF :</strong> cliquez sur « Télécharger en PDF », puis choisissez <strong>Enregistrer au format PDF</strong> comme destination. Le fichier sera proposé sous le nom <strong><?= e($encaissement['receipt_number']) ?>.pdf</strong>.</div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= APP_URL ?>/treasury/encaissements" class="btn btn-outline-secondary">Retour</a>
                    <button type="button" class="btn btn-outline-secondary" onclick="ouvrirApercuRecu()"><i class="bi bi-printer me-1"></i>Imprimer</button>
                    <a class="btn btn-primary" href="<?= APP_URL ?>/treasury/encaissement-pdf/<?= (int)$encaissement['id'] ?>?download=1"><i class="bi bi-file-earmark-pdf me-1"></i>Télécharger en PDF</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($droits['annuler'])): ?>
    <div class="modal fade" id="modalAnnulerEncaissement" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="<?= APP_URL ?>/treasury/encaissement-annuler/<?= (int)$encaissement['id'] ?>" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Annuler l'encaissement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                    <label class="form-label fw-semibold">Motif d’annulation <span class="text-danger">*</span></label>
                    <textarea name="motif" class="form-control" rows="4" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="submit" class="btn btn-danger">Confirmer l’annulation</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="modalApercuRecu" tabindex="-1" aria-labelledby="modalApercuRecuLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="height:min(92vh,920px)">
            <div class="modal-header py-2">
                <div><h5 class="modal-title" id="modalApercuRecuLabel"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>Aperçu du reçu <?= e($encaissement['receipt_number']) ?></h5><small class="text-muted">Document PDF officiel prêt pour impression</small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-0 bg-secondary bg-opacity-10 position-relative">
                <div id="pdfPreviewLoader" class="position-absolute top-50 start-50 translate-middle text-center"><div class="spinner-border text-primary mb-2" role="status"></div><div class="small text-muted">Génération de l’aperçu PDF…</div></div>
                <iframe id="recuPdfFrame" title="Aperçu PDF du reçu" class="w-100 h-100 border-0" data-src="<?= APP_URL ?>/treasury/encaissement-pdf/<?= (int)$encaissement['id'] ?>#toolbar=1&navpanes=0&view=FitH"></iframe>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                <a class="btn btn-outline-primary" href="<?= APP_URL ?>/treasury/encaissement-pdf/<?= (int)$encaissement['id'] ?>?download=1"><i class="bi bi-download me-1"></i>Télécharger</a>
                <button type="button" class="btn btn-primary" onclick="imprimerPdfRecu()"><i class="bi bi-printer me-1"></i>Imprimer le PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modalElement = document.getElementById('modalApercuRecu');
    const frame = document.getElementById('recuPdfFrame');
    const loader = document.getElementById('pdfPreviewLoader');
    if (frame) frame.addEventListener('load', function () { if (loader) loader.classList.add('d-none'); });

    window.ouvrirApercuRecu = function () {
        if (!frame.getAttribute('src')) frame.setAttribute('src', frame.dataset.src);
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    };
    window.imprimerPdfRecu = function () {
        if (!frame || !frame.contentWindow) return;
        try { frame.contentWindow.focus(); frame.contentWindow.print(); }
        catch (error) { window.open(frame.dataset.src, '_blank', 'noopener'); }
    };
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
