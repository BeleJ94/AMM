<?php
$estModification = isset($courrier) && !empty($courrier);
$pageTitle = ($estModification ? 'Modifier' : 'Nouveau') . ' courrier — ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <div class="page-hero">
            <div class="d-flex align-items-center mb-0">
                <a href="<?= APP_URL ?>/courrier/liste" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-<?= $estModification ? 'pencil' : 'envelope-plus' ?> me-2 text-primary"></i>
                        <?= $estModification ? 'Modifier le courrier' : 'Enregistrer un nouveau courrier' ?>
                    </h2>
                    <?php if ($estModification): ?>
                        <small class="text-muted"><?= e($courrier['numero_unique']) ?></small>
                    <?php endif; ?>
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
                    <form method="POST"
                          action="<?= APP_URL ?>/courrier/<?= $estModification ? 'modifier/' . $courrier['id'] : 'ajouter' ?>"
                          enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="row g-3">

                            <!-- Expéditeur -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expéditeur <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="expediteur" required
                                       placeholder="Nom de l'expéditeur ou organisme"
                                       value="<?= e($courrier['expediteur'] ?? $_POST['expediteur'] ?? '') ?>">
                            </div>

                            <!-- Référence du document -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Référence du document</label>
                                <input type="text" class="form-control" name="reference_document"
                                       placeholder="Ex: DGDA/CF/2026/041"
                                       value="<?= e($courrier['reference_document'] ?? $_POST['reference_document'] ?? '') ?>">
                                <small class="text-muted">Référence indiquée sur le courrier ou le document reçu.</small>
                            </div>

                            <!-- Type de document -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Type de document</label>
                                <select class="form-select" name="type_document">
                                    <option value="">-- Choisir --</option>
                                    <?php
                                    $types = ['DGDA','Fiscal','RH','Contrat','Juridique','Commercial','Administratif','Technique','Autre'];
                                    $typeActuel = $courrier['type_document'] ?? $_POST['type_document'] ?? '';
                                    foreach ($types as $t):
                                    ?>
                                        <option value="<?= $t ?>" <?= $typeActuel === $t ? 'selected' : '' ?>><?= $t ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Objet -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Objet du courrier <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="objet" rows="3" required
                                          placeholder="Description de l'objet du courrier"><?= e($courrier['objet'] ?? $_POST['objet'] ?? '') ?></textarea>
                            </div>

                            <!-- Urgence -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Niveau d'urgence</label>
                                <select class="form-select" name="urgence">
                                    <?php
                                    $urgenceActuelle = $courrier['urgence'] ?? $_POST['urgence'] ?? 'basse';
                                    foreach (['basse' => 'Basse', 'moyenne' => 'Moyenne', 'haute' => 'Haute'] as $v => $l):
                                    ?>
                                        <option value="<?= $v ?>" <?= $urgenceActuelle === $v ? 'selected' : '' ?>>
                                            <?= $l ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Date de réception -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date de réception <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_reception" required
                                       value="<?= e($courrier['date_reception'] ?? $_POST['date_reception'] ?? date('Y-m-d')) ?>">
                            </div>

                            <!-- Deadline -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Deadline (réponse attendue)</label>
                                <input type="date" class="form-control" name="deadline"
                                       value="<?= e($courrier['deadline'] ?? $_POST['deadline'] ?? '') ?>">
                            </div>

                            <!-- Service destinataire -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Service destinataire</label>
                                <select class="form-select" name="service_destinataire">
                                    <option value="">-- Choisir --</option>
                                    <?php
                                    $services = ['Direction','Finance','RH','Juridique','Commercial','Technique','Administration','Réception'];
                                    $serviceActuel = $courrier['service_destinataire'] ?? $_POST['service_destinataire'] ?? '';
                                    foreach ($services as $s):
                                    ?>
                                        <option value="<?= $s ?>" <?= $serviceActuel === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Fichier scan -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Document scanné</label>
                                <input type="file" class="form-control" name="fichier_scan"
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG, PNG — max 10 Mo</small>
                                <?php if (!empty($courrier['fichier_scan'])): ?>
                                    <div class="mt-1">
                                        <small class="text-success"><i class="bi bi-paperclip"></i> Fichier existant : <?= e($courrier['fichier_scan']) ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Reçu physique -->
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="recu_physique" id="recuPhysique"
                                           <?= ($courrier['recu_physique'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="recuPhysique">
                                        Document physique reçu (original papier en possession)
                                    </label>
                                </div>
                            </div>

                        </div>

                        <hr class="my-4">

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/courrier/liste" class="btn btn-outline-secondary">
                                <i class="bi bi-x me-1"></i>Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>
                                <?= $estModification ? 'Enregistrer les modifications' : 'Enregistrer le courrier' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
