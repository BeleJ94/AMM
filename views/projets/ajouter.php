<?php
$estModification = isset($projet) && !empty($projet);
$pageTitle = ($estModification ? 'Modifier' : 'Nouveau') . ' projet — ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="page-hero">
                <div class="d-flex align-items-center mb-0">
                    <a href="<?= APP_URL ?>/projets/liste" class="btn btn-outline-secondary btn-sm me-3">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-<?= $estModification ? 'pencil-square' : 'plus-square' ?> me-2 text-primary"></i>
                        <?= $estModification ? 'Modifier le projet' : 'Creer un projet' ?>
                    </h2>
                </div>
            </div>

            <?php afficherFlash(); ?>

            <?php if (!empty($erreurs)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($erreurs as $e): ?>
                            <li><?= e($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card form-card">
                <div class="card-body p-4">
                    <form method="POST" action="<?= APP_URL ?>/projets/<?= $estModification ? 'modifier/' . (int)$projet['id'] : 'ajouter' ?>">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Titre du projet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="titre" required
                                       value="<?= e($projet['titre'] ?? $_POST['titre'] ?? '') ?>"
                                       placeholder="Ex: Projet de modernisation des operations">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Responsable</label>
                                <select class="form-select" name="responsable_id">
                                    <option value="">-- Selectionner --</option>
                                    <?php
                                    $respValue = (int)($projet['responsable_id'] ?? ($_POST['responsable_id'] ?? 0));
                                    foreach ($responsables as $u):
                                    ?>
                                        <option value="<?= (int)$u['id'] ?>" <?= $respValue === (int)$u['id'] ? 'selected' : '' ?>>
                                            <?= e($u['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" rows="4" name="description" placeholder="Contexte, objectifs, livrables...\"><?= e($projet['description'] ?? $_POST['description'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Budget</label>
                                <input type="number" min="0" step="0.01" class="form-control" name="budget"
                                       value="<?= e($projet['budget'] ?? $_POST['budget'] ?? '0') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Devise</label>
                                <?php $deviseValue = $projet['devise'] ?? $_POST['devise'] ?? 'USD'; ?>
                                <select class="form-select" name="devise">
                                    <option value="USD" <?= $deviseValue === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="CDF" <?= $deviseValue === 'CDF' ? 'selected' : '' ?>>CDF</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date debut <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_debut" required
                                       value="<?= e($projet['date_debut'] ?? $_POST['date_debut'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date fin <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_fin" required
                                       value="<?= e($projet['date_fin'] ?? $_POST['date_fin'] ?? '') ?>">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label fw-semibold">Statut</label>
                                <?php $statutValue = $projet['statut'] ?? $_POST['statut'] ?? 'non_demarre'; ?>
                                <select class="form-select" name="statut">
                                    <option value="non_demarre" <?= $statutValue === 'non_demarre' ? 'selected' : '' ?>>N</option>
                                    <option value="en_cours" <?= $statutValue === 'en_cours' ? 'selected' : '' ?>>C</option>
                                    <option value="termine" <?= $statutValue === 'termine' ? 'selected' : '' ?>>T</option>
                                    <option value="en_retard" <?= $statutValue === 'en_retard' ? 'selected' : '' ?>>R</option>
                                    <option value="suspendu" <?= $statutValue === 'suspendu' ? 'selected' : '' ?>>S</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/projets/liste" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>
                                <?= $estModification ? 'Enregistrer' : 'Creer le projet' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>