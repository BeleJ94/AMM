<?php $pageTitle = 'Uploader un document — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="page-hero">
            <div class="d-flex align-items-center mb-0">
                <a href="<?= APP_URL ?>/documents/liste" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h2 class="fw-bold mb-0">
                    <i class="bi bi-cloud-upload me-2 text-primary"></i>Uploader un document
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
                    <form method="POST" action="<?= APP_URL ?>/documents/upload" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <!-- Zone de dépôt de fichier -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Fichier <span class="text-danger">*</span></label>
                               <div class="border border-2 rounded p-4 text-center pro-drop-zone" id="dropZone"
                                   style="cursor: pointer;">
                                <i class="bi bi-cloud-arrow-up fs-1 text-primary mb-3 d-block"></i>
                                <p class="mb-1">Glissez-déposez votre fichier ici ou</p>
                                <label for="fichier" class="btn btn-outline-primary btn-sm">Parcourir</label>
                                <input type="file" id="fichier" name="fichier" class="d-none" required
                                       accept=".pdf,.jpg,.jpeg,.png,.docx,.doc,.xlsx,.xls"
                                       onchange="afficherFichierChoisi(this)">
                                <p class="text-muted small mt-2 mb-0">PDF, Word, Excel, JPG, PNG — max 10 Mo</p>
                                <div id="nomFichierChoisi" class="mt-2 text-success fw-semibold"></div>
                            </div>
                        </div>

                        <div class="row g-3">

                            <!-- Titre -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Titre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="titre" required
                                       placeholder="Titre du document"
                                       value="<?= e($_POST['titre'] ?? '') ?>">
                            </div>

                            <!-- Type -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Type de document</label>
                                <select class="form-select" name="type_doc">
                                    <option value="">-- Choisir --</option>
                                    <?php foreach (['contrat','licence','rapport','facture','procès-verbal','accord','attestation','autre'] as $t): ?>
                                        <option value="<?= $t ?>" <?= ($_POST['type_doc'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Service propriétaire -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Service propriétaire</label>
                                <select class="form-select" name="service_proprietaire">
                                    <option value="">-- Choisir --</option>
                                    <?php foreach (['Direction','Finance','RH','Juridique','Commercial','Technique','Administration'] as $s): ?>
                                        <option value="<?= $s ?>"><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea class="form-control" name="description" rows="2"
                                          placeholder="Description du contenu du document..."><?= e($_POST['description'] ?? '') ?></textarea>
                            </div>

                            <!-- Projet -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Projet / Affaire</label>
                                <input type="text" class="form-control" name="projet"
                                       placeholder="Nom du projet associé"
                                       value="<?= e($_POST['projet'] ?? '') ?>">
                            </div>

                            <!-- Tags -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tags</label>
                                <input type="text" class="form-control" name="tags"
                                       placeholder="tag1, tag2, tag3"
                                       value="<?= e($_POST['tags'] ?? '') ?>">
                                <small class="text-muted">Séparez les tags par des virgules</small>
                            </div>

                            <!-- Date d'expiration -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Date d'expiration</label>
                                <input type="date" class="form-control" name="expire_le"
                                       value="<?= e($_POST['expire_le'] ?? '') ?>">
                                <small class="text-muted">Optionnel — Pour les licences, contrats, etc.</small>
                            </div>

                        </div>

                        <hr class="my-4">

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/documents/liste" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-1"></i>Uploader le document
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function afficherFichierChoisi(input) {
    if (input.files && input.files[0]) {
        const nom  = input.files[0].name;
        const size = (input.files[0].size / 1024 / 1024).toFixed(2);
        document.getElementById('nomFichierChoisi').innerHTML =
            '<i class="bi bi-file-earmark-check me-1"></i>' + nom + ' (' + size + ' Mo)';
    }
}

// Drag & Drop
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('bg-primary', 'bg-opacity-10'); });
dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('bg-primary', 'bg-opacity-10'); });
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('bg-primary', 'bg-opacity-10');
    const fichierInput = document.getElementById('fichier');
    fichierInput.files = e.dataTransfer.files;
    afficherFichierChoisi(fichierInput);
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
