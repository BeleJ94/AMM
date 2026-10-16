<?php $pageTitle = 'Liste des courriers — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid courrier-registre">

    <!-- En-tête -->
    <div class="page-hero">
    <div class="d-flex justify-content-between align-items-center mb-0">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-envelope me-2 text-primary"></i>Courriers</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard">Accueil</a></li>
                    <li class="breadcrumb-item active">Courriers</li>
                </ol>
            </nav>
        </div>
        <?php if (in_array($_SESSION['user_role'], ['reception', 'admin', 'chef_service'])): ?>
            <a href="<?= APP_URL ?>/courrier/ajouter" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nouveau courrier
            </a>
        <?php endif; ?>
    </div>
    </div>

    <?php afficherFlash(); ?>

    <!-- =========================================================
         TABLEAU DES COURRIERS
         ========================================================= -->
    <div class="card section-card">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi bi-list-ul me-2"></i><span id="courriersTotal"><?= $total ?></span> courrier(s)
            </span>
            <a id="exportCourriers" href="<?= APP_URL ?>/courrier/liste?export=csv&<?= http_build_query(array_filter($filtres)) ?>"
               class="btn btn-sm btn-outline-success">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
        <div class="registre-toolbar px-3 py-2 border-bottom">
            <form id="filtresCourriers" method="GET" action="<?= APP_URL ?>/courrier/liste">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-5 col-xl-4">
                        <label class="form-label" for="filtre-recherche">Recherche</label>
                        <input type="text" class="form-control form-control-sm" id="filtre-recherche" name="recherche"
                               placeholder="Numéro, référence, expéditeur, objet..."
                               value="<?= e($filtres['recherche']) ?>">
                    </div>
                    <div class="col-auto d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Rechercher</button>
                        <button id="plusFiltresCourriers" type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#filtresCourriersAvances" aria-expanded="false" aria-controls="filtresCourriersAvances">
                            <i class="bi bi-sliders me-1"></i>Plus de filtres <span id="nombreFiltresAvances" class="badge bg-primary ms-1 d-none"></span>
                        </button>
                        <a href="<?= APP_URL ?>/courrier/liste" class="btn btn-sm btn-link text-secondary" title="Réinitialiser les filtres" aria-label="Réinitialiser les filtres"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </div>
                <div id="filtresCourriersAvances" class="collapse">
                    <div class="row g-2 align-items-end pt-3 mt-2 border-top">
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="filtre-statut">État du courrier</label>
                        <select class="form-select form-select-sm" id="filtre-statut" name="statut">
                            <option value="">Tous les états</option>
                            <?php foreach (['Recu' => 'Reçu', 'Assigne' => 'Assigné', 'Accuse' => 'Accusé', 'En_cours' => 'En cours', 'Termine' => 'Terminé', 'Clos' => 'Clos', 'Archive' => 'Archivé', 'Overdue' => 'En retard'] as $s => $libelle): ?>
                                <option value="<?= $s ?>" <?= $filtres['statut'] === $s ? 'selected' : '' ?>><?= e($libelle) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="filtre-urgence">Urgence</label>
                        <select class="form-select form-select-sm" id="filtre-urgence" name="urgence">
                            <option value="">Toutes urgences</option>
                            <?php foreach (['basse','moyenne','haute'] as $u): ?>
                                <option value="<?= $u ?>" <?= $filtres['urgence'] === $u ? 'selected' : '' ?>><?= ucfirst($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="filtre-date_debut">Réception du</label>
                        <input type="date" class="form-control form-control-sm" id="filtre-date_debut" name="date_debut"
                               value="<?= e($filtres['date_debut']) ?>" title="Date début">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="filtre-date_fin">Au</label>
                        <input type="date" class="form-control form-control-sm" id="filtre-date_fin" name="date_fin"
                               value="<?= e($filtres['date_fin']) ?>" title="Date fin">
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <label class="form-label" for="filtre-service">Service destinataire</label>
                        <select id="filtre-service" class="form-select form-select-sm" name="service">
                            <option value="">Tous les services</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= e($service) ?>" <?= $filtres['service'] === $service ? 'selected' : '' ?>><?= e($service) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body p-0">

            <div class="table-responsive">
                <table id="tableCourriers" class="table table-sm table-hover align-middle compact mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Numéro</th>
                            <th>Référence</th>
                            <th>Expéditeur</th>
                            <th>Objet</th>
                            <th>Urgence</th>
                            <th>Statut</th>
                            <th>Réception</th>
                            <th>Échéance</th>
                            <th>Service</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<style>
.courrier-registre .registre-toolbar .form-label { font-size: .72rem; font-weight: 600; color: #64748b; margin-bottom: .2rem; }
.courrier-registre #tableCourriers { font-size: .79rem; }
.courrier-registre #tableCourriers th { white-space: nowrap; font-size: .7rem; color: #64748b; }
.courrier-registre #tableCourriers td, .courrier-registre #tableCourriers th { padding: .45rem .6rem; vertical-align: middle; }
.courrier-registre #tableCourriers td:nth-child(1), .courrier-registre #tableCourriers td:nth-child(7), .courrier-registre #tableCourriers td:nth-child(8), .courrier-registre #tableCourriers td:last-child { white-space: nowrap; }
.courrier-registre #tableCourriers .btn { padding: .15rem .35rem; font-size: .75rem; }
.courrier-registre .dataTables_wrapper { padding: .75rem; font-size: .8rem; }
.courrier-registre .dataTables_wrapper .row { margin: 0; }
</style>
<script>
$(function() {
    const form = document.getElementById('filtresCourriers');
    const advanced = document.getElementById('filtresCourriersAvances');
    const toggle = document.getElementById('plusFiltresCourriers');
    let rowMetadata = [];
    function updateFilterCount() {
        const count = Array.from(advanced.querySelectorAll('input, select')).filter(function(field) { return field.value !== ''; }).length;
        const badge = document.getElementById('nombreFiltresAvances');
        badge.textContent = count;
        badge.classList.toggle('d-none', count === 0);
        toggle.classList.toggle('border-primary', count > 0);
    }
    updateFilterCount();
    advanced.addEventListener('change', updateFilterCount);
    const table = $('#tableCourriers').DataTable({
        serverSide: true, processing: true, searching: false,
        pageLength: 15, lengthMenu: [15, 25, 50, 100],
        order: [], scrollX: true, autoWidth: false,
        dom: '<"row align-items-center mb-2"<"col-12"l>>rt<"row align-items-center mt-2"<"col-md-5"i><"col-md-7"p>>',
        ajax: {
            url: <?= json_encode(APP_URL . '/courrier/liste', JSON_HEX_TAG | JSON_HEX_AMP) ?>,
            data: function(data) { new FormData(form).forEach(function(value, key) { data[key] = value; }); },
            dataSrc: function(response) {
                document.getElementById('courriersTotal').textContent = response.recordsFiltered;
                const body = document.createElement('tbody');
                body.innerHTML = response.html;
                rowMetadata = Array.from(body.rows, function(row) {
                    return { state: row.dataset.registerState || '', complete: row.dataset.registerComplete || 'false' };
                });
                return Array.from(body.rows, function(row) { return Array.from(row.cells, function(cell) { return cell.innerHTML; }); });
            }
        },
        language: {
            url: '', processing: 'Chargement…', lengthMenu: '_MENU_ lignes par page',
            info: '_START_–_END_ sur _TOTAL_ courriers', infoEmpty: 'Aucun courrier',
            infoFiltered: '(sur _MAX_ au total)', emptyTable: 'Aucun courrier ne correspond aux filtres.',
            zeroRecords: 'Aucun courrier ne correspond aux filtres.',
            paginate: { first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent' }
        },
        createdRow: function(row, data, index) {
            const metadata = rowMetadata[index] || {};
            row.dataset.registerState = metadata.state || '';
            row.dataset.registerComplete = metadata.complete || 'false';
            row.cells[5].dataset.registerState = metadata.state || '';
        },
        columnDefs: [{ orderable: false, targets: 9 }]
    });
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        updateFilterCount();
        const params = new URLSearchParams(new FormData(form));
        history.replaceState(null, '', form.action + '?' + params.toString());
        document.getElementById('exportCourriers').href = form.action + '?export=csv&' + params.toString();
        table.ajax.reload();
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
