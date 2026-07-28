<?php $pageTitle = 'Comptes de trésorerie - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>
<?php
if (!function_exists('treasuryAccountIcon')) {
    function treasuryAccountIcon(string $type): string {
        return ['caisse'=>'bi-safe2','banque'=>'bi-bank','mobile_money'=>'bi-phone','autre'=>'bi-wallet2'][$type] ?? 'bi-wallet2';
    }
}
if (!function_exists('treasuryAccountTone')) {
    function treasuryAccountTone(string $status): string {
        if (in_array($status, ['ouverte','rapproche','controlee','verrouillee','actif'], true)) return 'success';
        if (in_array($status, ['solde_insuffisant'], true)) return 'danger';
        if (in_array($status, ['non_ouverte','brouillon','en_rapprochement','sans_releve'], true)) return 'warning';
        return 'secondary';
    }
}
?>
<style>
.account-directory-card { height:100%; overflow:hidden; transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease; }
.account-directory-card:hover { transform:translateY(-2px); border-color:#adc9e8; box-shadow:0 11px 25px rgba(19,50,82,.1); }
.account-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.account-type-icon { width:39px; height:39px; display:inline-flex; align-items:center; justify-content:center; border-radius:9px; color:#1769e0; background:#e9f2ff; font-size:1rem; }
.account-name { color:#172b43; font-size:.9rem; font-weight:800; }
.account-reference { color:#778698; font-size:.67rem; }
.account-balance-label { color:#718096; font-size:.64rem; font-weight:700; text-transform:uppercase; letter-spacing:.045em; }
.account-balance-value { color:#122b48; font-size:1.3rem; font-weight:850; letter-spacing:-.035em; }
.account-availability { padding:10px 0; border-top:1px solid #edf1f5; border-bottom:1px solid #edf1f5; }
.account-metric { color:#718096; font-size:.65rem; }.account-metric strong { display:block; margin-top:2px; color:#31465d; font-size:.76rem; }
.account-last-operation { color:#718096; font-size:.66rem; }
.account-filter-bar { border:1px solid var(--erp-border); border-radius:8px; background:#fff; box-shadow:var(--erp-shadow); }
.account-directory-kpi .erp-kpi-value { font-size:1.2rem; }
</style>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3">
            <div><nav aria-label="breadcrumb"><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/dashboard">Trésorerie</a></li><li class="breadcrumb-item active">Comptes</li></ol></nav><h2>Comptes de trésorerie</h2><p class="text-muted small mb-0 mt-1">Pilotez les liquidités, engagements et contrôles depuis un répertoire unique.</p></div>
            <a href="<?= APP_URL ?>/admin-finance" class="btn btn-outline-primary btn-sm"><i class="bi bi-gear me-1"></i>Paramétrer les comptes</a>
        </div>
    </div>
    <?php afficherFlash(); ?>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="card erp-kpi erp-kpi-primary account-directory-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Liquidité consolidée</div><span class="erp-kpi-icon"><i class="bi bi-wallet2"></i></span></div><div class="erp-kpi-value"><?= formatMontant((float)$resume['liquidite_usd'],'USD') ?></div><div class="erp-kpi-meta"><?= (int)$resume['total'] ?> compte(s) actif(s)</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card erp-kpi <?= $resume['disponible_usd'] >= 0 ? 'erp-kpi-success':'erp-kpi-danger' ?> account-directory-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Disponible réel</div><span class="erp-kpi-icon"><i class="bi bi-shield-check"></i></span></div><div class="erp-kpi-value <?= $resume['disponible_usd'] >= 0 ? 'text-success':'text-danger' ?>"><?= formatMontant((float)$resume['disponible_usd'],'USD') ?></div><div class="erp-kpi-meta">Après déduction des engagements</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card erp-kpi erp-kpi-warning account-directory-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">OP engagés</div><span class="erp-kpi-icon"><i class="bi bi-file-earmark-check"></i></span></div><div class="erp-kpi-value"><?= formatMontant((float)$resume['engagements_usd'],'USD') ?></div><div class="erp-kpi-meta">Paiements émis non exécutés</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card erp-kpi <?= $resume['alertes'] ? 'erp-kpi-danger':'erp-kpi-success' ?> account-directory-kpi"><div class="card-body"><div class="d-flex justify-content-between"><div class="erp-kpi-label">Comptes à contrôler</div><span class="erp-kpi-icon"><i class="bi bi-exclamation-triangle"></i></span></div><div class="erp-kpi-value <?= $resume['alertes'] ? 'text-danger':'text-success' ?>"><?= (int)$resume['alertes'] ?></div><div class="erp-kpi-meta">Action ou contrôle requis</div></div></div></div>
    </div>

    <div class="account-filter-bar p-3 mb-3">
        <form method="GET" action="<?= APP_URL ?>/treasury/comptes">
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small fw-semibold">Type de compte</label><select class="form-select form-select-sm" name="type"><option value="">Tous les types</option><?php foreach(['caisse'=>'Caisse','banque'=>'Banque','mobile_money'=>'Mobile Money','autre'=>'Autre'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $filtres['type']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label small fw-semibold">Devise</label><select class="form-select form-select-sm" name="devise"><option value="">Toutes</option><?php foreach(['USD','CDF'] as $currency): ?><option value="<?= $currency ?>" <?= $filtres['devise']===$currency?'selected':'' ?>><?= $currency ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Recherche</label><input class="form-control form-control-sm" name="recherche" value="<?= e($filtres['recherche']) ?>" placeholder="Nom ou référence du compte"></div>
                <div class="col-md-1"><div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="alertes" value="1" id="accountAlertsOnly" <?= $filtres['alertesSeulement']?'checked':'' ?>><label class="form-check-label small" for="accountAlertsOnly">Alertes</label></div></div>
                <div class="col-md-2 d-flex gap-1"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Filtrer</button><a href="<?= APP_URL ?>/treasury/comptes" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a></div>
            </div>
        </form>
    </div>

    <?php if (empty($comptes)): ?>
        <div class="card section-card"><?php treasuryUiEmptyState('bi-wallet2', 'Aucun compte trouvé', 'Ajustez les filtres ou activez un compte financier.'); ?></div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach($comptes as $compte): ?>
                <?php $tone=treasuryAccountTone($compte['status_operationnel']); ?>
                <div class="col-md-6 col-xxl-4">
                    <div class="card account-directory-card">
                        <div class="card-body">
                            <div class="account-card-head mb-3"><div class="d-flex gap-2"><span class="account-type-icon"><i class="bi <?= e(treasuryAccountIcon($compte['type_compte'])) ?>"></i></span><div><div class="account-name"><?= e($compte['nom']) ?></div><div class="account-reference"><?= e(treasuryUiLabel($compte['type_compte'])) ?> · <?= e($compte['devise']) ?> · <?= e($compte['reference_compte'] ?: 'Sans référence') ?></div></div></div><span class="badge bg-<?= e($tone) ?> bg-opacity-10 text-<?= e($tone) ?>"><?= e(treasuryUiLabel($compte['status_operationnel'])) ?></span></div>
                            <div class="mb-3"><div class="account-balance-label">Solde comptable</div><div class="account-balance-value"><?= formatMontant((float)$compte['solde'],$compte['devise']) ?></div></div>
                            <div class="account-availability d-flex justify-content-between gap-3 mb-3"><div class="account-metric">OP engagés<strong><?= formatMontant((float)$compte['engagements'],$compte['devise']) ?></strong></div><div class="account-metric text-end">Disponible réel<strong class="<?= $compte['disponible']<0?'text-danger':'text-success' ?>"><?= formatMontant((float)$compte['disponible'],$compte['devise']) ?></strong></div></div>
                            <div class="row g-2 mb-3"><div class="col-6 account-metric">Entrées du mois<strong class="text-success"><?= formatMontant((float)$compte['entrees_mois'],$compte['devise']) ?></strong></div><div class="col-6 account-metric text-end">Sorties du mois<strong class="text-danger"><?= formatMontant((float)$compte['sorties_mois'],$compte['devise']) ?></strong></div></div>
                            <div class="d-flex justify-content-between align-items-center gap-3"><div class="account-last-operation"><i class="bi bi-clock me-1"></i><?= $compte['derniere_operation'] ? 'Dernière opération : '.formatDate($compte['derniere_operation']) : 'Aucune opération enregistrée' ?></div><a href="<?= APP_URL ?>/treasury/compte/<?= (int)$compte['id'] ?>" class="btn btn-outline-primary btn-sm">Ouvrir<i class="bi bi-arrow-right ms-1"></i></a></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
