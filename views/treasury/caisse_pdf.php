<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 22px 28px 28px; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #17283e; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
    .page { position: relative; min-height: 520px; padding: 25px 28px 36px; border: 1px solid #cfd9e5; }
    .stripe { position: absolute; top: 0; left: 0; right: 0; height: 6px; background: #1769e0; }
    .stripe-dark { position: absolute; top: 0; left: 0; width: 34%; height: 6px; background: #0b2847; }
    .stripe-green { position: absolute; top: 0; right: 0; width: 22%; height: 6px; background: #1a9a5b; }
    .header, .kpis, .control-table, .journal, .signatures, .footer { width: 100%; border-collapse: collapse; }
    .brand { vertical-align: top; }
    .logo { display: block; width: 230px; height: 40px; }
    .brand-meta { margin-top: 5px; color: #65758a; font-size: 7px; line-height: 1.4; }
    .doc-meta { width: 280px; text-align: right; vertical-align: top; }
    .doc-type { color: #1769e0; font-size: 8px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; }
    .doc-title { margin: 4px 0 3px; color: #10243b; font-size: 18px; font-weight: bold; }
    .doc-period { color: #65758a; font-size: 8px; }
    .status { display: inline-block; margin-top: 6px; padding: 4px 9px; border: 1px solid #bdd4ec; color: #12558f; background: #edf6ff; font-size: 7px; font-weight: bold; text-transform: uppercase; }
    .rule { margin: 13px 0 11px; border-top: 1px solid #dfe6ef; }
    .section-title { margin: 0 0 6px; color: #64758a; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
    .kpis { margin-bottom: 11px; border: 1px solid #d6e0eb; }
    .kpis td { width: 20%; height: 56px; padding: 8px 10px; border-right: 1px solid #dfe6ef; vertical-align: middle; }
    .kpis td:last-child { border-right: 0; }
    .kpis .primary { color: #fff; background: #0b3c69; }
    .kpi-label { margin-bottom: 5px; color: #718096; font-size: 6px; font-weight: bold; text-transform: uppercase; }
    .primary .kpi-label { color: #b9d1e8; }
    .kpi-value { font-size: 12px; font-weight: bold; white-space: nowrap; }
    .positive { color: #147a48; }
    .negative { color: #b12634; }
    .control-table { margin-bottom: 11px; border: 1px solid #dfe6ef; background: #f8fbff; }
    .control-table td { padding: 7px 9px; border-right: 1px solid #dfe6ef; vertical-align: top; }
    .control-label { color: #718096; font-size: 6px; font-weight: bold; text-transform: uppercase; }
    .control-value { margin-top: 3px; color: #253950; font-size: 8px; font-weight: bold; }
    .journal { table-layout: fixed; }
    .journal thead { display: table-header-group; }
    .journal th { padding: 6px 5px; border: 1px solid #c9d6e4; color: #fff; background: #173e67; font-size: 6px; text-align: left; text-transform: uppercase; }
    .journal td { padding: 6px 5px; border-right: 1px solid #e0e7ef; border-bottom: 1px solid #e0e7ef; vertical-align: top; }
    .journal tbody tr:nth-child(even) td { background: #f8fafc; }
    .journal .num { text-align: right; white-space: nowrap; }
    .journal .empty { padding: 18px; color: #7b8999; text-align: center; }
    .journal tfoot td { padding: 7px 5px; border-top: 2px solid #173e67; background: #eef4fa; font-weight: bold; }
    .journal .opening td { background: #f0f5fa; font-weight: bold; }
    .signatures { margin-top: 24px; page-break-inside: avoid; }
    .signatures td { width: 29%; padding-top: 7px; border-top: 1px solid #7d8da0; color: #586b80; text-align: center; }
    .signatures .gap { width: 6.5%; border: 0; }
    .footer { position: fixed; bottom: 2px; left: 28px; width: 785px; border-top: 1px solid #dfe6ef; }
    .footer td { padding-top: 5px; color: #8290a0; font-size: 6px; }
    .footer-right { text-align: right; }
</style>
</head>
<body>
<div class="page">
    <div class="stripe"></div><div class="stripe-dark"></div><div class="stripe-green"></div>
    <table class="header"><tr>
        <td class="brand">
            <?php if ($logoDataUri): ?><img class="logo" src="<?= $logoDataUri ?>" width="230" height="40" alt="AMM"><?php endif; ?>
            <div class="brand-meta">Vente et fourniture d’énergie électrique · Lubumbashi, RDC</div>
        </td>
        <td class="doc-meta">
            <div class="doc-type">Trésorerie · Contrôle des liquidités</div>
            <div class="doc-title">LIVRE DE CAISSE</div>
            <div class="doc-period"><?= formatDate($dateDebut) ?><?= $dateFin !== $dateDebut ? ' — ' . formatDate($dateFin) : '' ?> · <?= e($compte['nom']) ?></div>
            <span class="status"><?= e(strtoupper(str_replace('_', ' ', $statut))) ?></span>
        </td>
    </tr></table>

    <div class="rule"></div>
    <div class="section-title">Synthèse exécutive</div>
    <table class="kpis"><tr>
        <td><div class="kpi-label">Solde d’ouverture</div><div class="kpi-value"><?= formatMontant($soldeOuverture, $devise) ?></div></td>
        <td><div class="kpi-label">Total des entrées</div><div class="kpi-value positive"><?= formatMontant($totalEntrees, $devise) ?></div></td>
        <td><div class="kpi-label">Total des sorties</div><div class="kpi-value negative"><?= formatMontant($totalSorties, $devise) ?></div></td>
        <td class="primary"><div class="kpi-label">Solde théorique</div><div class="kpi-value"><?= formatMontant($soldeTheorique, $devise) ?></div></td>
        <td><div class="kpi-label">Écart physique</div><div class="kpi-value <?= $ecart !== null && abs($ecart) > 0.001 ? 'negative' : 'positive' ?>"><?= $ecart !== null ? formatMontant($ecart, $devise) : 'Non compté' ?></div></td>
    </tr></table>

    <div class="section-title">Cadre de contrôle</div>
    <table class="control-table"><tr>
        <td><div class="control-label">Compte</div><div class="control-value"><?= e($compte['nom']) ?> · <?= e($devise) ?></div></td>
        <td><div class="control-label">Solde physique</div><div class="control-value"><?= $montantPhysique !== null ? formatMontant($montantPhysique, $devise) : 'À compter' ?></div></td>
        <td><div class="control-label">Plafond de caisse</div><div class="control-value"><?= formatMontant($plafondCaisseUsd, 'USD') ?></div></td>
        <td><div class="control-label">Clôturé par</div><div class="control-value"><?= e($session['closed_by_nom'] ?? '—') ?></div></td>
        <td><div class="control-label">Contrôlé par</div><div class="control-value"><?= e($session['checked_by_nom'] ?? '—') ?></div></td>
    </tr></table>

    <div class="section-title">Journal chronologique des mouvements</div>
    <table class="journal">
        <colgroup>
            <col style="width:7%"><col style="width:13%"><col style="width:8%"><col style="width:14%">
            <col style="width:22%"><col style="width:11%"><col style="width:11%"><col style="width:14%">
        </colgroup>
        <thead><tr><th>Date</th><th>Référence</th><th>Nature</th><th>Tiers</th><th>Libellé</th><th>Entrée</th><th>Sortie</th><th>Solde</th></tr></thead>
        <tbody>
            <tr class="opening"><td><?= formatDate($dateDebut) ?></td><td>REPORT</td><td>Ouverture</td><td>—</td><td>Solde reporté à l’ouverture</td><td></td><td></td><td class="num"><?= formatMontant($soldeOuverture, $devise) ?></td></tr>
            <?php if (empty($mouvements)): ?>
                <tr><td colspan="8" class="empty">Aucun mouvement validé sur la période sélectionnée.</td></tr>
            <?php else: ?>
                <?php foreach ($mouvements as $mouvement): ?>
                    <tr>
                        <td><?= formatDate($mouvement['operation_date']) ?></td>
                        <td><?= e($mouvement['reference_operation']) ?></td>
                        <td><?= $mouvement['sens'] === 'entree' ? 'Encaissement' : 'Décaissement' ?></td>
                        <td><?= e($mouvement['partenaire']) ?></td>
                        <td><?= e($mouvement['libelle']) ?></td>
                        <td class="num positive"><?= $mouvement['sens'] === 'entree' ? formatMontant((float)$mouvement['amount'], $devise) : '—' ?></td>
                        <td class="num negative"><?= $mouvement['sens'] === 'sortie' ? formatMontant((float)$mouvement['amount'], $devise) : '—' ?></td>
                        <td class="num"><?= formatMontant((float)$mouvement['running_balance'], $devise) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="5">TOTAUX ET SOLDE DE CLÔTURE</td><td class="num positive"><?= formatMontant($totalEntrees, $devise) ?></td><td class="num negative"><?= formatMontant($totalSorties, $devise) ?></td><td class="num"><?= formatMontant($soldeTheorique, $devise) ?></td></tr></tfoot>
    </table>

    <table class="signatures"><tr>
        <td>Préparé par · Trésorier</td><td class="gap"></td>
        <td>Vérifié par · Comptable senior</td><td class="gap"></td>
        <td>Visa · Direction financière</td>
    </tr></table>
</div>
<table class="footer"><tr>
    <td>AMM-ERP · Livre de caisse officiel · Document de contrôle interne</td>
    <td class="footer-right">Généré le <?= date('d/m/Y à H:i') ?> par <?= e($_SESSION['user_nom'] ?? 'Utilisateur autorisé') ?></td>
</tr></table>
</body>
</html>
