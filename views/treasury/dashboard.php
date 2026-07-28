<?php $pageTitle = 'Cockpit Trésorerie - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>
<?php
$moisLibelles = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
$soldeNetMois = (float)$stats['encaissements_mois_usd'] - (float)$stats['decaissements_mois_usd'];
$cashRatio = (float)$stats['plafond_caisse_usd'] > 0 ? min(100, ((float)$stats['solde_caisse_usd'] / (float)$stats['plafond_caisse_usd']) * 100) : 0;
$actionCount = count($actionsPrioritaires);
?>

<style>
.treasury-cockpit .dashboard-hero { padding-bottom:12px; }
.cockpit-context { display:inline-flex; align-items:center; gap:6px; color:#53657a; font-size:.69rem; font-weight:700; }
.cockpit-context:before { content:''; width:7px; height:7px; border-radius:50%; background:#198754; box-shadow:0 0 0 3px #e5f5ec; }
.liquidity-kpi .erp-kpi-meta { min-height:16px; }
.action-center-card { overflow:hidden; }
.action-center-head { padding:14px 16px; border-bottom:1px solid #e5ebf1; background:#fbfcfe; }
.action-row { display:grid; grid-template-columns:minmax(220px,1.45fr) minmax(125px,.7fr) minmax(115px,.55fr) minmax(105px,.45fr); gap:16px; align-items:center; padding:13px 16px; border-bottom:1px solid #edf1f5; }
.action-row:last-child { border-bottom:0; }
.action-row:hover { background:#fbfdff; }
.action-marker { width:8px; height:8px; flex:0 0 8px; border-radius:50%; background:#cbd5e1; }
.action-marker.tone-warning { background:#d99a18; }.action-marker.tone-primary { background:#1769e0; }.action-marker.tone-danger { background:#cf3542; }
.action-label { color:#1a2f47; font-size:.78rem; font-weight:800; }
.action-meta { color:#778698; font-size:.66rem; }
.action-reference { color:#1769e0; font-weight:800; }
.action-age { display:inline-flex; align-items:center; gap:4px; color:#718096; font-size:.68rem; }
.action-age.is-late { color:#b12634; font-weight:800; }
.control-list a { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:11px 14px; border-bottom:1px solid #edf1f5; color:#35485f; text-decoration:none; font-size:.74rem; font-weight:700; }
.control-list a:hover { color:#1769e0; background:#fbfdff; }
.control-count { min-width:27px; padding:3px 7px; border-radius:12px; color:#384b61; background:#edf2f7; text-align:center; font-size:.68rem; }
.control-count.is-alert { color:#a52834; background:#fdebed; }
.account-balance-list { margin:0; padding:0; list-style:none; }
.account-balance-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:11px 0; border-bottom:1px solid #edf1f5; }
.account-balance-item:last-child { border-bottom:0; }
.account-icon { width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; border-radius:7px; color:#1769e0; background:#e9f2ff; }
.cockpit-quick-actions { display:flex; gap:8px; flex-wrap:wrap; }
.cockpit-quick-actions .btn { font-size:.7rem; font-weight:700; }
.recent-operations th { white-space:nowrap; }
@media(max-width:991.98px){.action-row{grid-template-columns:1fr auto}.action-row .action-owner,.action-row .action-amount{display:none}}
@media(max-width:575.98px){.action-row{grid-template-columns:1fr}.action-row .btn{width:100%}.cockpit-filter{width:100%}}
</style>

<div class="container-fluid treasury-cockpit">
    <div class="dashboard-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
            <div>
                <div class="cockpit-context mb-2"><?= e($profilCockpit) ?> · Données opérationnelles</div>
                <h2>Cockpit Trésorerie</h2>
                <p class="text-muted small mb-0 mt-1">Liquidité, engagements et décisions nécessitant votre intervention.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-end">
                <form method="GET" action="<?= APP_URL ?>/treasury/dashboard" class="d-flex gap-2 cockpit-filter">
                    <select class="form-select form-select-sm" name="mois" aria-label="Mois"><?php foreach($moisLibelles as $n=>$label): ?><option value="<?= $n ?>" <?= (int)$mois===$n?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                    <input type="number" class="form-control form-control-sm" name="annee" min="2020" max="2100" value="<?= (int)$annee ?>" style="width:92px" aria-label="Année">
                    <button class="btn btn-outline-secondary btn-sm" title="Actualiser"><i class="bi bi-arrow-clockwise"></i></button>
                </form>
                <a href="<?= APP_URL ?>/treasury/rapports" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-bar-graph me-1"></i>Rapports CFO</a>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>
    <?php if (!empty($stats['alerte_plafond_caisse'])): ?>
        <div class="alert alert-danger d-flex align-items-center py-2 small"><i class="bi bi-exclamation-triangle-fill me-2"></i><div><strong>Plafond de caisse dépassé.</strong> Le solde atteint <?= formatMontant((float)$stats['solde_caisse_usd'],'USD') ?> pour un plafond de <?= formatMontant((float)$stats['plafond_caisse_usd'],'USD') ?>.</div></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3"><div class="card erp-kpi erp-kpi-primary liquidity-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Liquidité consolidée</div><span class="erp-kpi-icon"><i class="bi bi-wallet2"></i></span></div><div class="erp-kpi-value"><?= formatMontant((float)$positionLiquidite['total_usd'],'USD') ?></div><div class="erp-kpi-meta">Banques + caisse, converties en USD</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card erp-kpi <?= $positionLiquidite['disponible_usd'] >= 0 ? 'erp-kpi-success' : 'erp-kpi-danger' ?> liquidity-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Disponible après engagements</div><span class="erp-kpi-icon"><i class="bi bi-shield-check"></i></span></div><div class="erp-kpi-value <?= $positionLiquidite['disponible_usd'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatMontant((float)$positionLiquidite['disponible_usd'],'USD') ?></div><div class="erp-kpi-meta"><?= formatMontant((float)$positionLiquidite['engagements_usd'],'USD') ?> engagés par les OP émis</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card erp-kpi erp-kpi-warning liquidity-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Position caisse</div><span class="erp-kpi-icon"><i class="bi bi-safe2"></i></span></div><div class="erp-kpi-value"><?= formatMontant((float)$positionLiquidite['caisse_usd'],'USD') ?></div><div class="erp-kpi-meta">Plafond utilisé : <?= number_format($cashRatio,1,',',' ') ?> %</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card erp-kpi <?= $soldeNetMois >= 0 ? 'erp-kpi-success' : 'erp-kpi-danger' ?> liquidity-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Flux net du mois</div><span class="erp-kpi-icon"><i class="bi bi-activity"></i></span></div><div class="erp-kpi-value <?= $soldeNetMois >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatMontant($soldeNetMois,'USD') ?></div><div class="erp-kpi-meta"><?= e($moisLibelles[(int)$mois]) ?> <?= (int)$annee ?></div></div></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card section-card action-center-card h-100">
                <div class="action-center-head d-flex justify-content-between align-items-center gap-3">
                    <div><div class="erp-portlet-title"><i class="bi bi-lightning-charge text-warning"></i>Centre d’actions</div><div class="text-muted small mt-1">Priorités correspondant à vos habilitations</div></div>
                    <span class="badge bg-primary rounded-pill"><?= $actionCount ?> à traiter</span>
                </div>
                <?php if (empty($actionsPrioritaires)): ?>
                    <div class="text-center py-5 px-3"><i class="bi bi-check2-circle text-success fs-2"></i><div class="fw-semibold mt-2">Aucune action immédiate</div><div class="text-muted small">Les prochains dossiers nécessitant votre profil apparaîtront ici.</div></div>
                <?php else: ?>
                    <?php foreach ($actionsPrioritaires as $action): ?>
                        <?php $late = (int)$action['waiting_days'] >= 3; ?>
                        <div class="action-row">
                            <div class="d-flex gap-2 align-items-start">
                                <span class="action-marker tone-<?= e($action['tone']) ?> mt-1"></span>
                                <div><div class="action-label"><?= e($action['label']) ?></div><div class="action-meta"><span class="action-reference"><?= e($action['reference']) ?></span> · <?= e($action['beneficiary']) ?></div><div class="action-age <?= $late ? 'is-late' : '' ?>"><i class="bi bi-clock"></i><?= (int)$action['waiting_days'] === 0 ? 'Depuis aujourd’hui' : 'Depuis ' . (int)$action['waiting_days'] . ' jour' . ((int)$action['waiting_days'] > 1 ? 's' : '') ?></div></div>
                            </div>
                            <div class="action-owner"><div class="action-meta">Responsable</div><div class="fw-semibold small"><?= e($action['owner']) ?></div></div>
                            <div class="action-amount text-end"><div class="action-meta">Montant</div><div class="fw-semibold small text-nowrap"><?= e($action['amount']) ?></div></div>
                            <a href="<?= e($action['url']) ?>" class="btn btn-outline-<?= e($action['tone']) ?> btn-sm"><?= e($action['button']) ?><i class="bi bi-arrow-right ms-1"></i></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card section-card mb-3">
                <div class="card-header"><span class="erp-portlet-title"><i class="bi bi-shield-exclamation text-primary"></i>Contrôles opérationnels</span></div>
                <div class="control-list">
                    <a href="<?= APP_URL ?>/treasury/demandes"><span>Demandes dans le circuit</span><span class="control-count"><?= (int)$stats['demandes_en_attente'] ?></span></a>
                    <a href="<?= APP_URL ?>/treasury/ordres?status=emis"><span>OP non exécutés</span><span class="control-count <?= (int)$stats['ordres_non_executes'] ? 'is-alert' : '' ?>"><?= (int)$stats['ordres_non_executes'] ?></span></a>
                    <a href="<?= APP_URL ?>/treasury/comptes?type=banque&alertes=1"><span>Rapprochements en attente</span><span class="control-count"><?= (int)$stats['rapprochements_en_attente'] ?></span></a>
                    <a href="<?= APP_URL ?>/treasury/comptes?type=caisse&alertes=1"><span>Écarts de caisse</span><span class="control-count <?= (int)$stats['ecarts_caisse_count'] ? 'is-alert' : '' ?>"><?= (int)$stats['ecarts_caisse_count'] ?></span></a>
                </div>
                <div class="card-body border-top">
                    <div class="d-flex justify-content-between small mb-2"><span class="text-muted">Utilisation du plafond caisse</span><strong><?= number_format($cashRatio,1,',',' ') ?> %</strong></div>
                    <div class="progress" style="height:6px"><div class="progress-bar <?= $cashRatio>=100?'bg-danger':($cashRatio>=80?'bg-warning':'bg-success') ?>" style="width:<?= e((string)$cashRatio) ?>%"></div></div>
                </div>
            </div>
            <div class="cockpit-quick-actions">
                <a class="btn btn-primary" href="<?= APP_URL ?>/treasury/encaissement-ajouter"><i class="bi bi-plus-lg me-1"></i>Encaissement</a>
                <a class="btn btn-outline-primary" href="<?= APP_URL ?>/treasury/demande-ajouter"><i class="bi bi-card-checklist me-1"></i>Demande</a>
                <a class="btn btn-outline-secondary" href="<?= APP_URL ?>/treasury/releve-ajouter"><i class="bi bi-upload me-1"></i>Relevé</a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="card section-card h-100"><div class="card-header"><span class="erp-portlet-title"><i class="bi bi-bar-chart text-primary"></i>Flux de trésorerie</span><a class="erp-portlet-link" href="<?= APP_URL ?>/treasury/rapports">Analyse détaillée</a></div><div class="card-body"><canvas id="treasuryFlowChart" height="118"></canvas><div class="row g-2 border-top pt-3 mt-2 text-center"><div class="col-6"><div class="text-muted small">Encaissements du mois</div><strong class="text-success"><?= formatMontant((float)$stats['encaissements_mois_usd'],'USD') ?></strong></div><div class="col-6"><div class="text-muted small">Décaissements du mois</div><strong class="text-danger"><?= formatMontant((float)$stats['decaissements_mois_usd'],'USD') ?></strong></div></div></div></div>
        </div>
        <div class="col-xl-5">
            <div class="card section-card h-100"><div class="card-header"><span class="erp-portlet-title"><i class="bi bi-bank text-primary"></i>Position par compte</span><a class="erp-portlet-link" href="<?= APP_URL ?>/treasury/comptes">Répertoire complet</a></div><div class="card-body">
                <div class="d-flex justify-content-between pb-3 mb-1 border-bottom"><span class="text-muted small">Solde bancaire consolidé</span><strong><?= formatMontant((float)$positionLiquidite['banque_usd'],'USD') ?></strong></div>
                <?php if(empty($soldesBanque)): ?><div class="py-4 text-center text-muted small">Aucun compte bancaire actif.</div><?php else: ?><ul class="account-balance-list"><?php foreach(array_slice($soldesBanque,0,5) as $compte): ?><li class="account-balance-item"><div class="d-flex align-items-center gap-2"><span class="account-icon"><i class="bi bi-bank"></i></span><span><strong class="d-block small"><?= e($compte['nom']) ?></strong><span class="text-muted small"><?= e($compte['reference_compte'] ?: 'Sans référence') ?></span></span></div><strong class="small text-nowrap"><?= formatMontant((float)$compte['solde'],$compte['devise']) ?></strong></li><?php endforeach; ?></ul><?php endif; ?>
            </div></div>
        </div>
    </div>

    <div class="card section-card">
        <div class="card-header"><span class="erp-portlet-title"><i class="bi bi-clock-history text-primary"></i>Dernières opérations exécutées</span><a class="erp-portlet-link" href="<?= APP_URL ?>/treasury/rapports">Voir le registre</a></div>
        <div class="card-body p-0">
            <?php if(empty($operationsRecentes)): ?><div class="p-5 text-center text-muted">Les opérations validées apparaîtront ici.</div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0 recent-operations"><thead class="table-dark"><tr><th>Date</th><th>Référence</th><th>Flux</th><th>Tiers</th><th class="text-end">Montant</th><th>Statut</th></tr></thead><tbody><?php foreach($operationsRecentes as $op): ?><tr><td><?= formatDate($op['date_operation']) ?></td><td class="fw-semibold text-primary"><?= e($op['reference_operation']) ?></td><td><?= e(treasuryUiLabel($op['type_operation'])) ?></td><td><?= e($op['tiers'] ?: '—') ?></td><td class="text-end fw-semibold <?= $op['type_operation']==='encaissement'?'text-success':'text-danger' ?>"><?= $op['type_operation']==='encaissement'?'+ ':'- ' ?><?= formatMontant((float)$op['amount'],$op['currency']) ?></td><td><span class="badge bg-<?= treasuryUiStatusBadge($op['status']) ?>"><?= e(treasuryUiLabel($op['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </div>
    </div>
</div>

<script>
(function(){var c=document.getElementById('treasuryFlowChart');if(!c||typeof Chart==='undefined')return;new Chart(c,{type:'bar',data:{labels:['Aujourd’hui','Mois en cours'],datasets:[{label:'Encaissements',data:[<?= (float)$stats['encaissements_jour_usd'] ?>,<?= (float)$stats['encaissements_mois_usd'] ?>],backgroundColor:'rgba(22,131,75,.78)',borderRadius:5},{label:'Décaissements',data:[<?= (float)$stats['decaissements_jour_usd'] ?>,<?= (float)$stats['decaissements_mois_usd'] ?>],backgroundColor:'rgba(207,53,66,.72)',borderRadius:5}]},options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,font:{size:11}}}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:'#edf1f5'},ticks:{callback:function(v){return new Intl.NumberFormat('fr-FR',{notation:'compact',maximumFractionDigits:1}).format(v)+' USD';}}}}}});})();
</script>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
