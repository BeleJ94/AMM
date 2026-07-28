<?php $pageTitle = 'Détail facture — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-file-earmark-text me-2 text-primary"></i><?= e($facture['numero_facture']) ?></h2>
                <p class="text-muted mb-0"><?= e($facture['partenaire']) ?> - <?= e($facture['objet']) ?></p>
            </div>
            <a href="<?= APP_URL ?>/finance/factures" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Retour</a>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card section-card mb-3">
                <div class="card-header bg-transparent border-0 fw-semibold">Informations facture</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><small class="text-muted">Type</small><div><span class="badge bg-<?= $facture['type_facture'] === 'client' ? 'primary' : 'warning text-dark' ?>"><?= e($facture['type_facture']) ?></span></div></div>
                        <div class="col-md-4"><small class="text-muted">Statut</small><div><span class="badge bg-secondary"><?= e($facture['statut']) ?></span></div></div>
                        <div class="col-md-4"><small class="text-muted">Devise</small><div><?= e($facture['devise']) ?></div></div>
                        <div class="col-md-4"><small class="text-muted">Date facture</small><div><?= formatDate($facture['date_facture']) ?></div></div>
                        <div class="col-md-4"><small class="text-muted">Échéance</small><div><?= formatDate($facture['date_echeance']) ?></div></div>
                        <div class="col-md-4"><small class="text-muted">Créé par</small><div><?= e($facture['created_by_nom'] ?? '—') ?></div></div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-4"><small class="text-muted">Montant total</small><div class="fw-semibold"><?= formatMontant((float)$facture['montant_total'], $facture['devise']) ?></div></div>
                        <div class="col-md-4"><small class="text-muted">Montant payé</small><div class="fw-semibold text-success"><?= formatMontant((float)$totalPaye, $facture['devise']) ?></div></div>
                        <div class="col-md-4"><small class="text-muted">Reste</small><div class="fw-semibold <?= $reste > 0 ? 'text-danger' : 'text-success' ?>"><?= formatMontant((float)$reste, $facture['devise']) ?></div></div>
                    </div>
                    <?php if (!empty($facture['description'])): ?>
                        <hr>
                        <small class="text-muted d-block">Description</small>
                        <p class="mb-0"><?= e($facture['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card section-card mb-3">
                <div class="card-header bg-transparent border-0 fw-semibold">Paiements</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Date</th><th>Montant</th><th>Référence</th><th>Commentaire</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paiements)): ?>
                                    <tr><td colspan="4" class="text-center py-3 text-muted">Aucun paiement.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($paiements as $p): ?>
                                    <tr>
                                        <td><?= formatDate($p['date_paiement']) ?></td>
                                        <td><?= formatMontant((float)$p['montant'], $p['devise']) ?></td>
                                        <td><?= e($p['reference_paiement'] ?? '—') ?></td>
                                        <td><?= e($p['commentaire'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card section-card">
                <div class="card-header bg-transparent border-0 fw-semibold">Relances</div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (empty($relances)): ?>
                            <li class="list-group-item text-muted">Aucune relance.</li>
                        <?php endif; ?>
                        <?php foreach ($relances as $r): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span><span class="badge bg-warning text-dark me-1"><?= e($r['reminder_level']) ?></span><?= e($r['reminder_channel']) ?></span>
                                    <small class="text-muted"><?= e(substr((string)$r['reminded_at'], 0, 16)) ?></small>
                                </div>
                                <?php if (!empty($r['reminder_note'])): ?>
                                    <div class="small mt-1"><?= e($r['reminder_note']) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card section-card mb-3">
                <div class="card-header bg-transparent border-0 fw-semibold">Actions</div>
                <div class="card-body">
                    <?php if ($facture['statut'] === 'brouillon'): ?>
                        <form method="POST" action="<?= APP_URL ?>/finance/facture-valider/<?= (int)$facture['id'] ?>" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                            <button type="submit" class="btn btn-success w-100"><i class="bi bi-check2-circle me-1"></i>Valider la facture</button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" action="<?= APP_URL ?>/finance/facture-paiement/<?= (int)$facture['id'] ?>" enctype="multipart/form-data" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                        <h6 class="fw-semibold">Ajouter un paiement</h6>
                        <div class="row g-2">
                            <div class="col-md-6"><input type="date" class="form-control form-control-sm" name="date_paiement" value="<?= date('Y-m-d') ?>" required></div>
                            <div class="col-md-6"><input type="number" class="form-control form-control-sm" name="montant" min="0" step="0.01" placeholder="Montant" required></div>
                            <div class="col-md-6">
                                <select class="form-select form-select-sm" name="devise">
                                    <option value="USD" <?= $facture['devise'] === 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="CDF" <?= $facture['devise'] === 'CDF' ? 'selected' : '' ?>>CDF</option>
                                </select>
                            </div>
                            <div class="col-md-6"><input type="text" class="form-control form-control-sm" name="reference_paiement" placeholder="Référence"></div>
                            <div class="col-12"><input type="text" class="form-control form-control-sm" name="commentaire" placeholder="Commentaire"></div>
                            <div class="col-12"><input type="file" class="form-control form-control-sm" name="piece_jointe" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></div>
                            <div class="col-12"><button class="btn btn-primary btn-sm w-100" type="submit">Enregistrer paiement</button></div>
                        </div>
                    </form>

                    <form method="POST" action="<?= APP_URL ?>/finance/facture-relance/<?= (int)$facture['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                        <h6 class="fw-semibold">Ajouter une relance</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <select class="form-select form-select-sm" name="reminder_level">
                                    <option value="R1">R1</option>
                                    <option value="R2">R2</option>
                                    <option value="R3">R3</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <select class="form-select form-select-sm" name="reminder_channel">
                                    <option value="email">Email</option>
                                    <option value="telephone">Téléphone</option>
                                    <option value="courrier">Courrier</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <textarea class="form-control form-control-sm" name="reminder_note" rows="3" placeholder="Note de relance..."></textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-warning btn-sm w-100" type="submit">Enregistrer relance</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
