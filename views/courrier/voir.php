<?php $pageTitle = 'Courrier ' . e($courrier['numero_unique']) . ' — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <!-- En-tête -->
    <div class="page-hero">
    <div class="d-flex justify-content-between align-items-start mb-0">
        <div>
            <div class="d-flex align-items-center mb-2">
                <a href="<?= APP_URL ?>/courrier/liste" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-envelope-open me-2 text-primary"></i>
                        <?= e($courrier['numero_unique']) ?>
                    </h2>
                    <span class="badge bg-<?= badgeStatut($courrier['statut']) ?> ms-1 fs-6">
                        <?= e($courrier['statut']) ?>
                    </span>
                    <span class="badge bg-<?= badgeUrgence($courrier['urgence']) ?> ms-1">
                        Urgence : <?= ucfirst(e($courrier['urgence'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Boutons d'action rapide -->
        <div class="d-flex gap-2 flex-wrap">
            <?php if (in_array($_SESSION['user_role'], ['reception','admin','chef_service'])): ?>
                <a href="<?= APP_URL ?>/courrier/modifier/<?= $courrier['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Modifier
                </a>
                <a href="<?= APP_URL ?>/courrier/assigner/<?= $courrier['id'] ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-person-plus me-1"></i>Assigner
                </a>
            <?php endif; ?>

            <!-- Changer le statut -->
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-arrow-repeat me-1"></i>Statut
                </button>
                <ul class="dropdown-menu">
                    <?php foreach (['Recu','Assigne','Accuse','En_cours','Termine','Clos','Archive'] as $s): ?>
                        <?php if ($s !== $courrier['statut']): ?>
                            <li>
                                <a class="dropdown-item" href="#"
                                   onclick="changerStatut(<?= $courrier['id'] ?>, '<?= $s ?>'); return false;">
                                    <span class="badge bg-<?= badgeStatut($s) ?> me-2"><?= $s ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Clôturer -->
            <?php if (!in_array($courrier['statut'], ['Clos','Archive'])): ?>
                <a href="<?= APP_URL ?>/courrier/cloturer/<?= $courrier['id'] ?>"
                   class="btn btn-success btn-sm"
                   onclick="return confirm('Confirmer la clôture ? (Vérifie la présence de preuve)')">
                    <i class="bi bi-check2-circle me-1"></i>Clôturer
                </a>
            <?php endif; ?>

            <!-- Escalader -->
            <button class="btn btn-danger btn-sm" onclick="escalader(<?= $courrier['id'] ?>)">
                <i class="bi bi-exclamation-triangle me-1"></i>Escalader
            </button>
        </div>
    </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="row g-4">

        <!-- INFORMATIONS DU COURRIER -->
        <div class="col-lg-5">
            <div class="card section-card">
                <div class="card-header bg-primary text-white fw-semibold">
                    <i class="bi bi-info-circle me-2"></i>Informations générales
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr><th class="text-muted" width="40%">Numéro :</th><td class="fw-semibold"><?= e($courrier['numero_unique']) ?></td></tr>
                        <tr><th class="text-muted">Référence document :</th><td><?= e($courrier['reference_document'] ?? '—') ?></td></tr>
                        <tr><th class="text-muted">Expéditeur :</th><td><?= e($courrier['expediteur']) ?></td></tr>
                        <tr><th class="text-muted">Type :</th><td><?= e($courrier['type_document'] ?? '—') ?></td></tr>
                        <tr><th class="text-muted">Service :</th><td><?= e($courrier['service_destinataire'] ?? '—') ?></td></tr>
                        <tr><th class="text-muted">Réception :</th><td><?= formatDate($courrier['date_reception']) ?></td></tr>
                        <tr>
                            <th class="text-muted">Deadline :</th>
                            <td>
                                <?php if (!empty($courrier['deadline'])): ?>
                                    <?php
                                    $jours = (int)ceil((strtotime($courrier['deadline']) - time()) / 86400);
                                    $cls   = $jours < 0 ? 'text-danger fw-bold' : ($jours <= 3 ? 'text-warning' : 'text-success');
                                    ?>
                                    <span class="<?= $cls ?>">
                                        <?= formatDate($courrier['deadline']) ?>
                                        <?= $jours < 0 ? "(J+" . abs($jours) . " de retard)" : "(J-$jours)" ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><th class="text-muted">Doc. physique :</th>
                            <td><?= $courrier['recu_physique'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>' ?></td>
                        </tr>
                        <tr><th class="text-muted">Enregistré par :</th><td><?= e($courrier['createur'] ?? '—') ?></td></tr>
                        <tr><th class="text-muted">Enregistré le :</th><td><?= formatDate(substr($courrier['created_at'], 0, 10)) ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Objet -->
            <div class="card section-card mt-3">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-chat-text me-2 text-primary"></i>Objet
                </div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(e($courrier['objet'])) ?></p>
                </div>
            </div>

            <!-- Document scanné -->
            <?php if (!empty($courrier['fichier_scan'])): ?>
                <div class="card section-card mt-3">
                    <div class="card-header bg-transparent border-0 fw-semibold">
                        <i class="bi bi-paperclip me-2 text-primary"></i>Document scanné
                    </div>
                    <div class="card-body text-center">
                        <?php
                        $ext = strtolower(pathinfo($courrier['fichier_scan'], PATHINFO_EXTENSION));
                        $url = UPLOAD_URL . $courrier['fichier_scan'];
                        ?>
                        <div class="small text-muted mb-3">
                            <?= e(basename($courrier['fichier_scan'])) ?>
                        </div>
                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalApercuDocument">
                                <i class="bi bi-eye me-1"></i>Aperçu
                            </button>
                            <a href="<?= $url ?>" target="_blank" download class="btn btn-success btn-sm">
                                <i class="bi bi-download me-1"></i>Télécharger
                            </a>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="modalApercuDocument" tabindex="-1" aria-labelledby="modalApercuDocumentLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalApercuDocumentLabel">
                                    <i class="bi bi-file-earmark-text me-2"></i>Aperçu du document scanné
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body text-center">
                                <?php if ($ext === 'pdf'): ?>
                                    <iframe src="<?= $url ?>" width="100%" height="700px" class="border rounded"></iframe>
                                <?php elseif (in_array($ext, ['jpg','jpeg','png'])): ?>
                                    <img src="<?= $url ?>" class="img-fluid rounded shadow-sm" alt="Document scanné">
                                <?php else: ?>
                                    <div class="alert alert-info mb-0">
                                        L'aperçu intégré n'est pas disponible pour ce type de fichier. Utilisez le téléchargement.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <a href="<?= $url ?>" target="_blank" download class="btn btn-success">
                                    <i class="bi bi-download me-1"></i>Télécharger
                                </a>
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ASSIGNATIONS & PREUVES -->
        <div class="col-lg-7">

            <!-- Assignations -->
            <div class="card section-card mb-4">
                <div class="card-header bg-warning bg-opacity-10 border-0 d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-people me-2 text-warning"></i>Assignations</span>
                    <?php if (in_array($_SESSION['user_role'], ['reception','admin','chef_service'])): ?>
                        <a href="<?= APP_URL ?>/courrier/assigner/<?= $courrier['id'] ?>" class="btn btn-warning btn-sm">
                            <i class="bi bi-plus"></i> Assigner
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($assignations)): ?>
                        <div class="text-center p-3 text-muted"><i class="bi bi-person-x me-2"></i>Aucune assignation</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($assignations as $a): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-semibold"><i class="bi bi-person-fill me-1 text-primary"></i><?= e($a['agent_nom']) ?></span>
                                            <small class="text-muted ms-2"><?= e($a['agent_email']) ?></small>
                                            <div class="mt-1">
                                                <small>Assigné le : <?= formatDate(substr($a['assigned_at'], 0, 10)) ?></small>
                                                <?php if (!empty($a['acknowledged_at'])): ?>
                                                    <span class="badge bg-success ms-2">
                                                        <i class="bi bi-check me-1"></i>Accusé le <?= formatDate(substr($a['acknowledged_at'], 0, 10)) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning ms-2">Non accusé</span>
                                                    <?php if ($a['assigned_to'] == $_SESSION['user_id']): ?>
                                                        <button class="btn btn-success btn-sm ms-2"
                                                                onclick="accuserReception(<?= $courrier['id'] ?>)">
                                                            <i class="bi bi-check-lg"></i> Accuser réception
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($a['instructions'])): ?>
                                                <small class="text-muted d-block mt-1"><i class="bi bi-chat-quote me-1"></i><?= e($a['instructions']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php
                                        switch ($a['statut']) {
                                            case 'accepte':
                                                $badgeAssignation = 'success';
                                                break;
                                            case 'refuse':
                                                $badgeAssignation = 'danger';
                                                break;
                                            case 'termine':
                                                $badgeAssignation = 'secondary';
                                                break;
                                            default:
                                                $badgeAssignation = 'warning';
                                        }
                                        ?>
                                        <span class="badge bg-<?= $badgeAssignation ?>"><?= e($a['statut']) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Preuves -->
            <div class="card section-card mb-4">
                <div class="card-header bg-success bg-opacity-10 border-0 fw-semibold">
                    <i class="bi bi-shield-check me-2 text-success"></i>Preuves
                    <span class="badge bg-success ms-2"><?= count($preuves) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($preuves)): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Aucune preuve. Le courrier ne pourra pas être clôturé sans preuve.
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach ($preuves as $p): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <span class="badge bg-primary me-2"><?= e($p['type_preuve']) ?></span>
                                            <span><?= e($p['commentaire'] ?? '') ?></span>
                                            <small class="text-muted d-block">Par <?= e($p['uploadeur'] ?? 'Inconnu') ?> — <?= formatDate(substr($p['uploaded_at'], 0, 10)) ?></small>
                                        </div>
                                        <?php if (!empty($p['fichier'])): ?>
                                            <a href="<?= UPLOAD_URL . $p['fichier'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <!-- Formulaire ajout preuve -->
                    <hr>
                    <h6 class="fw-semibold mb-3"><i class="bi bi-plus-circle me-2"></i>Ajouter une preuve</h6>
                    <form method="POST" action="<?= APP_URL ?>/courrier/preuve/<?= $courrier['id'] ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <select class="form-select form-select-sm" name="type_preuve" required>
                                    <?php foreach (['reponse','paiement','transmission','accusé','autre'] as $t): ?>
                                        <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="file" class="form-control form-control-sm" name="fichier"
                                       accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" name="commentaire"
                                       placeholder="Commentaire...">
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="bi bi-upload me-1"></i>Joindre la preuve
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Accuser réception via AJAX
function accuserReception(courrierId) {
    if (!confirm('Confirmer l\'accusé de réception ?')) return;

    fetch('<?= APP_URL ?>/courrier/accuser/' + courrierId, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Réception accusée avec succès !');
            location.reload();
        } else {
            alert('Erreur : ' + data.message);
        }
    });
}

// Changer le statut via AJAX
function changerStatut(courrierId, statut) {
    if (!confirm('Changer le statut en "' + statut + '" ?')) return;

    const formData = new FormData();
    formData.append('statut', statut);

    fetch('<?= APP_URL ?>/courrier/statut/' + courrierId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors du changement de statut.');
        }
    });
}

// Escalader
function escalader(courrierId) {
    if (!confirm('Escalader ce courrier aux chefs de service ?')) return;

    fetch('<?= APP_URL ?>/courrier/escalader/' + courrierId, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Courrier escaladé avec succès aux chefs de service.');
            location.reload();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
