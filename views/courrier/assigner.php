<?php $pageTitle = 'Assigner le courrier — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="page-hero">
            <div class="d-flex align-items-center mb-0">
                <a href="<?= APP_URL ?>/courrier/voir/<?= $courrier['id'] ?>" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-person-plus me-2 text-warning"></i>Assigner le courrier
                    </h2>
                    <small class="text-muted"><?= e($courrier['numero_unique']) ?> — <?= e(substr($courrier['objet'], 0, 60)) ?>...</small>
                </div>
            </div>
            </div>

            <?php afficherFlash(); ?>

            <!-- Résumé du courrier -->
            <div class="card summary-card mb-4 border-start border-primary border-3">
                <div class="card-body py-2">
                    <div class="row">
                        <div class="col-md-4"><strong>Expéditeur :</strong> <?= e($courrier['expediteur']) ?></div>
                        <div class="col-md-4"><strong>Deadline :</strong> <?= formatDate($courrier['deadline']) ?></div>
                        <div class="col-md-4">
                            <strong>Urgence :</strong>
                            <span class="badge bg-<?= badgeUrgence($courrier['urgence']) ?>"><?= ucfirst(e($courrier['urgence'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card form-card">
                <div class="card-body p-4">
                    <form method="POST" action="<?= APP_URL ?>/courrier/assigner/<?= $courrier['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <!-- Sélection des agents -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Sélectionner le(s) agent(s) <span class="text-danger">*</span></label>
                            <div class="border rounded p-3" style="max-height: 280px; overflow-y: auto;">
                                <?php
                                // Grouper par service
                                $usersParService = [];
                                foreach ($users as $u) {
                                    $usersParService[$u['service'] ?? 'Autre'][] = $u;
                                }
                                ?>
                                <?php foreach ($usersParService as $service => $agentsDuService): ?>
                                    <div class="mb-2">
                                        <small class="text-uppercase text-muted fw-bold"><?= e($service) ?></small>
                                        <?php foreach ($agentsDuService as $u): ?>
                                            <div class="form-check ms-2">
                                                <input class="form-check-input" type="checkbox"
                                                       name="agents[]" value="<?= $u['id'] ?>"
                                                       id="agent_<?= $u['id'] ?>">
                                                <label class="form-check-label" for="agent_<?= $u['id'] ?>">
                                                    <strong><?= e($u['nom']) ?></strong>
                                                    <span class="badge bg-secondary ms-1"><?= e($u['role']) ?></span>
                                                    <small class="text-muted"><?= e($u['email']) ?></small>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-1 d-flex gap-2">
                                <button type="button" class="btn btn-link btn-sm p-0" onclick="toutCocher()">Tout sélectionner</button>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" onclick="toutDecocher()">Tout désélectionner</button>
                            </div>
                        </div>

                        <!-- Date limite pour l'agent -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date limite pour l'agent</label>
                            <input type="date" class="form-control" name="due_date"
                                   value="<?= e($courrier['deadline'] ?? '') ?>">
                        </div>

                        <!-- Instructions -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Instructions spéciales</label>
                            <textarea class="form-control" name="instructions" rows="3"
                                      placeholder="Instructions à communiquer à l'agent..."></textarea>
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/courrier/voir/<?= $courrier['id'] ?>" class="btn btn-outline-secondary">
                                Annuler
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-person-check me-1"></i>Assigner le courrier
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function toutCocher() {
    document.querySelectorAll('input[name="agents[]"]').forEach(cb => cb.checked = true);
}
function toutDecocher() {
    document.querySelectorAll('input[name="agents[]"]').forEach(cb => cb.checked = false);
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
