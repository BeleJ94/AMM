<?php $pageTitle = 'Nouveau releve bancaire - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<?php
$moisLibelles = [
    1 => 'Janvier', 2 => 'Fevrier', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Aout',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
];
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="page-hero">
                <div class="d-flex align-items-center mb-0">
                    <a href="<?= APP_URL ?>/treasury/banque" class="btn btn-outline-secondary btn-sm me-3"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h2 class="fw-bold mb-1"><i class="bi bi-bank me-2 text-primary"></i>Nouveau releve bancaire</h2>
                        <div class="text-muted small">Un seul releve mensuel est autorise par compte bancaire.</div>
                    </div>
                </div>
            </div>

            <?php afficherFlash(); ?>

            <?php if (!empty($erreurs)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($erreurs as $erreur): ?>
                            <li><?= e($erreur) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card form-card">
                <div class="card-body p-4">
                    <form method="POST" action="<?= APP_URL ?>/treasury/releve-ajouter" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Compte bancaire <span class="text-danger">*</span></label>
                                <select class="form-select" name="account_id" required>
                                    <option value="">-- Selectionner --</option>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= (int)$compte['id'] ?>" data-currency="<?= e($compte['devise']) ?>" <?= (int)($_POST['account_id'] ?? 0) === (int)$compte['id'] ? 'selected' : '' ?>>
                                            <?= e($compte['nom']) ?> (<?= e($compte['devise']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Mois <span class="text-danger">*</span></label>
                                <select class="form-select" name="period_month" required>
                                    <?php foreach ($moisLibelles as $numero => $libelle): ?>
                                        <option value="<?= $numero ?>" <?= (int)($_POST['period_month'] ?? date('n')) === $numero ? 'selected' : '' ?>><?= e($libelle) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Annee <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="period_year" min="2020" max="2100" value="<?= e($_POST['period_year'] ?? date('Y')) ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Numero releve</label>
                                <input type="text" class="form-control" name="statement_number" value="<?= e($_POST['statement_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Devise <span class="text-danger">*</span></label>
                                <select class="form-select" name="currency" required>
                                    <option value="USD" <?= ($_POST['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="CDF" <?= ($_POST['currency'] ?? '') === 'CDF' ? 'selected' : '' ?>>CDF</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Fichier releve</label>
                                <input type="file" class="form-control" name="statement_file" accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.csv">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Solde ouverture <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="opening_balance" value="<?= e($_POST['opening_balance'] ?? '0') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Solde cloture <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="closing_balance" value="<?= e($_POST['closing_balance'] ?? '0') ?>" required>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <a href="<?= APP_URL ?>/treasury/banque" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Creer le releve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
