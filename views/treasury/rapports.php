<?php $pageTitle = 'Rapports Trésorerie - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>

<?php
$typesRapports = [
    'situation_globale' => 'Situation de trésorerie globale',
    'caisse_journalier' => 'Rapport journalier de caisse',
    'encaissements' => 'Liste des encaissements',
    'decaissements' => 'Liste des decaissements',
    'demandes_statut' => 'Demandes de fonds par statut',
    'ordres_paiement' => 'Ordres de paiement',
    'rapprochement_mensuel' => 'Rapprochement bancaire mensuel',
];

$methodes = [
    '' => 'Tous les modes',
    'especes' => 'Especes',
    'banque' => 'Banque',
    'mobile_money' => 'Mobile money',
];

$statuts = [
    '' => 'Tous les statuts',
    'brouillon' => 'brouillon',
    'valide' => 'valide',
    'annule' => 'annule',
    'soumise' => 'soumise',
    'validee_comptable' => 'validee_comptable',
    'validee_direction' => 'validee_direction',
    'rejetee' => 'rejetee',
    'ordre_emis' => 'ordre_emis',
    'payee' => 'payee',
    'emis' => 'emis',
    'execute' => 'execute',
    'en_rapprochement' => 'en_rapprochement',
    'rapproche' => 'rapproche',
    'verrouille' => 'verrouille',
];

$moisLibelles = [
    1 => 'Janvier', 2 => 'Fevrier', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Aout',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
];

$tableId = 'tableRapportTresorerie';
$exportName = 'rapport_tresorerie_' . preg_replace('/[^a-z0-9_]+/i', '_', $type);
?>

<style>
@media print {
    .sidebar, .navbar, .app-topbar, .app-sidebar, .app-sidebar-offcanvas, .app-footer,
    .no-print, footer { display: none !important; }
    .app-layout, .main-content { margin-left: 0 !important; padding: 0 !important; display: block !important; }
    .container-fluid { width: 100% !important; max-width: none !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    .page-hero { padding: 0 0 1rem 0 !important; background: transparent !important; }
    body { background: #fff !important; }
    table { font-size: 11px; }
}
</style>

<div class="container-fluid">
    <div class="page-hero no-print">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Rapports Trésorerie</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/dashboard">Trésorerie</a></li>
                        <li class="breadcrumb-item active">Rapports</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" onclick="exporterCSV('<?= e($tableId) ?>', '<?= e($exportName) ?>')">
                    <i class="bi bi-download me-1"></i>CSV
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Imprimer / PDF
                </button>
            </div>
        </div>
    </div>

    <?php afficherFlash(); ?>



    <div class="card section-card">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold"><i class="bi bi-table me-2"></i><?= e($rapport['titre'] ?? ($typesRapports[$type] ?? 'Rapport')) ?></div>
                <div class="small text-muted">
                    Genere le <?= e(date('d/m/Y H:i')) ?> - <?= count($rapport['lignes'] ?? []) ?> ligne(s)
                </div>
            </div>
            <div class="d-flex gap-2 no-print">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exporterCSV('<?= e($tableId) ?>', '<?= e($exportName) ?>')">
                    <i class="bi bi-download me-1"></i>CSV
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>PDF
                </button>
            </div>
        </div>
        <form data-register-filters method="GET" action="<?= APP_URL ?>/treasury/rapports">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-6" data-register-basic>
                        <label class="form-label small fw-semibold">Rapport</label>
                        <select class="form-select form-select-sm" name="type">
                            <?php foreach ($typesRapports as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label small fw-semibold">Date debut</label>
                        <input type="date" class="form-control form-control-sm" name="date_debut" value="<?= e($filtres['date_debut']) ?>">
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label small fw-semibold">Date fin</label>
                        <input type="date" class="form-control form-control-sm" name="date_fin" value="<?= e($filtres['date_fin']) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-semibold">Compte</label>
                        <select class="form-select form-select-sm" name="account_id">
                            <option value="0">Tous les comptes</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?= (int)$compte['id'] ?>" <?= (int)$filtres['account_id'] === (int)$compte['id'] ? 'selected' : '' ?>>
                                    <?= e($compte['nom']) ?> (<?= e($compte['devise']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-2">
                        <label class="form-label small fw-semibold">Annee</label>
                        <input type="number" class="form-control form-control-sm" name="annee" min="2020" max="2100" value="<?= (int)$filtres['annee'] ?>">
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label small fw-semibold">Mois</label>
                        <select class="form-select form-select-sm" name="mois">
                            <option value="0">Tous</option>
                            <?php foreach ($moisLibelles as $numero => $label): ?>
                                <option value="<?= $numero ?>" <?= (int)$filtres['mois'] === $numero ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label small fw-semibold">Statut</label>
                        <select class="form-select form-select-sm" name="status">
                            <?php foreach ($statuts as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($filtres['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label small fw-semibold">Mode paiement</label>
                        <select class="form-select form-select-sm" name="payment_method">
                            <?php foreach ($methodes as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($filtres['payment_method'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label small fw-semibold">Recherche</label>
                        <input type="text" class="form-control form-control-sm" name="recherche" value="<?= e($filtres['recherche'] ?? '') ?>" placeholder="Reference, tiers, objet...">
                    </div>
                    <div class="col-lg-2 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-funnel me-1"></i>Generer
                        </button>
                        <a href="<?= APP_URL ?>/treasury/rapports" class="btn btn-outline-secondary btn-sm" title="Reinitialiser"><i class="bi bi-x"></i></a>
                    </div>
                </div>
            </form>
        <div class="card-body p-0">
            <?php if (empty($rapport['lignes'])): ?>
                <?php treasuryUiEmptyState('bi-inboxes', 'Aucune donnée pour ce rapport', 'Ajustez la période, le compte ou les statuts puis régénérez le rapport.'); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="<?= e($tableId) ?>" data-register-table data-register-order='[]'>
                        <thead class="table-dark">
                            <tr>
                                <?php foreach ($rapport['colonnes'] as $colonne): ?>
                                    <th><?= e($colonne) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rapport['lignes'] as $ligne): ?>
                                <tr>
                                    <?php foreach ($ligne as $valeur): ?>
                                        <td><?= e($valeur === null || $valeur === '' ? '—' : (string)$valeur) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
