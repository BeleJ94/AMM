<?php $pageTitle = 'Médiathèque — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <div class="page-hero">
    <div class="d-flex justify-content-between align-items-center mb-0">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-folder2-open me-2 text-primary"></i>Médiathèque – Documents</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard">Accueil</a></li>
                    <li class="breadcrumb-item active">Documents</li>
                </ol>
            </nav>
        </div>
        <a href="<?= APP_URL ?>/documents/upload" class="btn btn-primary">
            <i class="bi bi-cloud-upload me-1"></i>Uploader un document
        </a>
    </div>
    </div>

    <?php afficherFlash(); ?>

    <!-- Filtres -->
    <div class="card filter-card mb-4">
        <div class="card-body">
            <form method="GET" action="<?= APP_URL ?>/documents/liste">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" name="recherche"
                               placeholder="Rechercher par titre, description, tags..."
                               value="<?= e($filtres['recherche']) ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" name="type_doc">
                            <option value="">Tous les types</option>
                            <?php foreach (['contrat','licence','rapport','facture','procès-verbal','autre'] as $t): ?>
                                <option value="<?= $t ?>" <?= $filtres['type_doc'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" name="service"
                               placeholder="Filtrer par service..."
                               value="<?= e($filtres['service']) ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                            <a href="<?= APP_URL ?>/documents/liste" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau des documents -->
    <div class="card section-card">
        <div class="card-header bg-transparent border-0 fw-semibold">
            <i class="bi bi-files me-2"></i><?= $pagination['total'] ?> document(s)
        </div>
        <div class="card-body p-0">
            <?php if (empty($documents)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-folder fs-3 d-block mb-2"></i>Aucun document trouvé
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tableDocuments" class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Titre</th>
                            <th>Type</th>
                            <th>Service</th>
                            <th>Version</th>
                            <th>Uploadé par</th>
                            <th>Date</th>
                            <th>Expire le</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $d): ?>
                            <?php
                            $ext = strtolower(pathinfo($d['fichier'], PATHINFO_EXTENSION));
                            switch ($ext) {
                                case 'pdf':
                                    $icone = 'bi-file-earmark-pdf text-danger';
                                    break;
                                case 'doc':
                                case 'docx':
                                    $icone = 'bi-file-earmark-word text-primary';
                                    break;
                                case 'xls':
                                case 'xlsx':
                                    $icone = 'bi-file-earmark-excel text-success';
                                    break;
                                case 'jpg':
                                case 'jpeg':
                                case 'png':
                                    $icone = 'bi-file-earmark-image text-info';
                                    break;
                                default:
                                    $icone = 'bi-file-earmark text-secondary';
                            }
                            $expire = !empty($d['expire_le']);
                            $expireJours = $expire ? (int)ceil((strtotime($d['expire_le']) - time()) / 86400) : null;
                            $expireCls   = $expire ? ($expireJours < 0 ? 'text-danger fw-bold' : ($expireJours <= 30 ? 'text-warning' : 'text-muted')) : '';
                            ?>
                            <tr>
                                <td>
                                    <i class="bi <?= $icone ?> me-2"></i>
                                    <a href="<?= APP_URL ?>/documents/voir/<?= $d['id'] ?>" class="fw-semibold text-decoration-none">
                                        <?= e($d['titre']) ?>
                                    </a>
                                    <?php if (!empty($d['tags'])): ?>
                                        <br>
                                        <?php foreach (explode(',', $d['tags']) as $tag): ?>
                                            <span class="badge bg-light text-dark border me-1" style="font-size:0.7rem;"><?= e(trim($tag)) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($d['type_doc'] ?? '—') ?></td>
                                <td><?= e($d['service_proprietaire'] ?? '—') ?></td>
                                <td><span class="badge bg-info text-dark">v<?= $d['version'] ?></span></td>
                                <td><?= e($d['uploadeur_nom'] ?? '—') ?></td>
                                <td><?= formatDate(substr($d['uploaded_at'], 0, 10)) ?></td>
                                <td class="<?= $expireCls ?>">
                                    <?php if ($expire): ?>
                                        <?= formatDate($d['expire_le']) ?>
                                        <?php if ($expireJours !== null && $expireJours <= 30): ?>
                                            <small>(<?= $expireJours < 0 ? 'Expiré' : "J-$expireJours" ?>)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php $urlPreview = UPLOAD_URL . $d['fichier']; ?>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button"
                                                class="btn btn-outline-primary"
                                                title="Aperçu"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalApercuDocument"
                                                data-document-title="<?= e($d['titre']) ?>"
                                                data-document-type="<?= e($ext) ?>"
                                                data-preview-url="<?= e($urlPreview) ?>"
                                                data-download-url="<?= APP_URL ?>/documents/telecharger/<?= $d['id'] ?>"
                                                data-detail-url="<?= APP_URL ?>/documents/voir/<?= $d['id'] ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="<?= APP_URL ?>/documents/telecharger/<?= $d['id'] ?>" class="btn btn-outline-success" title="Télécharger">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <a href="<?= APP_URL ?>/documents/version/<?= $d['id'] ?>" class="btn btn-outline-warning" title="Nouvelle version">
                                            <i class="bi bi-arrow-up-circle"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer d-flex justify-content-end">
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                            <li class="page-item <?= $p == $pagination['page_courante'] ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filtres, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="modalApercuDocument" tabindex="-1" aria-labelledby="modalApercuDocumentLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalApercuDocumentLabel">
                    <i class="bi bi-file-earmark-text me-2"></i><span id="modalDocumentTitle">Aperçu du document</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body text-center" id="modalDocumentBody">
                <div class="text-muted">Chargement de l'aperçu...</div>
            </div>
            <div class="modal-footer">
                <a href="#" id="modalDetailLink" class="btn btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Détail
                </a>
                <a href="#" id="modalDownloadLink" class="btn btn-success">
                    <i class="bi bi-download me-1"></i>Télécharger
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    if ($('#tableDocuments').length) {
        $('#tableDocuments').DataTable({
            paging: false,
            info: false,
            searching: false,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json' },
            columnDefs: [{ orderable: false, targets: 7 }]
        });
    }

    const modalDocument = document.getElementById('modalApercuDocument');
    if (modalDocument) {
        modalDocument.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const title = button.getAttribute('data-document-title') || 'Aperçu du document';
            const type = (button.getAttribute('data-document-type') || '').toLowerCase();
            const previewUrl = button.getAttribute('data-preview-url') || '#';
            const downloadUrl = button.getAttribute('data-download-url') || '#';
            const detailUrl = button.getAttribute('data-detail-url') || '#';

            document.getElementById('modalDocumentTitle').textContent = title;
            document.getElementById('modalDownloadLink').setAttribute('href', downloadUrl);
            document.getElementById('modalDetailLink').setAttribute('href', detailUrl);

            let html = '';
            if (type === 'pdf') {
                html = '<iframe src="' + previewUrl + '" width="100%" height="700px" class="border rounded" style="background:#fff;"></iframe>';
            } else if (['jpg', 'jpeg', 'png'].indexOf(type) !== -1) {
                html = '<img src="' + previewUrl + '" class="img-fluid rounded shadow-sm" alt="Aperçu du document">';
            } else {
                html = '<div class="alert alert-info mb-0">L\'aperçu intégré n\'est pas disponible pour ce type de fichier. Utilisez le téléchargement ou la page de détail.</div>';
            }

            document.getElementById('modalDocumentBody').innerHTML = html;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
