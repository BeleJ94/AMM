<?php $pageTitle = e($document['titre']) . ' — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">

            <div class="page-hero">
            <div class="d-flex align-items-center mb-0">
                <a href="<?= APP_URL ?>/documents/liste" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-file-earmark me-2 text-primary"></i><?= e($document['titre']) ?>
                    </h2>
                    <span class="badge bg-info text-dark me-1">v<?= $document['version'] ?></span>
                    <span class="badge bg-secondary"><?= e($document['type_doc'] ?? 'Document') ?></span>
                </div>
            </div>
            </div>

            <?php afficherFlash(); ?>

            <!-- Informations du document -->
            <div class="card section-card mb-4">
                <div class="card-header bg-primary text-white fw-semibold">
                    <i class="bi bi-info-circle me-2"></i>Informations
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm mb-0">
                                <tr><th class="text-muted">Titre :</th><td><?= e($document['titre']) ?></td></tr>
                                <tr><th class="text-muted">Type :</th><td><?= e($document['type_doc'] ?? '—') ?></td></tr>
                                <tr><th class="text-muted">Service :</th><td><?= e($document['service_proprietaire'] ?? '—') ?></td></tr>
                                <tr><th class="text-muted">Projet :</th><td><?= e($document['projet'] ?? '—') ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm mb-0">
                                <tr><th class="text-muted">Version :</th><td><span class="badge bg-info text-dark">v<?= $document['version'] ?></span></td></tr>
                                <tr><th class="text-muted">Uploadé par :</th><td><?= e($document['uploadeur_nom'] ?? '—') ?></td></tr>
                                <tr><th class="text-muted">Date :</th><td><?= formatDate(substr($document['uploaded_at'], 0, 10)) ?></td></tr>
                                <tr><th class="text-muted">Expire le :</th>
                                    <td><?= !empty($document['expire_le']) ? formatDate($document['expire_le']) : '—' ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if (!empty($document['description'])): ?>
                        <hr>
                        <p class="mb-0"><strong>Description :</strong> <?= e($document['description']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($document['tags'])): ?>
                        <div class="mt-2">
                            <?php foreach (explode(',', $document['tags']) as $tag): ?>
                                <span class="badge bg-light text-dark border me-1"><?= e(trim($tag)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3 d-flex gap-2">
                        <a href="<?= APP_URL ?>/documents/telecharger/<?= $document['id'] ?>" class="btn btn-success">
                            <i class="bi bi-download me-1"></i>Télécharger v<?= $document['version'] ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Aperçu du fichier -->
            <?php
            $ext = strtolower(pathinfo($document['fichier'], PATHINFO_EXTENSION));
            $urlFichier = UPLOAD_URL . $document['fichier'];
            ?>
            <?php if ($ext === 'pdf'): ?>
                <div class="card section-card mb-4">
                    <div class="card-header bg-transparent border-0 fw-semibold">
                        <i class="bi bi-eye me-2"></i>Aperçu PDF
                    </div>
                    <div class="card-body p-0">
                        <iframe src="<?= $urlFichier ?>" width="100%" height="500px" style="border:none;"></iframe>
                    </div>
                </div>
            <?php elseif (in_array($ext, ['jpg','jpeg','png'])): ?>
                <div class="card section-card mb-4">
                    <div class="card-body text-center">
                        <img src="<?= $urlFichier ?>" class="img-fluid rounded" alt="<?= e($document['titre']) ?>">
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <div class="col-lg-4">

            <!-- Historique des versions -->
            <div class="card section-card mb-4">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Historique des versions
                    <span class="badge bg-primary ms-1"><?= count($versions) ?></span>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_reverse($versions) as $v): ?>
                            <li class="list-group-item <?= $v['id'] == $document['id'] ? 'bg-primary bg-opacity-10' : '' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-<?= $v['id'] == $document['id'] ? 'primary' : 'secondary' ?> me-2">v<?= $v['version'] ?></span>
                                        <small><?= formatDate(substr($v['uploaded_at'], 0, 10)) ?></small>
                                        <small class="text-muted d-block"><?= e($v['uploadeur_nom'] ?? '—') ?></small>
                                    </div>
                                    <?php if ($v['id'] != $document['id']): ?>
                                        <a href="<?= APP_URL ?>/documents/voir/<?= $v['id'] ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-success">Actuel</span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Nouvelle version -->
            <div class="card form-card">
                <div class="card-header bg-warning bg-opacity-10 border-0 fw-semibold">
                    <i class="bi bi-arrow-up-circle me-2 text-warning"></i>Uploader une nouvelle version
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= APP_URL ?>/documents/version/<?= $document['id'] ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nouveau fichier <span class="text-danger">*</span></label>
                            <input type="file" class="form-control form-control-sm" name="fichier" required
                                   accept=".pdf,.jpg,.jpeg,.png,.docx,.doc,.xlsx,.xls">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Note de version</label>
                            <textarea class="form-control form-control-sm" name="description" rows="2"
                                      placeholder="Changements apportés..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nouvelle date d'expiration</label>
                            <input type="date" class="form-control form-control-sm" name="expire_le"
                                   value="<?= e($document['expire_le'] ?? '') ?>">
                        </div>

                        <button type="submit" class="btn btn-warning w-100">
                            <i class="bi bi-upload me-1"></i>Uploader (v<?= $document['version'] + 1 ?>)
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
