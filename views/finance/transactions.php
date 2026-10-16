<?php $pageTitle = 'Transactions financières — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-center mb-0">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-cash-stack me-2 text-success"></i>Transactions financières</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/finance/overview">Financial Tracking</a></li>
                        <li class="breadcrumb-item active">Transactions</li>
                    </ol>
                </nav>
            </div>
            <a href="<?= APP_URL ?>/finance/ajouter" class="btn btn-success">
                <i class="bi bi-plus-lg me-1"></i>Nouveau flux
            </a>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="card section-card">
        <div class="card-header bg-transparent border-0 fw-semibold">
            <i class="bi bi-files me-2"></i><?= (int)$pagination['total'] ?> transaction(s)
        </div>
        <form data-register-filters method="GET" action="<?= APP_URL ?>/finance/transactions">
                <input type="hidden" name="per_page" value="<?= (int)$parPage ?>">
                <div class="row g-2 align-items-center">
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="type_flux">
                            <option value="">Type flux</option>
                            <option value="revenu" <?= $filtres['type_flux'] === 'revenu' ? 'selected' : '' ?>>Revenu</option>
                            <option value="depense" <?= $filtres['type_flux'] === 'depense' ? 'selected' : '' ?>>Dépense</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="statut">
                            <option value="">Statut</option>
                            <option value="brouillon" <?= $filtres['statut'] === 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                            <option value="valide" <?= $filtres['statut'] === 'valide' ? 'selected' : '' ?>>Validé</option>
                            <option value="annule" <?= $filtres['statut'] === 'annule' ? 'selected' : '' ?>>Annulé</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <select class="form-select form-select-sm" name="devise">
                            <option value="">Devise</option>
                            <option value="USD" <?= $filtres['devise'] === 'USD' ? 'selected' : '' ?>>USD</option>
                            <option value="CDF" <?= $filtres['devise'] === 'CDF' ? 'selected' : '' ?>>CDF</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <input type="number" class="form-control form-control-sm" min="2020" max="2100" name="annee" value="<?= (int)$filtres['annee'] ?>">
                    </div>
                    <div class="col-md-1">
                        <select class="form-select form-select-sm" name="mois">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= (int)$filtres['mois'] === $m ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" name="recherche" placeholder="Réf, partenaire, catégorie..." value="<?= e($filtres['recherche']) ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
                        <a href="<?= APP_URL ?>/finance/transactions" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                    </div>
                </div>
            </form>

        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" data-register-table data-register-mode="server" data-register-total="<?= (int)$pagination['total'] ?>" data-register-page-size="<?= (int)$parPage ?>">
                    <thead class="table-dark">
                        <tr>
                            <th>Réf</th>
                            <th>Date</th>
                            <th>Flux</th>
                            <th>Catégorie</th>
                            <th>Compte</th>
                            <th>Montant</th>
                            <th>USD base</th>
                            <th>Statut</th>
                            <th>Créé par</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <tr data-register-state="<?= e($t['statut']) ?>" data-register-complete="false">
                                <td class="fw-semibold"><?= e($t['reference_unique']) ?></td>
                                <td><?= formatDate($t['date_operation']) ?></td>
                                <td><span class="badge bg-<?= $t['type_flux'] === 'revenu' ? 'success' : 'danger' ?>"><?= e($t['type_flux']) ?></span></td>
                                <td><?= e($t['categorie_nom']) ?></td>
                                <td><?= e($t['compte_nom']) ?></td>
                                <td><?= formatMontant((float)$t['montant'], $t['devise']) ?></td>
                                <td><?= formatMontant((float)$t['montant_usd'], 'USD') ?></td>
                                <td data-register-state="<?= e($t['statut']) ?>">
                                    <?php
                                    $badge = 'secondary';
                                    if ($t['statut'] === 'valide') $badge = 'success';
                                    if ($t['statut'] === 'brouillon') $badge = 'warning text-dark';
                                    if ($t['statut'] === 'annule') $badge = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= e(['valide' => 'Validée', 'brouillon' => 'Brouillon', 'annule' => 'Annulée'][$t['statut']] ?? $t['statut']) ?></span>
                                    <?php if ($t['statut'] === 'annule'): ?>
                                        <details class="small mt-1" style="max-width:240px">
                                            <summary class="text-muted" style="cursor:pointer">Motif et traçabilité</summary>
                                            <div class="mt-2 text-wrap"><?= nl2br(e($t['cancellation_reason'] ?? '')) ?></div>
                                            <div class="text-muted mt-1"><?= e($t['cancelled_by_nom'] ?? 'Utilisateur autorisé') ?>
                                                <?php if (!empty($t['cancelled_at'])): ?> · <?= e(date('d/m/Y à H:i', strtotime($t['cancelled_at']))) ?><?php endif; ?>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($t['created_by_nom'] ?? '—') ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <?php if ($t['statut'] === 'brouillon'): ?>
                                            <form method="POST" action="<?= APP_URL ?>/finance/valider/<?= (int)$t['id'] ?>" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Valider">
                                                    <i class="bi bi-check2-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($t['statut'] !== 'annule' && $peutAnnulerTransaction): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Annuler avec un motif"
                                                data-bs-toggle="modal" data-bs-target="#transactionCancelModal"
                                                data-action="<?= APP_URL ?>/finance/annuler/<?= (int)$t['id'] ?>"
                                                data-reference="<?= e($t['reference_unique']) ?>"
                                                data-amount="<?= e(formatMontant((float)$t['montant'], $t['devise'])) ?>"
                                                data-account="<?= e($t['compte_nom']) ?>">
                                                <i class="bi bi-x-circle me-1"></i>Annuler
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer d-flex justify-content-end" data-register-pagination>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                            <li class="page-item <?= $p == $pagination['page_courante'] ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($filtres, ['page' => $p, 'per_page' => $parPage])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($peutAnnulerTransaction): ?>
<div class="modal fade" id="transactionCancelModal" tabindex="-1" aria-labelledby="transactionCancelTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow" id="transactionCancelForm">
            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
            <div class="modal-header border-0 pb-0">
                <div>
                    <div class="text-uppercase text-muted small fw-semibold mb-1">Contrôle de la Direction</div>
                    <h5 class="modal-title fw-bold" id="transactionCancelTitle">Annuler la transaction</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="rounded-3 p-3 mb-3" style="background:#f3f6fa">
                    <div class="d-flex justify-content-between gap-3"><strong id="transactionCancelReference"></strong><strong id="transactionCancelAmount"></strong></div>
                    <div class="text-muted small mt-1" id="transactionCancelAccount"></div>
                </div>
                <p class="small text-muted">L’encaissement ou le paiement lié sera également annulé et les soldes seront recalculés. Le dossier et le motif resteront consultables dans l’historique.</p>
                <label for="transactionCancelReason" class="form-label fw-semibold">Motif de l’annulation <span class="text-danger">*</span></label>
                <textarea name="motif" id="transactionCancelReason" class="form-control" rows="4" required maxlength="2000" placeholder="Précisez l’erreur et la correction attendue…" aria-describedby="transactionCancelHelp"></textarea>
                <div class="form-text" id="transactionCancelHelp">Une opération rapprochée ou rattachée à une journée clôturée nécessite une régularisation comptable.</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Conserver la transaction</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Confirmer l’annulation</button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('transactionCancelModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) return;
    const form = document.getElementById('transactionCancelForm');
    form.action = button.dataset.action;
    form.reset();
    document.getElementById('transactionCancelReference').textContent = button.dataset.reference;
    document.getElementById('transactionCancelAmount').textContent = button.dataset.amount;
    document.getElementById('transactionCancelAccount').textContent = button.dataset.account;
});
document.getElementById('transactionCancelModal').addEventListener('shown.bs.modal', function () {
    document.getElementById('transactionCancelReason').focus();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
