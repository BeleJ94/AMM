<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 22px 28px 28px; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #17283e; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
    .page { position: relative; min-height: 520px; padding: 25px 28px 36px; border: 1px solid #cfd9e5; }
    .stripe { position:absolute; top:0; left:0; right:0; height:6px; background:#1769e0; }
    .stripe-dark { position:absolute; top:0; left:0; width:34%; height:6px; background:#0b2847; }
    .stripe-green { position:absolute; top:0; right:0; width:22%; height:6px; background:#1a9a5b; }
    .header,.kpis,.details,.footer { width:100%; border-collapse:collapse; }
    .logo { display:block; width:230px; height:40px; }
    .brand-meta { margin-top:5px; color:#65758a; font-size:7px; }
    .doc-meta { width:390px; text-align:right; vertical-align:top; }
    .doc-type { color:#1769e0; font-size:8px; font-weight:bold; letter-spacing:1.3px; text-transform:uppercase; }
    .doc-title { margin:4px 0; color:#10243b; font-size:17px; font-weight:bold; }
    .doc-subtitle { color:#65758a; font-size:8px; }
    .rule { margin:13px 0 11px; border-top:1px solid #dfe6ef; }
    .section-title { margin:0 0 6px; color:#64758a; font-size:7px; font-weight:bold; letter-spacing:1px; text-transform:uppercase; }
    .kpis { margin-bottom:12px; border:1px solid #d6e0eb; }
    .kpis td { width:25%; height:54px; padding:8px 11px; border-right:1px solid #dfe6ef; vertical-align:middle; }
    .kpis td:last-child { border-right:0; }
    .kpis .primary { color:#fff; background:#0b3c69; }
    .label { margin-bottom:5px; color:#718096; font-size:6px; font-weight:bold; text-transform:uppercase; }
    .primary .label { color:#b9d1e8; }
    .value { font-size:12px; font-weight:bold; }
    .details { table-layout:fixed; }
    .details thead { display:table-header-group; }
    .details th { padding:6px 5px; border:1px solid #c9d6e4; color:#fff; background:#173e67; font-size:6px; text-align:left; text-transform:uppercase; }
    .details td { padding:6px 5px; border-right:1px solid #e0e7ef; border-bottom:1px solid #e0e7ef; vertical-align:top; }
    .details tbody tr:nth-child(even) td { background:#f8fafc; }
    .details .num { text-align:right; white-space:nowrap; font-weight:bold; }
    .details .center { text-align:center; }
    .details .empty { padding:20px; color:#7b8999; text-align:center; }
    .high { color:#b12634; font-weight:bold; }
    .footer { position:fixed; bottom:2px; left:28px; width:785px; border-top:1px solid #dfe6ef; }
    .footer td { padding-top:5px; color:#8290a0; font-size:6px; }
    .footer-right { text-align:right; }
</style>
</head>
<body>
<?php
$averageUsd = count($demandesKpi) > 0 ? $totalUsd / count($demandesKpi) : 0;
$highCount = count(array_filter($demandesKpi, static function ($ligne) {
    return ($ligne['urgency'] ?? '') === 'haute';
}));
$isRegister = ($metric ?? '') === 'registre';
$actionLabels = [
    'brouillon' => 'À compléter et soumettre',
    'soumise' => 'Contrôle comptable requis',
    'validee_comptable' => 'Approbation Direction requise',
    'validee_direction' => 'Ordre de paiement à émettre',
    'ordre_emis' => 'Paiement à exécuter',
    'payee' => 'Paiement terminé',
    'rejetee' => 'Correction requise',
    'annulee' => 'Demande annulée',
];
?>
<div class="page">
    <div class="stripe"></div><div class="stripe-dark"></div><div class="stripe-green"></div>
    <table class="header"><tr>
        <td><?php if ($logoDataUri): ?><img class="logo" src="<?= $logoDataUri ?>" width="230" height="40" alt="AMM"><?php endif; ?><div class="brand-meta">Vente et fourniture d’énergie électrique · Lubumbashi, RDC</div></td>
        <td class="doc-meta"><div class="doc-type">Trésorerie · Pilotage des engagements</div><div class="doc-title"><?= e(strtoupper($definition['title'])) ?></div><div class="doc-subtitle"><?= e($definition['subtitle']) ?> · <?= date('d/m/Y H:i') ?></div></td>
    </tr></table>
    <div class="rule"></div>

    <div class="section-title">Synthèse exécutive</div>
    <table class="kpis"><tr>
        <td><div class="label">Volume</div><div class="value"><?= count($demandesKpi) ?> demande(s)</div></td>
        <td class="primary"><div class="label">Engagement total</div><div class="value"><?= formatMontant($totalUsd, 'USD') ?></div></td>
        <td><div class="label">Montant moyen</div><div class="value"><?= formatMontant($averageUsd, 'USD') ?></div></td>
        <td><div class="label">Urgences hautes</div><div class="value <?= $highCount > 0 ? 'high' : '' ?>"><?= $highCount ?></div></td>
    </tr></table>

    <div class="section-title">Détail des demandes</div>
    <table class="details">
        <colgroup><col style="width:12%"><col style="width:8%"><col style="width:16%"><col style="width:24%"><col style="width:13%"><col style="width:12%"><col style="width:7%"><col style="width:8%"></colgroup>
        <thead><tr><th>Numéro</th><th>Date</th><th>Bénéficiaire</th><th>Objet</th><th>Catégorie</th><th>Montant USD</th><th>Urgence</th><th><?= $isRegister ? 'Prochaine action' : 'Statut' ?></th></tr></thead>
        <tbody>
        <?php if (empty($demandesKpi)): ?>
            <tr><td colspan="8" class="empty">Aucune demande ne correspond à cet indicateur.</td></tr>
        <?php else: foreach ($demandesKpi as $demande): ?>
            <tr>
                <td><?= e($demande['request_number']) ?></td>
                <td><?= formatDate($demande['request_date']) ?></td>
                <td><?= e($demande['beneficiary']) ?></td>
                <td><?= e($demande['objet']) ?></td>
                <td><?= e($demande['category_nom'] ?? '—') ?></td>
                <td class="num"><?= formatMontant((float)$demande['amount_usd'], 'USD') ?></td>
                <td class="<?= ($demande['urgency'] ?? '') === 'haute' ? 'high' : '' ?>"><?= e(ucfirst($demande['urgency'])) ?></td>
                <td>
                    <?php if ($isRegister): ?>
                        <?php
                        $pdfActionLabel = $actionLabels[$demande['status']] ?? 'Suivi requis';
                        if (($demande['status'] ?? '') === 'validee_comptable') {
                            if (!empty($demande['direction_generale_validee']) && empty($demande['direction_financiere_validee'])) {
                                $pdfActionLabel = 'Validation Direction financière requise';
                            } elseif (!empty($demande['direction_financiere_validee']) && empty($demande['direction_generale_validee'])) {
                                $pdfActionLabel = 'Validation Direction générale requise';
                            } else {
                                $pdfActionLabel = 'Double approbation Direction requise';
                            }
                        }
                        ?>
                        <strong><?= e($pdfActionLabel) ?></strong><br>
                        <span style="color:#718096"><?= e(ucfirst(str_replace('_', ' ', $demande['status']))) ?></span>
                    <?php else: ?>
                        <?= e(ucfirst(str_replace('_', ' ', $demande['status']))) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<table class="footer"><tr><td>AMM-ERP · Vue CFO des demandes de fonds · Document de contrôle interne</td><td class="footer-right">Généré par <?= e($_SESSION['user_nom'] ?? 'Utilisateur autorisé') ?></td></tr></table>
</body>
</html>
