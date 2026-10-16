<?php $pageTitle = 'Rapprochement bancaire - ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/_ui.php'; ?>

<?php
if (!function_exists('treasuryLineStatusBadge')) {
    function treasuryLineStatusBadge(string $status): string {
        switch ($status) {
            case 'rapproche':
                return 'success';
            case 'ecart':
                return 'danger';
            default:
                return 'secondary';
        }
    }
}

$moisLibelles = [
    1 => 'Janvier', 2 => 'Fevrier', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Aout',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
];
$estVerrouille = in_array($releve['status'], ['rapproche','verrouille'], true)
    || in_array($rapprochement['status'] ?? '', ['valide','verrouille'], true);
$totalDebit = (float)($rapprochement['total_debit'] ?? array_sum(array_map(static fn($l) => (float)$l['debit'], $lignes)));
$totalCredit = (float)($rapprochement['total_credit'] ?? array_sum(array_map(static fn($l) => (float)$l['credit'], $lignes)));
$matchedAmount = (float)($rapprochement['matched_amount'] ?? 0);
$varianceAmount = (float)($rapprochement['variance_amount'] ?? array_sum(array_map(static fn($l) => $l['status'] === 'non_rapproche' ? max((float)$l['debit'], (float)$l['credit']) : 0, $lignes)));
$periode = ($moisLibelles[(int)$releve['period_month']] ?? $releve['period_month']) . ' ' . (int)$releve['period_year'];
$soldeCourantReleve = (float)$releve['opening_balance'];
$lignesAvecSolde = [];
foreach ($lignes as $ligne) {
    $soldeCourantReleve += (float)$ligne['credit'] - (float)$ligne['debit'];
    $ligne['running_balance'] = $soldeCourantReleve;
    $lignesAvecSolde[] = $ligne;
}
?>

