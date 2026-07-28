<?php $pageTitle = 'Utilisateurs — ' . APP_NAME; ?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>

<div class="container-fluid">

    <div class="page-hero">
    <div class="d-flex justify-content-between align-items-center mb-0">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-people me-2 text-primary"></i>Gestion des utilisateurs</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard">Accueil</a></li>
                    <li class="breadcrumb-item active">Utilisateurs</li>
                </ol>
            </nav>
        </div>
        <a href="<?= APP_URL ?>/users/ajouter" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>Ajouter un utilisateur
        </a>
    </div>
    </div>

    <?php afficherFlash(); ?>

    <div class="card section-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tableUsers" class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Service</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Créé le</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr class="<?= $u['actif'] ? '' : 'table-secondary' ?>">
                                <td><?= $u['id'] ?></td>
                                <td class="fw-semibold"><?= e($u['nom']) ?></td>
                                <td><a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a></td>
                                <td>
                                    <?php
                                    switch ($u['role']) {
                                        case 'admin':
                                            $badgeRole = 'danger';
                                            break;
                                        case 'direction':
                                        case 'directeur_general':
                                            $badgeRole = 'dark';
                                            break;
                                        case 'directeur_financier':
                                            $badgeRole = 'primary';
                                            break;
                                        case 'comptable_senior':
                                            $badgeRole = 'success';
                                            break;
                                        case 'tresorier':
                                            $badgeRole = 'info';
                                            break;
                                        case 'auditeur_interne':
                                            $badgeRole = 'secondary';
                                            break;
                                        case 'chef_service':
                                            $badgeRole = 'warning';
                                            break;
                                        case 'reception':
                                            $badgeRole = 'info';
                                            break;
                                        default:
                                            $badgeRole = 'secondary';
                                    }
                                    ?>
                                    <span class="badge bg-<?= $badgeRole ?>"><?= e(libelleRole($u['role'])) ?></span>
                                </td>
                                <td><?= e($u['service'] ?? '—') ?></td>
                                <td><?= e($u['telephone'] ?? '—') ?></td>
                                <td>
                                    <?php if ($u['actif']): ?>
                                        <span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate(substr($u['created_at'], 0, 10)) ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= APP_URL ?>/users/modifier/<?= $u['id'] ?>" class="btn btn-outline-primary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="<?= APP_URL ?>/users/toggle/<?= $u['id'] ?>"
                                           class="btn btn-outline-<?= $u['actif'] ? 'danger' : 'success' ?>"
                                           title="<?= $u['actif'] ? 'Désactiver' : 'Activer' ?>"
                                           onclick="return confirm('Confirmer cette action ?')">
                                            <i class="bi bi-<?= $u['actif'] ? 'slash-circle' : 'check-circle' ?>"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#tableUsers').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json'
        },
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: 8 }
        ]
    });
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
