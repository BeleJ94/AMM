<?php
$estModification = isset($contrat) && !empty($contrat);
$pageTitle = ($estModification ? 'Modifier' : 'Nouveau') . ' contrat — ' . APP_NAME;
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <div class="page-hero">
            <div class="d-flex align-items-center mb-0">
                <a href="<?= APP_URL ?>/contrats/liste" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h2 class="fw-bold mb-0">
                    <i class="bi bi-file-earmark-<?= $estModification ? 'pen' : 'plus' ?> me-2 text-primary"></i>
                    <?= $estModification ? 'Modifier le contrat' : 'Nouveau contrat' ?>
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
                    <form method="POST" action="<?= APP_URL ?>/contrats/<?= $estModification ? 'modifier/' . $contrat['id'] : 'ajouter' ?>">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">

                        <div class="row g-3">

                            <!-- Partenaire -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Partenaire <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="partenaire" required
                                       placeholder="Nom de l'entreprise ou personne"
                                       value="<?= e($contrat['partenaire'] ?? $_POST['partenaire'] ?? '') ?>">
                            </div>

                            <!-- Objet -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Objet du contrat <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="objet" required
                                       placeholder="Objet ou titre du contrat"
                                       value="<?= e($contrat['objet'] ?? $_POST['objet'] ?? '') ?>">
                            </div>

                            <!-- Valeur -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Valeur du contrat</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="valeur" step="0.01" min="0"
                                           value="<?= e($contrat['valeur'] ?? $_POST['valeur'] ?? '0') ?>">
                                    <select class="form-select" name="devise" style="max-width: 80px;">
                                        <option value="USD" <?= ($contrat['devise'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD</option>
                                        <option value="CDF" <?= ($contrat['devise'] ?? '') === 'CDF' ? 'selected' : '' ?>>CDF</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Date signature -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date de signature</label>
                                <input type="date" class="form-control" name="date_signature"
                                       value="<?= e($contrat['date_signature'] ?? $_POST['date_signature'] ?? '') ?>">
                            </div>

                            <!-- Reconduction tacite -->
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="tacite_reconduction" id="reconduction"
                                           <?= ($contrat['tacite_reconduction'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="reconduction">
                                        Reconduction tacite
                                    </label>
                                </div>
                            </div>

                            <!-- Date début -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date de début <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_debut" required
                                       value="<?= e($contrat['date_debut'] ?? $_POST['date_debut'] ?? '') ?>">
                            </div>

                            <!-- Date fin -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date de fin <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_fin" required
                                       value="<?= e($contrat['date_fin'] ?? $_POST['date_fin'] ?? '') ?>">
                            </div>

                            <!-- Statut (modification seulement) -->
                            <?php if ($estModification): ?>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Statut</label>
                                <select class="form-select" name="statut">
                                    <?php foreach (['actif','expire','resilie','renouvele'] as $s): ?>
                                        <option value="<?= $s ?>" <?= ($contrat['statut'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <!-- Document lié -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Document contractuel associé</label>
                                <select class="form-select" name="document_id">
                                    <option value="">-- Lier à un document existant --</option>
                                    <?php foreach ($documents as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= ($contrat['document_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                            [v<?= $d['version'] ?>] <?= e($d['titre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Ou <a href="<?= APP_URL ?>/documents/upload" target="_blank">uploader un nouveau document</a></small>
                            </div>

                        </div>

                        <hr class="my-4">

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= APP_URL ?>/contrats/liste" class="btn btn-outline-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>
                                <?= $estModification ? 'Enregistrer les modifications' : 'Créer le contrat' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Section paiements (en mode modification) -->
            <?php if ($estModification && isset($paiements)): ?>
                <div class="card section-card mt-4">
                    <div class="card-header bg-success bg-opacity-10 fw-semibold border-0">
                        <i class="bi bi-cash-coin me-2 text-success"></i>Suivi des paiements
                        <span class="badge bg-success ms-1"><?= count($paiements) ?></span>
                    </div>
                    <div class="card-body p-0">
                            <table id="tableContratPaiements" class="table table-sm table-hover align-middle compact mb-0" data-register-table>
                                <thead class="table-light">
                                    <tr><th>Date</th><th>Montant</th><th>Commentaire</th><th>Preuve</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paiements as $p): ?>
                                        <tr>
                                            <td><?= formatDate($p['date_paiement']) ?></td>
                                            <td><?= formatMontant($p['montant'], $contrat['devise']) ?></td>
                                            <td><?= e($p['commentaire'] ?? '—') ?></td>
                                            <td>
                                                <?php if (!empty($p['preuve_fichier'])): ?>
                                                    <a href="<?= UPLOAD_URL . $p['preuve_fichier'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <td colspan="1"><strong>Total payé :</strong></td>
                                        <td colspan="3"><strong><?= formatMontant($totalPaye, $contrat['devise']) ?></strong>
                                            <?php if ($resteAPayer > 0): ?>
                                                <span class="text-danger ms-3">(Reste : <?= formatMontant($resteAPayer, $contrat['devise']) ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                    </div>
                    <!-- Formulaire ajout paiement -->
                    <div class="card-footer">
                        <h6 class="fw-semibold mb-2"><i class="bi bi-plus-circle me-2"></i>Ajouter un paiement</h6>
                        <form method="POST" action="<?= APP_URL ?>/contrats/paiement/<?= $contrat['id'] ?>" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <input type="date" class="form-control form-control-sm" name="date_paiement"
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control form-control-sm" name="montant"
                                           step="0.01" min="0" placeholder="Montant" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control form-control-sm" name="commentaire"
                                           placeholder="Commentaire...">
                                </div>
                                <div class="col-md-2">
                                    <input type="file" class="form-control form-control-sm" name="preuve_fichier"
                                           accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-success btn-sm w-100">
                                        <i class="bi bi-plus"></i> Enregistrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
