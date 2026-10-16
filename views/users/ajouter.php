<?php
$estModification = isset($user) && !empty($user);
$pageTitle = ($estModification ? 'Modifier' : 'Ajouter') . ' un utilisateur — ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="page-hero">
            <div class="d-flex align-items-center mb-0">
                <a href="<?= APP_URL ?>/users/liste" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-<?= $estModification ? 'pencil' : 'person-plus' ?> me-2 text-primary"></i>
                        <?= $estModification ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur' ?>
                    </h2>
                </div>
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
                    <form method="POST" action="<?= APP_URL ?>/users/<?= $estModification ? 'modifier/' . $user['id'] : 'ajouter' ?>">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="row g-3">

                            <!-- Nom complet -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nom complet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nom" required
                                       value="<?= e($user['nom'] ?? $_POST['nom'] ?? '') ?>">
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Adresse e-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" required
                                       value="<?= e($user['email'] ?? $_POST['email'] ?? '') ?>">
                            </div>

                            <!-- Mot de passe -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <?= $estModification ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe *' ?>
                                </label>
                                <input type="password" class="form-control" name="<?= $estModification ? 'nouveau_mdp' : 'password' ?>"
                                       <?= !$estModification ? 'required' : '' ?>>
                                <small class="text-muted">Stocké en clair pour l'instant</small>
                            </div>

                            <!-- Rôle -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Rôle <span class="text-danger">*</span></label>
                                <select class="form-select" name="role" required>
                                    <?php
                                    $roles = rolesApplication();
                                    $roleActuel = $user['role'] ?? $_POST['role'] ?? 'agent';
                                    foreach ($roles as $r => $libelle):
                                    ?>
                                        <option value="<?= $r ?>" <?= $roleActuel === $r ? 'selected' : '' ?>>
                                            <?= e($libelle) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Service -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Service / Département</label>
                                <select class="form-select" name="service_id" aria-label="Département de l’utilisateur">
                                    <option value="">Non affecté</option>
                                    <?php $serviceSelectionne = (int)($_POST['service_id'] ?? $user['service_id'] ?? 0); ?>
                                    <?php foreach ($departements as $departement): ?>
                                        <option value="<?= (int)$departement['id'] ?>" <?= $serviceSelectionne === (int)$departement['id'] ? 'selected' : '' ?> <?= !$departement['actif'] && (int)($user['service_id'] ?? 0) !== (int)$departement['id'] ? 'disabled' : '' ?>><?= e($departement['nom']) ?><?= $departement['actif'] ? '' : ' · Inactif' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Gérer la liste dans <a href="<?= APP_URL ?>/admin-departements">Administration → Départements</a>.</div>
                            </div>

                            <!-- Téléphone -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Téléphone</label>
                                <input type="text" class="form-control" name="telephone"
                                       placeholder="+243 xxx xxx xxx"
                                       value="<?= e($user['telephone'] ?? $_POST['telephone'] ?? '') ?>">
                            </div>

                        </div>

                        <hr class="my-4">

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/users/liste" class="btn btn-outline-secondary">
                                <i class="bi bi-x me-1"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>
                                <?= $estModification ? 'Enregistrer les modifications' : 'Créer l\'utilisateur' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
