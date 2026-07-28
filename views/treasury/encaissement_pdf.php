<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 24px 30px; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #17283e; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
    .document { position: relative; min-height: 760px; padding: 34px 38px 24px; border: 1px solid #cfd9e5; }
    .top-stripe { position: absolute; top: 0; left: 0; right: 0; height: 7px; background: #1769e0; }
    .top-stripe-dark { position: absolute; top: 0; left: 0; width: 34%; height: 7px; background: #0b2847; }
    .top-stripe-green { position: absolute; top: 0; right: 0; width: 22%; height: 7px; background: #1a9a5b; }
    .header-table, .info-table, .amount-table, .signature-table, .footer-table { width: 100%; border-collapse: collapse; }
    .brand { vertical-align: top; }
    .logo { display: block; width: 288px; height: 50px; }
    .company-meta { margin-top: 7px; color: #65758a; font-size: 8px; line-height: 1.45; }
    .document-meta { padding-top: 2px; text-align: right; vertical-align: top; }
    .document-type { color: #1769e0; font-size: 8px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
    .document-number { margin: 5px 0 6px; color: #10243b; font-size: 18px; font-weight: bold; }
    .status { display: inline-block; padding: 4px 9px; border-radius: 10px; font-size: 7px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
    .status-valide { color: #126b3d; background: #e7f7ee; border: 1px solid #bce7cf; }
    .status-brouillon { color: #8a5b00; background: #fff4d9; border: 1px solid #efd795; }
    .status-annule { color: #a31f2b; background: #fdebed; border: 1px solid #f4bfc5; }
    .rule { margin: 20px 0; border-top: 1px solid #dfe6ef; }
    .section-title { margin: 0 0 8px; color: #64758a; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
    .info-table { margin-bottom: 18px; border: 1px solid #dfe6ef; }
    .info-table td { height: 58px; padding: 10px 12px; border-right: 1px solid #dfe6ef; vertical-align: top; }
    .label { margin-bottom: 5px; color: #718096; font-size: 7px; font-weight: bold; text-transform: uppercase; }
    .value { color: #17283e; font-size: 10px; font-weight: bold; }
    .object { margin-bottom: 18px; padding: 11px 13px; border-left: 3px solid #1769e0; background: #f4f7fb; font-weight: bold; }
    .amount-table { margin-bottom: 18px; border: 1px solid #cbd9e9; }
    .amount-table td { height: 74px; padding: 12px 14px; vertical-align: middle; }
    .amount-main { width: 41%; color: #fff; background: #0b3c69; }
    .amount-main .label { color: #adc7e3; }
    .amount-value { margin-top: 5px; font-size: 19px; font-weight: bold; }
    .amount-side { width: 29.5%; border-right: 1px solid #dfe6ef; background: #f8fbff; }
    .reference-table { width: 100%; margin-bottom: 17px; border-collapse: collapse; }
    .reference-table th, .reference-table td { padding: 7px 0; border-bottom: 1px solid #edf1f5; text-align: left; }
    .reference-table th { width: 34%; color: #718096; font-size: 7px; text-transform: uppercase; }
    .reference-table td { color: #253950; font-size: 9px; font-weight: bold; }
    .notes { padding: 9px 11px; border: 1px solid #e2e8f0; background: #fafbfd; color: #44566c; font-size: 8px; }
    .signature-table { margin-top: 58px; }
    .signature-table td { width: 42%; padding-top: 8px; border-top: 1px solid #8898aa; color: #5e6f82; font-size: 8px; text-align: center; }
    .signature-table .gap { width: 16%; border: 0; }
    .footer-table { position: absolute; bottom: 17px; left: 38px; width: 670px; border-top: 1px solid #dfe6ef; }
    .footer-table td { padding-top: 7px; color: #8290a0; font-size: 7px; }
    .footer-right { text-align: right; }
    .watermark { position: absolute; top: 340px; left: 125px; width: 500px; color: #f7dfe2; font-size: 54px; font-weight: bold; letter-spacing: 7px; text-align: center; transform: rotate(-25deg); }
</style>
</head>
<body>
<?php
$methodLabelPdf = ['especes'=>'Espèces','banque'=>'Banque','mobile_money'=>'Mobile money'][$encaissement['payment_method']] ?? ucfirst((string)$encaissement['payment_method']);
$statusLabelPdf = ['valide'=>'Validé','brouillon'=>'Brouillon','annule'=>'Annulé'][$encaissement['status']] ?? ucfirst((string)$encaissement['status']);
?>
<div class="document">
    <div class="top-stripe"></div><div class="top-stripe-dark"></div><div class="top-stripe-green"></div>
    <?php if ($encaissement['status'] !== 'valide'): ?><div class="watermark"><?= e(strtoupper($statusLabelPdf)) ?></div><?php endif; ?>
    <table class="header-table"><tr>
        <td class="brand"><?php if($logoDataUri): ?><img class="logo" src="<?= $logoDataUri ?>" width="288" height="50" alt="AMM"><?php endif; ?><div class="company-meta">Vente et fourniture d’énergie électrique<br>Lubumbashi, République Démocratique du Congo</div></td>
        <td class="document-meta" style="width:190px"><div class="document-type">Reçu d’encaissement</div><div class="document-number"><?= e($encaissement['receipt_number']) ?></div><span class="status status-<?= e($encaissement['status']) ?>"><?= e($statusLabelPdf) ?></span></td>
    </tr></table>
    <div class="rule"></div>

    <div class="section-title">Informations de l’encaissement</div>
    <table class="info-table"><tr><td style="width:48%"><div class="label">Reçu de</div><div class="value"><?= e($encaissement['payer_name']) ?></div></td><td style="width:25%"><div class="label">Date de réception</div><div class="value"><?= formatDate($encaissement['date_receipt']) ?></div></td><td><div class="label">Mode de paiement</div><div class="value"><?= e($methodLabelPdf) ?></div></td></tr></table>

    <div class="section-title">Objet du paiement</div><div class="object"><?= e($encaissement['objet']) ?></div>
    <div class="section-title">Montant encaissé</div>
    <table class="amount-table"><tr><td class="amount-main"><div class="label">Montant reçu</div><div class="amount-value"><?= formatMontant((float)$encaissement['amount'],$encaissement['currency']) ?></div></td><td class="amount-side"><div class="label">Contre-valeur USD</div><div class="value"><?= formatMontant((float)$encaissement['amount_usd'],'USD') ?></div></td><td class="amount-side"><div class="label">Compte crédité</div><div class="value"><?= e($encaissement['account_nom'] ?? '—') ?></div><div style="margin-top:4px;color:#718096;font-size:7px"><?= e(ucfirst(str_replace('_',' ',(string)($encaissement['type_compte'] ?? '')))) ?></div></td></tr></table>

    <div class="section-title">Traçabilité et références</div>
    <table class="reference-table">
        <tr><th>Référence paiement</th><td><?= e($encaissement['reference_payment'] ?: '—') ?></td></tr>
        <tr><th>Taux CDF/USD</th><td><?= !empty($encaissement['exchange_rate']) ? e($encaissement['exchange_rate']) : '—' ?></td></tr>
        <tr><th>Enregistré par</th><td><?= e($encaissement['created_by_nom'] ?? '—') ?></td></tr>
        <tr><th>Validation</th><td><?= e($encaissement['validated_by_nom'] ?? '—') ?><?= !empty($encaissement['validated_at']) ? ' · '.e(date('d/m/Y à H:i',strtotime($encaissement['validated_at']))) : '' ?></td></tr>
        <?php if(!empty($encaissement['financial_transaction_id'])): ?><tr><th>Écriture financière</th><td>Transaction #<?= (int)$encaissement['financial_transaction_id'] ?></td></tr><?php endif; ?>
        <?php if($encaissement['status']==='annule'): ?><tr><th>Annulé par</th><td><?= e($encaissement['cancelled_by_nom'] ?? '—') ?></td></tr><tr><th>Motif d’annulation</th><td><?= e($encaissement['cancellation_reason'] ?? '—') ?></td></tr><?php endif; ?>
    </table>
    <?php if(!empty($encaissement['notes'])): ?><div class="section-title">Observations</div><div class="notes"><?= nl2br(e($encaissement['notes'])) ?></div><?php endif; ?>

    <table class="signature-table"><tr><td>Signature du payeur</td><td class="gap"></td><td>Trésorier · Signature et cachet AMM</td></tr></table>
    <table class="footer-table"><tr><td>Document officiel généré par AMM-ERP · v<?= e(APP_VERSION) ?></td><td class="footer-right">Généré le <?= date('d/m/Y à H:i') ?> · Page 1/1</td></tr></table>
</div>
</body></html>
