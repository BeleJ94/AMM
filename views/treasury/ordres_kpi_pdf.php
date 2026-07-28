<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
@page{margin:22px 28px 28px}*{box-sizing:border-box}body{margin:0;color:#17283e;font-family:DejaVu Sans,sans-serif;font-size:8px}
.page{position:relative;min-height:520px;padding:25px 28px 36px;border:1px solid #cfd9e5}.stripe{position:absolute;top:0;left:0;right:0;height:6px;background:#1769e0}.stripe:before{content:"";display:block;width:34%;height:6px;background:#0b2847}.stripe:after{content:"";position:absolute;top:0;right:0;width:22%;height:6px;background:#1a9a5b}
.header,.kpis,.details,.footer{width:100%;border-collapse:collapse}.logo{display:block;width:230px;height:40px}.brand-meta{margin-top:5px;color:#65758a;font-size:7px}.doc-meta{width:390px;text-align:right;vertical-align:top}.doc-type{color:#1769e0;font-size:8px;font-weight:bold;letter-spacing:1.2px;text-transform:uppercase}.doc-title{margin:4px 0;color:#10243b;font-size:17px;font-weight:bold}.doc-subtitle{color:#65758a;font-size:8px}.rule{margin:13px 0 11px;border-top:1px solid #dfe6ef}.section-title{margin:0 0 6px;color:#64758a;font-size:7px;font-weight:bold;letter-spacing:1px;text-transform:uppercase}
.kpis{margin-bottom:12px;border:1px solid #d6e0eb}.kpis td{width:25%;height:54px;padding:8px 11px;border-right:1px solid #dfe6ef;vertical-align:middle}.kpis td:last-child{border-right:0}.kpis .primary{color:#fff;background:#0b3c69}.label{margin-bottom:5px;color:#718096;font-size:6px;font-weight:bold;text-transform:uppercase}.primary .label{color:#b9d1e8}.value{font-size:12px;font-weight:bold}
.details{table-layout:fixed}.details thead{display:table-header-group}.details th{padding:6px 5px;border:1px solid #c9d6e4;color:#fff;background:#173e67;font-size:6px;text-align:left;text-transform:uppercase}.details td{padding:6px 5px;border-right:1px solid #e0e7ef;border-bottom:1px solid #e0e7ef;vertical-align:top}.details tbody tr:nth-child(even) td{background:#f8fafc}.details .num{text-align:right;white-space:nowrap;font-weight:bold}.details .empty{padding:20px;color:#7b8999;text-align:center}.status{font-weight:bold}.footer{position:fixed;bottom:2px;left:28px;width:785px;border-top:1px solid #dfe6ef}.footer td{padding-top:5px;color:#8290a0;font-size:6px}.footer-right{text-align:right}
</style>
</head>
<body>
<?php
$averageUsd = count($ordresKpi) ? $totalUsd / count($ordresKpi) : 0;
$cashCount = count(array_filter($ordresKpi, static function ($ligne) { return ($ligne['account_type'] ?? '') === 'caisse'; }));
?>
<div class="page">
    <div class="stripe"></div>
    <table class="header"><tr>
        <td><?php if ($logoDataUri): ?><img class="logo" src="<?= $logoDataUri ?>" width="230" height="40" alt="AMM"><?php endif; ?><div class="brand-meta">Vente et fourniture d’énergie électrique · Lubumbashi, RDC</div></td>
        <td class="doc-meta"><div class="doc-type">Trésorerie · Pilotage des décaissements</div><div class="doc-title"><?= e(strtoupper($definition['title'])) ?></div><div class="doc-subtitle"><?= e($definition['subtitle']) ?> · <?= date('d/m/Y H:i') ?></div></td>
    </tr></table>
    <div class="rule"></div>
    <div class="section-title">Synthèse exécutive</div>
    <table class="kpis"><tr>
        <td><div class="label">Volume</div><div class="value"><?= count($ordresKpi) ?> ordre(s)</div></td>
        <td class="primary"><div class="label">Engagement total</div><div class="value"><?= formatMontant($totalUsd, 'USD') ?></div></td>
        <td><div class="label">Montant moyen</div><div class="value"><?= formatMontant($averageUsd, 'USD') ?></div></td>
        <td><div class="label">Ordres sur caisse</div><div class="value"><?= $cashCount ?></div></td>
    </tr></table>
    <div class="section-title">Détail des ordres de paiement</div>
    <table class="details">
        <colgroup><col style="width:12%"><col style="width:8%"><col style="width:12%"><col style="width:15%"><col style="width:21%"><col style="width:14%"><col style="width:10%"><col style="width:8%"></colgroup>
        <thead><tr><th>Ordre</th><th>Date</th><th>Demande</th><th>Bénéficiaire</th><th>Objet</th><th>Compte / mode</th><th>Montant USD</th><th>Statut</th></tr></thead>
        <tbody>
        <?php if (empty($ordresKpi)): ?>
            <tr><td colspan="8" class="empty">Aucun ordre ne correspond à cet indicateur.</td></tr>
        <?php else: foreach ($ordresKpi as $ordre): ?>
            <?php $method = ($ordre['account_type'] ?? '') === 'caisse' ? 'Espèces' : ucfirst(str_replace('_',' ',(string)$ordre['payment_method'])); ?>
            <tr>
                <td><strong><?= e($ordre['order_number']) ?></strong></td><td><?= formatDate($ordre['order_date']) ?></td>
                <td><?= e($ordre['request_number']) ?></td><td><?= e($ordre['beneficiary']) ?></td><td><?= e($ordre['objet']) ?></td>
                <td><?= e($ordre['account_nom'] ?? '—') ?><br><span style="color:#718096"><?= e($method) ?></span></td>
                <td class="num"><?= formatMontant((float)$ordre['amount_usd'], 'USD') ?></td>
                <td class="status"><?= e(ucfirst(str_replace('_',' ',(string)$ordre['status']))) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<table class="footer"><tr><td>AMM-ERP · Vue CFO des ordres de paiement · Document de contrôle interne</td><td class="footer-right">Généré par <?= e($_SESSION['user_nom'] ?? 'Utilisateur autorisé') ?></td></tr></table>
</body>
</html>