<style>
.treasury-compact-table {
    font-size: .82rem;
}
.treasury-compact-table > :not(caption) > * > * {
    padding: .45rem .55rem;
    vertical-align: middle;
}
.movement-detail-row td {
    background: #f8fbff;
    border-top: 0;
}
.movement-detail-row.collapse.show,
.movement-detail-row.collapsing {
    display: table-row;
}
.movement-toggle {
    width: 30px;
    height: 30px;
    padding: 0;
}
@media print {
    .sidebar, .navbar, .btn, .no-print, footer { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    .page-hero { padding: 0 0 1rem 0 !important; background: transparent !important; }
    body { background: #fff !important; }
}
</style>

<div class="container-fluid">
    <div class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-0">
            <div class="d-flex align-items-center">
                <a href="<?= APP_URL ?>/treasury/banque" class="btn btn-outline-secondary btn-sm me-3 no-print"><i class="bi bi-arrow-left"></i></a>
                <div>
                    <h2 class="fw-bold mb-1"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Rapprochement bancaire</h2>
                    <div class="text-muted small"><?= e($releve['account_nom']) ?> - <?= e($periode) ?></div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-secondary no-print align-self-start align-self-lg-center" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Imprimer
            </button>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <?php if ($estVerrouille): ?>
        <div class="alert alert-dark">
            <i class="bi bi-lock-fill me-1"></i>Rapprochement valide et verrouille. Les lignes ne peuvent plus etre modifiees.
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card section-card h-100"><div class="card-body">
                <div class="text-muted small">Solde ouverture</div>
                <div class="h5 fw-bold mb-0"><?= formatMontant((float)$releve['opening_balance'], $releve['currency']) ?></div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card section-card h-100"><div class="card-body">
                <div class="text-muted small">Solde cloture</div>
                <div class="h5 fw-bold mb-0"><?= formatMontant((float)$releve['closing_balance'], $releve['currency']) ?></div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card section-card h-100"><div class="card-body">
                <div class="text-muted small">Montant rapproche</div>
                <div class="h5 fw-bold text-success mb-0"><?= formatMontant($matchedAmount, $releve['currency']) ?></div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card section-card h-100"><div class="card-body">
                <div class="text-muted small">Ecart / non rapproche</div>
                <div class="h5 fw-bold <?= $varianceAmount > 0.01 ? 'text-danger' : 'text-success' ?> mb-0"><?= formatMontant($varianceAmount, $releve['currency']) ?></div>
            </div></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card section-card mb-4">
                <div class="card-header bg-transparent border-0 fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-table me-2"></i>Lignes du releve</span>
                    <span class="d-flex align-items-center gap-2">
                        <span class="small text-muted">Debit: <?= formatMontant($totalDebit, $releve['currency']) ?> | Credit: <?= formatMontant($totalCredit, $releve['currency']) ?></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm no-print" onclick="exporterCSV('bankStatementLinesTable', 'lignes_releve_bancaire')">
                            <i class="bi bi-file-earmark-excel me-1"></i>Excel
                        </button>
                    </span>
                </div>
                <form data-register-filters data-register-client="true" data-register-movement="bankStatementLinesTable" class="no-print">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3"><label class="form-label small fw-semibold" for="bankStatementLinesTableStart">Date début</label><input id="bankStatementLinesTableStart" type="date" name="movement_start" class="form-control form-control-sm" data-filter-start></div>
                            <div class="col-md-3"><label class="form-label small fw-semibold" for="bankStatementLinesTableEnd">Date fin</label><input id="bankStatementLinesTableEnd" type="date" name="movement_end" class="form-control form-control-sm" data-filter-end></div>
                            <div class="col-md-2"><button type="reset" class="btn btn-outline-secondary btn-sm" title="Réinitialiser les dates"><i class="bi bi-arrow-counterclockwise me-1"></i>Réinitialiser</button></div>
                        </div>
                    </form>
                <div class="card-body p-0">
                    <?php if (empty($lignesAvecSolde)): ?>
                        <?php treasuryUiEmptyState('bi-inboxes', 'Aucune ligne saisie', 'Ajoutez les mouvements du relevé bancaire pour commencer le rapprochement.'); ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 treasury-compact-table" id="bankStatementLinesTable" data-register-table data-register-options='{"order":[[1,"asc"]],"columnDefs":[{"orderable":false,"targets":[0,7]}]}'>
                                <thead class="table-dark">
                                    <tr>
                                        <th class="no-print" data-export-skip="1"></th>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th class="text-end">Solde</th>
                                        <th>Statut</th>
                                        <th class="text-center no-print" data-export-skip="1">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lignesAvecSolde as $index => $ligne): ?>
                                        <?php $detailId = 'bankLineDetail' . (int)$index; ?>
                                        <tr class="movement-master-row"
                                            data-date="<?= e($ligne['operation_date']) ?>"
                                            data-sort="<?= e($ligne['operation_date'] . ' ' . sprintf('%08d', (int)$ligne['id'])) ?>">
                                            <td class="no-print" data-export-skip="1">
                                                <button class="btn btn-outline-secondary btn-sm movement-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($detailId) ?>" aria-expanded="false" title="Voir les détails">
                                                    <i class="bi bi-chevron-down"></i>
                                                </button>
                                            </td>
                                            <td data-order="<?= e($ligne['operation_date']) ?>"><?= formatDate($ligne['operation_date']) ?></td>
                                            <td class="fw-semibold"><?= e($ligne['reference_operation'] ?? '—') ?></td>
                                            <td class="text-end text-danger" data-export-value="<?= e(number_format((float)$ligne['debit'], 2, '.', '')) ?>"><?= (float)$ligne['debit'] > 0 ? formatMontant((float)$ligne['debit'], $releve['currency']) : '—' ?></td>
                                            <td class="text-end text-success" data-export-value="<?= e(number_format((float)$ligne['credit'], 2, '.', '')) ?>"><?= (float)$ligne['credit'] > 0 ? formatMontant((float)$ligne['credit'], $releve['currency']) : '—' ?></td>
                                            <td class="text-end fw-semibold" data-export-value="<?= e(number_format((float)$ligne['running_balance'], 2, '.', '')) ?>"><?= formatMontant((float)$ligne['running_balance'], $releve['currency']) ?></td>
                                            <td><span class="badge bg-<?= treasuryUiStatusBadge($ligne['status']) ?>"><?= e(treasuryUiLabel($ligne['status'])) ?></span></td>
                                            <td class="text-center no-print" data-export-skip="1">
                                                <?php if (!$estVerrouille && $ligne['status'] !== 'rapproche'): ?>
                                                    <form method="POST" action="<?= APP_URL ?>/treasury/rapprochement/<?= (int)$releve['id'] ?>" class="d-flex flex-column flex-md-row gap-1">
                                                        <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                                        <input type="hidden" name="action" value="rapprocher_ligne">
                                                        <input type="hidden" name="line_id" value="<?= (int)$ligne['id'] ?>">
                                                        <select name="transaction_id" class="form-select form-select-sm" required>
                                                            <option value="">Transaction...</option>
                                                            <?php foreach ($transactions as $transaction): ?>
                                                                <option value="<?= (int)$transaction['id'] ?>">
                                                                    <?= e($transaction['reference_unique']) ?> - <?= formatMontant((float)$transaction['montant'], $transaction['devise']) ?> - <?= e($transaction['partenaire'] ?? '') ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-outline-success btn-sm text-nowrap" title="Associer"><i class="bi bi-link-45deg me-1"></i>Associer</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr class="movement-detail-row collapse" id="<?= e($detailId) ?>" data-export-skip="1">
                                            <td colspan="8">
                                                <div class="row g-2 small">
                                                    <div class="col-md-5"><span class="text-muted">Libellé:</span> <strong><?= e($ligne['label']) ?></strong></div>
                                                    <div class="col-md-3"><span class="text-muted">Transaction:</span> <?= e($ligne['transaction_reference'] ?? '—') ?></div>
                                                    <div class="col-md-4"><span class="text-muted">Commentaire:</span> <?= e($ligne['line_comment'] ?? '—') ?></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <?php if (!$estVerrouille): ?>
                <div class="card form-card mb-4 no-print">
                    <div class="card-header bg-transparent border-0 fw-semibold">
                        <i class="bi bi-plus-circle me-2"></i>Ajouter une ligne
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= APP_URL ?>/treasury/rapprochement/<?= (int)$releve['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                            <input type="hidden" name="action" value="ajouter_ligne">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Date</label>
                                <input type="date" class="form-control" name="operation_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Libelle</label>
                                <input type="text" class="form-control" name="label" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Reference</label>
                                <input type="text" class="form-control" name="reference_operation">
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Debit</label>
                                    <input type="number" class="form-control" name="debit" step="0.01" min="0" value="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold">Credit</label>
                                    <input type="number" class="form-control" name="credit" step="0.01" min="0" value="0">
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label fw-semibold">Commentaire</label>
                                <textarea class="form-control" name="line_comment" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mt-3">
                                <i class="bi bi-save me-1"></i>Ajouter
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card section-card mb-4">
                <div class="card-header bg-transparent border-0 fw-semibold">
                    <i class="bi bi-file-earmark-text me-2"></i>Rapport mensuel
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr><th class="text-muted">Compte</th><td><?= e($releve['account_nom']) ?></td></tr>
                        <tr><th class="text-muted">Periode</th><td><?= e($periode) ?></td></tr>
                        <tr><th class="text-muted">Statut releve</th><td><span class="badge bg-<?= treasuryUiStatusBadge($releve['status']) ?>"><?= e(treasuryUiLabel($releve['status'])) ?></span></td></tr>
                        <tr><th class="text-muted">Statut rapprochement</th><td><span class="badge bg-<?= treasuryUiStatusBadge($rapprochement['status'] ?? 'brouillon') ?>"><?= e(treasuryUiLabel($rapprochement['status'] ?? 'brouillon')) ?></span></td></tr>
                        <tr><th class="text-muted">Valide par</th><td><?= e($rapprochement['reconciled_by_nom'] ?? '—') ?></td></tr>
                        <tr><th class="text-muted">Date validation</th><td><?= !empty($rapprochement['reconciled_at']) ? e(date('d/m/Y H:i', strtotime($rapprochement['reconciled_at']))) : '—' ?></td></tr>
                    </table>
                    <?php if (!empty($releve['statement_file'])): ?>
                        <a href="<?= UPLOAD_URL . e($releve['statement_file']) ?>" target="_blank" class="btn btn-outline-primary w-100 no-print">
                            <i class="bi bi-paperclip me-1"></i>Voir le fichier releve
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$estVerrouille && $peutValiderRapprochement): ?>
                <div class="card form-card no-print">
                    <div class="card-header bg-transparent border-0 fw-semibold">
                        <i class="bi bi-lock me-2 text-success"></i>Valider et verrouiller
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= APP_URL ?>/treasury/rapprochement/<?= (int)$releve['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                            <input type="hidden" name="action" value="valider">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Commentaire</label>
                                <textarea class="form-control" name="commentaire" rows="3" placeholder="Conclusion du rapprochement"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success w-100" onclick="return confirm('Valider et verrouiller ce rapprochement ?')">
                                <i class="bi bi-check2-circle me-1"></i>Valider le rapprochement
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $movementRegisterId = 'bankStatementLinesTable'; require __DIR__ . '/_register_movement.php'; ?>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
