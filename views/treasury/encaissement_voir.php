<?php $pageTitle = 'Recu ' . e($encaissement['receipt_number']) . ' - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>

<?php
$encaissementSwal = $_SESSION['encaissement_swal'] ?? null;
unset($_SESSION['encaissement_swal']);

$statusBadge = 'secondary';
if ($encaissement['status'] === 'valide') $statusBadge = 'success';
if ($encaissement['status'] === 'brouillon') $statusBadge = 'warning text-dark';
if ($encaissement['status'] === 'annule') $statusBadge = 'danger';

$methodLabel = $encaissement['payment_method'];
if ($methodLabel === 'especes') $methodLabel = 'Especes';
if ($methodLabel === 'banque') $methodLabel = 'Banque';
if ($methodLabel === 'mobile_money') $methodLabel = 'Mobile money';
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
                <?php if ($encaissement['status'] === 'brouillon'): ?>
                    <form method="POST" action="<?= APP_URL ?>/treasury/encaissement-voir/<?= (int)$encaissement['id'] ?>" onsubmit="return confirm('Valider cet encaissement ?')">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                        <input type="hidden" name="action" value="valider">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i>Valider
                        </button>
                    </form>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAnnulerEncaissement">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card section-card receipt-print-card">
                <div class="card-body p-3 p-md-4">
                    <article class="receipt-document p-4 p-md-5" aria-label="Reçu officiel d'encaissement">
                    <?php if ($encaissement['status'] !== 'valide'): ?><div class="receipt-watermark"><?= e(strtoupper(treasuryUiLabel($encaissement['status']))) ?></div><?php endif; ?>

                    <header class="receipt-header d-flex justify-content-between align-items-start gap-4 pt-2">
                        <div class="receipt-brand">
                            <img src="<?= APP_URL ?>/assets/images/logo/logo_amm.jpg" alt="AMM" class="receipt-brand-logo">
                            <div class="receipt-company-meta">Vente et fourniture d’énergie électrique<br>Lubumbashi, République Démocratique du Congo</div>
                        </div>
                        <div class="receipt-header-meta text-end flex-shrink-0"><div class="receipt-doc-type">Reçu d’encaissement</div><div class="receipt-number"><?= e($encaissement['receipt_number']) ?></div><span class="receipt-status receipt-status-<?= e($encaissement['status']) ?>"><i class="bi bi-<?= $encaissement['status']==='valide'?'check-circle-fill':($encaissement['status']==='annule'?'x-circle-fill':'clock-fill') ?>"></i><?= e(treasuryUiLabel($encaissement['status'])) ?></span></div>
                    </header>

                    <div class="receipt-rule"></div>
                    <div class="receipt-section-title">Informations de l’encaissement</div>
                    <div class="receipt-info-grid mb-4">
                        <div class="receipt-info-cell"><div class="receipt-field-label">Reçu de</div><div class="receipt-field-value fs-6"><?= e($encaissement['payer_name']) ?></div></div>
                        <div class="receipt-info-cell"><div class="receipt-field-label">Date de réception</div><div class="receipt-field-value"><?= formatDate($encaissement['date_receipt']) ?></div></div>
                        <div class="receipt-info-cell"><div class="receipt-field-label">Mode de paiement</div><div class="receipt-field-value"><?= e($methodLabel) ?></div></div>
                    </div>

                    <div class="receipt-section-title">Objet du paiement</div>
                    <div class="receipt-object mb-4"><div class="receipt-field-value"><?= e($encaissement['objet']) ?></div></div>

                    <div class="receipt-section-title">Montant encaissé</div>
                    <div class="receipt-amount-panel mb-4">
                        <div class="receipt-amount-main"><div class="receipt-field-label">Montant reçu</div><div class="receipt-amount-value"><?= formatMontant((float)$encaissement['amount'], $encaissement['currency']) ?></div></div>
                        <div class="receipt-amount-side"><div class="receipt-field-label">Contre-valeur USD</div><div class="receipt-field-value fs-6"><?= formatMontant((float)$encaissement['amount_usd'], 'USD') ?></div><?php if($encaissement['currency']==='USD'): ?><small class="text-muted">Devise de base</small><?php endif; ?></div>
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
                        <tr>
                            <th>Validation</th>
                            <td>
                                <?= e($encaissement['validated_by_nom'] ?? '—') ?>
                                <?php if (!empty($encaissement['validated_at'])): ?>
                                    <span class="text-muted"> · <?= e(date('d/m/Y à H:i', strtotime($encaissement['validated_at']))) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
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

<?php if ($encaissement['status'] === 'brouillon'): ?>
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
