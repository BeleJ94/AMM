<?php $pageTitle = 'Banque et rapprochement - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>

<?php
if (!function_exists('treasuryBankStatusBadge')) {
    function treasuryBankStatusBadge(string $status): string {
        switch ($status) {
            case 'brouillon':
                return 'secondary';
            case 'en_rapprochement':
                return 'warning text-dark';
            case 'rapproche':
                return 'success';
            case 'verrouille':
                return 'dark';
            default:
                return 'secondary';
        }
    }
}

$moisLibelles = [
    1 => 'Janvier', 2 => 'Fevrier', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Aout',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
];
?>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-bank me-2 text-primary"></i>Banque / rapprochement</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/treasury/dashboard">Trésorerie</a></li>
                        <li class="breadcrumb-item active">Banque</li>
                    </ol>
                </nav>
            </div>
            <a href="<?= APP_URL ?>/treasury/releve-ajouter" class="btn btn-primary align-self-start align-self-lg-center">
                <i class="bi bi-plus-lg me-1"></i>Nouveau releve
            </a>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="GET" action="<?= APP_URL ?>/treasury/banque">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Compte bancaire</label>
                        <select class="form-select form-select-sm" name="account_id">
                            <option value="">Tous les comptes</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?= (int)$compte['id'] ?>" <?= (int)($filtres['account_id'] ?? 0) === (int)$compte['id'] ? 'selected' : '' ?>>
                                    <?= e($compte['nom']) ?> (<?= e($compte['devise']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Annee</label>
                        <input type="number" class="form-control form-control-sm" name="period_year" min="2020" max="2100" value="<?= e($filtres['period_year'] ?? date('Y')) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Mois</label>
                        <select class="form-select form-select-sm" name="period_month">
                            <option value="0">Tous</option>
                            <?php foreach ($moisLibelles as $numero => $libelle): ?>
                                <option value="<?= $numero ?>" <?= (int)($filtres['period_month'] ?? 0) === $numero ? 'selected' : '' ?>><?= e($libelle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Statut</label>
                        <select class="form-select form-select-sm" name="status">
                            <option value="">Tous</option>
                            <?php foreach (['brouillon','en_rapprochement','rapproche','verrouille'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($filtres['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Filtrer</button>
                        <a href="<?= APP_URL ?>/treasury/banque" class="btn btn-outline-secondary btn-sm" title="Reinitialiser"><i class="bi bi-x"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card section-card">
        <div class="card-header bg-transparent border-0 fw-semibold">
            <i class="bi bi-list-check me-2"></i>Releves bancaires
        </div>
        <div class="card-body p-0">
            <?php if (empty($releves)): ?>
                <?php treasuryUiEmptyState('bi-bank', 'Aucun relevé bancaire trouvé', 'Créez un relevé mensuel pour démarrer le rapprochement.', '<a href="' . APP_URL . '/treasury/releve-ajouter" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Nouveau relevé</a>'); ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Periode</th>
                                <th>Compte</th>
                                <th>Numero</th>
                                <th>Solde ouverture</th>
                                <th>Solde cloture</th>
                                <th>Statut</th>
                                <th>Cree par</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($releves as $releve): ?>
                                <tr>
                                    <td><?= e($moisLibelles[(int)$releve['period_month']] ?? $releve['period_month']) ?> <?= (int)$releve['period_year'] ?></td>
                                    <td><?= e($releve['account_nom']) ?></td>
                                    <td><?= e($releve['statement_number'] ?? '—') ?></td>
                                    <td><?= formatMontant((float)$releve['opening_balance'], $releve['currency']) ?></td>
                                    <td><?= formatMontant((float)$releve['closing_balance'], $releve['currency']) ?></td>
                                    <td><span class="badge bg-<?= treasuryUiStatusBadge($releve['status']) ?>"><?= e(treasuryUiLabel($releve['status'])) ?></span></td>
                                    <td><?= e($releve['created_by_nom'] ?? '—') ?></td>
                                    <td class="text-center">
                                        <a href="<?= APP_URL ?>/treasury/rapprochement/<?= (int)$releve['id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-arrow-left-right me-1"></i>Rapprocher
                                        </a>
                                    </td>
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
