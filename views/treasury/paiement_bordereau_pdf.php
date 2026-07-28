<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 24px 30px 30px; }
* { box-sizing: border-box; }
body { margin:0; color:#17283e; font-family:DejaVu Sans,sans-serif; font-size:9px; }
.page { position:relative; min-height:735px; padding:30px 34px 42px; border:1px solid #cfd9e5; }
.stripe { position:absolute; inset:0 0 auto; height:6px; background:#1769e0; }
.stripe-dark { position:absolute; top:0; left:0; width:34%; height:6px; background:#0b2847; }
.stripe-green { position:absolute; top:0; right:0; width:22%; height:6px; background:#1a9a5b; }
.header,.summary,.details,.trace,.signatures,.footer { width:100%; border-collapse:collapse; }
.logo { display:block; width:230px; height:40px; }
.brand-meta { margin-top:5px; color:#65758a; font-size:7px; line-height:1.4; }
.doc-meta { width:300px; text-align:right; vertical-align:top; }
.doc-type { color:#1769e0; font-size:8px; font-weight:bold; letter-spacing:1.2px; text-transform:uppercase; }
.doc-title { margin:4px 0 3px; color:#10243b; font-size:18px; font-weight:bold; }
.doc-number { color:#65758a; font-size:8px; }
.status { display:inline-block; margin-top:6px; padding:4px 9px; border:1px solid #b9dfca; color:#137044; background:#edf9f2; font-size:7px; font-weight:bold; text-transform:uppercase; }
.rule { margin:14px 0 12px; border-top:1px solid #dfe6ef; }
.section-title { margin:0 0 7px; color:#64758a; font-size:7px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; }
.summary { margin-bottom:14px; border:1px solid #cbd9e9; }
.summary td { width:33.33%; padding:13px; border-right:1px solid #dfe6ef; vertical-align:middle; }
.summary td:last-child { border-right:0; }
.summary .primary { color:#fff; background:#0b3c69; }
.label { margin-bottom:5px; color:#718096; font-size:6px; font-weight:bold; text-transform:uppercase; }
.primary .label { color:#b9d1e8; }
.value { color:#253950; font-size:10px; font-weight:bold; line-height:1.4; }
.primary .value { color:#fff; font-size:17px; }
.details { margin-bottom:14px; table-layout:fixed; }
.details td { padding:10px 11px; border:1px solid #dfe6ef; vertical-align:top; }
.object { margin-bottom:14px; padding:11px 13px; border-left:3px solid #1769e0; background:#f7f9fc; line-height:1.5; }
.trace { margin-bottom:14px; }
.trace th,.trace td { padding:8px 0; border-bottom:1px solid #edf1f5; text-align:left; }
.trace th { width:34%; color:#718096; font-size:6px; font-weight:bold; text-transform:uppercase; }
.trace td { color:#253950; font-weight:bold; }
.evidence { padding:10px 12px; border:1px solid #e1e7ee; color:#44566c; background:#fafbfd; line-height:1.5; }
.signatures { margin-top:70px; }
.signatures td { width:30%; padding-top:8px; border-top:1px solid #7d8da0; color:#586b80; text-align:center; }
.signatures .gap { width:5%; border:0; }
.footer { position:fixed; bottom:3px; left:30px; width:735px; border-top:1px solid #dfe6ef; }
.footer td { padding-top:6px; color:#8290a0; font-size:6px; }
.footer-right { text-align:right; }
</style>
</head>
<body>
<?php
$paymentMethodLabel = [
    'especes' => 'Espèces',
    'banque' => 'Banque',
    'mobile_money' => 'Mobile money',
][$ordre['payment_method'] ?? ''] ?? ucfirst(str_replace('_', ' ', (string)($ordre['payment_method'] ?? '')));
$isCashPayment = ($ordre['payment_method'] ?? '') === 'especes' || ($ordre['account_type'] ?? '') === 'caisse';
if ($isCashPayment) $paymentMethodLabel = 'Espèces';
?>
<div class="page">
    <div class="stripe"></div><div class="stripe-dark"></div><div class="stripe-green"></div>
    <table class="header"><tr>
        <td><?php if ($logoDataUri): ?><img class="logo" src="<?= $logoDataUri ?>" width="230" height="40" alt="AMM"><?php endif; ?><div class="brand-meta">Vente et fourniture d’énergie électrique · Lubumbashi, RDC</div></td>
        <td class="doc-meta"><div class="doc-type">Trésorerie · Preuve interne</div><div class="doc-title"><?= $isCashPayment ? 'BON DE DÉCAISSEMENT' : 'BORDEREAU DE PAIEMENT' ?></div><div class="doc-number"><?= e($ordre['order_number']) ?> · Paiement #<?= (int)$ordre['payment_id'] ?></div><span class="status">Exécuté</span></td>
    </tr></table>
    <div class="rule"></div>

    <div class="section-title">Synthèse du paiement</div>
    <table class="summary"><tr>
        <td class="primary"><div class="label">Montant payé</div><div class="value"><?= formatMontant((float)$ordre['amount'], $ordre['currency']) ?></div></td>
        <td><div class="label">Bénéficiaire</div><div class="value"><?= e($ordre['beneficiary']) ?></div></td>
        <td><div class="label">Date du paiement</div><div class="value"><?= formatDate($ordre['payment_date']) ?></div></td>
    </tr></table>

    <div class="section-title">Coordonnées de règlement</div>
    <table class="details"><tr>
        <td><div class="label">Mode de paiement</div><div class="value"><?= e($paymentMethodLabel) ?></div></td>
        <td><div class="label">Compte débité</div><div class="value"><?= e($ordre['account_nom'] ?? '—') ?></div></td>
        <td><div class="label">Référence du paiement</div><div class="value"><?= e($ordre['executed_reference_payment'] ?: '—') ?></div></td>
    </tr></table>

    <div class="section-title">Objet</div>
    <div class="object"><strong><?= e($ordre['objet']) ?></strong><br><span style="color:#65758a">Demande <?= e($ordre['request_number']) ?> · Ordre <?= e($ordre['order_number']) ?></span></div>

    <div class="section-title">Traçabilité</div>
    <table class="trace">
        <tr><th>Ordre émis par</th><td><?= e($ordre['issued_by_nom'] ?? '—') ?> · <?= !empty($ordre['issued_at']) ? e(date('d/m/Y à H:i', strtotime($ordre['issued_at']))) : '—' ?></td></tr>
        <tr><th>Paiement exécuté par</th><td><?= e($ordre['executed_by_nom'] ?? '—') ?> · <?= !empty($ordre['executed_at']) ? e(date('d/m/Y à H:i', strtotime($ordre['executed_at']))) : '—' ?></td></tr>
        <tr><th>Transaction financière</th><td><?= !empty($ordre['financial_transaction_id']) ? '#' . (int)$ordre['financial_transaction_id'] : '—' ?></td></tr>
    </table>

    <div class="section-title">Justification et pièces</div>
    <div class="evidence">
        <?php if ($isCashPayment): ?>
            Le présent bon constitue la preuve interne du décaissement enregistré dans AMM-ERP. La signature du bénéficiaire vaut acquit. Une copie signée peut être jointe ultérieurement au dossier.
        <?php else: ?>
            Le présent bordereau constitue la preuve interne de comptabilisation. La preuve externe de l’établissement bancaire ou de l’opérateur est conservée dans le dossier électronique du paiement.
        <?php endif; ?>
    </div>

    <table class="signatures"><tr>
        <td>Exécuté par · Trésorerie</td><td class="gap"></td>
        <td>Reçu par · Bénéficiaire<br><br>Date : ____ / ____ / ______</td><td class="gap"></td>
        <td>Contrôle · Comptabilité</td>
    </tr></table>
</div>
<table class="footer"><tr><td>AMM-ERP · Preuve interne de paiement · Document de contrôle</td><td class="footer-right">Généré le <?= date('d/m/Y à H:i') ?> par <?= e($_SESSION['user_nom'] ?? 'Utilisateur autorisé') ?></td></tr></table>
</body>
</html>
