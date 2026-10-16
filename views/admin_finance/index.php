<?php $pageTitle = 'Paramètres financiers — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">
    <div class="page-hero">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-sliders me-2 text-primary"></i>Paramètres financiers</h2>
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard">Accueil</a></li>
                <li class="breadcrumb-item">Administration</li>
                <li class="breadcrumb-item active">Paramètres financiers</li>
            </ol></nav>
        </div>
    </div>

    <?php afficherFlash(); ?>

    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#comptes" type="button"><i class="bi bi-wallet2 me-1"></i>Comptes</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#categories" type="button"><i class="bi bi-tags me-1"></i>Catégories</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#regles" type="button"><i class="bi bi-shield-check me-1"></i>Règles de trésorerie</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="comptes">
            <div class="row g-4">
                <div class="col-xl-4">
                    <div class="card form-card">
                        <div class="card-header bg-transparent fw-semibold"><i class="bi bi-plus-circle me-2"></i><span id="titreCompte">Nouveau compte</span></div>
                        <div class="card-body">
                            <form method="POST" action="<?= APP_URL ?>/admin-finance/compte-enregistrer" id="formCompte">
                                <input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                                <input type="hidden" name="id" id="compteId" value="">
                                <div class="mb-3"><label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label><input class="form-control" name="nom" id="compteNom" maxlength="120" required></div>
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label fw-semibold">Type</label><select class="form-select" name="type_compte" id="compteType" required><option value="caisse">Caisse</option><option value="banque">Banque</option><option value="mobile_money">Mobile money</option><option value="autre">Autre</option></select></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold">Devise</label><select class="form-select" name="devise" id="compteDevise" required><option value="USD">USD</option><option value="CDF">CDF</option></select></div>
                                </div>
                                <div class="my-3"><label class="form-label fw-semibold">Référence du compte</label><input class="form-control" name="reference_compte" id="compteReference" maxlength="120" placeholder="N° compte, caisse ou wallet"></div>
                                <div class="d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg me-1"></i>Enregistrer</button><button type="button" class="btn btn-outline-secondary d-none" id="annulerCompte">Annuler</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="card section-card"><div class="card-header bg-transparent fw-semibold">Comptes financiers</div><div class="card-body p-0"><div class="table-responsive">
                        <table data-register-table data-register-filter-columns='{"1":"Type","2":"Devise","4":"Statut"}' class="table table-sm table-hover mb-0"><thead class="table-dark"><tr><th>Nom</th><th>Type</th><th>Devise</th><th>Référence</th><th>Statut</th><th class="text-end">Actions</th></tr></thead><tbody>
                        <?php foreach ($comptes as $compte): ?>
                            <tr class="<?= $compte['actif'] ? '' : 'table-secondary' ?>" data-register-state="<?= $compte['actif'] ? 'actif' : 'inactif' ?>" data-register-complete="false"><td class="fw-semibold"><?= e($compte['nom']) ?></td><td><?= e(ucfirst(str_replace('_', ' ', $compte['type_compte']))) ?></td><td><span class="badge bg-info text-dark"><?= e($compte['devise']) ?></span></td><td><?= e($compte['reference_compte'] ?: '—') ?></td><td data-register-state="<?= $compte['actif'] ? 'actif' : 'inactif' ?>"><span class="badge bg-<?= $compte['actif'] ? 'success' : 'secondary' ?>"><?= $compte['actif'] ? 'Actif' : 'Inactif' ?></span></td><td class="text-end"><div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-outline-primary btn-sm modifier-compte" data-id="<?= (int)$compte['id'] ?>" data-nom="<?= e($compte['nom']) ?>" data-type="<?= e($compte['type_compte']) ?>" data-devise="<?= e($compte['devise']) ?>" data-reference="<?= e($compte['reference_compte']) ?>"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="<?= APP_URL ?>/admin-finance/compte-toggle/<?= (int)$compte['id'] ?>" onsubmit="return confirm('Confirmer le changement de statut de ce compte ?')"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><button class="btn btn-outline-<?= $compte['actif'] ? 'danger' : 'success' ?> btn-sm"><i class="bi bi-<?= $compte['actif'] ? 'slash-circle' : 'check-circle' ?>"></i></button></form>
                            </div></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    </div></div></div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="categories">
            <div class="row g-4">
                <div class="col-xl-4"><div class="card form-card"><div class="card-header bg-transparent fw-semibold"><i class="bi bi-plus-circle me-2"></i><span id="titreCategorie">Nouvelle catégorie</span></div><div class="card-body">
                    <form method="POST" action="<?= APP_URL ?>/admin-finance/categorie-enregistrer" id="formCategorie"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><input type="hidden" name="id" id="categorieId">
                        <div class="mb-3"><label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label><input class="form-control" name="nom" id="categorieNom" maxlength="120" required></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Type de flux</label><select class="form-select" name="type_flux" id="categorieType" required><option value="depense">Dépense</option><option value="revenu">Revenu</option></select></div>
                        <div class="d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg me-1"></i>Enregistrer</button><button type="button" class="btn btn-outline-secondary d-none" id="annulerCategorie">Annuler</button></div>
                    </form>
                </div></div></div>
                <div class="col-xl-8"><div class="card section-card"><div class="card-header bg-transparent fw-semibold">Catégories financières</div><div class="card-body p-0"><div class="table-responsive">
                    <table data-register-table data-register-filter-columns='{"1":"Flux","2":"Statut"}' class="table table-sm table-hover mb-0"><thead class="table-dark"><tr><th>Nom</th><th>Flux</th><th>Statut</th><th class="text-end">Actions</th></tr></thead><tbody>
                    <?php foreach ($categories as $categorie): ?><tr class="<?= $categorie['actif'] ? '' : 'table-secondary' ?>" data-register-state="<?= $categorie['actif'] ? 'actif' : 'inactif' ?>" data-register-complete="false"><td class="fw-semibold"><?= e($categorie['nom']) ?></td><td><span class="badge bg-<?= $categorie['type_flux'] === 'revenu' ? 'success' : 'danger' ?>"><?= e(ucfirst($categorie['type_flux'])) ?></span></td><td data-register-state="<?= $categorie['actif'] ? 'actif' : 'inactif' ?>"><span class="badge bg-<?= $categorie['actif'] ? 'success' : 'secondary' ?>"><?= $categorie['actif'] ? 'Active' : 'Inactive' ?></span></td><td class="text-end"><div class="d-inline-flex gap-1">
                        <button type="button" class="btn btn-outline-primary btn-sm modifier-categorie" data-id="<?= (int)$categorie['id'] ?>" data-nom="<?= e($categorie['nom']) ?>" data-type="<?= e($categorie['type_flux']) ?>"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="<?= APP_URL ?>/admin-finance/categorie-toggle/<?= (int)$categorie['id'] ?>" onsubmit="return confirm('Confirmer le changement de statut de cette catégorie ?')"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>"><button class="btn btn-outline-<?= $categorie['actif'] ? 'danger' : 'success' ?> btn-sm"><i class="bi bi-<?= $categorie['actif'] ? 'slash-circle' : 'check-circle' ?>"></i></button></form>
                    </div></td></tr><?php endforeach; ?>
                    </tbody></table>
                </div></div></div></div>
            </div>
        </div>

        <div class="tab-pane fade" id="regles">
            <div class="row justify-content-center"><div class="col-xl-8"><div class="card form-card"><div class="card-header bg-transparent fw-semibold"><i class="bi bi-shield-check me-2"></i>Règles de trésorerie</div><div class="card-body p-4">
                <form method="POST" action="<?= APP_URL ?>/admin-finance/parametres-enregistrer"><input type="hidden" name="csrf_token" value="<?= genererTokenCSRF() ?>">
                    <div class="row g-4">
                        <div class="col-md-6"><label class="form-label fw-semibold">Plafond de caisse (USD)</label><input type="number" min="0.01" step="0.01" class="form-control" name="cash_ceiling_usd" value="<?= e($parametres['cash_ceiling_usd']) ?>" required><div class="form-text">Déclenche l’alerte de dépassement de caisse.</div></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Seuil de validation Direction (USD)</label><input type="number" min="0.01" step="0.01" class="form-control" name="direction_approval_threshold_usd" value="<?= e($parametres['direction_approval_threshold_usd']) ?>" required><div class="form-text">À partir de ce montant, les validations DF et DG sont requises.</div></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Taux de change par défaut (CDF pour 1 USD)</label><input type="number" min="0.01" step="0.01" class="form-control" name="default_exchange_rate_cdf_usd" value="<?= e($parametres['default_exchange_rate_cdf_usd']) ?>" required></div>
                    </div><hr class="my-4"><div class="text-end"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Enregistrer les règles</button></div>
                </form>
            </div></div></div></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const resetCompte = () => { document.getElementById('formCompte').reset(); document.getElementById('compteId').value = ''; document.getElementById('titreCompte').textContent = 'Nouveau compte'; document.getElementById('annulerCompte').classList.add('d-none'); };
    document.querySelectorAll('.modifier-compte').forEach(btn => btn.addEventListener('click', () => { document.getElementById('compteId').value=btn.dataset.id; document.getElementById('compteNom').value=btn.dataset.nom; document.getElementById('compteType').value=btn.dataset.type; document.getElementById('compteDevise').value=btn.dataset.devise; document.getElementById('compteReference').value=btn.dataset.reference; document.getElementById('titreCompte').textContent='Modifier le compte'; document.getElementById('annulerCompte').classList.remove('d-none'); window.scrollTo({top:0,behavior:'smooth'}); }));
    document.getElementById('annulerCompte').addEventListener('click', resetCompte);
    const resetCategorie = () => { document.getElementById('formCategorie').reset(); document.getElementById('categorieId').value=''; document.getElementById('titreCategorie').textContent='Nouvelle catégorie'; document.getElementById('annulerCategorie').classList.add('d-none'); };
    document.querySelectorAll('.modifier-categorie').forEach(btn => btn.addEventListener('click', () => { document.getElementById('categorieId').value=btn.dataset.id; document.getElementById('categorieNom').value=btn.dataset.nom; document.getElementById('categorieType').value=btn.dataset.type; document.getElementById('titreCategorie').textContent='Modifier la catégorie'; document.getElementById('annulerCategorie').classList.remove('d-none'); }));
    document.getElementById('annulerCategorie').addEventListener('click', resetCategorie);
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
